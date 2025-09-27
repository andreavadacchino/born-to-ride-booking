# Endpoint & I/O Inventory

## AJAX (admin/front)
- btr_create_preventivo (nopriv): `includes/class-btr-preventivi.php:15,229`
  - Nonce: `nonce` con `wp_verify_nonce('btr_booking_form_nonce')`
  - Input: dati cliente, camere, partecipanti; sanitizzazione `sanitize_*`, JSON decode sicuro.
- Pagamenti (frontend): `includes/class-btr-payment-ajax.php`
  - btr_create_payment_plan (nopriv): nonce `btr_payment_plan_nonce`, sanitizzazione + rate‑limit.
  - btr_process_group_payment (nopriv): validazione `BTR_Payment_Security::validate_group_checkout()` + creazione ordine WC.
  - btr_check_payment_status (nopriv): nonce `btr_check_payment_nonce`, query sicure su hash.
  - Admin: btr_send_payment_reminder, btr_get_payment_stats, btr_update_payment_note → `current_user_can` + nonce `btr_payment_admin_nonce`.
- Group Payments: `includes/class-btr-group-payments.php`
  - btr_generate_group_payment_links, btr_generate_individual_payment_link, btr_send_payment_email, btr_process_individual_payment (varie; uso `check_ajax_referer('btr_group_payments')` dove previsto).
- Date ranges admin: `includes/class-btr-ajax-handlers.php`
  - btr_save_date_range, btr_get_package_date_ranges → nonce `btr_ajax_nonce` + `current_user_can('edit_posts')`.

## REST API
- Namespace `btr/v1` in `includes/class-btr-payment-rest-controller.php`
  - GET `/payments/{payment_hash}` → `permission_callback: payment_permissions_check` (valida hash + rate‑limit).
  - POST `/payments/{payment_hash}/process` → nonce `btr_payment_process_{hash}`, transazione DB.
  - GET `/payments/{payment_hash}/status` → `permission_callback` come sopra.
  - POST `/payments/{payment_hash}/webhook` → `permission_callback: webhook_permissions_check` (firma header).
  - POST `/payments/validate` → pubblico (`__return_true`) ma valida formato hash e stato.

## Form handler (admin_post)
- Nessun handler `admin_post_*` rilevato.

## DB layer e tabelle
- Installer: `includes/class-btr-database-auto-installer.php`
  - Tabelle: `wp_btr_payment_plans`, `wp_btr_group_payments`, `wp_btr_payment_reminders`.
- Manager: `includes/class-btr-database-manager.php` per `wp_btr_order_shares` (CRUD, cache, dbDelta).
- Query: uso esteso di `$wpdb->prepare()` e `->insert()/->update()` per operazioni su `btr_group_payments` e correlate.

## File/Path I/O
- Scritture file: `includes/class-btr-pdf-generator.php` (PDF/htaccess), `includes/class-btr-email-template-manager.php` (template/config JSON), file admin build/release.
- Upload diretti non rilevati; TCPDF usa `fopen/unlink` su path noti.

## HTTP esterni
- `includes/class-btr-gateway-api-manager.php`: `wp_remote_request/post` verso gateway pagamento; webhook `php://input`.

