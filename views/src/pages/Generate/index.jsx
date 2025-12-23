import React, { useState, useEffect } from 'react';
import {
    ShoppingOutlined,
    FileTextOutlined,
    EditOutlined,
    CheckCircleOutlined,
    LoadingOutlined,
    PictureOutlined,
    AudioOutlined,
    ThunderboltOutlined,
    FilterOutlined,
    CrownOutlined,
    RocketOutlined,
    AppstoreOutlined,
    UnorderedListOutlined,
    CopyOutlined,
    SaveOutlined,
    CloseCircleOutlined,
    TagsOutlined
} from '@ant-design/icons';
import LockedFeature from '../../components/LockedFeature';

const Generate = () => {
    const hasWoo = window.genwaveFreeSettings?.hasWooCommerce === '1';
    const [contentType, setContentType] = useState(hasWoo ? 'products' : 'posts');
    const [items, setItems] = useState([]);
    const [selectedItem, setSelectedItem] = useState(null);
    const [loading, setLoading] = useState(true);
    const [generating, setGenerating] = useState(false);
    const [applying, setApplying] = useState(false);
    // Free version: single selection only (Pro allows multiple)
    const [selectedGenerateOption, setSelectedGenerateOption] = useState('description');
    const [language, setLanguage] = useState('en');
    const [customInstructions, setCustomInstructions] = useState('');
    const [result, setResult] = useState(null);
    const [generatedContent, setGeneratedContent] = useState(null);
    const [generatedField, setGeneratedField] = useState(null);

    useEffect(() => {
        // Wait for settings to be available
        const checkAndFetch = () => {
            if (window.genwaveFreeSettings?.ajaxurl) {
                fetchItems();
            } else {
                // Retry after a short delay if settings not ready
                setTimeout(checkAndFetch, 100);
            }
        };
        checkAndFetch();
    }, [contentType]);

    const fetchItems = async () => {
        setLoading(true);
        setSelectedItem(null);
        try {
            const action = contentType === 'products'
                ? 'genwave_get_all_products'
                : 'genwave_get_all_posts';

            const response = await fetch(window.genwaveFreeSettings.ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: action,
                    nonce: window.genwaveFreeSettings.nonce
                })
            });

            const data = await response.json();
            if (data.success) {
                // API returns { products: [...] } or { posts: [...] }
                const itemsArray = contentType === 'products'
                    ? (data.data?.products || [])
                    : (data.data?.posts || []);
                setItems(itemsArray);
            }
        } catch (error) {
            console.error('Failed to fetch items:', error);
        }
        setLoading(false);
    };

    const handleGenerate = async () => {
        if (!selectedItem) {
            alert('Please select an item first');
            return;
        }

        if (!selectedGenerateOption) {
            alert('Please select an option to generate');
            return;
        }

        setGenerating(true);
        setResult(null);
        setGeneratedContent(null);
        setGeneratedField(selectedGenerateOption);

        try {
            const response = await fetch(window.genwaveFreeSettings.ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'genwave_generate_single',
                    nonce: window.genwaveFreeSettings.generateNonce,
                    post_id: selectedItem.id,
                    post_type: contentType === 'products' ? 'product' : 'post',
                    generation_method: selectedGenerateOption,
                    language: language,
                    instructions: customInstructions
                })
            });

            const data = await response.json();

            // Check for errors at multiple levels
            const hasError = data.data?.error || data.data?.data?.error;

            if (data.success && !hasError) {
                // Extract content from nested response structure
                // Response: data.data = { data: { results: { results: [...], token_usage: {...} } }, job_id, post_request_id }
                const apiData = data.data?.data || data.data;
                const resultsWrapper = apiData?.results || {};
                const resultsArray = resultsWrapper?.results || apiData?.results || [];
                const firstResult = Array.isArray(resultsArray) ? resultsArray[0] : {};
                const content = firstResult.content || {};

                // Get the generated text based on field type
                const fieldMap = {
                    'title': content.title,
                    'description': content.description || content.content,
                    'shortDescription': content.short_description || content.shortDescription
                };

                const generatedText = fieldMap[selectedGenerateOption] || Object.values(content)[0] || '';

                // Get token usage info
                // token_usage and tokens_used are SEPARATE keys at resultsWrapper level
                const tokenUsage = resultsWrapper?.token_usage || apiData?.token_usage || {};

                setGeneratedContent(generatedText);

                const newBalance = tokenUsage.tokens_balance || 0;
                setResult({
                    success: true,
                    message: 'Content generated successfully!',
                    tokenUsage: {
                        estimated: tokenUsage.estimated_total_tokens || 0,
                        charged: tokenUsage.tokens_charged || tokenUsage.actual_total_tokens || 0,
                        returned: tokenUsage.tokens_returned || 0,
                        balance: newBalance
                    }
                });

                // Update admin bar token display
                const adminBarTokens = document.querySelector('#wp-admin-bar-custom_text_with_icon span');
                if (adminBarTokens) {
                    adminBarTokens.textContent = parseFloat(newBalance).toFixed(2);
                }
            } else {
                const errorMessage = data.data?.data?.message
                    || data.data?.message
                    || data.message
                    || 'Generation failed';
                setResult({
                    success: false,
                    message: errorMessage
                });
            }
        } catch (error) {
            console.error('Generation error:', error);
            setResult({
                success: false,
                message: 'An error occurred during generation'
            });
        }

        setGenerating(false);
    };

    const handleApply = async () => {
        if (!generatedContent || !selectedItem) return;

        setApplying(true);

        try {
            const response = await fetch(window.genwaveFreeSettings.ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'genwave_apply_content',
                    nonce: window.genwaveFreeSettings.generateNonce,
                    post_id: selectedItem.id,
                    field: generatedField,
                    content: generatedContent
                })
            });

            const data = await response.json();

            if (data.success) {
                setResult(prev => ({
                    ...prev,
                    applied: true,
                    applyMessage: 'Content applied successfully!'
                }));
                // Update item in list to show as generated
                setItems(prev => prev.map(item =>
                    item.id === selectedItem.id
                        ? { ...item, generated: true }
                        : item
                ));
            } else {
                alert(data.data?.message || 'Failed to apply content');
            }
        } catch (error) {
            alert('Error applying content');
        }

        setApplying(false);
    };

    const copyToClipboard = () => {
        if (generatedContent) {
            // Strip HTML for copying plain text
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = generatedContent;
            const plainText = tempDiv.textContent || tempDiv.innerText;
            navigator.clipboard.writeText(plainText);
            alert('Content copied to clipboard!');
        }
    };

    return (
        <div className="gw-page">
            {/* Header */}
            <div className="gw-page__header">
                <h1 className="gw-page__title">Generate Content</h1>
                <p className="gw-page__subtitle">Create AI-powered content for your products and posts</p>
            </div>

            {/* Content Type Selector */}
            <div className="gw-section">
                <h3 className="gw-section__title">
                    <AppstoreOutlined /> Select Content Type
                </h3>
                <div style={{ display: 'flex', gap: '16px' }}>
                    {hasWoo && (
                        <button
                            className={`gw-btn ${contentType === 'products' ? 'gw-btn--primary' : 'gw-btn--secondary'}`}
                            onClick={() => setContentType('products')}
                        >
                            <ShoppingOutlined /> Products
                        </button>
                    )}
                    <button
                        className={`gw-btn ${contentType === 'posts' ? 'gw-btn--primary' : 'gw-btn--secondary'}`}
                        onClick={() => setContentType('posts')}
                    >
                        <FileTextOutlined /> Posts
                    </button>
                </div>
            </div>

            {/* Advanced Filters - Pro Feature */}
            <div className="gw-section gw-section--disabled" style={{ padding: '16px', opacity: 0.6 }}>
                <div style={{ display: 'flex', gap: '12px', alignItems: 'center', justifyContent: 'space-between' }}>
                    <div style={{ display: 'flex', gap: '12px', alignItems: 'center' }}>
                        <FilterOutlined /> Filter by: Category, Status, Generated, Has Image...
                    </div>
                    <span className="gw-pro-badge"><CrownOutlined /> Pro</span>
                </div>
            </div>

            {/* Items List */}
            <div className="gw-section">
                <h3 className="gw-section__title">
                    <UnorderedListOutlined /> Select Item
                    <span style={{ fontSize: '14px', fontWeight: 'normal', color: 'var(--gw-gray-500)', marginLeft: '8px' }}>
                        (Select one item to generate content)
                    </span>
                </h3>

                {loading ? (
                    <div className="gw-loading">
                        <div className="gw-spinner"></div>
                    </div>
                ) : items.length === 0 ? (
                    <div className="gw-empty">
                        <div className="gw-empty__icon">
                            {contentType === 'products' ? <ShoppingOutlined /> : <FileTextOutlined />}
                        </div>
                        <h4 className="gw-empty__title">No {contentType} found</h4>
                        <p className="gw-empty__text">Create some {contentType} first to generate AI content</p>
                    </div>
                ) : (
                    <div style={{ maxHeight: '300px', overflowY: 'auto' }}>
                        <table className="gw-table">
                            <thead>
                                <tr>
                                    <th style={{ width: '50px' }}></th>
                                    <th>Title</th>
                                    <th style={{ width: '100px' }}>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {items.slice(0, 20).map((item) => (
                                    <tr
                                        key={item.id}
                                        onClick={() => setSelectedItem(item)}
                                        style={{
                                            cursor: 'pointer',
                                            background: selectedItem?.id === item.id ? 'rgba(99, 102, 241, 0.1)' : undefined
                                        }}
                                    >
                                        <td>
                                            <input
                                                type="radio"
                                                checked={selectedItem?.id === item.id}
                                                onChange={() => setSelectedItem(item)}
                                            />
                                        </td>
                                        <td>{item.title || item.name || `Item #${item.id}`}</td>
                                        <td>
                                            <span style={{
                                                padding: '4px 8px',
                                                borderRadius: '4px',
                                                fontSize: '12px',
                                                background: item.generated ? 'var(--gw-success)' : 'var(--gw-gray-200)',
                                                color: item.generated ? 'white' : 'var(--gw-gray-700)'
                                            }}>
                                                {item.generated ? 'Generated' : 'Not Generated'}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        {items.length > 20 && (
                            <div style={{ textAlign: 'center', padding: '16px', color: 'var(--gw-gray-500)' }}>
                                Showing 20 of {items.length} items. Upgrade to Pro for bulk selection.
                            </div>
                        )}
                    </div>
                )}

                {/* Bulk Selection - Pro Feature */}
                <div style={{ marginTop: '16px', padding: '12px', background: 'var(--gw-gray-50)', borderRadius: '8px', opacity: 0.6 }}>
                    <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                        <label style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
                            <input type="checkbox" disabled />
                            Select All ({items.length} items)
                        </label>
                        <span className="gw-pro-badge"><CrownOutlined /> Pro</span>
                    </div>
                </div>
            </div>

            {/* Generation Options */}
            <div className="gw-section">
                <h3 className="gw-section__title">
                    <EditOutlined /> Generation Options
                </h3>

                <div className="gw-generate-options">
                    {/* Text Options - Single Select (Free version) */}
                    <div
                        className={`gw-option-card ${selectedGenerateOption === 'title' ? 'gw-option-card--selected' : ''}`}
                        onClick={() => setSelectedGenerateOption('title')}
                    >
                        <div className="gw-option-card__title">
                            <FileTextOutlined /> Title
                        </div>
                        <div className="gw-option-card__desc">Generate SEO-optimized title</div>
                    </div>

                    <div
                        className={`gw-option-card ${selectedGenerateOption === 'description' ? 'gw-option-card--selected' : ''}`}
                        onClick={() => setSelectedGenerateOption('description')}
                    >
                        <div className="gw-option-card__title">
                            <FileTextOutlined /> Description
                        </div>
                        <div className="gw-option-card__desc">Generate detailed description</div>
                    </div>

                    <div
                        className={`gw-option-card ${selectedGenerateOption === 'shortDescription' ? 'gw-option-card--selected' : ''}`}
                        onClick={() => setSelectedGenerateOption('shortDescription')}
                    >
                        <div className="gw-option-card__title">
                            <FileTextOutlined /> Short Description
                        </div>
                        <div className="gw-option-card__desc">Generate brief summary</div>
                    </div>

                    {/* SEO Option - Pro */}
                    <div className="gw-option-card gw-option-card--pro">
                        <div className="gw-option-card__title">
                            <RocketOutlined /> SEO Meta
                            <span className="gw-pro-badge gw-pro-badge--small"><CrownOutlined /> Pro</span>
                        </div>
                        <div className="gw-option-card__desc">Meta title, description & keywords</div>
                    </div>

                    {/* Categories Option - Pro */}
                    <div className="gw-option-card gw-option-card--pro">
                        <div className="gw-option-card__title">
                            <AppstoreOutlined /> Categories
                            <span className="gw-pro-badge gw-pro-badge--small"><CrownOutlined /> Pro</span>
                        </div>
                        <div className="gw-option-card__desc">Auto-assign relevant categories</div>
                    </div>

                    {/* Tags Option - Pro */}
                    <div className="gw-option-card gw-option-card--pro">
                        <div className="gw-option-card__title">
                            <TagsOutlined /> Tags
                            <span className="gw-pro-badge gw-pro-badge--small"><CrownOutlined /> Pro</span>
                        </div>
                        <div className="gw-option-card__desc">Generate relevant tags automatically</div>
                    </div>

                    {/* Image Option - Pro */}
                    <div className="gw-option-card gw-option-card--pro">
                        <div className="gw-option-card__title">
                            <PictureOutlined /> AI Image
                            <span className="gw-pro-badge gw-pro-badge--small"><CrownOutlined /> Pro</span>
                        </div>
                        <div className="gw-option-card__desc">Generate with DALL-E, Flux</div>
                    </div>

                    {/* Audio Option - Pro */}
                    <div className="gw-option-card gw-option-card--pro">
                        <div className="gw-option-card__title">
                            <AudioOutlined /> AI Audio
                            <span className="gw-pro-badge gw-pro-badge--small"><CrownOutlined /> Pro</span>
                        </div>
                        <div className="gw-option-card__desc">Text-to-speech narration</div>
                    </div>

                </div>

                {/* Subtle hint for multi-select */}
                <p style={{
                    fontSize: '13px',
                    color: 'var(--gw-gray-500)',
                    marginTop: '12px',
                    display: 'flex',
                    alignItems: 'center',
                    gap: '6px'
                }}>
                    <CrownOutlined style={{ color: 'var(--gw-warning)' }} />
                    Want to generate multiple fields at once? <a href="https://genwave.ai/plans" target="_blank" rel="noopener noreferrer" style={{ color: 'var(--gw-primary)' }}>Upgrade to Pro</a>
                </p>

                {/* Language Selector */}
                <div style={{ marginTop: '24px' }}>
                    <label style={{ display: 'block', marginBottom: '8px', fontWeight: '600' }}>
                        Language
                    </label>
                    <select
                        value={language}
                        onChange={(e) => setLanguage(e.target.value)}
                        style={{
                            padding: '10px 16px',
                            borderRadius: '8px',
                            border: '1px solid var(--gw-gray-300)',
                            fontSize: '14px',
                            minWidth: '200px'
                        }}
                    >
                        <option value="en">English</option>
                        <option value="he">עברית (Hebrew)</option>
                        <option value="ar">العربية (Arabic)</option>
                        <option value="es">Español (Spanish)</option>
                        <option value="fr">Français (French)</option>
                        <option value="de">Deutsch (German)</option>
                        <option value="it">Italiano (Italian)</option>
                        <option value="pt">Português (Portuguese)</option>
                        <option value="ru">Русский (Russian)</option>
                        <option value="zh">中文 (Chinese)</option>
                        <option value="ja">日本語 (Japanese)</option>
                        <option value="ko">한국어 (Korean)</option>
                        <option value="nl">Nederlands (Dutch)</option>
                        <option value="pl">Polski (Polish)</option>
                        <option value="tr">Türkçe (Turkish)</option>
                        <option value="th">ไทย (Thai)</option>
                        <option value="vi">Tiếng Việt (Vietnamese)</option>
                        <option value="hi">हिन्दी (Hindi)</option>
                        <option value="id">Bahasa Indonesia</option>
                        <option value="uk">Українська (Ukrainian)</option>
                        <option value="el">Ελληνικά (Greek)</option>
                        <option value="sv">Svenska (Swedish)</option>
                        <option value="da">Dansk (Danish)</option>
                        <option value="no">Norsk (Norwegian)</option>
                        <option value="fi">Suomi (Finnish)</option>
                        <option value="cs">Čeština (Czech)</option>
                        <option value="hu">Magyar (Hungarian)</option>
                        <option value="ro">Română (Romanian)</option>
                    </select>
                </div>

                {/* Custom Instructions */}
                <div style={{ marginTop: '24px' }}>
                    <label style={{ display: 'block', marginBottom: '8px', fontWeight: '600' }}>
                        <EditOutlined style={{ marginRight: '6px' }} />
                        Custom Instructions (Optional)
                    </label>
                    <textarea
                        value={customInstructions}
                        onChange={(e) => setCustomInstructions(e.target.value)}
                        placeholder="Enter specific instructions for the AI... (e.g., 'Write about the health benefits of this product' or 'Focus on eco-friendly features')"
                        maxLength={1000}
                        style={{
                            width: '100%',
                            padding: '12px 16px',
                            borderRadius: '8px',
                            border: '1px solid var(--gw-gray-300)',
                            fontSize: '14px',
                            minHeight: '100px',
                            resize: 'vertical',
                            fontFamily: 'inherit'
                        }}
                    />
                    <div style={{
                        display: 'flex',
                        justifyContent: 'space-between',
                        marginTop: '6px',
                        fontSize: '12px',
                        color: 'var(--gw-gray-500)'
                    }}>
                        <span>Tell the AI what to write about or how to write it</span>
                        <span>{customInstructions.length}/1000</span>
                    </div>
                </div>
            </div>

            {/* Generate Button */}
            <div className="gw-section" style={{ display: 'flex', alignItems: 'center', gap: '16px' }}>
                <button
                    className="gw-btn gw-btn--primary"
                    onClick={handleGenerate}
                    disabled={!selectedItem || generating}
                    style={{ padding: '14px 28px', fontSize: '16px' }}
                >
                    {generating ? (
                        <>
                            <LoadingOutlined spin /> Generating...
                        </>
                    ) : (
                        <>
                            <ThunderboltOutlined /> Generate Content
                        </>
                    )}
                </button>

                {selectedItem && (
                    <span style={{ color: 'var(--gw-gray-500)' }}>
                        Selected: <strong>{selectedItem.title || selectedItem.name}</strong>
                    </span>
                )}
            </div>

            {/* Generating Indicator */}
            {generating && (
                <div className="gw-section" style={{
                    background: 'linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(168, 85, 247, 0.1) 100%)',
                    border: '2px solid var(--gw-primary)',
                    textAlign: 'center',
                    padding: '32px'
                }}>
                    <div style={{ fontSize: '48px', marginBottom: '16px' }}>
                        <LoadingOutlined spin style={{ color: 'var(--gw-primary)' }} />
                    </div>
                    <h3 style={{ color: 'var(--gw-primary)', margin: '0 0 8px 0' }}>
                        Generating Content...
                    </h3>
                    <p style={{ color: 'var(--gw-gray-500)', margin: 0 }}>
                        AI is creating {generatedField === 'title' ? 'a title' : generatedField === 'shortDescription' ? 'a short description' : 'content'} for "{selectedItem?.title || selectedItem?.name}"
                    </p>
                </div>
            )}

            {/* Result */}
            {result && !generating && (
                <div className="gw-section" style={{
                    background: result.success ? 'rgba(16, 185, 129, 0.05)' : 'rgba(239, 68, 68, 0.1)',
                    border: `2px solid ${result.success ? 'var(--gw-success)' : 'var(--gw-danger)'}`
                }}>
                    {/* Header */}
                    <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '16px' }}>
                        <h3 className="gw-section__title" style={{
                            color: result.success ? 'var(--gw-success)' : 'var(--gw-danger)',
                            margin: 0
                        }}>
                            {result.success ? <CheckCircleOutlined /> : <CloseCircleOutlined />}
                            {' '}{result.message}
                        </h3>
                        {result.applied && (
                            <span style={{
                                background: 'var(--gw-success)',
                                color: 'white',
                                padding: '4px 12px',
                                borderRadius: '16px',
                                fontSize: '12px',
                                fontWeight: '600'
                            }}>
                                ✓ Applied to {contentType === 'products' ? 'Product' : 'Post'}
                            </span>
                        )}
                    </div>

                    {/* Token Usage */}
                    {result.success && result.tokenUsage && (
                        <div style={{
                            display: 'flex',
                            gap: '24px',
                            padding: '12px 16px',
                            background: 'rgba(99, 102, 241, 0.1)',
                            borderRadius: '8px',
                            marginBottom: '16px',
                            fontSize: '13px',
                            flexWrap: 'wrap'
                        }}>
                            <span><strong>Estimated:</strong> {result.tokenUsage.estimated?.toFixed(6)} tokens</span>
                            <span style={{ color: 'var(--gw-success)' }}><strong>Charged:</strong> {result.tokenUsage.charged?.toFixed(6)} tokens</span>
                            {result.tokenUsage.returned > 0 && (
                                <span style={{ color: 'var(--gw-primary)' }}><strong>Returned:</strong> {result.tokenUsage.returned?.toFixed(6)} tokens</span>
                            )}
                            <span style={{ marginLeft: 'auto', color: 'var(--gw-primary)', fontWeight: '600' }}>
                                <strong>Balance:</strong> {result.tokenUsage.balance?.toFixed(2)} tokens
                            </span>
                        </div>
                    )}

                    {/* Generated Content */}
                    {generatedContent && (
                        <>
                            <div style={{
                                background: 'white',
                                padding: '20px',
                                borderRadius: '8px',
                                border: '1px solid var(--gw-gray-200)',
                                maxHeight: '400px',
                                overflowY: 'auto'
                            }}>
                                <div style={{
                                    fontSize: '11px',
                                    textTransform: 'uppercase',
                                    color: 'var(--gw-gray-500)',
                                    marginBottom: '12px',
                                    fontWeight: '600'
                                }}>
                                    Generated {generatedField === 'title' ? 'Title' : generatedField === 'shortDescription' ? 'Short Description' : 'Description'}:
                                </div>
                                {generatedField === 'title' ? (
                                    <div style={{ fontSize: '18px', fontWeight: '600' }}>
                                        {generatedContent}
                                    </div>
                                ) : (
                                    <div
                                        className="gw-generated-content"
                                        dangerouslySetInnerHTML={{ __html: generatedContent }}
                                        style={{ lineHeight: '1.7' }}
                                    />
                                )}
                            </div>

                            {/* Action Buttons */}
                            <div style={{ display: 'flex', gap: '12px', marginTop: '16px', flexWrap: 'wrap' }}>
                                {!result.applied && (
                                    <>
                                        <button
                                            className="gw-btn gw-btn--primary"
                                            onClick={handleApply}
                                            disabled={applying}
                                            style={{ padding: '12px 24px' }}
                                        >
                                            {applying ? (
                                                <><LoadingOutlined spin /> Applying...</>
                                            ) : (
                                                <><SaveOutlined /> Apply to {contentType === 'products' ? 'Product' : 'Post'}</>
                                            )}
                                        </button>
                                        <button
                                            className="gw-btn gw-btn--secondary"
                                            onClick={copyToClipboard}
                                            style={{ padding: '12px 24px' }}
                                        >
                                            <CopyOutlined /> Copy to Clipboard
                                        </button>
                                        <button
                                            className="gw-btn gw-btn--secondary"
                                            onClick={handleGenerate}
                                            style={{ padding: '12px 24px' }}
                                        >
                                            <ThunderboltOutlined /> Regenerate
                                        </button>
                                    </>
                                )}
                                <a
                                    href={`${window.location.origin}/wp-admin/post.php?post=${selectedItem?.id}&action=edit`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="gw-btn gw-btn--secondary"
                                    style={{ padding: '12px 24px', textDecoration: 'none' }}
                                >
                                    <EditOutlined /> View {contentType === 'products' ? 'Product' : 'Post'}
                                </a>
                            </div>
                        </>
                    )}
                </div>
            )}

            {/* Pro Features Banner */}
            <div className="gw-upgrade-banner" style={{ marginTop: '24px' }}>
                <div className="gw-upgrade-banner__content">
                    <h3>Want More Power?</h3>
                    <p>Upgrade to Pro for bulk generation, AI images, and more</p>
                    <div className="gw-upgrade-banner__features">
                        <span className="gw-upgrade-banner__feature">
                            <RocketOutlined /> Bulk Generate 100+ items
                        </span>
                        <span className="gw-upgrade-banner__feature">
                            <PictureOutlined /> AI Image Generation
                        </span>
                        <span className="gw-upgrade-banner__feature">
                            <AudioOutlined /> Text-to-Speech
                        </span>
                    </div>
                </div>
                <a
                    href="https://genwave.ai/plans"
                    target="_blank"
                    rel="noopener noreferrer"
                    className="gw-upgrade-banner__btn"
                >
                    <CrownOutlined /> Upgrade Now
                </a>
            </div>
        </div>
    );
};

export default Generate;
