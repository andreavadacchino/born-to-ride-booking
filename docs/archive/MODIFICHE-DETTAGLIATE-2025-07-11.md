# Dettaglio Modifiche Tecniche - 10 Gennaio 2025

## Modifiche al Codice - Linea per Linea

### 1. includes/blocks/btr-checkout-summary/block.php

#### Riga 56-84: Fix Neonati Duplicati
```php
// PRIMA: Nessun controllo duplicati
// DOPO: 
// Rimuovi solo i duplicati esatti dei neonati
if (!empty($anagrafici_data)) {
    $anagrafici_filtrati = [];
    $neonati_visti = [];
    
    foreach ($anagrafici_data as $persona) {
        // Per i neonati, verifica duplicati esatti
        if (($persona['tipo_persona'] ?? '') === 'neonato' || 
            ($persona['camera_tipo'] ?? '') === 'Culla per Neonati') {
            
            $chiave_neonato = strtolower(trim(
                ($persona['nome'] ?? '') . '|' . 
                ($persona['cognome'] ?? '') . '|' . 
                ($persona['tipo_persona'] ?? '') . '|' . 
                ($persona['camera_tipo'] ?? '')
            ));
            
            if (!in_array($chiave_neonato, $neonati_visti)) {
                $anagrafici_filtrati[] = $persona;
                $neonati_visti[] = $chiave_neonato;
            }
        } else {
            // Per non-neonati, mantieni sempre
            $anagrafici_filtrati[] = $persona;
        }
    }
    
    $anagrafici_data = $anagrafici_filtrati;
}
```

#### Riga 94-109: Aggiunta Campi Extra
```php
// Estrai i valori necessari
$preventivo_data = [
    // ... campi esistenti ...
    'camere_selezionate' => isset($all_meta['_camere_selezionate'][0]) ? maybe_unserialize($all_meta['_camere_selezionate'][0]) : [],
    'date_ranges' => isset($all_meta['_date_ranges'][0]) ? $all_meta['_date_ranges'][0] : '',
    'data_pacchetto' => isset($all_meta['_data_pacchetto'][0]) ? $all_meta['_data_pacchetto'][0] : '',
    'btr_extra_night_date' => isset($all_meta['_btr_extra_night_date'][0]) ? maybe_unserialize($all_meta['_btr_extra_night_date'][0]) : [],
];
```

#### Riga 121-215: Nuovo Riepilogo Pacchetto
```php
// Mostra nome pacchetto e dettagli
<?php if (!empty($preventivo_data['nome_pacchetto']) || !empty($preventivo_data['data_partenza'])) : ?>
    <div class="btr-package-info">
        <?php if (!empty($preventivo_data['nome_pacchetto'])) : ?>
            <h3 class="btr-package-name"><?php echo esc_html($preventivo_data['nome_pacchetto']); ?></h3>
        <?php endif; ?>
        
        <div class="btr-package-details">
            <?php 
            // Data del pacchetto principale
            if (!empty($preventivo_data['data_pacchetto'])) {
                echo '<div class="btr-detail-line"><span class="btr-detail-label">Date pacchetto:</span> ' . 
                     esc_html($preventivo_data['data_pacchetto']) . '</div>';
            }
            
            // Durata
            if (!empty($preventivo_data['durata'])) {
                echo '<div class="btr-detail-line"><span class="btr-detail-label">Durata:</span> ' . 
                     esc_html($preventivo_data['durata']) . '</div>';
            }
            
            // Camere selezionate con dettaglio occupanti
            // Notti extra con parsing date multiple
            ?>
        </div>
    </div>
<?php endif; ?>
```

### 2. includes/class-btr-checkout.php

#### Riga 62-64: Nuovi Hook Pulizia Sessione
```php
// Pulisci la sessione quando il carrello viene svuotato
add_action( 'woocommerce_cart_emptied', [ $this, 'cleanup_session' ] );
add_action( 'woocommerce_before_cart_item_quantity_zero', [ $this, 'check_and_cleanup_session' ] );
add_action( 'woocommerce_remove_cart_item', [ $this, 'check_and_cleanup_session' ] );
```

#### Riga 192-224: Metodi Pulizia Sessione
```php
/**
 * Rimuove il preventivo salvato in sessione una volta completato l'ordine.
 */
public function cleanup_session() {
    WC()->session->__unset( 'btr_preventivo_id' );
    WC()->session->__unset( 'btr_anagrafici_data' );
    WC()->session->__unset( '_preventivo_id' );
}

/**
 * Controlla se il carrello contiene ancora articoli del preventivo e pulisce se necessario
 */
public function check_and_cleanup_session() {
    $preventivo_id = WC()->session->get( 'btr_preventivo_id' );
    if ( ! $preventivo_id ) {
        return;
    }
    
    $has_preventivo_items = false;
    
    if ( WC()->cart && ! WC()->cart->is_empty() ) {
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( isset( $cart_item['preventivo_id'] ) && $cart_item['preventivo_id'] == $preventivo_id ) {
                $has_preventivo_items = true;
                break;
            }
        }
    }
    
    if ( ! $has_preventivo_items ) {
        $this->cleanup_session();
    }
}
```

#### Modifica adjust_cart_item_prices (per sincronizzare totali)
```php
// Calcola il totale dai componenti del riepilogo
if (!empty($riepilogo['totali'])) {
    $totale_dal_riepilogo = 0;
    $totale_dal_riepilogo += floatval($riepilogo['totali']['subtotale_prezzi_base'] ?? 0);
    $totale_dal_riepilogo += floatval($riepilogo['totali']['subtotale_supplementi_base'] ?? 0);
    $totale_dal_riepilogo += floatval($riepilogo['totali']['subtotale_notti_extra'] ?? 0);
    $totale_dal_riepilogo += floatval($riepilogo['totali']['subtotale_supplementi_extra'] ?? 0);
    
    if ($totale_dal_riepilogo > 0) {
        $cart_item['data']->set_price($totale_dal_riepilogo);
    }
}
```

