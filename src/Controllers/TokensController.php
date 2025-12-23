<?php

namespace GenWavePlugin\Controllers;

if (!defined('ABSPATH')) {
    exit;
}

use GenWavePlugin\Global\ApiManager;
use GenWavePlugin\Global\Config;

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

    public  function RefreshTokens()
    {
        $response = self::$api_manager->post('/refresh-tokens', [
            // 'domain' => $domain,
        ], Config::get('token'), Config::get('uidd'));

        return $response['tokens'] ?? $response;
    }
}