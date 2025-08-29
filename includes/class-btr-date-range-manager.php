<?php
/**
 * Gestione date ranges continue per pacchetti
 * 
 * Gestisce la creazione, validazione e salvataggio di intervalli continui
 * di date per i pacchetti, sostituendo il sistema di calendario discreto.
 * 
 * @since 1.0.15
 * @author Born To Ride Booking
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Date_Range_Manager {
    
    /**
     * Validazione e salvataggio di un range di date
     * 
     * @param int $package_id ID del pacchetto
     * @param string $start_date Data inizio (Y-m-d)
     * @param string $end_date Data fine (Y-m-d) 
     * @param array $options Opzioni aggiuntive
     * @return array|WP_Error Array con i giorni creati o errore
     */
    public function save_date_range(int $package_id, string $start_date, string $end_date, array $options = []) {
        try {
            // Validazione range
            $this->validate_date_range($start_date, $end_date);
            
            // Validazione package
            $this->validate_package($package_id);
            
            // Generazione giorni continui
            $continuous_dates = $this->generate_continuous_dates($start_date, $end_date);
            
            // Salvataggio nel database
            $saved_records = $this->save_continuous_dates($package_id, $start_date, $end_date, $continuous_dates, $options);
            
            return [
                'success' => true,
                'range_start' => $start_date,
                'range_end' => $end_date,
                'total_days' => count($continuous_dates),
                'saved_records' => count($saved_records),
                'dates' => $continuous_dates
            ];
            
        } catch (Exception $e) {
            return new WP_Error(
                'date_range_error',
                $e->getMessage(),
                ['status' => 400]
            );
        }
    }
    
    /**
     * Validazione del range di date
     * 
     * @param string $start_date Data inizio
     * @param string $end_date Data fine
     * @throws InvalidArgumentException Se il range non è valido
     */
    private function validate_date_range(string $start_date, string $end_date): void {
        // Validazione formato date
        $start_time = strtotime($start_date);
        $end_time = strtotime($end_date);
        
        if ($start_time === false) {
            throw new InvalidArgumentException(__('Invalid start date format', 'born-to-ride-booking'));
        }
        
        if ($end_time === false) {
            throw new InvalidArgumentException(__('Invalid end date format', 'born-to-ride-booking'));
        }
        
        // Validazione ordine logico
        if ($end_time < $start_time) {
            throw new InvalidArgumentException(__('End date cannot be before start date', 'born-to-ride-booking'));
        }
        
        // Validazione range massimo (es. 1 anno)
        $max_range_days = apply_filters('btr_max_date_range_days', 365);
        $range_days = ($end_time - $start_time) / DAY_IN_SECONDS;
        
        if ($range_days > $max_range_days) {
            throw new InvalidArgumentException(
                sprintf(__('Date range cannot exceed %d days', 'born-to-ride-booking'), $max_range_days)
            );
        }
        
        // Validazione date nel passato
        $today = strtotime('today');
        if ($start_time < $today) {
            throw new InvalidArgumentException(__('Start date cannot be in the past', 'born-to-ride-booking'));
        }
    }
    
    /**
     * Validazione del pacchetto
     * 
     * @param int $package_id ID del pacchetto
     * @throws InvalidArgumentException Se il pacchetto non è valido
     */
    private function validate_package(int $package_id): void {
        $package = get_post($package_id);
        
        if (!$package || $package->post_type !== 'btr_pacchetti') {
            throw new InvalidArgumentException(__('Invalid package ID', 'born-to-ride-booking'));
        }
        
        if ($package->post_status !== 'publish') {
            throw new InvalidArgumentException(__('Package must be published', 'born-to-ride-booking'));
        }
    }
    
    /**
     * Generazione di un array di date continue
     * 
     * @param string $start_date Data inizio
     * @param string $end_date Data fine
     * @return array Array di date in formato Y-m-d
     */
    private function generate_continuous_dates(string $start_date, string $end_date): array {
        $dates = [];
        $current = strtotime($start_date);
        $end = strtotime($end_date);
        $day_index = 0;
        
        while ($current <= $end) {
            $dates[] = [
                'date' => date('Y-m-d', $current),
                'day_index' => $day_index,
                'timestamp' => $current
            ];
            
            $current = strtotime('+1 day', $current);
            $day_index++;
        }
        
        return $dates;
    }
    
    /**
     * Salvataggio delle date continue nel database
     * 
     * @param int $package_id ID del pacchetto
     * @param string $start_date Data inizio range
     * @param string $end_date Data fine range
     * @param array $continuous_dates Array delle date generate
     * @param array $options Opzioni aggiuntive
     * @return array Array degli ID dei record salvati
     */
    private function save_continuous_dates(int $package_id, string $start_date, string $end_date, array $continuous_dates, array $options): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'btr_package_date_ranges';
        $saved_records = [];
        $now = current_time('mysql');
        
        // Rimozione range esistenti sovrapposti (opzionale)
        if (isset($options['replace_existing']) && $options['replace_existing']) {
            $this->remove_overlapping_ranges($package_id, $start_date, $end_date);
        }
        
        // Preparazione dati default
        $default_options = [
            'is_available' => 1,
            'max_capacity' => null,
            'current_bookings' => 0,
            'price_modifier' => 0.00,
            'notes' => null
        ];
        
        $insert_options = array_merge($default_options, $options);
        
        // Inserimento batch per performance
        $values = [];
        $placeholders = [];
        
        foreach ($continuous_dates as $date_info) {
            $placeholders[] = "(%d, %s, %s, %s, %d, %d, %d, %d, %f, %s, %s, %s)";
            $values = array_merge($values, [
                $package_id,
                $start_date,
                $end_date,
                $date_info['date'],
                $date_info['day_index'],
                $insert_options['is_available'],
                $insert_options['max_capacity'],
                $insert_options['current_bookings'],
                $insert_options['price_modifier'],
                $insert_options['notes'],
                $now,
                $now
            ]);
        }
        
        if (!empty($placeholders)) {
            $sql = "INSERT INTO {$table_name} 
                    (package_id, range_start_date, range_end_date, single_date, day_index, 
                     is_available, max_capacity, current_bookings, price_modifier, notes, 
                     created_at, updated_at) 
                    VALUES " . implode(', ', $placeholders);
            
            $result = $wpdb->query($wpdb->prepare($sql, $values));
            
            if ($result === false) {
                throw new Exception(__('Failed to save date ranges to database', 'born-to-ride-booking'));
            }
            
            // Recupero ID inseriti
            $first_id = $wpdb->insert_id;
            for ($i = 0; $i < count($continuous_dates); $i++) {
                $saved_records[] = $first_id + $i;
            }
        }
        
        return $saved_records;
    }
    
    /**
     * Rimozione range sovrapposti esistenti
     * 
     * @param int $package_id ID del pacchetto
     * @param string $start_date Data inizio
     * @param string $end_date Data fine
     */
    private function remove_overlapping_ranges(int $package_id, string $start_date, string $end_date): void {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'btr_package_date_ranges';
        
        $wpdb->delete(
            $table_name,
            [
                'package_id' => $package_id
            ],
            [
                '%d'
            ]
        );
        
        // Log dell'operazione
        do_action('btr_date_ranges_removed', $package_id, $start_date, $end_date);
    }
    
    /**
     * Recupero range di date per un pacchetto
     * 
     * @param int $package_id ID del pacchetto
     * @param array $filters Filtri opzionali
     * @return array Array dei range trovati
     */
    public function get_package_date_ranges(int $package_id, array $filters = []): array {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'btr_package_date_ranges';
        
        $where_conditions = ['package_id = %d'];
        $where_values = [$package_id];
        
        // Filtri opzionali
        if (isset($filters['available_only']) && $filters['available_only']) {
            $where_conditions[] = 'is_available = 1';
        }
        
        if (isset($filters['date_from'])) {
            $where_conditions[] = 'single_date >= %s';
            $where_values[] = $filters['date_from'];
        }
        
        if (isset($filters['date_to'])) {
            $where_conditions[] = 'single_date <= %s';
            $where_values[] = $filters['date_to'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $sql = "SELECT * FROM {$table_name} 
                WHERE {$where_clause} 
                ORDER BY range_start_date ASC, day_index ASC";
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $where_values), ARRAY_A);
        
        return $results ?: [];
    }
    
    /**
     * Verifica disponibilità per una data specifica
     * 
     * @param int $package_id ID del pacchetto
     * @param string $date Data da verificare (Y-m-d)
     * @return bool|array False se non disponibile, array con info se disponibile
     */
    public function check_date_availability(int $package_id, string $date) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'btr_package_date_ranges';
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE package_id = %d 
             AND single_date = %s 
             AND is_available = 1
             LIMIT 1",
            $package_id,
            $date
        ), ARRAY_A);
        
        if (!$result) {
            return false;
        }
        
        // Verifica capacità se impostata
        if ($result['max_capacity'] !== null) {
            if ($result['current_bookings'] >= $result['max_capacity']) {
                return false;
            }
        }
        
        return $result;
    }
    
    /**
     * Aggiornamento contatore prenotazioni per una data
     * 
     * @param int $package_id ID del pacchetto
     * @param string $date Data (Y-m-d)
     * @param int $increment Incremento (può essere negativo)
     * @return bool Successo operazione
     */
    public function update_bookings_count(int $package_id, string $date, int $increment = 1): bool {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'btr_package_date_ranges';
        
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE {$table_name} 
             SET current_bookings = GREATEST(0, current_bookings + %d),
                 updated_at = %s
             WHERE package_id = %d 
             AND single_date = %s",
            $increment,
            current_time('mysql'),
            $package_id,
            $date
        ));
        
        return $result !== false;
    }
}