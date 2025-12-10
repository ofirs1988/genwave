<?php
namespace GenWavePlugin\Controllers;

use GenWavePlugin\Global\ApiManager;
use GenWavePlugin\Global\Config;

class IntegrationCallbackController
{
    /**
     * Handle the callback from Laravel after successful login
     * This retrieves credentials from session using credentials_session ID
     */
    public static function handleCallback()
    {
        // Check if this is a callback with credentials_session
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a callback from external OAuth-like flow
        if (!isset($_GET['credentials_session'])) {
            return false;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is a callback from external OAuth-like flow
        $credentials_session = sanitize_text_field(wp_unslash($_GET['credentials_session']));


        // Retrieve credentials from Laravel using the session ID
        $api_manager = new ApiManager(GENWAVE_API_URL);
        $response = $api_manager->postSecure('/integration/retrieve-credentials', [  // Fixed: removed /api prefix
            'credentials_session' => $credentials_session,
        ]);


        // Check if retrieval was successful
        if (!isset($response['success']) || $response['success'] !== true) {
            // Handle error
            $error_message = $response['error'] ?? 'Failed to retrieve credentials';
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('Credential retrieval failed: ' . $error_message);
            }

            // Show error to user
            add_action('admin_notices', function() use ($error_message) {
                ?>
                <div class="notice notice-error is-dismissible">
                    <p><strong>Authentication Error:</strong> <?php echo esc_html($error_message); ?></p>
                </div>
                <?php
            });

            return false;
        }

        // Log what we received

        // Store the credentials securely
        Config::set('token', $response['token']);
        Config::set('uidd', $response['uuid']);
        Config::set('license_key', $response['license_key']);
        Config::set('domain', $response['domain']);
        Config::set('active', $response['active']);
        Config::set('plan', $response['plan']);
        Config::set('tokens', $response['tokens']);


        // Success message
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><strong>Success!</strong> You are now connected to your Gen Wave account.</p>
            </div>
            <?php
        });

        // Clean redirect (remove credentials_session from URL)
        $clean_url = \remove_query_arg('credentials_session');
        \wp_safe_redirect($clean_url);
        exit;
    }
}
