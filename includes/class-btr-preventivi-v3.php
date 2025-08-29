<?php
/**
 * BTR Preventivi V3 - Versione migliorata con correzioni complete
 * 
 * Migliorie rispetto a V2:
 * - Parsing corretto di TUTTI i campi dal payload
 * - Variabili in italiano per maggiore chiarezza
 * - Validazione robusta dei dati
 * - Retrocompatibilità garantita
 * - Logging dettagliato per debug
 * 
 * @package Born_To_Ride_Booking
 * @since 1.0.150
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Preventivi_V3 {
    
    /**
     * Logger instance
     * @var WC_Logger
     */
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = null;
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
     * Registra gli hook AJAX
     */
    public function register_ajax_hooks() {
        add_action('wp_ajax_btr_create_preventivo', [$this, 'create_preventivo']);
        add_action('wp_ajax_nopriv_btr_create_preventivo', [$this, 'create_preventivo']);
    }
    
    /**
     * FUNZIONE PRINCIPALE: Crea il preventivo
     * Completamente riscritta con parsing corretto e variabili italiane
     */
    public function create_preventivo() {
        $context = ['source' => 'BTR_Preventivi_V3'];
        
        try {
            // ========== STEP 1: VALIDAZIONE SICUREZZA ==========
            $this->valida_sicurezza();
            
            // ========== STEP 2: PARSING COMPLETO E CORRETTO DEL PAYLOAD ==========
            $dati_prenotazione = $this->estrai_dati_completi_payload();
            $this->log_debug('Dati estratti dal payload', $dati_prenotazione);
            
            // ========== STEP 3: VALIDAZIONE DATI ==========
            $this->valida_dati_prenotazione($dati_prenotazione);
            
            // ========== STEP 4: CREAZIONE POST PREVENTIVO ==========
            $id_preventivo = $this->crea_post_preventivo($dati_prenotazione);
            $this->log_info("Preventivo creato con ID: $id_preventivo");
            
            // ========== STEP 5: SALVATAGGIO COMPLETO DATI ==========
            $this->salva_tutti_i_dati($id_preventivo, $dati_prenotazione);
            
            // ========== STEP 6: SINCRONIZZAZIONE WOOCOMMERCE ==========
            $this->sincronizza_con_woocommerce($id_preventivo, $dati_prenotazione);
            
            // ========== STEP 7: GENERAZIONE PDF ==========
            $url_pdf = $this->genera_pdf_se_necessario($id_preventivo);
            
            // ========== STEP 8: INVIO EMAIL ==========
            $this->invia_email_notifica($id_preventivo, $url_pdf);
            
            // ========== STEP 9: RISPOSTA SUCCESSO ==========
            $url_redirect = add_query_arg('preventivo_id', $id_preventivo, home_url('/riepilogo-preventivo/'));
            
            wp_send_json_success([
                'message' => 'Preventivo creato con successo',
                'preventivo_id' => $id_preventivo,
                'pdf_url' => $url_pdf,
                'redirect_url' => $url_redirect
            ]);
            
        } catch (Exception $e) {
            $this->gestisci_errore($e);
        }
    }
    
    /**
     * Valida la sicurezza della richiesta
     */
    private function valida_sicurezza() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'btr_booking_form_nonce')) {
            throw new Exception('Nonce di sicurezza non valido');
        }
    }
    
    /**
     * Estrae TUTTI i dati dal payload con mapping corretto
     * CRITICO: Questa funzione mappa i nomi dei campi dal frontend
     */
    private function estrai_dati_completi_payload() {
        $dati = [];
        
        // ========== DATI CLIENTE (metadata) ==========
        $dati['dati_cliente'] = [
            'nome_cliente' => sanitize_text_field($_POST['customer_nome'] ?? $_POST['cliente_nome'] ?? ''),
            'email_cliente' => sanitize_email($_POST['customer_email'] ?? $_POST['cliente_email'] ?? ''),
            'telefono_cliente' => sanitize_text_field($_POST['customer_telefono'] ?? $_POST['cliente_telefono'] ?? ''),
        ];
        
        // ========== DATI PACCHETTO ==========
        $dati['dati_pacchetto'] = [
            'id_pacchetto' => intval($_POST['pkg_package_id'] ?? $_POST['package_id'] ?? 0),
            'id_prodotto' => intval($_POST['pkg_product_id'] ?? $_POST['product_id'] ?? 0),
            'id_variante' => intval($_POST['pkg_variant_id'] ?? $_POST['variant_id'] ?? 0),
            'titolo_pacchetto' => sanitize_text_field($_POST['pkg_nome_pacchetto'] ?? $_POST['nome_pacchetto'] ?? ''),
            'slug_pacchetto' => sanitize_text_field($_POST['pkg_slug'] ?? $_POST['package_slug'] ?? ''),
            'sku' => sanitize_text_field($_POST['sku'] ?? ''),
            'tipologia_prenotazione' => sanitize_text_field($_POST['pkg_tipologia_prenotazione'] ?? $_POST['tipologia_prenotazione'] ?? ''),
        ];
        
        // ========== DURATA (Correzione: legge i campi corretti) ==========
        // Parsing intelligente della durata dal campo 'durata' o 'pkg_durata'
        $durata_raw = $_POST['durata'] ?? $_POST['pkg_durata'] ?? '';
        $durata_notti = 0;
        $durata_giorni = 0;
        
        // Se durata è un numero semplice (es: "2"), assumiamo sia giorni
        if (is_numeric($durata_raw)) {
            $durata_giorni = intval($durata_raw);
            $durata_notti = max(0, $durata_giorni - 1);
        }
        // Se durata è una stringa formattata (es: "2 giorni - 1 notti")
        elseif (preg_match('/(\d+)\s*giorn[io]/i', $durata_raw, $matches_giorni)) {
            $durata_giorni = intval($matches_giorni[1]);
            if (preg_match('/(\d+)\s*nott[ie]/i', $durata_raw, $matches_notti)) {
                $durata_notti = intval($matches_notti[1]);
            } else {
                $durata_notti = max(0, $durata_giorni - 1);
            }
        }
        
        $dati['dati_pacchetto']['durata_notti'] = $durata_notti;
        $dati['dati_pacchetto']['durata_giorni'] = $durata_giorni;
        $dati['dati_pacchetto']['durata_formattata'] = $durata_raw ?: "{$durata_giorni} giorni - {$durata_notti} notti";
        
        // ========== PARTECIPANTI ==========
        $dati['partecipanti'] = [
            'num_adulti' => intval($_POST['participants_adults'] ?? $_POST['num_adults'] ?? 0),
            'num_bambini' => intval($_POST['participants_children_total'] ?? $_POST['num_children'] ?? 0),
            'num_neonati' => intval($_POST['participants_infants'] ?? $_POST['num_infants'] ?? 0),
            'bambini_per_fascia' => [
                'f1' => intval($_POST['participants_children_f1'] ?? 0),
                'f2' => intval($_POST['participants_children_f2'] ?? 0),
                'f3' => intval($_POST['participants_children_f3'] ?? 0),
                'f4' => intval($_POST['participants_children_f4'] ?? 0),
            ]
        ];
        
        // ========== DATE ==========
        $dati['info_date'] = [
            'data_arrivo' => sanitize_text_field($_POST['dates_check_in'] ?? $_POST['check_in_date'] ?? ''),
            'data_partenza' => sanitize_text_field($_POST['dates_check_out'] ?? $_POST['check_out_date'] ?? ''),
            'data_viaggio' => sanitize_text_field($_POST['pkg_selected_date'] ?? $_POST['selected_date'] ?? ''),
            'id_range_date' => sanitize_text_field($_POST['pkg_date_ranges_id'] ?? $_POST['date_ranges_id'] ?? ''),
        ];
        
        // ========== NOTTI EXTRA (Correzione completa) ==========
        $notte_extra_attiva = intval($_POST['extra_nights_enabled'] ?? $_POST['extra_night'] ?? 0);
        $dati['notti_extra'] = [
            'attive' => $notte_extra_attiva === 1,
            'numero_notti' => intval($_POST['extra_nights_numero'] ?? $_POST['pricing_notti_extra_numero'] ?? 0),
            'data_notte_extra' => sanitize_text_field($_POST['dates_extra_night'] ?? $_POST['extra_nights_date'] ?? $_POST['btr_extra_night_date'] ?? ''),
            'prezzo_per_persona' => floatval($_POST['extra_nights_price_per_person'] ?? $_POST['extra_night_pp'] ?? 0),
            'costo_totale' => floatval($_POST['extra_nights_total_cost'] ?? $_POST['extra_night_total'] ?? 0),
        ];
        
        // Correggi il costo totale notti extra se necessario
        if ($dati['notti_extra']['attive'] && $dati['notti_extra']['costo_totale'] == 0) {
            // Ricalcola in base ai dati disponibili
            $totale_notti_extra = floatval($_POST['pricing_subtotale_notti_extra'] ?? 0);
            if ($totale_notti_extra > 0) {
                $dati['notti_extra']['costo_totale'] = $totale_notti_extra;
            }
        }
        
        // ========== PREZZI (Correzione: legge i campi corretti dal payload) ==========
        $dati['prezzi'] = [
            'prezzo_per_persona' => floatval($_POST['pricing_adulti_prezzo_unitario'] ?? $_POST['price_per_person'] ?? 0),
            'totale_base' => floatval($_POST['pricing_subtotale_prezzi_base'] ?? $_POST['totale_base'] ?? 0),
            'totale_camere' => floatval($_POST['pricing_totale_camere'] ?? $_POST['totals_rooms'] ?? 0),
            'totale_costi_extra' => floatval($_POST['pricing_totale_costi_extra'] ?? $_POST['totals_extra_costs'] ?? 0),
            'totale_assicurazioni' => floatval($_POST['pricing_totale_assicurazioni'] ?? $_POST['totals_insurances'] ?? 0),
            'totale_generale' => floatval($_POST['pricing_totale_generale'] ?? $_POST['totals_grand_total'] ?? 0),
            'totale_supplementi' => floatval($_POST['pricing_subtotale_supplementi_base'] ?? 0) + 
                                   floatval($_POST['pricing_subtotale_supplementi_extra'] ?? 0),
        ];
        
        // ========== CAMERE ==========
        $dati['camere'] = $this->estrai_dati_camere();
        
        // ========== ANAGRAFICI ==========
        $dati['anagrafici'] = $this->estrai_dati_anagrafici();
        
        // ========== BOOKING DATA JSON (per retrocompatibilità) ==========
        if (isset($_POST['booking_data_json'])) {
            $booking_json = stripslashes($_POST['booking_data_json']);
            $decoded = json_decode($booking_json, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $dati['booking_data_json'] = $decoded;
                
                // Usa il JSON per correggere eventuali dati mancanti
                $this->integra_dati_da_json($dati, $decoded);
            }
        }
        
        // ========== COSTI EXTRA DETTAGLIATI ==========
        $dati['costi_extra'] = $this->estrai_costi_extra();
        
        // ========== DATI SESSIONE ==========
        $dati['dati_sessione'] = [
            'hash_sessione' => sanitize_text_field($_POST['session_hash'] ?? ''),
            'chiave_carrello' => sanitize_text_field($_POST['cart_item_key'] ?? ''),
        ];
        
        // ========== METODO PAGAMENTO ==========
        $dati['metodo_pagamento'] = sanitize_text_field($_POST['payment_method'] ?? 'full_payment');
        
        return $dati;
    }
    
    /**
     * Integra dati mancanti dal JSON se disponibile
     */
    private function integra_dati_da_json(&$dati, $json) {
        // Correggi prezzi se mancanti
        if (empty($dati['prezzi']['prezzo_per_persona']) && isset($json['pricing']['detailed_breakdown']['partecipanti']['adulti']['prezzo_base_unitario'])) {
            $dati['prezzi']['prezzo_per_persona'] = floatval($json['pricing']['detailed_breakdown']['partecipanti']['adulti']['prezzo_base_unitario']);
        }
        
        // Correggi notti extra
        if (isset($json['extra_nights']) && $json['extra_nights']['enabled']) {
            if (empty($dati['notti_extra']['costo_totale'])) {
                $dati['notti_extra']['costo_totale'] = floatval($json['extra_nights']['total_cost'] ?? 0);
            }
            if (empty($dati['notti_extra']['numero_notti'])) {
                // Calcola numero notti dal JSON
                $notti = $json['pricing']['detailed_breakdown']['notti_extra']['numero_notti'] ?? 1;
                $dati['notti_extra']['numero_notti'] = intval($notti);
            }
        }
        
        // Correggi durata se mancante
        if (empty($dati['dati_pacchetto']['durata_giorni']) && isset($json['package']['durata'])) {
            $dati['dati_pacchetto']['durata_giorni'] = intval($json['package']['durata']);
            $dati['dati_pacchetto']['durata_notti'] = max(0, $dati['dati_pacchetto']['durata_giorni'] - 1);
        }
    }
    
    /**
     * Estrae i dati delle camere dal payload
     */
    private function estrai_dati_camere() {
        $camere = [];
        
        // Prima prova a leggere dal campo 'rooms' (array)
        if (isset($_POST['rooms']) && is_array($_POST['rooms'])) {
            foreach ($_POST['rooms'] as $camera) {
                $camere[] = $this->parse_singola_camera($camera);
            }
        }
        // Altrimenti prova dal campo 'camere' (potrebbe essere JSON)
        elseif (isset($_POST['camere'])) {
            $camere_data = $_POST['camere'];
            
            // Se è una stringa JSON, decodifica
            if (is_string($camere_data)) {
                $decoded = json_decode(stripslashes($camere_data), true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    foreach ($decoded as $camera) {
                        $camere[] = $this->parse_singola_camera($camera);
                    }
                }
            }
            // Se è già un array
            elseif (is_array($camere_data)) {
                foreach ($camere_data as $camera) {
                    $camere[] = $this->parse_singola_camera($camera);
                }
            }
        }
        
        return $camere;
    }
    
    /**
     * Parse di una singola camera
     */
    private function parse_singola_camera($camera) {
        return [
            'id_variazione' => intval($camera['variation_id'] ?? 0),
            'tipo' => sanitize_text_field($camera['tipo'] ?? $camera['type'] ?? ''),
            'sottotipo' => sanitize_text_field($camera['sottotipo'] ?? ''),
            'quantita' => intval($camera['quantita'] ?? $camera['quantity'] ?? 0),
            'capacita' => intval($camera['capacita'] ?? $camera['capacity'] ?? 0),
            'adulti' => intval($camera['adulti'] ?? 0),
            'bambini' => intval($camera['bambini'] ?? 0),
            'neonati' => intval($camera['neonati'] ?? $camera['assigned_infants'] ?? 0),
            'prezzo_base' => floatval($camera['prezzo_base'] ?? $camera['price'] ?? 0),
            'supplemento' => floatval($camera['supplemento'] ?? 0),
            'sconto' => floatval($camera['sconto'] ?? 0),
            'totale' => floatval($camera['totale'] ?? $camera['totale_camera'] ?? 0),
            // Assegnazioni bambini per fascia
            'bambini_f1_assegnati' => intval($camera['assigned_child_f1'] ?? 0),
            'bambini_f2_assegnati' => intval($camera['assigned_child_f2'] ?? 0),
            'bambini_f3_assegnati' => intval($camera['assigned_child_f3'] ?? 0),
            'bambini_f4_assegnati' => intval($camera['assigned_child_f4'] ?? 0),
            // Prezzi bambini per fascia
            'prezzo_bambino_f1' => floatval($camera['price_child_f1'] ?? 0),
            'prezzo_bambino_f2' => floatval($camera['price_child_f2'] ?? 0),
            'prezzo_bambino_f3' => floatval($camera['price_child_f3'] ?? 0),
            'prezzo_bambino_f4' => floatval($camera['price_child_f4'] ?? 0),
        ];
    }
    
    /**
     * Estrae i dati anagrafici
     */
    private function estrai_dati_anagrafici() {
        $anagrafici = [];
        
        if (isset($_POST['anagrafici']) && is_array($_POST['anagrafici'])) {
            foreach ($_POST['anagrafici'] as $index => $persona) {
                $dati_persona = [
                    'nome' => sanitize_text_field($persona['nome'] ?? ''),
                    'cognome' => sanitize_text_field($persona['cognome'] ?? ''),
                    'email' => sanitize_email($persona['email'] ?? ''),
                    'telefono' => sanitize_text_field($persona['telefono'] ?? ''),
                    'data_nascita' => sanitize_text_field($persona['data_nascita'] ?? ''),
                    'citta_nascita' => sanitize_text_field($persona['citta_nascita'] ?? ''),
                    'codice_fiscale' => sanitize_text_field($persona['codice_fiscale'] ?? ''),
                    'indirizzo' => sanitize_text_field($persona['indirizzo_residenza'] ?? ''),
                    'numero_civico' => sanitize_text_field($persona['numero_civico'] ?? ''),
                    'citta' => sanitize_text_field($persona['citta_residenza'] ?? ''),
                    'provincia' => sanitize_text_field($persona['provincia_residenza'] ?? ''),
                    'cap' => sanitize_text_field($persona['cap_residenza'] ?? ''),
                    'nazione' => sanitize_text_field($persona['nazione'] ?? 'IT'),
                    'camera' => intval($persona['camera'] ?? 0),
                    'camera_tipo' => sanitize_text_field($persona['camera_tipo'] ?? ''),
                    'tipo_letto' => sanitize_text_field($persona['tipo_letto'] ?? ''),
                ];
                
                // Determina il tipo di partecipante in base all'indice e ai conteggi
                $num_adulti = intval($_POST['participants_adults'] ?? $_POST['num_adults'] ?? 0);
                $num_bambini = intval($_POST['participants_children_total'] ?? $_POST['num_children'] ?? 0);
                
                if ($index < $num_adulti) {
                    $dati_persona['tipo_partecipante'] = 'adulto';
                } elseif ($index < $num_adulti + $num_bambini) {
                    $dati_persona['tipo_partecipante'] = 'bambino';
                    // Determina la fascia del bambino se possibile
                    $dati_persona['fascia_eta'] = $this->determina_fascia_bambino($index - $num_adulti);
                } else {
                    $dati_persona['tipo_partecipante'] = 'neonato';
                }
                
                // Costi extra
                if (isset($persona['costi_extra']) && is_array($persona['costi_extra'])) {
                    $dati_persona['costi_extra'] = [];
                    foreach ($persona['costi_extra'] as $nome_extra => $extra_data) {
                        if (isset($extra_data['selected']) && $extra_data['selected']) {
                            $dati_persona['costi_extra'][$nome_extra] = [
                                'selezionato' => true,
                                'prezzo' => floatval($extra_data['price'] ?? 0)
                            ];
                        }
                    }
                }
                
                // Assicurazioni
                if (isset($persona['assicurazioni']) && is_array($persona['assicurazioni'])) {
                    $dati_persona['assicurazioni'] = $persona['assicurazioni'];
                }
                
                $anagrafici[] = $dati_persona;
            }
        }
        
        return $anagrafici;
    }
    
    /**
     * Determina la fascia del bambino
     */
    private function determina_fascia_bambino($indice_bambino) {
        // Conta bambini per fascia
        $f1 = intval($_POST['participants_children_f1'] ?? 0);
        $f2 = intval($_POST['participants_children_f2'] ?? 0);
        $f3 = intval($_POST['participants_children_f3'] ?? 0);
        $f4 = intval($_POST['participants_children_f4'] ?? 0);
        
        if ($indice_bambino < $f1) return 'f1';
        if ($indice_bambino < $f1 + $f2) return 'f2';
        if ($indice_bambino < $f1 + $f2 + $f3) return 'f3';
        if ($indice_bambino < $f1 + $f2 + $f3 + $f4) return 'f4';
        
        return 'f1'; // Default
    }
    
    /**
     * Estrae i costi extra
     */
    private function estrai_costi_extra() {
        $costi_extra = [];
        
        // Estrai prezzi costi extra
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'extra_costs_prices[') === 0) {
                preg_match('/extra_costs_prices\[([^\]]+)\]/', $key, $matches);
                if (isset($matches[1])) {
                    $nome_extra = $matches[1];
                    $costi_extra[$nome_extra] = floatval($value);
                }
            }
        }
        
        return $costi_extra;
    }
    
    /**
     * Valida i dati della prenotazione
     */
    private function valida_dati_prenotazione($dati) {
        $errori = [];
        
        // Validazione cliente
        if (empty($dati['dati_cliente']['nome_cliente'])) {
            $errori[] = 'Nome cliente obbligatorio';
        }
        if (empty($dati['dati_cliente']['email_cliente'])) {
            $errori[] = 'Email cliente obbligatoria';
        }
        if (!is_email($dati['dati_cliente']['email_cliente'])) {
            $errori[] = 'Email cliente non valida';
        }
        
        // Validazione pacchetto
        if (empty($dati['dati_pacchetto']['id_pacchetto'])) {
            $errori[] = 'ID pacchetto obbligatorio';
        }
        
        // Validazione partecipanti
        $totale_partecipanti = $dati['partecipanti']['num_adulti'] + 
                               $dati['partecipanti']['num_bambini'] + 
                               $dati['partecipanti']['num_neonati'];
        
        if ($totale_partecipanti < 1) {
            $errori[] = 'Almeno un partecipante richiesto';
        }
        
        // Validazione prezzi
        if ($dati['prezzi']['totale_generale'] <= 0) {
            $errori[] = 'Totale generale deve essere maggiore di zero';
        }
        
        // Se ci sono errori, lancia eccezione
        if (!empty($errori)) {
            throw new Exception('Errori di validazione: ' . implode(', ', $errori));
        }
    }
    
    /**
     * Crea il post del preventivo
     */
    private function crea_post_preventivo($dati) {
        $post_data = [
            'post_type' => 'btr_preventivi',
            'post_title' => sprintf(
                'Preventivo per %s - %s - %s',
                $dati['dati_cliente']['nome_cliente'],
                $dati['dati_pacchetto']['titolo_pacchetto'],
                date('d/m/Y H:i:s')
            ),
            'post_status' => 'publish',
            'post_author' => get_current_user_id() ?: 1
        ];
        
        $id_preventivo = wp_insert_post($post_data);
        
        if (is_wp_error($id_preventivo)) {
            throw new Exception('Errore creazione preventivo: ' . $id_preventivo->get_error_message());
        }
        
        return $id_preventivo;
    }
    
    /**
     * Salva TUTTI i dati del preventivo con retrocompatibilità
     */
    private function salva_tutti_i_dati($id_preventivo, $dati) {
        // ========== 1. SALVA IL JSON COMPLETO (Single Source of Truth) ==========
        update_post_meta($id_preventivo, '_btr_quote_data_json', wp_json_encode($dati));
        update_post_meta($id_preventivo, '_btr_quote_version', '3.0');
        update_post_meta($id_preventivo, '_btr_quote_timestamp', current_time('mysql'));
        
        // ========== 2. DATI CLIENTE (con retrocompatibilità) ==========
        update_post_meta($id_preventivo, '_cliente_nome', $dati['dati_cliente']['nome_cliente']);
        update_post_meta($id_preventivo, '_cliente_email', $dati['dati_cliente']['email_cliente']);
        update_post_meta($id_preventivo, '_cliente_telefono', $dati['dati_cliente']['telefono_cliente']);
        
        // Retrocompatibilità con nomi inglesi
        update_post_meta($id_preventivo, '_customer_name', $dati['dati_cliente']['nome_cliente']);
        update_post_meta($id_preventivo, '_customer_email', $dati['dati_cliente']['email_cliente']);
        update_post_meta($id_preventivo, '_customer_phone', $dati['dati_cliente']['telefono_cliente']);
        
        // ========== 3. DATI PACCHETTO ==========
        update_post_meta($id_preventivo, '_pacchetto_id', $dati['dati_pacchetto']['id_pacchetto']);
        update_post_meta($id_preventivo, '_package_id', $dati['dati_pacchetto']['id_pacchetto']); // retrocompat
        update_post_meta($id_preventivo, '_product_id', $dati['dati_pacchetto']['id_prodotto']);
        update_post_meta($id_preventivo, '_variant_id', $dati['dati_pacchetto']['id_variante']);
        update_post_meta($id_preventivo, '_nome_pacchetto', $dati['dati_pacchetto']['titolo_pacchetto']);
        update_post_meta($id_preventivo, '_package_title', $dati['dati_pacchetto']['titolo_pacchetto']); // retrocompat
        update_post_meta($id_preventivo, '_tipologia_prenotazione', $dati['dati_pacchetto']['tipologia_prenotazione']);
        
        // ========== 4. DURATA (CORREZIONE IMPORTANTE) ==========
        update_post_meta($id_preventivo, '_durata', $dati['dati_pacchetto']['durata_formattata']);
        update_post_meta($id_preventivo, '_duration_nights', $dati['dati_pacchetto']['durata_notti']);
        update_post_meta($id_preventivo, '_duration_days', $dati['dati_pacchetto']['durata_giorni']);
        
        // ========== 5. PARTECIPANTI ==========
        update_post_meta($id_preventivo, '_num_adults', $dati['partecipanti']['num_adulti']);
        update_post_meta($id_preventivo, '_num_children', $dati['partecipanti']['num_bambini']);
        update_post_meta($id_preventivo, '_num_neonati', $dati['partecipanti']['num_neonati']);
        
        // Salva dettaglio fasce bambini
        update_post_meta($id_preventivo, '_bambini_per_fascia', $dati['partecipanti']['bambini_per_fascia']);
        
        // ========== 6. DATE ==========
        update_post_meta($id_preventivo, '_data_arrivo', $dati['info_date']['data_arrivo']);
        update_post_meta($id_preventivo, '_data_partenza', $dati['info_date']['data_partenza']);
        update_post_meta($id_preventivo, '_data_viaggio', $dati['info_date']['data_viaggio']);
        
        // Retrocompatibilità
        update_post_meta($id_preventivo, '_check_in', $dati['info_date']['data_arrivo']);
        update_post_meta($id_preventivo, '_check_out', $dati['info_date']['data_partenza']);
        update_post_meta($id_preventivo, '_travel_date', $dati['info_date']['data_viaggio']);
        update_post_meta($id_preventivo, '_selected_date', $dati['info_date']['data_viaggio']);
        update_post_meta($id_preventivo, '_data_pacchetto', $dati['info_date']['data_viaggio']);
        
        // ========== 7. NOTTI EXTRA (CORREZIONE COMPLETA) ==========
        update_post_meta($id_preventivo, '_extra_night', $dati['notti_extra']['attive'] ? 1 : 0);
        update_post_meta($id_preventivo, '_extra_night_flag', $dati['notti_extra']['attive'] ? 1 : 0);
        update_post_meta($id_preventivo, '_numero_notti_extra', $dati['notti_extra']['numero_notti']);
        update_post_meta($id_preventivo, '_extra_night_pp', $dati['notti_extra']['prezzo_per_persona']);
        update_post_meta($id_preventivo, '_extra_night_total', $dati['notti_extra']['costo_totale']);
        update_post_meta($id_preventivo, '_extra_night_date', $dati['notti_extra']['data_notte_extra']);
        update_post_meta($id_preventivo, '_btr_extra_night_date', $dati['notti_extra']['data_notte_extra']);
        
        // ========== 8. PREZZI (CORREZIONE COMPLETA) ==========
        update_post_meta($id_preventivo, '_prezzo_per_persona', $dati['prezzi']['prezzo_per_persona']);
        update_post_meta($id_preventivo, '_price_per_person', $dati['prezzi']['prezzo_per_persona']); // retrocompat
        update_post_meta($id_preventivo, '_totale_base', $dati['prezzi']['totale_base']);
        update_post_meta($id_preventivo, '_totale_camere', $dati['prezzi']['totale_camere']);
        update_post_meta($id_preventivo, '_totale_costi_extra', $dati['prezzi']['totale_costi_extra']);
        update_post_meta($id_preventivo, '_totale_extra', $dati['prezzi']['totale_costi_extra']); // alias
        update_post_meta($id_preventivo, '_totale_assicurazioni', $dati['prezzi']['totale_assicurazioni']);
        update_post_meta($id_preventivo, '_totale_generale', $dati['prezzi']['totale_generale']);
        update_post_meta($id_preventivo, '_supplemento_totale', $dati['prezzi']['totale_supplementi']);
        
        // Retrocompatibilità multipla per il totale
        update_post_meta($id_preventivo, '_prezzo_totale', $dati['prezzi']['totale_generale']);
        update_post_meta($id_preventivo, '_totale_preventivo', $dati['prezzi']['totale_generale']);
        update_post_meta($id_preventivo, '_btr_grand_total', $dati['prezzi']['totale_generale']);
        
        // ========== 9. CAMERE ==========
        update_post_meta($id_preventivo, '_camere_selezionate', $dati['camere']);
        update_post_meta($id_preventivo, '_btr_rooms', $dati['camere']); // nuovo nome
        
        // ========== 10. ANAGRAFICI ==========
        update_post_meta($id_preventivo, '_anagrafici_preventivo', $dati['anagrafici']);
        update_post_meta($id_preventivo, '_btr_participants', $dati['anagrafici']); // nuovo nome
        
        // ========== 11. COSTI EXTRA ==========
        if (!empty($dati['costi_extra'])) {
            update_post_meta($id_preventivo, '_btr_extra_costs', $dati['costi_extra']);
            update_post_meta($id_preventivo, '_costi_extra_dettaglio', $dati['costi_extra']);
        }
        
        // ========== 12. STATO E SESSIONE ==========
        update_post_meta($id_preventivo, '_stato_preventivo', 'creato');
        update_post_meta($id_preventivo, '_payment_method', $dati['metodo_pagamento']);
        
        if (!empty($dati['dati_sessione']['hash_sessione'])) {
            update_post_meta($id_preventivo, '_wc_session_hash', $dati['dati_sessione']['hash_sessione']);
        }
        
        if (!empty($dati['dati_sessione']['chiave_carrello'])) {
            update_post_meta($id_preventivo, '_wc_cart_item_key', $dati['dati_sessione']['chiave_carrello']);
        }
        
        // ========== 13. BOOKING DATA JSON (retrocompatibilità) ==========
        if (!empty($dati['booking_data_json'])) {
            update_post_meta($id_preventivo, '_booking_data_json', $dati['booking_data_json']);
            
            // Salva anche il riepilogo dettagliato se presente
            if (isset($dati['booking_data_json']['pricing']['detailed_breakdown'])) {
                update_post_meta($id_preventivo, '_riepilogo_calcoli_dettagliato', 
                    $dati['booking_data_json']['pricing']['detailed_breakdown']);
            }
        }
    }
    
    /**
     * Sincronizza con WooCommerce
     */
    private function sincronizza_con_woocommerce($id_preventivo, $dati) {
        if (WC()->session) {
            WC()->session->set('btr_preventivo_id', $id_preventivo);
            WC()->session->set('_preventivo_id', $id_preventivo);
            
            // Salva dati essenziali in sessione
            WC()->session->set('btr_quote_data', [
                'quote_id' => $id_preventivo,
                'package_id' => $dati['dati_pacchetto']['id_pacchetto'],
                'total' => $dati['prezzi']['totale_generale'],
                'payment_method' => $dati['metodo_pagamento']
            ]);
        }
        
        // Aggiorna meta del carrello se presente
        if (!empty($dati['dati_sessione']['chiave_carrello']) && WC()->cart) {
            $chiave_carrello = $dati['dati_sessione']['chiave_carrello'];
            $carrello = WC()->cart->get_cart();
            
            if (isset($carrello[$chiave_carrello])) {
                WC()->cart->cart_contents[$chiave_carrello]['btr_preventivo_id'] = $id_preventivo;
                WC()->cart->set_session();
            }
        }
    }
    
    /**
     * Genera PDF se necessario
     */
    private function genera_pdf_se_necessario($id_preventivo) {
        try {
            $file_pdf_generator = BTR_PLUGIN_DIR . 'includes/class-btr-pdf-generator.php';
            if (file_exists($file_pdf_generator)) {
                require_once $file_pdf_generator;
                
                if (class_exists('BTR_PDF_Generator')) {
                    $pdf_generator = new BTR_PDF_Generator();
                    
                    // Prova vari metodi
                    if (method_exists($pdf_generator, 'generate_preventivo_pdf')) {
                        $url_pdf = $pdf_generator->generate_preventivo_pdf($id_preventivo);
                    } elseif (method_exists($pdf_generator, 'generate_quote_pdf')) {
                        $url_pdf = $pdf_generator->generate_quote_pdf($id_preventivo);
                    } else {
                        $this->log_info('Generazione PDF saltata - metodo non trovato');
                        return '';
                    }
                    
                    if (!empty($url_pdf)) {
                        update_post_meta($id_preventivo, '_pdf_url', $url_pdf);
                        return $url_pdf;
                    }
                }
            }
        } catch (Exception $e) {
            $this->log_warning("Generazione PDF fallita per preventivo $id_preventivo: " . $e->getMessage());
        }
        
        return '';
    }
    
    /**
     * Invia email di notifica
     */
    private function invia_email_notifica($id_preventivo, $url_pdf) {
        // Per ora skippiamo l'invio email
        $this->log_info("Email notifiche saltate per preventivo $id_preventivo");
    }
    
    /**
     * Gestione errori
     */
    private function gestisci_errore($exception) {
        $this->log_error('Errore creazione preventivo: ' . $exception->getMessage());
        
        wp_send_json_error([
            'message' => 'Si è verificato un errore nella creazione del preventivo: ' . $exception->getMessage()
        ]);
    }
    
    // ========== METODI DI LOGGING ==========
    
    private function log_info($messaggio) {
        if ($logger = $this->get_logger()) {
            $logger->info($messaggio, ['source' => 'BTR_Preventivi_V3']);
        }
        btr_debug_log("[INFO] $messaggio");
    }
    
    private function log_warning($messaggio) {
        if ($logger = $this->get_logger()) {
            $logger->warning($messaggio, ['source' => 'BTR_Preventivi_V3']);
        }
        btr_debug_log("[WARNING] $messaggio");
    }
    
    private function log_error($messaggio) {
        if ($logger = $this->get_logger()) {
            $logger->error($messaggio, ['source' => 'BTR_Preventivi_V3']);
        }
        btr_debug_log("[ERROR] $messaggio");
    }
    
    private function log_debug($messaggio, $dati = null) {
        if (defined('BTR_DEBUG') && BTR_DEBUG) {
            $log_msg = "[DEBUG] $messaggio";
            if ($dati !== null) {
                $log_msg .= " - Dati: " . print_r($dati, true);
            }
            btr_debug_log($log_msg);
        }
    }
}