<?php

namespace GenWavePlugin\Pages;

if (!defined('ABSPATH')) {
    exit;
}

use GenWavePlugin\Core\AdminApp;

class Tools {

    private $adminPageManager;

    public function __construct($adminPageManager) {
        $this->adminPageManager = $adminPageManager;
        add_action('wp_loaded', array($this, 'register_tools_page'));
        add_action('admin_post_ai_clear_cache', array($this, 'handle_clear_cache'));
        add_action('admin_post_ai_repair_database', array($this, 'handle_repair_database'));
    }

    public function register_tools_page() {
        $this->adminPageManager->addSubmenu(
            'gen-wave-plugin-settings',
            __('Maintenance', 'gen-wave'),
            __('Maintenance', 'gen-wave'),
            'manage_options',
            'ai-tools',
            array($this, 'render_tools_page'),
            999
        );
    }

    public function render_tools_page() {
        // The outcome of the last action (handle_* redirect back with it).
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display-only, no state changes
        if (isset($_GET['cache_cleared'])) {
            \GenWavePlugin\Core\AdminApp::flash('success', __("Genwave's caches were cleared.", 'gen-wave'));
        } elseif (isset($_GET['db_repaired'])) {
            \GenWavePlugin\Core\AdminApp::flash('success', __('Database checked and repaired.', 'gen-wave') . ' ' . sanitize_text_field(wp_unslash($_GET['db_repaired'])));
        } elseif (isset($_GET['error'])) {
            \GenWavePlugin\Core\AdminApp::flash('error', sanitize_text_field(wp_unslash($_GET['error'])));
        }
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        \GenWavePlugin\Core\AdminApp::databaseStatus($this->get_database_status());
        \GenWavePlugin\Core\AdminApp::render('tools');
    }

