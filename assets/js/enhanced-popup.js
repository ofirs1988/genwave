/**
 * Enhanced AI Content Generator - Vanilla JS Implementation
 * Multi-step wizard with full feature set
 */

(function() {
    'use strict';

    // Configuration constants
    const STEPS = {
        SELECT: 'select',
        CONFIGURE: 'configure', 
        RESULT: 'result'
    };

    const DEFAULT_LENGTHS = {
        title: 60,
        content: 300,
        excerpt: 150,
        keywords: 10,
        description: 160,
        tags: 20
    };

    const CONTENT_OPTIONS = {
        title: { label: 'Title', description: 'Generate an engaging title' },
        content: { label: 'Content', description: 'Generate main content body' },
        excerpt: { label: 'Excerpt', description: 'Generate a summary excerpt' },
        description: { label: 'Meta Description', description: 'Generate SEO meta description' },
        keywords: { label: 'Keywords', description: 'Generate relevant keywords' },
        tags: { label: 'Tags', description: 'Generate relevant tags' }
    };

    function createEnhancedPopup(props) {
        let currentStep = STEPS.SELECT;
        let selectedOptions = [];
        let lengthSettings = { ...DEFAULT_LENGTHS };
        let instructions = {};
        let estimatedCost = 0;
        let isCalculating = false;

        // Create overlay
        const overlay = document.createElement('div');
        overlay.className = 'ai-generator-overlay';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100000;
            opacity: 0;
            transition: opacity 0.3s ease;
        `;

        // Create popup content
        const popup = document.createElement('div');
        popup.className = 'ai-generator-popup';
        popup.style.cssText = `
            background-color: white;
            border-radius: 12px;
            padding: 0;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow: hidden;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.15);
            transform: scale(0.9);
            transition: transform 0.3s ease;
        `;

        overlay.appendChild(popup);

        // Render functions
        function renderHeader() {
            return `
                <div class="ai-generator-header" style="
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    padding: 20px 30px;
                    position: relative;
                ">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h2 style="margin: 0; font-size: 24px; font-weight: 600;">🤖 AI Content Generator</h2>
                            <p style="margin: 5px 0 0 0; opacity: 0.9; font-size: 14px;">${getStepDescription()}</p>
                        </div>
                        <button id="ai-close-btn" style="
                            background: rgba(255, 255, 255, 0.2);
                            border: none;
                            width: 32px;
                            height: 32px;
                            border-radius: 50%;
                            color: white;
                            font-size: 18px;
                            cursor: pointer;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            transition: background-color 0.2s;
                        ">×</button>
                    </div>
                    ${renderProgressBar()}
                </div>
            `;
        }

        function getStepDescription() {
            switch (currentStep) {
                case STEPS.SELECT: return 'Select content types to generate';
                case STEPS.CONFIGURE: return 'Configure generation settings';
                case STEPS.RESULT: return 'Generated content ready';
                default: return '';
            }
        }

        function renderProgressBar() {
            const steps = Object.values(STEPS);
            const currentIndex = steps.indexOf(currentStep);
            const progress = ((currentIndex + 1) / steps.length) * 100;
            
            return `
                <div style="margin-top: 20px;">
                    <div style="
                        background: rgba(255, 255, 255, 0.2);
                        height: 4px;
                        border-radius: 2px;
                        overflow: hidden;
                    ">
                        <div style="
                            background: rgba(255, 255, 255, 0.8);
                            height: 100%;
                            width: ${progress}%;
                            border-radius: 2px;
                            transition: width 0.3s ease;
                        "></div>
                    </div>
                </div>
            `;
        }

        function renderSelectStep() {
            return `
                <div class="ai-generator-content" style="padding: 30px;">
                    <div style="margin-bottom: 25px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                        <h4 style="margin: 0 0 10px 0; color: #495057;">Post Information</h4>
                        <div style="font-size: 14px; color: #6c757d; line-height: 1.6;">
                            <div><strong>Title:</strong> ${props.postTitle || 'Untitled'}</div>
                            <div><strong>Type:</strong> ${props.postType}</div>
                            <div><strong>ID:</strong> ${props.postId}</div>
                        </div>
                    </div>

                    <h4 style="margin: 0 0 20px 0; color: #333;">Select Content to Generate:</h4>
                    <div class="content-options" style="
                        display: grid;
                        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                        gap: 15px;
                        margin-bottom: 30px;
                    ">
                        ${Object.entries(CONTENT_OPTIONS).map(([key, option]) => `
                            <label class="content-option" data-option="${key}" style="
                                display: flex;
                                align-items: center;
                                padding: 15px;
                                border: 2px solid #e9ecef;
                                border-radius: 8px;
                                cursor: pointer;
                                transition: all 0.2s ease;
                                background: white;
                            ">
                                <input type="checkbox" value="${key}" style="margin-right: 12px; transform: scale(1.2);">
                                <div>
                                    <div style="font-weight: 600; color: #333; margin-bottom: 4px;">${option.label}</div>
                                    <div style="font-size: 13px; color: #6c757d;">${option.description}</div>
                                </div>
                            </label>
                        `).join('')}
                    </div>

                    <div class="cost-estimate" style="
                        padding: 15px;
                        background: #e8f4f8;
                        border: 1px solid #bee5eb;
                        border-radius: 8px;
                        margin-bottom: 20px;
                        ${selectedOptions.length === 0 ? 'display: none;' : ''}
                    ">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #0c5460; font-weight: 500;">💰 Estimated Cost:</span>
                            <span id="cost-display" style="color: #0c5460; font-weight: bold;">
                                ${isCalculating ? '⏳ Calculating...' : `$${estimatedCost.toFixed(4)}`}
                            </span>
                        </div>
                    </div>

                    <div style="display: flex; gap: 15px; justify-content: flex-end;">
                        <button id="ai-cancel-btn" style="
                            padding: 12px 24px;
                            border: 2px solid #6c757d;
                            background: white;
                            border-radius: 8px;
                            cursor: pointer;
                            color: #6c757d;
                            font-weight: 500;
                            transition: all 0.2s ease;
                        ">Cancel</button>
                        <button id="ai-next-btn" style="
                            padding: 12px 24px;
                            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                            color: white;
                            border: none;
                            border-radius: 8px;
                            cursor: pointer;
                            font-weight: 500;
                            transition: all 0.2s ease;
                            ${selectedOptions.length === 0 ? 'opacity: 0.5; cursor: not-allowed;' : ''}
                        " ${selectedOptions.length === 0 ? 'disabled' : ''}>Next Step →</button>
                    </div>
                </div>
            `;
        }

        function renderConfigureStep() {
            return `
                <div class="ai-generator-content" style="padding: 30px;">
                    <h4 style="margin: 0 0 20px 0; color: #333;">Configure Generation Settings</h4>
                    
                    <div class="settings-grid" style="display: grid; gap: 25px; margin-bottom: 30px;">
                        ${selectedOptions.map(option => `
                            <div class="setting-group" style="
                                border: 1px solid #e9ecef;
                                border-radius: 8px;
                                padding: 20px;
                                background: #fafafa;
                            ">
                                <h5 style="margin: 0 0 15px 0; color: #495057; display: flex; align-items: center;">
                                    <span style="margin-right: 8px;">${getOptionIcon(option)}</span>
                                    ${CONTENT_OPTIONS[option].label}
                                </h5>
                                
                                <div style="margin-bottom: 15px;">
                                    <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #495057;">Length (${option === 'keywords' || option === 'tags' ? 'count' : 'characters'}):</label>
                                    <input type="number" 
                                           id="length-${option}" 
                                           value="${lengthSettings[option]}" 
                                           min="10" 
                                           max="${getMaxLength(option)}" 
                                           style="
                                               width: 100%;
                                               padding: 10px;
                                               border: 1px solid #ced4da;
                                               border-radius: 6px;
                                               font-size: 14px;
                                           ">
                                </div>
                                
                                <div>
                                    <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #495057;">Special Instructions (optional):</label>
                                    <textarea id="instruction-${option}" 
                                              placeholder="e.g., Make it professional, include technical terms..." 
                                              style="
                                                  width: 100%;
                                                  padding: 10px;
                                                  border: 1px solid #ced4da;
                                                  border-radius: 6px;
                                                  font-size: 14px;
                                                  resize: vertical;
                                                  min-height: 60px;
                                              ">${instructions[option] || ''}</textarea>
                                </div>
                            </div>
                        `).join('')}
                    </div>

                    <div class="cost-estimate" style="
                        padding: 15px;
                        background: #e8f4f8;
                        border: 1px solid #bee5eb;
                        border-radius: 8px;
                        margin-bottom: 20px;
                    ">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #0c5460; font-weight: 500;">💰 Final Estimated Cost:</span>
                            <span id="final-cost-display" style="color: #0c5460; font-weight: bold;">
                                ${isCalculating ? '⏳ Calculating...' : `$${estimatedCost.toFixed(4)}`}
                            </span>
                        </div>
                    </div>

                    <div style="display: flex; gap: 15px; justify-content: space-between;">
                        <button id="ai-back-btn" style="
                            padding: 12px 24px;
                            border: 2px solid #6c757d;
                            background: white;
                            border-radius: 8px;
                            cursor: pointer;
                            color: #6c757d;
                            font-weight: 500;
                            transition: all 0.2s ease;
                        ">← Back</button>
                        <button id="ai-generate-btn" style="
                            padding: 12px 32px;
                            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
                            color: white;
                            border: none;
                            border-radius: 8px;
                            cursor: pointer;
                            font-weight: 500;
                            transition: all 0.2s ease;
                            font-size: 16px;
                        ">🚀 Generate Content</button>
                    </div>
                </div>
            `;
        }

        function renderResultStep(results) {
            return `
                <div class="ai-generator-content" style="padding: 30px;">
                    <div style="text-align: center; margin-bottom: 30px;">
                        <div style="font-size: 48px; margin-bottom: 15px;">🎉</div>
                        <h3 style="margin: 0; color: #28a745;">Content Generated Successfully!</h3>
                    </div>

                    <div class="results-container" style="max-height: 400px; overflow-y: auto; margin-bottom: 30px;">
                        ${Object.entries(results).map(([type, content]) => `
                            <div class="result-item" style="
                                margin-bottom: 25px;
                                border: 1px solid #e9ecef;
                                border-radius: 8px;
                                overflow: hidden;
                            ">
                                <div style="
                                    background: #f8f9fa;
                                    padding: 15px;
                                    border-bottom: 1px solid #e9ecef;
                                    display: flex;
                                    justify-content: space-between;
                                    align-items: center;
                                ">
                                    <h5 style="margin: 0; color: #495057; display: flex; align-items: center;">
                                        <span style="margin-right: 8px;">${getOptionIcon(type)}</span>
                                        ${CONTENT_OPTIONS[type].label}
                                    </h5>
                                    <button class="apply-btn" data-type="${type}" style="
                                        padding: 8px 16px;
                                        background: #007bff;
                                        color: white;
                                        border: none;
                                        border-radius: 6px;
                                        cursor: pointer;
                                        font-size: 13px;
                                        font-weight: 500;
                                        transition: background-color 0.2s ease;
                                    ">Apply to Editor</button>
                                </div>
                                <div style="padding: 20px;">
                                    <div style="
                                        background: white;
                                        border: 1px solid #e9ecef;
                                        border-radius: 6px;
                                        padding: 15px;
                                        font-size: 14px;
                                        line-height: 1.6;
                                        color: #495057;
                                        white-space: pre-wrap;
                                    ">${content}</div>
                                </div>
                            </div>
                        `).join('')}
                    </div>

                    <div style="display: flex; gap: 15px; justify-content: space-between;">
                        <button id="ai-regenerate-btn" style="
                            padding: 12px 24px;
                            border: 2px solid #17a2b8;
                            background: white;
                            border-radius: 8px;
                            cursor: pointer;
                            color: #17a2b8;
                            font-weight: 500;
                            transition: all 0.2s ease;
                        ">🔄 Generate Again</button>
                        <button id="ai-done-btn" style="
                            padding: 12px 32px;
                            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
                            color: white;
                            border: none;
                            border-radius: 8px;
                            cursor: pointer;
                            font-weight: 500;
                            transition: all 0.2s ease;
                        ">✅ Done</button>
                    </div>
                </div>
            `;
        }

        function getOptionIcon(option) {
            const icons = {
                title: '📝',
                content: '📄', 
                excerpt: '📋',
                description: '🔍',
                keywords: '🏷️',
                tags: '📌'
            };
            return icons[option] || '📝';
        }

        function getMaxLength(option) {
            const maxLengths = {
                title: 200,
                content: 2000,
                excerpt: 300,
                description: 300,
                keywords: 50,
                tags: 50
            };
            return maxLengths[option] || 500;
        }

        function render() {
            let content;
            switch (currentStep) {
                case STEPS.SELECT:
                    content = renderSelectStep();
                    break;
                case STEPS.CONFIGURE:
                    content = renderConfigureStep();
                    break;
                case STEPS.RESULT:
                    content = renderResultStep(JSON.parse(popup.dataset.results || '{}'));
                    break;
                default:
                    content = renderSelectStep();
            }

            popup.innerHTML = renderHeader() + content;
            attachEventListeners();
        }

        function attachEventListeners() {
            // Close button
            const closeBtn = popup.querySelector('#ai-close-btn');
            if (closeBtn) closeBtn.addEventListener('click', closePopup);

            // Cancel button  
            const cancelBtn = popup.querySelector('#ai-cancel-btn');
            if (cancelBtn) cancelBtn.addEventListener('click', closePopup);

            // Step-specific event listeners
            if (currentStep === STEPS.SELECT) {
                attachSelectStepListeners();
            } else if (currentStep === STEPS.CONFIGURE) {
                attachConfigureStepListeners();
            } else if (currentStep === STEPS.RESULT) {
                attachResultStepListeners();
            }

            // Overlay click to close
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    closePopup();
                }
            });
        }

        function attachSelectStepListeners() {
            // Option selection
            const checkboxes = popup.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    updateSelectedOptions();
                    updateCostEstimate();
                });
            });

            // Option labels for better UX
            const labels = popup.querySelectorAll('.content-option');
            labels.forEach(label => {
                label.addEventListener('click', function(e) {
                    if (e.target.tagName !== 'INPUT') {
                        const checkbox = label.querySelector('input[type="checkbox"]');
                        checkbox.checked = !checkbox.checked;
                        checkbox.dispatchEvent(new Event('change'));
                    }
                });
            });

            // Next button
            const nextBtn = popup.querySelector('#ai-next-btn');
            if (nextBtn) {
                nextBtn.addEventListener('click', function() {
                    if (selectedOptions.length > 0) {
                        currentStep = STEPS.CONFIGURE;
                        render();
                    }
                });
            }
        }

        function attachConfigureStepListeners() {
            // Back button
            const backBtn = popup.querySelector('#ai-back-btn');
            if (backBtn) {
                backBtn.addEventListener('click', function() {
                    currentStep = STEPS.SELECT;
                    render();
                });
            }

            // Length inputs
            selectedOptions.forEach(option => {
                const lengthInput = popup.querySelector(`#length-${option}`);
                if (lengthInput) {
                    lengthInput.addEventListener('input', function() {
                        lengthSettings[option] = parseInt(this.value) || DEFAULT_LENGTHS[option];
                        updateCostEstimate();
                    });
                }

                const instructionInput = popup.querySelector(`#instruction-${option}`);
                if (instructionInput) {
                    instructionInput.addEventListener('input', function() {
                        instructions[option] = this.value;
                    });
                }
            });

            // Generate button
            const generateBtn = popup.querySelector('#ai-generate-btn');
            if (generateBtn) {
                generateBtn.addEventListener('click', generateContent);
            }
        }

        function attachResultStepListeners() {
            // Apply buttons
            const applyBtns = popup.querySelectorAll('.apply-btn');
            applyBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const type = this.dataset.type;
                    const content = this.closest('.result-item').querySelector('div[style*="white-space: pre-wrap"]').textContent;
                    applyToEditor(type, content);
                    
                    // Visual feedback
                    const originalText = this.textContent;
                    this.textContent = '✅ Applied!';
                    this.style.backgroundColor = '#28a745';
                    setTimeout(() => {
                        this.textContent = originalText;
                        this.style.backgroundColor = '#007bff';
                    }, 2000);
                });
            });

            // Regenerate button
            const regenerateBtn = popup.querySelector('#ai-regenerate-btn');
            if (regenerateBtn) {
                regenerateBtn.addEventListener('click', function() {
                    currentStep = STEPS.CONFIGURE;
                    render();
                });
            }

            // Done button
            const doneBtn = popup.querySelector('#ai-done-btn');
            if (doneBtn) {
                doneBtn.addEventListener('click', closePopup);
            }
        }

        function updateSelectedOptions() {
            const checkboxes = popup.querySelectorAll('input[type="checkbox"]:checked');
            selectedOptions = Array.from(checkboxes).map(cb => cb.value);
            
            // Update UI
            updateSelectionUI();
            updateNextButton();
        }

        function updateSelectionUI() {
            const labels = popup.querySelectorAll('.content-option');
            labels.forEach(label => {
                const option = label.dataset.option;
                const isSelected = selectedOptions.includes(option);
                
                if (isSelected) {
                    label.style.borderColor = '#667eea';
                    label.style.background = 'linear-gradient(135deg, #f8f9ff 0%, #f1f3ff 100%)';
                    label.style.transform = 'translateY(-2px)';
                    label.style.boxShadow = '0 4px 12px rgba(102, 126, 234, 0.15)';
                } else {
                    label.style.borderColor = '#e9ecef';
                    label.style.background = 'white';
                    label.style.transform = 'none';
                    label.style.boxShadow = 'none';
                }
            });

            // Show/hide cost estimate
            const costEstimate = popup.querySelector('.cost-estimate');
            if (costEstimate) {
                costEstimate.style.display = selectedOptions.length > 0 ? 'block' : 'none';
            }
        }

        function updateNextButton() {
            const nextBtn = popup.querySelector('#ai-next-btn');
            if (nextBtn) {
                if (selectedOptions.length > 0) {
                    nextBtn.disabled = false;
                    nextBtn.style.opacity = '1';
                    nextBtn.style.cursor = 'pointer';
                } else {
                    nextBtn.disabled = true;
                    nextBtn.style.opacity = '0.5';
                    nextBtn.style.cursor = 'not-allowed';
                }
            }
        }

        async function updateCostEstimate() {
            if (selectedOptions.length === 0) {
                estimatedCost = 0;
                return;
            }

            isCalculating = true;
            updateCostDisplay();

            try {
                const formData = new FormData();
                formData.append('action', 'ai_calculate_tokens');
                formData.append('post_title', props.postTitle);
                formData.append('post_content', props.postContent);
                formData.append('selected_options', JSON.stringify(selectedOptions));
                formData.append('length_settings', JSON.stringify(lengthSettings));
                formData.append('instructions', JSON.stringify(instructions));
                formData.append('nonce', window.aiSettings ? window.aiSettings.nonce : '');

                const response = await fetch(window.aiSettings ? window.aiSettings.ajaxurl : '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                
                if (result.success) {
                    estimatedCost = parseFloat(result.data.total_cost) || 0;
                } else {
                    console.warn('Cost calculation failed:', result.data?.message);
                    estimatedCost = 0;
                }
            } catch (error) {
                console.error('Cost estimation error:', error);
                estimatedCost = 0;
            } finally {
                isCalculating = false;
                updateCostDisplay();
            }
        }

        function updateCostDisplay() {
            const displays = popup.querySelectorAll('#cost-display, #final-cost-display');
            displays.forEach(display => {
                if (display) {
                    display.textContent = isCalculating ? '⏳ Calculating...' : `$${estimatedCost.toFixed(4)}`;
                }
            });
        }

        async function generateContent() {
            const generateBtn = popup.querySelector('#ai-generate-btn');
            const originalText = generateBtn.innerHTML;

            // Show loading state
            generateBtn.innerHTML = '⏳ Starting Streaming...';
            generateBtn.disabled = true;
            generateBtn.style.opacity = '0.7';

            try {
                console.log('🚀 Starting LiteLLM streaming generation...');

                // Close the popup first since we'll show streaming UI
                closePopup();

                // Prepare data for LiteLLM streaming
                const requestData = {
                    request_id: 'popup-' + Date.now(),
                    posts: [{
                        id: props.postId,
                        title: props.postTitle,
                        content: props.postContent,
                        type: props.postType
                    }],
                    provider: 'openai',
                    model: 'gpt-4o',
                    instructions: selectedOptions.length > 0 ?
                        [`Generate ${selectedOptions.join(', ')} for this content.`] :
                        ['Generate appropriate content for this item.']
                };

                console.log('📤 Dispatching start-litellm-streaming event', requestData);

                // Dispatch streaming event
                document.dispatchEvent(new CustomEvent('start-litellm-streaming', {
                    detail: { requestData }
                }));

            } catch (error) {
                console.error('Streaming setup error:', error);
                alert('Failed to start streaming: ' + error.message);

                // Restore button state only if popup still exists
                if (document.body.contains(generateBtn)) {
                    generateBtn.innerHTML = originalText;
                    generateBtn.disabled = false;
                    generateBtn.style.opacity = '1';
                }
            }
        }

        function applyToEditor(type, content) {
            // Implementation depends on editor type
            const isGutenberg = typeof wp !== 'undefined' && wp.data;
            
            try {
                if (isGutenberg && wp.data.select('core/editor')) {
                    applyToGutenberg(type, content);
                } else {
                    applyToClassicEditor(type, content);
                }
            } catch (error) {
                console.error('Error applying content:', error);
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(content).then(() => {
                    alert('Content copied to clipboard!');
                }).catch(() => {
                    alert('Please manually copy the content.');
                });
            }
        }

        function applyToGutenberg(type, content) {
            const { dispatch } = wp.data;
            
            switch (type) {
                case 'title':
                    dispatch('core/editor').editPost({ title: content });
                    break;
                case 'content':
                    dispatch('core/editor').editPost({ content: content });
                    break;
                case 'excerpt':
                    dispatch('core/editor').editPost({ excerpt: content });
                    break;
                default:
                    // For other types, copy to clipboard
                    navigator.clipboard.writeText(content);
            }
        }

        function applyToClassicEditor(type, content) {
            switch (type) {
                case 'title':
                    const titleField = document.getElementById('title');
                    if (titleField) titleField.value = content;
                    break;
                case 'content':
                    if (window.tinyMCE && window.tinyMCE.activeEditor && !window.tinyMCE.activeEditor.isHidden()) {
                        window.tinyMCE.activeEditor.setContent(content);
                    } else {
                        const contentField = document.getElementById('content');
                        if (contentField) contentField.value = content;
                    }
                    break;
                case 'excerpt':
                    const excerptField = document.getElementById('excerpt');
                    if (excerptField) excerptField.value = content;
                    break;
                default:
                    // Copy to clipboard as fallback
                    navigator.clipboard.writeText(content);
            }
        }

        function closePopup() {
            // Fade out animation
            overlay.style.opacity = '0';
            popup.style.transform = 'scale(0.9)';
            
            setTimeout(() => {
                if (overlay.parentNode) {
                    overlay.parentNode.removeChild(overlay);
                }
            }, 300);
        }

        // Initialize
        render();
        
        // Fade in animation
        setTimeout(() => {
            overlay.style.opacity = '1';
            popup.style.transform = 'scale(1)';
        }, 10);

        return overlay;
    }

    // Export to global scope
    window.openEnhancedAIPopup = function(props) {
        const popup = createEnhancedPopup(props);
        document.body.appendChild(popup);
    };

    // Keep backward compatibility 
    window.openVanillaAIPopup = function(props) {
        const popup = createEnhancedPopup(props);
        document.body.appendChild(popup);
    };

})();