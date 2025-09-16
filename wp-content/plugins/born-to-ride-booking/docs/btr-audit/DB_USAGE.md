# Uso Database

## Tabelle custom
- Installer: `includes/class-btr-database-auto-installer.php`
  - `wp_btr_payment_plans` (piani pagamento)
  - `wp_btr_group_payments` (pagamenti individuali gruppo)
  - `wp_btr_payment_reminders` (promemoria)
- Manager dedicato: `includes/class-btr-database-manager.php` per `wp_btr_order_shares` (dbDelta, CRUD, cache transiente).

## Query e sicurezza
- Prepared statements: uso esteso di `$wpdb->prepare()` per SELECT/UPDATE/DELETE.
  - Esempi: `includes/class-btr-payment-ajax.php:300`, `.../payment-integration.php:173,183,189,214`.
- Insert/Update: `$wpdb->insert()`/`$wpdb->update()` con formati espliciti.
- Indici: presenti nei ddl (`payment_hash`, `preventivo_id`, status, scadenze) per query cron e lookup rapidi.

## Punti di attenzione
- Alcuni `SELECT *` nei cron/report (es. `payment-cron.php:294`) → valutare colonne mirate.
- Transazioni: `process_payment()` usa `START TRANSACTION`/`COMMIT` (compatibilità DB host da verificare).
- Pulizia dati: `BTR_Payment_Security::validate_*` e sanitizzazione input prima di interrogare il DB.

## Migrazioni
- Auto‑installer su `plugins_loaded`/`admin_init` + `register_activation_hook()`; file `includes/db-updates/` per step incrementali.
- Versionamento opzione: `btr_db_version`, `btr_order_shares_db_version`.

