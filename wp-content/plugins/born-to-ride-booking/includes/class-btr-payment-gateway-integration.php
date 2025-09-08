<?php
/**
 * Integrazione Gateway di Pagamento per Born to Ride
 * 
 * Gestisce l'integrazione con gateway di pagamento per supportare
 * pagamenti caparra/saldo con Stripe, PayPal e altri gateway.
 * 
 * @package BornToRideBooking
 * @since 1.0.98
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Payment_Gateway_Integration {
    
    /**
     * Instance singleton
     */
    private static $instance = null;
    
    /**
     * Gateway registrati
     */
    private $gateways = [];
    
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
        // Inizializza gateway
        add_action('init', [$this, 'init_gateways']);
        
        // Hook per pagamenti
        add_filter('woocommerce_payment_complete_order_status', [$this, 'handle_payment_complete'], 10, 3);
        add_action('woocommerce_order_status_changed', [$this, 'handle_order_status_change'], 10, 4);
        
        // Hook per webhook
        add_action('woocommerce_api_btr_payment_webhook', [$this, 'handle_webhook']);
        
        // Hook per refund
        add_action('woocommerce_order_refunded', [$this, 'handle_refund'], 10, 2);
        
        // Filtri per gateway specifici
        add_filter('woocommerce_gateway_stripe_process_payment', [$this, 'process_stripe_deposit'], 10, 2);
        add_filter('woocommerce_paypal_payments_process_payment', [$this, 'process_paypal_deposit'], 10, 2);
        
        // Admin notices
        add_action('admin_notices', [$this, 'check_gateway_requirements']);
    }
    
    /**
     * Inizializza gateway supportati
     */
    public function init_gateways() {
        // Registra gateway Stripe
        if ($this->is_stripe_available()) {
            $this->register_gateway('stripe', new BTR_Gateway_Stripe());
        }
        
        // Registra gateway PayPal
        if ($this->is_paypal_available()) {
            $this->register_gateway('paypal', new BTR_Gateway_PayPal());
        }
        
        // Hook per gateway custom
        do_action('btr_register_payment_gateways', $this);
    }
    
    /**
     * Registra un gateway
     */
    public function register_gateway($id, BTR_Gateway_Interface $gateway) {
        $this->gateways[$id] = $gateway;
        btr_debug_log("Gateway registrato: $id");
    }
    
    /**
     * Ottieni gateway per ID
     */
    public function get_gateway($id) {
        return isset($this->gateways[$id]) ? $this->gateways[$id] : null;
    }
    
    /**
     * Gestisce completamento pagamento
     */
    public function handle_payment_complete($order_status, $order_id, $order) {
        $payment_mode = $order->get_meta('_btr_payment_mode');
        
        if ($payment_mode === 'deposit') {
            // Se è caparra, imposta stato custom
            return 'deposit-paid';
        } elseif ($payment_mode === 'balance') {
            // Se è saldo, imposta pagamento completo
            return 'fully-paid';
        }
        
        return $order_status;
    }
    
    /**
     * Gestisce cambio stato ordine
     */
    public function handle_order_status_change($order_id, $old_status, $new_status, $order) {
        $payment_mode = $order->get_meta('_btr_payment_mode');
        
        // Log per debug
        btr_debug_log("Cambio stato ordine #$order_id: $old_status -> $new_status (mode: $payment_mode)");
        
        // Se ordine caparra diventa pagato
        if ($payment_mode === 'deposit' && in_array($new_status, ['processing', 'completed'])) {
            $this->process_deposit_paid($order);
        }
        
        // Se ordine saldo diventa pagato
        if ($payment_mode === 'balance' && in_array($new_status, ['processing', 'completed'])) {
            $this->process_balance_paid($order);
        }
    }
    
    /**
     * Processa pagamento caparra completato
     */
    private function process_deposit_paid($order) {
        $preventivo_id = $order->get_meta('_btr_preventivo_id');
        if (!$preventivo_id) {
            return;
        }
        
        // Aggiorna stato preventivo
        update_post_meta($preventivo_id, '_btr_deposit_status', 'paid');
        update_post_meta($preventivo_id, '_btr_deposit_paid_date', current_time('mysql'));
        update_post_meta($preventivo_id, '_btr_deposit_order_id', $order->get_id());
        
        // Genera payment intent per saldo futuro (se Stripe)
        $payment_method = $order->get_payment_method();
        if ($payment_method === 'stripe' && $gateway = $this->get_gateway('stripe')) {
            $balance_amount = $order->get_meta('_btr_balance_amount');
            $gateway->create_future_payment_intent($order, $balance_amount);
        }
        
        // Trigger evento
        do_action('btr_deposit_payment_completed', $preventivo_id, $order);
    }
    
    /**
     * Processa pagamento saldo completato
     */
    private function process_balance_paid($order) {
        $preventivo_id = $order->get_meta('_btr_preventivo_id');
        if (!$preventivo_id) {
            return;
        }
        
        // Aggiorna stato preventivo
        update_post_meta($preventivo_id, '_btr_payment_status', 'fully_paid');
        update_post_meta($preventivo_id, '_btr_balance_paid_date', current_time('mysql'));
        update_post_meta($preventivo_id, '_btr_balance_order_id', $order->get_id());
        
        // Aggiorna ordine caparra collegato
        $deposit_order_id = get_post_meta($preventivo_id, '_btr_deposit_order_id', true);
        if ($deposit_order_id) {
            $deposit_order = wc_get_order($deposit_order_id);
            if ($deposit_order) {
                $deposit_order->update_status('fully-paid', __('Saldo pagato', 'born-to-ride-booking'));
            }
        }
        
        // Trigger evento
        do_action('btr_full_payment_completed', $preventivo_id, $order);
    }
    
    /**
     * Gestisce webhook gateway
     */
    public function handle_webhook() {
        $gateway_id = isset($_GET['gateway']) ? sanitize_text_field($_GET['gateway']) : '';
        $gateway = $this->get_gateway($gateway_id);
        
        if (!$gateway) {
            wp_die('Gateway non valido', 'Errore', ['response' => 400]);
        }
        
        // Delega al gateway specifico
        $gateway->handle_webhook();
    }
    
    /**
     * Gestisce refund
     */
    public function handle_refund($order_id, $refund_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $payment_mode = $order->get_meta('_btr_payment_mode');
        $refund = wc_get_order($refund_id);
        
        // Log refund
        btr_debug_log("Refund ordine #$order_id (mode: $payment_mode): " . $refund->get_amount());
        
        // Se è refund totale di caparra
        if ($payment_mode === 'deposit' && $refund->get_amount() == $order->get_total()) {
            $preventivo_id = $order->get_meta('_btr_preventivo_id');
            if ($preventivo_id) {
                update_post_meta($preventivo_id, '_btr_deposit_status', 'refunded');
                
                // Cancella payment intent futuro se esiste
                $payment_method = $order->get_payment_method();
                if ($payment_method === 'stripe' && $gateway = $this->get_gateway('stripe')) {
                    $gateway->cancel_future_payment_intent($order);
                }
            }
        }
        
        do_action('btr_payment_refunded', $order, $refund);
    }
    
    /**
     * Processa pagamento Stripe per caparra
     */
    public function process_stripe_deposit($result, $order_id) {
        $order = wc_get_order($order_id);
        if (!$order || $order->get_meta('_btr_payment_mode') !== 'deposit') {
            return $result;
        }
        
        $gateway = $this->get_gateway('stripe');
        if (!$gateway) {
            return $result;
        }
        
        // Modifica metadati Stripe per caparra
        return $gateway->process_deposit_payment($result, $order);
    }
    
    /**
     * Processa pagamento PayPal per caparra
     */
    public function process_paypal_deposit($result, $order_id) {
        $order = wc_get_order($order_id);
        if (!$order || $order->get_meta('_btr_payment_mode') !== 'deposit') {
            return $result;
        }
        
        $gateway = $this->get_gateway('paypal');
        if (!$gateway) {
            return $result;
        }
        
        // Modifica parametri PayPal per caparra
        return $gateway->process_deposit_payment($result, $order);
    }
    
    /**
     * Verifica requisiti gateway
     */
    public function check_gateway_requirements() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $notices = [];
        
        // Verifica Stripe
        if (!$this->is_stripe_available() && $this->is_stripe_configured()) {
            $notices[] = __('BTR Payment Integration: WooCommerce Stripe plugin non attivo', 'born-to-ride-booking');
        }
        
        // Verifica PayPal
        if (!$this->is_paypal_available() && $this->is_paypal_configured()) {
            $notices[] = __('BTR Payment Integration: WooCommerce PayPal Payments plugin non attivo', 'born-to-ride-booking');
        }
        
        foreach ($notices as $notice) {
            echo '<div class="notice notice-warning"><p>' . esc_html($notice) . '</p></div>';
        }
    }
    
    /**
     * Verifica se Stripe è disponibile
     */
    private function is_stripe_available() {
        return class_exists('WC_Gateway_Stripe') || class_exists('WC_Stripe');
    }
    
    /**
     * Verifica se PayPal è disponibile
     */
    private function is_paypal_available() {
        return class_exists('WC_Gateway_PPCP') || class_exists('WC_Gateway_PayPal');
    }
    
    /**
     * Verifica se Stripe è configurato
     */
    private function is_stripe_configured() {
        $settings = get_option('woocommerce_stripe_settings', []);
        return !empty($settings['enabled']) && $settings['enabled'] === 'yes';
    }
    
    /**
     * Verifica se PayPal è configurato
     */
    private function is_paypal_configured() {
        $settings = get_option('woocommerce_ppcp_settings', []);
        if (empty($settings)) {
            $settings = get_option('woocommerce_paypal_settings', []);
        }
        return !empty($settings['enabled']) && $settings['enabled'] === 'yes';
    }
}

