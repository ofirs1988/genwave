<?php

namespace GenWavePlugin\Global;

if (!defined('ABSPATH')) {
    exit;
}

use GenWavePlugin\Controllers\VerifyLoginController;
use GenWavePlugin\WP_Error;

class ApiManager {
    protected $api_base_url;

    public function __construct($api_base_url = '') {
        // Set the base URL for the API
        $api_base_url = GENWAVE_API_URL;
        $this->api_base_url = rtrim($api_base_url, '/');
    }

    /**
     * Debug log helper - only logs when WP_DEBUG is enabled
     * @param string $message
     */
    private function debug_log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
            error_log($message);
        }
    }

    /**
     * Get the clean domain name for from-domain header
     * Prioritizes: wp_options siteurl > HTTP_HOST > SERVER_NAME
     * Removes protocol, paths, and query parameters
     *
     * @return string Clean domain name (e.g., 'example.com')
     */
    public static function getFromDomain() {
        // Priority 1: Get siteurl from wp_options
        $site_url = get_option('siteurl');

        if (!empty($site_url)) {
            return self::cleanDomain($site_url);
        }

        // Priority 2: Use site_url() function
        $site_url = site_url();
        if (!empty($site_url)) {
            return self::cleanDomain($site_url);
        }

        // Priority 3: HTTP_HOST (current request domain)
        if (!empty($_SERVER['HTTP_HOST'])) {
            return self::cleanDomain(sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])));
        }

        // Priority 4: SERVER_NAME
        if (!empty($_SERVER['SERVER_NAME'])) {
            return self::cleanDomain(sanitize_text_field(wp_unslash($_SERVER['SERVER_NAME'])));
        }

        // Fallback: return GEN_WAVE_DOMAIN if defined, or 'unknown'
        return defined('GEN_WAVE_DOMAIN') ? GEN_WAVE_DOMAIN : 'unknown';
    }

    /**
     * Clean a URL/domain string to extract just the domain
     * Removes protocol (http/https), paths, query params, and port
     *
     * @param string $url The URL or domain to clean
     * @return string Clean domain name
     */
    private static function cleanDomain($url) {
        // Remove protocol
        $domain = preg_replace('#^https?://#', '', $url);

        // Remove path and query string
        $domain = preg_replace('#[/?].*$#', '', $domain);

        // Remove port if present (e.g., :8080)
        $domain = preg_replace('#:\d+$#', '', $domain);

        // Remove www. prefix (optional, keep if you want it)
        // $domain = preg_replace('#^www\.#', '', $domain);

        // Sanitize
        $domain = sanitize_text_field(trim($domain));

        return $domain;
    }

    /**
     * Make a GET request to the API.
     *
     * @param string $endpoint The API endpoint to request.
     * @param array $params Query parameters to include in the request.
     * @return array|WP_Error The response from the API or a WP_Error object on failure.
     */
    public function get($endpoint, $params = []) {
        $url = $this->buildUrl($endpoint, $params);
        $response = wp_remote_get($url);

        return $this->handleResponse($response);
    }

    /**
     * Make a POST request to the API.
     *
     * @param string $endpoint The API endpoint to request.
     * @param array $data The data to send in the POST request.
     * @return array|WP_Error The response from the API or a WP_Error object on failure.
     */
    /**
     * POST request without authentication (for public endpoints like integration)
     * @param string $endpoint
     * @param array $data
     * @return array
     */
    public function postSecure($endpoint, $data = [])
    {
        $url = $this->buildUrl($endpoint);

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        // Only disable SSL verification in localhost development environment
        $is_localhost = (strpos($this->api_base_url, 'localhost') !== false ||
                        strpos($this->api_base_url, '127.0.0.1') !== false ||
                        strpos($this->api_base_url, '.local') !== false);

        $response = wp_remote_post( $url, [
            'body' => json_encode($data),
            'headers' => $headers,
            'sslverify' => !$is_localhost,
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('Gen Wave API Error: ' . $response->get_error_message());
            }
            return [
                'success' => false,
                'error' => true,
                'message' => $response->get_error_message(),
            ];
        }

        $response = $this->handleResponse($response);
        return $response;
    }

    public function post($endpoint, $data = [], $token = null, $uidd = null) {
        $url = $this->buildUrl($endpoint);
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'from-domain' => self::getFromDomain(), // Dynamic domain from wp_options or current request
            'server-ip' => isset($_SERVER['SERVER_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_ADDR'])) : 'unknown', // Add the server IP here
            'license-key' => Config::get('license_key') ?? null // Add the server IP here
        ];
        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        if ($uidd) {
            $headers['uidd'] = $uidd; // Add uidd to headers
        }

        // Only disable SSL verification in localhost development environment
        $is_localhost = (strpos($this->api_base_url, 'localhost') !== false ||
                        strpos($this->api_base_url, '127.0.0.1') !== false ||
                        strpos($this->api_base_url, '.local') !== false);

        add_filter('http_request_timeout', function() {
            return 30; // Increase to 30 seconds or more
        });
        $response = wp_remote_post( $url, [
            'body' => json_encode($data),
            'headers' => $headers,
            'sslverify' => !$is_localhost, // Enable SSL verification in production
        ]);

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Debug mode only
                error_log('WP_Error occurred: ' . print_r($response->get_error_message(), true));
            }
            // Return formatted error response instead of raw WP_Error
            return [
                'success' => false,
                'error' => true,
                'message' => $response->get_error_message(),
                'data' => null
            ];
        }
        $response = $this->handleResponse($response);


        if (isset($response['error']) && $response['error'] === true) {
            // Return response if error = true
            return $response;
        }
        if (is_array($response) && key_exists('auth', $response)) {
            if (isset($response['auth']) && !$response['auth']) {
                VerifyLoginController::redirectLogin();
            }
        }

        return $response;
    }


    public function postWithCurl($endpoint, $data = [], $token = null) {
        $url = $this->buildUrl($endpoint);

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $args = [
            'method' => 'POST',
            'headers' => $headers,
            'body' => wp_json_encode($data),
            'timeout' => 30,
            'sslverify' => !(strpos($url, 'localhost') !== false || strpos($url, '.local') !== false),
        ];

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body($response);
        $decoded_data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Failed to decode JSON response', 'body' => $body];
        }

        return $decoded_data;
    }

    /**
     * Build the full URL for the API request.
     *
     * @param string $endpoint The API endpoint.                                                             ai-awosomeai-awosome
     * @param array $params Query parameters to include in the request.
     * @return string The full URL for the API request.
     */
    protected function buildUrl($endpoint, $params = []) {
        $url = $this->api_base_url . '/api/' . ltrim($endpoint, '/');
        if (!empty($params)) {
            $url = add_query_arg($params, $url);
        }
        return $url;
    }

    /**
     * Handle the API response.
     *
     * @param WP_Error|array $response The response from wp_remote_get or wp_remote_post.
     * @return array|WP_Error The decoded response body or a WP_Error object on failure.
     */
    protected function handleResponse($response) {
        if (is_wp_error($response)) {
            // Log the error for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('API WP_Error: ' . $response->get_error_message());
            }
            return [
                'success' => false,
                'error' => true,
                'message' => $response->get_error_message(),
                'data' => null
            ];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Log response details for debugging
        
        // Handle non-200 status codes
        if ($response_code !== 200) {
            // Try to parse the response body for a better error message
            $error_data = json_decode($body, true);
            $error_message = "API returned status code: $response_code";

            // Check if Laravel returned a specific error message
            if (is_array($error_data)) {
                if (isset($error_data['message'])) {
                    $error_message = $error_data['message'];
                }
                // Return the full error response from Laravel
                return array_merge([
                    'success' => false,
                    'error' => true,
                    'status_code' => $response_code,
                    'data' => null
                ], $error_data);
            }

            return [
                'success' => false,
                'error' => true,
                'message' => $error_message,
                'status_code' => $response_code,
                'data' => null
            ];
        }
        
        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('JSON Decode Error: ' . json_last_error_msg());
            }
            return [
                'success' => false,
                'error' => true,
                'message' => 'Failed to decode JSON response: ' . json_last_error_msg(),
                'raw_body' => $body,
                'data' => null
            ];
        }

        return $decoded;
    }

    /**
     * Call SmartPolling Node.js API for fast job status checking
     *
     * @param int $job_id The WordPress job ID to check
     * @return array|null The API response or null on failure
     */
    public static function callSmartPolling($job_id) {
        // Check if SmartPolling is configured
        if (!defined('SMART_POLLING_URL') || empty(SMART_POLLING_URL)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('SmartPolling URL not configured');
            }
            return null;
        }

        $url = SMART_POLLING_URL . '/api/jobs/' . intval($job_id);

        // Get authentication token from config
        $token = Config::get('token');
        if (empty($token)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('SmartPolling: No authentication token available');
            }
            return null;
        }

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];

        // Make HTTP request to Node.js API
        // Only disable SSL verification for localhost/development
        $is_localhost = (defined('SMART_POLLING_URL') &&
                        (strpos(SMART_POLLING_URL, 'localhost') !== false ||
                         strpos(SMART_POLLING_URL, '127.0.0.1') !== false ||
                         strpos(SMART_POLLING_URL, '.local') !== false));

        $response = wp_remote_get($url, [
            'timeout' => 5, // Fast timeout for polling
            'headers' => $headers,
            'sslverify' => !$is_localhost // Enable SSL verification in production
        ]);

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('SmartPolling API Error: ' . $response->get_error_message());
            }
            return null;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status_code !== 200) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log("SmartPolling API HTTP Error: {$status_code} - {$body}");
            }
            return null;
        }

        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('SmartPolling JSON Error: ' . json_last_error_msg());
            }
            return null;
        }

        // Log successful API call

        return $decoded;
    }

    /**
     * Get detailed items for a job from SmartPolling API
     *
     * @param int $job_id The WordPress job ID
     * @return array|null The items data or null on failure
     */
    public static function getSmartPollingItems($job_id) {
        // Check if SmartPolling is configured
        if (!defined('SMART_POLLING_URL') || empty(SMART_POLLING_URL)) {
            return null;
        }

        $url = SMART_POLLING_URL . '/api/jobs/' . intval($job_id) . '/items';

        $token = Config::get('token');
        if (empty($token)) {
            return null;
        }

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];

        // Only disable SSL verification for localhost/development
        $is_localhost = (defined('SMART_POLLING_URL') &&
                        (strpos(SMART_POLLING_URL, 'localhost') !== false ||
                         strpos(SMART_POLLING_URL, '127.0.0.1') !== false ||
                         strpos(SMART_POLLING_URL, '.local') !== false));

        $response = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => $headers,
            'sslverify' => !$is_localhost // Enable SSL verification in production
        ]);

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('SmartPolling Items API Error: ' . $response->get_error_message());
            }
            return null;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        return $decoded;
    }

    /**
     * Set the API base URL.
     *
     * @param string $url The new base URL for the API.
     */
    public function setApiBaseUrl($url) {
        $this->api_base_url = rtrim($url, '/');
    }

    /**
     * Make a POST request to the LiteLLM API.
     *
     * @param string $endpoint The API endpoint to request.
     * @param array $data The data to send in the POST request.
     * @param string $token The authentication token.
     * @param string $uidd The user UUID.
     * @return array The response from the API.
     */
    public function postToLiteLLM($endpoint, $data = [], $token = null, $uidd = null) {
        if (!defined('GEN_WAVE_SMART_API')) {
            return [
                'success' => false,
                'error' => true,
                'message' => 'LiteLLM API URL not configured',
                'data' => null
            ];
        }

        $url = rtrim(GEN_WAVE_SMART_API, '/') . '/api/v1/' . ltrim($endpoint, '/');
        // Decrypt token and UUID (same as callLiteLLMStreaming)
        $decrypted_token = $token ? $this->decryptToken($token) : null;
        $decrypted_uidd = $uidd ? $this->decryptToken($uidd) : null;

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'from-domain' => self::getFromDomain(),
            'server-ip' => isset($_SERVER['SERVER_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_ADDR'])) : 'unknown',
            'license-key' => Config::get('license_key') ?? null
        ];

        if ($decrypted_token) {
            $headers['Authorization'] = 'Bearer ' . $decrypted_token;
        }
        if ($decrypted_uidd) {
            $headers['uidd'] = $decrypted_uidd;
        }

        // Enhanced timeout for AI processing
        add_filter('http_request_timeout', function() {
            return 300; // 5 minutes for AI processing
        });


        // Only disable SSL verification for localhost/development
        $is_localhost = (defined('GEN_WAVE_SMART_API') &&
                        (strpos(GEN_WAVE_SMART_API, 'localhost') !== false ||
                         strpos(GEN_WAVE_SMART_API, '127.0.0.1') !== false ||
                         strpos(GEN_WAVE_SMART_API, '.local') !== false));

        $response = wp_remote_post($url, [
            'body' => json_encode($data),
            'headers' => $headers,
            'timeout' => 300,
            'sslverify' => !$is_localhost // Enable SSL verification in production
        ]);

        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('LiteLLM WP_Error occurred: ' . $response->get_error_message());
            }
            return [
                'success' => false,
                'error' => true,
                'message' => 'AI service is currently unavailable. Please try again later.',
                'data' => null
            ];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);


        // Handle non-200 status codes
        if ($response_code !== 200) {
            // Try to parse error details from response
            $decoded = json_decode($body, true);

            // Map status codes to user-friendly messages
            $error_messages = [
                401 => __('Authentication failed. Please reconnect your account.', 'gen-wave'),
                403 => __('Access denied. Your license may have expired or you have insufficient tokens.', 'gen-wave'),
                402 => __('Insufficient tokens. Please purchase more tokens to continue.', 'gen-wave'),
                429 => __('Too many requests. Please wait a moment and try again.', 'gen-wave'),
                500 => __('AI service error. Please try again later.', 'gen-wave'),
                503 => __('AI service is temporarily unavailable. Please try again later.', 'gen-wave'),
            ];

            // Get base error message
            $error_message = $error_messages[$response_code] ?? __('An error occurred while processing your request.', 'gen-wave');

            // Check for specific error details from the API
            if ($decoded) {
                // FastAPI/LiteLLM returns errors in detail.message format
                if (isset($decoded['detail'])) {
                    $detail = $decoded['detail'];

                    // If detail is an object with message field (FastAPI HTTPException format)
                    if (is_array($detail) && isset($detail['message'])) {
                        $error_message = $detail['message'];

                        // Include additional info if available
                        $result = [
                            'success' => false,
                            'error' => true,
                            'status_code' => $response_code,
                            'message' => $error_message,
                            'auth' => $detail['auth'] ?? false,
                            'url' => $detail['url'] ?? '',
                            'btn_text' => $detail['btn_text'] ?? __('Learn more', 'gen-wave'),
                            'data' => null
                        ];
                        return $result;
                    }

                    // If detail is a string
                    if (is_string($detail)) {
                        $error_message = $detail;
                    }
                } elseif (isset($decoded['message'])) {
                    $error_message = $decoded['message'];
                }

                // Return full error response from API
                return array_merge([
                    'success' => false,
                    'error' => true,
                    'status_code' => $response_code,
                    'data' => null
                ], $decoded, ['message' => $error_message]);
            }

            return [
                'success' => false,
                'error' => true,
                'message' => $error_message,
                'status_code' => $response_code,
                'data' => null
            ];
        }

        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('LiteLLM JSON Decode Error: ' . json_last_error_msg());
            }
            return [
                'success' => false,
                'error' => true,
                'message' => 'Failed to decode LiteLLM JSON response: ' . json_last_error_msg(),
                'raw_body' => $body,
                'data' => null
            ];
        }


        // Transform LiteLLM response to match expected WordPress format
        return $this->transformLiteLLMResponse($decoded);
    }

    /**
     * Transform LiteLLM API response to match expected WordPress format.
     *
     * @param array $litellmResponse The response from LiteLLM API.
     * @return array The transformed response.
     */
    private function transformLiteLLMResponse($litellmResponse) {
        // Check if this is an error response
        if (isset($litellmResponse['error']) && $litellmResponse['error']) {
            return [
                'success' => false,
                'error' => true,
                'message' => $litellmResponse['message'] ?? 'Unknown LiteLLM error',
                'data' => null
            ];
        }

        // Check if this is a successful single post response
        if (isset($litellmResponse['status']) && $litellmResponse['status'] === 'completed' &&
            isset($litellmResponse['results']) && is_array($litellmResponse['results'])) {

            // Transform each result to match Laravel format
            $responses = [];
            foreach ($litellmResponse['results'] as $result) {
                if (isset($result['post_id']) && isset($result['content'])) {
                    $responses[] = [
                        'headers' => [],
                        'original' => [
                            'success' => true,
                            'data' => [
                                'ai_tokens_id' => 1, // Will be updated by Laravel
                                'ai_token_request_id' => time(),
                                'job_request_id' => $litellmResponse['request_id'] ?? 0,
                                'post_id' => $result['post_id'],
                                'send_request' => 1,
                                'error_message' => null,
                                'attempts' => 1,
                                'length' => strlen($result['content']),
                                'prompt_tokens' => $result['tokens_used']['prompt_tokens'] ?? 0,
                                'response' => json_encode(['content' => $result['content']], JSON_PRETTY_PRINT),
                                'response_data' => json_encode([
                                    'content' => $result['content'],
                                    'model' => 'litellm',
                                    'processing_time' => $result['processing_time'] ?? 0,
                                    'usage' => $result['tokens_used'] ?? []
                                ]),
                                'tokens_used' => $result['tokens_used']['completion_tokens'] ?? 0,
                                'total_tokens_used' => $result['tokens_used']['total_tokens'] ?? 0,
                                'time' => ($result['processing_time'] ?? 0) / 1000 // Convert ms to seconds
                            ]
                        ],
                        'exception' => null
                    ];
                }
            }

            return [
                'success' => true,
                'error' => false,
                'data' => [
                    'success' => true,
                    'request_id' => $litellmResponse['request_id'] ?? null,
                    'message' => 'Request processed successfully',
                    'responses' => $responses
                ]
            ];
        }

        // Check if this is a bulk processing response
        if (isset($litellmResponse['status']) && $litellmResponse['status'] === 'processing') {
            return [
                'success' => true,
                'error' => false,
                'data' => [
                    'success' => true,
                    'request_id' => $litellmResponse['request_id'] ?? null,
                    'message' => $litellmResponse['message'] ?? 'Bulk processing started',
                    'status' => 'processing',
                    'job_id' => $litellmResponse['request_id'] ?? null
                ]
            ];
        }

        // Default response format
        return [
            'success' => true,
            'error' => false,
            'data' => $litellmResponse
        ];
    }

    /**
     * Check job status in LiteLLM API.
     *
     * @param string $requestId The LiteLLM request ID.
     * @param string $token The authentication token.
     * @param string $uidd The user UUID.
     * @return array The job status response.
     */
    public function checkLiteLLMJobStatus($requestId, $token = null, $uidd = null) {
        if (!defined('GEN_WAVE_SMART_API')) {
            return [
                'success' => false,
                'error' => true,
                'message' => 'LiteLLM API URL not configured'
            ];
        }

        $url = rtrim(GEN_WAVE_SMART_API, '/') . '/api/v1/job/' . $requestId;

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'from-domain' => self::getFromDomain(),
            'license-key' => Config::get('license_key') ?? null
        ];

        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        if ($uidd) {
            $headers['uidd'] = $uidd;
        }

        // Only disable SSL verification for localhost/development
        $is_localhost = (defined('GEN_WAVE_SMART_API') &&
                        (strpos(GEN_WAVE_SMART_API, 'localhost') !== false ||
                         strpos(GEN_WAVE_SMART_API, '127.0.0.1') !== false ||
                         strpos(GEN_WAVE_SMART_API, '.local') !== false));

        $response = wp_remote_get($url, [
            'headers' => $headers,
            'timeout' => 30,
            'sslverify' => !$is_localhost // Enable SSL verification in production
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => true,
                'message' => 'Failed to check job status: ' . $response->get_error_message()
            ];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            return [
                'success' => false,
                'error' => true,
                'message' => "Job status check failed with code: $response_code"
            ];
        }

        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => true,
                'message' => 'Failed to decode job status response'
            ];
        }

        return [
            'success' => true,
            'data' => $decoded
        ];
    }

    /**
     * Get job results from LiteLLM API.
     *
     * @param string $requestId The LiteLLM request ID.
     * @param string $token The authentication token.
     * @param string $uidd The user UUID.
     * @return array The job results response.
     */
    public function getLiteLLMJobResults($requestId, $token = null, $uidd = null) {
        if (!defined('GEN_WAVE_SMART_API')) {
            return [
                'success' => false,
                'error' => true,
                'message' => 'LiteLLM API URL not configured'
            ];
        }

        $url = rtrim(GEN_WAVE_SMART_API, '/') . '/api/v1/job/' . $requestId . '/results';

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'from-domain' => self::getFromDomain(),
            'license-key' => Config::get('license_key') ?? null
        ];

        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        if ($uidd) {
            $headers['uidd'] = $uidd;
        }

        // Only disable SSL verification for localhost/development
        $is_localhost = (defined('GEN_WAVE_SMART_API') &&
                        (strpos(GEN_WAVE_SMART_API, 'localhost') !== false ||
                         strpos(GEN_WAVE_SMART_API, '127.0.0.1') !== false ||
                         strpos(GEN_WAVE_SMART_API, '.local') !== false));

        $response = wp_remote_get($url, [
            'headers' => $headers,
            'timeout' => 30,
            'sslverify' => !$is_localhost // Enable SSL verification in production
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => true,
                'message' => 'Failed to get job results: ' . $response->get_error_message()
            ];
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($response_code !== 200) {
            return [
                'success' => false,
                'error' => true,
                'message' => "Job results failed with code: $response_code"
            ];
        }

        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => true,
                'message' => 'Failed to decode job results response'
            ];
        }

        // Transform results to match Laravel format
        return $this->transformLiteLLMResponse($decoded);
    }

    /**
     * Wrapper function for backward compatibility with old signature
     *
     * @param array|object $posts_or_request_data Either array of posts or full request_data object
     * @param string|null $language Language for generation
     * @param string|null $generation_method Generation method (title/description/short_description)
     * @param int|null $length Content length
     * @param string|null $type Request type (single/bulk)
     * @param bool|null $async Async flag
     * @return array Response from LiteLLM API
     */
    public function callLiteLLMStreaming($posts_or_request_data, $language = null, $generation_method = null, $length = null, $type = 'single', $async = false, $custom_instructions = null) {
        // If first parameter is already formatted request_data, use it directly
        if (is_array($posts_or_request_data) && isset($posts_or_request_data['posts'])) {
            return $this->callLiteLLMStreamingInternal($posts_or_request_data);
        }

        // Otherwise, build request_data from parameters (backward compatibility)
        $selected_option = $generation_method ?? 'description';

        // Build instructions as a dictionary for Python (field => instruction text)
        $instructions_dict = [];
        if (!empty($custom_instructions)) {
            $instructions_dict[$selected_option] = $custom_instructions;
        }

        $request_data = [
            'request_id' => time() . wp_rand(1000, 9999),
            'posts' => is_array($posts_or_request_data) ? $posts_or_request_data : [$posts_or_request_data],
            'language' => $language ?? 'English',
            'provider' => 'openai',
            'model' => 'gpt-4o-mini',
            'selectedOptions' => [$selected_option],
            'customInstructions' => $custom_instructions, // Pass raw custom instructions
            'generateOptions' => [
                'length' => $length ?? 1000,
                'type' => $type ?? 'single',
                'instructions' => $instructions_dict // Pass as dict for Python
            ]
        ];

        return $this->callLiteLLMStreamingInternal($request_data);
    }

    /**
     * Internal function - Call LiteLLM Streaming API with proper authentication and error handling
     *
     * @param array $request_data The streaming request data
     * @return array Response from LiteLLM API
     */
    private function callLiteLLMStreamingInternal($request_data) {
        try {
            $start_time = microtime(true); // ⏱️ Start timing

            // LiteLLM API endpoint - choose based on post count
            // Single posts use immediate /generate-single endpoint (NO streaming)
            // Bulk posts use streaming /generate-stream endpoint
            $is_single_post = (isset($request_data['posts']) && count($request_data['posts']) === 1);
            $endpoint = $is_single_post ? '/api/v1/generate-single' : '/api/v1/generate-stream';

            $litellm_url = defined('GEN_WAVE_SMART_API')
                ? GEN_WAVE_SMART_API . $endpoint
                : 'https://api.genwave.ai' . $endpoint; // Fallback to production


            // Prepare posts data for LiteLLM (enrich with WordPress data)
            $litellm_posts = [];
            if (isset($request_data['posts']) && is_array($request_data['posts'])) {
                foreach ($request_data['posts'] as $post_data) {
                    // Check if $post_data is already a full post array (from PostTypeController)
                    // or just a post ID (from older code)
                    if (is_array($post_data) && isset($post_data['id'])) {
                        // Already formatted post data from PostTypeController
                        $litellm_posts[] = [
                            'id' => intval($post_data['id']),
                            'title' => $post_data['title'] ?? '',
                            'content' => $post_data['content'] ?? '',
                            'post_type' => $post_data['post_type'] ?? 'post',
                            'featured_image' => $post_data['featured_image'] ?? null,
                            'excerpt' => $post_data['excerpt'] ?? ''
                        ];
                    } else {
                        // Legacy: just a post ID, need to fetch post data
                        $post_id = is_numeric($post_data) ? intval($post_data) : $post_data;
                        $post = get_post($post_id);
                        if ($post) {
                            $litellm_posts[] = [
                                'id' => intval($post_id),
                                'title' => $post->post_title,
                                'content' => $post->post_content,
                                'post_type' => $post->post_type,
                                'excerpt' => $post->post_excerpt
                            ];
                        }
                    }
                }
            }

            // Get business context from My Business settings (gen-wave-pro)
            $business_context = $this->getBusinessContext();

            // 📋 LOG business context being sent to AI
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if (!empty($business_context)) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log("================================================================================");
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log("📋 BUSINESS CONTEXT SENT TO LITELLM API:");
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log("================================================================================");
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Debug mode only
                    error_log(print_r($business_context, true));
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log("================================================================================");
                } else {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log("📋 No business context to send (no checkboxes checked or no data)");
                }
            }

            // Prepare LiteLLM request payload (generateOptions already transformed in PostTypeController)
            // DEBUG: Log selectedOptions being sent
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Debug mode only
                error_log("Gen Wave: Building LiteLLM payload, selectedOptions from request_data: " . print_r($request_data['selectedOptions'] ?? ['description'], true));
            }

            $language = $request_data['language'] ?? 'en';
            $selected_options = $request_data['selectedOptions'] ?? ['description'];

            // Build instructions - if custom instructions provided, use as dict (for Python)
            // Otherwise use generic list for backward compatibility
            $custom_instructions = $request_data['customInstructions'] ?? '';
            if (!empty($custom_instructions)) {
                // Pass as dictionary: field => instruction text
                $instructions_payload = [];
                foreach ($selected_options as $option) {
                    $instructions_payload[$option] = $custom_instructions;
                }
            } else {
                // Generic instructions (list)
                $instructions_payload = [
                    'Generate high-quality content for WordPress posts',
                    'Focus on: ' . implode(', ', $selected_options)
                ];
            }

            $litellm_payload = [
                'request_id' => $request_data['request_id'],
                'posts' => $litellm_posts,
                'provider' => $request_data['provider'] ?? 'openai',
                'model' => $request_data['model'] ?? 'gpt-4',
                'language' => $language,  // Pass language as top-level field for Python
                'instructions' => $instructions_payload,
                'selectedOptions' => $selected_options,
                'businessContext' => $business_context
            ];

            // DEBUG: Log the full payload
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Debug mode only
                error_log("Gen Wave: Full LiteLLM payload selectedOptions: " . print_r($litellm_payload['selectedOptions'], true));
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Debug mode only
                error_log("Gen Wave: Custom instructions in payload: " . (!empty($custom_instructions) ? $custom_instructions : 'NONE'));
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Debug mode only
                error_log("Gen Wave: Instructions payload type: " . (is_array($instructions_payload) && !isset($instructions_payload[0]) ? 'DICT' : 'LIST'));
            }

            // Skip duplicate check for single posts (allow regeneration)
            if (count($litellm_posts) === 1) {
                $litellm_payload['skipDuplicateCheck'] = true;
            }

            // Add generateOptions if provided (already in correct camelCase format from PostTypeController)
            if (isset($request_data['generateOptions']) && !empty($request_data['generateOptions'])) {
                $litellm_payload['generateOptions'] = $request_data['generateOptions'];
            }


            // Make the API call - decrypt authentication before sending
            $encrypted_token = Config::get('token');
            $encrypted_uidd = Config::get('uidd');
            $license_key = Config::get('license_key');


            // Decrypt token and UUID using decryptToken (Laravel-compatible)
            $decrypted_token = $this->decryptToken($encrypted_token);
            $decrypted_uidd = $this->decryptToken($encrypted_uidd);

            // DEBUG: Log decryption results
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("Gen Wave Auth Debug:");
                error_log("  Encrypted token (first 50): " . substr($encrypted_token, 0, 50));
                error_log("  Decrypted token (first 50): " . ($decrypted_token ? substr($decrypted_token, 0, 50) : 'EMPTY/FAILED'));
                error_log("  Encrypted UIDD (first 50): " . substr($encrypted_uidd, 0, 50));
                error_log("  Decrypted UIDD: " . ($decrypted_uidd ?: 'EMPTY/FAILED'));
            }

            $headers = [
                'Content-Type' => 'application/json',
                'Accept' => $is_single_post ? 'application/json' : 'text/event-stream',
                'from-domain' => self::getFromDomain(),
                'server-ip' => isset($_SERVER['SERVER_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_ADDR'])) : 'unknown',
                'license-key' => $license_key ?? null
            ];

            if ($decrypted_token && $decrypted_uidd) {
                $headers['Authorization'] = 'Bearer ' . $decrypted_token;
                $headers['uidd'] = $decrypted_uidd;
            } else {
            }


            $request_start = microtime(true); // ⏱️ Time the HTTP request
            $response = wp_remote_post($litellm_url, [
                'headers' => $headers,
                'body' => json_encode($litellm_payload),
                'timeout' => 60, // Increased timeout for streaming
                'blocking' => true
            ]);
            $request_duration = microtime(true) - $request_start; // ⏱️ Calculate request time


            // Check for WordPress HTTP errors
            if (is_wp_error($response)) {
                return [
                    'error' => true,
                    'message' => 'HTTP Error: ' . $response->get_error_message()
                ];
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $response_headers = wp_remote_retrieve_headers($response);


            if ($response_code !== 200) {

                // Try to parse error message
                $error_data = json_decode($response_body, true);
                $error_message = 'Genwave API Error (Code: ' . $response_code . ')';

                if ($error_data) {

                    if (isset($error_data['message'])) {
                        $error_message .= ': ' . $error_data['message'];
                    } elseif (isset($error_data['detail'])) {
                        if (is_array($error_data['detail'])) {
                            $error_message .= ': ' . implode(', ', array_column($error_data['detail'], 'msg'));
                        } else {
                            $error_message .= ': ' . $error_data['detail'];
                        }
                    }
                }


                return [
                    'error' => true,
                    'message' => $error_message,
                    'response_code' => $response_code,
                    'raw_response' => $response_body
                ];
            }


            // Parse response based on endpoint type
            $parse_start = microtime(true); // ⏱️ Time the parsing

            if ($is_single_post) {
                // Parse JSON response from /generate-single endpoint
                $json_response = json_decode($response_body, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    return [
                        'error' => true,
                        'message' => 'Failed to parse JSON response: ' . json_last_error_msg()
                    ];
                }

                // Extract full data from response (including token_usage, job_request_id)
                // Format: {success: true, data: {results: [...], token_usage: {...}, job_request_id: ...}}
                if (isset($json_response['success']) && $json_response['success'] === true &&
                    isset($json_response['data'])) {

                    // Return the FULL data object (not just results)
                    // This includes: results, token_usage, job_request_id, etc.
                    $streaming_results = $json_response['data'];
                } else {
                    return [
                        'error' => true,
                        'message' => 'Invalid response format from /generate-single'
                    ];
                }
            } else {
                // Parse streaming response from /generate-stream endpoint
                $streaming_results = $this->parseStreamingResponse($response_body);
            }

            $parse_duration = microtime(true) - $parse_start; // ⏱️ Calculate parse time


            if (empty($streaming_results)) {
                return [
                    'error' => true,
                    'message' => 'No content generated from response'
                ];
            }

            $total_duration = microtime(true) - $start_time; // ⏱️ Total time

            return [
                'error' => false,
                'success' => true,
                'results' => $streaming_results,
                'message' => $is_single_post ? 'LiteLLM single post completed successfully' : 'LiteLLM streaming completed successfully'
            ];

        } catch (Exception $e) {
            return [
                'error' => true,
                'message' => 'Exception in LiteLLM API call: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get business context from My Business settings (gen-wave-pro)
     * Returns only fields where the "Include in AI requests" checkbox is checked
     *
     * @return array Business context data
     */
    private function getBusinessContext() {
        $business_context = [];

        // Get My Business settings from gen-wave-pro
        $settings = get_option('genwave_mybusiness_settings', []);

        if (empty($settings)) {
            return $business_context;
        }

        // Map of field names to their include checkbox names
        $field_mappings = [
            'business_name' => 'include_business_name',
            'business_type' => 'include_business_type',
            'business_description' => 'include_business_description',
            'target_audience' => 'include_target_audience',
            'brand_voice' => 'include_brand_voice',
            'keywords' => 'include_keywords',
        ];

        // Build context with only fields where include is checked
        foreach ($field_mappings as $field => $include_key) {
            if (!empty($settings[$include_key]) && !empty($settings[$field])) {
                $business_context[$field] = $settings[$field];
            }
        }

        return $business_context;
    }

    /**
     * 🔐 Decrypt AES-256-CBC encrypted token (same as Laravel ModifyToken middleware)
     */
    private function decryptToken($encrypted_base64) {
        if (empty($encrypted_base64)) {
            return '';
        }

        // Use the same secret key as Laravel and Python (from Config class)
        $secretKey = Config::get('encryption_key');
        if (!$secretKey) {
            return '';
        }


        // Generate IV the same way as Laravel: substr(hash('sha256', $secretKey), 0, 16)
        $iv = substr(hash('sha256', $secretKey), 0, 16);

        // Decode base64
        $encrypted_data = base64_decode($encrypted_base64);
        if ($encrypted_data === false) {
            return '';
        }


        // Decrypt using AES-256-CBC with OPENSSL_RAW_DATA flag (input is raw binary from base64_decode)
        $decrypted_base64 = openssl_decrypt($encrypted_data, 'AES-256-CBC', $secretKey, OPENSSL_RAW_DATA, $iv);

        if ($decrypted_base64 === false) {
            $error = openssl_error_string();
            return '';
        }


        // Laravel encrypts base64_encode($token), so we need to decode it to get the actual token
        $actual_token = base64_decode($decrypted_base64);
        if ($actual_token === false) {
            return '';
        }

        return $actual_token;
    }

    /**
     * Parse LiteLLM streaming response format (Server-Sent Events)
     *
     * @param string $response_body The raw streaming response
     * @return array Parsed content results including token_usage and job_request_id
     */
    private function parseStreamingResponse($response_body) {
        $content_results = [];
        $token_usage = null;
        $job_request_id = null;
        $results_array = [];
        $lines = explode("\n", $response_body);

        foreach ($lines as $line) {
            $line = trim($line);

            // Parse Server-Sent Events format: "data: {json}"
            if (strpos($line, 'data: ') === 0) {
                $json_data = substr($line, 6); // Remove "data: " prefix
                $data = json_decode($json_data, true);

                if ($data && isset($data['type'])) {

                    // Look for content in different event types
                    if ($data['type'] === 'post_complete' && isset($data['content'])) {
                        // Content from completed post (main format from Python streaming)
                        $post_content = [];

                        if (is_array($data['content'])) {
                            $post_content = $data['content'];
                        } else if (is_string($data['content'])) {
                            // Try to decode JSON string
                            $decoded = json_decode($data['content'], true);
                            if ($decoded && is_array($decoded)) {
                                $post_content = $decoded;
                            } else {
                                $post_content = ['content' => $data['content']];
                            }
                        } else {
                            $post_content = ['content' => $data['content']];
                        }

                        // Add to results array with post_id
                        $results_array[] = [
                            'content' => $post_content,
                            'post_id' => $data['post_id'] ?? null
                        ];

                        // Also merge into content_results for backward compatibility
                        $content_results = array_merge($content_results, $post_content);

                    } elseif ($data['type'] === 'image_completed' && isset($data['images'])) {
                        // Image generation completed event
                        $image_content = [
                            'images' => $data['images'],
                            'image_generation' => [
                                'count' => count($data['images']),
                                'message' => $data['message'] ?? 'Images generated'
                            ]
                        ];

                        // Add to results array with post_id
                        $results_array[] = [
                            'content' => $image_content,
                            'post_id' => $data['post_id'] ?? null
                        ];

                        // Also merge into content_results for backward compatibility
                        $content_results = array_merge($content_results, $image_content);

                    } elseif ($data['type'] === 'content' && isset($data['content'])) {
                        // Legacy content format (fallback)
                        if (is_array($data['content'])) {
                            $content_results = array_merge($content_results, $data['content']);
                        }
                    } elseif ($data['type'] === 'complete') {
                        // Final completion event - contains token_usage and job_request_id
                        if (isset($data['token_usage'])) {
                            $token_usage = $data['token_usage'];
                        }
                        if (isset($data['job_request_id'])) {
                            $job_request_id = $data['job_request_id'];
                        }
                        if (isset($data['token_balance'])) {
                            if (!$token_usage) $token_usage = [];
                            $token_usage['tokens_balance'] = $data['token_balance'];
                        }
                    }
                }
            }
        }

        // Return structured response similar to /generate-single
        return [
            'results' => !empty($results_array) ? $results_array : [['content' => $content_results]],
            'token_usage' => $token_usage,
            'job_request_id' => $job_request_id,
            'token_balance' => $token_usage['tokens_balance'] ?? null
        ];
    }
}
