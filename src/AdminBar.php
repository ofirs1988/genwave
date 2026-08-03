<?php

namespace GenWavePlugin;

if (!defined('ABSPATH')) {
    exit;
}

use GenWavePlugin\Core\Config;

class AdminBar {
    public function __construct()
    {
        // Priority 81 puts the credits node immediately after the agent's
        // "My Agent" toolbar item (added at 80), so the two always sit together.
        add_action('admin_bar_menu', [$this,'show_credits_on_to_admin_bar'], 81);
    }

    function show_credits_on_to_admin_bar($wp_admin_bar) {
        // Read the SHARED balance (kept in sync with the agent via the backend),
        // falling back to the anchor's own copy.
        $credits = get_option('aiaw_credits', Config::get('credits'));

        // Lightning bolt — same mark the GenWave Agent uses (cyan #00ffd5).
        $icon = '<svg style="width:14px;height:14px;vertical-align:-2px;margin-right:2px;color:#00ffd5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/></svg>';

        // Format the credits to show 2 decimal places
        $formatted_credits = $credits > 0 ? number_format(floor((float)$credits * 100) / 100, 2, '.', '') : '0.00';

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