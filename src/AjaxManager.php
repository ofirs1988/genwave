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
    private $pluginsHandler;

    public function __construct()
    {
        // Initialize specialized handlers
        $this->generationHandler = new GenerationHandler();
        $this->productHandler = new ProductHandler();
        $this->pluginsHandler = new PluginsHandler();

        add_action('wp_ajax_genwave_verify_login', [VerifyLoginController::class , 'verifyLogin']);
        // No nopriv hook (F3): the auto-login URL carries the owner's SaaS session
        // and must never be issued to an unauthenticated caller.

        // Note: Languages, Credits, and PostTypeController features removed
        // Free plugin is now fully independent

        // Product/Post handlers - delegated to ProductHandler
        add_action('wp_ajax_genwave_get_all_products', [$this->productHandler, 'getAllProducts']);
        add_action('wp_ajax_genwave_get_all_posts', [$this->productHandler, 'getAllPosts']);
        // Logged-in only (1.2.2): the handler checks edit_post for the post asked for.
        add_action('wp_ajax_genwave_get_post_data', [$this->productHandler, 'handle_get_post_data']);

        add_action('wp_ajax_genwave_refresh_credits', [$this, 'handle_refresh_credits']);
        add_action('wp_ajax_genwave_refresh_tokens', [$this, 'handle_refresh_credits']); // backward compat
        add_action('wp_ajax_genwave_disconnect_account', [$this, 'handle_disconnect_account']);

        // Dashboard stats endpoint
        add_action('wp_ajax_genwave_get_dashboard_stats', [$this, 'handle_get_dashboard_stats']);

        // Plugin marketplace (install paid Genwave plugins from inside WP)
        add_action('wp_ajax_genwave_list_plugins', [$this->pluginsHandler, 'handle_list_plugins']);
        // genwave_install_plugin is gone (1.3.0): Genwave plugins are installed from
        // the customer's Genwave account (Core\SiteCommands), see Links::install().

        // REMOVED (1.2.2): genwave_polling_results and genwave_poll_proxy (also on
        // nopriv). Only the unused streaming client called them.
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
        // capability check a low-privilege user could wipe the site's Genwave
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
            error_log('Genwave API Response Body: ' . substr($body, 0, 1000));
        }
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Failed to decode JSON response', 'body' => $body];
        }

        return $data;
    }

}

