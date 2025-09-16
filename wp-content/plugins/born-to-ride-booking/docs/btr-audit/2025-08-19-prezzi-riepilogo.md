Born to Ride — Audit Fix Prezzi Riepilogo

Contesto
- Funzione: `render_riepilogo_preventivo_shortcode($atts)`
- File: `wp-content/plugins/born-to-ride-booking/includes/class-btr-preventivi.php`
- Problema segnalato: totale mostrato come `1,28` invece di `1.279,00`.

Root Cause
- `floatval()` su stringhe localizzate italiane (es. `"1.279,00"`) converte a `1.279` (interpreta `.` come decimale e tronca alla virgola), causando un totale errato.
- Evidenza:
  - `class-btr-preventivi.php:1468-1476` (prima della patch) utilizzava `floatval($this->meta('_pricing_totale_generale_display'))`.

Intervento
- Aggiunto parser robusto per importi localizzati:
  - `class-btr-preventivi.php:...` metodo privato `parse_localized_price($value): float` (gestisce `€`, NBSP, separatori di migliaia e decimali IT/EN).
- Sostituita la conversione del totale display:
  - `class-btr-preventivi.php:1468-1476` ora usa `parse_localized_price()` su `_pricing_totale_generale_display`.
- Hardening salvataggio totale:
  - `class-btr-preventivi.php:752-763` applica `round((float)$prezzo_totale, 2)` prima di salvare `_prezzo_totale` e `_totale_preventivo`.

Impatto
- Correzione immediata dei totali nel riepilogo: “1.279,00” viene letto come `1279.00` e mostrato correttamente con `btr_format_price_i18n()`.
- Migliore coerenza dei dati: i totali salvati sono arrotondati a 2 decimali.

Raccomandazioni
- Abilitato `BTR_USE_REFACTORED_QUOTE=true` per usare `class-btr-preventivi-v4.php` con `parse_price()`/`fmt_price()` in salvataggio (hook AJAX sovrascritti), assicurando parsing e arrotondamento coerenti per tutti i campi `pricing_*`.
- Dove possibile, salvare in DB valori numerici “grezzi” (float) e usare la formattazione solo in output.

Sicurezza & Performance
- Nessun impatto sui permessi. Patch non destruttiva e a basso rischio.
- Parsing O(1) su stringhe corte; impatto prestazionale trascurabile.

Verifica
- Con `_pricing_totale_generale_display = "1.279,00"` o `"€1.279,00"`, il totale visualizzato è `€1.279,00`.
- Test rapido: `test-preventivo-display.php` con ID preventivo interessato.
