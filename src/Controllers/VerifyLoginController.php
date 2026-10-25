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

        // Only a site administrator may obtain the Genwave auto-login URL — it
        // carries a live session into the OWNER's SaaS account. Without this an
        // editor/contributor (or, via the nopriv hook, an unauthenticated caller)
        // who has the nonce could take over the owner's Genwave account (F3).
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if (!Config::get('license_key')) {
            wp_send_json_error(['success' => false, 'message' => __('Enter your license key first.', 'gen-wave')]);
        }

        // v2: connect directly. The panel proves ownership with an HMAC challenge
        // back to this site and returns the per-site signing key — no dashboard
        // round-trip. Keep the response shape (redirect on success) so the existing
        // admin UI simply reloads into the connected state.
        $result = \GenWavePlugin\Core\AgentAuth::connect();
        if (!empty($result['success'])) {
            wp_send_json_success([
                'success'  => true,
                'redirect' => admin_url('admin.php?page=gen-wave-plugin-settings&connected=1'),
                'message'  => $result['message'],
            ]);
        }
        wp_send_json_error(['success' => false, 'message' => $result['message']]);

        wp_die(); // End the process after sending response
    }

}