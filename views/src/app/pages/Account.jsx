import React, { useState } from 'react';
import Glyph from '../Glyph';
import { data, t, ajax, Card, Row, Button, Callout, Pill, PageHeader, Confirm, Note, usageWords, UpgradeCallout } from '../ui';

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
        setError(r?.data?.message || t('The site could not be disconnected. Try again.', 'לא הצלחנו לנתק את האתר. נסו שוב.'));
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
        setError(r?.data?.message || t('Connection failed. Please try again.', 'החיבור נכשל. נסו שוב.'));
    };

    return (
        <div className="gw-col">
            <PageHeader
                eyebrow={d.site?.name}
                title={a.connected ? t('Your Genwave account', 'חשבון Genwave שלך') : t('Connect your Genwave account', 'חיבור חשבון Genwave')}
                sub={
                    a.connected
                        ? t('This site is connected. The Genwave plugins on it use this connection.', 'האתר מחובר. התוספים של Genwave באתר משתמשים בחיבור הזה.')
                        : t('Connect once and the Genwave Agent can run the site with you: pages, products, SEO and fixes, by conversation.', 'מתחברים פעם אחת, ו-Genwave Agent יכול לנהל איתך את האתר בשיחה: עמודים, מוצרים, SEO ותיקונים.')
                }
            />

            {flash?.message && (
                <Callout tone={flash.type === 'error' ? 'bad' : flash.type === 'warning' ? 'warn' : 'ok'}>{flash.message}</Callout>
            )}
            {error && <Callout tone="bad">{error}</Callout>}

            {a.connected && a.expired && (
                <Callout tone="warn" title={t('License expired', 'הרישיון פג')} actions={<Button size="sm" variant="primary" href={d.links?.billing} external>{t('Renew', 'חידוש')}</Button>}>
                    {t('Renew to keep using the Genwave Agent.', 'חדשו כדי להמשיך להשתמש ב-Genwave Agent.')}
                </Callout>
            )}

            {a.connected ? <Connected a={a} d={d} agent={agent} onDisconnect={() => setAsking(true)} /> : <NotConnected a={a} d={d} busy={busy} onReconnect={reconnect} />}

            <div className="gw-foot-links">
                <a href={d.links?.support} target="_blank" rel="noopener noreferrer">{t('Need help? Contact support', 'צריכים עזרה? פנו לתמיכה')}</a>
            </div>

            {asking && (
                <Confirm
                    title={t('Disconnect this site?', 'לנתק את האתר?')}
                    confirmLabel={t('Disconnect', 'ניתוק')}
                    danger
                    busy={busy}
                    onConfirm={disconnect}
                    onCancel={() => !busy && setAsking(false)}
                >
                    {t('The Genwave Agent and the other Genwave plugins stop working here until you connect again. Nothing on the site is deleted.', 'Genwave Agent ושאר התוספים של Genwave יפסיקו לעבוד כאן עד שתתחברו שוב. שום דבר באתר לא נמחק.')}
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
                    <p className="gw-hero__title">{t('Connected to Genwave', 'מחובר ל-Genwave')}</p>
                    <p className="gw-hero__detail">{t('Manage your site through the Genwave Agent chat.', 'מנהלים את האתר דרך הצ׳אט של Genwave Agent.')}</p>
                    {agent.state === 'active' && (
                        <div className="gw-hero__actions">
                            <Button variant="primary" icon="chat" href={agent.url}>{t('Open the Agent', 'פתיחת ה-Agent')}</Button>
                        </div>
                    )}
                </div>
            </div>

            <UpgradeCallout a={a} links={d.links} />

            {agent.state === 'missing' && (
                <Callout
                    tone="warn"
                    title={t('One more step: add Genwave Agent', 'עוד צעד אחד: הוספת Genwave Agent')}
                    actions={
                        agent.canUpload && (
                            <>
                                <Button size="sm" variant="primary" icon="arrowOut" href={agent.url} external>{t('Get Genwave Agent', 'להורדת Genwave Agent')}</Button>
                                <Button size="sm" variant="ghost" icon="download" href={agent.uploadUrl}>{t('Upload Plugin', 'העלאת תוסף')}</Button>
                            </>
                        )
                    }
                >
                    {agent.canUpload
                        ? t('The agent chat is a separate plugin, and it is not on WordPress.org. Download its zip from your Genwave account, then upload it here in Plugins > Add New > Upload Plugin and activate it. Keep this plugin installed too: the Agent uses its connection.', 'הצ׳אט של ה-Agent הוא תוסף נפרד, והוא לא נמצא ב-WordPress.org. מורידים את קובץ ה-ZIP שלו מחשבון Genwave, מעלים אותו כאן בתוספים > הוספת תוסף > העלאת תוסף ומפעילים. השאירו גם את התוסף הזה: ה-Agent משתמש בחיבור שלו.')
                        : t('The agent chat is a separate plugin. Ask a site administrator to add it.', 'הצ׳אט של ה-Agent הוא תוסף נפרד. בקשו ממנהל האתר להוסיף אותו.')}
                </Callout>
            )}
            {agent.state === 'inactive' && (
                <Callout tone="warn" title={t('Genwave Agent is installed but not active', 'Genwave Agent מותקן אבל לא פעיל')} actions={<Button size="sm" variant="primary" icon="bolt" href={agent.url}>{t('Activate Genwave Agent', 'הפעלת Genwave Agent')}</Button>}>
                    {t('Activate it to open the agent chat.', 'הפעילו אותו כדי לפתוח את הצ׳אט.')}
                </Callout>
            )}

            <Card title={t('Account', 'חשבון')}>
                <dl>
                    <div className="gw-kv"><dt>{t('License key', 'מפתח רישיון')}</dt><dd><code dir="ltr">{a.licenseMasked || '—'}</code></dd></div>
                    <div className="gw-kv"><dt>{t('Status', 'סטטוס')}</dt><dd>{a.expired ? <Pill tone="warn">{t('Expired', 'פג תוקף')}</Pill> : <Pill tone="ok">{t('Active', 'פעיל')}</Pill>}</dd></div>
                    <div className="gw-kv"><dt>{t('Usage', 'שימוש')}</dt><dd>{usageWords(a)}</dd></div>
                </dl>
                <Row
                    label={t('Plan, usage and billing', 'תוכנית, שימוש וחיוב')}
                    detail={t('In your Genwave account.', 'בחשבון Genwave שלך.')}
                    end={
                        <>
                            <Button size="sm" variant="secondary" href={d.links?.usage} external icon="chart">{t('Usage', 'שימוש')}</Button>
                            <Button size="sm" variant="secondary" href={d.links?.account} external icon="arrowOut">{t('Manage account', 'ניהול החשבון')}</Button>
                        </>
                    }
                />
            </Card>

            {a.canManage && (
                <Card title={t('Disconnect', 'ניתוק')} danger>
                    <Row
                        label={t('Disconnect this site from Genwave', 'ניתוק האתר מ-Genwave')}
                        detail={t('You can connect it again at any time.', 'אפשר לחבר אותו שוב בכל זמן.')}
                        end={<Button size="sm" variant="danger" icon="logout" onClick={onDisconnect}>{t('Disconnect', 'ניתוק')}</Button>}
                    />
                </Card>
            )}
        </>
    );
}

function NotConnected({ a, d, busy, onReconnect }) {
    const features = [
        { icon: 'chat', label: t('Natural conversation', 'שיחה טבעית'), detail: t('Run the whole site by talking to it.', 'מנהלים את כל האתר בשיחה.') },
        { icon: 'layout', label: t('Every area of WordPress', 'כל חלקי הוורדפרס'), detail: t('Content, WooCommerce, plugins, SEO and fixing errors.', 'תוכן, WooCommerce, תוספים, SEO ותיקון שגיאות.') },
        { icon: 'shield', label: t('Safe by design', 'בטוח מהיסוד'), detail: t('A preview before every change to your site.', 'תצוגה מקדימה לפני כל שינוי באתר.') },
    ];
    return (
        <>
            <div className="gw-hero">
                <span className="gw-tile"><Glyph name="plug" size={20} /></span>
                <div className="gw-hero__text">
                    <p className="gw-hero__title">{t('Connect to Genwave', 'התחברות ל-Genwave')}</p>
                    <p className="gw-hero__detail">
                        {a.canManage
                            ? t('Sign in or create a free account, then approve this site. No key to copy.', 'נכנסים או פותחים חשבון חינם, ומאשרים את האתר. אין מפתח להעתיק.')
                            : t('Ask a site administrator to connect this site to Genwave.', 'בקשו ממנהל האתר לחבר אותו ל-Genwave.')}
                    </p>
                    {a.canManage && (
                        <div className="gw-hero__actions">
                            <Button variant="primary" size="lg" icon="logout" href={a.connectUrl}>{t('Connect to Genwave', 'התחברות ל-Genwave')}</Button>
                        </div>
                    )}
                </div>
            </div>

            <Card title={t('What you get', 'מה מקבלים')}>
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
                                {t('Have an API key? Connect with it instead', 'יש לכם מפתח API? התחברו איתו')}
                                <Glyph name="arrow" className="gw-flip" />
                            </summary>
                            <div className="gw-details__body">
                                <form method="post" action={d.pages?.account}>
                                    <input type="hidden" name="ai_settings_nonce" value={d.nonces?.key || ''} />
                                    <label className="gw-field">
                                        <span className="gw-field__label">{t('License key', 'מפתח רישיון')}</span>
                                        <input
                                            className="gw-input gw-input--mono"
                                            name="ai_license_key"
                                            dir="ltr"
                                            autoComplete="off"
                                            placeholder="XXXX-XXXX-XXXX-XXXX"
                                            defaultValue={a.licenseKey || ''}
                                            required
                                        />
                                        <span className="gw-field__hint">{t('Find it in your Genwave account, under the site.', 'נמצא בחשבון Genwave שלכם, ליד האתר.')}</span>
                                    </label>
                                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
                                        <button type="submit" name="save_settings" value="1" className="gw-btn gw-btn--primary">
                                            <Glyph name="key" /> {t('Connect with this key', 'התחברות עם המפתח')}
                                        </button>
                                        {a.hasLicense && (
                                            <Button variant="secondary" busy={busy} onClick={onReconnect}>{t('Try the saved key again', 'לנסות שוב את המפתח השמור')}</Button>
                                        )}
                                    </div>
                                </form>
                            </div>
                        </details>
                    </div>
                </div>
            )}
            {!a.canManage && <Note>{t('Only an administrator can connect the site.', 'רק מנהל יכול לחבר את האתר.')}</Note>}
        </>
    );
}
