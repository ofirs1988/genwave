<?php
/**
 * Runs when the plugin is deleted from Plugins (not on deactivation).
 *
 * Removes what this plugin stored: the connection (license key, site id and
 * the per-site signing key), its settings, its transients and its tables.
 * Left on purpose: options other Genwave plugins write and read themselves
 * (genwave_agent_plan_state, aiaw_credits), so deleting this plugin does not
 * break the Agent while it is still installed, and the `{prefix}jobs` table
 * unless it is empty (the name is generic; another plugin may use it).
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/** One site's data. */
function genwave_uninstall_site() {
    global $wpdb;

    // Settings and the connection (Core\Config keys are stored as genwave_<key>).
    $options = [
        'genwave_license_key',
        'genwave_domain',
        'genwave_active',
        'genwave_uidd',
        'genwave_token',
        'genwave_plan',
        'genwave_credits',
        'genwave_license_expired',
        'genwave_expiration_date',
        'genwave_site_uid',
        'genwave_site_key',
        'genwave_disconnected',
        'genwave_admin_option',
        'genwave_mybusiness_settings',
        'genwave_db_version',
        'genwave_installation_date',
        'genwave_tables_installed',
        'genwave_rest_api_flushed_v2',
    ];
    foreach ($options as $option) {
        delete_option($option);
    }

    delete_transient('genwave_challenge_open');
    delete_transient('genwave_activation_redirect');
    delete_transient('genwave_plugin_marketplace_list');

    $tables = [
        $wpdb->prefix . 'gen_requests_posts', // before gen_requests (foreign key)
        $wpdb->prefix . 'gen_requests',
        $wpdb->prefix . 'gen_settings',
        $wpdb->prefix . 'gen_token_usage',
        $wpdb->prefix . 'gen_status',
    ];
    foreach ($tables as $table) {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Table names are fixed and prefixed; dropping them is the point of uninstall
        $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
    }

    $jobs = $wpdb->prefix . 'jobs';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off check during uninstall
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $jobs)) === $jobs) {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Fixed, prefixed table name
        $rows = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$jobs}`");
        if ($rows === 0) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Fixed, prefixed table name; only when empty
            $wpdb->query("DROP TABLE IF EXISTS `{$jobs}`");
        }
    }
}

if (is_multisite()) {
    foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $site_id) {
        switch_to_blog($site_id);
        genwave_uninstall_site();
        restore_current_blog();
    }
} else {
    genwave_uninstall_site();
}

// The "Don't show this again" choice on the reconnect notice (user meta is network-wide).
delete_metadata('user', 0, 'genwave_reconnect_dismissed', '', true);
