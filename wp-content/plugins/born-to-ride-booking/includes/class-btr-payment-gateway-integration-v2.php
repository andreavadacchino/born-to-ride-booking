<?php
/**
 * Integrazione Gateway di Pagamento Ottimizzata per Born to Ride
 * 
 * Utilizza i gateway WooCommerce esistenti quando disponibili,
 * evitando duplicazione di configurazioni API.
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
        // Inizializza gateway dopo che WooCommerce è caricato
        add_action('woocommerce_init', [$this, 'init_gateways']);
        
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
        // Ottieni tutti i gateway WooCommerce disponibili
        $wc_gateways = WC()->payment_gateways ? WC()->payment_gateways->payment_gateways() : [];
        
        // Registra gateway Stripe se disponibile
        if (isset($wc_gateways['stripe']) && $wc_gateways['stripe']->enabled === 'yes') {
            $this->register_gateway('stripe', new BTR_Gateway_Stripe_Optimized($wc_gateways['stripe']));
            btr_debug_log('[BTR Gateway] Stripe registrato usando configurazione WooCommerce esistente');
        }
        
        // Registra gateway PayPal se disponibile
        $paypal_gateway = null;
        if (isset($wc_gateways['ppcp-gateway']) && $wc_gateways['ppcp-gateway']->enabled === 'yes') {
            $paypal_gateway = $wc_gateways['ppcp-gateway'];
        } elseif (isset($wc_gateways['paypal']) && $wc_gateways['paypal']->enabled === 'yes') {
            $paypal_gateway = $wc_gateways['paypal'];
        }
        
        if ($paypal_gateway) {
            $this->register_gateway('paypal', new BTR_Gateway_PayPal_Optimized($paypal_gateway));
            btr_debug_log('[BTR Gateway] PayPal registrato usando configurazione WooCommerce esistente');
        }
        
        // Hook per gateway custom
        do_action('btr_register_payment_gateways', $this);
    }
    
    /**
     * Registra un gateway
     */
    public function register_gateway($id, BTR_Gateway_Interface $gateway) {
        $this->gateways[$id] = $gateway;
    }
    
    /**
     * Ottieni gateway per ID
     */
    public function get_gateway($id) {
        return isset($this->gateways[$id]) ? $this->gateways[$id] : null;
    }
    
    /**
     * Verifica se un gateway è disponibile e configurato
     */
    public function is_gateway_available($gateway_id) {
        return isset($this->gateways[$gateway_id]);
    }
    
    /**
     * Ottieni configurazione gateway
     */
    public function get_gateway_config($gateway_id) {
        $gateway = $this->get_gateway($gateway_id);
        if (!$gateway) {
            return false;
        }
        
        return $gateway->get_configuration();
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
        
        // Verifica configurazioni mancanti
        if (!$this->is_gateway_available('stripe') && $this->is_stripe_plugin_active()) {
            $notices[] = sprintf(
                __('BTR Payment: Gateway Stripe disponibile ma non abilitato. <a href="%s">Abilita Stripe</a>', 'born-to-ride-booking'),
                admin_url('admin.php?page=wc-settings&tab=checkout&section=stripe')
            );
        }
        
        if (!$this->is_gateway_available('paypal') && $this->is_paypal_plugin_active()) {
            $notices[] = sprintf(
                __('BTR Payment: Gateway PayPal disponibile ma non abilitato. <a href="%s">Abilita PayPal</a>', 'born-to-ride-booking'),
                admin_url('admin.php?page=wc-settings&tab=checkout')
            );
        }
        
        foreach ($notices as $notice) {
            echo '<div class="notice notice-warning"><p>' . wp_kses_post($notice) . '</p></div>';
        }
    }
    
    /**
     * Verifica se plugin Stripe è attivo
     */
    private function is_stripe_plugin_active() {
        return class_exists('WC_Gateway_Stripe');
    }
    
    /**
     * Verifica se plugin PayPal è attivo
     */
    private function is_paypal_plugin_active() {
        return class_exists('WC_Gateway_PPCP') || class_exists('WC_Gateway_PayPal');
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
    public function get_configuration();
}

/**
 * Gateway Stripe Ottimizzato
 */
class BTR_Gateway_Stripe_Optimized implements BTR_Gateway_Interface {
    
    /**
     * Gateway WooCommerce Stripe
     */
    private $wc_gateway;
    
    /**
     * Stripe API
     */
    private $stripe_api;
    
    /**
     * Constructor
     */
    public function __construct($wc_gateway) {
        $this->wc_gateway = $wc_gateway;
        
        // Inizializza Stripe API se disponibile
        if (class_exists('WC_Stripe_API')) {
            $this->stripe_api = new WC_Stripe_API();
        }
    }
    
