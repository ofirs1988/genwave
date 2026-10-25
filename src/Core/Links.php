<?php

namespace GenWavePlugin\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Every page on Genwave that a button in this plugin opens.
 *
 * The customer account lives at app.genwave.ai (the old account address is
 * retired). The app remembers where a signed-out visitor was going and lands
 * them there after signing in, so these link to its pages directly.
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

    /**
     * The page in the Genwave account where the Genwave plugins are offered
     * for download ("Connect your site", app.genwave.ai/connect). This plugin
     * never installs them: the administrator downloads the zip there and
     * uploads it in Plugins > Add New > Upload Plugin.
     */
    public static function getPlugins(): string
    {
        return self::app('connect');
    }

    /** WordPress's own "Upload Plugin" screen. */
    public static function uploadPlugin(): string
    {
        return admin_url('plugin-install.php?tab=upload');
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
