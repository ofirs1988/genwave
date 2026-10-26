import React, { useState } from 'react';
import Glyph from '../Glyph';
import { data, Card, Row, Callout, PageHeader, Pill, Confirm } from '../ui';
import { __ } from '@wordpress/i18n';

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
                eyebrow={__("Tools", 'gen-wave')}
                title={__("Maintenance", 'gen-wave')}
                sub={__("The plugin keeps a few tables of its own. If something looks wrong, check and repair them here.", 'gen-wave')}
            />

            {d.flash?.message && <Callout tone={d.flash.type === 'error' ? 'bad' : 'ok'}>{d.flash.message}</Callout>}

            <Card
                title={__("Database", 'gen-wave')}
                hint={db.all_ok ? __("All tables are in order.", 'gen-wave') : __("Some tables need a repair.", 'gen-wave')}
                end={db.all_ok ? <Pill tone="ok">{__("OK", 'gen-wave')}</Pill> : <Pill tone="bad">{__("Needs repair", 'gen-wave')}</Pill>}
            >
                {tables.map(([name, s]) => (
                    <Row
                        key={name}
                        wrap={false}
                        label={<code dir="ltr">{name}</code>}
                        detail={
                            !s.exists
                                ? __("Missing", 'gen-wave')
                                : s.missing_columns?.length
                                  ? `${__("Missing columns", 'gen-wave')}: ${s.missing_columns.join(', ')}`
                                  : null
                        }
                        end={s.exists && !s.missing_columns?.length ? <Glyph name="check" className="gw-list__done" /> : <Glyph name="warning" />}
                    />
                ))}
                <Row
                    label={__("Check and repair", 'gen-wave')}
                    detail={__("Adds any missing table or column. Nothing is deleted.", 'gen-wave')}
                    end={post('ai_repair_database', 'ai_nonce', tools.repairNonce, __("Repair", 'gen-wave'), 'database', db.all_ok ? 'secondary' : 'primary')}
                />
            </Card>

            <Card title={__("Caches", 'gen-wave')}>
                <Row
                    label={__("Clear Genwave's caches", 'gen-wave')}
                    detail={__("Forgets the saved list of Genwave plugins and checks again for plugin updates. The rest of the site is not touched.", 'gen-wave')}
                    end={post('ai_clear_cache', 'ai_nonce', tools.cacheNonce, __("Clear", 'gen-wave'), 'broom', 'secondary', __("Clear Genwave's caches now?", 'gen-wave'))}
                />
            </Card>

            <Card title={__("System", 'gen-wave')}>
                <dl>
                    <div className="gw-kv"><dt>Genwave</dt><dd className="gw-num" dir="ltr">{d.version}</dd></div>
                    <div className="gw-kv"><dt>WordPress</dt><dd className="gw-num" dir="ltr">{tools.wp}</dd></div>
                    <div className="gw-kv"><dt>PHP</dt><dd className="gw-num" dir="ltr">{tools.php}</dd></div>
                    <div className="gw-kv"><dt>WooCommerce</dt><dd>{d.hasWoo ? __("Active", 'gen-wave') : __("Not active", 'gen-wave')}</dd></div>
                </dl>
            </Card>

            {asking && (
                <Confirm
                    title={asking.text}
                    confirmLabel={__("Clear", 'gen-wave')}
                    busy={sending}
                    onCancel={() => !sending && setAsking(null)}
                    onConfirm={() => {
                        setSending(true);
                        asking.form.dataset.ok = '1';
                        asking.form.requestSubmit();
                    }}
                >
                    {__("Nothing on the site changes. Genwave reads its plugin list again and WordPress checks for plugin updates.", 'gen-wave')}
                </Confirm>
            )}
        </div>
    );
}
