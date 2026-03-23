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

    public  function RefreshCredits()
    {
        // Try new endpoint first, fall back to old
        $response = self::$api_manager->post('/refresh-credits', [
            // 'domain' => $domain,
        ], Config::get('token'), Config::get('uidd'));

        return $response['credits'] ?? $response['tokens'] ?? $response;
    }

    /**
     * @deprecated Use RefreshCredits() instead
     */
    public function RefreshTokens()
    {
        return $this->RefreshCredits();
    }
}