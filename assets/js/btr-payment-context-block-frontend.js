/**
 * BTR Payment Context Block - Frontend JavaScript
 * Script frontend per il blocco contesto pagamento
 * 
 * @version 1.0.240
 */

(function() {
    'use strict';

    // Verifica che siamo nel checkout
    if (!document.body.classList.contains('woocommerce-checkout')) {
        return;
    }

    /**
     * Inizializzazione blocco frontend
     */
    function initPaymentContextBlock() {
        const blocks = document.querySelectorAll('.btr-checkout-payment-context-block');
        
        if (blocks.length === 0) {
            console.log('BTR Payment Context Block: Nessun blocco trovato nel DOM');
            return;
        }

        console.log('BTR Payment Context Block: Inizializzati ' + blocks.length + ' blocchi');

        // Aggiungi animazioni di entrata
        blocks.forEach(function(block, index) {
            // Delay per animazione progressiva se ci sono più blocchi
            setTimeout(function() {
                block.style.opacity = '0';
                block.style.transform = 'translateY(20px)';
                block.style.transition = 'all 0.3s ease-out';
                
                // Trigger animazione
                requestAnimationFrame(function() {
                    block.style.opacity = '1';
                    block.style.transform = 'translateY(0)';
                });
            }, index * 100);
        });

        // Aggiungi listener per aggiornamenti carrello (se necessario)
        document.body.addEventListener('update_checkout', function() {
            console.log('BTR Payment Context Block: Checkout aggiornato');
            // Potremmo aggiornare il contenuto del blocco qui se necessario
        });
    }

    // Inizializza quando il DOM è pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPaymentContextBlock);
    } else {
        initPaymentContextBlock();
    }

    // Re-inizializza dopo aggiornamenti AJAX del checkout
    document.body.addEventListener('updated_checkout', initPaymentContextBlock);

    console.log('BTR Payment Context Block: Frontend script caricato');

})();