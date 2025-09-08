<?php
/**
 * Gestione drag & drop per riordinare gli extra nell'admin
 * 
 * @package Born_To_Ride_Booking
 * @since 1.0.55
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Extra_Costs_Sortable {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
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
        // Hook per aggiungere script e stili nell'admin
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // AJAX handler per salvare l'ordine
        add_action('wp_ajax_btr_save_extra_costs_order', [$this, 'ajax_save_order']);
        
        // Filtro per ordinare gli extra quando vengono recuperati
        add_filter('btr_get_costi_extra', [$this, 'sort_extra_costs'], 10, 2);
        
        // Aggiungi campo ordine nei metabox
        add_action('btr_extra_costs_metabox_field', [$this, 'add_order_field'], 10, 2);
    }
    
    /**
     * Enqueue script e stili per drag & drop
     */
    public function enqueue_admin_assets($hook) {
        // Solo nelle pagine dei pacchetti
        if (!in_array($hook, ['post.php', 'post-new.php'])) {
            return;
        }
        
        global $post;
        if (!$post || $post->post_type !== 'pacchetti') {
            return;
        }
        
        // jQuery UI Sortable
        wp_enqueue_script('jquery-ui-sortable');
        
        // Script personalizzato
        wp_enqueue_script(
            'btr-extra-costs-sortable',
            BTR_PLUGIN_URL . 'assets/js/admin-extra-costs-sortable.js',
            ['jquery', 'jquery-ui-sortable'],
            BTR_VERSION,
            true
        );
        
        // Localizza script
        wp_localize_script('btr-extra-costs-sortable', 'btr_sortable', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('btr-extra-costs-order'),
            'saving_text' => __('Salvataggio ordine...', 'born-to-ride-booking'),
            'saved_text' => __('Ordine salvato!', 'born-to-ride-booking'),
            'error_text' => __('Errore nel salvataggio', 'born-to-ride-booking')
        ]);
        
        // Stili CSS inline
        $css = '
            .btr-extra-costs-list {
                position: relative;
            }
            
            .btr-extra-cost-item {
                background: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 3px;
                padding: 10px;
                margin-bottom: 10px;
                cursor: move;
                position: relative;
            }
            
            .btr-extra-cost-item.ui-sortable-helper {
                opacity: 0.8;
                box-shadow: 0 3px 6px rgba(0,0,0,0.2);
            }
            
            .btr-extra-cost-item.ui-sortable-placeholder {
                visibility: visible !important;
                background: #f0f0f0;
                border: 2px dashed #999;
            }
            
            .btr-drag-handle {
                position: absolute;
                left: 5px;
                top: 50%;
                transform: translateY(-50%);
                width: 20px;
                height: 20px;
                cursor: grab;
                opacity: 0.5;
                transition: opacity 0.2s;
            }
            
            .btr-drag-handle:hover {
                opacity: 1;
            }
            
            .btr-drag-handle::before {
                content: "≡";
                font-size: 20px;
                line-height: 20px;
                display: block;
                text-align: center;
            }
            
            .btr-extra-cost-item.is-dragging .btr-drag-handle {
                cursor: grabbing;
            }
            
            .btr-extra-cost-content {
                margin-left: 30px;
            }
            
            .btr-sort-status {
                display: inline-block;
                margin-left: 10px;
                padding: 3px 8px;
                border-radius: 3px;
                font-size: 12px;
                opacity: 0;
                transition: opacity 0.3s;
            }
            
            .btr-sort-status.show {
                opacity: 1;
            }
            
            .btr-sort-status.saving {
                background: #f0b849;
                color: #fff;
            }
            
            .btr-sort-status.saved {
                background: #46b450;
                color: #fff;
            }
            
            .btr-sort-status.error {
                background: #dc3232;
                color: #fff;
            }
            
            /* Aggiunta indicatore ordine */
            .btr-extra-order-badge {
                position: absolute;
                top: 5px;
                right: 5px;
                background: #0073aa;
                color: white;
                border-radius: 3px;
                padding: 2px 6px;
                font-size: 11px;
                font-weight: bold;
            }
        ';
        
        wp_add_inline_style('wp-admin', $css);
    }
    
    /**
     * AJAX handler per salvare l'ordine
     */
    public function ajax_save_order() {
        check_ajax_referer('btr-extra-costs-order', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permessi insufficienti', 'born-to-ride-booking')]);
            return;
        }
        
        $package_id = intval($_POST['package_id'] ?? 0);
        $order = $_POST['order'] ?? [];
        
        if (!$package_id) {
            wp_send_json_error(['message' => __('ID pacchetto non valido', 'born-to-ride-booking')]);
            return;
        }
        
        // Recupera gli extra correnti
        $costi_extra = get_post_meta($package_id, 'btr_costi_extra', true);
        if (!is_array($costi_extra)) {
            wp_send_json_error(['message' => __('Nessun extra trovato', 'born-to-ride-booking')]);
            return;
        }
        
        // Riordina gli extra secondo il nuovo ordine
        $ordered_extras = [];
        $order_index = 0;
        
        foreach ($order as $slug) {
            foreach ($costi_extra as $extra) {
                if (($extra['slug'] ?? '') === $slug) {
                    $extra['order'] = $order_index++;
                    $ordered_extras[] = $extra;
                    break;
                }
            }
        }
        
        // Aggiungi eventuali extra non presenti nell'ordine (per sicurezza)
        foreach ($costi_extra as $extra) {
            $found = false;
            foreach ($ordered_extras as $ordered) {
                if (($ordered['slug'] ?? '') === ($extra['slug'] ?? '')) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $extra['order'] = $order_index++;
                $ordered_extras[] = $extra;
            }
        }
        
        // Salva l'ordine aggiornato
        update_post_meta($package_id, 'btr_costi_extra', $ordered_extras);
        
        btr_debug_log('BTR_Extra_Costs_Sortable: Aggiornato ordine extra per pacchetto #' . $package_id);
        
        wp_send_json_success([
            'message' => __('Ordine salvato con successo', 'born-to-ride-booking'),
            'order' => array_column($ordered_extras, 'slug')
        ]);
    }
    
    /**
     * Ordina gli extra quando vengono recuperati
     */
    public function sort_extra_costs($costi_extra, $package_id) {
        if (!is_array($costi_extra)) {
            return $costi_extra;
        }
        
        // Ordina per campo 'order', poi per nome
        usort($costi_extra, function($a, $b) {
            $order_a = isset($a['order']) ? intval($a['order']) : 999;
            $order_b = isset($b['order']) ? intval($b['order']) : 999;
            
            if ($order_a === $order_b) {
                // Se l'ordine è uguale, ordina per nome
                $nome_a = $a['nome'] ?? '';
                $nome_b = $b['nome'] ?? '';
                return strcasecmp($nome_a, $nome_b);
            }
            
            return $order_a - $order_b;
        });
        
        return $costi_extra;
    }
    
    /**
     * Aggiunge campo ordine nascosto nei metabox
     */
    public function add_order_field($extra, $index) {
        $order = isset($extra['order']) ? intval($extra['order']) : $index;
        ?>
        <input type="hidden" 
               name="btr_costi_extra[<?php echo esc_attr($index); ?>][order]" 
               value="<?php echo esc_attr($order); ?>" 
               class="btr-extra-order-field">
        <?php
    }
    
    /**
     * Helper per ottenere gli extra ordinati
     */
    public static function get_sorted_extras($package_id) {
        $costi_extra = get_post_meta($package_id, 'btr_costi_extra', true);
        
        if (!is_array($costi_extra)) {
            return [];
        }
        
        // Applica il filtro per ordinare
        return apply_filters('btr_get_costi_extra', $costi_extra, $package_id);
    }
}

// Inizializza la classe
add_action('init', function() {
    BTR_Extra_Costs_Sortable::get_instance();
});