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
            'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyMCAyMCI+PHBhdGggZD0iTS42IDExLjJDMi4yIDYuNiA1IDUgNy40IDUuMWMzIC4xIDQuNCAyLjMgNiAzLjggMS4yIDEgMy0uOSA0LjItMiAuOC0uNyAyLS44IDEuOC4xLS44IDIuNi0yLjggNi42LTUuOCA3LTMgLjQtNC44LTIuMi02LjYtMy42LTIuMi0xLjYtNC44LTEuMi02LjQuOHoiLz48cGF0aCBkPSJNMi42IDE0LjZjMS0zIDIuOC00IDQuNC00IDEuNiAxLjQgMyAzLjYgNS42IDQtMiAuMy0zLjItMS42LTUuOC0xLjctMS42IDAtMyAuNy00LjIgMS43eiIvPjwvc3ZnPg=='
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