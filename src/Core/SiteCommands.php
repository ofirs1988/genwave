<?php

namespace GenWavePlugin\Core;

if (!defined('ABSPATH')) {
    exit;
}

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Commands from the customer's Genwave account (app.genwave.ai) to this site.
 *
 * Genwave plugins are installed from the account, not from this dashboard.
 * WordPress.org guideline 8: a plugin hosted there may not install plugins from
 * other servers on its own; a management service that pushes software from its
 * own domain may. The customer clicks "Install" in their Genwave account, the
 * account sends a signed command here, and this route installs the plugin from
 * Genwave's download server and activates it.
 *
 * Signed with a key only this site and the account can derive:
 *   command_key = HMAC-SHA256(site_key, "genwave-site-command-v1")
 * kept apart from the key the site signs its own agent requests with, so a
 * signature made for one direction never counts for the other.
 *   canonical = "POST" \n "/gen-wave/v1/command" \n sha256_hex(body) \n timestamp \n nonce
 *   headers   X-GW-Timestamp, X-GW-Nonce, X-GW-Signature (base64)
 * Five minutes either way, each nonce once.
 * Body (JSON): { "site": <site_uid>, "op": "status" | "install", "slug": <plugin> }.
 *
 * Only Genwave's own plugins, only from cdn.genwave.ai: a command names one of
 * the slugs below, never a URL.
 */
class SiteCommands
{
    const NS = 'gen-wave/v1';
    const ROUTE = '/command';

    /** The plugins a command may install, and where each one comes from. */
    const PLUGINS = [
        'genwave-agent'    => 'https://cdn.genwave.ai/plugins/genwave-agent-latest.zip',
        'genwave-chatbot'  => 'https://cdn.genwave.ai/plugins/genwave-chatbot-latest.zip',
        'genwave-elements' => 'https://cdn.genwave.ai/plugins/genwave-elements-latest.zip',
    ];

    private const SKEW = 300;

    public static function register(): void
    {
        add_action('rest_api_init', [self::class, 'routes']);
    }

    public static function routes(): void
    {
        register_rest_route(self::NS, self::ROUTE, [
            'methods' => 'POST',
            'callback' => [self::class, 'handle'],
            'permission_callback' => [self::class, 'verify'],
        ]);
    }

    /** The key commands are signed with, from the site key (raw bytes in, raw bytes out). */
    public static function commandKey(string $siteKey): string
    {
        return hash_hmac('sha256', 'genwave-site-command-v1', $siteKey, true);
    }

    /** permission_callback: the signature, the time, the nonce and the site must all match. */
    public static function verify(WP_REST_Request $request)
    {
        $siteUid = (string) Config::get('site_uid');
        $siteKey = base64_decode((string) Config::get('site_key'), true);
        if ($siteUid === '' || $siteKey === false || $siteKey === '') {
            return new WP_Error('genwave_not_connected', __('This site is not connected to Genwave.', 'gen-wave'), ['status' => 409]);
        }

        $refused = new WP_Error('genwave_bad_signature', __('This command is not signed by your Genwave account.', 'gen-wave'), ['status' => 401]);
        $ts = (string) $request->get_header('x_gw_timestamp');
        $nonce = (string) $request->get_header('x_gw_nonce');
        $signature = (string) $request->get_header('x_gw_signature');
        if (!ctype_digit($ts) || abs(time() - (int) $ts) > self::SKEW || !preg_match('/^[A-Za-z0-9_-]{16,128}$/', $nonce) || $signature === '') {
            return $refused;
        }

        $body = $request->get_body();
        $canonical = 'POST' . "\n" . '/' . self::NS . self::ROUTE . "\n" . hash('sha256', $body) . "\n" . $ts . "\n" . $nonce;
        $expected = base64_encode(hash_hmac('sha256', $canonical, self::commandKey($siteKey), true));
        if (!hash_equals($expected, $signature)) {
            return $refused;
        }

        $data = json_decode($body, true);
        if (!is_array($data) || !hash_equals($siteUid, (string) ($data['site'] ?? ''))) {
            return $refused;
        }

        // Each nonce once (kept a little longer than the time window).
        $seen = 'genwave_cmd_' . md5($nonce);
        if (get_transient($seen)) {
            return $refused;
        }
        set_transient($seen, 1, 2 * self::SKEW);

        return true;
    }

