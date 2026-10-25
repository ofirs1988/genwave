<?php

namespace GenWavePlugin\Core;

if (!defined('ABSPATH')) {
    exit;
}

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Read-only status questions from the customer's Genwave account (app.genwave.ai).
 *
 * The account's Sites page shows which Genwave plugins a connected site has.
 * It asks here with a signed request, and this route answers with what is
 * installed and active. Nothing is installed, activated or changed: since
 * 1.3.1 this plugin never installs or activates other plugins. Genwave
 * plugins are added by the site's administrator, by uploading the zip from
 * their Genwave account in Plugins > Add New > Upload Plugin.
 *
 * Signed with a key only this site and the account can derive:
 *   command_key = HMAC-SHA256(site_key, "genwave-site-command-v1")
 * kept apart from the key the site signs its own agent requests with, so a
 * signature made for one direction never counts for the other.
 *   canonical = "POST" \n "/gen-wave/v1/command" \n sha256_hex(body) \n timestamp \n nonce
 *   headers   X-GW-Timestamp, X-GW-Nonce, X-GW-Signature (base64)
 * Five minutes either way, each nonce once.
 * Body (JSON): { "site": <site_uid>, "op": "status" }.
 */
class SiteCommands
{
    const NS = 'gen-wave/v1';
    const ROUTE = '/command';

    /** The Genwave plugins the status reports on. */
    const PLUGINS = ['genwave-agent', 'genwave-chatbot', 'genwave-elements'];

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

        // Accounts built for 1.3.0 may still ask this site to install a plugin.
        // It never does: the answer says how the administrator adds it instead.
        if ($op === 'install') {
            return new WP_REST_Response([
                'success' => false,
                'code' => 'cannot_install',
                'message' => __('This plugin does not install other plugins. Download the plugin from your Genwave account and upload it in Plugins > Add New > Upload Plugin.', 'gen-wave'),
            ] + self::status(), 422);
        }

        return new WP_REST_Response(['success' => false, 'code' => 'unknown_op', 'message' => __('Unknown command.', 'gen-wave')], 400);
    }

    /** Where each Genwave plugin stands here. */
    public static function status(): array
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugins = [];
        foreach (self::PLUGINS as $slug) {
            $found = self::find($slug);
            $plugins[$slug] = [
                'installed' => $found !== null,
                'active' => $found !== null && is_plugin_active($found['file']),
                'version' => $found['version'] ?? null,
            ];
        }
        return [
            'plugins' => $plugins,
            // The account offers its zip download instead of an install button.
            'can_install' => false,
            'wp' => get_bloginfo('version'),
            'php' => PHP_VERSION,
        ];
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
}
