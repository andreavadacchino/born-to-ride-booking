<?php
/**
 * BTR Order Recovery System
 * 
 * Gestisce il recupero di ordini abbandonati e la riparazione di ordini esistenti
 * 
 * @package Born_To_Ride_Booking
 * @since 1.0.235
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class BTR_Order_Recovery
 * 
 * Sistema intelligente per recuperare ordini abbandonati e riparare ordini esistenti
 * Include anche funzionalità per generare link di recovery sicuri
 */
class BTR_Order_Recovery {
    
    /**
     * Instance singleton
     */
    private static $instance = null;
    
    /**
     * Get instance
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
    public function __construct() {
        // Hook per gestire recovery link
        add_action('init', [$this, 'handle_recovery_link']);
        
        // Hook per riparare ordini al login
        add_action('wp_login', [$this, 'repair_user_orders_on_login'], 10, 2);
        
        // Ajax handler per check ordini da riparare
        add_action('wp_ajax_btr_check_orders_to_repair', [$this, 'ajax_check_orders_to_repair']);
        
        // Scheduled action per identificare ordini abbandonati
        add_action('btr_check_abandoned_orders', [$this, 'check_and_mark_abandoned_orders']);
        
        // Schedule cron se non esiste
        if (!wp_next_scheduled('btr_check_abandoned_orders')) {
            wp_schedule_event(time(), 'hourly', 'btr_check_abandoned_orders');
        }
    }
    
    /**
     * Ripara ordini esistenti senza metadati corretti
     * 
     * Questo metodo trova ordini che hanno _btr_preventivo_id ma non _btr_is_group_organizer
     * e aggiunge i metadati mancanti
     * 
     * @param int|null $user_id Limita la riparazione agli ordini di un utente specifico
     * @return array Report con numero di ordini riparati
     */
    public function repair_existing_orders($user_id = null) {
        global $wpdb;
        
        $repaired = 0;
        $errors = [];
        
        // Query per trovare ordini con preventivo ma senza flag organizzatore
        $query = "
            SELECT DISTINCT p.ID, p.post_author, pm1.meta_value as preventivo_id
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_btr_preventivo_id'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_btr_is_group_organizer'
            WHERE p.post_type IN ('shop_order', 'shop_order_placehold')
            AND pm2.meta_id IS NULL
        ";
        
        if ($user_id) {
            $query .= $wpdb->prepare(" AND p.post_author = %d", $user_id);
        }
        
        $orders_to_repair = $wpdb->get_results($query);
        
        foreach ($orders_to_repair as $order_data) {
            try {
                // Verifica che il preventivo esista e sia valido
                $preventivo = get_post($order_data->preventivo_id);
                if (!$preventivo || $preventivo->post_type !== 'btr_preventivi') {
                    continue;
                }
                
                // Verifica se ci sono pagamenti di gruppo per questo preventivo
                $has_group_payments = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}btr_group_payments WHERE preventivo_id = %d",
                    $order_data->preventivo_id
                ));
                