/**
 * Interfaccia gateway
 */
interface BTR_Gateway_Interface {
    public function process_deposit_payment($result, $order);
    public function handle_webhook();
    public function supports_partial_payments();
    public function supports_future_payments();
}

/**
 * Gateway Stripe
 */
class BTR_Gateway_Stripe implements BTR_Gateway_Interface {
    
    /**
     * Processa pagamento caparra
     */
    public function process_deposit_payment($result, $order) {
        // Aggiungi metadata per Stripe
        add_filter('wc_stripe_payment_metadata', function($metadata, $order_obj) use ($order) {
            if ($order_obj->get_id() === $order->get_id()) {
                $metadata['payment_type'] = 'deposit';
                $metadata['deposit_percentage'] = $order->get_meta('_btr_deposit_percentage');
                $metadata['full_amount'] = $order->get_meta('_btr_full_amount');
                $metadata['balance_amount'] = $order->get_meta('_btr_balance_amount');
                $metadata['preventivo_id'] = $order->get_meta('_btr_preventivo_id');
            }
            return $metadata;
        }, 10, 2);
        
        return $result;
    }
    
    /**
     * Crea payment intent per pagamento futuro
     */
    public function create_future_payment_intent($order, $amount) {
        // Recupera customer Stripe
        $customer_id = $order->get_meta('_stripe_customer_id');
        if (!$customer_id) {
            return;
        }
        
        // TODO: Implementare creazione SetupIntent per pagamenti futuri
        // Richiede integrazione diretta con Stripe API
        
        btr_debug_log("TODO: Creare SetupIntent Stripe per customer $customer_id, importo futuro: $amount");
    }
    
