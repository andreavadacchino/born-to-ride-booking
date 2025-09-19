jQuery(document).ready(function ($) {


    // ===== FIX v1.0.226: PULIZIA CACHE CORROTTA - ELIMINA DATI FANTASMA =====
    console.log("[BTR v1.0.226] Cache Manager Attivo - Eliminazione dati fantasma...");
    
    // PULIZIA IMMEDIATA cache corrotta
    (function pulisciCacheCorretta() {
        const currentUrl = window.location.href;
        const preventivo_id = jQuery("#preventivo_id").val() || jQuery("[name=preventivo_id]").val();
        const lastPreventivoId = localStorage.getItem("btr_last_preventivo_id");
        
        // FORZA pulizia sempre (per fix immediato)
        console.log("[BTR CACHE FIX] === ELIMINAZIONE DATI FANTASMA ATTIVA ===");
        
        // Lista chiavi corrotte da eliminare
        const corruptedKeys = ["btr_checkout_data", "btr_state", "btr_anagrafici_data", "btr_cliente_nome", "btr_cliente_email", "btr_emergency_state"];
        
        // Pulisci localStorage
        corruptedKeys.forEach(key => {
            if (localStorage.getItem(key)) {
                console.log("[ELIMINATO] localStorage." + key + ":", localStorage.getItem(key));
                localStorage.removeItem(key);
            }
        });
        
        // Pulisci TUTTO ciò che contiene "btr"
        const allKeys = [];
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key && (key.includes("btr") || key.includes("checkout"))) {
                allKeys.push(key);
            }
        }
        allKeys.forEach(key => {
            console.log("[PURGE] localStorage." + key);
            localStorage.removeItem(key);
        });
        
        // Pulisci sessionStorage
        try {
            for (let i = sessionStorage.length - 1; i >= 0; i--) {
                const key = sessionStorage.key(i);
                if (key && (key.includes("btr") || key.includes("checkout"))) {
                    console.log("[PURGE] sessionStorage." + key);
                    sessionStorage.removeItem(key);
                }
            }
        } catch(e) { console.error("Errore pulizia session:", e); }
        
        if (preventivo_id) {
            localStorage.setItem("btr_last_preventivo_id", preventivo_id);
            console.log("[SALVATO] Nuovo preventivo_id:", preventivo_id);
        }
        
        console.log("[BTR CACHE FIX] ✅ Cache pulita! Dati fantasma ELIMINATI.");
    })();
    // ===== JSON STATE MANAGER - SINGLE SOURCE OF TRUTH =====
    // Stato centralizzato per tutti i calcoli del booking
    window.btrBookingState = {
        // Dati base pacchetto
        totale_camere: 0,
        prezzo_base_per_persona: 0,
        num_adults: 0,
        num_children: 0,
        num_neonati: 0,
        notti: 1,
        extra_nights: 0,
        
        // Costi extra strutturati
        costi_extra: {},
        
        // Totali calcolati
        totale_costi_extra: 0,
        totale_riduzioni: 0,
        totale_assicurazioni: 0,
        totale_generale: 0,
        
        // Metodi per aggiornare lo stato
        updateTotalesCamere: function(totale) {
            this.totale_camere = parseFloat(totale) || 0;
            this.recalculateTotal();
            console.log('[STATE] Totale camere aggiornato:', this.totale_camere);
        },
        
        setCostoExtra: function(slug, importo, nome, partecipante = null) {
            if (!this.costi_extra[slug]) {
                this.costi_extra[slug] = {
                    nome: nome || slug,
                    importo_unitario: importo,
                    count: 0,
                    totale: 0,
                    partecipanti: []
                };
            }
            
            // Aggiorna o aggiungi partecipante
            if (partecipante && !this.costi_extra[slug].partecipanti.includes(partecipante)) {
                this.costi_extra[slug].partecipanti.push(partecipante);
            }
            
            this.costi_extra[slug].count = this.costi_extra[slug].partecipanti.length || 1;
            this.costi_extra[slug].totale = this.costi_extra[slug].importo_unitario * this.costi_extra[slug].count;
            
            this.recalculateTotal();
            console.log('[STATE] Costo extra impostato:', slug, this.costi_extra[slug]);
        },
        
        updateTotaleAssicurazioni: function(totale) {
            this.totale_assicurazioni = parseFloat(totale) || 0;
            this.recalculateTotal();
            console.log('[STATE] Totale assicurazioni aggiornato:', this.totale_assicurazioni);
        },

        removeCostoExtra: function(slug) {
            if (this.costi_extra[slug]) {
                delete this.costi_extra[slug];
                this.recalculateTotal();
                console.log('[STATE] Costo extra rimosso:', slug);
            }
        },
        
        recalculateTotal: function() {
            this.totale_costi_extra = 0;
            this.totale_riduzioni = 0;
            
            for (const slug in this.costi_extra) {
                const cost = this.costi_extra[slug];
                if (cost.totale >= 0) {
                    this.totale_costi_extra += cost.totale;
                } else {
                    this.totale_riduzioni += cost.totale; // Già negativo
                }
            }
            
            this.totale_generale = this.totale_camere + this.totale_costi_extra + this.totale_riduzioni + this.totale_assicurazioni;
            
            console.log('[STATE] Totali ricalcolati:', {
                camere: this.totale_camere,
                extra: this.totale_costi_extra,
                riduzioni: this.totale_riduzioni,
                assicurazioni: this.totale_assicurazioni,
                generale: this.totale_generale
            });
            
            // Trigger evento per update UI
            $(document).trigger('btr:state:updated', [this]);
            
            // UNIFIED CALCULATOR v2.0: Validazione automatica ogni 2 secondi
            clearTimeout(this._validationTimer);
            this._validationTimer = setTimeout(() => {
                this.validateWithUnifiedCalculator().catch(error => {
                    console.log('[UNIFIED CALCULATOR] Validazione differita fallita (normale se offline):', error);
                });
            }, 2000);
        },
        
        // Metodo per ottenere i dati per AJAX
        getPayloadData: function() {
            return {
                pricing_totale_camere: this.totale_camere,
                pricing_total_extra_costs: this.totale_costi_extra,
                pricing_total_riduzioni: Math.abs(this.totale_riduzioni), // Positivo per payload
                pricing_totale_assicurazioni: this.totale_assicurazioni,
                pricing_total_price: this.totale_generale,
                pricing_costi_extra_dettagliati: this.costi_extra,
                pricing_num_adults: this.num_adults,
                pricing_num_children: this.num_children,
                pricing_num_neonati: this.num_neonati
            };
        },
        
        // UNIFIED CALCULATOR v2.0: Sincronizzazione calcoli frontend-backend
        validateWithUnifiedCalculator: function() {
            const data = this.getEnhancedPayloadData();
            
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: btr_ajax.rest_url + 'btr/v1/calculate',
                    method: 'POST',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', btr_ajax.nonce);
                    },
                    contentType: 'application/json',
                    data: JSON.stringify({data: data}),
                    success: (response) => {
                        console.log('[UNIFIED CALCULATOR v2.0] Validazione:', response);
                        
                        if (!response.success) {
                            console.error('[UNIFIED CALCULATOR] Errore backend:', response.error);
                            reject(new Error(response.error));
                            return;
                        }
                        
                        // Confronta risultati frontend vs backend
                        const frontendTotal = this.totale_generale;
                        const backendTotal = response.totale_finale;
                        const discrepanza = Math.abs(frontendTotal - backendTotal);
                        
                        if (discrepanza > 0.01) {
                            console.warn('[SPLIT-BRAIN RISOLTO] Discrepanza corretta:', {
                                frontend: frontendTotal,
                                backend: backendTotal,
                                discrepanza: discrepanza,
                                breakdown: response.breakdown
                            });
                            
                            // Aggiorna stato frontend con valori corretti dal backend
                            this.totale_generale = backendTotal;
                            this.totale_camere = response.breakdown.totale_camere;
                            this.totale_costi_extra = response.breakdown.totale_costi_extra;
                            this.totale_riduzioni = response.breakdown.totale_riduzioni;
                            
                            $(document).trigger('btr:state:synchronized', [response]);
                        }
                        
                        resolve(response);
                    },
                    error: (xhr, status, error) => {
                        console.error('[UNIFIED CALCULATOR] Errore validazione:', error);
                        reject(error);
                    }
                });
            });
        },
        
        // UNIFIED CALCULATOR v2.0: Payload migliorato con tutti i dati necessari
        getEnhancedPayloadData: function() {
            return {
                // Dati base
                pricing_totale_camere: this.totale_camere,
                pricing_total_extra_costs: this.totale_costi_extra,
                pricing_total_riduzioni: Math.abs(this.totale_riduzioni),
                pricing_total_price: this.totale_generale,
                pricing_costi_extra_dettagliati: this.costi_extra,
                
                // Conteggi persone
                pricing_num_adults: this.num_adults,
                pricing_num_children: this.num_children,
                pricing_num_neonati: this.num_neonati,
                
                // Dettaglio bambini per fascia
                pricing_num_children_f1: parseInt($('#btr_num_child_f1').val() || 0),
                pricing_num_children_f2: parseInt($('#btr_num_child_f2').val() || 0),
                pricing_num_children_f3: parseInt($('#btr_num_child_f3').val() || 0), 
                pricing_num_children_f4: parseInt($('#btr_num_child_f4').val() || 0),
                
                // Notti extra
                extra_night_flag: window.btrExtraFlagActive || false,
                extra_nights_count: window.btrExtraNightsCount || 1,
                extra_night_price: parseFloat($('.btr-room-card').first().data('extra-night-pp') || 40),
                
                // Supplementi
                supplemento_per_persona: parseFloat($('.btr-room-card').first().data('supplemento') || 0),
                notti_base: parseInt($('.btr-room-card').first().data('notti-base') || 1)
            };
        },
        
        // Debug method
        debug: function() {
            console.log('[STATE DEBUG] Stato completo:', JSON.stringify(this, null, 2));
        }
    };

    /**
     * Helper function to format prices in Italian format
     * @param {number} amount - Amount to format
     * @param {number} decimals - Number of decimal places (default: 2)
     * @param {boolean} showCurrency - Show € symbol (default: true)
     * @param {boolean} prefixCurrency - € as prefix if true, suffix if false (default: true)
     * @returns {string} Price formatted in Italian format (€1.000,50)
     */
    function btrFormatPrice(amount, decimals = 2, showCurrency = true, prefixCurrency = true) {
        // Ensure amount is a valid number
        amount = parseFloat(amount) || 0;
        
        // Format with Italian separators: . for thousands, , for decimals
        const formatted = amount.toLocaleString('it-IT', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
        
        // Add € symbol if requested
        if (showCurrency) {
            if (prefixCurrency) {
                return '€' + formatted;
            } else {
                return formatted + ' €';
            }
        }
        
        return formatted;
    }

    /**
     * Helper function to parse price from formatted string
     * @param {string} priceString - Formatted price string (e.g., "€1.000,50")
     * @returns {number} Numeric value
     */
    function btrParsePrice(priceString) {
        if (typeof priceString !== 'string') return parseFloat(priceString) || 0;
        
        // Remove € symbol and spaces
        let cleaned = priceString.replace(/[€\s]/g, '');
        
        // Replace Italian decimal separator (,) with English (.)
        // Handle thousands separator properly
        if (cleaned.includes(',')) {
            // Split by comma (decimal separator)
            const parts = cleaned.split(',');
            if (parts.length === 2) {
                // Remove dots from the integer part (thousands separators)
                const integerPart = parts[0].replace(/\./g, '');
                cleaned = integerPart + '.' + parts[1];
            }
        } else {
            // No comma, just remove dots (they might be thousands separators)
            // Only if there are more than 3 digits after the last dot
            const dotIndex = cleaned.lastIndexOf('.');
            if (dotIndex > -1 && cleaned.length - dotIndex > 4) {
                cleaned = cleaned.replace(/\./g, '');
            }
        }
        
        return parseFloat(cleaned) || 0;
    }

    /**
     * Initialize click-to-toggle tooltips for dynamically created info wrappers.
     */
    function initTooltips(context) {
        $(context).find('.btr-info-wrapper').each(function(){
            var $wrapper = $(this);
            // Ensure tooltip is hidden initially
            $wrapper.find('.btr-tooltip').hide();
            // Toggle on icon click
            $wrapper.find('.btr-info-icon').off('click').on('click', function(e){
                e.preventDefault();
                e.stopPropagation();
                $wrapper.find('.btr-tooltip').slideToggle(200);
            });
        });
    }

    // --- Funzione per applicare le maschere Inputmask ai campi CAP, data di nascita e telefono ---
    function applyInputMasks(context) {
        // Assicurati che Inputmask sia caricato
        if (typeof Inputmask === "undefined") {
            console.error("Inputmask non è caricato.");
            return;
        }
        // CAP mask: 5 digits
        Inputmask({
            mask: '99999',
            placeholder: '_',
            showMaskOnHover: false
        }).mask($(context).find('input[name^="anagrafici"][name$="[cap]"]'));

        // Data di nascita - Non usiamo più Inputmask perché abbiamo il nostro date picker
        // che gestisce sia selezione da calendario che input manuale

        // Telefono: maschera custom +39 999 9999999 con supporto autocompletamento
        const phoneInputs = $(context).find('input[name^="anagrafici"][name$="[telefono]"]');
        
        phoneInputs.each(function() {
            const $input = $(this);
            
            // Aggiungi attributi per migliorare l'autocompletamento
            $input.attr({
                'autocomplete': 'tel',
                'inputmode': 'tel'
            });
            
            // Flag per tracciare se stiamo gestendo un autocompletamento
            let isAutoFilling = false;
            
            // Listener per l'evento beforeinput (rileva autocompletamento)
            $input.on('beforeinput', function(e) {
                // Se l'evento è di tipo insertReplacementText, è probabile un autocompletamento
                if (e.originalEvent && e.originalEvent.inputType === 'insertReplacementText') {
                    isAutoFilling = true;
                }
            });
            
            // Applica la mask iniziale
            const applyPhoneMask = () => {
                return Inputmask({
                    mask: '+39 999 9999999',
                    placeholder: '_',
                    showMaskOnHover: false,
                    showMaskOnFocus: true,
                    inputmode: "numeric",
                    definitions: {
                        '9': {
                            validator: "[0-9]",
                            cardinality: 1,
                            definitionSymbol: "*"
                        }
                    },
                    onincomplete: function () {
                        this.setCustomValidity("Inserisci un numero valido");
                    },
                    oncomplete: function () {
                        this.setCustomValidity("");
                    }
                }).mask(this);
            };
            
            // Applica la mask inizialmente
            applyPhoneMask.call(this);
            
            // Gestisci l'input per rilevare e formattare l'autocompletamento
            $input.on('input', function(e) {
                if (isAutoFilling) {
                    isAutoFilling = false;
                    
                    const value = this.value;
                    
                    // Se il valore non è nel formato della mask, riformattalo
                    if (value && !value.startsWith('+39 ')) {
                        // Rimuovi temporaneamente la mask
                        if (this.inputmask) {
                            this.inputmask.remove();
                        }
                        
                        // Estrai solo i numeri
                        let cleanNumber = value.replace(/\D/g, '');
                        
                        // Rimuovi il prefisso 39 se presente
                        if (cleanNumber.startsWith('39') && cleanNumber.length > 10) {
                            cleanNumber = cleanNumber.substring(2);
                        }
                        
                        // Limita a 10 cifre
                        cleanNumber = cleanNumber.substring(0, 10);
                        
                        // Se abbiamo un numero valido
                        if (cleanNumber.length >= 9) {
                            // Formatta il numero
                            const formattedNumber = '+39 ' + cleanNumber.substring(0, 3) + ' ' + cleanNumber.substring(3);
                            
                            // Imposta il valore formattato
                            this.value = formattedNumber;
                            
                            // Riapplica la mask
                            applyPhoneMask.call(this);
                            
                            // Trigger l'evento change per validazione
                            $(this).trigger('change');
                        } else {
                            // Riapplica la mask con il valore corrente
                            applyPhoneMask.call(this);
                        }
                    }
                }
            });
            
            // Gestisci anche l'evento change per catturare autocompletamenti che bypassano input
            $input.on('change', function(e) {
                const value = this.value;
                
                // Se il valore non è nel formato corretto e non è vuoto
                if (value && !value.match(/^\+39 \d{3} \d{7}$/)) {
                    // Rimuovi la mask
                    if (this.inputmask) {
                        this.inputmask.remove();
                    }
                    
                    // Estrai solo i numeri
                    let cleanNumber = value.replace(/\D/g, '');
                    
                    // Rimuovi il prefisso 39 se presente
                    if (cleanNumber.startsWith('39') && cleanNumber.length > 10) {
                        cleanNumber = cleanNumber.substring(2);
                    }
                    
                    // Limita a 10 cifre
                    cleanNumber = cleanNumber.substring(0, 10);
                    
                    // Se abbiamo almeno 9 cifre
                    if (cleanNumber.length >= 9) {
                        // Formatta il numero
                        const formattedNumber = '+39 ' + cleanNumber.substring(0, 3) + ' ' + cleanNumber.substring(3, 10);
                        
                        // Imposta il valore formattato
                        this.value = formattedNumber;
                        
                        // Riapplica la mask
                        applyPhoneMask.call(this);
                    } else {
                        // Riapplica la mask vuota
                        this.value = '';
                        applyPhoneMask.call(this);
                    }
                }
            });
            
            // Gestisci il paste event
            $input.on('paste', function(e) {
                isAutoFilling = true;
            });
        });
    }

    // Applica le maschere all'avvio su tutti i partecipanti già presenti
    applyInputMasks(document);
    // Initialize tooltips on load
    initTooltips(document);
// Assicurati che Inputmask sia caricato
// <script src="https://cdn.jsdelivr.net/npm/inputmask@5.0.8/dist/inputmask.min.js"></script>


    /* --------------------------------------------------------------
 *  DEBUG PANEL  (attivabile con ?debug=1 nell'URL)
 * -------------------------------------------------------------- */
    const BTR_DEBUG = new URLSearchParams(window.location.search).get('debug') === '1';
    let $debugPane = null;

    if (BTR_DEBUG) {
        // Inject CSS only once
        if ( !document.getElementById('btr-debug-style') ) {
            const css = `
                #btr-debug-panel{
                    position: fixed;
    bottom: .5em;
    right: .5em;
    width: 420px;
    max-height: 75vh;
    display: flex;
    flex-direction: column;
    font-family: SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
    background: #1e293b;
    color: #f1f5f9;
    border-top-left-radius: 8px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, .25);
    z-index: 99999;
    border-radius: 1.2em;
                }
                #btr-debug-panel .debug-header{
                        background: #0f172a;
    padding: 8px 2em;
    font-size: 13px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-top-left-radius: 8px;
    border-radius: 1.5em 1.5em 0 0;
                }
                #btr-debug-panel .debug-header .debug-title{font-weight:600;letter-spacing:.02em;}
                #btr-debug-panel .debug-header button{
                    background:none;border:none;color:#f1f5f9;font-size:16px;cursor:pointer;
                    line-height:1;padding:0 4px;
                }
                #btr-debug-panel .debug-body{
                    padding:10px 12px;overflow-y:auto;flex:1;
                    scrollbar-width:thin;
                }
                #btr-debug-panel .debug-body::-webkit-scrollbar{width:6px}
                #btr-debug-panel .debug-body::-webkit-scrollbar-thumb{
                    background:#334155;border-radius:3px;
                }
                #btr-debug-panel .debug-entry{margin-bottom:10px;border-bottom:1px solid rgba(255,255,255,.08);padding-bottom:6px;}
                #btr-debug-panel .debug-entry:last-child{border:none;}
                #btr-debug-panel .debug-entry strong{color:#38bdf8}
                #btr-debug-panel .debug-entry pre{
                    margin:4px 0 0;font-size:12px;line-height:1.45;white-space:pre-wrap;
                    word-break:break-word;color:#e2e8f0;
                    background: transparent;
                }
            `;
            $('<style id="btr-debug-style">').text(css).appendTo('head');
        }

        // Build panel markup
        $debugPane = $(`
            <div id="btr-debug-panel">
                <div class="debug-header">
                    <span class="debug-title">BTR Debug</span>
                    <button type="button" id="btr-debug-clear" title="Clear">×</button>
                </div>
                <div class="debug-body"></div>
            </div>
        `).appendTo('body');

        // clear logs button
        $debugPane.on('click', '#btr-debug-clear', () => {
            $debugPane.find('.debug-body').empty();
        });
    }

    function btrLog(label, data){
        if(!BTR_DEBUG) return;
        console.log('[BTR]',label,data);
        const body = $debugPane.find('.debug-body');
        const html = `
            <div class="debug-entry">
                <strong>${label}</strong>
                <pre>${JSON.stringify(data,null,2)}</pre>
            </div>`;
        body.append(html);
    }



    // Selezione degli elementi del DOM
    const form = $('#btr-booking-form');
    const numPeopleSection = $('#btr-num-people-section');
    const roomTypesSection = $('#btr-room-types-section');
    const roomTypesContainer = $('#btr-room-types-container');
    const proceedButton = $('#btr-proceed');
    const assicurazioneButton = $('#btr-generate-participants');
    const customerSection = $('#btr-customer-section');
    const createQuoteButton = $('#btr-create-quote');
    /* ------------------------------------------------------------------
       Sezione "Notte extra" (select #btr_add_extra_night)
       ▸ la teniamo nascosta finché l'utente non sceglie una data.
    ------------------------------------------------------------------ */
    const $extraNightSelect = $('#btr_add_extra_night');
    if ($extraNightSelect.length) {
        // nascondi il suo wrapper (label, div o classe dedicata) finché non serve
        $extraNightSelect.closest('.btr-extra-night-select, .form-group, label, div').hide();
    }

    /* ------------------------------------------------------------------
       Nascondi anche il vecchio box checkbox .btr-extra-night-option
       finché l'utente non sceglie una data.
    ------------------------------------------------------------------ */
    const $extraNightBox = $('.btr-extra-night-option');
    if ( $extraNightBox.length ) {
        $extraNightBox.hide();
    }

    // Aggiornamento: usiamo i nuovi campi Infanti, Bambini Fascia 1 e 2
    const numAdultsField   = $('#btr_num_adults');
    const numInfantsField  = $('#btr_num_infants');
    const numChildF1Field  = $('#btr_num_child_f1');
    const numChildF2Field  = $('#btr_num_child_f2');
    // nuove fasce 3‑4
    const numChildF3Field  = $('#btr_num_child_f3');
    const numChildF4Field  = $('#btr_num_child_f4');

    // Valori numerici
    let numAdults   = parseInt(numAdultsField.val(), 10)   || 0;
    let numInfants  = parseInt(numInfantsField.val(), 10)  || 0;
    let numChildF1  = parseInt(numChildF1Field.val(), 10)  || 0;
    let numChildF2  = parseInt(numChildF2Field.val(), 10)  || 0;
    let numChildF3  = parseInt(numChildF3Field.val(), 10)  || 0;
    let numChildF4  = parseInt(numChildF4Field.val(), 10)  || 0;

    // Se gli infanti non contano come occupanti
    let numChildren = numChildF1 + numChildF2 + numChildF3 + numChildF4;
    let totalPeople = numAdults + numChildren; // I neonati non occupano posti letto

    const totalCapacityDisplay = $('#btr-total-capacity');
    const requiredCapacityDisplay = $('#btr-required-capacity');
    const totalPriceDisplay = $('#btr-total-price');
    
    // UNIFIED CALCULATOR v1.0.216: Event listener per sincronizzazione UI
    $(document).on('btr:state:synchronized', function(event, calculatorResponse) {
        console.log('[UNIFIED CALCULATOR] Sincronizzazione UI con backend:', calculatorResponse);
        
        // Aggiorna display prezzo totale con valore backend
        const backendTotal = calculatorResponse.totale_finale;
        totalPriceDisplay.html(`Prezzo Totale: <strong>${btrFormatPrice(backendTotal)}</strong> <small class="text-warning">📊 (sincronizzato)</small>`);
        $('#btr-total-price-visual').html(btrFormatPrice(backendTotal));
        
        // Mostra notifica di sincronizzazione
        if (typeof showBTRNotification === 'function') {
            showBTRNotification(
                `💰 Prezzo aggiornato: ${btrFormatPrice(backendTotal)}`, 
                'I calcoli sono stati sincronizzati con il sistema centrale.', 
                'info', 
                3000
            );
        }
    });
    const bookingResponse = $('#btr-booking-response');

    // Variabili per tenere traccia dello stato
    let requiredCapacity = 0;
    let selectedCapacity = 0;
    let totalPrice = 0;
    // Mappa child‑id → room‑index per assegnazioni bambini alle camere
    let childAssignments = {};

    /**
     * Mostra un alert "moderno" sempre tramite il sistema di toast/notification
     * definito in window.showNotification, per coerenza con il resto dell'interfaccia.
     *
     * @param {string}  message  Testo da mostrare
     * @param {string}  [icon]   Icona SweetAlert2 (success|error|warning|info|question)
     * @param {string}  [title]  Titolo del popup
     */
    function showAlert(message, icon = 'warning', title = '') {
        // Usa sempre il sistema di toast definito in window.showNotification
        // per coerenza con il resto dell'interfaccia.
        window.showNotification(message, icon);
    }

    // ------------------------------------------------------------------
    //  🔔  Simple toast notification helper
    // ------------------------------------------------------------------
    //  We create a single container once per page‑load.
    if ($('#btr-notification-container').length === 0) {
        $('body').append(
            '<div id="btr-notification-container" class="btr-notification-container"></div>'
        );
    }

    /**
     * Global toast notification.
     * @param {String} message – text to show
     * @param {String} [type]  – 'success' | 'error' | 'warning' | 'info'
     * @param {Number} [duration] – duration in ms, 0 means stay until dismissed
     * @param {Boolean} [dismissable] – whether to show a close button
     * @returns {jQuery} – the toast element
     */
    // Funzione globale per mostrare notifiche
window.showNotification = function(message, type = 'error', duration = 5000, dismissable = true) {
        // Pulisce eventuali notifiche esistenti
        $('#btr-notification-container').empty();
        const $container = $('#btr-notification-container');

        // Create close button if dismissable
        const closeButton = dismissable ?
            `<button class="btr-toast-close" aria-label="Chiudi notifica">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>` : '';

        const $toast = $(`
            <div class="btr-toast btr-toast-${type}">
                <div class="btr-toast-content">
                    <div class="btr-toast-icon">
                        ${type === 'error' ?
            '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>' :
            '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>'
        }
                    </div>
                    <div class="btr-toast-message">${message}</div>
                    ${closeButton}
                </div>
                ${duration > 0 ? '<div class="btr-toast-progress"></div>' : ''}
            </div>
        `);

        $container.append($toast);

        // Add click handler for close button
        if (dismissable) {
            $toast.find('.btr-toast-close').on('click', function() {
                $toast.addClass('btr-toast-hiding');
                setTimeout(() => $toast.remove(), 300);
                // Trigger a custom event that can be listened for
                $(document).trigger('btr-notification-closed');
            });
        }

        // Animazione della barra di progresso (solo se c'è una durata)
        if (duration > 0) {
            const $progress = $toast.find('.btr-toast-progress');
            $progress.css('animation-duration', `${duration}ms`);

            // Rimuovi la notifica dopo il tempo specificato solo se duration > 0
            setTimeout(() => {
                $toast.addClass('btr-toast-hiding');
                setTimeout(() => $toast.remove(), 300);
            }, duration);
        }

        // Return the toast element for further manipulation
        return $toast;
    };

    // Aggiungi stili per evidenziare i campi con errori
    $('<style>')
        .text(`
            .btr-person-card.btr-missing {
                border: 2px solid var(--btr-danger);
                box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.2);
                animation: btr-pulse 2s infinite;
                transition: all 0.3s ease;
            }

            @keyframes btr-pulse {
                0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
                70% { box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
                100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
            }

            .btr-field-group.has-error input,
            .btr-field-group.has-error select {
                border-color: var(--btr-danger);
                background-color: rgba(239, 68, 68, 0.05);
                transition: all 0.3s ease;
            }

            .btr-field-error {
                color: var(--btr-danger);
                font-size: 0.75rem;
                margin-top: 0.25rem;
                font-weight: 500;
                animation: btr-fade-in 0.3s ease-out;
            }

            @keyframes btr-fade-in {
                from { opacity: 0; transform: translateY(-5px); }
                to { opacity: 1; transform: translateY(0); }
            }

            .asign-camera.has-error h4 {
                color: var(--btr-danger);
                transition: color 0.3s ease;
            }

            /* Toast notifications */
            #btr-notification-container {
                position: fixed;
                bottom: 1rem;
                right: 1rem;
                z-index: 9999;
                display: flex;
                flex-direction: column;
                gap: 0.5rem;
                max-width: 24rem;
            }

            .btr-toast {
                padding: 1rem;
                border-radius: 0.5rem;
                background-color: white;
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
                overflow: hidden;
                animation: btr-slide-in 0.3s ease-out forwards;
                position: relative;
            }

            .btr-toast-content {
                display: flex;
                align-items: flex-start;
                position: relative;
            }

            .btr-toast-message {
                flex: 1;
                margin: 0 0.5rem;
            }

            .btr-toast-close {
                background: none;
                border: none;
                padding: 0.25rem;
                cursor: pointer;
                opacity: 0.7;
                transition: opacity 0.2s;
                color: currentColor;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                position: absolute;
                top: 0;
                right: 0;
            }

            .btr-toast-close:hover {
                opacity: 1;
                background-color: rgba(0, 0, 0, 0.05);
            }

            .btr-toast-hiding {
                animation: btr-slide-out 0.3s ease-in forwards;
            }

            @keyframes btr-slide-in {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }

            @keyframes btr-slide-out {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }

            /* Overlay for error notifications */
            .btr-notification-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 9998; /* Just below the notification */
            }


            .btr-toast-icon {
                flex-shrink: 0;
                width: 1.5rem;
                height: 1.5rem;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .btr-toast-message {
                flex-grow: 1;
                font-size: 0.875rem;
                line-height: 1.5;
            }

            .btr-toast-progress {
                position: absolute;
                bottom: 0;
                left: 0;
                height: 3px;
                background-color: currentColor;
                width: 100%;
                transform-origin: left;
                animation: btr-progress 5000ms linear forwards;
                opacity: 0.5;
            }

            @keyframes btr-progress {
                from { transform: scaleX(1); }
                to { transform: scaleX(0); }
            }

            .btr-toast-error {
                border-left: 4px solid var(--btr-danger);
                color: var(--btr-danger);
            }

            .btr-toast-success {
                border-left: 4px solid var(--btr-success);
                color: var(--btr-success);
            }

            .btr-toast-info {
                border-left: 4px solid var(--btr-primary);
                color: var(--btr-primary);
            }
            /* Extra night option */
            .btr-extra-night-option {
                margin: 1rem 0;
                font-size: 0.9rem;
                padding: 0.75rem 1rem;
                background: #f3f4f6;
                border-radius: 6px;
            }
            .btr-extra-night-option input {
                margin-right: 0.5rem;
            }
        `)
        .appendTo('head');


    /**
     * Aggiorna lo stato (abilitato / disabilitato) dei pulsanti di
     * assegnazione bambini in base alla quantità di camere selezionate
     * e alle eventuali assegnazioni già effettuate.
     */
    function refreshChildButtons () {
        $('.btr-child-selector').each(function () {
            const $roomCard  = $(this).closest('.btr-room-card');
            const qty        = parseInt($roomCard.find('.btr-room-quantity').val(), 10) || 0;
            const capacity   = parseInt($roomCard.find('.btr-room-quantity').data('capacity'), 10) || 1;
            const roomIndex  = $(this).data('room-index');

            // slot disponibili = quantità camere * capacità camera
            const maxChildForRoom = qty * capacity;

            // numero di bambini già assegnati a questa camera
            const assignedCount = Object.values(childAssignments)
                .filter(v => v === roomIndex).length;

            $(this).find('.btr-child-btn').each(function () {
                const childId      = $(this).data('child-id');
                const assignedRoom = childAssignments[childId];

                const alreadyTaken   = (assignedRoom !== undefined) && (assignedRoom !== roomIndex);
                const isSelected     = (assignedRoom === roomIndex);
                const noMoreAllowed  = (!isSelected && assignedCount >= maxChildForRoom);
                
                // NUOVO: Verifica se l'assegnazione violerebbe la regola adulto obbligatorio
                let wouldViolateAdultRule = false;
                if (!isSelected && !alreadyTaken && qty > 0) {
                    // Simula l'assegnazione per verificare
                    const futureChildrenCount = assignedCount + 1;
                    const totalSlots = qty * capacity;
                    const futureAdultsSlots = totalSlots - futureChildrenCount;
                    const requiredAdults = Math.min(qty, 1);
                    
                    wouldViolateAdultRule = futureAdultsSlots < requiredAdults;
                }

                $(this)
                    .prop('disabled', alreadyTaken || noMoreAllowed || qty === 0 || wouldViolateAdultRule)
                    .toggleClass('assigned', alreadyTaken)
                    .toggleClass('selected', isSelected)
                    .toggleClass('would-violate-adult-rule', wouldViolateAdultRule && !isSelected && !alreadyTaken)
                    .attr('title', wouldViolateAdultRule ? 'Non selezionabile: ogni camera deve avere almeno un adulto' : '');
            });
        });
    }


    const icona_singola = '<svg id="Raggruppa_26" data-name="Raggruppa 26" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="67.972" height="38.255" viewBox="0 0 67.972 38.255"><defs><clipPath id="clip-path"><rect id="Rettangolo_33" data-name="Rettangolo 33" width="67.972" height="38.255" fill="#0097c5"/></clipPath></defs><g id="Raggruppa_25" data-name="Raggruppa 25"><path id="Tracciato_37" data-name="Tracciato 37" d="M0,2.53C.818-.163,4.263-1.013,5.874,1.5a6.341,6.341,0,0,1,.5,1.092V12.732A7.468,7.468,0,0,1,7.8,11.967c1.421-.485,7.051-.451,8.716-.278a5.184,5.184,0,0,1,4.333,3.428,6.113,6.113,0,0,1,3.378-1.331H58.75A5.735,5.735,0,0,1,61.6,14.852c-.657-4.4,4.852-6.013,6.374-1.722V37.51c-.265.319-.421.583-.871.654a28.632,28.632,0,0,1-4.379.014c-.38-.034-1.122-.3-1.122-.735V32.872H6.372v4.571c0,.437-.742.7-1.122.735a28.632,28.632,0,0,1-4.379-.014C.421,38.093.265,37.829,0,37.51ZM2.124,36.052H4.248V2.123H2.124Zm63.724,0V13.461c0-.924-2.124-.924-2.124,0V36.052Zm-46.731-15.9V16.376a9.647,9.647,0,0,0-.393-.932,3.513,3.513,0,0,0-2.227-1.62,45.757,45.757,0,0,0-7.01-.038,3.285,3.285,0,0,0-2.669,1.576,7.705,7.705,0,0,0-.445,1.013v3.776ZM61.6,26.512V18.5c0-.994-1.789-2.629-2.853-2.585l-34.391,0c-1.16-.193-3.114,1.526-3.114,2.589v8.016Zm-42.483-4.24H6.372v8.48H61.6v-2.12H19.847a1.651,1.651,0,0,1-.73-.729Z" transform="translate(0 -0.001)" fill="#0097c5"/></g></svg>';


    const icona_doppia = '<svg id="Raggruppa_22" data-name="Raggruppa 22" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="55" height="50.651" viewBox="0 0 55 50.651"><defs><clipPath id="clip-path"><rect id="Rettangolo_24" data-name="Rettangolo 24" width="55" height="50.651" fill="#0097c5"/></clipPath></defs><g id="Raggruppa_21" data-name="Raggruppa 21"><path id="Tracciato_35" data-name="Tracciato 35" d="M55,32.061v14.9A4.408,4.408,0,0,1,47.229,49a5.013,5.013,0,0,1-.91-1.985V44.927H8.812v2.091A5.011,5.011,0,0,1,7.9,49,4.408,4.408,0,0,1,.132,46.964c.311-4.812-.416-10.153,0-14.9a12.943,12.943,0,0,1,3.32-7.132l0-18.121A7.643,7.643,0,0,1,10.148,0H44.663a7.628,7.628,0,0,1,6.8,6.809V24.715A12.905,12.905,0,0,1,55,32.061m-5.68-9.006V6.706c0-1.978-2.837-4.631-4.871-4.561l-34.285.016C8.334,2.241,5.6,4.8,5.6,6.6V23.27a7.043,7.043,0,0,1,2.238-1.084c.126-1.749-.234-3.652.286-5.343a5.747,5.747,0,0,1,4.92-3.977c3.5-.245,7.289.184,10.817.016a6.075,6.075,0,0,1,3.67,2.232c.235.04.466-.48.629-.634a6.244,6.244,0,0,1,3.119-1.6c3.543.19,7.419-.293,10.923-.015a5.816,5.816,0,0,1,4.841,4.055c.485,1.659.13,3.551.258,5.264ZM26.494,21.34V18.07a3.767,3.767,0,0,0-2.781-3.008c-3.39.1-7.1-.3-10.459-.052a3.51,3.51,0,0,0-2.876,1.947,8.707,8.707,0,0,0-.387,1.113v3.484c.66-.064,1.432-.194,2.085-.219,4.761-.185,9.645.148,14.418,0m18.647.214V18.07c0-1.495-1.835-2.953-3.264-3.06-3.335-.249-7,.172-10.372.033a3.853,3.853,0,0,0-2.868,2.92V21.34c4.805.151,9.733-.2,14.525,0,.619.025,1.352.158,1.978.219m7.716,14.8V32.222a11.478,11.478,0,0,0-1.414-4.053,9.946,9.946,0,0,0-8.387-4.689c-10.121-.534-20.7.413-30.873,0-4.469-.339-9.908,4.209-9.908,8.743V36.35h29.1a1.225,1.225,0,0,1,1.1,1.044c.031.328-.342,1.1-.674,1.1H2.275v4.288H52.857V38.494H41.765a1.555,1.555,0,0,1-.615-.563,1.162,1.162,0,0,1,.936-1.581ZM6.669,44.927H2.275V46.7c0,.037.231.6.278.686a2.252,2.252,0,0,0,3.777.154,6.054,6.054,0,0,0,.339-.733Zm46.188,0H48.463V46.8a6.054,6.054,0,0,0,.339.733,2.252,2.252,0,0,0,3.777-.154c.048-.091.278-.65.278-.686Z" transform="translate(0)" fill="#0097c5"/><path id="Tracciato_36" data-name="Tracciato 36" d="M251.372,255.243a1.062,1.062,0,1,1-1.062-1.063,1.063,1.063,0,0,1,1.062,1.063" transform="translate(-213.548 -217.774)" fill="#0097c5"/></g></svg>';

    const icona_tripla = '';
    const icona_quadrupla = '';
    const icona_quintupla = '';

    const icona_condivisa = '<svg id="Raggruppa_28" data-name="Raggruppa 28" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="55.295" height="55.184" viewBox="0 0 55.295 55.184"><defs><clipPath id="clip-path"><rect id="Rettangolo_34" data-name="Rettangolo 34" width="55.295" height="55.184" fill="#72c3dc"/></clipPath></defs><g id="Raggruppa_27" data-name="Raggruppa 27"><path id="Tracciato_38" data-name="Tracciato 38" d="M4.446,7.558A6.541,6.541,0,0,1,5.2,7.2a32.537,32.537,0,0,1,7.319-.2c1.336.181,3.12,1.64,3.12,3.054V12.1H38.373c-.285-3.026,3.431-3.918,4.489-1.047h7.986V1.8a6.555,6.555,0,0,1,.368-.8A2.222,2.222,0,0,1,55.279,2.03l.007,52.254a.918.918,0,0,1-.476.8,21.8,21.8,0,0,1-3.318.058,1.737,1.737,0,0,1-.644-.521V49.564H42.8v5.062a1.146,1.146,0,0,1-.3.4,10.977,10.977,0,0,1-3.706,0,1.4,1.4,0,0,1-.421-.511V49.564H4.446v5.062a1.736,1.736,0,0,1-.644.521,21.8,21.8,0,0,1-3.318-.058.918.918,0,0,1-.476-.8L.015,2.03A2.222,2.222,0,0,1,4.078,1a6.551,6.551,0,0,1,.368.8ZM2.814,1.631H1.648v51.89H2.814Zm49.667,51.89h1.166V1.631H52.481ZM14.006,12.1v-1.92c0-.568-1.027-1.446-1.6-1.543a40.969,40.969,0,0,0-6.449.024c-.531.111-1.509.985-1.509,1.519V12.1Zm26,41.425h1.166V10.931H40.006ZM50.849,12.678H42.8v1.164h8.045Zm-46.4,6.865H38.373V13.725H4.446Zm38.358,0h8.045V15.47H42.8Zm-4.43,1.629H4.446v1.164H38.373Zm12.475,0H42.8v1.164h8.045ZM38.373,23.965H4.446V34.088a6.542,6.542,0,0,1,.754-.353c1.188-.392,5.605-.353,7-.233A3.712,3.712,0,0,1,15.639,37.7H38.373Zm4.43,4.073h8.045V23.965H42.8Zm8.045,1.629H42.8V30.83h8.045Zm0,2.793H42.8v4.189h8.045ZM14.006,37.7a2.192,2.192,0,0,0-1.421-2.48,37.03,37.03,0,0,0-6.1-.085A2.11,2.11,0,0,0,4.446,37.7Zm36.842.582H42.8v1.164h8.045ZM38.373,39.325H4.446v5.818H38.373Zm4.43,5.818h8.045V41.07H42.8Zm-4.43,1.629H4.446v1.164H38.373Zm12.475,0H42.8v1.164h8.045Z" transform="translate(0 0)" fill="#72c3dc"/><path id="Tracciato_39" data-name="Tracciato 39" d="M65.185,95.589a25.151,25.151,0,0,1,3.565,0,.819.819,0,0,1-.1,1.6,25.246,25.246,0,0,1-3.467-.017.847.847,0,0,1,0-1.581" transform="translate(-54.594 -80.677)" fill="#72c3dc"/></g></svg>';
    const icona_child_f1 = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="7" r="4"/><path d="M5 22v-2a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v2"/></svg>';
    const icona_child_f2 = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="7" r="4"/><path d="M17 11v6M7 11v6"/><path d="M5 22h14"/></svg>';


    // Recupera la tipologia di prenotazione dal campo nascosto
    const tipologiaPrenotazione = form.find('input[name="btr_tipologia_prenotazione"]').val();
    /**
     * Ritorna 1 se l'utente ha richiesto la notte extra.
     * Considera sia il checkbox #btr_extra_night che la select #btr_add_extra_night,
     * perché possono coesistere a seconda del template utilizzato.
     */
    function getExtraNightFlag() {
        const chk = $('#btr_extra_night');
        const sel = $('#btr_add_extra_night');
        let flag  = 0;
        
        // Debug solo se potrebbe esserci un problema (selezione globale ma DOM a 0)
        const hasGlobalSelection = window.btrExtraNightSelection && window.btrExtraNightSelection.value === '1';
        const shouldDebug = hasGlobalSelection || sel.val() === '1' || chk.is(':checked');
        
        if (shouldDebug) {
            console.log('[BTR] 🔍 getExtraNightFlag() DEBUG:', {
                checkboxExists: chk.length > 0,
                checkboxChecked: chk.length > 0 ? chk.is(':checked') : false,
                selectExists: sel.length > 0,
                selectValue: sel.length > 0 ? sel.val() : 'N/A',
                globalSelection: window.btrExtraNightSelection
            });
        }
        
        // Logica originale
        if (chk.length && chk.is(':checked')) {
            flag = 1;
            console.log('[BTR] ✅ Flag=1 da checkbox');
        }
        if (sel.length && sel.val() === '1') {
            flag = 1;
            console.log('[BTR] ✅ Flag=1 da select');
        }
        
        // Fallback: usa variabile globale se i campi DOM non sono aggiornati
        if (flag === 0 && window.btrExtraNightSelection && window.btrExtraNightSelection.value === '1') {
            // Usa variabile globale solo se è recente (meno di 30 secondi)
            const isRecent = window.btrExtraNightSelection.timestamp && 
                           (Date.now() - window.btrExtraNightSelection.timestamp) < 30000;
            
            console.log('[BTR] 🔍 Fallback check:', {
                globalValue: window.btrExtraNightSelection.value,
                timestamp: window.btrExtraNightSelection.timestamp,
                timeSince: window.btrExtraNightSelection.timestamp ? Date.now() - window.btrExtraNightSelection.timestamp : 'N/A',
                isRecent: isRecent,
                currentFlag: flag
            });
            
            if (isRecent) {
                console.log('[BTR] 🔄 Usando fallback da variabile globale per getExtraNightFlag');
                flag = 1;
            } else {
                console.log('[BTR] ⚠️ Fallback non utilizzato - timestamp non recente o mancante');
            }
        }
        
        // Log finale solo se c'è una discrepanza
        if (shouldDebug && flag === 0 && hasGlobalSelection) {
            console.warn('[BTR] ⚠️ DISCREPANZA: getExtraNightFlag ritorna 0 ma selezione globale indica 1');
        }
        
        console.log('[BTR] 🎯 getExtraNightFlag() RISULTATO:', flag);
        return flag;
    }
    // -------------------------------------------------------------
    //  Se esistono sia la select #btr_add_extra_night che il checkbox
    //  #btr_extra_night, nascondiamo il checkbox per evitare doppione.
    // -------------------------------------------------------------
    if ( $('#btr_add_extra_night').length && $('#btr_extra_night').length ) {
        $('#btr_extra_night').closest('.btr-extra-night-option, label').hide();
    }
    console.log('Tipologia Prenotazione:', tipologiaPrenotazione);
    const isAllotment = (tipologiaPrenotazione === 'allotment_camere');


    function bindAllotmentQuantityEvents() {
      $('.btr-room-quantity').off('input').on('input', updateSelectedRoomsAllotment);
    }

    function updateSelectedRoomsAllotment() {
      let totalRooms = 0;
      let totalPrice = 0;
      $('.btr-room-quantity').each(function() {
        const qty = parseInt($(this).val(),10)||0;
        const price = parseFloat($(this).closest('.btr-room-card').find('.btr-price').text().replace('€',''))||0;
        totalRooms += qty;
        totalPrice += (price * qty);
      });
      totalCapacityDisplay.text(`Camere selezionate: ${totalRooms}`);
      totalPriceDisplay.text(`Prezzo Totale: ${btrFormatPrice(totalPrice)}`);
    }

    function updateNumPeople() {
        let numAdults = $('#btr_num_adults').length ? parseInt($('#btr_num_adults').val(), 10) || 0 : 0;
        let numInfants = $('#btr_num_infants').length ? parseInt($('#btr_num_infants').val(), 10) || 0 : 0;
        let numChild_f1 = $('#btr_num_child_f1').length ? parseInt($('#btr_num_child_f1').val(), 10) || 0 : 0;
        let numChild_f2 = $('#btr_num_child_f2').length ? parseInt($('#btr_num_child_f2').val(), 10) || 0 : 0;
        let numChild_f3 = $('#btr_num_child_f3').length ? parseInt($('#btr_num_child_f3').val(), 10) || 0 : 0;
        let numChild_f4 = $('#btr_num_child_f4').length ? parseInt($('#btr_num_child_f4').val(), 10) || 0 : 0;

        let numChildren = numChild_f1 + numChild_f2 + numChild_f3 + numChild_f4;
        let totalPeople = numAdults + numChildren + numInfants; // I neonati ora occupano posti letto ma sono non paganti

        $('#btr-check-people').removeClass('hide');

        if (totalPeople < 1) {
            totalPeople = 0;
        }

        $('#btr_num_people').val(totalPeople);
    }

    // OTTIMIZZAZIONE: Variabili per debouncing e gestione state
    let workflowResetTimeout = null;
    let isWorkflowResetting = false;
    
    // OTTIMIZZAZIONE: Funzione centralizzata per reset del workflow
    function resetBookingWorkflow(reason, resetLevel = 'partial') {
        console.log(`[BTR] 🔄 OTTIMIZZAZIONE: Reset workflow (${resetLevel}) -`, reason);
        
        // Previene reset multipli simultanei
        if (isWorkflowResetting) {
            console.log('[BTR] ⚠️ OTTIMIZZAZIONE: Reset già in corso, skip');
            return;
        }
        
        isWorkflowResetting = true;
        
        // Reset pulsante verifica
        $('#btr-check-people').removeClass('hide running');
        
        // Nasconde sezioni successive 
        roomTypesSection.slideUp();
        roomTypesContainer.empty();
        assicurazioneButton.slideUp();
        customerSection.slideUp();
        $('.timeline .step.step3').removeClass('active');
        
        // Reset contatori e prezzi
        totalCapacityDisplay.html('Capacità Totale Selezionata: 0 / <span id="btr-required-capacity">0</span>');
        requiredCapacityDisplay.text('');
        selectedCapacity = 0;
        totalPrice = 0;
        totalPriceDisplay.text('Prezzo Totale: €0,00');
        bookingResponse.empty();
        
        // Reset assegnazioni bambini SEMPRE (sia per reset partial che complete)
        if (typeof childAssignments !== 'undefined') {
            childAssignments = {};
            console.log('[BTR] 🧹 Reset assegnazioni bambini (partial/complete)');
        }
        
        // NUOVO: Reset completo dei form partecipanti se richiesto
        if (resetLevel === 'complete') {
            console.log('[BTR] 🧹 Reset completo: Pulisco form partecipanti e dati cliente');
            
            // Reset form partecipanti
            $('#btr-participants-wrapper').empty();
            
            // IMPORTANTE: Nasconde il pulsante "Procedi" quando si fa reset completo
            proceedButton.slideUp();
            console.log('[BTR] 🧹 Nascosto pulsante Procedi');
            
            // Reset sezione cliente
            $('#btr_cliente_nome, #btr_cliente_email').val('');
            
            // Reset timeline step avanzati
            $('.timeline .step.step4').removeClass('active');
            
            // Reset contatore culle se presente
            $('.btr-crib-info').remove();
            $('#btr-num-infants-tracker').remove();
            
            // Unbind event handlers delle culle per evitare conflitti
            $(document).off('change.cribLimitation');
        }
        
        // Reset flag dopo breve timeout
        setTimeout(() => {
            isWorkflowResetting = false;
        }, 200);
        
        console.log(`[BTR] ✅ OTTIMIZZAZIONE: Reset workflow (${resetLevel}) completato`);
    }

    // Bind change only to the actual inputs
    $('#btr_num_adults, #btr_num_infants, #btr_num_child_f1, #btr_num_child_f2, #btr_num_child_f3, #btr_num_child_f4').on('change', function () {
        updateNumPeople();
        
        // NUOVO: Reset completo se ci sono già form partecipanti generati
        const hasParticipantForms = $('#btr-participants-wrapper').children().length > 0;
        const resetLevel = hasParticipantForms ? 'complete' : 'partial';
        
        console.log(`[BTR] 🔄 Cambio numero persone - Reset ${resetLevel} (form esistenti: ${hasParticipantForms})`);
        resetBookingWorkflow('Cambio numero persone', resetLevel);
        
        // RIMOSSA: Verifica notti extra non più necessaria al cambio numero persone
        // La verifica avviene solo al click sulla data
        console.log('[BTR] 🔄 Cambio numero persone - Extra nights check rimosso');
    });

    // Gestione pulsanti + e - per neonati e bambini
    $('.btr-number-control').on('click', '.btr-plus, .btr-minus', function (e) {
        e.preventDefault();
        const controlId = $(this).parent().data('control');
        const $input = $('#' + controlId);
        let val = parseInt($input.val(), 10) || 0;
        if ($(this).hasClass('btr-plus')) {
            val++;
        } else if ($(this).hasClass('btr-minus') && val > 0) {
            val--;
        }
        $input.val(val).trigger('change');

        // Calcolo totale bambini
        let totalChildren = 0;
        $('#btr-children-panel .btr-number-control input').each(function () {
            const val = parseInt($(this).val(), 10);
            if (!isNaN(val)) {
                totalChildren += val;
            }
        });
        $('.btr-children-label strong').text(totalChildren);
        
        // NUOVO: Reset completo se ci sono già form partecipanti generati
        const hasParticipantForms = $('#btr-participants-wrapper').children().length > 0;
        if (hasParticipantForms) {
            console.log('[BTR] 🔄 Click su +/- con form esistenti - Reset completo');
            resetBookingWorkflow('Modifica numero partecipanti tramite +/-', 'complete');
        }
        
        // RIMOSSA: Verifica notti extra non più necessaria al cambio numero bambini
        // La verifica avviene solo al click sulla data
        console.log('[BTR] 🔄 Click su +/- - Extra nights check rimosso');
    });

    // Inizializza il valore al caricamento della pagina
    updateNumPeople();
    // Gestione salvataggio partecipante
    $('.btr-save-anagrafica').on('click', function () {
        const wrapper = $(this).closest('.btr-partecipante');

        // Applica le maschere anche quando si salva (utile se il wrapper è stato aggiunto dinamicamente)
        applyInputMasks(wrapper);

        // --- Validazione codice fiscale ---
        const codiceFiscaleInput = wrapper.find('input[name^="anagrafici"][name$="[codice_fiscale]"]');
        let codiceFiscale = codiceFiscaleInput.val().trim().toUpperCase();
        codiceFiscaleInput.val(codiceFiscale); // forza maiuscolo

        const isCodiceFiscaleValid = /^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/.test(codiceFiscale);

        const eta = parseInt(wrapper.find('[name$="[eta]"]').val() || 0, 10);
        const tipo = wrapper.find('[name$="[tipologia]"]').val() || '';

        const cfObbligatorio = eta >= 18 || ['adulto', 'maggiorenne'].includes(tipo.toLowerCase());

        if (cfObbligatorio && codiceFiscale === '') {
            window.dispatchEvent(new CustomEvent('btr-toast', {
                detail: {
                    message: 'Codice fiscale obbligatorio per i maggiorenni.',
                    type: 'error'
                }
            }));
            return;
        }

        if (codiceFiscale && !isCodiceFiscaleValid) {
            window.dispatchEvent(new CustomEvent('btr-toast', {
                detail: {
                    message: 'Codice fiscale non valido. Verifica e riprova.',
                    type: 'error'
                }
            }));
            return;
        }

        const provincia = wrapper.find('input[name^="anagrafici"][name$="[provincia_residenza]"]').val();
        const nazione = wrapper.find('input[name^="anagrafici"][name$="[nazione_residenza]"]').val();
        // ... (resto della logica già presente, qui solo snippet richiesto)
        // Esempio di creazione/aggiornamento oggetto datiPartecipante:
        let datiPartecipante = {
            // altri campi già presenti...
            codice_fiscale: codiceFiscale,
            provincia_residenza: provincia,
            nazione_residenza: nazione,
        };

        /* -----------------------------------------------------------
         *  Raccolta costi extra – esclusivamente per questo wrapper
         * ----------------------------------------------------------- */
        const costiExtra = {};

        // Ricava l’indice dal data‑attribute o dal name
        let idx = wrapper.data('index');
        if (typeof idx === 'undefined') {
            const firstInputName = wrapper.find('input[name^="anagrafici"]').first().attr('name') || '';
            const m = firstInputName.match(/^anagrafici\[(\d+)\]/);
            idx = m ? m[1] : null;
        }

        // Selettore di tutti i checkbox costi_extra con quell’indice
        // poi filtra in modo che il .closest('.btr-partecipante') sia proprio "wrapper"
        const $extraInputs = $(`input[name^="anagrafici[${idx}][costi_extra]"]:checked`).filter(function () {
            return $(this).closest('.btr-partecipante')[0] === wrapper[0];
        });

        $extraInputs.each(function () {
            const slugMatch = this.name.match(/\[costi_extra\]\[([^\]]+)\]/);
            if (slugMatch && slugMatch[1]) {
                costiExtra[slugMatch[1]] = true;
            }
        });

        datiPartecipante.costi_extra = costiExtra;

        // 🔄 overwrite completo – niente chiavi obsolete
        btrPartecipanti[idx] = Object.assign({}, datiPartecipante);

        // ... (proseguire con la logica di salvataggio già presente)
    });

    // Applica le maschere anche quando si aggiunge un nuovo partecipante dinamicamente
    // Assumiamo che venga emesso un evento "btr-partecipante-aggiunto" con {wrapper: <element>}
    $(document).on('btr-partecipante-aggiunto', function (e, data) {
        if (data && data.wrapper) {
            applyInputMasks(data.wrapper);
            initTooltips(data.wrapper);
        }
    });

    // RIMOSSO: Handler duplicato per #btr_date change - ora gestito dal handler principale nel form

    // Step 1: Selezione della data
    // Quando l'utente seleziona una data, imposta il valore di btr_date_ranges_id
    form.on('change', '#btr_date', function () {
        const selectedOption = $(this).find(':selected'); // Trova l'opzione selezionata
        const selectedDate = selectedOption.val(); // Ottieni il valore della data
        const variantId = selectedOption.data('id'); // Ottieni l'ID variante dal data-id
        
        console.log('[BTR] 📅 #btr_date change event, selectedDate:', selectedDate);
        
        // Imposta tutti i campi necessari
        $('#btr_selected_date').val(selectedDate);
        $('#btr_date_ranges_id').val(selectedDate);
        
        console.log('[BTR] 📌 Impostato btr_selected_date:', $('#btr_selected_date').val());

        // Gestione variante e UI
        if (variantId) {
            $('#btr_selected_variant_id').val(variantId); // Imposta l'ID variante nell'input nascosto
            
            var title = $(this).data('title');
            var desc = $(this).data('desc');
            $('#title-step').text(title);
            $('#desc-step').text(desc);
            $('.timeline .step.step2').addClass('active');
            
            console.log(`Variant ID for selected date (${selectedDate}): ${variantId}`);
        } else {
            showAlert('Errore: Nessuna variante trovata per la data selezionata.','error');
            return; // Esci se non c'è variante
        }

        // Pulisci la cache delle notti extra quando cambia la data
        clearExtraNightCache();
        
        // NUOVA LOGICA: Nasconde inizialmente il blocco notte extra (forza reset al cambio data)
        hideExtraNightControls(true);
        
        // Mostra la sezione per il numero di persone
        numPeopleSection.slideDown();
        
        // NUOVO: Reset completo quando si cambia data (sempre)
        // Questo forza l'utente a rifare tutto il flusso da capo
        console.log('[BTR] 📅 Cambio data - Reset completo del workflow');
        resetBookingWorkflow('Cambio data selezionata', 'complete');

        // Ricarica le camere se è stato già inserito un numero di persone valido
        const numPeople = parseInt($('#btr_num_people').val(), 10);
        const numAdults = parseInt(numAdultsField.val(), 10) || 0;
        const numInfants = parseInt(numInfantsField.val(), 10) || 0;
        const numChildF1 = parseInt(numChildF1Field.val(), 10) || 0;
        const numChildF2 = parseInt(numChildF2Field.val(), 10) || 0;
        const numChildF3  = parseInt(numChildF3Field.val(), 10)  || 0;
        const numChildF4  = parseInt(numChildF4Field.val(), 10)  || 0;
        const numChildren =numChildF1 + numChildF2 + numChildF3 + numChildF4;

        if (!isNaN(numPeople) && numPeople > 0) {
            console.log('[BTR] 📅 Secondo gestore: Cambio data con persone già selezionate, verifico notti extra');
            console.log('[BTR] 📊 Secondo gestore: numPeople =', numPeople, 'numChildren =', numChildren);
            requiredCapacity = numAdults + numChildF1 + numChildF2 + numChildF3 + numChildF4 + numInfants;
            requiredCapacityDisplay.text(requiredCapacity);
            selectedCapacity = 0;
            totalPrice = 0;
            
            // OTTIMIZZAZIONE: Mantieni il pulsante disponibile anche quando auto-carica le camere
            // Questo permette all'utente di fare nuove verifiche se cambia i parametri
            $('#btr-check-people').removeClass('hide running');
            console.log('[BTR] 🔄 OTTIMIZZAZIONE: Pulsante verifica reso disponibile per nuove verifiche');
            
            loadRoomTypes($('#btr_product_id').val(), numPeople, numAdults, numChildren, numInfants, numChildF1, numChildF2);
            
            // RIMOSSA: Verifica notti extra non più necessaria al cambio data con persone già selezionate
            // La verifica avviene solo al click sulla data
            console.log('[BTR] 📅 Cambio data con persone - Extra nights check rimosso');
        } else {
            console.log('[BTR] 📅 Secondo gestore: Cambio data SENZA persone selezionate');
            console.log('[BTR] 📊 Secondo gestore: numPeople =', numPeople, 'numChildren =', numChildren);
            
            // OTTIMIZZAZIONE: Assicura che il pulsante sia visibile anche senza persone
            $('#btr-check-people').removeClass('hide running');
            console.log('[BTR] 🔄 OTTIMIZZAZIONE: Pulsante verifica reso disponibile per prima verifica');
        }
    });



    // Estendi la logica di caricamento camere anche al click su .btr-date-card
    form.on('click', '.btr-date-card,.dropdown-item', function () {
        // Controlla se la data è disabilitata
        if ($(this).hasClass('disabled') || $(this).data('disabled') === true || $(this).data('disabled') === 'true' || $(this).data('disabled') === '1') {
            if (typeof window.showNotification === 'function') {
                const errorMessage = $(this).data('message') || 'Questa data non è disponibile per la prenotazione.';
                window.showNotification(errorMessage, 'error');
            }
            // Nasconde le sezioni successive
            $('#btr-num-people-section, .custom-dropdown-wrapper').fadeOut(300);
            return;
        }

        const selectedValue = $(this).data('value');
        $('#btr_date').val(selectedValue).trigger('change');
        
        // NUOVA LOGICA: Verifica notti extra SOLO al click sulla data
        // Controlla immediatamente se ci sono notti extra disponibili per la data selezionata
        checkExtraNightAvailabilityOnDateClick();

        // Forza il caricamento delle camere se già presente numero persone
        const numPeople = parseInt($('#btr_num_people').val(), 10);
        const numAdults = parseInt(numAdultsField.val(), 10) || 0;
        const numInfants = parseInt(numInfantsField.val(), 10) || 0;
        const numChildF1 = parseInt(numChildF1Field.val(), 10) || 0;
        const numChildF2 = parseInt(numChildF2Field.val(), 10) || 0;
        const numChildF3  = parseInt(numChildF3Field.val(), 10)  || 0;
        const numChildF4  = parseInt(numChildF4Field.val(), 10)  || 0;
        const numChildren = numChildF1 + numChildF2 + numChildF3 + numChildF4;

        if (!isNaN(numPeople) && numPeople > 0) {
            requiredCapacity = numAdults + numChildF1 + numChildF2 + numChildF3 + numChildF4 + numInfants;
            requiredCapacityDisplay.text(requiredCapacity);
            selectedCapacity = 0;
            totalPrice = 0;

            roomTypesContainer.empty();
            $('#btr-check-people').removeClass('hide');
            //loadRoomTypes($('#btr_product_id').val(), numPeople, numAdults, numChildren, numInfants, numChildF1, numChildF2);
        }
    });


    // Step 2: Verifica disponibilità in base al numero di persone
    $('#btr-check-people').on('click', function (e) {
        e.preventDefault();
        
        // OTTIMIZZAZIONE: Protezione contro doppi click
        if ($(this).hasClass('running')) {
            console.log('[BTR] 🚫 OTTIMIZZAZIONE: Doppio click rilevato, ignoro');
            return;
        }
        
        const productId = form.find('input[name="btr_product_id"]').val();
        const packageId = form.find('input[name="btr_package_id"]').val();
        const numPeople = parseInt($('#btr_num_people').val(), 10);
        const numAdults = parseInt(numAdultsField.val(), 10) || 0;
        const numInfants = parseInt(numInfantsField.val(), 10) || 0;
        const numChild_f1 = parseInt($('#btr_num_child_f1').val(), 10) || 0;
        const numChild_f2 = parseInt($('#btr_num_child_f2').val(), 10) || 0;
        const numChild_f3 = parseInt($('#btr_num_child_f3').val(), 10) || 0;
        const numChild_f4 = parseInt($('#btr_num_child_f4').val(), 10) || 0;
        const numChildren = numChild_f1 + numChild_f2 + numChild_f3 + numChild_f4;


        var title = $(this).data('title');
        var desc = $(this).data('desc');

        $(this).addClass('running');
        console.log('[BTR] 🔄 OTTIMIZZAZIONE: Verifica disponibilità avviata, pulsante in stato running');

        if (isNaN(numPeople) || numPeople < 1) {
            showAlert('Inserisci un numero valido di persone.','error');
            $(this).removeClass('running');
            return;
        }

        requiredCapacity = numAdults + numChild_f1 + numChild_f2 + numChild_f3 + numChild_f4 + numInfants;
        requiredCapacityDisplay.text(requiredCapacity);
        selectedCapacity = 0; // Resetta il conteggio
        totalPrice = 0;

        console.log('Verifica Disponibilità - Product ID:', productId, 'Numero di Persone:', numPeople);
        console.log('Selected Variant ID:', $('#btr_selected_variant_id').val());
        // —— DEBUG payload ——
        btrLog('check_availability_payload', {
            action: 'btr_check_availability',
            product_id: productId,
            package_id: packageId,
            num_people: numPeople,
            extra_night: getExtraNightFlag(),
            selected_variant_id: $('#btr_selected_variant_id').val()
        });

        // Chiamata AJAX per verificare la disponibilità
        $.post(btr_booking_form.ajax_url, {
            action: 'btr_check_availability',
            nonce: btr_booking_form.nonce,
            extra_night: getExtraNightFlag(),
            product_id: productId,
            package_id: packageId,
            num_people: numPeople,
            selected_variant_id: $('#btr_selected_variant_id').val(),
            num_child_f1: numChild_f1,
            num_child_f2: numChild_f2,
            num_child_f3: numChild_f3,
            num_child_f4: numChild_f4,
        }).done(function (response) {
            btrLog('check_availability_response', response);
            // Logga anche il numero di camere disponibili per la notte extra, se presente
            if (response.success && response.data && typeof response.data.global_stock_extra !== 'undefined') {
                btrLog('extra_night_rooms_available', {
                    extra_night_flag: getExtraNightFlag(),
                    rooms_extra_available: response.data.global_stock_extra
                });
            }
            if (response.success) {
                console.log('Disponibilità confermata.');
                
                // NUOVA LOGICA: Verifica notti extra dopo conferma disponibilità (usa versione ottimizzata)
                checkExtraNightAvailabilityOptimized();
                
                // Mostra la sezione per la selezione delle camere
                roomTypesSection.slideDown();
                loadRoomTypes(productId, numPeople, numAdults, numChildren, numInfants, numChild_f1, numChild_f2, numChild_f3, numChild_f4);

                $('#title-step').text(title);
                $('#desc-step').text(desc);
                $('.timeline .step.step3').addClass('active');
            } else {
                console.log('Errore Disponibilità:', response.data.message);
                showAlert(response.data.message || 'Errore di disponibilità.','error');
                // Nasconde le sezioni e resetta i valori
                roomTypesSection.slideUp();
                roomTypesContainer.empty();
                assicurazioneButton.slideUp();
                customerSection.slideUp();
                totalCapacityDisplay.html('Capacità Totale Selezionata: 0 / <span id="btr-required-capacity">0</span>');
                requiredCapacityDisplay.text('');
                selectedCapacity = 0;
                totalPrice = 0;
                totalPriceDisplay.text('Prezzo Totale: €0,00');
                $(this).removeClass('running');
            }
        }).fail(function (jqXHR, textStatus, errorThrown) {
            console.error('AJAX Error:', textStatus, errorThrown);
            showAlert('Errore durante la verifica della disponibilità.','error');
        });
    });


    function getRoomIcon(roomType) {
        const rt = roomType ? roomType.toLowerCase() : '';
        if (rt.includes("singola")) {
            return icona_singola;
        } else if (rt.includes("doppia")) {
            return icona_doppia;
        } else if (rt.includes("tripla")) {
            return icona_tripla || icona_doppia;
        } else if (rt.includes("quadrupla")) {
            return icona_quadrupla || icona_doppia;
        } else if (rt.includes("quintupla")) {
            return icona_quintupla || icona_doppia;
        } else if (rt.includes("condivisa")) {
            return icona_condivisa || icona_doppia;
        } else {
            return icona_doppia; // Default fallback
        }
    }
    // Funzione per caricare le camere disponibili
    function loadRoomTypes(productId, numPeople, numAdults, numChildren, numInfants, numChild_f1, numChild_f2, numChild_f3, numChild_f4) {
        const packageId = form.find('input[name="btr_package_id"]').val();
        
        // Reset assegnazioni bambini quando si ricaricano le camere
        if (typeof childAssignments !== 'undefined') {
            childAssignments = {};
            console.log('[BTR] 🧹 Reset assegnazioni bambini al caricamento camere');
        }
        
        /**
         * Ottieni label dinamiche - v1.0.183 Usa data attributes dal DOM come fonte primaria
         * Definizione globale per uso in room cards e breakdown
         */
        const getChildLabel = (fasciaId, fallback) => {
            // 1. Prima prova a leggere dal data attribute (più affidabile)
            const dataLabel = $('.btr-child-group[data-fascia="f' + fasciaId + '"]').first().attr('data-label');
            if (dataLabel) {
                return dataLabel;
            }
            
            // 2. Poi prova window.btrChildFasce
            if (window.btrChildFasce && Array.isArray(window.btrChildFasce)) {
                const fascia = window.btrChildFasce.find(f => f.id === fasciaId);
                if (fascia && fascia.label) {
                    return fascia.label;
                }
            }
            
            // 3. Infine usa data attribute sul select o fallback
            return $('#btr_num_child_f' + fasciaId).data('label') || fallback;
        };
        
        /**
         * Salva etichette dal DOM in variabile globale - v1.0.183
         */
        window.syncChildLabelsFromDOM = () => {
            const labels = {};
            
            // Leggi tutte le etichette dai data attributes
            $('.btr-child-group[data-fascia]').each(function() {
                const fascia = $(this).attr('data-fascia');
                const label = $(this).attr('data-label');
                if (fascia && label) {
                    labels[fascia] = label;
                }
            });
            
            // Salva in variabile globale per uso nel box summary
            if (Object.keys(labels).length > 0) {
                window.btrDynamicLabelsFromDOM = labels;
                console.log('[BTR v1.0.183] Etichette sincronizzate dal DOM:', labels);
            }
            
            return labels;
        };

        // v1.0.160 - Usa etichette dinamiche dal backend con fallback dinamici
        const dynamicLabels = window.btrDynamicChildLabels || {};
        const labelChildF1 = getChildLabel(1, dynamicLabels.f1);
        const labelChildF2 = getChildLabel(2, dynamicLabels.f2);
        const labelChildF3 = getChildLabel(3, dynamicLabels.f3);
        const labelChildF4 = getChildLabel(4, dynamicLabels.f4);

        // OTTIMIZZAZIONE: Non nascondere permanentemente il pulsante, solo rimuovere lo stato running
        // Questo permette all'utente di fare nuove verifiche dopo aver visto i risultati
        $('#btr-check-people').removeClass('running');
        console.log('[BTR] 🔄 OTTIMIZZAZIONE: Pulsante verifica reset ma mantenuto disponibile');
        roomTypesContainer.html('<div class="loader"><span class="element"></span><span class="element "></span><span class="element"></span></div>');
        // Chiamata AJAX per recuperare le camere disponibili
        // —— DEBUG payload ——
        btrLog('get_rooms_payload', {
            action: 'btr_get_rooms',
            product_id: productId,
            package_id: packageId,
            num_people: numPeople,
            extra_night: getExtraNightFlag(),
            selected_date: $('#btr_selected_date').val()
        });
        $.post(btr_booking_form.ajax_url, {
            action: 'btr_get_rooms',
            nonce: btr_booking_form.nonce,
            extra_night: getExtraNightFlag(),
            product_id: productId,
            package_id: packageId,
            num_people: numPeople,
            num_adults: numAdults,
            num_children: numChildF1 + numChildF2 + (numChildF3 || 0) + (numChildF4 || 0),
            selected_variant_id: $('#btr_selected_variant_id').val(),
            selected_date: $('#btr_selected_date').val(),
            num_child_f1: numChild_f1,
            num_child_f2: numChild_f2,
            num_child_f3: numChild_f3,
            num_child_f4: numChild_f4,
        }).done(function (response) {
            btrLog('get_rooms', response);
            
            // Gestione intelligente del numero di notti extra
            if (response.success && response.data) {
                // Prima verifica se le notti extra sono attive
                const extraNightsActive = response.data.extra_night === true || response.data.has_extra_nights === true;
                
                if (extraNightsActive) {
                    // Solo se le notti extra sono attive, gestisci il numero
                    if (typeof response.data.extra_nights_count !== 'undefined') {
                        window.btrExtraNightsCount = parseInt(response.data.extra_nights_count, 10) || 0;
                        console.log('[BTR] ✅ Numero notti extra dal backend:', window.btrExtraNightsCount);
                        console.log('[BTR] 🔢 Valore originale extra_nights_count:', response.data.extra_nights_count);
                        console.log('[BTR] 🔢 Valore parseInt:', parseInt(response.data.extra_nights_count, 10));
                        console.log('[BTR] 🔢 Valore finale window.btrExtraNightsCount:', window.btrExtraNightsCount);
                    } else {
                        // ATTENZIONE: Notti extra attive ma numero non specificato
                        console.error('[BTR] ❌ ERRORE CRITICO: Notti extra attive ma extra_nights_count non fornito dal backend!');
                        console.log('[BTR] 📊 Response data completa:', response.data);
                        console.log('[BTR] 📊 Keys disponibili:', Object.keys(response.data));
                        // Non impostiamo alcun fallback - questo forzerà il sistema a non calcolare il supplemento
                        window.btrExtraNightsCount = undefined;
                        
                        // Mostra un avviso all'utente se possibile
                        console.warn('[BTR] ⚠️ Il calcolo del supplemento per le notti extra potrebbe non essere corretto');
                    }
                } else {
                    // Notti extra non attive - non impostare alcun valore
                    window.btrExtraNightsCount = 0;
                    console.log('[BTR] 📅 Notti extra non attive per questa configurazione');
                    console.log('[BTR] 📅 extra_night value:', response.data.extra_night);
                    console.log('[BTR] 📅 has_extra_nights value:', response.data.has_extra_nights);
                }
            } else {
                // Errore nella risposta - non impostare alcun valore
                window.btrExtraNightsCount = undefined;
                console.error('[BTR] ❌ Risposta AJAX non valida');
            }
            
            // Logga anche il numero di camere disponibili per la notte extra, se presente
            if (response.success && response.data && typeof response.data.global_stock_extra !== 'undefined') {
                const currentFlag = getExtraNightFlag();
                console.log('[BTR] 🔍 DEBUG get_rooms callback - getExtraNightFlag():', currentFlag);
                console.log('[BTR] 🔍 DOM state in get_rooms:', {
                    selectValue: $('#btr_add_extra_night').val(),
                    selectExists: $('#btr_add_extra_night').length > 0,
                    globalSelection: window.btrExtraNightSelection,
                    fallbackUsed: currentFlag === 1 && $('#btr_add_extra_night').val() !== '1'
                });
                
                btrLog('extra_night_rooms_available', {
                    extra_night_flag: currentFlag,
                    rooms_extra_available: response.data.global_stock_extra,
                    extra_nights_count: window.btrExtraNightsCount
                });
            }
            if (response.success) {
                // v1.0.160 - Aggiorna le etichette dinamiche se fornite dalla risposta AJAX
                if (response.data.child_fasce && Array.isArray(response.data.child_fasce)) {
                    window.btrChildFasce = response.data.child_fasce;
                    console.log('[BTR] 📝 Etichette bambini dalle impostazioni pacchetto:', window.btrChildFasce);
                    
                    // Salva le etichette in un oggetto globale per uso successivo
                    window.btrDynamicLabelsFromServer = {};
                    window.btrChildFasce.forEach(function(fascia) {
                        window.btrDynamicLabelsFromServer['f' + fascia.id] = fascia.label;
                    });
                    console.log('[BTR] ✅ Etichette dinamiche dal server salvate:', window.btrDynamicLabelsFromServer);
                }
                
                // Se il backend non restituisce camere, mostra il messaggio "prova una nuova combinazione" e interrompi
                if (!response.data.rooms || response.data.rooms.length === 0) {
                    roomTypesContainer.empty(); // assicura che non restino loader/carte
                    showNoRoomsMessage();
                    // OTTIMIZZAZIONE: Reset del pulsante per permettere nuove verifiche
                    $('#btr-check-people').removeClass('running');
                    console.log('[BTR] 🔄 OTTIMIZZAZIONE: Nessuna camera disponibile, pulsante reset per nuove verifiche');
                    return;
                }
                roomTypesContainer.empty();


                 // ========================================================================
                // AVVISO NEONATI: Mostra avviso informativo quando sono selezionati neonati
                // ========================================================================
                // Controlla se il wrapper esiste già, altrimenti lo crea
                if ($('#btr-room-types-section .btr-wrapper-infants-notice').length === 0) {
                    $('#btr-room-types-section').prepend('<div class="btr-wrapper-infants-notice"></div>');
                }
                $('#btr-room-types-section .btr-wrapper-infants-notice').empty();
                if (numInfants > 0) {
                    const infantsWarningHtml = `
                        <div class="btr-infants-notice">
                            <div class="btr-notice-content">
                               <div class="btr-note-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                    </div>
                                <div>
                                    <h3>Informazioni sui Neonati (${numInfants})</h3>
                                    <p>
                                        I neonati <strong>non sono paganti</strong> ma <strong>occupano posti letto</strong>, quindi risultano nel totale dei partecipanti per il calcolo delle camere. 
                                        ${numInfants === 1 ? 'È disponibile' : 'Sono disponibili'} costi extra come la culla per neonati nei form di prenotazione.
                                    </p>
                                </div>
                            </div>
                        </div>
                    `;
                    $('#btr-room-types-section .btr-wrapper-infants-notice').append(infantsWarningHtml);
                }



                // --- FILTER: Mantieni solo le tipologie presenti in almeno una combo valida ---
                /* ------------------------------------------------------------------
                   Filtra le camere: mantieni solo le tipologie presenti
                   in almeno una delle combinazioni valide restituite dal backend.
                   ------------------------------------------------------------------ */
                const typesInCombos = new Set();
                (response.data.combos || []).forEach(combo => {
                    (combo.rooms || []).forEach(r => typesInCombos.add(r.type));
                });
                const filteredRooms = response.data.rooms.filter(r => typesInCombos.has(r.type));
                btrLog('rooms_filtered', {before: response.data.rooms.length, after: filteredRooms.length, typesInCombos: Array.from(typesInCombos)});
                const rooms = filteredRooms; // sostituisce la variabile originale
                // Ordina le camere per tipologia nell'ordine desiderato
                const ordineTipologie = ['Singola','Doppia','Tripla','Quadrupla','Quintupla','Condivisa'];
                rooms.sort((a, b) => {
                    const idxA = ordineTipologie.indexOf(a.type);
                    const idxB = ordineTipologie.indexOf(b.type);
                    return (idxA === -1 ? ordineTipologie.length : idxA) - (idxB === -1 ? ordineTipologie.length : idxB);
                });

                // [DEBUG] Badge rules
                const badgeRules = response.data.badge_rules || [];
                console.log('[DEBUG] Badge rules ricevute:', badgeRules);


                const showDisabledRooms = response.data.show_disabled_rooms === true;
                
                rooms.forEach(function (room, index) {
                    let roomType = room.type;
                    const variation_id = room.variation_id;
                    const capacity = parseInt(room.capacity, 10);

                    // Se il backend ha indicato stock_infinite, usa lo stock globale (o un numero alto) invece di 0
                    let maxAvailableRooms = parseInt(room.stock, 10);
                    if (isAllotment && room.stock_infinite) {
                        // Preferisci global_stock se fornito, altrimenti imposta un numero arbitrariamente alto
                        const globalStock = parseInt(response.data.global_stock, 10) || 9999;
                        maxAvailableRooms = globalStock;
                    }
                    console.log('Capacità camera:', roomType, 'ID variante:', variation_id, 'Capacità:', capacity, 'disponibili:', maxAvailableRooms);
                    if (isNaN(maxAvailableRooms)) {
                        console.warn(`Giacenza non valida per camera "${room.type}" (ID variante ${room.variation_id}):`, room.stock);
                    }
                    // Salta la camera se la capacità è maggiore del numero richiesto e non è abilitata la visualizzazione delle camere disabilitate
                    if (capacity > requiredCapacity && !showDisabledRooms) {
                        return;
                    }

                // Prezzi
                const regularPrice  = parseFloat(room.regular_price);
                const salePrice     = room.sale_price ? parseFloat(room.sale_price) : null;
                const scontoPercent = room.sconto ? parseFloat(room.sconto) : null;
                const isOnSale      = room.is_on_sale;
                const supplemento   = parseFloat(room.supplemento);
                // Il supplemento è salvato nel backend "per persona" ⇒ calcola quello per camera
                const supplementoCamera = supplemento * capacity;
                    // Prezzo per persona della notte extra (inviato dal backend)
                    const extraNightPP  = parseFloat(room.extra_night_pp || 0);
                    const extraNightFlag = getExtraNightFlag() === 1;
                    // Calcola supplemento per persona (il supplemento è definito per camera)
                    const supplementPerPerson = supplemento / room.capacity;

                    // Calcola il prezzo base per persona o per camera (allotment)
                    let basePricePerPerson;
                    let displayRegularPrice  = regularPrice; // valore da mostrare nell'HTML
                    let displaySalePrice     = salePrice;   // valore scontato da mostrare

                    if (isAllotment) {
                        // Se siamo in modalità allotment il "Prezzo per camera" deve
                        // rappresentare SEMPRE il prezzo di partenza *senza* supplemento.
                        // Quindi sottraiamo l'eventuale supplemento sia al prezzo pieno
                        // che (se presente) al prezzo scontato.
                        const rawCameraPrice = (isOnSale && salePrice !== null && salePrice >= 0)
                            ? salePrice
                            : regularPrice;

                        // Prezzo per camera senza supplemento
                        displayRegularPrice = Math.max(0, rawCameraPrice - supplemento);
                        displaySalePrice    = salePrice !== null
                            ? Math.max(0, salePrice - supplemento)
                            : null;

                        // Fallback di capacità per sicurezza
                        const capMap = {Singola:1, Doppia:2, Tripla:3, Quadrupla:4, Quintupla:5, Condivisa:6};
                        room.capacity = capMap[room.type] || parseInt(room.capacity, 10) || 1;

                        // Il backend invia già price_per_person corretto (senza supplemento)
                        basePricePerPerson = parseFloat(room.price_per_person);

                        // Gestione stock infinito (0 = illimitato)
                        room.stock = parseInt(room.stock, 10) || 0;
                        if (room.stock === 0) {
                            room.stock_infinite = true;
                            room.stock = 9999;
                        }
                    } else {
                        // Non‑allotment: usiamo direttamente il prezzo per persona inviato dal backend
                        basePricePerPerson = parseFloat(room.price_per_person);
                        displayRegularPrice = regularPrice;   // prezzo camera intera
                        displaySalePrice    = salePrice;      // eventuale prezzo scontato camera intera
                    }

                    // Il prezzo per persona NON include la notte extra; quella viene sommata solo al totale camera
                    const pricePerPerson = basePricePerPerson;

                    // Calcola il prezzo totale per camera (prezzo per persona * capacità)
                    let totalRoomPrice = 0;
                    let remainingCapacity = capacity;

                    let tempAdults = numAdults;
                    let tempF1 = numChildF1;
                    let tempF2 = numChildF2;

                    for (let p = 0; p < capacity; p++) {
                        // Aggiungi supplemento extra notte per ogni persona all'inizio di ogni iterazione
                        if (extraNightFlag && extraNightPP > 0) {
                            totalRoomPrice += extraNightPP;
                        }
                        if (tempAdults > 0) {
                            totalRoomPrice += pricePerPerson;
                            tempAdults--;
                        } else if (tempF1 > 0) {
                            const priceF1 = parseFloat(room.price_child_f1 || 0);
                            totalRoomPrice += priceF1 + (extraNightFlag ? extraNightPP : 0);
                            tempF1--;
                        } else if (tempF2 > 0) {
                            const priceF2 = parseFloat(room.price_child_f2 || 0);
                            totalRoomPrice += priceF2 + (extraNightFlag ? extraNightPP : 0);
                            tempF2--;
                        }
                    }
                    // ▸ Aggiungi il supplemento camera (valido per camera intera)
                    if (supplementoCamera > 0) {
                        totalRoomPrice += supplementoCamera;
                    }
                    
                    // ▸ Aggiungi il supplemento extra per le notti extra (se presenti)
                    if (extraNightFlag && extraNightPP > 0) {
                        const extraNightSupplement = supplementoCamera; // stesso supplemento per camera per le notti extra
                        totalRoomPrice += extraNightSupplement;
                    }
                    
                    totalRoomPrice = totalRoomPrice.toFixed(2);

                    // Prepara il testo dello stock
                    const stockLabel = (isAllotment && room.stock_infinite) ? '∞' : maxAvailableRooms;
                    const stockText  = `
                        <div class="btr-stock-info">
                            <span class="btr-stock-label">Disponibilità:</span>
                            <span class="btr-stock-value"><strong>${stockLabel} camer${stockLabel !== 1 ? 'e' : 'a'}</strong></span>
                        </div>
                    `;

                    // Prepara il testo del prezzo
                    const labelPrezzo = isAllotment ? 'Prezzo per camera:' : 'Prezzo per persona:';
                    let priceText = '';
                    let PersonPriceText = '';
                    // Prezzo pieno (adulto) per persona, SENZA sconti
                    const regularPersonPrice = basePricePerPerson + supplementPerPerson;
                    if (isOnSale && displaySalePrice !== null && displaySalePrice >= 0) {
                        priceText = `
        <div class="btr-price">
            <span class="btr-label-price">${labelPrezzo}</span> 
            <span class="btr-regular-price">${btrFormatPrice(displayRegularPrice)}</span>
            <span class="btr-sale-price">${btrFormatPrice(displaySalePrice)}</span>
        </div>
    `;
                    } else {
                        priceText = `
        <div class="btr-price">
            <span class="btr-label-price">${labelPrezzo}</span> 
            <span class="btr-price-value">${btrFormatPrice(displayRegularPrice)}</span>
        </div>
    `;
                    }


                    // F1 price display
                    if(numChild_f1 > 0) {
                        const fascia1Discount = response.data.bambini_fascia1_sconto || 0;
                        const priceF1 = room.price_child_f1 && room.price_child_f1 > 0 ? room.price_child_f1 : displayRegularPrice;
                        PersonPriceText += `
                            <div class="btr-price">
                                <span class="btr-discount-label btr-label-price">${(() => {
                                    const fascia = window.btrChildFasce ? window.btrChildFasce.find(f => f.id === 1) : null;
                                    return fascia ? fascia.label : 'Bambini F1';
                                })()}:</span> 
                                <span class="btr-regular-value">
                                    <span class="btr-total-price-value">${btrFormatPrice(parseFloat(priceF1))}</span> 
                                </span>
                            </div>
                        `;
                    }

                    // F2 price display
                    if(numChild_f2 > 0) {
                        const fascia2Discount = response.data.bambini_fascia2_sconto || 0;
                        const priceF2 = room.price_child_f2 && room.price_child_f2 > 0 ? room.price_child_f2 : displayRegularPrice;
                        PersonPriceText += `
                            <div class="btr-price">
                                <span class="btr-discount-label btr-label-price">${(() => {
                                    const fascia = window.btrChildFasce ? window.btrChildFasce.find(f => f.id === 2) : null;
                                    return fascia ? fascia.label : 'Bambini F2';
                                })()}:</span> 
                                <span class="btr-regular-value">
                                    <span class="btr-total-price-value">${btrFormatPrice(parseFloat(priceF2))}</span>
                                </span>
                            </div>
                        `;
                    }

                    // F3 price display
                    if(numChild_f3 > 0) {
                        const fascia3Discount = response.data.bambini_fascia3_sconto || 0;
                        const priceF3 = room.price_child_f3 && room.price_child_f3 > 0 ? room.price_child_f3 : displayRegularPrice;
                        PersonPriceText += `
                            <div class="btr-price">
                                <span class="btr-discount-label btr-label-price">${(() => {
                                    const fascia = window.btrChildFasce ? window.btrChildFasce.find(f => f.id === 3) : null;
                                    return fascia ? fascia.label : 'Bambini F3';
                                })()}:</span>
                                <span class="btr-regular-value">
                                    <span class="btr-total-price-value">${btrFormatPrice(parseFloat(priceF3))}</span>
                                </span>
                            </div>
                        `;
                    }

                    // F4 price display
                    if(numChild_f4 > 0) {
                        const fascia4Discount = response.data.bambini_fascia4_sconto || 0;
                        const priceF4 = room.price_child_f4 && room.price_child_f4 > 0 ? room.price_child_f4 : displayRegularPrice;
                        PersonPriceText += `
                            <div class="btr-price">
                                <span class="btr-discount-label btr-label-price">${(() => {
                                    const fascia = window.btrChildFasce ? window.btrChildFasce.find(f => f.id === 4) : null;
                                    return fascia ? fascia.label : 'Bambini F4';
                                })()}:</span>
                                <span class="btr-regular-value">
                                    <span class="btr-total-price-value">${btrFormatPrice(parseFloat(priceF4))}</span>
                                </span>
                            </div>
                        `;
                    }

                    if(numInfants > 0) {
                        PersonPriceText += `
                            <div class="btr-price">
                                <span class="btr-label-price">${btr_booking_form.labels.infant_plural || 'Neonati'}:</span> <span class="btr-regular-value">Non paganti</span>
                            </div>
                        `;
                    }

                    // Prepara il testo del supplemento se presente
                    let supplementoText = '';
                    let inclusoSupplemento = '';
                    if (supplemento > 0) {
                        supplementoText = `
                                <div class="btr-supplemento">
                                    <span class="btr-supplemento-label">Supplemento:</span>
                                    <span class="btr-supplemento-value">${btrFormatPrice(supplemento)}</span> 
                                    <small>a persona, a notte</small>
                                </div>
                            `;
                        inclusoSupplemento = ` <small>(incluso supplemento)</small>`;
                    }

                    // Testo del prezzo totale per persona (incluso supplemento)
                    const totalPricePerPersonText = `
                                <div class="btr-total-price-per-person">
                                    <!--<span class="btr-label-price">Prezzo persona` + inclusoSupplemento + `</span>:-->
                                    <span class="btr-label-price">${btr_booking_form.labels.adult_singular}</span>:
                                    <span class="btr-total-price-value">${btrFormatPrice(pricePerPerson)}</span>
                                </div>
                            `;
                    /* --------------------------------------------------------------
                     *  Blocco costo notte extra (visualizzazione)
                     * -------------------------------------------------------------- */
                    let extraNightText = '';
                    if (extraNightFlag && extraNightPP > 0) {
                        extraNightText = `
                                <div class="btr-extra-night-cost">
                                    <span class="btr-label-price">Notte extra:</span> 
                                    <span class="btr-extra-night-value">${btrFormatPrice(extraNightPP)}</span> 
                                    <small>a persona</small>
                                </div>
                            `;
                    }


                    const sconto_Percentuale = scontoPercent && scontoPercent > 0
                        ? `<span class="btr-sale-percent">Promo <strong>${scontoPercent}% Sconto</strong></span>`
                        : '';

                    // Sostituzione label per "Doppia"
                    // Sostituzione label per "Doppia" 
                    if(roomType === 'Doppia') {
                        roomType = 'Doppia/Matrimoniale';
                    }

                    const roomIcon = getRoomIcon(roomType);

                    // ------------------------------------------------------------------
                    //  Pulsanti di assegnazione bambini (visibili se ci sono bambini)
                    // ------------------------------------------------------------------
                    const isSingleRoom = roomType.toLowerCase().includes('singola');
                    let childButtonsHtml = '';
                    // Nei singoli non è consentita l'assegnazione di bambini e neonati
                    if (!isSingleRoom && (numChild_f1 > 0 || numChild_f2 > 0 || numChild_f3 > 0 || numChild_f4 > 0 || numInfants > 0)) {
                        childButtonsHtml += `
        <div class="btr-child-selector" data-room-index="${index}" style="display:none;">
            <p class="btr-child-selector-label">Assegna bambini e neonati a questa camera:</p>
            <div class="btr-child-buttons">
    `;

                        /* --- Gruppo 3‑12 --- */
                        if (numChild_f1 > 0) {
                            // v1.0.183 - Salva etichetta in data attribute per recupero affidabile
                            const f1Label = (window.btrDynamicLabelsFromServer && window.btrDynamicLabelsFromServer.f1) || labelChildF1;
                            childButtonsHtml += `
            <div class="btr-child-group" data-fascia="f1" data-label="${f1Label}">
                <span class="btr-child-group-title btr-f1-title">
                   ${icona_child_f1} <strong>${f1Label}</strong>
                </span>
        `;
                            for (let i = 0; i < numChild_f1; i++) {
                                childButtonsHtml += `
                <button type="button"
                        class="btr-child-btn btr-child-f1"
                        data-child-type="f1"
                        data-child-id="f1-${i}"
                        disabled>
                    B${i + 1}
                </button>`;
                            }
                            childButtonsHtml += `</div>`; /* chiude gruppo 3‑12 */
                        }

                        /* --- Gruppo 12‑14 --- */
                        if (numChild_f2 > 0) {
                            // v1.0.183 - Salva etichetta in data attribute per recupero affidabile
                            const f2Label = (window.btrDynamicLabelsFromServer && window.btrDynamicLabelsFromServer.f2) || labelChildF2;
                            childButtonsHtml += `
            <div class="btr-child-group" data-fascia="f2" data-label="${f2Label}">
                <span class="btr-child-group-title btr-f2-title">
                    ${icona_child_f2} <strong>${f2Label}</strong>
                </span>
        `;
                            for (let i = 0; i < numChild_f2; i++) {
                                childButtonsHtml += `
                <button type="button"
                        class="btr-child-btn btr-child-f2"
                        data-child-type="f2"
                        data-child-id="f2-${i}"
                        disabled>
                    B${i + 1}
                </button>`;
                            }
                            childButtonsHtml += `</div>`; /* chiude gruppo 12‑14 */
                        }

                        /* --- Gruppo F3 --- */
                        if (numChild_f3 > 0) {
                            // v1.0.183 - Salva etichetta in data attribute per recupero affidabile
                            const f3Label = (window.btrDynamicLabelsFromServer && window.btrDynamicLabelsFromServer.f3) || labelChildF3;
                            childButtonsHtml += `
            <div class="btr-child-group" data-fascia="f3" data-label="${f3Label}">
                <span class="btr-child-group-title btr-f3-title">
                    ${icona_child_f1} <strong>${f3Label}</strong>
                </span>
        `;
                            for (let i = 0; i < numChild_f3; i++) {
                                childButtonsHtml += `
                <button type="button"
                        class="btr-child-btn btr-child-f3"
                        data-child-type="f3"
                        data-child-id="f3-${i}"
                        disabled>
                    B${i + 1}
                </button>`;
                            }
                            childButtonsHtml += `</div>`; /* chiude gruppo F3 */
                        }

                        /* --- Gruppo F4 --- */
                        if (numChild_f4 > 0) {
                            // v1.0.183 - Salva etichetta in data attribute per recupero affidabile
                            const f4Label = (window.btrDynamicLabelsFromServer && window.btrDynamicLabelsFromServer.f4) || labelChildF4;
                            childButtonsHtml += `
            <div class="btr-child-group" data-fascia="f4" data-label="${f4Label}">
                <span class="btr-child-group-title btr-f4-title">
                    ${icona_child_f2} <strong>${f4Label}</strong>
                </span>
        `;
                            for (let i = 0; i < numChild_f4; i++) {
                                childButtonsHtml += `
                <button type="button"
                        class="btr-child-btn btr-child-f4"
                        data-child-type="f4"
                        data-child-id="f4-${i}"
                        disabled>
                    B${i + 1}
                </button>`;
                            }
                            childButtonsHtml += `</div>`; /* chiude gruppo F4 */
                        }

                        /* --- Gruppo Neonati --- */
                        if (numInfants > 0) {
                            childButtonsHtml += `
            <div class="btr-child-group">
                <span class="btr-child-group-title btr-infants-title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 12h.01"/><path d="M15 12h.01"/><path d="M10 16c.5.3 1.2.5 2 .5s1.5-.2 2-.5"/><path d="M19 6.3a9 9 0 0 1 0 11.4"/><path d="M5 6.3a9 9 0 0 0 0 11.4"/><path d="M10.5 2a9 9 0 0 1 3 0"/><path d="M10.5 22a9 9 0 0 1 3 0"/></svg> <strong>${btr_booking_form.labels.infant_plural || 'Neonati'} (0-3)</strong>
                </span>
        `;
                            for (let i = 0; i < numInfants; i++) {
                                childButtonsHtml += `
                <button type="button"
                        class="btr-child-btn btr-child-infant"
                        data-child-type="infant"
                        data-child-id="infant-${i}"
                        disabled>
                    N${i + 1}
                </button>`;
                            }
                            childButtonsHtml += `</div>`; /* chiude gruppo Neonati */
                        }

                        childButtonsHtml += `
            </div> <!-- /.btr-child-buttons -->
        </div> <!-- /.btr-child-selector -->
    `;
                    }

                    // Determina le classi della card in base alla disponibilità
                    const roomCardClasses = ['btr-room-card'].join(' ');

                // Badge dinamici basati sulle regole salvate, usiamo disponibilità globale solo se stock_infinite in modalità allotment
                    let dynamicBadge = '';
                    // determina la disponibilità da usare per il badge
                    const availabilityForBadge = isAllotment
                        ? (room.stock_infinite ? (response.data.global_stock || 0) : maxAvailableRooms)
                        : maxAvailableRooms;
                    for (const rule of badgeRules) {
                        const enabled = ['1', 1, true, 'true'].includes(rule.enabled);
                        if (!enabled) continue;
                        const threshold = parseInt(rule.soglia, 10);
                        if (isNaN(threshold)) continue;
                        const match =
                            (rule.condizione === 'eq' && availabilityForBadge === threshold) ||
                            (rule.condizione === 'lt' && availabilityForBadge < threshold) ||
                            (rule.condizione === 'lte' && availabilityForBadge <= threshold);
                        if (match) {
                            dynamicBadge = `<span class="${rule.class} btr-room-badge"><span>${rule.label}</span></span>`;
                            break;
                        }
                    }

                // Genera l'HTML per ogni tipologia di camera
                const roomHtml = `
                        <div class="${roomCardClasses}" data-room-index="${index}">
                            <div class="btr-room-header">
                                <div class="btr-room-icon">${roomIcon}</div> 
                                <h3 class="btr-room-type">Camera <strong>${roomType}</strong> ${dynamicBadge}</h3> ${sconto_Percentuale}
                                <p class="btr-room-capacity">Capacità: <strong>${capacity} person${capacity > 1 ? 'e' : 'a'}</strong></p>
                                <!--${stockText}-->
                            </div>
                            <div class="btr-room-body">

                                ${totalPricePerPersonText}
                                ${PersonPriceText}
                                ${childButtonsHtml}
                                ${supplementoText}

                                ${extraNightText}
                                <div class="btr-total-room-price">
                                    Prezzo totale:
                                    <span class="btr-total-room-price-value">€${totalRoomPrice}</span>
                                </div>
                               <small class="btr-price-estimate-note">* prezzo totale stimato per camera</small>
                            </div>
                            <div class="btr-room-footer">
                                ${
                                    maxAvailableRooms > 0 ? `
                                        <div class="btr-room-controls">
                                            <label for="btr-room-quantity-${sanitizeRoomType(roomType)}" class="btr-room-quantity-label">
                                                INDICA IL NUMERO DI CAMERE CHE DESIDERI
                                            </label>
                                            ${(!isSingleRoom && (numChild_f1 > 0 || numChild_f2 > 0 || numChild_f3 > 0 || numChild_f4 > 0 || numInfants > 0)) ? `
                                                <div class="btr-child-toggle-wrapper" style="display:none;">
                                                    <input type="checkbox" 
                                                           id="btr-child-toggle-${index}" 
                                                           class="btr-child-toggle" 
                                                           data-room-index="${index}">
                                                    <label for="btr-child-toggle-${index}" class="btr-child-toggle-label">
                                                        Vuoi assegnare bambini a questa camera?
                                                    </label>
                                                </div>
                                            ` : ''}
                                        </div>
                                        <div class="btr-number-input">
                                            <button class="btr-minus">–</button>
                                <input
                                    type="number"
                                    id="btr-room-quantity-${sanitizeRoomType(roomType)}"
                                    class="btr-room-quantity"
                                    data-variation-id="${variation_id}"
                                    data-room-type="${roomType}"
                                    data-capacity="${capacity}"
                                    data-price-per-person="${pricePerPerson}"
                                    data-regular-price-person="${regularPersonPrice.toFixed(2)}"
                                    data-price-child-f1="${room.price_child_f1 || 0}"
                                    data-price-child-f2="${room.price_child_f2 || 0}"
                                    data-price-child-f3="${room.price_child_f3 || 0}"
                                    data-price-child-f4="${room.price_child_f4 || 0}"
                                    data-extra-night-child-f1="${room.extra_night_child_f1 || (extraNightPP * 0.375)}"
                                    data-extra-night-child-f2="${room.extra_night_child_f2 || (extraNightPP * 0.5)}"
                                    data-extra-night-child-f3="${room.extra_night_child_f3 || (extraNightPP * 0.5)}"
                                    data-extra-night-child-f4="${room.extra_night_child_f4 || (extraNightPP * 0.5)}"
                                    data-sconto="${scontoPercent}"
                                    data-supplemento="${supplemento}"
                                    data-extra-night-pp="${extraNightPP}"
                                    data-stock="${maxAvailableRooms}"
                                    min="0"
                                    max="${maxAvailableRooms}"
                                    value="0">
                                            <button class="btr-plus">+</button>
                                        </div>
                                    ` : `
                                        <label for="btr-room-quantity-${sanitizeRoomType(roomType)}" class="btr-room-quantity-label">Questa tipologia di camera non è disponibile per le date selezionate.</label>
                                    `
                                }
                            </div>
                        </div>
                        ${ scontoPercent && scontoPercent > 0
                            ? `<small class="btr-price-save">Risparmi ${scontoPercent}% su questa camera</small>`
                            : '' }
                    `;
                // [DEBUG] Log dei valori calcolati per ogni camera
                console.log(`[DEBUG room ${index + 1}]`, {
                  roomType: room.type,
                  displayRegularPrice,
                  displaySalePrice,
                  supplemento,
                  basePricePerPerson,
                  pricePerPerson
                });
                // [DELETED: Gestione click pulsanti bambino → camera]
                roomTypesContainer.append(roomHtml);
                // Handler per il toggle dei bambini
                roomTypesContainer.off('change', '.btr-child-toggle').on('change', '.btr-child-toggle', function () {
                    const roomIndex = $(this).data('room-index');
                    const $childSelector = $(`.btr-child-selector[data-room-index="${roomIndex}"]`);
                    
                    if ($(this).is(':checked')) {
                        $childSelector.slideDown(300);
                    } else {
                        $childSelector.slideUp(300);
                        // Rimuovi eventuali assegnazioni quando si nasconde il selector
                        $childSelector.find('.btr-child-btn.selected').each(function() {
                            const childId = $(this).data('child-id');
                            delete childAssignments[childId];
                            $(this).removeClass('selected');
                        });
                        refreshChildButtons();
                        updateSelectedRooms();
                    }
                });
                
                // Delegated handler: si attacca una sola volta al container
                roomTypesContainer.off('click', '.btr-child-btn').on('click', '.btr-child-btn', function () {
                    if ($(this).prop('disabled')) return;

                    const $btn      = $(this);
                    const childId   = $btn.data('child-id');
                    const roomIndex = $btn.closest('.btr-child-selector').data('room-index');
                    const $roomCard = $(`.btr-room-card[data-room-index="${roomIndex}"]`);
                    const roomType = $roomCard.find('.btr-room-quantity').data('room-type') || 'Camera';
                    const capacity = parseInt($roomCard.find('.btr-room-quantity').data('capacity'), 10) || 1;
                    const quantity = parseInt($roomCard.find('.btr-room-quantity').val(), 10) || 0;
                    
                    // Se sta per assegnare il bambino a questa camera
                    if (childAssignments[childId] !== roomIndex) {
                        // FIX v1.0.194: Blocca bambini in camere singole
                        const roomTypeLower = roomType.toLowerCase();
                        const isSingle = roomTypeLower.includes('singola') || roomTypeLower.includes('single');
                        
                        if (isSingle) {
                            showNotification(
                                `Non puoi assegnare bambini alla ${roomType}.\n\n` +
                                `Le camere singole sono riservate esclusivamente agli adulti.`,
                                'error'
                            );
                            return; // Blocca l'assegnazione
                        }
                        
                        // Conta i bambini già assegnati a questa camera (incluso quello che stiamo per aggiungere)
                        let childrenInRoom = 1; // Il bambino che stiamo assegnando
                        Object.entries(childAssignments).forEach(([cid, idx]) => {
                            if (idx === roomIndex && cid !== childId) {
                                childrenInRoom++;
                            }
                        });
                        
                        // Calcola posti totali e verifica se ci sarà almeno un adulto
                        const totalSlots = quantity * capacity;
                        const adultsInRoom = totalSlots - childrenInRoom;
                        
                        // Verifica che ci sia almeno un adulto per camera
                        const requiredAdults = Math.min(quantity, 1); // Almeno 1 adulto, ma non più del numero di camere
                        
                        if (adultsInRoom < requiredAdults) {
                            // Mostra messaggio di errore
                            showNotification(
                                `Non puoi assegnare questo bambino alla ${roomType}.\n\n` +
                                `Ogni camera deve avere almeno un adulto.\n` +
                                `Con questo bambino ci sarebbero ${childrenInRoom} bambin${childrenInRoom > 1 ? 'i' : 'o'} e solo ${adultsInRoom} post${adultsInRoom !== 1 ? 'i' : 'o'} per adulti.`,
                                'error'
                            );
                            return; // Blocca l'assegnazione
                        }
                    }

                    if (childAssignments[childId] === roomIndex) {
                        // Deseleziona
                        delete childAssignments[childId];
                        $btn.removeClass('selected');
                    } else {
                        // Se già assegnato ad un'altra camera, togli l'highlight lì
                        if (childAssignments[childId] !== undefined) {
                            const prevRoom = childAssignments[childId];
                            roomTypesContainer
                                .find(`.btr-child-selector[data-room-index="${prevRoom}"] .btr-child-btn[data-child-id="${childId}"]`)
                                .removeClass('selected');
                        }
                        // Nuova assegnazione
                        childAssignments[childId] = roomIndex;
                        $btn.addClass('selected');
                    }

                    refreshChildButtons();
                    updateSelectedRooms();
                    
                    // NUOVO: Reset form partecipanti se già generati (modifica assegnazioni bambini)
                    const hasParticipantForms = $('#btr-participants-wrapper').children().length > 0;
                    if (hasParticipantForms) {
                        console.log('[BTR] 👶 Cambio assegnazione bambini con form esistenti - Reset completo');
                        resetBookingWorkflow('Modifica assegnazione bambini alle camere', 'complete');
                    }
                });
                // [DEBUG] Log finale HTML camere
                //console.log("[DEBUG final room list]", roomTypesContainer.html());

                // Aggiorna lo stato iniziale dei pulsanti appena creati
                refreshChildButtons();
                
                // v1.0.183 - Sincronizza etichette bambini dal DOM dopo il caricamento camere
                window.syncChildLabelsFromDOM();
                });


                // Mostra il bottone di Procedi
                assicurazioneButton.slideDown();

                // Disabilita il pulsante #btr-proceed all'inizio dopo la generazione del form del primo partecipante
                $('#btr-proceed').prop('disabled', true);

                // Evento per il cambio di quantità delle camere
                $('.btr-room-quantity').on('input', function () {
                    const $input = $(this);
                    const quantity = parseInt($input.val(), 10) || 0;
                    const $roomCard = $input.closest('.btr-room-card');
                    const roomIndex = $roomCard.data('room-index');
                    const $toggleWrapper = $roomCard.find('.btr-child-toggle-wrapper');
                    
                    // Mostra/nasconde il checkbox basandosi sulla quantità
                    if (quantity > 0) {
                        $toggleWrapper.slideDown(200);
                    } else {
                        $toggleWrapper.slideUp(200);
                        // Se nascondiamo il checkbox, deselezionalo e nascondi il selettore bambini
                        const $checkbox = $toggleWrapper.find('.btr-child-toggle');
                        if ($checkbox.is(':checked')) {
                            $checkbox.prop('checked', false).trigger('change');
                        }
                    }
                    
                    updateSelectedRooms();
                    
                    // NUOVO: Reset form partecipanti se già generati
                    const hasParticipantForms = $('#btr-participants-wrapper').children().length > 0;
                    if (hasParticipantForms) {
                        console.log('[BTR] 🏠 Cambio quantità camere con form esistenti - Reset completo');
                        resetBookingWorkflow('Modifica quantità camere', 'complete');
                    }
                });

                // Eventi per i pulsanti + e -
                $('.btr-minus').on('click', function (e) {
                    e.preventDefault();
                    const input = $(this).siblings('.btr-room-quantity');
                    let value = parseInt(input.val(), 10) || 0;
                    if (value > parseInt(input.attr('min'), 10)) {
                        value--;
                        input.val(value);
                        input.trigger('input');
                        
                        // NUOVO: Reset form partecipanti se già generati (il trigger('input') gestisce già il reset)
                        console.log('[BTR] ➖ Click su minus - trigger input gestirà il reset se necessario');
                    }
                });

                $('.btr-plus').on('click', function (e) {
                    e.preventDefault();
                    const input = $(this).siblings('.btr-room-quantity');
                    let value = parseInt(input.val(), 10) || 0;
                    const max = parseInt(input.attr('max'), 10);
                    if (value < max) {
                        value++;
                        input.val(value);
                        input.trigger('input');
                        
                        // NUOVO: Reset form partecipanti se già generati (il trigger('input') gestisce già il reset)
                        console.log('[BTR] ➕ Click su plus - trigger input gestirà il reset se necessario');
                    }
                });

                function updateSelectedRooms() {
                    selectedCapacity = 0;
                    totalPrice = 0;
                    // ── Nuovi contatori per riepilogo adulti / bambini ────────────────
                    let adultCount      = 0, adultTotal      = 0, adultSupplemento      = 0;
                    let childF1Count    = 0, childF1Total    = 0, childF1Supplemento    = 0;   // 3-12 anni
                    let childF2Count    = 0, childF2Total    = 0, childF2Supplemento    = 0;   // 12-14 anni
                    let childF3Count    = 0, childF3Total    = 0, childF3Supplemento    = 0;   // 14-17 anni
                    let childF4Count    = 0, childF4Total    = 0, childF4Supplemento    = 0;   // 17-… anni
                    let roomSummary = {};
                    let totalFullPrice = 0;

                    // Tracciamento adulti e bambini per tipo di camera
                    let adultsByRoomType = {};
                    let childF1ByRoomType = {};
                    let childF2ByRoomType = {};
                    let childF3ByRoomType = {};
                    let childF4ByRoomType = {};
                    let infantsByRoomType = {};
                    // Contatori globali rimanenti: si decrementano man mano che
                    // le persone vengono allocate nelle camere, così non vengono
                    // contate più volte.
                    let remainingAdults   = numAdults;
                    let remainingChildF1  = parseInt($('#btr_num_child_f1').val(), 10) || 0;
                    let remainingChildF2  = parseInt($('#btr_num_child_f2').val(), 10) || 0;
                    let remainingChildF3  = parseInt($('#btr_num_child_f3').val(), 10) || 0;
                    let remainingChildF4  = parseInt($('#btr_num_child_f4').val(), 10) || 0;
                    let remainingInfants  = parseInt($('#btr_num_infants').val(), 10) || 0;

                    // FIX: Definisci basePackageNights all'inizio della funzione per uso globale
                    let basePackageNights = 2; // Default fallback
                    
                    // Leggi il valore corretto dal backend (settato in class-btr-shortcodes.php)
                    if (window.btr_booking_form && window.btr_booking_form.base_nights) {
                        basePackageNights = parseInt(window.btr_booking_form.base_nights, 10);
                        console.log('[BTR CALC] ✅ Base package nights dal backend:', basePackageNights);
                    } else {
                        console.warn('[BTR CALC] ⚠️ Fallback a 2 notti - verificare meta field btr_numero_notti');
                    }

                    /* --------------------------------------------------------------
                     *  Calcolo costo Notte Extra per riepilogo partecipanti
                     * -------------------------------------------------------------- */
                    const extraNightFlag = getExtraNightFlag() === 1;
                    console.log('[BTR] 💰 updateSelectedRooms - extraNightFlag:', extraNightFlag, 'getExtraNightFlag():', getExtraNightFlag());
                    let   extraNightPP   = 0;
                    $('.btr-room-quantity').each(function () {
                        const qty = parseInt($(this).val(), 10) || 0;
                        if (qty > 0) {
                            extraNightPP = parseFloat($(this).data('extra-night-pp')) || 0;
                            return false; // break dopo la prima camera selezionata
                        }
                    });

                    // Calcola la capacità selezionata, il prezzo totale e raggruppa le quantità per tipologia
                    $('.btr-room-quantity').each(function () {
                        const quantity = parseInt($(this).val(), 10) || 0;
                        
                        // Salta le camere non selezionate (quantity = 0)
                        if (quantity === 0) {
                            return true; // continue
                        }
                        
                        const roomType = $(this).data('room-type');
                        const pricePerPerson = parseFloat($(this).data('price-per-person'));
                        // Prezzo adulto base (senza supplemento) e supplemento per camera
                        const supplementoPP       = parseFloat($(this).data('supplemento')) || 0; // per persona
                        const capacity            = parseInt($(this).data('capacity'), 10);
                        const supplementoCamera   = supplementoPP * capacity; // per camera
                        const extraNightPP  = parseFloat($(this).data('extra-night-pp')) || 0;
                        const extraNightFlag = getExtraNightFlag() === 1;
                        const adultBasePrice      = pricePerPerson;   // il prezzo adulto NON sottrae più il supplemento
                        const regularPricePerson = pricePerPerson;

                        // --- BEGIN: children assigned logic for room pricing ---
                        const roomCard   = $(this).closest('.btr-room-card');
                        const roomIndex  = parseInt(roomCard.data('room-index'), 10);
                        // Conteggio bambini e neonati assegnati a questa camera
                        let assignedF1 = 0, assignedF2 = 0, assignedF3 = 0, assignedF4 = 0, assignedInfants = 0;
                        Object.entries(childAssignments).forEach(([cid, idx]) => {
                            if (idx === roomIndex) {
                                if      (cid.startsWith('f1-')) assignedF1++;
                                else if (cid.startsWith('f2-')) assignedF2++;
                                else if (cid.startsWith('f3-')) assignedF3++;
                                else if (cid.startsWith('f4-')) assignedF4++;
                                else if (cid.startsWith('infant-')) assignedInfants++;
                            }
                        });
                        // --- END: children and infants assigned logic for room pricing ---

                        // ---------------------------------------------------------------------------------
                        //  Calcolo prezzo camera:
                        //  1. iniziamo dal costo "tutti adulti" (adultBasePrice × posti) // niente supplemento camera qui
                        //  2. per ogni bambino assegnato sostituiamo un adulto:
                        //     – sottraiamo adultBasePrice (slot adulto che esce)
                        //     – aggiungiamo childPrice (slot bambino che entra)
                        // ---------------------------------------------------------------------------------
                        selectedCapacity += capacity * quantity;

                        const totalSlots        = capacity * quantity;

                        // Prezzi bambini ricevuti dal data-* (possono essere riferiti alla camera intera)
                        const rawChildF1 = parseFloat($(this).data('price-child-f1'));
                        const rawChildF2 = parseFloat($(this).data('price-child-f2'));
                        const rawChildF3 = parseFloat($(this).data('price-child-f3'));
                        const rawChildF4 = parseFloat($(this).data('price-child-f4'));

                        // Helper – se il prezzo sembra essere per l'intera camera (maggiore di un adulto × capienza)
                        // lo dividiamo per la capacità per ottenere il prezzo per persona; altrimenti lo usiamo così.
                        const getChildPrice = (raw, def) => {
                            if (isNaN(raw) || raw <= 0) return def;           // fallback su prezzo adulto
                            return raw > def * capacity ? raw / capacity : raw;
                        };

                        const priceChildF1 = getChildPrice(rawChildF1, adultBasePrice);
                        const priceChildF2 = getChildPrice(rawChildF2, adultBasePrice);
                        const priceChildF3 = getChildPrice(rawChildF3, adultBasePrice);
                        const priceChildF4 = getChildPrice(rawChildF4, adultBasePrice);

                        const usedF1 = Math.min(assignedF1, remainingChildF1);
                        const usedF2 = Math.min(assignedF2, remainingChildF2);
                        const usedF3 = Math.min(assignedF3, remainingChildF3);
                        const usedF4 = Math.min(assignedF4, remainingChildF4);
                        const usedInfants = Math.min(assignedInfants, remainingInfants);

                        // Prezzo adulto per persona (usa sempre il prezzo base per persona)
                        const adultPriceNoSupp = adultBasePrice;
                        const childF1PriceNoSupp = priceChildF1;
                        const childF2PriceNoSupp = priceChildF2;

                        // CALCOLO PREZZO CORRETTO: prezzo base + supplemento base + notti extra + supplemento extra
                        let localTotalPrice = 0;
                        
                        // Calcola persone effettive per slot (escludendo anche i neonati)
                        const adultsInRoom = Math.max(0, totalSlots - usedF1 - usedF2 - usedF3 - usedF4 - usedInfants);
                        const totalPersonsInRooms = adultsInRoom + usedF1 + usedF2 + usedF3 + usedF4;  // I neonati non contano per il prezzo
                        
                        // 1. PREZZO BASE (per il pacchetto base)
                        localTotalPrice += adultsInRoom * adultPriceNoSupp;
                        localTotalPrice += usedF1 * priceChildF1;
                        localTotalPrice += usedF2 * priceChildF2;
                        localTotalPrice += usedF3 * priceChildF3;
                        localTotalPrice += usedF4 * priceChildF4;
                        
                        // 2. SUPPLEMENTO BASE (per il pacchetto base)
                        // Il supplemento è "a persona, a notte" quindi va moltiplicato per le notti del pacchetto base
                        // Usa la variabile basePackageNights definita all'inizio della funzione
                        localTotalPrice += totalPersonsInRooms * supplementoPP * basePackageNights;

                        // 3. NOTTI EXTRA: Aggiungi costi notti extra se presenti
                        let extraNightDays = 0;
                        
                        // DEBUG AVANZATO: Log di tutti i valori critici
                        console.log('[BTR CALC] 🔍 DEBUG CALCOLO NOTTI EXTRA:');
                        console.log('[BTR CALC] extraNightFlag:', extraNightFlag);
                        console.log('[BTR CALC] extraNightPP:', extraNightPP);
                        console.log('[BTR CALC] window.btrExtraNightsCount:', window.btrExtraNightsCount);
                        console.log('[BTR CALC] typeof window.btrExtraNightsCount:', typeof window.btrExtraNightsCount);
                        
                        if (extraNightFlag && extraNightPP > 0) {
                            // Recupera il numero di notti extra dal backend
                            if (typeof window.btrExtraNightsCount === 'number' && window.btrExtraNightsCount > 0) {
                                extraNightDays = window.btrExtraNightsCount;
                                console.log('[BTR CALC] ✅ Usando window.btrExtraNightsCount:', extraNightDays);
                            } else if (window.btrExtraNightsCount === undefined) {
                                // FIX TEMPORANEO: Se le notti extra sono attive ma il conteggio è undefined,
                                // usa 1 come fallback per evitare la perdita del supplemento
                                console.warn('[BTR CALC] ⚠️ window.btrExtraNightsCount undefined - usando fallback 1');
                                console.warn('[BTR CALC] ⚠️ Questo è un fix temporaneo per €10 mancanti');
                                extraNightDays = 1; // FALLBACK: assumi 1 notte extra
                                console.warn('[BTR CALC] 🔧 FALLBACK applicato - extraNightDays impostato a 1');
                            } else {
                                // window.btrExtraNightsCount è 0 - notti extra non attive
                                console.log('[BTR CALC] ⚠️ window.btrExtraNightsCount è 0 - notti extra non attive?');
                                extraNightDays = 0;
                            }
                            
                            console.log('[BTR CALC] 🎯 extraNightDays finale utilizzato:', extraNightDays);
                            
                            // Costo notte extra per adulti (il prezzo extraNightPP è già per tutte le notti extra)
                            const extraNightAdultCost = adultsInRoom * extraNightPP;
                            localTotalPrice += extraNightAdultCost;
                            
                            // Costo notte extra per bambini (il prezzo è già per tutte le notti extra)
                            // Percentuali corrette basate sui prezzi reali: F1=37.5%, F2=50%, F3=70%, F4=80%
                            const extraNightChildF1Cost = usedF1 * (typeof calculateChildExtraNightPrice === 'function' ? calculateChildExtraNightPrice('f1', extraNightPP) : extraNightPP * 0.375);
                            const extraNightChildF2Cost = usedF2 * (typeof calculateChildExtraNightPrice === 'function' ? calculateChildExtraNightPrice('f2', extraNightPP) : extraNightPP * 0.5);
                            const extraNightChildF3Cost = usedF3 * (typeof calculateChildExtraNightPrice === 'function' ? calculateChildExtraNightPrice('f3', extraNightPP) : extraNightPP * 0.7);
                            const extraNightChildF4Cost = usedF4 * (typeof calculateChildExtraNightPrice === 'function' ? calculateChildExtraNightPrice('f4', extraNightPP) : extraNightPP * 0.8);
                            
                            localTotalPrice += extraNightChildF1Cost + extraNightChildF2Cost + extraNightChildF3Cost + extraNightChildF4Cost;
                            
                            // 4. SUPPLEMENTO EXTRA (per le notti extra) 
                            // Il supplemento deve essere applicato per ogni notte extra
                            const supplementoExtraCalculated = totalPersonsInRooms * supplementoPP * extraNightDays;
                            console.log('[BTR CALC] 💰 CALCOLO SUPPLEMENTO EXTRA:');
                            console.log('[BTR CALC] totalPersonsInRooms:', totalPersonsInRooms);
                            console.log('[BTR CALC] supplementoPP:', supplementoPP);
                            console.log('[BTR CALC] extraNightDays:', extraNightDays);
                            console.log('[BTR CALC] Supplemento extra calcolato:', supplementoExtraCalculated);
                            
                            localTotalPrice += supplementoExtraCalculated;
                        }
                        
                        // DEBUG: Log del calcolo del prezzo per camera
                        console.log(`[BTR PRICE DEBUG] Camera ${roomType} (qty: ${quantity}):`);
                        console.log(`  - Adulti: ${adultsInRoom} × €${adultPriceNoSupp.toFixed(2)} = €${(adultsInRoom * adultPriceNoSupp).toFixed(2)}`);
                        console.log(`  - Bambini F1: ${usedF1} × €${priceChildF1.toFixed(2)} = €${(usedF1 * priceChildF1).toFixed(2)}`);
                        console.log(`  - Bambini F2: ${usedF2} × €${priceChildF2.toFixed(2)} = €${(usedF2 * priceChildF2).toFixed(2)}`);
                        console.log(`  - Supplemento base: ${totalPersonsInRooms} × €${supplementoPP.toFixed(2)} × ${basePackageNights} notte = €${(totalPersonsInRooms * supplementoPP * basePackageNights).toFixed(2)}`);
                        if (extraNightFlag && extraNightPP > 0) {
                            console.log(`  - Notti extra adulti: ${adultsInRoom} × €${extraNightPP.toFixed(2)} = €${(adultsInRoom * extraNightPP).toFixed(2)}`);
                            if (usedF1 > 0) {
                                const childF1ExtraPrice = typeof calculateChildExtraNightPrice === 'function' ? calculateChildExtraNightPrice('f1', extraNightPP) : extraNightPP * 0.375;
                                console.log(`  - Notti extra bambini F1: ${usedF1} × €${childF1ExtraPrice.toFixed(2)} = €${(usedF1 * childF1ExtraPrice).toFixed(2)}`);
                            }
                            if (usedF2 > 0) {
                                const childF2ExtraPrice = typeof calculateChildExtraNightPrice === 'function' ? calculateChildExtraNightPrice('f2', extraNightPP) : extraNightPP * 0.5;
                                console.log(`  - Notti extra bambini F2: ${usedF2} × €${childF2ExtraPrice.toFixed(2)} = €${(usedF2 * childF2ExtraPrice).toFixed(2)}`);
                            }
                            console.log(`  - Supplemento extra: ${totalPersonsInRooms} × €${supplementoPP.toFixed(2)} × ${extraNightDays} notte = €${(totalPersonsInRooms * supplementoPP * extraNightDays).toFixed(2)}`);
                        }
                        console.log(`  - TOTALE CAMERA: €${localTotalPrice.toFixed(2)}`);
                        
                        // Arrotonda il prezzo locale per evitare drift
                        localTotalPrice = parseFloat(localTotalPrice.toFixed(2));

                        // Aggiorna contatori globali (riusa la variabile adultsInRoom già definita)
                        adultCount      += adultsInRoom;
                        adultTotal      += adultsInRoom * adultPriceNoSupp;
                        if (supplementoPP > 0) {
                            adultSupplemento = supplementoPP;
                        }

                        // Traccia adulti per tipo di camera
                        if (!adultsByRoomType[roomType]) {
                            adultsByRoomType[roomType] = {
                                count: 0,
                                total: 0,
                                priceNoSupp: adultPriceNoSupp,
                                supplemento: supplementoPP
                            };
                        }
                        adultsByRoomType[roomType].count += adultsInRoom;
                        adultsByRoomType[roomType].total += adultsInRoom * adultPriceNoSupp;

                        childF1Count    += usedF1;
                        childF1Total    += usedF1 * childF1PriceNoSupp;
                        if (supplementoPP > 0) {
                            childF1Supplemento = supplementoPP;
                        }

                        // Traccia bambini F1 per tipo di camera
                        if (!childF1ByRoomType[roomType]) {
                            childF1ByRoomType[roomType] = {
                                count: 0,
                                total: 0,
                                priceNoSupp: childF1PriceNoSupp,
                                supplemento: supplementoPP
                            };
                        }
                        childF1ByRoomType[roomType].count += usedF1;
                        childF1ByRoomType[roomType].total += usedF1 * childF1PriceNoSupp;

                        childF2Count    += usedF2;
                        childF2Total    += usedF2 * childF2PriceNoSupp;
                        if (supplementoPP > 0) {
                            childF2Supplemento = supplementoPP;
                        }

                        // Traccia bambini F2 per tipo di camera
                        if (!childF2ByRoomType[roomType]) {
                            childF2ByRoomType[roomType] = {
                                count: 0,
                                total: 0,
                                priceNoSupp: childF2PriceNoSupp,
                                supplemento: supplementoPP
                            };
                        }
                        childF2ByRoomType[roomType].count += usedF2;
                        childF2ByRoomType[roomType].total += usedF2 * childF2PriceNoSupp;

                        // --- F3 tracking and count ---
                        childF3Count    += usedF3;
                        childF3Total    += usedF3 * priceChildF3;
                        if (supplementoPP > 0) {
                            childF3Supplemento = supplementoPP;
                        }

                        // Traccia bambini F3 per tipo di camera
                        if (!childF3ByRoomType[roomType]) {
                            childF3ByRoomType[roomType] = {
                                count: 0,
                                total: 0,
                                priceNoSupp: priceChildF3,
                                supplemento: supplementoPP
                            };
                        }
                        childF3ByRoomType[roomType].count += usedF3;
                        childF3ByRoomType[roomType].total += usedF3 * priceChildF3;

                        // --- F4 tracking and count ---
                        childF4Count    += usedF4;
                        childF4Total    += usedF4 * priceChildF4;
                        if (supplementoPP > 0) {
                            childF4Supplemento = supplementoPP;
                        }

                        // Traccia bambini F4 per tipo di camera
                        if (!childF4ByRoomType[roomType]) {
                            childF4ByRoomType[roomType] = {
                                count: 0,
                                total: 0,
                                priceNoSupp: priceChildF4,
                                supplemento: supplementoPP
                            };
                        }
                        childF4ByRoomType[roomType].count += usedF4;
                        childF4ByRoomType[roomType].total += usedF4 * priceChildF4;

                        // Traccia neonati per tipo di camera
                        if (usedInfants > 0) {
                            if (!infantsByRoomType[roomType]) {
                                infantsByRoomType[roomType] = {
                                    count: 0
                                };
                            }
                            infantsByRoomType[roomType].count += usedInfants;
                        }

                        remainingChildF1 -= usedF1;
                        remainingChildF2 -= usedF2;
                        remainingChildF3 -= usedF3;
                        remainingChildF4 -= usedF4;
                        remainingInfants -= usedInfants;

                        // Prezzo pieno usato per mostrare il <del>
                        totalFullPrice += (capacity * quantity) * regularPricePerson;
                        totalPrice += localTotalPrice;
                        // normalizza i decimali
                        totalFullPrice = parseFloat(totalFullPrice.toFixed(2));

                        // Update visual total for this room card
                        roomCard.find('.btr-total-room-price-value')
                                .text(btrFormatPrice(localTotalPrice));

                        if (quantity > 0) {
                            if (!roomSummary[roomType]) {
                                roomSummary[roomType] = 0;
                            }
                            roomSummary[roomType] += quantity;
                        }
                        // (Supplemento camera non aggiunto qui: ora mostrato a parte)
                    });

                    // Crea il testo di riepilogo per le camere selezionate
                    let summaryParts = [];
                    let summaryLines = [];
                    for (let type in roomSummary) {
                        let plural = roomSummary[type] > 1 ? 'e' : '';
                        summaryParts.push(`${roomSummary[type]} camera${plural} ${type}`);
                        summaryLines.push(`${roomSummary[type]}x ${type}`);
                    }
                    let summaryText = summaryParts.join(' - ');
                    let summaryTextMultiline = summaryLines.join('<br>');

                    // Aggiorna il display della capacità totale e del report di camere selezionate
                    totalCapacityDisplay.html(`Totali posti selezionati: <strong>${selectedCapacity}/${requiredCapacity} | ${summaryText}</strong>`);
                    // Mostra solo il prezzo finale senza confronti
                    let priceHtml = `${btrFormatPrice(totalPrice)}`;
                    totalPriceDisplay.html(`Prezzo Totale: <strong>${priceHtml}</strong>`);
                    $('#btr-total-price-visual').html(priceHtml);

    // ───────────── Riepilogo adulti / bambini ────────────
                    // Add minimal styles for .btr-price-save, del
                                        var css = `
                        .btr-price-save {
                            color:#e3342f;
                            font-weight:600;
                        }
                        del {opacity:.6}
                        .btr-people-breakdown {margin-top:4px;font-size:.9em;opacity:.85}
                        .btr-child-btn.selected{
                            background:#0097c5;
                            color:#fff;
                            border-color:#0097c5;
                        }
                        `;


                    if (!document.getElementById('btr-price-save-style')) {
                        var style = document.createElement('style');
                        style.id = 'btr-price-save-style';
                        style.innerHTML = css;
                        document.head.appendChild(style);
                    }
                    // Style for btr-child-btn, only if not already present
                    if (!document.getElementById('btr-child-btn-style')) {
                        const styleChild = document.createElement('style');
                        styleChild.id = 'btr-child-btn-style';
                        styleChild.innerHTML = `
        .btr-child-selector { margin-top:1rem }
        .btr-child-group { display:flex; flex-wrap:wrap; align-items:center; gap:.5rem; margin:.5rem 0 }
        .btr-child-group-title { display:flex; align-items:center; gap:.35rem; font-size:.85rem; background:#f3f4f6; padding:.25rem .5rem; border-radius:4px; }
        .btr-child-group-title svg { stroke:#0097c5 }
        .btr-child-btn {
            border:1px solid #cbd5e1;
            background:#fff;
            padding:.4rem .6rem;
            border-radius:4px;
            font-size:.8rem;
            cursor:pointer;
            transition:all .2s;
        }
        .btr-child-btn:hover:not(:disabled) { background:#f1f5f9 }
        .btr-child-btn.selected { background:#0097c5; border-color:#0097c5; color:#fff }
        .btr-child-btn.assigned { opacity:.5; cursor:not-allowed }
        .btr-child-btn:disabled { opacity:.35; cursor:not-allowed }
        .btr-child-btn.btr-child-infant { 
            background:#e7f3ff; 
            border-color:#92c9f1; 
            color:#0066cc; 
        }
        .btr-child-btn.btr-child-infant.selected { 
            background:#0066cc; 
            border-color:#0066cc; 
            color:#fff; 
        }
        .btr-child-btn.would-violate-adult-rule {
            background:#fee2e2 !important;
            border-color:#ef4444 !important;
            color:#dc2626 !important;
            opacity:.7;
            cursor:not-allowed;
            position:relative;
        }
        .btr-child-btn.would-violate-adult-rule:hover {
            background:#fecaca !important;
            border-color:#dc2626 !important;
        }
        .btr-child-btn.would-violate-adult-rule::after {
            content:'\u26A0';
            position:absolute;
            top:-6px;
            right:-6px;
            background:#ef4444;
            color:#fff;
            width:16px;
            height:16px;
            border-radius:50%;
            font-size:10px;
            display:flex;
            align-items:center;
            justify-content:center;
            font-weight:bold;
            box-shadow:0 1px 3px rgba(0,0,0,0.3);
        }
        .btr-child-selector-label small {
            display:block;
            color:#ef4444;
            font-weight:normal;
            font-size:0.75rem;
            margin-top:0.25rem;
        }
    `;
                        document.head.appendChild(styleChild);
                    }

                    // Riepilogo persone – mostra sotto la sezione "Partecipanti"
                    if ($('#btr-people-breakdown').length === 0) {
                        // Inserisce il riepilogo subito prima del wrapper dei partecipanti
                        $('#btr-participants-wrapper')
                            .before('<div id="btr-people-breakdown" class="btr-people-breakdown"></div>');
                    }


                    // Calcola prezzo medio per persona
                    const adultPricePer   = adultCount  ? (adultTotal  / adultCount)  : 0;
                    const childF1PricePer = childF1Count? (childF1Total/ childF1Count): 0;
                    const childF2PricePer = childF2Count? (childF2Total/ childF2Count): 0;
                    const childF3PricePer = childF3Count? (childF3Total/ childF3Count): 0;
                    const childF4PricePer = childF4Count? (childF4Total/ childF4Count): 0;

                    // Calcolo etichetta data/notte extra dal server (preferito)
                    let extraNightDateLabel = response.data.extra_night_date || '';
                    let breakdownParts = [];

                    // Riepilogo adulti per tipo di camera
                    for (const roomType in adultsByRoomType) {
                        const data = adultsByRoomType[roomType];
                        if (data.count > 0) {
                            const roomsQty = roomSummary[roomType] || 0;
                            const labelPersone = `${data.count}x ${data.count > 1 ? btr_booking_form.labels.adult_plural : btr_booking_form.labels.adult_singular}`;
                            const pricePer = data.priceNoSupp;
                            let line = `${labelPersone} in ${roomsQty}x ${roomType} <strong>${btrFormatPrice(pricePer)}</strong> a persona`;
                            if (data.supplemento > 0) {
                                line += ` + supplemento <strong>${btrFormatPrice(data.supplemento)}</strong> a persona, a notte (${basePackageNights} ${basePackageNights === 1 ? 'notte' : 'notti'})`;
                            }
                            breakdownParts.push(line);

                            // Riga notte extra per adulti (numero dinamico di notti)
                            if (extraNightFlag && extraNightPP > 0) {
                                console.log('[BTR] 🔢 Generazione riepilogo - window.btrExtraNightsCount:', window.btrExtraNightsCount);
                                const extraNightsCount = (typeof window.btrExtraNightsCount === 'number' && window.btrExtraNightsCount > 0) ? window.btrExtraNightsCount : 1; // Default a 1 solo per visualizzazione
                                console.log('[BTR] 🔢 Valore usato per riepilogo:', extraNightsCount);
                                let extraLine = `<strong>${extraNightsCount} Nott${extraNightsCount === 1 ? 'e' : 'i'} extra</strong> ${extraNightDateLabel ? ' del ' + extraNightDateLabel : ''} <strong>${btrFormatPrice(extraNightPP)}</strong> a persona`;
                                if (data.supplemento > 0) {
                                    extraLine += ` + supplemento <strong>${btrFormatPrice(data.supplemento)}</strong> a persona, a notte (${extraNightsCount} ${extraNightsCount === 1 ? 'notte' : 'notti'})`;
                                }
                                breakdownParts.push(extraLine);
                            }
                        }
                    }

                    // Riepilogo bambini 3-11 per tipo di camera
                    for (const roomType in childF1ByRoomType) {
                        const data = childF1ByRoomType[roomType];
                        if (data.count > 0) {
                            // v1.0.183: Usa etichette dal DOM se disponibili
                            const f1Label = window.btrDynamicLabelsFromDOM?.f1 || labelChildF1;
                            const labelPersone = `${data.count}x ${f1Label}`;
                            const pricePer = data.priceNoSupp;
                            let line = `${labelPersone} in ${roomType} <strong>${btrFormatPrice(pricePer)}</strong> a persona`;
                            if (data.supplemento > 0) {
                                line += ` + supplemento <strong>${btrFormatPrice(data.supplemento)}</strong> a persona, a notte (${basePackageNights} ${basePackageNights === 1 ? 'notte' : 'notti'})`;
                            }
                            breakdownParts.push(line);

                            // Riga notte extra per bambini F1 (prezzo dinamico dal backend)
                            if (extraNightFlag && extraNightPP > 0) {
                                const childExtraF1 = response.data.rooms?.[0]?.extra_night_child_f1 || (extraNightPP * 0.375); // Usa prezzo dal backend o fallback 37.5%
                                const extraNightsCount = (typeof window.btrExtraNightsCount === 'number' && window.btrExtraNightsCount > 0) ? window.btrExtraNightsCount : 1; // Default a 1 solo per visualizzazione
                                let extraLine = `<strong>${extraNightsCount} Nott${extraNightsCount === 1 ? 'e' : 'i'} extra</strong> ${extraNightDateLabel ? ' del ' + extraNightDateLabel : ''} <strong>${btrFormatPrice(childExtraF1)}</strong> a persona`;
                                if (data.supplemento > 0) {
                                    extraLine += ` + supplemento <strong>${btrFormatPrice(data.supplemento)}</strong> a persona, a notte (${extraNightsCount} ${extraNightsCount === 1 ? 'notte' : 'notti'})`;
                                }
                                breakdownParts.push(extraLine);
                            }
                        }
                    }

                    // Riepilogo bambini 12-14 per tipo di camera
                    for (const roomType in childF2ByRoomType) {
                        const data = childF2ByRoomType[roomType];
                        if (data.count > 0) {
                            // v1.0.183: Usa etichette dal DOM se disponibili
                            const f2Label = window.btrDynamicLabelsFromDOM?.f2 || labelChildF2;
                            const labelPersone = `${data.count}x ${f2Label}`;
                            const pricePer = data.priceNoSupp;
                            let line = `${labelPersone} in ${roomType} <strong>${btrFormatPrice(pricePer)}</strong> a persona`;
                            if (data.supplemento > 0) {
                                line += ` + supplemento <strong>${btrFormatPrice(data.supplemento)}</strong> a persona, a notte (${basePackageNights} ${basePackageNights === 1 ? 'notte' : 'notti'})`;
                            }
                            breakdownParts.push(line);

                            // Riga notte extra per bambini F2 (prezzo dinamico dal backend)
                            if (extraNightFlag && extraNightPP > 0) {
                                const childExtraF2 = response.data.rooms?.[0]?.extra_night_child_f2 || (extraNightPP * 0.5); // Usa prezzo dal backend o fallback 50%
                                const extraNightsCount = (typeof window.btrExtraNightsCount === 'number' && window.btrExtraNightsCount > 0) ? window.btrExtraNightsCount : 1; // Default a 1 solo per visualizzazione
                                let extraLine = `<strong>${extraNightsCount} Nott${extraNightsCount === 1 ? 'e' : 'i'} extra</strong> ${extraNightDateLabel ? ' del ' + extraNightDateLabel : ''} <strong>${btrFormatPrice(childExtraF2)}</strong> a persona`;
                                if (data.supplemento > 0) {
                                    extraLine += ` + supplemento <strong>${btrFormatPrice(data.supplemento)}</strong> a persona, a notte (${extraNightsCount} ${extraNightsCount === 1 ? 'notte' : 'notti'})`;
                                }
                                breakdownParts.push(extraLine);
                            }
                        }
                    }

                    // Riepilogo bambini F3 per tipo di camera
                    for (const roomType in childF3ByRoomType) {
                        const data = childF3ByRoomType[roomType];
                        if (data.count > 0) {
                            // v1.0.183: Usa etichette dal DOM se disponibili
                            const f3Label = window.btrDynamicLabelsFromDOM?.f3 || labelChildF3;
                            const labelPersone = `${data.count}x ${f3Label}`;
                            const pricePer = data.priceNoSupp;
                            let line = `${labelPersone} in ${roomType} <strong>${btrFormatPrice(pricePer)}</strong> a persona`;
                            if (data.supplemento > 0) {
                                line += ` + supplemento <strong>${btrFormatPrice(data.supplemento)}</strong> a persona, a notte (${basePackageNights} ${basePackageNights === 1 ? 'notte' : 'notti'})`;
                            }
                            breakdownParts.push(line);

                            // Riga notte extra per bambini F3 (prezzo dinamico dal backend)
                            if (extraNightFlag && extraNightPP > 0) {
                                const childExtraF3 = response.data.rooms?.[0]?.extra_night_child_f3 || (extraNightPP * 0.7); // FIXED: Usa prezzo dal backend o fallback 70% CORRETTO
                                const extraNightsCount = (typeof window.btrExtraNightsCount === 'number' && window.btrExtraNightsCount > 0) ? window.btrExtraNightsCount : 1; // Default a 1 solo per visualizzazione
                                let extraLine = `<strong>${extraNightsCount} Nott${extraNightsCount === 1 ? 'e' : 'i'} extra</strong> ${extraNightDateLabel ? ' del ' + extraNightDateLabel : ''} <strong>${btrFormatPrice(childExtraF3)}</strong> a persona`;
                                if (data.supplemento > 0) {
                                    extraLine += ` + supplemento <strong>${btrFormatPrice(data.supplemento)}</strong> a persona, a notte (${extraNightsCount} ${extraNightsCount === 1 ? 'notte' : 'notti'})`;
                                }
                                breakdownParts.push(extraLine);
                            }
                        }
                    }

                    // Riepilogo bambini F4 per tipo di camera
                    for (const roomType in childF4ByRoomType) {
                        const data = childF4ByRoomType[roomType];
                        if (data.count > 0) {
                            // v1.0.183: Usa etichette dal DOM se disponibili
                            const f4Label = window.btrDynamicLabelsFromDOM?.f4 || labelChildF4;
                            const labelPersone = `${data.count}x ${f4Label}`;
                            const pricePer = data.priceNoSupp;
                            let line = `${labelPersone} in ${roomType} <strong>${btrFormatPrice(pricePer)}</strong> a persona`;
                            if (data.supplemento > 0) {
                                line += ` + supplemento <strong>${btrFormatPrice(data.supplemento)}</strong> a persona, a notte (${basePackageNights} ${basePackageNights === 1 ? 'notte' : 'notti'})`;
                            }
                            breakdownParts.push(line);

                            // Riga notte extra per bambini F4 (prezzo dinamico dal backend)
                            if (extraNightFlag && extraNightPP > 0) {
                                const childExtraF4 = response.data.rooms?.[0]?.extra_night_child_f4 || (extraNightPP * 0.8); // FIXED: Usa prezzo dal backend o fallback 80% CORRETTO
                                const extraNightsCount = (typeof window.btrExtraNightsCount === 'number' && window.btrExtraNightsCount > 0) ? window.btrExtraNightsCount : 1; // Default a 1 solo per visualizzazione
                                let extraLine = `<strong>${extraNightsCount} Nott${extraNightsCount === 1 ? 'e' : 'i'} extra</strong> ${extraNightDateLabel ? ' del ' + extraNightDateLabel : ''} <strong>${btrFormatPrice(childExtraF4)}</strong> a persona`;
                                if (data.supplemento > 0) {
                                    extraLine += ` + supplemento <strong>${btrFormatPrice(data.supplemento)}</strong> a persona, a notte (${extraNightsCount} ${extraNightsCount === 1 ? 'notte' : 'notti'})`;
                                }
                                breakdownParts.push(extraLine);
                            }
                        }
                    }
                    
                    // Riepilogo neonati per tipo di camera (non paganti)
                    for (const roomType in infantsByRoomType) {
                        const data = infantsByRoomType[roomType];
                        if (data.count > 0) {
                            const labelPersone = `${data.count}x ${data.count > 1 ? btr_booking_form.labels.infant_plural : btr_booking_form.labels.infant_singular}`;
                            const line = `${labelPersone} in ${roomType} <strong>Non ${data.count > 1 ? 'paganti' : 'pagante'}</strong> (${data.count > 1 ? 'occupano' : 'occupa'} ${data.count > 1 ? 'posti letto' : 'posto letto'})`;
                            breakdownParts.push(line);
                        }
                    }
                    
                    $('#btr-selected-people-list').html(breakdownParts.join(' <br> '));

                    // Versione multilinea per layout avanzati
                    $('#btr-total-capacity-visual').html(`<strong>${selectedCapacity}</strong> su <strong>${requiredCapacity}</strong>`);
                    $('#btr-required-room-list').html(summaryTextMultiline);

                    // Calcola la capacità rimanente
                    let remainingCapacity = requiredCapacity - selectedCapacity;

                    // Aggiorna gli input delle camere e i pulsanti +/-
                    $('.btr-room-quantity').each(function () {
                        const capacity = parseInt($(this).data('capacity'), 10);
                        const stock = parseInt($(this).attr('data-stock'), 10);
                        const currentQuantity = parseInt($(this).val(), 10) || 0;

                        let maxAllowedQuantity = Math.min(
                            stock,
                            currentQuantity + Math.floor(remainingCapacity / capacity)
                        );
                        if (maxAllowedQuantity < 0) {
                            maxAllowedQuantity = 0;
                        }
                        $(this).attr('max', maxAllowedQuantity);

                        const minusButton = $(this).siblings('.btr-minus');
                        const plusButton = $(this).siblings('.btr-plus');

                        minusButton.prop('disabled', currentQuantity <= 0);
                        plusButton.prop('disabled', (currentQuantity >= maxAllowedQuantity) || (remainingCapacity <= 0));

                        if (maxAllowedQuantity === 0 && currentQuantity === 0) {
                            $(this).prop('disabled', true);
                            $(this).closest('.btr-room-card').addClass('btr-disabled');
                        } else {
                            $(this).prop('disabled', false);
                            $(this).closest('.btr-room-card').removeClass('btr-disabled');
                        }
                    });

                    // Mostra un avviso se la capacità totale selezionata è inferiore a quella richiesta
                    if (selectedCapacity < requiredCapacity) {
                        bookingResponse.html('<p class="btr-warning">Attenzione: non tutte le persone hanno una sistemazione. Per favore, modifica la selezione delle camere.</p>');
                        assicurazioneButton.slideUp();
                        customerSection.slideUp();
                    } else {
                        bookingResponse.empty();
                        assicurazioneButton.slideDown();
                    }
                    // Aggiorna lo stato dei pulsanti bambino‑camera
                    refreshChildButtons();
                }

                // Inizializza le camere selezionate
                updateSelectedRooms();
            } else {
                // Gestione degli errori...
                showNoRoomsMessage();
                // bookingResponse.html(...) rimosso per evitare doppia visualizzazione
                // Aggiungi stile inline per il messaggio visivo se non già presente
                if (!document.getElementById('btr-no-rooms-style')) {
                    const noRoomsCss = `
                        .btr-no-rooms-message {
                            text-align: center;
                            padding: 20px;
                            background-color: #f8f9fa;
                            border: 1px solid #dee2e6;
                            border-radius: 8px;
                            color: #6c757d;
                            margin: 20px 0;
                        }
                        .btr-no-rooms-message h4 {
                            color: #495057;
                            margin-bottom: 10px;
                        }
                    `;
                    const style = document.createElement('style');
                    style.id = 'btr-no-rooms-style';
                    style.innerHTML = noRoomsCss;
                    document.head.appendChild(style);
                }
            }
        }).fail(function (jqXHR, textStatus, errorThrown) {
            // Gestione degli errori AJAX...
            console.error('Errore AJAX:', textStatus, errorThrown);
            showNoRoomsMessage();
        });
    }

