<?php

namespace GenWavePlugin\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

use GenWavePlugin\Core\ApiManager;
use GenWavePlugin\Core\Config;

class TokensController{
    static $api_manager;

    public function __construct()
    {
        self::initialize(new ApiManager(GENWAVE_API_URL));
    }

    public static function initialize(ApiManager $manager)
    {
        self::$api_manager = $manager;
    }

    /**
     * Fetch the current credit balance from the AGENT backend (the real API),
     * not the dashboard. The balance lives in the shared billing DB, so this is
     * the single source of truth. We cache it in the `aiaw_credits` option — the
     * SAME place the genwave-agent plugin writes — so the number stays in sync
     * across both plugins. Works even when the agent plugin isn't active, because
     * the anchor (gen-wave) holds the credentials and calls the backend directly.
     *
     * @return float|array Balance on success, or an error array {error, auth, message}.
     */
    public function RefreshCredits()
    {
        $base = defined('GENWAVE_AGENT_API_URL') && GENWAVE_AGENT_API_URL
            ? GENWAVE_AGENT_API_URL
            : 'https://agent.genwave.ai';

        $token = Config::get('token');
        $uidd = Config::get('uidd');
        $license = Config::get('license_key');

        if (empty($token) || empty($license)) {
            return ['error' => true, 'auth' => false, 'message' => 'Connect your Genwave account first'];
        }

        $is_local = preg_match('#(localhost|127\.0\.0\.1|\.local)#', $base) === 1;
        $response = wp_remote_get(rtrim($base, '/') . '/credits', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
                'uidd' => $uidd,
                'license-key' => $license,
                'from-domain' => ApiManager::getFromDomain(),
            ],
            'timeout' => 20,
            'sslverify' => !$is_local,
        ]);

        if (is_wp_error($response)) {
            // Network problem — credentials are probably fine, don't wipe them.
            return ['error' => true, 'auth' => true, 'message' => $response->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 401 || $code === 403) {
            return ['error' => true, 'auth' => false, 'message' => 'Authentication failed. Please reconnect.'];
        }
        if ($code !== 200 || !is_array($body)) {
            return ['error' => true, 'auth' => true, 'message' => 'Could not read balance from the agent backend'];
        }

        $credits = $body['credit_balance'] ?? $body['credits'] ?? null;
        if ($credits === null) {
            return ['error' => true, 'auth' => true, 'message' => 'Unexpected response from the agent backend'];
        }

        // Sync: store in the SAME option the agent uses so both plugins agree.
        update_option('aiaw_credits', (float) $credits);
        Config::set('credits', (float) $credits); // keep the anchor's own copy aligned too

        return (float) $credits;
    }

    /**
     * @deprecated Use RefreshCredits() instead
     */
    public function RefreshTokens()
    {
        return $this->RefreshCredits();
    }
}