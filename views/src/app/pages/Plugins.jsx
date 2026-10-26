import React, { useCallback, useEffect, useState } from 'react';
import Glyph from '../Glyph';
import { data, ajax, Card, Button, Callout, PageHeader, Pill, Spinner } from '../ui';
import { __ } from '@wordpress/i18n';

/**
 * The Genwave plugins and where each stands on this site. This plugin does not
 * install them (a plugin hosted on WordPress.org may not install plugins from
 * other servers): a missing one links to the Genwave account, where its zip is
 * downloaded, and to WordPress's own Upload Plugin screen.
 */
export default function Plugins() {
    const d = data();
    const [plugins, setPlugins] = useState(null);
    const [error, setError] = useState('');
    const [refreshing, setRefreshing] = useState(false);

    const load = useCallback(
        async (force) => {
            setRefreshing(!!force);
            setError('');
            const r = await ajax('genwave_list_plugins', { security: d.nonces?.plugins, ...(force ? { force_refresh: '1' } : {}) });
            if (r?.success) setPlugins(r.data?.plugins || []);
            else {
                setPlugins((p) => p || []);
                setError(r?.data?.message || __("The list could not be loaded.", 'gen-wave'));
            }
            setRefreshing(false);
        },
        [d.nonces],
    );

    useEffect(() => {
        load(false);
    }, [load]);

    return (
        <div className="gw-col">
            <PageHeader
                eyebrow={__("Tools", 'gen-wave')}
                title={__("Genwave plugins", 'gen-wave')}
                sub={__("These plugins are not on WordPress.org. To add one, download its zip from your Genwave account, then upload it in Plugins > Add New > Upload Plugin and activate it.", 'gen-wave')}
            />
            {d.agent?.uploadUrl && (
                <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                    <Button size="sm" variant="secondary" icon="download" href={d.agent.uploadUrl}>{__("Upload Plugin", 'gen-wave')}</Button>
                </div>
            )}

            {error && <Callout tone="bad">{error}</Callout>}

            <Card
                title={__("Plugins", 'gen-wave')}
                end={
                    <button type="button" className="gw-iconbtn" onClick={() => load(true)} disabled={refreshing || plugins === null} aria-label={__("Refresh", 'gen-wave')} title={__("Refresh", 'gen-wave')}>
                        {refreshing ? <Spinner /> : <Glyph name="refresh" />}
                    </button>
                }
            >
                {plugins === null ? (
                    [0, 1, 2].map((i) => (
                        <div className="gw-row" key={i}>
                            <div className="gw-sk" style={{ width: 44, height: 44, borderRadius: 12 }} />
                            <div style={{ flex: 1, display: 'grid', gap: 8 }}>
                                <div className="gw-sk" style={{ height: 14, width: '40%' }} />
                                <div className="gw-sk" style={{ height: 12, width: '75%' }} />
                            </div>
                        </div>
                    ))
                ) : plugins.length === 0 ? (
                    <div className="gw-empty"><Glyph name="puzzle" size={28} />{__("No plugins to show right now.", 'gen-wave')}</div>
                ) : (
                    plugins.map((p) => <PluginRow key={p.slug} p={p} />)
                )}
            </Card>
        </div>
    );
}

function PluginRow({ p }) {
    const [iconFailed, setIconFailed] = useState(false);
    let action;
    if (p.active) {
        action = <Pill tone="ok">{__("Active", 'gen-wave')}{p.installed_version ? ` · ${p.installed_version}` : ''}</Pill>;
    } else if (p.installed) {
        action = p.activate_url ? (
            <Button size="sm" variant="primary" icon="bolt" href={p.activate_url}>{__("Activate", 'gen-wave')}</Button>
        ) : (
            <Pill>{__("Installed", 'gen-wave')}</Pill>
        );
    } else {
        action = p.get_url ? (
            <Button size="sm" variant="primary" icon="arrowOut" href={p.get_url} external>{__("Get it", 'gen-wave')}</Button>
        ) : (
            <Pill>{__("Not installed", 'gen-wave')}</Pill>
        );
    }
    return (
        <div className="gw-row gw-row--wrap" style={{ alignItems: 'flex-start' }}>
            <span className="gw-tile">
                {p.icon && !iconFailed ? <img src={p.icon} alt="" onError={() => setIconFailed(true)} /> : <Glyph name="puzzle" size={20} />}
            </span>
            <div className="gw-row__text">
                <div className="gw-row__label">{p.name}</div>
                <p className="gw-plugin__desc">{p.tagline || p.description}</p>
                <div className="gw-plugin__meta">
                    {p.is_free ? <span>{__("Free", 'gen-wave')}</span> : <span>{__("With your Genwave plan", 'gen-wave')}</span>}
                    {p.active && p.update_available && <Pill tone="warn">{__("Update ready", 'gen-wave')}</Pill>}
                    {p.homepage && <a href={p.homepage} target="_blank" rel="noopener noreferrer">{__("Learn more", 'gen-wave')}</a>}
                </div>
            </div>
            <div className="gw-row__end">{action}</div>
        </div>
    );
}
