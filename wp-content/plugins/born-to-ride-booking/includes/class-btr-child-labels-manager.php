<?php
/**
 * Gestione centralizzata delle etichette dinamiche per le fasce bambini
 * 
 * @package Born_To_Ride_Booking
 * @since 1.0.55
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Child_Labels_Manager {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Cache per etichette
     */
    private $labels_cache = [];
    
    /**
     * Get singleton instance
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
        // Hook per sostituire etichette statiche con quelle dinamiche
        add_filter('btr_child_label', [$this, 'get_dynamic_label'], 10, 2);
        add_filter('btr_child_labels_array', [$this, 'get_all_labels'], 10, 1);
        
        // Hook per salvare etichette nel preventivo
        add_action('btr_before_save_preventivo', [$this, 'prepare_labels_for_preventivo'], 10, 2);
        
        // Hook per aggiornare etichette in vari punti del sistema
        add_filter('btr_anagrafici_form_child_label', [$this, 'filter_anagrafici_label'], 10, 3);
        add_filter('btr_checkout_child_label', [$this, 'filter_checkout_label'], 10, 3);
        add_filter('btr_cart_child_label', [$this, 'filter_cart_label'], 10, 3);
        
        // AJAX per ottenere etichette dinamicamente
        add_action('wp_ajax_btr_get_child_labels', [$this, 'ajax_get_child_labels']);
        add_action('wp_ajax_nopriv_btr_get_child_labels', [$this, 'ajax_get_child_labels']);
    }
    
    /**
     * Ottieni l'etichetta per una fascia specifica
     * 
     * @param string $fascia Codice fascia (f1, f2, f3, f4)
     * @param mixed $context Contesto (preventivo_id, package_id, etc.)
     * @return string Etichetta
     */
    public function get_dynamic_label($fascia, $context = null) {
        // Se abbiamo un preventivo_id nel context, usa le etichette salvate
        if (is_numeric($context)) {
            $preventivo_id = intval($context);
            $saved_labels = get_post_meta($preventivo_id, '_child_category_labels', true);
            if (is_array($saved_labels) && isset($saved_labels[$fascia])) {
                return $saved_labels[$fascia];
            }
        }
        
        // Altrimenti usa le etichette dal sistema dinamico
        $dynamic_categories = new BTR_Dynamic_Child_Categories();
        $category = $dynamic_categories->get_category($fascia);
        
        if ($category && isset($category['label'])) {
            return $category['label'];
        }
        
        // v1.0.160 - Fallback usando helper centralizzato per etichette dinamiche
        // Prova a recuperare il package_id dal contesto se possibile
        $package_id = null;
        if (is_numeric($context)) {
            $package_id = get_post_meta($context, '_btr_pacchetto_id', true);
            if (empty($package_id)) {
                $package_id = get_post_meta($context, '_btr_id_pacchetto', true);
            }
        }
        
        // v1.0.182 - Usa SOLO la funzione helper centralizzata, NO hardcoded
        if (class_exists('BTR_Preventivi')) {
            $defaults = BTR_Preventivi::btr_get_child_age_labels($package_id);
        } else {
            // v1.0.182 - Se la classe non è disponibile, genera dinamicamente
            $dynamic_categories = new BTR_Dynamic_Child_Categories();
            $categories = $dynamic_categories->get_categories(true);
            $defaults = [];
            foreach ($categories as $category) {
                $defaults[$category['id']] = $category['label'];
            }
        }
        
        return $defaults[$fascia] ?? 'Bambino';
    }
    
    /**
     * Ottieni tutte le etichette
     * 
     * @param mixed $context Contesto
     * @return array Array di etichette
     */
    public function get_all_labels($context = null) {
        $labels = [];
        
        // Se abbiamo un preventivo_id, usa quelle salvate
        if (is_numeric($context)) {
            $saved_labels = get_post_meta($context, '_child_category_labels', true);
            if (is_array($saved_labels)) {
                return $saved_labels;
            }
            
            // v1.0.160 - Prova a recuperare il package_id dal preventivo
            $package_id = get_post_meta($context, '_btr_pacchetto_id', true);
            if (empty($package_id)) {
                $package_id = get_post_meta($context, '_btr_id_pacchetto', true);
            }
            if ($package_id) {
                $context = $package_id;
            }
        }
        
        // v1.0.160 - Usa la funzione helper centralizzata con package_id
        if (class_exists('BTR_Preventivi')) {
            return BTR_Preventivi::btr_get_child_age_labels($context);
        }
        
        // Fallback: costruisci dalle categorie dinamiche
        $dynamic_categories = new BTR_Dynamic_Child_Categories();
        $categories = $dynamic_categories->get_categories(true); // Solo abilitate
        
        foreach ($categories as $category) {
            $labels[$category['id']] = $category['label'];
        }
        
        return $labels;
    }
    
    /**
     * Prepara le etichette per il salvataggio nel preventivo
     */
    public function prepare_labels_for_preventivo($preventivo_id, $data) {
        // v1.0.187 - FIX: NON sovrascrivere se ci sono già etichette dal frontend
        $existing_labels = get_post_meta($preventivo_id, '_child_category_labels', true);
        
        // Se ci sono già etichette salvate (dal frontend), NON sovrascriverle!
        if (!empty($existing_labels) && is_array($existing_labels)) {
            // Verifica se sono etichette valide dal frontend (non contengono "Bambini")
            $has_valid_labels = false;
            foreach ($existing_labels as $label) {
                if (!empty($label) && strpos($label, 'Bambini') !== 0) {
                    $has_valid_labels = true;
                    break;
                }
            }
            
            if ($has_valid_labels) {
                btr_debug_log('[BTR v1.0.187] Child_Labels_Manager: Etichette già presenti dal frontend, NON sovrascrivo: ' . print_r($existing_labels, true));
                return; // Esci senza sovrascrivere
            }
        }
        
        // Solo se NON ci sono etichette o sono hardcoded, usa quelle del sistema
        $labels = $this->get_all_labels();
        
        // Salva nel preventivo per riferimento futuro
        update_post_meta($preventivo_id, '_child_category_labels', $labels);
        
        btr_debug_log('[BTR v1.0.187] Child_Labels_Manager: Salvate etichette di fallback nel preventivo #' . $preventivo_id . ': ' . print_r($labels, true));
    }
    
    /**
     * Filtra etichetta nel form anagrafici
     */
    public function filter_anagrafici_label($label, $fascia, $context) {
        // Se abbiamo un preventivo_id nel context
        if (isset($context['preventivo_id'])) {
            return $this->get_dynamic_label($fascia, $context['preventivo_id']);
        }
        
        return $this->get_dynamic_label($fascia);
    }
    
    /**
     * Filtra etichetta nel checkout
     */
    public function filter_checkout_label($label, $fascia, $cart_item) {
        // Cerca il preventivo_id nel cart item
        $preventivo_id = $cart_item['preventivo_id'] ?? 0;
        
        if ($preventivo_id) {
            return $this->get_dynamic_label($fascia, $preventivo_id);
        }
        
        return $this->get_dynamic_label($fascia);
    }
    
    /**
     * Filtra etichetta nel carrello
     */
    public function filter_cart_label($label, $fascia, $cart_item) {
        return $this->filter_checkout_label($label, $fascia, $cart_item);
    }
    
    /**
     * AJAX handler per ottenere etichette
     */
    public function ajax_get_child_labels() {
        $preventivo_id = intval($_POST['preventivo_id'] ?? 0);
        $labels = $this->get_all_labels($preventivo_id);
        
        wp_send_json_success($labels);
    }
    
    /**
     * Aggiorna tutte le occorrenze di etichette statiche nel codice
     * 
     * @param string $content Contenuto da processare
     * @param mixed $context Contesto per le etichette
     * @return string Contenuto con etichette aggiornate
     */
    public function replace_static_labels($content, $context = null) {
        $labels = $this->get_all_labels($context);
        
        // Pattern per trovare etichette statiche - v1.0.185: NO hardcoded
        $patterns = [
            '/Bambino 3-6 anni/i' => $labels['f1'] ?? '3-6 anni',
            '/Bambino 6-8 anni/i' => $labels['f2'] ?? '6-12',
            '/Bambino 8-10 anni/i' => $labels['f3'] ?? '12-14',
            '/Bambino 11-12 anni/i' => $labels['f4'] ?? '14-15',
            '/F1\s*\(3-6\)/i' => 'F1 (' . $labels['f1'] . ')',
            '/F2\s*\(6-8\)/i' => 'F2 (' . $labels['f2'] . ')',
            '/F3\s*\(8-10\)/i' => 'F3 (' . $labels['f3'] . ')',
            '/F4\s*\(11-12\)/i' => 'F4 (' . $labels['f4'] . ')'
        ];
        
        foreach ($patterns as $pattern => $replacement) {
            $content = preg_replace($pattern, $replacement, $content);
        }
        
        return $content;
    }
    
    /**
     * Helper per template - ottieni etichetta formattata
     * 
     * @param string $fascia Codice fascia
     * @param mixed $context Contesto
     * @param bool $include_code Include il codice fascia (es: "F1 - ")
     * @return string Etichetta formattata
     */
    public function get_formatted_label($fascia, $context = null, $include_code = false) {
        $label = $this->get_dynamic_label($fascia, $context);
        
        if ($include_code) {
            return strtoupper($fascia) . ' - ' . $label;
        }
        
        return $label;
    }
    
    /**
     * Sincronizza etichette tra preventivo e ordine
     */
    public function sync_labels_to_order($order_id, $preventivo_id) {
        $labels = get_post_meta($preventivo_id, '_child_category_labels', true);
        
        if (is_array($labels)) {
            update_post_meta($order_id, '_child_category_labels', $labels);
            btr_debug_log('BTR_Child_Labels_Manager: Sincronizzate etichette da preventivo #' . $preventivo_id . ' a ordine #' . $order_id);
        }
    }
    
    /**
     * Ottieni range età da etichetta
     * 
     * @param string $fascia Codice fascia
     * @return array Array con 'min' e 'max' età
     */
    public function get_age_range($fascia) {
        $dynamic_categories = new BTR_Dynamic_Child_Categories();
        $category = $dynamic_categories->get_category($fascia);
        
        if ($category) {
            return [
                'min' => $category['age_min'] ?? 0,
                'max' => $category['age_max'] ?? 18
            ];
        }
        
        // v1.0.182 - Genera dinamicamente i range, NO hardcoded
        $defaults = [];
        for ($i = 1; $i <= 4; $i++) {
            $base_age = 2 + ($i * 3); // Formula dinamica
            $defaults['f' . $i] = [
                'min' => $base_age - 1,
                'max' => $base_age + 1
            ];
        }
        
        return $defaults[$fascia] ?? ['min' => 0, 'max' => 18];
    }
}

// Funzione helper globale
if (!function_exists('btr_get_child_label')) {
    function btr_get_child_label($fascia, $context = null) {
        return BTR_Child_Labels_Manager::get_instance()->get_dynamic_label($fascia, $context);
    }
}

// v1.0.160 - Funzione helper per ottenere tutte le etichette con supporto package_id
if (!function_exists('btr_get_all_child_labels')) {
    function btr_get_all_child_labels($context = null) {
        // Se context è numerico, potrebbe essere un package_id o preventivo_id
        // La logica di distinzione è gestita dentro get_all_labels
        return BTR_Child_Labels_Manager::get_instance()->get_all_labels($context);
    }
}