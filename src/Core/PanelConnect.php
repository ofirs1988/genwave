<?php

namespace GenWavePlugin\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * "Connect to Genwave": connect this site by approving it in the Genwave panel,
 * instead of copying a license key.
 *
 * 1. start(): an administrator clicks the button (nonced admin-post). We mint a
 *    one-time `state` and a PKCE verifier, keep both in a per-user transient,
 *    and send the browser to {panel}/connect/authorize with the site address,
 *    the state and sha256(verifier). The verifier itself never leaves the site.
 * 2. The customer signs in (or signs up) there and approves this site.
 * 3. handleCallback(): the panel sends the browser back here with a short-lived
 *    code and our state. We check the state belongs to this admin, then redeem
 *    the code server to server together with the verifier. The panel answers
 *    with the license key, and AgentAuth::connect() finishes the connection
 *    exactly as a pasted key would (HMAC challenge, per-site signing key).
 *
 * The license key never travels in a URL. A code seen by anyone else is useless
 * without the verifier, and a callback this admin did not start is rejected.
 */
class PanelConnect
{
    const START_ACTION = 'genwave_connect_start';
    const CALLBACK_ARG = 'gw_connect';
    const SETTINGS_PAGE = 'gen-wave-plugin-settings';
    /** Long enough to create an account and confirm the email on the way. */
    const TTL = 1800;

    public static function register(): void
    {
        add_action('admin_post_' . self::START_ACTION, [self::class, 'start']);
        add_action('admin_init', [self::class, 'maybeHandleCallback']);
        add_action('admin_init', [self::class, 'maybeRedirectAfterActivation']);
        add_action('admin_notices', [self::class, 'resultNotice']);
    }

    /** The button's link: a nonced admin-post request that starts the flow. */
    public static function startUrl(): string
    {
        // Not wp_nonce_url(): it HTML-escapes the "&", and esc_url() escapes again.
        return add_query_arg(
            ['action' => self::START_ACTION, '_wpnonce' => wp_create_nonce(self::START_ACTION)],
            admin_url('admin-post.php')
        );
    }

    public static function start(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Only an administrator can connect this site to Genwave.', 'gen-wave'), '', ['response' => 403]);
        }
        check_admin_referer(self::START_ACTION);

        $state = self::token(32);
        $verifier = self::token(48);
        set_transient(self::flowKey(), ['state' => $state, 'verifier' => $verifier], self::TTL);

        // Offer to install the Agent in the same approval when it is not here yet
        // and this admin may install plugins.
        $offerAgent = AgentPlugin::state() === 'missing' && current_user_can('install_plugins');

        $params = [
            'v' => '1',
            'site_url' => get_site_url(),
            'return_url' => self::returnUrl(),
            'state' => $state,
            'code_challenge' => self::base64url(hash('sha256', $verifier, true)),
            'code_challenge_method' => 'S256',
            'agent' => $offerAgent ? '1' : '0',
        ];
        // add_query_arg() does not encode values; these carry URLs.
        $url = add_query_arg(array_map('rawurlencode', $params), AgentAuth::panelUrl() . '/connect/authorize');

