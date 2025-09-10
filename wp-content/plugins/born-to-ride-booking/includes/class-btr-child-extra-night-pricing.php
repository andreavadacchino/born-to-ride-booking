<?php
/**
 * Sistema prezzi notte extra per categorie bambini
 * 
 * Gestisce prezzi personalizzati per notti extra dei bambini
 * invece del "metà prezzo" hardcoded
 * 
 * @since 1.0.15
 * @author Born To Ride Booking
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Child_Extra_Night_Pricing {
    
    /**
     * Configurazione default per prezzi notte extra bambini
     */
    const DEFAULT_PRICING = [
        'f1' => [
            'pricing_type' => 'percentage',
            'pricing_value' => 50, // 50% del prezzo adulto
            'enabled' => true
        ],
        'f2' => [
            'pricing_type' => 'percentage', 
            'pricing_value' => 60, // 60% del prezzo adulto
            'enabled' => true
        ],
        'f3' => [
            'pricing_type' => 'percentage',
            'pricing_value' => 70, // 70% del prezzo adulto
            'enabled' => true
        ],
        'f4' => [
            'pricing_type' => 'percentage',
            'pricing_value' => 80, // 80% del prezzo adulto
            'enabled' => true
        ]
    ];

    public function __construct() {
        // Hook per menu admin
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // AJAX endpoints
        add_action('wp_ajax_btr_save_extra_night_pricing', [$this, 'ajax_save_pricing']);
        add_action('wp_ajax_btr_reset_extra_night_pricing', [$this, 'ajax_reset_pricing']);
        
        // Hook per fornire configurazioni al frontend
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_config']);
        
        // Filtro per calcolo prezzi notte extra
        add_filter('btr_calculate_child_extra_night_price', [$this, 'calculate_child_extra_night_price'], 10, 3);
        
        // Inizializzazione default
        $this->maybe_initialize_defaults();
    }

    /**
     * Inizializza configurazioni default se non esistono
     */
    private function maybe_initialize_defaults() {
        $pricing = get_option('btr_child_extra_night_pricing', false);
        if ($pricing === false) {
            update_option('btr_child_extra_night_pricing', self::DEFAULT_PRICING);
        }
    }

    /**
     * Aggiunge menu admin
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=preventivo',
            'Prezzi Notte Extra Bambini',
            'Notte Extra Bambini',
            'manage_options',
            'btr-child-extra-night-pricing',
            [$this, 'admin_page']
        );
    }

    /**
     * Pagina admin
     */
    public function admin_page() {
        include BTR_PLUGIN_DIR . 'admin/views/child-extra-night-pricing-admin.php';
    }

    /**
     * Ottiene configurazioni pricing
     */
    public function get_pricing_config() {
        return get_option('btr_child_extra_night_pricing', self::DEFAULT_PRICING);
    }

    /**
     * Ottiene configurazione per categoria specifica
     */
    public function get_category_pricing($category_id) {
        $pricing = $this->get_pricing_config();
        return $pricing[$category_id] ?? null;
    }

    /**
     * Calcola prezzo notte extra per bambino
     */
    public function calculate_child_extra_night_price($category_id, $adult_extra_night_price, $context = '') {
        $config = $this->get_category_pricing($category_id);
        
        if (!$config || !$config['enabled']) {
            return $adult_extra_night_price * 0.5; // Fallback al 50% se non configurato
        }

        switch ($config['pricing_type']) {
            case 'percentage':
                return ($adult_extra_night_price * $config['pricing_value']) / 100;
                
            case 'fixed_discount':
                return max(0, $adult_extra_night_price - $config['pricing_value']);
                
            case 'fixed_price':
                return $config['pricing_value'];
                
            case 'free':
                return 0;
                
            default:
                return $adult_extra_night_price * 0.5; // Fallback
        }
    }

    /**
     * Genera configurazione JavaScript per frontend
     */
    public function get_frontend_config() {
        $pricing = $this->get_pricing_config();
        
        return [
            'pricing' => $pricing,
            'calculate_price_function' => 'calculateChildExtraNightPrice'
        ];
    }

    /**
     * Aggiunge configurazioni al frontend
     */
    public function enqueue_frontend_config() {
        if (is_singular('pacchetti') || (function_exists('is_shop') && is_shop())) {
            wp_localize_script('jquery', 'btrChildExtraNightPricing', $this->get_frontend_config());
            
            // Aggiunge JavaScript inline per la funzione di calcolo
            $js = $this->generate_frontend_js();
            wp_add_inline_script('jquery', $js);
        }
    }

    /**
     * Genera JavaScript per calcolo prezzi frontend
     */
    private function generate_frontend_js() {
        $config = $this->get_frontend_config();
        
        return "
        // Sistema prezzi notte extra bambini
        window.btrChildExtraNightPricing = " . json_encode($config) . ";
        
        function calculateChildExtraNightPrice(categoryId, adultExtraNightPrice) {
            if (!window.btrChildExtraNightPricing || !window.btrChildExtraNightPricing.pricing[categoryId]) {
                return adultExtraNightPrice * 0.5; // Fallback 50%
            }
            
            const config = window.btrChildExtraNightPricing.pricing[categoryId];
            
            if (!config.enabled) {
                return adultExtraNightPrice * 0.5;
            }
            
            switch (config.pricing_type) {
                case 'percentage':
                    return (adultExtraNightPrice * config.pricing_value) / 100;
                case 'fixed_discount':
                    return Math.max(0, adultExtraNightPrice - config.pricing_value);
                case 'fixed_price':
                    return config.pricing_value;
                case 'free':
                    return 0;
                default:
                    return adultExtraNightPrice * 0.5;
            }
        }
        
        // Funzione helper per ottenere il display del prezzo
        function getChildExtraNightPriceDisplay(categoryId) {
            if (!window.btrChildExtraNightPricing || !window.btrChildExtraNightPricing.pricing[categoryId]) {
                return '50%';
            }
            
            const config = window.btrChildExtraNightPricing.pricing[categoryId];
            
            switch (config.pricing_type) {
                case 'percentage':
                    return config.pricing_value + '%';
                case 'fixed_discount':
                    return '-€' + config.pricing_value;
                case 'fixed_price':
                    return '€' + config.pricing_value;
                case 'free':
                    return 'Gratuito';
                default:
                    return '50%';
            }
        }
        ";
    }

    /**
     * Valida configurazione pricing
     */
    private function validate_pricing_config($config) {
        $errors = [];

        foreach ($config as $category_id => $pricing) {
            if (!is_array($pricing)) {
                $errors[] = "Configurazione non valida per categoria {$category_id}";
                continue;
            }

            // Valida pricing_type
            $valid_types = ['percentage', 'fixed_discount', 'fixed_price', 'free'];
            if (!in_array($pricing['pricing_type'], $valid_types)) {
                $errors[] = "Tipo pricing non valido per categoria {$category_id}";
            }

            // Valida pricing_value
            $pricing_value = floatval($pricing['pricing_value'] ?? 0);
            if ($pricing_value < 0) {
                $errors[] = "Valore pricing non può essere negativo per categoria {$category_id}";
            }

            if ($pricing['pricing_type'] === 'percentage' && $pricing_value > 100) {
                $errors[] = "Percentuale non può superare 100% per categoria {$category_id}";
            }
        }

        return $errors;
    }

    /**
     * AJAX: Salva configurazioni pricing
     */
    public function ajax_save_pricing() {
        check_ajax_referer('btr_child_extra_night_pricing', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }

        $pricing_config = json_decode(stripslashes($_POST['pricing_config'] ?? '{}'), true);
        if (!is_array($pricing_config)) {
            wp_send_json_error('Dati non validi');
        }

        $errors = $this->validate_pricing_config($pricing_config);
        if (!empty($errors)) {
            wp_send_json_error([
                'message' => 'Errori di validazione',
                'errors' => $errors
            ]);
        }

        // Sanitizza i dati
        $sanitized_config = [];
        foreach ($pricing_config as $category_id => $pricing) {
            $sanitized_config[sanitize_key($category_id)] = [
                'pricing_type' => sanitize_text_field($pricing['pricing_type']),
                'pricing_value' => floatval($pricing['pricing_value']),
                'enabled' => !empty($pricing['enabled'])
            ];
        }

        update_option('btr_child_extra_night_pricing', $sanitized_config);
        
        wp_send_json_success([
            'message' => 'Configurazioni salvate con successo',
            'pricing_config' => $sanitized_config
        ]);
    }

    /**
     * AJAX: Ripristina configurazioni default
     */
    public function ajax_reset_pricing() {
        check_ajax_referer('btr_child_extra_night_pricing', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }

        update_option('btr_child_extra_night_pricing', self::DEFAULT_PRICING);
        
        wp_send_json_success([
            'message' => 'Configurazioni ripristinate ai valori default',
            'pricing_config' => self::DEFAULT_PRICING
        ]);
    }

    /**
     * Ottiene riepilogo configurazioni per display
     */
    public function get_pricing_summary() {
        $pricing = $this->get_pricing_config();
        $summary = [];

        foreach ($pricing as $category_id => $config) {
            $display = $config['enabled'] ? $this->get_pricing_display($config) : 'Disabilitato';
            $summary[$category_id] = $display;
        }

        return $summary;
    }

    /**
     * Ottiene display human-readable della configurazione
     */
    private function get_pricing_display($config) {
        switch ($config['pricing_type']) {
            case 'percentage':
                return $config['pricing_value'] . '% del prezzo adulto';
            case 'fixed_discount':
                return 'Prezzo adulto - €' . number_format($config['pricing_value'], 2);
            case 'fixed_price':
                return '€' . number_format($config['pricing_value'], 2) . ' fisso';
            case 'free':
                return 'Gratuito';
            default:
                return 'Non configurato';
        }
    }
}