<?php

namespace GenWavePlugin;

if (!defined('ABSPATH')) {
    exit;
}

class AdminBar {
    public function __construct()
    {
        // Priority 81 puts the node immediately after the agent's "My Agent"
        // toolbar item (added at 80), so the two always sit together.
        add_action('admin_bar_menu', [$this,'show_usage_on_admin_bar'], 81);
    }

    /**
     * How usage stands, in words, or null when there is nothing to say.
     *
     * Plans have no credits to count. A paid plan shows nothing, unless the
     * month went far past normal use and the agent is on its lighter model
     * until renewal ("Light mode"). The Free plan shows about how many AI
     * actions are left (5 credits = ~30 actions). The plan state is written by
     * the Genwave Agent from each balance read (genwave_agent_plan_state);
     * without it nothing is shown rather than a guess.
     *
     * @return array{text:string,title:string,warn:bool}|null
     */
    public static function usage_label(): ?array
    {
        $state = get_option('genwave_agent_plan_state', null);
        if (!is_array($state)) {
            return null;
        }
        if (!empty($state['paid'])) {
            if (empty($state['over'])) {
                return null;
            }
            return [
                'text'  => __('Light mode', 'gen-wave'),
                'title' => __('This month went far past normal use, so Genwave is working on its lighter, faster model until your plan renews.', 'gen-wave'),
                'warn'  => true,
            ];
        }
        $actions = max(0, (int) floor((float) get_option('aiaw_credits', 0) * 6));
        return [
            /* translators: %d: approximate number of AI actions left this month */
            'text'  => sprintf(__('~%d AI actions left', 'gen-wave'), $actions),
            'title' => __('Free AI actions left this month', 'gen-wave'),
            'warn'  => $actions < 5,
        ];
    }

    function show_usage_on_admin_bar($wp_admin_bar) {
        $label = self::usage_label();
        if ($label === null) {
            return;
        }

        $color = $label['warn'] ? '#f59e0b' : '#00ffd5';
        // Lightning bolt: the same mark the Genwave Agent uses.
        $icon = '<svg style="width:14px;height:14px;vertical-align:-2px;margin-right:2px;color:' . $color . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/></svg>';

        $wp_admin_bar->add_node([
            'id'    => 'custom_text_with_icon',
            'title' => $icon . ' <span>' . esc_html($label['text']) . '</span>',
            'href'  => \GenWavePlugin\Core\Links::app('usage'),
            'meta'  => [
                'class'  => 'gen-wave-admin-bar-credits',
                'title'  => $label['title'],
                'target' => '_blank',
            ],
        ]);
    }
}
