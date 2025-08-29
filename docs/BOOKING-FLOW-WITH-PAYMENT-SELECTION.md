# Flusso di Prenotazione con Selezione Modalità di Pagamento

## Overview
Questo documento descrive il flusso completo di prenotazione dal form anagrafici al checkout finale, includendo la pagina intermedia di selezione modalità di pagamento.

## Architettura del Flusso

### 1. Form Anagrafici (`btr-form-anagrafici.php`)
L'utente compila i dati dei partecipanti e clicca "Vai al Checkout".

**Dettagli tecnici:**
- Il form ha due modalità di submit:
  - **AJAX submit**: Quando c'è `remaining_time` (pulsante "Salva e Completa")
  - **Form submit normale**: Quando non c'è `remaining_time` (pulsante "Vai al Checkout")
- L'action del form è `btr_convert_to_checkout`
- Include un nonce per sicurezza: `btr_convert_to_checkout_nonce`

### 2. Gestione Backend (`class-btr-preventivi-ordini.php`)
La classe `BTR_Preventivo_To_Order` gestisce la conversione del preventivo in ordine.

**Metodo `convert_to_checkout()`:**
```php
// 1. Verifica nonce e preventivo ID
// 2. Aggiorna stato preventivo a 'convertito'
// 3. Inizializza sessione WooCommerce
// 4. Svuota carrello
// 5. Salva dati anagrafici nel preventivo
// 6. Ricalcola totali (assicurazioni, costi extra)
// 7. Aggiunge prodotti al carrello
// 8. DECISIONE CRITICA: Verifica se payment plans sono abilitati
```

### 3. Logica di Redirect
Il sistema decide dove reindirizzare l'utente basandosi su:

```php
if (get_option('btr_enable_payment_plans', true)) {
    // Verifica se esiste già un piano di pagamento
    $existing_plan = BTR_Payment_Plans::get_payment_plan($preventivo_id);
    
    if (!$existing_plan && $totale > 0) {
        // Redirect alla pagina di selezione pagamento
        $payment_selection_page_id = get_option('btr_payment_selection_page_id');
        
        if ($payment_selection_page_id) {
            $redirect_url = add_query_arg([
                'preventivo_id' => $preventivo_id
            ], get_permalink($payment_selection_page_id));
        } else {
            // Fallback: cerca per slug
            $payment_page = get_page_by_path('selezione-piano-pagamento');
        }
    }
} else {
    // Redirect diretto al checkout
    $redirect_url = wc_get_checkout_url();
}
```

### 4. Pagina Selezione Pagamento (`payment-selection-page.php`)
Se il sistema è attivo, l'utente viene reindirizzato qui per scegliere la modalità.

**Opzioni disponibili:**
1. **Pagamento Individuale** - Ogni partecipante paga la propria quota
2. **Pagamento di Gruppo** - Selezione di chi paga e per quante quote
3. **Acconto + Saldo** - Pagamento dilazionato con percentuale configurabile

**Shortcode:** `[btr_payment_selection]`

### 5. Gestione Selezione (`class-btr-payment-selection-shortcode.php`)
Gestisce la selezione dell'utente e crea il piano di pagamento.

**AJAX Handler `handle_create_payment_plan()`:**
- Verifica nonce e dati
- Crea il piano di pagamento tramite `BTR_Payment_Plans`
- Per pagamento di gruppo: genera link individuali tramite `BTR_Group_Payments`
- Redirect finale: checkout o pagina riepilogo link

## Configurazione del Sistema

### Opzioni WordPress
```php
// Abilitazione sistema payment plans
'btr_enable_payment_plans' => true/false (default: true)

// ID della pagina di selezione
'btr_payment_selection_page_id' => int

// Opzioni bonifico bancario
'btr_enable_bank_transfer_plans' => true/false
'btr_bank_transfer_info' => string

// Percentuale acconto default
'btr_default_deposit_percentage' => int (default: 30)

// Auto-invio email per pagamenti di gruppo
'btr_auto_send_payment_links' => 'yes'/'no' (default: 'yes')
```

### Attivazione del Sistema
Per attivare il sistema di selezione pagamento:

1. **Metodo Automatico**: Usa lo script `activate-payment-plans-system.php`
   ```
   http://born-to-ride.local/activate-payment-plans-system.php
   ```

