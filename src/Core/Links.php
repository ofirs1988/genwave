<?php

namespace GenWavePlugin\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Every page on Genwave that a button in this plugin opens.
 *
 * The buttons pointed at account.genwave.ai, the old dashboard. It now sends
 * every page to the new app's sign-in screen and drops the path, so "Sign up
 * free" opened a login form and "Buy more credits" never reached the credits
 * page; the Dashboard's "Learn more" was a 404. The app (app.genwave.ai)
 * remembers where a signed-out visitor was going and lands them there after
 * signing in, so these link to it directly.
 */
class Links
{
    const SITE = 'https://genwave.ai';

    /** A page in the customer app. Follows GENWAVE_PANEL_URL like the connect flow. */
    public static function app(string $path = ''): string
    {
        return AgentAuth::panelUrl() . ($path === '' ? '' : '/' . ltrim($path, '/'));
    }

    public static function account(): string
    {
        return self::app();
    }

    public static function register(): string
    {
        return self::app('register');
    }

    public static function billing(): string
    {
        return self::app('account/billing');
    }

    public static function credits(): string
    {
        return self::app('account/billing/credits');
    }

    public static function plans(): string
    {
        return self::app('account/billing/plans');
    }

    public static function support(): string
    {
        return self::app('support');
    }

    /** The agent's page on the public site. */
    public static function agentInfo(): string
    {
        return self::SITE . '/agent/';
    }

    /** Genwave Agent download, counted like the site's own download buttons. */
    public static function agentDownload(): string
    {
        return self::download('genwave-agent');
    }

    /** A Genwave plugin's zip, counted like the site's own download buttons. */
    public static function download(string $slug): string
    {
        return self::app('api/download-track/' . rawurlencode($slug) . '?source=plugin');
    }

    /**
     * Install a Genwave plugin on this site from the customer's Genwave account
     * (SiteCommands): the account page names this site, installs on a click
     * (at once with $auto) and sends the admin back to $return.
     */
    public static function install(string $slug, string $return = '', bool $auto = false): string
    {
        $args = [
            'plugin' => $slug,
            'site'   => get_site_url(),
            'return' => $return !== '' ? $return : admin_url('admin.php?page=gen-wave-plugins'),
        ];
        if ($auto) {
            $args['auto'] = '1';
        }
        return self::app('sites/install') . '?' . http_build_query($args, '', '&', PHP_QUERY_RFC3986);
    }

    /** The links the React pages use. */
    public static function forScript(): array
    {
        return [
            'plans'     => self::plans(),
            'agentInfo' => self::agentInfo(),
        ];
    }
}
