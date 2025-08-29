/**
 * Script per gestione caparra nel checkout WooCommerce
 * 
 * @package BornToRideBooking
 * @since 1.0.98
 */

(function($) {
    'use strict';
    
    const BTRDepositCheckout = {
        
        /**
         * Inizializzazione
         */
        init: function() {
            this.bindEvents();
            this.addDepositToggle();
            this.updateCheckoutDisplay();
        },
        
        /**
         * Bind eventi
         */
        bindEvents: function() {
            // Toggle modalità caparra
            $(document).on('change', '#btr_deposit_toggle', this.handleDepositToggle.bind(this));
            
            // Update display quando cambia il totale
            $(document.body).on('updated_checkout', this.updateCheckoutDisplay.bind(this));
            
            // Intercetta submit checkout
            $(document).on('checkout_place_order', this.validateDepositCheckout.bind(this));
        },
        
        /**
         * Aggiunge toggle per caparra
         */
        addDepositToggle: function() {
            // Verifica se siamo in modalità preventivo
            if (!this.hasPreventivo()) {
                return;
            }
            
            // Aggiungi toggle prima del riepilogo ordine
            const toggleHtml = `
                <div class="btr-deposit-toggle-wrapper">
                    <h3>${btr_deposit_checkout.strings.toggle_deposit}</h3>
                    <label class="btr-deposit-toggle">
                        <input type="checkbox" id="btr_deposit_toggle" name="btr_deposit_mode">
                        <span class="toggle-label">${btr_deposit_checkout.strings.toggle_deposit}</span>
                    </label>
                    <div class="btr-deposit-toggle-info" style="display: none;">
                        <p>${btr_deposit_checkout.strings.deposit_info}</p>
                    </div>
                </div>
            `;
            
            // Inserisci prima del payment methods o order review
            const $paymentMethods = $('#payment');
            const $orderReview = $('#order_review');
            
            if ($paymentMethods.length) {
                $(toggleHtml).insertBefore($paymentMethods);
            } else if ($orderReview.length) {
                $(toggleHtml).insertBefore($orderReview);
            }
        },
        
        /**
         * Gestisce toggle caparra
         */
        handleDepositToggle: function(e) {
            const isChecked = $(e.target).is(':checked');
            const $info = $('.btr-deposit-toggle-info');
            
            // Mostra/nascondi info
            if (isChecked) {
                $info.slideDown();
            } else {
                $info.slideUp();
            }
            
            // Disabilita form durante aggiornamento
            this.blockCheckout();
            
            // Invia richiesta AJAX
            $.ajax({
                url: btr_deposit_checkout.ajax_url,
                type: 'POST',
                data: {
                    action: 'btr_toggle_deposit_mode',
                    enable: isChecked,
                    nonce: btr_deposit_checkout.nonce
                },
                success: (response) => {
                    if (response.success) {
                        // Aggiorna checkout
                        $(document.body).trigger('update_checkout');
                        
                        // Mostra messaggio
                        this.showNotice(response.data.message, 'success');
                    } else {
                        // Ripristina toggle
                        $(e.target).prop('checked', !isChecked);
                        this.showNotice(response.data || 'Errore durante l\'aggiornamento', 'error');
                    }
                },
                error: () => {
                    // Ripristina toggle
                    $(e.target).prop('checked', !isChecked);
                    this.showNotice('Errore di connessione', 'error');
                },
                complete: () => {
                    this.unblockCheckout();
                }
            });
        },
        
        /**
         * Aggiorna display checkout
         */
        updateCheckoutDisplay: function() {
            // Evidenzia sezione caparra se attiva
            const $depositInfo = $('.btr-deposit-info');
            if ($depositInfo.length) {
                $depositInfo.addClass('highlight');
                
                // Anima per attirare attenzione
                setTimeout(() => {
                    $depositInfo.removeClass('highlight');
                }, 2000);
            }
            
            // Aggiorna testo pulsante ordine
            const $placeOrderBtn = $('#place_order');
            if ($placeOrderBtn.length && $('#btr_deposit_toggle').is(':checked')) {
                const originalText = $placeOrderBtn.data('value') || $placeOrderBtn.val();
                $placeOrderBtn.data('original-value', originalText);
                $placeOrderBtn.val('Paga Caparra');
            } else if ($placeOrderBtn.data('original-value')) {
                $placeOrderBtn.val($placeOrderBtn.data('original-value'));
            }
        },
        
        /**
         * Valida checkout caparra
         */
        validateDepositCheckout: function() {
            if (!$('#btr_deposit_toggle').is(':checked')) {
                return true;
            }
            
            // Aggiungi conferma per caparra
            const $terms = $('#terms');
            if ($terms.length && !$terms.is(':checked')) {
                // WooCommerce gestirà già questo
                return true;
            }
            
            // Potremmo aggiungere ulteriori validazioni qui
            
            return true;
        },
        
        /**
         * Verifica se abbiamo un preventivo
         */
        hasPreventivo: function() {
            // Cerca indicatori che siamo in checkout da preventivo
            return $('input[name="preventivo_id"]').length > 0 || 
                   $('.btr-checkout-summary').length > 0;
        },
        
        /**
         * Blocca checkout durante aggiornamenti
         */
        blockCheckout: function() {
            $('#order_review').block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });
        },
        
        /**
         * Sblocca checkout
         */
        unblockCheckout: function() {
            $('#order_review').unblock();
        },
        
        /**
         * Mostra notifica
         */
        showNotice: function(message, type = 'success') {
            // Rimuovi notifiche esistenti
            $('.woocommerce-error, .woocommerce-message').remove();
            
            const noticeClass = type === 'error' ? 'woocommerce-error' : 'woocommerce-message';
            const $notice = $(`<div class="${noticeClass}" role="alert">${message}</div>`);
            
            // Inserisci prima del form
            const $form = $('form.checkout');
            if ($form.length) {
                $notice.insertBefore($form);
            } else {
                $notice.prependTo('.woocommerce');
            }
            
            // Scroll to notice
            $('html, body').animate({
                scrollTop: $notice.offset().top - 100
            }, 500);
            
            // Auto-remove dopo 5 secondi
            setTimeout(() => {
                $notice.fadeOut(() => {
                    $notice.remove();
                });
            }, 5000);
        }
    };
    
    // Inizializza quando DOM è pronto
    $(document).ready(function() {
        BTRDepositCheckout.init();
    });
    
    // Stili CSS inline per il toggle
    const styles = `
        <style>
        .btr-deposit-toggle-wrapper {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #e0e0e0;
        }
        
        .btr-deposit-toggle-wrapper h3 {
            margin: 0 0 15px;
            font-size: 18px;
            color: #333;
        }
        
        .btr-deposit-toggle {
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        
        .btr-deposit-toggle input[type="checkbox"] {
            margin-right: 10px;
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .btr-deposit-toggle .toggle-label {
            font-size: 16px;
            font-weight: 500;
            color: #0097c5;
            cursor: pointer;
        }
        
        .btr-deposit-toggle-info {
            margin-top: 15px;
            padding: 15px;
            background: #e3f2fd;
            border-radius: 4px;
            border-left: 4px solid #0097c5;
        }
        
        .btr-deposit-toggle-info p {
            margin: 0;
            color: #0c5460;
        }
        
        .btr-deposit-info.highlight {
            animation: highlight 0.5s ease-in-out;
        }
        
        @keyframes highlight {
            0% { background-color: #e3f2fd; }
            50% { background-color: #b3e0fd; }
            100% { background-color: #e3f2fd; }
        }
        
        #place_order[value="Paga Caparra"] {
            background-color: #0097c5;
        }
        
        #place_order[value="Paga Caparra"]:hover {
            background-color: #0086ad;
        }
        </style>
    `;
    
    // Aggiungi stili al documento
    if (!$('#btr-deposit-checkout-styles').length) {
        $('head').append(styles);
    }
    
})(jQuery);