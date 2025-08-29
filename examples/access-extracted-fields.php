<?php
/**
 * Esempio di accesso ai campi estratti dal JSON lato PHP
 * 
 * Dopo le modifiche, tutti i dati del booking sono accessibili 
 * direttamente tramite $_POST senza dover fare json_decode
 * 
 * Data: 09/08/2025
 * Versione: 2.2
 */

// Esempio di handler AJAX che riceve i dati
add_action('wp_ajax_btr_create_preventivo', 'handle_booking_submission');
add_action('wp_ajax_nopriv_btr_create_preventivo', 'handle_booking_submission');

function handle_booking_submission() {
    // Verifica nonce
    if (!wp_verify_nonce($_POST['nonce'], 'btr_booking_form_nonce')) {
        wp_die('Nonce verification failed');
    }
    
    // ========================================
    // ACCESSO AI DATI ESTRATTI SINGOLARMENTE
    // ========================================
    
    // 1. METADATA - Informazioni sulla richiesta
    $timestamp = sanitize_text_field($_POST['metadata_timestamp'] ?? '');
    $user_agent = sanitize_text_field($_POST['metadata_user_agent'] ?? '');
    $url = esc_url_raw($_POST['metadata_url'] ?? '');
    
    // 2. PACKAGE - Dati del pacchetto
    $package_id = intval($_POST['pkg_package_id'] ?? 0);
    $product_id = intval($_POST['pkg_product_id'] ?? 0);
    $variant_id = intval($_POST['pkg_variant_id'] ?? 0);
    $nome_pacchetto = sanitize_text_field($_POST['pkg_nome_pacchetto'] ?? '');
    $durata = intval($_POST['pkg_durata'] ?? 0);
    $selected_date = sanitize_text_field($_POST['pkg_selected_date'] ?? '');
    
    // 3. CUSTOMER - Dati cliente principale
    $customer_nome = sanitize_text_field($_POST['customer_nome'] ?? '');
    $customer_email = sanitize_email($_POST['customer_email'] ?? '');
    
    // 4. PARTICIPANTS - Breakdown partecipanti
    $adults = intval($_POST['participants_adults'] ?? 0);
    $infants = intval($_POST['participants_infants'] ?? 0);
    $total_people = intval($_POST['participants_total_people'] ?? 0);
    
    // Bambini per fascia d'età
    $children_f1 = intval($_POST['participants_children_f1'] ?? 0); // 3-6 anni
    $children_f2 = intval($_POST['participants_children_f2'] ?? 0); // 6-8 anni  
    $children_f3 = intval($_POST['participants_children_f3'] ?? 0); // 8-10 anni
    $children_f4 = intval($_POST['participants_children_f4'] ?? 0); // 11-12 anni
    $children_total = intval($_POST['participants_children_total'] ?? 0);
    
    // 5. ANAGRAFICI - Array di partecipanti
    $anagrafici = $_POST['anagrafici'] ?? [];
    $anagrafici_count = intval($_POST['anagrafici_count'] ?? 0);
    
    // Esempio di accesso ai dati anagrafici
    foreach ($anagrafici as $index => $partecipante) {
        $nome = sanitize_text_field($partecipante['nome'] ?? '');
        $cognome = sanitize_text_field($partecipante['cognome'] ?? '');
        $email = sanitize_email($partecipante['email'] ?? '');
        $telefono = sanitize_text_field($partecipante['telefono'] ?? '');
        $data_nascita = sanitize_text_field($partecipante['data_nascita'] ?? '');
        $codice_fiscale = sanitize_text_field($partecipante['codice_fiscale'] ?? '');
        
        // Costi extra selezionati per questo partecipante
        $costi_extra = $partecipante['costi_extra'] ?? [];
        foreach ($costi_extra as $extra_key => $value) {
            // $extra_key contiene il nome del costo extra (es. 'animale-domestico')
            // $value sarà '1' se selezionato
            if ($value === '1') {
                // Costo extra selezionato
                error_log("Partecipante $index ha selezionato: $extra_key");
            }
        }
        
        // Assicurazioni selezionate
        $assicurazioni = $partecipante['assicurazioni'] ?? [];
        foreach ($assicurazioni as $ass_key => $value) {
            if ($value === '1') {
                // Assicurazione selezionata
                error_log("Partecipante $index ha assicurazione: $ass_key");
            }
        }
    }
    
    // 6. PRICING - Totali e breakdown prezzi
    $total_price = floatval($_POST['pricing_total_price'] ?? 0);
    $breakdown_available = $_POST['pricing_breakdown_available'] === '1';
    
    // Totali dettagliati
    $subtotale_prezzi_base = floatval($_POST['pricing_subtotale_prezzi_base'] ?? 0);
    $subtotale_supplementi_base = floatval($_POST['pricing_subtotale_supplementi_base'] ?? 0);
    $subtotale_notti_extra = floatval($_POST['pricing_subtotale_notti_extra'] ?? 0);
    $subtotale_supplementi_extra = floatval($_POST['pricing_subtotale_supplementi_extra'] ?? 0);
    $totale_generale = floatval($_POST['pricing_totale_generale'] ?? 0);
    
    // Breakdown adulti
    $adulti_quantita = intval($_POST['pricing_adulti_quantita'] ?? 0);
    $adulti_prezzo_unitario = floatval($_POST['pricing_adulti_prezzo_unitario'] ?? 0);
    $adulti_totale = floatval($_POST['pricing_adulti_totale'] ?? 0);
    
    // Breakdown bambini F1
    $bambini_f1_quantita = intval($_POST['pricing_bambini_f1_quantita'] ?? 0);
    $bambini_f1_prezzo_unitario = floatval($_POST['pricing_bambini_f1_prezzo_unitario'] ?? 0);
    $bambini_f1_totale = floatval($_POST['pricing_bambini_f1_totale'] ?? 0);
    
    // Info notti extra
    $notti_extra_attive = $_POST['pricing_notti_extra_attive'] === '1';
    $notti_extra_numero = intval($_POST['pricing_notti_extra_numero'] ?? 0);
    $notti_extra_prezzo_adulto = floatval($_POST['pricing_notti_extra_prezzo_adulto'] ?? 0);
    
    // 7. EXTRA NIGHTS - Dettagli
    $extra_nights_enabled = $_POST['extra_nights_enabled'] === '1';
    $extra_nights_price_pp = floatval($_POST['extra_nights_price_per_person'] ?? 0);
    $extra_nights_total = floatval($_POST['extra_nights_total_cost'] ?? 0);
    $extra_nights_date = sanitize_text_field($_POST['extra_nights_date'] ?? '');
    
    // 8. DATES - Date check-in/out
    $check_in = sanitize_text_field($_POST['dates_check_in'] ?? '');
    $check_out = sanitize_text_field($_POST['dates_check_out'] ?? '');
    $extra_night_date = sanitize_text_field($_POST['dates_extra_night'] ?? '');
    
    // 9. ROOMS - Array camere selezionate
    $rooms = $_POST['rooms'] ?? [];
    $rooms_count = intval($_POST['rooms_count'] ?? 0);
    
    foreach ($rooms as $index => $room) {
        $room_type = sanitize_text_field($room['type'] ?? '');
        $room_quantity = intval($room['quantity'] ?? 0);
        $room_capacity = intval($room['capacity'] ?? 0);
        $room_price = floatval($room['price'] ?? 0);
    }
    
    // ========================================
    // ACCESSO AL JSON COMPLETO (OPZIONALE)
    // ========================================
    
    // Il JSON completo è ancora disponibile per backward compatibility
    $booking_data_json = $_POST['booking_data_json'] ?? '';
    if (!empty($booking_data_json)) {
        $booking_data = json_decode(stripslashes($booking_data_json), true);
        
        // Ora hai accesso a tutti i dati anche in formato strutturato
        // Utile per operazioni complesse o per mantenere compatibilità
    }
    
    // ========================================
    // ESEMPIO DI UTILIZZO
    // ========================================
    
    // Crea il preventivo con i dati estratti
    $preventivo_data = array(
        'post_type' => 'preventivi',
        'post_title' => sprintf('Preventivo - %s - %s', $customer_nome, $selected_date),
        'post_status' => 'publish',
        'meta_input' => array(
            '_cliente_nome' => $customer_nome,
            '_cliente_email' => $customer_email,
            '_package_id' => $package_id,
            '_num_adults' => $adults,
            '_num_children_f1' => $children_f1,
            '_num_children_f2' => $children_f2,
            '_num_children_f3' => $children_f3,
            '_num_children_f4' => $children_f4,
            '_num_infants' => $infants,
            '_totale_preventivo' => $totale_generale,
            '_check_in_date' => $check_in,
            '_check_out_date' => $check_out,
            '_extra_nights_enabled' => $extra_nights_enabled,
            '_anagrafici_data' => $anagrafici, // Salva array completo
        )
    );
    
    $preventivo_id = wp_insert_post($preventivo_data);
    
    if (!is_wp_error($preventivo_id)) {
        // Preventivo creato con successo
        
        // Salva dati anagrafici in tabella custom se necessario
        global $wpdb;
        $table_name = $wpdb->prefix . 'btr_quote_participants';
        
        foreach ($anagrafici as $index => $partecipante) {
            $wpdb->insert(
                $table_name,
                array(
                    'quote_id' => $preventivo_id,
                    'nome' => $partecipante['nome'] ?? '',
                    'cognome' => $partecipante['cognome'] ?? '',
                    'email' => $partecipante['email'] ?? '',
                    'telefono' => $partecipante['telefono'] ?? '',
                    'data_nascita' => $partecipante['data_nascita'] ?? '',
                    'codice_fiscale' => $partecipante['codice_fiscale'] ?? '',
                    // Altri campi...
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%s')
            );
        }
        
        // Risposta di successo
        wp_send_json_success(array(
            'message' => 'Preventivo creato con successo',
            'preventivo_id' => $preventivo_id,
            'redirect_url' => get_permalink($preventivo_id)
        ));
    } else {
        // Errore nella creazione
        wp_send_json_error(array(
            'message' => 'Errore nella creazione del preventivo',
            'error' => $preventivo_id->get_error_message()
        ));
    }
}

// ========================================
// VANTAGGI DI QUESTO APPROCCIO
// ========================================

/*
1. **Accesso diretto ai dati**: Non serve più fare json_decode() per accedere ai campi
2. **Type safety**: Puoi validare e sanitizzare ogni campo individualmente
3. **Performance**: Eviti il parsing JSON per campi frequentemente usati
4. **Debugging facile**: Puoi vedere tutti i campi nel $_POST array
5. **Backward compatible**: Il JSON completo è ancora disponibile se serve
6. **WordPress best practice**: Usa i pattern standard di WordPress per form handling
7. **Array PHP-friendly**: Gli array sono accessibili con la notazione standard PHP
8. **Sicurezza**: Ogni campo può essere sanitizzato con le funzioni appropriate
*/
?>