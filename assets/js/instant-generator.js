/**
 * Gen Wave Instant Generator Settings JavaScript
 */
jQuery(document).ready(function($) {
    // Exit if settings data not available
    if (typeof genwaveInstantGenerator === 'undefined') {
        return;
    }

    $('#test-connection-btn').on('click', function() {
        const button = $(this);
        const status = $('#connection-status');

        button.prop('disabled', true).text('Testing...');
        status.html('<span style="color: #666;">Testing connection...</span>');

        $.post(ajaxurl, {
            action: 'genwave_test_connection',
            nonce: genwaveInstantGenerator.nonce
        }, function(response) {
            button.prop('disabled', false).text('Test API Connection');

            if (response.success) {
                status.html('<span style="color: #46b450;">✅ Connection successful!</span>');
            } else {
                status.html('<span style="color: #dc3232;">❌ Connection failed: ' + (response.data || 'Unknown error') + '</span>');
            }
        }).fail(function() {
            button.prop('disabled', false).text('Test API Connection');
            status.html('<span style="color: #dc3232;">❌ Request failed</span>');
        });
    });
});
