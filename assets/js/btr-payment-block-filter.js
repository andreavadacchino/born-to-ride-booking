/**
 * BTR Payment Block Filter - WooCommerce Inner Block Registration
 * Registra il nostro blocco custom come inner block consentito nel payment block
 * 
 * @version 1.0.240
 */

(function() {
    'use strict';

    function registerFilter() {
        // Verifica che le API WooCommerce siano disponibili
        const api = window.wc && (window.wc.blocksCheckout || window.wc.wcBlocksRegistry);
        if (!api) {
            // Riprova leggermente più tardi in editor
            setTimeout(registerFilter, 100);
            return;
        }

        const registerCheckoutFilters = (window.wc.blocksCheckout && window.wc.blocksCheckout.registerCheckoutFilters)
            || (window.wc.wcBlocksRegistry && window.wc.wcBlocksRegistry.registerCheckoutFilters);

        if (typeof registerCheckoutFilters !== 'function') {
            console.warn('BTR Payment Block Filter: API WooCommerce non disponibili');
            return;
        }

        /**
         * Aggiunge il blocco al set di inner blocks consentiti del Payment Block
         */
        const addBTRPaymentContextBlock = (defaultValue, extensions, args) => {
            const blockName = (args && (args.block || args.blockName)) || '';
            // Debug opzionale:
            // console.log('[BTR] innerBlockTypes filter for', blockName, defaultValue);

            if (blockName === 'woocommerce/checkout-payment-block') {
                if (Array.isArray(defaultValue) && !defaultValue.includes('born-to-ride/payment-context')) {
                    defaultValue.push('born-to-ride/payment-context');
                    // console.log('BTR Payment Block Filter: aggiunto born-to-ride/payment-context');
                }
            }
            return defaultValue;
        };

        try {
            registerCheckoutFilters('born-to-ride-booking', {
                additionalCartCheckoutInnerBlockTypes: addBTRPaymentContextBlock,
            });
            // console.log('BTR Payment Block Filter: registrato');
        } catch (error) {
            console.error('BTR Payment Block Filter: Errore nella registrazione del filtro:', error);
        }
    }

    // In editor, usa domReady per essere certi di agganciare i filtri per tempo
    if (window.wp && window.wp.domReady) {
        window.wp.domReady(registerFilter);
    } else {
        // Fallback
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', registerFilter);
        } else {
            registerFilter();
        }
    }
})();
