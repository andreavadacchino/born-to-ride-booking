<?php
/**
 * Gestione AJAX per test e diagnostica gateway di pagamento
 * 
 * @package BornToRideBooking
 * @since 1.0.98
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Payment_Gateway_Ajax {
    
    /**
     * Constructor
     */
    public function __construct() {
        // AJAX handlers per admin
        add_action('wp_ajax_btr_test_gateway_connection', [$this, 'test_gateway_connection']);
        add_action('wp_ajax_btr_check_gateway_status', [$this, 'check_gateway_status']);
        add_action('wp_ajax_btr_test_gateway_webhook', [$this, 'test_gateway_webhook']);
    }
    
    /**
     * Test connessione gateway
     */
    public function test_gateway_connection() {
        // Verifica permessi e nonce
        if (!current_user_can('manage_options') || !check_ajax_referer('btr_test_gateway', 'nonce', false)) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $gateway_id = sanitize_text_field($_POST['gateway'] ?? '');
        if (!in_array($gateway_id, ['stripe', 'paypal'])) {
            wp_send_json_error('Gateway non valido');
        }
        
        $gateway_integration = BTR_Payment_Gateway_Integration::get_instance();
        $gateway = $gateway_integration->get_gateway($gateway_id);
        
        if (!$gateway) {
            wp_send_json_error('Gateway non configurato');
        }
        
        $config = $gateway->get_configuration();
        $result = [
            'gateway' => $gateway_id,
            'connected' => false,
            'testmode' => false,
            'details' => [],
            'errors' => []
        ];
        
        if ($gateway_id === 'stripe') {
            $result = $this->test_stripe_connection($config);
        } elseif ($gateway_id === 'paypal') {
            $result = $this->test_paypal_connection($config);
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Test connessione Stripe
     */
    private function test_stripe_connection($config) {
        $result = [
            'gateway' => 'stripe',
            'connected' => false,
            'testmode' => $config['testmode'] ?? false,
            'details' => [],
            'errors' => []
        ];
        
        if (!$config['enabled']) {
            $result['errors'][] = 'Gateway Stripe non abilitato in WooCommerce';
            return $result;
        }
        
        // Test API key
        if (empty($config['secret_key'])) {
            $result['errors'][] = 'Secret key non configurata';
            return $result;
        }
        
        // Prova a fare una chiamata API semplice
        if (class_exists('WC_Stripe_API')) {
            try {
                // Imposta temporaneamente la key per il test
                $original_key = WC_Stripe_API::get_secret_key();
                WC_Stripe_API::set_secret_key($config['secret_key']);
                
                // Test chiamata - recupera informazioni account
                $response = WC_Stripe_API::request([], 'account', 'GET');
                
                if (!is_wp_error($response)) {
                    $result['connected'] = true;
                    $result['details'] = [
                        'account_id' => $response->id ?? 'N/A',
                        'business_name' => $response->business_profile->name ?? 'N/A',
                        'country' => $response->country ?? 'N/A',
                        'capabilities' => [
                            'card_payments' => $response->capabilities->card_payments ?? 'inactive',
                            'transfers' => $response->capabilities->transfers ?? 'inactive'
                        ]
                    ];
                } else {
                    $result['errors'][] = 'Errore API: ' . $response->get_error_message();
                }
                
                // Ripristina key originale
                WC_Stripe_API::set_secret_key($original_key);
                
            } catch (Exception $e) {
                $result['errors'][] = 'Eccezione: ' . $e->getMessage();
            }
        } else {
            $result['errors'][] = 'Classe WC_Stripe_API non disponibile';
        }
        
        // Verifica webhook
        if ($result['connected'] && !empty($config['webhook_secret'])) {
            $result['details']['webhook_configured'] = true;
        } else {
            $result['details']['webhook_configured'] = false;
        }
        
        return $result;
    }
    
    /**
     * Test connessione PayPal
     */
    private function test_paypal_connection($config) {
        $result = [
            'gateway' => 'paypal',
            'connected' => false,
            'testmode' => false,
            'details' => [],
            'errors' => []
        ];
        
        if (!$config['enabled']) {
            $result['errors'][] = 'Gateway PayPal non abilitato in WooCommerce';
            return $result;
        }
        
        // Verifica tipo di gateway PayPal
        if (class_exists('WC_Gateway_PPCP')) {
            // PayPal Payments (nuovo)
            $result['details']['gateway_type'] = 'PayPal Payments';
            
            if (!empty($config['api_credentials']['merchant_id'])) {
                $result['connected'] = true;
                $result['details']['merchant_id'] = substr($config['api_credentials']['merchant_id'], 0, 10) . '...';
                $result['details']['reference_transactions'] = $config['supports_future_payments'] ? 'Abilitato' : 'Non abilitato';
            } else {
                $result['errors'][] = 'Merchant ID non configurato';
            }
            
        } elseif (class_exists('WC_Gateway_PayPal')) {
            // PayPal Standard (legacy)
            $result['details']['gateway_type'] = 'PayPal Standard';
            $result['connected'] = true; // PayPal Standard non richiede API
            $result['details']['note'] = 'PayPal Standard non supporta funzionalità avanzate come Reference Transactions';
        }
        
        return $result;
    }
    
    /**
     * Verifica stato completo del sistema gateway
     */
    public function check_gateway_status() {
        // Verifica permessi e nonce
        if (!current_user_can('manage_options') || !check_ajax_referer('btr_test_gateway', 'nonce', false)) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $gateway_integration = BTR_Payment_Gateway_Integration::get_instance();
        
        $status = [
            'plugins' => [
                'stripe' => class_exists('WC_Gateway_Stripe'),
                'paypal' => class_exists('WC_Gateway_PPCP') || class_exists('WC_Gateway_PayPal')
            ],
            'gateways' => [],
            'settings' => [
                'deposit_percentage' => get_option('btr_deposit_percentage_default', 30),
                'deposit_email' => get_option('btr_gateway_deposit_paid_email', '1') === '1',
                'reminder_email' => get_option('btr_gateway_balance_reminder_email', '1') === '1',
                'complete_email' => get_option('btr_gateway_fully_paid_email', '1') === '1',
                'debug_logging' => get_option('btr_gateway_debug_logging') === '1'
            ],
            'recommendations' => []
        ];
        
        // Verifica configurazione Stripe
        if ($gateway_integration->is_gateway_available('stripe')) {
            $stripe_config = $gateway_integration->get_gateway_config('stripe');
            $status['gateways']['stripe'] = [
                'enabled' => $stripe_config['enabled'],
                'api_configured' => !empty($stripe_config['secret_key']),
                'testmode' => $stripe_config['testmode'],
                'webhook_configured' => !empty($stripe_config['webhook_secret']),
                'supports_future_payments' => true
            ];
        } elseif (class_exists('WC_Gateway_Stripe')) {
            $status['gateways']['stripe'] = [
                'enabled' => false,
                'api_configured' => false
            ];
            $status['recommendations'][] = 'Stripe è installato ma non configurato. Abilitalo nelle impostazioni WooCommerce.';
        }
        
        // Verifica configurazione PayPal
        if ($gateway_integration->is_gateway_available('paypal')) {
            $paypal_config = $gateway_integration->get_gateway_config('paypal');
            $status['gateways']['paypal'] = [
                'enabled' => $paypal_config['enabled'],
                'api_configured' => true, // PayPal è sempre configurato se abilitato
                'supports_future_payments' => $paypal_config['supports_future_payments']
            ];
            
            if (!$paypal_config['supports_future_payments']) {
                $status['recommendations'][] = 'PayPal non supporta Reference Transactions. Contatta PayPal per abilitarle.';
            }
        } elseif (class_exists('WC_Gateway_PPCP') || class_exists('WC_Gateway_PayPal')) {
            $status['gateways']['paypal'] = [
                'enabled' => false,
                'api_configured' => false
            ];
            $status['recommendations'][] = 'PayPal è installato ma non configurato. Abilitalo nelle impostazioni WooCommerce.';
        }
        
        // Raccomandazioni generali
        if (!$status['plugins']['stripe'] && !$status['plugins']['paypal']) {
            $status['recommendations'][] = 'Nessun gateway di pagamento installato. Installa almeno Stripe o PayPal.';
        }
        
        if (empty($status['gateways'])) {
            $status['recommendations'][] = 'Nessun gateway configurato. Il sistema di pagamenti BTR non funzionerà.';
        }
        
        // Verifica webhook URL
        $webhook_urls = [
            'stripe' => home_url('/wc-api/btr_payment_webhook?gateway=stripe'),
            'paypal' => home_url('/wc-api/btr_payment_webhook?gateway=paypal')
        ];
        
        foreach ($webhook_urls as $gateway => $url) {
            if (isset($status['gateways'][$gateway]) && $status['gateways'][$gateway]['enabled']) {
                $status['gateways'][$gateway]['webhook_url'] = $url;
            }
        }
        
        wp_send_json_success($status);
    }
    
    /**
     * Test webhook gateway
     */
    public function test_gateway_webhook() {
        // Verifica permessi e nonce
        if (!current_user_can('manage_options') || !check_ajax_referer('btr_test_gateway', 'nonce', false)) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        $gateway_id = sanitize_text_field($_POST['gateway'] ?? '');
        if (!in_array($gateway_id, ['stripe', 'paypal'])) {
            wp_send_json_error('Gateway non valido');
        }
        
        // Simula webhook test
        $test_data = [];
        if ($gateway_id === 'stripe') {
            $test_data = [
                'type' => 'payment_intent.succeeded',
                'data' => [
                    'object' => [
                        'id' => 'pi_test_' . uniqid(),
                        'amount' => 10000,
                        'currency' => 'eur',
                        'metadata' => [
                            'btr_payment' => true,
                            'payment_type' => 'test',
                            'order_id' => 'test_order'
                        ]
                    ]
                ]
            ];
        } else {
            $test_data = [
                'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
                'resource' => [
                    'id' => 'test_' . uniqid(),
                    'amount' => [
                        'value' => '100.00',
                        'currency_code' => 'EUR'
                    ],
                    'custom_id' => json_encode([
                        'payment_type' => 'test',
                        'order_id' => 'test_order'
                    ])
                ]
            ];
        }
        
        // Log test webhook
        btr_debug_log("[BTR Gateway Test] Webhook test per $gateway_id: " . print_r($test_data, true));
        
        wp_send_json_success([
            'message' => sprintf(
                'Test webhook %s completato. Controlla il log per i dettagli.',
                strtoupper($gateway_id)
            ),
            'webhook_url' => home_url('/wc-api/btr_payment_webhook?gateway=' . $gateway_id),
            'test_data' => $test_data
        ]);
    }
}

// Inizializza
new BTR_Payment_Gateway_Ajax();