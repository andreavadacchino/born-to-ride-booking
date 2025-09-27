# Flussi Critici: Preventivo e Pagamenti

## Creazione Preventivo (AJAX)
1) Input (front): form booking con nonce `btr_booking_form_nonce` → POST `btr_create_preventivo`.
2) Validazione: `wp_verify_nonce`, `sanitize_text_field/email/int`, decode JSON camere/participants.
3) Logica: calcolo partecipanti (correzione fasce bambini), determinazione prezzi camere e supplementi.
4) Persistenza: `wp_insert_post('btr_preventivi')` + `update_post_meta` per totali, etichette bambini, anagrafica.
5) Output: `wp_send_json_success` con ID preventivo e dati riepilogo.

Call graph (estratto)
- `BTR_Preventivi::create_preventivo()` → `determine_number_of_persons()` → lettura meta WooCommerce → somma totali → salva meta.

ASCII sequence
Front form → [Nonce OK?] → Sanitize → Calcoli → Crea post → Salva meta → Response JSON

## Piano di Pagamento e Link Gruppo
1) Dopo salvataggio anagrafici: hook `btr_after_save_anagrafici` → `BTR_Payment_Plans::maybe_show_payment_plan_selection()`.
2) AJAX `btr_save_payment_plan` (nonce) → `create_payment_plan()` su `wp_btr_payment_plans`.
3) AJAX `btr_generate_group_split_links` (nonce) → `BTR_Payment_Plans::generate_group_payment_links()` o `BTR_Group_Payments::generate_group_payment_links()` → inserisce in `wp_btr_group_payments` + crea URL.

## Pagamento Individuale (REST)
1) GET `/btr/v1/payments/{hash}`: controlli `payment_permissions_check`, rate‑limit, stato, dettagli ordine.
2) POST `/process`: verifica nonce `btr_payment_process_{hash}` + transazione DB → crea/aggiorna intent via `BTR_Gateway_API_Manager` o fallback WC.
3) Webhook `/webhook`: firma `x-webhook-signature`, idempotenza, coda retry (`BTR_Webhook_Queue_Manager`).

## Integrazione WooCommerce
- Alla scelta caparra/saldo: set di chiavi in `WC()->session` per flusso checkout.
- Creazione ordine gruppo: `BTR_Payment_Ajax::create_wc_order_for_payment()` con meta `_btr_*`.

