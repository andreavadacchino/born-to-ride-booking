# ISTRUZIONI DEPLOYMENT v1.0.61

## File per Produzione
**File ZIP**: `born-to-ride-booking-v1.0.61-production.zip`  
**Dimensione**: ~531 MB  
**Data**: 2025-07-15 02:02  

## Problema Risolto
**Errore precedente**:
```
Impossibile copiare il file. born-to-ride-booking/includes/blocks/btr-checkout-summary/node_modules/typescript/lib/zh-tw/diagnosticMessages.generated.json
```

**Soluzione applicata**:
- ✅ Rimossa cartella `node_modules` da `includes/blocks/btr-checkout-summary/`
- ✅ Rimossi file `package.json` e `package-lock.json` non necessari in produzione
- ✅ Mantenuti solo file compilati: `build/`, `block.php`, `style.css`

## Istruzioni Installazione

### 1. Backup Attuale
```bash
# Sul server di produzione
cp -r wp-content/plugins/born-to-ride-booking wp-content/plugins/born-to-ride-booking-backup-$(date +%Y%m%d)
```

### 2. Upload e Installazione
1. **Upload ZIP**: Carica `born-to-ride-booking-v1.0.61-production.zip` via WordPress Admin
2. **Installazione**: WordPress > Plugin > Aggiungi nuovo > Carica plugin
3. **Attivazione**: Attiva il plugin dopo l'installazione

### 3. Verifiche Post-Installazione

#### A. Checkout Summary
- ✅ Tutti i partecipanti adulti visibili (De Daniele, Leonardo Colatorti, etc.)
- ✅ Assicurazioni e costi extra mostrati sotto ogni partecipante
- ✅ Nessun phantom participant (neonati duplicati rimossi)
- ✅ Layout pulito senza commenti HTML visibili

#### B. Totali WooCommerce
- ✅ Subtotale: **1.184,36€** (non più 1.194,36€ o 1.516,74€)
- ✅ Totale finale: **1.184,36€**
- ✅ Sezioni "Totale Assicurazioni" e "Totale Costi extra" visibili

#### C. Funzionalità Core
- ✅ Creazione preventivi
- ✅ Calcolo prezzi dinamici
- ✅ Integrazione WooCommerce
- ✅ Generazione PDF

## Modifiche Principali v1.0.61

### 🔧 Production Deployment Fix
- **Phantom participants filtering mirato**: Solo neonati duplicati rimossi
- **Cart synchronization corretta**: Totali WooCommerce allineati  
- **Fix dipendenze sviluppo**: Rimossi node_modules per installazione server

### 🐛 Fix Critical da v1.0.60
- Sincronizzazione totali preventivo/carrello
- Filtraggio partecipanti più preciso
- Fix metodo privato WooCommerce

## File Interessati dalle Modifiche

```
includes/blocks/btr-checkout-summary/
├── block.php (phantom filtering mirato)
├── style.css (layout migliorato)
├── build/ (file compilati mantenuti)
└── [RIMOSSI: node_modules/, package.json, package-lock.json]

includes/class-btr-cart-extras-manager.php
├── Sincronizzazione multi-livello totali
├── Fix metodo privato reset_totals()
└── Debug logging completo

CHANGELOG.md
└── Documentazione completa v1.0.61
```

## Test di Verifica

### Test 1: Checkout Summary
1. Crea preventivo con 2 adulti + 1 bambino + 1 neonato
2. Aggiungi assicurazioni e costi extra (incluso skipass -35€)
3. Vai al checkout
4. **Verifica**: 
   - Adulti e bambini visibili con rispettivi costi
   - Solo neonati duplicati rimossi
   - Totali corretti (1.184,36€)

### Test 2: Installazione Server
1. Upload ZIP tramite WordPress Admin
2. **Verifica**: Nessun errore "Impossibile copiare il file"
3. **Verifica**: Plugin si attiva correttamente
4. **Verifica**: Nessun errore PHP nei log

## Rollback (se necessario)
```bash
# Sul server di produzione, se ci sono problemi
rm -rf wp-content/plugins/born-to-ride-booking
mv wp-content/plugins/born-to-ride-booking-backup-YYYYMMDD wp-content/plugins/born-to-ride-booking
```

## Supporto Debug
Se ci sono problemi, abilitare debug WordPress:
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('BTR_DEBUG', true);
```

Log disponibili in: `wp-content/debug.log`

---

**Deploy Ready**: Il pacchetto v1.0.61 è pronto per l'installazione su server di produzione senza errori di dipendenze.