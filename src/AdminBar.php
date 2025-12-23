<?php

namespace GenWavePlugin;

if (!defined('ABSPATH')) {
    exit;
}

use GenWavePlugin\Global\Config;

class AdminBar {
    public function __construct()
    {
        add_action('admin_bar_menu', [$this,'show_tokens_on_to_admin_bar'], 100);
    }

    function show_tokens_on_to_admin_bar($wp_admin_bar) {
        $tokens = Config::get('tokens');

        // Diamond emoji icon (same as Job Monitor table)
        $icon = '💎';

        // Format the tokens to show 2 decimal places
        $formatted_tokens = $tokens > 0 ? number_format((float)$tokens, 2, '.', '') : '0.00';

        // Format the text display
        $text = '<span>' . $formatted_tokens . '</span>' . ' Tokens';

        // Add the icon and text to the Admin bar
        $args = array(
            'id'    => 'custom_text_with_icon',  // Unique element ID
            'title' => $icon . ' ' . $text, // Combine icon and text
            'meta'  => array(
                'class' => 'gen-wave-admin-bar-tokens', // Custom CSS class
            ),
        );

        // Add new button to admin bar
        $wp_admin_bar->add_node($args);
    }
}