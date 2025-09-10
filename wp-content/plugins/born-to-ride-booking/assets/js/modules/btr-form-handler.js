/**
 * BTR Form Handler - Gestione avanzata form multi-step
 * 
 * @package Born_To_Ride_Booking
 * @version 3.0.0
 */

(function($, window, document) {
    'use strict';

    /**
     * BTR Form Handler Class
     */
    class BTRFormHandler {
        
        constructor(stateManager, calculator) {
            this.version = '3.0.0';
            this.stateManager = stateManager;
            this.calculator = calculator;
            this.currentStep = 1;
            this.totalSteps = 3;
            this.validationEnabled = true;
            this.autoSave = true;
            this.formElements = {};
            
            this.init();
        }
        
        /**
         * Inizializza il form handler
         */
        init() {
            this.cacheFormElements();
            this.bindFormEvents();
            this.setupValidation();
            this.setupAutoSave();
            this.initializeSteps();
            
            console.log('BTR Form Handler v3.0 initialized');
        }
        
        /**
         * Cache elementi form per performance
         */
        cacheFormElements() {
            this.formElements = {
                // Form principale
                bookingForm: $('#btr-booking-form, .btr-preventivo-form'),
                
                // Step containers
                steps: $('.btr-form-step'),
                stepIndicators: $('.step-indicator, .progress-step'),
                
                // Navigazione
                nextButton: $('.btn-next-step, .next-step'),
                prevButton: $('.btn-prev-step, .prev-step'),
                submitButton: $('.btn-submit, input[type="submit"]'),
                
                // Campi chiave
                participants: $('input[name^="num_"]'),
                rooms: $('.room-select, input[name^="room_"]'),
                extras: $('input[name^="extra_"], .extra-cost-checkbox'),
                personalData: $('input[name^="anagrafico_"], input[name^="participant_"]'),
                
                // Totali
                totalDisplays: $('.totale-generale, .total-amount'),
                priceBreakdown: $('.price-breakdown, .riepilogo-costi'),
                
                // Status elements
                loadingSpinner: $('.calculation-spinner'),
                errorMessages: $('.error-message, .form-error'),
                successMessages: $('.success-message, .form-success')
            };
        }
        
        /**
         * Bind eventi form
         */
        bindFormEvents() {
            const self = this;
            
            // Navigazione step
            this.formElements.nextButton.on('click', function(e) {
                e.preventDefault();
                self.nextStep();
            });
            
            this.formElements.prevButton.on('click', function(e) {
                e.preventDefault();
                self.prevStep();
            });
            
            // Submit form
            this.formElements.submitButton.on('click', function(e) {
                e.preventDefault();
                self.handleFormSubmit();
            });
            
            // Cambi partecipanti
            this.formElements.participants.on('change blur', function() {
                const field = $(this).attr('name');
                const value = parseInt($(this).val()) || 0;
                
                self.stateManager.updateState({
                    [field]: value
                });
                
                self.updateParticipantFields(field, value);
                self.calculator.triggerCalculation('participants_changed');
            });
            
            // Cambi camere
            this.formElements.rooms.on('change', function() {
                self.handleRoomChange($(this));
            });
            
            // Cambi costi extra
            this.formElements.extras.on('change', function() {
                self.handleExtraChange($(this));
            });
            
            // Dati anagrafici
            this.formElements.personalData.on('change blur', function() {
                self.handlePersonalDataChange($(this));
            });
            
            // Eventi State Manager
            this.stateManager.on('state.updated', (data) => {
                self.syncFormWithState(data);
            });
            
            // Eventi keyboard
            $(document).on('keydown', function(e) {
                if (e.which === 13 && !$(e.target).is('textarea')) {
                    e.preventDefault();
                    self.nextStep();
                }
            });
        }
        
        /**
         * Setup validazione form
         */
        setupValidation() {
            const self = this;
            
            // Validazione real-time
            this.formElements.bookingForm.find('input, select, textarea').on('blur', function() {
                if (self.validationEnabled) {
                    self.validateField($(this));
                }
            });
            
            // Validazione step
            this.stateManager.on('validation.required', () => {
                self.validateCurrentStep();
            });
        }
        
        /**
         * Setup auto-salvataggio
         */
        setupAutoSave() {
            const self = this;
            
            if (this.autoSave) {
                // Auto-save ogni 30 secondi
                setInterval(() => {
                    if (self.stateManager.isDirty()) {
                        self.stateManager.saveToSession();
                    }
                }, 30000);
                
                // Save prima di uscire dalla pagina
                $(window).on('beforeunload', function() {
                    if (self.stateManager.isDirty()) {
                        self.stateManager.saveToSession();
                    }
                });
            }
        }
        
        /**
         * Inizializza step
         */
        initializeSteps() {
            this.currentStep = this.getCurrentStepFromUrl() || 1;
            this.showStep(this.currentStep);
            this.updateStepIndicators();
        }
        
        /**
         * Avanza al prossimo step
         */
        async nextStep() {
            console.log('[FORM] Next step requested from step:', this.currentStep);
            
            // Valida step corrente
            const isValid = await this.validateCurrentStep();
            if (!isValid) {
                console.log('[FORM] Current step validation failed');
                return;
            }
            
            // Salva stato
            await this.saveCurrentStep();
            
            if (this.currentStep < this.totalSteps) {
                this.currentStep++;
                this.showStep(this.currentStep);
                this.updateStepIndicators();
                this.updateUrl();
                
                // Trigger evento
                this.stateManager.trigger('form.step.changed', {
                    step: this.currentStep,
                    direction: 'next'
                });
            } else {
                // Ultimo step - submit form
                this.handleFormSubmit();
            }
        }
        
        /**
         * Torna al step precedente
         */
        async prevStep() {
            console.log('[FORM] Previous step requested from step:', this.currentStep);
            
            if (this.currentStep > 1) {
                this.currentStep--;
                this.showStep(this.currentStep);
                this.updateStepIndicators();
                this.updateUrl();
                
                // Trigger evento
                this.stateManager.trigger('form.step.changed', {
                    step: this.currentStep,
                    direction: 'prev'
                });
            }
        }
        
        /**
         * Mostra step specifico
         */
        showStep(stepNumber) {
            console.log('[FORM] Showing step:', stepNumber);
            
            // Nascondi tutti gli step
            this.formElements.steps.hide().removeClass('active current');
            
            // Mostra step corrente
            const $currentStep = $(`.btr-form-step[data-step="${stepNumber}"], .step-${stepNumber}`);
            $currentStep.show().addClass('active current');
            
            // Aggiorna bottoni navigazione
            this.updateNavigationButtons();
            
            // Focus primo campo
            setTimeout(() => {
                $currentStep.find('input, select, textarea').filter(':visible').first().focus();
            }, 100);
        }
        
        /**
         * Aggiorna indicatori step
         */
        updateStepIndicators() {
            this.formElements.stepIndicators.each((index, element) => {
                const $indicator = $(element);
                const stepNum = index + 1;
                
                $indicator.removeClass('completed current');
                
                if (stepNum < this.currentStep) {
                    $indicator.addClass('completed');
                } else if (stepNum === this.currentStep) {
                    $indicator.addClass('current');
                }
            });
        }
        
        /**
         * Aggiorna bottoni navigazione
         */
        updateNavigationButtons() {
            // Bottone precedente
            this.formElements.prevButton.toggle(this.currentStep > 1);
            
            // Bottone successivo/submit
            if (this.currentStep < this.totalSteps) {
                this.formElements.nextButton.show().text('Avanti');
                this.formElements.submitButton.hide();
            } else {
                this.formElements.nextButton.hide();
                this.formElements.submitButton.show();
            }
        }
        
        /**
         * Valida step corrente
         */
        async validateCurrentStep() {
            const stepData = this.getCurrentStepData();
            const validationResult = await this.stateManager.validate(stepData, this.currentStep);
            
            if (!validationResult.isValid) {
                this.showValidationErrors(validationResult.errors);
                return false;
            }
            
            this.clearValidationErrors();
            return true;
        }
        
        /**
         * Ottieni dati step corrente
         */
        getCurrentStepData() {
            const $currentStep = this.formElements.steps.filter('.active');
            const data = {};
            
            $currentStep.find('input, select, textarea').each(function() {
                const $field = $(this);
                const name = $field.attr('name');
                const value = $field.val();
                
                if (name && value !== undefined) {
                    data[name] = value;
                }
            });
            
            return data;
        }
        
        /**
         * Salva step corrente
         */
        async saveCurrentStep() {
            const stepData = this.getCurrentStepData();
            
            // Aggiorna stato
            this.stateManager.updateState({
                [`step_${this.currentStep}_data`]: stepData,
                current_step: this.currentStep
            });
            
            // Salva in sessione
            await this.stateManager.saveToSession();
        }
        
        /**
         * Gestisce cambio camera
         */
        handleRoomChange($roomElement) {
            const roomId = $roomElement.val();
            const roomIndex = $roomElement.data('room-index') || 0;
            
            // Aggiorna stato
            this.stateManager.updateState({
                [`room_${roomIndex}_type`]: roomId
            });
            
            // Trigger calcolo
            this.calculator.triggerCalculation('room_changed');
            
            console.log('[FORM] Room changed:', roomIndex, roomId);
        }
        
        /**
         * Gestisce cambio costo extra
         */
        handleExtraChange($extraElement) {
            const isChecked = $extraElement.is(':checked');
            const extraSlug = $extraElement.data('extra-slug') || $extraElement.attr('name');
            const extraPrice = parseFloat($extraElement.data('price')) || 0;
            const extraName = $extraElement.data('name') || extraSlug;
            
            if (isChecked) {
                this.stateManager.setCostoExtra(extraSlug, extraPrice, extraName);
            } else {
                this.stateManager.removeCostoExtra(extraSlug);
            }
            
            // Trigger calcolo
            this.calculator.triggerCalculation('extra_changed');
            
            console.log('[FORM] Extra changed:', extraSlug, isChecked, extraPrice);
        }
        
        /**
         * Gestisce cambio dati anagrafici
         */
        handlePersonalDataChange($field) {
            const name = $field.attr('name');
            const value = $field.val();
            
            // Aggiorna stato
            this.stateManager.updateState({
                [name]: value
            });
            
            // Validazione field specifica
            this.validateField($field);
            
            console.log('[FORM] Personal data changed:', name, value);
        }
        
        /**
         * Aggiorna campi partecipanti
         */
        updateParticipantFields(field, value) {
            // Mostra/nascondi sezioni bambini
            if (field === 'num_children') {
                $('.children-details').toggle(value > 0);
                
                // Genera campi età bambini
                this.generateChildrenFields(value);
            }
            
            // Aggiorna contatori totali
            if (field.startsWith('num_')) {
                this.updateTotalParticipants();
            }
        }
        
        /**
         * Genera campi età bambini
         */
        generateChildrenFields(numChildren) {
            const $container = $('.children-ages-container, .bambini-eta-container');
            $container.empty();
            
            for (let i = 0; i < numChildren; i++) {
                const fieldHtml = `
                    <div class="child-age-field">
                        <label for="child_age_${i}">Età bambino ${i + 1}</label>
                        <select name="child_age_${i}" id="child_age_${i}" required>
                            <option value="">Seleziona età</option>
                            <option value="0-2">0-2 anni</option>
                            <option value="3-6">3-6 anni</option>
                            <option value="7-12">7-12 anni</option>
                            <option value="13-17">13-17 anni</option>
                        </select>
                    </div>
                `;
                $container.append(fieldHtml);
            }
        }
        
        /**
         * Aggiorna totale partecipanti
         */
        updateTotalParticipants() {
            const adults = parseInt(this.formElements.participants.filter('[name="num_adults"]').val()) || 0;
            const children = parseInt(this.formElements.participants.filter('[name="num_children"]').val()) || 0;
            const neonati = parseInt(this.formElements.participants.filter('[name="num_neonati"]').val()) || 0;
            
            const total = adults + children + neonati;
            
            $('.total-participants').text(total);
            this.stateManager.updateState({ total_participants: total });
        }
        
        /**
         * Sincronizza form con stato
         */
        syncFormWithState(stateData) {
            Object.entries(stateData).forEach(([key, value]) => {
                const $field = $(`[name="${key}"]`);
                if ($field.length > 0) {
                    if ($field.is(':checkbox')) {
                        $field.prop('checked', Boolean(value));
                    } else {
                        $field.val(value);
                    }
                }
            });
        }
        
        /**
         * Valida singolo campo
         */
        validateField($field) {
            const name = $field.attr('name');
            const value = $field.val();
            const isValid = this.stateManager.validateField(name, value);
            
            $field.closest('.form-field, .field-wrapper').toggleClass('has-error', !isValid);
            
            return isValid;
        }
        
        /**
         * Mostra errori validazione
         */
        showValidationErrors(errors) {
            this.clearValidationErrors();
            
            Object.entries(errors).forEach(([field, messages]) => {
                const $field = $(`[name="${field}"]`);
                const $wrapper = $field.closest('.form-field, .field-wrapper');
                
                $wrapper.addClass('has-error');
                
                const errorHtml = `<div class="field-error">${messages.join(', ')}</div>`;
                $wrapper.append(errorHtml);
            });
            
            // Scroll al primo errore
            const $firstError = $('.has-error').first();
            if ($firstError.length) {
                $firstError[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
        
        /**
         * Pulisci errori validazione
         */
        clearValidationErrors() {
            $('.has-error').removeClass('has-error');
            $('.field-error').remove();
        }
        
        /**
         * Gestisce submit form
         */
        async handleFormSubmit() {
            console.log('[FORM] Form submit requested');
            
            try {
                // Validazione finale
                const isValid = await this.validateEntireForm();
                if (!isValid) {
                    console.log('[FORM] Form validation failed');
                    return;
                }
                
                // Mostra loading
                this.showLoading();
                
                // Ottieni payload completo
                const payload = this.stateManager.getSubmitPayload();
                
                // Submit tramite AJAX
                const result = await window.btrAjaxClient.createPreventivo(payload);
                
                if (result.success) {
                    this.handleSubmitSuccess(result);
                } else {
                    this.handleSubmitError(result);
                }
                
            } catch (error) {
                console.error('[FORM] Submit error:', error);
                this.handleSubmitError(error);
            } finally {
                this.hideLoading();
            }
        }
        
        /**
         * Valida form intero
         */
        async validateEntireForm() {
            const formData = this.getAllFormData();
            const validationResult = await this.stateManager.validate(formData, 'all');
            
            if (!validationResult.isValid) {
                this.showValidationErrors(validationResult.errors);
                return false;
            }
            
            return true;
        }
        
        /**
         * Ottieni tutti i dati form
         */
        getAllFormData() {
            const data = {};
            
            this.formElements.bookingForm.find('input, select, textarea').each(function() {
                const $field = $(this);
                const name = $field.attr('name');
                let value = $field.val();
                
                if (name) {
                    if ($field.is(':checkbox')) {
                        value = $field.is(':checked');
                    }
                    data[name] = value;
                }
            });
            
            return data;
        }
        
        /**
         * Gestisce successo submit
         */
        handleSubmitSuccess(result) {
            console.log('[FORM] Submit successful:', result);
            
            // Mostra messaggio successo
            this.formElements.successMessages.show().find('.message-text').text(
                result.message || 'Prenotazione inviata con successo!'
            );
            
            // Redirect se necessario
            if (result.redirect_url) {
                setTimeout(() => {
                    window.location.href = result.redirect_url;
                }, 2000);
            }
            
            // Trigger evento
            this.stateManager.trigger('form.submitted', result);
        }
        
        /**
         * Gestisce errore submit
         */
        handleSubmitError(error) {
            console.error('[FORM] Submit error:', error);
            
            // Mostra messaggio errore
            this.formElements.errorMessages.show().find('.message-text').text(
                error.message || 'Errore durante l\'invio della prenotazione.'
            );
            
            // Trigger evento
            this.stateManager.trigger('form.error', error);
        }
        
        /**
         * Mostra loading
         */
        showLoading() {
            this.formElements.loadingSpinner.show();
            this.formElements.submitButton.prop('disabled', true);
        }
        
        /**
         * Nascondi loading
         */
        hideLoading() {
            this.formElements.loadingSpinner.hide();
            this.formElements.submitButton.prop('disabled', false);
        }
        
        /**
         * Ottieni step corrente da URL
         */
        getCurrentStepFromUrl() {
            const urlParams = new URLSearchParams(window.location.search);
            const step = parseInt(urlParams.get('step')) || 1;
            return Math.max(1, Math.min(step, this.totalSteps));
        }
        
        /**
         * Aggiorna URL con step corrente
         */
        updateUrl() {
            const url = new URL(window.location);
            url.searchParams.set('step', this.currentStep);
            window.history.replaceState(null, '', url);
        }
        
        /**
         * Reset form
         */
        reset() {
            this.currentStep = 1;
            this.formElements.bookingForm[0].reset();
            this.stateManager.reset();
            this.showStep(1);
            this.clearValidationErrors();
        }
        
        /**
         * Debug info
         */
        getDebugInfo() {
            return {
                version: this.version,
                currentStep: this.currentStep,
                totalSteps: this.totalSteps,
                validationEnabled: this.validationEnabled,
                autoSave: this.autoSave,
                formElementsCount: Object.keys(this.formElements).length
            };
        }
    }
    
    // Export globalmente
    window.BTRFormHandler = BTRFormHandler;
    
})(window.jQuery || window.$, window, document);