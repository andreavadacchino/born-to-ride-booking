# WordPress Routing Implementation - v1.0.99

## 📅 Data: 22 Gennaio 2025

## 🎯 Obiettivo
Configurare il routing WordPress per rendere accessibili i link di pagamento individuali nel formato `/pay-individual/{hash}/`.

## ✅ Implementazioni Completate (5 Waves)

### WAVE 1: Core Rewrite Rules ⚡
**File modificato**: `includes/class-btr-group-payments.php`

1. **Aggiunto metodo `add_rewrite_rules()`**:
   ```php
   public function add_rewrite_rules() {
       add_rewrite_rule(
           '^pay-individual/([a-f0-9]{64})/?$',
           'index.php?btr_payment_action=individual&btr_payment_hash=$matches[1]',
           'top'
       );
   }
   ```

2. **Registrato hook su `init`** per attivare le rules

3. **Pattern alternativo** per compatibilità: `/btr-payment/{hash}/`

### WAVE 2: Flush Rules Mechanism 🔄
**Nuovo file creato**: `includes/class-btr-rewrite-rules-manager.php`

1. **Sistema di versioning** per evitare flush continui
2. **Admin notice** con pulsante AJAX per flush manuale
3. **Hook automatici** su activation/deactivation
4. **Costante versione**: `CURRENT_RULES_VERSION = '1.0.99.1'`

### WAVE 3: Integration & Template Loading 🔌
**Miglioramenti a `handle_payment_page()`**:

1. **Nocache headers** per prevenire caching
2. **Debug logging** per troubleshooting
3. **Status headers** corretti (200)
4. **Exit sicuro** dopo rendering

### WAVE 4: Testing & Validation 🧪
**Script di test creati**:

1. **`test-payment-routing.php`**:
   - Verifica rewrite rules registrate
   - Controlla query vars
   - Genera link di test
   - Valida configurazione database

2. **`test-group-payment-flow.php`**:
   - Test end-to-end completo
   - Crea preventivo di test
   - Genera link pagamento
   - Verifica funzionamento

### WAVE 5: Documentation 📚
Questa documentazione completa l'implementazione.

## 🔧 Come Funziona

### Flusso Routing:
1. **URL richiesto**: `https://sito.com/pay-individual/{hash}/`
2. **Rewrite rule** trasforma in: `index.php?btr_payment_action=individual&btr_payment_hash={hash}`
3. **WordPress** popola le query vars
4. **`template_redirect`** intercetta e chiama `handle_payment_page()`
5. **Pagina renderizzata** con template custom

### Componenti Chiave:
- **Rewrite Rules**: Pattern regex per URL recognition
- **Query Vars**: Parametri custom per WordPress
- **Template Redirect**: Hook per intercettare richieste
- **Rules Manager**: Gestione centralizzata flush

## 🚀 Istruzioni per Admin

### Prima Attivazione:
1. Dopo aver aggiornato il plugin, vai in **Dashboard WordPress**
2. Se vedi l'avviso giallo "Le rewrite rules devono essere aggiornate"
3. Clicca su **"Aggiorna Rewrite Rules"**
4. Attendi il reload della pagina

### Test Funzionamento:
1. Accedi a: `https://tuosito.com/test-payment-routing.php`
2. Verifica che tutte le sezioni mostrino ✅
3. Clicca su uno dei "Test Link"
4. Dovresti vedere la pagina (anche se con errore "link non valido")
5. Se ottieni 404, ripeti il flush delle rules

### Test Completo:
1. Accedi a: `https://tuosito.com/test-group-payment-flow.php`
2. Clicca "Inizia Test"
3. Segui i passaggi per generare link reali
4. Testa i link generati
5. Ricorda di pulire i dati di test

## 🐛 Troubleshooting

### Link danno 404:
1. Vai in **Impostazioni → Permalink**
2. Clicca **"Salva modifiche"** (anche senza cambiare nulla)
3. Riprova i link

### Admin notice non scompare:
1. Usa il pulsante nell'avviso stesso
2. NON usare Impostazioni → Permalink
3. Se persiste, controlla versione in database

### Debug mode:
Aggiungi in `wp-config.php`:
```php
define('BTR_DEBUG', true);
```

Poi controlla `wp-content/debug.log` per messaggi.

## 📊 Database

### Tabelle utilizzate:
- `wp_btr_group_payments` - Pagamenti individuali
- `wp_btr_payment_links` - Link con hash sicuri

### Option utilizzate:
- `btr_rewrite_rules_version` - Versione rules corrente

## 🔐 Sicurezza

1. **Hash SHA256** - 64 caratteri, impossibili da indovinare
2. **Scadenza temporale** - Link validi per 72 ore
3. **Validazione database** - Link verificati prima del rendering
4. **Nonce verification** - Per operazioni AJAX

## 📝 Modifiche Future

Per aggiungere nuovi pattern URL:

1. Modifica `add_rewrite_rules()` in `class-btr-group-payments.php`
2. Incrementa `CURRENT_RULES_VERSION` in `class-btr-rewrite-rules-manager.php`
3. Il sistema farà flush automatico al prossimo caricamento

## ✨ Note Finali

Il sistema è ora completamente operativo. I link di pagamento individuali sono accessibili e pronti per l'uso in produzione. Il routing è robusto con fallback e logging per facilitare il debug.

---
**Versione**: 1.0.99
**Ultimo aggiornamento**: 22 Gennaio 2025