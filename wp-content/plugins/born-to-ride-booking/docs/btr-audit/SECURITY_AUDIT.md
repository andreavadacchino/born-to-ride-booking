# Security Audit (OWASP/WP)

## Riepilogo
- Stato generale: Medium. Buona copertura nonce/permessi; migliorabile hardening e minimizzazione dati.

## Evidenze principali
- CSRF/Nonce
  - OK: `btr_create_preventivo` verifica nonce (`class-btr-preventivi.php:173` circa).
  - OK: AJAX pagamenti con `check_ajax_referer`/`wp_verify_nonce` (es. `class-btr-payment-ajax.php:24,329,359,420`).
  - OK: Admin AJAX date ranges (`class-btr-ajax-handlers.php:93`).
- Capability
  - OK: Admin handler con `current_user_can('manage_options'|'edit_posts'|'edit_post', $id)` diffusi (es. `class-btr-payment-gateway-ajax.php:30,177,266`).
  - Attenzione: endpoint nopriv (es. `btr_create_payment_plan`, `btr_process_group_payment`) affidano l’autorizzazione a nonce/hash → accettabile, ma richiede nonce forte e TTL breve.
- XSS
  - Output admin usa `esc_html` in molte viste; comunque verificare tutte le `echo` dei template `templates/`.
  - Email/template manager scrive file HTML: assicurare escaping contenuti dinamici prima del render.
- SQLi
  - Uso esteso di `$wpdb->prepare()` e formati in `insert/update` (es. `class-btr-payment-integration.php:172+`).
  - Alcuni `SELECT *` per report/cron: basso rischio ma migliorabile.
- File inclusion/Path traversal
  - Inclusioni con path costanti `BTR_PLUGIN_DIR`; nessun input utente usato per require.
  - Scritture file: `class-btr-email-template-manager.php` e `class-btr-pdf-generator.php` in percorsi controllati.
- SSRF/HTTP
  - Gateway: `BTR_Gateway_API_Manager` usa `wp_remote_request/post` (timeout/ssl? da verificare), valida firma webhook.
- REST `permission_callback`
  - `/payments/*`: `payment_permissions_check` valida hash e rate‑limit; `/validate` è pubblico ma valida input.
- PII/Log
  - `BTR_Payment_Security::log_security_event()` scrive in `wp-content/btr-security.log` (maschera email/CF) → controllare permessi del file e rotazione.

## Raccomandazioni
- Aumentare TTL/entropia dei nonce pubblici e documentarne la durata.
- Aggiungere `cap` minima per admin AJAX non critici (es. `edit_posts`).
- Ridurre `SELECT *` in cron/report a colonne necessarie.
- Assicurare `timeout`, `sslverify` e gestione errori su HTTP esterni.
- Riesaminare escaping nei template frontend (`templates/*.php`).

