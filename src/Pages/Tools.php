<?php

namespace GenWavePlugin\Pages;

if (!defined('ABSPATH')) {
    exit;
}

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
            'Tools',
            'Tools',
            'manage_options',
            'ai-tools',
            array($this, 'render_tools_page'),
            999
        );
    }

    public function render_tools_page() {
        // Handle success/error messages
        $message = '';
        $message_type = '';

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only, no state changes
        if (isset($_GET['cache_cleared'])) {
            $message = 'Cache cleared successfully!';
            $message_type = 'success';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only, no state changes
        } elseif (isset($_GET['update_checked'])) {
            $message = 'Update check completed successfully!';
            $message_type = 'success';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only, no state changes
        } elseif (isset($_GET['db_repaired'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only, no state changes
            $message = 'Database repair completed! ' . sanitize_text_field(wp_unslash($_GET['db_repaired']));
            $message_type = 'success';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only, no state changes
        } elseif (isset($_GET['error'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only, no state changes
            $message = 'An error occurred: ' . sanitize_text_field(wp_unslash($_GET['error']));
            $message_type = 'error';
        }

        ?>
        <div class="wrap">
            <h1>
                <span class="dashicons dashicons-admin-tools" style="margin-right: 8px;"></span>
                Tools - Gen Wave Plugin
            </h1>

            <?php if ($message): ?>
                <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>

            <div class="card" style="max-width: 800px;">
                <h2 class="title">Cache & Updates Management</h2>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label>Clear Update Cache</label>
                            </th>
                            <td>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: inline;">
                                    <?php wp_nonce_field('ai_clear_cache_nonce', 'ai_nonce'); ?>
                                    <input type="hidden" name="action" value="ai_clear_cache">
                                    <button type="submit" class="button button-secondary" onclick="return confirm('Are you sure you want to clear the cache?')">
                                        <span class="dashicons dashicons-trash" style="margin-top: 3px; margin-left: 5px;"></span>
                                        Clear Cache
                                    </button>
                                </form>
                                <p class="description">
                                    Clears all plugin update cache and forces a fresh check from the server.
                                </p>
                            </td>
                        </tr>

                    </tbody>
                </table>
            </div>

            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2 class="title">Database Management</h2>
                <?php $db_status = $this->get_database_status(); ?>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">Database Status</th>
                            <td>
                                <?php if ($db_status['all_ok']): ?>
                                    <span style="color: #00a32a;">✅ All tables OK</span>
                                <?php else: ?>
                                    <span style="color: #dc3232;">❌ Issues detected</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Tables Status</th>
                            <td>
                                <?php foreach ($db_status['tables'] as $table => $status): ?>
                                    <div style="margin-bottom: 5px;">
                                        <?php if ($status['exists']): ?>
                                            <span style="color: #00a32a;">✅</span>
                                        <?php else: ?>
                                            <span style="color: #dc3232;">❌</span>
                                        <?php endif; ?>
                                        <code><?php echo esc_html($table); ?></code>
                                        <?php if (!empty($status['missing_columns'])): ?>
                                            <br><small style="color: #dc3232; margin-right: 20px;">Missing columns: <?php echo esc_html(implode(', ', $status['missing_columns'])); ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">DB Version</th>
                            <td><code><?php echo esc_html(get_option('genwave_db_version', 'Not set')); ?></code></td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label>Repair Database</label>
                            </th>
                            <td>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: inline;">
                                    <?php wp_nonce_field('ai_repair_database_nonce', 'ai_nonce'); ?>
                                    <input type="hidden" name="action" value="ai_repair_database">
                                    <button type="submit" class="button button-primary" onclick="return confirm('This will check and repair all database tables. Continue?')">
                                        <span class="dashicons dashicons-database-add" style="margin-top: 3px; margin-left: 5px;"></span>
                                        Check &amp; Repair Database
                                    </button>
                                </form>
                                <p class="description">
                                    Checks all plugin tables and adds any missing columns or tables.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2 class="title">System Information</h2>
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row">Current Version</th>
                            <td><code><?php echo esc_html(GEN_WAVE_VERSION); ?></code></td>
                        </tr>
                        <tr>
                            <th scope="row">Update Server URL</th>
                            <td><code><?php echo esc_html(GENWAVE_API_URL . '/api/check-update-free'); ?></code></td>
                        </tr>
                        <tr>
                            <th scope="row">Cache Key</th>
                            <td><code><?php echo esc_html('genwave_update_check_' . md5('ai/ai.php')); ?></code></td>
                        </tr>
                        <tr>
                            <th scope="row">Cache Status</th>
                            <td>
                                <?php
                                $cache_key = 'genwave_update_check_' . md5('ai/ai.php');
                                $cached_data = get_transient($cache_key);
                                if ($cached_data === false): ?>
                                    <span style="color: #dc3232;">❌ No Cache</span>
                                <?php else: ?>
                                    <span style="color: #00a32a;">✅ Cache Present</span>
                                    <?php if (is_object($cached_data) && isset($cached_data->new_version)): ?>
                                        <br><small>Available version: <?php echo esc_html($cached_data->new_version); ?></small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">WordPress Updates Status</th>
                            <td>
                                <?php
                                $wp_updates = get_site_transient('update_plugins');
                                $plugin_slug = 'ai/ai.php';
                                if ($wp_updates && isset($wp_updates->response[$plugin_slug])): ?>
                                    <span style="color: #ff8c00;">🔄 Update Available</span>
                                    <br><small>New version: <?php echo esc_html($wp_updates->response[$plugin_slug]->new_version); ?></small>
                                <?php else: ?>
                                    <span style="color: #00a32a;">✅ Up to Date</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2 class="title">Useful Links</h2>
                <p>
                    <a href="<?php echo esc_url(admin_url('plugins.php')); ?>" class="button">
                        <span class="dashicons dashicons-admin-plugins" style="margin-top: 3px; margin-left: 5px;"></span>
                        Plugins Page
                    </a>

                    <a href="<?php echo esc_url(admin_url('update-core.php')); ?>" class="button" style="margin-right: 10px;">
                        <span class="dashicons dashicons-update" style="margin-top: 3px; margin-left: 5px;"></span>
                        WordPress Updates
                    </a>

                    <a href="<?php echo esc_url(admin_url('plugins.php?clear_update_cache=1')); ?>" class="button" style="margin-right: 10px;">
                        <span class="dashicons dashicons-editor-refresh" style="margin-top: 3px; margin-left: 5px;"></span>
                        Refresh Cache (URL)
                    </a>
                </p>
            </div>
        </div>
        <?php
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
            // Clear AI plugin cache
            $cache_key = 'genwave_update_check_' . md5('ai/ai.php');
            delete_transient($cache_key);

            // Clear WordPress update caches
            delete_site_transient('update_plugins');
            delete_site_transient('update_themes');
            delete_site_transient('update_core');

            // Clear additional caches
            wp_cache_flush();
            wp_clean_plugins_cache();

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