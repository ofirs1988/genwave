<?php

namespace GenWavePlugin\Services;

/**
 * ValidationService - Centralized input validation
 *
 * Provides reusable validation methods for:
 * - Post IDs
 * - Generation methods
 * - Languages
 * - Content lengths
 * - Instructions
 * - User permissions
 */
class ValidationService
{
    /**
     * Allowed generation methods
     */
    const ALLOWED_METHODS = ['title', 'short_description', 'description'];

    /**
     * Allowed post types
     */
    const ALLOWED_POST_TYPES = ['post', 'product', 'page'];

    /**
     * Validate post ID
     *
     * @param int $post_id Post ID to validate
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validatePostId($post_id)
    {
        $post_id = intval($post_id);

        if (!$post_id || $post_id < 1) {
            return [
                'valid' => false,
                'error' => 'Valid Post ID is required'
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Validate generation method
     *
     * @param string $method Generation method
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateGenerationMethod($method)
    {
        $method = sanitize_text_field($method);

        if (!in_array($method, self::ALLOWED_METHODS, true)) {
            return [
                'valid' => false,
                'error' => 'Invalid generation method. Allowed: ' . implode(', ', self::ALLOWED_METHODS)
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Validate language parameter
     *
     * @param string $language Language name
     * @param int $maxLength Maximum length (default 50)
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateLanguage($language, $maxLength = 50)
    {
        $language = sanitize_text_field($language);

        if (empty($language) || strlen($language) > $maxLength) {
            return [
                'valid' => false,
                'error' => "Language must be between 1 and {$maxLength} characters"
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Validate content length
     *
     * @param int $length Content length
     * @param int $min Minimum length (default 100)
     * @param int $max Maximum length (default 10000)
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateContentLength($length, $min = 100, $max = 10000)
    {
        $length = intval($length);

        if ($length < $min || $length > $max) {
            return [
                'valid' => false,
                'error' => "Content length must be between {$min} and {$max} characters"
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Validate instructions text
     *
     * @param string $instructions Instructions text
     * @param int $maxLength Maximum length (default 1000)
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateInstructions($instructions, $maxLength = 1000)
    {
        $instructions = sanitize_textarea_field($instructions);

        if (strlen($instructions) > $maxLength) {
            return [
                'valid' => false,
                'error' => "Instructions cannot exceed {$maxLength} characters"
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Validate post exists and check permissions
     *
     * @param int $post_id Post ID
     * @param bool $checkPermissions Check edit permissions (default true)
     * @return array ['valid' => bool, 'error' => string|null, 'post' => WP_Post|null]
     */
    public static function validatePostAccess($post_id, $checkPermissions = true)
    {
        $post = get_post($post_id);

        if (!$post) {
            return [
                'valid' => false,
                'error' => 'Post not found',
                'post' => null
            ];
        }

        if ($checkPermissions && !current_user_can('edit_post', $post_id)) {
            return [
                'valid' => false,
                'error' => 'You do not have permission to edit this post',
                'post' => null
            ];
        }

        // Verify post type is allowed
        if (!in_array($post->post_type, self::ALLOWED_POST_TYPES, true)) {
            return [
                'valid' => false,
                'error' => 'Invalid post type',
                'post' => null
            ];
        }

        return [
            'valid' => true,
            'error' => null,
            'post' => $post
        ];
    }

    /**
     * Validate JSON data
     *
     * @param string $json JSON string
     * @param int $maxSize Maximum size in bytes (default 5MB)
     * @return array ['valid' => bool, 'error' => string|null, 'data' => array|null]
     */
    public static function validateJson($json, $maxSize = 5000000)
    {
        // Check size
        if (strlen($json) > $maxSize) {
            return [
                'valid' => false,
                'error' => 'JSON data exceeds maximum size',
                'data' => null
            ];
        }

        // Decode JSON
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'valid' => false,
                'error' => 'Invalid JSON format: ' . json_last_error_msg(),
                'data' => null
            ];
        }

        // Ensure it's an array
        if (!is_array($data)) {
            return [
                'valid' => false,
                'error' => 'JSON data must be an array',
                'data' => null
            ];
        }

        return [
            'valid' => true,
            'error' => null,
            'data' => $data
        ];
    }

    /**
     * Validate array count
     *
     * @param array $array Array to validate
     * @param int $max Maximum count (default 100)
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateArrayCount($array, $max = 100)
    {
        if (!is_array($array)) {
            return [
                'valid' => false,
                'error' => 'Data must be an array'
            ];
        }

        if (empty($array)) {
            return [
                'valid' => false,
                'error' => 'Array is empty'
            ];
        }

        if (count($array) > $max) {
            return [
                'valid' => false,
                'error' => "Cannot process more than {$max} items at once"
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Validate token balance
     *
     * @param mixed $balance Token balance
     * @return array ['valid' => bool, 'error' => string|null, 'value' => float]
     */
    public static function validateTokenBalance($balance)
    {
        $balance = floatval($balance);

        if ($balance < 0) {
            return [
                'valid' => false,
                'error' => 'Token balance cannot be negative',
                'value' => 0
            ];
        }

        return [
            'valid' => true,
            'error' => null,
            'value' => $balance
        ];
    }

    /**
     * Validate nonce
     *
     * @param string $nonce Nonce to validate
     * @param string|array $action Action name or array of action names
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateNonce($nonce, $action)
    {
        if (empty($nonce)) {
            return [
                'valid' => false,
                'error' => 'Nonce is required'
            ];
        }

        // Support multiple action names
        $actions = is_array($action) ? $action : [$action];

        foreach ($actions as $act) {
            if (wp_verify_nonce($nonce, $act)) {
                return ['valid' => true, 'error' => null];
            }
        }

        return [
            'valid' => false,
            'error' => 'Security verification failed'
        ];
    }

    /**
     * Validate user capability
     *
     * @param string $capability Capability to check
     * @param int|null $object_id Optional object ID
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateCapability($capability, $object_id = null)
    {
        $has_cap = $object_id
            ? current_user_can($capability, $object_id)
            : current_user_can($capability);

        if (!$has_cap) {
            return [
                'valid' => false,
                'error' => 'Insufficient permissions'
            ];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Batch validate multiple fields
     *
     * @param array $validations Array of validation results
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function batchValidate($validations)
    {
        $errors = [];

        foreach ($validations as $field => $result) {
            if (!$result['valid']) {
                $errors[$field] = $result['error'];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}
