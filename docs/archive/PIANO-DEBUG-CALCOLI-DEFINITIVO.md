# Piano di Debug e Correzione Definitivo - Sistema Calcoli Born to Ride

## Analisi del Problema

### Problemi Identificati

1. **Inconsistenza nei nomi dei meta fields**
   - Il sistema usa sia `_num_adults` che `_numero_adulti`
   - Stesso problema per `_num_children` vs `_numero_bambini`
   - Questo causa visualizzazioni errate (es: "0 Persone" invece di "2 Adulti")

2. **Calcoli duplicati e non sincronizzati**
   - I calcoli vengono eseguiti in più punti:
     - `class-btr-preventivi.php` (creazione preventivo)
     - `class-btr-cost-calculator.php` (centralizzato ma non usato ovunque)
     - `payment-selection-page.php` (calcolo al volo)
     - `class-btr-checkout.php` (ricalcolo al checkout)
   - Ogni punto può produrre risultati diversi

3. **Mancanza di una fonte unica di verità**
   - I totali sono salvati in diversi meta:
     - `_totale_camere`
     - `_prezzo_base`
     - `_prezzo_totale`
     - `_totale_preventivo`
   - Non c'è chiarezza su quale sia il valore autoritativo

4. **Problemi con assicurazioni e costi extra**
   - Calcoli per persona vs per camera non sempre corretti
   - Costi extra per notte non sempre moltiplicati correttamente
   - Assicurazioni negative (sconti) gestite in modo inconsistente

5. **Sincronizzazione WooCommerce**
   - Il carrello WooCommerce non sempre riflette i totali del preventivo
   - Mancanza di aggiornamento automatico quando cambiano i dati

## Piano di Correzione

### Fase 1: Standardizzazione dei Meta Fields (PRIORITÀ ALTA)

**File da modificare:**
1. `includes/class-btr-preventivi.php`
2. `includes/class-btr-shortcode-anagrafici.php`
3. Tutti i template che leggono i meta

**Azioni:**
```php
// Creare funzioni helper per leggere i meta con fallback
function btr_get_adults_count($preventivo_id) {
    return intval(get_post_meta($preventivo_id, '_num_adults', true) ?: 
                  get_post_meta($preventivo_id, '_numero_adulti', true));
}

function btr_get_children_count($preventivo_id) {
    return intval(get_post_meta($preventivo_id, '_num_children', true) ?: 
                  get_post_meta($preventivo_id, '_numero_bambini', true));
}

function btr_get_infants_count($preventivo_id) {
    return intval(get_post_meta($preventivo_id, '_num_neonati', true));
}
```

### Fase 2: Centralizzare i Calcoli (PRIORITÀ ALTA)

**Implementazione:**

1. **Estendere `class-btr-cost-calculator.php`** per gestire TUTTI i calcoli:
   ```php
   class BTR_Cost_Calculator {
       // Aggiungere metodo per calcolare costi extra per persona
       public function calculate_extra_costs_per_person($anagrafici, $costi_extra_meta) {
           // Logica centralizzata per calcolare costi extra
       }
       
       // Aggiungere metodo per calcolare le assicurazioni
       public function calculate_insurances_per_person($anagrafici) {
           // Logica centralizzata per le assicurazioni
       }
   }
   ```

2. **Sostituire tutti i calcoli inline** nei template con chiamate al calculator:
   ```php
   // Invece di calcolare inline
   $calculator = BTR_Cost_Calculator::get_instance();
   $totals = $calculator->calculate_all_totals($preventivo_id);
   ```

### Fase 3: Implementare Sistema di Cache e Validazione (PRIORITÀ MEDIA)

**Nuovo file:** `includes/class-btr-totals-cache.php`

```php
class BTR_Totals_Cache {
    // Cache dei totali calcolati per evitare ricalcoli
    private static $cache = [];
    
    public static function get_totals($preventivo_id, $force_recalc = false) {
        if (!$force_recalc && isset(self::$cache[$preventivo_id])) {
            return self::$cache[$preventivo_id];
        }
        
        $calculator = BTR_Cost_Calculator::get_instance();
        $totals = $calculator->calculate_all_totals($preventivo_id);
        self::$cache[$preventivo_id] = $totals;
        
        return $totals;
    }
    
    public static function invalidate($preventivo_id) {
        unset(self::$cache[$preventivo_id]);
    }
}
```

### Fase 4: Sincronizzazione WooCommerce (PRIORITÀ ALTA)

**Modifiche a `class-btr-checkout.php`:**