    /**
     * Ottieni configurazione
     */
    public function get_configuration() {
        return [
            'enabled' => $this->wc_gateway->enabled === 'yes',
            'testmode' => $this->wc_gateway->testmode === 'yes',
            'secret_key' => $this->wc_gateway->secret_key,
            'publishable_key' => $this->wc_gateway->publishable_key,
            'webhook_secret' => $this->wc_gateway->webhook_secret ?? get_option('btr_stripe_webhook_secret'),
            'supports_future_payments' => true,
            'supports_partial_payments' => true
        ];
    }
    
    /**
     * Processa pagamento caparra
     */
    public function process_deposit_payment($result, $order) {
        // Aggiungi metadata per Stripe usando l'API del plugin
        add_filter('wc_stripe_payment_metadata', function($metadata, $order_obj) use ($order) {
            if ($order_obj->get_id() === $order->get_id()) {
                $metadata['payment_type'] = 'deposit';
                $metadata['deposit_percentage'] = $order->get_meta('_btr_deposit_percentage');
                $metadata['full_amount'] = $order->get_meta('_btr_full_amount');
                $metadata['balance_amount'] = $order->get_meta('_btr_balance_amount');
                $metadata['preventivo_id'] = $order->get_meta('_btr_preventivo_id');
                $metadata['btr_payment'] = true;
            }
            return $metadata;
        }, 10, 2);
        
        return $result;
    }
    
