<?php

namespace GenWavePlugin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Installation Manager Class
 * Handles smart installation checks and database table creation for Gen Wave
 */
class InstallationManager {

    /**
     * Option name to store installation status
     */
    const INSTALLATION_OPTION = 'genwave_tables_installed';

    /**
     * Static cache to avoid multiple checks per request
     */
    private static $checked = null;

    /**
     * List of required tables (without wp_ prefix)
     */
    const REQUIRED_TABLES = [
        'gen_requests',
        'gen_requests_posts',
        'gen_settings',
        'gen_token_usage',
        'gen_status',
        'jobs'
    ];

    /**
     * Check if installation is needed and run it if necessary
     *
     * @return bool True if installation was run or not needed, false on error
     */
    public static function checkAndInstall() {
        // Static cache - only check once per request
        if (self::$checked !== null) {
            return self::$checked;
        }

        // First check: Option exists and is true
        $installation_status = get_option(self::INSTALLATION_OPTION, false);

        if ($installation_status) {
            // Installation was marked as complete, skip verification for performance
            self::$checked = true;
            return true;
        }

        // Second check: Verify all tables exist
        if (!self::verifyTablesExist()) {
            self::$checked = self::runInstallation();
            return self::$checked;
        }

        // Tables exist but option is not set, mark as installed
        update_option(self::INSTALLATION_OPTION, true);
        self::$checked = true;
        return true;
    }

    /**
     * Verify that all required tables exist in the database
     *
     * @return bool True if all tables exist, false otherwise
     */
    private static function verifyTablesExist() {
        global $wpdb;

        $missing_tables = [];

        foreach (self::REQUIRED_TABLES as $table_name) {
            $full_table_name = $wpdb->prefix . $table_name;

            // Check if table exists
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Required to check if plugin table exists
            $table_exists = $wpdb->get_var($wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $full_table_name
            ));

            if ($table_exists !== $full_table_name) {
                $missing_tables[] = $full_table_name;
            }
        }

        if (!empty($missing_tables)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('Gen Wave: Missing tables: ' . implode(', ', $missing_tables));
            }
            return false;
        }

        return true;
    }

    /**
     * Run the installation process
     *
     * @return bool True on success, false on error
     */
    private static function runInstallation() {
        try {
            // Include the install.php file
            $install_file = GEN_WAVE_PATH . 'install.php';

            if (!file_exists($install_file)) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log('Gen Wave: install.php file not found at: ' . $install_file);
                }
                return false;
            }

            // Include the installation file
            require_once $install_file;

            // Call the installation function
            if (function_exists('genwave_create_tables')) {
                $result = genwave_create_tables();

                // Verify installation was successful
                if (self::verifyTablesExist()) {
                    update_option(self::INSTALLATION_OPTION, true);
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                        error_log('Gen Wave: Installation completed successfully.');
                    }
                    return true;
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                        error_log('Gen Wave: Installation failed - tables still missing after creation attempt.');
                    }
                    return false;
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                    error_log('Gen Wave: genwave_create_tables function not found in install.php');
                }
                return false;
            }

        } catch (\Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
                error_log('Gen Wave: Installation error: ' . $e->getMessage());
            }
            return false;
        }
    }

    /**
     * Force reinstall - useful for debugging or updates
     *
     * @return bool True on success, false on error
     */
    public static function forceReinstall() {
        delete_option(self::INSTALLATION_OPTION);
        return self::checkAndInstall();
    }

    /**
     * Get installation status information
     *
     * @return array Status information
     */
    public static function getInstallationStatus() {
        $option_status = get_option(self::INSTALLATION_OPTION, false);
        $tables_exist = self::verifyTablesExist();

        return [
            'option_set' => $option_status,
            'tables_exist' => $tables_exist,
            'is_installed' => $option_status && $tables_exist,
            'required_tables' => self::REQUIRED_TABLES
        ];
    }
}
