import React, { useEffect, useState } from 'react';
import Glyph from '../Glyph';
import { data, ajax, Card, Button, Callout, PageHeader, Spinner, Pill } from '../ui';
import { __ } from '@wordpress/i18n';

/**
 * AI writing: a title, description or short description for one product or
 * post at a time (genwave_generate_single, then genwave_apply_content).
 */

const FIELDS = () => [
    { key: 'title', label: __("Title", 'gen-wave'), desc: __("A concise, search-friendly title", 'gen-wave') },
    { key: 'description', label: __("Description", 'gen-wave'), desc: __("A full, detailed description", 'gen-wave') },
    { key: 'shortDescription', label: __("Short description", 'gen-wave'), desc: __("A brief one-line summary", 'gen-wave') },
];

const LANGUAGES = [
    ['en', 'English'], ['he', 'עברית'], ['ar', 'العربية'], ['es', 'Español'], ['fr', 'Français'],
    ['de', 'Deutsch'], ['it', 'Italiano'], ['pt', 'Português'], ['ru', 'Русский'], ['zh', '中文'],
    ['ja', '日本語'], ['nl', 'Nederlands'], ['pl', 'Polski'], ['tr', 'Türkçe'], ['hi', 'हिन्दी'],
];

export default function Generate() {
    const d = data();
    const hasWoo = !!d.hasWoo;
    const [type, setType] = useState(hasWoo ? 'products' : 'posts');
    const [items, setItems] = useState([]);
    const [loading, setLoading] = useState(true);
    const [selected, setSelected] = useState(null);
    const [field, setField] = useState('description');
    const [language, setLanguage] = useState((d.locale || 'en').slice(0, 2));
    const [instructions, setInstructions] = useState('');
    const [generating, setGenerating] = useState(false);
    const [applying, setApplying] = useState(false);
    const [result, setResult] = useState(null);
    const [content, setContent] = useState(null);
    const [doneField, setDoneField] = useState(null);
    const [copied, setCopied] = useState(false);

    useEffect(() => {
        let live = true;
        setLoading(true);
        setSelected(null);
        ajax(type === 'products' ? 'genwave_get_all_products' : 'genwave_get_all_posts', { nonce: d.nonces?.general }).then((r) => {
            if (!live) return;
            setItems(r?.success ? (type === 'products' ? r.data?.products : r.data?.posts) || [] : []);
            setLoading(false);
        });
        return () => {
            live = false;
        };
    }, [type, d.nonces]);

    const fieldLabel = (f) => FIELDS().find((o) => o.key === f)?.label || __("Content", 'gen-wave');

    const generate = async () => {
        if (!selected) return;
        setGenerating(true);
        setResult(null);
        setContent(null);
        setCopied(false);
        setDoneField(field);
        const r = await ajax('genwave_generate_single', {
            nonce: d.nonces?.generate,
            post_id: selected.id,
            post_type: type === 'products' ? 'product' : 'post',
            // The server's names (GenerationHandler): short_description, not shortDescription.
            generation_method: field === 'shortDescription' ? 'short_description' : field,
            language,
            instructions,
        });
        const failed = r?.data?.error || r?.data?.data?.error;
        if (r?.success && !failed) {
            const api = r.data?.data || r.data;
            const wrap = api?.results || {};
            const list = wrap?.results || api?.results || [];
            const first = Array.isArray(list) ? list[0] || {} : {};
            const c = first.content || {};
            const byField = {
                title: c.title,
                description: c.description || c.content,
                shortDescription: c.short_description || c.shortDescription,
            };
            setContent(byField[field] || Object.values(c)[0] || '');
            setResult({ ok: true });
        } else {
            setResult({ ok: false, message: r?.data?.data?.message || r?.data?.message || r?.message || __("Generation failed", 'gen-wave') });
        }
        setGenerating(false);
    };

    const apply = async () => {
        if (!content || !selected) return;
        setApplying(true);
        const r = await ajax('genwave_apply_content', { nonce: d.nonces?.generate, post_id: selected.id, field: doneField, content });
        setApplying(false);
        if (r?.success) {
            setResult((p) => ({ ...p, applied: true }));
            setItems((p) => p.map((it) => (it.id === selected.id ? { ...it, generated: true } : it)));
        } else {
            setResult((p) => ({ ...p, applyError: r?.data?.message || __("The text could not be saved.", 'gen-wave') }));
        }
    };

    const copy = () => {
        const div = document.createElement('div');
        div.innerHTML = content || '';
        navigator.clipboard?.writeText(div.textContent || '').then(() => setCopied(true));
    };

    return (
        <div className="gw-col gw-col--wide">
            <PageHeader
                eyebrow={__("Tools", 'gen-wave')}
                title={__("AI writing", 'gen-wave')}
                sub={__("Titles and descriptions for your products and posts, one field at a time. You read it before it is saved.", 'gen-wave')}
            />

            {hasWoo && (
                <div className="gw-chips" role="tablist">
                    <button type="button" role="tab" aria-selected={type === 'products'} className={`gw-chip${type === 'products' ? ' is-on' : ''}`} onClick={() => setType('products')}>
                        <Glyph name="cart" /> {__("Products", 'gen-wave')}
                    </button>
                    <button type="button" role="tab" aria-selected={type === 'posts'} className={`gw-chip${type === 'posts' ? ' is-on' : ''}`} onClick={() => setType('posts')}>
                        <Glyph name="post" /> {__("Posts", 'gen-wave')}
                    </button>
                </div>
            )}

            <div className="gw-grid2">
                <Card title={type === 'products' ? __("Choose a product", 'gen-wave') : __("Choose a post", 'gen-wave')} hint={!loading && items.length > 50 ? __("The 50 most recent", 'gen-wave') : null}>
                    {loading ? (
                        <div className="gw-card__pad" style={{ display: 'grid', gap: 10 }}>
                            {[0, 1, 2, 3, 4].map((i) => <div key={i} className="gw-sk" style={{ height: 18 }} />)}
                        </div>
                    ) : items.length === 0 ? (
                        <div className="gw-empty">
                            <Glyph name={type === 'products' ? 'cart' : 'post'} size={28} />
                            {type === 'products' ? __("No products yet.", 'gen-wave') : __("No posts yet.", 'gen-wave')}
                        </div>
                    ) : (
                        <div className="gw-list" role="listbox" aria-label={__("Items", 'gen-wave')}>
                            {items.slice(0, 50).map((it) => (
                                <button
                                    type="button"
                                    key={it.id}
                                    role="option"
                                    aria-selected={selected?.id === it.id}
                                    className={`gw-list__row${selected?.id === it.id ? ' is-on' : ''}`}
                                    onClick={() => setSelected(it)}
                                >
                                    <span className="gw-list__name">{it.title || it.name || `#${it.id}`}</span>
                                    {it.generated && <Glyph name="check" className="gw-list__done" />}
                                </button>
                            ))}
                        </div>
                    )}
                </Card>

                <div>
                    <div className="gw-card__head"><h3 className="gw-card__title">{__("What to write", 'gen-wave')}</h3></div>
                    <div className="gw-options">
                        {FIELDS().map((o) => (
                            <button type="button" key={o.key} className={`gw-option${field === o.key ? ' is-on' : ''}`} onClick={() => setField(o.key)} aria-pressed={field === o.key}>
                                <span className="gw-radio" />
                                <span>
                                    <span className="gw-option__label" style={{ display: 'block' }}>{o.label}</span>
                                    <span className="gw-option__desc" style={{ display: 'block' }}>{o.desc}</span>
                                </span>
                            </button>
                        ))}
                    </div>

                    <label className="gw-field">
                        <span className="gw-field__label">{__("Language", 'gen-wave')}</span>
                        <select className="gw-input" value={language} onChange={(e) => setLanguage(e.target.value)}>
                            {LANGUAGES.map(([code, name]) => <option key={code} value={code}>{name}</option>)}
                        </select>
                    </label>

                    <label className="gw-field">
                        <span className="gw-field__label">{__("Instructions", 'gen-wave')}<em>{__("Optional", 'gen-wave')}</em></span>
                        <textarea
                            className="gw-input"
                            value={instructions}
                            maxLength={1000}
                            onChange={(e) => setInstructions(e.target.value)}
                            placeholder={__("e.g. Focus on the eco-friendly materials, in a warm tone.", 'gen-wave')}
                        />
                        <span className="gw-field__hint gw-num" dir="ltr" style={{ textAlign: 'end' }}>{instructions.length}/1000</span>
                    </label>

                    <Button variant="primary" block icon="sparkle" busy={generating} disabled={!selected} onClick={generate}>
                        {generating ? __("Writing…", 'gen-wave') : `${__("Write", 'gen-wave')} ${fieldLabel(field)}`}
                    </Button>
                    <p className="gw-field__hint" style={{ textAlign: 'center' }}>
                        {selected ? <>{__("For", 'gen-wave')}: <strong>{selected.title || selected.name}</strong></> : __("Choose an item first.", 'gen-wave')}
                    </p>
                </div>
            </div>

            {result && !generating && (
                <div style={{ marginTop: 24 }}>
                    {!result.ok ? (
                        <Callout tone="bad">{result.message}</Callout>
                    ) : (
                        <Card
                            title={`${fieldLabel(doneField)} · ${selected?.title || selected?.name || ''}`}
                            end={result.applied ? <Pill tone="ok">{__("Saved", 'gen-wave')}</Pill> : null}
                        >
                            {doneField === 'title' ? (
                                <div className="gw-output"><p className="gw-output__title">{content}</p></div>
                            ) : (
                                <div className="gw-output" dangerouslySetInnerHTML={{ __html: content || '' }} />
                            )}
                            {!result.applied && (
                                <div className="gw-row" style={{ flexWrap: 'wrap' }}>
                                    <Button variant="primary" size="sm" icon="check" busy={applying} onClick={apply}>{__("Save to the item", 'gen-wave')}</Button>
                                    <Button variant="secondary" size="sm" icon="copy" onClick={copy}>{copied ? __("Copied", 'gen-wave') : __("Copy", 'gen-wave')}</Button>
                                    <Button variant="ghost" size="sm" icon="refresh" onClick={generate}>{__("Write again", 'gen-wave')}</Button>
                                    {result.applyError && <span className="gw-note gw-note--bad">{result.applyError}</span>}
                                </div>
                            )}
                        </Card>
                    )}
                </div>
            )}
            {generating && (
                <div className="gw-loading" style={{ padding: '48px 0' }}><Spinner size={20} /></div>
            )}
        </div>
    );
}
