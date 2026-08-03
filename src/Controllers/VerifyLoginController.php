<?php

namespace GenWavePlugin\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

use GenWavePlugin\Core\Config;

class VerifyLoginController
{
    public static function verifyLogin() {
        // Verify login request
        check_ajax_referer('verify_login_nonce', 'security');

        // Only a site administrator may obtain the GenWave auto-login URL — it
        // carries a live session into the OWNER's SaaS account. Without this an
        // editor/contributor (or, via the nopriv hook, an unauthenticated caller)
        // who has the nonce could take over the owner's GenWave account (F3).
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['success' => false, 'message' => 'Unauthorized'], 403);
        }

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

        // Anti-CSRF / session-fixation for the connect callback (F5): mint a
        // one-time state token, tie it to THIS admin, and hand it to the
        // dashboard so it can echo it back on the credentials_session callback.
        // handleCallback() rejects any callback whose state we did not issue.
        $state = wp_generate_password(32, false, false);
        set_transient('gw_connect_state_' . get_current_user_id(), $state, 300);

        // TRY NEW SECURE METHOD FIRST
        try {
            $api_manager = new \GenWavePlugin\Core\ApiManager(GENWAVE_API_URL);
            $response = $api_manager->postSecure('/integration/initiate', [  // Fixed: removed /api prefix
                'license_key' => $license_key,
                'domain' => $server_domain,
                'redirect' => $redirect_back_url,
                'action' => 'integration',
                'state' => $state,
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

        // The legacy fallback put license_key/domain in the login URL and made the
        // dashboard hand the SSO token/uuid back in the redirect URL (findings
        // F1/F6). It is removed. If the secure initiate above did not return a
        // login_url, fail CLOSED — send the admin back to settings with an error
        // rather than fall back to a flow that exposes credentials in the URL.
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
            error_log('Secure integration initiate did not return a login_url; failing closed.');
        }

        return add_query_arg(
            'error',
            'Could not start a secure connection to GenWave. Please try again.',
            $redirect_back_url
        );
    }
}