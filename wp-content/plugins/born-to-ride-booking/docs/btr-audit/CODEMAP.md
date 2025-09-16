# Mappa Codice e Architettura

## Struttura del progetto (plugin)
- Radice: `wp-content/plugins/born-to-ride-booking/`
  - `born-to-ride-booking.php`: bootstrap, include classi, init, DB installer.
  - `includes/`: logica core (CPT, preventivi, pagamenti, REST, cron, DB).
  - `admin/`: UI amministrazione, menu, pagine impostazioni, diagnostica.
  - `assets/`, `templates/`, `docs/`, `tests/`, `bin/`.

## Moduli e pattern principali
- Preventivi: `BTR_Preventivi` (AJAX `btr_create_preventivo`, salvataggi meta, shortcode riepilogo).
- Pagamenti: `BTR_Payment_Plans`, `BTR_Group_Payments`, `BTR_Payment_Ajax`, `BTR_Payment_Integration`, `BTR_Payment_REST_Controller`.
- DB: `BTR_Database_Auto_Installer` (tabelle `btr_payment_plans`, `btr_group_payments`, `btr_payment_reminders`), `BTR_Database_Manager` (tabella `btr_order_shares`).
- WooCommerce sync: `BTR_WooCommerce_Sync`, integrazioni depositi/saldi, gateway API manager.
- UI/Shortcode: `BTR_Shortcodes`, `BTR_Anagrafici_Shortcode`, `BTR_WPBakery_Modules`.
- Cron: `BTR_Payment_Cron`, `BTR_Payment_Cron_Manager`, `BTR_Webhook_Queue_Manager`, `BTR_Cron`.
- Sicurezza: `BTR_Payment_Security` (nonce, rate‑limit, permission, log).

Pattern architetturali
- Singleton dove necessario (es. `get_instance()`), Manager/Service per sottosistemi, classi focalizzate per AJAX/REST.

## Inventario funzionale
- CPT/Taxonomie: `register_post_type('btr_preventivi','btr_pacchetti')`, taxonomie in `class-btr-taxonomies.php` e `class-btr-woocommerce-sync.php` (attributi prodotto).
- Shortcode: `btr_riepilogo_preventivo`, `btr_payment_selection`, `btr_payment_links_summary`, `btr_pacchetto_singolo`.
- Admin pages: gestite in `admin/class-btr-*.php` (menu manager, settings gateway, dashboard); screens per preventivi e pagamenti.
- Impostazioni/Opzioni: uso diffuso di `get_option/update_option` (es. pagina riepilogo link pagamenti).
- Cron: hook `btr_*` pianificati (invio reminder, expire, cleanup, webhook retry).
- WP‑CLI: non rilevato.

## Dipendenze esterne
- Core WP/WooCommerce API, REST API (`register_rest_route`), AJAX, rewrite rules.
- Librerie: TCPDF in `lib/tcpdf/` per PDF.

Riferimenti rapidi (file:line)
- REST: `includes/class-btr-payment-rest-controller.php:62,72,82,92,102`.
- AJAX preventivo: `includes/class-btr-preventivi.php:15,229`.
- AJAX pagamenti: `includes/class-btr-payment-ajax.php` (nonce/capability vari handler).
- Cron: `includes/class-btr-payment-cron-manager.php:86-107`, `.../payment-cron.php:66-93`.