2. **Metodo Manuale**:
   - Imposta `btr_enable_payment_plans` a `true`
   - Crea una pagina con shortcode `[btr_payment_selection]`
   - Salva l'ID della pagina in `btr_payment_selection_page_id`

## Diagramma del Flusso

```
┌─────────────────────────┐
│   Form Anagrafici       │
│  (Dati partecipanti)    │
└───────────┬─────────────┘
            │ Submit "Vai al Checkout"
            ▼
┌─────────────────────────┐
│  convert_to_checkout()  │
│  - Salva dati           │
│  - Crea prodotti        │
│  - Check payment plans  │
└───────────┬─────────────┘
            │
            ▼
        ┌───────┐     NO    ┌──────────────┐
        │Plans  │ ─────────►│   Checkout   │
        │Attivi?│            │   Diretto    │
        └───┬───┘            └──────────────┘
            │ SI
            ▼
┌─────────────────────────┐
│  Pagina Selezione      │
│  Modalità Pagamento     │
│  - Individuale          │
│  - Gruppo               │
│  - Acconto + Saldo      │
└───────────┬─────────────┘
            │ Selezione
            ▼
        ┌────────┐
        │ Gruppo │────────► Genera Link ────► Riepilogo Link
        └────────┘
            │
      Altri casi
            │
            ▼
┌─────────────────────────┐
│   Checkout Finale       │
│   WooCommerce           │
└─────────────────────────┘
```

## Hooks e Filtri Disponibili

### Actions
- `btr_after_anagrafici_saved` - Dopo il salvataggio dei dati anagrafici
- `admin_post_btr_convert_to_checkout` - Handler per la conversione
- `wp_ajax_btr_create_payment_plan` - AJAX per creazione piano

### Filtri
- `woocommerce_checkout_fields` - Popola campi checkout con dati preventivo

## Testing del Flusso

### Prerequisiti
1. Sistema payment plans attivo (`btr_enable_payment_plans` = true)
2. Pagina di selezione creata e configurata
3. Shortcode `[btr_payment_selection]` inserito nella pagina

### Test Step-by-Step
1. Crea un preventivo di test
2. Vai al form anagrafici: `/inserisci-anagrafici/?preventivo_id={ID}`
3. Compila tutti i campi richiesti
4. Clicca "Vai al Checkout"
5. **Verifica**: Dovresti essere reindirizzato alla pagina di selezione pagamento
6. Scegli una modalità di pagamento
7. Procedi con la selezione
8. **Verifica**: Arrivo al checkout finale o alla pagina riepilogo link

### Debug
Attiva il debug con:
```php
define('BTR_DEBUG', true);
```

Controlla i log in `wp-content/debug.log` per:
- `[BTR DEBUG]` - Messaggi di debug del plugin
- Stati del carrello
- Redirect URL
- Errori di conversione

## Troubleshooting

### Problema: Redirect diretto al checkout senza selezione
**Cause possibili:**
1. `btr_enable_payment_plans` è false
2. La pagina di selezione non esiste
3. Esiste già un piano di pagamento per il preventivo

**Soluzione:**
- Esegui `activate-payment-plans-system.php`
- Verifica le opzioni nel database

### Problema: Errore 404 sulla pagina di selezione
**Cause:**
1. Pagina non pubblicata
2. Permalink non aggiornati

**Soluzione:**
- Vai su Impostazioni > Permalink e salva
- Verifica che la pagina sia pubblicata

### Problema: Pagamento di gruppo non genera link
**Cause:**
1. Classe `BTR_Group_Payments` non caricata
2. Tabelle database mancanti

**Soluzione:**
- Verifica che le tabelle `btr_group_payments` e `btr_payment_links` esistano
- Controlla i log per errori di generazione link

## Note per gli Sviluppatori

### Estensione del Sistema
Per aggiungere nuove modalità di pagamento:
1. Modifica il template `payment-selection-page.php`
2. Aggiungi la logica in `handle_create_payment_plan()`
3. Implementa la classe di gestione (es. `BTR_Custom_Payment`)

### Performance
- Il carrello viene svuotato prima di aggiungere nuovi prodotti
- I dati vengono salvati in transient come backup
- La sessione WooCommerce mantiene il riferimento al preventivo

### Sicurezza
- Tutti i form usano nonce verification
- I dati vengono sanitizzati prima del salvataggio
- Le capability vengono verificate per le operazioni admin