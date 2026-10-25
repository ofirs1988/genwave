<?php

namespace GenWavePlugin\Pages;

use GenWavePlugin\Core\AdminPageManager;

if (!defined('ABSPATH')) {
    exit;
}

class Dashboard
{
    private $adminPageManager;

    public function __construct(AdminPageManager $adminPageManager)
    {
        $this->adminPageManager = $adminPageManager;
        add_action('wp_loaded', array($this, 'register_page'));
    }

    public function register_page()
    {
        $this->adminPageManager->addSubmenu(
            'gen-wave-plugin-settings',
            __('Genwave', 'gen-wave'),
            __('Overview', 'gen-wave'),
            'manage_options',
            'gen-wave-dashboard',
            array($this, 'render_page'),
            2
        );
    }

    public function render_page()
    {
        \GenWavePlugin\Core\AdminApp::render('overview');
    }
}
