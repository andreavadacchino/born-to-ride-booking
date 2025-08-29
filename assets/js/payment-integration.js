/**
 * Script integrazione sistema pagamenti
 * 
 * @package BornToRideBooking
 * @since 1.0.98
 */

(function($) {
    'use strict';
    
    // Funzione globale per mostrare selezione piano pagamento
    window.showPaymentPlanSelection = function(preventivoId, options) {
        // Default options
        options = $.extend({
            bankTransferEnabled: true,
            bankTransferInfo: '',
            depositPercentage: 30
        }, options || {});
        // Crea modal
        const modalHtml = `
            <div id="btr-payment-plan-modal" class="btr-modal-overlay">
                <div class="btr-modal-content">
                    <div class="btr-modal-header">
                        <h2>Seleziona la modalità di pagamento</h2>
                        <button type="button" class="btr-modal-close">&times;</button>
                    </div>
                    <div class="btr-modal-body">
                        <div class="btr-loading">
                            <div class="spinner"></div>
                            <p>Caricamento opzioni di pagamento...</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHtml);
        
        // Carica template selezione
        $.ajax({
            url: btr_payment_integration.ajax_url,
            type: 'POST',
            data: {
                action: 'btr_get_payment_selection_template',
                preventivo_id: preventivoId,
                bank_transfer_enabled: options.bankTransferEnabled,
                nonce: btr_payment_integration.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#btr-payment-plan-modal .btr-modal-body').html(response.data.html);
                    initializePaymentSelection(preventivoId, options);
                } else {
                    // Usa template inline se AJAX fallisce
                    showInlinePaymentSelection(preventivoId, options);
                }
            },
            error: function() {
                showInlinePaymentSelection(preventivoId, options);
            }
        });
        
        // Event handlers
        $(document).on('click', '.btr-modal-close, .btr-modal-overlay', function(e) {
            if (e.target === this) {
                closePaymentModal();
            }
        });
    };
    
    /**
     * Mostra selezione inline
     */
    function showInlinePaymentSelection(preventivoId, options) {
        options = options || {bankTransferEnabled: true, bankTransferInfo: '', depositPercentage: 30};
        const html = `
            <form id="btr-payment-plan-form">
                <div class="btr-payment-options">
                    <div class="btr-payment-option" data-plan="full">
                        <input type="radio" name="payment_plan" id="plan_full" value="full" checked>
                        <label for="plan_full">
                            <span class="option-icon">💳</span>
                            <span class="option-title">Pagamento Completo</span>
                            <span class="option-description">Paga l'intero importo in un'unica soluzione</span>
                        </label>
                    </div>
                    
                    <div class="btr-payment-option" data-plan="deposit_balance">
                        <input type="radio" name="payment_plan" id="plan_deposit" value="deposit_balance">
                        <label for="plan_deposit">
                            <span class="option-icon">📊</span>
                            <span class="option-title">Caparra + Saldo</span>
                            <span class="option-description">Paga una caparra ora e il saldo successivamente</span>
                        </label>
                        <div class="deposit-config" style="display: none;">
                            <label>Percentuale caparra:</label>
                            <input type="range" name="deposit_percentage" min="10" max="90" value="${options.depositPercentage}" step="5">
                            <span class="deposit-value">${options.depositPercentage}%</span>
                        </div>
                    </div>
                    
                    <div class="btr-payment-option" data-plan="group_split">
                        <input type="radio" name="payment_plan" id="plan_group" value="group_split">
                        <label for="plan_group">
                            <span class="option-icon">👥</span>
                            <span class="option-title">Pagamento di Gruppo</span>
                            <span class="option-description">Ogni partecipante paga la propria quota individualmente</span>
                        </label>
                    </div>
                </div>
                
                ${options.bankTransferInfo ? `
                    <div class="btr-bank-transfer-info">
                        <div class="info-icon">💡</div>
                        <p>${options.bankTransferInfo}</p>
                    </div>
                ` : ''}
                
                <div class="btr-modal-footer">
                    <button type="button" class="button button-secondary btr-modal-cancel">Annulla</button>
                    <button type="submit" class="button button-primary">Procedi</button>
                </div>
            </form>
        `;
        
        $('#btr-payment-plan-modal .btr-modal-body').html(html);
        initializePaymentSelection(preventivoId, options);
    }
    
    /**
     * Inizializza selezione
     */
    function initializePaymentSelection(preventivoId, options) {
        options = options || {bankTransferEnabled: true, bankTransferInfo: '', depositPercentage: 30};
        // Radio change handler
        $('input[name="payment_plan"]').on('change', function() {
            $('.btr-payment-option').removeClass('selected');
            $(this).closest('.btr-payment-option').addClass('selected');
            
            // Mostra/nascondi config caparra
            if ($(this).val() === 'deposit_balance') {
                $('.deposit-config').slideDown();
            } else {
                $('.deposit-config').slideUp();
            }
        });
        
        // Slider caparra
        $('input[name="deposit_percentage"]').on('input', function() {
            $('.deposit-value').text($(this).val() + '%');
        });
        
        // Cancel button handler
        $('.btr-modal-cancel').on('click', function(e) {
            e.preventDefault();
            closePaymentModal();
        });
        
        // Submit form
        $('#btr-payment-plan-form').on('submit', function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const $submitBtn = $form.find('button[type="submit"]');
            
            // Disabilita submit
            $submitBtn.prop('disabled', true).text(btr_payment_integration.strings.loading);
            
            // Prepara dati
            const formData = new FormData(this);
            formData.append('action', 'btr_create_payment_plan');
            formData.append('preventivo_id', preventivoId);
            formData.append('nonce', btr_payment_integration.nonce);
            
            // Se è gruppo, dobbiamo configurare la distribuzione
            if ($('input[name="payment_plan"]:checked').val() === 'group_split') {
                // Per ora usa distribuzione uguale
                // TODO: permettere configurazione personalizzata
            }
            
            // Invia richiesta
            $.ajax({
                url: btr_payment_integration.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Chiudi modal
                        closePaymentModal();
                        
                        // Redirect basato su tipo piano
                        if (response.data.redirect_url) {
                            window.location.href = response.data.redirect_url;
                        } else {
                            // Ricarica pagina
                            location.reload();
                        }
                    } else {
                        alert(response.data.message || btr_payment_integration.strings.error);
                        $submitBtn.prop('disabled', false).text('Procedi');
                    }
                },
                error: function() {
                    alert(btr_payment_integration.strings.error);
                    $submitBtn.prop('disabled', false).text('Procedi');
                }
            });
        });
    }
    
    /**
     * Chiudi modal
     */
    function closePaymentModal() {
        $('#btr-payment-plan-modal').fadeOut(function() {
            $(this).remove();
        });
    }
    
    // Rendi la funzione globale per compatibilità
    window.closePaymentModal = closePaymentModal;
    
    // Stili CSS inline per il modal
    const styles = `
        <style>
        .btr-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btr-modal-content {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow: hidden;
        }
        
        .btr-modal-header {
            padding: 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .btr-modal-header h2 {
            margin: 0;
            font-size: 24px;
        }
        
        .btr-modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #999;
            padding: 0;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btr-modal-close:hover {
            color: #333;
        }
        
        .btr-modal-body {
            padding: 30px;
            overflow-y: auto;
        }
        
        .btr-loading {
            text-align: center;
            padding: 40px;
        }
        
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #0097c5;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .btr-payment-options {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .btr-payment-option {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btr-payment-option:hover {
            border-color: #0097c5;
            background: #f9f9f9;
        }
        
        .btr-payment-option.selected {
            border-color: #0097c5;
            background: #e3f2fd;
        }
        
        .btr-payment-option input[type="radio"] {
            display: none;
        }
        
        .btr-payment-option label {
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            margin: 0;
        }
        
        .option-icon {
            font-size: 32px;
        }
        
        .option-title {
            font-size: 18px;
            font-weight: 600;
            display: block;
        }
        
        .option-description {
            font-size: 14px;
            color: #666;
            display: block;
            margin-top: 5px;
        }
        
        .deposit-config {
            margin-top: 20px;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 4px;
        }
        
        .deposit-config label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .deposit-config input[type="range"] {
            width: 100%;
            margin-bottom: 5px;
        }
        
        .deposit-value {
            font-size: 24px;
            font-weight: 600;
            color: #0097c5;
        }
        
        .btr-bank-transfer-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }
        
        .btr-bank-transfer-info .info-icon {
            font-size: 20px;
            flex-shrink: 0;
        }
        
        .btr-bank-transfer-info p {
            margin: 0;
            font-size: 14px;
            line-height: 1.5;
            color: #856404;
        }
        
        .btr-modal-footer {
            padding: 20px;
            border-top: 1px solid #e0e0e0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        @media (max-width: 600px) {
            .btr-modal-content {
                width: 95%;
                margin: 20px;
            }
            
            .option-icon {
                font-size: 24px;
            }
            
            .option-title {
                font-size: 16px;
            }
        }
        </style>
    `;
    
    // Aggiungi stili al documento
    if (!$('#btr-payment-modal-styles').length) {
        $('head').append(styles);
    }
    
})(jQuery);