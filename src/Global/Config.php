<?php

namespace GenWavePlugin\Global;

if (!defined('ABSPATH')) {
    exit;
}

class Config {
    private static $options = [
        'license_key' => '',
        'domain' => '',
        'active' => '',
        'auth_token' => '',
        'uidd' => '',
        'token' => '',
        'plan' => '',
        'tokens' => '',
        'license_expired' => '0',
        'expiration_date' => '',
        'encryption_key' => 'ATW1kctl7zkJDLC7IRC8JDfPBrgREiLu', // Default encryption key (AES-256-CBC)
    ];

    public static function get($key) {
        if (array_key_exists($key, self::$options)) {
            return get_option('genwave_' . $key, self::$options[$key]);
        }
        return null;
    }

    public static function set($key, $value) {
        if (array_key_exists($key, self::$options)) {
            update_option('genwave_' . $key, sanitize_text_field($value));
        }
    }

    public static function delete($key) {
        if (array_key_exists($key, self::$options)) {
            delete_option('genwave_' . $key);
        }
    }

    public static function load_defaults() {
        foreach (self::$options as $key => $default) {
            if (get_option('genwave_' . $key) === false) {
                add_option('genwave_' . $key, $default);
            }
        }
    }

    public static function delete_all() {
        foreach (self::$options as $key => $default) {
            delete_option('genwave_' . $key);
        }
    }
}
