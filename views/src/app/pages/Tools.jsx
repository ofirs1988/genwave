import React, { useState } from 'react';
import Glyph from '../Glyph';
import { data, t, Card, Row, Callout, PageHeader, Pill, Confirm } from '../ui';

/**
 * Maintenance: the plugin's own database tables (check and repair), clearing
 * its own caches (never the site's), and the versions it runs on. The buttons post to admin-post.php
 * (Pages\Tools) and come back here with the outcome.
 */
export default function Tools() {
    const d = data();
    const tools = d.tools || {};
    const db = tools.db || { all_ok: true, tables: {} };
    const tables = Object.entries(db.tables || {});
    const [asking, setAsking] = useState(null); // { form, text }
    const [sending, setSending] = useState(false);

    const post = (action, nonceName, nonce, label, icon, variant = 'secondary', confirmText) => (
        <form
            method="post"
            action={tools.postUrl}
            onSubmit={(e) => {
                if (confirmText && !e.currentTarget.dataset.ok) {
                    e.preventDefault();
                    setAsking({ form: e.currentTarget, text: confirmText });
                    return;
                }
                setSending(true);
            }}
        >
            <input type="hidden" name="action" value={action} />
            <input type="hidden" name={nonceName} value={nonce || ''} />
            <button type="submit" className={`gw-btn gw-btn--${variant} gw-btn--sm`}>
                <Glyph name={icon} /> {label}
            </button>
        </form>
    );

    return (
        <div className="gw-col">
            <PageHeader
                eyebrow={t('Tools', 'כלים')}
                title={t('Maintenance', 'תחזוקה')}
                sub={t('The plugin keeps a few tables of its own. If something looks wrong, check and repair them here.', 'התוסף שומר כמה טבלאות משלו. אם משהו נראה לא תקין, בודקים ומתקנים אותן כאן.')}
            />

            {d.flash?.message && <Callout tone={d.flash.type === 'error' ? 'bad' : 'ok'}>{d.flash.message}</Callout>}

            <Card
                title={t('Database', 'מסד נתונים')}
                hint={db.all_ok ? t('All tables are in order.', 'כל הטבלאות תקינות.') : t('Some tables need a repair.', 'חלק מהטבלאות צריכות תיקון.')}
                end={db.all_ok ? <Pill tone="ok">{t('OK', 'תקין')}</Pill> : <Pill tone="bad">{t('Needs repair', 'צריך תיקון')}</Pill>}
            >
                {tables.map(([name, s]) => (
                    <Row
                        key={name}
                        wrap={false}
                        label={<code dir="ltr">{name}</code>}
                        detail={
                            !s.exists
                                ? t('Missing', 'חסרה')
                                : s.missing_columns?.length
                                  ? `${t('Missing columns', 'עמודות חסרות')}: ${s.missing_columns.join(', ')}`
                                  : null
                        }
                        end={s.exists && !s.missing_columns?.length ? <Glyph name="check" className="gw-list__done" /> : <Glyph name="warning" />}
                    />
                ))}
                <Row
                    label={t('Check and repair', 'בדיקה ותיקון')}
                    detail={t('Adds any missing table or column. Nothing is deleted.', 'מוסיף טבלה או עמודה שחסרות. שום דבר לא נמחק.')}
                    end={post('ai_repair_database', 'ai_nonce', tools.repairNonce, t('Repair', 'תיקון'), 'database', db.all_ok ? 'secondary' : 'primary')}
                />
            </Card>

            <Card title={t('Caches', 'מטמון')}>
                <Row
                    label={t("Clear Genwave's caches", 'ניקוי המטמון של Genwave')}
                    detail={t('Forgets the saved list of Genwave plugins and checks again for plugin updates. The rest of the site is not touched.', 'שוכח את רשימת התוספים של Genwave שנשמרה ובודק שוב אם יש עדכונים לתוספים. שאר האתר לא מושפע.')}
                    end={post('ai_clear_cache', 'ai_nonce', tools.cacheNonce, t('Clear', 'ניקוי'), 'broom', 'secondary', t("Clear Genwave's caches now?", 'לנקות עכשיו את המטמון של Genwave?'))}
                />
            </Card>

            <Card title={t('System', 'מערכת')}>
                <dl>
                    <div className="gw-kv"><dt>Genwave</dt><dd className="gw-num" dir="ltr">{d.version}</dd></div>
                    <div className="gw-kv"><dt>WordPress</dt><dd className="gw-num" dir="ltr">{tools.wp}</dd></div>
                    <div className="gw-kv"><dt>PHP</dt><dd className="gw-num" dir="ltr">{tools.php}</dd></div>
                    <div className="gw-kv"><dt>WooCommerce</dt><dd>{d.hasWoo ? t('Active', 'פעיל') : t('Not active', 'לא פעיל')}</dd></div>
                </dl>
            </Card>

            {asking && (
                <Confirm
                    title={asking.text}
                    confirmLabel={t('Clear', 'ניקוי')}
                    busy={sending}
                    onCancel={() => !sending && setAsking(null)}
                    onConfirm={() => {
                        setSending(true);
                        asking.form.dataset.ok = '1';
                        asking.form.requestSubmit();
                    }}
                >
                    {t('Nothing on the site changes. Genwave reads its plugin list again and WordPress checks for plugin updates.', 'שום דבר באתר לא משתנה. Genwave קורא שוב את רשימת התוספים, ווורדפרס בודק אם יש עדכונים לתוספים.')}
                </Confirm>
            )}
        </div>
    );
}
