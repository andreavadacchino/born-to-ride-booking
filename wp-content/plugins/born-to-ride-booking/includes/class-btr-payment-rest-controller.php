<?php
/**
 * REST API Controller for Individual Payments
 * 
 * Handles REST API endpoints for secure individual payment processing
 * 
 * @package BornToRideBooking
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Payment_REST_Controller extends WP_REST_Controller {
    
    /**
     * Namespace
     */
    protected $namespace = 'btr/v1';
    
    /**
     * Resource name
     */
    protected $rest_base = 'payments';
    
    /**
     * Instance of payment security class
     */
    private $security;
    
    /**
     * Instance of database manager
     */
    private $db_manager;
    
    /**
     * Instance of gateway API manager
     */
    private $gateway_manager;
    
    /**
     * Instance of webhook queue manager
     */
    private $webhook_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->security = new BTR_Payment_Security();
        $this->db_manager = BTR_Database_Manager::get_instance();
        $this->gateway_manager = BTR_Gateway_API_Manager::get_instance();
        $this->webhook_manager = new BTR_Webhook_Queue_Manager();
    }
    
    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Get payment details
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<payment_hash>[a-zA-Z0-9]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_payment_details'],
                'permission_callback' => [$this, 'payment_permissions_check'],
                'args' => $this->get_payment_args()
            ]
        ]);
        
        // Process individual payment
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<payment_hash>[a-zA-Z0-9]+)/process', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'process_payment'],
                'permission_callback' => [$this, 'payment_permissions_check'],
                'args' => $this->get_process_payment_args()
            ]
        ]);
        
        // Check payment status
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<payment_hash>[a-zA-Z0-9]+)/status', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_payment_status'],
                'permission_callback' => [$this, 'payment_permissions_check'],
                'args' => $this->get_payment_args()
            ]
        ]);
        
        // Update payment status (webhook endpoint)
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<payment_hash>[a-zA-Z0-9]+)/webhook', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'handle_payment_webhook'],
                'permission_callback' => [$this, 'webhook_permissions_check'],
                'args' => $this->get_webhook_args()
            ]
        ]);
        
        // Validate payment token (utility endpoint)
        register_rest_route($this->namespace, '/' . $this->rest_base . '/validate', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'validate_payment_token'],
                'permission_callback' => '__return_true', // Public endpoint
                'args' => [
                    'payment_hash' => [
                        'required' => true,
                        'type' => 'string',
                        'validate_callback' => [$this, 'validate_payment_hash']
                    ]
                ]
            ]
        ]);
    }
    
    /**
     * Get payment details
     */
    public function get_payment_details($request) {
        $payment_hash = sanitize_text_field($request['payment_hash']);
        
        // Rate limiting
        if (!$this->security->check_rate_limit('get_payment_details', $payment_hash, 60, 10)) {
            return new WP_Error(
                'rate_limit_exceeded',
                __('Too many requests. Please try again later.', 'born-to-ride-booking'),
                ['status' => 429]
            );
        }
        
        // Get payment data
        $payment_data = $this->db_manager->get_payment_by_hash($payment_hash);
        if (!$payment_data) {
            return new WP_Error(
                'payment_not_found',
                __('Payment not found.', 'born-to-ride-booking'),
                ['status' => 404]
            );
        }
        
        // Check if payment link is still valid
        if (!$this->is_payment_link_valid($payment_data)) {
            return new WP_Error(
                'payment_expired',
                __('Payment link has expired.', 'born-to-ride-booking'),
                ['status' => 410]
            );
        }
        
        // Get additional order details
        $order_id = $payment_data->order_id;
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return new WP_Error(
                'order_not_found',
                __('Associated order not found.', 'born-to-ride-booking'),
                ['status' => 404]
            );
        }
        
        // Prepare response data
        $response_data = [
            'payment_id' => $payment_data->payment_id,
            'payment_hash' => $payment_data->payment_hash,
            'participant_name' => $payment_data->participant_name,
            'participant_email' => $payment_data->participant_email,
            'amount' => floatval($payment_data->amount),
            'currency' => $payment_data->currency,
            'payment_type' => $payment_data->payment_type,
            'payment_status' => $payment_data->payment_status,
            'expires_at' => $payment_data->expires_at,
            'order' => [
                'id' => $order->get_id(),
                'total' => $order->get_total(),
                'currency' => $order->get_currency(),
                'status' => $order->get_status()
            ],
            'package_details' => $this->get_package_details($order),
            'payment_methods' => $this->get_available_payment_methods($payment_data),
            'security_nonce' => wp_create_nonce('btr_payment_process_' . $payment_hash)
        ];
        
        // Log access
        $this->security->log_security_event('payment_details_accessed', [
            'payment_hash' => $payment_hash,
            'ip_address' => $this->security->get_client_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        return rest_ensure_response($response_data);
    }
    
    /**
     * Process individual payment
     */
    public function process_payment($request) {
        $payment_hash = sanitize_text_field($request['payment_hash']);
        $payment_method = sanitize_text_field($request['payment_method']);
        $gateway_data = $request['gateway_data'] ?? [];
        
        // Rate limiting
        if (!$this->security->check_rate_limit('process_payment', $payment_hash, 300, 3)) {
            return new WP_Error(
                'rate_limit_exceeded',
                __('Too many payment attempts. Please wait before trying again.', 'born-to-ride-booking'),
                ['status' => 429]
            );
        }
        
        // Verify security nonce
        $nonce = $request['security_nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'btr_payment_process_' . $payment_hash)) {
            return new WP_Error(
                'invalid_nonce',
                __('Security check failed.', 'born-to-ride-booking'),
                ['status' => 403]
            );
        }
        
        // Get payment data
        $payment_data = $this->db_manager->get_payment_by_hash($payment_hash);
        if (!$payment_data) {
            return new WP_Error(
                'payment_not_found',
                __('Payment not found.', 'born-to-ride-booking'),
                ['status' => 404]
            );
        }
        
        // Validate payment status
        if ($payment_data->payment_status !== 'pending') {
            return new WP_Error(
                'payment_already_processed',
                __('This payment has already been processed.', 'born-to-ride-booking'),
                ['status' => 409]
            );
        }
        
        // Check expiry
        if (!$this->is_payment_link_valid($payment_data)) {
            return new WP_Error(
                'payment_expired',
                __('Payment link has expired.', 'born-to-ride-booking'),
                ['status' => 410]
            );
        }
        
        // Validate payment method
        $available_methods = $this->get_available_payment_methods($payment_data);
        if (!in_array($payment_method, array_keys($available_methods))) {
            return new WP_Error(
                'invalid_payment_method',
                __('Invalid payment method.', 'born-to-ride-booking'),
                ['status' => 400]
            );
        }
        
        // Start database transaction
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        
        try {
            // Update payment status to processing
            $this->db_manager->update_payment_status($payment_data->payment_id, 'processing', [
                'gateway_data' => $gateway_data,
                'processing_started_at' => current_time('mysql')
            ]);
            
            // Process payment based on method
            $result = $this->process_payment_by_method($payment_method, $payment_data, $gateway_data);
            
            if (is_wp_error($result)) {
                // Rollback on error
                $wpdb->query('ROLLBACK');
                
                // Update status back to pending
                $this->db_manager->update_payment_status($payment_data->payment_id, 'pending', [
                    'last_error' => $result->get_error_message(),
                    'last_attempt_at' => current_time('mysql')
                ]);
                
                return $result;
            }
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            // Log successful payment processing
            $this->security->log_security_event('payment_processed', [
                'payment_hash' => $payment_hash,
                'payment_method' => $payment_method,
                'amount' => $payment_data->amount,
                'ip_address' => $this->security->get_client_ip()
            ]);
            
            return rest_ensure_response([
                'success' => true,
                'message' => __('Payment processed successfully.', 'born-to-ride-booking'),
                'payment_status' => 'completed',
                'transaction_id' => $result['transaction_id'] ?? null,
                'redirect_url' => $result['redirect_url'] ?? null
            ]);
            
        } catch (Exception $e) {
            // Rollback on exception
            $wpdb->query('ROLLBACK');
            
            // Log error
            $this->security->log_security_event('payment_processing_error', [
                'payment_hash' => $payment_hash,
                'error' => $e->getMessage(),
                'ip_address' => $this->security->get_client_ip()
            ]);
            
            return new WP_Error(
                'payment_processing_error',
                __('Payment processing failed. Please try again.', 'born-to-ride-booking'),
                ['status' => 500]
            );
        }
    }
    
    /**
     * Get payment status
     */
    public function get_payment_status($request) {
        $payment_hash = sanitize_text_field($request['payment_hash']);
        
        // Rate limiting
        if (!$this->security->check_rate_limit('get_payment_status', $payment_hash, 60, 30)) {
            return new WP_Error(
                'rate_limit_exceeded',
                __('Too many requests. Please try again later.', 'born-to-ride-booking'),
                ['status' => 429]
            );
        }
        
        // Get payment data
        $payment_data = $this->db_manager->get_payment_by_hash($payment_hash);
        if (!$payment_data) {
            return new WP_Error(
                'payment_not_found',
                __('Payment not found.', 'born-to-ride-booking'),
                ['status' => 404]
            );
        }
        
        $response_data = [
            'payment_status' => $payment_data->payment_status,
            'amount' => floatval($payment_data->amount),
            'currency' => $payment_data->currency,
            'paid_at' => $payment_data->paid_at,
            'expires_at' => $payment_data->expires_at,
            'is_expired' => !$this->is_payment_link_valid($payment_data),
            'transaction_id' => $payment_data->transaction_id
        ];
        
        return rest_ensure_response($response_data);
    }
    
    /**
     * Handle payment webhook
     */
    public function handle_payment_webhook($request) {
        $payment_hash = sanitize_text_field($request['payment_hash']);
        $webhook_data = $request->get_json_params();
        
        // Verify webhook signature
        $signature = $request->get_header('x-webhook-signature');
        if (!$this->security->verify_webhook_signature($webhook_data, $signature)) {
            return new WP_Error(
                'invalid_signature',
                __('Invalid webhook signature.', 'born-to-ride-booking'),
                ['status' => 403]
            );
        }
        
        // Process webhook with retry and idempotency
        $result = $this->webhook_manager->handle_webhook_with_retry($payment_hash, $webhook_data, $signature);
        
        if (is_wp_error($result)) {
            // Log error but return success to prevent endless retries from gateway
            $this->security->log_security_event('webhook_processing_failed', [
                'payment_hash' => $payment_hash,
                'error' => $result->get_error_message(),
                'webhook_type' => $webhook_data['type'] ?? 'unknown',
                'queued_for_retry' => true
            ], 'error');
            
            return rest_ensure_response([
                'success' => false,
                'message' => __('Webhook queued for retry.', 'born-to-ride-booking'),
                'queued_for_retry' => true
            ]);
        }
        
        return rest_ensure_response([
            'success' => true,
            'message' => __('Webhook processed successfully.', 'born-to-ride-booking')
        ]);
    }
    
    /**
     * Validate payment token
     */
    public function validate_payment_token($request) {
        $payment_hash = sanitize_text_field($request['payment_hash']);
        
        // Get payment data
        $payment_data = $this->db_manager->get_payment_by_hash($payment_hash);
        
        $is_valid = $payment_data && $this->is_payment_link_valid($payment_data);
        
        return rest_ensure_response([
            'valid' => $is_valid,
            'expired' => $payment_data && !$this->is_payment_link_valid($payment_data),
            'exists' => !empty($payment_data)
        ]);
    }
    
    /**
     * Check permissions for payment operations
     */
    public function payment_permissions_check($request) {
        $payment_hash = sanitize_text_field($request['payment_hash']);
        
        // Validate payment hash format
        if (!$this->validate_payment_hash($payment_hash)) {
            return false;
        }
        
        // Payment hash serves as authentication token
        return true;
    }
    
    /**
     * Check permissions for webhook operations
     */
    public function webhook_permissions_check($request) {
        // Webhooks are authenticated via signature verification in the handler
        return true;
    }
    
    /**
     * Validate payment hash format
     */
    public function validate_payment_hash($payment_hash) {
        return preg_match('/^[a-zA-Z0-9]{40,64}$/', $payment_hash);
    }
    
    /**
     * Check if payment link is still valid
     */
    private function is_payment_link_valid($payment_data) {
        if (!$payment_data) {
            return false;
        }
        
        // Check expiry
        if ($payment_data->expires_at && strtotime($payment_data->expires_at) < time()) {
            return false;
        }
        
        // Check if already paid
        if (in_array($payment_data->payment_status, ['completed', 'cancelled'])) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Get package details for display
     */
    private function get_package_details($order) {
        $package_details = [];
        
        // Get package from order meta
        $preventivo_id = $order->get_meta('_btr_preventivo_id');
        if ($preventivo_id) {
            $package_id = get_post_meta($preventivo_id, '_pacchetto_id', true);
            if ($package_id) {
                $package_details = [
                    'id' => $package_id,
                    'title' => get_the_title($package_id),
                    'dates' => [
                        'departure' => get_post_meta($preventivo_id, '_data_partenza', true),
                        'return' => get_post_meta($preventivo_id, '_data_ritorno', true)
                    ],
                    'participants' => [
                        'adults' => get_post_meta($preventivo_id, '_numero_adulti', true),
                        'children' => get_post_meta($preventivo_id, '_numero_bambini', true)
                    ]
                ];
            }
        }
        
        return $package_details;
    }
    
    /**
     * Get available payment methods for a payment
     */
    private function get_available_payment_methods($payment_data) {
        $methods = [];
        
        // Get enabled gateways from WooCommerce
        $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
        
        foreach ($available_gateways as $gateway_id => $gateway) {
            if ($gateway->enabled === 'yes') {
                $methods[$gateway_id] = [
                    'id' => $gateway_id,
                    'title' => $gateway->get_title(),
                    'description' => $gateway->get_description(),
                    'icon' => $gateway->get_icon(),
                    'supports' => $gateway->supports
                ];
            }
        }
        
        return $methods;
    }
    
    /**
     * Process payment by method
     */
    private function process_payment_by_method($payment_method, $payment_data, $gateway_data) {
        try {
            // Use the new Gateway API Manager for direct API integration
            $result = $this->gateway_manager->create_payment_intent($payment_data, $payment_method, $gateway_data);
            
            if (is_wp_error($result)) {
                return $result;
            }
            
            // For methods that require confirmation (like Stripe), return intent data
            if (in_array($payment_method, ['stripe'])) {
                // Update payment status to processing with intent data
                $this->db_manager->update_payment_status($payment_data->payment_id, 'processing', [
                    'payment_intent_id' => $result['payment_intent_id'],
                    'client_secret' => $result['client_secret'] ?? '',
                    'gateway_response' => json_encode($result),
                    'processing_started_at' => current_time('mysql')
                ]);
                
                return [
                    'result' => 'success',
                    'payment_method' => $payment_method,
                    'requires_confirmation' => true,
                    'payment_intent_id' => $result['payment_intent_id'],
                    'client_secret' => $result['client_secret'] ?? '',
                    'requires_action' => $result['requires_action'] ?? false,
                    'next_action' => $result['next_action'] ?? null
                ];
            }
            
            // For redirect-based methods (like PayPal), return redirect URL
            if (in_array($payment_method, ['ppcp-gateway', 'paypal'])) {
                // Update payment status to processing
                $this->db_manager->update_payment_status($payment_data->payment_id, 'processing', [
                    'payment_intent_id' => $result['payment_intent_id'],
                    'gateway_response' => json_encode($result),
                    'processing_started_at' => current_time('mysql')
                ]);
                
                return [
                    'result' => 'success',
                    'payment_method' => $payment_method,
                    'requires_redirect' => true,
                    'payment_intent_id' => $result['payment_intent_id'],
                    'redirect_url' => $result['approval_url'] ?? '',
                    'return_url' => $gateway_data['return_url'] ?? ''
                ];
            }
            
            // For other gateways, fallback to WooCommerce processing
            return $this->fallback_woocommerce_processing($payment_method, $payment_data, $gateway_data);
            
        } catch (Exception $e) {
            return new WP_Error(
                'gateway_error',
                $e->getMessage()
            );
        }
    }
    
    /**
     * Fallback to WooCommerce processing for unsupported gateways
     */
    private function fallback_woocommerce_processing($payment_method, $payment_data, $gateway_data) {
        // Get gateway instance
        $gateways = WC()->payment_gateways()->get_available_payment_gateways();
        
        if (!isset($gateways[$payment_method])) {
            return new WP_Error(
                'gateway_not_available',
                __('Payment gateway not available.', 'born-to-ride-booking')
            );
        }
        
        $gateway = $gateways[$payment_method];
        
        // Get the associated order
        $order = wc_get_order($payment_data->order_id);
        if (!$order) {
            return new WP_Error(
                'order_not_found',
                __('Order not found.', 'born-to-ride-booking')
            );
        }
        
        // Process payment through WooCommerce gateway
        try {
            $result = $gateway->process_payment($order->get_id());
            
            if ($result['result'] === 'success') {
                // Update payment status
                $this->db_manager->update_payment_status($payment_data->payment_id, 'completed', [
                    'transaction_id' => $result['transaction_id'] ?? '',
                    'paid_at' => current_time('mysql'),
                    'gateway_response' => json_encode($result)
                ]);
                
                // Trigger payment completion actions
                do_action('btr_individual_payment_completed', $payment_data, $order, $result);
                
                return $result;
            } else {
                return new WP_Error(
                    'payment_failed',
                    $result['message'] ?? __('Payment failed.', 'born-to-ride-booking')
                );
            }
            
        } catch (Exception $e) {
            return new WP_Error(
                'gateway_error',
                $e->getMessage()
            );
        }
    }
    
    /**
     * Process webhook event
     */
    private function process_webhook_event($event_type, $payment_data, $webhook_data) {
        switch ($event_type) {
            case 'payment.completed':
                return $this->handle_payment_completed_webhook($payment_data, $webhook_data);
                
            case 'payment.failed':
                return $this->handle_payment_failed_webhook($payment_data, $webhook_data);
                
            case 'payment.cancelled':
                return $this->handle_payment_cancelled_webhook($payment_data, $webhook_data);
                
            default:
                return new WP_Error(
                    'unknown_webhook_event',
                    __('Unknown webhook event type.', 'born-to-ride-booking')
                );
        }
    }
    
    /**
     * Handle payment completed webhook
     */
    private function handle_payment_completed_webhook($payment_data, $webhook_data) {
        // Check if payment is already completed (idempotency)
        if ($payment_data->payment_status === 'completed') {
            $this->security->log_security_event('webhook_payment_already_completed', [
                'payment_hash' => $payment_data->payment_hash,
                'webhook_type' => 'payment.completed'
            ]);
            return true;
        }
        
        // Update payment status
        $updated = $this->db_manager->update_payment_status($payment_data->payment_id, 'completed', [
            'transaction_id' => $webhook_data['transaction_id'] ?? '',
            'paid_at' => current_time('mysql'),
            'webhook_data' => json_encode($webhook_data)
        ]);
        
        if ($updated) {
            // Trigger completion actions only if update was successful
            do_action('btr_webhook_payment_completed', $payment_data, $webhook_data);
            
            $this->security->log_security_event('webhook_payment_completed', [
                'payment_hash' => $payment_data->payment_hash,
                'transaction_id' => $webhook_data['transaction_id'] ?? ''
            ]);
        }
        
        return $updated;
    }
    
    /**
     * Handle payment failed webhook
     */
    private function handle_payment_failed_webhook($payment_data, $webhook_data) {
        // Check if payment is already failed (idempotency)
        if ($payment_data->payment_status === 'failed') {
            $this->security->log_security_event('webhook_payment_already_failed', [
                'payment_hash' => $payment_data->payment_hash,
                'webhook_type' => 'payment.failed'
            ]);
            return true;
        }
        
        // Update payment status
        $updated = $this->db_manager->update_payment_status($payment_data->payment_id, 'failed', [
            'failure_reason' => $webhook_data['failure_reason'] ?? '',
            'failed_at' => current_time('mysql'),
            'webhook_data' => json_encode($webhook_data)
        ]);
        
        if ($updated) {
            // Trigger failure actions only if update was successful
            do_action('btr_webhook_payment_failed', $payment_data, $webhook_data);
            
            $this->security->log_security_event('webhook_payment_failed', [
                'payment_hash' => $payment_data->payment_hash,
                'failure_reason' => $webhook_data['failure_reason'] ?? ''
            ]);
        }
        
        return $updated;
    }
    
    /**
     * Handle payment cancelled webhook
     */
    private function handle_payment_cancelled_webhook($payment_data, $webhook_data) {
        // Check if payment is already cancelled (idempotency)
        if ($payment_data->payment_status === 'cancelled') {
            $this->security->log_security_event('webhook_payment_already_cancelled', [
                'payment_hash' => $payment_data->payment_hash,
                'webhook_type' => 'payment.cancelled'
            ]);
            return true;
        }
        
        // Update payment status
        $updated = $this->db_manager->update_payment_status($payment_data->payment_id, 'cancelled', [
            'cancelled_at' => current_time('mysql'),
            'webhook_data' => json_encode($webhook_data)
        ]);
        
        if ($updated) {
            // Trigger cancellation actions only if update was successful
            do_action('btr_webhook_payment_cancelled', $payment_data, $webhook_data);
            
            $this->security->log_security_event('webhook_payment_cancelled', [
                'payment_hash' => $payment_data->payment_hash
            ]);
        }
        
        return $updated;
    }
    
    /**
     * Get payment endpoint arguments
     */
    protected function get_payment_args() {
        return [
            'payment_hash' => [
                'required' => true,
                'type' => 'string',
                'validate_callback' => [$this, 'validate_payment_hash']
            ]
        ];
    }
    
    /**
     * Get process payment arguments
     */
    protected function get_process_payment_args() {
        return [
            'payment_hash' => [
                'required' => true,
                'type' => 'string',
                'validate_callback' => [$this, 'validate_payment_hash']
            ],
            'payment_method' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'security_nonce' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'gateway_data' => [
                'required' => false,
                'type' => 'object',
                'sanitize_callback' => [$this, 'sanitize_gateway_data']
            ]
        ];
    }
    
    /**
     * Get webhook arguments
     */
    protected function get_webhook_args() {
        return [
            'payment_hash' => [
                'required' => true,
                'type' => 'string',
                'validate_callback' => [$this, 'validate_payment_hash']
            ]
        ];
    }
    
    /**
     * Sanitize gateway data
     */
    public function sanitize_gateway_data($value) {
        if (!is_array($value)) {
            return [];
        }
        
        return array_map('sanitize_text_field', $value);
    }
}