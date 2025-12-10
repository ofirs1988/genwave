<?php

namespace GenWavePlugin\Handlers;

use Exception;

/**
 *
 * Responsibilities:
 * - Handle AI polling results
 * - Proxy API requests to solve CORS issues
 * - Process individual post responses
 * - Update request status
 * - Manage token balance updates
 */
class PollingHandler
{
    /**
     * Handle AI polling results from JavaScript (Free plugin version)
     *
     * @return void
     */
    public function handle_ai_polling_results()
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
            error_log('AI Polling AJAX (Free Plugin): Function called!');
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r,WordPress.Security.NonceVerification.Missing -- Debug mode only, nonce verified below
            error_log('AI Polling AJAX (Free Plugin): POST data: ' . print_r($_POST, true));
        }

        try {
            // SECURITY: Verify nonce for CSRF protection (accept both admin and frontend nonces)
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified immediately below
            $nonce = isset($_POST['security']) ? sanitize_text_field(wp_unslash($_POST['security'])) : (isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '');
            $nonce_check = wp_verify_nonce($nonce, 'ai_awesome_admin_nonce') ||
                          wp_verify_nonce($nonce, 'ai_awesome_frontend_nonce') ||
                          wp_verify_nonce($nonce, 'genwave_polling_nonce');

            if (!$nonce_check) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log('AI Polling AJAX (Free Plugin): Invalid nonce');
                }
                wp_send_json_error(__('Security verification failed', 'gen-wave'));
                return;
            }

            // VALIDATION: Check if response data is provided
            if (!isset($_POST['response']) || empty($_POST['response'])) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log('AI Polling AJAX (Free Plugin): No response data provided');
                }
                wp_send_json_error(__('No response data provided', 'gen-wave'));
                return;
            }

            $responseData = sanitize_textarea_field(wp_unslash($_POST['response']));

            // VALIDATION: Check response size (prevent memory issues)
            if (strlen($responseData) > 5000000) { // 5MB limit
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log('AI Polling AJAX (Free Plugin): Response data too large');
                }
                wp_send_json_error(__('Response data exceeds maximum size', 'gen-wave'));
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
                    /* translators: %s: JSON error message */
                    wp_send_json_error(sprintf(__('Invalid JSON format: %s', 'gen-wave'), json_last_error_msg()));
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
                wp_send_json_error(__('Response data must be an array', 'gen-wave'));
                return;
            }

            // VALIDATION: Check if array is empty
            if (empty($responseData)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log('AI Polling AJAX (Free Plugin): Empty response array');
                }
                wp_send_json_error(__('Response data is empty', 'gen-wave'));
                return;
            }

            // VALIDATION: Limit number of posts to prevent DoS
            if (count($responseData) > 100) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log('AI Polling AJAX (Free Plugin): Too many posts in response (' . count($responseData) . ')');
                }
                wp_send_json_error(__('Cannot process more than 100 posts at once', 'gen-wave'));
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
     *
     * @param int $postId Post ID
     * @param array $content Response content
     * @return array Result
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

            // Download images from R2 and save to WordPress before storing
            if (isset($contentToStore['images']) && is_array($contentToStore['images'])) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log("Processing " . count($contentToStore['images']) . " images for post $postId");
                }

                foreach ($contentToStore['images'] as &$imageData) {
                    if (isset($imageData['image_url']) && !empty($imageData['image_url'])) {
                        $r2Url = $imageData['image_url'];
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                            error_log("Downloading image from R2: $r2Url");
                        }

                        // Download image from R2 and save to WordPress
                        $attachmentId = $this->downloadAndSaveImage($r2Url, $postId);

                        if ($attachmentId) {
                            // Get WordPress media library URL
                            $wpUrl = wp_get_attachment_url($attachmentId);

                            if ($wpUrl) {
                                // Update image URL to WordPress URL (not R2)
                                $imageData['wp_attachment_id'] = $attachmentId;
                                $imageData['wp_url'] = $wpUrl;
                                $imageData['r2_url'] = $r2Url;  // Keep R2 URL for reference
                                $imageData['image_url'] = $wpUrl;  // Set as primary URL

                                if (defined('WP_DEBUG') && WP_DEBUG) {
                                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                                    error_log("Image downloaded and saved to WordPress: $wpUrl (attachment ID: $attachmentId)");
                                }

                                // Delete image from R2 now that it's safely stored in WordPress
                                $this->deleteImageFromR2($r2Url);
                            }
                        } else {
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                                error_log("Failed to download image from R2: $r2Url");
                            }
                        }
                    }
                }
                unset($imageData);  // Break reference
            }

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
                'message' => 'Exception: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update request status in database
     *
     * @param int $requestId Request ID
     * @param string $status Status
     * @param int|null $progress Progress percentage
     * @return void
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
     * Download image from URL and save to WordPress media library
     *
     * @param string $imageUrl Image URL (from R2 or anywhere)
     * @param int $postId Post ID to attach the image to
     * @return int|false Attachment ID on success, false on failure
     */
    private function downloadAndSaveImage($imageUrl, $postId)
    {
        try {
            // Validate URL
            if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log("Invalid image URL: $imageUrl");
                }
                return false;
            }

            // Check if image already exists (by URL) to avoid duplicates
            global $wpdb;
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Check for duplicate image by meta value
            $existingId = $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta}
                 WHERE meta_key = '_ai_source_url' AND meta_value = %s
                 LIMIT 1",
                $imageUrl
            ));

            if ($existingId) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log("Image already exists in media library with ID: $existingId");
                }
                return $existingId;
            }

            // Include required WordPress functions
            if (!function_exists('media_handle_sideload')) {
                require_once(ABSPATH . 'wp-admin/includes/media.php');
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                require_once(ABSPATH . 'wp-admin/includes/image.php');
            }

            // Download the image
            $temp_file = download_url($imageUrl);

            if (is_wp_error($temp_file)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log("Failed to download image: " . $temp_file->get_error_message());
                }
                return false;
            }

            // Prepare file array
            $parsed_url = wp_parse_url($imageUrl, PHP_URL_PATH);
            $file_array = [
                'name' => basename($parsed_url) ?: 'ai-generated-image-' . time() . '.png',
                'tmp_name' => $temp_file
            ];

            // Add file extension if missing
            $pathinfo = pathinfo($file_array['name']);
            if (empty($pathinfo['extension'])) {
                $file_array['name'] .= '.png';
            }

            // Handle the upload
            $attachment_id = media_handle_sideload($file_array, $postId, 'AI Generated Image');

            // Clean up temp file
            wp_delete_file($temp_file);

            if (is_wp_error($attachment_id)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log("Failed to handle sideload: " . $attachment_id->get_error_message());
                }
                return false;
            }

            // Add metadata
            update_post_meta($attachment_id, '_ai_generated', true);
            update_post_meta($attachment_id, '_ai_source_url', $imageUrl);
            update_post_meta($attachment_id, '_ai_generated_date', current_time('mysql'));

            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log("Successfully downloaded and added image to media library with ID: $attachment_id");
            }
            return $attachment_id;

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log("Error downloading image from URL: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Delete image from R2 storage after it's saved to WordPress
     *
     * @param string $r2Url R2 image URL
     * @return bool True on success, false on failure
     */
    private function deleteImageFromR2($r2Url)
    {
        try {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log("Requesting deletion of image from R2: $r2Url");
            }

            // Get API URL
            $apiUrl = $this->getApiUrl();
            $deleteUrl = $apiUrl . '/r2/delete-image';

            // Get API key from settings
            $apiKey = get_option('genwave_litellm_api_key');

            if (empty($apiKey)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log("Cannot delete R2 image: API key not configured");
                }
                return false;
            }

            // Send delete request to Python API
            $response = wp_remote_post($deleteUrl, [
                'timeout' => 15,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'x-api-key' => $apiKey
                ],
                'body' => wp_json_encode([
                    'r2_url' => $r2Url
                ]),
                'sslverify' => false  // For local development
            ]);

            if (is_wp_error($response)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log("Failed to send R2 delete request: " . $response->get_error_message());
                }
                return false;
            }

            $statusCode = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if ($statusCode === 200) {
                $result = json_decode($body, true);

                if (isset($result['success']) && $result['success']) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                        error_log("Successfully deleted image from R2: $r2Url");
                    }
                    return true;
                } else {
                    $message = $result['message'] ?? 'Unknown error';
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                        error_log("R2 deletion returned success=false: $message");
                    }
                    return false;
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log("R2 delete request failed with status $statusCode: $body");
                }
                return false;
            }

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log("Error deleting image from R2: " . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Handle API proxy requests to solve CORS issues
     *
     * @return void
     */
    public function handle_ai_poll_proxy()
    {
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
            error_log('AI Poll Proxy (AjaxManager): Function called!');
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r,WordPress.Security.NonceVerification.Missing -- Debug mode only, nonce verified below
            error_log('AI Poll Proxy (AjaxManager) called with POST data: ' . print_r($_POST, true));
        }

        // Check if the action matches
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified below
        $action = isset($_POST['action']) ? sanitize_text_field(wp_unslash($_POST['action'])) : '';
        if ($action !== 'ai_poll_proxy') {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('AI Poll Proxy (AjaxManager): Wrong action received: ' . ($action ?: 'none'));
            }
            wp_send_json_error('Wrong action', 400);
            return;
        }

        // Verify nonce for security - accept both admin and frontend nonces
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        $nonce_check = wp_verify_nonce($nonce, 'ai_awesome_admin_nonce') ||
                      wp_verify_nonce($nonce, 'ai_awesome_frontend_nonce');

        if (!$nonce_check) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('AI Poll Proxy (AjaxManager): Invalid nonce. Received: ' . $nonce);
            }
            wp_send_json_error('Invalid nonce', 403);
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

            if ($status_code === 200) {
                $data = json_decode($body, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    wp_send_json_success($data);
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                        error_log('AI Poll Proxy (AjaxManager): JSON decode error: ' . json_last_error_msg());
                    }
                    wp_send_json_error('Invalid JSON response from API', 500);
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log('AI Poll Proxy (AjaxManager): Non-200 status code: ' . $status_code);
                }
                wp_send_json_error('API returned status ' . $status_code . ': ' . $body, $status_code);
            }

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('AI Poll Proxy (AjaxManager): Exception: ' . $e->getMessage());
            }
            wp_send_json_error('Server error: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get API URL based on environment
     *
     * @return string API URL
     */
    private function getApiUrl()
    {
        // Allow override via constant for development
        if (defined('GENWAVE_API_URL')) {
            return GENWAVE_API_URL;
        }

        return 'https://account.genwave.ai/api';
    }

    /**
     * Handle token balance update requests from frontend JavaScript
     *
     * @return void
     */
    public function handle_update_token_balance()
    {
        try {
            // Log the function call
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('Token Balance Update AJAX: Function called!');
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r,WordPress.Security.NonceVerification.Missing -- Debug mode only, nonce verified below
                error_log('Token Balance Update AJAX: POST data: ' . print_r($_POST, true));
            }

            // Verify nonce for security (accept both admin and frontend nonces)
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified immediately below
            $nonce = isset($_POST['security']) ? sanitize_text_field(wp_unslash($_POST['security'])) : (isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '');
            $nonce_check = wp_verify_nonce($nonce, 'ai_awesome_admin_nonce') ||
                          wp_verify_nonce($nonce, 'ai_awesome_frontend_nonce');

            if (!$nonce_check) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log('Token Balance Update AJAX: Invalid nonce. Received: ' . $nonce);
                }
                wp_send_json_error('Invalid nonce');
                return;
            }

            // Verify required parameters
            if (!isset($_POST['token_balance'])) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log('Token Balance Update AJAX: No token_balance parameter provided');
                }
                wp_send_json_error('Token balance parameter required');
                return;
            }

            $tokenBalance = floatval($_POST['token_balance']);

            // Basic validation
            if ($tokenBalance < 0) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log('Token Balance Update AJAX: Invalid token balance value: ' . $tokenBalance);
                }
                wp_send_json_error('Invalid token balance value');
                return;
            }

            // Update the token balance in wp_options
            if (update_option('genwave_token_balance', $tokenBalance)) {
                // Return success response
                wp_send_json_success([
                    'token_balance' => $tokenBalance,
                    'formatted_balance' => number_format($tokenBalance, 2)
                ]);
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log('Token Balance Update AJAX: Failed to update token balance option');
                }
                wp_send_json_error('Failed to update token balance in WordPress');
            }

        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('Token Balance Update AJAX: Exception: ' . $e->getMessage());
            }
            wp_send_json_error('Error updating token balance: ' . $e->getMessage());
        }
    }
}