    public static function handle(WP_REST_Request $request): WP_REST_Response
    {
        $data = json_decode($request->get_body(), true);
        $op = is_array($data) ? (string) ($data['op'] ?? '') : '';

        if ($op === 'status') {
            return new WP_REST_Response(['success' => true] + self::status(), 200);
        }

        if ($op === 'install') {
            $slug = (string) ($data['slug'] ?? '');
            if (!isset(self::PLUGINS[$slug])) {
                return new WP_REST_Response(['success' => false, 'code' => 'unknown_plugin', 'message' => __('Genwave does not offer this plugin.', 'gen-wave')], 400);
            }
            $result = self::install($slug);
            return new WP_REST_Response($result + self::status(), $result['success'] ? 200 : 422);
        }

        return new WP_REST_Response(['success' => false, 'code' => 'unknown_op', 'message' => __('Unknown command.', 'gen-wave')], 400);
    }

    /** Where each Genwave plugin stands here, and whether WordPress can install plugins by itself. */
    public static function status(): array
    {
        self::loadAdminIncludes();
        $plugins = [];
        foreach (array_keys(self::PLUGINS) as $slug) {
            $found = self::find($slug);
            $plugins[$slug] = [
                'installed' => $found !== null,
                'active' => $found !== null && is_plugin_active($found['file']),
                'version' => $found['version'] ?? null,
            ];
        }
        return [
            'plugins' => $plugins,
            'can_install' => self::canInstall(),
            'wp' => get_bloginfo('version'),
            'php' => PHP_VERSION,
        ];
    }

    /**
     * Install (when missing) and activate one Genwave plugin.
     *
     * @return array{success:bool, code?:string, message?:string}
     */
    private static function install(string $slug): array
    {
        self::loadAdminIncludes();

        $found = self::find($slug);
        if ($found === null) {
            if (!self::canInstall()) {
                return [
                    'success' => false,
                    'code' => 'cannot_install',
                    'message' => __('WordPress on this site cannot install plugins by itself (file changes are turned off or need FTP details). Download the plugin and upload it in Plugins > Add New.', 'gen-wave'),
                ];
            }
            $upgrader = new \Plugin_Upgrader(new \Automatic_Upgrader_Skin());
            $result = $upgrader->install(self::PLUGINS[$slug]);
            if (is_wp_error($result)) {
                return ['success' => false, 'code' => 'install_failed', 'message' => $result->get_error_message()];
            }
            if ($result !== true) {
                $messages = $upgrader->skin->get_upgrade_messages();
                return [
                    'success' => false,
                    'code' => 'install_failed',
                    'message' => $messages ? wp_strip_all_tags(implode(' ', $messages)) : __('The plugin could not be installed.', 'gen-wave'),
                ];
            }
            wp_clean_plugins_cache();
            $found = self::find($slug);
            if ($found === null) {
                return ['success' => false, 'code' => 'install_failed', 'message' => __('The plugin was downloaded but its folder was not found.', 'gen-wave')];
            }
        }

        if (!is_plugin_active($found['file'])) {
            $activated = activate_plugin($found['file']);
            if (is_wp_error($activated)) {
                return ['success' => false, 'code' => 'activate_failed', 'message' => $activated->get_error_message()];
            }
        }

        return ['success' => true];
    }

    /** True when WordPress can write plugin files without asking for FTP details. */
    private static function canInstall(): bool
    {
        if (function_exists('wp_is_file_mod_allowed') && !wp_is_file_mod_allowed('genwave_site_command')) {
            return false;
        }
        return get_filesystem_method([], WP_PLUGIN_DIR) === 'direct';
    }

    /** A plugin by its folder name: ['file' => "slug/main.php", 'version' => "1.2.3"], or null. */
    private static function find(string $slug): ?array
    {
        foreach (get_plugins() as $file => $data) {
            if (strtok($file, '/') === $slug) {
                return ['file' => $file, 'version' => $data['Version'] ?? null];
            }
        }
        return null;
    }

    private static function loadAdminIncludes(): void
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    }
}
