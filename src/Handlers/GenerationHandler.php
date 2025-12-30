<?php

namespace GenWavePlugin\Handlers;

if (!defined('ABSPATH')) {
    exit;
}

use GenWavePlugin\Core\ApiManager;
use Exception;

/**
 * GenerationHandler - Handles AI content generation for posts/products
 *
 * Responsibilities:
 * - Single post/product content generation
 * - Marking content as converted
 * - Job ID generation
 * - Token usage tracking
 */
class GenerationHandler
{
    /**
     * Handle single content generation from metabox (Free plugin)
     *
     * @return void
     */
    public function handle_genwave_generate_single()
    {
        global $wpdb;
        $wp_request_id = null;
        $post_request_id = null;

        try {
            // SECURITY: Verify nonce for CSRF protection
            if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'genwave_generate_nonce')) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log('Genwave Generate Single: Invalid nonce');
                }
                wp_send_json_error(['message' => __('Security verification failed. Please refresh the page and try again.', 'gen-wave')]);
                return;
            }

            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('Genwave Generate Single: Function called');
            }

            // VALIDATION: Get and validate post ID
            $post_id = intval($_POST['post_id'] ?? 0);
            if (!$post_id || $post_id < 1) {
                wp_send_json_error(['message' => __('Valid Post ID is required', 'gen-wave')]);
                return;
            }

            // VALIDATION: Get and validate generation method
            $generation_method = isset($_POST['generation_method']) ? sanitize_text_field(wp_unslash($_POST['generation_method'])) : 'title';
            $allowed_methods = ['title', 'short_description', 'description'];
            if (!in_array($generation_method, $allowed_methods, true)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log("Genwave: Invalid generation_method: {$generation_method}");
                }
                /* translators: %s: comma-separated list of allowed generation methods */
                wp_send_json_error(['message' => sprintf(__('Invalid generation method. Allowed: %s', 'gen-wave'), implode(', ', $allowed_methods))]);
                return;
            }

            // VALIDATION: Get and validate language - send as-is, Python handles mapping
            $language = isset($_POST['language']) ? sanitize_text_field(wp_unslash($_POST['language'])) : 'en';
            if (empty($language) || strlen($language) > 50) {
                wp_send_json_error(['message' => __('Invalid language parameter', 'gen-wave')]);
                return;
            }

            // VALIDATION: Get and validate content length
            $length = intval($_POST['length'] ?? 1000);
            if ($length < 100 || $length > 10000) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log("Genwave: Invalid length: {$length}");
                }
                wp_send_json_error(['message' => __('Content length must be between 100 and 10000 characters', 'gen-wave')]);
                return;
            }

            // VALIDATION: Get and validate custom instructions
            $custom_instructions = isset($_POST['instructions']) ? sanitize_textarea_field(wp_unslash($_POST['instructions'])) : '';
            if (strlen($custom_instructions) > 1000) {
                wp_send_json_error(['message' => __('Instructions cannot exceed 1000 characters', 'gen-wave')]);
                return;
            }

            // VALIDATION: Verify the post exists and user has permission to edit it
            $post = get_post($post_id);
            if (!$post) {
                return;
            }

            // Check if user can edit this post
            if (!current_user_can('edit_post', $post_id)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log("Genwave: User cannot edit post {$post_id}");
                }
                wp_send_json_error(['message' => __('You do not have permission to edit this post', 'gen-wave')]);
                return;
            }

            // Verify post type is allowed
            $allowed_post_types = ['post', 'product', 'page'];
            if (!in_array($post->post_type, $allowed_post_types, true)) {
                wp_send_json_error(['message' => __('Invalid post type', 'gen-wave')]);
                return;
            }

            // Store current content before generation
            $current_content = '';
            if ($generation_method === 'title') {
                $current_content = $post->post_title;
            } elseif ($generation_method === 'short_description') {
                $current_content = $post->post_excerpt;
            } else {
                $current_content = $post->post_content;
            }

            // Initialize ApiManager
            $api_manager = new ApiManager();

            // Prepare post data for LiteLLM
            if (!empty($custom_instructions)) {
                if ($generation_method === 'title') {
                    $post_data = [
                        'id' => $post->ID,
                        'title' => $custom_instructions,
                        'content' => '',
                        'excerpt' => '',
                        'post_type' => $post->post_type ?: 'post'
                    ];
                } else {
                    $post_data = [
                        'id' => $post->ID,
                        'title' => $custom_instructions,
                        'content' => '',
                        'excerpt' => '',
                        'post_type' => $post->post_type ?: 'post'
                    ];
                }
            } else {
                $post_data = [
                    'id' => $post->ID,
                    'title' => $post->post_title ?: 'Untitled',
                    'content' => $post->post_content ?: '',
                    'excerpt' => $post->post_excerpt ?: '',
                    'post_type' => $post->post_type ?: 'post'
                ];
            }

            // Generate validated job_id
            $job_id = $this->generate_validated_job_id();
            if (!$job_id) {
                wp_send_json_error(['message' => __('Failed to generate job ID. Please try again.', 'gen-wave')]);
                return;
            }

            // Insert record in wp_gen_requests
            $requests_table = $wpdb->prefix . 'gen_requests';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert for plugin data
            $inserted = $wpdb->insert(
                $requests_table,
                [
                    'job_id' => $job_id,
                    'user_id' => get_current_user_id(),
                    'type' => 'single',
                    'status' => 'pending',
                ],
                ['%s', '%d', '%s', '%s', '%s', '%s']
            );

            if (!$inserted) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log("Genwave: Failed to insert request record: " . $wpdb->last_error);
                }
                wp_send_json_error(['message' => __('Failed to create generation request', 'gen-wave')]);
                return;
            }

            $wp_request_id = $wpdb->insert_id;
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log("Genwave: Inserted request record with ID: {$wp_request_id}, job_id: {$job_id}");
            }

            // Insert record in wp_gen_requests_posts
            $requests_posts_table = $wpdb->prefix . 'gen_requests_posts';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert for plugin data
            $post_inserted = $wpdb->insert(
                $requests_posts_table,
                [
                    'request_id' => $wp_request_id,
                    'post_id' => $post_id,
                    'generation_type' => $generation_method,
                    'is_converted' => 0,
                ],
                ['%d', '%d', '%s', '%d', '%s', '%s']
            );

            if (!$post_inserted) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log("Genwave: Failed to insert post request: " . $wpdb->last_error);
                }
                wp_send_json_error(['message' => __('Failed to create post request', 'gen-wave')]);
                return;
            }

            $post_request_id = $wpdb->insert_id;
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log("Genwave: Inserted post request with ID: {$post_request_id}");
            }

            // Call LiteLLM API
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log("=== GEN WAVE: CALLING LITELLM ===");
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log("Genwave: Post ID: " . $post_id);
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log("Genwave: Generation Method: " . $generation_method);
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log("Genwave: Language: " . $language);
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log("Genwave: Length: " . $length);
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Debug mode only
                error_log("Genwave: Post data: " . print_r($post_data, true));
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log("Genwave: Custom Instructions: " . $custom_instructions);
            }

            // Get credentials for logging (encrypted)
            $encrypted_token = \GenWavePlugin\Core\Config::get('token');
            $encrypted_uidd = \GenWavePlugin\Core\Config::get('uidd');
            $license_key = \GenWavePlugin\Core\Config::get('license_key');

            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log("Genwave: License Key: " . $license_key);
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log("Genwave: Has Token: " . ($encrypted_token ? 'YES' : 'NO'));
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log("Genwave: Has UIDD: " . ($encrypted_uidd ? 'YES' : 'NO'));
            }

            $result = $api_manager->callLiteLLMStreaming(
                [$post_data],
                $language,
                $generation_method,
                $length,
                'single',
                false,
                $custom_instructions // Pass custom instructions to API
            );

            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log("=== GEN WAVE: LITELLM RESPONSE ===");
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Debug mode only
                error_log("Genwave: Response: " . print_r($result, true));
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log("Genwave: Has Error: " . (isset($result['error']) && $result['error'] ? 'YES' : 'NO'));
                if (isset($result['error']) && $result['error']) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log("Genwave: Error Message: " . ($result['message'] ?? 'Unknown'));
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log("Genwave: Response Code: " . ($result['response_code'] ?? 'N/A'));
                }
            }

            // Save token usage if available
            if (isset($result['tokens_used'])) {
                $this->save_token_usage($job_id, $result['tokens_used'], $result['token_charge_id'] ?? 0);
            }

            // Return success with results
            wp_send_json_success([
                'data' => $result,
                'job_id' => $job_id,
                'post_request_id' => $post_request_id
            ]);

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('Genwave Generate Single: Exception: ' . $e->getMessage());
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('Genwave Generate Single: Stack trace: ' . $e->getTraceAsString());
            }
            wp_send_json_error([
                /* translators: %s: error message from the exception */
                'message' => sprintf(__('Error generating content: %s', 'gen-wave'), $e->getMessage())
            ]);
        }
    }

    /**
     * Mark post as converted when user clicks Update button
     *
     * @return void
     */
    public function handle_mark_converted()
    {
        try {
            // SECURITY: Verify nonce for CSRF protection
            if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'genwave_mark_converted_nonce')) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log('Genwave Mark Converted: Invalid nonce');
                }
                wp_send_json_error(['message' => __('Security verification failed', 'gen-wave')]);
                return;
            }

            global $wpdb;

            // VALIDATION: Get and validate post_request_id
            $post_request_id = intval($_POST['post_request_id'] ?? 0);
            if (!$post_request_id || $post_request_id < 1) {
                wp_send_json_error(['message' => __('Valid Post request ID is required', 'gen-wave')]);
                return;
            }

            $requests_posts_table = $wpdb->prefix . 'gen_requests_posts';

            // VALIDATION: Verify the record exists and get post_id
            // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is safe (uses $wpdb->prefix)
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
                wp_send_json_error(['message' => __('You do not have permission to update this content', 'gen-wave')]);
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
                wp_send_json_success(['message' => __('Post marked as converted', 'gen-wave')]);
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log("Genwave: Failed to mark post_request_id {$post_request_id} as converted");
                }
                wp_send_json_error(['message' => __('Failed to update conversion status', 'gen-wave')]);
            }

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('Genwave Mark Converted: Exception: ' . $e->getMessage());
            }
            /* translators: %s: error message */
            wp_send_json_error(['message' => sprintf(__('Error: %s', 'gen-wave'), $e->getMessage())]);
        }
    }

    /**
     * Generate validated job_id (like Pro plugin)
     * Returns numeric job_id or false on failure
     *
     * @param int $max_attempts Maximum number of attempts to generate unique ID
     * @return string|false
     */
    private function generate_validated_job_id($max_attempts = 5)
    {
        global $wpdb;

        for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
            try {
                // Generate job ID (9 digits)
                $time = time() % 1000000000;
                $random_number = random_int(0, 9999);
                $job_id = str_pad($time + $random_number, 9, '0', STR_PAD_LEFT);

                // Check for collision
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query for plugin data
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}gen_requests WHERE job_id = %s",
                    $job_id
                ));

                if ($exists == 0) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                        error_log("Genwave: Generated unique job_id: {$job_id}");
                    }
                    return $job_id;
                }

                // If collision detected, log and retry
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log("Genwave: Job ID collision detected ({$job_id}), attempt {$attempt}");
                }

            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log('Genwave: Error generating job_id: ' . $e->getMessage());
                }
            }
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
            error_log('Genwave: Failed to generate unique job_id after ' . $max_attempts . ' attempts');
        }
        return false;
    }

    /**
     * Save token usage for a job
     *
     * @param string $job_id Job ID
     * @param array $tokens_used Token usage data
     * @param float $token_charge Token charge amount
     * @return void
     */
    private function save_token_usage($job_id, $tokens_used, $token_charge)
    {
        global $wpdb;

        try {
            $token_usage_table = $wpdb->prefix . 'ai_pro_token_usage';

            // Insert token usage record
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert for plugin data
            $wpdb->insert(
                $token_usage_table,
                [
                    'job_id' => $job_id,
                    'user_id' => get_current_user_id(),
                    'input_tokens' => $tokens_used['input_tokens'] ?? 0,
                    'output_tokens' => $tokens_used['output_tokens'] ?? 0,
                    'total_tokens' => $tokens_used['total_tokens'] ?? 0,
                    'token_charge' => $token_charge,
                ],
                ['%s', '%d', '%d', '%d', '%d', '%f', '%s']
            );
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log("Genwave: Inserted token usage for job_id '{$job_id}'");
            }

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('Genwave: Failed to save token usage: ' . $e->getMessage());
            }
        }
    }
}
