import React, { useState, useEffect } from 'react';
import ReactDOM from 'react-dom';

const ContentGeneratorPopup = ({ postId, postTitle, postContent, postType, onClose }) => {
    const [selectedOptions, setSelectedOptions] = useState([]);
    const [isGenerating, setIsGenerating] = useState(false);
    const [generatedContent, setGeneratedContent] = useState({});
    const [error, setError] = useState(null);
    const [currentStep, setCurrentStep] = useState('select'); // select, configure, result
    const [instructions, setInstructions] = useState({});
    const [lengthSettings, setLengthSettings] = useState({
        title: 60,
        content: 500,
        excerpt: 150,
        keywords: 10,
        short_description: 100,
        description: 300
    });
    const [selectedModel, setSelectedModel] = useState('gpt-3.5-turbo');
    const [selectedProvider, setSelectedProvider] = useState('OpenAI');
    const [calculatedTokens, setCalculatedTokens] = useState(null);
    const [isCalculating, setIsCalculating] = useState(false);

    // אופציות זמינות לפי סוג התוכן
    const getAvailableOptions = () => {
        if (postType === 'product') {
            return [
                { key: 'title', label: 'Product Title', icon: '📝', description: 'Generate SEO-optimized product title' },
                { key: 'description', label: 'Full Description', icon: '📄', description: 'Create detailed product description' },
                { key: 'short_description', label: 'Short Description', icon: '📋', description: 'Generate concise product summary' },
                { key: 'keywords', label: 'SEO Keywords', icon: '🏷️', description: 'Generate relevant keywords' }
            ];
        } else {
            return [
                { key: 'title', label: 'Post Title', icon: '📝', description: 'Generate engaging post title' },
                { key: 'content', label: 'Post Content', icon: '📄', description: 'Create full blog post content' },
                { key: 'excerpt', label: 'Post Excerpt', icon: '📋', description: 'Generate post summary' },
                { key: 'keywords', label: 'SEO Keywords', icon: '🏷️', description: 'Generate relevant keywords' }
            ];
        }
    };

    const availableOptions = getAvailableOptions();

    // Toggle option selection
    const toggleOption = (optionKey) => {
        setSelectedOptions(prev => {
            if (prev.includes(optionKey)) {
                return prev.filter(key => key !== optionKey);
            }
            return [...prev, optionKey];
        });
    };

    // Calculate tokens
    const calculateTokens = async () => {
        if (selectedOptions.length === 0) {
            setError('Please select at least one option');
            return;
        }

        setIsCalculating(true);
        setError(null);

        try {
            const response = await fetch(`${window.aiSettings.ajaxurl}?action=ai_calculate_tokens_instant`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    post_id: postId,
                    selected_options: JSON.stringify(selectedOptions),
                    length_settings: JSON.stringify(lengthSettings),
                    instructions: JSON.stringify(instructions),
                    model: selectedModel,
                    provider: selectedProvider,
                    nonce: window.aiSettings.nonce
                })
            });

            const data = await response.json();

            if (data.success) {
                setCalculatedTokens(data.data);
                setCurrentStep('configure');
            } else {
                setError(data.data?.message || 'Failed to calculate tokens');
            }
        } catch (err) {
            setError('Network error: ' + err.message);
        } finally {
            setIsCalculating(false);
        }
    };

    // Generate content
    const generateContent = async () => {
        setIsGenerating(true);
        setError(null);

        try {
            const response = await fetch(`${window.aiSettings.ajaxurl}?action=ai_generate_instant`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    post_id: postId,
                    post_title: postTitle,
                    post_content: postContent,
                    post_type: postType,
                    selected_options: JSON.stringify(selectedOptions),
                    length_settings: JSON.stringify(lengthSettings),
                    instructions: JSON.stringify(instructions),
                    model: selectedModel,
                    provider: selectedProvider,
                    nonce: window.aiSettings.nonce
                })
            });

            const data = await response.json();

            if (data.success) {
                setGeneratedContent(data.data.content);
                setCurrentStep('result');
            } else {
                setError(data.data?.message || 'Failed to generate content');
            }
        } catch (err) {
            setError('Network error: ' + err.message);
        } finally {
            setIsGenerating(false);
        }
    };

    // Apply generated content to editor
    const applyContent = (key, value) => {
        // For Gutenberg editor
        if (window.wp && window.wp.data && window.wp.data.select('core/editor')) {
            const editor = window.wp.data.dispatch('core/editor');
            
            if (key === 'title') {
                editor.editPost({ title: value });
            } else if (key === 'content') {
                editor.resetBlocks(window.wp.blocks.parse(value));
            } else if (key === 'excerpt') {
                editor.editPost({ excerpt: value });
            }
        } 
        // For Classic editor
        else {
            if (key === 'title' && document.getElementById('title')) {
                document.getElementById('title').value = value;
            } else if (key === 'content' && window.tinyMCE && window.tinyMCE.activeEditor) {
                window.tinyMCE.activeEditor.setContent(value);
            } else if (key === 'excerpt' && document.getElementById('excerpt')) {
                document.getElementById('excerpt').value = value;
            }
        }

        // Show success message
        const message = document.createElement('div');
        message.className = 'ai-success-message';
        message.textContent = `${key} applied successfully!`;
        document.body.appendChild(message);
        setTimeout(() => message.remove(), 3000);
    };

    // Render step content
    const renderStepContent = () => {
        switch (currentStep) {
            case 'select':
                return (
                    <div className="ai-step-select">
                        <h3>Select Content to Generate</h3>
                        <div className="ai-options-grid">
                            {availableOptions.map(option => (
                                <div
                                    key={option.key}
                                    className={`ai-option-card ${selectedOptions.includes(option.key) ? 'selected' : ''}`}
                                    onClick={() => toggleOption(option.key)}
                                >
                                    <div className="ai-option-icon">{option.icon}</div>
                                    <div className="ai-option-content">
                                        <h4>{option.label}</h4>
                                        <p>{option.description}</p>
                                    </div>
                                    <div className="ai-option-checkbox">
                                        <input
                                            type="checkbox"
                                            checked={selectedOptions.includes(option.key)}
                                            onChange={() => {}}
                                        />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                );

            case 'configure':
                return (
                    <div className="ai-step-configure">
                        <h3>Configure Generation Settings</h3>
                        <div className="ai-config-sections">
                            {selectedOptions.map(optionKey => {
                                const option = availableOptions.find(o => o.key === optionKey);
                                return (
                                    <div key={optionKey} className="ai-config-section">
                                        <h4>{option.icon} {option.label}</h4>
                                        <div className="ai-config-field">
                                            <label>Length (words):</label>
                                            <input
                                                type="range"
                                                min="10"
                                                max="1000"
                                                value={lengthSettings[optionKey] || 100}
                                                onChange={(e) => setLengthSettings({
                                                    ...lengthSettings,
                                                    [optionKey]: parseInt(e.target.value)
                                                })}
                                            />
                                            <span>{lengthSettings[optionKey] || 100} words</span>
                                        </div>
                                        <div className="ai-config-field">
                                            <label>Special Instructions:</label>
                                            <textarea
                                                placeholder="Add any specific requirements..."
                                                value={instructions[optionKey] || ''}
                                                onChange={(e) => setInstructions({
                                                    ...instructions,
                                                    [optionKey]: e.target.value
                                                })}
                                                maxLength="300"
                                            />
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                        
                        {calculatedTokens && (
                            <div className="ai-token-info">
                                <div className="token-summary">
                                    <span>Estimated Tokens: {calculatedTokens.total_tokens}</span>
                                    <span>Estimated Cost: ${calculatedTokens.total_cost}</span>
                                </div>
                            </div>
                        )}
                    </div>
                );

            case 'result':
                return (
                    <div className="ai-step-result">
                        <h3>Generated Content</h3>
                        <div className="ai-results-container">
                            {Object.entries(generatedContent).map(([key, value]) => {
                                const option = availableOptions.find(o => o.key === key);
                                return (
                                    <div key={key} className="ai-result-item">
                                        <div className="ai-result-header">
                                            <h4>{option?.icon} {option?.label}</h4>
                                            <button
                                                className="ai-apply-btn"
                                                onClick={() => applyContent(key, value)}
                                            >
                                                Apply to Editor
                                            </button>
                                        </div>
                                        <div className="ai-result-content">
                                            <pre>{value}</pre>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                );

            default:
                return null;
        }
    };

    return (
        <div className="ai-generator-popup-overlay" onClick={onClose}>
            <div className="ai-generator-popup" onClick={(e) => e.stopPropagation()}>
                <div className="ai-popup-header">
                    <h2>🤖 AI Content Generator</h2>
                    <button className="ai-close-btn" onClick={onClose}>×</button>
                </div>

                <div className="ai-popup-body">
                    {error && (
                        <div className="ai-error-message">
                            ⚠️ {error}
                        </div>
                    )}

                    {renderStepContent()}
                </div>

                <div className="ai-popup-footer">
                    {currentStep === 'select' && (
                        <button
                            className="ai-btn ai-btn-primary"
                            onClick={calculateTokens}
                            disabled={selectedOptions.length === 0 || isCalculating}
                        >
                            {isCalculating ? 'Calculating...' : 'Next: Configure Settings'}
                        </button>
                    )}

                    {currentStep === 'configure' && (
                        <>
                            <button
                                className="ai-btn ai-btn-secondary"
                                onClick={() => setCurrentStep('select')}
                            >
                                Back
                            </button>
                            <button
                                className="ai-btn ai-btn-primary"
                                onClick={generateContent}
                                disabled={isGenerating}
                            >
                                {isGenerating ? (
                                    <>
                                        <span className="ai-spinner"></span>
                                        Generating...
                                    </>
                                ) : (
                                    '🚀 Generate Content'
                                )}
                            </button>
                        </>
                    )}

                    {currentStep === 'result' && (
                        <>
                            <button
                                className="ai-btn ai-btn-secondary"
                                onClick={() => {
                                    setCurrentStep('select');
                                    setGeneratedContent({});
                                }}
                            >
                                Generate More
                            </button>
                            <button
                                className="ai-btn ai-btn-primary"
                                onClick={onClose}
                            >
                                Done
                            </button>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
};

// Export for use in WordPress
window.AIContentGeneratorPopup = ContentGeneratorPopup;

export default ContentGeneratorPopup;