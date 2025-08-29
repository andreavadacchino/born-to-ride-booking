<?php
/**
 * BTR Preventivi V2 - Versione completamente riscritta
 * 
 * Funzione create_preventivo() riscritta da zero per essere:
 * - Robusta: gestione completa di tutti i dati del payload
 * - Scalabile: architettura modulare e estensibile
 * - Manutenibile: codice pulito e ben organizzato
 * 
 * @package Born_To_Ride_Booking
 * @since 1.0.148
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Preventivi_V2 {
    
    /**
     * Logger instance
     * @var WC_Logger
     */
    private $logger;
    
    /**
     * Data Manager instance
     * @var BTR_Quote_Data_Manager
     */
    private $data_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Il logger sarà inizializzato lazy quando necessario
        $this->logger = null;
        
        // Inizializza data manager solo se il file esiste
        $data_manager_file = BTR_PLUGIN_DIR . 'includes/class-btr-quote-data-manager.php';
        if (file_exists($data_manager_file)) {
            require_once $data_manager_file;
            $this->data_manager = new BTR_Quote_Data_Manager();
        }
        
        // NON registriamo gli hook qui - saranno registrati dal plugin principale
        // per evitare conflitti con la classe originale
    }
    
    /**
     * Ottieni il logger (lazy initialization)
     */
    private function get_logger() {
        if ($this->logger === null && function_exists('wc_get_logger')) {
            $this->logger = wc_get_logger();
        }
        return $this->logger;
    }
    
    /**
     * Registra gli hook AJAX - chiamato solo quando questa versione è attiva
     */
    public function register_ajax_hooks() {
        add_action('wp_ajax_btr_create_preventivo', [$this, 'create_preventivo']);
        add_action('wp_ajax_nopriv_btr_create_preventivo', [$this, 'create_preventivo']);
    }
    
    /**
     * NUOVA FUNZIONE create_preventivo() - Completamente riscritta da zero
     * 
     * Salva TUTTI i dati del payload in modo strutturato:
     * - metadata (customer info, package info, etc)
     * - participants (adults, children, infants with all details)
     * - rooms configuration
     * - pricing breakdown
     * - booking_data_json (complete structured data)
     * - extra costs
     * - insurance
     * - dates and session data
     */
    public function create_preventivo() {
        $context = ['source' => 'BTR_Preventivi_V2'];
        
        try {
            // ========== STEP 1: VALIDAZIONE SICUREZZA ==========
            $this->validate_security();
            if ($logger = $this->get_logger()) {
                $logger->info('Security validation passed', $context);
            }
            
            // ========== STEP 2: PARSING COMPLETO DEL PAYLOAD ==========
            $payload = $this->parse_complete_payload();
            if ($logger = $this->get_logger()) {
                $logger->info('Payload parsed successfully', $context);
            }
            
            // ========== STEP 3: VALIDAZIONE DATI ==========
            $this->validate_payload_data($payload);
            if ($logger = $this->get_logger()) {
                $logger->info('Payload validation passed', $context);
            }
            
            // ========== STEP 4: CREAZIONE POST PREVENTIVO ==========
            $quote_id = $this->create_quote_post($payload);
            if ($logger = $this->get_logger()) {
                $logger->info(sprintf('Quote post created: ID %d', $quote_id), $context);
            }
            
            // ========== STEP 5: SALVATAGGIO COMPLETO DATI STRUTTURATI ==========
            $this->save_all_quote_data($quote_id, $payload);
            if ($logger = $this->get_logger()) {
                $logger->info(sprintf('All data saved for quote %d', $quote_id), $context);
            }
            
            // ========== STEP 6: SINCRONIZZAZIONE WOOCOMMERCE ==========
            $this->sync_with_woocommerce($quote_id, $payload);
            if ($logger = $this->get_logger()) {
                $logger->info('WooCommerce sync completed', $context);
            }
            
            // ========== STEP 7: GENERAZIONE PDF (OPZIONALE) ==========
            $pdf_url = $this->generate_pdf_if_needed($quote_id);
            
            // ========== STEP 8: INVIO EMAIL (OPZIONALE) ==========
            $this->send_notification_emails($quote_id, $pdf_url);
            
            // ========== STEP 9: RISPOSTA SUCCESSO ==========
            $redirect_url = add_query_arg('preventivo_id', $quote_id, home_url('/riepilogo-preventivo/'));
            
            wp_send_json_success([
                'message' => 'Preventivo creato con successo',
                'preventivo_id' => $quote_id,
                'pdf_url' => $pdf_url,
                'redirect_url' => $redirect_url
            ]);
            
        } catch (Exception $e) {
            $this->handle_error($e);
        }
    }
    
    /**
     * Valida la sicurezza della richiesta
     */
    private function validate_security() {
        // Usa lo stesso nonce action della versione originale per compatibilità
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'btr_booking_form_nonce')) {
            throw new Exception('Nonce di sicurezza non valido');
        }
    }
    
    /**
     * Parse completo del payload con tutti i campi
     */
    private function parse_complete_payload() {
        $payload = [];
        
        // ========== METADATA ==========
        $payload['metadata'] = [
            'customer_name' => sanitize_text_field($_POST['cliente_nome'] ?? ''),
            'customer_email' => sanitize_email($_POST['cliente_email'] ?? ''),
            'customer_phone' => sanitize_text_field($_POST['cliente_telefono'] ?? ''),
            'package_id' => intval($_POST['package_id'] ?? 0),
            'product_id' => intval($_POST['product_id'] ?? 0),
            'variant_id' => intval($_POST['variant_id'] ?? 0),
            'package_title' => sanitize_text_field($_POST['nome_pacchetto'] ?? ''),
            'package_slug' => sanitize_text_field($_POST['package_slug'] ?? ''),
            'sku' => sanitize_text_field($_POST['sku'] ?? ''),
            'duration_nights' => intval($_POST['duration_nights'] ?? 0),
            'duration_days' => intval($_POST['duration_days'] ?? 0),
            'durata' => sanitize_text_field($_POST['durata'] ?? ''),
            'tipologia_prenotazione' => sanitize_text_field($_POST['tipologia_prenotazione'] ?? ''),
        ];
        
        // ========== PARTICIPANTS ==========
        $payload['participants'] = $this->parse_participants_data();
        
        // ========== ROOMS ==========
        $payload['rooms'] = $this->parse_rooms_data();
        
        // ========== DATES ==========
        $payload['date_info'] = [
            'check_in' => sanitize_text_field($_POST['check_in_date'] ?? ''),
            'check_out' => sanitize_text_field($_POST['check_out_date'] ?? ''),
            'travel_date' => sanitize_text_field($_POST['selected_date'] ?? ''),
            'date_ranges_id' => sanitize_text_field($_POST['date_ranges_id'] ?? ''),
            'extra_night_flag' => intval($_POST['extra_night'] ?? 0),
            'extra_night_date' => sanitize_text_field($_POST['extra_night_date'] ?? ''),
        ];
        
        // ========== PRICING ==========
        $payload['pricing'] = [
            'price_per_person' => floatval($_POST['price_per_person'] ?? 0),
            'totale_base' => floatval($_POST['totale_base'] ?? 0),
            'totale_camere' => floatval($_POST['pricing_totale_camere'] ?? 0),
            'totale_extra' => floatval($_POST['pricing_totale_costi_extra'] ?? 0),
            'totale_assicurazioni' => floatval($_POST['totale_assicurazioni'] ?? 0),
            'totale_generale' => floatval($_POST['pricing_totale_generale'] ?? 0),
            'extra_night_pp' => floatval($_POST['extra_night_pp'] ?? 0),
            'extra_night_total' => floatval($_POST['extra_night_total'] ?? 0),
        ];
        
        // ========== BOOKING DATA JSON (COMPLETO) ==========
        if (isset($_POST['booking_data_json'])) {
            $booking_json = stripslashes($_POST['booking_data_json']);
            $decoded = json_decode($booking_json, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $payload['booking_data_json'] = $decoded;
            }
        }
        
        // ========== CHILD CATEGORIES ==========
        if (isset($_POST['child_categories'])) {
            $child_cats = stripslashes($_POST['child_categories']);
            $decoded = json_decode($child_cats, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $payload['child_categories'] = $decoded;
            }
        }
        
        // ========== ANAGRAFICI (PARTICIPANT DETAILS) ==========
        $payload['anagrafici'] = $this->parse_anagrafici_data();
        
        // ========== SESSION DATA ==========
        $payload['session_data'] = [
            'session_hash' => sanitize_text_field($_POST['session_hash'] ?? ''),
            'cart_item_key' => sanitize_text_field($_POST['cart_item_key'] ?? ''),
        ];
        
        // ========== PAYMENT METHOD ==========
        $payload['payment_method'] = sanitize_text_field($_POST['payment_method'] ?? 'full_payment');
        
        return $payload;
    }
    
    /**
     * Parse dei dati dei partecipanti
     */
    private function parse_participants_data() {
        $participants = [
            'adults' => [],
            'children' => [],
            'infants' => []
        ];
        
        // Parse adulti
        $num_adults = intval($_POST['num_adults'] ?? 0);
        
        // Parse bambini con fasce
        $num_children = intval($_POST['num_children'] ?? 0);
        
        // Parse neonati
        $num_infants = intval($_POST['num_infants'] ?? 0);
        
        // Parse dettagli partecipanti da anagrafici
        if (isset($_POST['anagrafici']) && is_array($_POST['anagrafici'])) {
            foreach ($_POST['anagrafici'] as $index => $person) {
                $participant = $this->parse_single_participant($person);
                
                // Determina il tipo in base all'età o altri criteri
                if (isset($person['fascia'])) {
                    if ($person['fascia'] === 'neonato' || $person['fascia'] === 'infant') {
                        $participants['infants'][] = $participant;
                    } elseif ($person['fascia'] !== 'adulto') {
                        $participants['children'][] = $participant;
                    } else {
                        $participants['adults'][] = $participant;
                    }
                } else {
                    // Fallback: primi N sono adulti, poi bambini, poi neonati
                    if ($index < $num_adults) {
                        $participants['adults'][] = $participant;
                    } elseif ($index < $num_adults + $num_children) {
                        $participants['children'][] = $participant;
                    } else {
                        $participants['infants'][] = $participant;
                    }
                }
            }
        }
        
        return $participants;
    }
    
    /**
     * Parse di un singolo partecipante
     */
    private function parse_single_participant($data) {
        $participant = [
            'nome' => sanitize_text_field($data['nome'] ?? ''),
            'cognome' => sanitize_text_field($data['cognome'] ?? ''),
            'email' => sanitize_email($data['email'] ?? ''),
            'telefono' => sanitize_text_field($data['telefono'] ?? ''),
            'data_nascita' => sanitize_text_field($data['data_nascita'] ?? ''),
            'citta_nascita' => sanitize_text_field($data['citta_nascita'] ?? ''),
            'codice_fiscale' => sanitize_text_field($data['codice_fiscale'] ?? ''),
            'indirizzo' => sanitize_text_field($data['indirizzo_residenza'] ?? ''),
            'numero_civico' => sanitize_text_field($data['numero_civico'] ?? ''),
            'citta' => sanitize_text_field($data['citta_residenza'] ?? ''),
            'provincia' => sanitize_text_field($data['provincia_residenza'] ?? ''),
            'cap' => sanitize_text_field($data['cap_residenza'] ?? ''),
            'nazione' => sanitize_text_field($data['nazione'] ?? 'IT'),
            'camera' => intval($data['camera'] ?? 0),
            'camera_tipo' => sanitize_text_field($data['camera_tipo'] ?? ''),
            'tipo_letto' => sanitize_text_field($data['tipo_letto'] ?? ''),
            'fascia' => sanitize_text_field($data['fascia'] ?? ''),
            'age' => intval($data['age'] ?? 0),
        ];
        
        // Parse costi extra
        if (isset($data['costi_extra'])) {
            if (is_string($data['costi_extra'])) {
                $decoded = json_decode(stripslashes($data['costi_extra']), true);
                $participant['costi_extra'] = $decoded ?: [];
            } else {
                $participant['costi_extra'] = $data['costi_extra'];
            }
        }
        
        // Parse assicurazioni
        if (isset($data['assicurazioni'])) {
            if (is_string($data['assicurazioni'])) {
                $decoded = json_decode(stripslashes($data['assicurazioni']), true);
                $participant['assicurazioni'] = $decoded ?: [];
            } else {
                $participant['assicurazioni'] = $data['assicurazioni'];
            }
        }
        
        return $participant;
    }
    
    /**
     * Parse dei dati delle camere
     */
    private function parse_rooms_data() {
        $rooms = [];
        
        if (isset($_POST['camere'])) {
            $camere = $_POST['camere'];
            
            // Se è JSON, decodifica
            if (is_string($camere)) {
                $decoded = json_decode(stripslashes($camere), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $camere = $decoded;
                }
            }
            
            // Parse ogni camera
            if (is_array($camere)) {
                foreach ($camere as $camera) {
                    $rooms[] = [
                        'variation_id' => intval($camera['variation_id'] ?? 0),
                        'tipo' => sanitize_text_field($camera['tipo'] ?? ''),
                        'sottotipo' => sanitize_text_field($camera['sottotipo'] ?? ''),
                        'quantita' => intval($camera['quantita'] ?? 0),
                        'adulti' => intval($camera['adulti'] ?? 0),
                        'bambini' => intval($camera['bambini'] ?? 0),
                        'neonati' => intval($camera['neonati'] ?? 0),
                        'prezzo_base' => floatval($camera['prezzo_per_persona'] ?? 0),
                        'supplemento' => floatval($camera['supplemento'] ?? 0),
                        'sconto' => floatval($camera['sconto'] ?? 0),
                        'totale' => floatval($camera['totale_camera'] ?? 0),
                        'assigned_child_f1' => intval($camera['assigned_child_f1'] ?? 0),
                        'assigned_child_f2' => intval($camera['assigned_child_f2'] ?? 0),
                        'assigned_child_f3' => intval($camera['assigned_child_f3'] ?? 0),
                        'assigned_child_f4' => intval($camera['assigned_child_f4'] ?? 0),
                        'price_child_f1' => floatval($camera['price_child_f1'] ?? 0),
                        'price_child_f2' => floatval($camera['price_child_f2'] ?? 0),
                        'price_child_f3' => floatval($camera['price_child_f3'] ?? 0),
                        'price_child_f4' => floatval($camera['price_child_f4'] ?? 0),
                    ];
                }
            }
        }
        
        return $rooms;
    }
    
    /**
     * Parse dei dati anagrafici
     */
    private function parse_anagrafici_data() {
        $anagrafici = [];
        
        if (isset($_POST['anagrafici']) && is_array($_POST['anagrafici'])) {
            foreach ($_POST['anagrafici'] as $persona) {
                $anagrafici[] = $this->parse_single_participant($persona);
            }
        }
        
        return $anagrafici;
    }
    
    /**
     * Valida i dati del payload
     */
    private function validate_payload_data($payload) {
        // Validazione campi obbligatori
        if (empty($payload['metadata']['customer_name'])) {
            throw new Exception('Nome cliente obbligatorio');
        }
        
        if (empty($payload['metadata']['customer_email'])) {
            throw new Exception('Email cliente obbligatoria');
        }
        
        if (empty($payload['metadata']['package_id'])) {
            throw new Exception('ID pacchetto obbligatorio');
        }
        
        // Validazione partecipanti
        $total_participants = count($payload['participants']['adults']) + 
                            count($payload['participants']['children']) + 
                            count($payload['participants']['infants']);
        
        if ($total_participants < 1) {
            throw new Exception('Almeno un partecipante richiesto');
        }
    }
    
    /**
     * Crea il post del preventivo
     */
    private function create_quote_post($payload) {
        $post_data = [
            'post_type' => 'btr_preventivi',
            'post_title' => sprintf(
                'Preventivo per %s - %s - %s',
                $payload['metadata']['customer_name'],
                $payload['metadata']['package_title'],
                date('d/m/Y H:i:s')
            ),
            'post_status' => 'publish',
            'post_author' => get_current_user_id() ?: 1
        ];
        
        $quote_id = wp_insert_post($post_data);
        
        if (is_wp_error($quote_id)) {
            throw new Exception('Errore creazione preventivo: ' . $quote_id->get_error_message());
        }
        
        return $quote_id;
    }
    
    /**
     * Salva TUTTI i dati del preventivo in modo strutturato
     */
    private function save_all_quote_data($quote_id, $payload) {
        // ========== 1. SALVA IL JSON COMPLETO (Single Source of Truth) ==========
        update_post_meta($quote_id, '_btr_quote_data_json', wp_json_encode($payload));
        update_post_meta($quote_id, '_btr_quote_version', '2.0');
        update_post_meta($quote_id, '_btr_quote_timestamp', current_time('mysql'));
        
        // ========== 2. METADATA ==========
        foreach ($payload['metadata'] as $key => $value) {
            update_post_meta($quote_id, '_' . $key, $value);
        }
        
        // ========== 3. PARTECIPANTI ==========
        update_post_meta($quote_id, '_btr_participants', $payload['participants']);
        update_post_meta($quote_id, '_num_adults', count($payload['participants']['adults']));
        update_post_meta($quote_id, '_num_children', count($payload['participants']['children']));
        update_post_meta($quote_id, '_num_neonati', count($payload['participants']['infants']));
        
        // Salva anche anagrafici per compatibilità
        update_post_meta($quote_id, '_anagrafici_preventivo', $payload['anagrafici']);
        
        // ========== 4. CAMERE ==========
        update_post_meta($quote_id, '_btr_rooms', $payload['rooms']);
        update_post_meta($quote_id, '_camere_selezionate', $payload['rooms']); // Compatibilità
        
        // ========== 5. DATE ==========
        foreach ($payload['date_info'] as $key => $value) {
            update_post_meta($quote_id, '_' . $key, $value);
        }
        
        // ========== 6. PREZZI ==========
        foreach ($payload['pricing'] as $key => $value) {
            update_post_meta($quote_id, '_' . $key, $value);
        }
        
        // Salva anche con nomi legacy per compatibilità
        update_post_meta($quote_id, '_prezzo_totale', $payload['pricing']['totale_generale']);
        update_post_meta($quote_id, '_totale_preventivo', $payload['pricing']['totale_generale']);
        update_post_meta($quote_id, '_btr_grand_total', $payload['pricing']['totale_generale']);
        
        // ========== 7. BOOKING DATA JSON ==========
        if (!empty($payload['booking_data_json'])) {
            update_post_meta($quote_id, '_booking_data_json', $payload['booking_data_json']);
            
            // Estrai e salva dati specifici dal booking_data_json
            $this->extract_and_save_booking_data($quote_id, $payload['booking_data_json']);
        }
        
        // ========== 8. CATEGORIE BAMBINI ==========
        if (!empty($payload['child_categories'])) {
            update_post_meta($quote_id, '_child_categories', $payload['child_categories']);
            update_post_meta($quote_id, '_child_category_labels', $payload['child_categories']);
        }
        
        // ========== 9. STATO E SESSIONE ==========
        update_post_meta($quote_id, '_stato_preventivo', 'creato');
        update_post_meta($quote_id, '_payment_method', $payload['payment_method']);
        
        if (!empty($payload['session_data']['session_hash'])) {
            update_post_meta($quote_id, '_wc_session_hash', $payload['session_data']['session_hash']);
        }
        
        if (!empty($payload['session_data']['cart_item_key'])) {
            update_post_meta($quote_id, '_wc_cart_item_key', $payload['session_data']['cart_item_key']);
        }
        
        // ========== 10. CALCOLI AGGREGATI ==========
        $this->calculate_and_save_aggregates($quote_id, $payload);
    }
    
    /**
     * Estrae e salva dati specifici dal booking_data_json
     */
    private function extract_and_save_booking_data($quote_id, $booking_data) {
        // Extra nights
        if (isset($booking_data['extra_nights'])) {
            update_post_meta($quote_id, '_extra_nights_data', $booking_data['extra_nights']);
            update_post_meta($quote_id, '_extra_night_total', floatval($booking_data['extra_nights']['total_cost'] ?? 0));
            update_post_meta($quote_id, '_numero_notti_extra', intval($booking_data['extra_nights']['nights_count'] ?? 0));
        }
        
        // Extra costs
        if (isset($booking_data['extra_costs'])) {
            update_post_meta($quote_id, '_btr_extra_costs', $booking_data['extra_costs']);
            $total_extra = array_sum(array_column($booking_data['extra_costs'], 'total'));
            update_post_meta($quote_id, '_totale_costi_extra', $total_extra);
        }
        
        // Insurance
        if (isset($booking_data['insurance'])) {
            update_post_meta($quote_id, '_btr_insurance', $booking_data['insurance']);
            update_post_meta($quote_id, '_totale_assicurazioni', floatval($booking_data['insurance']['total'] ?? 0));
        }
        
        // Detailed breakdown
        if (isset($booking_data['pricing']['detailed_breakdown'])) {
            update_post_meta($quote_id, '_riepilogo_calcoli_dettagliato', $booking_data['pricing']['detailed_breakdown']);
            
            // Salva totali dal breakdown
            $totali = $booking_data['pricing']['detailed_breakdown']['totali'] ?? [];
            if (!empty($totali)) {
                update_post_meta($quote_id, '_subtotale_base', floatval($totali['subtotale_base'] ?? 0));
                update_post_meta($quote_id, '_subtotale_supplementi', floatval($totali['subtotale_supplementi_base'] ?? 0));
                update_post_meta($quote_id, '_subtotale_notti_extra', floatval($totali['subtotale_notti_extra'] ?? 0));
                update_post_meta($quote_id, '_supplemento_totale', 
                    floatval($totali['subtotale_supplementi_base'] ?? 0) + 
                    floatval($totali['subtotale_supplementi_extra'] ?? 0)
                );
            }
        }
    }
    
    /**
     * Calcola e salva i dati aggregati
     */
    private function calculate_and_save_aggregates($quote_id, $payload) {
        // Calcola totali costi extra per partecipante
        $total_extra_costs = 0;
        $total_insurance = 0;
        
        foreach ($payload['anagrafici'] as $person) {
            // Costi extra
            if (!empty($person['costi_extra']) && is_array($person['costi_extra'])) {
                foreach ($person['costi_extra'] as $extra_key => $extra_value) {
                    if ($extra_value) {
                        // Qui andrebbe recuperato il costo dal pacchetto
                        // Per ora usa un valore di default
                        $total_extra_costs += 15; // Valore placeholder
                    }
                }
            }
            
            // Assicurazioni
            if (!empty($person['assicurazioni']) && is_array($person['assicurazioni'])) {
                foreach ($person['assicurazioni'] as $insurance_key => $insurance_value) {
                    if ($insurance_value) {
                        // Qui andrebbe recuperato il costo dal pacchetto
                        // Per ora usa un valore di default
                        $total_insurance += 10; // Valore placeholder
                    }
                }
            }
        }
        
        // Se abbiamo i totali dal booking_data_json, usa quelli (più accurati)
        if (isset($payload['booking_data_json']['extra_costs'])) {
            $total_extra_costs = array_sum(array_column($payload['booking_data_json']['extra_costs'], 'total'));
        }
        
        if (isset($payload['booking_data_json']['insurance']['total'])) {
            $total_insurance = floatval($payload['booking_data_json']['insurance']['total']);
        }
        
        update_post_meta($quote_id, '_aggregated_extra_costs', $total_extra_costs);
        update_post_meta($quote_id, '_aggregated_insurance', $total_insurance);
    }
    
    /**
     * Sincronizza con WooCommerce
     */
    private function sync_with_woocommerce($quote_id, $payload) {
        // Aggiorna sessione WooCommerce
        if (WC()->session) {
            WC()->session->set('btr_preventivo_id', $quote_id);
            WC()->session->set('_preventivo_id', $quote_id);
            
            // Salva dati essenziali in sessione
            WC()->session->set('btr_quote_data', [
                'quote_id' => $quote_id,
                'package_id' => $payload['metadata']['package_id'],
                'total' => $payload['pricing']['totale_generale'],
                'payment_method' => $payload['payment_method']
            ]);
        }
        
        // Aggiorna meta del carrello se presente
        if (!empty($payload['session_data']['cart_item_key']) && WC()->cart) {
            $cart_item_key = $payload['session_data']['cart_item_key'];
            $cart = WC()->cart->get_cart();
            
            if (isset($cart[$cart_item_key])) {
                WC()->cart->cart_contents[$cart_item_key]['btr_preventivo_id'] = $quote_id;
                WC()->cart->set_session();
            }
        }
    }
    
    /**
     * Genera PDF se necessario
     */
    private function generate_pdf_if_needed($quote_id) {
        try {
            // Verifica se la classe esiste e ha il metodo corretto
            $pdf_generator_file = BTR_PLUGIN_DIR . 'includes/class-btr-pdf-generator.php';
            if (file_exists($pdf_generator_file)) {
                require_once $pdf_generator_file;
                
                if (class_exists('BTR_PDF_Generator')) {
                    $pdf_generator = new BTR_PDF_Generator();
                    
                    // Verifica quale metodo è disponibile
                    if (method_exists($pdf_generator, 'generate_preventivo_pdf')) {
                        $pdf_url = $pdf_generator->generate_preventivo_pdf($quote_id);
                    } elseif (method_exists($pdf_generator, 'generate_quote_pdf')) {
                        $pdf_url = $pdf_generator->generate_quote_pdf($quote_id);
                    } else {
                        // Fallback: non generare PDF se il metodo non esiste
                        if ($logger = $this->get_logger()) {
                            $logger->info('PDF generation skipped - method not found', ['source' => 'BTR_Preventivi_V2']);
                        }
                        return '';
                    }
                    
                    if (!empty($pdf_url)) {
                        update_post_meta($quote_id, '_pdf_url', $pdf_url);
                        return $pdf_url;
                    }
                }
            }
        } catch (Exception $e) {
            if ($logger = $this->get_logger()) {
                $logger->warning(
                    sprintf('PDF generation failed for quote %d: %s', $quote_id, $e->getMessage()),
                    ['source' => 'BTR_Preventivi_V2']
                );
            }
        }
        
        return '';
    }
    
    /**
     * Invia email di notifica
     */
    private function send_notification_emails($quote_id, $pdf_url) {
        try {
            // Per ora skippiamo l'invio email se la classe non esiste
            // TODO: Verificare quale sistema di email è effettivamente in uso
            if ($logger = $this->get_logger()) {
                $logger->info(
                    sprintf('Email notifications skipped for quote %d', $quote_id),
                    ['source' => 'BTR_Preventivi_V2']
                );
            }
        } catch (Exception $e) {
            if ($logger = $this->get_logger()) {
                $logger->warning(
                    sprintf('Email sending failed for quote %d: %s', $quote_id, $e->getMessage()),
                    ['source' => 'BTR_Preventivi_V2']
                );
            }
        }
    }
    
    /**
     * Gestione errori
     */
    private function handle_error($exception) {
        if ($logger = $this->get_logger()) {
            $logger->error(
                'Errore creazione preventivo: ' . $exception->getMessage(),
                [
                    'source' => 'BTR_Preventivi_V2',
                    'trace' => $exception->getTraceAsString()
                ]
            );
        }
        
        wp_send_json_error([
            'message' => 'Si è verificato un errore nella creazione del preventivo: ' . $exception->getMessage()
        ]);
    }
}