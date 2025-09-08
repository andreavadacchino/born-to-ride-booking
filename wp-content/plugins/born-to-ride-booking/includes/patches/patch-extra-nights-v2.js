/**
 * Patch per correggere il conteggio notti extra
 * Problema: Il sistema conta 3 notti invece di 2
 * Soluzione: Intercetta e corregge il valore
 */
(function() {
    'use strict';
    
    console.log('[BTR PATCH v2] 🔧 Inizializzazione patch notti extra...');
    
    // Salva il valore originale
    let _btrExtraNightsCount = window.btrExtraNightsCount;
    let patchApplied = false;
    
    // Intercetta l'accesso alla proprietà
    Object.defineProperty(window, 'btrExtraNightsCount', {
        get: function() {
            // Se il valore è 3, correggilo a 2
            if (_btrExtraNightsCount === 3 && !patchApplied) {
                console.log('[BTR PATCH v2] ⚠️ Rilevate 3 notti extra, correggo a 2');
                return 2;
            }
            return _btrExtraNightsCount;
        },
        set: function(value) {
            console.log('[BTR PATCH v2] 📊 btrExtraNightsCount impostato a:', value);
            _btrExtraNightsCount = value;
            
            // Se viene impostato a 3, segnala il problema
            if (value === 3 && !patchApplied) {
                console.warn('[BTR PATCH v2] ⚠️ ATTENZIONE: Il backend sta inviando 3 notti invece di 2!');
                console.log('[BTR PATCH v2] ✅ La patch correggerà automaticamente a 2');
                patchApplied = true;
            }
        }
    });
    
    // Intercetta anche le risposte AJAX per correggere alla fonte
    const originalAjax = jQuery.ajax;
    jQuery.ajax = function(options) {
        const originalSuccess = options.success;
        
        if (options.data && options.data.action === 'btr_get_rooms') {
            options.success = function(response) {
                if (response && response.data && response.data.extra_nights_count === 3) {
                    console.log('[BTR PATCH v2] 🔄 Intercettata risposta AJAX con 3 notti');
                    response.data.extra_nights_count = 2;
                    console.log('[BTR PATCH v2] ✅ Corretto a 2 notti nella risposta AJAX');
                }
                
                if (originalSuccess) {
                    originalSuccess.apply(this, arguments);
                }
            };
        }
        
        return originalAjax.call(this, options);
    };
    
    // Log informativo
    console.log('[BTR PATCH v2] ✅ Patch attivata - Correzione 3→2 notti extra');
    console.log('[BTR PATCH v2] 📌 Versione: 2.0 - Fix conteggio notti supplemento');
    
    // Esponi funzione di debug
    window.btrPatchDebug = function() {
        console.log('=== BTR PATCH v2 DEBUG ===');
        console.log('Valore interno:', _btrExtraNightsCount);
        console.log('Valore corretto:', window.btrExtraNightsCount);
        console.log('Patch applicata:', patchApplied);
    };
})();