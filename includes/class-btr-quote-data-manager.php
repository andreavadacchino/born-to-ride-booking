<?php
/**
 * BTR Quote Data Manager
 * 
 * Gestisce il salvataggio strutturato e robusto dei dati del preventivo
 * usando il pattern Single Source of Truth con JSON come fonte primaria
 * 
 * @package Born_To_Ride_Booking
 * @since 1.0.148
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Quote_Data_Manager {
    
    /**
     * Logger instance
     * @var WC_Logger
     */
    private $logger;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Logger inizializzato lazy quando necessario
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
     * Salva i dati completi del preventivo
     * 
     * @param int $quote_id ID del preventivo
     * @param array $payload Payload completo dall'AJAX
     * @return bool True se salvato con successo
     */
    public function save_quote_data($quote_id, $payload) {
        $context = ['source' => 'BTR_Quote_Data_Manager'];
        
        try {
            // 1. Salva il payload completo come JSON (Single Source of Truth)
            $this->save_complete_json($quote_id, $payload);
            
            // 2. Estrai e salva i campi critici per le query
            $this->save_queryable_fields($quote_id, $payload);
            
            // 3. Salva i dati dei partecipanti
            $this->save_participants_data($quote_id, $payload);
            
            // 4. Salva i dati delle camere
            $this->save_rooms_data($quote_id, $payload);
            
            // 5. Salva i dati dei prezzi
            $this->save_pricing_data($quote_id, $payload);
            
            // 6. Salva i costi extra
            $this->save_extra_costs($quote_id, $payload);
            
            // 7. Salva le assicurazioni
            $this->save_insurance_data($quote_id, $payload);
            
            // 8. Salva i metadata del pacchetto
            $this->save_package_metadata($quote_id, $payload);
            
            // 9. Salva i dati di sessione WooCommerce
            $this->save_session_data($quote_id, $payload);
            
            // 10. Calcola e salva i totali finali
            $this->calculate_and_save_totals($quote_id, $payload);
            
            if ($logger = $this->get_logger()) {
                $logger->info(
                    sprintf('Dati preventivo %d salvati con successo', $quote_id),
                    $context
                );
            }
            
            return true;
            
        } catch (Exception $e) {
            if ($logger = $this->get_logger()) {
                $logger->error(
                    sprintf('Errore salvataggio dati preventivo %d: %s', $quote_id, $e->getMessage()),
                    $context
                );
            }
            return false;
        }
    }
    
    /**
     * Salva il payload completo come JSON
     */
    private function save_complete_json($quote_id, $payload) {
        // Rimuovi i campi sensibili prima del salvataggio
        $clean_payload = $this->sanitize_payload($payload);
        
        // Salva come JSON strutturato
        update_post_meta($quote_id, '_btr_quote_data_json', wp_json_encode($clean_payload));
        update_post_meta($quote_id, '_btr_quote_version', '2.0');
        update_post_meta($quote_id, '_btr_quote_timestamp', current_time('mysql'));
    }
    
    /**
     * Salva i campi essenziali per le query
     */
    private function save_queryable_fields($quote_id, $payload) {
        // Dati cliente
        update_post_meta($quote_id, '_customer_name', sanitize_text_field($payload['metadata']['customer_name'] ?? ''));
        update_post_meta($quote_id, '_customer_email', sanitize_email($payload['metadata']['customer_email'] ?? ''));
        update_post_meta($quote_id, '_customer_phone', sanitize_text_field($payload['metadata']['customer_phone'] ?? ''));
        
        // ID pacchetto e prodotto
        update_post_meta($quote_id, '_package_id', intval($payload['metadata']['package_id'] ?? 0));
        update_post_meta($quote_id, '_product_id', intval($payload['metadata']['product_id'] ?? 0));
        
        // Date
        update_post_meta($quote_id, '_check_in_date', sanitize_text_field($payload['date_info']['check_in'] ?? ''));
        update_post_meta($quote_id, '_check_out_date', sanitize_text_field($payload['date_info']['check_out'] ?? ''));
        update_post_meta($quote_id, '_travel_date', sanitize_text_field($payload['date_info']['travel_date'] ?? ''));
        
        // Stato e tipo pagamento
        update_post_meta($quote_id, '_quote_status', 'pending');
        update_post_meta($quote_id, '_payment_method', sanitize_text_field($payload['payment_method'] ?? 'full_payment'));
    }
    
    /**
     * Salva i dati dei partecipanti
     */
    private function save_participants_data($quote_id, $payload) {
        $participants = [];
        
        // Adulti
        if (isset($payload['participants']['adults'])) {
            foreach ($payload['participants']['adults'] as $adult) {
                $participants[] = $this->format_participant($adult, 'adult');
            }
        }
        
        // Bambini
        if (isset($payload['participants']['children'])) {
            foreach ($payload['participants']['children'] as $child) {
                $participants[] = $this->format_participant($child, 'child');
            }
        }
        
        // Neonati
        if (isset($payload['participants']['infants'])) {
            foreach ($payload['participants']['infants'] as $infant) {
                $participants[] = $this->format_participant($infant, 'infant');
            }
        }
        
        // Salva array partecipanti
        update_post_meta($quote_id, '_btr_participants', $participants);
        
        // Salva contatori
        update_post_meta($quote_id, '_num_adults', count($payload['participants']['adults'] ?? []));
        update_post_meta($quote_id, '_num_children', count($payload['participants']['children'] ?? []));
        update_post_meta($quote_id, '_num_infants', count($payload['participants']['infants'] ?? []));
    }
    
    /**
     * Formatta i dati del partecipante
     */
    private function format_participant($data, $type) {
        return [
            'type' => $type,
            'nome' => sanitize_text_field($data['nome'] ?? ''),
            'cognome' => sanitize_text_field($data['cognome'] ?? ''),
            'data_nascita' => sanitize_text_field($data['data_nascita'] ?? ''),
            'citta_nascita' => sanitize_text_field($data['citta_nascita'] ?? ''),
            'codice_fiscale' => sanitize_text_field($data['codice_fiscale'] ?? ''),
            'email' => sanitize_email($data['email'] ?? ''),
            'telefono' => sanitize_text_field($data['telefono'] ?? ''),
            'indirizzo' => sanitize_text_field($data['indirizzo'] ?? ''),
            'citta' => sanitize_text_field($data['citta'] ?? ''),
            'cap' => sanitize_text_field($data['cap'] ?? ''),
            'nazione' => sanitize_text_field($data['nazione'] ?? ''),
            'camera' => intval($data['camera'] ?? 0),
            'fascia' => sanitize_text_field($data['fascia'] ?? ''),
            'age' => intval($data['age'] ?? 0),
            'price' => floatval($data['price'] ?? 0)
        ];
    }
    
    /**
     * Salva i dati delle camere
     */
    private function save_rooms_data($quote_id, $payload) {
        if (!isset($payload['rooms'])) {
            return;
        }
        
        $rooms = [];
        foreach ($payload['rooms'] as $room) {
            $rooms[] = [
                'tipo' => sanitize_text_field($room['tipo'] ?? ''),
                'quantita' => intval($room['quantita'] ?? 0),
                'adulti' => intval($room['adulti'] ?? 0),
                'bambini' => intval($room['bambini'] ?? 0),
                'neonati' => intval($room['neonati'] ?? 0),
                'prezzo_base' => floatval($room['prezzo_base'] ?? 0),
                'supplemento' => floatval($room['supplemento'] ?? 0),
                'totale' => floatval($room['totale'] ?? 0)
            ];
        }
        
        update_post_meta($quote_id, '_btr_rooms', $rooms);
        update_post_meta($quote_id, '_camere_selezionate', $rooms); // Compatibilità
    }
    
    /**
     * Salva i dati dei prezzi
     */
    private function save_pricing_data($quote_id, $payload) {
        $booking_data = $payload['booking_data_json'] ?? [];
        $pricing = $booking_data['pricing'] ?? [];
        
        // Prezzi base
        update_post_meta($quote_id, '_price_per_person', floatval($pricing['price_per_person'] ?? 0));
        update_post_meta($quote_id, '_base_total', floatval($pricing['base_total'] ?? 0));
        
        // Prezzi bambini
        if (isset($pricing['child_prices'])) {
            update_post_meta($quote_id, '_child_prices', $pricing['child_prices']);
        }
        
        // Supplementi
        update_post_meta($quote_id, '_supplements_total', floatval($pricing['supplements_total'] ?? 0));
        
        // Notti extra
        if (isset($booking_data['extra_nights'])) {
            update_post_meta($quote_id, '_extra_nights_data', $booking_data['extra_nights']);
            update_post_meta($quote_id, '_extra_night_total', floatval($booking_data['extra_nights']['total_cost'] ?? 0));
        }
    }
    
    /**
     * Salva i costi extra
     */
    private function save_extra_costs($quote_id, $payload) {
        $booking_data = $payload['booking_data_json'] ?? [];
        $extra_costs = $booking_data['extra_costs'] ?? [];
        
        if (!empty($extra_costs)) {
            update_post_meta($quote_id, '_btr_extra_costs', $extra_costs);
            
            // Calcola totale costi extra
            $total = array_sum(array_column($extra_costs, 'total'));
            update_post_meta($quote_id, '_extra_costs_total', $total);
        }
    }
    
    /**
     * Salva i dati delle assicurazioni
     */
    private function save_insurance_data($quote_id, $payload) {
        $booking_data = $payload['booking_data_json'] ?? [];
        $insurance = $booking_data['insurance'] ?? [];
        
        if (!empty($insurance)) {
            update_post_meta($quote_id, '_btr_insurance', $insurance);
            update_post_meta($quote_id, '_insurance_total', floatval($insurance['total'] ?? 0));
        }
    }
    
    /**
     * Salva i metadata del pacchetto
     */
    private function save_package_metadata($quote_id, $payload) {
        $metadata = $payload['metadata'] ?? [];
        
        // Informazioni pacchetto
        update_post_meta($quote_id, '_package_title', sanitize_text_field($metadata['package_title'] ?? ''));
        update_post_meta($quote_id, '_package_slug', sanitize_text_field($metadata['package_slug'] ?? ''));
        update_post_meta($quote_id, '_package_sku', sanitize_text_field($metadata['sku'] ?? ''));
        
        // Durata
        update_post_meta($quote_id, '_duration_nights', intval($metadata['duration_nights'] ?? 0));
        update_post_meta($quote_id, '_duration_days', intval($metadata['duration_days'] ?? 0));
        
        // Categorie bambini
        if (isset($payload['child_categories'])) {
            update_post_meta($quote_id, '_child_categories', $payload['child_categories']);
        }
    }
    
    /**
     * Salva i dati di sessione WooCommerce
     */
    private function save_session_data($quote_id, $payload) {
        // Salva hash sessione per collegamento
        if (isset($payload['session_hash'])) {
            update_post_meta($quote_id, '_wc_session_hash', sanitize_text_field($payload['session_hash']));
        }
        
        // Salva ID carrello se presente
        if (isset($payload['cart_item_key'])) {
            update_post_meta($quote_id, '_wc_cart_item_key', sanitize_text_field($payload['cart_item_key']));
        }
    }
    
    /**
     * Calcola e salva i totali finali
     */
    private function calculate_and_save_totals($quote_id, $payload) {
        $booking_data = $payload['booking_data_json'] ?? [];
        $totals = $booking_data['pricing']['detailed_breakdown']['totali'] ?? [];
        
        // Usa i totali dal payload se disponibili
        if (!empty($totals)) {
            update_post_meta($quote_id, '_subtotal_base', floatval($totals['subtotale_base'] ?? 0));
            update_post_meta($quote_id, '_subtotal_supplements', floatval($totals['subtotale_supplementi_base'] ?? 0));
            update_post_meta($quote_id, '_subtotal_extra_nights', floatval($totals['subtotale_notti_extra'] ?? 0));
            update_post_meta($quote_id, '_subtotal_extra_costs', floatval($totals['subtotale_costi_extra'] ?? 0));
            update_post_meta($quote_id, '_subtotal_insurance', floatval($totals['subtotale_assicurazioni'] ?? 0));
            update_post_meta($quote_id, '_grand_total', floatval($totals['totale_generale'] ?? 0));
        } else {
            // Calcolo fallback se non abbiamo i totali nel payload
            $this->calculate_totals_fallback($quote_id, $payload);
        }
        
        // Salva sempre il totale principale
        $grand_total = floatval($payload['totale_generale'] ?? 0);
        update_post_meta($quote_id, '_btr_grand_total', $grand_total);
        update_post_meta($quote_id, '_totale_preventivo', $grand_total);
    }
    
    /**
     * Calcolo fallback dei totali
     */
    private function calculate_totals_fallback($quote_id, $payload) {
        $base_total = floatval($payload['totale_base'] ?? 0);
        $extra_costs = floatval($payload['totale_extra'] ?? 0);
        $insurance = floatval($payload['totale_assicurazioni'] ?? 0);
        
        $grand_total = $base_total + $extra_costs + $insurance;
        
        update_post_meta($quote_id, '_subtotal_base', $base_total);
        update_post_meta($quote_id, '_subtotal_extra_costs', $extra_costs);
        update_post_meta($quote_id, '_subtotal_insurance', $insurance);
        update_post_meta($quote_id, '_grand_total', $grand_total);
    }
    
    /**
     * Sanitizza il payload rimuovendo dati sensibili
     */
    private function sanitize_payload($payload) {
        // Rimuovi dati sensibili se presenti
        unset($payload['nonce']);
        unset($payload['security']);
        unset($payload['_wpnonce']);
        
        // Rimuovi dati di sessione sensibili
        if (isset($payload['session_data'])) {
            unset($payload['session_data']['customer_id']);
            unset($payload['session_data']['session_key']);
        }
        
        return $payload;
    }
    
    /**
     * Recupera i dati completi del preventivo
     * 
     * @param int $quote_id ID del preventivo
     * @return array Dati completi del preventivo
     */
    public function get_quote_data($quote_id) {
        $json_data = get_post_meta($quote_id, '_btr_quote_data_json', true);
        
        if ($json_data) {
            return json_decode($json_data, true);
        }
        
        // Fallback: costruisci dai meta separati
        return $this->build_quote_data_from_meta($quote_id);
    }
    
    /**
     * Costruisce i dati del preventivo dai meta fields
     */
    private function build_quote_data_from_meta($quote_id) {
        return [
            'metadata' => [
                'customer_name' => get_post_meta($quote_id, '_customer_name', true),
                'customer_email' => get_post_meta($quote_id, '_customer_email', true),
                'customer_phone' => get_post_meta($quote_id, '_customer_phone', true),
                'package_id' => get_post_meta($quote_id, '_package_id', true),
                'product_id' => get_post_meta($quote_id, '_product_id', true),
            ],
            'participants' => get_post_meta($quote_id, '_btr_participants', true) ?? [],
            'rooms' => get_post_meta($quote_id, '_btr_rooms', true) ?? [],
            'pricing' => [
                'base_total' => get_post_meta($quote_id, '_base_total', true),
                'supplements_total' => get_post_meta($quote_id, '_supplements_total', true),
                'extra_costs_total' => get_post_meta($quote_id, '_extra_costs_total', true),
                'insurance_total' => get_post_meta($quote_id, '_insurance_total', true),
                'grand_total' => get_post_meta($quote_id, '_grand_total', true),
            ],
            'dates' => [
                'check_in' => get_post_meta($quote_id, '_check_in_date', true),
                'check_out' => get_post_meta($quote_id, '_check_out_date', true),
                'travel_date' => get_post_meta($quote_id, '_travel_date', true),
            ]
        ];
    }
}