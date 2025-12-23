<?php

namespace GenWavePlugin\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

use GenWavePlugin\Global\Config;

class VerifyLoginController
{
    public static function verifyLogin() {
        // Verify login request
        check_ajax_referer('verify_login_nonce', 'security');
        // Continue processing the request
        $license_key = Config::get('license_key');
        if ($license_key) {
            $login_url = self::getUrl();
            wp_send_json_success(['success' => true , 'redirect' => $login_url  , 'message' => 'Invalid license key.' ]);
        } else {
            wp_send_json_error(['success' => false , 'message' => 'Invalid license key.'] );
        }

        wp_die(); // End the process after sending response
    }

    public static function redirectLogin()
    {
        $login_url = self::getUrl();
        // Use wp_safe_redirect with allowed_redirect_hosts filter for external URLs
        add_filter('allowed_redirect_hosts', function($hosts) {
            $hosts[] = wp_parse_url(GENWAVE_API_URL, PHP_URL_HOST);
            return $hosts;
        });
        wp_safe_redirect($login_url);
        exit;
    }

    /**
     * @return string
     */
    public static function getUrl(): string
    {
        $redirect_back_url = admin_url('admin.php?page=gen-wave-plugin-settings');
        $server_domain = get_site_url();
        $license_key = Config::get('license_key');

        // TRY NEW SECURE METHOD FIRST
        try {
            $api_manager = new \GenWavePlugin\Global\ApiManager(GENWAVE_API_URL);
            $response = $api_manager->postSecure('/integration/initiate', [  // Fixed: removed /api prefix
                'license_key' => $license_key,
                'domain' => $server_domain,
                'redirect' => $redirect_back_url,
                'action' => 'integration',
            ]);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log,WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Debug mode only
                error_log('Integration initiate response: ' . print_r($response, true));
            }

            // If successful, Laravel returns a login_url with session_id (safe!)
            if (isset($response['success']) && $response['success'] === true && isset($response['login_url'])) {
                return $response['login_url'];
            }
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('Integration initiate failed: ' . $e->getMessage());
            }
        }

        // FALLBACK TO OLD METHOD (will be removed later)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
            error_log('Using fallback old method for integration');
        }
        $laravel_login_url = GENWAVE_API_URL . '/login';
        $original_url = isset($_SERVER['REQUEST_URI']) ? urlencode(sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']))) : '';

        $login_url = $laravel_login_url . '?redirect=' . urlencode($redirect_back_url)
            . '&redirect_url=' . $original_url
            . '&action=' . 'integration'
            . '&license_key=' . urlencode($license_key)
            . '&domain=' . urlencode($server_domain);

        return $login_url;
    }
}