<?php
/**
 * Enqueue class for managing WordPress scripts and styles
 *
 * Enhanced with conditional loading:
 * - frontend.js is loaded ONLY when there are active requests in the database
 * - Active requests = status NOT IN ('completed', 'failed')
 * - This optimizes performance by avoiding unnecessary polling when no active jobs exist
 */

namespace GenWavePlugin\Global;

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
        // Only load on Gen Wave plugin pages and post/product edit screens
        $screen = get_current_screen();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking page parameter for conditional loading
        $current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';
        $is_genwave_page = strpos($current_page, 'gen-wave') !== false;
        $is_edit_screen = $screen && in_array($screen->base, ['post', 'page']) && in_array($screen->post_type, ['post', 'page', 'product']);

        // Check if we're on Dashboard or Generate pages (need React bundle)
        $is_react_page = in_array($current_page, ['gen-wave-dashboard', 'gen-wave-generate']);

        // Exit early if not on a relevant page
        if (!$is_genwave_page && !$is_edit_screen) {
            return;
        }

        // Load React bundle for Dashboard and Generate pages
        if ($is_react_page) {
            $this->enqueueReactBundle();
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

        // Always enqueue streaming client for LiteLLM functionality
        wp_enqueue_script('gen-wave-plugin-streaming-client', GEN_WAVE_ASSETS_URL .'/js/streaming-client.js', ['jquery'], GEN_WAVE_VERSION, true);

        // Get user credentials from database
        $license_key = \GenWavePlugin\Global\Config::get('license_key');
        $uidd_encrypted = \GenWavePlugin\Global\Config::get('uidd');
        $token_encrypted = \GenWavePlugin\Global\Config::get('token');

        // Decrypt token and uidd
        $encryption = new \GenWavePlugin\Services\EncryptionService();
        $uidd = $uidd_encrypted ? $encryption->decrypt($uidd_encrypted) : '';
        $token = $token_encrypted ? $encryption->decrypt($token_encrypted) : '';

        // Add config for streaming client
        wp_localize_script('gen-wave-plugin-streaming-client', 'genwaveConfig', [
            'isDev' => $this->isDevelopmentEnvironment(),
            'isDebug' => defined('WP_DEBUG') && WP_DEBUG,
            'environment' => $this->getEnvironment(),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('genwave_admin_nonce'),
            'apiUrl' => $this->getApiUrl(),
            'domain' => $this->getCurrentDomain(),
            'useProxy' => $this->isDevelopmentEnvironment(),
            'litellmUrl' => defined('GEN_WAVE_SMART_API') ? GEN_WAVE_SMART_API : '',
            'useLiteLLM' => true,
            'token' => $token ?: '',
            'uidd' => $uidd ?: '',
            'licenseKey' => $license_key ?: ''
        ]);
        // Localize script to pass PHP data to JS
        wp_localize_script('gen-wave-plugin-admin-script', 'genwave_admin_data', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('verify_login_nonce'),
            'refresh_tokens_nonce' => wp_create_nonce('refresh_tokens_nonce'),
            'disconnect_account_nonce' => wp_create_nonce('disconnect_account_nonce'),
            'genwave_nonce' => wp_create_nonce('genwave_nonce'),
        ]);
    }

    /**
     * Check if current environment is development
     */
    private function isDevelopmentEnvironment() {
        // Method 1: Check WordPress constants
        if (defined('WP_DEBUG') && WP_DEBUG) {
            return true;
        }

        // Method 2: Check if it's a local development environment
        $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
        $localHosts = [
            'localhost',
            '127.0.0.1',
            '::1',
            '.local',
            '.test',
            '.dev',
            'wp.local',
            'ai.local'
        ];

        foreach ($localHosts as $localHost) {
            if (strpos($host, $localHost) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get current environment type
     */
    private function getEnvironment() {
        if (defined('WP_ENVIRONMENT_TYPE')) {
            return WP_ENVIRONMENT_TYPE;
        }

        if ($this->isDevelopmentEnvironment()) {
            return 'development';
        }

        $host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
        if (strpos($host, 'staging') !== false || strpos($host, 'dev') !== false) {
            return 'staging';
        }

        return 'production';
    }

    /**
     * Get API URL based on environment
     */
    private function getApiUrl() {
        // Allow override via constant for development
        if (defined('GENWAVE_API_URL')) {
            return GENWAVE_API_URL;
        }

        return 'https://account.genwave.ai/api';
    }

    /**
     * Get current domain for API calls
     */
    private function getCurrentDomain() {
        $domain = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';

        // Remove www. prefix if exists
        if (strpos($domain, 'www.') === 0) {
            $domain = substr($domain, 4);
        }

        return $domain;
    }

    /**
     * Enqueue React bundle for Dashboard and Generate pages
     */
    private function enqueueReactBundle() {
        $dist_path = GEN_WAVE_PATH . '/dist';
        $dist_url = GEN_WAVE_URL . '/dist';

        // Find the bundle files (they have content hash in filename)
        $js_files = glob($dist_path . '/bundle.*.js');
        $css_files = glob($dist_path . '/styles.*.css');

        // Enqueue CSS bundle if exists
        if (!empty($css_files)) {
            $css_file = basename($css_files[0]);
            wp_enqueue_style(
                'gen-wave-react-styles',
                $dist_url . '/' . $css_file,
                [],
                GEN_WAVE_VERSION
            );
        }

        // Enqueue JS bundle if exists
        if (!empty($js_files)) {
            $js_file = basename($js_files[0]);
            wp_enqueue_script(
                'gen-wave-react-bundle',
                $dist_url . '/' . $js_file,
                [],
                GEN_WAVE_VERSION,
                true
            );

            // Localize script with settings for React app
            // Note: wp_localize_script converts booleans to strings, so we use '1'/'0' and check in JS
            wp_localize_script(
                'gen-wave-react-bundle',
                'genwaveFreeSettings',
                [
                    'ajaxurl'        => admin_url('admin-ajax.php'),
                    'nonce'          => wp_create_nonce('genwave_nonce'),
                    'generateNonce'  => wp_create_nonce('genwave_generate_nonce'),
                    'isPro'          => '0',
                    'hasWooCommerce' => class_exists('WooCommerce') ? '1' : '0',
                ]
            );
        }
    }
}