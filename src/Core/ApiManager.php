<?php

namespace GenWavePlugin\Core;

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
                error_log('Genwave API Error: ' . $response->get_error_message());
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
     * Set the API base URL.
     *
     * @param string $url The new base URL for the API.
     */
    public function setApiBaseUrl($url) {
        $this->api_base_url = rtrim($url, '/');
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

}
