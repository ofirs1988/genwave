<?php

namespace GenWavePlugin\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

use GenWavePlugin\Global\Config;

class DisconnectController
{
    /**
     * Disconnect from Laravel - remove all credentials except license_key
     */
    public static function disconnect()
    {
        try {
            // Get current credentials before deleting
            $licenseKey = Config::get('license_key');
            $domain = Config::get('domain');

            // Notify Laravel about the disconnection
            if (!empty($licenseKey) && !empty($domain)) {
                $apiUrl = defined('GENWAVE_API_URL') ? GENWAVE_API_URL . '/wordpress/disconnect' : 'https://account.genwave.ai/api/wordpress/disconnect';

                $response = wp_remote_post($apiUrl, [
                    'body' => [
                        'license_key' => $licenseKey,
                        'domain' => $domain,
                    ],
                    'timeout' => 15,
                    'sslverify' => true,
                ]);

                if (is_wp_error($response)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                        error_log('DisconnectController: Failed to notify Laravel - ' . $response->get_error_message());
                    }
                } else {
                    $body = wp_remote_retrieve_body($response);
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                        error_log('DisconnectController: Laravel notified - ' . $body);
                    }
                }
            }

            // Remove all integration credentials except license_key
            Config::delete('token');
            Config::delete('uidd');
            Config::delete('domain');
            Config::delete('active');
            Config::delete('plan');
            Config::delete('tokens');

            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('DisconnectController: Successfully disconnected from Laravel');
            }

            return [
                'success' => true,
                'message' => 'Successfully disconnected from your account.',
            ];
        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('DisconnectController: Error disconnecting - ' . $e->getMessage());
            }

            return [
                'success' => false,
                'message' => 'Error disconnecting: ' . $e->getMessage(),
            ];
        }
    }
}
