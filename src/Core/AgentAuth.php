<?php

namespace GenWavePlugin\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Signed agent authentication (v2).
 *
 * The site connects once against the panel, which proves domain ownership with
 * an HMAC challenge and returns a per-site signing key. Every request to the
 * agent is then signed with that key, so the agent can verify each call
 * cryptographically. No bearer tokens, no license key sent as a credential.
 *
 * Stored (wp_options, via Config): genwave_site_uid, genwave_site_key (base64).
 */
class AgentAuth
{
    /** Panel base URL that runs the connect + verification. */
    public static function panelUrl(): string
    {
        if (defined('GENWAVE_PANEL_URL') && GENWAVE_PANEL_URL) {
            return rtrim(GENWAVE_PANEL_URL, '/');
        }
        return 'https://app.genwave.ai';
    }

    /** True once the site has completed the signed connect. */
    public static function isConnected(): bool
    {
        return (bool) Config::get('site_uid') && (bool) Config::get('site_key');
    }

    /**
     * Connect this site: panel verifies ownership (HMAC challenge back to us) and
     * returns the per-site signing key. Returns [success:bool, message:string].
     */
    public static function connect(): array
    {
        $licenseKey = Config::get('license_key');
        if (empty($licenseKey)) {
            return ['success' => false, 'message' => __('Enter your license key first.', 'gen-wave')];
        }

        $response = wp_remote_post(self::panelUrl() . '/api/plugin/connect', [
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'body' => wp_json_encode([
                'license_key' => $licenseKey,
                'site_url' => get_site_url(),
            ]),
            'timeout' => 30,
            // Local panel over http/self-signed; real panel is always verified.
            'sslverify' => ! preg_match('#(localhost|127\.0\.0\.1|\.local)#', self::panelUrl()),
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['success']) || empty($data['site_uid']) || empty($data['site_key'])) {
            return ['success' => false, 'message' => $data['message'] ?? __('Connection failed.', 'gen-wave')];
        }

        Config::set('site_uid', sanitize_text_field($data['site_uid']));
        Config::set('site_key', sanitize_text_field($data['site_key']));
        Config::set('license_key', $licenseKey);
        Config::set('domain', $data['domain'] ?? get_site_url());
        Config::set('active', '1');
        if (isset($data['plan'])) {
            Config::set('plan', $data['plan']);
        }
        if (isset($data['credits'])) {
            Config::set('credits', (float) $data['credits']);
            update_option('aiaw_credits', (float) $data['credits']);
        }

        return ['success' => true, 'message' => __('Your site is connected.', 'gen-wave')];
    }

    /**
     * Register the global outbound signer. One filter on the anchor plugin signs
     * EVERY request to the agent host — from this plugin and the Agent/Chatbot
     * plugins alike — since they all share the connection stored here.
     */
    public static function register(): void
    {
        add_filter('http_request_args', [self::class, 'signOutbound'], 20, 2);
        add_action('admin_notices', [self::class, 'reconnectNotice']);
    }

    /** Nudge the admin to reconnect when a license is set but the site isn't v2-connected. */
    public static function reconnectNotice(): void
    {
        if (! current_user_can('manage_options') || self::isConnected() || ! Config::get('license_key')) {
            return;
        }
        $url = admin_url('admin.php?page=gen-wave-plugin-settings');
        echo '<div class="notice notice-warning"><p>'
            . esc_html__('Genwave needs to reconnect your site to keep working securely.', 'gen-wave')
            . ' <a href="' . esc_url($url) . '">' . esc_html__('Reconnect now', 'gen-wave') . '</a>'
            . '</p></div>';
    }

    /** Inject X-GW-* signing headers on any outbound request to the agent host. */
    public static function signOutbound($args, $url)
    {
        if (!self::isConnected() || !self::isAgentUrl($url)) {
            return $args;
        }
        $method = strtoupper($args['method'] ?? 'GET');

        // Sign path + query so query params can't be tampered.
        $query = parse_url($url, PHP_URL_QUERY);
        $path = (parse_url($url, PHP_URL_PATH) ?: '/') . ($query ? '?' . $query : '');

        // Normalize the body so the signed bytes match what is actually sent. Array
        // bodies are JSON-encoded here (otherwise the transport would encode them
        // differently and the signature would never match).
        $body = $args['body'] ?? '';
        if (is_array($body)) {
            $body = wp_json_encode($body);
            $args['body'] = $body;
            $has_ct = false;
            foreach (array_keys(is_array($args['headers'] ?? null) ? $args['headers'] : []) as $h) {
                if (strcasecmp($h, 'Content-Type') === 0) { $has_ct = true; break; }
            }
            if (!$has_ct) {
                $args['headers'] = (is_array($args['headers'] ?? null) ? $args['headers'] : []) + ['Content-Type' => 'application/json'];
            }
        } elseif (!is_string($body)) {
            $body = '';
        }

        $headers = self::signedHeaders($method, $path, $body);
        if (!empty($headers)) {
            $args['headers'] = array_merge(is_array($args['headers'] ?? null) ? $args['headers'] : [], $headers);
        }
        return $args;
    }

    /** True when $url points at the agent backend host. */
    private static function isAgentUrl($url): bool
    {
        $agentBase = defined('GENWAVE_AGENT_API_URL') && GENWAVE_AGENT_API_URL
            ? GENWAVE_AGENT_API_URL
            : 'https://agent.genwave.ai';
        $agentHost = parse_url($agentBase, PHP_URL_HOST);
        $host = parse_url((string) $url, PHP_URL_HOST);
        return $host && $agentHost && strcasecmp($host, $agentHost) === 0;
    }

    /** Drop local connection state (used on disconnect / auth failure). */
    public static function forget(): void
    {
        Config::set('site_uid', '');
        Config::set('site_key', '');
        Config::set('active', '0');
    }

    /**
     * Build the signing headers for one agent request.
     *
     * canonical = METHOD \n PATH \n sha256_hex(body) \n timestamp \n nonce
     * X-GW-Signature = base64( HMAC-SHA256(site_key, canonical) )
     *
     * @param string $method HTTP method (e.g. "POST")
     * @param string $path   URL path the agent will see (e.g. "/generate-single")
     * @param string $body   Raw request body (JSON string), "" for GET
     * @return array<string,string> Headers, or [] if not connected.
     */
    public static function signedHeaders(string $method, string $path, string $body = ''): array
    {
        $siteUid = Config::get('site_uid');
        $siteKeyB64 = Config::get('site_key');
        if (empty($siteUid) || empty($siteKeyB64)) {
            return [];
        }
        $siteKey = base64_decode($siteKeyB64, true);
        if ($siteKey === false || $siteKey === '') {
            return [];
        }

        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $bodyHash = hash('sha256', $body);
        $canonical = strtoupper($method) . "\n" . $path . "\n" . $bodyHash . "\n" . $timestamp . "\n" . $nonce;
        $signature = base64_encode(hash_hmac('sha256', $canonical, $siteKey, true));

        return [
            'X-GW-Site' => $siteUid,
            'X-GW-Timestamp' => $timestamp,
            'X-GW-Nonce' => $nonce,
            'X-GW-Signature' => $signature,
        ];
    }
}
