<?php

namespace GenWavePlugin;

if (!defined('ABSPATH')) {
    exit;
}

use GenWavePlugin\Core\AdminPageManager;
use GenWavePlugin\Core\AiRestApi;
use GenWavePlugin\Core\Enqueue;
use GenWavePlugin\Pages\Settings;
use GenWavePlugin\Pages\Tools;
use GenWavePlugin\Pages\Dashboard;
use GenWavePlugin\Pages\Generate;
use GenWavePlugin\Pages\Plugins;

class Plugin {
    protected $adminPageManager;
    public function __construct() {
        // Initialize the plugin
        $this->initialize();
    }

    public function initialize() {
        // Sign every outbound agent request (v2 auth). One global filter on the
        // anchor plugin covers this plugin and the Agent/Chatbot plugins.
        \GenWavePlugin\Core\AgentAuth::register();

        // "Connect to Genwave": connect by approving the site in the panel.
        \GenWavePlugin\Core\PanelConnect::register();

        // Read-only status questions from the customer's Genwave account.
        \GenWavePlugin\Core\SiteCommands::register();

        // The Genwave screens: one full-screen app in the Agent's design.
        \GenWavePlugin\Core\AdminApp::register();

        // Load AJAX manager for both admin and frontend
        new AjaxManager();


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
        new Plugins($this->adminPageManager);

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
