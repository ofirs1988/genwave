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


/**
 * ENCRYPTION KEY ARCHITECTURE
 *
 * This plugin uses a shared secret key for AES-256-CBC encryption between:
 * - WordPress Plugin (this code)
 * - Genwave Laravel Backend (account.genwave.ai)
 * - Genwave AI API (api.genwave.ai)
 *
 * SECURITY DESIGN:
 * 1. The key is stored in wp_options (database) for each installation
 * 2. The default key below serves as:
 *    - Initial setup fallback
 *    - Shared secret for service-to-service authentication
 *    - Backward compatibility with existing installations
 *
 * REMOVED (findings F1/F9): this plugin previously shipped a shared AES-256-CBC
 * key in source and used it to encrypt the SSO token/uidd. Because the plugin is
 * public, that key was public, so the encryption protected nothing. Credentials
 * are now delivered in plaintext over the authenticated, one-time
 * credentials_session channel (server-to-server HTTPS) and stored as-is. No key.
 */
// The former shared AES secret is gone (findings F1/F9): credentials now travel
// in plaintext over the authenticated one-time credentials_session channel, so no
// key is shipped in source. The constant is still defined — resolved only from an
// out-of-band value if one exists — so any lingering reference degrades to "" and
// never fatals.
if (!defined('GEN_WAVE_SECRET_KEY')) {
    define('GEN_WAVE_SECRET_KEY', (string) get_option('genwave_encryption_key', ''));
}

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

/**
 * Let the site's GenWave Front Site (the headless React site served from
 * *.genwave.site) read admin-ajax responses cross-origin.
 *
 * When a site is converted to a headless Front Site, WordPress stays as the CMS
 * and every plugin's front-end widget (live chat, popups, forms, …) still calls
 * this site's admin-ajax.php — but now from a different origin, which the
 * browser blocks unless the response carries CORS headers. WordPress only sends
 * them for "allowed" origins, so we add the request's own Front Site origin.
 *
 * Security: this only lets the browser READ the response for an origin that is
 * this customer's own front end. Every admin-ajax action still enforces its own
 * nonce / capability — CORS does not bypass authentication. REST (/wp-json) is
 * already origin-echoed by WordPress core, so only admin-ajax needs this.
 */
add_filter('allowed_http_origins', function ($origins) {
    $origin = function_exists('get_http_origin') ? get_http_origin() : '';
    if ($origin) {
        $host = strtolower((string) wp_parse_url($origin, PHP_URL_HOST));
        if ($host === 'genwave.site' || substr($host, -13) === '.genwave.site') {
            $origins[] = $origin;
        }
    }
    return $origins;
});