import React, { useEffect, useState } from 'react';
import Glyph from '../Glyph';
import { data, ajax, Card, ActionRow, Button, Callout, Stats, PageHeader, Spinner, usageWords, UpgradeCallout } from '../ui';
import { __ } from '@wordpress/i18n';

/** Where this site stands with Genwave, and the ways in. */
export default function Overview({ go }) {
    const d = data();
    const a = d.account || {};
    const agent = d.agent || {};
    const [requests, setRequests] = useState(null);

    useEffect(() => {
        ajax('genwave_get_dashboard_stats', { security: d.nonces?.stats }).then((r) =>
            setRequests(r?.success ? Number(r.data?.total_requests || 0) : '—'),
        );
    }, [d.nonces]);

    const agentAction = {
        active: { label: __("Open the Agent", 'gen-wave'), icon: 'chat' },
        inactive: { label: __("Activate Genwave Agent", 'gen-wave'), icon: 'bolt' },
        missing: { label: __("Get Genwave Agent", 'gen-wave'), icon: 'arrowOut' },
    }[agent.state] || { label: __("Open the Agent", 'gen-wave'), icon: 'chat' };

    return (
        <div className="gw-col">
            <PageHeader
                eyebrow={d.site?.name}
                title={__("Genwave on your site", 'gen-wave')}
                sub={__("This plugin connects the site to your Genwave account. The Genwave Agent then runs the site with you, by conversation.", 'gen-wave')}
            />

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

            {a.connected ? (
                <div className="gw-hero is-on">
                    <span className="gw-tile gw-tile--on"><Glyph name="check" size={20} /></span>
                    <div className="gw-hero__text">
                        <p className="gw-hero__title">{__("Your site is connected", 'gen-wave')}</p>
                        <p className="gw-hero__detail">
                            {agent.state === 'active'
                                ? __("Ask the Agent for anything on the site: pages, products, SEO, fixes.", 'gen-wave')
                                : agent.state === 'missing'
                                  ? __("One more step: the Agent chat is a separate plugin. Download it from your Genwave account, then upload it in Plugins > Add New > Upload Plugin.", 'gen-wave')
                                  : __("One more step: activate Genwave Agent, the Agent chat.", 'gen-wave')}
                        </p>
                        <div className="gw-hero__actions">
                            <Button variant="primary" href={agent.url} external={agent.state === 'missing'} icon={agentAction.icon}>{agentAction.label}</Button>
                            {agent.state === 'missing' && agent.canUpload && (
                                <Button variant="ghost" href={agent.uploadUrl} icon="download">{__("Upload Plugin", 'gen-wave')}</Button>
                            )}
                        </div>
                    </div>
                </div>
            ) : (
                <div className="gw-hero">
                    <span className="gw-tile"><Glyph name="plug" size={20} /></span>
                    <div className="gw-hero__text">
                        <p className="gw-hero__title">{__("Connect this site to Genwave", 'gen-wave')}</p>
                        <p className="gw-hero__detail">
                            {__("Sign in or create a free account, then approve the site. No key to copy.", 'gen-wave')}
                        </p>
                        <div className="gw-hero__actions">
                            <Button variant="primary" icon="logout" onClick={() => go('account')}>{__("Connect", 'gen-wave')}</Button>
                        </div>
                    </div>
                </div>
            )}

            {a.connected && <UpgradeCallout a={a} links={d.links} />}

            {a.connected && (
                <Stats
                    items={[
                        { icon: 'chart', label: __("Usage", 'gen-wave'), value: usageWords(a) },
                        { icon: 'bolt', label: __("Requests", 'gen-wave'), value: requests === null ? <Spinner /> : requests },
                        {
                            icon: 'bot',
                            label: 'Genwave Agent',
                            value: { active: __("Active", 'gen-wave'), inactive: __("Not active", 'gen-wave'), missing: __("Not installed", 'gen-wave') }[agent.state],
                        },
                    ]}
                />
            )}

            <Card title={__("Next", 'gen-wave')}>
                {a.connected && agent.state === 'active' && (
                    <ActionRow icon="chat" label={__("Talk to the Agent", 'gen-wave')} detail={__("Describe what you need in plain words. You see what will change before it happens.", 'gen-wave')} href={agent.url} />
                )}
                <ActionRow icon="write" label={__("Write with AI", 'gen-wave')} detail={__("Titles and descriptions for products and posts, one field at a time.", 'gen-wave')} onClick={() => go('generate')} />
                <ActionRow icon="puzzle" label={__("Genwave plugins", 'gen-wave')} detail={__("The Agent, the Chatbot and Elements, and where each one stands on this site.", 'gen-wave')} onClick={() => go('plugins')} />
                <ActionRow icon="chart" label={__("Usage and plan", 'gen-wave')} detail="app.genwave.ai" href={d.links?.usage} external />
            </Card>
        </div>
    );
}
