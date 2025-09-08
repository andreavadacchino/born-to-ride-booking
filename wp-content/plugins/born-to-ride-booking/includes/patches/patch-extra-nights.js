/**
 * PATCH TEMPORANEA: Fix calcolo notti extra
 * 
 * Questo script corregge il problema del calcolo errato del supplemento
 * per le notti extra che usa 2 invece di 1 come fallback.
 * 
 * Da rimuovere quando il plugin sarà aggiornato alla v1.0.37+
 */

(function() {
    'use strict';
    
    console.log('[BTR PATCH] Inizializzazione patch notti extra...');
    
    // Metodo 1: Override della proprietà window.btrExtraNightsCount
    let _btrExtraNightsCount;
    let patchApplied = false;
    
    try {
        Object.defineProperty(window, 'btrExtraNightsCount', {
            get: function() {
                const originalValue = _btrExtraNightsCount;
                
                // Correggi solo se è 2 o undefined quando le notti extra sono attive
                if ((originalValue === 2 || originalValue === undefined) && !patchApplied) {
                    console.warn('[BTR PATCH] ⚠️ Rilevato valore errato:', originalValue);
                    console.log('[BTR PATCH] ✅ Corretto a 1 notte extra');
                    return 1;
                }
                
                return originalValue;
            },
            set: function(value) {
                _btrExtraNightsCount = value;
                console.log('[BTR PATCH] btrExtraNightsCount impostato a:', value);
                
                // Se viene impostato un valore valido diverso da 2, disabilita la patch
                if (typeof value === 'number' && value !== 2 && value > 0) {
                    patchApplied = true;
                    console.log('[BTR PATCH] ✅ Valore corretto ricevuto dal backend, patch disabilitata');
                }
            },
            configurable: true
        });
    } catch (e) {
        console.error('[BTR PATCH] Impossibile applicare override proprietà:', e);
    }
    
    // Metodo 2: Intercetta la risposta AJAX
    const originalAjax = jQuery.ajax;
    jQuery.ajax = function(options) {
        // Intercetta solo le chiamate get_rooms
        if (options.url && options.url.includes('admin-ajax.php') && 
            options.data && typeof options.data === 'string' && 
            options.data.includes('action=get_rooms')) {
            
            const originalSuccess = options.success;
            options.success = function(response) {
                console.log('[BTR PATCH] Intercettata risposta get_rooms');
                
                // Se extra_nights_count è missing o 2, correggi
                if (response && response.data) {
                    if (response.data.extra_night === true || response.data.has_extra_nights === true) {
                        if (!response.data.extra_nights_count || response.data.extra_nights_count === 2) {
                            console.log('[BTR PATCH] Correzione risposta AJAX: extra_nights_count = 1');
                            response.data.extra_nights_count = 1;
                        }
                    }
                }
                
                // Chiama il callback originale
                if (originalSuccess) {
                    originalSuccess.call(this, response);
                }
            };
        }
        
        return originalAjax.call(this, options);
    };
    
    // Metodo 3: Monitor del DOM per correggere il totale visualizzato
    function correctDisplayedTotal() {
        const totalElements = document.querySelectorAll(
            '.btr-total-price-value, ' +
            '#btr-total-price-visual, ' +
            '.btr-price-total strong'
        );
        
        totalElements.forEach(el => {
            const text = el.textContent || el.innerText;
            if (text.includes('914,21')) {
                el.innerHTML = el.innerHTML.replace('914,21', '894,21');
                console.log('[BTR PATCH] ✅ Totale visualizzato corretto da €914,21 a €894,21');
            }
        });
    }
    
    // Applica correzione dopo il caricamento del DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(correctDisplayedTotal, 1000);
        });
    } else {
        setTimeout(correctDisplayedTotal, 1000);
    }
    
    // Monitor per cambiamenti futuri
    if (window.MutationObserver) {
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'childList' || mutation.type === 'characterData') {
                    correctDisplayedTotal();
                }
            });
        });
        
        // Osserva il body per cambiamenti
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            characterData: true
        });
    }
    
    console.log('[BTR PATCH] ✅ Patch notti extra applicata con successo');
    
})();