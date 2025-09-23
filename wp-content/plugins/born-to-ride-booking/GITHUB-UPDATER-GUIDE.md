# GitHub Updater - Guida Configurazione

## Setup Rapido

### 1. Configura il Repository GitHub

1. Crea un repository su GitHub per il plugin
2. Modifica `updater-config.json` con i tuoi dati:

```json
{
    "github_username": "tuousername",
    "github_repository": "born-to-ride-booking",
    "github_token": "",  // Solo per repo privati
    "cache_expiration": 43200
}
```

### 2. Modifica Headers nel Plugin

Aggiorna gli headers in `born-to-ride-booking.php`:

```php
/**
 * Update URI: https://github.com/tuousername/born-to-ride-booking
 * GitHub Plugin URI: https://github.com/tuousername/born-to-ride-booking
 */
```

### 3. Crea Release su GitHub

1. Vai su GitHub → Releases → "Create a new release"
2. Crea un tag versione (es: `v1.0.250`)
3. Il tag DEVE corrispondere alla versione nel plugin
4. Carica il file ZIP del plugin come asset (opzionale)

## Workflow Completo

### Sviluppo Locale → Produzione

1. **Sviluppo Locale**
   ```bash
   # Modifica codice
   # Incrementa versione in born-to-ride-booking.php
   # Test locale
   ```

2. **Push su GitHub**
   ```bash
   git add .
   git commit -m "feat: nuova funzionalità - v1.0.250"
   git push origin main
   ```

3. **Crea Release**
   ```bash
   git tag v1.0.250
   git push origin v1.0.250
   # Oppure crea release via GitHub UI
   ```

4. **Aggiornamento Automatico**
   - Il sito di produzione rileverà l'aggiornamento
   - Apparirà nella dashboard WordPress → Plugin
   - Clicca "Aggiorna ora"

## Repository Privati

Per repository privati, crea un Personal Access Token:

1. GitHub → Settings → Developer settings → Personal access tokens
2. Genera nuovo token con permessi `repo`
3. Aggiungi in `updater-config.json`:

```json
{
    "github_token": "ghp_tuoTokenQui"
}
```

⚠️ **ATTENZIONE**: Il token sarà visibile nel codice. Valuta alternative più sicure per produzione.

## Limiti e Considerazioni

### Rate Limits GitHub API
- **Senza autenticazione**: 60 richieste/ora
- **Con token**: 5000 richieste/ora
- **Cache**: 12 ore per ridurre richieste

### Sicurezza
- ❌ Nessuna firma digitale dei pacchetti
- ❌ Token visibili nel codice (per repo privati)
- ✅ HTTPS per tutti i download
- ✅ WordPress nonce per azioni admin

### Best Practices
1. **Usa sempre tag semantici** (v1.0.250, non 1.0.250)
2. **Incrementa sempre la versione** nel plugin
3. **Testa in staging** prima della produzione
4. **Monitora i rate limits** se hai molti siti

## Troubleshooting

### L'aggiornamento non appare
1. Verifica che il tag corrisponda alla versione
2. Clicca "Controlla aggiornamenti" nella pagina plugin
3. Controlla i log: `wp-content/debug.log`

### Errore 403 Forbidden
- Hai superato i rate limits
- Aggiungi un token per aumentare i limiti

### Cartella errata dopo aggiornamento
- Il sistema rinomina automaticamente
- Se fallisce, rinomina manualmente in `born-to-ride-booking`

## Testing

### Test Manuale
1. Cambia versione locale a versione più bassa
2. Crea release su GitHub con versione più alta
3. Vai su WordPress → Plugin
4. Dovrebbe apparire "Aggiornamento disponibile"

### Forzare Controllo
- Clicca link "Check for updates" nella lista plugin
- Oppure svuota transient: `delete_transient('update_plugins')`

## Comandi Utili

```bash
# Crea release da CLI
gh release create v1.0.250 \
  --title "Version 1.0.250" \
  --notes "Descrizione modifiche"

# Crea ZIP per release
zip -r born-to-ride-booking.zip born-to-ride-booking/ \
  -x "*.git*" \
  -x "*/node_modules/*" \
  -x "*/.DS_Store"
```

## Disabilitare Updater

Se necessario disabilitare temporaneamente:

1. Rinomina/elimina `updater-config.json`
2. O commenta l'inizializzazione in `born-to-ride-booking.php`

---

**Versione Sistema**: 1.0.0
**Compatibilità**: WordPress 5.0+, PHP 7.2+
**Ultimo Aggiornamento**: Gennaio 2025