# Fix Totale Camere - 2025-01-25

## Problema
Il totale finale nella pagina di selezione pagamento mostrava €768,30 invece di €614,30 perché includeva erroneamente i supplementi extra nel "Totale Camere".

## Analisi
Dai dati del preventivo:
- subtotale_prezzi_base: €429.30
- subtotale_supplementi_base: €60
- subtotale_notti_extra: €95
- subtotale_supplementi_extra: €30 (supplementi per le notti extra)
- Totale assicurazioni: €15
- Totale costi extra: €15

### Calcolo Corretto
- **Totale Camere**: €584.30 (prezzi base + supplementi base + notti extra)
- **Supplementi notti extra**: €30 (da mostrare separatamente)
- **Totale assicurazioni**: €15
- **Totale costi extra**: €15
- **TOTALE FINALE**: €614.30

Il problema era che i supplementi_extra (€30) venivano inclusi nel "Totale Camere", facendolo diventare €614.30 invece di €584.30.

## Modifiche Apportate

### 1. `templates/payment-selection-page.php`
- Rimosso `subtotale_supplementi_extra` dal calcolo di `$prezzo_base_calcolato`
- Aggiunto `$supplementi_extra` come variabile separata
- Aggiornato il calcolo del totale finale per includere `supplementi_extra` separatamente
- Aggiunto display dei supplementi extra nella UI

### 2. `templates/payment-selection-page-unified.php`
- Stesse modifiche del template principale per consistenza

## Codice Modificato

### Prima:
```php
$prezzo_base_calcolato = floatval($totali['subtotale_prezzi_base'] ?? 0) + 
                        floatval($totali['subtotale_supplementi_base'] ?? 0) + 
                        floatval($totali['subtotale_notti_extra'] ?? 0) + 
                        floatval($totali['subtotale_supplementi_extra'] ?? 0);
```

### Dopo:
```php
// IMPORTANTE: Il totale camere NON include i supplementi extra
$prezzo_base_calcolato = floatval($totali['subtotale_prezzi_base'] ?? 0) + 
                        floatval($totali['subtotale_supplementi_base'] ?? 0) + 
                        floatval($totali['subtotale_notti_extra'] ?? 0);

// Recupera i supplementi extra separatamente
$supplementi_extra = floatval($riepilogo_dettagliato['totali']['subtotale_supplementi_extra'] ?? 0);

// Il totale finale include tutto
$totale_preventivo = $prezzo_base + $supplementi_extra + $totale_assicurazioni + $totale_costi_extra;
```

## UI Update
Aggiunto display dei supplementi extra dopo il totale camere:
```php
<?php if (isset($supplementi_extra) && $supplementi_extra > 0): ?>
<li>
    <span class="label"><?php esc_html_e('Supplementi notti extra:', 'born-to-ride-booking'); ?></span>
    <span class="value">+ <?php echo btr_format_price_i18n($supplementi_extra); ?></span>
</li>
<?php endif; ?>
```

## Script di Debug
Creati due script di debug per analizzare il problema:
- `tests/debug-calcoli-totale-camere.php` - Analisi generale dei totali
- `tests/debug-supplementi-extra.php` - Focus specifico sui supplementi extra

## Risultato
Ora il sistema mostra correttamente:
- Totale Camere: €584.30 (senza supplementi extra)
- Supplementi notti extra: €30.00 (mostrati separatamente)
- Totale finale: €614.30 (include tutto)