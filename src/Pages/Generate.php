<?php

namespace GenWavePlugin\Pages;

use GenWavePlugin\Core\AdminPageManager;

if (!defined('ABSPATH')) {
    exit;
}

class Generate
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
            'Generate',
            'Generate',
            'manage_options',
            'gen-wave-generate',
            array($this, 'render_page'),
            3
        );
    }

    public function render_page()
    {
        // Localize script with settings (wp_localize_script is called in Enqueue.php)
        // Just render the container div
        ?>
        <div id="genwave-generate-app"></div>
        <?php
    }
}
