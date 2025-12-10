<?php

namespace GenWavePlugin\Global;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

class AiRestApi extends WP_REST_Controller
{
    protected static $instance = null; // Instance holder
    protected $namespace = 'gen-wave/v1'; // Namespace definition
    protected $base = 'responses'; // Base route

    public function __construct()
    {
        if (self::$instance) {
            return; // If already instantiated, do nothing
        }
        self::$instance = $this; // Set instance
        add_action('rest_api_init', [$this, 'register_ai_awesome_route']);

        // Flush rewrite rules once after plugin update (temporary)
        if (!get_option('genwave_rest_api_flushed_v2')) {
            add_action('init', function() {
                flush_rewrite_rules();
                update_option('genwave_rest_api_flushed_v2', true);
            });
        }
    }

    /**
     * SECURITY: Check REST API permissions
     * Validates that the request comes from authorized source
     */
    public function check_api_permission($request)
    {
        // Option 1: Check if user is logged in and has edit_posts capability
        if (is_user_logged_in() && current_user_can('edit_posts')) {
            return true;
        }

        // Option 2: Validate API key from headers (for external Laravel backend)
        $api_key = $request->get_header('X-GenWave-API-Key');
        $stored_key = Config::get('license_key'); // Use license key as API key

        if (!empty($api_key) && !empty($stored_key) && hash_equals($stored_key, $api_key)) {
            return true;
        }

        // Option 3: Check for valid uidd in header (from Laravel)
        $uidd_header = $request->get_header('X-GenWave-UIDD');
        $stored_uidd = Config::get('uidd');

        if (!empty($uidd_header) && !empty($stored_uidd) && hash_equals($stored_uidd, $uidd_header)) {
            return true;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
            error_log('REST API Auth: Unauthorized - logged_in: ' . (is_user_logged_in() ? 'yes' : 'no') . ', can_edit: ' . (current_user_can('edit_posts') ? 'yes' : 'no'));
        }
        return new WP_Error('rest_forbidden', __('You do not have permission to access this endpoint', 'gen-wave'), ['status' => 403]);
    }

    /**
     * Check permissions for response-data endpoint (admin only)
     */
    public function check_admin_permission($request)
    {
        // Check if user is logged in and is admin
        if (is_user_logged_in() && current_user_can('manage_options')) {
            return true;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
            error_log('REST API Auth: Unauthorized - logged_in: ' . (is_user_logged_in() ? 'yes' : 'no') . ', is_admin: ' . (current_user_can('manage_options') ? 'yes' : 'no'));
        }
        return new WP_Error('rest_forbidden', __('You do not have permission to access this endpoint', 'gen-wave'), ['status' => 403]);
    }

    public function register_ai_awesome_route()
    {
        register_rest_route($this->namespace, '/' . $this->base, [
            'methods' => 'POST',
            'callback' => [$this, 'handle_post_response'],
            'permission_callback' => [$this, 'check_api_permission'], // SECURITY: Require authentication
        ]);

        register_rest_route($this->namespace, '/response-data', [
            'methods' => 'GET',
            'callback' => [$this, 'getResponseData'],
            'permission_callback' => function() {
                // Allow if user is logged in
                return is_user_logged_in();
            }
        ]);

        register_rest_route($this->namespace, '/converting-products', [
            'methods' => 'GET',
            'callback' => [$this, 'getConvertingProducts'],
            'permission_callback' => [$this, 'check_admin_permission'], // SECURITY: Admin only
        ]);

        // Domain verification endpoint - public access for Laravel to verify plugin is active
        register_rest_route($this->namespace, '/verify-domain', [
            'methods' => 'GET',
            'callback' => [$this, 'verifyDomainEndpoint'],
            'permission_callback' => '__return_true', // Public endpoint
        ]);
    }

