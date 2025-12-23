<?php

namespace GenWavePlugin\Helper;

if (!defined('ABSPATH')) {
    exit;
}

use GenWavePlugin\Global\ApiManager;
use GenWavePlugin\Global\Config;

class License {
    static ApiManager $api_manager;
    static $licenseKey = false;
    public static function initialize(ApiManager $manager)
    {
        self::$api_manager = $manager;
    }

    public static function verifyLicenseKey() {
        if(self::$licenseKey){
            $api_data = self::$api_manager->post('/validate-license', [
                'license_key' => self::$licenseKey,
                //'domain' => $domain,
            ]);

            if (is_wp_error($api_data)) {
                $message = 'Failed to fetch API data: ' . $api_data->get_error_message();
                Config::set('active', false);
                return [
                    'message' => $message,
                    'success'  => false,
                ];
            }

            // Check for error response from API
            if (isset($api_data['error']) && $api_data['error'] === true) {
                Config::set('active', false);
                $message = $api_data['message'] ?? 'API returned an error';
                return [
                    'message' => $message,
                    'success'  => false,
                ];
            }

            // Check for success response
            if (isset($api_data['success']) && $api_data['success']) {
                Config::set('license_key', self::$licenseKey);
                $message = 'Success, valid license';
                return [
                    'message' => $message,
                    'success'  => true,
                ];
            }

            // Check for status response (alternative format)
            if (isset($api_data['status']) && $api_data['status'] === true) {
                Config::set('license_key', self::$licenseKey);
                $message = $api_data['message'] ?? 'Success, valid license';
                return [
                    'message' => $message,
                    'success'  => true,
                ];
            }

            // Unknown response format - show what we got
            Config::set('active', false);
            $message = $api_data['message'] ?? 'Invalid license key or server error';
            return [
                'message' => $message,
                'success'  => false,
            ];
        }

        return [
            'message' => 'License key is required',
            'success'  => false,
        ];
    }
}