<?php

namespace GenWavePlugin;

if (!defined('ABSPATH')) {
    exit;
}

use GenWavePlugin\Controllers\TokensController;
use GenWavePlugin\Controllers\VerifyLoginController;
use GenWavePlugin\Controllers\WoocommerceController;
use GenWavePlugin\Core\ApiManager;
use GenWavePlugin\Core\Config;
use GenWavePlugin\Handlers\GenerationHandler;
use GenWavePlugin\Handlers\ProductHandler;
use GenWavePlugin\Handlers\PollingHandler;
use GenWavePlugin\Handlers\PluginsHandler;

/**
 * AjaxManager - AJAX request router (REFACTORED)
 *
 * This class now delegates to specialized handlers:
 * - GenerationHandler: Content generation, conversion tracking
 * - ProductHandler: Product/post listing and filtering
 */
class AjaxManager
{
    private $token;
    private $uidd;
    private $generationHandler;
    private $productHandler;
    private $pollingHandler;
    private $pluginsHandler;

    public function __construct()
    {
        // Initialize specialized handlers
        $this->generationHandler = new GenerationHandler();
        $this->productHandler = new ProductHandler();
        $this->pollingHandler = new PollingHandler();
        $this->pluginsHandler = new PluginsHandler();

        add_action('wp_ajax_genwave_verify_login', [VerifyLoginController::class , 'verifyLogin']);
        // No nopriv hook (F3): the auto-login URL carries the owner's SaaS session
        // and must never be issued to an unauthenticated caller.

        // Note: Languages, Credits, and PostTypeController features removed
        // Free plugin is now fully independent

        // Product/Post handlers - delegated to ProductHandler
        add_action('wp_ajax_genwave_get_all_products', [$this->productHandler, 'getAllProducts']);
        add_action('wp_ajax_genwave_get_all_posts', [$this->productHandler, 'getAllPosts']);
        add_action('wp_ajax_genwave_get_post_data', [$this->productHandler, 'handle_get_post_data']);
        add_action('wp_ajax_nopriv_genwave_get_post_data', [$this->productHandler, 'handle_get_post_data']);

        add_action('wp_ajax_genwave_refresh_credits', [$this, 'handle_refresh_credits']);
        add_action('wp_ajax_genwave_refresh_tokens', [$this, 'handle_refresh_credits']); // backward compat
        add_action('wp_ajax_genwave_disconnect_account', [$this, 'handle_disconnect_account']);
        add_action('wp_ajax_genwave_check_license_status', [$this, 'handle_check_license_status']);

        // Dashboard stats endpoint
        add_action('wp_ajax_genwave_get_dashboard_stats', [$this, 'handle_get_dashboard_stats']);

        // Plugin marketplace (install paid GenWave plugins from inside WP)
        add_action('wp_ajax_genwave_list_plugins', [$this->pluginsHandler, 'handle_list_plugins']);
        add_action('wp_ajax_genwave_install_plugin', [$this->pluginsHandler, 'handle_install_plugin']);

        // Polling handlers - delegated to PollingHandler
        add_action('wp_ajax_genwave_polling_results', [$this->pollingHandler, 'handle_ai_polling_results']);
        add_action('wp_ajax_nopriv_genwave_polling_results', [$this->pollingHandler, 'handle_ai_polling_results']);
        add_action('wp_ajax_genwave_poll_proxy', [$this->pollingHandler, 'handle_ai_poll_proxy']);
        add_action('wp_ajax_nopriv_genwave_poll_proxy', [$this->pollingHandler, 'handle_ai_poll_proxy']);
        // REMOVED (F11): the genwave_update_credit_balance / _token_balance actions
        // and their handler are gone. Nothing called them (no JS caller anywhere),
        // they let the client dictate the displayed balance, and were even exposed
        // on nopriv. The authoritative balance is fetched from the server via
        // handle_refresh_credits.

        // Generation handlers - delegated to GenerationHandler
        add_action('wp_ajax_genwave_generate_single', [$this->generationHandler, 'handle_genwave_generate_single']);
        add_action('wp_ajax_genwave_mark_converted', [$this->generationHandler, 'handle_mark_converted']);
        add_action('wp_ajax_genwave_apply_content', [$this, 'handle_apply_content']);

        $this->token = Config::get('token');
        $this->uidd = Config::get('uidd');
    }

