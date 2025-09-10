# ISTRUZIONI PER SVUOTARE LA CACHE

## Il problema delle "2 Notti extra" è stato risolto!

### Cosa è stato fatto:
1. Corretto il bug di normalizzazione delle date nel backend
2. Aggiunto il caricamento dei dati mancanti nel metodo AJAX
3. Ora il sistema riconosce correttamente 1 notte extra per la data "24 - 25 Gennaio 2026"

### Per vedere le modifiche:

#### 1. Svuota la cache del browser:
- **Chrome/Edge**: Ctrl+Shift+R (Windows) o Cmd+Shift+R (Mac)
- **Firefox**: Ctrl+F5 (Windows) o Cmd+Shift+R (Mac)  
- **Safari**: Cmd+Option+E poi ricarica la pagina

#### 2. Svuota la cache di WordPress (se usi un plugin di cache):
- WP Rocket: Dashboard → WP Rocket → Clear Cache
- W3 Total Cache: Performance → Dashboard → Empty All Caches
- WP Super Cache: Settings → WP Super Cache → Delete Cache

#### 3. Test finale:
1. Vai alla pagina di prenotazione
2. Seleziona la data "24 - 25 Gennaio 2026"
3. Attiva "Sì, aggiungi la notte extra"
4. Nel riepilogo dovresti vedere **"1 Notte extra"** invece di "2 Notti extra"

### File di test disponibili:
- `/tests/verify-final-fix.php` - Verifica che il backend invii il valore corretto
- `/tests/verify-extra-nights-working.php` - Test rapido del conteggio

### Nota tecnica:
Il problema era causato da:
1. Le date nel database erano già in formato Y-m-d ma il codice cercava di normalizzarle come date italiane
2. I dati delle notti extra non venivano caricati nel metodo AJAX

Entrambi i problemi sono stati risolti.