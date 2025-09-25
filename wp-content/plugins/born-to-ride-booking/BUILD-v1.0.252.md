# Build del Plugin Born to Ride Booking v1.0.252

## Pacchetti prodotti
- **Pacchetto principale**: `born-to-ride-booking.zip`
- **Pacchetto versionato**: `born-to-ride-booking-v1.0.252.zip`
- **Dimensione**: 52.06 MB
- **Percorso**: `wp-content/plugins/born-to-ride-booking/`

## Contenuto incluso
- File principali del plugin (`born-to-ride-booking.php`, directory `includes/`, `admin/`, `templates/`, `assets/`, `lib/`)
- Configurazione aggiornamenti (`updater-config.json`, `.distignore`)
- Nuovi stili per dashboard e partecipanti nella selezione pagamento di gruppo
- Zip generati con lo script `build-plugin-zip.php` aggiornato alla versione 1.0.252

## Esclusioni dal pacchetto
- File di sviluppo e diagnostica (`*.bak`, script di test, helper interni)
- Dipendenze di sviluppo (`node_modules/`, `composer.json`, `vendor/`)
- Documentazione temporanea e configurazioni IDE (`docs/*` non essenziali, `.idea/`, `.vscode/`)
- Script di build e file di test (`build-plugin-zip.php`, `phpunit.xml*`, test manuali)

## Note di rilascio
- Restyling minimale del riepilogo pagamenti di gruppo in stile “twilight”
- Riorganizzazione delle card partecipanti con layout responsive a griglia
- Aggiornamento versione interna (`BTR_VERSION`, `package.json`) a 1.0.252
- Build script allineato alla nuova versione

## Test eseguiti
- `php -l wp-content/plugins/born-to-ride-booking/templates/payment-selection-page-riepilogo-style.php`

## Deploy in produzione
1. Effettuare un backup completo (file + database)
2. Disattivare temporaneamente plugin di cache/CDN
3. Caricare `born-to-ride-booking-v1.0.252.zip` da **Plugin > Aggiungi nuovo > Carica plugin**
4. Attivare e verificare flusso di prenotazione e pagamenti di gruppo
5. Controllare i log di errore (PHP e browser) durante un checkout di prova
6. Riabilitare cache/CDN
