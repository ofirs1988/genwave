<?php

namespace GenWavePlugin\Pages;

use GenWavePlugin\Global\AdminPageManager;
use GenWavePlugin\Global\Config;
use GenWavePlugin\Global\ViewManager;

class WooCommerceAIPage {
    protected $view_manager;

    public function __construct(AdminPageManager $adminPageManager) {
        $license_key = Config::get('license_key');
        $domain = Config::get('domain');
        if (!empty($license_key) || !empty($domain)) {
            $this->view_manager = new ViewManager(plugin_dir_path(__FILE__) . '../views');
            $adminPageManager->addSubmenu(
                'gen-wave-plugin-settings',  // Parent slug (WooCommerce menu)
                'WooCommerce AI Settings',
                'WooCommerce AI',
                'manage_options',
                'woocommerce-ai-settings',
                [$this, 'renderPagea'],
                2
            );
        }
    }

    public function renderPagea() {
        if (!class_exists('WooCommerce')) {
            $this->view_manager->render('woocommerce-ai-page', [
                'error_message' => 'WooCommerce is not installed or activated on this site.'
            ]);
            return;
        }

        // Check if license_key and domain are set
        $license_key = Config::get('license_key');
        $domain = Config::get('domain');
        if (empty($license_key) || empty($domain)) {
            $this->view_manager->render('woocommerce-ai-page', [
                'error_message' => 'You must have a valid License Key and Domain to access this page.'
            ]);
            return;
        }

        // Retrieve all WooCommerce products
        $args = [
            'post_type' => 'product',
            'posts_per_page' => -1
        ];
        $products = get_posts($args);

        // Determine the active tab
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only, no state changes
        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'general';

        // Render the view with products
        $this->view_manager->render('woocommerce-ai-page', [
            'active_tab' => $active_tab,
            'products' => $products,
        ]);
    }

}
