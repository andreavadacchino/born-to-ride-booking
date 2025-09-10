<?php
/**
 * Direct Gateway API Manager for REST API Integration
 * 
 * Handles direct integration with Stripe and PayPal APIs for individual payments
 * This is separate from the WooCommerce gateway integration to support REST API flows
 * 
 * @package BornToRideBooking
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Gateway_API_Manager {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Available gateways
     */
    private $gateways = [];
    
    /**
     * Configuration cache
     */
    private $config_cache = [];
    
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
        $this->initialize_gateways();
    }
    
    /**
     * Initialize available gateways
     */
    private function initialize_gateways() {
        // Get WooCommerce available gateways
        $wc_gateways = WC()->payment_gateways()->get_available_payment_gateways();
        
        foreach ($wc_gateways as $gateway_id => $gateway) {
            if ($gateway->enabled === 'yes') {
                $this->gateways[$gateway_id] = [
                    'id' => $gateway_id,
                    'title' => $gateway->get_title(),
                    'description' => $gateway->get_description(),
                    'supports' => $gateway->supports,
                    'api_handler' => $this->get_api_handler($gateway_id)
                ];
            }
        }
    }
    
    /**
     * Get API handler for gateway
     */
    private function get_api_handler($gateway_id) {
        switch ($gateway_id) {
            case 'stripe':
                return new BTR_Stripe_API_Handler();
            case 'ppcp-gateway':
            case 'paypal':
                return new BTR_PayPal_API_Handler();
            default:
                return new BTR_Generic_Gateway_Handler($gateway_id);
        }
    }
    
    /**
     * Create payment intent for individual payment
     */
    public function create_payment_intent($payment_data, $gateway_id, $gateway_data = []) {
        if (!isset($this->gateways[$gateway_id])) {
            return new WP_Error(
                'gateway_not_available',
                __('Payment gateway not available.', 'born-to-ride-booking')
            );
        }
        
        $handler = $this->gateways[$gateway_id]['api_handler'];
        
        if (!$handler) {
            return new WP_Error(
                'handler_not_available',
                __('Gateway handler not available.', 'born-to-ride-booking')
            );
        }
        
        try {
            return $handler->create_payment_intent($payment_data, $gateway_data);
        } catch (Exception $e) {
            return new WP_Error(
                'payment_intent_failed',
                $e->getMessage()
            );
        }
    }
    
    /**
     * Confirm payment intent
     */
    public function confirm_payment_intent($payment_intent_id, $gateway_id, $gateway_data = []) {
        if (!isset($this->gateways[$gateway_id])) {
            return new WP_Error(
                'gateway_not_available',
                __('Payment gateway not available.', 'born-to-ride-booking')
            );
        }
        
        $handler = $this->gateways[$gateway_id]['api_handler'];
        
        try {
            return $handler->confirm_payment_intent($payment_intent_id, $gateway_data);
        } catch (Exception $e) {
            return new WP_Error(
                'payment_confirmation_failed',
                $e->getMessage()
            );
        }
    }
    
    /**
     * Get payment status
     */
    public function get_payment_status($payment_intent_id, $gateway_id) {
        if (!isset($this->gateways[$gateway_id])) {
            return new WP_Error(
                'gateway_not_available',
                __('Payment gateway not available.', 'born-to-ride-booking')
            );
        }
        
        $handler = $this->gateways[$gateway_id]['api_handler'];
        
        try {
            return $handler->get_payment_status($payment_intent_id);
        } catch (Exception $e) {
            return new WP_Error(
                'status_check_failed',
                $e->getMessage()
            );
        }
    }
    
    /**
     * Process webhook event
     */
    public function process_webhook_event($gateway_id, $event_data) {
        if (!isset($this->gateways[$gateway_id])) {
            return new WP_Error(
                'gateway_not_available',
                __('Payment gateway not available.', 'born-to-ride-booking')
            );
        }
        
        $handler = $this->gateways[$gateway_id]['api_handler'];
        
        try {
            return $handler->process_webhook_event($event_data);
        } catch (Exception $e) {
            return new WP_Error(
                'webhook_processing_failed',
                $e->getMessage()
            );
        }
    }
    
    /**
     * Get available gateways
     */
    public function get_available_gateways() {
        return $this->gateways;
    }
    
    /**
     * Check if gateway supports feature
     */
    public function gateway_supports($gateway_id, $feature) {
        if (!isset($this->gateways[$gateway_id])) {
            return false;
        }
        
        return in_array($feature, $this->gateways[$gateway_id]['supports']);
    }
}

