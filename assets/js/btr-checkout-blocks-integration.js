/**
 * BTR Checkout Blocks Integration
 * 
 * Questo file gestisce l'integrazione del blocco BTR Checkout Summary
 * con i blocchi WooCommerce Checkout, permettendo il posizionamento
 * nella sidebar del totals.
 */

(function() {
    'use strict';
    
    // Funzione per registrare l'integrazione
    function registerBTRCheckoutIntegration() {
        console.log('[BTR] 🔄 Tentativo registrazione integrazione checkout blocks');
        console.log('[BTR] window.wc disponibile:', !!window.wc);
        console.log('[BTR] window.wc.blocksCheckout disponibile:', !!(window.wc && window.wc.blocksCheckout));
        
        // Verifica che le API WooCommerce Blocks siano disponibili
        if (!window.wc || !window.wc.blocksCheckout) {
            console.warn('[BTR] WooCommerce Blocks API non disponibile, riprovo...');
            // Riprova dopo un breve ritardo
            setTimeout(registerBTRCheckoutIntegration, 100);
            return;
        }

        const { registerCheckoutFilters } = window.wc.blocksCheckout;
        
        // Verifica anche wp.blocks
        if (window.wp && window.wp.blocks) {
            const { getBlockType } = window.wp.blocks;
            
            // Verifica se il nostro blocco è registrato
            const btrBlock = getBlockType('btr/checkout-summary');
            if (btrBlock) {
                console.log('[BTR] Blocco checkout summary trovato:', btrBlock);
            } else {
                console.warn('[BTR] Blocco checkout summary non ancora registrato');
            }
        }

        /**
         * Modifica i tipi di blocchi consentiti nelle aree inner block del checkout
         * 
         * @param {Array} defaultValue - Array dei blocchi già consentiti
         * @param {Object} extensions - Estensioni registrate
         * @param {Object} args - Argomenti contenenti info sul blocco corrente
         * @return {Array} Array modificato con il nostro blocco aggiunto
         */
        const modifyAdditionalInnerBlockTypes = (defaultValue, extensions, args) => {
            // Log per debug
            console.log('[BTR] Filtro chiamato per blocco:', args?.block, 'Blocchi attuali:', defaultValue);
            
            // Aggiungi il nostro blocco in tutte le aree del checkout per massima compatibilità
            const checkoutAreas = [
                'woocommerce/checkout-totals-block',
                'woocommerce/checkout-order-summary-block',
                'woocommerce/checkout',
                'woocommerce/cart-totals-block'
            ];
            
            if (checkoutAreas.includes(args?.block)) {
                // Aggiungi il nostro blocco se non è già presente
                if (!defaultValue.includes('btr/checkout-summary')) {
                    defaultValue.push('btr/checkout-summary');
                    console.log('[BTR] Blocco checkout summary aggiunto a:', args.block);
                }
            }
            
            return defaultValue;
        };

        // Registra il filtro con un namespace univoco
        try {
            registerCheckoutFilters('born-to-ride-booking', {
                additionalCartCheckoutInnerBlockTypes: modifyAdditionalInnerBlockTypes,
            });
            console.log('[BTR] ✅ Integrazione checkout blocks registrata con successo');
        } catch (error) {
            console.error('[BTR] ❌ Errore nella registrazione del filtro:', error);
            
            // Fallback per versioni precedenti di WooCommerce
            try {
                if (window.wc && window.wc.wcBlocksRegistry) {
                    console.log('[BTR] 🔄 Tentativo fallback con wcBlocksRegistry');
                    const { registerCheckoutFilters: legacyRegister } = window.wc.wcBlocksRegistry;
                    if (legacyRegister) {
                        legacyRegister('born-to-ride-booking', {
                            additionalCartCheckoutInnerBlockTypes: modifyAdditionalInnerBlockTypes,
                        });
                        console.log('[BTR] ✅ Fallback registrazione completata');
                    }
                }
            } catch (fallbackError) {
                console.error('[BTR] ❌ Anche il fallback è fallito:', fallbackError);
            }
        }
    }
    
    // Inizializza quando il DOM è pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', registerBTRCheckoutIntegration);
    } else {
        // DOM già caricato
        registerBTRCheckoutIntegration();
    }
    
    // Registra anche quando l'editor è pronto
    if (window.wp && window.wp.domReady) {
        window.wp.domReady(registerBTRCheckoutIntegration);
    }
})();