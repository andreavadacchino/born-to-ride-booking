# Riepilogo Fix Sconti WooCommerce

## Problema Identificato
Il totale WooCommerce non corrispondeva al custom summary perché gli sconti (valori negativi come "No Skipass -€35,00") non venivano applicati correttamente al carrello.

## Soluzione Implementata

### 1. Sistema di Fees Persistenti
Abbiamo implementato un sistema che salva gli sconti come fees negative in sessione WooCommerce, garantendo che vengano applicate in modo persistente.

### 2. File Modificati
- `/includes/class-btr-preventivi-ordini.php`: Aggiunto sistema completo di gestione fees con hook multipli
- Corretto il metodo BTR_Price_Calculator da `calcola_totali_preventivo()` a `calculate_preventivo_total()`

### 3. Test Creati
- `/tests/ripristina-preventivo-sessione.php`: Recupera preventivo_id dal carrello se mancante
- `/tests/fix-sconti-chiari.php`: Applica sconti come fees separate  
- `/tests/debug-sconti-separati.php`: Analisi dettagliata del flusso sconti
- `/tests/test-completo-sconti.php`: Test completo con tutte le fasi
- `/tests/verifica-sessione-preventivo.php`: Verifica stato sessione e preventivo

## Come Usare i Fix

### Procedura Completa:

1. **Se il preventivo_id non è in sessione:**
   ```
   /wp-content/plugins/born-to-ride-booking/tests/ripristina-preventivo-sessione.php
   ```
   Clicca "Ripristina Preventivo in Sessione"

2. **Per applicare gli sconti come fees separate:**
   ```
   /wp-content/plugins/born-to-ride-booking/tests/fix-sconti-chiari.php
   ```
   Questo script:
   - Pulisce vecchie fees
   - Crea fees separate per ogni sconto (es. "No Skipass -35€")
   - Salva in sessione
   - Forza ricalcolo carrello

3. **Per test completo con analisi:**
   ```
   /wp-content/plugins/born-to-ride-booking/tests/test-completo-sconti.php
   ```
   Questo script fa tutto automaticamente:
   - Verifica/ripristina sessione
   - Analizza dati preventivo
   - Calcola totali con BTR_Price_Calculator
   - Applica fees se necessario
   - Verifica risultato

## Risultato Atteso

Dopo l'applicazione del fix:
- Gli sconti appaiono come voci separate nel carrello (es. "No Skipass -35€")
- Le assicurazioni rimangono come prodotti separati (+15€)
- Il totale WooCommerce corrisponde esattamente al custom summary (€574,30)
- Maggiore trasparenza per l'utente finale

## Note Tecniche

### Hook WooCommerce Utilizzati:
- `woocommerce_cart_calculate_fees` (priorità 999)
- `woocommerce_before_calculate_totals` (priorità 5)
- `woocommerce_after_calculate_totals` (priorità 999)
- `woocommerce_store_api_cart_update_cart_from_request`
- `woocommerce_store_api_cart_update_order_from_request`

### Persistenza Dati:
- Fees salvate in `WC()->session->set('btr_cart_fees', $fees)`
- Preventivo ID in `WC()->session->set('_preventivo_id', $id)`

## Troubleshooting

Se il totale non si aggiorna:
1. Verifica che il preventivo_id sia in sessione
2. Controlla che ci siano costi extra negativi nei dati anagrafici
3. Usa il pulsante "Forza Ricalcolo" nel test
4. Svuota e ricrea il carrello se necessario