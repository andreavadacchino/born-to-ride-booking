# Verifica Flusso Frontend → AJAX → Salvataggio Meta

## Invio preventivo (frontend-scripts.js → btr_create_preventivo)
- JS: `assets/js/frontend-scripts.js`
  - Submit form: costruisce `FormData` con camere, anagrafici, extra, breakdown.
  - Aggiunge: `action = btr_create_preventivo`, `nonce = btr_booking_form.nonce` (righe ~4633–4634).
- Localizzazione nonce: `includes/class-btr-shortcodes.php`
  - `wp_localize_script('btr-booking-form-js', 'btr_booking_form', { ajax_url, nonce: wp_create_nonce('btr_booking_form_nonce'), ... })`.
- Handler server: `includes/class-btr-preventivi.php`
  - Verifica nonce: `wp_verify_nonce($_POST['nonce'], 'btr_booking_form_nonce')`.
  - Crea post: `wp_insert_post('btr_preventivi')` e salva meta chiave (cliente, camere, totali, etichette bambini, date, extra, anagrafici).
  - Sanitizzazione: `sanitize_text_field`, `sanitize_email`, cast numerici/float, decode JSON con fallback.
  - Output: `wp_send_json_success` con ID e dati.

Esito: allineamento corretto tra nonce frontend e verifica server per la creazione del preventivo.

## Salvataggio anagrafici (btr_save_anagrafici)
- Template/Form: `templates/admin/btr-form-anagrafici-refactored.php`
  - Campo nonce: `wp_nonce_field('btr_update_anagrafici_nonce','btr_update_anagrafici_nonce_field')`.
  - Hidden `action = btr_save_anagrafici`; form inviato via JS `assets/js/anagrafici-scripts.js` a `btr_anagrafici.ajax_url`.
- Localizzazione anagrafici: `includes/class-btr-shortcode-anagrafici.php`
  - `wp_localize_script('btr-anagrafici-js','btr_anagrafici',{ ajax_url, nonce: wp_create_nonce('btr_update_anagrafici_nonce') })`.
- Handler server 1: `includes/class-btr-shortcode-anagrafici.php`
  - Hook: `wp_ajax_btr_save_anagrafici` (+ nopriv).
  - Verifica nonce: accetta sia il campo `btr_update_anagrafici_nonce_field` sia `nonce` con azione `btr_update_anagrafici_nonce`.
  - Salva meta: `_anagrafici_preventivo`, ricalcolo totali, set in sessione, eventuale redirect.
- Handler server 2: `includes/class-btr-preventivi.php`
  - Hook: `wp_ajax_btr_save_anagrafici` (+ nopriv).
  - Verifica nonce: `wp_verify_nonce($_POST['nonce'], 'btr_save_anagrafici')` (diverso dall’azione usata nel form).

Osservazione critica: conflitto doppio handler sulla stessa azione `btr_save_anagrafici` con schemi di nonce differenti.
- Ordine di inizializzazione: `BTR_Preventivi` viene istanziato prima di `BTR_Anagrafici_Shortcode` → il primo handler può intercettare la richiesta e fallire la verifica, interrompendo la risposta.
- Rimedio suggerito: unificare l’handler (preferibile quello in `class-btr-shortcode-anagrafici.php`) e rimuovere/renominare l’altro hook; in alternativa allineare il controllo nonce in `class-btr-preventivi.php` per accettare `btr_update_anagrafici_nonce_field`.

## Meta salvati (estratto chiavi principali)
- Cliente: `_cliente_nome`, `_cliente_email`, `_cliente_telefono`.
- Preventivo/contesto: `_pacchetto_id`, `_date_ranges`, `_selected_date`, `_tipologia_prenotazione`, `_product_id`, `_variant_id`.
- Partecipanti: `_num_adults`, `_num_children`, `_num_neonati`, `_anagrafici_preventivo`.
- Prezzi/totali: `_totale_camere`, `_prezzo_base`, `_prezzo_totale`, `_totale_preventivo`, `_supplemento_totale`, `_totale_assicurazioni`, `_totale_costi_extra`, `_btr_grand_total`.
- Etichette bambini: `_child_label_f1..f4`, `_child_category_labels`.
- Extra: `_extra_night`, `_extra_night_pp`, `_extra_night_total`, `_btr_extra_night_date`, `_numero_notti_extra`, `_extra_costs_*`.

Valutazione sanitizzazione: coerente sui campi primitivi (text/email/int/float). Array complessi (anagrafici, breakdown) sono normalizzati e ripuliti a livello di elemento.