/**
 * Abstract base class for gateway API handlers
 */
abstract class BTR_Gateway_API_Handler_Base {
    
    /**
     * Gateway configuration
     */
    protected $config = [];
    
    /**
     * Gateway ID
     */
    protected $gateway_id;
    
    /**
     * Constructor
     */
    public function __construct($gateway_id = '') {
        $this->gateway_id = $gateway_id;
        $this->load_configuration();
    }
    
    /**
     * Load gateway configuration
     */
    abstract protected function load_configuration();
    
    /**
     * Create payment intent
     */
    abstract public function create_payment_intent($payment_data, $gateway_data = []);
    
    /**
     * Confirm payment intent
     */
    abstract public function confirm_payment_intent($payment_intent_id, $gateway_data = []);
    
    /**
     * Get payment status
     */
    abstract public function get_payment_status($payment_intent_id);
    
    /**
     * Process webhook event
     */
    abstract public function process_webhook_event($event_data);
    
    /**
     * Validate configuration
     */
    protected function validate_configuration() {
        return !empty($this->config) && $this->is_properly_configured();
    }
    
    /**
     * Check if gateway is properly configured
     */
    abstract protected function is_properly_configured();
}

/**
 * Stripe API Handler
 */
class BTR_Stripe_API_Handler extends BTR_Gateway_API_Handler_Base {
    
    /**
     * Stripe API client
     */
    private $stripe_client;
    
    /**
     * Load Stripe configuration
     */
    protected function load_configuration() {
        // Get Stripe configuration from WooCommerce gateway
        $stripe_gateway = WC()->payment_gateways()->get_available_payment_gateways()['stripe'] ?? null;
        
        if ($stripe_gateway && $stripe_gateway->enabled === 'yes') {
            $this->config = [
                'publishable_key' => $stripe_gateway->get_option('publishable_key'),
                'secret_key' => $stripe_gateway->get_option('secret_key'),
                'testmode' => $stripe_gateway->get_option('testmode') === 'yes',
                'webhook_secret' => $stripe_gateway->get_option('webhook_secret')
            ];
            
            // Initialize Stripe client if available
            $this->initialize_stripe_client();
        }
    }
    
    /**
     * Initialize Stripe client
     */
    private function initialize_stripe_client() {
        // Check if Stripe library is available
        if (class_exists('\Stripe\Stripe')) {
            \Stripe\Stripe::setApiKey($this->config['secret_key']);
            $this->stripe_client = new \Stripe\StripeClient($this->config['secret_key']);
        }
    }
    
    /**
     * Check if properly configured
     */
    protected function is_properly_configured() {
        return !empty($this->config['secret_key']) && !empty($this->config['publishable_key']);
    }
    
