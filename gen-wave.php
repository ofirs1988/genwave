<?php
/*
 * Plugin Name: Genwave - AI Agent
 * Description: The #1 AI Agent for your website. Build plugins, fix errors, create pages, manage WooCommerce & optimize SEO — all through natural conversation. 250+ actions, 7-layer security, 48+ languages.
 * Version: 1.1.2
 * Author: Genwave.ai
 * Author URI: https://genwave.ai
 * Text Domain: gen-wave
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html

Genwave is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

Genwave is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Genwave. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define( 'GEN_WAVE_VERSION', '1.1.2' );

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

// The agent backend — where the anchor reads the credit balance and runs content
// generation. Replaces the retired liteLLM "smart API". Defined here so the free
// plugin works on its own; if wp-config or the agent plugin already set it, that
// value is kept.
if (!defined('GENWAVE_AGENT_API_URL')) {
    define('GENWAVE_AGENT_API_URL', 'https://agent.genwave.ai');
}


// Auth v2: the site connects with an HMAC challenge and signs every agent request
// with a per-site key (see src/Core/AgentAuth.php). No shared secret ships in source;
// the removed AES key (findings F1/F9) and GEN_WAVE_SECRET_KEY are gone entirely.

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
    // No encryption key is provisioned on activation anymore — the shared-key AES
    // layer was removed (findings F1/F9).

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