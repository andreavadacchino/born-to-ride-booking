<?php
/**
 * AJAX Handlers per il plugin Born to Ride Booking
 * 
 * Gestisce tutte le richieste AJAX del plugin, incluse quelle per
 * il date range picker e altre funzionalità amministrative.
 * 
 * @since 1.0.15
 * @author Born To Ride Booking
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_AJAX_Handlers {
    
    /**
     * Constructor - registra gli hook AJAX
     */
    public function __construct() {
        // Date Range Picker AJAX endpoints
        add_action('wp_ajax_btr_save_date_range', [$this, 'handle_save_date_range']);
        add_action('wp_ajax_btr_get_package_date_ranges', [$this, 'handle_get_package_date_ranges']);
        
        // Enqueue nonce per sicurezza AJAX
        add_action('admin_enqueue_scripts', [$this, 'localize_ajax_data']);
    }
    
    /**
     * Localizza i dati AJAX per gli script del admin
     */
    public function localize_ajax_data($hook) {
        // Solo nelle pagine admin dei pacchetti
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }
        
        global $post;
        if (!$post || $post->post_type !== 'pacchetti') {
            return;
        }
        
        wp_localize_script('jquery', 'btrAdmin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('btr_ajax_nonce'),
            'strings' => [
                'loading' => __('Loading...', 'born-to-ride-booking'),
                'error' => __('An error occurred', 'born-to-ride-booking'),
                'saved' => __('Saved successfully', 'born-to-ride-booking'),
            ]
        ]);
    }
    
    /**
     * Gestisce il salvataggio di un range di date
     */
    public function handle_save_date_range() {
        try {
            // Verifica nonce per sicurezza
            $this->verify_ajax_nonce();
            
            // Verifica capacità utente
            if (!current_user_can('edit_posts')) {
                throw new Exception(__('Insufficient permissions', 'born-to-ride-booking'));
            }
            
            // Validazione parametri richiesti
            $package_id = $this->get_required_param('package_id', 'int');
            $start_date = $this->get_required_param('start_date', 'string');
            $end_date = $this->get_required_param('end_date', 'string');
            
            // Validazione formato date
            if (!$this->is_valid_date($start_date) || !$this->is_valid_date($end_date)) {
                throw new Exception(__('Invalid date format. Use Y-m-d format.', 'born-to-ride-booking'));
            }
            
            // Validazione package ID
            $package = get_post($package_id);
            if (!$package || $package->post_type !== 'pacchetti') {
                throw new Exception(__('Invalid package ID', 'born-to-ride-booking'));
            }
            
            // Opzioni di salvataggio
            $options = [
                'replace_existing' => true, // Sostituisce range esistenti
                'is_available' => 1,
                'max_capacity' => null,
                'current_bookings' => 0,
                'price_modifier' => 0.00,
                'notes' => sanitize_text_field($_POST['notes'] ?? '')
            ];
            
            // Includi il Date Range Manager se non già caricato
            if (!class_exists('BTR_Date_Range_Manager')) {
                require_once BTR_PLUGIN_DIR . 'includes/class-btr-date-range-manager.php';
            }
            
            // Salva il range usando il Date Range Manager
            $date_manager = new BTR_Date_Range_Manager();
            $result = $date_manager->save_date_range($package_id, $start_date, $end_date, $options);
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            
            // Log dell'operazione
            $this->log_date_range_action('saved', $package_id, $start_date, $end_date, $result);
            
            // Risposta di successo
            wp_send_json_success([
                'message' => __('Date range saved successfully', 'born-to-ride-booking'),
                'range_data' => $result,
                'package_id' => $package_id,
                'start_date' => $start_date,
                'end_date' => $end_date
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => 'date_range_save_error'
            ]);
        }
    }
    
    /**
     * Gestisce il recupero dei range di date per un pacchetto
     */
    public function handle_get_package_date_ranges() {
        try {
            // Verifica nonce per sicurezza
            $this->verify_ajax_nonce();
            
            // Verifica capacità utente
            if (!current_user_can('edit_posts')) {
                throw new Exception(__('Insufficient permissions', 'born-to-ride-booking'));
            }
            
            // Validazione parametri
            $package_id = $this->get_required_param('package_id', 'int');
            
            // Validazione package ID
            $package = get_post($package_id);
            if (!$package || $package->post_type !== 'pacchetti') {
                throw new Exception(__('Invalid package ID', 'born-to-ride-booking'));
            }
            
            // Filtri opzionali
            $filters = [];
            if (isset($_POST['available_only']) && $_POST['available_only']) {
                $filters['available_only'] = true;
            }
            if (isset($_POST['date_from']) && $this->is_valid_date($_POST['date_from'])) {
                $filters['date_from'] = sanitize_text_field($_POST['date_from']);
            }
            if (isset($_POST['date_to']) && $this->is_valid_date($_POST['date_to'])) {
                $filters['date_to'] = sanitize_text_field($_POST['date_to']);
            }
            
            // Includi il Date Range Manager se non già caricato
            if (!class_exists('BTR_Date_Range_Manager')) {
                require_once BTR_PLUGIN_DIR . 'includes/class-btr-date-range-manager.php';
            }
            
            // Recupera i range esistenti
            $date_manager = new BTR_Date_Range_Manager();
            $ranges = $date_manager->get_package_date_ranges($package_id, $filters);
            
            // Raggruppa per range continuo per facilitare la visualizzazione
            $grouped_ranges = $this->group_continuous_ranges($ranges);
            
            // Log dell'operazione
            $this->log_date_range_action('retrieved', $package_id, null, null, [
                'total_ranges' => count($grouped_ranges),
                'total_dates' => count($ranges)
            ]);
            
            // Risposta di successo
            wp_send_json_success([
                'ranges' => $grouped_ranges,
                'individual_dates' => $ranges,
                'package_id' => $package_id,
                'filters_applied' => $filters,
                'total_ranges' => count($grouped_ranges),
                'total_dates' => count($ranges)
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error([
                'message' => $e->getMessage(),
                'code' => 'date_range_get_error'
            ]);
        }
    }
    
    /**
     * Verifica il nonce AJAX per sicurezza
     * @throws Exception Se il nonce non è valido
     */
    private function verify_ajax_nonce() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'btr_ajax_nonce')) {
            throw new Exception(__('Invalid security token', 'born-to-ride-booking'));
        }
    }
    
    /**
     * Ottiene un parametro richiesto dalla richiesta POST
     * @param string $key Chiave del parametro
     * @param string $type Tipo di validazione: 'int', 'string', 'float'
     * @return mixed Valore validato
     * @throws Exception Se il parametro non è presente o non valido
     */
    private function get_required_param($key, $type = 'string') {
        if (!isset($_POST[$key]) || empty($_POST[$key])) {
            throw new Exception(sprintf(__('Required parameter "%s" is missing', 'born-to-ride-booking'), $key));
        }
        
        $value = $_POST[$key];
        
        switch ($type) {
            case 'int':
                $value = intval($value);
                if ($value <= 0) {
                    throw new Exception(sprintf(__('Parameter "%s" must be a positive integer', 'born-to-ride-booking'), $key));
                }
                break;
                
            case 'float':
                $value = floatval($value);
                break;
                
            case 'string':
            default:
                $value = sanitize_text_field($value);
                if (empty($value)) {
                    throw new Exception(sprintf(__('Parameter "%s" cannot be empty', 'born-to-ride-booking'), $key));
                }
                break;
        }
        
        return $value;
    }
    
    /**
     * Valida il formato di una data
     * @param string $date Data da validare
     * @return bool True se valida
     */
    private function is_valid_date($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    /**
     * Raggruppa le date individuali in range continui
     * @param array $individual_dates Array delle date individuali
     * @return array Array dei range raggruppati
     */
    private function group_continuous_ranges($individual_dates) {
        if (empty($individual_dates)) {
            return [];
        }
        
        $grouped = [];
        $current_group = null;
        
        foreach ($individual_dates as $date_record) {
            $range_key = $date_record['range_start_date'] . '_' . $date_record['range_end_date'];
            
            if (!isset($grouped[$range_key])) {
                $grouped[$range_key] = [
                    'range_start_date' => $date_record['range_start_date'],
                    'range_end_date' => $date_record['range_end_date'],
                    'package_id' => $date_record['package_id'],
                    'total_days' => 0,
                    'available_days' => 0,
                    'dates' => [],
                    'created_at' => $date_record['created_at'],
                    'updated_at' => $date_record['updated_at']
                ];
            }
            
            $grouped[$range_key]['dates'][] = $date_record;
            $grouped[$range_key]['total_days']++;
            
            if ($date_record['is_available']) {
                $grouped[$range_key]['available_days']++;
            }
            
            // Aggiorna il timestamp più recente
            if ($date_record['updated_at'] > $grouped[$range_key]['updated_at']) {
                $grouped[$range_key]['updated_at'] = $date_record['updated_at'];
            }
        }
        
        // Ordina le date all'interno di ogni gruppo
        foreach ($grouped as &$group) {
            usort($group['dates'], function($a, $b) {
                return strcmp($a['single_date'], $b['single_date']);
            });
        }
        
        return array_values($grouped);
    }
    
    /**
     * Log delle operazioni sui range di date
     * @param string $action Azione eseguita
     * @param int $package_id ID del pacchetto
     * @param string|null $start_date Data inizio
     * @param string|null $end_date Data fine
     * @param array|null $additional_data Dati aggiuntivi
     */
    private function log_date_range_action($action, $package_id, $start_date = null, $end_date = null, $additional_data = null) {
        if (!defined('BTR_DEBUG') || !BTR_DEBUG) {
            return;
        }
        
        $log_data = [
            'timestamp' => current_time('mysql'),
            'action' => $action,
            'package_id' => $package_id,
            'user_id' => get_current_user_id(),
            'start_date' => $start_date,
            'end_date' => $end_date,
            'additional_data' => $additional_data
        ];
        
        $log_message = sprintf(
            '[BTR Date Range] %s - Action: %s, Package: %d, User: %d, Range: %s to %s',
            $log_data['timestamp'],
            $action,
            $package_id,
            $log_data['user_id'],
            $start_date ?: 'N/A',
            $end_date ?: 'N/A'
        );
        
        if ($additional_data) {
            $log_message .= ' - Data: ' . json_encode($additional_data);
        }
        
        error_log($log_message);
        
        // Trigger action per eventuali estensioni
        do_action('btr_date_range_action_logged', $log_data);
    }
}