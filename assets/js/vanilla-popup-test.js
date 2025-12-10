/**
 * Vanilla JS popup test - no React dependencies
 */

(function() {
    'use strict';

    function createSimplePopup(props) {
        // Create overlay
        const overlay = document.createElement('div');
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
        `;

        // Create popup content
        const popup = document.createElement('div');
        popup.style.cssText = `
            background-color: white;
            border-radius: 12px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        `;

        popup.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="margin: 0; font-size: 20px;">🤖 AI Content Generator</h2>
                <button id="ai-close-btn" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #999;">×</button>
            </div>
            
            <div style="margin-bottom: 20px;">
                <p><strong>Post ID:</strong> ${props.postId}</p>
                <p><strong>Title:</strong> ${props.postTitle}</p>
                <p><strong>Type:</strong> ${props.postType}</p>
                <p><strong>Content Preview:</strong> ${props.postContent.substring(0, 100)}...</p>
            </div>

            <div style="border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                <h3 style="margin: 0 0 15px 0;">Select Content to Generate:</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" value="title" style="margin-right: 8px;"> Title
                    </label>
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" value="content" style="margin-right: 8px;"> Content
                    </label>
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" value="excerpt" style="margin-right: 8px;"> Excerpt
                    </label>
                    <label style="display: flex; align-items: center; cursor: pointer;">
                        <input type="checkbox" value="keywords" style="margin-right: 8px;"> Keywords
                    </label>
                </div>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button id="ai-cancel-btn" style="padding: 10px 20px; border: 1px solid #ddd; background: white; border-radius: 6px; cursor: pointer;">Cancel</button>
                <button id="ai-generate-btn" style="padding: 10px 20px; background: #1890ff; color: white; border: none; border-radius: 6px; cursor: pointer;">🚀 Generate Content</button>
            </div>
        `;

        overlay.appendChild(popup);

        // Add event listeners
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                closePopup();
            }
        });

        popup.querySelector('#ai-close-btn').addEventListener('click', closePopup);
        popup.querySelector('#ai-cancel-btn').addEventListener('click', closePopup);
        popup.querySelector('#ai-generate-btn').addEventListener('click', async function() {
            const checkboxes = popup.querySelectorAll('input[type="checkbox"]:checked');
            const selected = Array.from(checkboxes).map(cb => cb.value);
            
            if (selected.length === 0) {
                alert('Please select at least one option');
                return;
            }
            
            // Show loading
            const btn = this;
            const originalText = btn.innerHTML;
            btn.innerHTML = '⏳ Generating...';
            btn.disabled = true;
            
            try {
                // Call WordPress AJAX
                const formData = new FormData();
                formData.append('action', 'ai_generate_instant');
                formData.append('post_id', props.postId);
                formData.append('post_title', props.postTitle);
                formData.append('post_content', props.postContent);
                formData.append('post_type', props.postType);
                formData.append('selected_options', JSON.stringify(selected));
                formData.append('length_settings', JSON.stringify({
                    title: 60,
                    content: 300,
                    excerpt: 150,
                    keywords: 10
                }));
                formData.append('instructions', JSON.stringify({}));
                formData.append('model', 'gpt-3.5-turbo');
                formData.append('provider', 'OpenAI');
                formData.append('nonce', window.aiSettings ? window.aiSettings.nonce : '');

                const response = await fetch(window.aiSettings ? window.aiSettings.ajaxurl : '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                
                if (result.success) {
                    // Show results
                    const content = result.data.content;
                    let resultHtml = '<h3>Generated Content:</h3>';
                    
                    Object.keys(content).forEach(key => {
                        resultHtml += `
                            <div style="margin-bottom: 15px; padding: 15px; border: 1px solid #ddd; border-radius: 5px;">
                                <strong>${key.toUpperCase()}:</strong>
                                <div style="margin-top: 8px; background: #f9f9f9; padding: 10px; border-radius: 3px;">
                                    ${content[key]}
                                </div>
                                <button onclick="applyToEditor('${key}', this.previousElementSibling.textContent)" 
                                        style="margin-top: 8px; padding: 5px 10px; background: #4CAF50; color: white; border: none; border-radius: 3px; cursor: pointer;">
                                    Apply to Editor
                                </button>
                            </div>
                        `;
                    });
                    
                    popup.innerHTML = `
                        <h2 style="margin: 0 0 20px 0;">🎉 Content Generated!</h2>
                        ${resultHtml}
                        <button onclick="closePopup()" style="margin-top: 20px; padding: 10px 20px; background: #1890ff; color: white; border: none; border-radius: 6px; cursor: pointer;">Close</button>
                    `;
                } else {
                    alert('Generation failed: ' + (result.data ? result.data.message : 'Unknown error'));
                }
                
            } catch (error) {
                console.error('Generation error:', error);
                alert('Network error: ' + error.message);
            } finally {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        });

        function closePopup() {
            if (overlay.parentNode) {
                overlay.parentNode.removeChild(overlay);
            }
        }

        return overlay;
    }

    // Export to global scope
    window.openVanillaAIPopup = function(props) {
        const popup = createSimplePopup(props);
        document.body.appendChild(popup);
    };

})();