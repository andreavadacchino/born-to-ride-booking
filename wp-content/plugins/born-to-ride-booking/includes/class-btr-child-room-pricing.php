<?php
/**
 * Sistema prezzi per bambini nelle tipologie di camere
 * 
 * Gestisce prezzi personalizzati per bambini nelle diverse tipologie di camere
 * 
 * @since 1.0.16
 * @author Born To Ride Booking
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Child_Room_Pricing {
    
    /**
     * Configurazione default per prezzi bambini nelle camere
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
        // Hook per salvataggio dei prezzi bambini nelle camere
        add_action('save_post_btr_pacchetti', [$this, 'save_child_room_pricing'], 10, 2);
        
        // Hook per fornire configurazioni al frontend
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_config']);
        
        // Filtro per calcolo prezzi bambini nelle camere
        add_filter('btr_calculate_child_room_price', [$this, 'calculate_child_room_price'], 10, 4);
        
        // Hook per aggiungere campi al metabox
        add_action('btr_after_room_pricing_fields', [$this, 'add_child_pricing_fields']);
    }

    /**
     * Salva i prezzi per bambini nelle camere
     */
    public function save_child_room_pricing($post_id, $post) {
        // Verifica nonce
        if (!isset($_POST['btr_nonce']) || !wp_verify_nonce($_POST['btr_nonce'], 'save_btr_pacchetti_meta')) {
            return;
        }

        // Verifica permessi
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Array delle tipologie di camere
        $room_types = ['singole', 'doppie', 'triple', 'quadruple', 'quintuple'];
        
        // Array delle fasce di età bambini
        $child_categories = ['f1', 'f2', 'f3', 'f4'];

        foreach ($room_types as $room_type) {
            foreach ($child_categories as $category) {
                $field_name = "btr_child_pricing_{$room_type}_{$category}";
                $enabled_field = "btr_child_pricing_{$room_types}_{$category}_enabled";
                
                // Salva se abilitato
                if (isset($_POST[$enabled_field])) {
                    update_post_meta($post_id, $enabled_field, '1');
                } else {
                    update_post_meta($post_id, $enabled_field, '0');
                }
                
                // Salva il prezzo
                if (isset($_POST[$field_name])) {
                    $price = floatval($_POST[$field_name]);
                    update_post_meta($post_id, $field_name, $price);
                }
            }
        }

        // Salva i prezzi per bambini nell'allotment se presente
        if (isset($_POST['btr_camere_allotment']) && is_array($_POST['btr_camere_allotment'])) {
            $this->save_allotment_child_pricing($post_id, $_POST['btr_camere_allotment']);
        }
        
        // Salva i prezzi per bambini nelle notti extra se presente
        if (isset($_POST['btr_camere_extra_allotment_by_date']) && is_array($_POST['btr_camere_extra_allotment_by_date'])) {
            $this->save_extra_allotment_child_pricing($post_id, $_POST['btr_camere_extra_allotment_by_date']);
        }
    }

    /**
     * Aggiunge i campi per i prezzi bambini dopo i campi di prezzo delle camere
     */
    public function add_child_pricing_fields($room_type, $post_id) {
        // Ottieni le fasce di età bambini
        $child_categories = $this->get_child_categories();
        
        if (empty($child_categories)) {
            return;
        }

        echo '<div class="btr-child-pricing-section">';
        echo '<h5>Prezzi per Bambini</h5>';
        
        foreach ($child_categories as $category) {
            $field_name = "btr_child_pricing_{$room_type}_{$category['id']}";
            $enabled_field = "btr_child_pricing_{$room_type}_{$category['id']}_enabled";
            
            $enabled = get_post_meta($post_id, $enabled_field, true);
            $price = get_post_meta($post_id, $field_name, true);
            
            echo '<div class="btr-child-pricing-row">';
            echo '<div class="btr-child-pricing-label">';
            echo '<label for="' . esc_attr($enabled_field) . '">' . esc_html($category['label']) . '</label>';
            echo '</div>';
            
            echo '<div class="btr-child-pricing-enabled">';
            echo '<input type="checkbox" id="' . esc_attr($enabled_field) . '" name="' . esc_attr($enabled_field) . '" value="1" ' . checked($enabled, '1', false) . '/>';
            echo '</div>';
            
            echo '<div class="btr-child-pricing-price">';
            echo '<input type="number" name="' . esc_attr($field_name) . '" value="' . esc_attr($price) . '" step="0.01" min="0" placeholder="Prezzo" />';
            echo '<small>€ per bambino</small>';
            echo '</div>';
            
            echo '</div>';
        }
        
        echo '</div>';
    }

    /**
     * Ottiene le fasce di età bambini
     */
    public function get_child_categories() {
        // Prova a ottenere da sistema dinamico
        if (class_exists('BTR_Dynamic_Child_Categories')) {
            $dynamic_categories = new BTR_Dynamic_Child_Categories();
            return $dynamic_categories->get_categories(true);
        }
        
        // Fallback a categorie predefinite
        return [
            ['id' => 'f1', 'label' => 'Bambini 3-8 anni', 'age_min' => 3, 'age_max' => 8],
            ['id' => 'f2', 'label' => 'Bambini 8-12 anni', 'age_min' => 8, 'age_max' => 12],
            ['id' => 'f3', 'label' => 'Bambini 12-14 anni', 'age_min' => 12, 'age_max' => 14],
            ['id' => 'f4', 'label' => 'Bambini 14-15 anni', 'age_min' => 14, 'age_max' => 15]
        ];
    }

    /**
     * Calcola prezzo per bambino in una specifica tipologia di camera
     */
    public function calculate_child_room_price($room_type, $category_id, $adult_price, $post_id) {
        $enabled_field = "btr_child_pricing_{$room_type}_{$category_id}_enabled";
        $price_field = "btr_child_pricing_{$room_type}_{$category_id}";
        
        $enabled = get_post_meta($post_id, $enabled_field, true);
        $child_price = get_post_meta($post_id, $price_field, true);
        
        if ($enabled !== '1' || empty($child_price)) {
            // Fallback al 50% del prezzo adulto
            return $adult_price * 0.5;
        }
        
        return floatval($child_price);
    }

    /**
     * Ottiene tutti i prezzi per bambini di un pacchetto
     */
    public function get_package_child_pricing($post_id) {
        $room_types = ['singole', 'doppie', 'triple', 'quadruple', 'quintuple'];
        $child_categories = $this->get_child_categories();
        $pricing = [];
        
        foreach ($room_types as $room_type) {
            $pricing[$room_type] = [];
            foreach ($child_categories as $category) {
                $enabled_field = "btr_child_pricing_{$room_type}_{$category['id']}_enabled";
                $price_field = "btr_child_pricing_{$room_type}_{$category['id']}";
                
                $enabled = get_post_meta($post_id, $enabled_field, true);
                $price = get_post_meta($post_id, $price_field, true);
                
                $pricing[$room_type][$category['id']] = [
                    'enabled' => $enabled === '1',
                    'price' => floatval($price),
                    'label' => $category['label']
                ];
            }
        }
        
        return $pricing;
    }

    /**
     * Genera configurazione JavaScript per frontend
     */
    public function get_frontend_config($post_id) {
        return [
            'package_id' => $post_id,
            'pricing' => $this->get_package_child_pricing($post_id),
            'categories' => $this->get_child_categories()
        ];
    }

    /**
     * Aggiunge configurazioni al frontend
     */
    public function enqueue_frontend_config() {
        if (is_singular('btr_pacchetti') || (function_exists('is_shop') && is_shop())) {
            global $post;
            if ($post && $post->post_type === 'btr_pacchetti') {
                wp_localize_script('jquery', 'btrChildRoomPricing', $this->get_frontend_config($post->ID));
                
                // Aggiunge JavaScript inline per la funzione di calcolo
                $js = $this->generate_frontend_js();
                wp_add_inline_script('jquery', $js);
            }
        }
    }

    /**
     * Genera JavaScript per calcolo prezzi frontend
     */
    private function generate_frontend_js() {
        return "
        // Sistema prezzi bambini nelle camere
        function calculateChildRoomPrice(roomType, categoryId, adultPrice) {
            if (!window.btrChildRoomPricing || !window.btrChildRoomPricing.pricing[roomType]) {
                return adultPrice * 0.5; // Fallback 50%
            }
            
            const roomPricing = window.btrChildRoomPricing.pricing[roomType];
            if (!roomPricing[categoryId] || !roomPricing[categoryId].enabled) {
                return adultPrice * 0.5; // Fallback 50%
            }
            
            return roomPricing[categoryId].price;
        }
        
        // Funzione helper per ottenere il display del prezzo
        function getChildRoomPriceDisplay(roomType, categoryId) {
            if (!window.btrChildRoomPricing || !window.btrChildRoomPricing.pricing[roomType]) {
                return '50%';
            }
            
            const roomPricing = window.btrChildRoomPricing.pricing[roomType];
            if (!roomPricing[categoryId] || !roomPricing[categoryId].enabled) {
                return '50%';
            }
            
            return '€' + roomPricing[categoryId].price;
        }
        ";
    }

    /**
     * Salva i prezzi per bambini per l'allotment
     * 
     * @param int $post_id ID del post
     * @param array $allotment_data Dati dell'allotment
     */
    public function save_allotment_child_pricing($post_id, $allotment_data) {
        if (!is_array($allotment_data)) {
            return;
        }

        // Salva i dati dell'allotment con i prezzi per bambini
        update_post_meta($post_id, 'btr_camere_allotment', $allotment_data);
    }

    /**
     * Salva i prezzi per bambini per le notti extra dell'allotment
     * 
     * @param int $post_id ID del post
     * @param array $extra_allotment_data Dati delle notti extra
     */
    public function save_extra_allotment_child_pricing($post_id, $extra_allotment_data) {
        if (!is_array($extra_allotment_data)) {
            return;
        }

        // Salva i dati delle notti extra con i prezzi per bambini
        update_post_meta($post_id, 'btr_camere_extra_allotment_by_date', $extra_allotment_data);
    }

    /**
     * Ottiene i prezzi per bambini dall'allotment
     * 
     * @param int $post_id ID del post
     * @param string $date_key Chiave della data
     * @param string $room_type Tipologia di camera
     * @return array Dati dei prezzi per bambini
     */
    public function get_allotment_child_pricing($post_id, $date_key, $room_type) {
        $allotment_data = get_post_meta($post_id, 'btr_camere_allotment', true);
        
        if (!is_array($allotment_data) || !isset($allotment_data[$date_key][$room_type]['child_pricing'])) {
            return [];
        }
        
        return $allotment_data[$date_key][$room_type]['child_pricing'];
    }

    /**
     * Ottiene i prezzi per bambini dalle notti extra dell'allotment
     * 
     * @param int $post_id ID del post
     * @param string $date_key Chiave della data
     * @return array Dati dei prezzi per bambini
     */
    public function get_extra_allotment_child_pricing($post_id, $date_key) {
        $extra_allotment_data = get_post_meta($post_id, 'btr_camere_extra_allotment_by_date', true);
        
        if (!is_array($extra_allotment_data) || !isset($extra_allotment_data[$date_key]['child_pricing'])) {
            return [];
        }
        
        return $extra_allotment_data[$date_key]['child_pricing'];
    }

    /**
     * Calcola prezzo per bambino nell'allotment
     * 
     * @param int $post_id ID del post
     * @param string $date_key Chiave della data
     * @param string $room_type Tipologia di camera
     * @param string $category_id ID della categoria bambino
     * @param float $adult_price Prezzo adulto
     * @return float Prezzo per bambino
     */
    public function calculate_allotment_child_price($post_id, $date_key, $room_type, $category_id, $adult_price) {
        $child_pricing = $this->get_allotment_child_pricing($post_id, $date_key, $room_type);
        
        $enabled_key = $category_id . '_enabled';
        if (!isset($child_pricing[$enabled_key]) || $child_pricing[$enabled_key] !== '1') {
            return $adult_price * 0.5; // Fallback 50%
        }
        
        if (!isset($child_pricing[$category_id]) || empty($child_pricing[$category_id])) {
            return $adult_price * 0.5; // Fallback 50%
        }
        
        return floatval($child_pricing[$category_id]);
    }

    /**
     * Calcola prezzo per bambino nelle notti extra dell'allotment
     * 
     * @param int $post_id ID del post
     * @param string $date_key Chiave della data
     * @param string $category_id ID della categoria bambino
     * @param float $adult_price Prezzo adulto
     * @return float Prezzo per bambino
     */
    public function calculate_extra_allotment_child_price($post_id, $date_key, $category_id, $adult_price) {
        $child_pricing = $this->get_extra_allotment_child_pricing($post_id, $date_key);
        
        // Se c'è un prezzo specifico per questa data, usalo
        if (isset($child_pricing[$category_id]) && !empty($child_pricing[$category_id])) {
            return floatval($child_pricing[$category_id]);
        }
        
        // Altrimenti usa il prezzo globale o il fallback
        return $this->get_global_child_price($post_id, $category_id, $adult_price);
    }

    /**
     * Ottiene il prezzo globale per bambino
     * 
     * @param int $post_id ID del post
     * @param string $category_id ID della categoria bambino
     * @param float $adult_price Prezzo adulto
     * @return float Prezzo per bambino
     */
    public function get_global_child_price($post_id, $category_id, $adult_price) {
        $global_enabled_field = "btr_global_child_pricing_{$category_id}_enabled";
        $global_price_field = "btr_global_child_pricing_{$category_id}";
        
        $global_enabled = get_post_meta($post_id, $global_enabled_field, true);
        if ($global_enabled !== '1') {
            return $adult_price * 0.5; // Fallback 50%
        }
        
        $global_price = get_post_meta($post_id, $global_price_field, true);
        if (empty($global_price)) {
            return $adult_price * 0.5; // Fallback 50%
        }
        
        return floatval($global_price);
    }

    /**
     * Ottiene tutti i prezzi globali per bambini
     * 
     * @param int $post_id ID del post
     * @return array Array dei prezzi globali
     */
    public function get_global_child_pricing($post_id) {
        $child_categories = $this->get_child_categories();
        $pricing = [];
        
        foreach ($child_categories as $category) {
            $global_enabled_field = "btr_global_child_pricing_{$category['id']}_enabled";
            $global_price_field = "btr_global_child_pricing_{$category['id']}";
            
            $enabled = get_post_meta($post_id, $global_enabled_field, true);
            $price = get_post_meta($post_id, $global_price_field, true);
            
            $pricing[$category['id']] = [
                'enabled' => $enabled === '1',
                'price' => floatval($price),
                'label' => $category['label']
            ];
        }
        
        return $pricing;
    }
} 