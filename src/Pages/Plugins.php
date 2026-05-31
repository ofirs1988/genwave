<?php

namespace GenWavePlugin\Pages;

use GenWavePlugin\Core\AdminPageManager;

if (!defined('ABSPATH')) {
    exit;
}

class Plugins
{
    private $adminPageManager;

    public function __construct(AdminPageManager $adminPageManager)
    {
        $this->adminPageManager = $adminPageManager;
        add_action('wp_loaded', [$this, 'register_page']);
    }

    public function register_page()
    {
        $this->adminPageManager->addSubmenu(
            'gen-wave-plugin-settings',
            __('Genwave Plugins', 'gen-wave'),
            __('Plugins', 'gen-wave'),
            'install_plugins',
            'gen-wave-plugins',
            [$this, 'render_page'],
            6
        );
    }

    public function render_page()
    {
        ?>
        <div id="genwave-plugins-app"></div>
        <?php
    }
}
