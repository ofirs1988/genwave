<?php

namespace GenWavePlugin\Handlers;

if (!defined('ABSPATH')) {
    exit;
}

use GenWavePlugin\Core\ApiManager;
use GenWavePlugin\Core\Config;

/**
 * PluginsHandler — backs the in-WP "Plugins" marketplace page.
 *
 * Talks to the Laravel dashboard's GET /api/plugins to list available
 * GenWave plugins, then installs them via WP_Upgrader on demand.
 */
class PluginsHandler
{
    private const LIST_CACHE_KEY = 'genwave_plugin_marketplace_list';
    private const LIST_CACHE_TTL = 300; // 5 minutes

    /**
     * Slugs to hide from the marketplace page. These products are retired, so we
     * filter them out locally regardless of what the dashboard still lists.
     */
    private const HIDDEN_SLUGS = [
        'gen-wave-pro',
        'genwave-seo',
        'genwave-transfer',
    ];

    /**
     * AJAX: return list of available GenWave plugins augmented with local status.
     */
    public function handle_list_plugins(): void
    {
        if (! $this->verify_admin_request()) {
            return;
        }

        $force_refresh = isset($_POST['force_refresh']) && $_POST['force_refresh'] === '1';
        $remote = $this->fetch_remote_list($force_refresh);

        if (! $remote['ok']) {
            wp_send_json_error([
                'message' => $remote['message'] ?? __('Failed to fetch plugin list', 'gen-wave'),
            ]);
            return;
        }

        $visible = array_filter(
            $remote['plugins'],
            static function ($p) {
                return ! in_array($p['slug'] ?? '', self::HIDDEN_SLUGS, true);
            }
        );

        $augmented = array_map([$this, 'augment_with_local_status'], array_values($visible));

        wp_send_json_success([
            'plugins' => $augmented,
        ]);
    }

    /**
     * AJAX: install (and activate) a single plugin by slug.
     */
    public function handle_install_plugin(): void
    {
        if (! $this->verify_admin_request()) {
            return;
        }

        $slug = isset($_POST['slug']) ? sanitize_key(wp_unslash($_POST['slug'])) : '';
        if ($slug === '') {
            wp_send_json_error(['message' => __('Missing plugin slug', 'gen-wave')]);
            return;
        }

        // Pull a fresh list to get a non-expired signed download_url
        $remote = $this->fetch_remote_list(true);
        if (! $remote['ok']) {
            wp_send_json_error([
                'message' => $remote['message'] ?? __('Could not get download URL', 'gen-wave'),
            ]);
            return;
        }

        $target = null;
        foreach ($remote['plugins'] as $p) {
            if (($p['slug'] ?? '') === $slug) {
                $target = $p;
                break;
            }
        }
        if (! $target || empty($target['download_url'])) {
            wp_send_json_error(['message' => __('Plugin not available for download', 'gen-wave')]);
            return;
        }

        $install_result = $this->install_and_activate($slug, $target['download_url']);
        if (! $install_result['ok']) {
            wp_send_json_error(['message' => $install_result['message']]);
            return;
        }

        wp_send_json_success([
            'message' => __('Plugin installed and activated', 'gen-wave'),
            'plugin_file' => $install_result['plugin_file'] ?? null,
            'activated' => $install_result['activated'] ?? false,
        ]);
    }

    /**
     * Common admin-request guard. Returns false and sends a JSON error if blocked.
     */
    private function verify_admin_request(): bool
    {
        $nonce = isset($_POST['security']) ? sanitize_text_field(wp_unslash($_POST['security'])) : '';
        if (! wp_verify_nonce($nonce, 'genwave_plugins_nonce')) {
            wp_send_json_error(['message' => __('Security check failed', 'gen-wave')], 403);
            return false;
        }

        if (! current_user_can('install_plugins')) {
            wp_send_json_error(['message' => __('You do not have permission to install plugins', 'gen-wave')], 403);
            return false;
        }

        return true;
    }

