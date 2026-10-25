import React, { useState, useEffect, useCallback } from 'react';
import {
    DownloadOutlined,
    CheckCircleOutlined,
    ReloadOutlined,
    LinkOutlined,
    AppstoreOutlined,
    ThunderboltOutlined,
} from '@ant-design/icons';

/**
 * Genwave Plugins page.
 *
 * Lists the Genwave plugins and where each stands on this site. A missing one
 * is installed from the customer's Genwave account (it sends this site a signed
 * install command, see Core\SiteCommands) or from its zip; a plugin hosted on
 * WordPress.org may not install plugins from other servers itself.
 */
const Plugins = () => {
    const [plugins, setPlugins] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [refreshing, setRefreshing] = useState(false);

    const settings = window.genwaveFreeSettings || {};
    const ajaxurl = settings.ajaxurl;
    const nonce = settings.pluginsNonce;

    const fetchPlugins = useCallback(async ({ forceRefresh = false } = {}) => {
        if (!ajaxurl || !nonce) {
            setError('Plugin settings not available. Please reload the page.');
            setLoading(false);
            return;
        }

        try {
            if (forceRefresh) {
                setRefreshing(true);
            } else {
                setLoading(true);
            }
            setError(null);

            const body = new URLSearchParams({
                action: 'genwave_list_plugins',
                security: nonce,
            });
            if (forceRefresh) {
                body.append('force_refresh', '1');
            }

            const res = await fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body,
            });
            const json = await res.json();

            if (json && json.success) {
                setPlugins(json.data.plugins || []);
            } else {
                setError((json && json.data && json.data.message) || 'Failed to load plugins');
            }
        } catch (e) {
            setError(e.message || 'Network error');
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    }, [ajaxurl, nonce]);

    useEffect(() => {
        fetchPlugins();
    }, [fetchPlugins]);

    const header = (
        <header className="gw-mkt__hero">
            <div className="gw-mkt__hero-glow" aria-hidden="true" />
            <div className="gw-mkt__hero-content">
                <div className="gw-mkt__hero-text">
                    <span className="gw-mkt__eyebrow">
                        <ThunderboltOutlined /> Marketplace
                    </span>
                    <h1 className="gw-mkt__title">Genwave Plugins</h1>
                    <p className="gw-mkt__subtitle">
                        Install a Genwave plugin from your Genwave account in one click, or download its zip.
                    </p>
                </div>
                <button
                    type="button"
                    className="gw-mkt__refresh"
                    onClick={() => fetchPlugins({ forceRefresh: true })}
                    disabled={refreshing || loading}
                >
                    <ReloadOutlined spin={refreshing} /> {refreshing ? 'Refreshing' : 'Refresh'}
                </button>
            </div>
        </header>
    );

    if (loading) {
        return (
            <div className="gw-page gw-mkt">
                {header}
                <div className="gw-mkt__grid">
                    {[0, 1, 2].map((i) => (
                        <div className="gw-mkt-card gw-mkt-card--skeleton" key={i}>
                            <div className="gw-sk gw-sk__icon" />
                            <div className="gw-sk gw-sk__line gw-sk__line--title" />
                            <div className="gw-sk gw-sk__line" />
                            <div className="gw-sk gw-sk__line gw-sk__line--short" />
                            <div className="gw-sk gw-sk__btn" />
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    return (
        <div className="gw-page gw-mkt">
            {header}

            {error && <div className="gw-mkt__alert">{error}</div>}

            <div className="gw-mkt__grid">
                {plugins.map((p, i) => (
                    <PluginCard
                        key={p.slug}
                        plugin={p}
                        index={i}
                    />
                ))}
            </div>

            {!error && plugins.length === 0 && (
                <div className="gw-mkt__empty">
                    <AppstoreOutlined />
                    <p>No plugins available right now.</p>
                </div>
            )}
        </div>
    );
};

const initialsFor = (name = '') =>
    name.replace(/[^a-zA-Z0-9 ]/g, '').trim().split(/\s+/).slice(0, 2).map((w) => w[0]).join('').toUpperCase() || 'GW';

const PluginCard = ({ plugin, index }) => {
    const [iconFailed, setIconFailed] = useState(false);
    const showIcon = plugin.icon && !iconFailed;

    const renderAction = () => {
        if (plugin.active) {
            return (
                <span className="gw-mkt-btn gw-mkt-btn--active" aria-disabled="true">
                    <CheckCircleOutlined /> Active · v{plugin.installed_version}
                </span>
            );
        }
        if (plugin.installed) {
            return plugin.activate_url ? (
                <a className="gw-mkt-btn gw-mkt-btn--primary" href={plugin.activate_url}>
                    <ThunderboltOutlined /> Activate v{plugin.installed_version}
                </a>
            ) : (
                <span className="gw-mkt-btn gw-mkt-btn--active" aria-disabled="true">
                    Installed · v{plugin.installed_version}
                </span>
            );
        }
        return (
            <>
                <a className="gw-mkt-btn gw-mkt-btn--primary" href={plugin.install_url}>
                    <LinkOutlined /> Install from your Genwave account
                </a>
                <a className="gw-mkt-card__link" href={plugin.zip_url}>
                    <DownloadOutlined /> Download zip
                </a>
            </>
        );
    };

    return (
        <article
            className={`gw-mkt-card${plugin.active ? ' is-active' : ''}`}
            style={{ animationDelay: `${Math.min(index, 8) * 60}ms` }}
        >
            <span className="gw-mkt-card__accent" aria-hidden="true" />

            <div className="gw-mkt-card__top">
                <div className={`gw-mkt-card__icon${showIcon ? '' : ' gw-mkt-card__icon--fallback'}`}>
                    {showIcon ? (
                        <img src={plugin.icon} alt="" onError={() => setIconFailed(true)} />
                    ) : (
                        <span>{initialsFor(plugin.name)}</span>
                    )}
                </div>
                <div className="gw-mkt-card__heading">
                    <h3 className="gw-mkt-card__title">{plugin.name}</h3>
                    {plugin.tagline && <p className="gw-mkt-card__tagline">{plugin.tagline}</p>}
                </div>
                {plugin.active && (
                    <span className="gw-mkt-card__flag" title="Active">
                        <CheckCircleOutlined />
                    </span>
                )}
            </div>

            {plugin.description && (
                <p className="gw-mkt-card__desc">{plugin.description}</p>
            )}

            <div className="gw-mkt-card__meta">
                <span className="gw-mkt-chip">WP {plugin.requires_wp}+</span>
                <span className="gw-mkt-chip">PHP {plugin.requires_php}+</span>
                {plugin.active && plugin.update_available && (
                    <span className="gw-mkt-chip gw-mkt-chip--update">
                        <ReloadOutlined /> Update ready
                    </span>
                )}
                {plugin.homepage && (
                    <a className="gw-mkt-card__link" href={plugin.homepage} target="_blank" rel="noopener noreferrer">
                        <LinkOutlined /> Learn more
                    </a>
                )}
            </div>

            <div className="gw-mkt-card__foot">{renderAction()}</div>
        </article>
    );
};

export default Plugins;
