import React, { useState } from 'react';
import Glyph from '../Glyph';
import { data, ajax, Card, Row, Button, Callout, Pill, PageHeader, Confirm, Note, usageWords, UpgradeCallout } from '../ui';
import { __ } from '@wordpress/i18n';

/**
 * Connecting the site to Genwave, and the account it is connected to.
 * Connect to Genwave is the way (Core\PanelConnect: sign in, approve, back);
 * pasting the license key is the other one (posted to this page, Settings.php).
 */
export default function Account() {
    const d = data();
    const a = d.account || {};
    const agent = d.agent || {};
    const flash = d.flash;
    const [asking, setAsking] = useState(false);
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');

    const disconnect = async () => {
        setBusy(true);
        setError('');
        const r = await ajax('genwave_disconnect_account', { security: d.nonces?.disconnect });
        if (r?.success) {
            window.location.href = d.pages?.account || window.location.href;
            return;
        }
        setBusy(false);
        setAsking(false);
        setError(r?.data?.message || __("The site could not be disconnected. Try again.", 'gen-wave'));
    };

    const reconnect = async () => {
        setBusy(true);
        setError('');
        const r = await ajax('genwave_verify_login', { security: d.nonces?.verify });
        if (r?.success) {
            window.location.href = d.pages?.account || window.location.href;
            return;
        }
        setBusy(false);
        setError(r?.data?.message || __("Connection failed. Please try again.", 'gen-wave'));
    };

    return (
        <div className="gw-col">
            <PageHeader
                eyebrow={d.site?.name}
                title={a.connected ? __("Your Genwave account", 'gen-wave') : __("Connect your Genwave account", 'gen-wave')}
                sub={
                    a.connected
                        ? __("This site is connected. The Genwave plugins on it use this connection.", 'gen-wave')
                        : __("Connect once and the Genwave Agent can run the site with you: pages, products, SEO and fixes, by conversation.", 'gen-wave')
                }
            />

            {flash?.message && (
                <Callout tone={flash.type === 'error' ? 'bad' : flash.type === 'warning' ? 'warn' : 'ok'}>{flash.message}</Callout>
            )}
            {error && <Callout tone="bad">{error}</Callout>}

            {a.connected && a.needsReconnect && (
                <Callout
                    tone="warn"
                    title={__("Reconnect this site", 'gen-wave')}
                    actions={a.connectUrl ? <Button size="sm" variant="primary" href={a.connectUrl} icon="plug">{__("Reconnect", 'gen-wave')}</Button> : null}
                >
                    {a.needsReconnect === 'moved'
                        ? __("The site address changed since it was connected, so your Genwave account still knows it by the old one. Connect again to keep the Genwave plugins working.", 'gen-wave')
                        : __("Genwave no longer accepts this connection: it was removed from your account or the key was replaced. Connect again to keep the Genwave plugins working.", 'gen-wave')}
                </Callout>
            )}

            {a.connected && a.expired && (
                <Callout tone="warn" title={__("License expired", 'gen-wave')} actions={<Button size="sm" variant="primary" href={d.links?.billing} external>{__("Renew", 'gen-wave')}</Button>}>
                    {__("Renew to keep using the Genwave Agent.", 'gen-wave')}
                </Callout>
            )}

            {a.connected ? <Connected a={a} d={d} agent={agent} onDisconnect={() => setAsking(true)} /> : <NotConnected a={a} d={d} busy={busy} onReconnect={reconnect} />}

            <div className="gw-foot-links">
                <a href={d.links?.support} target="_blank" rel="noopener noreferrer">{__("Need help? Contact support", 'gen-wave')}</a>
            </div>

            {asking && (
                <Confirm
                    title={__("Disconnect this site?", 'gen-wave')}
                    confirmLabel={__("Disconnect", 'gen-wave')}
                    danger
                    busy={busy}
                    onConfirm={disconnect}
                    onCancel={() => !busy && setAsking(false)}
                >
                    {__("The Genwave Agent and the other Genwave plugins stop working here until you connect again. Nothing on the site is deleted.", 'gen-wave')}
                </Confirm>
            )}
        </div>
    );
}

