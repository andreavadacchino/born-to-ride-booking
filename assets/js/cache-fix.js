// FIX v1.0.226: CACHE MANAGER - ELIMINA DATI FANTASMA
jQuery(document).ready(function($) {
    console.log('[BTR v1.0.226] Cache Manager Attivo - Pulizia dati fantasma...');
    
    // Funzione pulizia cache corrotta
    function pulisciCacheCorretta() {
        const currentUrl = window.location.href;
        const preventivo_id = jQuery('#preventivo_id').val() || jQuery('[name="preventivo_id"]').val();
        const lastPreventivoId = localStorage.getItem('btr_last_preventivo_id');
        
        // Pulisci se: nuova prenotazione O cambio preventivo
        const shouldClean = currentUrl.includes('pacchetti') || 
                          currentUrl.includes('search') || 
                          currentUrl.includes('categoria-pacchetto') ||
                          currentUrl.includes('concludi-ordine') ||
                          (preventivo_id && preventivo_id !== lastPreventivoId);
        
        if (shouldClean || true) { // FORZA pulizia sempre per fix immediato
            console.log('[BTR CACHE FIX] === ELIMINAZIONE DATI FANTASMA ===');
            
            // Lista chiavi corrotte da eliminare
            const corruptedKeys = [
                'btr_checkout_data',
                'btr_state', 
                'btr_anagrafici_data',
                'btr_cliente_nome',
                'btr_cliente_email',
                'btr_emergency_state',
                'btr_performance_metrics'
            ];
            
            // Pulisci localStorage
            corruptedKeys.forEach(key => {
                if (localStorage.getItem(key)) {
                    console.log('[ELIMINATO] localStorage.' + key + ':', localStorage.getItem(key));
                    localStorage.removeItem(key);
                }
            });
            
            // Pulisci TUTTO ciò che contiene "btr"
            const allKeys = [];
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && (key.includes('btr') || key.includes('checkout') || key.includes('anagrafici'))) {
                    allKeys.push(key);
                }
            }
            allKeys.forEach(key => {
                console.log('[PURGE] Rimuovo:', key);
                localStorage.removeItem(key);
            });
            
            // Pulisci sessionStorage
            try {
                for (let i = sessionStorage.length - 1; i >= 0; i--) {
                    const key = sessionStorage.key(i);
                    if (key && (key.includes('btr') || key.includes('checkout'))) {
                        console.log('[PURGE] sessionStorage.' + key);
                        sessionStorage.removeItem(key);
                    }
                }
            } catch(e) {
                console.error('Errore pulizia session:', e);
            }
            
            // Salva nuovo preventivo
            if (preventivo_id) {
                localStorage.setItem('btr_last_preventivo_id', preventivo_id);
                console.log('[SALVATO] Nuovo preventivo_id:', preventivo_id);
            }
            
            console.log('[BTR CACHE FIX] ✅ Cache pulita! Dati fantasma ELIMINATI.');
            
            // Forza refresh dei dati dal server
            if (window.location.href.includes('concludi-ordine')) {
                console.log('[BTR] Ricarico dati puliti dal server...');
                // Trigger evento per ricaricare dati
                jQuery(document).trigger('btr:cache:cleaned');
            }
        }
    }
    
    // Esegui pulizia IMMEDIATAMENTE
    pulisciCacheCorretta();
    
    // Pulisci anche quando si clicca su elementi di navigazione
    jQuery(document).on('click', 'a[href*="pacchetti"], .new-booking-btn, .reset-btn', function() {
        console.log('[BTR] Click su nuovo booking - pulizia cache');
        pulisciCacheCorretta();
    });
    
    // Pulisci quando si lascia la pagina checkout
    if (window.location.href.includes('concludi-ordine')) {
        window.addEventListener('beforeunload', function() {
            localStorage.removeItem('btr_checkout_data');
            localStorage.removeItem('btr_anagrafici_data');
            console.log('[BTR] Uscita da checkout - cache pulita');
        });
    }
});