1. **Hook per sincronizzare al caricamento del checkout:**
   ```php
   add_action('woocommerce_before_checkout_form', function() {
       if (WC()->session) {
           $preventivo_id = WC()->session->get('_preventivo_id');
           if ($preventivo_id) {
               BTR_Checkout::sync_cart_with_preventivo($preventivo_id);
           }
       }
   });
   ```

2. **Metodo di sincronizzazione:**
   ```php
   public static function sync_cart_with_preventivo($preventivo_id) {
       $calculator = BTR_Cost_Calculator::get_instance();
       $totals = $calculator->calculate_all_totals($preventivo_id);
       
       // Aggiornare fees del carrello
       WC()->cart->remove_all_fees();
       
       if ($totals['totale_assicurazioni'] > 0) {
           WC()->cart->add_fee(__('Assicurazioni', 'btr'), $totals['totale_assicurazioni']);
       }
       
       if ($totals['totale_costi_extra'] != 0) {
           $label = $totals['totale_costi_extra'] > 0 ? __('Servizi Extra', 'btr') : __('Riduzioni', 'btr');
           WC()->cart->add_fee($label, $totals['totale_costi_extra']);
       }
   }
   ```

### Fase 5: Aggiungere Logging e Debug (PRIORITÀ MEDIA)

**Nuovo sistema di logging:**

```php
class BTR_Debug_Logger {
    public static function log_calculation($preventivo_id, $step, $data) {
        if (!defined('BTR_DEBUG') || !BTR_DEBUG) return;
        
        $log = [
            'timestamp' => current_time('mysql'),
            'preventivo_id' => $preventivo_id,
            'step' => $step,
            'data' => $data
        ];
        
        error_log('[BTR Calculation] ' . json_encode($log));
    }
}
```

### Fase 6: Fix Template Specifici

**Template da aggiornare:**

1. **`payment-selection-page.php` e varianti:**
   - Usare le nuove funzioni helper per i conteggi
   - Usare il calculator centralizzato per i totali
   - Rimuovere calcoli inline

2. **`preventivo-review.php`:**
   - Implementare visualizzazione consistente dei totali
   - Aggiungere breakdown dettagliato per trasparenza

3. **`checkout-summary/block.php`:**
   - Sincronizzare con i totali del preventivo
   - Mostrare stesso breakdown della payment selection

## Ordine di Implementazione

### Sprint 1 (Urgente - 2-3 giorni) - COMPLETATO ✅
1. ✅ Implementare funzioni helper per meta fields (2 ore) - FATTO
   - Creato file `includes/helpers/btr-meta-helpers.php`
   - Implementate tutte le funzioni helper con fallback
2. ✅ Fix immediato nei template payment-selection (1 ora) - FATTO
   - Aggiornato `payment-selection-page-riepilogo-style.php`
   - Usa sistema centralizzato BTR_Cost_Calculator
3. ✅ Testare il flusso completo (2 ore) - PRONTO
   - Creato script di test `test-centralizzazione-calcoli.php`

### Sprint 2 (Alta priorità - 3-4 giorni)
1. Estendere BTR_Cost_Calculator (4 ore)
2. Sostituire calcoli inline in tutti i template (6 ore)
3. Implementare sincronizzazione WooCommerce (4 ore)
4. Testing approfondito (4 ore)

### Sprint 3 (Media priorità - 2-3 giorni)
1. Implementare sistema di cache (3 ore)
2. Aggiungere logging dettagliato (2 ore)
3. Creare script di migrazione per meta fields (3 ore)
4. Documentazione finale (2 ore)

## Test Plan

### Test Case 1: Flusso Standard
1. Creare preventivo con 2 adulti
2. Aggiungere assicurazioni miste
3. Aggiungere costi extra (positivi e negativi)
4. Verificare totali in ogni step
5. Completare checkout

### Test Case 2: Edge Cases
1. Solo bambini (nessun adulto)
2. Mix adulti/bambini/neonati
3. Assicurazioni negative (sconti)
4. Costi extra per notte
5. Pagamento di gruppo

### Test Case 3: Regressione
1. Preventivi esistenti devono continuare a funzionare
2. Ordini completati devono mantenere i totali
3. Email devono mostrare importi corretti

## Metriche di Successo

1. **Consistenza**: Stesso totale in tutti gli step (100%)
2. **Accuratezza**: Calcoli matematicamente corretti (100%)
3. **Performance**: Tempo di caricamento < 2s per step
4. **Affidabilità**: Zero errori in produzione per 30 giorni

## Note per il Refactoring Futuro

1. Considerare migrazione a React per il form preventivo
2. Implementare API REST per i calcoli real-time
3. Aggiungere unit test per il calculator
4. Considerare architettura event-driven per aggiornamenti

---

**Documento creato il**: 24/07/2025
**Versione**: 1.0
**Autore**: Sistema di Analisi BTR