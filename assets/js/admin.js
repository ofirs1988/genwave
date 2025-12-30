// Wrap all code in jQuery ready function
jQuery(document).ready(function($) {
    // Only run license key code on the Gen Wave settings page
    const urlParams = new URLSearchParams(window.location.search);
    const currentPage = urlParams.get('page');
    const isSettingsPage = currentPage === 'gen-wave-plugin-dashboard' || currentPage === 'gen-wave-plugin-settings';

    if (isSettingsPage) {
        const inputField = document.getElementById('licenseKey');
        const hiddenInput = document.getElementById('hiddenLicenseKey');
        const loader = document.getElementById('input-loader');

        if (inputField && hiddenInput && loader) {
            // Store the original license key
            const originalLicenseKey = hiddenInput.value;

            // Keep field empty and disabled until data loads
            inputField.value = '';
            inputField.disabled = true;

            // Simulate loading for 0.5 seconds
            setTimeout(function () {
                // Check value length and add mask for display
                if (originalLicenseKey && originalLicenseKey.length > 6) {
                    const maskedKey = '*'.repeat(originalLicenseKey.length - 6) + originalLicenseKey.slice(-6);
                    inputField.value = maskedKey;
                } else {
                    inputField.value = originalLicenseKey || '';
                }

                // Remove loader and enable field editing
                loader.style.display = 'none';
                inputField.disabled = false;
                inputField.placeholder = 'Enter your license key';
            }, 500);

            // On form submit, restore original value if user didn't change it
            const form = inputField.closest('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    // If the value still contains asterisks (masked), use the original
                    if (inputField.value.includes('*') && originalLicenseKey) {
                        inputField.value = originalLicenseKey;
                    }
                });
            }
        } else if (inputField) {
            // If no loader, just enable the input
            inputField.disabled = false;
            inputField.placeholder = 'Enter your license key';
        }
    }

    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    $('#verify_by_login').on('click', function(e) {
        e.preventDefault();

        var button = $(this);
        var buttonParent = button.closest('.gw-setup-step, .mb-3');
        var alertsContainer = $('#gw-alerts');

        // Remove any existing message
        buttonParent.find('.gw-action-message, .action-message').remove();
        alertsContainer.find('.gw-alert-info').remove();

        // Add loading message after the button
        button.after('<div class="gw-action-message loading">Verifying login...</div>');
        button.prop('disabled', true);
        button.addClass('loading');

        $.ajax({
            url: genwave_admin_data.ajaxurl,
            type: 'POST',
            data: {
                action: 'genwave_verify_login',
                security: genwave_admin_data.nonce,
                license_key: $('#licenseKey').val()
            },
            success: function(response) {
                buttonParent.find('.gw-action-message, .action-message').remove();

                if (response.success) {
                    button.after('<div class="gw-action-message success">' + response.data.message + '</div>');

                    setTimeout(() => {
                        window.location.href = response.data.redirect;
                    }, 1000);
                } else {
                    button.after('<div class="gw-action-message error">' + (response.data.message || 'An error occurred. Please try again.') + '</div>');
                    button.prop('disabled', false);
                    button.removeClass('loading');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                buttonParent.find('.gw-action-message, .action-message').remove();
                button.after('<div class="gw-action-message error">An error occurred. Please try again later.</div>');
                button.prop('disabled', false);
                button.removeClass('loading');
            }
        });
    });


    $('#refresh_tokens').on('click', function (e) {
        e.preventDefault();

        var button = $(this);
        var cardStats = button.closest('.gw-card-stats, .mb-3');

        // Remove any existing message
        cardStats.find('.gw-action-message, .action-message').remove();

        // Add loading state to button
        button.addClass('loading');
        button.prop('disabled', true);

        var data = {
            action: 'genwave_refresh_tokens',
            security: genwave_admin_data.refresh_tokens_nonce,
        };

        $.post(ajaxurl, data, function (response) {
            if (response.success) {
                const tokens = response.data.tokens;

                // Update the token balance display
                $('#token-balance').text(parseFloat(tokens).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));

                // Update token count in menu
                $('li#wp-admin-bar-custom_text_with_icon').find('span').html(tokens);

                // Re-enable button after animation
                setTimeout(() => {
                    button.prop('disabled', false);
                    button.removeClass('loading');
                }, 500);
            } else {
                cardStats.append('<div class="gw-action-message error">' + (response.data.message || 'An unexpected error occurred.') + '</div>');
                button.prop('disabled', false);
                button.removeClass('loading');
            }
        }).fail(function (xhr, status, error) {
            cardStats.append('<div class="gw-action-message error">There was a problem communicating with the server.</div>');
            button.prop('disabled', false);
            button.removeClass('loading');
        });
    });

    // Refresh License Handler (check if license was renewed)
    $('#refresh_license').on('click', function (e) {
        e.preventDefault();

        var button = $(this);
        var alertBox = button.closest('.gw-alert');
        var alertContent = alertBox.find('.gw-alert-content');

        // Add loading state
        button.addClass('loading');
        button.prop('disabled', true);
        button.find('svg').css('animation', 'spin 1s linear infinite');

        $.ajax({
            url: genwave_admin_data.ajaxurl,
            type: 'POST',
            data: {
                action: 'genwave_check_license_status',
                security: genwave_admin_data.genwave_nonce
            },
            success: function(response) {
                button.removeClass('loading');
                button.prop('disabled', false);
                button.find('svg').css('animation', '');

                if (response.success) {
                    if (response.data.expired === false) {
                        // License is now valid - reload the page
                        alertBox.removeClass('gw-alert-warning').addClass('gw-alert-success');
                        alertContent.find('strong').text('License Renewed!');
                        alertContent.find('p').text('Your license is now active. Refreshing...');
                        alertContent.find('.gw-alert-buttons').remove();

                        setTimeout(function() {
                            window.location.reload();
                        }, 1500);
                    } else {
                        // Still expired - update message
                        alertContent.find('p').text('License is still expired. Please renew your subscription.');
                    }
                } else {
                    // Show error message in the alert
                    alertContent.find('p').text(response.data.message || 'Failed to check license status. Please try again.');
                }
            },
            error: function() {
                button.removeClass('loading');
                button.prop('disabled', false);
                button.find('svg').css('animation', '');
                // Show error message in the alert
                alertContent.find('p').text('Failed to connect to server. Please try again.');
            }
        });
    });

    // Disconnect Account Handler
    $('#disconnect_account').on('click', function (e) {
        e.preventDefault();

        var button = $(this);
        var cardFooter = button.closest('.gw-card-footer, .mb-3');
        var card = button.closest('.gw-card, .mb-3');

        // Remove any existing message
        card.find('.gw-action-message, .action-message').remove();

        // Check if already in confirmation mode
        if (button.hasClass('confirming')) {
            // User clicked again - proceed with disconnect
            button.removeClass('confirming');
            button.find('.gw-btn-text').text('Disconnect');

            // Add loading message
            cardFooter.after('<div class="gw-action-message loading" style="margin: 16px 20px;">Disconnecting...</div>');
            button.prop('disabled', true);
            button.addClass('loading');

            var data = {
                action: 'genwave_disconnect_account',
                security: genwave_admin_data.disconnect_account_nonce,
            };

            $.post(ajaxurl, data, function (response) {
                card.find('.gw-action-message, .action-message').remove();

                if (response.success) {
                    cardFooter.after('<div class="gw-action-message success" style="margin: 16px 20px;">' + response.data.message + '</div>');

                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    cardFooter.after('<div class="gw-action-message error" style="margin: 16px 20px;">' + (response.data.message || 'An error occurred while disconnecting.') + '</div>');
                    button.prop('disabled', false);
                    button.removeClass('loading');
                }
            }).fail(function (xhr, status, error) {
                card.find('.gw-action-message, .action-message').remove();
                cardFooter.after('<div class="gw-action-message error" style="margin: 16px 20px;">There was a problem communicating with the server.</div>');
                button.prop('disabled', false);
                button.removeClass('loading');
            });
        } else {
            // First click - show confirmation
            button.addClass('confirming');

            // Change button text to confirm
            var originalHtml = button.html();
            button.html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg> Click again to confirm');

            // Show cancel option
            cardFooter.after('<div class="gw-action-message warning" style="margin: 16px 20px;">Click the button again to disconnect, or <a href="#" class="gw-cancel-disconnect">cancel</a></div>');

            // Handle cancel
            card.find('.gw-cancel-disconnect').on('click', function(ev) {
                ev.preventDefault();
                button.removeClass('confirming');
                button.html(originalHtml);
                card.find('.gw-action-message').remove();
            });

            // Auto-cancel after 5 seconds
            setTimeout(function() {
                if (button.hasClass('confirming')) {
                    button.removeClass('confirming');
                    button.html(originalHtml);
                    card.find('.gw-action-message').remove();
                }
            }, 5000);
        }
    });


    const messageBoxes = document.querySelectorAll('.system-messages');

    messageBoxes.forEach(messageBox => {
        const closeButton = messageBox.querySelector('button');

        closeButton.addEventListener('click', function () {
            // Remove message from screen directly (without saving to server)
            messageBox.style.opacity = '0';
            messageBox.style.transition = 'opacity 0.3s ease-out';

            setTimeout(function() {
                messageBox.remove();
            }, 300);
        });
    });
});
