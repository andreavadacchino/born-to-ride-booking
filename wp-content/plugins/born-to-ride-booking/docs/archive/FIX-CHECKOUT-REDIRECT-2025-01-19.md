# Fix Redirect Checkout - 19 Gennaio 2025

## Problema
Dopo aver cliccato "Vai al Checkout" dal form anagrafici, l'utente non viene reindirizzato correttamente alla pagina "Concludi l'ordine" di WooCommerce.

## Errori Identificati

### 1. Errore Campo Checkout
```
La funzione woocommerce_register_additional_checkout_field è stata richiamata in maniera scorretta. 
A checkout field cannot be registered without an id.
```

**Causa**: Mancava il campo `id` nella registrazione del campo checkout.

**Soluzione Implementata**: 
- File: `includes/class-btr-preventivi-ordini.php`
- Linea 49: Aggiunto `'id' => 'preventivo_id'`

### 2. Possibili Cause del Redirect Non Funzionante

1. **Pagina Checkout non configurata**: WooCommerce potrebbe non avere una pagina checkout configurata
2. **Problemi di permalink**: I permalink potrebbero necessitare di essere rigenerati
3. **Plugin di cache**: Potrebbero interferire con il redirect
4. **Sessione WooCommerce**: Potrebbe non essere inizializzata correttamente

## Soluzioni Implementate

### 1. Fix Campo Checkout (COMPLETATO)
```php
woocommerce_register_additional_checkout_field([
    'id'      => 'preventivo_id',  // AGGIUNTO
    'name'    => 'preventivo_id',
    'type'    => 'hidden',
    'group'   => 'order',
    'default' => $default,
]);
```

### 2. Debug e Fallback URL (COMPLETATO)
Aggiunto logging e fallback nel metodo `convert_to_checkout()`:
```php
// Debug: verifica URL checkout
error_log("[BTR DEBUG] URL Checkout WooCommerce: " . $redirect_url);

// Verifica se l'URL è valido, altrimenti usa fallback
if (empty($redirect_url) || $redirect_url === home_url('/')) {
    $checkout_page_id = wc_get_page_id('checkout');
    if ($checkout_page_id > 0) {
        $redirect_url = get_permalink($checkout_page_id);
    } else {
        $redirect_url = home_url('/checkout/');
    }
}
```

### 3. Backup Dati in Transient (COMPLETATO)
Aggiunto salvataggio temporaneo dei dati come backup:
```php
set_transient('btr_temp_preventivo_' . $preventivo_id, [
    'preventivo_id' => $preventivo_id,
    'cart_contents' => WC()->cart->get_cart(),
], 3600);
```

## Come Verificare e Risolvere

### 1. Esegui il Test di Configurazione
Vai a: `/wp-content/plugins/born-to-ride-booking/tests/test-checkout-configuration.php`

Questo test verifica:
- Se WooCommerce è attivo
- Se le pagine sono configurate correttamente
- L'URL del checkout
- Lo stato della sessione
- Il contenuto del carrello

### 2. Verifica Impostazioni WooCommerce
1. Vai in **WooCommerce → Impostazioni → Avanzate**
2. Verifica che sia selezionata una pagina per "Pagina di checkout"
3. Se non c'è, creane una nuova con shortcode `[woocommerce_checkout]`

### 3. Rigenera Permalink
1. Vai in **Impostazioni → Permalink**
2. Clicca su "Salva modifiche" (anche senza cambiare nulla)

### 4. Controlla il Debug Log
Dopo aver cliccato "Vai al Checkout", controlla `/wp-content/debug.log` per:
```
[BTR DEBUG] URL Checkout WooCommerce: ...
[BTR DEBUG] Checkout page ID: ...
[BTR DEBUG] Fallback URL usato: ...
```

### 5. Svuota Cache
- Svuota la cache del browser
- Se usi plugin di caching (W3 Total Cache, WP Super Cache, ecc.), svuotali

## Test del Flusso Completo

1. Vai al riepilogo preventivo
2. Clicca "Procedi con la prenotazione"
3. Compila il form anagrafici
4. Clicca "Vai al Checkout"
5. Dovresti essere reindirizzato a `/checkout/` o alla pagina configurata

## Se il Problema Persiste

1. **Verifica i log** per vedere quale URL viene utilizzato
2. **Controlla manualmente** se l'URL del checkout funziona (es. vai direttamente a `/checkout/`)
3. **Verifica conflitti** con altri plugin disattivandoli temporaneamente
4. **Crea una nuova pagina checkout** se quella esistente ha problemi

## File Modificati
- `includes/class-btr-preventivi-ordini.php` - Fix campo checkout e debug redirect
- `tests/test-checkout-configuration.php` - Script di test per verificare la configurazione