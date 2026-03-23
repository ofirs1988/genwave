<?php

namespace GenWavePlugin;

if (!defined('ABSPATH')) {
    exit;
}

use GenWavePlugin\Core\Config;

class AdminBar {
    public function __construct()
    {
        add_action('admin_bar_menu', [$this,'show_credits_on_to_admin_bar'], 100);
    }

    function show_credits_on_to_admin_bar($wp_admin_bar) {
        $credits = Config::get('credits');

        // Diamond emoji icon (same as Job Monitor table)
        $icon = '💎';

        // Format the credits to show 2 decimal places
        $formatted_credits = $credits > 0 ? number_format((float)$credits, 2, '.', '') : '0.00';

        // Format the text display
        $text = '<span>' . $formatted_credits . '</span>' . ' Credits';

        // Add the icon and text to the Admin bar
        $args = array(
            'id'    => 'custom_text_with_icon',  // Unique element ID
            'title' => $icon . ' ' . $text, // Combine icon and text
            'meta'  => array(
                'class' => 'gen-wave-admin-bar-credits', // Custom CSS class
            ),
        );

        // Add new button to admin bar
        $wp_admin_bar->add_node($args);
    }
}