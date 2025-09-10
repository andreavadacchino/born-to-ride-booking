# Fix Completo Assicurazioni Store API - v1.0.105

## Problema
Le assicurazioni (€15) non venivano incluse nel totale del checkout WooCommerce Blocks (React). Il totale mostrava €614,30 invece di €644,30.

## Causa Principale
WooCommerce Blocks utilizza Store API REST che non rispetta automaticamente i prezzi custom impostati tramite `set_price()`. Servono hook specifici per l'API.

## Soluzione Implementata

### 1. Modifica esistente in `class-btr-checkout.php`
Gli hook erano già stati aggiunti ma mancava una parte dell'implementazione:
- `woocommerce_store_api_product_price` (priority 999)
- `woocommerce_store_api_cart_update_cart_from_request` (priority 999)

### 2. Nuova classe `class-btr-store-api-integration.php`
Creata per gestire l'integrazione completa con Store API:
- Registrazione dati custom per cart items
- Schema per proprietà custom (custom_price, item_type, etc.)
- Sincronizzazione prezzi durante aggiornamenti API
- Support per WooCommerce Blocks loaded

### 3. Aggiornamento `born-to-ride-booking.php`
- Aggiunto `require_once` per class-btr-store-api-integration.php
- Versione aggiornata a 1.0.105

## Come Funziona

1. **Prodotti Virtuali**: Le assicurazioni sono aggiunte come prodotti virtuali con `custom_price`
2. **Checkout Classico**: Hook `adjust_cart_item_prices` gestisce i prezzi
3. **Checkout Blocks**: 
   - Store API richiede prezzi via REST
   - `woocommerce_store_api_product_price` intercetta e restituisce custom_price
   - `woocommerce_store_api_cart_update_cart_from_request` mantiene sincronizzazione
   - ExtendSchema aggiunge proprietà custom all'API

## File Modificati
- `/born-to-ride-booking.php` (v1.0.105)
- `/includes/class-btr-checkout.php` (già modificato)
- `/includes/class-btr-store-api-integration.php` (nuovo)

## Script di Debug Creati
- `debug-store-api-insurance.php`
- `force-recalculate-cart-with-insurance.php`
- `analyze-all-price-hooks.php`

## Risultato Atteso
- Totale Camere: €584,30
- Supplementi extra: €30,00
- Assicurazioni: €15,00
- Costi extra: €15,00
- **TOTALE: €644,30** ✓

## Test
1. Visitare `/debug-store-api-insurance.php` per verificare integrazione
2. Controllare che BTR_Store_API_Integration sia caricata
3. Verificare totale checkout = €644,30
4. Confermare che assicurazioni mantengano prezzo custom