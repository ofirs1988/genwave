import React, { useEffect } from 'react';
import Glyph from './Glyph';
import { __ } from '@wordpress/i18n';

/**
 * The building blocks of the Genwave admin screens (app.css), the same pieces
 * as the Chatbot's kit: page header, card, row, buttons, callout, pill, dialog.
 */

/** Everything the server put on the page (Core\AdminApp::data). */
export const data = () => window.genwaveApp || {};


/** POST to admin-ajax.php; resolves to the parsed JSON (or { success:false }). */
export async function ajax(action, fields = {}) {
    try {
        const res = await fetch(data().ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action, ...fields }),
        });
        return await res.json();
    } catch (e) {
        return { success: false, data: { message: e.message } };
    }
}

/** How usage stands, in words: never a number (AdminBar::usage_label states). */
export function usageWords(a) {
    const words = {
        ok: __("Limited use", 'gen-wave'),
        low: __("Free use almost used up", 'gen-wave'),
        out: __("Free use used up", 'gen-wave'),
        light: __("Light mode", 'gen-wave'),
    };
    return words[a?.usageState] || a?.usageText || __("Normal use fully included", 'gen-wave');
}

/** The invitation when the Free plan's monthly use is almost or fully used. */
export function UpgradeCallout({ a, links }) {
    if (a?.usageState !== 'out' && a?.usageState !== 'low') return null;
    const out = a.usageState === 'out';
    return (
        <Callout
            tone="ok"
            icon="sparkle"
            title={out ? __("You've used this month's free use", 'gen-wave') : __("Your free use is almost used up", 'gen-wave')}
            actions={<Button size="sm" variant="primary" icon="arrowOut" href={links?.plans} external>{__("Upgrade", 'gen-wave')}</Button>}
        >
            {__("Enjoying what we built together? On a paid plan the agent keeps working for you, with the best models and AI images.", 'gen-wave')}
        </Callout>
    );
}

export function Spinner({ size = 16 }) {
    return (
        <svg className="gw-spin" width={size} height={size} viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="9" stroke="currentColor" strokeWidth="3" opacity="0.25" />
            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
        </svg>
    );
}

export function PageHeader({ icon, eyebrow, title, sub }) {
    return (
        <header className="gw-head">
            {icon && <div className="gw-head__tile"><span className="gw-tile"><Glyph name={icon} size={20} /></span></div>}
            {eyebrow && <p className="gw-eyebrow">{eyebrow}</p>}
            <h2 className="gw-title">{title}</h2>
            {sub && <p className="gw-sub">{sub}</p>}
        </header>
    );
}

export function Card({ title, hint, end, danger, children }) {
    return (
        <section className={`gw-card${danger ? ' gw-card--danger' : ''}`}>
            {(title || end) && (
                <div className="gw-card__head">
                    <div>
                        {title && <h3 className="gw-card__title">{title}</h3>}
                        {hint && <p className="gw-card__hint">{hint}</p>}
                    </div>
                    {end}
                </div>
            )}
            <div className="gw-card__body">{children}</div>
        </section>
    );
}

export function Row({ label, detail, end, wrap = true }) {
    return (
        <div className={`gw-row${wrap ? ' gw-row--wrap' : ''}`}>
            <div className="gw-row__text">
                <div className="gw-row__label">{label}</div>
                {detail && <div className="gw-row__detail">{detail}</div>}
            </div>
            {end && <div className="gw-row__end">{end}</div>}
        </div>
    );
}

/** A row that goes somewhere: icon tile, text, trailing arrow. */
export function ActionRow({ icon, label, detail, href, onClick, external }) {
    const inner = (
        <>
            <span className="gw-tile gw-tile--sm"><Glyph name={icon} size={18} /></span>
            <span className="gw-row__text">
                <span className="gw-row__label" style={{ display: 'block' }}>{label}</span>
                {detail && <span className="gw-row__detail" style={{ display: 'block' }}>{detail}</span>}
            </span>
            <Glyph name={external ? 'arrowOut' : 'arrow'} className="gw-action__go gw-flip" />
        </>
    );
    return href ? (
        <a className="gw-action" href={href} target={external ? '_blank' : undefined} rel={external ? 'noopener noreferrer' : undefined}>{inner}</a>
    ) : (
        <button type="button" className="gw-action" onClick={onClick}>{inner}</button>
    );
}

export function Button({ variant = 'secondary', size, block, href, external, icon, busy, children, className = '', ...rest }) {
    const cls = `gw-btn gw-btn--${variant}${size ? ` gw-btn--${size}` : ''}${block ? ' gw-btn--block' : ''} ${className}`;
    const body = (
        <>
            {busy ? <Spinner /> : icon ? <Glyph name={icon} /> : null}
            {children}
        </>
    );
    if (href) {
        return (
            <a className={cls} href={href} target={external ? '_blank' : undefined} rel={external ? 'noopener noreferrer' : undefined} {...rest}>
                {body}
            </a>
        );
    }
    return (
        <button type="button" className={cls} disabled={busy || rest.disabled} {...rest}>
            {body}
        </button>
    );
}

export function Pill({ tone, children }) {
    return <span className={`gw-pill${tone ? ` gw-pill--${tone}` : ''}`}>{children}</span>;
}

export function Callout({ tone, icon, title, children, actions }) {
    const glyph = icon || { warn: 'warning', bad: 'error', ok: 'check' }[tone] || 'info';
    return (
        <div className={`gw-callout${tone ? ` gw-callout--${tone}` : ''}`} role={tone === 'bad' ? 'alert' : undefined}>
            <Glyph name={glyph} />
            <div className="gw-callout__text">
                {title && <strong>{title}</strong>}
                {children}
                {actions && <div className="gw-callout__actions">{actions}</div>}
            </div>
        </div>
    );
}

export function Note({ tone, children }) {
    const glyph = { ok: 'check', bad: 'warning' }[tone] || 'info';
    return (
        <p className={`gw-note${tone ? ` gw-note--${tone}` : ''}`}>
            <Glyph name={glyph} /> {children}
        </p>
    );
}

export function Stats({ items }) {
    return (
        <div className="gw-stats">
            {items.map((s) => (
                <div className="gw-stat" key={s.label}>
                    <div className="gw-stat__label">{s.icon && <Glyph name={s.icon} size={14} />}{s.label}</div>
                    <div className="gw-stat__value">{s.value}</div>
                    {s.note && <div className="gw-stat__note">{s.note}</div>}
                </div>
            ))}
        </div>
    );
}

export function Confirm({ title, children, confirmLabel, danger, busy, onConfirm, onCancel }) {
    useEffect(() => {
        const onKey = (e) => e.key === 'Escape' && (e.stopPropagation(), onCancel());
        window.addEventListener('keydown', onKey, true);
        return () => window.removeEventListener('keydown', onKey, true);
    }, [onCancel]);
    return (
        <div className="gw-overlay" onMouseDown={(e) => e.target === e.currentTarget && onCancel()}>
            <div className="gw-dialog" role="dialog" aria-modal="true" aria-label={title}>
                <div className="gw-dialog__head">{title}</div>
                <div className="gw-dialog__body">{children}</div>
                <div className="gw-dialog__foot">
                    <Button variant="ghost" onClick={onCancel}>{__("Cancel", 'gen-wave')}</Button>
                    <Button variant={danger ? 'danger' : 'primary'} busy={busy} onClick={onConfirm}>{confirmLabel}</Button>
                </div>
            </div>
        </div>
    );
}