    public function handle_refresh_credits()
    {

        if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'refresh_credits_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }
        $response = (new TokensController())->RefreshCredits();

        // Check for error response
        if (is_array($response) && isset($response['error']) && $response['error'] === true) {
            // Only clear credentials when authentication explicitly failed
            // (auth: false = the token was rejected). Transient/network errors
            // report auth: true and must change NOTHING — never wipe credentials
            // and never mark the license expired off a failed balance refresh.
            $auth_valid = isset($response['auth']) && $response['auth'] === true;

            if (!$auth_valid) {
                Config::delete('uidd');
                Config::delete('token');
                Config::delete('domain');
                Config::delete('active');
                Config::delete('plan');
            }

            wp_send_json_error([
                'auth' => $response['auth'] ?? false,
                'url' => $response['url'] ?? '',
                'btn_text' => $response['btn_text'] ?? 'Learn more',
                'message' => $response['message'] ?? 'An error occurred.'
            ]);
        }

        // Extract credit balance from response (support both old and new API response keys)
        $credit_balance = 0;
        if (is_numeric($response)) {
            $credit_balance = $response;
        } elseif (is_array($response)) {
            if (isset($response['credit_balance'])) {
                $credit_balance = $response['credit_balance'];
            } elseif (isset($response['token_balance'])) {
                $credit_balance = $response['token_balance'];
            } elseif (isset($response['credits'])) {
                $credit_balance = $response['credits'];
            } elseif (isset($response['tokens'])) {
                $credit_balance = $response['tokens'];
            } elseif (isset($response['data']['credit_balance'])) {
                $credit_balance = $response['data']['credit_balance'];
            } elseif (isset($response['data']['token_balance'])) {
                $credit_balance = $response['data']['token_balance'];
            }
        }

        // Save credit balance
        Config::set('credits', $credit_balance);

        // Success - clear expired flag if it was set
        Config::set('license_expired', '0');
        wp_send_json_success(['credits' => $credit_balance]);
    }

    public function handle_disconnect_account()
    {
        if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'disconnect_account_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        // Only an administrator may disconnect the site (F8): the nonce is exposed
        // to any admin-area role (incl. contributors on edit screens), so without a
        // capability check a low-privilege user could wipe the site's GenWave
        // credentials and break the agent for everyone.
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $result = \GenWavePlugin\Controllers\DisconnectController::disconnect();

        if ($result['success']) {
            wp_send_json_success([
                'message' => $result['message']
            ]);
        } else {
            wp_send_json_error([
                'message' => $result['message']
            ]);
        }
    }

