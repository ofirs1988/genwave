<?php

namespace GenWavePlugin\Services;

use Exception;

/**
 * EncryptionService - Centralized encryption/decryption
 *
 * Provides secure AES-256-CBC encryption for:
 * - Token encryption
 * - UIDD encryption
 * - Sensitive data encryption
 */
class EncryptionService
{
    /**
     * Encryption method
     */
    const CIPHER_METHOD = 'AES-256-CBC';

    /**
     * Get encryption key
     *
     * @return string Encryption key
     * @throws Exception If key is not available
     */
    private static function getKey()
    {
        // Try to get from constant first (most secure)
        if (defined('GEN_WAVE_SECRET_KEY')) {
            return GEN_WAVE_SECRET_KEY;
        }

        // Fallback to wp_options
        $key = get_option('aiaw_encryption_key');
        if (!$key) {
            throw new Exception('Encryption key not found');
        }

        return $key;
    }

    /**
     * Encrypt data using AES-256-CBC
     *
     * @param string $data Data to encrypt
     * @return string|false Encrypted data (base64 encoded) or false on failure
     */
    public static function encrypt($data)
    {
        try {
            $key = self::getKey();

            // Generate a random IV
            $iv_length = openssl_cipher_iv_length(self::CIPHER_METHOD);
            $iv = openssl_random_pseudo_bytes($iv_length);

            // Encrypt the data
            $encrypted = openssl_encrypt(
                $data,
                self::CIPHER_METHOD,
                $key,
                0,
                $iv
            );

            if ($encrypted === false) {
                return false;
            }

            // Combine IV and encrypted data, then base64 encode
            $result = base64_encode($iv . $encrypted);

            return $result;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Decrypt data using AES-256-CBC
     *
     * @param string $encrypted_data Encrypted data (base64 encoded)
     * @return string|false Decrypted data or false on failure
     */
    public static function decrypt($encrypted_data)
    {
        try {
            $key = self::getKey();

            // Decode from base64
            $data = base64_decode($encrypted_data);

            // Extract IV and encrypted content
            $iv_length = openssl_cipher_iv_length(self::CIPHER_METHOD);
            $iv = substr($data, 0, $iv_length);
            $encrypted = substr($data, $iv_length);

            // Decrypt the data
            $decrypted = openssl_decrypt(
                $encrypted,
                self::CIPHER_METHOD,
                $key,
                0,
                $iv
            );

            if ($decrypted === false) {
                return false;
            }

            return $decrypted;

        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Hash data using SHA-256
     *
     * @param string $data Data to hash
     * @return string Hashed data
     */
    public static function hash($data)
    {
        return hash('sha256', $data);
    }

    /**
     * Compare two strings in constant time (prevent timing attacks)
     *
     * @param string $known_string Known string
     * @param string $user_string User-provided string
     * @return bool True if strings match
     */
    public static function secureCompare($known_string, $user_string)
    {
        return hash_equals($known_string, $user_string);
    }

    /**
     * Generate a cryptographically secure random string
     *
     * @param int $length Length of random string (default 32)
     * @return string Random string (hex)
     */
    public static function generateRandomString($length = 32)
    {
        try {
            $bytes = openssl_random_pseudo_bytes($length / 2);
            return bin2hex($bytes);
        } catch (Exception $e) {
            // Fallback to wp_generate_password
            return substr(wp_generate_password($length, true, true), 0, $length);
        }
    }

    /**
     * Encrypt token for API transmission
     *
     * @param string $token Token to encrypt
     * @return string|false Encrypted token or false on failure
     */
    public static function encryptToken($token)
    {
        if (empty($token)) {
            return false;
        }

        return self::encrypt($token);
    }

    /**
     * Decrypt token from API response
     *
     * @param string $encrypted_token Encrypted token
     * @return string|false Decrypted token or false on failure
     */
    public static function decryptToken($encrypted_token)
    {
        if (empty($encrypted_token)) {
            return false;
        }

        return self::decrypt($encrypted_token);
    }

    /**
     * Validate encryption key strength
     *
     * @param string $key Key to validate
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateKeyStrength($key)
    {
        if (strlen($key) < 32) {
            return [
                'valid' => false,
                'error' => 'Encryption key must be at least 32 characters'
            ];
        }

        // Check for common weak keys
        $weak_keys = ['12345', 'password', 'secret', 'admin'];
        foreach ($weak_keys as $weak) {
            if (stripos($key, $weak) !== false) {
                return [
                    'valid' => false,
                    'error' => 'Encryption key appears to be weak'
                ];
            }
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Rotate encryption key (re-encrypt all data with new key)
     * WARNING: Use with caution!
     *
     * @param string $new_key New encryption key
     * @return bool Success
     */
    public static function rotateKey($new_key)
    {
        // Validate new key strength
        $validation = self::validateKeyStrength($new_key);
        if (!$validation['valid']) {
            return false;
        }

        // This is a placeholder - in production, you would:
        // 1. Decrypt all encrypted data with old key
        // 2. Encrypt with new key
        // 3. Update all records
        // 4. Store new key

        return false;
    }
}
