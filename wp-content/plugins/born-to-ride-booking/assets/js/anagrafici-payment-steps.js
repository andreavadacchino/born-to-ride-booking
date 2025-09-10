/**
 * Born to Ride - Multi-step Form Handler
 * Gestisce il form anagrafici con step integrato per la selezione pagamento
 * 
 * @since 1.0.99
 */
(function($) {
    'use strict';

    const BTRMultiStepForm = {
        
        // Stato corrente
        currentStep: 1,
        totalSteps: 2,
        preventivo_id: null,
        anagraficiValid: false,
        paymentSelected: false,
        
        // Elementi DOM
        elements: {
            formWrapper: null,
            anagraficiSection: null,
            paymentSection: null,
            continueButton: null,
            backButton: null,
            submitButton: null,
            stepsIndicator: null,
            loadingOverlay: null
        },
        
        /**
         * Inizializza il sistema multi-step
         */
        init: function() {
            // Verifica se siamo nella pagina anagrafici con selettori più ampi
            const $form = $('#btr-anagrafici-form, .btr-form, form[action*="admin-post.php"]').filter(function() {
                // Verifica che sia effettivamente un form anagrafici
                return $(this).find('input[name="preventivo_id"]').length > 0 || 
                       $(this).find('.btr-anagrafici-container, .room-assignment-section').length > 0;
            }).first();
            
            if (!$form.length) {
                console.log('[BTR] Form anagrafici non trovato, skip inizializzazione');
                return;
            }
            
            console.log('[BTR] Inizializzazione multi-step form - Form trovato:', $form.attr('id') || $form.attr('class'));
            
            // Setup iniziale
            this.setupElements();
            this.createPaymentSection();
            this.createStepIndicator();
            this.createNavigationButtons();
            this.bindEvents();
            this.showStep(1);
            
            // Recupera preventivo_id
            this.preventivo_id = $('input[name="preventivo_id"]').val();
            console.log('[BTR] Preventivo ID:', this.preventivo_id);
        },
        
        /**
         * Setup elementi DOM
         */
        setupElements: function() {
            // Trova il form esistente con selettori più flessibili
            const $form = $('#btr-anagrafici-form, .btr-form, form[action*="admin-post.php"]').filter(function() {
                return $(this).find('input[name="preventivo_id"]').length > 0 || 
                       $(this).find('.btr-anagrafici-container, .room-assignment-section').length > 0;
            }).first();
            
            if (!$form.length) {
                console.error('[BTR] Form non trovato in setupElements');
                return;
            }
            
            console.log('[BTR] setupElements - Form trovato');
            
            // Crea wrapper per gli step se non esiste già
            if (!$form.parent('.btr-multistep-wrapper').length) {
                $form.wrap('<div class="btr-multistep-wrapper"></div>');
            }
            this.elements.formWrapper = $('.btr-multistep-wrapper');
            
            // Trova tutti i contenuti del form da wrappare nello step 1
            const $formContent = $form.find('.btr-anagrafici-container, .room-assignment-section, .insurance-selection, .btr-person-card').closest('div').parent();
            
            if ($formContent.length === 0) {
                // Fallback: wrappa tutto il contenuto del form eccetto i campi hidden
                const $allContent = $form.children().not('input[type="hidden"], .btr-step-section, .btr-steps-indicator');
                $allContent.wrapAll('<div class="btr-step-1 btr-step-section" data-step="1"></div>');
            } else {
                $formContent.wrapAll('<div class="btr-step-1 btr-step-section" data-step="1"></div>');
            }
            
            this.elements.anagraficiSection = $('.btr-step-1');
            
            // Nascondi il submit originale
            const $originalSubmit = $form.find('button[type="submit"], input[type="submit"]').not('.btr-btn-continue, .btr-btn-submit');
            console.log('[BTR] Submit originale trovato:', $originalSubmit.length);
            $originalSubmit.hide().addClass('btr-original-submit');
            this.elements.submitButton = $originalSubmit;
        },
        
        /**
         * Crea la sezione per la selezione pagamento
         */
        createPaymentSection: function() {
            const paymentHTML = `
                <div class="btr-step-2 btr-step-section" data-step="2" style="display: none;">
                    <h2>Seleziona Modalità di Pagamento</h2>
                    
                    <div class="btr-payment-options">
                        <!-- Pagamento Completo -->
                        <div class="btr-payment-option" data-payment-type="full">
                            <input type="radio" name="payment_type" id="payment_full" value="full" />
                            <label for="payment_full">
                                <div class="payment-icon">💳</div>
                                <h3>Pagamento Completo</h3>
                                <p>Paga l'intero importo ora</p>
                                <div class="payment-amount" id="full-amount">€ -</div>
                            </label>
                        </div>
                        
                        <!-- Pagamento di Gruppo -->
                        <div class="btr-payment-option" data-payment-type="group">
                            <input type="radio" name="payment_type" id="payment_group" value="group" />
                            <label for="payment_group">
                                <div class="payment-icon">👥</div>
                                <h3>Pagamento di Gruppo</h3>
                                <p>Dividi il pagamento tra i partecipanti</p>
                                <div class="payment-details">Genera link individuali</div>
                            </label>
                        </div>
                        
                        <!-- Acconto + Saldo -->
                        <div class="btr-payment-option" data-payment-type="deposit">
                            <input type="radio" name="payment_type" id="payment_deposit" value="deposit" />
                            <label for="payment_deposit">
                                <div class="payment-icon">📅</div>
                                <h3>Acconto + Saldo</h3>
                                <p>Paga un acconto ora e il saldo successivamente</p>
                                <div class="payment-amount">
                                    <span>Acconto: </span><span id="deposit-amount">€ -</span>
                                </div>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Dettagli pagamento gruppo (nascosto inizialmente) -->
                    <div class="btr-group-payment-details" style="display: none;">
                        <h3>Seleziona chi paga</h3>
                        <div class="group-participants-list">
                            <!-- Popolato dinamicamente -->
                        </div>
                    </div>
                    
                    <!-- Riepilogo -->
                    <div class="btr-payment-summary">
                        <h3>Riepilogo Preventivo</h3>
                        <div class="summary-content">
                            <div class="summary-row">
                                <span>Totale Preventivo:</span>
                                <span id="total-amount">€ -</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            this.elements.anagraficiSection.after(paymentHTML);
            this.elements.paymentSection = $('.btr-step-2');
        },
        
        /**
         * Crea indicatore step
         */
        createStepIndicator: function() {
            const indicatorHTML = `
                <div class="btr-steps-indicator">
                    <div class="step-item active" data-step="1">
                        <span class="step-number">1</span>
                        <span class="step-label">Dati Anagrafici</span>
                    </div>
                    <div class="step-connector"></div>
                    <div class="step-item" data-step="2">
                        <span class="step-number">2</span>
                        <span class="step-label">Modalità Pagamento</span>
                    </div>
                </div>
            `;
            
            this.elements.formWrapper.prepend(indicatorHTML);
            this.elements.stepsIndicator = $('.btr-steps-indicator');
        },
        
        /**
         * Crea pulsanti navigazione
         */
        createNavigationButtons: function() {
            const navigationHTML = `
                <div class="btr-step-navigation">
                    <button type="button" class="btr-btn-back" style="display: none;">
                        ← Indietro
                    </button>
                    <button type="button" class="btr-btn-continue">
                        Continua →
                    </button>
                </div>
            `;
            
            // Inserisci dopo la sezione anagrafici
            this.elements.anagraficiSection.append(navigationHTML);
            
            // Aggiungi anche alla sezione pagamento
            this.elements.paymentSection.append(navigationHTML.replace('btr-btn-continue', 'btr-btn-submit').replace('Continua →', 'Vai al Checkout'));
            
            this.elements.continueButton = $('.btr-btn-continue');
            this.elements.backButton = $('.btr-btn-back');
        },
        
        /**
         * Bind eventi
         */
        bindEvents: function() {
            const self = this;
            
            // Navigazione
            $(document).on('click', '.btr-btn-continue', function(e) {
                e.preventDefault();
                self.handleContinue();
            });
            
            $(document).on('click', '.btr-btn-back', function(e) {
                e.preventDefault();
                self.showStep(self.currentStep - 1);
            });
            
            $(document).on('click', '.btr-btn-submit', function(e) {
                e.preventDefault();
                self.handleFinalSubmit();
            });
            
            // Selezione pagamento
            $(document).on('change', 'input[name="payment_type"]', function() {
                self.handlePaymentSelection($(this).val());
            });
            
            // Click su step indicator
            $(document).on('click', '.step-item', function() {
                const step = parseInt($(this).data('step'));
                if (step < self.currentStep || (step === 2 && self.anagraficiValid)) {
                    self.showStep(step);
                }
            });
        },
        
        /**
         * Gestisce il click su Continua
         */
        handleContinue: function() {
            if (this.currentStep === 1) {
                // Valida anagrafici
                if (this.validateAnagrafici()) {
                    this.anagraficiValid = true;
                    this.saveAnagraficiTemp();
                    this.loadPaymentData();
                    this.showStep(2);
                }
            }
        },
        
        /**
         * Valida i dati anagrafici
         */
        validateAnagrafici: function() {
            let valid = true;
            const errors = [];
            
            // Valida che ogni persona abbia i dati richiesti
            $('.btr-person-card').each(function() {
                const $card = $(this);
                const nome = $card.find('input[name*="[nome]"]').val();
                const cognome = $card.find('input[name*="[cognome]"]').val();
                const dataNascita = $card.find('input[name*="[data_nascita]"]').val();
                const camera = $card.find('input[name*="[camera]"]').val();
                
                if (!nome || !cognome || !dataNascita) {
                    valid = false;
                    errors.push('Completa tutti i dati anagrafici richiesti');
                    return false;
                }
                
                if (!camera) {
                    valid = false;
                    errors.push('Assegna una camera a tutti i partecipanti');
                    return false;
                }
            });
            
            if (!valid) {
                this.showNotification(errors.join('<br>'), 'error');
            }
            
            return valid;
        },
        
        /**
         * Salva temporaneamente gli anagrafici
         */
        saveAnagraficiTemp: function() {
            const self = this;
            const formData = $('#btr-anagrafici-form, .btr-form').serialize();
            
            // Salva in sessione temporanea via AJAX
            $.post(btr_anagrafici.ajax_url, {
                action: 'btr_save_anagrafici_temp',
                nonce: btr_anagrafici.nonce,
                data: formData
            });
        },
        
        /**
         * Carica i dati per il pagamento
         */
        loadPaymentData: function() {
            const self = this;
            
            // Mostra loading
            this.showLoading();
            
            $.post(btr_anagrafici.ajax_url, {
                action: 'btr_get_payment_data',
                nonce: btr_anagrafici.nonce,
                preventivo_id: this.preventivo_id
            }, function(response) {
                self.hideLoading();
                
                if (response.success) {
                    // Aggiorna importi
                    $('#total-amount').text('€ ' + response.data.total_formatted);
                    $('#full-amount').text('€ ' + response.data.total_formatted);
                    $('#deposit-amount').text('€ ' + response.data.deposit_formatted);
                    
                    // Se pagamento gruppo, popola partecipanti
                    if (response.data.participants) {
                        self.populateGroupParticipants(response.data.participants);
                    }
                }
            });
        },
        
        /**
         * Gestisce la selezione del tipo di pagamento
         */
        handlePaymentSelection: function(type) {
            this.paymentSelected = true;
            
            $('.btr-payment-option').removeClass('selected');
            $(`.btr-payment-option[data-payment-type="${type}"]`).addClass('selected');
            
            // Mostra/nascondi dettagli gruppo
            if (type === 'group') {
                $('.btr-group-payment-details').slideDown();
            } else {
                $('.btr-group-payment-details').slideUp();
            }
        },
        
        /**
         * Popola lista partecipanti per pagamento gruppo
         */
        populateGroupParticipants: function(participants) {
            let html = '';
            
            participants.forEach(function(p, index) {
                html += `
                    <div class="group-participant">
                        <label>
                            <input type="checkbox" name="group_payers[]" value="${index}" checked />
                            <span>${p.nome} ${p.cognome}</span>
                        </label>
                        <input type="number" name="group_shares[${index}]" value="1" min="1" max="10" />
                        <span>quota/e</span>
                    </div>
                `;
            });
            
            $('.group-participants-list').html(html);
        },
        
        /**
         * Gestisce il submit finale
         */
        handleFinalSubmit: function() {
            if (!this.paymentSelected) {
                this.showNotification('Seleziona una modalità di pagamento', 'error');
                return;
            }
            
            // Aggiungi i dati del pagamento al form
            const paymentData = this.collectPaymentData();
            this.appendPaymentDataToForm(paymentData);
            
            // Trigger del submit originale
            this.elements.submitButton.click();
        },
        
        /**
         * Raccoglie i dati del pagamento
         */
        collectPaymentData: function() {
            const data = {
                payment_type: $('input[name="payment_type"]:checked').val()
            };
            
            if (data.payment_type === 'group') {
                data.group_payers = [];
                data.group_shares = {};
                
                $('input[name="group_payers[]"]:checked').each(function() {
                    const index = $(this).val();
                    data.group_payers.push(index);
                    data.group_shares[index] = $(`input[name="group_shares[${index}]"]`).val();
                });
            }
            
            return data;
        },
        
        /**
         * Aggiunge i dati del pagamento al form
         */
        appendPaymentDataToForm: function(data) {
            const $form = $('#btr-anagrafici-form').length ? $('#btr-anagrafici-form') : $('.btr-form');
            
            // Rimuovi eventuali input precedenti
            $form.find('input[name^="payment_"]').remove();
            
            // Aggiungi nuovi input
            $form.append(`<input type="hidden" name="payment_plan_type" value="${data.payment_type}" />`);
            
            if (data.payment_type === 'group' && data.group_payers) {
                data.group_payers.forEach(function(payer, idx) {
                    $form.append(`<input type="hidden" name="payment_group_payers[]" value="${payer}" />`);
                    $form.append(`<input type="hidden" name="payment_group_shares[${payer}]" value="${data.group_shares[payer]}" />`);
                });
            }
        },
        
        /**
         * Mostra uno step specifico
         */
        showStep: function(step) {
            this.currentStep = step;
            
            // Nascondi tutti gli step
            $('.btr-step-section').hide();
            
            // Mostra lo step corrente
            $(`.btr-step-${step}`).fadeIn();
            
            // Aggiorna indicatore
            $('.step-item').removeClass('active completed');
            for (let i = 1; i < step; i++) {
                $(`.step-item[data-step="${i}"]`).addClass('completed');
            }
            $(`.step-item[data-step="${step}"]`).addClass('active');
            
            // Mostra/nascondi pulsanti
            if (step === 1) {
                $('.btr-btn-back').hide();
            } else {
                $('.btr-btn-back').show();
            }
            
            // Scroll to top
            $('html, body').animate({ scrollTop: this.elements.formWrapper.offset().top - 100 }, 300);
        },
        
        /**
         * Mostra notifica
         */
        showNotification: function(message, type = 'info') {
            const $notification = $(`
                <div class="btr-notification btr-notification-${type}">
                    ${message}
                </div>
            `);
            
            $('body').append($notification);
            
            setTimeout(function() {
                $notification.fadeIn();
            }, 100);
            
            setTimeout(function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },
        
        /**
         * Mostra loading
         */
        showLoading: function() {
            if (!this.elements.loadingOverlay) {
                this.elements.loadingOverlay = $('<div class="btr-loading-overlay"><div class="spinner"></div></div>');
                $('body').append(this.elements.loadingOverlay);
            }
            this.elements.loadingOverlay.fadeIn();
        },
        
        /**
         * Nascondi loading
         */
        hideLoading: function() {
            if (this.elements.loadingOverlay) {
                this.elements.loadingOverlay.fadeOut();
            }
        }
    };
    
    // Inizializza quando DOM è pronto
    $(document).ready(function() {
        console.log('[BTR] Document ready - Inizializzazione multi-step form...');
        
        // Aggiungi un piccolo delay per assicurarsi che tutto sia caricato
        setTimeout(function() {
            console.log('[BTR] Tentativo inizializzazione dopo delay...');
            BTRMultiStepForm.init();
            
            // Debug: verifica cosa è stato creato
            setTimeout(function() {
                console.log('[BTR] Debug finale:');
                console.log('- Wrapper multi-step:', $('.btr-multistep-wrapper').length);
                console.log('- Indicatore step:', $('.btr-steps-indicator').length);
                console.log('- Pulsante Continua:', $('.btr-btn-continue').length);
                console.log('- Step 1:', $('.btr-step-1').length);
                console.log('- Step 2:', $('.btr-step-2').length);
            }, 1000);
        }, 100);
    });
    
})(jQuery);