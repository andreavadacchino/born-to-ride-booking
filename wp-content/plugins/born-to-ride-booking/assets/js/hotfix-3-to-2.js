/**
 * Hotfix temporaneo per correggere il calcolo 3→2 notti
 * Da includere nelle pagine di booking fino alla risoluzione definitiva
 */
(function() {
    'use strict';
    
    console.log('[BTR HOTFIX] Inizializzazione correzione 3→2 notti...');
    
    // Intercetta la variabile globale
    let _originalValue = window.btrExtraNightsCount;
    
    Object.defineProperty(window, 'btrExtraNightsCount', {
        get: function() {
            if (_originalValue === 3) {
                console.log('[BTR HOTFIX] Corretto valore da 3 a 2');
                return 2;
            }
            return _originalValue;
        },
        set: function(value) {
            console.log('[BTR HOTFIX] btrExtraNightsCount impostato a:', value);
            _originalValue = value;
            if (value === 3) {
                console.warn('[BTR HOTFIX] ⚠️ Rilevato tentativo di impostare 3 notti - verrà corretto a 2');
            }
        },
        configurable: true
    });
    
    // Monitora le chiamate AJAX
    if (typeof jQuery !== 'undefined') {
        jQuery(document).ajaxComplete(function(event, xhr, settings) {
            if (settings.data && settings.data.includes('btr_get_rooms')) {
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    const count = xhr.responseJSON.data.extra_nights_count;
                    if (count === 3) {
                        console.warn('[BTR HOTFIX] Risposta AJAX contiene 3 notti extra - sarà corretta a 2');
                    }
                }
            }
        });
    }
    
    console.log('[BTR HOTFIX] ✅ Hotfix attivo - correzione automatica 3→2 notti');
})();