    /**
     * Handle applying generated content to a post/product
     */
    public function handle_apply_content()
    {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'genwave_generate_nonce')) {
            wp_send_json_error(['message' => 'Security verification failed']);
            return;
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        $field = sanitize_text_field(wp_unslash($_POST['field'] ?? ''));
        $content = wp_kses_post(wp_unslash($_POST['content'] ?? ''));

        if (!$post_id) {
            wp_send_json_error(['message' => 'Invalid post ID']);
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(['message' => 'Permission denied']);
            return;
        }

        if (empty($content)) {
            wp_send_json_error(['message' => 'No content to apply']);
            return;
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(['message' => 'Post not found']);
            return;
        }

        $update_data = ['ID' => $post_id];

        switch ($field) {
            case 'title':
                $update_data['post_title'] = sanitize_text_field($content);
                break;

            case 'description':
                $update_data['post_content'] = $content;
                break;

            case 'shortDescription':
                $update_data['post_excerpt'] = $content;
                // For WooCommerce products, also update the short_description meta
                if ($post->post_type === 'product') {
                    update_post_meta($post_id, '_product_short_description', $content);
                }
                break;

            default:
                wp_send_json_error(['message' => 'Invalid field type']);
                return;
        }

        $result = wp_update_post($update_data, true);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => 'Failed to update: ' . $result->get_error_message()]);
            return;
        }

        wp_send_json_success([
            'message' => 'Content applied successfully',
            'post_id' => $post_id,
            'field' => $field
        ]);
    }

    public function handle_check_license_status()
    {
        if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'genwave_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }

        $license_key = Config::get('license_key');
        if (empty($license_key)) {
            wp_send_json_error(['message' => 'No license key found']);
        }

        // Call Python API to check license status
        $api_manager = new ApiManager();
        $response = $api_manager->postToLiteLLM('check-license-status', [
            'license_key' => $license_key,
        ], Config::get('token'), Config::get('uidd'));

        // Check for error
        if (isset($response['error']) && $response['error'] === true) {
            wp_send_json_error([
                'message' => $response['message'] ?? 'Failed to check license status'
            ]);
        }

        // Get the data from wrapped response (postToLiteLLM wraps response in 'data')
        $data = $response['data'] ?? $response;

        // Check if expired
        $is_expired = isset($data['expired']) ? $data['expired'] : true;

        // Update local config
        Config::set('license_expired', $is_expired ? '1' : '0');
        if (isset($data['expiration_date'])) {
            Config::set('expiration_date', $data['expiration_date']);
        }

        wp_send_json_success([
            'expired' => $is_expired,
            'expiration_date' => $data['expiration_date'] ?? null,
            'message' => $is_expired ? 'License is still expired' : 'License is active'
        ]);
    }

    /**
     * Handle dashboard stats AJAX request
     */
    public function handle_get_dashboard_stats()
    {
        // Verify nonce
        if (!isset($_POST['security']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['security'])), 'genwave_nonce')) {
            wp_send_json_error(['message' => 'Invalid nonce']);
            return;
        }

        // Check user capability
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
            return;
        }

        global $wpdb;

        // Get credit balance from the SHARED option (synced with the agent),
        // falling back to the anchor's own copy.
        $credit_balance = floatval(get_option('aiaw_credits', Config::get('credits') ?? 0));

        // Get total requests count
        $requests_table = $wpdb->prefix . 'gen_requests';
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name built from $wpdb->prefix (not user input); prepare() does not support identifier placeholders pre-WP 6.2
        $total_requests = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$requests_table} WHERE deleted_at IS NULL"
        );
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        wp_send_json_success([
            'credit_balance' => $credit_balance,
            'total_requests' => $total_requests
        ]);
    }

    public function getRecentProducts()
    {
        $args = [
            'post_type' => 'product',
            'posts_per_page' => 5,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        $query = new \WP_Query($args);
        $products = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                global $product; 

                $products[] = [
                    'id' => $product->get_id(),
                    'name' => $product->get_name(),
                    'price' => $product->get_price(),
                    'image' => wp_get_attachment_url($product->get_image_id()), 
                    'stock_status' => $product->get_stock_status(),
                ];
            }
            wp_reset_postdata(); 
        }

        wp_send_json_success($products);
    }

    public function getAllProducts()
    {
        global $wpdb;

        // SECURITY: Verify user capabilities (admin only)
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions', 403);
            return;
        }

        // Query to fetch all products
        $args = [
            'post_type' => 'product',
            'posts_per_page' => -1, // Fetch all products
        ];

        $query = new \WP_Query($args);
        $products = [];
        $categories = []; // Collect all categories

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                global $product;

                $post_id = $product->get_id();
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for plugin data
                $is_converted = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT is_converted
                     FROM wp_gen_requests_posts
                     WHERE post_id = %d",
                        $post_id
                    )
                );

                // Skip products that are already converted
                if ($is_converted === '1') {
                    continue;
                }

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for plugin data
                $is_generated = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*)
                     FROM wp_gen_requests_posts
                     WHERE post_id = %d AND get_response = 1",
                        $post_id
                    )
                );

                $image_id = $product->get_image_id(); // Get image ID
                $image_url = wp_get_attachment_url($image_id); // Get image URL
                $description = $product->get_description(); // Get product description

                // Get categories for the product
                $product_categories = wp_get_post_terms($post_id, 'product_cat', ['fields' => 'names']);
                $categories = array_merge($categories, $product_categories);

                $products[] = [
                    'id' => $post_id,
                    'name' => $product->get_name(),
                    'price' => $product->get_price(),
                    'stock_status' => $product->get_stock_status(),
                    'image' => $image_url, // Include image URL
                    'description' => $description, // Include description
                    'categories' => $product_categories, // Include categories
                    'generated' => $is_generated > 0,
                ];
            }
            wp_reset_postdata(); // Reset post data
        }

        // Remove duplicate categories and sort them
        $categories = array_unique($categories);
        sort($categories);

        wp_send_json_success([
            'products' => $products,
            'categories' => $categories,
        ]); // Return as JSON response
    }

    public function getAllPosts()
    {
        global $wpdb;

        // SECURITY: Verify user capabilities (admin only)
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions', 403);
            return;
        }

        // Query to fetch all posts
        $args = [
            'post_type' => 'post',
            'posts_per_page' => -1, // Fetch all posts
            'post_status' => 'publish'
        ];

        $query = new \WP_Query($args);
        $posts = [];
        $categories = []; // Collect all categories
        $authors = []; // Collect all authors

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                global $post;

                $post_id = $post->ID;
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for plugin data
                $is_converted = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT is_converted
                     FROM wp_gen_requests_posts
                     WHERE post_id = %d",
                        $post_id
                    )
                );

                // Skip posts that are already converted
                if ($is_converted === '1') {
                    continue;
                }

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for plugin data
                $is_generated = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*)
                     FROM wp_gen_requests_posts
                     WHERE post_id = %d AND get_response = 1",
                        $post_id
                    )
                );

                $featured_image_id = get_post_thumbnail_id($post_id);
                $featured_image_url = wp_get_attachment_url($featured_image_id);
                $excerpt = get_the_excerpt($post_id);
                $content = get_the_content('', false, $post_id);
                
                // Get author info
                $author_id = $post->post_author;
                $author_name = get_the_author_meta('display_name', $author_id);
                $authors[$author_id] = $author_name;

                // Get categories for the post
                $post_categories = wp_get_post_terms($post_id, 'category', ['fields' => 'names']);
                $categories = array_merge($categories, $post_categories);

                $posts[] = [
                    'id' => $post_id,
                    'title' => get_the_title($post_id),
                    'slug' => $post->post_name,
                    'status' => $post->post_status,
                    'author_id' => $author_id,
                    'author' => $author_name,
                    'date' => get_the_date('Y-m-d H:i:s', $post_id),
                    'featured_image' => $featured_image_url,
                    'excerpt' => $excerpt,
                    'content' => $content,
                    'categories' => $post_categories,
                    'generated' => $is_generated > 0,
                ];
            }
            wp_reset_postdata(); // Reset post data
        }

        // Remove duplicate categories and sort them
        $categories = array_unique($categories);
        sort($categories);

        // Convert authors array to indexed array for frontend
        $authors_list = [];
        foreach ($authors as $author_id => $author_name) {
            $authors_list[] = [
                'id' => $author_id,
                'name' => $author_name
            ];
        }

        wp_send_json_success([
            'posts' => $posts,
            'categories' => $categories,
            'authors' => $authors_list,
        ]); // Return as JSON response
    }


    public function handleAdminAction()
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized', 403);
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Admin action with capability check
        if (!isset($_POST['data'])) {
            wp_send_json_error('Missing data parameter', 400);
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Admin action with capability check
        $data = sanitize_text_field(wp_unslash($_POST['data']));
        update_option('genwave_admin_option', $data);

    }

    public function handleSiteAction()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public action
        if (!isset($_POST['data'])) {
            wp_send_json_error('Missing data parameter', 400);
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Public action
        $data = sanitize_text_field(wp_unslash($_POST['data']));

        $result = $this->processData($data);

        wp_send_json_success(['result' => $result]);
    }

    private function processData($data)
    {
        return strtoupper($data); 
    }

    public function handleResponse($response) {
        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body($response);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
            error_log('GenWave API Response Body: ' . substr($body, 0, 1000));
        }
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Failed to decode JSON response', 'body' => $body];
        }

        return $data;
    }

    /**
     * Handle AI polling results from JavaScript (Free plugin version)
     */
    public function handle_ai_polling_results()
    {
        try {
            // SECURITY: Verify nonce for CSRF protection FIRST (accept both admin and frontend nonces)
            $nonce = isset($_POST['security']) ? sanitize_text_field(wp_unslash($_POST['security'])) : (isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '');
            $nonce_check = wp_verify_nonce($nonce, 'genwave_admin_nonce') ||
                          wp_verify_nonce($nonce, 'genwave_frontend_nonce') ||
                          wp_verify_nonce($nonce, 'genwave_polling_nonce');

            if (!$nonce_check) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log('AI Polling AJAX (Free Plugin): Invalid nonce');
                }
                wp_send_json_error('Security verification failed');
                return;
            }

            // VALIDATION: Check if response data is provided
            if (!isset($_POST['response']) || empty($_POST['response'])) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log('AI Polling AJAX (Free Plugin): No response data provided');
                }
                wp_send_json_error('No response data provided');
                return;
            }

            $responseData = sanitize_text_field(wp_unslash($_POST['response']));

            // VALIDATION: Check response size (prevent memory issues)
            if (strlen($responseData) > 5000000) { // 5MB limit
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log('AI Polling AJAX (Free Plugin): Response data too large');
                }
                wp_send_json_error('Response data exceeds maximum size');
                return;
            }

            // VALIDATION: Decode JSON and validate structure
            if (is_string($responseData)) {
                $decodedData = json_decode($responseData, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                        error_log('AI Polling AJAX (Free Plugin): Invalid JSON - ' . json_last_error_msg());
                    }
                    wp_send_json_error('Invalid JSON format: ' . json_last_error_msg());
                    return;
                }
                $responseData = $decodedData;
            }

            // VALIDATION: Ensure responseData is an array
            if (!is_array($responseData)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log('AI Polling AJAX (Free Plugin): Response data is not an array');
                }
                wp_send_json_error('Response data must be an array');
                return;
            }

            // VALIDATION: Check if array is empty
            if (empty($responseData)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log('AI Polling AJAX (Free Plugin): Empty response array');
                }
                wp_send_json_error('Response data is empty');
                return;
            }

            // VALIDATION: Limit number of posts to prevent DoS
            if (count($responseData) > 100) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log('AI Polling AJAX (Free Plugin): Too many posts in response (' . count($responseData) . ')');
                }
                wp_send_json_error('Cannot process more than 100 posts at once');
                return;
            }

            // Log the incoming data
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Debug mode only
                error_log('AI Polling Results (Free Plugin) received: ' . print_r($responseData, true));
            }

            // Also log to debug.log for easier viewing

            // Process each post in the response
            $processedPosts = [];
            $errors = [];

            foreach ($responseData as $postId => $content) {
                // VALIDATION: Validate post ID
                $postId = intval($postId);
                if ($postId < 1) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                        error_log("AI Polling (Free Plugin): Invalid post ID: {$postId}");
                    }
                    $errors[] = "Invalid post ID: {$postId}";
                    continue;
                }
                try {
                    $result = $this->process_post_response($postId, $content);
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                        error_log("AI Polling (Free Plugin): Post {$postId} result: " . wp_json_encode($result));
                    }

                    if ($result['success']) {
                        $processedPosts[] = $postId;
                    } else {
                        $errors[] = "Post {$postId}: " . $result['message'];
                    }
                } catch (Exception $e) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                        error_log("AI Polling (Free Plugin): Exception for post {$postId}: " . $e->getMessage());
                    }
                    $errors[] = "Post {$postId}: " . $e->getMessage();
                }
            }

            // Return response
            $response = [
                'success' => true,
                'message' => 'AI responses processed (Free Plugin)',
                'processed_posts' => $processedPosts,
                'processed_count' => count($processedPosts),
                'total_count' => count($responseData)
            ];

            if (!empty($errors)) {
                $response['errors'] = $errors;
                $response['has_errors'] = true;
            }

            wp_send_json_success($response);

        } catch (Exception $e) {
            wp_send_json_error('Failed to process AI responses: ' . $e->getMessage());
        }
    }

    /**
     * Process individual post response (Free plugin version)
     */
    private function process_post_response($postId, $content)
    {
        global $wpdb;

        try {
            // Validate post exists
            $post = get_post($postId);
            if (!$post) {
                return [
                    'success' => false,
                ];
            }

            // Get request_id from content if available
            $requestId = $content['request_id'] ?? null;

            // Find corresponding WordPress request record
            $requestsPostsTable = $wpdb->prefix . 'gen_requests_posts';
            $requestPost = null;
            
            if ($requestId) {
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names are safe (uses $wpdb->prefix), custom table query
                $requestPost = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$requestsPostsTable} WHERE post_id = %d AND request_id IN (
                        SELECT id FROM {$wpdb->prefix}gen_requests WHERE id = %d
                    ) ORDER BY id DESC LIMIT 1",
                    $postId,
                    $requestId
                ));
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            }

            if (!$requestPost) {
                // Fallback: find by post_id
                // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is safe (uses $wpdb->prefix), custom table query
                $requestPost = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$requestsPostsTable} WHERE post_id = %d ORDER BY id DESC LIMIT 1",
                    $postId
                ));
                // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            }

            if (!$requestPost) {
                return [
                    'success' => false,
                ];
            }

            $contentToStore = $content;
            unset($contentToStore['request_id']);

            // Update the request post with received content
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update for plugin data
            $updateResult = $wpdb->update(
                $requestsPostsTable,
                [
                    'additional_content' => json_encode($contentToStore, JSON_UNESCAPED_UNICODE),
                    'get_response' => 1,
                ],
                ['id' => $requestPost->id]
            );

            if ($updateResult === false) {
                return [
                    'success' => false,
                    'message' => 'Failed to update request post in database'
                ];
            }

            // Update main request progress if we have request_id
            if ($requestId) {
            }

            return [
                'success' => true,
                'request_post_id' => $requestPost->id
            ];

        } catch (Exception $e) {
            
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Update request status (Free plugin version)
     */
    private function update_request_status($requestId, $status, $progress = null)
    {
        global $wpdb;

        $requestsTable = $wpdb->prefix . 'gen_requests';

        $updateData = [
            'status' => $status,
        ];

        if ($progress !== null) {
            $updateData['progress'] = $progress;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update for plugin data
        $wpdb->update(
            $requestsTable,
            $updateData,
            ['id' => $requestId]
        );
    }
    
    /**
     * Handle API proxy requests to solve CORS issues
     */
    public function handle_ai_poll_proxy() {
        // SECURITY: Verify nonce FIRST - accept both admin and frontend nonces
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        $nonce_check = wp_verify_nonce($nonce, 'genwave_admin_nonce') ||
                      wp_verify_nonce($nonce, 'genwave_frontend_nonce');

        if (!$nonce_check) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('GenWave Poll Proxy: Invalid nonce');
            }
            wp_send_json_error('Invalid nonce', 403);
            return;
        }

        // Debug logging AFTER nonce verification
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
            error_log('AI Poll Proxy: Function called');
        }

        // Check if the action matches
        $action = isset($_POST['action']) ? sanitize_text_field(wp_unslash($_POST['action'])) : '';
        if ($action !== 'ai_poll_proxy') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('AI Poll Proxy: Wrong action received: ' . $action);
            }
            wp_send_json_error('Wrong action', 400);
            return;
        }

        $domain = isset($_POST['domain']) ? sanitize_text_field(wp_unslash($_POST['domain'])) : '';
        if (empty($domain)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('AI Poll Proxy (AjaxManager): Domain parameter missing');
            }
            wp_send_json_error('Domain parameter required', 400);
            return;
        }


        try {
            // Get API URL based on environment
            $apiUrl = $this->getApiUrl();
            $pollUrl = $apiUrl . '/poll-results/' . $domain;

            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('AI Poll Proxy (AjaxManager): Making request to: ' . $pollUrl);
            }

            // SECURITY: Only disable SSL verification in localhost development environment
            $is_localhost = (strpos($apiUrl, 'localhost') !== false ||
                            strpos($apiUrl, '127.0.0.1') !== false ||
                            strpos($apiUrl, '.local') !== false);

            // Make the API request
            $response = wp_remote_get($pollUrl, [
                'timeout' => 30,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'sslverify' => !$is_localhost // Enable SSL verification in production
            ]);
            
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log('AI Poll Proxy (AjaxManager): WP Error: ' . $error_message);
                }
                wp_send_json_error('API request failed: ' . $error_message, 500);
                return;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('AI Poll Proxy (AjaxManager): API response status: ' . $status_code);
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('AI Poll Proxy (AjaxManager): API response body: ' . substr($body, 0, 500));
            }

            if ($status_code !== 200) {
                wp_send_json_error('API returned status: ' . $status_code, $status_code);
                return;
            }

            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log('AI Poll Proxy (AjaxManager): JSON decode error: ' . json_last_error_msg());
                }
                wp_send_json_error('Invalid JSON response from API', 500);
                return;
            }

            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('AI Poll Proxy (AjaxManager): Success, returning data');
            }

            // Return the API response
            wp_send_json($data);

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('AI Poll Proxy (AjaxManager): Exception: ' . $e->getMessage());
            }
            wp_send_json_error('Proxy error: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get API URL based on environment
     */
    private function getApiUrl() {
        // Allow override via constant for development
        if (defined('GENWAVE_API_URL')) {
            return GENWAVE_API_URL;
        }

        return 'https://account.genwave.ai/api';
    }
    

    /**
     * Handle saving streaming results to database
     */
    // Old handle_save_streaming_results method removed - now using StreamingController

    /**
     * Handle get post data AJAX request
     */
    public function handle_get_post_data() {
        try {
            // SECURITY: Verify nonce for CSRF protection (REQUIRED)
            $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
            if (empty($nonce) || !wp_verify_nonce($nonce, 'genwave_nonce')) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log('Get Post Data AJAX: Invalid or missing nonce');
                }
                wp_send_json_error(__('Security verification failed', 'gen-wave'), 403);
                return;
            }

            // Get post ID from request
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

            if (!$post_id) {
                wp_send_json_error('Post ID is required');
                return;
            }

            // Get the post
            $post = get_post($post_id);

            if (!$post) {
                return;
            }

            // Get post data
            $post_data = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'content' => $post->post_content,
                'excerpt' => $post->post_excerpt,
                'type' => $post->post_type,
                'status' => $post->post_status
            ];

            // If it's a product, get additional WooCommerce data
            if ($post->post_type === 'product' && function_exists('wc_get_product')) {
                $product = wc_get_product($post_id);
                if ($product) {
                    $post_data['short_description'] = $product->get_short_description();
                    $post_data['description'] = $product->get_description();
                    $post_data['price'] = $product->get_price();
                    $post_data['sku'] = $product->get_sku();
                }
            }

            wp_send_json_success([
                'data' => $post_data,
            ]);

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('Get Post Data AJAX: Exception: ' . $e->getMessage());
            }
            wp_send_json_error('Error retrieving post data: ' . $e->getMessage());
        }
    }


    /**
     * Mark post as converted when user clicks Update button
     */
    public function handle_mark_converted() {
        try {
            // SECURITY: Verify nonce for CSRF protection
            if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'genwave_mark_converted_nonce')) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log('Genwave Mark Converted: Invalid nonce');
                }
                wp_send_json_error(['message' => 'Security verification failed']);
                return;
            }

            global $wpdb;

            // VALIDATION: Get and validate post_request_id
            $post_request_id = intval($_POST['post_request_id'] ?? 0);
            if (!$post_request_id || $post_request_id < 1) {
                wp_send_json_error(['message' => 'Valid Post request ID is required']);
                return;
            }

            $requests_posts_table = $wpdb->prefix . 'gen_requests_posts';

            // VALIDATION: Verify the record exists and get post_id
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is safe (uses $wpdb->prefix), custom table query
            $record = $wpdb->get_row($wpdb->prepare(
                "SELECT id, post_id FROM {$requests_posts_table} WHERE id = %d",
                $post_request_id
            ));
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

            if (!$record) {
                return;
            }

            // VALIDATION: Check if user has permission to edit the associated post
            if (!current_user_can('edit_post', $record->post_id)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log("Genwave: User cannot edit post {$record->post_id} for request {$post_request_id}");
                }
                wp_send_json_error(['message' => 'You do not have permission to update this content']);
                return;
            }

            // Update is_converted = 1 for this specific request
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update for plugin data
            $updated = $wpdb->update(
                $requests_posts_table,
                [
                    'is_converted' => 1,
                    'convert_time' => current_time('mysql'),
                ],
                ['id' => $post_request_id],
                ['%d', '%s'],
                ['%d']
            );

            if ($updated !== false) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log("Genwave: Marked post_request_id {$post_request_id} as converted (is_converted=1)");
                }
                wp_send_json_success(['message' => 'Post marked as converted']);
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log("Genwave: Failed to mark post_request_id {$post_request_id} as converted");
                }
                wp_send_json_error(['message' => 'Failed to update conversion status']);
            }

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('Genwave Mark Converted: Exception: ' . $e->getMessage());
            }
            wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
        }
    }
}