    public function handle_post_response(WP_REST_Request $request)
    {
        // Retrieve the data from the request
        $data = $request->get_json_params();
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Debug mode only
            error_log(print_r($data, true));
        }
        if($data){
            if (array_key_exists('response', $data)) {
                // Decode the response from the server
                $response = json_decode($data['response']);
                if($response){
                    global $wpdb;
                    $table_name_posts = $wpdb->prefix . 'gen_requests_posts';

                    foreach ($response as $key => $item) {
                        // Prepare the data
                        $postId = $key; // The key from the object


                        // Convert the object to JSON
                        $additionalContentJson = json_encode($item);

                        // Check if record exists
                        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is safe (uses $wpdb->prefix)
                        $existing = $wpdb->get_var($wpdb->prepare(
                            "SELECT id FROM {$table_name_posts} WHERE post_id = %d AND request_id = %d",
                            $postId,
                            $item->request_id
                        ));
                        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter


                        if ($existing) {
                            // Update existing record
                            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update for plugin data
                            $result = $wpdb->update(
                                $table_name_posts,
                                [
                                    'additional_content' => $additionalContentJson,
                                    'get_response' => 1,
                                    'ai' => 2,
                                    'updated_at' => current_time('mysql'),
                                ],
                                [
                                    'post_id' => $postId,
                                    'request_id' => $item->request_id,
                                ]
                            );
                        } else {
                            // Insert new record
                            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert for plugin data
                            $result = $wpdb->insert(
                                $table_name_posts,
                                [
                                    'post_id' => $postId,
                                    'request_id' => $item->request_id,
                                    'additional_content' => $additionalContentJson,
                                    'get_response' => 1,
                                    'ai' => 2,
                                    'updated_at' => current_time('mysql'),
                                    'created_at' => current_time('mysql'),
                                ]
                            );
                        }

                        // Check for database errors
                        if ($wpdb->last_error) {
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                                error_log('API Error: Database error: ' . $wpdb->last_error);
                            }
                        }
                    }

                    return new WP_REST_Response(['success' => true, 'message' => __('Response data saved successfully', 'gen-wave')], 200);
                } else {
                    return new WP_REST_Response(['error' => __('Invalid JSON in response', 'gen-wave')], 400);
                }
            } else {
                return new WP_REST_Response(['error' => __('Missing response key in data', 'gen-wave')], 400);
            }
        }

        return new WP_REST_Response(['error' => __('No data received', 'gen-wave')], 400);
    }

    public function getResponseData(WP_REST_Request $request)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gen_requests_posts';
        $requests_table = $wpdb->prefix . 'gen_requests';

        // Get filter parameters
        $status = $request->get_param('status') ?: 'pending'; // pending / applied / declined / all
        $post_type = $request->get_param('post_type') ?: 'all'; // all / product / post
        $date_from = $request->get_param('date_from');
        $date_to = $request->get_param('date_to');
        $job_id = $request->get_param('job_id'); // Filter by specific job_id

        // Build WHERE conditions
        $where_conditions = ["rp.get_response = 1"];

        // Status filter
        if ($status === 'pending') {
            $where_conditions[] = "rp.is_converted = 0 AND rp.deleted_at IS NULL";
        } elseif ($status === 'applied') {
            $where_conditions[] = "rp.is_converted = 1 AND rp.deleted_at IS NULL";
        } elseif ($status === 'declined') {
            $where_conditions[] = "rp.deleted_at IS NOT NULL";
        } elseif ($status === 'all') {
            // No additional filter for all
        }

        // Date filter
        if ($date_from) {
            $where_conditions[] = $wpdb->prepare("rp.created_at >= %s", $date_from);
        }
        if ($date_to) {
            $where_conditions[] = $wpdb->prepare("rp.created_at <= %s", $date_to . ' 23:59:59');
        }

        // Job ID filter - join with gen_requests table
        $join_clause = "";
        if ($job_id) {
            $join_clause = "INNER JOIN {$requests_table} r ON rp.request_id = r.id";
            $where_conditions[] = $wpdb->prepare("r.job_id = %s", $job_id);
        }

        $where_clause = implode(' AND ', $where_conditions);

        // Get all unique post IDs with responses first, with better ordering
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Dynamic WHERE conditions already use $wpdb->prepare() for user inputs, table names from $wpdb->prefix
        $post_ids_query = "
            SELECT DISTINCT rp.post_id
            FROM {$table_name} rp
            {$join_clause}
            WHERE {$where_clause}
            ORDER BY (SELECT MAX(created_at) FROM {$table_name} t2 WHERE t2.post_id = rp.post_id) DESC
        ";
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query built with prepared conditions above
        $post_ids = $wpdb->get_col($post_ids_query);


        if (empty($post_ids)) {
            return new WP_REST_Response([], 200);
        }

        $result = [];

        // Build WHERE clause for individual post queries (without the rp. prefix for simple queries)
        $simple_where_conditions = ["get_response = 1"];
        if ($status === 'pending') {
            $simple_where_conditions[] = "is_converted = 0 AND deleted_at IS NULL";
        } elseif ($status === 'applied') {
            $simple_where_conditions[] = "is_converted = 1 AND deleted_at IS NULL";
        } elseif ($status === 'declined') {
            $simple_where_conditions[] = "deleted_at IS NOT NULL";
        }
        if ($date_from) {
            $simple_where_conditions[] = $wpdb->prepare("created_at >= %s", $date_from);
        }
        if ($date_to) {
            $simple_where_conditions[] = $wpdb->prepare("created_at <= %s", $date_to . ' 23:59:59');
        }

        // For job_id filter, we need to filter by request_id
        $request_ids_for_job = [];
        if ($job_id) {
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is safe (uses $wpdb->prefix)
            $request_ids_for_job = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM {$requests_table} WHERE job_id = %s",
                $job_id
            ));
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
            if (!empty($request_ids_for_job)) {
                $simple_where_conditions[] = "request_id IN (" . implode(',', array_map('intval', $request_ids_for_job)) . ")";
            }
        }

        $simple_where_clause = implode(' AND ', $simple_where_conditions);

        // For each unique post ID
        foreach ($post_ids as $post_id) {
            // Get post details
            $post = get_post($post_id);
            if (!$post) {
                continue;
            }

            // Filter by post type if specified
            if ($post_type !== 'all' && $post->post_type !== $post_type) {
                continue;
            }

            // Get all updates for this post with same filters
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name safe, post_id is integer, conditions use prepare()
            $updates = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE post_id = %d AND {$simple_where_clause} ORDER BY created_at DESC",
                intval($post_id)
            ));
            // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter


            // Process each update to decode additional_content
            foreach ($updates as &$update) {
                if (!empty($update->additional_content)) {
                    $decoded_content = json_decode($update->additional_content, true);
                    if ($decoded_content) {
                        $update->additional_content_decoded = $decoded_content;
                    } else {
                    }
                } else {
                }
            }

            // Skip posts with no valid updates
            if (empty($updates)) {
                continue;
            }

            // Get the most recent created_at timestamp from updates
            $created_at = !empty($updates) ? $updates[0]->created_at : null;

            // Add to result array
            $result[] = [
                'post_id' => (int)$post_id,
                'product_name' => $post->post_title,
                'description' => $post->post_content,
                'post_type' => $post->post_type, // Add post_type for frontend field mapping
                'created_at' => $created_at,
                'updates' => $updates
            ];
        }


        return new WP_REST_Response($result, 200);
    }

    public function getConvertingProducts(WP_REST_Request $request)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'gen_requests_posts';

        // Get all products that have received AI responses (both converted and not converted)
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is safe (uses $wpdb->prefix)
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.* FROM {$table_name} t WHERE t.get_response = %d ORDER BY t.created_at DESC",
                1
            )
        );
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

        // Process each result to decode additional_content and extract AI description
        foreach ($results as &$result) {
            $result->new = '';

            if (!empty($result->additional_content)) {
                $decoded_content = json_decode($result->additional_content, true);
                if ($decoded_content && isset($decoded_content['description'])) {
                    $result->new = $decoded_content['description'];
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                        error_log("Failed to decode additional_content for post {$result->post_id}: " . substr($result->additional_content, 0, 100));
                    }
                }
            }
        }

        return new WP_REST_Response($results, 200);
    }

    /**
     * Domain verification endpoint for Laravel backend
     * Verifies that Gen Wave plugin is active and returns verification token
     *
     * Endpoint: GET /wp-json/gen-wave/v1/verify-domain?token=VERIFICATION_TOKEN
     */
    public function verifyDomainEndpoint(WP_REST_Request $request)
    {

        // Get verification token from query parameter
        $token = $request->get_param('token');

        if (empty($token)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => __('Verification token is required', 'gen-wave')
            ], 400);
        }


        // Return success with plugin information
        return new WP_REST_Response([
            'success' => true,
            'message' => __('Gen Wave plugin is active', 'gen-wave'),
            'plugin_version' => GEN_WAVE_VERSION,
            'token' => $token,
            'domain' => get_site_url(),
            'verified_at' => current_time('mysql')
        ], 200);
    }
}
