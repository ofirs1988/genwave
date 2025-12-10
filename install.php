<?php
/**
 * Gen Wave - Database Installation
 * Creates all necessary database tables on plugin activation
 *
 * Table Prefix: gen_ (instead of ai_pro_)
 *
 * Tables created:
 * - gen_requests
 * - gen_requests_posts
 * - gen_settings
 * - gen_token_usage
 * - gen_status
 * - jobs
 */

/**
 * Function to create necessary database tables upon plugin activation.
 */
function genwave_create_tables() {
    global $wpdb;
    if (defined('WP_DEBUG') && WP_DEBUG) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
        error_log('Gen Wave: genwave_create_tables called');
    }

    // Define the charset and collation
    $charset_collate = $wpdb->get_charset_collate();
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    /**
     * Table: gen_requests
     * Main requests table for tracking AI generation jobs
     */
    $table_name_requests = $wpdb->prefix . 'gen_requests';
    $sql_requests = "CREATE TABLE IF NOT EXISTS {$table_name_requests} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        job_id BIGINT(20) UNSIGNED NOT NULL,
        args TEXT NOT NULL,
        request_time FLOAT DEFAULT NULL,
        user_id BIGINT(20) NOT NULL,
        type VARCHAR(20) DEFAULT NULL,
        response_data LONGTEXT DEFAULT NULL,
        response_time DATETIME DEFAULT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        progress FLOAT DEFAULT 0,
        current_stage VARCHAR(50) DEFAULT NULL,
        error TEXT DEFAULT NULL,
        total_items INT DEFAULT 0,
        processed_items INT DEFAULT 0,
        delivered TINYINT(1) DEFAULT 0 COMMENT '0=not delivered, 1=delivered to WordPress',
        delivered_at DATETIME DEFAULT NULL,
        laravel_completed TINYINT(1) DEFAULT 0,
        laravel_completed_at DATETIME DEFAULT NULL,
        deleted_at DATETIME DEFAULT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_job_id (job_id),
        KEY idx_delivered (delivered, status),
        KEY idx_status (status),
        KEY idx_user_id (user_id)
    ) {$charset_collate}";

    dbDelta($sql_requests);

    if ($wpdb->last_error) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
            error_log('Gen Wave: Error creating gen_requests table: ' . $wpdb->last_error);
        }
        return false;
    }

    /**
     * Table: gen_requests_posts
     * Links between requests and posts with additional metadata
     */
    $table_name_posts = $wpdb->prefix . 'gen_requests_posts';
    $sql_posts = "CREATE TABLE IF NOT EXISTS {$table_name_posts} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        request_id BIGINT(20) UNSIGNED NOT NULL,
        post_id BIGINT(20) UNSIGNED NOT NULL,
        generation_type VARCHAR(50) DEFAULT NULL,
        post_type VARCHAR(50) NOT NULL,
        status VARCHAR(50) DEFAULT 'pending',
        progress_percent FLOAT DEFAULT 0,
        ai_started_at DATETIME DEFAULT NULL,
        ai_completed_at DATETIME DEFAULT NULL,
        processing_duration FLOAT DEFAULT NULL,
        failed_at DATETIME DEFAULT NULL,
        old TEXT DEFAULT NULL,
        additional_content LONGTEXT NULL,
        get_response TINYINT(1) DEFAULT 0,
        is_converted TINYINT(1) DEFAULT 0,
        ai TINYINT(1) DEFAULT 0 COMMENT '1 = wp, 2 = ai',
        applied_fields TEXT NULL,
        convert_time DATETIME DEFAULT NULL,
        deleted_at DATETIME DEFAULT NULL,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY request_id (request_id),
        KEY post_id (post_id),
        KEY idx_status (status),
        CONSTRAINT fk_gen_request_id FOREIGN KEY (request_id)
            REFERENCES {$table_name_requests} (id)
            ON DELETE CASCADE
    ) {$charset_collate}";

    dbDelta($sql_posts);

    if ($wpdb->last_error) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
            error_log('Gen Wave: Error creating gen_requests_posts table: ' . $wpdb->last_error);
        }
    }

    /**
     * Table: gen_settings
     * Plugin settings storage
     */
    $table_name_settings = $wpdb->prefix . 'gen_settings';
    $sql_settings = "CREATE TABLE IF NOT EXISTS {$table_name_settings} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        option_name VARCHAR(191) NOT NULL UNIQUE,
        option_value LONGTEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) {$charset_collate};";

    dbDelta($sql_settings);

    if ($wpdb->last_error) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
            error_log('Gen Wave: Error creating gen_settings table: ' . $wpdb->last_error);
        }
    }

    /**
     * Table: gen_token_usage
     * Tracks token usage and costs for AI requests
     */
    $table_name_token_usage = $wpdb->prefix . 'gen_token_usage';
    $sql_token_usage = "CREATE TABLE IF NOT EXISTS {$table_name_token_usage} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        job_id BIGINT(20) UNSIGNED NOT NULL,
        tokens_estimated DECIMAL(15,8) DEFAULT 0.00000000,
        tokens_actually_used DECIMAL(15,8) DEFAULT 0.00000000,
        tokens_refunded DECIMAL(15,8) DEFAULT 0.00000000,
        tokens_charged_to_user DECIMAL(15,8) DEFAULT 0.00000000,
        refund_applied TINYINT(1) DEFAULT 0,
        usage_efficiency DECIMAL(15,8) DEFAULT 0.00000000,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_job_id (job_id)
    ) {$charset_collate}";

    dbDelta($sql_token_usage);

    if ($wpdb->last_error) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
            error_log('Gen Wave: Error creating gen_token_usage table: ' . $wpdb->last_error);
        }
    }

    /**
     * Table: gen_status
     * Tracks job status and errors
     */
    $table_name_status = $wpdb->prefix . 'gen_status';
    $sql_status = "CREATE TABLE IF NOT EXISTS {$table_name_status} (
        id MEDIUMINT(9) NOT NULL AUTO_INCREMENT,
        job_id MEDIUMINT(9) NOT NULL,
        status VARCHAR(50) NOT NULL,
        error_message TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY job_id (job_id)
    ) {$charset_collate}";

    dbDelta($sql_status);

    if ($wpdb->last_error) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
            error_log('Gen Wave: Error creating gen_status table: ' . $wpdb->last_error);
        }
    }

    /**
     * Table: jobs
     * Queue table for background processing
     */
    $table_name_jobs = $wpdb->prefix . 'jobs';
    $sql_jobs = "CREATE TABLE IF NOT EXISTS {$table_name_jobs} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        queue VARCHAR(255) NOT NULL,
        payload LONGTEXT NOT NULL,
        attempts INT(11) NOT NULL DEFAULT 0,
        reserved_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_queue (queue),
        KEY idx_reserved_at (reserved_at)
    ) {$charset_collate};";

    dbDelta($sql_jobs);

    if ($wpdb->last_error) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
            error_log('Gen Wave: Error creating jobs table: ' . $wpdb->last_error);
        }
    }

    // Mark installation as complete
    update_option('genwave_tables_installed', true);
    update_option('genwave_installation_date', current_time('mysql'));

    if (defined('WP_DEBUG') && WP_DEBUG) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
        error_log('Gen Wave: All tables created successfully');
    }
    return true;
}

/**
 * Drop all Gen Wave tables (for uninstallation)
 */
function genwave_drop_tables() {
    global $wpdb;

    $tables = [
        $wpdb->prefix . 'gen_requests_posts', // Drop this first due to foreign key
        $wpdb->prefix . 'gen_requests',
        $wpdb->prefix . 'gen_settings',
        $wpdb->prefix . 'gen_token_usage',
        $wpdb->prefix . 'gen_status',
        $wpdb->prefix . 'jobs'
    ];

    foreach ($tables as $table) {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Table name is safe (uses $wpdb->prefix), schema change required for uninstall
        $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
    }

    delete_option('genwave_tables_installed');
    delete_option('genwave_installation_date');

    if (defined('WP_DEBUG') && WP_DEBUG) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
        error_log('Gen Wave: All tables dropped');
    }
}
