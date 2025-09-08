<?php
/**
 * Sistema validazione età bambini alla partenza viaggio
 * 
 * Verifica che l'età dei bambini al momento della partenza
 * corrisponda alle categorie child selezionate
 * 
 * @since 1.0.15
 * @author Born To Ride Booking
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Child_Age_Validator {
    
    public function __construct() {
        // Hook per validazione AJAX durante la prenotazione
        add_action('wp_ajax_btr_validate_child_ages', [$this, 'ajax_validate_child_ages']);
        add_action('wp_ajax_nopriv_btr_validate_child_ages', [$this, 'ajax_validate_child_ages']);
        
        // Hook per validazione prima del salvataggio preventivo
        add_action('btr_before_create_preventivo', [$this, 'validate_before_preventivo'], 10, 2);
        
        // Aggiunge script per validazione frontend
        add_action('wp_enqueue_scripts', [$this, 'enqueue_validation_scripts']);
        
        // Menu admin per impostazioni validazione
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // AJAX per impostazioni admin
        add_action('wp_ajax_btr_save_age_validation_settings', [$this, 'ajax_save_settings']);
        
        // Inizializza impostazioni default
        $this->maybe_initialize_defaults();
    }

    /**
     * Inizializza impostazioni default
     */
    private function maybe_initialize_defaults() {
        $settings = get_option('btr_age_validation_settings', false);
        if ($settings === false) {
            $default_settings = [
                'enabled' => true,
                'strict_validation' => false, // Se true, blocca la prenotazione
                'show_warnings' => true,
                'auto_suggest_corrections' => true,
                'allow_age_tolerance' => true,
                'tolerance_months' => 3, // Tolleranza in mesi
                'notification_type' => 'warning' // warning, error, info
            ];
            update_option('btr_age_validation_settings', $default_settings);
        }
    }

    /**
     * Aggiunge menu admin
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=preventivo',
            'Validazione Età Bambini',
            'Validazione Età',
            'manage_options',
            'btr-age-validation',
            [$this, 'admin_page']
        );
    }

    /**
     * Pagina admin
     */
    public function admin_page() {
        include BTR_PLUGIN_DIR . 'admin/views/age-validation-admin.php';
    }

    /**
     * Aggiunge script di validazione al frontend
     */
    public function enqueue_validation_scripts() {
        if (is_singular('pacchetti') || (function_exists('is_shop') && is_shop())) {
            wp_enqueue_script(
                'btr-age-validator',
                BTR_PLUGIN_URL . 'assets/js/child-age-validator.js',
                ['jquery'],
                BTR_VERSION,
                true
            );
            
            wp_localize_script('btr-age-validator', 'btrAgeValidator', [
                'settings' => $this->get_validation_settings(),
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('btr_age_validation')
            ]);
        }
    }

    /**
     * Ottiene impostazioni validazione
     */
    public function get_validation_settings() {
        return get_option('btr_age_validation_settings', []);
    }

    /**
     * Calcola età al momento della partenza
     */
    public function calculate_age_at_departure($birth_date, $departure_date) {
        if (empty($birth_date) || empty($departure_date)) {
            return null;
        }

        try {
            $birth = new DateTime($birth_date);
            $departure = new DateTime($departure_date);
            
            if ($birth > $departure) {
                return null; // Data di nascita nel futuro rispetto alla partenza
            }
            
            $interval = $birth->diff($departure);
            return [
                'years' => $interval->y,
                'months' => $interval->m,
                'days' => $interval->d,
                'total_months' => ($interval->y * 12) + $interval->m
            ];
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Verifica se l'età rientra in una categoria
     */
    public function check_age_in_category($age_data, $category_config) {
        if (!$age_data || !$category_config) {
            return false;
        }

        $age_years = $age_data['years'];
        $age_months = $age_data['total_months'];
        
        $min_age_months = $category_config['age_min'] * 12;
        $max_age_months = $category_config['age_max'] * 12;
        
        return $age_months >= $min_age_months && $age_months < $max_age_months;
    }

    /**
     * Trova categoria corretta per una data età
     */
    public function find_correct_category($age_data) {
        if (!$age_data) {
            return null;
        }

        // Ottieni categorie child dinamiche
        if (class_exists('BTR_Dynamic_Child_Categories')) {
            $dynamic_categories = new BTR_Dynamic_Child_Categories();
            $categories = $dynamic_categories->get_categories(true);
        } else {
            // Fallback a categorie predefinite
            $categories = [
                ['id' => 'f1', 'age_min' => 3, 'age_max' => 8, 'label' => 'Bambini 3-8 anni'],
                ['id' => 'f2', 'age_min' => 8, 'age_max' => 12, 'label' => 'Bambini 8-12 anni'],
                ['id' => 'f3', 'age_min' => 12, 'age_max' => 14, 'label' => 'Bambini 12-14 anni'],
                ['id' => 'f4', 'age_min' => 14, 'age_max' => 15, 'label' => 'Bambini 14-15 anni']
            ];
        }

        foreach ($categories as $category) {
            if ($this->check_age_in_category($age_data, $category)) {
                return $category;
            }
        }

        return null; // Nessuna categoria trovata
    }

    /**
     * Valida le età di tutti i bambini
     */
    public function validate_all_children($anagrafici, $departure_date) {
        $settings = $this->get_validation_settings();
        $results = [
            'valid' => true,
            'warnings' => [],
            'errors' => [],
            'suggestions' => [],
            'children_analysis' => []
        ];

        if (!$settings['enabled']) {
            return $results;
        }

        foreach ($anagrafici as $index => $person) {
            if (empty($person['data_nascita'])) {
                continue; // Salta se non c'è data di nascita
            }

            $age_data = $this->calculate_age_at_departure($person['data_nascita'], $departure_date);
            if (!$age_data) {
                $results['errors'][] = [
                    'type' => 'invalid_date',
                    'person_index' => $index,
                    'message' => "Data di nascita non valida per {$person['nome']} {$person['cognome']}"
                ];
                continue;
            }

            $correct_category = $this->find_correct_category($age_data);
            $person_analysis = [
                'index' => $index,
                'name' => trim(($person['nome'] ?? '') . ' ' . ($person['cognome'] ?? '')),
                'birth_date' => $person['data_nascita'],
                'age_at_departure' => $age_data,
                'correct_category' => $correct_category,
                'selected_category' => $person['selected_category'] ?? null,
                'is_valid' => false,
                'needs_correction' => false
            ];

            // Verifica se la categoria selezionata è corretta
            if ($person_analysis['selected_category']) {
                $selected_category_config = $this->get_category_config($person_analysis['selected_category']);
                if ($selected_category_config) {
                    $is_in_selected = $this->check_age_in_category($age_data, $selected_category_config);
                    $person_analysis['is_valid'] = $is_in_selected;
                    
                    if (!$is_in_selected) {
                        $person_analysis['needs_correction'] = true;
                        $message = "L'età di {$person_analysis['name']} ({$age_data['years']} anni) non corrisponde alla categoria selezionata";
                        
                        if ($correct_category) {
                            $message .= ". Dovrebbe essere in: {$correct_category['label']}";
                            $results['suggestions'][] = [
                                'person_index' => $index,
                                'suggested_category' => $correct_category,
                                'current_category' => $person_analysis['selected_category']
                            ];
                        }
                        
                        if ($settings['strict_validation']) {
                            $results['errors'][] = [
                                'type' => 'age_mismatch',
                                'person_index' => $index,
                                'message' => $message
                            ];
                            $results['valid'] = false;
                        } else {
                            $results['warnings'][] = [
                                'type' => 'age_mismatch',
                                'person_index' => $index,
                                'message' => $message
                            ];
                        }
                    }
                }
            }

            $results['children_analysis'][] = $person_analysis;
        }

        return $results;
    }

    /**
     * Ottiene configurazione categoria per ID
     */
    private function get_category_config($category_id) {
        if (class_exists('BTR_Dynamic_Child_Categories')) {
            $dynamic_categories = new BTR_Dynamic_Child_Categories();
            return $dynamic_categories->get_category($category_id);
        }
        
        // Fallback
        $default_categories = [
            'f1' => ['id' => 'f1', 'age_min' => 3, 'age_max' => 8, 'label' => 'Bambini 3-8 anni'],
            'f2' => ['id' => 'f2', 'age_min' => 8, 'age_max' => 12, 'label' => 'Bambini 8-12 anni'],
            'f3' => ['id' => 'f3', 'age_min' => 12, 'age_max' => 14, 'label' => 'Bambini 12-14 anni'],
            'f4' => ['id' => 'f4', 'age_min' => 14, 'age_max' => 15, 'label' => 'Bambini 14-15 anni']
        ];
        
        return $default_categories[$category_id] ?? null;
    }

    /**
     * AJAX: Valida età bambini
     */
    public function ajax_validate_child_ages() {
        check_ajax_referer('btr_age_validation', 'nonce');

        $anagrafici = json_decode(stripslashes($_POST['anagrafici'] ?? '[]'), true);
        $departure_date = sanitize_text_field($_POST['departure_date'] ?? '');

        if (empty($anagrafici) || empty($departure_date)) {
            wp_send_json_error('Dati mancanti per la validazione');
        }

        $validation_results = $this->validate_all_children($anagrafici, $departure_date);
        
        wp_send_json_success($validation_results);
    }

    /**
     * Hook per validazione prima della creazione preventivo
     */
    public function validate_before_preventivo($anagrafici, $booking_data) {
        $departure_date = $booking_data['departure_date'] ?? '';
        if (empty($departure_date)) {
            return; // Non possiamo validare senza data di partenza
        }

        $validation_results = $this->validate_all_children($anagrafici, $departure_date);
        
        if (!$validation_results['valid'] && $this->get_validation_settings()['strict_validation']) {
            wp_die(
                'Validazione età bambini fallita. Correggere le incongruenze prima di procedere.',
                'Errore Validazione Età',
                ['response' => 400]
            );
        }
    }

    /**
     * AJAX: Salva impostazioni validazione
     */
    public function ajax_save_settings() {
        check_ajax_referer('btr_age_validation_settings', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }

        $settings = [
            'enabled' => !empty($_POST['enabled']),
            'strict_validation' => !empty($_POST['strict_validation']),
            'show_warnings' => !empty($_POST['show_warnings']),
            'auto_suggest_corrections' => !empty($_POST['auto_suggest_corrections']),
            'allow_age_tolerance' => !empty($_POST['allow_age_tolerance']),
            'tolerance_months' => intval($_POST['tolerance_months'] ?? 3),
            'notification_type' => sanitize_text_field($_POST['notification_type'] ?? 'warning')
        ];

        update_option('btr_age_validation_settings', $settings);
        
        wp_send_json_success([
            'message' => 'Impostazioni salvate con successo',
            'settings' => $settings
        ]);
    }

    /**
     * Genera report di validazione in formato human-readable
     */
    public function generate_validation_report($validation_results) {
        $report = [
            'summary' => '',
            'details' => [],
            'recommendations' => []
        ];

        $total_issues = count($validation_results['errors']) + count($validation_results['warnings']);
        
        if ($total_issues === 0) {
            $report['summary'] = 'Tutte le età dei bambini sono valide per le categorie selezionate.';
            return $report;
        }

        $report['summary'] = "Trovate {$total_issues} incongruenze nell'età dei bambini.";
        
        foreach ($validation_results['errors'] as $error) {
            $report['details'][] = '❌ Errore: ' . $error['message'];
        }
        
        foreach ($validation_results['warnings'] as $warning) {
            $report['details'][] = '⚠️ Avviso: ' . $warning['message'];
        }
        
        foreach ($validation_results['suggestions'] as $suggestion) {
            $report['recommendations'][] = "Suggerimento: Sposta il bambino #{$suggestion['person_index']} nella categoria {$suggestion['suggested_category']['label']}";
        }

        return $report;
    }
}