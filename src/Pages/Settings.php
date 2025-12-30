<?php

namespace GenWavePlugin\Pages;

if (!defined('ABSPATH')) {
    exit;
}

use GenWavePlugin\Core\AdminPageManager;
use GenWavePlugin\Core\ApiManager;
use GenWavePlugin\Core\Config;
use GenWavePlugin\Core\ViewManager;
use GenWavePlugin\Helper\Decrypt;
use GenWavePlugin\Helper\License;

class Settings
{
    public $adminPageManager;

    public function __construct(AdminPageManager $adminPageManager)
    {
        $this->adminPageManager = $adminPageManager;
        add_action('init', function () {
            // Add headers to all requests
            if (!headers_sent()) {
                header("Access-Control-Allow-Origin: " . GENWAVE_API_URL);
                header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
                header("Access-Control-Allow-Headers: X-Requested-With, Content-Type, Accept, Origin, Authorization");
            }
        });
        add_action('rest_api_init', function () {
            // Allow headers for OPTIONS requests as well
            if (!headers_sent()) {
                header("Access-Control-Allow-Origin: " . GENWAVE_API_URL);
                header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
                header("Access-Control-Allow-Headers: X-Requested-With, Content-Type, Accept, Origin, Authorization");
                header("Access-Control-Allow-Credentials: true");
            }
        }, 15);
        add_action('wp_loaded', array($this, 'registerSettings'));
        add_action('admin_notices', function() {
            // Only if we're on the plugin settings page
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only, no state changes
            if (isset($_GET['error-license']) && sanitize_text_field(wp_unslash($_GET['error-license'])) == '1') {
                // Check if license doesn't exist
                $license_key = get_option('genwave_license_key');
                if (!$license_key) {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('You must enter a license to use the AI PRO Plugin.', 'gen-wave') . '</p></div>';
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
        //$api_manager = new ApiManager('http://127.0.0.1:8000/api/');

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only, no state changes
        if (isset($_GET['error'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only, no state changes
            echo ' <div class="notice notice-error is-dismissible"><p>' . esc_html(sanitize_text_field(wp_unslash($_GET['error']))) . '</p></div>';
        }



        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only, no state changes
        if (isset($_GET['uuid'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only, no state changes
            if (isset($_GET['domain'])) {
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only, no state changes
                $uuid = sanitize_text_field(wp_unslash($_GET['uuid']));
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only, no state changes
                $domain = sanitize_text_field(wp_unslash($_GET['domain']));
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only, no state changes
                $token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only, no state changes
                $plan = isset($_GET['plan']) ? sanitize_text_field(wp_unslash($_GET['plan'])) : '';
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only, no state changes
                $tokens = isset($_GET['tokens']) ? sanitize_text_field(wp_unslash($_GET['tokens'])) : '';
                if ($domain == get_site_url()) {
                    //$accessToken = sanitize_text_field($_GET['token']);
                    Config::set('uidd', $uuid);
                    Config::set('token', $token);
                    Config::set('auth_date', time());
                    Config::set('domain', $domain);
                    Config::set('active', 1);
                    Config::set('plan', $plan);
                    Config::set('tokens', $tokens);
                    // Use WordPress inline script function instead of direct echo
                    wp_add_inline_script('jquery', 'history.replaceState({}, "", "/wp-admin/admin.php?page=gen-wave-plugin-settings");');
                    $view_manager = new ViewManager(plugin_dir_path(__FILE__) . '../../views');
                    $data = [
                        'license_key' => Config::get('license_key') ?? null,
                        'active' => Config::get('active'),
                        'uidd' => Config::get('uidd') ?? null,
                        'license_expired' => Config::get('license_expired') ?? '0',
                        'expiration_date' => Config::get('expiration_date') ?? null,
                    ];
                    $view_manager->render('settings', [
                        'message' => $message ?? '',
                        'data' => $data,
                    ]);
                    return true;
                }
            }
        }


        if (isset($_POST['save_settings']) && check_admin_referer('save_ai_settings', 'ai_settings_nonce')) {
            $license_key = isset($_POST['ai_license_key']) ? sanitize_text_field(wp_unslash($_POST['ai_license_key'])) : '';
            // $domain = sanitize_text_field($_POST['ai_domain']);
            if (!empty($license_key)) {
                License::$licenseKey = $license_key;
                License::initialize(new ApiManager(GENWAVE_API_URL));
                $verifyLicenseKey = License::verifyLicenseKey();
                if ($verifyLicenseKey['success']) {
                    //$this->redirect_to_laravel_login();
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($verifyLicenseKey['message']) . '</p></div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($verifyLicenseKey['message']) . '</p></div>';
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
            'tokens' => Config::get('tokens') ?? 0,
        ];



        $view_manager = new ViewManager(plugin_dir_path(__FILE__) . '../../views');
        $view_manager->render('settings', [
            'message' => $message ?? '',
            'data' => $data,
        ]);
    }
}
