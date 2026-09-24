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
        return self::app('api/download-track/genwave-agent?source=plugin');
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
