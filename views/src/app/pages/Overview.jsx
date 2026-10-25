import React, { useEffect, useState } from 'react';
import Glyph from '../Glyph';
import { data, t, ajax, Card, ActionRow, Button, Stats, PageHeader, Spinner, usageWords, UpgradeCallout } from '../ui';

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
        active: { label: t('Open the Agent', 'פתיחת ה-Agent'), icon: 'chat' },
        inactive: { label: t('Activate Genwave Agent', 'הפעלת Genwave Agent'), icon: 'bolt' },
        missing: { label: t('Get Genwave Agent', 'להורדת Genwave Agent'), icon: 'arrowOut' },
    }[agent.state] || { label: t('Open the Agent', 'פתיחת ה-Agent'), icon: 'chat' };

    return (
        <div className="gw-col">
            <PageHeader
                eyebrow={d.site?.name}
                title={t('Genwave on your site', 'Genwave באתר שלך')}
                sub={t(
                    'This plugin connects the site to your Genwave account. The Genwave Agent then runs the site with you, by conversation.',
                    'התוסף הזה מחבר את האתר לחשבון Genwave שלך. אחר כך Genwave Agent מנהל איתך את האתר בשיחה.',
                )}
            />

            {a.connected ? (
                <div className="gw-hero is-on">
                    <span className="gw-tile gw-tile--on"><Glyph name="check" size={20} /></span>
                    <div className="gw-hero__text">
                        <p className="gw-hero__title">{t('Your site is connected', 'האתר שלך מחובר')}</p>
                        <p className="gw-hero__detail">
                            {agent.state === 'active'
                                ? t('Ask the Agent for anything on the site: pages, products, SEO, fixes.', 'בקשו מה-Agent כל דבר באתר: עמודים, מוצרים, SEO ותיקונים.')
                                : agent.state === 'missing'
                                  ? t('One more step: the Agent chat is a separate plugin. Download it from your Genwave account, then upload it in Plugins > Add New > Upload Plugin.', 'עוד צעד אחד: הצ׳אט של ה-Agent הוא תוסף נפרד. מורידים אותו מחשבון Genwave ומעלים בתוספים > הוספת תוסף > העלאת תוסף.')
                                  : t('One more step: activate Genwave Agent, the Agent chat.', 'עוד צעד אחד: הפעלת Genwave Agent, הצ׳אט של ה-Agent.')}
                        </p>
                        <div className="gw-hero__actions">
                            <Button variant="primary" href={agent.url} external={agent.state === 'missing'} icon={agentAction.icon}>{agentAction.label}</Button>
                            {agent.state === 'missing' && agent.canUpload && (
                                <Button variant="ghost" href={agent.uploadUrl} icon="download">{t('Upload Plugin', 'העלאת תוסף')}</Button>
                            )}
                        </div>
                    </div>
                </div>
            ) : (
                <div className="gw-hero">
                    <span className="gw-tile"><Glyph name="plug" size={20} /></span>
                    <div className="gw-hero__text">
                        <p className="gw-hero__title">{t('Connect this site to Genwave', 'חברו את האתר ל-Genwave')}</p>
                        <p className="gw-hero__detail">
                            {t('Sign in or create a free account, then approve the site. No key to copy.', 'נכנסים או פותחים חשבון חינם ומאשרים את האתר. אין מפתח להעתיק.')}
                        </p>
                        <div className="gw-hero__actions">
                            <Button variant="primary" icon="logout" onClick={() => go('account')}>{t('Connect', 'חיבור')}</Button>
                        </div>
                    </div>
                </div>
            )}

            {a.connected && <UpgradeCallout a={a} links={d.links} />}

            {a.connected && (
                <Stats
                    items={[
                        { icon: 'chart', label: t('Usage', 'שימוש'), value: usageWords(a) },
                        { icon: 'bolt', label: t('Requests', 'בקשות'), value: requests === null ? <Spinner /> : requests },
                        {
                            icon: 'bot',
                            label: 'Genwave Agent',
                            value: { active: t('Active', 'פעיל'), inactive: t('Not active', 'לא פעיל'), missing: t('Not installed', 'לא מותקן') }[agent.state],
                        },
                    ]}
                />
            )}

            <Card title={t('Next', 'הלאה')}>
                {a.connected && agent.state === 'active' && (
                    <ActionRow icon="chat" label={t('Talk to the Agent', 'לדבר עם ה-Agent')} detail={t('Describe what you need in plain words. You see what will change before it happens.', 'מתארים מה צריך במילים פשוטות, ורואים מה ישתנה לפני שזה קורה.')} href={agent.url} />
                )}
                <ActionRow icon="write" label={t('Write with AI', 'כתיבה עם AI')} detail={t('Titles and descriptions for products and posts, one field at a time.', 'כותרות ותיאורים למוצרים ולפוסטים, שדה אחד בכל פעם.')} onClick={() => go('generate')} />
                <ActionRow icon="puzzle" label={t('Genwave plugins', 'התוספים של Genwave')} detail={t('The Agent, the Chatbot and Elements, and where each one stands on this site.', 'ה-Agent, הצ׳אטבוט ו-Elements, ומה המצב של כל אחד מהם באתר.')} onClick={() => go('plugins')} />
                <ActionRow icon="chart" label={t('Usage and plan', 'שימוש ותוכנית')} detail="app.genwave.ai" href={d.links?.usage} external />
            </Card>
        </div>
    );
}
