import React, { useCallback, useEffect, useState } from 'react';
import Glyph from '../Glyph';
import { data, t, ajax, Card, Button, Callout, PageHeader, Pill, Spinner } from '../ui';

/**
 * The Genwave plugins and where each stands on this site. A missing one is
 * installed from the Genwave account (it sends this site a signed command,
 * Core\SiteCommands) or from its zip: a plugin hosted on WordPress.org may not
 * install plugins from other servers itself.
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
                setError(r?.data?.message || t('The list could not be loaded.', 'לא הצלחנו לטעון את הרשימה.'));
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
                eyebrow={t('Tools', 'כלים')}
                title={t('Genwave plugins', 'התוספים של Genwave')}
                sub={t(
                    'Install a Genwave plugin from your Genwave account in one click: it installs it here and brings you back. Or download the zip and upload it in Plugins > Add New.',
                    'מתקינים תוסף של Genwave מחשבון Genwave בלחיצה אחת: הוא מתקין אותו כאן ומחזיר אתכם. או מורידים את ה-ZIP ומעלים בתוספים > הוספת תוסף.',
                )}
            />

            {error && <Callout tone="bad">{error}</Callout>}

            <Card
                title={t('Plugins', 'תוספים')}
                end={
                    <button type="button" className="gw-iconbtn" onClick={() => load(true)} disabled={refreshing || plugins === null} aria-label={t('Refresh', 'רענון')} title={t('Refresh', 'רענון')}>
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
                    <div className="gw-empty"><Glyph name="puzzle" size={28} />{t('No plugins to show right now.', 'אין כרגע תוספים להציג.')}</div>
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
        action = <Pill tone="ok">{t('Active', 'פעיל')}{p.installed_version ? ` · ${p.installed_version}` : ''}</Pill>;
    } else if (p.installed) {
        action = p.activate_url ? (
            <Button size="sm" variant="primary" icon="bolt" href={p.activate_url}>{t('Activate', 'הפעלה')}</Button>
        ) : (
            <Pill>{t('Installed', 'מותקן')}</Pill>
        );
    } else {
        action = (
            <>
                <Button size="sm" variant="ghost" icon="download" href={p.zip_url}>{t('Zip', 'ZIP')}</Button>
                <Button size="sm" variant="primary" icon="arrowOut" href={p.install_url}>{t('Install', 'התקנה')}</Button>
            </>
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
                    {p.is_free ? <span>{t('Free', 'חינם')}</span> : <span>{t('With your Genwave plan', 'כלול בתוכנית Genwave')}</span>}
                    {p.active && p.update_available && <Pill tone="warn">{t('Update ready', 'יש עדכון')}</Pill>}
                    {p.homepage && <a href={p.homepage} target="_blank" rel="noopener noreferrer">{t('Learn more', 'עוד פרטים')}</a>}
                </div>
            </div>
            <div className="gw-row__end">{action}</div>
        </div>
    );
}