                if ($has_group_payments > 0) {
                    // Aggiungi metadati mancanti
                    update_post_meta($order_data->ID, '_btr_is_group_organizer', 'yes');
                    update_post_meta($order_data->ID, '_btr_order_type', 'group_organizer');
                    update_post_meta($order_data->ID, '_customer_user', $order_data->post_author);
                    
                    // Recupera e salva totali dal preventivo
                    $totale = get_post_meta($order_data->preventivo_id, '_prezzo_totale', true);
                    if ($totale) {
                        update_post_meta($order_data->ID, '_btr_total_amount', $totale);
                    }
                    
                    // Log
                    btr_debug_log('BTR Recovery: Riparato ordine ' . $order_data->ID . ' per preventivo ' . $order_data->preventivo_id);
                    
                    // Aggiungi nota all'ordine
                    $order = wc_get_order($order_data->ID);
                    if ($order) {
                        $order->add_order_note(__('Ordine organizzatore riparato dal sistema recovery.', 'born-to-ride-booking'));
                    }
                    
                    $repaired++;
                }
                
            } catch (Exception $e) {
                $errors[] = 'Errore riparazione ordine ' . $order_data->ID . ': ' . $e->getMessage();
                btr_debug_log('BTR Recovery Error: ' . $e->getMessage());
            }
        }
        
        return [
            'repaired' => $repaired,
            'errors' => $errors,
            'checked' => count($orders_to_repair)
        ];
    }
    
    /**
     * Identifica ordini abbandonati e li marca per recovery
     */
    public function check_and_mark_abandoned_orders() {
        global $wpdb;
        
        // Trova ordini draft più vecchi di 1 ora
        $one_hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));
        
        $abandoned_orders = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, p.post_date, p.post_author
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            WHERE p.post_type IN ('shop_order', 'shop_order_placehold')
            AND p.post_status = 'draft'
            AND p.post_date < %s
            AND pm.meta_key = '_btr_is_group_organizer'
            AND pm.meta_value = 'yes'
            AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm2 
                WHERE pm2.post_id = p.ID 
                AND pm2.meta_key = '_btr_abandoned_notified'
            )
        ", $one_hour_ago));
        
        foreach ($abandoned_orders as $order_data) {
            // Marca come notificato per evitare spam
            update_post_meta($order_data->ID, '_btr_abandoned_notified', current_time('timestamp'));
            
            // Genera recovery token
            $recovery_token = $this->generate_recovery_token($order_data->ID);
            update_post_meta($order_data->ID, '_btr_recovery_token', $recovery_token);
            
            // Trigger per email (gestito da class-btr-abandoned-cart-emails.php)
            do_action('btr_order_abandoned', $order_data->ID, $recovery_token);
            
            btr_debug_log('BTR Recovery: Ordine ' . $order_data->ID . ' marcato come abbandonato');
        }
    }
    
    /**
     * Genera token sicuro per recovery link
     */
    private function generate_recovery_token($order_id) {
        return wp_hash($order_id . wp_salt('auth') . time());
    }
    
    /**
     * Gestisce click su recovery link
     */
    public function handle_recovery_link() {
        if (!isset($_GET['btr-recovery']) || !isset($_GET['token'])) {
            return;
        }
        
        $order_id = intval($_GET['btr-recovery']);
        $token = sanitize_text_field($_GET['token']);
        
        // Verifica token
        $stored_token = get_post_meta($order_id, '_btr_recovery_token', true);
        if (!$stored_token || $stored_token !== $token) {
            wp_die(__('Link di recupero non valido o scaduto.', 'born-to-ride-booking'));
        }
        
        // Verifica che l'ordine esista e sia draft
        $order = wc_get_order($order_id);
        if (!$order || $order->get_status() !== 'draft') {
            wp_die(__('Ordine non trovato o già completato.', 'born-to-ride-booking'));
        }
        
        // Auto-login se necessario
        $user_id = $order->get_customer_id();
        if ($user_id && !is_user_logged_in()) {
            wp_set_auth_cookie($user_id, true);
            wp_set_current_user($user_id);
        }
        
        // Ripristina carrello
        $this->restore_cart_from_order($order);
        
        // Redirect al checkout
        wp_redirect(wc_get_checkout_url());
        exit;
    }
    
    /**
     * Ripristina carrello da ordine draft
     */
    private function restore_cart_from_order($order) {
        // Svuota carrello corrente
        WC()->cart->empty_cart();
        
        // Recupera dati dal preventivo
        $preventivo_id = $order->get_meta('_btr_preventivo_id');
        if (!$preventivo_id) {
            return;
        }
        
        // Ricrea il prodotto virtuale nel carrello
        $product_id = get_option('btr_virtual_organizer_product_id');
        if ($product_id) {
            WC()->cart->add_to_cart($product_id, 1, 0, array(), array(
                'btr_preventivo_id' => $preventivo_id,
                'btr_order_type' => 'group_organizer',
                'btr_total_amount' => $order->get_meta('_btr_total_amount'),
                'btr_covered_amount' => $order->get_meta('_btr_covered_amount'),
                'custom_price' => 0
            ));
            
            // Ripristina dati sessione
            WC()->session->set('btr_is_organizer_order', true);
            WC()->session->set('btr_preventivo_id', $preventivo_id);
            WC()->session->set('btr_payment_type', 'group_organizer');
            WC()->session->set('btr_total_amount', $order->get_meta('_btr_total_amount'));
            WC()->session->set('btr_covered_amount', $order->get_meta('_btr_covered_amount'));
            WC()->session->set('btr_participants_info', $order->get_meta('_btr_participants_info'));
            
            // Salva sessione
            WC()->session->save_data();
            
            btr_debug_log('BTR Recovery: Carrello ripristinato per ordine ' . $order->get_id());
        }
    }
    
    /**
     * Ripara ordini dell'utente al login
     */
    public function repair_user_orders_on_login($user_login, $user) {
        $this->repair_existing_orders($user->ID);
    }
    
    /**
     * Ajax handler per check ordini da riparare (admin)
     */
    public function ajax_check_orders_to_repair() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Unauthorized');
        }
        
        $result = $this->repair_existing_orders();
        
        wp_send_json_success($result);
    }
    
    /**
     * Metodo helper per generare recovery URL
     */
    public static function get_recovery_url($order_id, $token = null) {
        if (!$token) {
            $token = get_post_meta($order_id, '_btr_recovery_token', true);
        }
        
        return add_query_arg([
            'btr-recovery' => $order_id,
            'token' => $token
        ], home_url());
    }
}

// Inizializza
BTR_Order_Recovery::get_instance();