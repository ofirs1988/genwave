<?php

namespace GenWavePlugin\Handlers;

if (!defined('ABSPATH')) {
    exit;
}

use GenWavePlugin\Core\AgentAuth;
use GenWavePlugin\Core\ApiManager;
use GenWavePlugin\Core\Config;
use GenWavePlugin\Core\Links;

/**
 * PluginsHandler — backs the in-WP "Plugins" page.
 *
 * Lists the Genwave plugins with where each stands on this site. Installing is
 * done from the customer's Genwave account (Core\SiteCommands, Links::install):
 * a plugin hosted on WordPress.org may not install plugins from other servers
 * itself (guideline 8). A plugin already here is activated the usual way.
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
     * AJAX: return list of available Genwave plugins augmented with local status.
     */
    public function handle_list_plugins(): void
    {
        if (! $this->verify_admin_request()) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce is verified in verify_admin_request() above.
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
            wp_send_json_error(['message' => __('You do not have permission to manage plugins', 'gen-wave')], 403);
            return false;
        }

        return true;
    }

    /**
     * GET /api/plugins on app.genwave.ai (follows GENWAVE_PANEL_URL). Cached in a transient.
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

        // Sites connected the current way hold site_uid + site_key and never
        // receive the old token/uidd. Requiring those here told every recently
        // connected customer "Connect your Genwave account first" on a site that
        // was connected, so this page could not install the agent for them.
        // The dashboard's list is open to every site; the headers below only
        // identify the caller for its logs.
        $token = Config::get('token');
        $uidd = Config::get('uidd');
        $license_key = Config::get('license_key');
        $legacy_connected = ! empty($token) && ! empty($uidd);

        if (! AgentAuth::isConnected() && ! $legacy_connected) {
            return [
                'ok' => false,
                'message' => __('Please connect your Genwave account on the Account page first.', 'gen-wave'),
            ];
        }

        $url = AgentAuth::panelUrl() . '/api/plugins';
        $is_localhost = preg_match('#(localhost|127\.0\.0\.1|\.local)#', $url) === 1;

        $headers = [
            'Accept' => 'application/json',
            'from-domain' => ApiManager::getFromDomain(),
            'server-ip' => isset($_SERVER['SERVER_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_ADDR'])) : 'unknown',
        ];
        if (! empty($license_key)) {
            $headers['license-key'] = $license_key;
        }
        if ($legacy_connected) {
            $headers['Authorization'] = 'Bearer ' . $token;
            $headers['uidd'] = $uidd;
        }

        $response = wp_remote_get($url, [
            'headers' => $headers,
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
                // translators: %d is the HTTP status code returned by the Genwave dashboard.
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

        // Install from the Genwave account, or the zip to upload by hand; a
        // plugin already here is activated with WordPress's own link.
        unset($plugin['download_url']);
        $plugin['install_url'] = Links::install($slug);
        $plugin['zip_url'] = Links::download($slug);
        $plugin['activate_url'] = ($local && ! $plugin['active'] && current_user_can('activate_plugins'))
            ? add_query_arg(
                [
                    'action'   => 'activate',
                    'plugin'   => rawurlencode($local['plugin_file']),
                    '_wpnonce' => wp_create_nonce('activate-plugin_' . $local['plugin_file']),
                ],
                admin_url('plugins.php')
            )
            : null;

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
}
