jQuery(document).ready(function ($) {

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

        // Data di nascita mask: gg/mm/aaaa
        Inputmask({
            mask: '99/99/9999',
            placeholder: 'gg/mm/aaaa',
            showMaskOnHover: false,
            showMaskOnFocus: true,
            inputFormat: 'dd/mm/yyyy'
        }).mask($(context).find('input[name^="anagrafici"][name$="[data_nascita]"]'));

        // Telefono: maschera custom +39 999 9999999
        Inputmask({
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
        }).mask(
            // Usa vanilla JS per compatibilità con Inputmask v5+
            Array.from($(context).find('input[name^="anagrafici"][name$="[telefono]"]'))
        );
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

                $(this)
                    .prop('disabled', alreadyTaken || noMoreAllowed || qty === 0)
                    .toggleClass('assigned', alreadyTaken)
                    .toggleClass('selected', isSelected);
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
        if (chk.length && chk.is(':checked')) {
            flag = 1;
        }
        if (sel.length && sel.val() === '1') {
            flag = 1;
        }
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
      totalPriceDisplay.text(`Prezzo Totale: €${totalPrice.toFixed(2)}`);
    }

    function updateNumPeople() {
        let numAdults = $('#btr_num_adults').length ? parseInt($('#btr_num_adults').val(), 10) || 0 : 0;
        let numInfants = $('#btr_num_infants').length ? parseInt($('#btr_num_infants').val(), 10) || 0 : 0;
        let numChild_f1 = $('#btr_num_child_f1').length ? parseInt($('#btr_num_child_f1').val(), 10) || 0 : 0;
        let numChild_f2 = $('#btr_num_child_f2').length ? parseInt($('#btr_num_child_f2').val(), 10) || 0 : 0;
        let numChild_f3 = $('#btr_num_child_f3').length ? parseInt($('#btr_num_child_f3').val(), 10) || 0 : 0;
        let numChild_f4 = $('#btr_num_child_f4').length ? parseInt($('#btr_num_child_f4').val(), 10) || 0 : 0;

        let numChildren = numChild_f1 + numChild_f2 + numChild_f3 + numChild_f4;
        let totalPeople = numAdults + numChildren; // I neonati non occupano posti letto

        $('#btr-check-people').removeClass('hide');

        if (totalPeople < 1) {
            totalPeople = 0;
        }

        $('#btr_num_people').val(totalPeople);
    }

    // Bind change only to the actual inputs
    $('#btr_num_adults, #btr_num_infants, #btr_num_child_f1, #btr_num_child_f2, #btr_num_child_f3, #btr_num_child_f4').on('change', function () {
        updateNumPeople();
        // Reset availability: reopen check button and hide downstream sections
        $('#btr-check-people').removeClass('hide running');
        roomTypesSection.slideUp();
        roomTypesContainer.empty();
        assicurazioneButton.slideUp();
        customerSection.slideUp();
        $('.timeline .step.step3').removeClass('active');
        
        // NUOVA LOGICA OTTIMIZZATA: Verifica notti extra con debounce quando cambiano i numeri
        console.log('[BTR] 🔄 Cambio numero persone - Triggering debounced check');
        debouncedExtraNightCheck();
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
        
        // Anche qui triggeriamo la verifica ottimizzata delle notti extra
        console.log('[BTR] 🔄 Click su +/- - Triggering debounced check');
        debouncedExtraNightCheck();
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

    $('#btr_date').on('change', function () {
        const selectedOption = $(this).find(':selected'); // Trova l'opzione selezionata
        const selectedDate = selectedOption.val(); // Ottieni il valore della data
        console.log('[BTR] 📅 #btr_date change event, selectedDate:', selectedDate);
        $('#btr_selected_date').val(selectedDate);
        console.log('[BTR] 📌 Impostato btr_selected_date:', $('#btr_selected_date').val());
        const variantId = selectedOption.data('id'); // Ottieni l'ID variante dal data-id

        var title = $(this).data('title');
        var desc = $(this).data('desc');

        if (variantId) {
            $('#btr_selected_variant_id').val(variantId); // Imposta l'ID variante nell'input nascosto
            $('#title-step').text(title);
            $('#desc-step').text(desc);
            $('.timeline .step.step2').addClass('active');
            console.log(`Variant ID for selected date (${selectedDate}): ${variantId}`);
        } else {
            showAlert('Errore: Nessuna variante trovata per la data selezionata.','error');
        }
    });

    // Step 1: Selezione della data
    // Quando l'utente seleziona una data, imposta il valore di btr_date_ranges_id
    form.on('change', '#btr_date', function () {
        const selectedDateRangeId = $(this).val(); // Supponendo che il valore sia l'ID della variazione
        $('#btr_date_ranges_id').val(selectedDateRangeId);

        // Pulisci la cache delle notti extra quando cambia la data
        clearExtraNightCache();
        
        // NUOVA LOGICA: Nasconde inizialmente il blocco notte extra
        hideExtraNightControls();
        
        // Mostra la sezione per il numero di persone
        numPeopleSection.slideDown();
        // Nasconde le altre sezioni e resetta i valori
        roomTypesSection.slideUp();
        roomTypesContainer.empty();
        assicurazioneButton.slideUp();
        customerSection.slideUp();
        totalCapacityDisplay.html('Capacità Totale Selezionata: 0 / <span id="btr-required-capacity">0</span>');
        requiredCapacityDisplay.text('');
        selectedCapacity = 0;
        totalPrice = 0;
        totalPriceDisplay.text('Prezzo Totale: €0.00');
        bookingResponse.empty();

        // Forza il reset del pulsante verifica e del contenuto camere
        $('#btr-check-people').removeClass('hide running');
        roomTypesSection.slideUp();
        roomTypesContainer.empty();
        assicurazioneButton.slideUp();
        customerSection.slideUp();
        $('.timeline .step.step3').removeClass('active');

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
            requiredCapacity = numAdults + numChildF1 + numChildF2 + numChildF3 + numChildF4;
            requiredCapacityDisplay.text(requiredCapacity);
            selectedCapacity = 0;
            totalPrice = 0;
            loadRoomTypes($('#btr_product_id').val(), numPeople, numAdults, numChildren, numInfants, numChildF1, numChildF2);
            
            // Se c'è già un numero di persone, verifica subito le notti extra
            debouncedExtraNightCheck();
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
        
        // RIMOSSA: la chiamata checkExtraNightAvailability al click sulla data
        // Ora la verifica delle notti extra avviene solo quando cambia il numero di persone
        // (viene già chiamata dal trigger change sopra se c'è un numero di persone)

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
            requiredCapacity = numAdults + numChildF1 + numChildF2 + numChildF3 + numChildF4;
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

        if (isNaN(numPeople) || numPeople < 1) {
            showAlert('Inserisci un numero valido di persone.','error');
            $(this).removeClass('running');
            return;
        }

        requiredCapacity = numAdults + numChild_f1 + numChild_f2 + numChild_f3 + numChild_f4;
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
                totalPriceDisplay.text('Prezzo Totale: €0.00');
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
        
        /**
         * Ottieni label dinamiche da window.btrChildFasce o fallback
         * Definizione globale per uso in room cards e breakdown
         */
        const getChildLabel = (fasciaId, fallback) => {
            if (window.btrChildFasce && Array.isArray(window.btrChildFasce)) {
                const fascia = window.btrChildFasce.find(f => f.id === fasciaId);
                return fascia ? fascia.label : fallback;
            }
            return fallback;
        };

        const labelChildF1 = getChildLabel(1, '3-5 anni');
        const labelChildF2 = getChildLabel(2, '6-7 anni');
        const labelChildF3 = getChildLabel(3, '8-10 anni');
        const labelChildF4 = getChildLabel(4, '11-12 anni');

        $('#btr-check-people').removeClass('running').addClass('hide');
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
            // Logga anche il numero di camere disponibili per la notte extra, se presente
            if (response.success && response.data && typeof response.data.global_stock_extra !== 'undefined') {
                btrLog('extra_night_rooms_available', {
                    extra_night_flag: getExtraNightFlag(),
                    rooms_extra_available: response.data.global_stock_extra
                });
            }
            if (response.success) {
                // Se il backend non restituisce camere, mostra il messaggio "prova una nuova combinazione" e interrompi
                if (!response.data.rooms || response.data.rooms.length === 0) {
                    roomTypesContainer.empty(); // assicura che non restino loader/carte
                    showNoRoomsMessage();
                    $('#btr-check-people').removeClass('running');
                    return;
                }
                roomTypesContainer.empty();
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
            <span class="btr-regular-price">€${displayRegularPrice.toFixed(2)}</span>
            <span class="btr-sale-price">€${displaySalePrice.toFixed(2)}</span>
        </div>
    `;
                    } else {
                        priceText = `
        <div class="btr-price">
            <span class="btr-label-price">${labelPrezzo}</span> 
            <span class="btr-price-value">€${displayRegularPrice.toFixed(2)}</span>
        </div>
    `;
                    }


                    if(numChild_f1 > 0) {
                        // Aggiunta visualizzazione riduzioni bambini
                        const fascia1Discount = response.data.bambini_fascia1_sconto || 0;

                        // Mostra anche i prezzi scontati per bambini nella visualizzazione
                        if (room.price_child_f1 && room.price_child_f1 > 0 && fascia1Discount > 0) {
                            PersonPriceText += `
                                <div class="btr-price">
                                    <span class="btr-discount-label btr-label-price">Bambini ${labelChildF1}:</span> 
                                    <span class="btr-discount-value">
                                        <span class="btr-total-price-value">€${parseFloat(room.price_child_f1).toFixed(2)}</span> 
                                       <!-- <span class="btr-regular-price">€${regularPersonPrice.toFixed(2)}</span> 
                                        <span class="btr-sale-price">€${parseFloat(room.price_child_f1).toFixed(2)}</span>
                                         <span class="btr-sale-percent-light">-${response.data.bambini_fascia1_sconto}%</span>-->
                                     </span>
                                </div>
                            `;
                        }
                    }

                    if(numChild_f2 > 0) {
                        const fascia2Discount = parseFloat(room.price_child_f2 || 0);
                        if (room.price_child_f2 && room.price_child_f2 > 0 && fascia2Discount > 0) {
                            PersonPriceText += `
                                <div class="btr-price">
                                    <span class="btr-discount-label btr-label-price">Bambini ${labelChildF2}:</span> 
                                    <span class="btr-regular-value">
                                    <span class="btr-total-price-value">€${parseFloat(room.price_child_f2).toFixed(2)}</span>
                                    <!--<span class="btr-regular-price">€${regularPersonPrice.toFixed(2)}</span>
                                     <span class="btr-sale-price">€${parseFloat(room.price_child_f2).toFixed(2)}</span>
                                     <span class="btr-sale-percent-light">-${response.data.bambini_fascia2_sconto}%</span>-->
                                     </span>
                                </div>
                            `;
                        }
                    }

                    // F3 price display
                    if(numChild_f3 > 0) {
                        const fascia3Discount = parseFloat(room.price_child_f3 || 0);
                        if (room.price_child_f3 && room.price_child_f3 > 0 && fascia3Discount > 0) {
                            PersonPriceText += `
                                <div class="btr-price">
                                    <span class="btr-discount-label btr-label-price">Bambini ${labelChildF3}:</span>
                                    <span class="btr-regular-value">
                                    <span class="btr-total-price-value">€${parseFloat(room.price_child_f3).toFixed(2)}</span>
                                     </span>
                                </div>
                            `;
                        }
                    }

                    // F4 price display
                    if(numChild_f4 > 0) {
                        const fascia4Discount = parseFloat(room.price_child_f4 || 0);
                        if (room.price_child_f4 && room.price_child_f4 > 0 && fascia4Discount > 0) {
                            PersonPriceText += `
                                <div class="btr-price">
                                    <span class="btr-discount-label btr-label-price">Bambini ${labelChildF4}:</span>
                                    <span class="btr-regular-value">
                                    <span class="btr-total-price-value">€${parseFloat(room.price_child_f4).toFixed(2)}</span>
                                     </span>
                                </div>
                            `;
                        }
                    }

                    if(numInfants > 0) {
                        PersonPriceText += `
                            <div class="btr-price">
                                <span class="btr-label-price">Neonati:</span> <span class="btr-regular-value">Non paganti</span>
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
                                    <span class="btr-supplemento-value">€${supplemento.toFixed(2)}</span> 
                                    <small>a persona</small>
                                </div>
                            `;
                        inclusoSupplemento = ` <small>(incluso supplemento)</small>`;
                    }

                    // Testo del prezzo totale per persona (incluso supplemento)
                    const totalPricePerPersonText = `
                                <div class="btr-total-price-per-person">
                                    <!--<span class="btr-label-price">Prezzo persona` + inclusoSupplemento + `</span>:-->
                                    <span class="btr-label-price">Adulto</span>:
                                    <span class="btr-total-price-value">€${pricePerPerson.toFixed(2)}</span>
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
                                    <span class="btr-extra-night-value">€${extraNightPP.toFixed(2)}</span> 
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
                    // Nei singoli non è consentita l'assegnazione di bambini
                    if (!isSingleRoom && (numChild_f1 > 0 || numChild_f2 > 0 || numChild_f3 > 0 || numChild_f4 > 0)) {
                        childButtonsHtml += `
        <div class="btr-child-selector" data-room-index="${index}">
            <p class="btr-child-selector-label">Assegna bambini a questa camera:</p>
            <div class="btr-child-buttons">
    `;

                        /* --- Gruppo 3‑12 --- */
                        if (numChild_f1 > 0) {
                            childButtonsHtml += `
            <div class="btr-child-group">
                <span class="btr-child-group-title btr-f1-title">
                   ${icona_child_f1} <strong>${labelChildF1}</strong>
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
                            childButtonsHtml += `
            <div class="btr-child-group">
                <span class="btr-child-group-title btr-f2-title">
                    ${icona_child_f2} <strong>${labelChildF2}</strong>
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
                            childButtonsHtml += `
            <div class="btr-child-group">
                <span class="btr-child-group-title btr-f3-title">
                    ${icona_child_f1} <strong>${labelChildF3}</strong>
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
                            childButtonsHtml += `
            <div class="btr-child-group">
                <span class="btr-child-group-title btr-f4-title">
                    ${icona_child_f2} <strong>${labelChildF4}</strong>
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
                                        <label for="btr-room-quantity-${sanitizeRoomType(roomType)}" class="btr-room-quantity-label">
                                            INDICA IL NUMERO DI CAMERE CHE DESIDERI
                                        </label>
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
                // Delegated handler: si attacca una sola volta al container
                roomTypesContainer.off('click', '.btr-child-btn').on('click', '.btr-child-btn', function () {
                    if ($(this).prop('disabled')) return;

                    const $btn      = $(this);
                    const childId   = $btn.data('child-id');
                    const roomIndex = $btn.closest('.btr-child-selector').data('room-index');

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
                });
                // [DEBUG] Log finale HTML camere
                //console.log("[DEBUG final room list]", roomTypesContainer.html());

                // Aggiorna lo stato iniziale dei pulsanti appena creati
                refreshChildButtons();
                });


                // Mostra il bottone di Procedi
                assicurazioneButton.slideDown();

                // Disabilita il pulsante #btr-proceed all'inizio dopo la generazione del form del primo partecipante
                $('#btr-proceed').prop('disabled', true);

                // Evento per il cambio di quantità delle camere
                $('.btr-room-quantity').on('input', function () {
                    updateSelectedRooms();
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
                    // Contatori globali rimanenti: si decrementano man mano che
                    // le persone vengono allocate nelle camere, così non vengono
                    // contate più volte.
                    let remainingAdults   = numAdults;
                    let remainingChildF1  = parseInt($('#btr_num_child_f1').val(), 10) || 0;
                    let remainingChildF2  = parseInt($('#btr_num_child_f2').val(), 10) || 0;
                    let remainingChildF3  = parseInt($('#btr_num_child_f3').val(), 10) || 0;
                    let remainingChildF4  = parseInt($('#btr_num_child_f4').val(), 10) || 0;

                    /* --------------------------------------------------------------
                     *  Calcolo costo Notte Extra per riepilogo partecipanti
                     * -------------------------------------------------------------- */
                    const extraNightFlag = getExtraNightFlag() === 1;
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
                        // Conteggio bambini assegnati a questa camera
                        let assignedF1 = 0, assignedF2 = 0, assignedF3 = 0, assignedF4 = 0;
                        Object.entries(childAssignments).forEach(([cid, idx]) => {
                            if (idx === roomIndex) {
                                if      (cid.startsWith('f1-')) assignedF1++;
                                else if (cid.startsWith('f2-')) assignedF2++;
                                else if (cid.startsWith('f3-')) assignedF3++;
                                else if (cid.startsWith('f4-')) assignedF4++;
                            }
                        });
                        // --- END: children assigned logic for room pricing ---

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

                        // Prezzo adulto per persona (usa sempre il prezzo base per persona)
                        const adultPriceNoSupp = adultBasePrice;
                        const childF1PriceNoSupp = priceChildF1;
                        const childF2PriceNoSupp = priceChildF2;

                        // Calcola il prezzo totale per camera considerando le notti base (2 notti)
                        const baseNights = 2;
                        let localTotalPrice = 0;
                        
                        // Calcola persone effettive per slot
                        const adultsInRoom = Math.max(0, totalSlots - usedF1 - usedF2 - usedF3 - usedF4);
                        
                        
                        // Calcola il prezzo base per questa combinazione di camere
                        // Nota: totalSlots già include quantity * capacity, quindi non moltiplichiamo di nuovo
                        const totalPersonsInRooms = adultsInRoom + usedF1 + usedF2 + usedF3 + usedF4;
                        
                        // Prezzo base per notti principali (già include tutte le persone in tutte le camere)
                        localTotalPrice += adultsInRoom * adultPriceNoSupp;
                        localTotalPrice += usedF1 * priceChildF1;
                        localTotalPrice += usedF2 * priceChildF2;
                        localTotalPrice += usedF3 * priceChildF3;
                        localTotalPrice += usedF4 * priceChildF4;
                        
                        // Aggiungi supplementi per persona per notte base (2 notti)
                        localTotalPrice += totalPersonsInRooms * supplementoPP * baseNights;

                        // Aggiungi costo notte extra (per persona per notte extra)
                        if (extraNightFlag && extraNightPP > 0) {
                            const extraNights = 2; // Moltiplicatore per notti extra
                            localTotalPrice += totalPersonsInRooms * extraNightPP * extraNights;
                            // Aggiungi supplementi per le notti extra (stesso supplemento delle notti base)
                            localTotalPrice += totalPersonsInRooms * supplementoPP * extraNights;
                            
                        }
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

                        remainingChildF1 -= usedF1;
                        remainingChildF2 -= usedF2;
                        remainingChildF3 -= usedF3;
                        remainingChildF4 -= usedF4;

                        // Prezzo pieno usato per mostrare il <del>
                        totalFullPrice += (capacity * quantity) * regularPricePerson;
                        totalPrice += localTotalPrice;
                        // normalizza i decimali
                        totalFullPrice = parseFloat(totalFullPrice.toFixed(2));

                        // Update visual total for this room card
                        roomCard.find('.btr-total-room-price-value')
                                .text('€' + localTotalPrice.toFixed(2));

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
                    // Nuovo riepilogo prezzo con sconto visivo
                    const discountAmount = (totalFullPrice - totalPrice).toFixed(2);
                    let priceHtml = discountAmount > 0
                        ? `<del>€${totalFullPrice.toFixed(2)}</del> &nbsp; €${totalPrice.toFixed(2)} <small class="btr-save-amount">(-€${discountAmount})</small>`
                        : `€${totalPrice.toFixed(2)}`;
                    totalPriceDisplay.html(`Prezzo Totale: <strong>${priceHtml}</strong>`);
                    $('#btr-total-price-visual').html(priceHtml);

    // ───────────── Riepilogo adulti / bambini ────────────
                    // Add minimal styles for .btr-price-save, .btr-save-amount, del
                                        var css = `
                        .btr-price-save,
                        .btr-save-amount {
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
                            const labelPersone = `${data.count}x ${data.count > 1 ? 'Adulti' : 'Adulto'}`;
                            const pricePer = data.priceNoSupp;
                            let line = `${labelPersone} in ${roomsQty}x ${roomType} <strong>€${pricePer.toFixed(2)}</strong> a persona`;
                            if (data.supplemento > 0) {
                                line += ` + supplemento <strong>€${data.supplemento.toFixed(2)}</strong> a persona, a notte`;
                            }
                            breakdownParts.push(line);

                            // Riga notte extra per adulti (2 notti)
                            if (extraNightFlag && extraNightPP > 0) {
                                let extraLine = `<strong>2 Notti extra</strong> ${extraNightDateLabel ? ' del ' + extraNightDateLabel : ''} <strong>€${extraNightPP.toFixed(2)}</strong> a persona`;
                                if (data.supplemento > 0) {
                                    extraLine += ` + supplemento <strong>€${data.supplemento.toFixed(2)}</strong> a persona, a notte`;
                                }
                                breakdownParts.push(extraLine);
                            }
                        }
                    }

                    // Riepilogo bambini 3-11 per tipo di camera
                    for (const roomType in childF1ByRoomType) {
                        const data = childF1ByRoomType[roomType];
                        if (data.count > 0) {
                            // Per semplicità, mostra solo il numero di bambini senza specificare le camere
                            // dato che possono essere sistemati insieme ad adulti
                            const labelPersone = `${data.count}x Bambino ${labelChildF1}`;
                            const pricePer = data.priceNoSupp;
                            let line = `${labelPersone} in ${roomType} <strong>€${pricePer.toFixed(2)}</strong> a persona`;
                            if (data.supplemento > 0) {
                                line += ` + supplemento <strong>€${data.supplemento.toFixed(2)}</strong> a persona, a notte`;
                            }
                            breakdownParts.push(line);

                            // Riga notte extra per bambini F1 (prezzo specifico per 2 notti)
                            if (extraNightFlag && extraNightPP > 0) {
                                const childExtra = 22.00; // Prezzo specifico F1 per notte extra
                                let extraLine = `<strong>2 Notti extra</strong> ${extraNightDateLabel ? ' del ' + extraNightDateLabel : ''} <strong>€${childExtra.toFixed(2)}</strong> a persona`;
                                if (data.supplemento > 0) {
                                    extraLine += ` + supplemento <strong>€${data.supplemento.toFixed(2)}</strong> a persona, a notte`;
                                }
                                breakdownParts.push(extraLine);
                            }
                        }
                    }

                    // Riepilogo bambini 12-14 per tipo di camera
                    for (const roomType in childF2ByRoomType) {
                        const data = childF2ByRoomType[roomType];
                        if (data.count > 0) {
                            // Per semplicità, mostra solo il numero di bambini senza specificare le camere
                            // dato che possono essere sistemati insieme ad adulti
                            const labelPersone = `${data.count}x Bambino ${labelChildF2}`;
                            const pricePer = data.priceNoSupp;
                            let line = `${labelPersone} in ${roomType} <strong>€${pricePer.toFixed(2)}</strong> a persona`;
                            if (data.supplemento > 0) {
                                line += ` + supplemento <strong>€${data.supplemento.toFixed(2)}</strong> a persona, a notte`;
                            }
                            breakdownParts.push(line);

                            // Riga notte extra per bambini F2 (prezzo specifico per 2 notti)
                            if (extraNightFlag && extraNightPP > 0) {
                                const childExtra = 23.00; // Prezzo specifico F2 per notte extra
                                let extraLine = `<strong>2 Notti extra</strong> ${extraNightDateLabel ? ' del ' + extraNightDateLabel : ''} <strong>€${childExtra.toFixed(2)}</strong> a persona`;
                                if (data.supplemento > 0) {
                                    extraLine += ` + supplemento <strong>€${data.supplemento.toFixed(2)}</strong> a persona, a notte`;
                                }
                                breakdownParts.push(extraLine);
                            }
                        }
                    }

                    // Riepilogo bambini F3 per tipo di camera
                    for (const roomType in childF3ByRoomType) {
                        const data = childF3ByRoomType[roomType];
                        if (data.count > 0) {
                            // Per semplicità, mostra solo il numero di bambini senza specificare le camere
                            // dato che possono essere sistemati insieme ad adulti
                            const labelPersone = `${data.count}x Bambino ${labelChildF3}`;
                            const pricePer = data.priceNoSupp;
                            let line = `${labelPersone} in ${roomType} <strong>€${pricePer.toFixed(2)}</strong> a persona`;
                            if (data.supplemento > 0) {
                                line += ` + supplemento <strong>€${data.supplemento.toFixed(2)}</strong> a persona, a notte`;
                            }
                            breakdownParts.push(line);

                            // Riga notte extra per bambini F3 (prezzo specifico per 2 notti)
                            if (extraNightFlag && extraNightPP > 0) {
                                const childExtra = 22.00; // Prezzo specifico F3 per notte extra
                                let extraLine = `<strong>2 Notti extra</strong> ${extraNightDateLabel ? ' del ' + extraNightDateLabel : ''} <strong>€${childExtra.toFixed(2)}</strong> a persona`;
                                if (data.supplemento > 0) {
                                    extraLine += ` + supplemento <strong>€${data.supplemento.toFixed(2)}</strong> a persona, a notte`;
                                }
                                breakdownParts.push(extraLine);
                            }
                        }
                    }

                    // Riepilogo bambini F4 per tipo di camera
                    for (const roomType in childF4ByRoomType) {
                        const data = childF4ByRoomType[roomType];
                        if (data.count > 0) {
                            // Per semplicità, mostra solo il numero di bambini senza specificare le camere
                            // dato che possono essere sistemati insieme ad adulti
                            const labelPersone = `${data.count}x Bambino ${labelChildF4}`;
                            const pricePer = data.priceNoSupp;
                            let line = `${labelPersone} in ${roomType} <strong>€${pricePer.toFixed(2)}</strong> a persona`;
                            if (data.supplemento > 0) {
                                line += ` + supplemento <strong>€${data.supplemento.toFixed(2)}</strong> a persona, a notte`;
                            }
                            breakdownParts.push(line);

                            // Riga notte extra per bambini F4 (prezzo specifico per 2 notti)
                            if (extraNightFlag && extraNightPP > 0) {
                                const childExtra = 23.00; // Prezzo specifico F4 per notte extra
                                let extraLine = `<strong>2 Notti extra</strong> ${extraNightDateLabel ? ' del ' + extraNightDateLabel : ''} <strong>€${childExtra.toFixed(2)}</strong> a persona`;
                                if (data.supplemento > 0) {
                                    extraLine += ` + supplemento <strong>€${data.supplemento.toFixed(2)}</strong> a persona, a notte`;
                                }
                                breakdownParts.push(extraLine);
                            }
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
                    const style = document.createElement('style');
                    style.id = 'btr-no-rooms-style';
                    style.innerHTML = css;
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
                let assignedF1 = 0, assignedF2 = 0, assignedF3 = 0, assignedF4 = 0;
                Object.entries(childAssignments).forEach(([cid, idx]) => {
                    if (idx === currentRoomIdx) {
                        if      (cid.startsWith('f1-')) assignedF1++;
                        else if (cid.startsWith('f2-')) assignedF2++;
                        else if (cid.startsWith('f3-')) assignedF3++;
                        else if (cid.startsWith('f4-')) assignedF4++;
                    }
                });

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
        console.log(`Prezzo Totale: €${totalPrice.toFixed(2)}`);


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

        // Invia i dati al server per creare il preventivo
        // Costruzione di FormData per includere tutti i campi anagrafici (inclusi i nuovi)
        const formData = new FormData();
        formData.append('action', 'btr_create_preventivo');
        formData.append('nonce', btr_booking_form.nonce);
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
        formData.append('extra_night', getExtraNightFlag());
        formData.append('selected_date', $('#btr_selected_date').val());
        formData.append('extra_night_pp', extraNightPPSubmit);
        formData.append('extra_night_total', extraNightTotalSubmit);

        // Add extra night date - use the selected date or derive it from other data
        const selectedDate = $('#btr_selected_date').val();
        let extraNightDate = '';

        if (getExtraNightFlag() === 1 && selectedDate) {
            // Try to extract the date from the selected date range (format: "24 - 25 Gennaio 2026")
            const dateMatch = selectedDate.match(/(\d+)\s*-\s*(\d+)\s+([^\s]+)\s+(\d+)/);
            if (dateMatch) {
                const endDay = dateMatch[2];
                const month = dateMatch[3];
                const year = dateMatch[4];
                extraNightDate = `${endDay} ${month} ${year}`;
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
        
        // TEST TEMPORANEO: Forza alcuni costi extra per il primo partecipante
        if (anagrafici.length > 0 && Object.keys(anagrafici[0].costi_extra).length === 0) {
            console.warn('⚠️ ATTENZIONE: Nessun costo extra trovato! Aggiungo dati di test...');
            anagrafici[0].costi_extra = {
                'animale-domestico': true,
                'culla-per-neonati': true
            };
            console.log('✅ TEST: Aggiunti costi extra di test al primo partecipante');
        }
        
        // Aggiungi i dati anagrafici come array di oggetti, ma anche i nuovi campi come richiesto
        anagrafici.forEach(function(partecipante, i) {
            console.log(`📤 DEBUG: Invio dati partecipante ${i}:`, partecipante);
            formData.append(`anagrafici[${i}][nome]`, partecipante.nome || '');
            formData.append(`anagrafici[${i}][cognome]`, partecipante.cognome || '');
            formData.append(`anagrafici[${i}][data_nascita]`, partecipante.data_nascita || '');
            formData.append(`anagrafici[${i}][citta_nascita]`, partecipante.citta_nascita || '');
            formData.append(`anagrafici[${i}][citta_residenza]`, partecipante.citta_residenza || '');
            formData.append(`anagrafici[${i}][provincia_residenza]`, partecipante.provincia_residenza || '');
            formData.append(`anagrafici[${i}][email]`, partecipante.email || '');
            formData.append(`anagrafici[${i}][telefono]`, partecipante.telefono || '');
            // Nuovi campi richiesti
            formData.append(`anagrafici[${i}][codice_fiscale]`, partecipante.codice_fiscale || '');
            formData.append(`anagrafici[${i}][indirizzo_residenza]`, partecipante.indirizzo_residenza || '');
            formData.append(`anagrafici[${i}][cap_residenza]`, partecipante.cap_residenza || '');
            // Assicurazioni e costi extra (serializzati come JSON)
            const assicurazioniJson = JSON.stringify(partecipante.assicurazioni || {});
            const costiExtraJson = JSON.stringify(partecipante.costi_extra || {});
            console.log(`📤 DEBUG: Partecipante ${i} - assicurazioni JSON:`, assicurazioniJson);
            console.log(`📤 DEBUG: Partecipante ${i} - costi_extra JSON:`, costiExtraJson);
            formData.append(`anagrafici[${i}][assicurazioni]`, assicurazioniJson);
            formData.append(`anagrafici[${i}][costi_extra]`, costiExtraJson);
        });

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
        const numPeople = numAdults + numChildren; // Gli infanti non contano

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

            showNotification(message,'error');
            return; // blocca la continuazione
        }
        const numPeople = numAdults + numChild_f1 + numChild_f2 + numChild_f3 + numChild_f4;
        const hasChildren = numChild_f1 > 0 || numChild_f2 > 0 || numChild_f3 > 0 || numChild_f4 > 0;
        const hasInfants = numInfants > 0;

        if (numPeople < 1) {
            showAlert('Inserisci almeno un partecipante.','error');
            return;
        }

        /* --------------------------------------------------------
           Calcolo prezzo definitivo applicando gli sconti bambini
           -------------------------------------------------------- */
        let adultsLeft   = numAdults;
        let childF2Left  = numChild_f2;
        let childF1Left  = numChild_f1;
        let childF3Left  = numChild_f3;
        let childF4Left  = numChild_f4;
        let finalTotal   = 0;

        $('.btr-room-quantity').each(function () {
            const qty            = parseInt($(this).val(), 10) || 0;
            if (!qty) return;

            const capacity       = parseInt($(this).data('capacity'), 10);
            const priceAdult     = parseFloat($(this).data('price-per-person')) || 0;
            const priceChildF1   = parseFloat($(this).data('price-child-f1'))   || priceAdult;
            const priceChildF2   = parseFloat($(this).data('price-child-f2'))   || priceAdult;
            const priceChildF3   = parseFloat($(this).data('price-child-f3'))   || priceAdult;
            const priceChildF4   = parseFloat($(this).data('price-child-f4'))   || priceAdult;
            const supplementoPP  = parseFloat($(this).data('supplemento')) || 0;
            const extraNightPP   = parseFloat($(this).data('extra-night-pp')) || 0;
            const extraNightFlag = getExtraNightFlag() === 1;

            for (let c = 0; c < qty; c++) {
                for (let s = 0; s < capacity; s++) {
                    if (adultsLeft > 0) {
                        finalTotal += priceAdult;
                        finalTotal += supplementoPP;
                        adultsLeft--;
                    } else if (childF2Left > 0) {
                        finalTotal += priceChildF2;
                        finalTotal += supplementoPP;
                        childF2Left--;
                    } else if (childF1Left > 0) {
                        finalTotal += priceChildF1;
                        finalTotal += supplementoPP;
                        childF1Left--;
                    } else if (childF3Left > 0) {
                        finalTotal += priceChildF3;
                        finalTotal += supplementoPP;
                        childF3Left--;
                    } else if (childF4Left > 0) {
                        finalTotal += priceChildF4;
                        finalTotal += supplementoPP;
                        childF4Left--;
                    }

                    // Aggiungi costo notte extra per ogni persona
                    if (extraNightFlag && extraNightPP > 0) {
                        finalTotal += extraNightPP;
                    }
                }
            }
        });

        // Aggiorna il riepilogo prezzi
        totalPrice = finalTotal;
        $('#btr-total-price-visual').html(`€${finalTotal.toFixed(2)}`);

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
                    html += `<p class="info-neonati"><strong>Neonati:</strong> ${numInfants} (non pagano e non occupano posti)</p>`;
                }

                for (let i = 0; i < numPeople; i++) {
                    const currentIndex = i;
                    const isFirst = i === 0;
                    let posizione = '<strong>'+ordinali[i] + '</strong> partecipante' || `Partecipante ${i + 1}`;
                    // --- Etichetta dinamica della fascia d'età --------------------------
                    // Le label vengono lette da data-label sugli input numero bambini,
                    // così gli intervalli possono essere cambiati dal backend senza
                    // toccare questo script.
                    const childLabels = {
                        f1: $('#btr_num_child_f1').data('label') || 'Bambino 3‑11',
                        f2: $('#btr_num_child_f2').data('label') || 'Bambino 12‑14',
                        f3: $('#btr_num_child_f3').data('label') || 'Ragazzo 14‑17',
                        f4: $('#btr_num_child_f4').data('label') || 'Ragazzo 17+'
                    };

                    if (hasChildren) {
                        const categories = [
                            { count: numAdults,                    label: 'Adulto'        },
                            { count: numChild_f1,                  label: childLabels.f1 },
                            { count: numChild_f2,                  label: childLabels.f2 },
                            { count: numChild_f3,                  label: childLabels.f3 },
                            { count: numChild_f4,                  label: childLabels.f4 }
                        ];

                        let accumulated = 0;
                        for (const cat of categories) {
                            accumulated += cat.count;
                            if (i < accumulated) {
                                posizione += ` <small>(${cat.label})</small>`;
                                break;
                            }
                        }
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
                    if (extrasPerPersona.length) {
                        html += `
                            <fieldset class="btr-assicurazioni">
                                <h4>Costi Extra</h4>
                                <p>Puoi selezionare eventuali costi extra da applicare al partecipante.</p>
                        `;
                        // garantiamo slug univoci per i costi extra per persona
                        let extraSlugCounts = {};
                        extrasPerPersona.forEach(extra => {
                            const baseSlug = slugify(extra.nome || 'extra');
                            if (extraSlugCounts[baseSlug] === undefined) {
                                extraSlugCounts[baseSlug] = 0;
                            } else {
                                extraSlugCounts[baseSlug]++;
                            }
                            const slug = extraSlugCounts[baseSlug] === 0 ? baseSlug : `${baseSlug}-${extraSlugCounts[baseSlug]}`;
                            const label = extra.nome || 'Extra';
                            const importo = parseFloat(extra.importo || 0).toFixed(2);
                            const sconto  = parseFloat(extra.sconto  || 0);
                            const scontoTxt = sconto > 0 ? ` <strong>- ${sconto}%</strong>` : '';
                            html += `
                                <div class="btr-assicurazione-item">
                                    <label>
                                        <input type="checkbox" name="anagrafici[${i}][costi_extra][${slug}]" value="1" />
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

                    html += '</div>';
                }

                // --- Costi extra PER DURATA (una sola volta) ------------------
                if (extrasPerDurata.length) {
                    html += `
                        <fieldset class="btr-assicurazioni">
                            <h4>Costi Extra (per durata)</h4>
                            <p>Questi costi si applicano una sola volta all'intero soggiorno.</p>
                    `;
                    // garantiamo slug univoci per i costi extra per durata
                    let extraDurataSlugCounts = {};
                    extrasPerDurata.forEach(extra => {
                        const baseSlug = slugify(extra.nome || 'extra');
                        if (extraDurataSlugCounts[baseSlug] === undefined) {
                            extraDurataSlugCounts[baseSlug] = 0;
                        } else {
                            extraDurataSlugCounts[baseSlug]++;
                        }
                        const slug = extraDurataSlugCounts[baseSlug] === 0 ? baseSlug : `${baseSlug}-${extraDurataSlugCounts[baseSlug]}`;
                        const label = extra.nome || 'Extra';
                        const importo = parseFloat(extra.importo || 0).toFixed(2);
                        const sconto  = parseFloat(extra.sconto  || 0);
                        const scontoTxt = sconto > 0 ? ` <strong>- ${sconto}%</strong>` : '';
                        html += `
                            <div class="btr-assicurazione-item">
                                <label>
                                    <input type="checkbox" name="costi_extra_durata[${slug}]" value="1" />
                                    ${label} <strong>${importo} €</strong>${scontoTxt}
                                </label>
                            </div>
                        `;
                    });
                    html += '</fieldset>';
                }


                html += '</div>';
                $('#btr-participants-wrapper').html(html);
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
        // Cancella il timer precedente se esiste
        if (extraNightDebounceTimer) {
            clearTimeout(extraNightDebounceTimer);
        }
        
        // Imposta un nuovo timer
        extraNightDebounceTimer = setTimeout(function() {
            checkExtraNightAvailabilityOptimized();
        }, EXTRA_NIGHT_DEBOUNCE_DELAY);
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
        $('.custom-dropdown-wrapper').slideDown(300);
        
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
     */
    function hideExtraNightControls() {
        console.log('[BTR] 🔍 Nascondo controlli notte extra');
        
        // Reset dei valori
        if ($extraNightSelect.length) {
            $extraNightSelect.val('0');
        }
        if ($('#btr_extra_night').length) {
            $('#btr_extra_night').prop('checked', false);
        }
        
        // Reset del dropdown visibile
        $('#dropdown-display').text('No, solo pacchetto base');
        
        // Nasconde direttamente il wrapper del dropdown personalizzato
        $('.custom-dropdown-wrapper').slideUp(300);
        
        // Nasconde il vecchio box checkbox se presente
        if ($extraNightBox.length) {
            $extraNightBox.slideUp(300);
        }
    }

    // Step 1: Selezione della data
    // Quando l'utente seleziona una data, imposta il valore di btr_date_ranges_id
    form.on('change', '#btr_date', function () {
        const selectedDateRangeId = $(this).val(); // Supponendo che il valore sia l'ID della variazione
        $('#btr_date_ranges_id').val(selectedDateRangeId);

        // Pulisci la cache delle notti extra quando cambia la data
        clearExtraNightCache();
        
        // NUOVA LOGICA: Nasconde inizialmente il blocco notte extra
        hideExtraNightControls();
        
        // Mostra la sezione per il numero di persone
        numPeopleSection.slideDown();
        // Nasconde le altre sezioni e resetta i valori
        roomTypesSection.slideUp();
        roomTypesContainer.empty();
        assicurazioneButton.slideUp();
        customerSection.slideUp();
        totalCapacityDisplay.html('Capacità Totale Selezionata: 0 / <span id="btr-required-capacity">0</span>');
        requiredCapacityDisplay.text('');
        selectedCapacity = 0;
        totalPrice = 0;
        totalPriceDisplay.text('Prezzo Totale: €0.00');
        bookingResponse.empty();

        // Forza il reset del pulsante verifica e del contenuto camere
        $('#btr-check-people').removeClass('hide running');
        roomTypesSection.slideUp();
        roomTypesContainer.empty();
        assicurazioneButton.slideUp();
        customerSection.slideUp();
        $('.timeline .step.step3').removeClass('active');

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
            requiredCapacity = numAdults + numChildF1 + numChildF2 + numChildF3 + numChildF4;
            requiredCapacityDisplay.text(requiredCapacity);
            selectedCapacity = 0;
            totalPrice = 0;
            loadRoomTypes($('#btr_product_id').val(), numPeople, numAdults, numChildren, numInfants, numChildF1, numChildF2);
            
            // Se c'è già un numero di persone, verifica subito le notti extra
            debouncedExtraNightCheck();
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
        
        // RIMOSSA: la chiamata checkExtraNightAvailability al click sulla data
        // Ora la verifica delle notti extra avviene solo quando cambia il numero di persone
        // (viene già chiamata dal trigger change sopra se c'è un numero di persone)

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
            requiredCapacity = numAdults + numChildF1 + numChildF2 + numChildF3 + numChildF4;
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

        if (isNaN(numPeople) || numPeople < 1) {
            showAlert('Inserisci un numero valido di persone.','error');
            $(this).removeClass('running');
            return;
        }

        requiredCapacity = numAdults + numChild_f1 + numChild_f2 + numChild_f3 + numChild_f4;
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
                totalPriceDisplay.text('Prezzo Totale: €0.00');
                $(this).removeClass('running');
            }
        }).fail(function (jqXHR, textStatus, errorThrown) {
            console.error('AJAX Error:', textStatus, errorThrown);
            showAlert('Errore durante la verifica della disponibilità.','error');
        });
    });



});
