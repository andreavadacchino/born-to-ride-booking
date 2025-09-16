# Fix Assicurazioni nel Checkout WooCommerce Blocks - 2025-01-25

## Problema
Le assicurazioni non venivano incluse nel totale del carrello/checkout. Il totale mostrava €614,30 invece di €644,30 (mancavano €15 di assicurazioni).

## Causa
WooCommerce Blocks (il nuovo checkout React) utilizza l'API Store REST invece dei tradizionali hook PHP. I prezzi custom impostati solo tramite `set_price()` non venivano propagati correttamente all'API Store.

## Soluzione Implementata

### 1. Modifica in `class-btr-checkout.php`

#### Hook aggiunti (righe 98-101):
```php
// CORREZIONE 2025-01-25: Supporto per WooCommerce Blocks (React checkout)
// Assicura che i prezzi custom siano gestiti correttamente nell'API Store
add_filter( 'woocommerce_store_api_product_price', [ $this, 'handle_store_api_product_price' ], 999, 3 );
add_action( 'woocommerce_store_api_cart_update_cart_from_request', [ $this, 'ensure_custom_prices_in_store_api' ], 999, 2 );
```

#### Funzioni aggiunte (righe 1044-1083):

**`handle_store_api_product_price()`**
- Intercetta le richieste di prezzo dall'API Store
- Verifica se il prodotto nel carrello ha un `custom_price`
- Restituisce il prezzo custom invece del prezzo standard

**`ensure_custom_prices_in_store_api()`**
- Si attiva quando l'API Store aggiorna il carrello
- Riapplica tutti i prezzi custom ai prodotti nel carrello
- Garantisce che i prezzi custom persistano durante le operazioni API

### 2. Flusso di funzionamento

1. **Aggiunta assicurazioni**: La funzione `add_detailed_cart_items()` in `class-btr-preventivi-ordini.php` aggiunge le assicurazioni come prodotti virtuali con `custom_price`

2. **Checkout classico**: L'hook `adjust_cart_item_prices()` gestisce i prezzi custom

3. **Checkout Blocks (React)**: 
   - L'API Store richiede i prezzi tramite REST
   - Il filtro `woocommerce_store_api_product_price` intercetta e restituisce il prezzo custom
   - L'action `woocommerce_store_api_cart_update_cart_from_request` mantiene i prezzi sincronizzati

## Risultato
Ora il totale del checkout include correttamente:
- Totale Camere: €584,30
- Supplementi extra: €30,00
- Assicurazioni: €15,00
- Costi extra: €15,00
- **TOTALE: €644,30** ✓

## Note tecniche
- La soluzione è retrocompatibile con il checkout classico
- Supporta sia prezzi positivi che negativi (sconti)
- Include logging per debug quando `WP_DEBUG` è attivo
- Priorità 999 per garantire l'esecuzione dopo altri plugin

## File modificati
- `/wp-content/plugins/born-to-ride-booking/includes/class-btr-checkout.php`

## Script di debug creati
- `debug-cart-insurance-issue.php` - Analisi generale del carrello
- `debug-assicurazioni-totale.php` - Debug specifico per assicurazioni
- `fix-woocommerce-blocks-insurance.php` - Test della soluzione