/**
 * BTR Unified Calculator Frontend Integration
 * 
 * Integra il frontend con l'Unified Calculator v2.0
 * per risolvere definitivamente il problema split-brain
 * 
 * @version 1.0.201
 */

// Configurazione globale
window.btrUnifiedCalculatorConfig = window.btrUnifiedCalculatorConfig || {};

jQuery(document).ready(function($) {
    
    // Verifica se l'Unified Calculator è attivo
    if (!window.btrUnifiedCalculatorConfig.unifiedCalculatorEnabled) {
        console.log('[BTR] Unified Calculator v2.0 disattivo - usando sistema legacy');
        return;
    }
    
    console.log('[BTR] Unified Calculator v2.0 ATTIVO - Split-brain fix abilitato');
    
    /**
     * Estende il booking state con metodi Unified Calculator
     */
    if (window.btrBookingState) {
        
        // METODO PRINCIPALE: Validazione con Unified Calculator
        window.btrBookingState.validateWithUnifiedCalculator = function() {
            const calculationData = this.getUnifiedCalculatorData();
            
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: window.btrUnifiedCalculatorConfig.restUrl + 'validate',
                    method: 'POST',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', window.btrUnifiedCalculatorConfig.nonce);
                    },
                    data: {
                        frontend_total: this.totale_generale,
                        calculation_data: JSON.stringify(calculationData)
                    },
                    success: (response) => {
                        console.log('[UNIFIED CALCULATOR v2.0] Validazione:', response);
                        
                        if (!response.success) {
                            console.error('[UNIFIED CALCULATOR] Errore backend:', response.error);
                            reject(new Error(response.error));
                            return;
                        }
                        
                        const data = response.data;
                        
                        if (!data.is_valid) {
                            console.warn('[SPLIT-BRAIN DETECTOR] Discrepanza rilevata:', {
                                frontend: data.frontend_total,
                                backend: data.backend_total,
                                differenza: data.difference,
                                percentuale: data.percentage_diff + '%',
                                dettagli: data.backend_calculation
                            });
                            
                            // Auto-correzione se abilitata
                            if (window.btrUnifiedCalculatorConfig.autoCorrect !== false) {
                                this.updateFromBackendCalculation(data.backend_calculation);
                                $(document).trigger('btr:split-brain:corrected', [data]);
                                
                                // Notifica visuale di correzione
                                this.showSplitBrainCorrectionNotice(data);
                            }
                            
                            // Mostra warning se abilitato
                            if (window.btrUnifiedCalculatorConfig.showWarnings === true) {
                                this.showCalculationWarning(data);
                            }
                        }
                        
                        resolve(data);
                    }.bind(this),
                    error: (xhr, status, error) => {
                        console.error('[UNIFIED CALCULATOR] Errore validazione:', error);
                        // Non bloccare il flusso per errori di rete
                        resolve({ is_valid: true, note: 'Validazione offline' });
                    }
                });
            });
        };
        
        // Prepara dati per Unified Calculator
        window.btrBookingState.getUnifiedCalculatorData = function() {
            // Recupera package ID dall'elemento del form
            const packageId = parseInt($('#btr_booking_form').data('package-id')) || 
                             parseInt($('[data-package-id]').first().data('package-id')) || 
                             parseInt($('#package_id').val()) || 0;
            
            // Recupera dati partecipanti
            const adults = parseInt($('#adults').val()) || this.num_adults || 0;
            const children = {
                f1: parseInt($('#children_f1').val()) || 0,
                f2: parseInt($('#children_f2').val()) || 0,
                f3: parseInt($('#children_f3').val()) || 0,
                f4: parseInt($('#children_f4').val()) || 0
            };
            
            // Recupera dati camere selezionate
            const rooms = this.getSelectedRoomsData();
            
            // Recupera notti extra
            const extraNights = parseInt($('#extra_nights').val()) || window.btrExtraNightsCount || 0;
            
            // Recupera costi extra
            const extraCosts = this.getExtraCostsData();
            
            return {
                package_id: packageId,
                participants: {
                    adults: adults,
                    children: children
                },
                rooms: rooms,
                extra_nights: extraNights,
                extra_costs: extraCosts
            };
        };
        
        // Estrae dati camere selezionate
        window.btrBookingState.getSelectedRoomsData = function() {
            const rooms = [];
            
            // Cerca elementi room nel DOM
            $('.room-selector, .camera-selezionata, [data-room-type]').each(function() {
                const $room = $(this);
                const roomType = $room.data('room-type') || $room.find('[data-room-type]').data('room-type') || 'doppia';
                
                const roomData = {
                    type: roomType,
                    adults: parseInt($room.find('.adults-count, [name*="adults"]').val()) || 0,
                    children: {
                        f1: parseInt($room.find('.children-f1-count, [name*="f1"]').val()) || 0,
                        f2: parseInt($room.find('.children-f2-count, [name*="f2"]').val()) || 0,
                        f3: parseInt($room.find('.children-f3-count, [name*="f3"]').val()) || 0,
                        f4: parseInt($room.find('.children-f4-count, [name*="f4"]').val()) || 0
                    }
                };
                
                // Aggiungi solo se la camera ha occupanti
                const totalOccupants = roomData.adults + 
                    roomData.children.f1 + roomData.children.f2 + 
                    roomData.children.f3 + roomData.children.f4;
                    
                if (totalOccupants > 0) {
                    rooms.push(roomData);
                }
            });
            
            // Fallback: crea camera di default se nessuna trovata
            if (rooms.length === 0) {
                rooms.push({
                    type: 'doppia',
                    adults: this.num_adults || adults,
                    children: {
                        f1: parseInt($('#children_f1').val()) || 0,
                        f2: parseInt($('#children_f2').val()) || 0,
                        f3: parseInt($('#children_f3').val()) || 0,
                        f4: parseInt($('#children_f4').val()) || 0
                    }
                });
            }
            
            return rooms;
        };
        
        // Estrae dati costi extra
        window.btrBookingState.getExtraCostsData = function() {
            const extraCosts = [];
            
            // Converti costi_extra object in array per Unified Calculator
            for (const slug in this.costi_extra) {
                const cost = this.costi_extra[slug];
                extraCosts.push({
                    name: cost.nome || slug,
                    price: cost.importo_unitario || 0,
                    quantity: cost.count || 1,
                    applies_to: 'all' // Default: si applica a tutti
                });
            }
            
            return extraCosts;
        };
        
        // Aggiorna stato frontend con calcolo backend
        window.btrBookingState.updateFromBackendCalculation = function(backendData) {
            this.totale_camere = backendData.totale_camere;
            this.totale_costi_extra = backendData.totale_costi_extra;
            this.totale_generale = backendData.totale_generale;
            
            // Aggiorna anche il display
            this.updateTotalDisplay();
            
            console.log('[SPLIT-BRAIN CORRECTED] Frontend sincronizzato con backend:', backendData);
        };
        
        // Mostra warning discrepanza calcoli
        window.btrBookingState.showCalculationWarning = function(data) {
            const message = `ATTENZIONE: Rilevata discrepanza nei calcoli!\n` +
                          `Frontend: €${data.frontend_total.toFixed(2)}\n` +
                          `Backend: €${data.backend_total.toFixed(2)}\n` +
                          `Differenza: €${data.difference.toFixed(2)} (${data.percentage_diff.toFixed(2)}%)`;
                          
            console.warn(message);
            
            // Mostra notification nel UI se disponibile
            if (typeof window.showNotification === 'function') {
                window.showNotification(message, 'warning');
            }
        };
        
        // Mostra notifica correzione split-brain
        window.btrBookingState.showSplitBrainCorrectionNotice = function(data) {
            if (window.btrUnifiedCalculatorConfig.debugMode) {
                const $notice = $(`
                    <div class="btr-split-brain-notice" style="
                        position: fixed; top: 20px; right: 20px; 
                        background: #28a745; color: white; padding: 10px 15px; 
                        border-radius: 5px; z-index: 10000; max-width: 300px;
                        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
                    ">
                        <strong>✅ Split-Brain Corretto!</strong><br>
                        <small>Differenza: €${data.difference.toFixed(2)} (${data.percentage_diff.toFixed(2)}%)</small>
                    </div>
                `);
                
                $('body').append($notice);
                
                // Rimuovi dopo 3 secondi
                setTimeout(() => {
                    $notice.fadeOut(500, () => $notice.remove());
                }, 3000);
            }
        };
        
        // Aggiorna display totali
        window.btrBookingState.updateTotalDisplay = function() {
            const formattedTotal = btrFormatPrice(this.totale_generale);
            $('.total-price, .totale-generale, [data-total-price]').each(function() {
                $(this).text(formattedTotal);
            });
        };
        
        // Hook nel metodo recalculateTotal esistente
        const originalRecalculateTotal = window.btrBookingState.recalculateTotal;
        window.btrBookingState.recalculateTotal = function() {
            // Chiama il metodo originale
            originalRecalculateTotal.call(this);
            
            // Se validazione frontend è attiva, valida ogni 2 secondi
            if (window.btrUnifiedCalculatorConfig.frontendValidationEnabled) {
                clearTimeout(this._validationTimer);
                this._validationTimer = setTimeout(() => {
                    this.validateWithUnifiedCalculator().catch(error => {
                        console.log('[UNIFIED CALCULATOR] Validazione differita fallita (normale se offline):', error);
                    });
                }, 2000);
            }
        };
        
        console.log('[BTR] Unified Calculator frontend integration caricata');
    }
    
    // Event handlers per debugging
    if (window.btrUnifiedCalculatorConfig.debugMode) {
        $(document).on('btr:split-brain:corrected', function(event, data) {
            console.group('[SPLIT-BRAIN DEBUG] Correzione applicata');
            console.log('Dati discrepanza:', data);
            console.log('Stato aggiornato:', window.btrBookingState);
            console.groupEnd();
        });
        
        $(document).on('btr:state:updated', function(event, state) {
            console.log('[BTR STATE] Aggiornamento:', state);
        });
    }
});

/**
 * Helper per formattazione prezzi (se non disponibile)
 */
if (typeof btrFormatPrice !== 'function') {
    window.btrFormatPrice = function(amount, decimals = 2, showCurrency = true) {
        amount = parseFloat(amount) || 0;
        
        const formatted = amount.toLocaleString('it-IT', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
        
        return showCurrency ? '€' + formatted : formatted;
    };
}