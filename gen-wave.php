<?php
/*
 * Plugin Name: Genwave - AI Generate
 * Description: AI-powered content generation for WordPress posts and WooCommerce products. Generate titles, descriptions, and more with custom instructions in any language.
 * Version: 1.0.3
 * Author: Genwave.ai
 * Author URI: https://genwave.ai
 * Text Domain: gen-wave
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html

Gen Wave is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

Gen Wave is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Gen Wave. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define( 'GEN_WAVE_VERSION', '1.0.3' );

define( 'GEN_WAVE__FILE__', __FILE__ );
define( 'GEN_WAVE_PLUGIN_BASE', plugin_basename( GEN_WAVE__FILE__ ) );
define( 'GEN_WAVE_PATH', plugin_dir_path( GEN_WAVE__FILE__ ) );
//define( 'GEN_WAVE_DOMAIN', $_SERVER['SERVER_NAME'] );



if ( defined( 'GEN_WAVE_TESTS' ) && GEN_WAVE_TESTS ) {
    define( 'GEN_WAVE_URL', 'file://' . GEN_WAVE_PATH );
} else {
    define( 'GEN_WAVE_URL', plugins_url( '/', GEN_WAVE__FILE__ ) );
}

define( 'GEN_WAVE_MODULES_PATH', plugin_dir_path( GEN_WAVE__FILE__ ) . '/modules' );
define( 'GEN_WAVE_ASSETS_PATH', GEN_WAVE_PATH . 'assets/' );
const GEN_WAVE_ASSETS_URL = GEN_WAVE_URL . 'assets/';

/**
 * Check if running on localhost environment
 */
function gen_wave_is_localhost($whitelist = ['127.0.0.1', '::1']) {
    // Check REMOTE_ADDR first
    $remote_addr = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    if (in_array($remote_addr, $whitelist)) {
        return true;
    }

    // Also check HTTP_HOST for background processes where REMOTE_ADDR is not set
    $http_host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
    $server_name = isset($_SERVER['SERVER_NAME']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_NAME'])) : '';

    $localhost_hosts = ['localhost', '127.0.0.1', 'wp.local', 'wp-ai.local'];
    if (in_array($http_host, $localhost_hosts) || in_array($server_name, $localhost_hosts)) {
        return true;
    }

    // Check if running in CLI mode (like WP-Cron)
    if (php_sapi_name() === 'cli' || defined('WP_CLI')) {
        // In CLI mode, check if we're on localhost by site_url
        $site_url = get_option('siteurl', '');
        if (strpos($site_url, 'localhost') !== false || strpos($site_url, '.local') !== false) {
            return true;
        }
    }

    return false;
}

// API URLs - use wp-config.php constants for development override
if (!defined('GEN_WAVE_DOMAIN')) {
    $gen_wave_http_host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
    define('GEN_WAVE_DOMAIN', $gen_wave_http_host ?: 'genwave.ai');
}

if (!defined('GENWAVE_API_URL')) {
    define('GENWAVE_API_URL', 'https://account.genwave.ai');
}

if (!defined('GEN_WAVE_SMART_API')) {
    define('GEN_WAVE_SMART_API', 'https://api.genwave.ai');
}


/**
 * ENCRYPTION KEY ARCHITECTURE
 *
 * This plugin uses a shared secret key for AES-256-CBC encryption between:
 * - WordPress Plugin (this code)
 * - Gen Wave Laravel Backend (account.genwave.ai)
 * - Gen Wave AI API (api.genwave.ai)
 *
 * SECURITY DESIGN:
 * 1. The key is stored in wp_options (database) for each installation
 * 2. The default key below serves as:
 *    - Initial setup fallback
 *    - Shared secret for service-to-service authentication
 *    - Backward compatibility with existing installations
 *
 * 3. Key rotation is supported through the Gen Wave dashboard
 * 4. All three services must use the same key for encryption/decryption to work
 *
 * This is intentional security-by-design, not a vulnerability.
 */
$gen_wave_encryption_key = get_option('genwave_encryption_key');
if (!$gen_wave_encryption_key) {
    // Default shared secret - synchronized with Gen Wave backend services
    $gen_wave_encryption_key = 'ATW1kctl7zkJDLC7IRC8JDfPBrgREiLu';
}
define('GEN_WAVE_SECRET_KEY', $gen_wave_encryption_key);

// Include Composer's autoload file
require_once __DIR__ . '/vendor/autoload.php';

// Include upgrade script
require_once __DIR__ . '/upgrade.php';

// Instantiate and initialize the plugin
use GenWavePlugin\Plugin;

// Instantiate the Plugin class, which will manage everything
$plugin = new Plugin();

/**
 * Plugin activation hook - saves encryption key and creates database tables
 */
function genwave_plugin_activation() {
    // Check if encryption key already exists
    $existing_key = get_option('genwave_encryption_key');

    if (!$existing_key) {
        // Save default key to wp_options on first activation
        $default_key = 'ATW1kctl7zkJDLC7IRC8JDfPBrgREiLu';
        update_option('genwave_encryption_key', $default_key, false); // false = don't autoload
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
            error_log('Gen Wave: Encryption key saved to wp_options on activation');
        }
    }

    // Create database tables
    require_once GEN_WAVE_PATH . 'src/InstallationManager.php';
    \GenWavePlugin\InstallationManager::checkAndInstall();
}

// Register activation hook
register_activation_hook(__FILE__, 'genwave_plugin_activation');

/**
 * Smart Installation Check on Init
 *
 * Checks if tables exist on every load (but only installs if missing)
 * This is a safety net in case activation hook didn't run
 */
add_action('init', function() {
    // Load the InstallationManager class
    require_once GEN_WAVE_PATH . 'src/InstallationManager.php';

    // Check and install if needed (will skip if already installed)
    \GenWavePlugin\InstallationManager::checkAndInstall();
}, 1); // High priority to run early