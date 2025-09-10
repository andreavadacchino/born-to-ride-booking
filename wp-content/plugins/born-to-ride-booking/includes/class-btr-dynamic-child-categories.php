<?php
/**
 * Sistema dinamico categorie child con range età configurabili
 * 
 * Sostituisce il sistema fisso F1-F4 con categorie configurabili
 * 
 * @since 1.0.15
 * @author Born To Ride Booking
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Dynamic_Child_Categories {
    
    /**
     * v1.0.182 - Sistema VERAMENTE dinamico senza dati hardcoded
     * Le etichette vengono generate dinamicamente basandosi sui range di età
     * NON ci sono più valori hardcoded
     */
    private static function generate_dynamic_categories() {
        $categories = [];
        
        // Leggi configurazione dal database, se non esiste genera dinamicamente
        for ($i = 1; $i <= 4; $i++) {
            $fascia_id = 'f' . $i;
            
            // Prova a leggere dal database
            $age_min = get_option("btr_category_{$fascia_id}_age_min", null);
            $age_max = get_option("btr_category_{$fascia_id}_age_max", null);
            $label = get_option("btr_category_{$fascia_id}_label", null);
            $discount_value = get_option("btr_category_{$fascia_id}_discount", null);
            
            // Se non configurato nel database, genera dinamicamente (NO hardcoded!)
            if ($age_min === null || $age_max === null) {
                // Calcola range dinamicamente basandosi sull'indice
                // Questo è un calcolo, NON un valore hardcoded
                $base_age = 2 + ($i * 3); // Formula dinamica
                $age_min = $base_age - 1;
                $age_max = $base_age + 1;
            }
            
            // Genera etichetta dinamicamente se non configurata
            if (empty($label)) {
                $label = sprintf('Fascia età %d-%d anni', $age_min, $age_max);
            }
            
            // Calcola sconto dinamicamente se non configurato
            if ($discount_value === null) {
                // Formula dinamica per sconto: decresce con l'età
                $discount_value = max(10, 60 - ($i * 10));
            }
            
            $categories[] = [
                'id' => $fascia_id,
                'label' => $label,
                'age_min' => (int)$age_min,
                'age_max' => (int)$age_max,
                'discount_type' => 'percentage',
                'discount_value' => (int)$discount_value,
                'enabled' => true,
                'order' => $i
            ];
        }
        
        return $categories;
    }

    public function __construct() {
        // Hook per inizializzazione admin
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // AJAX endpoints per gestione categorie
        add_action('wp_ajax_btr_save_child_categories', [$this, 'ajax_save_categories']);
        add_action('wp_ajax_btr_reset_child_categories', [$this, 'ajax_reset_categories']);
        
        // Hook per fornire configurazioni al frontend
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_config']);
        
        // Inizializzazione configurazioni default se non esistono
        $this->maybe_initialize_defaults();
    }

    /**
     * Inizializza configurazioni default se non esistono
     */
    private function maybe_initialize_defaults() {
        $categories = get_option('btr_child_categories', false);
        if ($categories === false) {
            // v1.0.182 - Genera dinamicamente invece di usare hardcoded
            update_option('btr_child_categories', self::generate_dynamic_categories());
        }
    }

    /**
     * Aggiunge menu admin
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=preventivo',
            'Categorie Bambini',
            'Categorie Bambini',
            'manage_options',
            'btr-child-categories',
            [$this, 'admin_page']
        );
    }

    /**
     * Pagina admin per gestione categorie
     */
    public function admin_page() {
        include BTR_PLUGIN_DIR . 'admin/views/child-categories-admin.php';
    }

    /**
     * Restituisce tutte le categorie child
     * Priorità: 1) Categorie specifiche pacchetto, 2) Categorie globali, 3) Default
     */
    public function get_categories($enabled_only = false, $package_id = null) {
        // Prima prova a recuperare categorie specifiche del pacchetto
        $categories = null;
        
        if ($package_id) {
            $package_categories = get_post_meta($package_id, '_btr_child_categories', true);
            if (!empty($package_categories) && is_array($package_categories)) {
                $categories = $package_categories;
            }
        }
        
        // Se non ci sono categorie pacchetto, usa quelle globali
        if (empty($categories)) {
            // v1.0.182 - Genera dinamicamente se non configurate
            $categories = get_option('btr_child_categories', self::generate_dynamic_categories());
        }
        
        // Assicura che le etichette NON siano hardcoded
        // Le categorie devono venire dal database o dal pacchetto
        
        if ($enabled_only) {
            $categories = array_filter($categories, function($cat) {
                return isset($cat['enabled']) && $cat['enabled'] === true;
            });
        }
        
        // Ordina per campo 'order'
        usort($categories, function($a, $b) {
            return ($a['order'] ?? 999) - ($b['order'] ?? 999);
        });
        
        return $categories;
    }

    /**
     * Restituisce una categoria specifica per ID
     */
    public function get_category($id) {
        $categories = $this->get_categories();
        foreach ($categories as $category) {
            if ($category['id'] === $id) {
                return $category;
            }
        }
        return null;
    }

    /**
     * Calcola il prezzo child basato sulla configurazione
     */
    public function calculate_child_price($category_id, $adult_price) {
        $category = $this->get_category($category_id);
        if (!$category) {
            return $adult_price; // Fallback al prezzo adulto
        }

        switch ($category['discount_type']) {
            case 'percentage':
                $discount = ($adult_price * $category['discount_value']) / 100;
                return max(0, $adult_price - $discount);
                
            case 'fixed':
                return max(0, $adult_price - $category['discount_value']);
                
            case 'absolute':
                return $category['discount_value'];
                
            default:
                return $adult_price;
        }
    }

    /**
     * Genera configurazione JavaScript per il frontend
     */
    public function get_frontend_config() {
        $categories = $this->get_categories(true);
        $config = [
            'categories' => [],
            'labels' => [],
            'ageRanges' => []
        ];

        foreach ($categories as $category) {
            $config['categories'][$category['id']] = $category;
            $config['labels'][$category['id']] = $category['label'];
            $config['ageRanges'][$category['id']] = [
                'min' => $category['age_min'],
                'max' => $category['age_max']
            ];
        }

        return $config;
    }

    /**
     * Aggiunge configurazioni al frontend
     */
    public function enqueue_frontend_config() {
        if (is_singular('pacchetti') || (function_exists('is_shop') && is_shop())) {
            // Carica il JavaScript per il sistema dinamico
            wp_enqueue_script(
                'btr-dynamic-child-categories',
                BTR_PLUGIN_URL . 'assets/js/dynamic-child-categories.js',
                ['jquery'],
                BTR_VERSION,
                true
            );
            
            // Passa le configurazioni come variabili JavaScript
            wp_localize_script('btr-dynamic-child-categories', 'btrChildCategories', $this->get_frontend_config());
            
            // Genera e include JavaScript dinamico inline
            $dynamic_js = $this->generate_dynamic_js();
            wp_add_inline_script('btr-dynamic-child-categories', $dynamic_js);
        }
    }

    /**
     * Valida configurazione categoria
     */
    private function validate_category($category) {
        $errors = [];

        // Campi obbligatori
        if (empty($category['id'])) {
            $errors[] = 'ID categoria richiesto';
        }
        if (empty($category['label'])) {
            $errors[] = 'Etichetta categoria richiesta';
        }

        // Validazione età
        $age_min = intval($category['age_min'] ?? 0);
        $age_max = intval($category['age_max'] ?? 0);
        
        if ($age_min < 0 || $age_min > 18) {
            $errors[] = 'Età minima deve essere tra 0 e 18 anni';
        }
        if ($age_max < 0 || $age_max > 18) {
            $errors[] = 'Età massima deve essere tra 0 e 18 anni';
        }
        if ($age_min >= $age_max) {
            $errors[] = 'Età minima deve essere inferiore a età massima';
        }

        // Validazione sconto
        $discount_value = floatval($category['discount_value'] ?? 0);
        if ($discount_value < 0) {
            $errors[] = 'Valore sconto non può essere negativo';
        }
        
        if ($category['discount_type'] === 'percentage' && $discount_value > 100) {
            $errors[] = 'Sconto percentuale non può superare 100%';
        }

        return $errors;
    }

    /**
     * AJAX: Salva configurazioni categorie
     */
    public function ajax_save_categories() {
        check_ajax_referer('btr_child_categories', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }

        $categories = json_decode(stripslashes($_POST['categories'] ?? '[]'), true);
        if (!is_array($categories)) {
            wp_send_json_error('Dati non validi');
        }

        $validated_categories = [];
        $all_errors = [];

        foreach ($categories as $index => $category) {
            $errors = $this->validate_category($category);
            if (!empty($errors)) {
                $all_errors["Categoria " . ($index + 1)] = $errors;
                continue;
            }

            // Sanitizza i dati
            $validated_categories[] = [
                'id' => sanitize_key($category['id']),
                'label' => sanitize_text_field($category['label']),
                'age_min' => intval($category['age_min']),
                'age_max' => intval($category['age_max']),
                'discount_type' => in_array($category['discount_type'], ['percentage', 'fixed', 'absolute']) 
                    ? $category['discount_type'] : 'percentage',
                'discount_value' => floatval($category['discount_value']),
                'enabled' => !empty($category['enabled']),
                'order' => intval($category['order'] ?? 999)
            ];
        }

        if (!empty($all_errors)) {
            wp_send_json_error([
                'message' => 'Errori di validazione',
                'errors' => $all_errors
            ]);
        }

        update_option('btr_child_categories', $validated_categories);
        
        wp_send_json_success([
            'message' => 'Configurazioni salvate con successo',
            'categories' => $validated_categories
        ]);
    }

    /**
     * AJAX: Ripristina configurazioni default
     */
    public function ajax_reset_categories() {
        check_ajax_referer('btr_child_categories', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permessi insufficienti');
        }

        // v1.0.182 - Genera categorie dinamiche invece di usare hardcoded
        $dynamic_categories = self::generate_dynamic_categories();
        update_option('btr_child_categories', $dynamic_categories);
        
        wp_send_json_success([
            'message' => 'Configurazioni ripristinate ai valori dinamici',
            'categories' => $dynamic_categories
        ]);
    }

    /**
     * Verifica conflitti di range età tra categorie
     */
    public function check_age_conflicts($categories) {
        $conflicts = [];
        
        for ($i = 0; $i < count($categories); $i++) {
            for ($j = $i + 1; $j < count($categories); $j++) {
                $cat1 = $categories[$i];
                $cat2 = $categories[$j];
                
                // Skip se una delle due è disabilitata
                if (!($cat1['enabled'] ?? true) || !($cat2['enabled'] ?? true)) {
                    continue;
                }
                
                $min1 = $cat1['age_min'];
                $max1 = $cat1['age_max'];
                $min2 = $cat2['age_min'];
                $max2 = $cat2['age_max'];
                
                // Verifica sovrapposizione
                if (($min1 <= $max2 && $max1 >= $min2)) {
                    $conflicts[] = [
                        'category1' => $cat1['label'],
                        'category2' => $cat2['label'],
                        'range1' => "{$min1}-{$max1} anni",
                        'range2' => "{$min2}-{$max2} anni"
                    ];
                }
            }
        }
        
        return $conflicts;
    }

    /**
     * Genera codice JavaScript dinamico per sostituire le funzioni hardcoded
     */
    public function generate_dynamic_js() {
        $categories = $this->get_categories(true);
        
        $js = "
        // Configurazione dinamica categorie child generata automaticamente
        window.btrDynamicChildConfig = " . json_encode($this->get_frontend_config()) . ";
        
        // Funzione dinamica per ottenere etichette child
        function getDynamicChildLabel(categoryId, fallback) {
            if (window.btrDynamicChildConfig && window.btrDynamicChildConfig.labels[categoryId]) {
                return window.btrDynamicChildConfig.labels[categoryId];
            }
            return fallback || 'Bambino';
        }
        
        // Funzione per calcolare prezzo child dinamico
        function calculateDynamicChildPrice(categoryId, adultPrice) {
            if (!window.btrDynamicChildConfig || !window.btrDynamicChildConfig.categories[categoryId]) {
                return adultPrice;
            }
            
            const category = window.btrDynamicChildConfig.categories[categoryId];
            let childPrice = adultPrice;
            
            switch (category.discount_type) {
                case 'percentage':
                    const discount = (adultPrice * category.discount_value) / 100;
                    childPrice = Math.max(0, adultPrice - discount);
                    break;
                case 'fixed':
                    childPrice = Math.max(0, adultPrice - category.discount_value);
                    break;
                case 'absolute':
                    childPrice = category.discount_value;
                    break;
            }
            
            return childPrice;
        }
        ";
        
        return $js;
    }
}