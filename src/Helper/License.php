<?php

namespace GenWavePlugin\Helper;

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
            } else {
                if($api_data['success']) {
                    Config::set('license_key', self::$licenseKey);
                    $message = 'Success, valid license';
                    return [
                        'message' => $message,
                        'success'  => true,
                    ];
                }else{
                    Config::set('active', false);
                    $message = 'Failed to fetch API data';
                    return [
                        'message' => $message,
                        'success'  => false,
                    ];
                }
            }
        }

    }
}