    /**
     * Call GET /api/plugins on the dashboard. Caches the response in a transient.
     *
     * @return array{ok:bool, plugins?:array, message?:string}
     */
    private function fetch_remote_list(bool $force_refresh): array
    {
        if (! $force_refresh) {
            $cached = get_transient(self::LIST_CACHE_KEY);
            if (is_array($cached) && isset($cached['plugins'])) {
                return ['ok' => true, 'plugins' => $cached['plugins']];
            }
        }

        $token = Config::get('token');
        $uidd = Config::get('uidd');
        $license_key = Config::get('license_key');

        if (empty($token) || empty($uidd) || empty($license_key)) {
            return [
                'ok' => false,
                'message' => __('Connect your Genwave account first', 'gen-wave'),
            ];
        }

        $url = rtrim(GENWAVE_API_URL, '/') . '/api/plugins';
        $is_localhost = preg_match('#(localhost|127\.0\.0\.1|\.local)#', $url) === 1;

        $response = wp_remote_get($url, [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
                'uidd' => $uidd,
                'license-key' => $license_key,
                'from-domain' => ApiManager::getFromDomain(),
                'server-ip' => isset($_SERVER['SERVER_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_ADDR'])) : 'unknown',
            ],
            'sslverify' => ! $is_localhost,
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            return ['ok' => false, 'message' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 || ! is_array($body) || empty($body['plugins'])) {
            $msg = is_array($body) && ! empty($body['message'])
                ? $body['message']
                : sprintf(__('Dashboard returned HTTP %d', 'gen-wave'), $code);
            return ['ok' => false, 'message' => $msg];
        }

        set_transient(self::LIST_CACHE_KEY, $body, self::LIST_CACHE_TTL);

        return ['ok' => true, 'plugins' => $body['plugins']];
    }

    /**
     * Add installed/active status + current installed version to a remote entry.
     */
    private function augment_with_local_status(array $plugin): array
    {
        $slug = $plugin['slug'] ?? '';
        $local = $this->find_installed_plugin($slug);

        $plugin['installed'] = $local !== null;
        $plugin['active'] = $local !== null ? is_plugin_active($local['plugin_file']) : false;
        $plugin['installed_version'] = $local['version'] ?? null;
        $plugin['plugin_file'] = $local['plugin_file'] ?? null;
        $plugin['update_available'] = ($local && isset($plugin['version']))
            ? version_compare($local['version'], $plugin['version'], '<')
            : false;

        // Strip the signed download_url from the public response — it's only
        // needed for the install action, which re-fetches the list fresh.
        unset($plugin['download_url']);

        return $plugin;
    }

    /**
     * Find a plugin in wp-content/plugins by slug (folder name match).
     */
    private function find_installed_plugin(string $slug): ?array
    {
        if (! function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all = get_plugins();

        foreach ($all as $plugin_file => $data) {
            $folder = strtok($plugin_file, '/');
            if ($folder === $slug) {
                return [
                    'plugin_file' => $plugin_file,
                    'version' => $data['Version'] ?? null,
                ];
            }
        }

        return null;
    }

    /**
     * Download + install + activate via WP_Upgrader.
     *
     * @return array{ok:bool, message?:string, plugin_file?:string, activated?:bool}
     */
    private function install_and_activate(string $slug, string $download_url): array
    {
        if (! function_exists('request_filesystem_credentials')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (! class_exists('Plugin_Upgrader')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        }
        if (! class_exists('WP_Ajax_Upgrader_Skin')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader-skins.php';
        }
        if (! function_exists('activate_plugin')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // If already installed, don't re-download.
        $existing = $this->find_installed_plugin($slug);
        if ($existing) {
            $activated = false;
            if (! is_plugin_active($existing['plugin_file'])) {
                $err = activate_plugin($existing['plugin_file']);
                if (is_wp_error($err)) {
                    return ['ok' => false, 'message' => $err->get_error_message()];
                }
                $activated = true;
            }
            return [
                'ok' => true,
                'plugin_file' => $existing['plugin_file'],
                'activated' => $activated,
            ];
        }

        $upgrader = new \Plugin_Upgrader(new \WP_Ajax_Upgrader_Skin());
        $result = $upgrader->install($download_url);

        if (is_wp_error($result)) {
            return ['ok' => false, 'message' => $result->get_error_message()];
        }
        if ($result === false) {
            $skin_errors = $upgrader->skin->get_error_messages();
            return [
                'ok' => false,
                'message' => $skin_errors ? implode('; ', $skin_errors) : __('Install failed', 'gen-wave'),
            ];
        }

        // Locate the new plugin file post-install.
        $installed = $this->find_installed_plugin($slug);
        if (! $installed) {
            return ['ok' => false, 'message' => __('Installed but plugin folder not found', 'gen-wave')];
        }

        $err = activate_plugin($installed['plugin_file']);
        if (is_wp_error($err)) {
            return [
                'ok' => true,
                'plugin_file' => $installed['plugin_file'],
                'activated' => false,
                'message' => $err->get_error_message(),
            ];
        }

        // Invalidate the list cache so the UI reflects the new state on refresh.
        delete_transient(self::LIST_CACHE_KEY);

        return [
            'ok' => true,
            'plugin_file' => $installed['plugin_file'],
            'activated' => true,
        ];
    }
}
