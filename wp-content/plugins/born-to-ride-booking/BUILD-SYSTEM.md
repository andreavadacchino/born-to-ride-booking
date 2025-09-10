# Sistema di Build e Versioning - Born to Ride Booking

## Panoramica

Il nuovo sistema di build professionale risolve i problemi di cache in produzione e segue le best practice di WordPress per la distribuzione dei plugin.

## Componenti Principali

### 1. Build Release Script (`build-release-pro.php`)

Script principale per creare build di produzione con:
- **Cache busting automatico**: Aggiorna BTR_VERSION in tutti i file
- **Esclusioni intelligenti**: Rimuove file di sviluppo, test e documentazione
- **Versioning consistente**: Tutti gli assets JS/CSS usano BTR_VERSION
- **Timestamp per file critici**: Forza refresh dei file principali
- **Pulizia automatica**: Mantiene solo le ultime 5 build

### 2. Version Manager (`version-manager.php`)

Gestisce l'incremento automatico delle versioni:
- **Semantic versioning**: Major.Minor.Patch
- **Aggiornamento automatico** di tutti i riferimenti alla versione
- **Integrazione CHANGELOG**: Documenta automaticamente i cambiamenti
- **Interfaccia CLI e Web**: Utilizzabile da terminale o browser

### 3. Build Script Legacy (`build-plugin-zip.php`)

Script esistente mantenuto per compatibilità, ma si consiglia di usare `build-release-pro.php`.

## Utilizzo

### Da Browser (Amministratori)

1. **Incrementa versione**:
   ```
   https://tuosito.com/wp-content/plugins/born-to-ride-booking/version-manager.php
   ```

2. **Crea build**:
   ```
   https://tuosito.com/wp-content/plugins/born-to-ride-booking/build-release-pro.php
   ```

### Da Linea di Comando

1. **Incrementa versione**:
   ```bash
   # Mostra info versione corrente
   php version-manager.php info
   
   # Incrementa patch version (1.0.62 → 1.0.63)
   php version-manager.php patch "Fix calcolo totali checkout"
   
   # Incrementa minor version (1.0.62 → 1.1.0)
   php version-manager.php minor "Aggiunta gestione coupon"
   
   # Incrementa major version (1.0.62 → 2.0.0)
   php version-manager.php major "Refactoring completo architettura"
   ```

2. **Crea build**:
   ```bash
   php build-release-pro.php
   ```

## Cache Busting

Il sistema implementa diverse strategie per evitare problemi di cache:

### 1. Versioning Consistente
Tutti i file JS/CSS utilizzano `BTR_VERSION` come parametro di versione:
```php
wp_enqueue_script('btr-script', $url, [], BTR_VERSION);
```

### 2. Timestamp per File Critici
I file principali includono anche un timestamp nel build:
```php
wp_enqueue_script('btr-critical', $url . '?v=' . BTR_VERSION . '.' . $timestamp);
```

### 3. File Interessati
- `/includes/class-btr-shortcodes.php`
- `/includes/class-btr-shortcode-anagrafici.php`  
- `/includes/class-btr-frontend-display.php`

## File Esclusi dal Build

### Directory
- `tests/` - Test di sviluppo
- `build/` - Build precedenti
- `.git/`, `.github/` - Controllo versione
- `node_modules/` - Dipendenze npm
- `vendor/` - Dipendenze composer
- `.idea/`, `.vscode/` - File IDE

### File Patterns
- `*.log`, `*.bak`, `*.backup` - File temporanei
- `test-*.php`, `debug-*.php` - Script di test/debug
- `MODIFICHE-*.md`, `TODO*.md` - Documentazione sviluppo
- `*.map` - Source maps
- File di configurazione sviluppo

## Best Practice

1. **Prima di ogni release**:
   - Incrementa la versione con `version-manager.php`
   - Testa le modifiche in locale
   - Crea la build con `build-release-pro.php`

2. **Deployment in produzione**:
   - Carica solo il file ZIP generato
   - Il sistema di versioning forzerà il refresh degli assets
   - Verifica che i file JS/CSS siano aggiornati

3. **Troubleshooting cache**:
   - Controlla che BTR_VERSION sia aggiornata
   - Verifica i parametri ?v= negli URL degli assets
   - Pulisci cache del server/CDN se necessario

## Vantaggi del Nuovo Sistema

1. **Zero problemi di cache**: Versioning automatico garantisce refresh
2. **Build pulite**: Solo file necessari per produzione
3. **Processo standardizzato**: Stesso risultato ogni volta
4. **Documentazione automatica**: CHANGELOG aggiornato automaticamente
5. **Facile da usare**: Interfaccia web o CLI

## Migrazione dal Vecchio Sistema

Se stai usando il vecchio `build-plugin-zip.php`:

1. Usa `version-manager.php` per aggiornare la versione
2. Usa `build-release-pro.php` invece di `build-plugin-zip.php`
3. Il nuovo sistema è retrocompatibile

## Manutenzione

- Le build vecchie vengono eliminate automaticamente (mantiene ultime 5)
- I file temporanei vengono puliti dopo ogni build
- Il sistema verifica automaticamente la struttura del progetto