<?php

namespace GenWavePlugin\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

use GenWavePlugin\Core\Config;
use GenWavePlugin\Core\AgentAuth;

class DisconnectController
{
    /**
     * Disconnect the site - remove the panel's domain verification and clear all
     * local credentials except the license key (so re-connecting is one click).
     */
    public static function disconnect()
    {
        try {
            // v2: tell the panel to drop this domain's verification (verify_domain /
            // wp_connected → 0) and clear the local signing keys (site_uid/site_key).
            // Reads license_key + domain from Config, so run it before deleting them.
            AgentAuth::disconnect();

            // Remove the remaining (legacy) integration credentials; keep license_key.
            Config::delete('token');
            Config::delete('uidd');
            Config::delete('domain');
            Config::delete('active');
            Config::delete('plan');
            Config::delete('credits');

            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('DisconnectController: Successfully disconnected');
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
