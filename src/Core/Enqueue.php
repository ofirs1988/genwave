<?php
/**
 * Enqueue class for managing WordPress scripts and styles
 *
 * Enhanced with conditional loading:
 * - frontend.js is loaded ONLY when there are active requests in the database
 * - Active requests = status NOT IN ('completed', 'failed')
 * - This optimizes performance by avoiding unnecessary polling when no active jobs exist
 */

namespace GenWavePlugin\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Enqueue {
    public function __construct() {
        // Hook into the frontend securely
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
    }

    /**
     * Check if there are active requests in the database
     * Active requests are those with status other than 'completed' or 'failed'
     *
     * @return bool True if there are active requests, false otherwise
     */
    private function hasActiveRequests() {
        global $wpdb;

        // Check if the table exists first
        $table_name = $wpdb->prefix . 'gen_requests';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to check if plugin table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $wpdb->esc_like($table_name)
        ));

        if (!$table_exists) {
            return false;
        }

        // Query for active requests (not completed and not failed)
        // Active statuses include: pending, processing, queued, running, paused, etc.
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is safe (uses $wpdb->prefix)
        $active_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name}
             WHERE status NOT IN (%s, %s, %s)
             AND deleted_at IS NULL",
            'completed',
            'failed',
            'cancelled'
        ));
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

        return (int) $active_count > 0;
    }

    public function enqueueScripts($hook) {
        // Only load on Genwave plugin pages and post/product edit screens
        $screen = get_current_screen();
        $is_edit_screen = $screen && in_array($screen->base, ['post', 'page']) && in_array($screen->post_type, ['post', 'page', 'product']);

        // The Genwave screens are one app with its own bundle (AdminApp); the
        // libraries below are only for the AI box on edit screens.
        if (AdminApp::isAppPage() || !$is_edit_screen) {
            return;
        }

        // Enqueue Bootstrap CSS (bundled locally for WordPress.org compliance)
        wp_enqueue_style('bootstrap-css', GEN_WAVE_ASSETS_URL . '/vendor/bootstrap/bootstrap.min.css', [], '5.3.0');

        // Enqueue metabox CSS and JS on post/product edit screens
        if ($is_edit_screen) {
            // Enqueue metabox styles
            wp_enqueue_style('gen-wave-metabox-style', GEN_WAVE_ASSETS_URL . '/css/metabox.css', [], GEN_WAVE_VERSION);

            // Enqueue metabox scripts
            wp_enqueue_script('gen-wave-metabox-script', GEN_WAVE_ASSETS_URL . '/js/metabox.js', ['jquery', 'select2'], GEN_WAVE_VERSION, true);

            // Get the current post ID
            global $post;
            $post_id = isset($post->ID) ? $post->ID : 0;

            // Localize script with nonces and post ID
            wp_localize_script('gen-wave-metabox-script', 'genwaveMetabox', [
                'postId' => $post_id,
                'generateNonce' => wp_create_nonce('genwave_generate_nonce'),
                'markConvertedNonce' => wp_create_nonce('genwave_mark_converted_nonce'),
            ]);
        }

        // Enqueue SweetAlert2 CSS (bundled locally for WordPress.org compliance)
        wp_enqueue_style('sweetalert2-css', GEN_WAVE_ASSETS_URL . '/vendor/sweetalert2/sweetalert2.min.css', [], '11');

        // Enqueue Font Awesome (bundled locally for WordPress.org compliance)
        wp_enqueue_style('font-awesome', GEN_WAVE_ASSETS_URL . '/vendor/fontawesome/css/all.min.css', [], '6.0.0');

        // Enqueue Select2 (bundled locally for WordPress.org compliance)
        wp_enqueue_style('select2', GEN_WAVE_ASSETS_URL . '/vendor/select2/select2.min.css', [], '4.1.0');

        // Enqueue admin styles
        wp_enqueue_style('gen-wave-plugin-admin-style', GEN_WAVE_ASSETS_URL . '/css/admin.css', [], GEN_WAVE_VERSION);

        // Enqueue Bootstrap JS (bundled locally for WordPress.org compliance)
        wp_enqueue_script('bootstrap-js', GEN_WAVE_ASSETS_URL . '/vendor/bootstrap/bootstrap.bundle.min.js', [], '5.3.0', true);

        // Enqueue SweetAlert2 JS (bundled locally for WordPress.org compliance)
        wp_enqueue_script('sweetalert2-js', GEN_WAVE_ASSETS_URL . '/vendor/sweetalert2/sweetalert2.all.min.js', [], '11', true);

        // Enqueue Select2 JS (bundled locally for WordPress.org compliance)
        wp_enqueue_script('select2', GEN_WAVE_ASSETS_URL . '/vendor/select2/select2.min.js', ['jquery'], '4.1.0', true);

        // Enqueue admin scripts
        wp_enqueue_script('gen-wave-plugin-admin-script', GEN_WAVE_ASSETS_URL .'/js/admin.js', ['jquery'], GEN_WAVE_VERSION, true);

        // The browser never holds credentials: generation runs through
        // server-side AJAX, which signs requests from Config directly.
        // Localize script to pass PHP data to JS
        wp_localize_script('gen-wave-plugin-admin-script', 'genwave_admin_data', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('verify_login_nonce'),
            'refresh_credits_nonce' => wp_create_nonce('refresh_credits_nonce'),
            'disconnect_account_nonce' => wp_create_nonce('disconnect_account_nonce'),
            'genwave_nonce' => wp_create_nonce('genwave_nonce'),
        ]);
    }
}
