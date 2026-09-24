<?php

namespace GenWavePlugin\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Where the separate Genwave Agent plugin stands on this site.
 *
 * The agent chat is its own plugin. "Open the Agent" used to link straight to
 * its admin page, and on a site without it WordPress answered "Sorry, you are
 * not allowed to access this page" - to an administrator who had done nothing
 * wrong (reported from a live site). Every link to the agent goes through here
 * now, so the button says what the next step really is.
 */
class AgentPlugin
{
    const SLUG = 'genwave-agent';

    /** The agent's plugin file ("genwave-agent/genwave-agent.php"), or '' when absent. */
    public static function file(): string
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        foreach (array_keys(get_plugins()) as $file) {
            if (strpos($file, self::SLUG . '/') === 0) {
                return $file;
            }
        }
        return '';
    }

    /** 'active' | 'inactive' | 'missing'. */
    public static function state(): string
    {
        $file = self::file();
        if ($file === '') {
            return 'missing';
        }
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active($file) ? 'active' : 'inactive';
    }

    /**
     * The link for the current state: open the chat, activate the plugin
     * (nonced, for users who may activate plugins), or download it.
     */
    public static function url(?string $state = null): string
    {
        $state = $state ?? self::state();
        if ($state === 'active') {
            return admin_url('admin.php?page=' . self::SLUG);
        }
        if ($state === 'inactive') {
            if (!current_user_can('activate_plugins')) {
                return admin_url('plugins.php');
            }
            // Not wp_nonce_url(): it returns the URL HTML-escaped ("&amp;"),
            // which the React page would put in an href as-is and break.
            $file = self::file();
            return add_query_arg(
                [
                    'action'   => 'activate',
                    'plugin'   => rawurlencode($file),
                    '_wpnonce' => wp_create_nonce('activate-plugin_' . $file),
                ],
                admin_url('plugins.php')
            );
        }
        // Missing: the Genwave Plugins page installs it in one click. Users who
        // may not install plugins get the file to pass to someone who can.
        if (current_user_can('install_plugins')) {
            return admin_url('admin.php?page=gen-wave-plugins');
        }
        return Links::agentDownload();
    }

    /** State + link, for the React pages. */
    public static function forScript(): array
    {
        $state = self::state();
        return [
            'state'      => $state,
            'url'        => self::url($state),
            'oneClick'   => $state === 'missing' && current_user_can('install_plugins'),
            'uploadUrl'  => admin_url('plugin-install.php?tab=upload'),
        ];
    }
}