    public function handle_clear_cache() {
        // Verify nonce
        if (!isset($_POST['ai_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ai_nonce'])), 'ai_clear_cache_nonce')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        try {
            // Only what Genwave keeps: the list of Genwave plugins, and the
            // plugin update check (so a new Genwave version shows at once).
            // Never the site's whole object cache, themes or core: those
            // belong to the site, and flushing them slows every page down.
            delete_transient('genwave_plugin_marketplace_list');
            wp_clean_plugins_cache(true);

            // Redirect with success message
            wp_safe_redirect(add_query_arg('cache_cleared', '1', admin_url('admin.php?page=ai-tools')));
            exit;

        } catch (\Exception $e) {
            // Redirect with error message
            wp_safe_redirect(add_query_arg('error', urlencode($e->getMessage()), admin_url('admin.php?page=ai-tools')));
            exit;
        }
    }

    /**
     * Get database status - check all tables and columns
     */
    public function get_database_status() {
        global $wpdb;

        $required_tables = [
            'gen_requests' => [
                'id', 'job_id', 'args', 'request_time', 'user_id', 'type', 'response_data',
                'response_time', 'status', 'progress', 'current_stage', 'error', 'total_items',
                'processed_items', 'delivered', 'delivered_at', 'laravel_completed',
                'laravel_completed_at', 'deleted_at', 'updated_at', 'created_at'
            ],
            'gen_requests_posts' => [
                'id', 'request_id', 'post_id', 'generation_type', 'post_type', 'status',
                'progress_percent', 'ai_started_at', 'ai_completed_at', 'processing_duration',
                'failed_at', 'old', 'additional_content', 'get_response', 'is_converted',
                'ai', 'applied_fields', 'convert_time', 'deleted_at', 'updated_at', 'created_at'
            ],
            'gen_settings' => [
                'id', 'option_name', 'option_value', 'created_at', 'updated_at'
            ],
            'gen_token_usage' => [
                'id', 'job_id', 'tokens_estimated', 'tokens_actually_used', 'tokens_refunded',
                'tokens_charged_to_user', 'refund_applied', 'usage_efficiency', 'created_at'
            ],
            'gen_status' => [
                'id', 'job_id', 'status', 'error_message', 'created_at', 'updated_at'
            ],
            'jobs' => [
                'id', 'queue', 'payload', 'attempts', 'reserved_at', 'created_at', 'updated_at'
            ]
        ];

        $status = [
            'all_ok' => true,
            'tables' => []
        ];

        foreach ($required_tables as $table_name => $required_columns) {
            $full_table_name = $wpdb->prefix . $table_name;
            $table_status = [
                'exists' => false,
                'missing_columns' => []
            ];

            // Check if table exists
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to check if plugin table exists
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $full_table_name
            ));

            if ($table_exists === $full_table_name) {
                $table_status['exists'] = true;

                // Get existing columns
                $existing_columns = [];
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is safe (uses $wpdb->prefix)
                $columns = $wpdb->get_results("SHOW COLUMNS FROM `{$full_table_name}`");
                foreach ($columns as $column) {
                    $existing_columns[] = $column->Field;
                }

                // Check for missing columns
                foreach ($required_columns as $col) {
                    if (!in_array($col, $existing_columns)) {
                        $table_status['missing_columns'][] = $col;
                        $status['all_ok'] = false;
                    }
                }
            } else {
                $status['all_ok'] = false;
            }

            $status['tables'][$full_table_name] = $table_status;
        }

        return $status;
    }

    /**
     * Handle database repair action
     */
    public function handle_repair_database() {
        // Verify nonce
        if (!isset($_POST['ai_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ai_nonce'])), 'ai_repair_database_nonce')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        global $wpdb;

        try {
            $repairs_made = [];
            $errors = [];

            // First, run the install script to create any missing tables
            require_once GEN_WAVE_PATH . 'install.php';
            if (function_exists('genwave_create_tables')) {
                genwave_create_tables();
                $repairs_made[] = 'Ran table creation script';
            }

            // Now add missing columns to gen_requests_posts
            $table_name = $wpdb->prefix . 'gen_requests_posts';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to check if plugin table exists
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));

            if ($table_exists) {
                // Get existing columns
                $existing_columns = [];
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is safe (uses $wpdb->prefix)
                $columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}`");
                foreach ($columns as $column) {
                    $existing_columns[] = $column->Field;
                }

                // Columns to add with their definitions
                $columns_to_add = [
                    'status' => "VARCHAR(50) DEFAULT 'pending'",
                    'progress_percent' => "FLOAT DEFAULT 0",
                    'ai_started_at' => "DATETIME DEFAULT NULL",
                    'ai_completed_at' => "DATETIME DEFAULT NULL",
                    'processing_duration' => "FLOAT DEFAULT NULL",
                    'failed_at' => "DATETIME DEFAULT NULL"
                ];

                foreach ($columns_to_add as $column_name => $column_definition) {
                    if (!in_array($column_name, $existing_columns)) {
                        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared -- Table/column names are safe, definition is hardcoded
                        $sql = "ALTER TABLE `{$table_name}` ADD COLUMN `{$column_name}` {$column_definition}";
                        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Query built with safe table/column names above, schema change required for repair
                        $result = $wpdb->query($sql);

                        if ($result !== false) {
                            $repairs_made[] = "Added column: {$column_name}";
                        } else {
                            $errors[] = "Failed to add column {$column_name}: " . $wpdb->last_error;
                        }
                    }
                }

                // Add index on status if not exists
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is safe (uses $wpdb->prefix)
                $index_exists = $wpdb->get_var("SHOW INDEX FROM `{$table_name}` WHERE Key_name = 'idx_status'");
                if (!$index_exists && in_array('status', $existing_columns) || in_array('status', array_keys($columns_to_add))) {
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is safe (uses $wpdb->prefix), schema change required for repair
                    $wpdb->query("ALTER TABLE `{$table_name}` ADD INDEX idx_status (status)");
                    if (!$wpdb->last_error) {
                        $repairs_made[] = "Added index: idx_status";
                    }
                }
            }

            // Update DB version
            update_option('genwave_db_version', '1.0.8');

            // Build result message
            $result_message = '';
            if (!empty($repairs_made)) {
                $result_message = count($repairs_made) . ' repairs made';
            } else {
                $result_message = 'No repairs needed';
            }

            if (!empty($errors)) {
                wp_safe_redirect(add_query_arg('error', urlencode(implode(', ', $errors)), admin_url('admin.php?page=ai-tools')));
            } else {
                wp_safe_redirect(add_query_arg('db_repaired', urlencode($result_message), admin_url('admin.php?page=ai-tools')));
            }
            exit;

        } catch (\Exception $e) {
            wp_safe_redirect(add_query_arg('error', urlencode($e->getMessage()), admin_url('admin.php?page=ai-tools')));
            exit;
        }
    }
}