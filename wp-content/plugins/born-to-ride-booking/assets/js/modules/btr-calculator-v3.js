/**
 * BTR Calculator v3.0 - Calcoli frontend integrati con backend
 * 
 * @package Born_To_Ride_Booking
 * @version 3.0.0
 */

(function($, window, document) {
    'use strict';

    /**
     * BTR Calculator Class v3
     */
    class BTRCalculatorV3 {
        
        constructor(stateManager, ajaxClient) {
            this.version = '3.0.0';
            this.stateManager = stateManager;
            this.ajaxClient = ajaxClient;
            this.calculationQueue = [];
            this.isCalculating = false;
            this.debounceTimeout = null;
            this.lastCalculation = null;
            
            this.init();
        }
        
        /**
         * Inizializza il calculator
         */
        init() {
            // Bind eventi di calcolo
            this.bindCalculationEvents();
            
            // Setup debouncing per performance
            this.setupDebouncing();
            
            console.log('BTR Calculator v3.0 initialized');
        }
        
        /**
         * Bind eventi che triggherano calcoli
         */
        bindCalculationEvents() {
            const self = this;
            
            // Cambi nel numero di partecipanti
            $(document).on('change', 'input[name^="num_"], input[name$="_count"]', function() {
                self.triggerCalculation('participants_changed');
            });
            
            // Cambi nelle camere
            $(document).on('change', '.room-select, input[name^="room_"]', function() {
                self.triggerCalculation('rooms_changed');
            });
            
            // Cambi nei costi extra
            $(document).on('change', 'input[name^="extra_"], .extra-cost-checkbox', function() {
                self.triggerCalculation('extras_changed');
            });
            
            // Cambi nelle notti extra
            $(document).on('change', 'input[name="extra_nights"]', function() {
                self.triggerCalculation('extra_nights_changed');
            });
            
            // Listen per eventi State Manager
            this.stateManager.on('state.changed', (data) => {
                self.triggerCalculation('state_changed', data);
            });
        }
        
        /**
         * Setup debouncing per performance
         */
        setupDebouncing() {
            // Debounce calcoli per 300ms
            this.debouncedCalculate = this.debounce(this.performCalculation.bind(this), 300);
        }
        
        /**
         * Trigger calcolo con debouncing
         */
        triggerCalculation(reason = 'manual', data = null) {
            console.log('[CALC] Calculation triggered:', reason);
            
            // Clear previous timeout
            if (this.debounceTimeout) {
                clearTimeout(this.debounceTimeout);
            }
            
            // Add to queue
            this.calculationQueue.push({
                reason: reason,
                data: data,
                timestamp: Date.now()
            });
            
            // Schedule debounced calculation
            this.debouncedCalculate();
        }
        
        /**
         * Esegue calcolo effettivo
         */
        async performCalculation() {
            if (this.isCalculating) {
                console.log('[CALC] Calculation already in progress, skipping');
                return;
            }
            
            this.isCalculating = true;
            const startTime = Date.now();
            
            try {
                // Prendi ultimo item dalla queue
                const lastRequest = this.calculationQueue[this.calculationQueue.length - 1];
                this.calculationQueue = []; // Clear queue
                
                console.log('[CALC] Starting calculation for:', lastRequest?.reason);
                
                // Ottieni payload corrente dallo State Manager
                const payload = this.stateManager.getCalculationPayload();
                
                // Verifica se il payload è cambiato
                if (this.hasPayloadChanged(payload)) {
                    // Usa v3 endpoint se disponibile
                    const useV3 = this.shouldUseV3();
                    
                    let result;
                    if (useV3) {
                        result = await this.calculateV3(payload);
                    } else {
                        result = await this.calculateLegacy(payload);
                    }
                    
                    if (result.success) {
                        await this.processCalculationResult(result, payload);
                    } else {
                        this.handleCalculationError(result);
                    }
                } else {
                    console.log('[CALC] Payload unchanged, skipping calculation');
                }
                
                const duration = Date.now() - startTime;
                console.log(`[CALC] Calculation completed in ${duration}ms`);
                
            } catch (error) {
                console.error('[CALC] Calculation error:', error);
                this.handleCalculationError(error);
            } finally {
                this.isCalculating = false;
            }
        }
        
        /**
         * Calcolo v3 con Unified Calculator
         */
        async calculateV3(payload) {
            console.log('[CALC] Using v3 calculation endpoint');
            
            try {
                const result = await this.ajaxClient.calculateV3(payload, {
                    timeout: 10000 // 10 secondi per calcoli complessi
                });
                
                if (result.success) {
                    // Store per comparison
                    this.lastCalculation = {
                        payload: JSON.parse(JSON.stringify(payload)),
                        result: result,
                        timestamp: Date.now(),
                        version: 'v3'
                    };
                }
                
                return result;
                
            } catch (error) {
                console.error('[CALC] v3 calculation failed:', error);
                
                // Fallback a legacy se v3 fallisce
                console.log('[CALC] Falling back to legacy calculation');
                return await this.calculateLegacy(payload);
            }
        }
        
        /**
         * Calcolo legacy (fallback)
         */
        async calculateLegacy(payload) {
            console.log('[CALC] Using legacy calculation endpoint');
            
            const result = await this.ajaxClient.calculateLegacy(payload);
            
            if (result.success) {
                this.lastCalculation = {
                    payload: JSON.parse(JSON.stringify(payload)),
                    result: result,
                    timestamp: Date.now(),
                    version: 'legacy'
                };
            }
            
            return result;
        }
        
        /**
         * Processa risultato calcolo
         */
        async processCalculationResult(result, originalPayload) {
            console.log('[CALC] Processing calculation result:', result);
            
            // Aggiorna State Manager con risultati
            await this.updateStateWithResults(result);
            
            // Aggiorna UI
            this.updateUI(result);
            
            // Valida discrepanze
            this.validateCalculationConsistency(result, originalPayload);
            
            // Trigger eventi
            this.stateManager.trigger('calculation.completed', {
                result: result,
                payload: originalPayload,
                timestamp: Date.now()
            });
        }
        
        /**
         * Aggiorna State Manager con risultati
         */
        async updateStateWithResults(result) {
            const updates = {
                // Totali calcolati
                totale_generale: result.data?.totale_finale || result.totale_finale || 0,
                totale_camere: result.data?.totale_camere || 0,
                totale_costi_extra: result.data?.totale_costi_extra || 0,
                
                // Breakdown dettagliato
                breakdown: result.data?.breakdown || {},
                
                // Pricing per fasce età
                pricing: result.data?.pricing || {},
                
                // Stato calcolo
                last_calculation: {
                    timestamp: Date.now(),
                    version: result.data?.version || 'unknown',
                    duration: result.data?.calculation_time || 0
                }
            };
            
            // Aggiorna stato in batch
            this.stateManager.updateState(updates);
            
            // Salva stato se auto-save abilitato
            if (this.stateManager.config.autoSave) {
                await this.stateManager.saveToSession();
            }
        }
        
        /**
         * Aggiorna UI con risultati
         */
        updateUI(result) {
            const data = result.data || result;
            
            // Aggiorna totali principali
            this.updateTotals(data);
            
            // Aggiorna breakdown prezzi
            this.updatePriceBreakdown(data.breakdown || {});
            
            // Aggiorna validazioni
            this.updateValidationStatus(data.validations || {});
            
            // Aggiorna indicatori di stato
            this.updateStatusIndicators(result.success);
        }
        
        /**
         * Aggiorna totali UI
         */
        updateTotals(data) {
            const totaleFinale = data.totale_finale || 0;
            const totaleCamere = data.totale_camere || 0;
            const totaleCostiExtra = data.totale_costi_extra || 0;
            
            // Aggiorna elementi totali
            $('.totale-generale, .total-amount').text(this.formatPrice(totaleFinale));
            $('.totale-camere').text(this.formatPrice(totaleCamere));
            $('.totale-extra').text(this.formatPrice(totaleCostiExtra));
            
            // Aggiorna hidden inputs per form
            $('input[name="totale_finale"]').val(totaleFinale);
            $('input[name="totale_camere"]').val(totaleCamere);
            $('input[name="totale_costi_extra"]').val(totaleCostiExtra);
        }
        
        /**
         * Aggiorna breakdown prezzi
         */
        updatePriceBreakdown(breakdown) {
            const $breakdown = $('.price-breakdown, .riepilogo-costi');
            
            if ($breakdown.length && Object.keys(breakdown).length > 0) {
                let html = '<div class="breakdown-section">';
                
                // Breakdown per categoria
                Object.entries(breakdown).forEach(([category, items]) => {
                    if (Array.isArray(items) && items.length > 0) {
                        html += `<h4>${this.formatCategoryName(category)}</h4>`;
                        html += '<ul class="breakdown-items">';
                        
                        items.forEach(item => {
                            html += `
                                <li class="breakdown-item">
                                    <span class="item-name">${item.name || item.descrizione}</span>
                                    <span class="item-amount">${this.formatPrice(item.amount || item.importo)}</span>
                                </li>
                            `;
                        });
                        
                        html += '</ul>';
                    }
                });
                
                html += '</div>';
                $breakdown.html(html);
            }
        }
        
        /**
         * Aggiorna stato validazioni
         */
        updateValidationStatus(validations) {
            $('.validation-status').removeClass('valid invalid').addClass(
                validations.all_valid ? 'valid' : 'invalid'
            );
            
            // Mostra errori specifici
            Object.entries(validations.errors || {}).forEach(([field, errors]) => {
                const $field = $(`[name="${field}"], .field-${field}`);
                $field.closest('.form-field').toggleClass('has-error', errors.length > 0);
                
                if (errors.length > 0) {
                    $field.attr('title', errors.join(', '));
                }
            });
        }
        
        /**
         * Aggiorna indicatori di stato
         */
        updateStatusIndicators(success) {
            $('.calculation-status').toggleClass('success', success).toggleClass('error', !success);
            
            if (success) {
                $('.calculation-spinner').hide();
                $('.calculation-success').show();
            } else {
                $('.calculation-spinner').hide();
                $('.calculation-error').show();
            }
        }
        
        /**
         * Gestisce errori di calcolo
         */
        handleCalculationError(error) {
            console.error('[CALC] Calculation error:', error);
            
            // Mostra errore in UI
            $('.calculation-error').show().find('.error-message').text(
                error.message || 'Errore durante il calcolo'
            );
            
            // Trigger evento errore
            this.stateManager.trigger('calculation.error', {
                error: error,
                timestamp: Date.now()
            });
        }
        
        /**
         * Verifica se payload è cambiato
         */
        hasPayloadChanged(currentPayload) {
            if (!this.lastCalculation) return true;
            
            const lastPayload = this.lastCalculation.payload;
            return JSON.stringify(currentPayload) !== JSON.stringify(lastPayload);
        }
        
        /**
         * Determina se usare v3 endpoint
         */
        shouldUseV3() {
            // Feature flag check
            if (window.btr_features?.v3_calculator === false) {
                return false;
            }
            
            // Fallback se v3 non disponibile
            if (!this.ajaxClient.endpoints.calculate) {
                return false;
            }
            
            return true;
        }
        
        /**
         * Valida consistenza calcoli
         */
        validateCalculationConsistency(result, payload) {
            if (!result.data?.validation_info) return;
            
            const validation = result.data.validation_info;
            
            if (validation.discrepancy_detected) {
                console.warn('[CALC] Calculation discrepancy detected:', validation);
                
                // Mostra warning in UI
                $('.calculation-warning').show().find('.warning-message').text(
                    `Discrepanza rilevata: €${validation.discrepancy.toFixed(2)}`
                );
            }
        }
        
        /**
         * Formatta prezzo
         */
        formatPrice(amount, showCurrency = true) {
            const num = parseFloat(amount) || 0;
            const formatted = num.toLocaleString('it-IT', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            
            return showCurrency ? `€ ${formatted}` : formatted;
        }
        
        /**
         * Formatta nome categoria
         */
        formatCategoryName(category) {
            const names = {
                'rooms': 'Camere',
                'extras': 'Costi Extra',
                'children': 'Bambini',
                'extra_nights': 'Notti Extra'
            };
            
            return names[category] || category.replace('_', ' ').toUpperCase();
        }
        
        /**
         * Debounce utility
         */
        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
        
        /**
         * Force recalculation
         */
        async forceRecalculation(reason = 'manual') {
            this.lastCalculation = null; // Reset per forzare calcolo
            await this.triggerCalculation(reason);
        }
        
        /**
         * Debug info
         */
        getDebugInfo() {
            return {
                version: this.version,
                isCalculating: this.isCalculating,
                queueLength: this.calculationQueue.length,
                lastCalculation: this.lastCalculation ? {
                    timestamp: this.lastCalculation.timestamp,
                    version: this.lastCalculation.version
                } : null
            };
        }
    }
    
    // Export globalmente
    window.BTRCalculatorV3 = BTRCalculatorV3;
    
})(window.jQuery || window.$, window, document);