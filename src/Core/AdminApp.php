<?php

namespace GenWavePlugin\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * The Genwave screens of this plugin as one full-screen app, in the language
 * of the Genwave Agent and the Genwave Chatbot (views/src/app). Every screen
 * keeps its own wp-admin page; each prints the same root with its route, and
 * the app moves between them without reloading.
 *
 * On these pages only the app's own bundle loads (no Bootstrap, SweetAlert,
 * Font Awesome or Select2), with the fonts bundled in assets/fonts.
 */
class AdminApp
{
    /** wp-admin page slug => app route. */
    const PAGES = [
        'gen-wave-plugin-settings'  => 'account',
        'gen-wave-plugin-dashboard' => 'account',
        'gen-wave-dashboard'        => 'overview',
        'gen-wave-generate'         => 'generate',
        'gen-wave-plugins'          => 'plugins',
        'ai-tools'                  => 'tools',
    ];

    /** The slug each route is opened with. */
    const ROUTE_SLUG = [
        'overview' => 'gen-wave-dashboard',
        'account'  => 'gen-wave-plugin-dashboard',
        'generate' => 'gen-wave-generate',
        'plugins'  => 'gen-wave-plugins',
        'tools'    => 'ai-tools',
    ];

    /** @var array{type:string,message:string}|null */
    private static $flash = null;

    public static function register(): void
    {
        add_action('admin_enqueue_scripts', [self::class, 'enqueue'], 20);
        add_filter('admin_body_class', [self::class, 'bodyClass']);
    }

    /** The current request is one of the app's pages. */
    public static function isAppPage(): bool
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page match
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        return is_admin() && isset(self::PAGES[$page]);
    }

    public static function bodyClass($classes)
    {
        return self::isAppPage() ? $classes . ' gw-app-page' : $classes;
    }

    /** A message to show at the top of the screen this request renders. */
    public static function flash(string $type, string $message): void
    {
        self::$flash = ['type' => $type, 'message' => $message];
    }

    /** Print the app's root for one route. */
    public static function render(string $route): void
    {
        $notice = PanelConnect::takeNotice();
        if ($notice && self::$flash === null) {
            self::$flash = $notice;
        }
        wp_localize_script('gen-wave-app', 'genwaveApp', self::data($route));
        echo '<div id="genwave-app" class="dark-theme" data-route="' . esc_attr($route) . '" dir="' . (is_rtl() ? 'rtl' : 'ltr') . '"></div>';
        echo '<noscript><p>' . esc_html__('Genwave needs JavaScript to show this page.', 'gen-wave') . '</p></noscript>';
        // The saved theme before the first paint, so a light-theme user sees no dark flash.
        echo "<script>try{if(localStorage.getItem('genwave-theme')==='light'){var e=document.getElementById('genwave-app');e.className='light-theme';}}catch(x){}</script>";
    }

    public static function enqueue(): void
    {
        if (!self::isAppPage()) {
            return;
        }
        $dist = GEN_WAVE_PATH . '/dist';
        $js = glob($dist . '/bundle.*.js');
        $css = glob($dist . '/styles.*.css');
        if (!empty($css)) {
            wp_enqueue_style('gen-wave-app', GEN_WAVE_URL . '/dist/' . basename($css[0]), [], GEN_WAVE_VERSION);
            wp_add_inline_style('gen-wave-app', self::fontFaces());
        }
        if (!empty($js)) {
            wp_enqueue_script('gen-wave-app', GEN_WAVE_URL . '/dist/' . basename($js[0]), [], GEN_WAVE_VERSION, true);
        }
    }

    /** The bundled fonts, under names of their own so they never clash with wp-admin's. */
    private static function fontFaces(): string
    {
        $base = GEN_WAVE_URL . '/assets/fonts/';
        return "@font-face{font-family:'GW Outfit';font-style:normal;font-weight:300 700;font-display:swap;src:url('"
            . esc_url($base . 'outfit-latin.woff2') . "') format('woff2')}"
            . "@font-face{font-family:'GW Assistant';font-style:normal;font-weight:400 700;font-display:swap;src:url('"
            . esc_url($base . 'assistant-hebrew.woff2') . "') format('woff2');unicode-range:U+0307-0308,U+0590-05FF,U+200C-2010,U+20AA,U+25CC,U+FB1D-FB4F}";
    }

    /** Everything the screens need, nothing secret (the license key only before connecting, to the admin who typed it). */
    private static function data(string $route): array
    {
        $connected = AgentAuth::isConnected();
        $licenseKey = (string) Config::get('license_key');
        $usage = \GenWavePlugin\AdminBar::usage_label();
        $canManage = current_user_can('manage_options');

        $pages = [];
        foreach (self::ROUTE_SLUG as $r => $slug) {
            $pages[$r] = admin_url('admin.php?page=' . $slug);
        }

        return [
            'route'   => $route,
            'version' => GEN_WAVE_VERSION,
            'locale'  => get_user_locale(),
            'adminUrl' => admin_url(),
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'pages'   => $pages,
            'site'    => ['name' => get_bloginfo('name'), 'url' => home_url()],
            'hasWoo'  => class_exists('WooCommerce'),
            'flash'   => self::$flash,
            'nonces'  => [
                'general'    => wp_create_nonce('genwave_nonce'),
                'stats'      => wp_create_nonce('genwave_nonce'),
                'generate'   => wp_create_nonce('genwave_generate_nonce'),
                'plugins'    => wp_create_nonce('genwave_plugins_nonce'),
                'verify'     => wp_create_nonce('verify_login_nonce'),
                'disconnect' => wp_create_nonce('disconnect_account_nonce'),
                'key'        => wp_create_nonce('save_ai_settings'),
            ],
            'account' => [
                'connected'     => $connected,
                'expired'       => Config::get('license_expired') === '1',
                'hasLicense'    => strlen($licenseKey) > 10,
                'licenseMasked' => $licenseKey !== '' ? substr($licenseKey, 0, 8) . '••••' . substr($licenseKey, -4) : '',
                'licenseKey'    => (!$connected && $canManage) ? $licenseKey : '',
                'usageText'     => $usage['text'] ?? '',
                'usageTitle'    => $usage['title'] ?? '',
                // ok | low | out (Free), light (paid past fair use), '' (nothing to say)
                'usageState'    => $usage['state'] ?? '',
                'canManage'     => $canManage,
                'connectUrl'    => $canManage ? PanelConnect::startUrl() : '',
            ],
            'agent'   => AgentPlugin::forScript(),
            'links'   => [
                'account' => Links::account(),
                'usage'   => Links::app('usage'),
                'billing' => Links::billing(),
                'plans'   => Links::plans(),
                'support' => Links::support(),
            ],
            'tools'   => $route === 'tools' ? self::toolsData() : null,
        ];
    }

    /** @var array|null The database check, set by the Maintenance page before it renders. */
    private static $dbStatus = null;

    public static function databaseStatus(array $status): void
    {
        self::$dbStatus = $status;
    }

    private static function toolsData(): array
    {
        return [
            'db'          => self::$dbStatus ?? ['all_ok' => true, 'tables' => []],
            'postUrl'     => admin_url('admin-post.php'),
            'repairNonce' => wp_create_nonce('ai_repair_database_nonce'),
            'cacheNonce'  => wp_create_nonce('ai_clear_cache_nonce'),
            'wp'          => get_bloginfo('version'),
            'php'         => PHP_VERSION,
        ];
    }
}
