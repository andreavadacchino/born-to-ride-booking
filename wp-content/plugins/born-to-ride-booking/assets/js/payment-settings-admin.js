/**
 * JavaScript per pannello amministrazione impostazioni pagamento
 * 
 * @package BornToRideBooking
 * @since 1.0.98+
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Test sistema pagamento
    window.btrTestPaymentSystem = function() {
        const button = $(event.target);
        const originalText = button.text();
        
        button.prop('disabled', true).text(btrPaymentSettings.strings.testing);
        
        $.ajax({
            url: btrPaymentSettings.ajax_url,
            type: 'POST',
            data: {
                action: 'btr_test_payment_system',
                nonce: btrPaymentSettings.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', btrPaymentSettings.strings.test_success);
                    console.log('Payment system test results:', response.data);
                } else {
                    showNotice('error', response.data?.message || btrPaymentSettings.strings.test_error);
                }
            },
            error: function(xhr, status, error) {
                showNotice('error', btrPaymentSettings.strings.test_error + ' ' + error);
                console.error('AJAX error:', error);
            },
            complete: function() {
                button.prop('disabled', false).text(originalText);
            }
        });
    };
    
    // Mostra notifica
    function showNotice(type, message) {
        const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        const notice = $('<div class="notice ' + noticeClass + ' is-dismissible">')
            .append('<p>' + message + '</p>')
            .append('<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>');
        
        $('.wrap h1').after(notice);
        
        // Auto rimozione dopo 5 secondi
        setTimeout(function() {
            notice.fadeOut();
        }, 5000);
        
        // Handle dismiss button
        notice.find('.notice-dismiss').on('click', function() {
            notice.fadeOut();
        });
    }
    
    // Gestione cambio opzioni
    $('input[name="btr_enable_payment_plans"]').on('change', function() {
        const isEnabled = $(this).is(':checked');
        const dependentFields = $('input[name="btr_enable_bank_transfer_plans"], input[name="btr_default_deposit_percentage"], textarea[name="btr_bank_transfer_info"]');
        
        if (isEnabled) {
            dependentFields.prop('disabled', false).closest('tr').removeClass('btr-disabled');
        } else {
            dependentFields.prop('disabled', true).closest('tr').addClass('btr-disabled');
        }
    }).trigger('change');
    
    // Validazione percentuale caparra
    $('input[name="btr_default_deposit_percentage"]').on('input', function() {
        const value = parseInt($(this).val());
        const min = parseInt($(this).attr('min'));
        const max = parseInt($(this).attr('max'));
        
        if (value < min) {
            $(this).val(min);
            showNotice('error', 'La percentuale minima è ' + min + '%');
        } else if (value > max) {
            $(this).val(max);
            showNotice('error', 'La percentuale massima è ' + max + '%');
        }
    });
    
    // Stili dinamici
    $('<style>').prop('type', 'text/css').html(`
        .btr-disabled {
            opacity: 0.5;
            pointer-events: none;
        }
        
        .notice.notice-success,
        .notice.notice-error {
            margin: 15px 0;
            border-left-width: 4px;
            border-left-style: solid;
        }
        
        .notice.notice-success {
            border-left-color: #00a32a;
            background-color: #f0f8f0;
        }
        
        .notice.notice-error {
            border-left-color: #d63638;
            background-color: #fdf0f0;
        }
        
        .btr-settings-box .button {
            width: 100%;
            text-align: center;
        }
    `).appendTo('head');
});

// AJAX handler per test sistema
jQuery(document).on('click', '[data-action="test-payment"]', function(e) {
    e.preventDefault();
    window.btrTestPaymentSystem.call(this);
});