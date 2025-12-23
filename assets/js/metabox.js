/**
 * Gen Wave MetaBox JavaScript
 */
jQuery(document).ready(function($) {
    // Exit if metabox data not available
    if (typeof genwaveMetabox === 'undefined') {
        return;
    }

    // SECURITY: Create nonces for AJAX requests
    var generateNonce = genwaveMetabox.generateNonce;
    var markConvertedNonce = genwaveMetabox.markConvertedNonce;
    var postId = genwaveMetabox.postId;

    // Show/hide instructions field based on generation method
    function updateInstructionsField() {
        var generationMethod = $('input[name="genwave_generation_method"]:checked').val();
        var $instructionsField = $('#genwave-instructions-field');

        if (generationMethod === 'title' || generationMethod === 'description') {
            $instructionsField.slideDown(300);
        } else {
            $instructionsField.slideUp(300);
        }
    }

    // Update instructions field on radio button change
    $('input[name="genwave_generation_method"]').on('change', updateInstructionsField);

    // Initialize on page load
    updateInstructionsField();

    // Character counter for instructions
    $('#genwave_instructions').on('input', function() {
        var charCount = $(this).val().length;
        $('#genwave-char-count').text(charCount);
    });

    // Instructions modal close handlers
    $('.genwave-instructions-close').on('click', function() {
        $('#genwave-instructions-modal').fadeOut();
        // Focus on the instructions textarea
        $('#genwave_instructions').focus();
    });

    // Toggle advanced options
    $('#genwave-toggle-options').on('click', function() {
        var $options = $('#genwave-advanced-options');
        var $icon = $('#genwave-toggle-icon');
        var $text = $('#genwave-toggle-text');

        if ($options.is(':visible')) {
            $options.slideUp(300);
            $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
            $text.text('Show Options');
        } else {
            $options.slideDown(300);
            $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
            $text.text('Hide Options');
        }
    });

    // Confetti function
    function createConfetti() {
        var container = $('#genwave-confetti-container');
        container.empty(); // Clear previous confetti

        var colors = ['#667eea', '#764ba2', '#f093fb', '#43e97b', '#ffd700', '#ff6b6b', '#4ecdc4', '#45b7d1'];
        var confettiCount = 50;

        for (var i = 0; i < confettiCount; i++) {
            var confetti = $('<div class="genwave-confetti"></div>');

            // Random properties
            var left = Math.random() * 100;
            var animationDelay = Math.random() * 0.5;
            var color = colors[Math.floor(Math.random() * colors.length)];
            var size = Math.random() * 8 + 6; // 6-14px
            var rotation = Math.random() * 360;

            confetti.css({
                'left': left + '%',
                'background': color,
                'width': size + 'px',
                'height': size + 'px',
                'animation-delay': animationDelay + 's',
                'transform': 'rotate(' + rotation + 'deg)',
                'border-radius': Math.random() > 0.5 ? '50%' : '0'
            });

            container.append(confetti);
        }

        // Clean up confetti after animation
        setTimeout(function() {
            container.empty();
        }, 3000);
    }

    // Load available languages (comprehensive WordPress language list)
    var languages = [
        'Afrikaans', 'Albanian', 'Arabic', 'Armenian', 'Azerbaijani',
        'Basque', 'Belarusian', 'Bengali', 'Bosnian', 'Bulgarian',
        'Catalan', 'Chinese (Simplified)', 'Chinese (Traditional)', 'Croatian', 'Czech',
        'Danish', 'Dutch', 'English', 'Estonian', 'Finnish',
        'French', 'Galician', 'Georgian', 'German', 'Greek',
        'Gujarati', 'Hebrew', 'Hindi', 'Hungarian', 'Icelandic',
        'Indonesian', 'Irish', 'Italian', 'Japanese', 'Kannada',
        'Kazakh', 'Khmer', 'Korean', 'Latvian', 'Lithuanian',
        'Macedonian', 'Malay', 'Malayalam', 'Marathi', 'Mongolian',
        'Nepali', 'Norwegian', 'Persian', 'Polish', 'Portuguese (Brazil)',
        'Portuguese (Portugal)', 'Punjabi', 'Romanian', 'Russian', 'Serbian',
        'Sinhala', 'Slovak', 'Slovenian', 'Spanish', 'Spanish (Mexico)',
        'Swahili', 'Swedish', 'Tamil', 'Telugu', 'Thai',
        'Turkish', 'Ukrainian', 'Urdu', 'Uzbek', 'Vietnamese',
        'Welsh', 'Yoruba'
    ];

    var $select = $('#genwave_language');
    var currentLang = $select.val() || 'English';
    $select.empty();

    // Add languages to dropdown
    languages.forEach(function(lang) {
        var selected = lang === currentLang ? ' selected' : '';
        $select.append('<option value="' + lang + '"' + selected + '>' + lang + '</option>');
    });

    // Initialize Select2 with search for language dropdown
    if (typeof $select.select2 === 'function') {
        $select.select2({
            placeholder: 'Select a language',
            allowClear: false,
            width: '100%',
            dropdownAutoWidth: true
        });
    }

    // Shared generation function
    function triggerGeneration($btn) {
        var $statusContainer = $('#genwave-status-container');

        // Remove any existing messages
        $statusContainer.empty();

        // Get selected generation method, language and length
        var generationMethod = $('input[name="genwave_generation_method"]:checked').val();
        var language = $('#genwave_language').val();
        var length = $('#genwave_length').val();
        var instructions = $('#genwave_instructions').val().trim();

        // Validate selection
        if (!generationMethod) {
            $statusContainer.html('<div class="genwave-action-message error"><i class="dashicons dashicons-warning"></i><span>Please select a generation method</span></div>');
            return false;
        }

        if (!language) {
            $statusContainer.html('<div class="genwave-action-message error"><i class="dashicons dashicons-warning"></i><span>Please select a language</span></div>');
            return false;
        }

        return {
            generationMethod: generationMethod,
            language: language,
            length: length,
            instructions: instructions
        };
    }

    // Top button - opens options if hidden
    $('#genwave-generate-btn').on('click', function(e) {
        e.preventDefault();

        var $btn = $(this);
        var $statusContainer = $('#genwave-status-container');
        var $options = $('#genwave-advanced-options');

        // Remove any existing messages
        $statusContainer.empty();

        // If options are hidden, show them first
        if (!$options.is(':visible')) {
            $('#genwave-toggle-options').trigger('click');

            // Show message to configure options
            $statusContainer.html('<div class="genwave-action-message success"><i class="dashicons dashicons-info"></i><span><strong>Configure Options:</strong> Please select your generation preferences below.</span></div>');

            return;
        }

        // Get selected generation method, language and length
        var generationMethod = $('input[name="genwave_generation_method"]:checked').val();
        var language = $('#genwave_language').val();
        var length = $('#genwave_length').val();
        var instructions = $('#genwave_instructions').val().trim();

        // Validate selection
        if (!generationMethod) {
            $statusContainer.html('<div class="genwave-action-message error"><i class="dashicons dashicons-warning"></i><span>Please select a generation method</span></div>');
            return;
        }

        if (!language) {
            $statusContainer.html('<div class="genwave-action-message error"><i class="dashicons dashicons-warning"></i><span>Please select a language</span></div>');
            return;
        }

        if (!length) {
            $statusContainer.html('<div class="genwave-action-message error"><i class="dashicons dashicons-warning"></i><span>Please select content length</span></div>');
            return;
        }

        // Check if instructions are required and missing
        if ((generationMethod === 'title' || generationMethod === 'description') && !instructions) {
            // Update help modal text
            var helpType = generationMethod === 'title' ? 'a title' : 'description';
            $('#genwave-help-type').text(helpType);

            // Show instructions help modal
            $('#genwave-instructions-modal').fadeIn();
            return;
        }

        // Disable button and show loading state
        $btn.prop('disabled', true).addClass('loading');
        $btn.find('.genwave-button-icon').text('⏳');
        $btn.find('.genwave-button-text').text('Generating...');
        $('#genwave-generate-bottom').prop('disabled', true).text('Generating...');

        // Show loading message
        $statusContainer.html('<div class="genwave-action-message loading"><i class="dashicons dashicons-update" style="animation: rotation 1s linear infinite;"></i><span>Generating content, please wait...</span></div>');

        // Open modal immediately with staging indication
        $('#genwave-content-modal').fadeIn();

        // Replace entire content wrapper with staging indication
        $('#genwave-content-wrapper').html(getStagingHTML());

        // Animate stages with enhanced effects
        setTimeout(function() {
            $('#genwave-stage-analyzing').css({
                'opacity': '1',
                'transform': 'translateX(0)',
                'box-shadow': '0 2px 8px rgba(102, 126, 234, 0.15)'
            });
        }, 500);

        setTimeout(function() {
            // Complete analyzing stage
            $('#genwave-stage-analyzing').css({
                'border-left-color': '#28a745',
                'box-shadow': '0 2px 8px rgba(40, 167, 69, 0.15)'
            }).find('div:first').css({
                'background': 'linear-gradient(135deg, #28a745 0%, #20c997 100%)',
                'box-shadow': '0 2px 8px rgba(40, 167, 69, 0.3)'
            });
            $('#genwave-stage-analyzing .dashicons').removeClass('dashicons-update').addClass('dashicons-yes').css({
                'color': 'white',
                'animation': 'none'
            });
            $('#genwave-stage-analyzing span:last').css({
                'color': '#2d3748',
                'font-weight': '600'
            });

            // Start generating stage
            $('#genwave-stage-generating').css({
                'border-left-color': '#667eea',
                'opacity': '1',
                'box-shadow': '0 2px 8px rgba(102, 126, 234, 0.15)',
                'transform': 'translateX(0)'
            }).find('div:first').css({
                'background': 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                'box-shadow': '0 2px 8px rgba(102, 126, 234, 0.3)'
            });
            $('#genwave-stage-generating .dashicons').removeClass('dashicons-edit').addClass('dashicons-update').css({
                'color': 'white',
                'animation': 'rotation 1s linear infinite'
            });
            $('#genwave-stage-generating span:last').css('color', '#4a5568');
        }, 1500);

        setTimeout(function() {
            // Complete generating stage
            $('#genwave-stage-generating').css({
                'border-left-color': '#28a745',
                'box-shadow': '0 2px 8px rgba(40, 167, 69, 0.15)'
            }).find('div:first').css({
                'background': 'linear-gradient(135deg, #28a745 0%, #20c997 100%)',
                'box-shadow': '0 2px 8px rgba(40, 167, 69, 0.3)'
            });
            $('#genwave-stage-generating .dashicons').removeClass('dashicons-update').addClass('dashicons-yes').css({
                'color': 'white',
                'animation': 'none'
            });
            $('#genwave-stage-generating span:last').css({
                'color': '#2d3748',
                'font-weight': '600'
            });

            // Start finalizing stage
            $('#genwave-stage-finalizing').css({
                'border-left-color': '#667eea',
                'opacity': '1',
                'box-shadow': '0 2px 8px rgba(102, 126, 234, 0.15)',
                'transform': 'translateX(0)'
            }).find('div:first').css({
                'background': 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                'box-shadow': '0 2px 8px rgba(102, 126, 234, 0.3)'
            });
            $('#genwave-stage-finalizing .dashicons').removeClass('dashicons-saved').addClass('dashicons-update').css({
                'color': 'white',
                'animation': 'rotation 1s linear infinite'
            });
            $('#genwave-stage-finalizing span:last').css('color', '#4a5568');
        }, 2500);

        // Hide token info during loading
        $('.genwave-token-info').hide();

        // Send AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'genwave_generate_single',
                post_id: postId,
                generation_method: generationMethod,
                language: language,
                length: length,
                instructions: instructions,
                nonce: generateNonce // SECURITY: Add nonce for CSRF protection
            },
            success: function(response) {
                // Re-enable button and reset state
                $btn.prop('disabled', false).removeClass('loading');
                $btn.find('.genwave-button-icon').text('✨');
                $btn.find('.genwave-button-text').text('Generate AI Content');
                $('#genwave-generate-bottom').prop('disabled', false).html('<span class="dashicons dashicons-welcome-write-blog" style="font-size: 16px; width: 16px; height: 16px; margin-top: 2px; margin-left: -4px;"></span> Generate Content');

                if (response.success && response.data && response.data.data) {
                    var data = response.data.data;
                    var results = data.results;

                    if (results && results.results && results.results.length > 0) {
                        var result = results.results[0];
                        var content = result.content;

                        // Token info is at results level, not result level!
                        var tokenUsage = results.tokens_used || {};
                        var tokenCost = results.token_charge_id || 0;
                        var tokenData = results.token_usage || {}; // Balance is here

                        // Modal is already open, just trigger confetti
                        createConfetti();

                        // Show token info again
                        $('.genwave-token-info').show();

                        // Populate token info
                        $('#genwave-input-tokens').text((tokenUsage.input_tokens || 0).toLocaleString());
                        $('#genwave-output-tokens').text((tokenUsage.output_tokens || 0).toLocaleString());
                        $('#genwave-total-tokens').text((tokenUsage.total_tokens || 0).toLocaleString());
                        $('#genwave-token-cost').text(parseFloat(tokenCost || 0).toFixed(6));

                        // Get the right content based on generation method
                        var generatedText = '';
                        if (generationMethod === 'title') {
                            generatedText = content.title || content.description || content || 'No content generated';
                        } else if (generationMethod === 'short_description') {
                            generatedText = content.short_description || content.description || content || 'No content generated';
                        } else {
                            generatedText = content.description || content.title || content || 'No content generated';
                        }

                        // Update modal title based on generation method
                        var contentTypeLabel = '';
                        if (generationMethod === 'title') {
                            contentTypeLabel = 'Generated Title:';
                        } else if (generationMethod === 'short_description') {
                            contentTypeLabel = 'Generated Short Description:';
                        } else {
                            contentTypeLabel = 'Generated Description:';
                        }

                        // Restore original wrapper with title and content
                        $('#genwave-content-wrapper').html(
                            '<h3 id="genwave-content-title" style="margin: 0 0 10px 0; font-size: 16px; color: #333;">' +
                            '<span class="dashicons dashicons-edit" style="font-size: 18px; margin-top: 2px;"></span> ' +
                            contentTypeLabel +
                            '</h3>' +
                            '<div id="genwave-generated-content" style="padding: 20px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 6px; white-space: pre-wrap; line-height: 1.8; max-height: 300px; overflow-y: auto; font-size: 14px; color: #333;">' + escapeHtml(generatedText) + '</div>'
                        );

                        // Update button text
                        var updateBtnText = 'Update Post';
                        if (generationMethod === 'title') {
                            updateBtnText = 'Update Title';
                        } else if (generationMethod === 'short_description') {
                            updateBtnText = 'Update Short Description';
                        } else {
                            updateBtnText = 'Update Description';
                        }
                        $('#genwave-update-btn-text').text(updateBtnText);

                        // Store the generated content for update
                        $('#genwave-update-content').data('generated-content', generatedText);
                        $('#genwave-update-content').data('generation-method', generationMethod);

                        // Store post_request_id for marking as converted later
                        if (response.data.post_request_id) {
                            $('#genwave-update-content').data('post-request-id', response.data.post_request_id);
                        }

                        // Update admin bar token balance if available
                        if (tokenData.tokens_balance !== undefined) {
                            var newBalance = parseFloat(tokenData.tokens_balance).toFixed(2);
                            $('#wp-admin-bar-custom_text_with_icon .ab-item span').text(newBalance);
                        }

                        // Success message
                        $statusContainer.html('<div class="genwave-action-message success"><i class="dashicons dashicons-yes"></i><span><strong>Success!</strong> Content generated successfully!</span></div>');
                    } else {
                        $statusContainer.html('<div class="genwave-action-message error"><i class="dashicons dashicons-no"></i><span><strong>Error:</strong> No content was generated.</span></div>');

                        // Show error in modal - replace entire wrapper
                        $('#genwave-content-wrapper').html(getErrorHTML('No Content Generated', 'The AI couldn\'t generate content. Please try again with different settings.'));
                    }
                } else {
                    var errorMsg = (response.data && response.data.message) ? response.data.message : 'Failed to generate content.';
                    $statusContainer.html('<div class="genwave-action-message error"><i class="dashicons dashicons-no"></i><span><strong>Error:</strong> ' + errorMsg + '</span></div>');

                    // Show error in modal - replace entire wrapper
                    $('#genwave-content-wrapper').html(getErrorHTML('Generation Failed', errorMsg));
                }
            },
            error: function(xhr, status, error) {
                $statusContainer.html('<div class="genwave-action-message error"><i class="dashicons dashicons-no"></i><span><strong>Error:</strong> Failed to connect to the server. Please try again.</span></div>');

                // Show error in modal - replace entire wrapper
                $('#genwave-content-wrapper').html(getErrorHTML('Oops! Something Went Wrong', 'Failed to connect to the server. Please check your connection and try again.'));

                // Re-enable button and reset state
                $btn.prop('disabled', false).removeClass('loading');
                $btn.find('.genwave-button-icon').text('✨');
                $btn.find('.genwave-button-text').text('Generate AI Content');
                $('#genwave-generate-bottom').prop('disabled', false).html('<span class="dashicons dashicons-welcome-write-blog" style="font-size: 16px; width: 16px; height: 16px; margin-top: 2px; margin-left: -4px;"></span> Generate Content');
            }
        });
    });

    // Bottom button - directly starts generation (options already visible)
    $('#genwave-generate-bottom').on('click', function(e) {
        e.preventDefault();
        // Trigger the main button click - it will skip the "show options" part since options are already visible
        $('#genwave-generate-btn').trigger('click');
    });

    // Modal close handlers
    $('.genwave-modal-close, #genwave-modal-cancel').on('click', function() {
        $('#genwave-content-modal').fadeOut();
    });

    // Close modal on outside click
    $('#genwave-content-modal').on('click', function(e) {
        if (e.target.id === 'genwave-content-modal') {
            $(this).fadeOut();
        }
    });

    // Update content button
    $('#genwave-update-content').on('click', function() {
        var generatedContent = $(this).data('generated-content');
        var generationMethod = $(this).data('generation-method');
        var postRequestId = $(this).data('post-request-id');

        if (!generatedContent) {
            alert('No content to update');
            return;
        }

        // Update based on generation method
        if (generationMethod === 'title') {
            // Update title field
            $('#title').val(generatedContent);

            // Also update Gutenberg title if it exists
            if (wp.data && wp.data.dispatch('core/editor')) {
                wp.data.dispatch('core/editor').editPost({
                    title: generatedContent
                });
            }

            $('.genwave-status-message').removeClass('error').addClass('success')
                .find('p').html('<strong>Updated!</strong> Title has been updated. Don\'t forget to save the post!');

        } else if (generationMethod === 'short_description') {
            // Update excerpt/short description
            $('#excerpt').val(generatedContent);

            // For WooCommerce products, update short description
            if ($('#woocommerce-product-data').length > 0) {
                // WooCommerce short description
                if (typeof tinymce !== 'undefined' && tinymce.get('excerpt')) {
                    tinymce.get('excerpt').setContent(generatedContent);
                }
            }

            // Also update Gutenberg excerpt if it exists
            if (wp.data && wp.data.dispatch('core/editor')) {
                wp.data.dispatch('core/editor').editPost({
                    excerpt: generatedContent
                });
            }

            $('.genwave-status-message').removeClass('error').addClass('success')
                .find('p').html('<strong>Updated!</strong> Short description has been updated. Don\'t forget to save!');

        } else {
            // Update main content (description)
            if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
                // Classic editor (TinyMCE)
                var editor = tinymce.get('content');
                if (editor) {
                    editor.setContent(generatedContent);
                }
            } else if (wp.data && wp.data.select('core/editor')) {
                // Gutenberg editor
                var blocks = wp.blocks.parse(generatedContent);
                wp.data.dispatch('core/block-editor').resetBlocks(blocks);
            } else {
                // Fallback to textarea
                $('#content').val(generatedContent);
            }

            $('.genwave-status-message').removeClass('error').addClass('success')
                .find('p').html('<strong>Updated!</strong> Content has been inserted into the editor. Don\'t forget to save the post!');
        }

        // Mark as converted in database if we have post_request_id
        if (postRequestId) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'genwave_mark_converted',
                    post_request_id: postRequestId,
                    nonce: markConvertedNonce // SECURITY: Add nonce for CSRF protection
                },
                success: function(response) {
                    // Marked as converted
                },
                error: function(xhr, status, error) {
                    // Failed to mark as converted
                }
            });
        }

        // Close modal
        $('#genwave-content-modal').fadeOut();

        // Show success message
        $('.genwave-metabox-status').fadeIn();

        // Scroll to top to see the updated content
        $('html, body').animate({ scrollTop: 0 }, 500);
    });

    // Helper function to escape HTML
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    // Helper function for staging HTML
    function getStagingHTML() {
        return '<div style="background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%); border: 2px solid #667eea30; border-radius: 12px; padding: 20px; position: relative; overflow: hidden;">' +
            '<div style="position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, #667eea08 0%, transparent 70%); animation: rotate 20s linear infinite;"></div>' +
            '<div style="position: relative; z-index: 1;">' +
            '<div style="text-align: center; margin-bottom: 15px;">' +
            '<div style="display: inline-flex; align-items: center; gap: 8px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);">' +
            '<span style="font-size: 16px;">✨</span><span>AI is Generating</span></div></div>' +
            '<div style="margin-bottom: 15px;">' +
            '<div style="width: 100%; height: 6px; background: rgba(102, 126, 234, 0.1); border-radius: 3px; overflow: hidden; box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);">' +
            '<div style="height: 100%; background: linear-gradient(90deg, #667eea 0%, #764ba2 50%, #667eea 100%); background-size: 200% 100%; animation: progressSlide 1.5s ease-in-out infinite; border-radius: 3px; box-shadow: 0 0 10px rgba(102, 126, 234, 0.5);"></div></div></div>' +
            '<div class="genwave-stages">' +
            '<div class="genwave-stage" style="display: flex; align-items: center; padding: 10px 14px; margin-bottom: 8px; background: white; border-radius: 8px; border-left: 4px solid #28a745; box-shadow: 0 2px 8px rgba(40, 167, 69, 0.15); transform: translateX(0);">' +
            '<div style="width: 24px; height: 24px; background: linear-gradient(135deg, #28a745 0%, #20c997 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 10px; box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);">' +
            '<span class="dashicons dashicons-yes" style="color: white; font-size: 12px;"></span></div>' +
            '<span style="color: #2d3748; font-size: 12px; font-weight: 600;">Connecting to AI Engine</span></div>' +
            '<div class="genwave-stage" id="genwave-stage-analyzing" style="display: flex; align-items: center; padding: 10px 14px; margin-bottom: 8px; background: white; border-radius: 8px; border-left: 4px solid #667eea; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.15); opacity: 0.6; transform: translateX(0);">' +
            '<div style="width: 24px; height: 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 10px; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);">' +
            '<span class="dashicons dashicons-update" style="color: white; font-size: 12px; animation: rotation 1s linear infinite;"></span></div>' +
            '<span style="color: #4a5568; font-size: 12px; font-weight: 500;">Analyzing Content</span></div>' +
            '<div class="genwave-stage" id="genwave-stage-generating" style="display: flex; align-items: center; padding: 10px 14px; margin-bottom: 8px; background: white; border-radius: 8px; border-left: 4px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05); opacity: 0.4; transform: translateX(0);">' +
            '<div style="width: 24px; height: 24px; background: #e2e8f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 10px;">' +
            '<span class="dashicons dashicons-edit" style="color: #a0aec0; font-size: 12px;"></span></div>' +
            '<span style="color: #718096; font-size: 12px; font-weight: 500;">Generating Content</span></div>' +
            '<div class="genwave-stage" id="genwave-stage-finalizing" style="display: flex; align-items: center; padding: 10px 14px; background: white; border-radius: 8px; border-left: 4px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05); opacity: 0.4; transform: translateX(0);">' +
            '<div style="width: 24px; height: 24px; background: #e2e8f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 10px;">' +
            '<span class="dashicons dashicons-saved" style="color: #a0aec0; font-size: 12px;"></span></div>' +
            '<span style="color: #718096; font-size: 12px; font-weight: 500;">Finalizing</span></div>' +
            '</div></div></div>';
    }

    // Helper function for error HTML
    function getErrorHTML(title, message) {
        return '<div style="text-align: center; padding: 40px 20px;">' +
            '<div style="font-size: 60px; margin-bottom: 20px;">❌</div>' +
            '<h3 style="margin: 0 0 10px 0; color: #dc3545; font-size: 20px;">' + escapeHtml(title) + '</h3>' +
            '<p style="color: #6c757d; margin: 0 0 20px 0; font-size: 14px;">' + escapeHtml(message) + '</p>' +
            '<button type="button" class="button" onclick="jQuery(\'#genwave-content-modal\').fadeOut();" style="background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%); color: white; border: none; padding: 10px 24px; border-radius: 8px; cursor: pointer; font-weight: 500;">Close</button>' +
            '</div>';
    }
});
