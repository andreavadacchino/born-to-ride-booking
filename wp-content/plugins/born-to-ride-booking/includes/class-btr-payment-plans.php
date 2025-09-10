<?php
/**
 * Gestione Piani di Pagamento per Born to Ride Booking
 * 
 * Estende il sistema di pagamento esistente per supportare:
 * - Pagamento completo
 * - Caparra + Saldo
 * - Suddivisione quote tra partecipanti
 *
 * @package BornToRideBooking
 * @since 1.0.98
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Payment_Plans {
    
    /**
     * Tipi di piano di pagamento supportati
     */
    const PLAN_TYPE_FULL = 'full';
    const PLAN_TYPE_DEPOSIT_BALANCE = 'deposit_balance';
    const PLAN_TYPE_GROUP_SPLIT = 'group_split';
    
    /**
     * Singleton instance
     * @var BTR_Payment_Plans
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     * @return BTR_Payment_Plans
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Hook dopo il salvataggio degli anagrafici
        add_action('btr_after_save_anagrafici', [$this, 'maybe_show_payment_plan_selection'], 10, 2);
        
        // AJAX handlers
        add_action('wp_ajax_btr_save_payment_plan', [$this, 'ajax_save_payment_plan']);
        add_action('wp_ajax_nopriv_btr_save_payment_plan', [$this, 'ajax_save_payment_plan']);
        
        add_action('wp_ajax_btr_generate_group_split_links', [$this, 'ajax_generate_group_split_links']);
        add_action('wp_ajax_nopriv_btr_generate_group_split_links', [$this, 'ajax_generate_group_split_links']);
        
        // Estendi il checkout per gestire pagamenti frazionati
        add_filter('woocommerce_checkout_order_processed', [$this, 'handle_split_payment_order'], 20, 3);
        
        // Aggiungi colonne admin
        add_filter('btr_payment_admin_columns', [$this, 'add_payment_plan_columns']);
        add_filter('btr_payment_admin_column_data', [$this, 'add_payment_plan_column_data'], 10, 3);
    }
    
    /**
     * Crea un nuovo piano di pagamento
     * 
     * @param int $preventivo_id ID del preventivo
     * @param array $args Argomenti del piano
     * @return int|WP_Error ID del piano creato o errore
     */
    public function create_payment_plan($preventivo_id, $args = []) {
        global $wpdb;
        
        $defaults = [
            'plan_type' => self::PLAN_TYPE_FULL,
            'deposit_percentage' => 30,
            'total_participants' => 1,
            'payment_distribution' => null
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        // Calcola totale dal preventivo
        $total_amount = $this->calculate_total_from_preventivo($preventivo_id);
        if (is_wp_error($total_amount)) {
            return $total_amount;
        }
        
        // Valida tipo di piano
        $valid_types = [self::PLAN_TYPE_FULL, self::PLAN_TYPE_DEPOSIT_BALANCE, self::PLAN_TYPE_GROUP_SPLIT];
        if (!in_array($args['plan_type'], $valid_types)) {
            return new WP_Error('invalid_plan_type', 'Tipo di piano non valido');
        }
        
        // Inserisci nel database
        $result = $wpdb->insert(
            $wpdb->prefix . 'btr_payment_plans',
            [
                'preventivo_id' => $preventivo_id,
                'plan_type' => $args['plan_type'],
                'total_amount' => $total_amount,
                'deposit_percentage' => $args['deposit_percentage'],
                'total_participants' => $args['total_participants'],
                'payment_distribution' => maybe_serialize($args['payment_distribution'])
            ],
            ['%d', '%s', '%f', '%d', '%d', '%s']
        );
        
        if (false === $result) {
            return new WP_Error('db_error', 'Errore nel salvataggio del piano di pagamento');
        }
        
        $plan_id = $wpdb->insert_id;
        
        // Log per debug
        if (defined('BTR_DEBUG') && BTR_DEBUG) {
            error_log(sprintf(
                '[BTR Payment Plans] Piano creato: ID=%d, Preventivo=%d, Tipo=%s, Totale=%f',
                $plan_id,
                $preventivo_id,
                $args['plan_type'],
                $total_amount
            ));
        }
        
        return $plan_id;
    }
    
    /**
     * Genera link di pagamento per gruppo
     * 
     * @param int $preventivo_id ID del preventivo
     * @param array $distribution Distribuzione quote
     * @return array|WP_Error Array di link generati o errore
     */
    public function generate_group_payment_links($preventivo_id, $distribution) {
        global $wpdb;
        
        // Verifica che esista un piano di tipo group_split
        $plan = $this->get_payment_plan_by_preventivo($preventivo_id);
        if (!$plan || $plan->plan_type !== self::PLAN_TYPE_GROUP_SPLIT) {
            return new WP_Error('invalid_plan', 'Piano di pagamento non trovato o non di tipo gruppo');
        }
        
        $total_amount = floatval($plan->total_amount);
        $links = [];
        
        // Recupera dati anagrafici per i nomi
        $anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
        if (!$anagrafici || !is_array($anagrafici)) {
            return new WP_Error('no_anagrafici', 'Dati anagrafici non trovati');
        }
        
        // Genera link per ogni partecipante
        foreach ($distribution as $participant_index => $share_info) {
            $share_percentage = floatval($share_info['percentage']);
            $share_amount = round($total_amount * ($share_percentage / 100), 2);
            
            // Recupera nome partecipante
            $participant_name = 'Partecipante ' . ($participant_index + 1);
            if (isset($anagrafici[$participant_index])) {
                $nome = $anagrafici[$participant_index]['nome'] ?? '';
                $cognome = $anagrafici[$participant_index]['cognome'] ?? '';
                if ($nome && $cognome) {
                    $participant_name = $nome . ' ' . $cognome;
                }
            }
            
            // Genera hash univoco
            $hash = wp_generate_password(32, false);
            
            // Inserisci record pagamento
            $payment_data = [
                'payment_hash' => $hash,
                'preventivo_id' => $preventivo_id,
                'payment_type' => 'group_share',
                'amount' => $share_amount,
                'status' => 'pending',
                'payment_plan_type' => self::PLAN_TYPE_GROUP_SPLIT,
                'group_member_id' => $participant_index,
                'group_member_name' => $participant_name,
                'share_percentage' => $share_percentage
            ];
            
            $result = $wpdb->insert(
                $wpdb->prefix . 'btr_group_payments',
                $payment_data,
                ['%s', '%d', '%s', '%f', '%s', '%s', '%d', '%s', '%f']
            );
            
            if (false === $result) {
                continue;
            }
            
            // Genera URL di pagamento
            $payment_url = home_url('/pagamento-gruppo/' . $hash);
            
            $links[] = [
                'participant_index' => $participant_index,
                'participant_name' => $participant_name,
                'amount' => $share_amount,
                'percentage' => $share_percentage,
                'url' => $payment_url,
                'hash' => $hash
            ];
        }
        
        return $links;
    }
    
    /**
     * Calcola il totale dal preventivo
     * 
     * @param int $preventivo_id
     * @return float|WP_Error
     */
    private function calculate_total_from_preventivo($preventivo_id) {
        // Prima prova con _totale_preventivo (campo principale usato nella pagina di selezione)
        $total = get_post_meta($preventivo_id, '_totale_preventivo', true);
        if ($total !== false && $total !== '') {
            $total_float = floatval($total);
            if ($total_float > 0) {
                return $total_float;
            }
        }
        
        // Secondo tentativo: riepilogo dettagliato
        $riepilogo = get_post_meta($preventivo_id, '_riepilogo_calcoli_dettagliato', true);
        if (!empty($riepilogo) && is_array($riepilogo)) {
            if (isset($riepilogo['totali']['totale_finale'])) {
                $total_float = floatval($riepilogo['totali']['totale_finale']);
                if ($total_float > 0) {
                    return $total_float;
                }
            }
        }
        
        // Terzo tentativo: fallback al prezzo totale semplice
        $total = get_post_meta($preventivo_id, '_prezzo_totale', true);
        if ($total !== false && $total !== '') {
            $total_float = floatval($total);
            if ($total_float > 0) {
                return $total_float;
            }
        }
        
        // Se tutti i tentativi falliscono, prova a calcolare manualmente
        $prezzo_base = floatval(get_post_meta($preventivo_id, '_prezzo_base', true));
        $supplementi = floatval(get_post_meta($preventivo_id, '_supplementi', true));
        $sconti = floatval(get_post_meta($preventivo_id, '_sconti', true));
        $totale_assicurazioni = floatval(get_post_meta($preventivo_id, '_totale_assicurazioni', true));
        $totale_costi_extra = floatval(get_post_meta($preventivo_id, '_totale_costi_extra', true));
        
        $calculated_total = $prezzo_base + $supplementi - $sconti + $totale_assicurazioni + $totale_costi_extra;
        
        if ($calculated_total > 0) {
            // Salva il totale calcolato per uso futuro
            update_post_meta($preventivo_id, '_totale_preventivo', $calculated_total);
            return $calculated_total;
        }
        
        return new WP_Error('no_price', 'Impossibile determinare il prezzo totale');
    }
    
    /**
     * Recupera piano di pagamento per preventivo
     * 
     * @param int $preventivo_id
     * @return object|null
     */
    public function get_payment_plan_by_preventivo($preventivo_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'btr_payment_plans';
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE preventivo_id = %d ORDER BY id DESC LIMIT 1",
            $preventivo_id
        ));
    }
    
    /**
     * Metodo statico per recuperare piano di pagamento
     * Utilizzato da class-btr-payment-integration.php
     * 
     * @param int $preventivo_id
     * @return object|null
     */
    public static function get_payment_plan($preventivo_id) {
        return self::get_instance()->get_payment_plan_by_preventivo($preventivo_id);
    }
    
    /**
     * AJAX handler per salvare il piano di pagamento
     */
    public function ajax_save_payment_plan() {
        // Verifica nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'btr_payment_plan_nonce')) {
            wp_send_json_error(['message' => 'Nonce non valido']);
        }
        
        $preventivo_id = isset($_POST['preventivo_id']) ? intval($_POST['preventivo_id']) : 0;
        $plan_type = isset($_POST['plan_type']) ? sanitize_text_field($_POST['plan_type']) : '';
        
        if (!$preventivo_id || !$plan_type) {
            wp_send_json_error(['message' => 'Dati mancanti']);
        }
        
        $args = [
            'plan_type' => $plan_type,
            'deposit_percentage' => isset($_POST['deposit_percentage']) ? intval($_POST['deposit_percentage']) : 30,
            'total_participants' => isset($_POST['total_participants']) ? intval($_POST['total_participants']) : 1,
            'payment_distribution' => isset($_POST['payment_distribution']) ? $_POST['payment_distribution'] : null
        ];
        
        $plan_id = $this->create_payment_plan($preventivo_id, $args);
        
        if (is_wp_error($plan_id)) {
            wp_send_json_error(['message' => $plan_id->get_error_message()]);
        }
        
        wp_send_json_success([
            'plan_id' => $plan_id,
            'message' => 'Piano di pagamento salvato con successo'
        ]);
    }
    
    /**
     * AJAX handler per generare link pagamento gruppo
     */
    public function ajax_generate_group_split_links() {
        // Verifica nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'btr_payment_plan_nonce')) {
            wp_send_json_error(['message' => 'Nonce non valido']);
        }
        
        $preventivo_id = isset($_POST['preventivo_id']) ? intval($_POST['preventivo_id']) : 0;
        $distribution = isset($_POST['distribution']) ? $_POST['distribution'] : [];
        
        if (!$preventivo_id || empty($distribution)) {
            wp_send_json_error(['message' => 'Dati mancanti']);
        }
        
        $links = $this->generate_group_payment_links($preventivo_id, $distribution);
        
        if (is_wp_error($links)) {
            wp_send_json_error(['message' => $links->get_error_message()]);
        }
        
        wp_send_json_success([
            'links' => $links,
            'message' => 'Link di pagamento generati con successo'
        ]);
    }
    
    /**
     * Mostra selezione piano di pagamento dopo anagrafici
     * 
     * @param int $preventivo_id
     * @param array $anagrafici
     */
    public function maybe_show_payment_plan_selection($preventivo_id, $anagrafici) {
        // Verifica se deve mostrare la selezione del piano
        if (!$this->should_show_payment_plan_selection($preventivo_id)) {
            return;
        }
        
        // Conta adulti per determinare chi può pagare
        $adults_count = 0;
        foreach ($anagrafici as $participant) {
            if (empty($participant['fascia']) || $participant['fascia'] === 'adulto') {
                $adults_count++;
            }
        }
        
        // Passa alla vista per rendering
        include BTR_PLUGIN_DIR . 'templates/frontend/payment-plan-selection.php';
    }
    
    /**
     * Verifica se mostrare la selezione del piano di pagamento
     * 
     * @param int $preventivo_id
     * @return bool
     */
    private function should_show_payment_plan_selection($preventivo_id) {
        // Non mostrare se già esiste un piano
        $existing_plan = $this->get_payment_plan_by_preventivo($preventivo_id);
        if ($existing_plan) {
            return false;
        }
        
        // Verifica altre condizioni (es. importo minimo)
        $total = $this->calculate_total_from_preventivo($preventivo_id);
        if (is_wp_error($total) || $total < 100) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Gestisce ordini con pagamento frazionato
     * 
     * @param int $order_id
     * @param array $posted_data
     * @param WC_Order $order
     */
    public function handle_split_payment_order($order_id, $posted_data, $order) {
        $preventivo_id = $order->get_meta('_preventivo_id');
        if (!$preventivo_id) {
            return;
        }
        
        $plan = $this->get_payment_plan_by_preventivo($preventivo_id);
        if (!$plan) {
            return;
        }
        
        // Aggiorna meta ordine con info piano di pagamento
        $order->update_meta_data('_btr_payment_plan_type', $plan->plan_type);
        $order->update_meta_data('_btr_payment_plan_id', $plan->id);
        
        // Se è un pagamento di gruppo, marca come parziale
        if ($plan->plan_type === self::PLAN_TYPE_GROUP_SPLIT) {
            $payment_hash = isset($_GET['payment_hash']) ? sanitize_text_field($_GET['payment_hash']) : '';
            if ($payment_hash) {
                $order->update_meta_data('_btr_payment_hash', $payment_hash);
                $order->update_meta_data('_btr_is_partial_payment', 'yes');
            }
        }
        
        $order->save();
    }
    
    /**
     * Aggiunge colonne admin per piani di pagamento
     * 
     * @param array $columns
     * @return array
     */
    public function add_payment_plan_columns($columns) {
        $columns['payment_plan'] = __('Piano Pagamento', 'born-to-ride-booking');
        return $columns;
    }
    
    /**
     * Aggiunge dati colonne admin
     * 
     * @param string $output
     * @param string $column_name
     * @param object $payment
     * @return string
     */
    public function add_payment_plan_column_data($output, $column_name, $payment) {
        if ($column_name !== 'payment_plan') {
            return $output;
        }
        
        $plan_labels = [
            self::PLAN_TYPE_FULL => __('Pagamento Completo', 'born-to-ride-booking'),
            self::PLAN_TYPE_DEPOSIT_BALANCE => __('Caparra + Saldo', 'born-to-ride-booking'),
            self::PLAN_TYPE_GROUP_SPLIT => __('Suddivisione Gruppo', 'born-to-ride-booking')
        ];
        
        $plan_type = $payment->payment_plan_type ?? self::PLAN_TYPE_FULL;
        $label = $plan_labels[$plan_type] ?? $plan_type;
        
        if ($plan_type === self::PLAN_TYPE_GROUP_SPLIT && !empty($payment->group_member_name)) {
            $label .= '<br><small>' . esc_html($payment->group_member_name) . '</small>';
        }
        
        return $label;
    }
}

// Inizializza la classe
BTR_Payment_Plans::get_instance();