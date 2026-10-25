<?php

namespace GenWavePlugin\Pages;

if (!defined('ABSPATH')) {
    exit;
}

use GenWavePlugin\Core\AdminPageManager;
use GenWavePlugin\Core\Config;
use GenWavePlugin\Core\ViewManager;
use GenWavePlugin\Helper\Decrypt;

class Settings
{
    public $adminPageManager;

    public function __construct(AdminPageManager $adminPageManager)
    {
        $this->adminPageManager = $adminPageManager;
        add_action('wp_loaded', array($this, 'registerSettings'));
        add_action('admin_notices', function() {
            // Only if we're on the plugin settings page
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only, no state changes
            if (isset($_GET['error-license']) && sanitize_text_field(wp_unslash($_GET['error-license'])) == '1') {
                // Check if license doesn't exist
                $license_key = get_option('genwave_license_key');
                if (!$license_key) {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('You must enter a license key to use Genwave.', 'gen-wave') . '</p></div>';
                }
            }
        });
        // add_action('admin_init', [$this, 'check_for_handle_laravel_token']);
    }

    public function registerSettings()
    {
        $this->adminPageManager->addPage(
            'Genwave Account',
            'Genwave',
            'manage_options',
            'gen-wave-plugin-settings',
            [$this, 'renderAdminPage'],
            'dashicons-admin-generic'
        );

        $this->adminPageManager->addSubmenu(
            'gen-wave-plugin-settings', // The submenu will be added under Genwave
            'Genwave Account',
            'Account',
            'manage_options',
            'gen-wave-plugin-dashboard',
            [$this, 'renderAdminPage'],
            1
        );
    }

    public function renderAdminPage()
    {

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only, no state changes
        if (isset($_GET['error'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only, no state changes
            echo ' <div class="notice notice-error is-dismissible"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['error']))) . '</p></div>';
        }



        // REMOVED (finding F6): the legacy connect path read the SSO token, uuid,
        // domain and plan straight from $_GET and persisted them with no nonce and
        // no state check — a CSRF that let a crafted URL bind this site to an
        // attacker's account. Connecting now happens only through app.genwave.ai:
        // Connect to Genwave (Core\PanelConnect, state + PKCE) or a pasted key
        // (AgentAuth::connect, HMAC challenge). Do not reintroduce a $_GET
        // credential handler.


        if (isset($_POST['save_settings']) && check_admin_referer('save_ai_settings', 'ai_settings_nonce')) {
            $license_key = isset($_POST['ai_license_key']) ? sanitize_text_field(wp_unslash($_POST['ai_license_key'])) : '';
            // $domain = sanitize_text_field($_POST['ai_domain']);
            if (!empty($license_key)) {
                // Connect with the pasted key right away (app.genwave.ai checks it
                // and proves this site); if that fails the site keeps the key it had.
                $previous_key = Config::get('license_key');
                Config::set('license_key', $license_key);
                $connected = \GenWavePlugin\Core\AgentAuth::connect();
                if (!empty($connected['success'])) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Your site is connected to Genwave.', 'gen-wave') . '</p></div>';
                } else {
                    Config::set('license_key', (string) $previous_key);
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($connected['message'] ?? __('Connection failed. Please try again.', 'gen-wave')) . '</p></div>';
                    return false;
                }
            } else {
                echo ' <div class="notice notice-error is-dismissible"><p>The license key field is required and cannot be empty.</p></div>';
            }
        }

        $license_key = esc_attr(Config::get('license_key'));
        // $domain = esc_attr(Config::get('domain'));

        $data = [
            'license_key' => $license_key,
            'active' => Config::get('active'),
            'uidd' => Config::get('uidd') ?? null,
            'license_expired' => Config::get('license_expired') ?? '0',
            'expiration_date' => Config::get('expiration_date') ?? null,
            'credits' => Config::get('credits') ?? 0,
        ];



        $view_manager = new ViewManager(plugin_dir_path(__FILE__) . '../../views');
        $view_manager->render('settings', [
            'message' => $message ?? '',
            'data' => $data,
        ]);
    }
}