    /**
     * Create payment intent
     */
    public function create_payment_intent($payment_data, $gateway_data = []) {
        if (!$this->validate_configuration()) {
            throw new Exception('Stripe not properly configured');
        }
        
        if (!$this->stripe_client) {
            throw new Exception('Stripe client not available');
        }
        
        try {
            $intent_data = [
                'amount' => round($payment_data->amount * 100), // Convert to cents
                'currency' => strtolower($payment_data->currency),
                'metadata' => [
                    'payment_hash' => $payment_data->payment_hash,
                    'participant_name' => $payment_data->participant_name,
                    'participant_email' => $payment_data->participant_email,
                    'order_id' => $payment_data->order_id,
                    'payment_type' => $payment_data->payment_type,
                    'btr_payment_id' => $payment_data->payment_id,
                    'source' => 'btr_individual_payment'
                ],
                'description' => sprintf(
                    __('Payment for %s - %s', 'born-to-ride-booking'),
                    $payment_data->participant_name,
                    get_the_title(get_post_meta($payment_data->order_id, '_btr_preventivo_id', true))
                )
            ];
            
            // Add confirmation method if not provided
            if (!isset($gateway_data['confirmation_method'])) {
                $intent_data['confirmation_method'] = 'manual';
                $intent_data['confirm'] = false;
            }
            
            // Add payment method if provided
            if (!empty($gateway_data['payment_method'])) {
                $intent_data['payment_method'] = $gateway_data['payment_method'];
            }
            
            // Create payment intent
            $intent = $this->stripe_client->paymentIntents->create($intent_data);
            
            return [
                'success' => true,
                'payment_intent_id' => $intent->id,
                'client_secret' => $intent->client_secret,
                'status' => $intent->status,
                'amount' => $intent->amount / 100,
                'currency' => $intent->currency,
                'requires_action' => $intent->status === 'requires_action',
                'next_action' => $intent->next_action
            ];
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            throw new Exception('Stripe API Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Confirm payment intent
     */
    public function confirm_payment_intent($payment_intent_id, $gateway_data = []) {
        if (!$this->validate_configuration()) {
            throw new Exception('Stripe not properly configured');
        }
        
        if (!$this->stripe_client) {
            throw new Exception('Stripe client not available');
        }
        
        try {
            $confirm_data = [];
            
            // Add payment method if provided
            if (!empty($gateway_data['payment_method'])) {
                $confirm_data['payment_method'] = $gateway_data['payment_method'];
            }
            
            // Add return URL for 3D Secure
            if (!empty($gateway_data['return_url'])) {
                $confirm_data['return_url'] = $gateway_data['return_url'];
            }
            
            $intent = $this->stripe_client->paymentIntents->confirm($payment_intent_id, $confirm_data);
            
            return [
                'success' => true,
                'payment_intent_id' => $intent->id,
                'status' => $intent->status,
                'requires_action' => $intent->status === 'requires_action',
                'next_action' => $intent->next_action,
                'transaction_id' => $intent->charges->data[0]->id ?? null
            ];
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            throw new Exception('Stripe Confirmation Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get payment status
     */
    public function get_payment_status($payment_intent_id) {
        if (!$this->validate_configuration()) {
            throw new Exception('Stripe not properly configured');
        }
        
        if (!$this->stripe_client) {
            throw new Exception('Stripe client not available');
        }
        
        try {
            $intent = $this->stripe_client->paymentIntents->retrieve($payment_intent_id);
            
            return [
                'payment_intent_id' => $intent->id,
                'status' => $intent->status,
                'amount' => $intent->amount / 100,
                'currency' => $intent->currency,
                'paid' => $intent->status === 'succeeded',
                'transaction_id' => $intent->charges->data[0]->id ?? null,
                'failure_reason' => $intent->last_payment_error->message ?? null
            ];
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            throw new Exception('Stripe Status Check Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Process webhook event
     */
    public function process_webhook_event($event_data) {
        // Verify webhook signature
        if (!$this->verify_webhook_signature($event_data)) {
            throw new Exception('Invalid webhook signature');
        }
        
        $event = $event_data['event'];
        
        switch ($event['type']) {
            case 'payment_intent.succeeded':
                return $this->handle_payment_succeeded($event['data']['object']);
                
            case 'payment_intent.payment_failed':
                return $this->handle_payment_failed($event['data']['object']);
                
            case 'payment_intent.requires_action':
                return $this->handle_requires_action($event['data']['object']);
                
            default:
                return ['processed' => false, 'message' => 'Event type not handled'];
        }
    }
    
    /**
     * Verify webhook signature
     */
    private function verify_webhook_signature($event_data) {
        if (empty($this->config['webhook_secret'])) {
            return true; // Skip verification if no secret configured
        }
        
        $payload = $event_data['payload'] ?? '';
        $signature = $event_data['signature'] ?? '';
        
        try {
            \Stripe\Webhook::constructEvent($payload, $signature, $this->config['webhook_secret']);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Handle payment succeeded webhook
     */
    private function handle_payment_succeeded($payment_intent) {
        $payment_hash = $payment_intent['metadata']['payment_hash'] ?? '';
        
        if ($payment_hash) {
            // Update payment status in database
            $db_manager = BTR_Database_Manager::get_instance();
            $payment_data = $db_manager->get_payment_by_hash($payment_hash);
            
            if ($payment_data) {
                $db_manager->update_payment_status($payment_data->payment_id, 'completed', [
                    'transaction_id' => $payment_intent['charges']['data'][0]['id'] ?? '',
                    'paid_at' => current_time('mysql'),
                    'gateway_response' => json_encode($payment_intent)
                ]);
                
                // Trigger completion actions
                do_action('btr_stripe_payment_completed', $payment_data, $payment_intent);
            }
        }
        
        return ['processed' => true, 'message' => 'Payment completed'];
    }
    
    /**
     * Handle payment failed webhook
     */
    private function handle_payment_failed($payment_intent) {
        $payment_hash = $payment_intent['metadata']['payment_hash'] ?? '';
        
        if ($payment_hash) {
            $db_manager = BTR_Database_Manager::get_instance();
            $payment_data = $db_manager->get_payment_by_hash($payment_hash);
            
            if ($payment_data) {
                $db_manager->update_payment_status($payment_data->payment_id, 'failed', [
                    'failure_reason' => $payment_intent['last_payment_error']['message'] ?? '',
                    'failed_at' => current_time('mysql'),
                    'gateway_response' => json_encode($payment_intent)
                ]);
                
                // Trigger failure actions
                do_action('btr_stripe_payment_failed', $payment_data, $payment_intent);
            }
        }
        
        return ['processed' => true, 'message' => 'Payment failure processed'];
    }
    
    /**
     * Handle requires action webhook
     */
    private function handle_requires_action($payment_intent) {
        // This is typically for 3D Secure authentication
        return ['processed' => true, 'message' => 'Payment requires action'];
    }
}

/**
 * PayPal API Handler
 */
class BTR_PayPal_API_Handler extends BTR_Gateway_API_Handler_Base {
    
    /**
     * PayPal API client
     */
    private $paypal_client;
    
    /**
     * Load PayPal configuration
     */
    protected function load_configuration() {
        // Get PayPal configuration from WooCommerce gateway
        $paypal_gateway = WC()->payment_gateways()->get_available_payment_gateways()['ppcp-gateway'] ?? 
                         WC()->payment_gateways()->get_available_payment_gateways()['paypal'] ?? null;
        
        if ($paypal_gateway && $paypal_gateway->enabled === 'yes') {
            $this->config = [
                'client_id' => $paypal_gateway->get_option('client_id'),
                'client_secret' => $paypal_gateway->get_option('client_secret'),
                'sandbox' => $paypal_gateway->get_option('sandbox') === 'yes',
                'webhook_id' => $paypal_gateway->get_option('webhook_id')
            ];
        }
    }
    
    /**
     * Check if properly configured
     */
    protected function is_properly_configured() {
        return !empty($this->config['client_id']) && !empty($this->config['client_secret']);
    }
    
    /**
     * Create payment intent (PayPal Order)
     */
    public function create_payment_intent($payment_data, $gateway_data = []) {
        if (!$this->validate_configuration()) {
            throw new Exception('PayPal not properly configured');
        }
        
        // Create PayPal order
        $order_data = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => strtoupper($payment_data->currency),
                        'value' => number_format($payment_data->amount, 2, '.', '')
                    ],
                    'description' => sprintf(
                        __('Payment for %s', 'born-to-ride-booking'),
                        $payment_data->participant_name
                    ),
                    'custom_id' => json_encode([
                        'payment_hash' => $payment_data->payment_hash,
                        'payment_id' => $payment_data->payment_id,
                        'order_id' => $payment_data->order_id
                    ])
                ]
            ],
            'application_context' => [
                'return_url' => $gateway_data['return_url'] ?? home_url('/payment-success/'),
                'cancel_url' => $gateway_data['cancel_url'] ?? home_url('/payment-cancelled/')
            ]
        ];
        
        // Make API call to PayPal
        $response = $this->make_paypal_api_call('POST', '/v2/checkout/orders', $order_data);
        
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }
        
        return [
            'success' => true,
            'payment_intent_id' => $response['id'],
            'approval_url' => $this->get_approval_url($response['links']),
            'status' => $response['status'],
            'amount' => $payment_data->amount,
            'currency' => $payment_data->currency
        ];
    }
    
    /**
     * Confirm payment intent (Capture PayPal Order)
     */
    public function confirm_payment_intent($payment_intent_id, $gateway_data = []) {
        if (!$this->validate_configuration()) {
            throw new Exception('PayPal not properly configured');
        }
        
        // Capture PayPal order
        $response = $this->make_paypal_api_call('POST', "/v2/checkout/orders/{$payment_intent_id}/capture");
        
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }
        
        $capture = $response['purchase_units'][0]['payments']['captures'][0] ?? null;
        
        return [
            'success' => true,
            'payment_intent_id' => $payment_intent_id,
            'status' => $response['status'],
            'transaction_id' => $capture['id'] ?? null,
            'capture_id' => $capture['id'] ?? null
        ];
    }
    
    /**
     * Get payment status
     */
    public function get_payment_status($payment_intent_id) {
        if (!$this->validate_configuration()) {
            throw new Exception('PayPal not properly configured');
        }
        
        $response = $this->make_paypal_api_call('GET', "/v2/checkout/orders/{$payment_intent_id}");
        
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }
        
        $capture = $response['purchase_units'][0]['payments']['captures'][0] ?? null;
        
        return [
            'payment_intent_id' => $payment_intent_id,
            'status' => $response['status'],
            'paid' => $response['status'] === 'COMPLETED',
            'transaction_id' => $capture['id'] ?? null,
            'amount' => $response['purchase_units'][0]['amount']['value'] ?? 0,
            'currency' => $response['purchase_units'][0]['amount']['currency_code'] ?? ''
        ];
    }
    
    /**
     * Process webhook event
     */
    public function process_webhook_event($event_data) {
        $event = $event_data['event'];
        
        switch ($event['event_type']) {
            case 'CHECKOUT.ORDER.COMPLETED':
                return $this->handle_order_completed($event['resource']);
                
            case 'PAYMENT.CAPTURE.COMPLETED':
                return $this->handle_payment_captured($event['resource']);
                
            case 'PAYMENT.CAPTURE.DENIED':
                return $this->handle_payment_denied($event['resource']);
                
            default:
                return ['processed' => false, 'message' => 'Event type not handled'];
        }
    }
    
    /**
     * Make PayPal API call
     */
    private function make_paypal_api_call($method, $endpoint, $data = null) {
        $base_url = $this->config['sandbox'] ? 
            'https://api-m.sandbox.paypal.com' : 
            'https://api-m.paypal.com';
        
        // Get access token
        $access_token = $this->get_access_token();
        if (is_wp_error($access_token)) {
            return $access_token;
        }
        
        $args = [
            'method' => $method,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $access_token,
                'PayPal-Request-Id' => wp_generate_uuid4()
            ],
            'timeout' => 30
        ];
        
        if ($data) {
            $args['body'] = json_encode($data);
        }
        
        $response = wp_remote_request($base_url . $endpoint, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);
        
        if ($code >= 400) {
            return new WP_Error('paypal_api_error', 'PayPal API Error: ' . $body);
        }
        
        return json_decode($body, true);
    }
    
    /**
     * Get PayPal access token
     */
    private function get_access_token() {
        $base_url = $this->config['sandbox'] ? 
            'https://api-m.sandbox.paypal.com' : 
            'https://api-m.paypal.com';
        
        $args = [
            'method' => 'POST',
            'headers' => [
                'Accept' => 'application/json',
                'Accept-Language' => 'en_US',
                'Authorization' => 'Basic ' . base64_encode($this->config['client_id'] . ':' . $this->config['client_secret'])
            ],
            'body' => 'grant_type=client_credentials'
        ];
        
        $response = wp_remote_post($base_url . '/v1/oauth2/token', $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        return $body['access_token'] ?? new WP_Error('no_access_token', 'No access token received');
    }
    
    /**
     * Get approval URL from PayPal links
     */
    private function get_approval_url($links) {
        foreach ($links as $link) {
            if ($link['rel'] === 'approve') {
                return $link['href'];
            }
        }
        return null;
    }
    
    /**
     * Handle order completed webhook
     */
    private function handle_order_completed($resource) {
        $custom_data = json_decode($resource['purchase_units'][0]['custom_id'] ?? '{}', true);
        $payment_hash = $custom_data['payment_hash'] ?? '';
        
        if ($payment_hash) {
            $db_manager = BTR_Database_Manager::get_instance();
            $payment_data = $db_manager->get_payment_by_hash($payment_hash);
            
            if ($payment_data) {
                $db_manager->update_payment_status($payment_data->payment_id, 'completed', [
                    'transaction_id' => $resource['id'],
                    'paid_at' => current_time('mysql'),
                    'gateway_response' => json_encode($resource)
                ]);
                
                do_action('btr_paypal_payment_completed', $payment_data, $resource);
            }
        }
        
        return ['processed' => true, 'message' => 'Order completed'];
    }
    
    /**
     * Handle payment captured webhook
     */
    private function handle_payment_captured($resource) {
        // Similar to order completed
        return $this->handle_order_completed($resource);
    }
    
    /**
     * Handle payment denied webhook
     */
    private function handle_payment_denied($resource) {
        $custom_data = json_decode($resource['custom_id'] ?? '{}', true);
        $payment_hash = $custom_data['payment_hash'] ?? '';
        
        if ($payment_hash) {
            $db_manager = BTR_Database_Manager::get_instance();
            $payment_data = $db_manager->get_payment_by_hash($payment_hash);
            
            if ($payment_data) {
                $db_manager->update_payment_status($payment_data->payment_id, 'failed', [
                    'failure_reason' => 'Payment denied by PayPal',
                    'failed_at' => current_time('mysql'),
                    'gateway_response' => json_encode($resource)
                ]);
                
                do_action('btr_paypal_payment_failed', $payment_data, $resource);
            }
        }
        
        return ['processed' => true, 'message' => 'Payment denied'];
    }
}

/**
 * Generic Gateway Handler for unsupported gateways
 */
class BTR_Generic_Gateway_Handler extends BTR_Gateway_API_Handler_Base {
    
    /**
     * Load configuration
     */
    protected function load_configuration() {
        // Get basic gateway info
        $gateway = WC()->payment_gateways()->get_available_payment_gateways()[$this->gateway_id] ?? null;
        
        if ($gateway) {
            $this->config = [
                'id' => $this->gateway_id,
                'title' => $gateway->get_title(),
                'enabled' => $gateway->enabled === 'yes'
            ];
        }
    }
    
    /**
     * Check if properly configured
     */
    protected function is_properly_configured() {
        return !empty($this->config['enabled']);
    }
    
    /**
     * Create payment intent - fallback to WooCommerce processing
     */
    public function create_payment_intent($payment_data, $gateway_data = []) {
        throw new Exception('Direct API integration not available for this gateway. Use WooCommerce processing.');
    }
    
    /**
     * Confirm payment intent
     */
    public function confirm_payment_intent($payment_intent_id, $gateway_data = []) {
        throw new Exception('Direct API integration not available for this gateway.');
    }
    
    /**
     * Get payment status
     */
    public function get_payment_status($payment_intent_id) {
        throw new Exception('Direct API integration not available for this gateway.');
    }
    
    /**
     * Process webhook event
     */
    public function process_webhook_event($event_data) {
        return ['processed' => false, 'message' => 'Generic handler - no webhook processing'];
    }
}