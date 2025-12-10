/**
 * AI Content Generator - Editor Integration
 * Adds AI generation button to WordPress post/product editor
 */

(function($) {
    'use strict';

    // Check if we're in the editor
    const isGutenberg = typeof wp !== 'undefined' && wp.data;
    const isClassicEditor = $('#post').length > 0;
    
    let popupContainer = null;
    let popupInstance = null;

    /**
     * Initialize the AI Generator
     */
    function initAIGenerator() {
        console.log('Initializing AI Generator');
        
        // Create container for React popup
        if (!popupContainer) {
            popupContainer = document.createElement('div');
            popupContainer.id = 'ai-generator-popup-root';
            popupContainer.style.zIndex = '100000';
            document.body.appendChild(popupContainer);
            console.log('Popup container created');
        }

        // Add button based on editor type
        if (isGutenberg) {
            console.log('Initializing Gutenberg integration');
            initGutenbergIntegration();
        } else if (isClassicEditor) {
            console.log('Initializing Classic Editor integration');
            initClassicEditorIntegration();
        }
    }

    /**
     * Initialize Gutenberg Block Editor Integration
     */
    function initGutenbergIntegration() {
        // Wait for Gutenberg to be ready
        wp.domReady(() => {
            // Add toolbar button
            const { PluginPostStatusInfo } = wp.editPost;
            const { registerPlugin } = wp.plugins;
            const { Button } = wp.components;
            const { createElement } = wp.element;
            const { select, dispatch } = wp.data;

            const AIGeneratorButton = () => {
                return createElement(
                    PluginPostStatusInfo,
                    {
                        className: 'ai-generator-panel'
                    },
                    createElement(
                        Button,
                        {
                            isPrimary: true,
                            onClick: openAIGenerator,
                            style: {
                                width: '100%',
                                justifyContent: 'center',
                                marginTop: '10px'
                            }
                        },
                        '🤖 Generate AI Content'
                    )
                );
            };

            // Register the plugin
            registerPlugin('ai-content-generator', {
                render: AIGeneratorButton
            });

            // Also add to toolbar
            const unsubscribe = wp.data.subscribe(() => {
                setTimeout(() => {
                    addToolbarButton();
                }, 100);
            });
        });
    }

    /**
     * Add button to Gutenberg toolbar
     */
    function addToolbarButton() {
        const toolbar = document.querySelector('.edit-post-header-toolbar');
        if (toolbar && !document.getElementById('ai-generator-toolbar-btn')) {
            const button = document.createElement('button');
            button.id = 'ai-generator-toolbar-btn';
            button.className = 'components-button editor-post-save-draft is-tertiary';
            button.innerHTML = '<span style="margin-right: 4px;">🤖</span> AI Generate';
            button.onclick = openAIGenerator;
            
            // Insert after the save draft button
            const saveButton = toolbar.querySelector('.editor-post-save-draft');
            if (saveButton && saveButton.parentNode) {
                saveButton.parentNode.insertBefore(button, saveButton.nextSibling);
            } else {
                toolbar.appendChild(button);
            }
        }
    }

    /**
     * Initialize Classic Editor Integration
     */
    function initClassicEditorIntegration() {
        // Add button to title area
        const $titleWrap = $('#titlewrap');
        if ($titleWrap.length && !$('#ai-generator-classic-btn').length) {
            const $button = $('<button>')
                .attr('id', 'ai-generator-classic-btn')
                .attr('type', 'button')
                .addClass('button button-primary')
                .css({
                    'margin-left': '10px',
                    'margin-top': '5px'
                })
                .html('<span style="margin-right: 4px;">🤖</span> Generate AI Content')
                .on('click', openAIGenerator);
            
            $titleWrap.append($button);
        }

        // Add button to publish box
        const $publishBox = $('#submitdiv .misc-pub-section:last');
        if ($publishBox.length && !$('#ai-generator-publish-btn').length) {
            const $section = $('<div>')
                .addClass('misc-pub-section')
                .html(`
                    <button type="button" id="ai-generator-publish-btn" class="button" style="width: 100%;">
                        <span style="margin-right: 4px;">🤖</span> Generate AI Content
                    </button>
                `);
            
            $section.find('button').on('click', openAIGenerator);
            $publishBox.after($section);
        }
    }

    /**
     * Get current post data
     */
    function getCurrentPostData() {
        let postId, postTitle, postContent, postType;

        // Try Gutenberg first if available
        if (isGutenberg && typeof wp !== 'undefined' && wp.data && wp.data.select('core/editor')) {
            try {
                const editor = wp.data.select('core/editor');
                postId = editor.getCurrentPostId();
                postTitle = editor.getEditedPostAttribute('title');
                postContent = editor.getEditedPostContent();
                postType = editor.getCurrentPostType();
            } catch (e) {
                console.log('Gutenberg data not available, falling back to DOM');
                // Fall back to DOM method
                postId = $('#post_ID').val() || window.aiSettings?.postId || 0;
                postTitle = $('#title').val() || '';
                postType = $('#post_type').val() || window.aiSettings?.postType || 'post';
                postContent = '';
            }
        } else {
            // Classic editor or fallback
            postId = $('#post_ID').val() || window.aiSettings?.postId || 0;
            postTitle = $('#title').val() || '';
            postType = $('#post_type').val() || window.aiSettings?.postType || 'post';
            
            // Get content from TinyMCE or textarea
            if (window.tinyMCE && window.tinyMCE.activeEditor && !window.tinyMCE.activeEditor.isHidden()) {
                postContent = window.tinyMCE.activeEditor.getContent();
            } else if ($('#content').length) {
                postContent = $('#content').val();
            } else {
                postContent = '';
            }
        }

        return {
            postId: postId || 0,
            postTitle: postTitle || '',
            postContent: postContent || '',
            postType: postType || 'post'
        };
    }

    /**
     * Open AI Generator Popup
     */
    function openAIGenerator() {
        console.log('openAIGenerator called');
        
        const postData = getCurrentPostData();
        console.log('Post data:', postData);
        
        // Ensure container exists - create it if needed
        if (!popupContainer) {
            console.log('Creating popup container on demand');
            popupContainer = document.createElement('div');
            popupContainer.id = 'ai-generator-popup-root';
            popupContainer.style.zIndex = '100000';
            document.body.appendChild(popupContainer);
        }
        
        // Check if React is loaded
        if (typeof React === 'undefined') {
            console.error('React not loaded');
            alert('React is not loaded');
            return;
        }
        
        if (typeof ReactDOM === 'undefined') {
            console.error('ReactDOM not loaded');
            alert('ReactDOM is not loaded');
            return;
        }

        // Use enhanced vanilla popup
        if (typeof window.openEnhancedAIPopup === 'function') {
            console.log('Using enhanced vanilla popup');
            window.openEnhancedAIPopup({
                postId: postData.postId,
                postTitle: postData.postTitle,
                postContent: postData.postContent,
                postType: postData.postType
            });
            return;
        }
        
        // Fallback to basic vanilla popup
        if (typeof window.openVanillaAIPopup === 'function') {
            console.log('Using vanilla popup fallback');
            window.openVanillaAIPopup({
                postId: postData.postId,
                postTitle: postData.postTitle,
                postContent: postData.postContent,
                postType: postData.postType
            });
            return;
        }

        // Check if component is loaded
        if (!window.AIContentGeneratorPopup) {
            console.error('AIContentGeneratorPopup not found');
            alert('AI Generator component is not loaded. Please refresh the page.');
            return;
        }

        console.log('All checks passed, rendering popup');

        // Render the popup
        const PopupComponent = window.AIContentGeneratorPopup;
        
        const popupElement = React.createElement(PopupComponent, {
            postId: postData.postId,
            postTitle: postData.postTitle,
            postContent: postData.postContent,
            postType: postData.postType,
            onClose: closeAIGenerator
        });

        console.log('React element created:', popupElement);

        // Use legacy render for WordPress compatibility
        try {
            // Clear any existing content
            if (popupInstance) {
                console.log('Unmounting existing popup instance');
                ReactDOM.unmountComponentAtNode(popupContainer);
            }
            
            console.log('Rendering to container:', popupContainer);
            
            // Use legacy render (works in WordPress React)
            popupInstance = ReactDOM.render(popupElement, popupContainer);
            
            console.log('Popup rendered successfully:', popupInstance);
        } catch (error) {
            console.error('React render error:', error);
            console.error('Error stack:', error.stack);
            alert('Failed to render popup: ' + error.message);
        }
    }

    /**
     * Close AI Generator Popup
     */
    function closeAIGenerator() {
        if (popupContainer && popupInstance) {
            try {
                ReactDOM.unmountComponentAtNode(popupContainer);
                popupInstance = null;
            } catch (error) {
                console.error('Error closing popup:', error);
            }
        }
    }

    // Make openAIGenerator available globally for button clicks
    window.openAIGenerator = openAIGenerator;

    /**
     * Check if we're on a post/product edit screen
     */
    function isEditScreen() {
        // Check for Gutenberg
        if (isGutenberg) {
            const postType = wp.data.select('core/editor')?.getCurrentPostType();
            return ['post', 'page', 'product'].includes(postType);
        }
        
        // Check for Classic Editor
        if (isClassicEditor) {
            const postType = $('#post_type').val();
            return ['post', 'page', 'product'].includes(postType);
        }
        
        return false;
    }

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        // Only initialize on edit screens
        if (isEditScreen()) {
            // Load React and ReactDOM if not already loaded
            if (typeof React === 'undefined' || typeof ReactDOM === 'undefined') {
                // React should be loaded by WordPress, but ensure it's available
                console.log('Waiting for React to load...');
                
                // Check every 500ms for React to be available
                const checkReact = setInterval(() => {
                    if (typeof React !== 'undefined' && typeof ReactDOM !== 'undefined') {
                        clearInterval(checkReact);
                        initAIGenerator();
                    }
                }, 500);
                
                // Stop checking after 10 seconds
                setTimeout(() => clearInterval(checkReact), 10000);
            } else {
                initAIGenerator();
            }
        }
    });

    /**
     * Also initialize when Gutenberg is ready
     */
    if (isGutenberg) {
        wp.domReady(() => {
            if (isEditScreen()) {
                setTimeout(initAIGenerator, 1000);
            }
        });
    }

})(jQuery);