        add_filter('allowed_redirect_hosts', [self::class, 'allowPanelHost']);
        wp_safe_redirect($url);
        exit;
    }

    /** @param string[] $hosts */
    public static function allowPanelHost($hosts): array
    {
        $hosts = is_array($hosts) ? $hosts : [];
        $panelHost = wp_parse_url(AgentAuth::panelUrl(), PHP_URL_HOST);
        if ($panelHost) {
            $hosts[] = $panelHost;
        }
        return $hosts;
    }

    /**
     * The panel's redirect back. Nonces can't apply to a request that comes from
     * another site; the state check below is what ties it to this admin's click.
     */
    public static function maybeHandleCallback(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- OAuth-style callback; verified by the per-user state token below.
        if (!isset($_GET[self::CALLBACK_ARG], $_GET['page'])
            || sanitize_key(wp_unslash($_GET[self::CALLBACK_ARG])) !== 'callback'
            || sanitize_key(wp_unslash($_GET['page'])) !== self::SETTINGS_PAGE) {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }

        $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
        $code = isset($_GET['code']) ? sanitize_text_field(wp_unslash($_GET['code'])) : '';
        $error = isset($_GET['error']) ? sanitize_key(wp_unslash($_GET['error'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        $flow = get_transient(self::flowKey());
        if (!is_array($flow) || empty($flow['state']) || $state === '' || !hash_equals((string) $flow['state'], $state)) {
            self::finish('error', __('This connection request could not be verified. Click “Connect to Genwave” to start again.', 'gen-wave'));
        }
        // One use: a reloaded or replayed callback finds nothing.
        delete_transient(self::flowKey());

        if ($error !== '') {
            self::finish('warning', __('Connection cancelled. Nothing was changed.', 'gen-wave'));
        }
        if (!preg_match('/^[A-Za-z0-9._-]{20,2048}$/', $code)) {
            self::finish('error', __('Genwave did not send a valid connection code. Click “Connect to Genwave” to try again.', 'gen-wave'));
        }

        $response = wp_remote_post(AgentAuth::panelUrl() . '/api/plugin/connect/exchange', [
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'body' => wp_json_encode([
                'code' => $code,
                'code_verifier' => (string) $flow['verifier'],
                'site_url' => get_site_url(),
            ]),
            'timeout' => 20,
            'sslverify' => !preg_match('#(localhost|127\.0\.0\.1|\.local)#', AgentAuth::panelUrl()),
        ]);
        if (is_wp_error($response)) {
            self::finish('error', $response->get_error_message());
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data) || empty($data['success']) || empty($data['license_key'])) {
            self::finish('error', is_array($data) && !empty($data['message'])
                ? sanitize_text_field($data['message'])
                : __('Connection failed. Please try again.', 'gen-wave'));
        }

        // Connect with the new key; if that fails, the site keeps the key it had.
        $previousKey = Config::get('license_key');
        Config::set('license_key', sanitize_text_field($data['license_key']));
        $result = AgentAuth::connect();
        if (empty($result['success'])) {
            Config::set('license_key', (string) $previousKey);
            self::finish('error', $result['message'] ?? __('Connection failed.', 'gen-wave'));
        }

        // Approved together with the connection: the settings page installs the
        // Agent next, with progress on screen (a long download here could time out).
        if (!empty($data['install_agent']) && current_user_can('install_plugins') && AgentPlugin::state() !== 'active') {
            set_transient(self::installKey(), 1, 10 * MINUTE_IN_SECONDS);
        }

        self::finish('success', __('Your site is connected to Genwave.', 'gen-wave'));
    }

    /** True once, right after a connect that asked for the Agent: the page installs it. */
    public static function takeAgentInstall(): bool
    {
        $pending = (bool) get_transient(self::installKey());
        if ($pending) {
            delete_transient(self::installKey());
        }
        return $pending && AgentPlugin::state() !== 'active' && current_user_can('install_plugins');
    }

    /**
     * Right after the plugin is activated, open its page so "Connect to Genwave"
     * is the next thing the admin sees. Not on bulk or network activation.
     */
    public static function maybeRedirectAfterActivation(): void
    {
        if (!get_transient('genwave_activation_redirect')) {
            return;
        }
        delete_transient('genwave_activation_redirect');
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check of WordPress's own bulk-activation flag.
        if (wp_doing_ajax() || is_network_admin() || isset($_GET['activate-multi']) || !current_user_can('manage_options') || AgentAuth::isConnected()) {
            return;
        }
        wp_safe_redirect(admin_url('admin.php?page=' . self::SETTINGS_PAGE));
        exit;
    }

    /** The outcome of the last connect attempt, shown once on the next admin page. */
    public static function resultNotice(): void
    {
        $notice = get_transient(self::noticeKey());
        if (!is_array($notice) || empty($notice['message'])) {
            return;
        }
        delete_transient(self::noticeKey());
        $type = in_array($notice['type'] ?? '', ['success', 'warning', 'error'], true) ? $notice['type'] : 'info';
        echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($notice['message']) . '</p></div>';
    }

    /** Record the outcome and land on a clean settings URL (no code or state left in it). */
    private static function finish(string $type, string $message): void
    {
        set_transient(self::noticeKey(), ['type' => $type, 'message' => $message], 5 * MINUTE_IN_SECONDS);
        wp_safe_redirect(admin_url('admin.php?page=' . self::SETTINGS_PAGE));
        exit;
    }

    private static function returnUrl(): string
    {
        return admin_url('admin.php?page=' . self::SETTINGS_PAGE . '&' . self::CALLBACK_ARG . '=callback');
    }

    private static function flowKey(): string
    {
        return 'genwave_connect_flow_' . get_current_user_id();
    }

    private static function installKey(): string
    {
        return 'genwave_connect_install_' . get_current_user_id();
    }

    private static function noticeKey(): string
    {
        return 'genwave_connect_notice_' . get_current_user_id();
    }

    private static function token(int $bytes): string
    {
        return self::base64url(random_bytes($bytes));
    }

    private static function base64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
