import React, { useState, useEffect } from 'react';
import {
    ShoppingOutlined,
    FileTextOutlined,
    EditOutlined,
    CheckCircleOutlined,
    LoadingOutlined,
    ThunderboltOutlined,
    UnorderedListOutlined,
    CopyOutlined,
    SaveOutlined,
    CloseCircleOutlined,
} from '@ant-design/icons';

const FIELD_OPTIONS = [
    { key: 'title', label: 'Title', desc: 'A concise, SEO-friendly title' },
    { key: 'description', label: 'Description', desc: 'A full, detailed description' },
    { key: 'shortDescription', label: 'Short description', desc: 'A brief one-line summary' },
];

const LANGUAGES = [
    ['en', 'English'], ['he', 'עברית'], ['ar', 'العربية'], ['es', 'Español'], ['fr', 'Français'],
    ['de', 'Deutsch'], ['it', 'Italiano'], ['pt', 'Português'], ['ru', 'Русский'], ['zh', '中文'],
    ['ja', '日本語'], ['nl', 'Nederlands'], ['pl', 'Polski'], ['tr', 'Türkçe'], ['hi', 'हिन्दी'],
];

const Generate = () => {
    const hasWoo = window.genwaveFreeSettings?.hasWooCommerce === '1';
    const [contentType, setContentType] = useState(hasWoo ? 'products' : 'posts');
    const [items, setItems] = useState([]);
    const [selectedItem, setSelectedItem] = useState(null);
    const [loading, setLoading] = useState(true);
    const [generating, setGenerating] = useState(false);
    const [applying, setApplying] = useState(false);
    const [selectedGenerateOption, setSelectedGenerateOption] = useState('description');
    const [language, setLanguage] = useState('en');
    const [customInstructions, setCustomInstructions] = useState('');
    const [result, setResult] = useState(null);
    const [generatedContent, setGeneratedContent] = useState(null);
    const [generatedField, setGeneratedField] = useState(null);

    useEffect(() => {
        const checkAndFetch = () => {
            if (window.genwaveFreeSettings?.ajaxurl) {
                fetchItems();
            } else {
                setTimeout(checkAndFetch, 100);
            }
        };
        checkAndFetch();
    }, [contentType]);

    const fetchItems = async () => {
        setLoading(true);
        setSelectedItem(null);
        try {
            const action = contentType === 'products' ? 'genwave_get_all_products' : 'genwave_get_all_posts';
            const response = await fetch(window.genwaveFreeSettings.ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action, nonce: window.genwaveFreeSettings.nonce }),
            });
            const data = await response.json();
            if (data.success) {
                const itemsArray = contentType === 'products' ? (data.data?.products || []) : (data.data?.posts || []);
                setItems(itemsArray);
            }
        } catch (error) {
            console.error('Failed to fetch items:', error);
        }
        setLoading(false);
    };

    const handleGenerate = async () => {
        if (!selectedItem) return;
        setGenerating(true);
        setResult(null);
        setGeneratedContent(null);
        setGeneratedField(selectedGenerateOption);

        try {
            const response = await fetch(window.genwaveFreeSettings.ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'genwave_generate_single',
                    nonce: window.genwaveFreeSettings.generateNonce,
                    post_id: selectedItem.id,
                    post_type: contentType === 'products' ? 'product' : 'post',
                    generation_method: selectedGenerateOption,
                    language: language,
                    instructions: customInstructions,
                }),
            });
            const data = await response.json();
            const hasError = data.data?.error || data.data?.data?.error;

            if (data.success && !hasError) {
                const apiData = data.data?.data || data.data;
                const resultsWrapper = apiData?.results || {};
                const resultsArray = resultsWrapper?.results || apiData?.results || [];
                const firstResult = Array.isArray(resultsArray) ? resultsArray[0] : {};
                const content = firstResult.content || {};
                const fieldMap = {
                    title: content.title,
                    description: content.description || content.content,
                    shortDescription: content.short_description || content.shortDescription,
                };
                const generatedText = fieldMap[selectedGenerateOption] || Object.values(content)[0] || '';
                const tokenUsage = resultsWrapper?.token_usage || apiData?.token_usage || {};
                setGeneratedContent(generatedText);

                const newBalance = tokenUsage.credits_balance || tokenUsage.tokens_balance || 0;
                setResult({
                    success: true,
                    message: 'Content generated',
                    creditUsage: {
                        charged: tokenUsage.credits_charged_to_user || tokenUsage.tokens_charged || tokenUsage.actual_total_tokens || 0,
                        balance: newBalance,
                    },
                });

                const adminBarTokens = document.querySelector('#wp-admin-bar-custom_text_with_icon span');
                if (adminBarTokens) adminBarTokens.textContent = parseFloat(newBalance).toFixed(2);
                const creditBalanceEl = document.getElementById('credit-balance');
                if (creditBalanceEl) creditBalanceEl.textContent = parseFloat(newBalance).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            } else {
                const errorMessage = data.data?.data?.message || data.data?.message || data.message || 'Generation failed';
                setResult({ success: false, message: errorMessage });
            }
        } catch (error) {
            console.error('Generation error:', error);
            setResult({ success: false, message: 'An error occurred during generation' });
        }
        setGenerating(false);
    };

    const handleApply = async () => {
        if (!generatedContent || !selectedItem) return;
        setApplying(true);
        try {
            const response = await fetch(window.genwaveFreeSettings.ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'genwave_apply_content',
                    nonce: window.genwaveFreeSettings.generateNonce,
                    post_id: selectedItem.id,
                    field: generatedField,
                    content: generatedContent,
                }),
            });
            const data = await response.json();
            if (data.success) {
                setResult((prev) => ({ ...prev, applied: true }));
                setItems((prev) => prev.map((item) => (item.id === selectedItem.id ? { ...item, generated: true } : item)));
            } else {
                alert(data.data?.message || 'Failed to apply content');
            }
        } catch (error) {
            alert('Error applying content');
        }
        setApplying(false);
    };

    const copyToClipboard = () => {
        if (!generatedContent) return;
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = generatedContent;
        navigator.clipboard.writeText(tempDiv.textContent || tempDiv.innerText || '');
    };

    const fieldLabel = (f) => (FIELD_OPTIONS.find((o) => o.key === f)?.label || 'Content');

    return (
        <div className="gw-page gw-gen">
            <header className="gw-gen__head">
                <h1 className="gw-gen__title">Generate Content</h1>
                <p className="gw-gen__subtitle">Generate titles and descriptions for your products and posts, one field at a time.</p>
            </header>

            {/* Content type */}
            {hasWoo && (
                <div className="gw-gen__toggle">
                    <button className={`gw-gen__seg ${contentType === 'products' ? 'is-on' : ''}`} onClick={() => setContentType('products')}>
                        <ShoppingOutlined /> Products
                    </button>
                    <button className={`gw-gen__seg ${contentType === 'posts' ? 'is-on' : ''}`} onClick={() => setContentType('posts')}>
                        <FileTextOutlined /> Posts
                    </button>
                </div>
            )}

            <div className="gw-gen__grid">
                {/* Left: item picker */}
                <section className="gw-card gw-gen-panel">
                    <h3 className="gw-gen-panel__title"><UnorderedListOutlined /> Choose an item</h3>
                    {loading ? (
                        <div className="gw-gen__loading"><LoadingOutlined /> Loading…</div>
                    ) : items.length === 0 ? (
                        <div className="gw-gen__empty">
                            {contentType === 'products' ? <ShoppingOutlined /> : <FileTextOutlined />}
                            <p>No {contentType} found yet.</p>
                        </div>
                    ) : (
                        <ul className="gw-gen-list">
                            {items.slice(0, 50).map((item) => (
                                <li
                                    key={item.id}
                                    className={`gw-gen-list__row ${selectedItem?.id === item.id ? 'is-sel' : ''}`}
                                    onClick={() => setSelectedItem(item)}
                                >
                                    <span className="gw-gen-list__radio" aria-hidden="true" />
                                    <span className="gw-gen-list__name">{item.title || item.name || `#${item.id}`}</span>
                                    {item.generated && <span className="gw-gen-list__done"><CheckCircleOutlined /></span>}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {/* Right: options */}
                <section className="gw-card gw-gen-panel">
                    <h3 className="gw-gen-panel__title"><EditOutlined /> What to generate</h3>

                    <div className="gw-gen-opts">
                        {FIELD_OPTIONS.map((o) => (
                            <button
                                key={o.key}
                                className={`gw-gen-opt ${selectedGenerateOption === o.key ? 'is-sel' : ''}`}
                                onClick={() => setSelectedGenerateOption(o.key)}
                            >
                                <span className="gw-gen-opt__label">{o.label}</span>
                                <span className="gw-gen-opt__desc">{o.desc}</span>
                            </button>
                        ))}
                    </div>

                    <label className="gw-gen-field">
                        <span className="gw-gen-field__label">Language</span>
                        <select className="gw-gen-select" value={language} onChange={(e) => setLanguage(e.target.value)}>
                            {LANGUAGES.map(([code, name]) => <option key={code} value={code}>{name}</option>)}
                        </select>
                    </label>

                    <label className="gw-gen-field">
                        <span className="gw-gen-field__label">Custom instructions <em>(optional)</em></span>
                        <textarea
                            className="gw-gen-textarea"
                            value={customInstructions}
                            onChange={(e) => setCustomInstructions(e.target.value)}
                            maxLength={1000}
                            placeholder="e.g. Focus on the eco-friendly features and a warm, friendly tone."
                        />
                        <span className="gw-gen-field__count">{customInstructions.length}/1000</span>
                    </label>

                    <button className="gw-gen__go" onClick={handleGenerate} disabled={!selectedItem || generating}>
                        {generating ? <><LoadingOutlined /> Generating…</> : <><ThunderboltOutlined /> Generate {fieldLabel(selectedGenerateOption)}</>}
                    </button>
                    {selectedItem && !generating && (
                        <p className="gw-gen__selhint">For: <strong>{selectedItem.title || selectedItem.name}</strong></p>
                    )}
                </section>
            </div>

            {/* Result */}
            {result && !generating && (
                <section className={`gw-card gw-gen-result ${result.success ? 'is-ok' : 'is-err'}`}>
                    <div className="gw-gen-result__head">
                        <h3>{result.success ? <CheckCircleOutlined /> : <CloseCircleOutlined />} {result.message}</h3>
                        {result.applied && <span className="gw-gen-result__applied"><CheckCircleOutlined /> Applied</span>}
                    </div>

                    {result.success && result.creditUsage && (
                        <div className="gw-gen-result__meta">
                            <span>Charged <strong>{Number(result.creditUsage.charged).toFixed(4)}</strong> credits</span>
                            <span className="gw-gen-result__bal">Balance <strong>{Number(result.creditUsage.balance).toFixed(2)}</strong></span>
                        </div>
                    )}

                    {generatedContent && (
                        <>
                            <div className="gw-gen-output">
                                <span className="gw-gen-output__label">Generated {fieldLabel(generatedField)}</span>
                                {generatedField === 'title' ? (
                                    <div className="gw-gen-output__title">{generatedContent}</div>
                                ) : (
                                    <div className="gw-gen-output__body" dangerouslySetInnerHTML={{ __html: generatedContent }} />
                                )}
                            </div>
                            {!result.applied && (
                                <div className="gw-gen-result__actions">
                                    <button className="gw-gen__go gw-gen__go--sm" onClick={handleApply} disabled={applying}>
                                        {applying ? <><LoadingOutlined /> Applying…</> : <><SaveOutlined /> Apply</>}
                                    </button>
                                    <button className="gw-gen__ghost" onClick={copyToClipboard}><CopyOutlined /> Copy</button>
                                    <button className="gw-gen__ghost" onClick={handleGenerate}><ThunderboltOutlined /> Regenerate</button>
                                </div>
                            )}
                        </>
                    )}
                </section>
            )}
        </div>
    );
};

export default Generate;
