<?php

namespace GenWavePlugin\Global;

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
        'encryption_key' => 'ATW1kctl7zkJDLC7IRC8JDfPBrgREiLu', // Default encryption key (AES-256-CBC)
    ];

    public static function get($key) {
        if (array_key_exists($key, self::$options)) {
            return get_option('aiaw_' . $key, self::$options[$key]);
        }
        return null;
    }

    public static function set($key, $value) {
        if (array_key_exists($key, self::$options)) {
            update_option('aiaw_' . $key, sanitize_text_field($value));
        }
    }

    public static function delete($key) {
        if (array_key_exists($key, self::$options)) {
            delete_option('aiaw_' . $key);
        }
    }

    public static function load_defaults() {
        foreach (self::$options as $key => $default) {
            if (get_option('ai_' . $key) === false) {
                add_option('aiaw_' . $key, $default);
            }
        }
    }

    public static function delete_all() {
        foreach (self::$options as $key => $default) {
            delete_option('aiaw_' . $key);
        }
    }
}
