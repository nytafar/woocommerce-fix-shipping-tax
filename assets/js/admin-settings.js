jQuery(function($) {
    const startButton = $('#wcfst-start-processing');
    const stopButton = $('#wcfst-stop-processing');
    const spinner = startButton.nextAll('.spinner').first();
    const feedbackContainer = $('#wcfst-tool-feedback');
    
    // Sync decimal precision settings
    const precisionInput = $('#wcfst_precision_value');
    const itemPrecisionInput = $('#wcfst_item_precision_value');
    
    // Sync item precision with general precision on change
    if (precisionInput.length && itemPrecisionInput.length) {
        precisionInput.on('change input', function() {
            itemPrecisionInput.val($(this).val());
        });
        
        // Add a note to indicate they're synced
        itemPrecisionInput.prop('readonly', true);
        itemPrecisionInput.css('background-color', '#f0f0f1');
        itemPrecisionInput.after('<p class="description" style="color: #2271b1; font-weight: 500;">⚙ This value is automatically synchronized with the "Decimal Precision" setting above.</p>');
    }

    startButton.on('click', function() {
        const startDate = $('input[name="wcfst_start_date"]').val();
        const endDate = $('input[name="wcfst_end_date"]').val();
        const overwrite = $('input[name="wcfst_overwrite_existing"]').is(':checked');

        const data = {
            action: 'wcfst_run_tool',
            nonce: wcfst_settings_data.nonce,
            action_type: 'start',
            start_date: startDate,
            end_date: endDate,
            overwrite: overwrite,
        };

        spinner.addClass('is-active');
        feedbackContainer.empty();

        $.post(wcfst_settings_data.ajax_url, data, function(response) {
            spinner.removeClass('is-active');
            if (response.success) {
                feedbackContainer.html('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
            } else {
                feedbackContainer.html('<div class="notice notice-error is-dismissible"><p>' + response.data.message + '</p></div>');
            }
        });
    });

    stopButton.on('click', function() {
        const data = {
            action: 'wcfst_run_tool',
            nonce: wcfst_settings_data.nonce,
            action_type: 'stop',
        };

        spinner.addClass('is-active');
        feedbackContainer.empty();

        $.post(wcfst_settings_data.ajax_url, data, function(response) {
            spinner.removeClass('is-active');
            if (response.success) {
                feedbackContainer.html('<div class="notice notice-warning is-dismissible"><p>' + response.data.message + '</p></div>');
            } else {
                feedbackContainer.html('<div class="notice notice-error is-dismissible"><p>' + response.data.message + '</p></div>');
            }
        });
    });
});
