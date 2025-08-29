<?php
/**
 * Admin per configurazione gateway pagamento
 * 
 * @package BornToRideBooking
 * @since 1.0.98
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Gateway_Settings_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Menu
        add_action('admin_menu', [$this, 'add_menu'], 30);
        
        // Settings
        add_action('admin_init', [$this, 'register_settings']);
        
        // AJAX
        add_action('wp_ajax_btr_test_gateway_webhook', [$this, 'ajax_test_webhook']);
    }
    
    /**
     * Aggiunge voce menu
     */
    public function add_menu() {
        add_submenu_page(
            'btr-booking',
            __('Gateway Pagamenti', 'born-to-ride-booking'),
            __('Gateway Pagamenti', 'born-to-ride-booking'),
            'manage_options',
            'btr-gateway-settings',
            [$this, 'render_page']
        );
    }
    
    /**
     * Registra settings
     */
    public function register_settings() {
        // Stripe settings
        register_setting('btr_payment_gateway_settings', 'btr_stripe_webhook_secret');
        register_setting('btr_payment_gateway_settings', 'btr_stripe_save_payment_method');
        
        // PayPal settings (solo per opzioni BTR specifiche)
        register_setting('btr_payment_gateway_settings', 'btr_paypal_reference_transactions');
        register_setting('btr_payment_gateway_settings', 'btr_paypal_ipn_url');
        
        // General settings
        register_setting('btr_payment_gateway_settings', 'btr_gateway_debug_logging');
        register_setting('btr_payment_gateway_settings', 'btr_deposit_percentage_default');
        // BTR global payment flow settings
        register_setting('btr_payment_gateway_settings', 'btr_enable_group_split');
        register_setting('btr_payment_gateway_settings', 'btr_group_split_threshold');
        register_setting('btr_payment_gateway_settings', 'btr_default_payment_mode');
        
        // Email settings
        register_setting('btr_payment_gateway_settings', 'btr_gateway_deposit_paid_email');
        register_setting('btr_payment_gateway_settings', 'btr_gateway_balance_reminder_email');
        register_setting('btr_payment_gateway_settings', 'btr_gateway_fully_paid_email');
    }
    
    /**
     * Render pagina
     */
    public function render_page() {
        // Usa la nuova vista ottimizzata
        include BTR_PLUGIN_DIR . 'admin/views/payment-gateway-settings-v2.php';
    }
    
    /**
     * AJAX test webhook
     */
    public function ajax_test_webhook() {
        check_ajax_referer('btr_test_gateway', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Non autorizzato');
        }
        
        $gateway = sanitize_text_field($_POST['gateway']);
        
        // Simula test webhook
        $test_data = [];
        
        if ($gateway === 'stripe') {
            $test_data = [
                'type' => 'payment_intent.succeeded',
                'data' => [
                    'object' => [
                        'id' => 'pi_test_' . uniqid(),
                        'amount' => 10000,
                        'currency' => 'eur',
                        'metadata' => [
                            'order_id' => '12345',
                            'payment_type' => 'deposit'
                        ]
                    ]
                ]
            ];
            
            $message = "Test webhook Stripe eseguito:\n";
            $message .= "Event Type: payment_intent.succeeded\n";
            $message .= "Payment Intent: " . $test_data['data']['object']['id'] . "\n";
            $message .= "Amount: €" . number_format($test_data['data']['object']['amount'] / 100, 2) . "\n";
            
            if (!get_option('btr_stripe_webhook_secret')) {
                $message .= "\nATTENZIONE: Webhook secret non configurato!";
            }
            
        } else {
            $test_data = [
                'payment_status' => 'Completed',
                'payment_gross' => '100.00',
                'payment_fee' => '3.50',
                'mc_currency' => 'EUR',
                'txn_id' => 'TEST' . uniqid(),
                'custom' => json_encode([
                    'order_id' => '12345',
                    'payment_type' => 'deposit'
                ])
            ];
            
            $message = "Test IPN PayPal eseguito:\n";
            $message .= "Transaction ID: " . $test_data['txn_id'] . "\n";
            $message .= "Payment Status: " . $test_data['payment_status'] . "\n";
            $message .= "Amount: €" . $test_data['payment_gross'] . "\n";
        }
        
        // Log test
        if (get_option('btr_gateway_debug_logging')) {
            btr_debug_log("Gateway test webhook ($gateway): " . print_r($test_data, true));
        }
        
        wp_send_json_success([
            'message' => $message,
            'test_data' => $test_data
        ]);
    }
}

// Inizializza
new BTR_Gateway_Settings_Admin();