function Connected({ a, d, agent, onDisconnect }) {
    return (
        <>
            <div className="gw-hero is-on">
                <span className="gw-tile gw-tile--on"><Glyph name="check" size={20} /></span>
                <div className="gw-hero__text">
                    <p className="gw-hero__title">{__("Connected to Genwave", 'gen-wave')}</p>
                    <p className="gw-hero__detail">{__("Manage your site through the Genwave Agent chat.", 'gen-wave')}</p>
                    {agent.state === 'active' && (
                        <div className="gw-hero__actions">
                            <Button variant="primary" icon="chat" href={agent.url}>{__("Open the Agent", 'gen-wave')}</Button>
                        </div>
                    )}
                </div>
            </div>

            <UpgradeCallout a={a} links={d.links} />

            {agent.state === 'missing' && (
                <Callout
                    tone="warn"
                    title={__("One more step: add Genwave Agent", 'gen-wave')}
                    actions={
                        agent.canUpload && (
                            <>
                                <Button size="sm" variant="primary" icon="arrowOut" href={agent.url} external>{__("Get Genwave Agent", 'gen-wave')}</Button>
                                <Button size="sm" variant="ghost" icon="download" href={agent.uploadUrl}>{__("Upload Plugin", 'gen-wave')}</Button>
                            </>
                        )
                    }
                >
                    {agent.canUpload
                        ? __("The agent chat is a separate plugin, and it is not on WordPress.org. Download its zip from your Genwave account, then upload it here in Plugins > Add New > Upload Plugin and activate it. Keep this plugin installed too: the Agent uses its connection.", 'gen-wave')
                        : __("The agent chat is a separate plugin. Ask a site administrator to add it.", 'gen-wave')}
                </Callout>
            )}
            {agent.state === 'inactive' && (
                <Callout tone="warn" title={__("Genwave Agent is installed but not active", 'gen-wave')} actions={<Button size="sm" variant="primary" icon="bolt" href={agent.url}>{__("Activate Genwave Agent", 'gen-wave')}</Button>}>
                    {__("Activate it to open the agent chat.", 'gen-wave')}
                </Callout>
            )}

            <Card title={__("Account", 'gen-wave')}>
                <dl>
                    <div className="gw-kv"><dt>{__("License key", 'gen-wave')}</dt><dd><code dir="ltr">{a.licenseMasked || '—'}</code></dd></div>
                    <div className="gw-kv"><dt>{__("Status", 'gen-wave')}</dt><dd>{a.expired ? <Pill tone="warn">{__("Expired", 'gen-wave')}</Pill> : <Pill tone="ok">{__("Active", 'gen-wave')}</Pill>}</dd></div>
                    <div className="gw-kv"><dt>{__("Usage", 'gen-wave')}</dt><dd>{usageWords(a)}</dd></div>
                </dl>
                <Row
                    label={__("Plan, usage and billing", 'gen-wave')}
                    detail={__("In your Genwave account.", 'gen-wave')}
                    end={
                        <>
                            <Button size="sm" variant="secondary" href={d.links?.usage} external icon="chart">{__("Usage", 'gen-wave')}</Button>
                            <Button size="sm" variant="secondary" href={d.links?.account} external icon="arrowOut">{__("Manage account", 'gen-wave')}</Button>
                        </>
                    }
                />
            </Card>

            {a.canManage && (
                <Card title={__("Disconnect", 'gen-wave')} danger>
                    <Row
                        label={__("Disconnect this site from Genwave", 'gen-wave')}
                        detail={__("You can connect it again at any time.", 'gen-wave')}
                        end={<Button size="sm" variant="danger" icon="logout" onClick={onDisconnect}>{__("Disconnect", 'gen-wave')}</Button>}
                    />
                </Card>
            )}
        </>
    );
}

function NotConnected({ a, d, busy, onReconnect }) {
    const features = [
        { icon: 'chat', label: __("Natural conversation", 'gen-wave'), detail: __("Run the whole site by talking to it.", 'gen-wave') },
        { icon: 'layout', label: __("Every area of WordPress", 'gen-wave'), detail: __("Content, WooCommerce, plugins, SEO and fixing errors.", 'gen-wave') },
        { icon: 'shield', label: __("Safe by design", 'gen-wave'), detail: __("A preview before every change to your site.", 'gen-wave') },
    ];
    return (
        <>
            <div className="gw-hero">
                <span className="gw-tile"><Glyph name="plug" size={20} /></span>
                <div className="gw-hero__text">
                    <p className="gw-hero__title">{__("Connect to Genwave", 'gen-wave')}</p>
                    <p className="gw-hero__detail">
                        {a.canManage
                            ? __("Sign in or create a free account, then approve this site. No key to copy.", 'gen-wave')
                            : __("Ask a site administrator to connect this site to Genwave.", 'gen-wave')}
                    </p>
                    {a.canManage && (
                        <div className="gw-hero__actions">
                            <Button variant="primary" size="lg" icon="logout" href={a.connectUrl}>{__("Connect to Genwave", 'gen-wave')}</Button>
                        </div>
                    )}
                </div>
            </div>

            <Card title={__("What you get", 'gen-wave')}>
                {features.map((f) => (
                    <div className="gw-row" key={f.label}>
                        <span className="gw-tile gw-tile--sm"><Glyph name={f.icon} size={18} /></span>
                        <div className="gw-row__text">
                            <div className="gw-row__label">{f.label}</div>
                            <div className="gw-row__detail">{f.detail}</div>
                        </div>
                    </div>
                ))}
            </Card>

            {a.canManage && (
                <div className="gw-card">
                    <div className="gw-card__body">
                        <details className="gw-details" open={a.hasLicense}>
                            <summary>
                                {__("Have an API key? Connect with it instead", 'gen-wave')}
                                <Glyph name="arrow" className="gw-flip" />
                            </summary>
                            <div className="gw-details__body">
                                <form method="post" action={d.pages?.account}>
                                    <input type="hidden" name="ai_settings_nonce" value={d.nonces?.key || ''} />
                                    <label className="gw-field">
                                        <span className="gw-field__label">{__("License key", 'gen-wave')}</span>
                                        <input
                                            className="gw-input gw-input--mono"
                                            name="ai_license_key"
                                            dir="ltr"
                                            autoComplete="off"
                                            placeholder="XXXX-XXXX-XXXX-XXXX"
                                            defaultValue={a.licenseKey || ''}
                                            required
                                        />
                                        <span className="gw-field__hint">{__("Find it in your Genwave account, under the site.", 'gen-wave')}</span>
                                    </label>
                                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                                        <button type="submit" name="save_settings" value="1" className="gw-btn gw-btn--primary">
                                            <Glyph name="key" /> {__("Connect with this key", 'gen-wave')}
                                        </button>
                                        {a.hasLicense && (
                                            <Button variant="secondary" busy={busy} onClick={onReconnect}>{__("Try the saved key again", 'gen-wave')}</Button>
                                        )}
                                    </div>
                                </form>
                            </div>
                        </details>
                    </div>
                </div>
            )}
            {!a.canManage && <Note>{__("Only an administrator can connect the site.", 'gen-wave')}</Note>}
        </>
    );
}
