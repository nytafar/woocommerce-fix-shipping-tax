/**
 * WooCommerce Fix Shipping Tax Admin Scripts
 */

(function($) {
    'use strict';
    
    // Tab navigation
    $(document).on('click', '.wcfst-tab-nav a', function(e) {
        e.preventDefault();
        
        var $link = $(this);
        var $nav = $link.closest('.wcfst-tabs');
        var target = $link.attr('href');
        
        // Update nav
        $nav.find('.wcfst-tab-nav li').removeClass('active');
        $link.parent().addClass('active');
        
        // Update content
        $nav.find('.wcfst-tab-content').removeClass('active');
        $(target).addClass('active');
    });
    
    // Apply fix button
    $(document).on('click', '.wcfst-apply-fix', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var $spinner = $button.next('.spinner');
        var orderId = $button.data('order-id');
        var taxRate = $button.data('tax-rate');
        
        // Disable button and show spinner
        $button.prop('disabled', true);
        $spinner.css('visibility', 'visible');
        
        // Make AJAX request
        $.ajax({
            url: wcfst.ajax_url,
            type: 'POST',
            data: {
                action: 'wcfst_apply_fix',
                nonce: wcfst.nonce,
                order_id: orderId,
                tax_rate: taxRate
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    if (response.data.message) {
                        showNotice(response.data.message, 'success');
                    }
                    
                    // Reload page after a short delay
                    if (response.data.redirect) {
                        setTimeout(function() {
                            window.location.href = response.data.redirect;
                        }, 1000);
                    }
                } else {
                    // Show error message
                    var message = response.data || wcfst.i18n.error;
                    showNotice(message, 'error');
                    
                    // Re-enable button
                    $button.prop('disabled', false);
                }
            },
            error: function() {
                // Show error message
                showNotice(wcfst.i18n.error, 'error');
                
                // Re-enable button
                $button.prop('disabled', false);
            },
            complete: function() {
                // Hide spinner
                $spinner.css('visibility', 'hidden');
            }
        });
    });
    
    /**
     * Show a notice in the meta box
     */
    function showNotice(message, type) {
        var $metaBox = $('.wcfst-meta-box');
        
        // Remove any existing notices
        $metaBox.find('.wcfst-notice').remove();
        
        // Create notice element
        var $notice = $('<div>')
            .addClass('wcfst-notice')
            .addClass(type)
            .text(message);
        
        // Add to meta box
        $metaBox.prepend($notice);
        
        // Auto-remove after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
})(jQuery);
