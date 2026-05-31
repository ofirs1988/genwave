import React, { useState, useEffect, useCallback } from 'react';
import {
    DownloadOutlined,
    CheckCircleOutlined,
    LoadingOutlined,
    ReloadOutlined,
    LinkOutlined,
    AppstoreOutlined,
} from '@ant-design/icons';

/**
 * Genwave Plugins marketplace page.
 *
 * Lists all available GenWave plugins, shows install/active status, and lets
 * the user install missing ones via WP_Upgrader in a single click.
 */
const Plugins = () => {
    const [plugins, setPlugins] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [installing, setInstalling] = useState({}); // { slug: true | error msg }
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

    const handleInstall = async (slug) => {
        setInstalling((cur) => ({ ...cur, [slug]: true }));

        try {
            const body = new URLSearchParams({
                action: 'genwave_install_plugin',
                security: nonce,
                slug,
            });

            const res = await fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body,
            });
            const json = await res.json();

            if (json && json.success) {
                // Refresh the list to update install/active status
                await fetchPlugins({ forceRefresh: true });
                setInstalling((cur) => {
                    const next = { ...cur };
                    delete next[slug];
                    return next;
                });
            } else {
                const msg = (json && json.data && json.data.message) || 'Install failed';
                setInstalling((cur) => ({ ...cur, [slug]: msg }));
            }
        } catch (e) {
            setInstalling((cur) => ({ ...cur, [slug]: e.message || 'Network error' }));
        }
    };

    if (loading) {
        return (
            <div className="gw-page gw-plugins">
                <h1 className="gw-page__title">
                    <AppstoreOutlined /> Genwave Plugins
                </h1>
                <div className="gw-loading-state">
                    <LoadingOutlined /> Loading available plugins…
                </div>
            </div>
        );
    }

    return (
        <div className="gw-page gw-plugins">
            <div className="gw-plugins__header">
                <div>
                    <h1 className="gw-page__title">
                        <AppstoreOutlined /> Genwave Plugins
                    </h1>
                    <p className="gw-page__subtitle">
                        Install GenWave plugins directly from your dashboard.
                    </p>
                </div>
                <button
                    type="button"
                    className="gw-btn gw-btn--ghost"
                    onClick={() => fetchPlugins({ forceRefresh: true })}
                    disabled={refreshing}
                >
                    {refreshing ? <LoadingOutlined /> : <ReloadOutlined />} Refresh
                </button>
            </div>

            {error && (
                <div className="gw-alert gw-alert--error">
                    {error}
                </div>
            )}

            <div className="gw-plugins__grid">
                {plugins.map((p) => (
                    <PluginCard
                        key={p.slug}
                        plugin={p}
                        installing={installing[p.slug]}
                        onInstall={() => handleInstall(p.slug)}
                    />
                ))}
            </div>

            {!error && plugins.length === 0 && (
                <div className="gw-empty-state">No plugins available.</div>
            )}
        </div>
    );
};

const PluginCard = ({ plugin, installing, onInstall }) => {
    const isInstalling = installing === true;
    const installError = typeof installing === 'string' ? installing : null;

    const renderAction = () => {
        if (isInstalling) {
            return (
                <button type="button" className="gw-btn gw-btn--primary" disabled>
                    <LoadingOutlined /> Installing…
                </button>
            );
        }

        if (plugin.active) {
            return (
                <span className="gw-pill gw-pill--success">
                    <CheckCircleOutlined /> Active &middot; v{plugin.installed_version}
                </span>
            );
        }

        if (plugin.installed) {
            return (
                <button type="button" className="gw-btn gw-btn--primary" onClick={onInstall}>
                    Activate v{plugin.installed_version}
                </button>
            );
        }

        return (
            <button type="button" className="gw-btn gw-btn--primary" onClick={onInstall}>
                <DownloadOutlined /> Install v{plugin.version}
            </button>
        );
    };

    return (
        <div className="gw-plugin-card">
            <div className="gw-plugin-card__head">
                {plugin.icon && (
                    <img
                        src={plugin.icon}
                        alt={plugin.name}
                        className="gw-plugin-card__icon"
                        onError={(e) => { e.currentTarget.style.display = 'none'; }}
                    />
                )}
                <div className="gw-plugin-card__title-block">
                    <h3 className="gw-plugin-card__title">{plugin.name}</h3>
                    {plugin.tagline && (
                        <p className="gw-plugin-card__tagline">{plugin.tagline}</p>
                    )}
                    <span className={`gw-pill ${plugin.is_free ? 'gw-pill--neutral' : 'gw-pill--paid'}`}>
                        {plugin.is_free ? 'Free' : 'Paid'}
                    </span>
                </div>
            </div>

            <p className="gw-plugin-card__description">
                {plugin.description}
            </p>

            <div className="gw-plugin-card__meta">
                <span>WP {plugin.requires_wp}+</span>
                <span>&middot;</span>
                <span>PHP {plugin.requires_php}+</span>
                {plugin.homepage && (
                    <>
                        <span>&middot;</span>
                        <a href={plugin.homepage} target="_blank" rel="noopener noreferrer">
                            <LinkOutlined /> Homepage
                        </a>
                    </>
                )}
            </div>

            {installError && (
                <div className="gw-alert gw-alert--error">
                    {installError}
                </div>
            )}

            <div className="gw-plugin-card__action">
                {renderAction()}
            </div>
        </div>
    );
};

export default Plugins;