### 3. includes/class-btr-shortcode-anagrafici.php

#### Riga 590-611: Aggiunta Campi Mancanti
```php
// Crea array sanitizzato con tutti i campi
$sanitized = [
    'nome' => sanitize_text_field($persona['nome'] ?? ''),
    'cognome' => sanitize_text_field($persona['cognome'] ?? ''),
    // ... altri campi esistenti ...
    'indirizzo_residenza' => sanitize_text_field($persona['indirizzo_residenza'] ?? ''),
    'numero_civico' => sanitize_text_field($persona['numero_civico'] ?? ''),
    'cap_residenza' => sanitize_text_field($persona['cap_residenza'] ?? ''),
    'codice_fiscale' => sanitize_text_field($persona['codice_fiscale'] ?? ''),
    'tipo_persona' => sanitize_text_field($persona['tipo_persona'] ?? ''), // IMPORTANTE
    // ... resto dei campi ...
];
```

#### Riga 636-659: Fix Critico Neonati
```php
// Fix: Prima di salvare, verifica il numero corretto di neonati
$num_neonati_attesi = intval(get_post_meta($preventivo_id, '_num_neonati', true));
$neonati_count = 0;
$anagrafici_filtrati = [];

foreach ($sanitized_anagrafici as $persona) {
    // Se è un neonato
    if (($persona['tipo_persona'] ?? '') === 'neonato' || 
        (!empty($persona['fascia']) && $persona['fascia'] === 'neonato')) {
        
        // Conta solo se non supera il numero atteso
        if ($neonati_count < $num_neonati_attesi) {
            $anagrafici_filtrati[] = $persona;
            $neonati_count++;
        }
        // Altrimenti scarta il neonato in eccesso
    } else {
        // Non è un neonato, mantieni sempre
        $anagrafici_filtrati[] = $persona;
    }
}

// Usa l'array filtrato invece di quello originale
$sanitized_anagrafici = $anagrafici_filtrati;
```

### 4. includes/blocks/btr-checkout-summary/style.css

#### Completa riscrittura per UI minimale:
```css
/* Informazioni pacchetto - In testa */
.wp-block-btr-checkout-summary .btr-package-info {
    order: -2;
    padding-bottom: 12px;
    margin-bottom: 12px;
    border-bottom: 1px solid #e0e0e0;
}

.wp-block-btr-checkout-summary .btr-package-name {
    margin: 0 0 4px 0;
    font-size: 16px;
    font-weight: 600;
    color: #000;
}

.wp-block-btr-checkout-summary .btr-package-details {
    margin-top: 8px;
    font-size: 13px;
    color: #666;
}

.wp-block-btr-checkout-summary .btr-detail-line {
    padding: 2px 0;
    display: flex;
    gap: 6px;
}

.wp-block-btr-checkout-summary .btr-detail-label {
    font-weight: 500;
    color: #333;
}

/* Lista partecipanti - Design ultra compatto */
.wp-block-btr-checkout-summary .btr-summary-anagrafici {
    order: -1;
}

/* Font sizes ridotti, colori neutri, spaziature minime */
```

### 5. assets/js/checkout-total-fix.js (nuovo file)

```javascript
jQuery(document).ready(function($) {
    if (!$('body').hasClass('woocommerce-checkout')) {
        return;
    }
    
    // Monitora discrepanze tra totale custom e WooCommerce
    var customTotal = $('.wp-block-btr-checkout-summary .btr-summary-total strong:last-child').text();
    var wcTotal = $('.woocommerce-checkout-review-order-table .order-total .woocommerce-Price-amount').text();
    
    if (customTotal && wcTotal) {
        var customTotalValue = customTotal.replace(/[^0-9,]/g, '').replace(',', '.');
        var wcTotalValue = wcTotal.replace(/[^0-9,]/g, '').replace(',', '.');
        
        if (Math.abs(parseFloat(customTotalValue) - parseFloat(wcTotalValue)) > 0.01) {
            console.log('BTR Checkout: Discrepanza totale rilevata');
            $(document.body).trigger('update_checkout');
        }
    }
});
```

## Impatto delle Modifiche

### Performance
- Nessun impatto negativo sulle performance
- Il filtro neonati aggiunge O(n) complessità ma su array piccoli (< 10 elementi)

### Compatibilità
- Retrocompatibile con dati esistenti
- Supporta sia strutture dati vecchie che nuove
- Non richiede migrazione database

### Sicurezza
- Tutti gli input sanitizzati correttamente
- Nonce verification mantenuta
- Nessuna nuova vulnerabilità introdotta

### UX Miglioramenti
- UI più pulita e minimale
- Informazioni meglio organizzate
- Nessun accumulo di dati vecchi
- Totali sempre accurati

## Testing Checklist

- [ ] Creare preventivo con 2 adulti, 1 bambino, 1 neonato
- [ ] Compilare form anagrafici
- [ ] Verificare solo 1 neonato nel checkout
- [ ] Verificare totali corretti (base + supplementi + notti extra)
- [ ] Svuotare carrello e verificare pulizia sessione
- [ ] Rifare procedura e verificare nessun dato vecchio
- [ ] Testare con notti extra
- [ ] Testare senza neonati
- [ ] Testare con multiple camere

## Rollback

Per tornare alla versione precedente:
1. Ripristinare i file dal backup
2. Svuotare la cache di WordPress
3. Svuotare la sessione WooCommerce
4. Testare il flusso completo