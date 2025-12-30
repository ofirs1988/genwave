<?php

namespace GenWavePlugin;

if (!defined('ABSPATH')) {
    exit;
}

use GenWavePlugin\Core\AdminPageManager;

class Main {
    public function init(AdminPageManager $adminPageManager) {
        $adminPageManager->addPage(
            'Genwave Plugin Settings',
            'Genwave Settings',
            'manage_options',
            'gen-wave-plugin-settings',
            [$this, 'renderAdminPage'],
            'dashicons-admin-generic'
        );
    }

    public function customFunction() {
        // Your custom logic here
        if (defined('WP_DEBUG') && WP_DEBUG) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug mode only
            error_log('Genwave Plugin is running!');
        }
    }
}