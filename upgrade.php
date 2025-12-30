<?php
/**
 * Genwave Plugin Upgrade Script
 * Handles database schema upgrades automatically
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Run upgrade check on plugin activation/update
 */
function genwave_check_and_upgrade() {
    $current_db_version = get_option('genwave_db_version', '0');
    $required_db_version = '1.0.8'; // Updated for gen_requests_posts columns

    // If already upgraded, skip
    if (version_compare($current_db_version, $required_db_version, '>=')) {
        return;
    }

    if (defined('WP_DEBUG') && WP_DEBUG) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
        error_log('Genwave: Starting database upgrade from version ' . $current_db_version . ' to ' . $required_db_version);
    }

    // Run upgrades based on version
    // Always run the column check - it's safe to run multiple times
    genwave_upgrade_to_1_0_2();

    // Update version
    update_option('genwave_db_version', $required_db_version);

    if (defined('WP_DEBUG') && WP_DEBUG) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
        error_log('Genwave: Database version set to ' . $required_db_version);
    }
}

/**
 * Upgrade to version 1.0.2
 * Adds missing columns to gen_requests_posts table:
 * - status
 * - progress_percent
 * - ai_started_at
 * - ai_completed_at
 * - processing_duration
 * - failed_at
 */
function genwave_upgrade_to_1_0_2() {
    global $wpdb;

    if (defined('WP_DEBUG') && WP_DEBUG) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
        error_log('Genwave: Upgrading database schema to 1.0.2 (gen_requests_posts columns)');
    }

    $table_name = $wpdb->prefix . 'gen_requests_posts';

    // Check if table exists
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to check if plugin table exists
    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));

    if (!$table_exists) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
            error_log('Genwave: Table ' . $table_name . ' does not exist, skipping upgrade');
        }
        return;
    }

    // Get existing columns
    $existing_columns = [];
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is safe (uses $wpdb->prefix)
    $columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}`");
    foreach ($columns as $column) {
        $existing_columns[] = $column->Field;
    }

    // Add missing columns
    $columns_to_add = [
        'status' => "VARCHAR(50) DEFAULT 'pending' AFTER post_type",
        'progress_percent' => "FLOAT DEFAULT 0 AFTER status",
        'ai_started_at' => "DATETIME DEFAULT NULL AFTER progress_percent",
        'ai_completed_at' => "DATETIME DEFAULT NULL AFTER ai_started_at",
        'processing_duration' => "FLOAT DEFAULT NULL AFTER ai_completed_at",
        'failed_at' => "DATETIME DEFAULT NULL AFTER processing_duration"
    ];

    foreach ($columns_to_add as $column_name => $column_definition) {
        if (!in_array($column_name, $existing_columns)) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Table/column names safe, definition hardcoded
            $sql = "ALTER TABLE `{$table_name}` ADD COLUMN `{$column_name}` {$column_definition}";
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query built with safe table/column names, schema change required for upgrade
            $wpdb->query($sql);

            if ($wpdb->last_error) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log('Genwave: Error adding column ' . $column_name . ': ' . $wpdb->last_error);
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log('Genwave: Added column ' . $column_name . ' to ' . $table_name);
                }
            }
        }
    }

    // Add index on status if not exists
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is safe (uses $wpdb->prefix)
    $index_exists = $wpdb->get_var("SHOW INDEX FROM `{$table_name}` WHERE Key_name = 'idx_status'");
    if (!$index_exists) {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is safe (uses $wpdb->prefix), schema change required for upgrade
        $wpdb->query("ALTER TABLE `{$table_name}` ADD INDEX idx_status (status)");
        if ($wpdb->last_error) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('Genwave: Error adding idx_status index: ' . $wpdb->last_error);
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('Genwave: Added idx_status index to ' . $table_name);
            }
        }
    }

    if (defined('WP_DEBUG') && WP_DEBUG) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
        error_log('Genwave: Schema upgrade to 1.0.2 completed');
    }
}

/**
 * Upgrade to version 1.1.0
 * Changes all token decimal columns from (10,2) to (15,8)
 */
function genwave_upgrade_to_1_1_0() {
    global $wpdb;

    if (defined('WP_DEBUG') && WP_DEBUG) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
        error_log('Genwave: Upgrading database schema to 1.1.0 (decimal precision upgrade)');
    }

    // Upgrade wp_ai_pro_token_usage table (in wp_ai database)
    $token_usage_table = 'wp_ai_pro_token_usage';

    // Check if table exists first
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name is a constant
    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $token_usage_table));

    if ($table_exists) {
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is a constant, schema update required
        $wpdb->query("
            ALTER TABLE `{$token_usage_table}`
            MODIFY COLUMN tokens_estimated DECIMAL(15,8) DEFAULT 0.00,
            MODIFY COLUMN tokens_actually_used DECIMAL(15,8) DEFAULT 0.00,
            MODIFY COLUMN tokens_refunded DECIMAL(15,8) DEFAULT 0.00,
            MODIFY COLUMN tokens_charged_to_user DECIMAL(15,8) DEFAULT 0.00,
            MODIFY COLUMN usage_efficiency DECIMAL(15,8) DEFAULT 0.0
        ");
        // phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter

        if ($wpdb->last_error) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('Genwave: Error upgrading wp_ai_pro_token_usage: ' . $wpdb->last_error);
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('Genwave: Successfully upgraded wp_ai_pro_token_usage to decimal(15,8)');
            }
        }
    }

    // Note: Other tables (ai_tokens, ai_tokens_charge, etc.) are in the Laravel database
    // and will be upgraded via Laravel migrations

    if (defined('WP_DEBUG') && WP_DEBUG) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
        error_log('Genwave: Schema upgrade to 1.1.0 completed');
    }
}

// Register activation hook
register_activation_hook(GEN_WAVE__FILE__, 'genwave_check_and_upgrade');

// Also check on plugin update (via admin_init)
add_action('admin_init', 'genwave_check_and_upgrade');
