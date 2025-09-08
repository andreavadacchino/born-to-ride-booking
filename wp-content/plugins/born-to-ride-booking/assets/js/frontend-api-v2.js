/**
 * Frontend API v2 - Canonical Handler per BTR Born to Ride Booking
 * Implementa il pattern "booking_data_json as Single Source of Truth"
 * 
 * @version 1.0.143
 * @author BTR Team
 */

(function($) {
    'use strict';
    
    /**
     * Invia preventivo usando handler canonico v2
     * Invia SOLO: action, nonce, booking_data_json
     */
    window.submitPreventivoCanonico = function() {
        console.log('[BTR v2] 🚀 Invio preventivo canonico...');
        
        try {
            // 1. Raccogli tutti i dati in formato JSON strutturato
            const bookingData = collectAllBookingDataV2();
            
            // 2. Validazione client-side base
            if (!validateBookingDataV2(bookingData)) {
                showErrorMessage('Dati preventivo non validi. Controllare i campi obbligatori.');
                return false;
            }
            
            // 3. Costruisci payload pulito - SOLO 3 campi
            const payload = {
                action: 'btr_create_preventivo_v2',  // Nuovo handler canonico
                nonce: btr_booking_form.nonce,
                booking_data_json: JSON.stringify(bookingData)
            };
            
            console.log('[BTR v2] 📤 Payload pulito (3 campi):', {
                action: payload.action,
                nonce: payload.nonce ? '✓ presente' : '✗ mancante',
                booking_data_json_size: payload.booking_data_json.length + ' chars'
            });
            
            // 4. Debug: log struttura dati (solo se debug attivo)
            if (window.btrBooking?.debug === true) {
                console.group('[BTR v2] 📋 Struttura booking_data_json');
                console.log('Customer:', bookingData.customer);
                console.log('Package:', bookingData.package);
                console.log('Participants:', bookingData.participants);
                console.log('Rooms count:', bookingData.rooms?.length || 0);
                console.log('Anagrafici count:', bookingData.anagrafici?.length || 0);
                console.log('Extra costs count:', Object.keys(bookingData.extra_costs || {}).length);
                console.log('Insurances count:', bookingData.insurances?.length || 0);
                console.groupEnd();
            }
            
            // 5. Invio AJAX pulito
            $.ajax({
                url: btr_booking_form.ajax_url,
                type: 'POST',
                data: payload,
                dataType: 'json',
                beforeSend: function() {
                    // Mostra loader
                    showLoadingState(true);
                },
                success: function(response) {
                    console.log('[BTR v2] ✅ Risposta server:', response);
                    
                    if (response.success) {
                        handleSuccessfulSubmission(response.data);
                    } else {
                        handleSubmissionError(response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[BTR v2] ❌ Errore AJAX:', { status, error, xhr });
                    handleAjaxError(xhr, status, error);
                },
                complete: function() {
                    showLoadingState(false);
                }
            });
            
        } catch (error) {
            console.error('[BTR v2] ❌ Errore critico:', error);
            showErrorMessage('Errore interno. Riprovare.');
            showLoadingState(false);
            return false;
        }
        
        return true;
    };
    
    /**
     * Raccoglie tutti i dati booking in formato canonico v2
     * @returns {Object} Struttura dati completa per booking_data_json
     */
    function collectAllBookingDataV2() {
        return {
            // Dati cliente
            customer: {
                name: getCustomerName(),
                email: getCustomerEmail(),
                phone: getCustomerPhone()
            },
            
            // Dati pacchetto
            package: {
                id: parseInt($('input[name="btr_package_id"]').val()) || 0,
                product_id: parseInt($('input[name="btr_product_id"]').val()) || 0,
                variant_id: parseInt($('input[name="selected_variant_id"]').val()) || 0,
                date_ranges_id: parseInt($('input[name="btr_date_ranges_id"]').val()) || 0,
                tipologia: $('input[name="btr_tipologia_prenotazione"]').val() || '',
                durata: parseInt($('input[name="btr_durata"]').val()) || 1,
                nome: $('input[name="btr_nome_pacchetto"]').val() || ''
            },
            
            // Partecipanti previsti
            participants: {
                adults: parseInt($('#btr_num_adults').val()) || 0,
                child_f1: parseInt($('#btr_num_children_f1').val()) || 0,
                child_f2: parseInt($('#btr_num_children_f2').val()) || 0,
                child_f3: parseInt($('#btr_num_children_f3').val()) || 0,
                child_f4: parseInt($('#btr_num_children_f4').val()) || 0,
                infants: parseInt($('#btr_num_infants').val()) || 0
            },
            
            // Date
            dates: {
                selected_date: $('#btr_selected_date').val() || '',
                check_in: extractCheckInDate(),
                check_out: extractCheckOutDate()
            },
            
            // Camere selezionate
            rooms: collectRoomsDataV2(),
            
            // Notti extra
            extra_nights: collectExtraNightsDataV2(),
            
            // Anagrafici dettagliati
            anagrafici: collectAnagraficiDataV2(),
            
            // Costi extra
            extra_costs: collectExtraCostsDataV2(),
            
            // Assicurazioni
            insurances: collectInsurancesDataV2(),
            
            // Totali frontend (per validazione)
            totals: {
                rooms_total: getTotaleCamera(),
                extra_costs_total: getTotaleCostiExtra(),
                insurances_total: getTotaleAssicurazioni(),
                grand_total: getTotaleGenerale()
            },
            
            // Metadata
            metadata: {
                version: '1.0.143',
                frontend_timestamp: new Date().toISOString(),
                user_agent: navigator.userAgent.substring(0, 100)
            }
        };
    }
    
    /**
     * Validazione base dati booking lato client
     */
    function validateBookingDataV2(bookingData) {
        // Validazioni critiche
        if (!bookingData.customer.email || !bookingData.customer.name) {
            console.error('[BTR v2] ❌ Dati cliente mancanti');
            return false;
        }
        
        if (!bookingData.package.id || bookingData.package.id <= 0) {
            console.error('[BTR v2] ❌ Package ID non valido');
            return false;
        }
        
        const totalParticipants = Object.values(bookingData.participants).reduce((a, b) => a + b, 0);
        if (totalParticipants <= 0) {
            console.error('[BTR v2] ❌ Nessun partecipante specificato');
            return false;
        }
        
        if (!bookingData.anagrafici || bookingData.anagrafici.length === 0) {
            console.error('[BTR v2] ❌ Anagrafici mancanti');
            return false;
        }
        
        // Validazione coerenza partecipanti vs anagrafici
        if (totalParticipants !== bookingData.anagrafici.length) {
            console.warn('[BTR v2] ⚠️ Mismatch partecipanti:', totalParticipants, 'vs anagrafici:', bookingData.anagrafici.length);
            // Non bloccare, lascia che il server gestisca
        }
        
        console.log('[BTR v2] ✅ Validazione client-side passata');
        return true;
    }
    
    // Helper functions per raccolta dati (implementazioni semplificate)
    function getCustomerName() {
        return $('input[name*="[nome]"]').first().val() || '';
    }
    
    function getCustomerEmail() {
        return $('input[name*="[email]"]').first().val() || '';
    }
    
    function getCustomerPhone() {
        return $('input[name*="[telefono]"]').first().val() || '';
    }
    
    function extractCheckInDate() {
        const selectedDate = $('#btr_selected_date').val();
        if (selectedDate) {
            const match = selectedDate.match(/(\d+)\s*-\s*(\d+)\s+([^\s]+)\s+(\d+)/);
            if (match) {
                return `${match[1]} ${match[3]} ${match[4]}`;
            }
        }
        return '';
    }
    
    function extractCheckOutDate() {
        const selectedDate = $('#btr_selected_date').val();
        if (selectedDate) {
            const match = selectedDate.match(/(\d+)\s*-\s*(\d+)\s+([^\s]+)\s+(\d+)/);
            if (match) {
                return `${match[2]} ${match[3]} ${match[4]}`;
            }
        }
        return '';
    }
    
    function collectRoomsDataV2() {
        // Riutilizza la logica esistente ma pulita
        if (window.collectRoomsData && typeof window.collectRoomsData === 'function') {
            return window.collectRoomsData();
        }
        return [];
    }
    
    function collectExtraNightsDataV2() {
        return {
            enabled: getExtraNightFlag() === 1,
            quantity: getExtraNightQuantity(),
            date: calculateExtraNightDate(),
            price_per_person: getExtraNightPricePerPerson(),
            total_cost: getExtraNightTotalCost()
        };
    }
    
    function collectAnagraficiDataV2() {
        // Riutilizza la logica esistente
        if (window.collectAnagraficiData && typeof window.collectAnagraficiData === 'function') {
            return window.collectAnagraficiData();
        }
        return [];
    }
    
    function collectExtraCostsDataV2() {
        // Converte il formato esistente in struttura pulita
        const extraCosts = [];
        if (window.btrBookingState && window.btrBookingState.costi_extra) {
            for (const [slug, data] of Object.entries(window.btrBookingState.costi_extra)) {
                extraCosts.push({
                    slug: slug,
                    name: data.nome,
                    unit_price: data.importo_unitario,
                    quantity: data.count,
                    total_amount: data.totale,
                    participants: data.partecipanti || []
                });
            }
        }
        return extraCosts;
    }
    
    function collectInsurancesDataV2() {
        // Implementazione semplificata per assicurazioni
        const insurances = [];
        $('input[name*="assicurazioni"]:checked').each(function() {
            const $input = $(this);
            insurances.push({
                type: $input.attr('name').match(/\[([^\]]+)\]$/)?.[1] || 'unknown',
                selected: true,
                amount: parseFloat($input.data('amount')) || 0
            });
        });
        return insurances;
    }
    
    // Helper functions per totali (da implementare o riutilizzare esistenti)
    function getTotaleCamera() {
        return window.btrBookingState?.totale_camere || 0;
    }
    
    function getTotaleCostiExtra() {
        return window.btrBookingState?.totale_costi_extra || 0;
    }
    
    function getTotaleAssicurazioni() {
        // Da implementare
        return 0;
    }
    
    function getTotaleGenerale() {
        return window.btrBookingState?.totale_generale || 0;
    }
    
    // Helper functions per extra nights
    function getExtraNightFlag() {
        return $('#extra-night-checkbox').is(':checked') ? 1 : 0;
    }
    
    function getExtraNightQuantity() {
        return parseInt($('#btr_extra_nights_quantity').val()) || 0;
    }
    
    function calculateExtraNightDate() {
        const checkIn = extractCheckInDate();
        if (checkIn) {
            try {
                const date = new Date(checkIn);
                date.setDate(date.getDate() - 1);
                return date.toISOString().split('T')[0];
            } catch (e) {
                console.error('[BTR v2] Errore calcolo data extra night:', e);
            }
        }
        return '';
    }
    
    function getExtraNightPricePerPerson() {
        return parseFloat($('#extra-night-pp').data('price')) || 0;
    }
    
    function getExtraNightTotalCost() {
        return parseFloat($('#extra-night-total').text()) || 0;
    }
    
    // Gestione risposta server
    function handleSuccessfulSubmission(data) {
        console.log('[BTR v2] ✅ Preventivo creato con successo:', data);
        
        // Mostra messaggio successo
        showSuccessMessage(`Preventivo #${data.preventivo_id} creato con successo! Totale: €${data.total}`);
        
        // Eventuali warnings
        if (data.warnings && data.warnings.length > 0) {
            console.warn('[BTR v2] ⚠️ Warnings:', data.warnings);
            data.warnings.forEach(warning => {
                showWarningMessage(warning);
            });
        }
        
        // Redirect o altre azioni
        if (data.redirect_url) {
            setTimeout(() => {
                window.location.href = data.redirect_url;
            }, 2000);
        }
    }
    
    function handleSubmissionError(data) {
        console.error('[BTR v2] ❌ Errore creazione preventivo:', data);
        
        let errorMessage = 'Errore durante la creazione del preventivo.';
        if (data.message) {
            errorMessage = data.message;
        }
        
        // Errori specifici
        if (data.code === 'PEOPLE_MISMATCH') {
            errorMessage = 'Numero di partecipanti non coerente. Verificare i dati inseriti.';
        } else if (data.code === 'INVALID_VARIATION') {
            errorMessage = 'Configurazione camera non valida. Ricaricare la pagina e riprovare.';
        }
        
        showErrorMessage(errorMessage);
    }
    
    function handleAjaxError(xhr, status, error) {
        console.error('[BTR v2] ❌ Errore AJAX:', { status, error, response: xhr.responseText });
        showErrorMessage('Errore di comunicazione con il server. Verificare la connessione.');
    }
    
    // UI Helper functions
    function showLoadingState(isLoading) {
        if (isLoading) {
            $('.btr-submit-button').prop('disabled', true).text('Invio in corso...');
        } else {
            $('.btr-submit-button').prop('disabled', false).text('Conferma Preventivo');
        }
    }
    
    function showSuccessMessage(message) {
        // Implementazione UI per messaggio successo
        console.log('[BTR v2] ✅ SUCCESS:', message);
    }
    
    function showErrorMessage(message) {
        // Implementazione UI per messaggio errore
        console.error('[BTR v2] ❌ ERROR:', message);
    }
    
    function showWarningMessage(message) {
        // Implementazione UI per messaggio warning
        console.warn('[BTR v2] ⚠️ WARNING:', message);
    }
    
    // Auto-inizializzazione quando il DOM è pronto
    $(document).ready(function() {
        console.log('[BTR v2] 🚀 Frontend API v2 inizializzato');
        
        // Sostituisci il handler del form principale con quello canonico
        if (window.btrBooking?.enableCanonicalV2 === true) {
            console.log('[BTR v2] 🔄 Canonical handler attivato');
            
            // Intercetta submit form principale
            $('form[data-btr-booking-form]').on('submit', function(e) {
                e.preventDefault();
                submitPreventivoCanonico();
                return false;
            });
        }
    });
    
})(jQuery);