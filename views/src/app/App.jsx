import React, { useEffect, useState } from 'react';
import Glyph from './Glyph';
import { data, t, Pill } from './ui';
import Overview from './pages/Overview';
import Account from './pages/Account';
import Generate from './pages/Generate';
import Plugins from './pages/Plugins';
import Tools from './pages/Tools';

/**
 * One full-screen app for every Genwave screen of this plugin, in the shell of
 * the Agent and the Chatbot: grouped navigation on the start side, "Back to
 * WordPress" (Esc) at the top, one column of content. Each screen keeps its
 * own wp-admin address (Core\AdminApp::PAGES); moving between them here does
 * not reload the page.
 */

const PAGES = {
    overview: Overview,
    account: Account,
    generate: Generate,
    plugins: Plugins,
    tools: Tools,
};

const NAV = () => [
    {
        label: t('Your site', 'האתר שלך'),
        items: [
            { route: 'overview', icon: 'home', label: t('Overview', 'סקירה') },
            { route: 'account', icon: 'key', label: t('Account', 'חשבון') },
        ],
    },
    {
        label: t('Tools', 'כלים'),
        items: [
            { route: 'generate', icon: 'write', label: t('AI writing', 'כתיבה עם AI') },
            { route: 'plugins', icon: 'puzzle', label: t('Genwave plugins', 'התוספים של Genwave') },
            { route: 'tools', icon: 'tool', label: t('Maintenance', 'תחזוקה') },
        ],
    },
];

const THEME_KEY = 'genwave-theme'; // shared with the Agent and the Chatbot

function readTheme() {
    try {
        return localStorage.getItem(THEME_KEY) === 'light' ? 'light' : 'dark';
    } catch (e) {
        return 'dark';
    }
}

export default function App({ initialRoute }) {
    const d = data();
    const [route, setRoute] = useState(PAGES[initialRoute] ? initialRoute : 'overview');
    const [theme, setTheme] = useState(readTheme);

    useEffect(() => {
        document.documentElement.classList.add('gw-app-open');
        return () => document.documentElement.classList.remove('gw-app-open');
    }, []);

    // Back and forward move between the screens visited here.
    useEffect(() => {
        const onPop = (e) => setRoute(e.state?.gwRoute && PAGES[e.state.gwRoute] ? e.state.gwRoute : initialRoute);
        window.history.replaceState({ ...(window.history.state || {}), gwRoute: route }, '');
        window.addEventListener('popstate', onPop);
        return () => window.removeEventListener('popstate', onPop);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Esc goes back to WordPress, unless the person is typing or a dialog is open.
    useEffect(() => {
        const onKey = (e) => {
            if (e.key !== 'Escape' || e.defaultPrevented) return;
            const tag = (e.target && e.target.tagName) || '';
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tag) || document.querySelector('#genwave-app .gw-overlay')) return;
            window.location.href = d.adminUrl;
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [d.adminUrl]);

    const go = (next) => {
        if (next === route || !PAGES[next]) return;
        const url = d.pages?.[next];
        // Maintenance reads the database on the server: open it as its own page.
        if (next === 'tools' && !d.tools && url) {
            window.location.href = url;
            return;
        }
        setRoute(next);
        if (url) window.history.pushState({ gwRoute: next }, '', url);
        const label = NAV().flatMap((g) => g.items).find((it) => it.route === next)?.label;
        if (label) document.title = document.title.replace(/^[^‹›]*(?=[‹›])/, `${label} `);
        document.getElementById('genwave-app')?.scrollTo(0, 0);
    };

    const toggleTheme = () => {
        const next = theme === 'dark' ? 'light' : 'dark';
        setTheme(next);
        try {
            localStorage.setItem(THEME_KEY, next);
        } catch (e) {
            /* private window: this visit only */
        }
    };

    useEffect(() => {
        const root = document.getElementById('genwave-app');
        if (!root) return;
        root.classList.toggle('light-theme', theme === 'light');
        root.classList.toggle('dark-theme', theme !== 'light');
    }, [theme]);

    const Page = PAGES[route];
    const connected = !!d.account?.connected;

    return (
        <div className="gw-shell">
            <aside className="gw-side">
                <div className="gw-brand">
                    <Glyph name="wave" size={20} />
                    <span className="gw-brand__name">Genwave</span>
                    {connected ? <Pill tone="ok">{t('Connected', 'מחובר')}</Pill> : <Pill>{t('Not connected', 'לא מחובר')}</Pill>}
                    <a className="gw-iconbtn gw-mobile-back" href={d.adminUrl} aria-label={t('Back to WordPress', 'חזרה לוורדפרס')}>
                        <Glyph name="close" />
                    </a>
                </div>
                <nav className="gw-nav" aria-label="Genwave">
                    {NAV().map((g) => (
                        <div className="gw-nav__group" key={g.label}>
                            <div className="gw-nav__group-label">{g.label}</div>
                            {g.items.map((it) => (
                                <a
                                    key={it.route}
                                    href={d.pages?.[it.route] || '#'}
                                    className={`gw-nav__item${route === it.route ? ' is-on' : ''}`}
                                    aria-current={route === it.route ? 'page' : undefined}
                                    onClick={(e) => {
                                        if (e.metaKey || e.ctrlKey || e.shiftKey) return;
                                        e.preventDefault();
                                        go(it.route);
                                    }}
                                >
                                    <Glyph name={it.icon} size={18} />
                                    {it.label}
                                </a>
                            ))}
                        </div>
                    ))}
                </nav>
                <div className="gw-side__foot">
                    <span dir="ltr">Genwave {d.version}</span>
                    <button
                        type="button"
                        className="gw-iconbtn"
                        onClick={toggleTheme}
                        aria-label={theme === 'dark' ? t('Light theme', 'ערכת נושא בהירה') : t('Dark theme', 'ערכת נושא כהה')}
                        title={theme === 'dark' ? t('Light theme', 'ערכת נושא בהירה') : t('Dark theme', 'ערכת נושא כהה')}
                    >
                        <Glyph name={theme === 'dark' ? 'sun' : 'moon'} />
                    </button>
                </div>
            </aside>

            <div className="gw-main">
                <div className="gw-topbar">
                    <a className="gw-btn gw-btn--ghost" style={{ height: 36, fontSize: 13 }} href={d.adminUrl}>
                        {t('Back to WordPress', 'חזרה לוורדפרס')} <kbd className="gw-kbd">Esc</kbd>
                    </a>
                </div>
                <main className="gw-content">
                    <Page key={route} go={go} />
                </main>
            </div>
        </div>
    );
}