function showNoRoomsMessage() {
    // Messaggi dinamici in base al tipo di errore
    let messageHTML = '';

    const tipo = getExtraNightFlag() === 1 ? 'notte_extra' : 'no_rooms';
    if (tipo === 'notte_extra') {
        messageHTML = `
            <h3>Notte extra non disponibile</h3>
            <p class="btr-no-rooms-message">
                <span>
                    Al momento l'opzione <strong>seconda notte</strong> non è disponibile per le date selezionate.<br>
                    Puoi procedere senza notte extra oppure scegliere date diverse.
                </span>
            </p>`;
    } else { // 'no_rooms' (default)
        messageHTML = `
            <h3>Nessuna camera disponibile</h3>
            <p class="btr-no-rooms-message">
                <span>
                    Spiacenti, non ci sono camere disponibili per questa configurazione.<br>
                    Prova a modificare il numero di partecipanti o le opzioni selezionate.
                </span>
            </p>`;
    }

    $('#btr-room-types-container').html(`
        <div class="btr-no-rooms-wrapper">
            <div class="btr-no-rooms-icon">
                <svg xmlns="http://www.w3.org/2000/svg" xml:space="preserve" viewBox="0 0 512 512"><path d="M471.971 424.301h-32.022V61.537C439.949 27.606 412.344 0 378.412 0H133.588C99.656 0 72.051 27.606 72.051 61.537v362.765H40.029v47.67h54.4c-3.947 4.255-6.366 9.946-6.366 16.193 0 13.143 10.693 23.835 23.835 23.835s23.835-10.693 23.835-23.835c0-6.248-2.419-11.938-6.366-16.193h253.268c-3.947 4.255-6.368 9.946-6.368 16.193 0 13.143 10.693 23.835 23.835 23.835s23.835-10.693 23.835-23.835c0-6.248-2.419-11.938-6.368-16.193h54.4v-47.671zM87.699 61.537c0-25.303 20.586-45.889 45.889-45.889h244.823c25.303 0 45.889 20.586 45.889 45.889v362.765H263.824v-112.08h-159.75v112.08H87.699zm160.477 266.332v96.432H119.722v-96.432zM111.898 496.352c-4.515 0-8.188-3.673-8.188-8.188s3.673-8.188 8.188-8.188 8.188 3.673 8.188 8.188-3.674 8.188-8.188 8.188m288.204 0c-4.515 0-8.188-3.673-8.188-8.188s3.673-8.188 8.188-8.188 8.188 3.673 8.188 8.188-3.672 8.188-8.188 8.188m56.222-40.028H55.676v-16.375h400.647z" style="fill:#0097c5"/><path d="M167.756 287.841h32.386v16.193h15.648v-31.841h-63.682v31.841h15.648zM136.092 336.058h15.648v80.053h-15.648zM216.156 336.058h15.648v80.053h-15.648zM407.926 115.901c0-31.014-23.709-56.59-53.954-59.567V23.835h-15.648v33.009c-28.389 4.667-50.12 29.366-50.12 59.056v292.025h119.722zm-15.648 76.235h-32.386v-16.375h32.386zm-32.022 15.647v24.381h-24.381v-40.393h8.369v16.012zm32.022-47.669h-48.034v16.012h-24.017v71.688h55.676v-40.029h16.375V392.28h-88.427V115.901c0-24.379 19.834-44.213 44.213-44.213s44.214 19.834 44.214 44.213z" style="fill:#0097c5"/></svg>
            </div>
            ${messageHTML}
        </div>
    `);
}


    // Toast notification system
    function btrShowToast(message, type = 'info') {
        let container = $('#btr-toast-container');
        if (!container.length) {
            container = $('<div id="btr-toast-container" class="btr-toast-container"></div>');
            $('body').append(container);
        }

        const toast = $('<div class="btr-toast ' + type + '">' + message + '</div>');

        // Aggiungi un pulsante di chiusura per i messaggi di errore
        if (type === 'error') {
            const closeButton = $('<button class="btr-toast-close">&times;</button>');
            toast.append(closeButton);

            // Gestisci il click sul pulsante di chiusura
            closeButton.on('click', function() {
                toast.addClass('disappearing');
                setTimeout(() => toast.remove(), 300);
            });
        }

        container.append(toast);
        setTimeout(() => toast.addClass('visible'), 100);

        // Per i messaggi di errore, non impostare un timeout automatico
        if (type !== 'error') {
            setTimeout(() => {
                toast.addClass('disappearing');
                setTimeout(() => toast.remove(), 300); // delay matches transition duration
            }, 5000);
        }
    }

    //btrShowToast('Messaggio di esempio', 'error');
    // Step 3: Procedi alla Sezione del Cliente
    proceedButton.on('click', function (e) {
        e.preventDefault();

        const requiredFields = [
            'nome',
            'cognome',
            'email',
            'telefono'
        ];

        let allValid = true;
        let firstInvalid = null;

        $('[name^="anagrafici[0]"]').removeClass('btr-error');
        $('.btr-error-msg').remove();

        const fieldLabels = {
            nome: 'Nome',
            cognome: 'Cognome',
            email: 'Email',
            telefono: 'Telefono'
        };

        const errorMessages = [];

        requiredFields.forEach(function (field) {
            const selector = '[name="anagrafici[0][' + field + ']"]';
            const input = $(selector);
            const val = input.val()?.trim();

            if (!val) {
                allValid = false;
                if (!firstInvalid) firstInvalid = input;
                input.addClass('btr-error');
                const label = fieldLabels[field] || field;
                errorMessages.push('Compila il campo obbligatorio: ' + label);
                return;
            }

            if (field === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
                allValid = false;
                if (!firstInvalid) firstInvalid = input;
                input.addClass('btr-error');
                errorMessages.push('Email non valida');
            }

            if (field === 'telefono' && !/^[\+0-9]{8,}$/.test(val)) {
                allValid = false;
                if (!firstInvalid) firstInvalid = input;
                input.addClass('btr-error');
                errorMessages.push('Numero di telefono non valido');
            }

            if (field === 'codice_fiscale' && val.length !== 16) {
                allValid = false;
                if (!firstInvalid) firstInvalid = input;
                input.addClass('btr-error');
                errorMessages.push('Codice fiscale non valido (16 caratteri)');
            }

            if (field === 'data_nascita') {
                const birthDate = new Date(val);
                const today = new Date();
                let age = today.getFullYear() - birthDate.getFullYear();
                const m = today.getMonth() - birthDate.getMonth();
                const d = today.getDate() - birthDate.getDate();
                if (m < 0 || (m === 0 && d < 0)) {
                    age--;
                }
                if (isNaN(birthDate.getTime())) {
                    allValid = false;
                    if (!firstInvalid) firstInvalid = input;
                    input.addClass('btr-error');
                    errorMessages.push('Data di nascita non valida');
                } else if (age < 18) {
                    allValid = false;
                    if (!firstInvalid) firstInvalid = input;
                    input.addClass('btr-error');
                    errorMessages.push('Il partecipante deve essere maggiorenne');
                }
            }
        });

        if (!allValid) {
            // Mostra solo il primo errore come toast
            if (errorMessages.length > 0) {
                btrShowToast(errorMessages[0], 'error');
            }

            // Elenco completo nel blocco errori in alto
            const alertHtml = '<ul>' + errorMessages.map(msg => `<li>${msg}</li>`).join('') + '</ul>';
            $('#btr-validation-alert').remove();
            $('<div id="btr-validation-alert" class="btr-global-alert btr-error-msg"></div>')
                .html(alertHtml)
                .insertBefore('#btr-step-content');

            if (firstInvalid) {
                $('html, body').animate({
                    scrollTop: firstInvalid.offset().top - 150
                }, 500);
                firstInvalid.focus();
            }

            return false;
        } else {
            $('#btr-validation-alert').remove();
        }

        var title = $(this).data('title');
        var desc = $(this).data('desc');

        $('#title-step').text(title);
        $('#desc-step').text(desc);

        // Popola i campi del cliente con i dati del primo partecipante
        const firstNome = $('[name="anagrafici[0][nome]"]').val();
        const firstEmail = $('[name="anagrafici[0][email]"]').val();

        $('#btr_cliente_nome').val(firstNome);
        $('#btr_cliente_email').val(firstEmail);

        customerSection.slideDown();
        proceedButton.slideUp();
    });

    /**
     * Genera un breakdown dettagliato di tutti i calcoli effettuati per il preventivo
     * Include prezzi base, supplementi, notti extra e totali organizzati per categoria
     */
    function generateDetailedCalculationBreakdown() {
        const numAdults = parseInt($('#btr_num_adults').val(), 10) || 0;
        const numChildF1 = parseInt($('#btr_num_child_f1').val(), 10) || 0;
        const numChildF2 = parseInt($('#btr_num_child_f2').val(), 10) || 0;
        const numChildF3 = parseInt($('#btr_num_child_f3').val(), 10) || 0;
        const numChildF4 = parseInt($('#btr_num_child_f4').val(), 10) || 0;
        
        const extraNightFlag = getExtraNightFlag() === 1;
        let extraNightPP = 0;
        let supplementoPP = 0;
        
        // Recupera i valori dai data attributes delle camere selezionate
        $('.btr-room-quantity').each(function () {
            const qty = parseInt($(this).val(), 10) || 0;
            if (qty > 0) {
                extraNightPP = parseFloat($(this).data('extra-night-pp')) || 0;
                supplementoPP = parseFloat($(this).data('supplemento')) || 0;
                console.log('[BREAKDOWN] Valori recuperati dai data attributes:', {
                    extraNightPP,
                    supplementoPP,
                    from: $(this).attr('class')
                });
                return false; // break - usa la prima camera con quantità > 0
            }
        });
        
        // v1.0.181 - Usa SOLO etichette dinamiche dal backend (NO hardcoded)
        const dynamicLabels = window.btrDynamicChildLabels || {};
        const labelChildF1 = window.btrDynamicCategories?.f1?.label || dynamicLabels.f1 || '';
        const labelChildF2 = window.btrDynamicCategories?.f2?.label || dynamicLabels.f2 || '';
        const labelChildF3 = window.btrDynamicCategories?.f3?.label || dynamicLabels.f3 || '';
        const labelChildF4 = window.btrDynamicCategories?.f4?.label || dynamicLabels.f4 || '';
        
        // Ottieni i prezzi per categoria
        let adultPrice = 0, childF1Price = 0, childF2Price = 0, childF3Price = 0, childF4Price = 0;
        $('.btr-room-quantity').each(function () {
            const qty = parseInt($(this).val(), 10) || 0;
            if (qty > 0) {
                adultPrice = parseFloat($(this).data('price-per-person')) || 0;
                childF1Price = parseFloat($(this).data('price-child-f1')) || adultPrice;
                childF2Price = parseFloat($(this).data('price-child-f2')) || adultPrice;
                childF3Price = parseFloat($(this).data('price-child-f3')) || adultPrice;
                childF4Price = parseFloat($(this).data('price-child-f4')) || adultPrice;
                return false; // break
            }
        });
        
        // Calcola prezzi notti extra per bambini
        const childF1ExtraPrice = typeof calculateChildExtraNightPrice === 'function' ? 
            calculateChildExtraNightPrice('f1', extraNightPP) : extraNightPP * 0.375;
        const childF2ExtraPrice = typeof calculateChildExtraNightPrice === 'function' ? 
            calculateChildExtraNightPrice('f2', extraNightPP) : extraNightPP * 0.5;
        const childF3ExtraPrice = typeof calculateChildExtraNightPrice === 'function' ? 
            calculateChildExtraNightPrice('f3', extraNightPP) : extraNightPP * 0.7;
        const childF4ExtraPrice = typeof calculateChildExtraNightPrice === 'function' ? 
            calculateChildExtraNightPrice('f4', extraNightPP) : extraNightPP * 0.8;
        
        // Struttura breakdown dettagliato
        const breakdown = {
            partecipanti: {},
            camere: {},
            totali: {
                subtotale_prezzi_base: 0,
                subtotale_supplementi_base: 0,
                subtotale_notti_extra: 0,
                subtotale_supplementi_extra: 0,
                totale_generale: 0
            },
            notti_extra: {
                attive: extraNightFlag,
                data: extraNightFlag ? ($('#btr_selected_date').val() || '') : '',
                numero_notti: extraNightFlag ? 1 : 0,  // Solo 1 notte extra (la notte prima del check-in)
                prezzo_adulto_per_notte: extraNightPP
            }
        };
        
        // Calcoli per adulti
        if (numAdults > 0) {
            // Il pacchetto base è sempre 2 notti (check-in e check-out)
            const baseNights = 2;
            // Se ci sono notti extra, è solo 1 notte extra (la notte prima del check-in)
            const extraNights = extraNightFlag ? 1 : 0;
            
            breakdown.partecipanti.adulti = {
                quantita: numAdults,
                prezzo_base_unitario: adultPrice,
                subtotale_base: numAdults * adultPrice,
                supplemento_base_unitario: supplementoPP,
                subtotale_supplemento_base: numAdults * supplementoPP, // Supplemento per persona (non per notte)
                notte_extra_unitario: extraNightFlag ? extraNightPP : 0,
                subtotale_notte_extra: extraNightFlag ? numAdults * extraNightPP * extraNights : 0,
                supplemento_extra_unitario: extraNightFlag ? supplementoPP : 0,
                subtotale_supplemento_extra: extraNightFlag ? numAdults * supplementoPP * extraNights : 0, // Supplemento per 1 notte extra
                totale: (numAdults * adultPrice) + 
                        (numAdults * supplementoPP) + 
                        (extraNightFlag ? numAdults * extraNightPP * extraNights : 0) +
                        (extraNightFlag ? numAdults * supplementoPP * extraNights : 0)
            };
            
            breakdown.totali.subtotale_prezzi_base += breakdown.partecipanti.adulti.subtotale_base;
            breakdown.totali.subtotale_supplementi_base += breakdown.partecipanti.adulti.subtotale_supplemento_base;
            breakdown.totali.subtotale_notti_extra += breakdown.partecipanti.adulti.subtotale_notte_extra;
            breakdown.totali.subtotale_supplementi_extra += breakdown.partecipanti.adulti.subtotale_supplemento_extra;
        }
        
        // Calcoli per bambini F1
        if (numChildF1 > 0) {
            const baseNights = 2;
            const extraNights = extraNightFlag ? 1 : 0;
            
            breakdown.partecipanti.bambini_f1 = {
                etichetta: labelChildF1,
                quantita: numChildF1,
                prezzo_base_unitario: childF1Price,
                subtotale_base: numChildF1 * childF1Price,
                supplemento_base_unitario: supplementoPP,
                subtotale_supplemento_base: numChildF1 * supplementoPP, // Supplemento per persona (non per notte)
                notte_extra_unitario: extraNightFlag ? childF1ExtraPrice : 0,
                subtotale_notte_extra: extraNightFlag ? numChildF1 * childF1ExtraPrice * extraNights : 0,
                supplemento_extra_unitario: extraNightFlag ? supplementoPP : 0,
                subtotale_supplemento_extra: extraNightFlag ? numChildF1 * supplementoPP * extraNights : 0, // Supplemento per 1 notte extra
                totale: (numChildF1 * childF1Price) + 
                        (numChildF1 * supplementoPP) + 
                        (extraNightFlag ? numChildF1 * childF1ExtraPrice * extraNights : 0) +
                        (extraNightFlag ? numChildF1 * supplementoPP * extraNights : 0)
            };
            
            breakdown.totali.subtotale_prezzi_base += breakdown.partecipanti.bambini_f1.subtotale_base;
            breakdown.totali.subtotale_supplementi_base += breakdown.partecipanti.bambini_f1.subtotale_supplemento_base;
            breakdown.totali.subtotale_notti_extra += breakdown.partecipanti.bambini_f1.subtotale_notte_extra;
            breakdown.totali.subtotale_supplementi_extra += breakdown.partecipanti.bambini_f1.subtotale_supplemento_extra;
        }
        
        // Calcoli per bambini F2
        if (numChildF2 > 0) {
            const baseNights = 2;
            const extraNights = extraNightFlag ? 1 : 0;
            
            breakdown.partecipanti.bambini_f2 = {
                etichetta: labelChildF2,
                quantita: numChildF2,
                prezzo_base_unitario: childF2Price,
                subtotale_base: numChildF2 * childF2Price,
                supplemento_base_unitario: supplementoPP,
                subtotale_supplemento_base: numChildF2 * supplementoPP, // Supplemento per persona (non per notte)
                notte_extra_unitario: extraNightFlag ? childF2ExtraPrice : 0,
                subtotale_notte_extra: extraNightFlag ? numChildF2 * childF2ExtraPrice * extraNights : 0,
                supplemento_extra_unitario: extraNightFlag ? supplementoPP : 0,
                subtotale_supplemento_extra: extraNightFlag ? numChildF2 * supplementoPP * extraNights : 0, // Supplemento per 1 notte extra
                totale: (numChildF2 * childF2Price) + 
                        (numChildF2 * supplementoPP) + 
                        (extraNightFlag ? numChildF2 * childF2ExtraPrice * extraNights : 0) +
                        (extraNightFlag ? numChildF2 * supplementoPP * extraNights : 0)
            };
            
            breakdown.totali.subtotale_prezzi_base += breakdown.partecipanti.bambini_f2.subtotale_base;
            breakdown.totali.subtotale_supplementi_base += breakdown.partecipanti.bambini_f2.subtotale_supplemento_base;
            breakdown.totali.subtotale_notti_extra += breakdown.partecipanti.bambini_f2.subtotale_notte_extra;
            breakdown.totali.subtotale_supplementi_extra += breakdown.partecipanti.bambini_f2.subtotale_supplemento_extra;
        }
        
        // Calcoli per bambini F3 e F4 (se presenti)
        if (numChildF3 > 0) {
            const baseNights = 2;
            const extraNights = extraNightFlag ? 1 : 0;
            
            breakdown.partecipanti.bambini_f3 = {
                etichetta: labelChildF3,
                quantita: numChildF3,
                prezzo_base_unitario: childF3Price,
                subtotale_base: numChildF3 * childF3Price,
                supplemento_base_unitario: supplementoPP,
                subtotale_supplemento_base: numChildF3 * supplementoPP, // Supplemento per persona (non per notte)
                notte_extra_unitario: extraNightFlag ? childF3ExtraPrice : 0,
                subtotale_notte_extra: extraNightFlag ? numChildF3 * childF3ExtraPrice * extraNights : 0,
                supplemento_extra_unitario: extraNightFlag ? supplementoPP : 0,
                subtotale_supplemento_extra: extraNightFlag ? numChildF3 * supplementoPP * extraNights : 0, // Supplemento per 1 notte extra
                totale: (numChildF3 * childF3Price) + 
                        (numChildF3 * supplementoPP) + 
                        (extraNightFlag ? numChildF3 * childF3ExtraPrice * extraNights : 0) +
                        (extraNightFlag ? numChildF3 * supplementoPP * extraNights : 0)
            };
            
            breakdown.totali.subtotale_prezzi_base += breakdown.partecipanti.bambini_f3.subtotale_base;
            breakdown.totali.subtotale_supplementi_base += breakdown.partecipanti.bambini_f3.subtotale_supplemento_base;
            breakdown.totali.subtotale_notti_extra += breakdown.partecipanti.bambini_f3.subtotale_notte_extra;
            breakdown.totali.subtotale_supplementi_extra += breakdown.partecipanti.bambini_f3.subtotale_supplemento_extra;
        }
        
        if (numChildF4 > 0) {
            const baseNights = 2;
            const extraNights = extraNightFlag ? 1 : 0;
            
            breakdown.partecipanti.bambini_f4 = {
                etichetta: labelChildF4,
                quantita: numChildF4,
                prezzo_base_unitario: childF4Price,
                subtotale_base: numChildF4 * childF4Price,
                supplemento_base_unitario: supplementoPP,
                subtotale_supplemento_base: numChildF4 * supplementoPP, // Supplemento per persona (non per notte)
                notte_extra_unitario: extraNightFlag ? childF4ExtraPrice : 0,
                subtotale_notte_extra: extraNightFlag ? numChildF4 * childF4ExtraPrice * extraNights : 0,
                supplemento_extra_unitario: extraNightFlag ? supplementoPP : 0,
                subtotale_supplemento_extra: extraNightFlag ? numChildF4 * supplementoPP * extraNights : 0, // Supplemento per 1 notte extra
                totale: (numChildF4 * childF4Price) + 
                        (numChildF4 * supplementoPP) + 
                        (extraNightFlag ? numChildF4 * childF4ExtraPrice * extraNights : 0) +
                        (extraNightFlag ? numChildF4 * supplementoPP * extraNights : 0)
            };
            
            breakdown.totali.subtotale_prezzi_base += breakdown.partecipanti.bambini_f4.subtotale_base;
            breakdown.totali.subtotale_supplementi_base += breakdown.partecipanti.bambini_f4.subtotale_supplemento_base;
            breakdown.totali.subtotale_notti_extra += breakdown.partecipanti.bambini_f4.subtotale_notte_extra;
            breakdown.totali.subtotale_supplementi_extra += breakdown.partecipanti.bambini_f4.subtotale_supplemento_extra;
        }
        
        // Calcola totale generale
        breakdown.totali.totale_generale = breakdown.totali.subtotale_prezzi_base + 
                                         breakdown.totali.subtotale_supplementi_base + 
                                         breakdown.totali.subtotale_notti_extra + 
                                         breakdown.totali.subtotale_supplementi_extra;
        
        // Arrotonda tutti i valori a 2 decimali
        const roundBreakdown = (obj) => {
            for (let key in obj) {
                if (typeof obj[key] === 'number') {
                    obj[key] = parseFloat(obj[key].toFixed(2));
                } else if (typeof obj[key] === 'object' && obj[key] !== null) {
                    roundBreakdown(obj[key]);
                }
            }
        };
        roundBreakdown(breakdown);
        
        return breakdown;
    }

    /**
     * Raccoglie tutti i dati del form booking in formato JSON strutturato
     * @returns {Object} Dati completi del booking
     */
    function collectAllBookingData() {
        console.log('[BTR] 📋 Raccogliendo tutti i dati del booking...');
        
        // Metadata di base
        const metadata = {
            timestamp: new Date().toISOString(),
            user_agent: navigator.userAgent,
            url: window.location.href
        };
        
        // Dati pacchetto
        const packageData = {
            package_id: $('input[name="btr_package_id"]').val() || '',
            product_id: $('input[name="btr_product_id"]').val() || '',
            variant_id: $('input[name="selected_variant_id"]').val() || '',
            date_ranges_id: $('input[name="btr_date_ranges_id"]').val() || '',
            nome_pacchetto: $('input[name="btr_nome_pacchetto"]').val() || '',
            tipologia_prenotazione: $('input[name="btr_tipologia_prenotazione"]').val() || '',
            durata: parseInt($('input[name="btr_durata"]').val(), 10) || 0,
            selected_date: $('#btr_selected_date').val() || ''
        };
        
        // Estrai date check-in e check-out dalla selected_date
        let checkIn = '', checkOut = '';
        if (packageData.selected_date) {
            const dateMatch = packageData.selected_date.match(/(\d+)\s*-\s*(\d+)\s+([^\s]+)\s+(\d+)/);
            if (dateMatch) {
                const startDay = dateMatch[1];
                const endDay = dateMatch[2];
                const month = dateMatch[3];
                const year = dateMatch[4];
                checkIn = `${startDay} ${month} ${year}`;
                checkOut = `${endDay} ${month} ${year}`;
            }
        }
        
        // Dati cliente
        const customer = {
            nome: $('#btr_cliente_nome').val().trim(),
            email: $('#btr_cliente_email').val().trim()
        };
        
        // Conta partecipanti per fasce età
        const participants = {
            adults: parseInt($('#btr_num_adults').val(), 10) || 0,
            children: {
                f1: parseInt($('#btr_num_child_f1').val(), 10) || 0,
                f2: parseInt($('#btr_num_child_f2').val(), 10) || 0,
                f3: parseInt($('#btr_num_child_f3').val(), 10) || 0,
                f4: parseInt($('#btr_num_child_f4').val(), 10) || 0,
                total_children: (parseInt($('#btr_num_child_f1').val(), 10) || 0) + 
                               (parseInt($('#btr_num_child_f2').val(), 10) || 0) + 
                               (parseInt($('#btr_num_child_f3').val(), 10) || 0) + 
                               (parseInt($('#btr_num_child_f4').val(), 10) || 0)
            },
            infants: parseInt($('#btr_num_infants').val(), 10) || 0,
            total_people: (parseInt($('#btr_num_adults').val(), 10) || 0) + 
                         (parseInt($('#btr_num_child_f1').val(), 10) || 0) + 
                         (parseInt($('#btr_num_child_f2').val(), 10) || 0) + 
                         (parseInt($('#btr_num_child_f3').val(), 10) || 0) + 
                         (parseInt($('#btr_num_child_f4').val(), 10) || 0) +
                         (parseInt($('#btr_num_infants').val(), 10) || 0)
        };
        
        // Raccogli camere selezionate - usa il selettore corretto .btr-room-quantity
        let rooms = [];
        
        // Usa il selector corretto .btr-room-quantity che è quello effettivamente usato nel frontend
        $('.btr-room-quantity').each(function () {
            const quantity = parseInt($(this).val(), 10) || 0;
            if (quantity > 0) {
                const $roomCard = $(this).closest('.btr-room-card');
                const currentRoomIdx = $roomCard.data('room-index');
                const variationId = parseInt($(this).data('variation-id')) || parseInt($roomCard.data('variation-id')) || 0;
                
                // Cattura prezzi dai data attributes dell'input o della card
                const pricePerPerson = parseFloat($(this).data('price-per-person')) || 
                                      parseFloat($roomCard.data('price-per-person')) || 0;
                const supplemento = parseFloat($(this).data('supplemento')) || 
                                   parseFloat($roomCard.data('supplemento')) || 0;
                const extraNightPP = parseFloat($(this).data('extra-night-pp')) || 
                                    parseFloat($roomCard.data('extra-night-pp')) || 0;
                
                // Conta bambini assegnati a questa camera
                let assignedF1 = 0, assignedF2 = 0, assignedF3 = 0, assignedF4 = 0, assignedInfants = 0;
                
                if (typeof childAssignments !== 'undefined') {
                    Object.entries(childAssignments).forEach(([cid, idx]) => {
                        if (idx === currentRoomIdx) {
                            if (cid.startsWith('f1-')) assignedF1++;
                            else if (cid.startsWith('f2-')) assignedF2++;
                            else if (cid.startsWith('f3-')) assignedF3++;
                            else if (cid.startsWith('f4-')) assignedF4++;
                            else if (cid.startsWith('infant-')) assignedInfants++;
                        }
                    });
                }
                
                // Calcola adulti assegnati - FIX v1.0.194: Rispetta regola "almeno 1 adulto per camera"
                const capacity = parseInt($(this).data('capacity')) || parseInt($roomCard.data('capacity')) || 2;
                const totalAssignedChildren = assignedF1 + assignedF2 + assignedF3 + assignedF4;
                
                // Determina adulti minimi richiesti per tipo camera
                const roomType = $(this).data('room-type') || $roomCard.find('.btr-card-title').text() || 'doppia';
                const roomTypeLower = roomType.toLowerCase();
                const isSingle = roomTypeLower.includes('singola') || roomTypeLower.includes('single');
                const requiresAdult = roomTypeLower.includes('doppia') || roomTypeLower.includes('double') ||
                                     roomTypeLower.includes('tripla') || roomTypeLower.includes('triple') ||
                                     roomTypeLower.includes('quadrupla') || roomTypeLower.includes('quadruple') ||
                                     roomTypeLower.includes('quintupla') || roomTypeLower.includes('quintuple');
                
                // Calcolo adulti: singole = 1 adulto fisso, altre camere = almeno 1 adulto
                const minAdults = isSingle ? 1 : (requiresAdult ? Math.min(quantity, 1) : 0);
                
                // CORREZIONE v1.0.203: Distribuzione proporzionale degli adulti
                const totalAdultsInForm = parseInt($('#btr_num_adults').val(), 10) || 0;
                
                let assignedAdults = 0;
                if (totalAdultsInForm > 0 && quantity > 0) {
                    // Conta totale camere per distribuzione proporzionale
                    let totalRoomCount = 0;
                    $('.btr-room-quantity').each(function() {
                        const q = parseInt($(this).val(), 10) || 0;
                        if (q > 0) {
                            totalRoomCount += q;
                        }
                    });
                    
                    if (totalRoomCount > 0) {
                        // Distribuisci proporzionalmente gli adulti
                        const adultsPerRoom = Math.ceil(totalAdultsInForm / totalRoomCount);
                        assignedAdults = Math.min(adultsPerRoom * quantity, totalAdultsInForm);
                        
                        // Rispetta capacità disponibile
                        const availableCapacity = (capacity * quantity) - totalAssignedChildren - assignedInfants;
                        assignedAdults = Math.min(assignedAdults, availableCapacity);
                        
                        // Assicura minimo richiesto
                        assignedAdults = Math.max(minAdults, assignedAdults);
                    } else {
                        assignedAdults = totalAdultsInForm;
                    }
                    
                    console.log('[BTR] 🔧 v1.0.203 - Booking data proportional distribution:', {
                        totalAdultsInForm,
                        totalRoomCount,
                        thisRoomQuantity: quantity,
                        assignedAdults
                    });
                } else {
                    // Fallback: usa la logica originale
                    assignedAdults = Math.max(minAdults, capacity - totalAssignedChildren - assignedInfants);
                }
                
                rooms.push({
                    variation_id: variationId,
                    tipo: $(this).data('room-type') || $roomCard.find('.btr-card-title').text() || 'doppia',
                    quantita: quantity,
                    prezzo_per_persona: pricePerPerson,
                    capacity: capacity,
                    regular_price: parseFloat($(this).data('regular-price')) || 0,
                    sale_price: parseFloat($(this).data('sale-price')) || 0,
                    supplemento: supplemento,
                    extra_night_pp: extraNightPP,
                    sconto: parseFloat($(this).data('sconto')) || 0,
                    price_child_f1: parseFloat($(this).data('price-child-f1')) || pricePerPerson * 0.7,
                    price_child_f2: parseFloat($(this).data('price-child-f2')) || pricePerPerson * 0.75,
                    price_child_f3: parseFloat($(this).data('price-child-f3')) || pricePerPerson * 0.8,
                    price_child_f4: parseFloat($(this).data('price-child-f4')) || pricePerPerson * 0.85,
                    assigned_adults: assignedAdults,
                    assigned_child_f1: assignedF1,
                    assigned_child_f2: assignedF2,
                    assigned_child_f3: assignedF3,
                    assigned_child_f4: assignedF4,
                    assigned_infants: assignedInfants,
                    assigned_adults: assignedAdults,
                    // Calcola totale camera
                    totale_camera: (assignedAdults * pricePerPerson) + 
                                  (assignedF1 * (parseFloat($(this).data('price-child-f1')) || pricePerPerson * 0.7)) +
                                  (assignedF2 * (parseFloat($(this).data('price-child-f2')) || pricePerPerson * 0.75)) +
                                  (assignedF3 * (parseFloat($(this).data('price-child-f3')) || pricePerPerson * 0.8)) +
                                  (assignedF4 * (parseFloat($(this).data('price-child-f4')) || pricePerPerson * 0.85)) +
                                  ((assignedAdults + totalAssignedChildren) * supplemento * 2) // Supplemento per 2 notti base
                });
            }
        });
        
        // Se non trova camere con il selector standard, usa i dati esistenti
        if (rooms.length === 0) {
            console.log('[BTR] ⚠️ Nessuna camera trovata con .btr-booking-card, uso dati alternativi');
            
            // Prova a usare i dati esistenti dalle camere selezionate
            const camereField = $('input[name="btr_camere_selezionate"]').val();
            if (camereField) {
                try {
                    const camereData = JSON.parse(camereField);
                    if (Array.isArray(camereData)) {
                        rooms = camereData.map(camera => ({
                            type: camera.tipo || camera.type || 'standard',
                            quantity: camera.quantita || camera.quantity || 1,
                            capacity: camera.capacita || camera.capacity || 2,
                            price: parseFloat(camera.prezzo || camera.price || 0),
                            occupants: {
                                adults: camera.adulti || camera.adults || 0,
                                children: camera.bambini || camera.children || 0,
                                infants: camera.neonati || camera.infants || 0
                            }
                        }));
                    }
                } catch (e) {
                    console.error('[BTR] Errore parsing dati camere:', e);
                }
            }
            
            // Se ancora non ci sono dati, crea array default basato su partecipanti
            if (rooms.length === 0 && participants.total_people > 0) {
                const roomsNeeded = Math.ceil(participants.total_people / 2);
                for (let i = 0; i < roomsNeeded; i++) {
                    rooms.push({
                        type: 'doppia',
                        quantity: 1,
                        capacity: 2,
                        price: 0,
                        occupants: {
                            adults: i === 0 ? Math.min(2, participants.adults) : 0,
                            children: i === 0 ? Math.min(2, participants.children.total_children) : 0,
                            infants: 0
                        }
                    });
                }
            }
        }
        
        // Dati extra nights - cattura il prezzo dalle camere se non disponibile nell'input nascosto
        let extraNightPricePerPerson = parseFloat($('#btr_extra_night_pp').val()) || 0;
        
        // Se non trova il prezzo nel campo nascosto, prova a prenderlo dalle camere
        if (extraNightPricePerPerson === 0 && rooms.length > 0) {
            for (const room of rooms) {
                if (room.extra_night_pp && room.extra_night_pp > 0) {
                    extraNightPricePerPerson = room.extra_night_pp;
                    break;
                }
            }
        }
        
        const extraNights = {
            enabled: getExtraNightFlag() === 1,
            price_per_person: extraNightPricePerPerson,
            total_cost: 0,
            date: ''
        };
        
        if (extraNights.enabled && extraNights.price_per_person > 0) {
            // Calcola il costo totale delle notti extra considerando le percentuali per bambini
            let extraNightsTotalCost = 0;
            
            // Adulti pagano il prezzo pieno
            extraNightsTotalCost += participants.adults * extraNights.price_per_person;
            
            // Bambini pagano percentuali diverse
            extraNightsTotalCost += participants.children.f1 * (extraNights.price_per_person * 0.375); // 37.5%
            extraNightsTotalCost += participants.children.f2 * (extraNights.price_per_person * 0.5);   // 50%
            extraNightsTotalCost += participants.children.f3 * (extraNights.price_per_person * 0.7);   // 70%
            extraNightsTotalCost += participants.children.f4 * (extraNights.price_per_person * 0.8);   // 80%
            // Neonati non pagano notti extra
            
            extraNights.total_cost = extraNightsTotalCost;
            
            // Calcola data notte extra (giorno prima del check-in)
            if (packageData.selected_date) {
                const dateMatch = packageData.selected_date.match(/(\d+)\s*-\s*(\d+)\s+([^\s]+)\s+(\d+)/);
                if (dateMatch) {
                    const startDay = parseInt(dateMatch[1]);
                    const month = dateMatch[3];
                    const year = dateMatch[4];
                    // La notte extra è il giorno prima del check-in
                    const extraNightDay = startDay - 1;
                    extraNights.date = `${extraNightDay} ${month} ${year}`;
                }
            }
        }
        
        // Raccogli costi extra durata con prezzi
        const extraCostsDuration = {};
        const extraCostsPrices = {};
        
        // Cattura i prezzi dei costi extra dal DOM
        $('.btr-extra-cost-checkbox, input[type="checkbox"][name*="costi_extra"]').each(function() {
            const $checkbox = $(this);
            const $label = $checkbox.closest('label');
            const $parent = $checkbox.closest('.btr-extra-cost-item, .form-check');
            
            // Estrai il nome/slug del costo extra
            let slug = '';
            const nameMatch = $checkbox.attr('name')?.match(/\[costi_extra\]\[([^\]]+)\]/);
            if (nameMatch) {
                slug = nameMatch[1];
            } else if ($checkbox.data('slug')) {
                slug = $checkbox.data('slug');
            } else if ($checkbox.val()) {
                slug = $checkbox.val();
            }
            
            if (slug) {
                // Cerca il prezzo nel DOM
                let price = 0;
                
                // Metodo 1: data-attribute (supporta valori negativi)
                if ($checkbox.data('importo') !== undefined) {
                    price = parseFloat($checkbox.data('importo')) || 0;
                } else if ($checkbox.data('price') !== undefined) {
                    price = parseFloat($checkbox.data('price')) || 0;
                } else if ($checkbox.data('cost') !== undefined) {
                    price = parseFloat($checkbox.data('cost')) || 0;
                }
                
                // Metodo 2: cerca nel testo del label
                if (price === 0) {
                    const labelText = $label.text() || $parent.text();
                    const priceMatch = labelText.match(/€\s*(\d+(?:[.,]\d+)?)/);
                    if (priceMatch) {
                        price = parseFloat(priceMatch[1].replace(',', '.'));
                    }
                }
                
                // Metodo 3: cerca in elementi vicini
                if (price === 0) {
                    const $priceElement = $parent.find('.price, .costo, .prezzo, .cost');
                    if ($priceElement.length) {
                        const priceText = $priceElement.text();
                        const priceMatch = priceText.match(/(\d+(?:[.,]\d+)?)/);
                        if (priceMatch) {
                            price = parseFloat(priceMatch[1].replace(',', '.'));
                        }
                    }
                }
                
                // Salva il prezzo trovato (includi valori negativi per riduzioni)
                if (price !== 0) {
                    extraCostsPrices[slug] = price;
                }
                
                // Se è selezionato, aggiungilo anche a extraCostsDuration
                if ($checkbox.is(':checked')) {
                    extraCostsDuration[slug] = {
                        selected: true,
                        price: price
                    };
                }
            }
        });
        
        // Valori di fallback per costi extra noti
        if (!extraCostsPrices['animale-domestico'] || extraCostsPrices['animale-domestico'] === 0) {
            extraCostsPrices['animale-domestico'] = 10;
        }
        if (!extraCostsPrices['culla-per-neonati'] || extraCostsPrices['culla-per-neonati'] === 0) {
            extraCostsPrices['culla-per-neonati'] = 15;
        }
        
        console.log('[BTR] 💰 Prezzi costi extra trovati:', extraCostsPrices);
        
        // Raccogli dati anagrafici partecipanti
        const anagrafici = [];
        $('.btr-partecipante-form, #btr-assicurazioni-container .btr-person-card, .btr-person-card').each(function(index) {
            const participant = {
                index: index,
                nome: $(this).find('input[name*="[nome]"]').val() || '',
                cognome: $(this).find('input[name*="[cognome]"]').val() || '',
                email: $(this).find('input[name*="[email]"]').val() || '',
                telefono: $(this).find('input[name*="[telefono]"]').val() || '',
                data_nascita: $(this).find('input[name*="[data_nascita]"]').val() || '',
                citta_nascita: $(this).find('input[name*="[citta_nascita]"]').val() || '',
                citta_residenza: $(this).find('input[name*="[citta_residenza]"]').val() || '',
                provincia_residenza: $(this).find('input[name*="[provincia_residenza]"]').val() || '',
                codice_fiscale: $(this).find('input[name*="[codice_fiscale]"]').val() || '',
                indirizzo_residenza: $(this).find('input[name*="[indirizzo_residenza]"]').val() || '',
                cap_residenza: $(this).find('input[name*="[cap_residenza]"]').val() || '',
                assicurazioni: {},
                costi_extra: {}
            };
            
            // Raccogli assicurazioni
            $(this).find('input[name*="[assicurazioni]"][type="checkbox"]:checked').each(function() {
                const name = $(this).attr('name');
                const match = name.match(/\[assicurazioni\]\[([^\]]+)\]/);
                if (match && match[1]) {
                    participant.assicurazioni[match[1]] = true;
                }
            });
            
            // Raccogli costi extra per questo partecipante con prezzi
            $(this).find('input[name*="[costi_extra]"][type="checkbox"]:checked').each(function() {
                const name = $(this).attr('name');
                const match = name.match(/\[costi_extra\]\[([^\]]+)\]/);
                if (match && match[1]) {
                    const slug = match[1];
                    // Includi sia il flag che il prezzo
                    participant.costi_extra[slug] = {
                        selected: true,
                        price: extraCostsPrices[slug] || 0
                    };
                }
            });
            
            anagrafici.push(participant);
        });
        
        // Se non ci sono partecipanti, crea almeno il primo dai campi obbligatori
        if (anagrafici.length === 0) {
            const firstParticipant = {
                index: 0,
                nome: $('input[name*="[nome]"]').first().val() || '',
                cognome: $('input[name*="[cognome]"]').first().val() || '',
                email: $('input[name*="[email]"]').first().val() || '',
                telefono: $('input[name*="[telefono]"]').first().val() || '',
                data_nascita: '',
                citta_nascita: '',
                citta_residenza: '',
                provincia_residenza: '',
                codice_fiscale: '',
                indirizzo_residenza: '',
                cap_residenza: '',
                assicurazioni: {},
                costi_extra: {}
            };
            
            // Raccogli costi extra globali per il primo partecipante con prezzi
            $('input[name*="[costi_extra]"][type="checkbox"]:checked').each(function() {
                const name = $(this).attr('name');
                const match = name.match(/\[costi_extra\]\[([^\]]+)\]/);
                if (match && match[1]) {
                    const slug = match[1];
                    firstParticipant.costi_extra[slug] = {
                        selected: true,
                        price: extraCostsPrices[slug] || 0
                    };
                }
            });
            
            anagrafici.push(firstParticipant);
        }
        
        // *** NUOVO: LETTURA DATI DA JSON STATE MANAGER ***

        // Helper robusto per parsing prezzo localizzato (IT/EN)
        function parsePriceLocalized(input) {
            if (input == null) return 0;
            let s = String(input).trim();
            if (!s) return 0;
            // Rimuovi simboli e spazi
            s = s.replace(/\u00A0|\s|€/g, '');
            // Caso: solo migliaia tipo 1.279 (senza decimali)
            if (/^\d+\.\d{3}$/.test(s)) {
                return Number(s.replace(/\./g, ''));
            }
            // Caso: ha migliaia e decimali (1.234,56 o 1,234.56)
            if (/^\d{1,3}([\.,]\d{3})+([\.,]\d+)?$/.test(s)) {
                const lastDot = s.lastIndexOf('.');
                const lastComma = s.lastIndexOf(',');
                const decPos = Math.max(lastDot, lastComma);
                if (decPos >= 0) {
                    const decSep = s[decPos];
                    const thousandSep = decSep === '.' ? ',' : '.';
                    s = s.split(thousandSep).join('');
                    if (decSep === ',') s = s.replace(',', '.');
                }
                return Number(s);
            }
            // Caso semplice: solo virgola decimale
            if (s.includes(',') && !s.includes('.')) {
                s = s.replace(',', '.');
            }
            // Rimuovi eventuali separatori residui di migliaia
            if (/^\d{1,3}(?:[\.,]\d{3})+$/.test(s)) {
                s = s.replace(/[\.,]/g, '');
            }
            const n = Number(s);
            return isNaN(n) ? 0 : n;
        }
        let pricing = {
            total_price: 0,
            breakdown_available: false,
            totale_camere: 0,
            totale_costi_extra: 0,
            totale_assicurazioni: 0,
            totale_generale_display: 0
        };
        
        // Priorità 1: Leggi dal State Manager centralizzato se disponibile
        if (window.btrBookingState && window.btrBookingState.totale_generale > 0) {
            const stateData = window.btrBookingState.getPayloadData();
            pricing = {
                total_price: stateData.pricing_total_price,
                breakdown_available: true,
                totale_camere: stateData.pricing_totale_camere,
                totale_costi_extra: stateData.pricing_total_extra_costs,
                totale_assicurazioni: stateData.pricing_totale_assicurazioni || 0,
                totale_generale_display: stateData.pricing_total_price,
                // Dati aggiuntivi dal state manager
                totale_riduzioni: stateData.pricing_total_riduzioni,
                costi_extra_dettagliati: stateData.pricing_costi_extra_dettagliati
            };
            
            console.log('[BTR] 📊 Pricing data dal State Manager:', pricing);
        } else {
            // Fallback: Metodo tradizionale DOM scraping
            console.log('[BTR] ⚠️ State Manager non disponibile, fallback a DOM scraping');
            
            // Cattura il totale visualizzato nel DOM (MIGLIORATO per pannello summary dinamico)
            let totalDisplayFound = false;
            
            // Trova il pannello summary dinamico (condiviso per entrambi i calcoli)
            const $summaryPanel = $('.btr-summary-box, .btr-summary-moved');
            
            // Metodo 1: Cerca nel pannello summary dinamico (priorità alta)
            if ($summaryPanel.length) {
                // Cerca elemento che contiene "TOTALE DA PAGARE" nel pannello summary
                $summaryPanel.find('*').each(function() {
                    const text = $(this).text();
                    if (text.includes('TOTALE DA PAGARE') || text.includes('Totale finale') || text.includes('TOTALE FINALE')) {
                        const match = text.match(/€?\s*([\d\.,]+)/);
                        if (match) {
                            pricing.totale_generale_display = parsePriceLocalized(match[1]);
                            totalDisplayFound = true;
                            console.log('[BTR] 📊 Totale finale dal pannello summary (fallback):', pricing.totale_generale_display);
                            return false; // Break
                        }
                    }
                });
                
                // Fallback: cerca nel display price del pannello (btr-total-price-visual)
                if (!totalDisplayFound) {
                    const $priceVisual = $summaryPanel.find('#btr-total-price-visual, .btr-price-amount');
                    if ($priceVisual.length) {
                        const priceText = $priceVisual.text();
                        const priceMatch = priceText.match(/€?\s*([\d\.,]+)/);
                        if (priceMatch) {
                            pricing.totale_generale_display = parsePriceLocalized(priceMatch[1]);
                            totalDisplayFound = true;
                            console.log('[BTR] 📊 Totale finale dal visual price (fallback):', pricing.totale_generale_display);
                        }
                    }
                }
            }
            
            // Metodo 2: Fallback sui selettori tradizionali
        if (!totalDisplayFound) {
            const $totaleDisplay = $('.totale-generale, .total-price, #total-price, .btr-total-price, .btr-totale-generale');
            if ($totaleDisplay.length) {
                const totaleText = $totaleDisplay.text();
                const totaleMatch = totaleText.match(/€?\s*([\d\.,]+)/);
                if (totaleMatch) {
                    pricing.totale_generale_display = parsePriceLocalized(totaleMatch[1]);
                    console.log('[BTR] 📊 Totale finale dai selettori tradizionali:', pricing.totale_generale_display);
                }
            }
        }
        
        // Cattura il totale camere dal riepilogo (MIGLIORATO per pannello summary dinamico)
        let camereTotalFound = false;
        
        // Metodo 1: Cerca nel pannello summary dinamico (priorità alta)
        if ($summaryPanel.length) {
            // Cerca elemento che contiene "Totale Camere" nel pannello summary
            $summaryPanel.find('*').each(function() {
                const text = $(this).text();
                if (text.includes('Totale Camere') || text.includes('TOTALE CAMERE')) {
                    const match = text.match(/€?\s*([\d\.,]+)/);
                    if (match) {
                        pricing.totale_camere = parsePriceLocalized(match[1]);
                        camereTotalFound = true;
                        console.log('[BTR] 📊 Totale camere dal pannello summary:', pricing.totale_camere);
                        return false; // Break
                    }
                }
            });
        }
        
        // Metodo 2: Fallback sui selettori tradizionali
        if (!camereTotalFound) {
            const $totaleCamere = $('.totale-camere, .rooms-total, .subtotale-camere, .btr-totale-camere, [data-total-rooms]');
            if ($totaleCamere.length) {
                const camereText = $totaleCamere.text();
            const camereMatch = camereText.match(/€?\s*([\d\.,]+)/);
            if (camereMatch) {
                pricing.totale_camere = parsePriceLocalized(camereMatch[1]);
                console.log('[BTR] 📊 Totale camere dai selettori tradizionali:', pricing.totale_camere);
            }
            }
        }
        
        // Se non trova il totale camere nel DOM, calcola dalle camere
        if (!pricing.totale_camere && rooms.length > 0) {
            pricing.totale_camere = rooms.reduce((sum, room) => {
                // Usa il totale_camera se disponibile, altrimenti calcola
                if (room.totale_camera) {
                    return sum + (room.totale_camera * room.quantita);
                }
                // Fallback: calcola dal prezzo per persona e capacità
                const prezzoPerPersona = room.prezzo_per_persona || room.price || 0;
                const persone = room.capacity || 2;
                const quantita = room.quantita || room.quantity || 1;
                const supplemento = room.supplemento || 0;
                const totaleBase = prezzoPerPersona * persone * quantita;
                const totaleSupplementi = supplemento * persone * quantita * 2; // 2 notti base
                return sum + totaleBase + totaleSupplementi;
            }, 0);
        }
        
        } // Fine del blocco else fallback
        
        // Calcola il totale dei costi extra selezionati (UNA SOLA fonte di verità) 
        // SPOSTATO FUORI dal blocco condizionale per essere sempre disponibile
        let totaleCostiExtra = 0;
        
        // CORREZIONE v1.0.128: UNA SOLA fonte di verità - SOLO dai partecipanti anagrafici
        // Se abbiamo il State Manager, usa i suoi dati (TEMPORANEAMENTE DISABILITATO)
        if (false && window.btrBookingState && window.btrBookingState.totale_generale > 0) {
            totaleCostiExtra = window.btrBookingState.totale_costi_extra + window.btrBookingState.totale_riduzioni;
        } else {
            // CORREZIONE CRITICA: Elimina duplicazioni usando SOLO i dati dei partecipanti
            console.log('[BTR] 🔧 CORREZIONE v1.0.128: Calcolo costi extra SOLO dai partecipanti (no duplicazioni)');
            
            // UNICO METODO: Raccogli costi extra dai partecipanti anagrafici
            anagrafici.forEach((partecipante, index) => {
                if (partecipante.costi_extra) {
                    Object.values(partecipante.costi_extra).forEach(costoExtra => {
                        if (costoExtra.selected && costoExtra.price !== 0) {
                            totaleCostiExtra += costoExtra.price;
                            console.log('[BTR] 📊 Partecipante', index + ':', costoExtra.price);
                        }
                    });
                }
            });
            
            console.log('[BTR] ✅ Totale costi extra (senza duplicazioni):', totaleCostiExtra, 'da', anagrafici.length, 'partecipanti');
        }
        
        pricing.totale_costi_extra = totaleCostiExtra;
        
        try {
            if (typeof generateDetailedCalculationBreakdown === 'function') {
                pricing.detailed_breakdown = generateDetailedCalculationBreakdown();
                pricing.breakdown_available = true;
                // Usa il totale dal display se disponibile, altrimenti dal calcolo
                pricing.total_price = pricing.totale_generale_display || 
                                     pricing.detailed_breakdown?.totali?.totale_generale || 0;
            }
        } catch (e) {
            console.warn('[BTR] ⚠️ Errore nel calcolo breakdown:', e);
            pricing.breakdown_available = false;
            // Usa il totale dal display come fallback
            pricing.total_price = pricing.totale_generale_display || 0;
        }
        
        // Se abbiamo il totale display ma non corrisponde al calcolo, usa quello del display
        if (pricing.totale_generale_display > 0 && 
            Math.abs(pricing.totale_generale_display - pricing.total_price) > 0.01) {
            console.log('[BTR] ⚠️ Discrepanza totale: display=' + pricing.totale_generale_display + 
                       ', calcolo=' + pricing.total_price + '. Uso display.');
            pricing.total_price = pricing.totale_generale_display;
        }
        
        // CORREZIONE CRITICA v1.0.127: Forza sempre il ricalcolo di TUTTI i totali
        // per assicurarsi che i costi extra (positivi e negativi) siano inclusi
        
        // Se abbiamo detailed_breakdown, usa quello per pricing_totale_camere
        if (pricing.detailed_breakdown && pricing.detailed_breakdown.totali && 
            pricing.detailed_breakdown.totali.totale_generale > 0) {
            console.log('[BTR] 🔧 CORREZIONE: Usando detailed_breakdown per totale camere');
            pricing.totale_camere = pricing.detailed_breakdown.totali.totale_generale;
        }
        
        // Se abbiamo il totale dal display DOM (più affidabile), usalo
        if (pricing.totale_generale_display > 0) {
            console.log('[BTR] 📊 Usando totale dal DOM:', pricing.totale_generale_display);
            pricing.totale_generale = pricing.totale_generale_display;
            pricing.total_price = pricing.totale_generale_display;
        } else {
            // SEMPRE ricalcola come somma dei componenti per includere costi extra
            console.log('[BTR] 🔄 Ricalcolo totale: camere(' + pricing.totale_camere + 
                       ') + extra(' + pricing.totale_costi_extra + 
                       ') + assicurazioni(' + pricing.totale_assicurazioni + ')');
            
            pricing.totale_generale = (pricing.totale_camere || 0) + 
                                     (pricing.totale_costi_extra || 0) + 
                                     (pricing.totale_assicurazioni || 0);
            pricing.total_price = pricing.totale_generale;
            
            console.log('[BTR] ✅ Totale finale calcolato:', pricing.totale_generale);
        }
        
        // Assemblaggio dati completi con TUTTI i dati necessari
        const completeData = {
            metadata: metadata,
            package: packageData,
            customer: customer,
            participants: participants,
            rooms: rooms,
            rooms_count: rooms.length,  // Conta corretta delle camere
            extra_nights: extraNights,
            extra_costs_duration: extraCostsDuration,
            extra_costs_prices: extraCostsPrices,  // Prezzi dei costi extra
            anagrafici: anagrafici,
            pricing: pricing,
            dates: {
                check_in: checkIn,
                check_out: checkOut,
                extra_night_date: extraNights.date
            },
            // Aggiungi totali dettagliati
            totals_breakdown: {
                rooms_total: pricing.totale_camere,
                extra_costs_total: pricing.totale_costi_extra,
                insurances_total: pricing.totale_assicurazioni,
                grand_total: pricing.total_price,
                display_total: pricing.totale_generale_display
            }
        };
        
        console.log('[BTR] ✅ Dati booking raccolti:', completeData);
        return completeData;
    }

    /**
     * Utility generica per appiattire ricorsivamente qualsiasi struttura JSON
     * in campi FormData con notazione bracket senza hardcode di nomi
     * 
     * @param {*} node - Nodo corrente da processare (object, array o primitiva)
     * @param {FormData} form - FormData dove aggiungere i campi appiattiti
     * @param {string} prefix - Prefisso corrente per il path (default: 'btr_flat')
     */
    function addFlattened(node, form, prefix = 'btr_flat') {
        // Normalizza il valore primitivo in stringa
        function normalizeValue(value) {
            if (value === null || value === undefined) {
                return '';
            }
            if (typeof value === 'boolean') {
                return value ? 'true' : 'false';
            }
            if (typeof value === 'number') {
                return String(value);
            }
            return String(value);
        }
        
        // Funzione ricorsiva interna per traversare la struttura
        function traverse(current, path) {
            if (current === null || current === undefined) {
                // Valore null/undefined - aggiungi come stringa vuota
                form.append(path, '');
                return;
            }
            
            if (Array.isArray(current)) {
                // È un array - itera con indici numerici
                if (current.length === 0) {
                    // Array vuoto - aggiungi indicatore
                    form.append(path + '[_empty]', 'true');
                } else {
                    current.forEach(function(item, index) {
                        const newPath = path + '[' + index + ']';
                        traverse(item, newPath);
                    });
                }
            } else if (typeof current === 'object') {
                // È un oggetto - itera con Object.keys()
                const keys = Object.keys(current);
                if (keys.length === 0) {
                    // Oggetto vuoto - aggiungi indicatore
                    form.append(path + '[_empty]', 'true');
                } else {
                    keys.forEach(function(key) {
                        const newPath = path + '[' + key + ']';
                        traverse(current[key], newPath);
                    });
                }
            } else {
                // È una primitiva (stringa, numero, booleano) - foglia
                const normalizedValue = normalizeValue(current);
                form.append(path, normalizedValue);
            }
        }
        
        // Inizia il traversal dal nodo radice
        traverse(node, prefix);
    }

    /**
     * Estrae dinamicamente i campi principali dal JSON e li aggiunge al FormData
     * per accesso diretto lato PHP senza dover fare json_decode
     * 
     * @param {Object} bookingData - Oggetto JSON con tutti i dati del booking
     * @param {FormData} formData - FormData dove aggiungere i campi
     */
    function extractAndAppendFields(bookingData, formData) {
        console.log('[BTR] 📤 Estrazione campi singoli dal JSON per invio AJAX...');
        
        // 1. METADATA - Estrai campi metadata
        if (bookingData.metadata) {
            formData.append('metadata_timestamp', bookingData.metadata.timestamp || '');
            formData.append('metadata_user_agent', bookingData.metadata.user_agent || '');
            formData.append('metadata_url', bookingData.metadata.url || '');
        }
        
        // 2. PACKAGE - Estrai dati pacchetto (già presenti ma per completezza)
        if (bookingData.package) {
            // Questi sono già aggiunti singolarmente, ma li aggiungiamo con prefisso per coerenza
            formData.append('pkg_package_id', bookingData.package.package_id || '');
            formData.append('pkg_product_id', bookingData.package.product_id || '');
            formData.append('pkg_variant_id', bookingData.package.variant_id || '');
            formData.append('pkg_date_ranges_id', bookingData.package.date_ranges_id || '');
            formData.append('pkg_nome_pacchetto', bookingData.package.nome_pacchetto || '');
            formData.append('pkg_tipologia_prenotazione', bookingData.package.tipologia_prenotazione || '');
            formData.append('pkg_durata', bookingData.package.durata || 0);
            formData.append('pkg_selected_date', bookingData.package.selected_date || '');
        }
        
        // 3. CUSTOMER - Dati cliente
        if (bookingData.customer) {
            formData.append('customer_nome', bookingData.customer.nome || '');
            formData.append('customer_email', bookingData.customer.email || '');
        }
        
        // 4. PARTICIPANTS - Estrai breakdown partecipanti
        if (bookingData.participants) {
            formData.append('participants_adults', bookingData.participants.adults || 0);
            formData.append('participants_infants', bookingData.participants.infants || 0);
            formData.append('participants_total_people', bookingData.participants.total_people || 0);
            
            // Breakdown bambini per fascia
            if (bookingData.participants.children) {
                formData.append('participants_children_f1', bookingData.participants.children.f1 || 0);
                formData.append('participants_children_f2', bookingData.participants.children.f2 || 0);
                formData.append('participants_children_f3', bookingData.participants.children.f3 || 0);
                formData.append('participants_children_f4', bookingData.participants.children.f4 || 0);
                formData.append('participants_children_total', bookingData.participants.children.total_children || 0);
            }
        }
        
        // 5. ANAGRAFICI - Estrai dati di ogni partecipante con notazione array PHP
        if (bookingData.anagrafici && Array.isArray(bookingData.anagrafici)) {
            bookingData.anagrafici.forEach(function(partecipante, index) {
                // Dati base partecipante
                formData.append(`anagrafici[${index}][nome]`, partecipante.nome || '');
                formData.append(`anagrafici[${index}][cognome]`, partecipante.cognome || '');
                formData.append(`anagrafici[${index}][email]`, partecipante.email || '');
                formData.append(`anagrafici[${index}][telefono]`, partecipante.telefono || '');
                formData.append(`anagrafici[${index}][data_nascita]`, partecipante.data_nascita || '');
                formData.append(`anagrafici[${index}][citta_nascita]`, partecipante.citta_nascita || '');
                formData.append(`anagrafici[${index}][citta_residenza]`, partecipante.citta_residenza || '');
                formData.append(`anagrafici[${index}][provincia_residenza]`, partecipante.provincia_residenza || '');
                formData.append(`anagrafici[${index}][codice_fiscale]`, partecipante.codice_fiscale || '');
                formData.append(`anagrafici[${index}][indirizzo_residenza]`, partecipante.indirizzo_residenza || '');
                formData.append(`anagrafici[${index}][cap_residenza]`, partecipante.cap_residenza || '');
                
                // Costi extra selezionati per questo partecipante CON PREZZI
                if (partecipante.costi_extra && typeof partecipante.costi_extra === 'object') {
                    Object.keys(partecipante.costi_extra).forEach(function(extraKey) {
                        const costoExtra = partecipante.costi_extra[extraKey];
                        if (typeof costoExtra === 'object' && costoExtra !== null) {
                            // Nuovo formato con prezzi
                            formData.append(`anagrafici[${index}][costi_extra][${extraKey}][selected]`, 
                                          costoExtra.selected ? '1' : '0');
                            formData.append(`anagrafici[${index}][costi_extra][${extraKey}][price]`, 
                                          costoExtra.price || '0');
                        } else if (costoExtra === true) {
                            // Formato legacy (backward compatibility)
                            formData.append(`anagrafici[${index}][costi_extra][${extraKey}]`, '1');
                        }
                    });
                }
                
                // Assicurazioni selezionate
                if (partecipante.assicurazioni && typeof partecipante.assicurazioni === 'object') {
                    Object.keys(partecipante.assicurazioni).forEach(function(assKey) {
                        if (partecipante.assicurazioni[assKey] === true) {
                            formData.append(`anagrafici[${index}][assicurazioni][${assKey}]`, '1');
                        }
                    });
                }
            });
            
            // Aggiungi conteggio totale partecipanti
            formData.append('anagrafici_count', bookingData.anagrafici.length);
        }
        
        // 6. PRICING - Estrai totali e breakdown prezzi COMPLETI
        if (bookingData.pricing) {
            formData.append('pricing_total_price', bookingData.pricing.total_price || 0);
            formData.append('pricing_breakdown_available', bookingData.pricing.breakdown_available ? '1' : '0');
            
            // Nuovi campi aggiunti per completezza
            formData.append('pricing_totale_camere', bookingData.pricing.totale_camere || 0);
            formData.append('pricing_totale_costi_extra', bookingData.pricing.totale_costi_extra || 0);
            formData.append('pricing_totale_assicurazioni', bookingData.pricing.totale_assicurazioni || 0);
            formData.append('pricing_totale_generale_display', bookingData.pricing.totale_generale_display || 0);
            
            // Aggiungi totale_generale principale (coerente con display se disponibile)
            formData.append('pricing_totale_generale', bookingData.pricing.totale_generale || 
                                                        bookingData.pricing.totale_generale_display || 
                                                        bookingData.pricing.total_price || 0);
            
            // Breakdown dettagliato se disponibile
            if (bookingData.pricing.detailed_breakdown) {
                const breakdown = bookingData.pricing.detailed_breakdown;
                
                // Totali principali
                if (breakdown.totali) {
                    formData.append('pricing_subtotale_prezzi_base', breakdown.totali.subtotale_prezzi_base || 0);
                    formData.append('pricing_subtotale_supplementi_base', breakdown.totali.subtotale_supplementi_base || 0);
                    formData.append('pricing_subtotale_notti_extra', breakdown.totali.subtotale_notti_extra || 0);
                    formData.append('pricing_subtotale_supplementi_extra', breakdown.totali.subtotale_supplementi_extra || 0);
                    formData.append('pricing_breakdown_totale_generale', breakdown.totali.totale_generale || 0);
                }
                
                // Breakdown adulti
                if (breakdown.partecipanti && breakdown.partecipanti.adulti) {
                    const adulti = breakdown.partecipanti.adulti;
                    formData.append('pricing_adulti_quantita', adulti.quantita || 0);
                    formData.append('pricing_adulti_prezzo_unitario', adulti.prezzo_base_unitario || 0);
                    formData.append('pricing_adulti_totale', adulti.totale || 0);
                }
                
                // Breakdown bambini F1
                if (breakdown.partecipanti && breakdown.partecipanti.bambini_f1) {
                    const f1 = breakdown.partecipanti.bambini_f1;
                    formData.append('pricing_bambini_f1_quantita', f1.quantita || 0);
                    formData.append('pricing_bambini_f1_prezzo_unitario', f1.prezzo_base_unitario || 0);
                    formData.append('pricing_bambini_f1_totale', f1.totale || 0);
                }
                
                // Info notti extra
                if (breakdown.notti_extra) {
                    formData.append('pricing_notti_extra_attive', breakdown.notti_extra.attive ? '1' : '0');
                    formData.append('pricing_notti_extra_numero', breakdown.notti_extra.numero_notti || 0);
                    formData.append('pricing_notti_extra_prezzo_adulto', breakdown.notti_extra.prezzo_adulto_per_notte || 0);
                    
                    // CORREZIONE v1.0.131: Aggiungi anche supplemento notte extra adulti
                    if (breakdown.partecipanti.adulti && breakdown.partecipanti.adulti.quantita > 0) {
                        formData.append('pricing_notti_extra_supplemento_adulto', breakdown.partecipanti.adulti.supplemento_extra_unitario || 0);
                        console.log('[BTR] 📊 Campo adulti aggiunto: supplemento notte extra =', breakdown.partecipanti.adulti.supplemento_extra_unitario);
                    }
                    
                    // CORREZIONE v1.0.129: Aggiungi prezzi e supplementi notte extra per categorie bambini
                    if (breakdown.partecipanti.bambini_f1 && breakdown.partecipanti.bambini_f1.quantita > 0) {
                        formData.append('pricing_bambini_f1_notte_extra_prezzo', breakdown.partecipanti.bambini_f1.notte_extra_unitario || 0);
                        formData.append('pricing_bambini_f1_notte_extra_supplemento', breakdown.partecipanti.bambini_f1.supplemento_extra_unitario || 0);
                        console.log('[BTR] 📊 Campi F1: prezzo notte extra =', breakdown.partecipanti.bambini_f1.notte_extra_unitario, 'supplemento =', breakdown.partecipanti.bambini_f1.supplemento_extra_unitario);
                    }
                    if (breakdown.partecipanti.bambini_f2 && breakdown.partecipanti.bambini_f2.quantita > 0) {
                        formData.append('pricing_bambini_f2_notte_extra_prezzo', breakdown.partecipanti.bambini_f2.notte_extra_unitario || 0);
                        formData.append('pricing_bambini_f2_notte_extra_supplemento', breakdown.partecipanti.bambini_f2.supplemento_extra_unitario || 0);
                        console.log('[BTR] 📊 Campi F2: prezzo notte extra =', breakdown.partecipanti.bambini_f2.notte_extra_unitario, 'supplemento =', breakdown.partecipanti.bambini_f2.supplemento_extra_unitario);
                    }
                    if (breakdown.partecipanti.bambini_f3 && breakdown.partecipanti.bambini_f3.quantita > 0) {
                        formData.append('pricing_bambini_f3_notte_extra_prezzo', breakdown.partecipanti.bambini_f3.notte_extra_unitario || 0);
                        formData.append('pricing_bambini_f3_notte_extra_supplemento', breakdown.partecipanti.bambini_f3.supplemento_extra_unitario || 0);
                        console.log('[BTR] 📊 Campi F3: prezzo notte extra =', breakdown.partecipanti.bambini_f3.notte_extra_unitario, 'supplemento =', breakdown.partecipanti.bambini_f3.supplemento_extra_unitario);
                    }
                    if (breakdown.partecipanti.bambini_f4 && breakdown.partecipanti.bambini_f4.quantita > 0) {
                        formData.append('pricing_bambini_f4_notte_extra_prezzo', breakdown.partecipanti.bambini_f4.notte_extra_unitario || 0);
                        formData.append('pricing_bambini_f4_notte_extra_supplemento', breakdown.partecipanti.bambini_f4.supplemento_extra_unitario || 0);
                        console.log('[BTR] 📊 Campi F4: prezzo notte extra =', breakdown.partecipanti.bambini_f4.notte_extra_unitario, 'supplemento =', breakdown.partecipanti.bambini_f4.supplemento_extra_unitario);
                    }
                }
            }
        }
        
        // 7. EXTRA NIGHTS - Dettagli notti extra
        if (bookingData.extra_nights) {
            formData.append('extra_nights_enabled', bookingData.extra_nights.enabled ? '1' : '0');
            formData.append('extra_nights_price_per_person', bookingData.extra_nights.price_per_person || 0);
            formData.append('extra_nights_total_cost', bookingData.extra_nights.total_cost || 0);
            formData.append('extra_nights_date', bookingData.extra_nights.date || '');
        }
        
        // 8. DATES - Date check-in/out
        if (bookingData.dates) {
            formData.append('dates_check_in', bookingData.dates.check_in || '');
            formData.append('dates_check_out', bookingData.dates.check_out || '');
            formData.append('dates_extra_night', bookingData.dates.extra_night_date || '');
        }
        
        // 9. ROOMS - Array camere selezionate
        if (bookingData.rooms && Array.isArray(bookingData.rooms)) {
            bookingData.rooms.forEach(function(room, index) {
                formData.append(`rooms[${index}][type]`, room.tipo || room.type || '');
                formData.append(`rooms[${index}][quantity]`, room.quantita || room.quantity || 0);
                formData.append(`rooms[${index}][capacity]`, room.capacity || 0);
                formData.append(`rooms[${index}][price]`, room.prezzo_per_persona || room.price || 0);
                formData.append(`rooms[${index}][variation_id]`, room.variation_id || 0);
                formData.append(`rooms[${index}][supplemento]`, room.supplemento || 0);
            });
            formData.append('rooms_count', bookingData.rooms_count || bookingData.rooms.length);
        }
        
        // 10. EXTRA COSTS PRICES - Prezzi dei costi extra
        if (bookingData.extra_costs_prices) {
            Object.keys(bookingData.extra_costs_prices).forEach(function(slug) {
                formData.append(`extra_costs_prices[${slug}]`, bookingData.extra_costs_prices[slug] || 0);
            });
        }
        
        // 11. TOTALS BREAKDOWN - Riepilogo totali dettagliato
        if (bookingData.totals_breakdown) {
            formData.append('totals_rooms', bookingData.totals_breakdown.rooms_total || 0);
            formData.append('totals_extra_costs', bookingData.totals_breakdown.extra_costs_total || 0);
            formData.append('totals_insurances', bookingData.totals_breakdown.insurances_total || 0);
            formData.append('totals_grand_total', bookingData.totals_breakdown.grand_total || 0);
            formData.append('totals_display_total', bookingData.totals_breakdown.display_total || 0);
        }
        
        console.log('[BTR] ✅ Campi estratti e aggiunti al FormData - Totale campi: 100+');
    }

    // Step 4: Gestione del submit del form (Crea Preventivo)
    form.on('submit', function (e) {
        e.preventDefault();


        $('.btn-crea-preventivo-form').addClass('running');
        // Raccogli i dati del cliente
        const clienteNome = $('#btr_cliente_nome').val().trim();
        const clienteEmail = $('#btr_cliente_email').val().trim();

        if (clienteNome === '' || clienteEmail === '') {
            showAlert('Per favore, inserisci il nome e l\'email del cliente.','error');
            return;
        }

        // Raccogli i dati delle camere selezionate
        const rooms = [];
        $('.btr-room-quantity').each(function () {
            const quantity = parseInt($(this).val(), 10);
            if (quantity > 0) {
                const roomType = $(this).data('room-type');
                const capacity = parseInt($(this).data('capacity'), 10);
                const pricePerPerson = parseFloat($(this).data('price-per-person'));
                const variationId = parseInt($(this).data('variation-id'), 10); // Associa la variante
                const regularPrice = parseFloat($(this).data('regular-price')) || 0;
                const salePrice = parseFloat($(this).data('sale-price')) || 0;
                const supplemento = parseFloat($(this).data('supplemento')) || 0;

                // --- prezzi e quantità bambini assegnati (necessari per preventivo) ---
                const priceChildF1  = parseFloat($(this).data('price-child-f1')) || 0;
                const priceChildF2  = parseFloat($(this).data('price-child-f2')) || 0;
                const priceChildF3  = parseFloat($(this).data('price-child-f3')) || 0;
                const priceChildF4  = parseFloat($(this).data('price-child-f4')) || 0;

                // conteggio bambini effettivamente assegnati a questa camera
                const currentRoomIdx = $(this).closest('.btr-room-card').data('room-index');
                let assignedF1 = 0, assignedF2 = 0, assignedF3 = 0, assignedF4 = 0, assignedInfants = 0, assignedAdults = 0;
                Object.entries(childAssignments).forEach(([cid, idx]) => {
                    if (idx === currentRoomIdx) {
                        if      (cid.startsWith('f1-')) assignedF1++;
                        else if (cid.startsWith('f2-')) assignedF2++;
                        else if (cid.startsWith('f3-')) assignedF3++;
                        else if (cid.startsWith('f4-')) assignedF4++;
                        else if (cid.startsWith('infant-')) assignedInfants++;
                    }
                });
                
                // CORREZIONE v1.0.203: Distribuzione proporzionale degli adulti tra le camere
                const totalAdultsInForm = parseInt($('#btr_num_adults').val(), 10) || 0;
                
                if (totalAdultsInForm > 0 && quantity > 0) {
                    // Conta il totale delle camere selezionate per distribuire gli adulti
                    let totalRoomCapacity = 0;
                    let totalRoomCount = 0;
                    $('.btr-room-quantity').each(function() {
                        const q = parseInt($(this).val(), 10) || 0;
                        if (q > 0) {
                            const cap = parseInt($(this).data('capacity'), 10) || 2;
                            totalRoomCapacity += (cap * q);
                            totalRoomCount += q;
                        }
                    });
                    
                    // Distribuisci proporzionalmente: se ho 3 adulti e 3 camere totali, 1 per camera
                    // Se questa tipologia ha quantity=2, assegna 2 adulti
                    if (totalRoomCount > 0) {
                        const adultsPerSingleRoom = Math.ceil(totalAdultsInForm / totalRoomCount);
                        assignedAdults = Math.min(adultsPerSingleRoom * quantity, totalAdultsInForm);
                        
                        // Non superare la capacità disponibile
                        const totalChildrenInRoom = assignedF1 + assignedF2 + assignedF3 + assignedF4 + assignedInfants;
                        const availableCapacity = (capacity * quantity) - totalChildrenInRoom;
                        assignedAdults = Math.min(assignedAdults, availableCapacity);
                    } else {
                        assignedAdults = totalAdultsInForm;
                    }
                    
                    console.log('[BTR] 🔧 v1.0.203 - Proportional adult distribution:', {
                        totalAdultsInForm,
                        totalRoomCount,
                        thisRoomQuantity: quantity,
                        assignedAdults,
                        capacity
                    });
                } else {
                    assignedAdults = 0;
                }

                rooms.push({
                    variation_id: variationId, // Aggiungi l'ID variante
                    tipo: roomType,
                    quantita: quantity,
                    prezzo_per_persona: pricePerPerson,
                    sconto: $(this).data('sconto') ? parseFloat($(this).data('sconto')) : 0,
                    regular_price: regularPrice,
                    sale_price: salePrice,
                    supplemento: supplemento,
                    capacity: capacity,
                    price_child_f1: priceChildF1,
                    price_child_f2: priceChildF2,
                    price_child_f3: priceChildF3,
                    price_child_f4: priceChildF4,
                    assigned_child_f1: assignedF1,
                    assigned_child_f2: assignedF2,
                    assigned_child_f3: assignedF3,
                    assigned_child_f4: assignedF4,
                    assigned_infants: assignedInfants,
                    assigned_adults: assignedAdults // AGGIUNTO: adulti assegnati alla camera
                });
            }
        });

        if (rooms.length === 0) {
            showAlert('Seleziona almeno una camera.','error');
            return;
        }

        if (selectedCapacity !== requiredCapacity) {
            showAlert(`La capacità totale delle camere selezionate (${selectedCapacity}) non corrisponde al numero di persone (${requiredCapacity}).`,'warning');
            return;
        }

        console.log('Camere Selezionate:', rooms);
        console.log(`Capacità Totale Selezionata: ${selectedCapacity} / ${requiredCapacity}`);
        console.log(`Prezzo Totale: ${btrFormatPrice(totalPrice)}`);


        // Raccogli i dati delle assicurazioni selezionate per ogni partecipante
        // Raccogli i partecipanti dalla prima fase (generati dinamicamente)
        const anagrafici = [];
        console.log('🔍 DEBUG: Inizio raccolta dati partecipanti');
        
        // DEBUG: Verifica quali container esistono
        console.log('🔍 DEBUG: Container esistenti nel DOM:');
        console.log('  - #btr-assicurazioni-container:', $('#btr-assicurazioni-container').length);
        console.log('  - .btr-partecipante-form:', $('.btr-partecipante-form').length);
        console.log('  - .btr-person-card:', $('.btr-person-card').length);
        console.log('  - Tutti gli input costi_extra:', $('input[name*="costi_extra"]').length);
        console.log('  - Input costi_extra selezionati:', $('input[name*="costi_extra"]:checked').length);
        
        // Cerca nei form generati dinamicamente nella prima fase
        // Prova diversi selettori per trovare i partecipanti
        let participantSelector = '.btr-partecipante-form';
        if ($('.btr-partecipante-form').length === 0) {
            console.log('🔍 DEBUG: .btr-partecipante-form non trovato, provo con altri selettori');
            if ($('#btr-assicurazioni-container .btr-person-card').length > 0) {
                participantSelector = '#btr-assicurazioni-container .btr-person-card';
                console.log('🔍 DEBUG: Usando #btr-assicurazioni-container .btr-person-card');
            } else if ($('.btr-person-card').length > 0) {
                participantSelector = '.btr-person-card';
                console.log('🔍 DEBUG: Usando .btr-person-card');
            } else {
                console.log('❌ DEBUG: Nessun container partecipanti trovato!');
            }
        }
        
        $(participantSelector).each(function (index) {
            console.log(`👤 DEBUG: Processando partecipante ${index}`);
            const assicurazioni = {};
            $(this).find('input[type="checkbox"]:checked').each(function () {
                const name = $(this).attr('name');
                const match = name.match(/anagrafici\[\d+\]\[assicurazioni\]\[([^\]]+)\]/);
                if (match && match[1]) {
                    assicurazioni[match[1]] = true;
                }
            });

            const costi_extra = {};
            console.log(`💰 DEBUG: Cerco costi extra per partecipante ${index}`);
            $(this).find('input[type="checkbox"]:checked').each(function () {
                const name = $(this).attr('name');
                console.log(`🔍 DEBUG: Checkbox selezionato - name: ${name}`);
                const match = name.match(/anagrafici\[\d+\]\[costi_extra\]\[([^\]]+)\]/);
                if (match && match[1]) {
                    costi_extra[match[1]] = true;
                    console.log(`✅ DEBUG: Costo extra trovato: ${match[1]}`);
                }
            });
            console.log(`💰 DEBUG: Costi extra raccolti per partecipante ${index}:`, costi_extra);

            // Recupera i nuovi campi anagrafici
            const codice_fiscale = $(this).find('input[name$="[codice_fiscale]"]').val() || '';
            const indirizzo_residenza = $(this).find('input[name$="[indirizzo_residenza]"]').val() || '';
            const cap_residenza = $(this).find('input[name$="[cap_residenza]"]').val() || '';

            const persona = {
                nome: $(this).find('input[name$="[nome]"]').val() || '',
                cognome: $(this).find('input[name$="[cognome]"]').val() || '',
                data_nascita: $(this).find('input[name$="[data_nascita]"]').val() || '',
                citta_nascita: $(this).find('input[name$="[citta_nascita]"]').val() || '',
                citta_residenza: $(this).find('input[name$="[citta_residenza]"]').val() || '',
                provincia_residenza: $(this).find('input[name$="[provincia_residenza]"]').val() || '',
                email: $(this).find('input[name$="[email]"]').val() || '',
                telefono: $(this).find('input[name$="[telefono]"]').val() || '',
                codice_fiscale: codice_fiscale,
                indirizzo_residenza: indirizzo_residenza,
                cap_residenza: cap_residenza,
                camera: '',
                camera_tipo: '',
                assicurazioni: assicurazioni,
                assicurazioni_dettagliate: [],
                costi_extra: costi_extra,
                costi_extra_dettagliate: []
            };

            anagrafici.push(persona);
        });

        // Raccogli i costi extra applicati all'intera durata del soggiorno
        const costiExtraDurata = {};
        console.log('🏢 DEBUG: Cerco costi extra per durata nel form dinamico');
        // Cerca in tutto il documento per i costi extra durata
        $('input[name^="costi_extra_durata"]').filter(':checked').each(function () {
            console.log(`🔍 DEBUG: Costo durata selezionato - name: ${this.name}`);
            const match = this.name.match(/costi_extra_durata\[([^\]]+)\]/);
            if (match && match[1]) {
                costiExtraDurata[match[1]] = true;
                console.log(`✅ DEBUG: Costo durata trovato: ${match[1]}`);
            }
        });
        console.log('🏢 DEBUG: Costi extra durata raccolti:', costiExtraDurata);
        /* --------------------------------------------------------------
           Dati notte extra da inviare nel preventivo
        -------------------------------------------------------------- */
        const extraNightFlagSubmit = getExtraNightFlag() === 1;
        let   extraNightPPSubmit   = 0;

        $('.btr-room-quantity').each(function () {
            const qty = parseInt($(this).val(), 10) || 0;
            if (qty > 0) {
                extraNightPPSubmit = parseFloat($(this).data('extra-night-pp')) || 0;
                return false; // interrompi loop alla prima camera selezionata
            }
        });

        const totalPeopleSubmit = numAdults
            + (parseInt($('#btr_num_child_f1').val(), 10) || 0)
            + (parseInt($('#btr_num_child_f2').val(), 10) || 0);
        const extraNightTotalSubmit = extraNightFlagSubmit ? extraNightPPSubmit * totalPeopleSubmit : 0;

        // Genera breakdown dettagliato dei calcoli per il preventivo
        const riepilogoCalcoli = generateDetailedCalculationBreakdown();
        console.log('[BTR] 📊 Breakdown dettagliato calcoli:', riepilogoCalcoli);

        // Raccogli tutti i dati in formato JSON strutturato
        const allBookingData = collectAllBookingData();
        
        // Invia i dati al server per creare il preventivo
        // Costruzione di FormData per includere tutti i campi anagrafici (inclusi i nuovi)
        const formData = new FormData();
        formData.append('action', 'btr_create_preventivo');
        formData.append('nonce', btr_booking_form.nonce);
        
        // v1.0.183: Aggiungi etichette dinamiche bambini al payload
        // Prima sincronizza le etichette dal DOM
        window.syncChildLabelsFromDOM();
        const childLabels = window.btrDynamicLabelsFromDOM || {};
        
        // Se non abbiamo etichette dal DOM, prova a prenderle dai data attributes o dal testo
        if (!childLabels.f1) {
            // Prima prova con data-label
            let label = $('.btr-child-group[data-fascia="f1"]').first().attr('data-label');
            // Se non trovato, prova con il testo del titolo
            if (!label) {
                label = $('.btr-f1-title strong').first().text().trim();
            }
            // Se ancora non trovato, cerca nel select
            if (!label) {
                label = $('#btr_num_child_f1 option:selected').text().replace(/[0-9]/g, '').trim();
            }
            childLabels.f1 = label || '';
            console.log('[BTR] F1 label ricercata nel DOM:', label);
        }
        if (!childLabels.f2) {
            let label = $('.btr-child-group[data-fascia="f2"]').first().attr('data-label');
            if (!label) {
                label = $('.btr-f2-title strong').first().text().trim();
            }
            if (!label) {
                label = $('#btr_num_child_f2 option:selected').text().replace(/[0-9]/g, '').trim();
            }
            childLabels.f2 = label || '';
            console.log('[BTR] F2 label ricercata nel DOM:', label);
        }
        if (!childLabels.f3) {
            let label = $('.btr-child-group[data-fascia="f3"]').first().attr('data-label');
            if (!label) {
                label = $('.btr-f3-title strong').first().text().trim();
            }
            if (!label) {
                label = $('#btr_num_child_f3 option:selected').text().replace(/[0-9]/g, '').trim();
            }
            childLabels.f3 = label || '';
            console.log('[BTR] F3 label ricercata nel DOM:', label);
        }
        if (!childLabels.f4) {
            let label = $('.btr-child-group[data-fascia="f4"]').first().attr('data-label');
            if (!label) {
                label = $('.btr-f4-title strong').first().text().trim();
            }
            if (!label) {
                label = $('#btr_num_child_f4 option:selected').text().replace(/[0-9]/g, '').trim();
            }
            childLabels.f4 = label || '';
            console.log('[BTR] F4 label ricercata nel DOM:', label);
        }
        
        // Log dettagliato per debug
        console.log('[BTR v1.0.183] Debug etichette:');
        console.log('- window.btrDynamicChildLabels:', window.btrDynamicChildLabels);
        console.log('- window.btrDynamicLabelsFromDOM:', window.btrDynamicLabelsFromDOM);
        console.log('- childLabels finale:', childLabels);
        
        formData.append('child_labels_f1', childLabels.f1 || '');
        formData.append('child_labels_f2', childLabels.f2 || '');
        formData.append('child_labels_f3', childLabels.f3 || '');
        formData.append('child_labels_f4', childLabels.f4 || '');
        
        console.log('[BTR v1.0.183] Etichette bambini inviate nel FormData:', {
            f1: childLabels.f1,
            f2: childLabels.f2,
            f3: childLabels.f3,
            f4: childLabels.f4
        });
        
        // NUOVO v2.2: Estrai e aggiungi TUTTI i campi singolarmente per accesso diretto PHP
        // Questo permette di accedere ai dati con $_POST['campo'] senza json_decode
        extractAndAppendFields(allBookingData, formData);
        
        // NUOVO: Payload JSON completo per v2.0 (mantenuto per backward compatibility)
        formData.append('booking_data_json', JSON.stringify(allBookingData));
        
        // NUOVO v2.1: Split dinamico del payload se feature flag attivo
        if (window.btrBooking && window.btrBooking.flags && window.btrBooking.flags.sendSplit === true) {
            try {
                // Log solo se debug attivo
                if (window.btrBooking.debug === true) {
                    console.group('[BTR] 🔄 Split Payload Attivo');
                    console.log('Feature flag sendSplit: ON');
                    console.log('Inizio flattening ricorsivo...');
                }
                
                // Applica flattening ricorsivo senza hardcode
                addFlattened(allBookingData, formData, 'btr_flat');
                
                // Aggiungi anche il JSON raw per backup se necessario
                if (window.btrBooking.flags.includeFlatRawJson === true) {
                    formData.append('btr_flat_raw_json', JSON.stringify(allBookingData));
                }
                
                if (window.btrBooking.debug === true) {
                    // Verifica campi aggiunti (solo conteggio per privacy)
                    let flatFieldCount = 0;
                    for (let pair of formData.entries()) {
                        if (pair[0].startsWith('btr_flat')) {
                            flatFieldCount++;
                        }
                    }
                    console.log('Campi btr_flat aggiunti:', flatFieldCount);
                    console.groupEnd();
                }
            } catch (error) {
                // Gestione errori graceful - continua senza split
                console.error('[BTR] ⚠️ Errore durante split payload:', error);
                // Non bloccare l'invio, procedi con payload normale
            }
        } else {
            // Feature flag disattivato o non presente
            if (window.btrBooking && window.btrBooking.debug === true) {
                console.log('[BTR] Split Payload: OFF (flag non attivo)');
            }
        }
        
        formData.append('cliente_nome', clienteNome);
        formData.append('cliente_email', clienteEmail);
        formData.append('package_id', $('input[name="btr_package_id"]').val());
        formData.append('product_id', $('input[name="btr_product_id"]').val());
        formData.append('variant_id', $('input[name="selected_variant_id"]').val());
        formData.append('date_ranges_id', $('input[name="btr_date_ranges_id"]').val());
        formData.append('tipologia_prenotazione', $('input[name="btr_tipologia_prenotazione"]').val());
        formData.append('durata', $('input[name="btr_durata"]').val());
        formData.append('nome_pacchetto', $('input[name="btr_nome_pacchetto"]').val());
        formData.append('prezzo_totale', totalPrice.toFixed(2));
        formData.append('camere', JSON.stringify(rooms));
        formData.append('costi_extra_durata', JSON.stringify(costiExtraDurata));
        formData.append('num_adults', parseInt($('#btr_num_adults').val(), 10) || 0);
        formData.append('num_children', numChildF1Field.length ? (parseInt(numChildF1Field.val(),10)||0) + (parseInt(numChildF2Field.val(),10)||0) : numChildren);
        // Debug: verifica valore neonati
        const infantsValue = parseInt($('#btr_num_infants').val(), 10) || 0;
        console.log('[BTR] 🍼 DEBUG num_infants:', {
            fieldExists: $('#btr_num_infants').length > 0,
            fieldValue: $('#btr_num_infants').val(),
            parsedValue: infantsValue
        });
        formData.append('num_infants', infantsValue);
        formData.append('extra_night', getExtraNightFlag());
        formData.append('selected_date', $('#btr_selected_date').val());
        formData.append('extra_night_pp', extraNightPPSubmit);
        formData.append('extra_night_total', extraNightTotalSubmit);
        
        // Aggiungi il breakdown dettagliato dei calcoli
        formData.append('riepilogo_calcoli_dettagliato', JSON.stringify(riepilogoCalcoli));

        // Add extra night date - use the selected date or derive it from other data
        const selectedDate = $('#btr_selected_date').val();
        let extraNightDate = '';

        if (getExtraNightFlag() === 1 && selectedDate) {
            // CORREZIONE v1.0.127: La notte extra è DOPO la fine del pacchetto base
            // Format: "21 - 23 Gennaio 2026" -> notte extra è il 23 Gennaio
            const dateMatch = selectedDate.match(/(\d+)\s*-\s*(\d+)\s+([^\s]+)\s+(\d+)/);
            if (dateMatch) {
                const endDay = parseInt(dateMatch[2]); // Giorno di fine del pacchetto base
                const month = dateMatch[3];
                const year = dateMatch[4];
                
                // CORREZIONE: La notte extra è nella stessa data di fine, non il giorno successivo
                // Perché la "notte extra" si riferisce alla notte dell'ultimo giorno
                extraNightDate = `${endDay} ${month} ${year}`;
                
                console.log('[BTR] 🔧 CORREZIONE data notte extra:', extraNightDate, 'dal range:', selectedDate);
            }
        }

        formData.append('btr_extra_night_date', extraNightDate);

        // Se non ci sono partecipanti nei container specifici, raccogli da tutti gli input del form
        if (anagrafici.length === 0) {
            console.log('🔍 DEBUG: Nessun partecipante trovato nei container, raccogliendo dai primi 4 campi obbligatori');
            // Raccoglie almeno il primo partecipante dai campi obbligatori della prima fase
            const primoPartecipante = {
                nome: $('input[name*="[nome]"]').first().val() || '',
                cognome: $('input[name*="[cognome]"]').first().val() || '',
                email: $('input[name*="[email]"]').first().val() || '',
                telefono: $('input[name*="[telefono]"]').first().val() || '',
                data_nascita: '',
                citta_nascita: '',
                citta_residenza: '',
                provincia_residenza: '',
                codice_fiscale: '',
                indirizzo_residenza: '',
                cap_residenza: '',
                assicurazioni: {},
                costi_extra: {}
            };
            
            // Raccogli i costi extra selezionati per il primo partecipante
            console.log('🔍 DEBUG: Cerco costi extra selezionati in tutto il DOM');
            $('input[name*="[costi_extra]["]:checked, input[name^="anagrafici["][name*="[costi_extra]["]:checked').each(function() {
                const name = $(this).attr('name');
                console.log(`🔍 DEBUG: Trovato input costi_extra: ${name}`);
                
                // Pattern per anagrafici[X][costi_extra][slug] o [costi_extra][slug]
                const match = name.match(/(?:anagrafici\[\d+\])??\[costi_extra\]\[([^\]]+)\]/);
                if (match && match[1]) {
                    primoPartecipante.costi_extra[match[1]] = true;
                    console.log(`✅ DEBUG: Costo extra primo partecipante: ${match[1]}`);
                }
            });
            
            anagrafici.push(primoPartecipante);
            console.log('👤 DEBUG: Primo partecipante creato manualmente:', primoPartecipante);
        }

        // ====================================================================
        // CORREZIONE: Raccolta completa costi extra da TUTTI i checkbox DOM
        // ====================================================================
        console.log('🔍 DEBUG: Raccolta completa costi extra da DOM...');
        
        // Reinizializza i costi extra per tutti i partecipanti
        anagrafici.forEach(function(partecipante, i) {
            partecipante.costi_extra = {};
        });
        
        // Raccogli tutti i checkbox dei costi extra selezionati
        // Usa un selettore più specifico per evitare di catturare altri checkbox
        $('input[type="checkbox"][name*="[costi_extra]"]:checked, input[type="checkbox"][name^="costi_extra_durata"]:checked').each(function() {
            const name = $(this).attr('name');
            const value = $(this).val();
            
            // Verifica se è un costo extra per partecipante
            const matchPartecipante = name.match(/anagrafici\[(\d+)\]\[costi_extra\]\[([^\]]+)\]/);
            if (matchPartecipante) {
                const participantIndex = parseInt(matchPartecipante[1], 10);
                const costoSlug = matchPartecipante[2];
                console.log(`✅ DEBUG: Checkbox costo extra selezionato: ${name} = ${value}`);
                
                // Verifica che l'indice sia valido
                if (participantIndex >= 0 && participantIndex < anagrafici.length) {
                    // Inizializza costi_extra se non esiste
                    if (!anagrafici[participantIndex].costi_extra) {
                        anagrafici[participantIndex].costi_extra = {};
                    }
                    anagrafici[participantIndex].costi_extra[costoSlug] = true;
                    console.log(`✅ DEBUG: Assegnato costo extra: partecipante[${participantIndex}].costi_extra[${costoSlug}] = true`);
                } else {
                    console.warn(`⚠️ DEBUG: Indice partecipante non valido: ${participantIndex}`);
                }
            }
            
            // Verifica se è un costo extra per durata
            const matchDurata = name.match(/costi_extra_durata\[([^\]]+)\]/);
            if (matchDurata) {
                costiExtraDurata[matchDurata[1]] = true;
                console.log(`✅ DEBUG: Costo extra durata: ${matchDurata[1]} = true`);
            }
        });

        // DEBUG: Verifica finale dati raccolti
        console.log('📋 DEBUG: Dati anagrafici finali:', anagrafici);
        console.log('📋 DEBUG: Costi extra durata finali:', costiExtraDurata);
        
        
        // OTTIMIZZAZIONE v1.0.126: I dati anagrafici sono già stati aggiunti tramite 
        // il sistema v2.2 (collectAllBookingData + extractAndAppendFields)
        // Rimuoviamo la duplicazione per ottimizzare il payload AJAX
        console.log('📤 OTTIMIZZAZIONE: Dati anagrafici già inviati tramite sistema v2.2 - duplicazione rimossa');

        $.ajax({
            url: btr_booking_form.ajax_url,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    console.log('Preventivo creato con successo.');
                    $('.btn-crea-preventivo-form').removeClass('running');
                    bookingResponse.html('<p class="btr-success">Preventivo creato con successo! Verrai reindirizzato al riepilogo del preventivo.</p>');
                    // Reindirizza alla pagina di riepilogo del preventivo dopo un breve ritardo
                    setTimeout(function () {
                        window.location.href = response.data.redirect_url;
                    }, 2000); // 2 secondi
                } else {
                    console.log('Errore Preventivo:', response.data.message);
                    bookingResponse.html(`<p class="btr-error">${response.data.message}</p>`);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error:', textStatus, errorThrown);
                $('.btn-crea-preventivo-form').removeClass('running');
                showAlert('Errore durante la creazione del preventivo.','error');
            }
        });
    });


    /**
     * Funzione per sanitizzare il tipo di camera in modo da creare ID validi
     */
    // Delegazione evento per pulsante assicurazioni
    $(document).on('click', '#btr-generate-insurance-template', function (e) {
        e.preventDefault();

        const preventivoId = $('input[name="preventivo_id"]').val() || 0;
        const packageId = $('input[name="btr_package_id"]').val();

        const numAdults = parseInt($('#btr_num_adults').val(), 10) || 0;
        const numChild_f1 = parseInt($('#btr_num_child_f1').val(), 10) || 0;
        const numChild_f2 = parseInt($('#btr_num_child_f2').val(), 10) || 0;
        const numChild_f3 = parseInt($('#btr_num_child_f3').val(), 10) || 0;
        const numChild_f4 = parseInt($('#btr_num_child_f4').val(), 10) || 0;
        const numInfants = parseInt($('#btr_num_infants').val(), 10) || 0;

        const numChildren = numChild_f1 + numChild_f2 + numChild_f3 + numChild_f4;
        const numPeople = numAdults + numChildren + numInfants; // I neonati ora occupano posti letto

        if (numPeople < 1) {
            showAlert('Inserisci almeno un partecipante.','error');
            return;
        }

        $.post(btr_booking_form.ajax_url, {
            action: 'btr_get_template_assicurazioni',
            nonce: btr_booking_form.nonce,
            preventivo_id: preventivoId,
            num_people: numPeople,
            package_id: packageId
        }).done(function (response) {
            if (response.success) {
                $('#btr-assicurazioni-response').html(response.data.html);
                $('html, body').animate({
                    scrollTop: $('#btr-assicurazioni-response').offset().top
                }, 600);
            } else {
                showAlert(response.data.message || 'Errore nel caricamento del template.','error');
            }
        }).fail(function () {
            showAlert('Errore durante la richiesta al server.','error');
        });
    });

    // Delegazione evento per generare la lista dei partecipanti con assicurazioni
    $(document).on('click', '#btr-generate-participants', function (e) {
        e.preventDefault();
        //console.log('CLICK TRIGGERED: #btr-generate-participants');

        const numAdults = parseInt($('#btr_num_adults').val(), 10) || 0;
        const numChild_f1 = parseInt($('#btr_num_child_f1').val(), 10) || 0;
        const numChild_f2 = parseInt($('#btr_num_child_f2').val(), 10) || 0;
        const numChild_f3 = parseInt($('#btr_num_child_f3').val(), 10) || 0;
        const numChild_f4 = parseInt($('#btr_num_child_f4').val(), 10) || 0;
        const numInfants = $('#btr_num_infants').length ? parseInt($('#btr_num_infants').val(), 10) || 0 : 0;

        /* -------------------------------------------------
           VALIDAZIONE ASSEGNAZIONE BAMBINI → CAMERE
           Conta solo i bambini effettivamente collocati in
           camere con quantità > 0; se la condizione non è
           soddisfatta blocca lo step successivo.
        -------------------------------------------------- */
        const totalChildrenSelected = numChild_f1 + numChild_f2 + numChild_f3 + numChild_f4;

        // Conta quanti bambini risultano assegnati a una camera
        const assignedChildren = Object.keys(childAssignments || {}).length;

        // se non assegnati tutti i bambini => avviso e stop
        if (totalChildrenSelected > 0 && assignedChildren < totalChildrenSelected) {
            const remaining = totalChildrenSelected - assignedChildren;
            const message = `Devi ancora assegnare ${remaining} bambino${remaining > 1 ? 'i' : ''} alle camere.`;

            // Evidenzia visivamente i checkbox per l'assegnazione bambini
            $('.btr-room-card').each(function() {
                const $card = $(this);
                const quantity = parseInt($card.find('.btr-room-quantity').val(), 10) || 0;
                
                if (quantity > 0) {
                    const $toggleWrapper = $card.find('.btr-child-toggle-wrapper');
                    const $checkbox = $toggleWrapper.find('.btr-child-toggle');
                    
                    // Mostra il checkbox se non è visibile
                    if ($toggleWrapper.is(':hidden')) {
                        $toggleWrapper.slideDown(200);
                    }
                    
                    // Se il checkbox non è selezionato, evidenzialo
                    if (!$checkbox.is(':checked')) {
                        $toggleWrapper.addClass('btr-error-highlight btr-shake-animation');
                        // Rimuovi la classe di animazione dopo che è completata
                        setTimeout(function() {
                            $toggleWrapper.removeClass('btr-shake-animation');
                        }, 400);
                    }
                }
            });
            
            // Scroll alla prima camera con checkbox non selezionato
            const $firstUnselected = $('.btr-child-toggle:not(:checked)').first();
            if ($firstUnselected.length) {
                $('html, body').animate({
                    scrollTop: $firstUnselected.closest('.btr-room-card').offset().top - 100
                }, 500);
            }

            showNotification(message,'error');
            return; // blocca la continuazione
        }
        
        // Rimuovi evidenziazione errore se tutto ok
        $('.btr-child-toggle-wrapper').removeClass('btr-error-highlight');

        /* -------------------------------------------------
           VALIDAZIONE ADULTI OBBLIGATORI PER CAMERE CON BAMBINI
           Ogni camera che ospita bambini deve avere almeno un adulto
        -------------------------------------------------- */
        const roomValidationErrors = [];
        
        // Raggruppa bambini per camera
        const childrenByRoom = {};
        Object.entries(childAssignments || {}).forEach(([childId, roomIndex]) => {
            if (!childrenByRoom[roomIndex]) {
                childrenByRoom[roomIndex] = [];
            }
            childrenByRoom[roomIndex].push(childId);
        });

        // Verifica ogni camera con bambini
        Object.entries(childrenByRoom).forEach(([roomIndex, assignedChildren]) => {
            const roomIndexInt = parseInt(roomIndex, 10);
            const $roomCard = $(`.btr-room-card[data-room-index="${roomIndexInt}"]`);
            const roomType = $roomCard.find('.btr-room-quantity').data('room-type') || 'Camera';
            const quantity = parseInt($roomCard.find('.btr-room-quantity').val(), 10) || 0;
            const capacity = parseInt($roomCard.find('.btr-room-quantity').data('capacity'), 10) || 1;
            
            // Salta camere non selezionate
            if (quantity === 0) return;
            
            const totalSlots = quantity * capacity;
            const childrenCount = assignedChildren.length;
            
            // Calcola quanti adulti sono necessari per questa camera
            const requiredAdults = Math.min(quantity, 1); // Almeno 1 adulto per camera, ma non più del numero di camere
            const availableAdultsSlots = totalSlots - childrenCount;
            
            // Verifica 1: Almeno un adulto per camera con bambini
            if (childrenCount > 0 && availableAdultsSlots < requiredAdults) {
                roomValidationErrors.push({
                    type: 'no_adult',
                    roomType: roomType,
                    childrenCount: childrenCount,
                    availableSlots: availableAdultsSlots
                });
            }
            
            // Verifica 2: Capacità non superata
            if (childrenCount > totalSlots) {
                roomValidationErrors.push({
                    type: 'capacity_exceeded',
                    roomType: roomType,
                    childrenCount: childrenCount,
                    totalSlots: totalSlots
                });
            }
        });

        // Se ci sono errori di validazione, mostra messaggio e blocca
        if (roomValidationErrors.length > 0) {
            let errorMessage = 'Errore nella configurazione delle camere:\n\n';
            
            roomValidationErrors.forEach(error => {
                if (error.type === 'no_adult') {
                    errorMessage += `• ${error.roomType}: Ogni camera deve contenere almeno un adulto se ci sono bambini assegnati (${error.childrenCount} bambini, ${error.availableSlots} posti disponibili per adulti).\n`;
                } else if (error.type === 'capacity_exceeded') {
                    errorMessage += `• ${error.roomType}: Capacità superata (${error.childrenCount} bambini assegnati, ${error.totalSlots} posti totali).\n`;
                }
            });
            
            errorMessage += '\nVerifica la capacità e l\'assegnazione corrente.';
            
            showAlert(errorMessage, 'error', 'Errore Assegnazione Camere');
            return; // blocca la continuazione
        }

        const numPeople = numAdults + numChild_f1 + numChild_f2 + numChild_f3 + numChild_f4 + numInfants; // Include neonati
        const hasChildren = numChild_f1 > 0 || numChild_f2 > 0 || numChild_f3 > 0 || numChild_f4 > 0;
        const hasInfants = numInfants > 0;

        if (numPeople < 1) {
            showAlert('Inserisci almeno un partecipante.','error');
            return;
        }

        /* --------------------------------------------------------
           NON RICALCOLARE IL PREZZO - USA QUELLO GIÀ CALCOLATO
           Il prezzo è già stato calcolato correttamente in updateSelectedRooms()
           quando l'utente ha selezionato le camere. Non serve ricalcolarlo.
           -------------------------------------------------------- */
        
        // Debug: mostra il prezzo già calcolato
        console.log('[BTR] 💰 USANDO PREZZO GIÀ CALCOLATO:', {
            totalPrice: totalPrice.toFixed(2),
            formattedPrice: btrFormatPrice(totalPrice),
            note: 'Il prezzo NON viene ricalcolato per mantenere la consistenza'
        });

        // NON aggiorniamo totalPrice né il display - usiamo quello esistente
        // totalPrice rimane invariato dal calcolo in updateSelectedRooms()
        
        const preventivoId = $('input[name="preventivo_id"]').val() || 0;
        const packageId = $('input[name="btr_package_id"]').val();

        $('#btr-participants-wrapper').html('<div class="loader"><span class="element"></span><span class="element "></span><span class="element"></span></div>');


        $.post(btr_booking_form.ajax_url, {
            action: 'btr_get_assicurazioni_config',
            nonce: btr_booking_form.nonce,
            preventivo_id: preventivoId,
            package_id: packageId
        }).done(function (response) {
            if (response.success && response.data && response.data.assicurazioni) {
                // Suddividi i costi extra in base a come devono essere applicati
                const extrasPerPersona = (response.data.costi_extra || [])
                    .filter(ex =>
                        (ex.attivo === '1' || ex.attivo === 1 || ex.attivo === true) &&
                        (ex.moltiplica_persone === '1' || ex.moltiplica_persone === 1 || ex.moltiplica_persone === true)
                    );

                const extrasPerDurata = (response.data.costi_extra || [])
                    .filter(ex =>
                        (ex.attivo === '1' || ex.attivo === 1 || ex.attivo === true) &&
                        (ex.moltiplica_durata === '1' || ex.moltiplica_durata === 1 || ex.moltiplica_durata === true)
                    );

                //console.log('Costi extra per persona:', extrasPerPersona);
                //console.log('Costi extra per durata:', extrasPerDurata);
                const assicurazioniDisponibili = response.data.assicurazioni;
                let html = '<div id="btr-assicurazioni-container">';
                const ordinali = ['Primo', 'Secondo', 'Terzo', 'Quarto', 'Quinto', 'Sesto'];

                $(this).slideUp();
                //console.log('Assicurazioni disponibili:', assicurazioniDisponibili);
                if (hasInfants) {
                    html += `<p class="info-neonati"><strong>${btr_booking_form.labels.infant_plural || 'Neonati'}:</strong> ${numInfants} (non pagano ma occupano posti letto)</p>`;
                }

                for (let i = 0; i < numPeople; i++) {
                    const currentIndex = i;
                    const isFirst = i === 0;
                    let posizione = '<strong>'+ordinali[i] + '</strong> ' + (btr_booking_form.labels.participant || 'partecipante');
                    // --- Etichetta dinamica della fascia d'età --------------------------
                    // Recupera le etichette corrette dal backend tramite window.btrChildFasce
                    // con fallback ai valori corretti (non quelli hardcoded errati)
                    const getLabel = (fasciaId, fallback) => {
                        if (window.btrChildFasce && Array.isArray(window.btrChildFasce)) {
                            const fascia = window.btrChildFasce.find(f => f.id === fasciaId);
                            return fascia ? fascia.label : fallback;
                        }
                        return $('#btr_num_child_f' + fasciaId).data('label') || fallback;
                    };
                    
                    // v1.0.181 - Usa SOLO etichette dinamiche dal backend (NO hardcoded)
                    const dynamicLabels = window.btrDynamicChildLabels || {};
                    const labelChildF1 = getLabel(1, dynamicLabels.f1 || '');
                    const labelChildF2 = getLabel(2, dynamicLabels.f2 || '');
                    const labelChildF3 = getLabel(3, dynamicLabels.f3 || '');
                    const labelChildF4 = getLabel(4, dynamicLabels.f4 || '');

                    // Crea array dinamico delle tipologie di partecipanti (stesso approccio di loadRoomTypes)
                    const categories = [
                        { count: numAdults,     label: btr_booking_form.labels.adult_singular || 'Adulto' },
                        { count: numChild_f1,   label: labelChildF1 },
                        { count: numChild_f2,   label: labelChildF2 },
                        { count: numChild_f3,   label: labelChildF3 },
                        { count: numChild_f4,   label: labelChildF4 },
                        { count: numInfants,    label: btr_booking_form.labels.infant_singular || 'Neonato' }
                    ];

                    // Determina dinamicamente la tipologia del partecipante corrente
                    let accumulated = 0;
                    let participantType = btr_booking_form.labels.adult_singular || 'Adulto'; // fallback
                    let isInfant = false;
                    
                    for (const cat of categories) {
                        accumulated += cat.count;
                        if (i < accumulated) {
                            participantType = cat.label;
                            // Determina se è un neonato
                            isInfant = (cat.label === (btr_booking_form.labels.infant_singular || 'Neonato'));
                            break;
                        }
                    }

                    if (hasChildren) {
                        posizione += ` <small>(${participantType})</small>`;
                    }
                    // NOTA: Mostra la card del partecipante con nota dinamica
                    html += `
                      <div class="btr-person-card" data-person-index="${i}">
                        <h3 class="person-title">
                          <span class="icona-partecipante">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5.52 19c.64-2.2 1.84-3 3.22-3h6.52c1.38 0 2.58.8 3.22 3"/><circle cx="12" cy="10" r="3"/><circle cx="12" cy="12" r="10"/></svg>
                            </span>
                            <span>${posizione}</span>
                        </h3>
                        ${
                          isFirst
                          ? `<div class="btr-nota-partecipante">
                                    <div class="btr-note">
                                        <div class="btr-note-icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                                        </div>
                                        <div class="btr-note-content">
                                            <h5>Informazioni importanti</h5>
                                            <p>
                                              Per il primo partecipante è necessario compilare tutti i dati completi per poter generare correttamente il preventivo                        </p>
                                        </div>
                                    </div>

                             </div>`
                          : isInfant
                          ? `<div class="btr-nota-partecipante">
                                        <div class="btr-note">
                                        <div class="btr-note-icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                                        </div>
                                        <div class="btr-note-content">
                                            <h5>Neonato - Solo nome e cognome richiesti</h5>
                                            <p>
                                             Per i neonati sono necessari solo nome e cognome. Non sono previsti costi extra.                     </p>
                                        </div>
                                    </div>
                             </div>`
                          : `<div class="btr-nota-partecipante">
                                        <div class="btr-note">
                                        <div class="btr-note-icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                                        </div>
                                        <div class="btr-note-content">
                                            <h5>Puoi lasciare vuoti i dati di questo partecipante</h5>
                                            <p>
                                             Non sono obbligatori in questa fase, ma puoi indicare il nome e selezionare eventuali costi extra o opzioni aggiuntive.                     </p>
                                        </div>
                                    </div>
                             </div>`
                        }
                        <div class="btr-grid">
                    `;
                    let fullFieldsHtml = '';
                    let lightFieldsHtml = '';

                    // Campi minimi per tutti
                    lightFieldsHtml = `
        <div class="btr-field-group w-50">
            <label for="btr_nome_${i}">Nome</label>
            <input type="text" id="btr_nome_${i}" name="anagrafici[${i}][nome]" ${isFirst ? 'required' : ''}/>
        </div>
        <div class="btr-field-group w-50">
            <label for="btr_cognome_${i}">Cognome</label>
            <input type="text" id="btr_cognome_${i}" name="anagrafici[${i}][cognome]" ${isFirst ? 'required' : ''}/>
        </div>
    `;

                    // Campi aggiuntivi solo per il primo - SOLO email e telefono (obbligatori)
                if (isFirst) {
                    fullFieldsHtml = `
    <div class="btr-field-group w-60">
        <label for="btr_email_${i}">Email personale</label>
        <input type="email" id="btr_email_${i}" name="anagrafici[${i}][email]" required />
    </div>
    <div class="btr-field-group w-40">
        <label for="btr_telefono_${i}">Telefono</label>
        <input type="tel" id="btr_telefono_${i}" name="anagrafici[${i}][telefono]" required />
    </div>
    `;
                }
                    html += `
                            ${lightFieldsHtml}
                            ${fullFieldsHtml}
                        </div>`;
                    // Le assicurazioni non vengono mostrate in questa fase




                    // --- Costi extra PER PERSONA -------------------------------
                    // I neonati non hanno costi extra
                    if (extrasPerPersona.length && !isInfant) {
                        // Determina se il partecipante corrente è un adulto
                        const isAdult = i < numAdults;
                        
                        // Filtra la culla per neonati se non ci sono neonati o se il partecipante non è un adulto
                        const filteredExtrasPerPersona = extrasPerPersona.filter(extra => {
                            if (extra.slug === 'culla-per-neonati') {
                                // La culla per neonati appare solo se:
                                // 1. Ci sono neonati selezionati (numInfants > 0)
                                // 2. Il partecipante corrente è un adulto
                                return numInfants > 0 && isAdult;
                            }
                            return true;
                        });
                        if (filteredExtrasPerPersona.length) {
                        html += `
                            <fieldset class="btr-assicurazioni">
                                <h4>Costi Extra</h4>
                                <p>Puoi selezionare eventuali costi extra da applicare al partecipante.</p>
                        `;
                        // Itera sui costi extra per persona
                            filteredExtrasPerPersona.forEach(extra => {
                            // CORREZIONE: Usa lo slug dal database se disponibile, altrimenti genera con slugify
                            const slug = extra.slug || slugify(extra.nome || 'extra');
                            const label = extra.nome || 'Extra';
                            const importo = parseFloat(extra.importo || 0).toFixed(2);
                            const sconto  = parseFloat(extra.sconto  || 0);
                            const scontoTxt = sconto > 0 ? ` <strong>- ${sconto}%</strong>` : '';
                            
                            // Attributi speciali per la culla per neonati
                            let extraAttributes = '';
                            let extraClasses = '';
                            if (extra.slug === 'culla-per-neonati') {
                                extraAttributes = `data-crib-checkbox="true" data-participant-index="${i}"`;
                                extraClasses = 'btr-crib-checkbox';
                            }
                            
                            html += `
                                <div class="btr-assicurazione-item">
                                    <label>
                                        <input type="checkbox" 
                                               name="anagrafici[${i}][costi_extra][${slug}]" 
                                               value="1" 
                                               class="${extraClasses}"
                                               data-importo="${extra.importo || 0}"
                                               data-cost-slug="${slug}"
                                               ${extraAttributes} />
                                        ${label}
                                        ${extra.tooltip_text ? `
                                        <span class="btr-info-wrapper">
                                            <button type="button"
                                                    class="btr-info-icon"
                                                    aria-describedby="cost-tooltip-${slug}"
                                                    aria-label="Informazioni costo extra"
                                                    title="">
                                                <svg class="btr-icon btr-icon-info-outline"
                                                     xmlns="http://www.w3.org/2000/svg"
                                                     viewBox="0 0 24 24"
                                                     aria-hidden="true"
                                                     focusable="false">
                                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none"/>
                                                    <line x1="12" y1="8" x2="12" y2="12" stroke="currentColor" stroke-width="2"/>
                                                    <circle cx="12" cy="16" r="1" fill="currentColor"/>
                                                </svg>
                                              
                                            </button>
                                            <div id="cost-tooltip-${slug}"
                                                 role="tooltip"
                                                 class="btr-tooltip">
                                                ${extra.tooltip_text}
                                            </div>
                                        </span>` : ''}
                                         <strong>${importo} €</strong>${scontoTxt}
                                    </label>
                                </div>
                            `;
                        });
                        html += '</fieldset>';
                        }
                    }

                    html += '</div>';
                }

                // --- Costi extra PER DURATA (una sola volta) ------------------
                if (extrasPerDurata.length) {
                    html += `
                        <fieldset class="btr-assicurazioni">
                            <h4>Costi Extra (per durata)</h4>
                            <p>Questi costi si applicano una sola volta all'intero soggiorno.</p>
                    `;
                    // Itera sui costi extra per durata
                    extrasPerDurata.forEach(extra => {
                        // CORREZIONE: Usa lo slug dal database se disponibile, altrimenti genera con slugify
                        const slug = extra.slug || slugify(extra.nome || 'extra');
                        const label = extra.nome || 'Extra';
                        const importo = parseFloat(extra.importo || 0).toFixed(2);
                        const sconto  = parseFloat(extra.sconto  || 0);
                        const scontoTxt = sconto > 0 ? ` <strong>- ${sconto}%</strong>` : '';
                        html += `
                            <div class="btr-assicurazione-item">
                                <label>
                                    <input type="checkbox" 
                                           name="costi_extra_durata[${slug}]" 
                                           value="1"
                                           data-importo="${extra.importo || 0}"
                                           data-cost-slug="${slug}" />
                                    ${label} <strong>${importo} €</strong>${scontoTxt}
                                </label>
                            </div>
                        `;
                    });
                    html += '</fieldset>';
                }


                html += '</div>';
                $('#btr-participants-wrapper').html(html);
                
                // ========================================================================
                // GESTIONE LIMITAZIONE CULLA PER NEONATI
                // ========================================================================
                // Implementa la logica per limitare la selezione delle culle in base al numero di neonati
                if (numInfants > 0) {
                    console.log(`[BTR] 🍼 Inizializzazione controllo culle per ${numInfants} neonato/i`);
                    
                    // Aggiungi input hidden per tracciare il numero di neonati
                    $('#btr-participants-wrapper').append(`<input type="hidden" id="btr-num-infants-tracker" value="${numInfants}">`);
                    
                    // Event handler per gestire la selezione delle culle
                    $(document).off('change.cribLimitation').on('change.cribLimitation', '.btr-crib-checkbox', function() {
                        const maxCribs = parseInt($('#btr-num-infants-tracker').val()) || 0;
                        const selectedCribs = $('.btr-crib-checkbox:checked').length;
                        
                        console.log(`[BTR] 🍼 Culle selezionate: ${selectedCribs}/${maxCribs}`);
                        
                        // NUOVA FUNZIONALITÀ: Aggiorna automaticamente il campo num_infants
                        // Se vengono selezionate culle ma num_infants è 0, aggiornalo
                        const currentInfants = parseInt($('#btr_num_infants').val(), 10) || 0;
                        if (selectedCribs > 0 && currentInfants === 0) {
                            $('#btr_num_infants').val(selectedCribs);
                            console.log(`[BTR] 🔄 AUTO-UPDATE: num_infants aggiornato da ${currentInfants} a ${selectedCribs} (culle selezionate)`);
                            
                            // Aggiorna anche il tracker interno
                            $('#btr-num-infants-tracker').val(selectedCribs);
                            
                            // Trigger un evento personalizzato per notificare il cambio
                            $('#btr_num_infants').trigger('infantsUpdated', [selectedCribs]);
                        }
                        // Se non ci sono più culle selezionate e il num_infants era stato auto-aggiornato, ripristinalo a 0
                        else if (selectedCribs === 0 && currentInfants > 0) {
                            // Verifica se il valore corrente corrisponde esattamente al numero di culle che c'erano prima
                            // Questo evita di resettare se l'utente aveva manualmente impostato un numero diverso
                            const lastSelectedCribs = parseInt($('#btr-num-infants-tracker').attr('data-last-selected') || '0', 10);
                            if (currentInfants === lastSelectedCribs) {
                                $('#btr_num_infants').val(0);
                                console.log(`[BTR] 🔄 AUTO-RESET: num_infants ripristinato a 0 (nessuna culla selezionata)`);
                                $('#btr-num-infants-tracker').val(0);
                                $('#btr_num_infants').trigger('infantsUpdated', [0]);
                            }
                        }
                        
                        // Salva il numero corrente di culle selezionate per future reference
                        $('#btr-num-infants-tracker').attr('data-last-selected', selectedCribs);
                        
                        if (selectedCribs >= maxCribs) {
                            // Disabilita tutte le altre checkbox non selezionate
                            $('.btr-crib-checkbox:not(:checked)').prop('disabled', true).closest('.btr-assicurazione-item').addClass('btr-disabled');
                            console.log(`[BTR] 🚫 Limite culle raggiunto (${maxCribs}), disabilito le altre`);
                        } else {
                            // Riabilita tutte le checkbox
                            $('.btr-crib-checkbox').prop('disabled', false).closest('.btr-assicurazione-item').removeClass('btr-disabled');
                            console.log(`[BTR] ✅ Culle disponibili, riabilito tutte le checkbox`);
                        }
                        
                        // Aggiorna il conteggio visivo se necessario
                        updateCribCounter(selectedCribs, maxCribs);
                    });
                    
                    // Trigger iniziale per impostare lo stato corretto
                    setTimeout(() => {
                        $('.btr-crib-checkbox').first().trigger('change.cribLimitation');
                    }, 100);
                    
                    // Aggiungi CSS per elementi disabilitati se non presente
                    if ($('#btr-crib-limitation-styles').length === 0) {
                        $('head').append(`
                            <style id="btr-crib-limitation-styles">
                                .btr-assicurazione-item.btr-disabled {
                                    opacity: 0.5;
                                    pointer-events: none;
                                }
                                .btr-assicurazione-item.btr-disabled label {
                                    color: #999;
                                    cursor: not-allowed;
                                }
                                .btr-crib-info {
                                    background: #fff3cd;
                                    border: 1px solid #ffeaa7;
                                    border-radius: 4px;
                                    padding: 10px;
                                    margin-bottom: 15px;
                                    font-size: 13px;
                                    display: flex;
                                    align-items: center;
                                    gap: 8px;
                                }
                                .btr-crib-counter {
                                    font-weight: bold;
                                    color: #856404;
                                }
                            </style>
                        `);
                    }
                }
                
                assicurazioneButton.slideUp();
                proceedButton.slideDown();
                $('html, body').animate({
                    scrollTop: $('#btr-participants-wrapper').offset().top - 150
                }, 600);
            } else {
                alert(response.data ? response.data.message : 'Nessuna assicurazione disponibile.');
            }
        }).fail(function () {
            alert('Errore durante il recupero delle assicurazioni.');
        });
    });
    function slugify(str) {
        return str.toString().toLowerCase()
            .replace(/\s+/g, '-')
            .replace(/[^\w-]+/g, '')
            .replace(/--+/g, '-')
            .trim();
    }

    /**
     * Aggiorna il contatore visivo delle culle per neonati
     * @param {number} selectedCribs - Numero di culle selezionate
     * @param {number} maxCribs - Numero massimo di culle disponibili
     */
    function updateCribCounter(selectedCribs, maxCribs) {
        const $cribInfo = $('.btr-crib-info');
        
        if ($cribInfo.length === 0 && maxCribs > 0) {
            // Crea il contatore se non esiste
            $('#btr-participants-wrapper').prepend(`
                <div class="btr-crib-info">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                    </svg>
                    <strong>🍼 Culle per neonati:</strong> 
                    <span class="btr-crib-counter">${selectedCribs}/${maxCribs}</span> selezionate
                    ${maxCribs === 1 ? ' (massimo 1 culla disponibile)' : ` (massimo ${maxCribs} culle disponibili)`}
                </div>
            `);
        } else if ($cribInfo.length > 0) {
            // Aggiorna il contatore esistente
            $cribInfo.find('.btr-crib-counter').text(`${selectedCribs}/${maxCribs}`);
        }
    }


    function sanitizeRoomType(roomType) {
        return roomType.replace(/\s+/g, '-').replace(/[^\w\-]+/g, '').toLowerCase();
    }



    // Disabilita il pulsante #btr-proceed finché i campi obbligatori del primo partecipante non sono completi
    // RIDOTTI A SOLO 4 CAMPI: nome, cognome, email, telefono
    $(document).on('input change', '[name^="anagrafici[0]"]', function () {
        const requiredFields = [
            'nome',
            'cognome', 
            'email',
            'telefono'
        ];
        let allValid = true;

        requiredFields.forEach(function (field) {
            const selector = '[name="anagrafici[0][' + field + ']"]';
            const val = $(selector).val();
            if (!val || val.trim() === '') {
                allValid = false;
            }
        });

        $('#btr-proceed').prop('disabled', !allValid);
    });

    // Mostra il campo nazione estera solo se la provincia è ESTERO



    /**
     * Rimuove l'attributo required dai campi nascosti per evitare errori di validazione
     */
    function fixHiddenRequiredFields() {
        $('input[required], textarea[required], select[required]').each(function() {
            const $field = $(this);
            const isVisible = $field.is(':visible') && $field.closest(':hidden').length === 0;
            
            if (!isVisible) {
                console.log('[BTR] 🔧 Rimuovo required da campo nascosto:', $field.attr('name'));
                $field.removeAttr('required').data('was-required', true);
            }
        });
    }

    /**
     * Sistema di caching e debounce per le verifiche delle notti extra
     * @since 1.0.19
     */
    let extraNightCache = new Map(); // Cache per evitare chiamate duplicate
    let extraNightDebounceTimer = null; // Timer per debounce
    const EXTRA_NIGHT_DEBOUNCE_DELAY = 500; // Delay in millisecondi

    /**
     * Genera una chiave univoca per il cache basata sui parametri
     */
    function generateExtraNightCacheKey(packageId, selectedDate, numPeople, numChildren) {
        return `${packageId}_${selectedDate}_${numPeople}_${numChildren}`;
    }

    /**
     * Verifica le notti extra con debounce e caching ottimizzato
     * @since 1.0.19
     */
    function checkExtraNightAvailabilityOptimized() {
        console.log('[BTR] ▶️ checkExtraNightAvailabilityOptimized() chiamata');
        
        // Prima di tutto, risolvi i campi required nascosti
        fixHiddenRequiredFields();
        
        const packageId = form.find('input[name="btr_package_id"]').val();
        let selectedDate = $('#btr_selected_date').val();
        
        // FALLBACK: Se btr_selected_date è vuoto, prova a ricavare dalla data selezionata
        if (!selectedDate) {
            selectedDate = $('#btr_date').val();
            console.log('[BTR] 🔄 Fallback: usando valore da #btr_date:', selectedDate);
        }
        
        const numPeople = parseInt($('#btr_num_people').val(), 10) || 0;
        const numChildren = parseInt(numChildF1Field.val(), 10) + parseInt(numChildF2Field.val(), 10) + 
                          parseInt(numChildF3Field.val(), 10) + parseInt(numChildF4Field.val(), 10);

        if (!packageId || !selectedDate) {
            console.log('[BTR] ❌ Dati insufficienti per verifica notti extra');
            hideExtraNightControls();
            return;
        }

        console.log('[BTR] 🚀 Verifico notti extra per:', {packageId, selectedDate, numPeople, numChildren});

        // Genera chiave cache
        const cacheKey = generateExtraNightCacheKey(packageId, selectedDate, numPeople, numChildren);
        
        // Controlla se abbiamo già la risposta in cache
        if (extraNightCache.has(cacheKey)) {
            const cachedResult = extraNightCache.get(cacheKey);
            console.log('[BTR] 📦 Usando risultato dalla cache:', cachedResult);
            
            if (cachedResult.has_extra_nights) {
                showExtraNightControls(cachedResult.extra_night_details);
            } else {
                hideExtraNightControls();
            }
            return;
        }

        // Mostra un indicatore di caricamento per il box notte extra
        if ($('.custom-dropdown-wrapper').length) {
            // Salva il contenuto originale se non è già salvato
            if (!$('.custom-dropdown-wrapper').data('original-content')) {
                $('.custom-dropdown-wrapper').data('original-content', $('.custom-dropdown-wrapper').html());
            }
            
            // Mostra l'indicatore di caricamento
            $('.custom-dropdown-wrapper').html('<div class="loading-indicator" style="padding: 20px; text-align: center; font-style: italic; color: #666; border-radius: 6px; background-color: #f9f9f9; box-shadow: 0 1px 3px rgba(0,0,0,0.1);"><div style="margin-bottom: 10px;"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#0097c5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg></div>Verifica disponibilità notti extra...</div>');
            $('.custom-dropdown-wrapper').slideDown(300);
        }

        $.post(btr_booking_form.ajax_url, {
            action: 'btr_check_extra_night_availability',
            nonce: btr_booking_form.nonce,
            package_id: packageId,
            selected_date: selectedDate,
            num_people: numPeople,
            num_children: numChildren
        }).done(function(response) {
            console.log('[BTR] 📊 Risposta verifica notti extra:', response);
            
            // Debug dettagliato
            if (response.success) {
                console.log('[BTR] ✅ Chiamata AJAX completata con successo');
                console.log('[BTR] 🔍 Notti extra disponibili:', response.data.has_extra_nights ? 'Sì' : 'No');
                
                if (response.data.extra_night_details) {
                    console.log('[BTR] 📅 Data notte extra:', response.data.extra_night_details.extra_date_formatted);
                }
            } else {
                console.error('[BTR] ❌ Errore nella risposta AJAX:', response.data.message);
            }
            
            // Salva nel cache
            extraNightCache.set(cacheKey, response.data);
            
            if (response.success && response.data.has_extra_nights) {
                console.log('[BTR] 🎯 Mostro controlli notte extra');
                showExtraNightControls(response.data.extra_night_details);
            } else {
                console.log('[BTR] 🎯 Nascondo controlli notte extra');
                hideExtraNightControls();
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error('[BTR] ❌ Errore verifica notti extra:', textStatus, errorThrown);
            // In caso di errore, nasconde il blocco per sicurezza
            hideExtraNightControls();
        });
    }

    /**
     * Wrapper con debounce per evitare troppe chiamate AJAX consecutive
     * @since 1.0.19
     */
    function debouncedExtraNightCheck() {
        console.log('[BTR] ⏰ debouncedExtraNightCheck() chiamata - avvio debounce timer');
        
        // Cancella il timer precedente se esiste
        if (extraNightDebounceTimer) {
            console.log('[BTR] ⏰ Cancello timer precedente');
            clearTimeout(extraNightDebounceTimer);
        }
        
        // Imposta un nuovo timer
        extraNightDebounceTimer = setTimeout(function() {
            console.log('[BTR] ⏰ Timer scaduto, eseguo verifica notti extra');
            checkExtraNightAvailabilityOptimized();
        }, EXTRA_NIGHT_DEBOUNCE_DELAY);
        
        console.log('[BTR] ⏰ Timer impostato per', EXTRA_NIGHT_DEBOUNCE_DELAY, 'ms');
    }

    /**
     * Pulisce il cache quando cambia la data (per garantire dati aggiornati)
     * @since 1.0.19
     */
    function clearExtraNightCache() {
        extraNightCache.clear();
        console.log('[BTR] 🧹 Cache notti extra pulito');
    }

    /**
     * Verifica le notti extra solo al click sulla data
     * Versione ottimizzata che verifica immediatamente se ci sono notti extra
     * senza attendere la selezione del numero di persone
     * @since 1.0.20
     */
    function checkExtraNightAvailabilityOnDateClick() {
        console.log('[BTR] ▶️ checkExtraNightAvailabilityOnDateClick() chiamata');
        
        // Prima di tutto, risolvi i campi required nascosti
        fixHiddenRequiredFields();
        
        const packageId = form.find('input[name="btr_package_id"]').val();
        let selectedDate = $('#btr_selected_date').val();
        
        // FALLBACK: Se btr_selected_date è vuoto, prova a ricavare dalla data selezionata
        if (!selectedDate) {
            selectedDate = $('#btr_date').val();
        }
        
        if (!packageId || !selectedDate) {
            console.log('[BTR] ❌ Dati insufficienti per verifica notti extra');
            hideExtraNightControls();
            return;
        }

        console.log('[BTR] 🚀 Verifico notti extra per data:', {packageId, selectedDate});

        // Controlla se abbiamo già la risposta in cache per questa data
        const cacheKey = `${packageId}_${selectedDate}_date_check`;
        
        if (extraNightCache.has(cacheKey)) {
            const cachedResult = extraNightCache.get(cacheKey);
            console.log('[BTR] 📦 Usando risultato dalla cache:', cachedResult);
            
            if (cachedResult.has_extra_nights) {
                showExtraNightControls(cachedResult.extra_night_details);
            } else {
                hideExtraNightControls();
            }
            return;
        }

        // Mostra l'indicatore di caricamento
        if (!$('.custom-dropdown-wrapper').data('original-content')) {
            $('.custom-dropdown-wrapper').data('original-content', $('.custom-dropdown-wrapper').html());
        }
        
        $('.custom-dropdown-wrapper').html('<div class="loading-indicator" style="padding: 20px; text-align: center; font-style: italic; color: #666; border-radius: 6px; background-color: #f9f9f9; box-shadow: 0 1px 3px rgba(0,0,0,0.1);"><div style="margin-bottom: 10px;"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#0097c5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg></div>Verifica disponibilità notti extra...</div>');
        $('.custom-dropdown-wrapper').slideDown(300);

        $.post(btr_booking_form.ajax_url, {
            action: 'btr_check_extra_night_availability',
            nonce: btr_booking_form.nonce,
            package_id: packageId,
            selected_date: selectedDate,
            num_people: 0, // Non importante per la verifica iniziale
            num_children: 0, // Non importante per la verifica iniziale
            date_only_check: true // Flag per indicare che è una verifica solo data
        }).done(function(response) {
            console.log('[BTR] 📊 Risposta verifica notti extra (solo data):', response);
            
            // Salva in cache
            extraNightCache.set(cacheKey, {
                has_extra_nights: response.success && response.data.has_extra_nights,
                extra_night_details: response.data.extra_night_details
            });
            
            if (response.success && response.data.has_extra_nights) {
                console.log('[BTR] ✅ Notti extra disponibili per questa data');
                showExtraNightControls(response.data.extra_night_details);
            } else {
                console.log('[BTR] 🎯 Nessuna notte extra disponibile per questa data');
                hideExtraNightControls();
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error('[BTR] ❌ Errore verifica notti extra:', textStatus, errorThrown);
            // In caso di errore, nasconde il blocco per sicurezza
            hideExtraNightControls();
        });
    }

    /**
     * Verifica dinamicamente se esistono notti extra configurate per la data e numero di persone selezionate
     * @since 1.0.18
     */
    function checkExtraNightAvailability() {
        console.log('[BTR] ▶️ checkExtraNightAvailability() chiamata');
        
        // Prima di tutto, risolvi i campi required nascosti
        fixHiddenRequiredFields();
        
        const packageId = form.find('input[name="btr_package_id"]').val();
        let selectedDate = $('#btr_selected_date').val();
        
        // FALLBACK: Se btr_selected_date è vuoto, prova a ricavare dalla data selezionata
        if (!selectedDate) {
            selectedDate = $('#btr_date').val();
            console.log('[BTR] 🔄 Fallback: usando valore da #btr_date:', selectedDate);
        }
        
        const numPeople = parseInt($('#btr_num_people').val(), 10) || 0;
        const numChildren = parseInt(numChildF1Field.val(), 10) + parseInt(numChildF2Field.val(), 10) + 
                          parseInt(numChildF3Field.val(), 10) + parseInt(numChildF4Field.val(), 10);

        console.log('[BTR] Parametri verifica:', {
            packageId: packageId,
            selectedDate: selectedDate,
            numPeople: numPeople,
            numChildren: numChildren
        });

        if (!packageId || !selectedDate) {
            console.log('[BTR] ❌ Dati insufficienti per verifica notti extra');
            return;
        }

        // Se il numero di persone è zero, verifica comunque se esistono configurazioni
        // Se esistono, mostra il blocco ma senza verifica di capacità
        // Se non esistono, nasconde il blocco
        const skipCapacityCheck = (numPeople === 0);

        console.log('[BTR] 🚀 Verifico notti extra per:', {packageId, selectedDate, numPeople, numChildren});
        console.log('[BTR] 📡 AJAX URL:', btr_booking_form.ajax_url);
        console.log('[BTR] 🔐 Nonce:', btr_booking_form.nonce);

        $.post(btr_booking_form.ajax_url, {
            action: 'btr_check_extra_night_availability',
            nonce: btr_booking_form.nonce,
            package_id: packageId,
            selected_date: selectedDate,
            num_people: numPeople,
            num_children: numChildren,
            skip_capacity_check: skipCapacityCheck
        }).done(function(response) {
            console.log('[BTR] Risposta verifica notti extra:', response);
            
            if (response.success && response.data.has_extra_nights) {
                // Mostra il blocco notte extra
                showExtraNightControls(response.data.extra_night_details);
            } else {
                // Nasconde il blocco notte extra
                hideExtraNightControls();
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error('[BTR] Errore verifica notti extra:', textStatus, errorThrown);
            // In caso di errore, nasconde il blocco per sicurezza
            hideExtraNightControls();
        });
    }

    /**
     * Mostra i controlli per le notti extra con animazione
     * @param {Object} details Dettagli delle notti extra dal server
     */
    function showExtraNightControls(details) {
        console.log('[BTR] 🔍 Mostro controlli notte extra:', details);
        
        // Salva la selezione corrente prima di ripristinare il contenuto
        const currentValue = $('#btr_add_extra_night').val();
        const currentDisplayText = $('#dropdown-display').text();
        const hasExistingSelection = currentValue === '1';
        
        console.log('[BTR] 💾 Stato attuale prima del ripristino:', {
            currentValue: currentValue,
            currentDisplayText: currentDisplayText,
            hasExistingSelection: hasExistingSelection
        });
        
        // Ripristina il contenuto originale se disponibile
        if ($('.custom-dropdown-wrapper').data('original-content')) {
            $('.custom-dropdown-wrapper').html($('.custom-dropdown-wrapper').data('original-content'));
        } else {
            // Altrimenti rimuovi solo l'indicatore di caricamento
            $('.custom-dropdown-wrapper .loading-indicator').remove();
        }
        
        // Aggiorna il testo della data se fornito
        if (details && details.extra_date_formatted) {
            $('.custom-dropdown-description').text(
                `Se selezionato, verrà aggiunto un supplemento per persona per la notte del ${details.extra_date_formatted}, soggetto a disponibilità.`
            );
        }

        // Mostra direttamente il wrapper del dropdown personalizzato
        $('.custom-dropdown-wrapper').slideDown(300, function() {
            // Dopo l'animazione, reinizializza il dropdown con logica semplificata
            initializeDropdownSimple();
            
            // Ripristina la selezione precedente se esisteva o dalla variabile globale
            const globalSelection = window.btrExtraNightSelection;
            const shouldRestore = hasExistingSelection || 
                                (globalSelection && globalSelection.value === '1' && 
                                 globalSelection.timestamp && (Date.now() - globalSelection.timestamp) < 10000);
            
            if (shouldRestore) {
                console.log('[BTR] 🔄 Ripristino selezione precedente (DOM o globale)');
                setTimeout(() => {
                    const hiddenInput = document.getElementById('btr_add_extra_night');
                    const display = document.querySelector('#dropdown-display');
                    
                    if (hiddenInput) {
                        hiddenInput.value = '1';
                        console.log('[BTR] ✅ Hidden input ripristinato a 1');
                    }
                    
                    if (display) {
                        const textToUse = globalSelection && globalSelection.text ? 
                                        globalSelection.text : 
                                        (currentDisplayText.includes('Sì') ? currentDisplayText : 'Sì, aggiungi la notte extra');
                        display.textContent = textToUse;
                        console.log('[BTR] ✅ Display ripristinato a:', textToUse);
                    }
                    
                    // Verifica che getExtraNightFlag ora ritorni 1
                    setTimeout(() => {
                        const finalFlag = getExtraNightFlag();
                        console.log('[BTR] 🔍 Verifica finale getExtraNightFlag dopo ripristino:', finalFlag);
                    }, 50);
                }, 100);
            }
        });
        
        // Se il dropdown è stato inizializzato, aggiorna il valore nascosto
        if ($extraNightSelect.length) {
            console.log('[BTR] 🔍 Trovato campo nascosto notte extra, aggiorno valore');
        } else {
            console.log('[BTR] ⚠️ Campo nascosto notte extra non trovato!');
        }
        
        // Mostra il vecchio box checkbox se presente
        if ($extraNightBox.length) {
            console.log('[BTR] 🔍 Trovato vecchio box checkbox, mostro');
            $extraNightBox.slideDown(300);
        }
    }

    /**
     * Nasconde i controlli per le notti extra con animazione
     * MODIFICATO: Preserva la selezione dell'utente se già fatta
     */
    function hideExtraNightControls(forceReset = false) {
        console.log('[BTR] 🔍 Nascondo controlli notte extra, forceReset:', forceReset);
        
        // Reset dei valori SOLO se forzato o se non c'è una selezione attiva
        const currentValue = $('#btr_add_extra_night').val();
        const hasUserSelection = currentValue === '1' && $('#dropdown-display').text().includes('Sì');
        
        if (forceReset || !hasUserSelection) {
            console.log('[BTR] 🔄 Reset valori notti extra - forceReset:', forceReset, 'hasUserSelection:', hasUserSelection);
        if ($extraNightSelect.length) {
            $extraNightSelect.val('0');
                console.log('[BTR] ↩️ Select resettato a 0');
        }
        if ($('#btr_extra_night').length) {
            $('#btr_extra_night').prop('checked', false);
                console.log('[BTR] ↩️ Checkbox deselezionato');
        }
        
        // Reset del dropdown visibile
        $('#dropdown-display').text('No, solo pacchetto base');
            console.log('[BTR] ↩️ Display resettato');
        } else {
            console.log('[BTR] 💾 PRESERVO selezione utente:', {
                currentValue: currentValue,
                displayText: $('#dropdown-display').text(),
                hasUserSelection: hasUserSelection
            });
        }
        
        // Nasconde direttamente il wrapper del dropdown personalizzato
        $('.custom-dropdown-wrapper').slideUp(300);
        
        // Nasconde il vecchio box checkbox se presente
        if ($extraNightBox.length) {
            $extraNightBox.slideUp(300);
        }
    }

    /**
     * Inizializza i gestori di eventi per il dropdown personalizzato delle notti extra
     * Compatibile con il gestore JavaScript inline del template
     * @since 1.0.20
     */
    function initializeCustomDropdownEvents() {
        console.log('[BTR] 🔧 Inizializzazione gestori dropdown personalizzato');
        
        // Gestore per il click sul pulsante dropdown (usa event delegation)
        $(document).on('click', '.dropdown-button', function(e) {
            // Se è il dropdown principale #dropdown-trigger, non interferire (gestito da initializeDropdownAfterLoad)
            if ($(this).closest('#dropdown-trigger').length) {
                console.log('[BTR] 📋 Dropdown principale trovato, gestito da initializeDropdownAfterLoad');
                return;
            }
            
            e.preventDefault();
            e.stopPropagation();
            
            const $dropdown = $(this).closest('.custom-dropdown');
            const $menu = $dropdown.find('.dropdown-menu');
            const $arrow = $(this).find('.dropdown-arrow');
            
            // Chiudi altri dropdown aperti
            $('.dropdown-menu').not($menu).removeClass('show');
            $('.dropdown-arrow').not($arrow).css('transform', 'rotate(0deg)');
            
            // Toggle del menu
            $menu.toggleClass('show');
            
            // Animazione freccia
            if ($menu.hasClass('show')) {
                $arrow.css('transform', 'rotate(180deg)');
            } else {
                $arrow.css('transform', 'rotate(0deg)');
            }
            
            console.log('[BTR] 📋 Dropdown menu toggled:', $menu.hasClass('show'));
        });
        
        // Gestore per la selezione delle opzioni del dropdown
        $(document).on('click', '.dropdown-item', function(e) {
            // Se è il dropdown principale #dropdown-trigger, non interferire (gestito da initializeDropdownAfterLoad)
            if ($(this).closest('#dropdown-trigger').length) {
                console.log('[BTR] 📋 Dropdown principale trovato, gestito da initializeDropdownAfterLoad');
                return;
            }
            
            e.preventDefault();
            e.stopPropagation();
            
            const $item = $(this);
            const value = $item.data('value');
            const text = $item.text();
            const $dropdown = $item.closest('.custom-dropdown');
            const $display = $dropdown.find('#dropdown-display');
            const $menu = $dropdown.find('.dropdown-menu');
            const $arrow = $dropdown.find('.dropdown-arrow');
            const $hiddenInput = $('#btr_add_extra_night');
            
            // Aggiorna il display
            $display.text(text);
            
            // Aggiorna il campo nascosto
            if ($hiddenInput.length) {
                $hiddenInput.val(value);
                console.log('[BTR] 📋 Campo nascosto aggiornato:', value);
            }
            
            // Chiudi il menu
            $menu.removeClass('show');
            $arrow.css('transform', 'rotate(0deg)');
            
            console.log('[BTR] 📋 Opzione selezionata:', {value, text});
            
            // Trigger event per eventuali altri listener
            $(document).trigger('btr:extra-night-changed', [value, text]);
        });
        
        // Chiudi il dropdown quando si clicca fuori (solo per dropdown dinamici)
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.custom-dropdown').length) {
                $('.dropdown-menu').not('#dropdown-trigger .dropdown-menu').removeClass('show');
                $('.dropdown-arrow').not('#dropdown-trigger .dropdown-arrow').css('transform', 'rotate(0deg)');
            }
        });
        
        // Gestore per i tasti della tastiera
        $(document).on('keydown', '.dropdown-button', function(e) {
            if ($(this).closest('#dropdown-trigger').length && document.getElementById('dropdown-trigger')) {
                // Se esiste il gestore inline, non interferire
                return;
            }
            
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                $(this).trigger('click');
            }
        });
        
        console.log('[BTR] ✅ Gestori dropdown personalizzato inizializzati');
    }

    /**
     * Reinizializza il dropdown delle notti extra dopo il caricamento dinamico
     * Ricrea i gestori di eventi inline come nel template originale
     * @since 1.0.20
     */
    function initializeDropdownAfterLoad() {
        console.log('[BTR] 🔄 Reinizializzazione dropdown dopo caricamento dinamico');
        
        const dropdown = document.getElementById('dropdown-trigger');
        
        if (!dropdown) {
            console.log('[BTR] ⚠️ Dropdown #dropdown-trigger non trovato');
            return;
        }
        
        // Verifica se i gestori sono già stati aggiunti
        if (dropdown.hasAttribute('data-initialized')) {
            console.log('[BTR] ✅ Dropdown già inizializzato');
            return;
        }
        
        const dropdownButton = dropdown.querySelector('.dropdown-button');
        const dropdownMenu = dropdown.querySelector('.dropdown-menu');
        const dropdownDisplay = dropdown.querySelector('#dropdown-display');
        const hiddenInput = document.getElementById('btr_add_extra_night');
        
        if (!dropdownButton || !dropdownMenu || !dropdownDisplay || !hiddenInput) {
            console.log('[BTR] ⚠️ Elementi dropdown mancanti');
            return;
        }
        
        // Rimuovi eventuali listener precedenti
        const newButton = dropdownButton.cloneNode(true);
        dropdownButton.parentNode.replaceChild(newButton, dropdownButton);
        
        // Aggiungi i nuovi listener
        newButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('[BTR] 📋 Click su dropdown button');
            
            dropdownMenu.classList.toggle('show');
            const arrow = newButton.querySelector('.dropdown-arrow');
            if (arrow) {
                arrow.style.transform = dropdownMenu.classList.contains('show')
                    ? 'rotate(180deg)'
                    : 'rotate(0deg)';
            }
        });
        
        // Aggiungi listener per le opzioni
        const dropdownItems = dropdown.querySelectorAll('.dropdown-item');
        dropdownItems.forEach(item => {
            // Crea nuovo elemento per rimuovere listener precedenti
            const newItem = item.cloneNode(true);
            item.parentNode.replaceChild(newItem, item);
            
            newItem.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const value = this.getAttribute('data-value');
                const text = this.textContent.trim();
                console.log('[BTR] 📋 CLICK su opzione:', {value, text, element: this});
                
                // Trova gli elementi correnti (non i riferimenti originali che potrebbero essere obsoleti)
                const currentDisplay = dropdown.querySelector('#dropdown-display');
                const currentHiddenInput = document.getElementById('btr_add_extra_night');
                const currentMenu = dropdown.querySelector('.dropdown-menu');
                
                if (!currentDisplay) {
                    console.error('[BTR] ❌ Display element non trovato!');
                    return;
                }
                
                if (!currentHiddenInput) {
                    console.error('[BTR] ❌ Hidden input non trovato!');
                    return;
                }
                
                // Aggiorna il display
                currentDisplay.textContent = text;
                console.log('[BTR] 📋 Display aggiornato a:', text);
                
                // Aggiorna il campo nascosto
                currentHiddenInput.value = value;
                console.log('[BTR] 📋 Hidden input aggiornato a:', value);
                
                // Chiudi il menu
                if (currentMenu) {
                    currentMenu.classList.remove('show');
                    console.log('[BTR] 📋 Menu chiuso');
                }
                
                // Ruota la freccia
                const currentArrow = newButton.querySelector('.dropdown-arrow');
                if (currentArrow) {
                    currentArrow.style.transform = 'rotate(0deg)';
                    console.log('[BTR] 📋 Freccia ruotata a 0deg');
                }
                
                // Trigger event personalizzato
                $(document).trigger('btr:extra-night-changed', [value, text]);
                console.log('[BTR] 📋 Event btr:extra-night-changed triggerato');
            });
        });
        
        // Chiudi menu con click esterno
        const outsideClickHandler = function(e) {
            if (!dropdown.contains(e.target)) {
                dropdownMenu.classList.remove('show');
                const arrow = newButton.querySelector('.dropdown-arrow');
                if (arrow) {
                    arrow.style.transform = 'rotate(0deg)';
                }
            }
        };
        
        // Rimuovi listener precedente se esiste
        document.removeEventListener('click', dropdown._outsideClickHandler);
        dropdown._outsideClickHandler = outsideClickHandler;
        document.addEventListener('click', outsideClickHandler);
        
        // Accessibilità: toggle con tastiera
        newButton.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
            }
        });
        
        // Marca come inizializzato
        dropdown.setAttribute('data-initialized', 'true');
        console.log('[BTR] ✅ Dropdown reinizializzato con successo');
    }

    /**
     * Versione semplificata per inizializzare il dropdown notti extra
     * Usa event delegation per evitare problemi con riferimenti DOM
     * @since 1.0.20
     */
    function initializeDropdownSimple() {
        console.log('[BTR] 🔄 Inizializzazione dropdown semplificata');
        
        const dropdown = document.getElementById('dropdown-trigger');
        if (!dropdown) {
            console.log('[BTR] ⚠️ Dropdown non trovato');
            return;
        }
        
        // Rimuovi listener precedenti
        if (dropdown._eventListenerAdded) {
            console.log('[BTR] ✅ Dropdown già inizializzato');
            return;
        }
        
        // Aggiungi event listener al contenitore dropdown
        dropdown.addEventListener('click', function(e) {
            e.stopPropagation();
            
            // Gestione click sul pulsante
            if (e.target.closest('.dropdown-button')) {
                e.preventDefault();
                const menu = this.querySelector('.dropdown-menu');
                const arrow = this.querySelector('.dropdown-arrow');
                
                if (menu) {
                    const isOpening = !menu.classList.contains('show');
                    menu.classList.toggle('show');
                    
                    if (arrow) {
                        arrow.style.transform = isOpening ? 'rotate(180deg)' : 'rotate(0deg)';
                    }
                    
                    console.log('[BTR] 📋 Menu toggled:', isOpening);
                }
                return;
            }
            
            // Gestione click sull'opzione
            if (e.target.closest('.dropdown-item')) {
                e.preventDefault();
                const option = e.target.closest('.dropdown-item');
                const value = option.getAttribute('data-value');
                const text = option.textContent.trim();
                
                console.log('[BTR] 📋 Opzione cliccata:', {value, text});
                
                // Aggiorna gli elementi
                const display = this.querySelector('#dropdown-display');
                const hiddenInput = document.getElementById('btr_add_extra_night');
                const menu = this.querySelector('.dropdown-menu');
                const arrow = this.querySelector('.dropdown-arrow');
                
                console.log('[BTR] 🔍 STATO PRIMA AGGIORNAMENTO:', {
                    displayExists: !!display,
                    displayText: display ? display.textContent : 'N/A',
                    hiddenInputExists: !!hiddenInput,
                    hiddenInputValue: hiddenInput ? hiddenInput.value : 'N/A',
                    hiddenInputId: hiddenInput ? hiddenInput.id : 'N/A'
                });
                
                if (display) {
                    display.textContent = text;
                    console.log('[BTR] ✅ Display aggiornato da "' + display.textContent + '" a "' + text + '"');
                } else {
                    console.error('[BTR] ❌ Display element NON trovato!');
                }
                
                if (hiddenInput) {
                    const oldValue = hiddenInput.value;
                    hiddenInput.value = value;
                    console.log('[BTR] ✅ Hidden input aggiornato da "' + oldValue + '" a "' + value + '"');
                    
                    // Verifica immediata che il valore sia stato effettivamente impostato
                    setTimeout(() => {
                        const currentValue = document.getElementById('btr_add_extra_night')?.value;
                        const jqueryValue = $('#btr_add_extra_night').val();
                        console.log('[BTR] 🔍 VERIFICA POST-AGGIORNAMENTO:', {
                            domValue: currentValue,
                            jqueryValue: jqueryValue,
                            getExtraNightFlag: getExtraNightFlag(),
                            valuesMatch: currentValue === value
                        });
                    }, 50);
                } else {
                    console.error('[BTR] ❌ Hidden input NON trovato!');
                    
                    // Prova a crearlo se non esiste
                    const newInput = document.createElement('input');
                    newInput.type = 'hidden';
                    newInput.id = 'btr_add_extra_night';
                    newInput.name = 'btr_add_extra_night';
                    newInput.value = value;
                    document.body.appendChild(newInput);
                    console.log('[BTR] 🆕 Campo nascosto creato dinamicamente con valore:', value);
                }
                
                // Chiudi menu
                if (menu) menu.classList.remove('show');
                if (arrow) arrow.style.transform = 'rotate(0deg)';
                
                // Test immediato della funzione getExtraNightFlag
                setTimeout(() => {
                    const flag = getExtraNightFlag();
                    console.log('[BTR] 🔍 Test getExtraNightFlag dopo aggiornamento:', {
                        flag: flag,
                        hiddenInputValue: hiddenInput ? hiddenInput.value : 'not found',
                        selectValue: $('#btr_add_extra_night').val(),
                        checkboxChecked: $('#btr_extra_night').is(':checked')
                    });
                }, 100);
                
                // Trigger evento
                $(document).trigger('btr:extra-night-changed', [value, text]);
                console.log('[BTR] ✅ Evento triggerato');
            }
        });
        
        // Gestore per chiudere con click esterno
        if (!document._extraNightOutsideHandler) {
            const outsideHandler = function(e) {
                if (!e.target.closest('#dropdown-trigger')) {
                    const dropdowns = document.querySelectorAll('#dropdown-trigger .dropdown-menu');
                    const arrows = document.querySelectorAll('#dropdown-trigger .dropdown-arrow');
                    
                    dropdowns.forEach(menu => menu.classList.remove('show'));
                    arrows.forEach(arrow => arrow.style.transform = 'rotate(0deg)');
                }
            };
            
            document.addEventListener('click', outsideHandler);
            document._extraNightOutsideHandler = true;
        }
        
        // Marca come inizializzato
        dropdown._eventListenerAdded = true;
        console.log('[BTR] ✅ Dropdown semplificato inizializzato');
    }

    // Inizializza i gestori del dropdown personalizzato
    initializeCustomDropdownEvents();

    /**
     * Variabile globale per preservare la selezione notti extra
     * Workaround per il problema di timing tra UI e ricaricamento AJAX
     */
    window.btrExtraNightSelection = {
        value: '0',
        text: 'No, solo pacchetto base',
        timestamp: null
    };

    /**
     * Listener per il cambiamento della selezione notti extra
     * Ricarica le camere e ricalcola i prezzi quando cambia la selezione
     * @since 1.0.20
     */
    $(document).on('btr:extra-night-changed', function(event, value, text) {
        console.log('[BTR] 🌙 Cambiamento notte extra:', {value, text});
        
        // Salva la selezione in variabile globale
        window.btrExtraNightSelection = {
            value: value,
            text: text,
            timestamp: Date.now()
        };
        console.log('[BTR] 💾 Selezione salvata in variabile globale:', window.btrExtraNightSelection);
        
        // NUOVO: Reset form partecipanti se già generati (cambio notte extra)
        const hasParticipantForms = $('#btr-participants-wrapper').children().length > 0;
        if (hasParticipantForms) {
            console.log('[BTR] 🌙 Cambio notte extra con form esistenti - Reset completo');
            resetBookingWorkflow('Modifica notte extra', 'complete');
        }
        
        // Ricarica le camere se sono stati già selezionati i partecipanti
        const numPeople = parseInt($('#btr_num_people').val(), 10);
        const numAdults = parseInt($('#btr_num_adults').val(), 10) || 0;
        const numInfants = parseInt($('#btr_num_infants').val(), 10) || 0;
        const numChildF1 = parseInt($('#btr_num_child_f1').val(), 10) || 0;
        const numChildF2 = parseInt($('#btr_num_child_f2').val(), 10) || 0;
        const numChildF3 = parseInt($('#btr_num_child_f3').val(), 10) || 0;
        const numChildF4 = parseInt($('#btr_num_child_f4').val(), 10) || 0;
        const numChildren = numChildF1 + numChildF2 + numChildF3 + numChildF4;
        
        if (!isNaN(numPeople) && numPeople > 0) {
            console.log('[BTR] 🔄 Ricaricamento camere per cambio notte extra');
            
            // Test getExtraNightFlag prima del ricaricamento
            const flagBeforeReload = getExtraNightFlag();
            console.log('[BTR] 🔍 Flag notte extra prima del ricaricamento:', flagBeforeReload);
            
            // Se il flag è ancora 0 ma l'evento indica value=1, aspetta che si aggiorni
            if (flagBeforeReload === 0 && value === '1') {
                console.log('[BTR] ⏰ Flag non ancora aggiornato, attendo 200ms...');
                setTimeout(() => {
                    const flagAfterWait = getExtraNightFlag();
                    console.log('[BTR] 🔍 Flag dopo attesa:', flagAfterWait);
                    performRoomReload();
                }, 200);
                return;
            }
            
            performRoomReload();
            
            function performRoomReload() {
                // Reset dello stato selezionato
                selectedCapacity = 0;
                totalPrice = 0;
                
                // Reset assegnazioni bambini prima di ricaricare
                if (typeof childAssignments !== 'undefined') {
                    childAssignments = {};
                    console.log('[BTR] 🧹 Reset assegnazioni bambini durante reload camere');
                }
                
                // Reset sezioni successive
                roomTypesSection.slideUp();
                roomTypesContainer.empty();
                assicurazioneButton.slideUp();
                customerSection.slideUp();
                $('.timeline .step.step3').removeClass('active');
                
                // Aggiorna il display della capacità richiesta
                requiredCapacity = numAdults + numChildF1 + numChildF2 + numChildF3 + numChildF4 + numInfants;
                requiredCapacityDisplay.text(requiredCapacity);
                
                // Verifica finale del flag prima di chiamare loadRoomTypes
                const finalFlag = getExtraNightFlag();
                console.log('[BTR] 🔄 Chiamando loadRoomTypes con extra_night flag finale:', finalFlag);
                
                loadRoomTypes($('#btr_product_id').val(), numPeople, numAdults, numChildren, numInfants, numChildF1, numChildF2, numChildF3, numChildF4);
                
                console.log('[BTR] ✅ Camere ricaricate dopo cambio notte extra');
            }
        } else {
            console.log('[BTR] ℹ️ Nessuna ricarica necessaria - persone non ancora selezionate');
        }
    });

    /**
     * Listener per il checkbox notte extra (se presente)
     * Gestisce il caso del checkbox invece del dropdown
     * @since 1.0.20
     */
    $(document).on('change', '#btr_extra_night', function() {
        const isChecked = $(this).is(':checked');
        const value = isChecked ? '1' : '0';
        const text = isChecked ? 'Sì, aggiungi la notte extra' : 'No, solo pacchetto base';
        
        console.log('[BTR] 🌙 Checkbox notte extra cambiato:', {value, text, checked: isChecked});
        
        // Trigger lo stesso evento del dropdown per uniformità
        $(document).trigger('btr:extra-night-changed', [value, text]);
    });

    /**
     * Test periodico per verificare lo stato delle notti extra
     * Utile per debugging - rimuovere in produzione
     * @since 1.0.20
     */
    function debugExtraNightStatus() {
        const flag = getExtraNightFlag();
        const selectValue = $('#btr_add_extra_night').val();
        const checkboxChecked = $('#btr_extra_night').is(':checked');
        
        console.log('[BTR] 🔍 Debug notti extra:', {
            flag: flag,
            selectValue: selectValue,
            checkboxChecked: checkboxChecked,
            selectExists: $('#btr_add_extra_night').length > 0,
            checkboxExists: $('#btr_extra_night').length > 0
        });
    }
    
    /**
     * Test automatico per verificare la funzionalità notti extra
     * Esegue un test completo del flusso dopo 10 secondi
     */
    setTimeout(function() {
        window.testExtraNightFunctionality = function() {
            console.log('[BTR] 🧪 === TEST FUNZIONALITÀ NOTTI EXTRA ===');
            
            const tests = [];
            
            // Test 1: Verifica esistenza elementi
            const dropdown = document.getElementById('dropdown-trigger');
            const hiddenInput = document.getElementById('btr_add_extra_night');
            const display = document.querySelector('#dropdown-display');
            
            tests.push({
                name: 'Elementi dropdown esistono',
                result: dropdown && hiddenInput && display,
                details: { dropdown: !!dropdown, hiddenInput: !!hiddenInput, display: !!display }
            });
            
            // Test 2: Test funzione getExtraNightFlag
            const initialFlag = getExtraNightFlag();
            tests.push({
                name: 'getExtraNightFlag iniziale',
                result: initialFlag === 0 || initialFlag === 1,
                details: { flag: initialFlag }
            });
            
            // Test 3: Simula selezione "Sì"
            if (hiddenInput && display) {
                hiddenInput.value = '1';
                display.textContent = 'Sì, aggiungi la notte extra';
                
                const flagAfterSet = getExtraNightFlag();
                tests.push({
                    name: 'Selezione "Sì" simulated',
                    result: flagAfterSet === 1,
                    details: { flag: flagAfterSet, hiddenValue: hiddenInput.value }
                });
                
                // Reset per test successivo
                hiddenInput.value = '0';
                display.textContent = 'No, solo pacchetto base';
            }
            
            // Mostra risultati
            console.log('[BTR] 📊 RISULTATI TEST:');
            tests.forEach((test, i) => {
                const status = test.result ? '✅ PASS' : '❌ FAIL';
                console.log(`[BTR] ${i+1}. ${test.name}: ${status}`, test.details);
            });
            
            const allPassed = tests.every(test => test.result);
            console.log(`[BTR] 🎯 RISULTATO FINALE: ${allPassed ? '✅ TUTTI I TEST PASSATI' : '❌ ALCUNI TEST FALLITI'}`);
            
            return { allPassed, tests };
        };
        
        console.log('[BTR] 🧪 Test disponibile! Esegui: window.testExtraNightFunctionality()');
    }, 10000);

    // Debug ogni 5 secondi per monitorare la variabile globale
    setInterval(function() {
        const flag = getExtraNightFlag();
        const globalSelection = window.btrExtraNightSelection;
        const selectValue = $('#btr_add_extra_night').val();
        
        console.log('[BTR] 🔍 MONITOR notti extra:', {
            getExtraNightFlag: flag,
            selectValue: selectValue,
            globalSelection: globalSelection,
            timestamp: new Date().toLocaleTimeString()
        });
    }, 5000);

    // RIMOSSO: Secondo handler duplicato per #btr_date change - consolidato nel primo handler



    // Estendi la logica di caricamento camere anche al click su .btr-date-card
    form.on('click', '.btr-date-card,.dropdown-item', function () {
        // Controlla se la data è disabilitata
        if ($(this).hasClass('disabled') || $(this).data('disabled') === true || $(this).data('disabled') === 'true' || $(this).data('disabled') === '1') {
            if (typeof window.showNotification === 'function') {
                const errorMessage = $(this).data('message') || 'Questa data non è disponibile per la prenotazione.';
                window.showNotification(errorMessage, 'error');
            }
            // Nasconde le sezioni successive
            $('#btr-num-people-section, .custom-dropdown-wrapper').fadeOut(300);
            return;
        }

        const selectedValue = $(this).data('value');
        $('#btr_date').val(selectedValue).trigger('change');
        
        // NUOVA LOGICA: Verifica notti extra SOLO al click sulla data
        // Controlla immediatamente se ci sono notti extra disponibili per la data selezionata
        checkExtraNightAvailabilityOnDateClick();

        // Forza il caricamento delle camere se già presente numero persone
        const numPeople = parseInt($('#btr_num_people').val(), 10);
        const numAdults = parseInt(numAdultsField.val(), 10) || 0;
        const numInfants = parseInt(numInfantsField.val(), 10) || 0;
        const numChildF1 = parseInt(numChildF1Field.val(), 10) || 0;
        const numChildF2 = parseInt(numChildF2Field.val(), 10) || 0;
        const numChildF3  = parseInt(numChildF3Field.val(), 10)  || 0;
        const numChildF4  = parseInt(numChildF4Field.val(), 10)  || 0;
        const numChildren = numChildF1 + numChildF2 + numChildF3 + numChildF4;

        if (!isNaN(numPeople) && numPeople > 0) {
            requiredCapacity = numAdults + numChildF1 + numChildF2 + numChildF3 + numChildF4 + numInfants;
            requiredCapacityDisplay.text(requiredCapacity);
            selectedCapacity = 0;
            totalPrice = 0;

            roomTypesContainer.empty();
            $('#btr-check-people').removeClass('hide');
            //loadRoomTypes($('#btr_product_id').val(), numPeople, numAdults, numChildren, numInfants, numChildF1, numChildF2);
        }
    });





});
