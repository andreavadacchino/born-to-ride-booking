# Fix Errore AJAX 500 - Pagina Riepilogo

## Problema
Quando si clicca su "Continua" dalla pagina riepilogo, la chiamata AJAX restituiva un errore 500:
```
POST http://localhost:10018/wp-admin/admin-ajax.php 500 (Internal Server Error)
```

## Causa
L'errore era causato da un problema di tipo nel metodo `get_payment_data()`:
```
Uncaught Error: Unsupported operand types: string * float
```

Il valore `$totale` recuperato da `get_post_meta()` era una stringa, ma veniva utilizzato in un'operazione matematica senza conversione.

## Soluzione Applicata

### 1. Conversione del totale in float
```php
// Converti il totale in numero
$totale = floatval($totale);
```

### 2. Aggiunta verifica esistenza preventivo
```php
// Verifica che il preventivo esista
$preventivo = get_post($preventivo_id);
if (!$preventivo || $preventivo->post_type !== 'preventivi') {
    wp_send_json_error(['message' => 'Preventivo non trovato']);
    return;
}
```

### 3. Miglioramento gestione errori
- Aggiunto try-catch per catturare eventuali eccezioni
- Aggiunto logging per debug
- Controllo esistenza WooCommerce session prima dell'uso

## File Modificato
`/includes/class-btr-shortcode-anagrafici.php` - metodo `get_payment_data()`

## Test
Dopo le modifiche, la chiamata AJAX dovrebbe funzionare correttamente e restituire i dati del preventivo in formato JSON.

## Note
- Il nonce utilizzato è `btr_update_anagrafici_nonce`
- La funzione richiede che WooCommerce sia attivo per gestire la sessione
- I dati del preventivo devono esistere nei post meta