    /**
     * Crea payment intent per pagamento futuro
     */
    public function create_future_payment_intent($order, $amount) {
        // Recupera customer Stripe dal plugin WooCommerce
        $customer_id = $order->get_meta('_stripe_customer_id');
        if (!$customer_id) {
            return;
        }
        
        // Usa l'API del plugin Stripe per creare SetupIntent
        if ($this->stripe_api) {
            try {
                $intent = WC_Stripe_API::request([
                    'customer' => $customer_id,
                    'usage' => 'off_session',
                    'metadata' => [
                        'order_id' => $order->get_id(),
                        'preventivo_id' => $order->get_meta('_btr_preventivo_id'),
                        'future_amount' => $amount,
                        'payment_type' => 'balance'
                    ]
                ], 'setup_intents');
                
                if (!is_wp_error($intent)) {
                    $order->update_meta_data('_btr_stripe_setup_intent', $intent->id);
                    $order->save();
                    btr_debug_log("SetupIntent creato: {$intent->id}");
                }
            } catch (Exception $e) {
                btr_debug_log("Errore creazione SetupIntent: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Cancella payment intent futuro
     */
    public function cancel_future_payment_intent($order) {
        $setup_intent_id = $order->get_meta('_btr_stripe_setup_intent');
        if ($setup_intent_id && $this->stripe_api) {
            try {
                WC_Stripe_API::request([], "setup_intents/{$setup_intent_id}/cancel", 'POST');
                $order->delete_meta_data('_btr_stripe_setup_intent');
                $order->save();
                btr_debug_log("SetupIntent cancellato: $setup_intent_id");
            } catch (Exception $e) {
                btr_debug_log("Errore cancellazione SetupIntent: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Gestisce webhook Stripe
     */
    public function handle_webhook() {
        // Usa il sistema webhook del plugin WooCommerce Stripe
        // Il plugin gestisce già la verifica della firma
        $payload = @file_get_contents('php://input');
        $event = json_decode($payload, true);
        
        if (!$event || !isset($event['type'])) {
            wp_die('Evento non valido', 'Errore', ['response' => 400]);
        }
        
        // Processa solo eventi BTR
        if (isset($event['data']['object']['metadata']['btr_payment'])) {
            switch ($event['type']) {
                case 'payment_intent.succeeded':
                    $this->handle_payment_success($event['data']['object']);
                    break;
                case 'payment_intent.payment_failed':
                    $this->handle_payment_failure($event['data']['object']);
                    break;
                case 'setup_intent.succeeded':
                    $this->handle_setup_intent_success($event['data']['object']);
                    break;
            }
        }
        
        wp_die('OK', 'OK', ['response' => 200]);
    }
    
    /**
     * Gestisce pagamento completato
     */
    private function handle_payment_success($payment_intent) {
        $order_id = $payment_intent['metadata']['order_id'] ?? null;
        if (!$order_id) {
            return;
        }
        
        $order = wc_get_order($order_id);
        if ($order) {
            $order->payment_complete($payment_intent['id']);
            btr_debug_log("Pagamento Stripe completato per ordine #{$order_id}");
        }
    }
    
    /**
     * Gestisce pagamento fallito
     */
    private function handle_payment_failure($payment_intent) {
        $order_id = $payment_intent['metadata']['order_id'] ?? null;
        if (!$order_id) {
            return;
        }
        
        $order = wc_get_order($order_id);
        if ($order) {
            $order->update_status('failed', __('Pagamento Stripe fallito', 'born-to-ride-booking'));
            btr_debug_log("Pagamento Stripe fallito per ordine #{$order_id}");
        }
    }
    
    /**
     * Gestisce setup intent completato
     */
    private function handle_setup_intent_success($setup_intent) {
        $preventivo_id = $setup_intent['metadata']['preventivo_id'] ?? null;
        if ($preventivo_id) {
            update_post_meta($preventivo_id, '_btr_stripe_payment_method', $setup_intent['payment_method']);
            btr_debug_log("Metodo di pagamento salvato per preventivo #{$preventivo_id}");
        }
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
 * Gateway PayPal Ottimizzato
 */
class BTR_Gateway_PayPal_Optimized implements BTR_Gateway_Interface {
    
    /**
     * Gateway WooCommerce PayPal
     */
    private $wc_gateway;
    
    /**
     * Constructor
     */
    public function __construct($wc_gateway) {
        $this->wc_gateway = $wc_gateway;
    }
    
    /**
     * Ottieni configurazione
     */
    public function get_configuration() {
        $config = [
            'enabled' => $this->wc_gateway->enabled === 'yes',
            'supports_future_payments' => false,
            'supports_partial_payments' => true
        ];
        
        // Configurazione specifica per PayPal Payments
        if (get_class($this->wc_gateway) === 'WC_Gateway_PPCP') {
            $config['api_credentials'] = [
                'merchant_id' => get_option('woocommerce_ppcp_merchant_id'),
                'client_id' => get_option('woocommerce_ppcp_client_id'),
                'client_secret' => get_option('woocommerce_ppcp_client_secret')
            ];
            $config['supports_future_payments'] = get_option('woocommerce_ppcp_reference_transactions') === 'yes';
        }
        
        return $config;
    }
    
    /**
     * Processa pagamento caparra
     */
    public function process_deposit_payment($result, $order) {
        // Modifica parametri PayPal per reference transaction
        add_filter('woocommerce_paypal_payments_purchase_unit_items', function($items, $order_obj) use ($order) {
            if ($order_obj->get_id() === $order->get_id()) {
                // Aggiungi informazioni caparra
                $items['custom_id'] = json_encode([
                    'payment_type' => 'deposit',
                    'preventivo_id' => $order->get_meta('_btr_preventivo_id'),
                    'balance_amount' => $order->get_meta('_btr_balance_amount')
                ]);
                
                // Abilita reference transaction se supportato
                if ($this->supports_future_payments()) {
                    $items['payment_options'] = [
                        'reference_transaction' => true
                    ];
                }
            }
            return $items;
        }, 10, 2);
        
        return $result;
    }
    
    /**
     * Gestisce webhook PayPal
     */
    public function handle_webhook() {
        // PayPal Payments plugin gestisce i webhook autonomamente
        // Verifichiamo solo gli eventi BTR
        $raw_post_data = file_get_contents('php://input');
        $notification = json_decode($raw_post_data, true);
        
        if (!$notification || !isset($notification['event_type'])) {
            wp_die('Notifica non valida', 'Errore', ['response' => 400]);
        }
        
        // Processa eventi BTR
        switch ($notification['event_type']) {
            case 'PAYMENT.CAPTURE.COMPLETED':
                $this->handle_payment_captured($notification);
                break;
            case 'PAYMENT.CAPTURE.DENIED':
                $this->handle_payment_denied($notification);
                break;
        }
        
        wp_die('OK', 'OK', ['response' => 200]);
    }
    
    /**
     * Gestisce pagamento catturato
     */
    private function handle_payment_captured($notification) {
        if (!isset($notification['resource']['custom_id'])) {
            return;
        }
        
        $custom = json_decode($notification['resource']['custom_id'], true);
        if (!$custom || !isset($custom['payment_type']) || $custom['payment_type'] !== 'deposit') {
            return;
        }
        
        // Trova ordine associato
        $orders = wc_get_orders([
            'meta_key' => '_transaction_id',
            'meta_value' => $notification['resource']['id'],
            'limit' => 1
        ]);
        
        if (!empty($orders)) {
            $order = $orders[0];
            $order->payment_complete($notification['resource']['id']);
            btr_debug_log("Pagamento PayPal completato per ordine #{$order->get_id()}");
        }
    }
    
    /**
     * Gestisce pagamento negato
     */
    private function handle_payment_denied($notification) {
        if (!isset($notification['resource']['custom_id'])) {
            return;
        }
        
        $custom = json_decode($notification['resource']['custom_id'], true);
        if (!$custom || !isset($custom['payment_type'])) {
            return;
        }
        
        // Trova ordine associato
        $orders = wc_get_orders([
            'meta_key' => '_transaction_id',
            'meta_value' => $notification['resource']['id'],
            'limit' => 1
        ]);
        
        if (!empty($orders)) {
            $order = $orders[0];
            $order->update_status('failed', __('Pagamento PayPal negato', 'born-to-ride-booking'));
            btr_debug_log("Pagamento PayPal negato per ordine #{$order->get_id()}");
        }
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
        // Solo con reference transactions abilitate
        if (get_class($this->wc_gateway) === 'WC_Gateway_PPCP') {
            return get_option('woocommerce_ppcp_reference_transactions') === 'yes';
        }
        return false;
    }
}

// Inizializza
add_action('plugins_loaded', function() {
    BTR_Payment_Gateway_Integration::get_instance();
}, 20);