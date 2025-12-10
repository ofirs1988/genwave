var language,currentLanguage,languagesNoRedirect,hasWasCookie,expirationDate;

// Wrap all code in jQuery ready function
jQuery(document).ready(function($) {
    const inputField = document.getElementById('licenseKey'); // שדה הקלט הגלוי
    const hiddenInput = document.getElementById('hiddenLicenseKey'); // שדה הקלט המוסתר
    const loader = document.getElementById('input-loader'); // ה-loader

    console.log('License Key Elements:', {
        inputField: inputField,
        hiddenInput: hiddenInput,
        loader: loader,
        hiddenValue: hiddenInput ? hiddenInput.value : 'N/A'
    });

    if (inputField && hiddenInput && loader) {
        // השארת השדה ריק ונעול עד שהנתונים נטענים
        inputField.value = '';
        inputField.disabled = true;

        // דימוי טעינה של 0.5 שניות (קוצר מ-2)
        setTimeout(function () {
            console.log('Loading license key...');
            // קבלת הערך מהשדה המוסתר
            const licenseKey = hiddenInput.value;

            // בדיקת אורך הערך והוספת מסיכה
            if (licenseKey && licenseKey.length > 6) {
                const maskedKey = '*'.repeat(licenseKey.length - 6) + licenseKey.slice(-6);
                inputField.value = maskedKey;
            } else {
                inputField.value = licenseKey || ''; // אם הערך קצר מ-6 תווים או ריק
            }

            // הסרת ה-loader ואפשרות לעריכת השדה
            loader.style.display = 'none';
            inputField.disabled = false;
            inputField.placeholder = 'Enter your license key';

            // הסרת השדה המוסתר
            if (hiddenInput.parentNode) {
                hiddenInput.remove();
            }

            console.log('License key loaded:', inputField.value);
        }, 500); // קוצר ל-0.5 שניות
    } else {
        console.error('Missing elements for license key!', {
            inputField: !!inputField,
            hiddenInput: !!hiddenInput,
            loader: !!loader
        });

        // אם אין loader, פשוט אפשר את ה-input
        if (inputField) {
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
        var buttonParent = button.closest('.mb-3');

        // Remove any existing message
        buttonParent.find('.action-message').remove();

        // Add loading message
        buttonParent.append('<div class="action-message loading"><i class="fa-solid fa-spinner fa-spin"></i><span>Verifying login...</span></div>');
        button.prop('disabled', true);
        button.parent().addClass('loading');

        $.ajax({
            url: passed.ajaxurl,
            type: 'POST',
            data: {
                action: 'verify_login',
                security: passed.nonce,
                license_key: $('#licenseKey').val()
            },
            success: function(response) {
                buttonParent.find('.action-message').remove();

                if (response.success) {
                    buttonParent.append('<div class="action-message success"><i class="fa-solid fa-check-circle"></i><span>' + response.data.message + '</span></div>');

                    setTimeout(() => {
                        window.location.href = response.data.redirect;
                    }, 1000);
                } else {
                    buttonParent.append('<div class="action-message error"><i class="fa-solid fa-times-circle"></i><span>' + (response.data.message || 'An error occurred. Please try again.') + '</span></div>');
                    button.prop('disabled', false);
                    button.parent().removeClass('loading');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                buttonParent.find('.action-message').remove();
                buttonParent.append('<div class="action-message error"><i class="fa-solid fa-times-circle"></i><span>An error occurred. Please try again later.</span></div>');
                button.prop('disabled', false);
                button.parent().removeClass('loading');
            }
        });
    });


    $('#select-all-products').on('click', function() {
        var allChecked = $('.product-checkbox:checked').length === $('.product-checkbox').length;

        if (allChecked) {
            // אם כל המוצרים מסומנים, הסר סימון
            $('.product-checkbox').prop('checked', false);
        } else {
            // אם לא כל המוצרים מסומנים, בחר את כולם
            $('.product-checkbox').prop('checked', true);
        }
    });




    $('#submit-button').on('click', function(e) {
        e.preventDefault(); // למנוע את השליחה הרגילה של הטופס

        // איסוף הנתונים מהטופס
        var formData = {
            license_key: $('#license_key').val(),
            domain: $('#domain').val(),
            selected_product: $('.product-radio:checked').val(), // איסוף המוצר שנבחר עם radio button
            action: 'generate_ai',
            security: passed.nonce // שימוש ב-nonce שהועבר מ-PHP
        };

        // בדיקה אם מוצר נבחר
        if (!formData.selected_product) {
            Swal.fire({
                icon: 'warning',
                title: 'No Product Selected',
                text: 'Please select a product before proceeding.',
                showConfirmButton: true
            });
            return;
        }

        // שליחת הנתונים באמצעות AJAX
        $.ajax({
            url: ajaxurl, // כתובת ה-URL של AJAX ב-WordPress
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Settings Saved!',
                        text: 'Your settings have been saved successfully.',
                        showConfirmButton: true
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: response.data || 'An error occurred while saving settings.',
                        showConfirmButton: true
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error(xhr.responseText);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred during the saving process. Please try again later.',
                    showConfirmButton: true
                });
            }
        });
    });

    $('#refresh_tokens').on('click', function (e) {
        e.preventDefault();

        var button = $(this);
        var buttonParent = button.closest('.mb-3');

        // Remove any existing message
        buttonParent.find('.action-message').remove();

        // Add loading message
        buttonParent.append('<div class="action-message loading"><i class="fa-solid fa-spinner fa-spin"></i><span>Refreshing tokens...</span></div>');
        button.prop('disabled', true);
        button.parent().addClass('loading');

        var data = {
            action: 'refresh_tokens_action',
            security: passed.refresh_tokens_nonce,
        };

        $.post(ajaxurl, data, function (response) {
            console.log(response);

            buttonParent.find('.action-message').remove();

            if (response.success) {
                const tokens = response.data.tokens;

                buttonParent.append('<div class="action-message success"><i class="fa-solid fa-check-circle"></i><span>Tokens refreshed successfully! New tokens: <strong>' + tokens + '</strong></span></div>');

                // Update token count in menu
                $('li#wp-admin-bar-custom_text_with_icon').find('span').html(tokens);

                // Re-enable button after 2 seconds
                setTimeout(() => {
                    button.prop('disabled', false);
                    button.parent().removeClass('loading');
                }, 2000);
            } else {
                buttonParent.append('<div class="action-message error"><i class="fa-solid fa-times-circle"></i><span>' + (response.data.message || 'An unexpected error occurred.') + '</span></div>');
                button.prop('disabled', false);
                button.parent().removeClass('loading');
            }
        }).fail(function (xhr, status, error) {
            buttonParent.find('.action-message').remove();
            buttonParent.append('<div class="action-message error"><i class="fa-solid fa-times-circle"></i><span>There was a problem communicating with the server. Please try again later.</span></div>');
            button.prop('disabled', false);
            button.parent().removeClass('loading');
        });
    });

    // Disconnect Account Handler
    $('#disconnect_account').on('click', function (e) {
        e.preventDefault();

        var button = $(this);
        var buttonParent = button.closest('.mb-3');

        // Remove any existing message
        buttonParent.find('.action-message').remove();

        // Show confirmation message
        buttonParent.append('<div class="action-message warning"><i class="fa-solid fa-exclamation-triangle"></i><span>Are you sure? This will disconnect your account. <a href="#" id="confirm-disconnect" style="color: #d32f2f;">Yes, disconnect</a> <a href="#" id="cancel-disconnect" style="color: #757575;">Cancel</a></span></div>');

        // Handle confirmation
        $(document).on('click', '#confirm-disconnect', function(e) {
            e.preventDefault();
            $(document).off('click', '#confirm-disconnect');
            $(document).off('click', '#cancel-disconnect');

            buttonParent.find('.action-message').remove();
            buttonParent.append('<div class="action-message loading"><i class="fa-solid fa-spinner fa-spin"></i><span>Disconnecting...</span></div>');
            button.prop('disabled', true);
            button.parent().addClass('loading');

            var data = {
                action: 'disconnect_account_action',
                security: passed.disconnect_account_nonce,
            };

            $.post(ajaxurl, data, function (response) {
                console.log(response);

                buttonParent.find('.action-message').remove();

                if (response.success) {
                    buttonParent.append('<div class="action-message success"><i class="fa-solid fa-check-circle"></i><span>' + response.data.message + '</span></div>');

                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    buttonParent.append('<div class="action-message error"><i class="fa-solid fa-times-circle"></i><span>' + (response.data.message || 'An error occurred while disconnecting.') + '</span></div>');
                    button.prop('disabled', false);
                    button.parent().removeClass('loading');
                }
            }).fail(function (xhr, status, error) {
                buttonParent.find('.action-message').remove();
                buttonParent.append('<div class="action-message error"><i class="fa-solid fa-times-circle"></i><span>There was a problem communicating with the server. Please try again later.</span></div>');
                button.prop('disabled', false);
                button.parent().removeClass('loading');
            });
        });

        // Handle cancel
        $(document).on('click', '#cancel-disconnect', function(e) {
            e.preventDefault();
            $(document).off('click', '#confirm-disconnect');
            $(document).off('click', '#cancel-disconnect');
            buttonParent.find('.action-message').remove();
        });
    });


    const messageBoxes = document.querySelectorAll('.system-messages');

    messageBoxes.forEach(messageBox => {
        const closeButton = messageBox.querySelector('button');

        closeButton.addEventListener('click', function () {
            // הסרת ההודעה מהמסך ישירות (ללא שמירה בשרת)
            messageBox.style.opacity = '0';
            messageBox.style.transition = 'opacity 0.3s ease-out';

            setTimeout(function() {
                messageBox.remove();
            }, 300);
        });
    });
});
