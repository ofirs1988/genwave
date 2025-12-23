<?php

namespace GenWavePlugin;

if (!defined('ABSPATH')) {
    exit;
}

use GenWavePlugin\Global\AdminPageManager;
use GenWavePlugin\Global\AiRestApi;
use GenWavePlugin\Global\Enqueue;
use GenWavePlugin\Pages\Settings;
use GenWavePlugin\Pages\Tools;
use GenWavePlugin\Pages\Dashboard;
use GenWavePlugin\Pages\Generate;

class Plugin {
    protected $adminPageManager;
    public function __construct() {
        // Initialize the plugin
        $this->initialize();
    }

    public function initialize() {
        // Load AJAX manager for both admin and frontend
        new AjaxManager();

        // Handle integration callback from Laravel (secure authentication flow)
        // Use admin_init hook to ensure WordPress functions are loaded
        add_action('admin_init', function() {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- External OAuth-like callback, session-based auth
            if (isset($_GET['credentials_session'])) {
                \GenWavePlugin\Controllers\IntegrationCallbackController::handleCallback();
            }
        });

        $this->loadAdminClasses();
        new AiRestApi();


    }

    private function loadAdminClasses(): void
    {
        // Load admin-specific classes
        $this->adminPageManager = new AdminPageManager();
        // Register the AdminPage
        new AdminBar();
        new Enqueue();
        new Settings($this->adminPageManager);
        new Dashboard($this->adminPageManager);
        new Generate($this->adminPageManager);
        new Tools($this->adminPageManager);

        // Register MetaBox for posts and products (only if Pro is not active)
        new MetaBox();

        // Register the WooCommerce AI Submenu
        do_action('gen_wave_loaded');

    }

    private function loadFrontendClasses() {
        // Load frontend-specific classes
        new Frontend();
    }
}
