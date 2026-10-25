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
     * No number is ever shown: there is nothing to count for the customer.
     * A paid plan shows nothing, unless the month went far past normal use
     * and the agent is on its lighter model until renewal ("Light mode"). The
     * Free plan is "Limited use", "almost used up" near the end of the month's
     * free use, and "used up" with an invitation to upgrade. The plan state is
     * written by the Genwave Agent from each balance read
     * (genwave_agent_plan_state, with free_state from the brain since 1.40);
     * without it nothing is shown rather than a guess.
     *
     * @return array{text:string,title:string,warn:bool,state:string}|null
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
                'state' => 'light',
            ];
        }
        $free = self::free_state($state);
        if ($free === 'out') {
            return [
                'text'  => __('Free use used up · Upgrade', 'gen-wave'),
                'title' => __("You've used this month's free use. Upgrade and the agent keeps working for you.", 'gen-wave'),
                'warn'  => true,
                'state' => 'out',
            ];
        }
        if ($free === 'low') {
            return [
                'text'  => __('Free use almost used up', 'gen-wave'),
                'title' => __('Your free use for this month is almost used up. Upgrade and the agent keeps working for you.', 'gen-wave'),
                'warn'  => true,
                'state' => 'low',
            ];
        }
        return [
            'text'  => __('Limited use', 'gen-wave'),
            'title' => __('The Free plan includes limited AI use each month.', 'gen-wave'),
            'warn'  => false,
            'state' => 'ok',
        ];
    }

    /**
     * The Free plan's state: "ok", "low" or "out". From the brain when the
     * Agent stored it; otherwise from the stored balance with the brain's own
     * thresholds (allowance.free_state: under 5/6 of a credit is about five
     * requests left).
     */
    private static function free_state(array $state): string
    {
        if (isset($state['free_state']) && in_array($state['free_state'], ['ok', 'low', 'out'], true)) {
            return $state['free_state'];
        }
        $balance = (float) get_option('aiaw_credits', 0);
        if ($balance < 0.01) {
            return 'out';
        }
        return $balance < 5 / 6 ? 'low' : 'ok';
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
            // The Free plan's label is an invitation: it opens the plans.
            'href'  => $label['state'] === 'light' ? \GenWavePlugin\Core\Links::app('usage') : \GenWavePlugin\Core\Links::plans(),
            'meta'  => [
                'class'  => 'gen-wave-admin-bar-credits',
                'title'  => $label['title'],
                'target' => '_blank',
            ],
        ]);
    }
}