    /**
     * Cancella payment intent futuro
     */
    public function cancel_future_payment_intent($order) {
        $setup_intent_id = $order->get_meta('_btr_stripe_setup_intent');
        if ($setup_intent_id) {
            // TODO: Implementare cancellazione SetupIntent
            btr_debug_log("TODO: Cancellare SetupIntent Stripe: $setup_intent_id");
        }
    }
    
    /**
     * Gestisce webhook Stripe
     */
    public function handle_webhook() {
        // Verifica firma webhook
        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $endpoint_secret = get_option('btr_stripe_webhook_secret');
        
        if (!$endpoint_secret) {
            wp_die('Webhook secret non configurato', 'Errore', ['response' => 500]);
        }
        
        // TODO: Implementare verifica firma e gestione eventi
        btr_debug_log("Webhook Stripe ricevuto: " . substr($payload, 0, 200));
        
        // Rispondi con 200 OK
        wp_die('OK', 'OK', ['response' => 200]);
    }
    
    /**
     * Supporta pagamenti parziali
     */
    public function supports_partial_payments() {
        return true;
    }
    
    /**
     * Supporta pagamenti futuri
     */
    public function supports_future_payments() {
        return true;
    }
}

/**
 * Gateway PayPal
 */
class BTR_Gateway_PayPal implements BTR_Gateway_Interface {
    
    /**
     * Processa pagamento caparra
     */
    public function process_deposit_payment($result, $order) {
        // Modifica parametri PayPal per reference transaction
        add_filter('woocommerce_paypal_payments_purchase_unit_items', function($items, $order_obj) use ($order) {
            if ($order_obj->get_id() === $order->get_id()) {
                // Aggiungi flag per reference transaction
                $items['payment_options'] = [
                    'payment_type' => 'DEPOSIT',
                    'reference_transaction' => true
                ];
            }
            return $items;
        }, 10, 2);
        
        return $result;
    }
    
    /**
     * Gestisce webhook PayPal (IPN)
     */
    public function handle_webhook() {
        // Verifica IPN
        $raw_post_data = file_get_contents('php://input');
        $raw_post_array = explode('&', $raw_post_data);
        $myPost = [];
        
        foreach ($raw_post_array as $keyval) {
            $keyval = explode('=', $keyval);
            if (count($keyval) == 2) {
                $myPost[$keyval[0]] = urldecode($keyval[1]);
            }
        }
        
        // TODO: Implementare verifica IPN con PayPal
        btr_debug_log("IPN PayPal ricevuto: " . print_r($myPost, true));
        
        // Rispondi con 200 OK
        wp_die('OK', 'OK', ['response' => 200]);
    }
    
    /**
     * Supporta pagamenti parziali
     */
    public function supports_partial_payments() {
        return true;
    }
    
    /**
     * Supporta pagamenti futuri
     */
    public function supports_future_payments() {
        // PayPal supporta reference transactions
        return true;
    }
}

// Inizializza
add_action('plugins_loaded', function() {
    BTR_Payment_Gateway_Integration::get_instance();
}, 20);