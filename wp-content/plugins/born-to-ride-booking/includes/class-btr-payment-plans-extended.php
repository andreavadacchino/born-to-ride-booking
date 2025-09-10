<?php
/**
 * Extended Payment Plans functionality
 * 
 * Estende BTR_Payment_Plans per integrare con la tabella btr_order_shares
 * e gestire pagamenti di gruppo e depositi in modo più completo
 * 
 * @package BornToRideBooking
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Assicura che la classe base sia caricata
if (!class_exists('BTR_Payment_Plans')) {
    require_once BTR_PLUGIN_DIR . 'includes/class-btr-payment-plans.php';
}

// Include Database Manager
if (!class_exists('BTR_Database_Manager')) {
    require_once BTR_PLUGIN_DIR . 'includes/class-btr-database-manager.php';
}

/**
 * Class BTR_Payment_Plans_Extended
 * 
 * Estende la funzionalità di payment plans con integrazione btr_order_shares
 */
class BTR_Payment_Plans_Extended extends BTR_Payment_Plans {
    
    /**
     * Database manager instance
     * @var BTR_Database_Manager
     */
    private $db_manager;
    
    /**
     * Singleton instance
     * @var BTR_Payment_Plans_Extended
     */
    private static $extended_instance = null;
    
    /**
     * Get extended singleton instance
     * @return BTR_Payment_Plans_Extended
     */
    public static function get_extended_instance() {
        if (null === self::$extended_instance) {
            self::$extended_instance = new self();
        }
        return self::$extended_instance;
    }
    
    /**
     * Constructor
     */
    protected function __construct() {
        parent::__construct();
        $this->db_manager = BTR_Database_Manager::get_instance();
        $this->init_extended_hooks();
    }
    
    /**
     * Initialize extended hooks
     */
    private function init_extended_hooks() {
        // Override parent's group payment creation
        remove_action('btr_after_create_payment_plan', [$this, 'generate_group_payment_links'], 10);
        add_action('btr_after_create_payment_plan', [$this, 'handle_payment_plan_creation'], 10, 3);
        
        // Hook per sincronizzazione stati
        add_action('btr_payment_status_changed', [$this, 'sync_payment_status'], 10, 3);
        
        // Hook per reminder
        add_action('btr_hourly_cron', [$this, 'process_payment_reminders']);
    }
    
    /**
     * Create payment shares for group payment
     * Enhanced with improved validation, atomic transactions, and audit trail
     * 
     * @param int $order_id WooCommerce order ID
     * @param array $participants_data Array of participant data
     * @param array $options Additional options for share creation
     * @return array|WP_Error Array of share IDs or error
     */
    public function create_group_payment_shares($order_id, $participants_data, $options = []) {
        // 1. Enhanced input validation
        $validation_result = $this->validate_group_payment_input($order_id, $participants_data, $options);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }
        
        $order = wc_get_order($order_id);
        $order_total = floatval($order->get_total());
        
        // 2. Advanced share calculation with distribution algorithms
        $calculated_shares = $this->calculate_payment_shares($participants_data, $order_total, $options);
        if (is_wp_error($calculated_shares)) {
            return $calculated_shares;
        }
        
        // 3. Comprehensive total validation
        $validation_result = $this->validate_share_totals($calculated_shares, $order_total);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }
        
        // 3.5. Get package-specific reminder configuration for group payments
        $reminder_days = 7; // Default value for group payments
        $preventivo_id = get_post_meta($order_id, '_preventivo_id', true);
        if ($preventivo_id) {
            $package_id = get_post_meta($preventivo_id, '_pacchetto_id', true);
            if ($package_id) {
                $package_reminder_days = get_post_meta($package_id, '_btr_payment_reminder_days', true);
                if (!empty($package_reminder_days)) {
                    // For group payments, reminders start earlier (24h after creation)
                    $reminder_days = max(1, intval($package_reminder_days) / 2);
                }
            }
        }
        
        $shares_created = [];
        $audit_data = [
            'operation' => 'create_group_payment_shares',
            'order_id' => $order_id,
            'order_total' => $order_total,
            'participant_count' => count($calculated_shares),
            'reminder_days' => $reminder_days,
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id()
        ];
        
        // 4. Enhanced atomic transaction with comprehensive error handling
        $transaction_result = $this->db_manager->transaction(function() use (
            $order_id, $calculated_shares, &$shares_created, &$audit_data, $options, $reminder_days
        ) {
            $payment_batch_id = wp_generate_uuid4();
            $audit_data['batch_id'] = $payment_batch_id;
            
            foreach ($calculated_shares as $share_data) {
                // Generate secure payment credentials
                $token = $this->generate_secure_payment_token();
                $payment_hash = $this->generate_payment_hash($order_id, $share_data['participant_email'], $token);
                $payment_link = $this->generate_payment_link($order_id, $payment_hash);
                
                // Enhanced share data structure
                $enhanced_share_data = [
                    'order_id' => $order_id,
                    'payment_batch_id' => $payment_batch_id,
                    'participant_id' => $share_data['participant_id'] ?? 0,
                    'participant_name' => sanitize_text_field($share_data['participant_name']),
                    'participant_email' => sanitize_email($share_data['participant_email']),
                    'participant_phone' => isset($share_data['participant_phone']) ? sanitize_text_field($share_data['participant_phone']) : null,
                    'amount_assigned' => $share_data['calculated_amount'],
                    'original_amount' => $share_data['original_amount'] ?? $share_data['calculated_amount'],
                    'share_percentage' => $share_data['share_percentage'],
                    'calculation_method' => $share_data['calculation_method'],
                    'payment_token' => $token,
                    'payment_hash' => $payment_hash,
                    'payment_link' => esc_url_raw($payment_link),
                    'payment_status' => 'pending',
                    'payment_type' => 'group_share',
                    'currency' => $order->get_currency(),
                    'expires_at' => date('Y-m-d H:i:s', strtotime('+72 hours')),
                    'next_reminder_at' => date('Y-m-d H:i:s', strtotime('+' . $reminder_days . ' days')),
                    'metadata' => json_encode([
                        'creation_options' => $options,
                        'calculation_details' => $share_data['calculation_details'] ?? [],
                        'batch_info' => ['batch_id' => $payment_batch_id, 'batch_size' => count($calculated_shares)]
                    ]),
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ];
                
                // Create share with enhanced error handling
                $share_id = $this->db_manager->create($enhanced_share_data);
                
                if (is_wp_error($share_id)) {
                    $audit_data['errors'][] = [
                        'participant' => $share_data['participant_name'],
                        'error' => $share_id->get_error_message(),
                        'timestamp' => current_time('mysql')
                    ];
                    throw new Exception(sprintf(
                        __('Errore creazione quota per %s: %s', 'born-to-ride-booking'),
                        $share_data['participant_name'],
                        $share_id->get_error_message()
                    ));
                }
                
                $shares_created[] = [
                    'share_id' => $share_id,
                    'participant_email' => $share_data['participant_email'],
                    'amount' => $share_data['calculated_amount'],
                    'payment_link' => $payment_link,
                    'payment_hash' => $payment_hash
                ];
                
                $audit_data['shares_created'][] = [
                    'share_id' => $share_id,
                    'participant_name' => $share_data['participant_name'],
                    'amount' => $share_data['calculated_amount'],
                    'percentage' => $share_data['share_percentage']
                ];
            }
            
            return true;
        });
        
        if (is_wp_error($transaction_result)) {
            $audit_data['status'] = 'failed';
            $audit_data['error'] = $transaction_result->get_error_message();
            $this->log_audit_trail($audit_data);
            return $transaction_result;
        }
        
        // 5. Enhanced order metadata and audit trail
        $this->update_order_metadata($order, $shares_created, $audit_data);
        
        // 6. Automatic email trigger system with enhanced error handling
        $email_results = $this->trigger_payment_emails($shares_created, $order);
        $audit_data['email_results'] = $email_results;
        
        // Log successful completion
        $audit_data['status'] = 'completed';
        $audit_data['completed_at'] = current_time('mysql');
        $this->log_audit_trail($audit_data);
        
        // Enhanced logging
        btr_debug_log(sprintf(
            'Successfully created %d payment shares for order %d (batch: %s, total: %s %s)',
            count($shares_created),
            $order_id,
            $audit_data['batch_id'],
            number_format($order_total, 2),
            $order->get_currency()
        ));
        
        return array_column($shares_created, 'share_id');
    }
    
    /**
     * Enhanced share calculation with multiple distribution algorithms
     * 
     * @param array $participants_data Raw participant data
     * @param float $order_total Order total amount
     * @param array $options Calculation options
     * @return array|WP_Error Calculated shares or error
     */
    private function calculate_payment_shares($participants_data, $order_total, $options = []) {
        $distribution_method = $options['distribution_method'] ?? 'equal';
        $calculated_shares = [];
        
        switch ($distribution_method) {
            case 'equal':
                $calculated_shares = $this->calculate_equal_shares($participants_data, $order_total);
                break;
                
            case 'custom':
                $calculated_shares = $this->calculate_custom_shares($participants_data, $order_total);
                break;
                
            case 'percentage':
                $calculated_shares = $this->calculate_percentage_shares($participants_data, $order_total);
                break;
                
            case 'weighted':
                $calculated_shares = $this->calculate_weighted_shares($participants_data, $order_total, $options);
                break;
                
            default:
                return new WP_Error('invalid_distribution_method', 
                    sprintf(__('Metodo di distribuzione non valido: %s', 'born-to-ride-booking'), $distribution_method)
                );
        }
        
        if (is_wp_error($calculated_shares)) {
            return $calculated_shares;
        }
        
        // Apply rounding and adjustment to ensure exact total match
        return $this->adjust_shares_for_exact_total($calculated_shares, $order_total);
    }
    
    /**
     * Calculate equal distribution shares
     */
    private function calculate_equal_shares($participants_data, $order_total) {
        $participant_count = count($participants_data);
        $share_amount = $order_total / $participant_count;
        $shares = [];
        
        foreach ($participants_data as $index => $participant) {
            $shares[] = [
                'participant_id' => $participant['id'] ?? 0,
                'participant_name' => $participant['name'],
                'participant_email' => $participant['email'],
                'participant_phone' => $participant['phone'] ?? null,
                'calculated_amount' => $share_amount,
                'share_percentage' => round(100 / $participant_count, 4),
                'calculation_method' => 'equal',
                'calculation_details' => [
                    'base_amount' => $share_amount,
                    'participant_count' => $participant_count,
                    'order_total' => $order_total
                ]
            ];
        }
        
        return $shares;
    }
    
    /**
     * Calculate custom amount shares
     */
    private function calculate_custom_shares($participants_data, $order_total) {
        $shares = [];
        $total_assigned = 0;
        
        foreach ($participants_data as $participant) {
            if (!isset($participant['amount']) || !is_numeric($participant['amount'])) {
                return new WP_Error('missing_custom_amount', 
                    sprintf(__('Importo personalizzato mancante per %s', 'born-to-ride-booking'), $participant['name'])
                );
            }
            
            $amount = floatval($participant['amount']);
            $total_assigned += $amount;
            
            $shares[] = [
                'participant_id' => $participant['id'] ?? 0,
                'participant_name' => $participant['name'],
                'participant_email' => $participant['email'],
                'participant_phone' => $participant['phone'] ?? null,
                'calculated_amount' => $amount,
                'original_amount' => $amount,
                'share_percentage' => round(($amount / $order_total) * 100, 4),
                'calculation_method' => 'custom',
                'calculation_details' => [
                    'custom_amount' => $amount,
                    'order_total' => $order_total
                ]
            ];
        }
        
        // Validate that custom amounts don't exceed order total
        if (abs($total_assigned - $order_total) > 0.01) {
            return new WP_Error('custom_amount_mismatch', 
                sprintf(
                    __('Totale importi personalizzati (€%s) non corrisponde al totale ordine (€%s)', 'born-to-ride-booking'),
                    number_format($total_assigned, 2),
                    number_format($order_total, 2)
                )
            );
        }
        
        return $shares;
    }
    
    /**
     * Calculate percentage-based shares
     */
    private function calculate_percentage_shares($participants_data, $order_total) {
        $shares = [];
        $total_percentage = 0;
        
        foreach ($participants_data as $participant) {
            if (!isset($participant['percentage']) || !is_numeric($participant['percentage'])) {
                return new WP_Error('missing_percentage', 
                    sprintf(__('Percentuale mancante per %s', 'born-to-ride-booking'), $participant['name'])
                );
            }
            
            $percentage = floatval($participant['percentage']);
            $total_percentage += $percentage;
            $amount = ($percentage / 100) * $order_total;
            
            $shares[] = [
                'participant_id' => $participant['id'] ?? 0,
                'participant_name' => $participant['name'],
                'participant_email' => $participant['email'],
                'participant_phone' => $participant['phone'] ?? null,
                'calculated_amount' => $amount,
                'share_percentage' => $percentage,
                'calculation_method' => 'percentage',
                'calculation_details' => [
                    'assigned_percentage' => $percentage,
                    'calculated_amount' => $amount,
                    'order_total' => $order_total
                ]
            ];
        }
        
        // Validate percentages sum to 100
        if (abs($total_percentage - 100) > 0.01) {
            return new WP_Error('percentage_sum_invalid', 
                sprintf(
                    __('Totale percentuali (%s%%) deve essere 100%%', 'born-to-ride-booking'),
                    number_format($total_percentage, 2)
                )
            );
        }
        
        return $shares;
    }
    
    /**
     * Calculate weighted shares based on custom weights
     */
    private function calculate_weighted_shares($participants_data, $order_total, $options) {
        $weight_field = $options['weight_field'] ?? 'weight';
        $shares = [];
        $total_weight = 0;
        
        // Calculate total weight
        foreach ($participants_data as $participant) {
            if (!isset($participant[$weight_field]) || !is_numeric($participant[$weight_field])) {
                return new WP_Error('missing_weight', 
                    sprintf(__('Peso mancante per %s', 'born-to-ride-booking'), $participant['name'])
                );
            }
            $total_weight += floatval($participant[$weight_field]);
        }
        
        if ($total_weight <= 0) {
            return new WP_Error('invalid_total_weight', __('Peso totale deve essere maggiore di zero', 'born-to-ride-booking'));
        }
        
        // Calculate weighted shares
        foreach ($participants_data as $participant) {
            $weight = floatval($participant[$weight_field]);
            $percentage = ($weight / $total_weight) * 100;
            $amount = ($weight / $total_weight) * $order_total;
            
            $shares[] = [
                'participant_id' => $participant['id'] ?? 0,
                'participant_name' => $participant['name'],
                'participant_email' => $participant['email'],
                'participant_phone' => $participant['phone'] ?? null,
                'calculated_amount' => $amount,
                'share_percentage' => round($percentage, 4),
                'calculation_method' => 'weighted',
                'calculation_details' => [
                    'weight' => $weight,
                    'total_weight' => $total_weight,
                    'calculated_percentage' => $percentage,
                    'order_total' => $order_total
                ]
            ];
        }
        
        return $shares;
    }
    
    /**
     * Adjust shares to ensure exact total match with order amount
     */
    private function adjust_shares_for_exact_total($shares, $order_total) {
        $calculated_total = array_sum(array_column($shares, 'calculated_amount'));
        $difference = $order_total - $calculated_total;
        
        // If difference is negligible (< 1 cent), adjust the largest share
        if (abs($difference) > 0 && abs($difference) < 0.01) {
            // Find the share with the largest amount to adjust
            $max_index = 0;
            $max_amount = 0;
            
            foreach ($shares as $index => $share) {
                if ($share['calculated_amount'] > $max_amount) {
                    $max_amount = $share['calculated_amount'];
                    $max_index = $index;
                }
            }
            
            $shares[$max_index]['calculated_amount'] += $difference;
            $shares[$max_index]['calculation_details']['rounding_adjustment'] = $difference;
        }
        
        return $shares;
    }
    
    /**
     * Generate secure payment token
     * 
     * @return string
     */
    private function generate_secure_payment_token() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Generate payment hash for secure identification
     * 
     * @param int $order_id
     * @param string $email
     * @param string $token
     * @return string
     */
    private function generate_payment_hash($order_id, $email, $token) {
        $data = $order_id . '|' . $email . '|' . $token . '|' . wp_salt('auth');
        return hash('sha256', $data);
    }
    
    /**
     * Generate payment link
     * 
     * @param int $order_id
     * @param string $token
     * @return string
     */
    private function generate_payment_link($order_id, $token) {
        return add_query_arg([
            'btr_payment' => 'individual',
            'order' => $order_id,
            'token' => $token
        ], home_url('/pagamento-individuale/'));
    }
    
    /**
     * Send payment link email
     * 
     * @param string $email
     * @param string $payment_link
     * @param array $participant_data
     * @return bool
     */
    private function send_payment_link_email($email, $payment_link, $participant_data) {
        // Usa il sistema email esistente se disponibile
        if (class_exists('BTR_Payment_Email_Manager')) {
            $email_manager = new BTR_Payment_Email_Manager();
            return $email_manager->send_payment_link($email, $payment_link, $participant_data);
        }
        
        // Fallback email base
        $subject = __('Il tuo link per il pagamento - Born to Ride', 'born-to-ride-booking');
        $message = sprintf(
            __('Ciao %s,\n\nEcco il tuo link personale per completare il pagamento:\n%s\n\nIl link scadrà tra 72 ore.\n\nGrazie,\nBorn to Ride Team', 'born-to-ride-booking'),
            $participant_data['name'],
            $payment_link
        );
        
        return wp_mail($email, $subject, $message);
    }
    
    /**
     * Enhanced deposit payment creation with enterprise features
     * 
     * @param int $order_id WooCommerce order ID
     * @param float $deposit_percentage Percentage for deposit (10-90)
     * @param array $options Additional configuration options
     * @return array|WP_Error Array with deposit/balance IDs or error
     */
    public function create_deposit_payment($order_id, $deposit_percentage, $options = []) {
        // 1. Enhanced input validation
        $validation_result = $this->validate_deposit_payment_input($order_id, $deposit_percentage, $options);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }
        
        $order = wc_get_order($order_id);
        $order_total = floatval($order->get_total());
        
        // 2. Enhanced calculation engine with configurable options
        $calculation_result = $this->calculate_deposit_amounts($order_total, $deposit_percentage, $options);
        if (is_wp_error($calculation_result)) {
            return $calculation_result;
        }
        
        $deposit_amount = $calculation_result['deposit_amount'];
        $balance_amount = $calculation_result['balance_amount'];
        $due_date = $calculation_result['due_date'];
        
        // 2.5. Get package-specific reminder configuration
        $reminder_days = 7; // Default value
        $preventivo_id = get_post_meta($order_id, '_preventivo_id', true);
        if ($preventivo_id) {
            $package_id = get_post_meta($preventivo_id, '_pacchetto_id', true);
            if ($package_id) {
                $package_reminder_days = get_post_meta($package_id, '_btr_payment_reminder_days', true);
                if (!empty($package_reminder_days)) {
                    $reminder_days = intval($package_reminder_days);
                }
            }
        }
        
        // 3. Prepare audit data
        $audit_data = [
            'operation' => 'create_deposit_payment',
            'order_id' => $order_id,
            'order_total' => $order_total,
            'deposit_percentage' => $deposit_percentage,
            'deposit_amount' => $deposit_amount,
            'balance_amount' => $balance_amount,
            'due_date' => $due_date,
            'reminder_days' => $reminder_days,
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id(),
            'options' => $options
        ];
        
        $shares_created = [];
        
        // 4. Enhanced atomic transaction with comprehensive error handling
        $transaction_result = $this->db_manager->transaction(function() use (
            $order_id, $order, $deposit_amount, $balance_amount, $due_date, 
            $deposit_percentage, $order_total, &$shares_created, &$audit_data, $options, $reminder_days
        ) {
            $payment_batch_id = wp_generate_uuid4();
            $audit_data['batch_id'] = $payment_batch_id;
            
            $customer_email = $order->get_billing_email();
            $customer_name = $order->get_formatted_billing_full_name();
            $customer_id = $order->get_customer_id();
            
            // Generate secure payment credentials for deposit
            $deposit_token = $this->generate_secure_payment_token();
            $deposit_hash = $this->generate_payment_hash($order_id, $customer_email, $deposit_token . '_deposit');
            $deposit_link = $this->generate_payment_link($order_id, $deposit_hash);
            
            // Enhanced deposit share data structure
            $deposit_share_data = [
                'order_id' => $order_id,
                'payment_batch_id' => $payment_batch_id,
                'participant_id' => $customer_id,
                'participant_name' => sanitize_text_field($customer_name),
                'participant_email' => sanitize_email($customer_email),
                'amount_assigned' => $deposit_amount,
                'share_percentage' => $deposit_percentage,
                'calculation_method' => 'deposit_percentage',
                'payment_token' => $deposit_token,
                'payment_hash' => $deposit_hash,
                'payment_link' => esc_url_raw($deposit_link),
                'payment_status' => 'pending',
                'payment_type' => 'deposit',
                'currency' => $order->get_currency(),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+72 hours')),
                'next_reminder_at' => date('Y-m-d H:i:s', strtotime('+24 hours')),
                'metadata' => json_encode([
                    'deposit_percentage' => $deposit_percentage,
                    'order_total' => $order_total,
                    'calculation_details' => [
                        'method' => 'percentage',
                        'percentage' => $deposit_percentage,
                        'calculated_amount' => $deposit_amount
                    ],
                    'batch_info' => ['batch_id' => $payment_batch_id, 'type' => 'deposit_balance'],
                    'options' => $options
                ]),
                'notes' => sprintf(__('Deposito %d%% di %s', 'born-to-ride-booking'), $deposit_percentage, wc_price($order_total)),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ];
            
            // Create deposit share
            $deposit_id = $this->db_manager->create($deposit_share_data);
            if (is_wp_error($deposit_id)) {
                throw new Exception(sprintf(
                    __('Errore creazione quota deposito: %s', 'born-to-ride-booking'),
                    $deposit_id->get_error_message()
                ));
            }
            
            // Generate secure payment credentials for balance
            $balance_token = $this->generate_secure_payment_token();
            $balance_hash = $this->generate_payment_hash($order_id, $customer_email, $balance_token . '_balance');
            $balance_link = $this->generate_payment_link($order_id, $balance_hash);
            
            // Enhanced balance share data structure
            $balance_share_data = [
                'order_id' => $order_id,
                'payment_batch_id' => $payment_batch_id,
                'participant_id' => $customer_id,
                'participant_name' => sanitize_text_field($customer_name),
                'participant_email' => sanitize_email($customer_email),
                'amount_assigned' => $balance_amount,
                'share_percentage' => (100 - $deposit_percentage),
                'calculation_method' => 'balance_remainder',
                'payment_token' => $balance_token,
                'payment_hash' => $balance_hash,
                'payment_link' => esc_url_raw($balance_link),
                'payment_status' => 'pending',
                'payment_type' => 'balance',
                'currency' => $order->get_currency(),
                'expires_at' => $due_date,
                'next_reminder_at' => date('Y-m-d H:i:s', strtotime($due_date . ' -' . $reminder_days . ' days')),
                'metadata' => json_encode([
                    'balance_percentage' => (100 - $deposit_percentage),
                    'order_total' => $order_total,
                    'deposit_amount' => $deposit_amount,
                    'due_date' => $due_date,
                    'calculation_details' => [
                        'method' => 'remainder',
                        'deposit_percentage' => $deposit_percentage,
                        'calculated_amount' => $balance_amount
                    ],
                    'batch_info' => ['batch_id' => $payment_batch_id, 'type' => 'deposit_balance'],
                    'options' => $options
                ]),
                'notes' => sprintf(__('Saldo di %s (scadenza: %s)', 'born-to-ride-booking'), wc_price($balance_amount), date('d/m/Y', strtotime($due_date))),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ];
            
            // Create balance share
            $balance_id = $this->db_manager->create($balance_share_data);
            if (is_wp_error($balance_id)) {
                throw new Exception(sprintf(
                    __('Errore creazione quota saldo: %s', 'born-to-ride-booking'),
                    $balance_id->get_error_message()
                ));
            }
            
            $shares_created = [
                'deposit' => [
                    'share_id' => $deposit_id,
                    'amount' => $deposit_amount,
                    'payment_link' => $deposit_link,
                    'payment_hash' => $deposit_hash,
                    'type' => 'deposit'
                ],
                'balance' => [
                    'share_id' => $balance_id,
                    'amount' => $balance_amount,
                    'payment_link' => $balance_link,
                    'payment_hash' => $balance_hash,
                    'type' => 'balance',
                    'due_date' => $due_date
                ]
            ];
            
            $audit_data['shares_created'] = $shares_created;
            
            return true;
        });
        
        if (is_wp_error($transaction_result)) {
            $audit_data['status'] = 'failed';
            $audit_data['error'] = $transaction_result->get_error_message();
            $this->log_audit_trail($audit_data);
            return $transaction_result;
        }
        
        // 5. Enhanced order metadata and state management
        $this->update_deposit_order_metadata($order, $shares_created, $audit_data, $options);
        
        // 6. Gateway integration for partial payments
        $this->integrate_with_payment_gateway($order, $shares_created, $options);
        
        // 7. Automatic email trigger system with enhanced templates
        $email_results = $this->trigger_deposit_payment_emails($shares_created, $order, $options);
        $audit_data['email_results'] = $email_results;
        
        // Log successful completion
        $audit_data['status'] = 'completed';
        $audit_data['completed_at'] = current_time('mysql');
        $this->log_audit_trail($audit_data);
        
        // Enhanced logging
        btr_debug_log(sprintf(
            'Successfully created deposit payment for order %d (batch: %s, deposit: %s %s, balance: %s %s, due: %s)',
            $order_id,
            $audit_data['batch_id'],
            number_format($deposit_amount, 2),
            $order->get_currency(),
            number_format($balance_amount, 2),
            $order->get_currency(),
            $due_date
        ));
        
        return $shares_created;
    }
    
    /**
     * Handle payment plan creation
     * Overrides parent method to integrate with btr_order_shares
     * 
     * @param int $plan_id
     * @param int $preventivo_id
     * @param array $plan_data
     */
    public function handle_payment_plan_creation($plan_id, $preventivo_id, $plan_data) {
        // Converti preventivo in ordine se necessario
        $order_id = $this->get_or_create_order_from_preventivo($preventivo_id);
        if (!$order_id) {
            btr_debug_log('Failed to create order from preventivo ' . $preventivo_id);
            return;
        }
        
        switch ($plan_data['plan_type']) {
            case self::PLAN_TYPE_GROUP_SPLIT:
                if (!empty($plan_data['payment_distribution'])) {
                    $this->create_group_payment_shares($order_id, $plan_data['payment_distribution']);
                }
                break;
                
            case self::PLAN_TYPE_DEPOSIT_BALANCE:
                $deposit_percentage = isset($plan_data['deposit_percentage']) ? 
                    intval($plan_data['deposit_percentage']) : 30;
                $this->create_deposit_payment($order_id, $deposit_percentage);
                break;
                
            case self::PLAN_TYPE_FULL:
            default:
                // Pagamento completo standard
                update_post_meta($order_id, '_btr_payment_mode', 'full');
                break;
        }
    }
    
    /**
     * Get or create WooCommerce order from preventivo
     * 
     * @param int $preventivo_id
     * @return int|false Order ID or false
     */
    private function get_or_create_order_from_preventivo($preventivo_id) {
        // Verifica se esiste già un ordine
        $existing_order = get_post_meta($preventivo_id, '_order_id', true);
        if ($existing_order && wc_get_order($existing_order)) {
            return $existing_order;
        }
        
        // Crea nuovo ordine se necessario
        if (class_exists('BTR_Preventivo_To_Order')) {
            $converter = new BTR_Preventivo_To_Order();
            $order_id = $converter->convert_to_order($preventivo_id);
            
            if ($order_id && !is_wp_error($order_id)) {
                update_post_meta($preventivo_id, '_order_id', $order_id);
                return $order_id;
            }
        }
        
        return false;
    }
    
    /**
     * Sync payment status between tables
     * 
     * @param int $payment_id
     * @param string $old_status
     * @param string $new_status
     */
    public function sync_payment_status($payment_id, $old_status, $new_status) {
        // Sincronizza stato tra btr_group_payments e btr_order_shares
        global $wpdb;
        
        // Ottieni dati pagamento da btr_group_payments
        $payment_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}btr_group_payments WHERE payment_id = %d",
            $payment_id
        ));
        
        if (!$payment_data) {
            return;
        }
        
        // Trova share corrispondente in btr_order_shares
        $share = $this->db_manager->get_by_email($payment_data->participant_email);
        if (!empty($share)) {
            foreach ($share as $share_record) {
                if ($share_record['order_id'] == $payment_data->order_id) {
                    $this->db_manager->update_payment_status(
                        $share_record['id'],
                        $new_status,
                        $payment_data->transaction_id
                    );
                    break;
                }
            }
        }
    }
    
    /**
     * Process payment reminders
     * Hook per cron job
     */
    public function process_payment_reminders() {
        $pending_reminders = $this->db_manager->get_pending_reminders();
        
        foreach ($pending_reminders as $share) {
            // Invia reminder
            $this->send_payment_reminder($share);
            
            // Incrementa contatore reminder
            $this->db_manager->increment_reminder_count($share['id']);
            
            // Calcola prossimo reminder (3 giorni dopo)
            $next_reminder = date('Y-m-d H:i:s', strtotime('+3 days'));
            $this->db_manager->update($share['id'], [
                'next_reminder_at' => $next_reminder
            ]);
        }
    }
    
    /**
     * Send payment reminder
     * 
     * @param array $share Share data
     * @return bool
     */
    private function send_payment_reminder($share) {
        $subject = __('Promemoria pagamento - Born to Ride', 'born-to-ride-booking');
        $message = sprintf(
            __('Ciao %s,\n\nTi ricordiamo che hai un pagamento in sospeso di %s.\n\nPuoi completare il pagamento al seguente link:\n%s\n\nGrazie,\nBorn to Ride Team', 'born-to-ride-booking'),
            $share['participant_name'],
            wc_price($share['amount_assigned']),
            $share['payment_link']
        );
        
        return wp_mail($share['participant_email'], $subject, $message);
    }
    
    /**
     * Get payment statistics for an order
     * 
     * @param int $order_id
     * @return array
     */
    public function get_order_payment_statistics($order_id) {
        return $this->db_manager->get_order_statistics($order_id);
    }
    
    /**
     * Check if all shares are paid for an order
     * 
     * @param int $order_id
     * @return bool
     */
    public function are_all_shares_paid($order_id) {
        $stats = $this->get_order_payment_statistics($order_id);
        return isset($stats['pending_count']) && $stats['pending_count'] === 0;
    }
    
    // === DEPOSIT PAYMENT SUPPORT METHODS ===
    
    /**
     * Enhanced input validation for deposit payment creation
     * 
     * @param int $order_id
     * @param float $deposit_percentage
     * @param array $options
     * @return bool|WP_Error
     */
    private function validate_deposit_payment_input($order_id, $deposit_percentage, $options = []) {
        // Validate order exists
        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('invalid_order', __('Ordine non valido', 'born-to-ride-booking'));
        }
        
        // Validate deposit percentage range
        if (!is_numeric($deposit_percentage) || $deposit_percentage < 10 || $deposit_percentage > 90) {
            return new WP_Error('invalid_deposit_percentage', 
                sprintf(__('Percentuale deposito deve essere tra 10%% e 90%% (fornita: %s%%)', 'born-to-ride-booking'), 
                    $deposit_percentage)
            );
        }
        
        // Validate order total
        $order_total = floatval($order->get_total());
        if ($order_total <= 0) {
            return new WP_Error('invalid_order_total', __('Totale ordine non valido', 'born-to-ride-booking'));
        }
        
        // Check if deposit payment already exists
        $existing_shares = $this->db_manager->get_by_order($order_id);
        foreach ($existing_shares as $share) {
            if (in_array($share['payment_type'], ['deposit', 'balance'])) {
                return new WP_Error('deposit_already_exists', 
                    __('Sistema di deposito già configurato per questo ordine', 'born-to-ride-booking')
                );
            }
        }
        
        // Validate customer information
        if (empty($order->get_billing_email())) {
            return new WP_Error('missing_customer_email', __('Email cliente mancante', 'born-to-ride-booking'));
        }
        
        return true;
    }
    
    /**
     * Enhanced calculation engine for deposit amounts
     * 
     * @param float $order_total
     * @param float $deposit_percentage
     * @param array $options
     * @return array|WP_Error
     */
    private function calculate_deposit_amounts($order_total, $deposit_percentage, $options = []) {
        // Calculate base amounts
        $deposit_amount = round(($order_total * $deposit_percentage) / 100, 2);
        $balance_amount = round($order_total - $deposit_amount, 2);
        
        // Ensure exact total match
        $calculated_total = $deposit_amount + $balance_amount;
        $difference = $order_total - $calculated_total;
        
        if (abs($difference) > 0.01) {
            return new WP_Error('calculation_mismatch', 
                sprintf(__('Errore calcolo: deposito €%s + saldo €%s ≠ totale €%s', 'born-to-ride-booking'),
                    number_format($deposit_amount, 2),
                    number_format($balance_amount, 2),
                    number_format($order_total, 2)
                )
            );
        }
        
        // Adjust balance if there's a minor difference
        if (abs($difference) > 0 && abs($difference) <= 0.01) {
            $balance_amount += $difference;
        }
        
        // Calculate due date
        $due_days = isset($options['balance_due_days']) ? intval($options['balance_due_days']) : 30;
        $due_date = date('Y-m-d H:i:s', strtotime("+{$due_days} days"));
        
        return [
            'deposit_amount' => $deposit_amount,
            'balance_amount' => $balance_amount,
            'due_date' => $due_date,
            'calculation_details' => [
                'order_total' => $order_total,
                'deposit_percentage' => $deposit_percentage,
                'balance_percentage' => (100 - $deposit_percentage),
                'due_days' => $due_days,
                'adjustment' => $difference
            ]
        ];
    }
    
    /**
     * Enhanced order metadata update for deposit payments
     * 
     * @param WC_Order $order
     * @param array $shares_created
     * @param array $audit_data
     * @param array $options
     */
    private function update_deposit_order_metadata($order, $shares_created, $audit_data, $options = []) {
        $order_id = $order->get_id();
        
        // Update WooCommerce order meta
        update_post_meta($order_id, '_btr_payment_mode', 'deposit_balance');
        update_post_meta($order_id, '_btr_deposit_percentage', $audit_data['deposit_percentage']);
        update_post_meta($order_id, '_btr_deposit_amount', $audit_data['deposit_amount']);
        update_post_meta($order_id, '_btr_balance_amount', $audit_data['balance_amount']);
        update_post_meta($order_id, '_btr_balance_due_date', $audit_data['due_date']);
        update_post_meta($order_id, '_btr_payment_batch_id', $audit_data['batch_id']);
        update_post_meta($order_id, '_btr_deposit_paid', false);
        update_post_meta($order_id, '_btr_balance_paid', false);
        
        // Store payment links for admin reference
        update_post_meta($order_id, '_btr_deposit_link', $shares_created['deposit']['payment_link']);
        update_post_meta($order_id, '_btr_balance_link', $shares_created['balance']['payment_link']);
        
        // Store share IDs for easy reference
        update_post_meta($order_id, '_btr_deposit_share_id', $shares_created['deposit']['share_id']);
        update_post_meta($order_id, '_btr_balance_share_id', $shares_created['balance']['share_id']);
        
        // Add order note with comprehensive information
        $order->add_order_note(sprintf(
            __('Modalità deposito/saldo attivata. Deposito: %s (%d%%), Saldo: %s (scadenza: %s). Batch ID: %s', 'born-to-ride-booking'),
            wc_price($audit_data['deposit_amount']),
            $audit_data['deposit_percentage'],
            wc_price($audit_data['balance_amount']),
            date('d/m/Y', strtotime($audit_data['due_date'])),
            $audit_data['batch_id']
        ));
        
        // Update order total to deposit amount for immediate processing
        $order->set_total($audit_data['deposit_amount']);
        $order->save();
    }
    
    /**
     * Integration with payment gateways for partial payments
     * 
     * @param WC_Order $order
     * @param array $shares_created
     * @param array $options
     */
    private function integrate_with_payment_gateway($order, $shares_created, $options = []) {
        // Add gateway-specific metadata for partial payment support
        if (class_exists('BTR_Gateway_API_Manager')) {
            $gateway_manager = new BTR_Gateway_API_Manager();
            
            // Register partial payment with supported gateways
            $gateway_data = [
                'order_id' => $order->get_id(),
                'payment_type' => 'deposit_balance',
                'deposit_amount' => $shares_created['deposit']['amount'],
                'balance_amount' => $shares_created['balance']['amount'],
                'deposit_hash' => $shares_created['deposit']['payment_hash'],
                'balance_hash' => $shares_created['balance']['payment_hash'],
                'due_date' => $shares_created['balance']['due_date']
            ];
            
            $gateway_manager->register_partial_payment($gateway_data);
        }
        
        // Add hooks for payment gateway callbacks
        add_action('btr_payment_completed', [$this, 'handle_deposit_payment_completion'], 10, 2);
        add_action('btr_payment_failed', [$this, 'handle_deposit_payment_failure'], 10, 2);
    }
    
    /**
     * Enhanced email trigger system for deposit payments
     * 
     * @param array $shares_created
     * @param WC_Order $order
     * @param array $options
     * @return array
     */
    private function trigger_deposit_payment_emails($shares_created, $order, $options = []) {
        $email_results = [];
        
        // Initialize email template manager
        if (class_exists('BTR_Email_Template_Manager')) {
            $email_manager = new BTR_Email_Template_Manager();
            
            $customer_email = $order->get_billing_email();
            
            // Send deposit payment email
            $deposit_variables = [
                'participant_name' => $order->get_formatted_billing_full_name(),
                'participant_email' => $customer_email,
                'amount' => number_format($shares_created['deposit']['amount'], 2),
                'currency' => $order->get_currency(),
                'payment_type' => 'deposit',
                'payment_link' => $shares_created['deposit']['payment_link'],
                'balance_amount' => number_format($shares_created['balance']['amount'], 2),
                'due_date' => date('d/m/Y', strtotime($shares_created['balance']['due_date'])),
                'order_id' => $order->get_id(),
                'language' => 'it'
            ];
            
            $deposit_sent = $email_manager->send_email(
                $customer_email,
                'deposit_payment_created',
                $deposit_variables,
                'it'
            );
            
            $email_results['deposit_email'] = [
                'sent' => $deposit_sent,
                'recipient' => $customer_email,
                'type' => 'deposit_payment_created'
            ];
            
        } else {
            // Fallback basic email
            $deposit_sent = $this->send_basic_deposit_email($shares_created, $order);
            $email_results['deposit_email'] = [
                'sent' => $deposit_sent,
                'recipient' => $order->get_billing_email(),
                'type' => 'basic_deposit_email'
            ];
        }
        
        return $email_results;
    }
    
    /**
     * Send basic deposit email as fallback
     * 
     * @param array $shares_created
     * @param WC_Order $order
     * @return bool
     */
    private function send_basic_deposit_email($shares_created, $order) {
        $customer_email = $order->get_billing_email();
        $customer_name = $order->get_formatted_billing_full_name();
        
        $subject = sprintf(__('Pagamento Deposito - Ordine #%s - Born to Ride', 'born-to-ride-booking'), $order->get_order_number());
        
        $message = sprintf(
            __("Ciao %s,\n\nGrazie per il tuo ordine! Abbiamo configurato un sistema di pagamento in due fasi:\n\n**DEPOSITO (da pagare subito):**\n- Importo: %s\n- Link pagamento: %s\n\n**SALDO (da pagare entro il %s):**\n- Importo: %s\n- Link pagamento: %s\n\nIl deposito deve essere pagato per confermare la prenotazione.\nIl saldo può essere pagato in qualsiasi momento prima della scadenza.\n\nGrazie per aver scelto Born to Ride!\n\nBorn to Ride Team", 'born-to-ride-booking'),
            $customer_name,
            wc_price($shares_created['deposit']['amount']),
            $shares_created['deposit']['payment_link'],
            date('d/m/Y', strtotime($shares_created['balance']['due_date'])),
            wc_price($shares_created['balance']['amount']),
            $shares_created['balance']['payment_link']
        );
        
        return wp_mail($customer_email, $subject, $message);
    }
    
    /**
     * Handle deposit payment completion
     * 
     * @param int $payment_id
     * @param array $payment_data
     */
    public function handle_deposit_payment_completion($payment_id, $payment_data) {
        $share = $this->db_manager->get_by_id($payment_id);
        if (!$share || $share['payment_type'] !== 'deposit') {
            return;
        }
        
        // Update order meta when deposit is paid
        update_post_meta($share['order_id'], '_btr_deposit_paid', true);
        update_post_meta($share['order_id'], '_btr_deposit_paid_at', current_time('mysql'));
        
        // Add order note
        $order = wc_get_order($share['order_id']);
        if ($order) {
            $order->add_order_note(sprintf(
                __('Deposito pagato con successo: %s. Saldo rimanente: %s', 'born-to-ride-booking'),
                wc_price($share['amount_assigned']),
                get_post_meta($share['order_id'], '_btr_balance_amount', true)
            ));
        }
        
        // Log audit trail
        $this->log_audit_trail([
            'operation' => 'deposit_payment_completed',
            'order_id' => $share['order_id'],
            'payment_id' => $payment_id,
            'amount' => $share['amount_assigned'],
            'completed_at' => current_time('mysql')
        ]);
    }
    
    /**
     * Handle deposit payment failure
     * 
     * @param int $payment_id
     * @param array $payment_data
     */
    public function handle_deposit_payment_failure($payment_id, $payment_data) {
        $share = $this->db_manager->get_by_id($payment_id);
        if (!$share || $share['payment_type'] !== 'deposit') {
            return;
        }
        
        // Add order note about failed deposit
        $order = wc_get_order($share['order_id']);
        if ($order) {
            $order->add_order_note(sprintf(
                __('Pagamento deposito fallito: %s. Motivo: %s', 'born-to-ride-booking'),
                wc_price($share['amount_assigned']),
                $payment_data['failure_reason'] ?? 'Errore sconosciuto'
            ));
        }
        
        // Log audit trail
        $this->log_audit_trail([
            'operation' => 'deposit_payment_failed',
            'order_id' => $share['order_id'],
            'payment_id' => $payment_id,
            'amount' => $share['amount_assigned'],
            'failure_reason' => $payment_data['failure_reason'] ?? 'Unknown error',
            'failed_at' => current_time('mysql')
        ]);
    }
    
    // === SHARED SUPPORT METHODS ===
    
    /**
     * Enhanced input validation for group payment
     * 
     * @param int $order_id
     * @param array $participants_data
     * @param array $options
     * @return bool|WP_Error
     */
    private function validate_group_payment_input($order_id, $participants_data, $options = []) {
        // Validate order exists
        $order = wc_get_order($order_id);
        if (!$order) {
            return new WP_Error('invalid_order', __('Ordine non valido', 'born-to-ride-booking'));
        }
        
        // Validate participants data
        if (empty($participants_data) || !is_array($participants_data)) {
            return new WP_Error('empty_participants', __('Dati partecipanti mancanti', 'born-to-ride-booking'));
        }
        
        // Validate participant count (2-50 participants)
        $participant_count = count($participants_data);
        if ($participant_count < 2 || $participant_count > 50) {
            return new WP_Error('invalid_participant_count', 
                sprintf(__('Numero partecipanti deve essere tra 2 e 50 (forniti: %d)', 'born-to-ride-booking'), $participant_count)
            );
        }
        
        // Validate each participant
        $emails_seen = [];
        foreach ($participants_data as $index => $participant) {
            // Required fields
            if (empty($participant['name']) || empty($participant['email'])) {
                return new WP_Error('missing_participant_data', 
                    sprintf(__('Nome ed email obbligatori per partecipante #%d', 'born-to-ride-booking'), $index + 1)
                );
            }
            
            // Valid email
            if (!is_email($participant['email'])) {
                return new WP_Error('invalid_participant_email', 
                    sprintf(__('Email non valida per partecipante %s: %s', 'born-to-ride-booking'), $participant['name'], $participant['email'])
                );
            }
            
            // Unique emails
            if (in_array($participant['email'], $emails_seen)) {
                return new WP_Error('duplicate_participant_email', 
                    sprintf(__('Email duplicata: %s', 'born-to-ride-booking'), $participant['email'])
                );
            }
            $emails_seen[] = $participant['email'];
        }
        
        return true;
    }
    
    /**
     * Validate share totals against order total
     * 
     * @param array $calculated_shares
     * @param float $order_total
     * @return bool|WP_Error
     */
    private function validate_share_totals($calculated_shares, $order_total) {
        $total_shares = array_sum(array_column($calculated_shares, 'calculated_amount'));
        $difference = abs($order_total - $total_shares);
        
        // Allow 1 cent tolerance for rounding
        if ($difference > 0.01) {
            return new WP_Error('share_total_mismatch', 
                sprintf(
                    __('Totale quote (€%s) non corrisponde al totale ordine (€%s). Differenza: €%s', 'born-to-ride-booking'),
                    number_format($total_shares, 2),
                    number_format($order_total, 2),
                    number_format($difference, 2)
                )
            );
        }
        
        return true;
    }
    
    /**
     * Update order metadata for group payments
     * 
     * @param WC_Order $order
     * @param array $shares_created
     * @param array $audit_data
     */
    private function update_order_metadata($order, $shares_created, $audit_data) {
        $order_id = $order->get_id();
        
        // Update WooCommerce order meta
        update_post_meta($order_id, '_btr_payment_mode', 'group_split');
        update_post_meta($order_id, '_btr_payment_batch_id', $audit_data['batch_id']);
        update_post_meta($order_id, '_btr_participant_count', $audit_data['participant_count']);
        update_post_meta($order_id, '_btr_total_shares', count($shares_created));
        
        // Store share IDs for admin reference
        $share_ids = array_column($shares_created, 'share_id');
        update_post_meta($order_id, '_btr_share_ids', $share_ids);
        
        // Add comprehensive order note
        $order->add_order_note(sprintf(
            __('Pagamento di gruppo configurato. Partecipanti: %d, Quote create: %d, Batch ID: %s', 'born-to-ride-booking'),
            $audit_data['participant_count'],
            count($shares_created),
            $audit_data['batch_id']
        ));
    }
    
    /**
     * Enhanced email trigger system for group payments
     * 
     * @param array $shares_created
     * @param WC_Order $order
     * @return array
     */
    private function trigger_payment_emails($shares_created, $order) {
        $email_results = [];
        
        // Initialize email template manager if available
        if (class_exists('BTR_Email_Template_Manager')) {
            $email_manager = new BTR_Email_Template_Manager();
            
            foreach ($shares_created as $share) {
                $template_variables = [
                    'participant_name' => $share['participant_name'] ?? 'Partecipante',
                    'participant_email' => $share['participant_email'],
                    'amount' => number_format($share['amount'], 2),
                    'currency' => $order->get_currency(),
                    'payment_type' => 'group_share',
                    'payment_link' => $share['payment_link'],
                    'order_id' => $order->get_id(),
                    'language' => 'it'
                ];
                
                $sent = $email_manager->send_email(
                    $share['participant_email'],
                    'group_payment_invitation',
                    $template_variables,
                    'it'
                );
                
                $email_results[] = [
                    'participant_email' => $share['participant_email'],
                    'sent' => $sent,
                    'type' => 'group_payment_invitation'
                ];
            }
        } else {
            // Fallback to basic email sending
            foreach ($shares_created as $share) {
                $sent = $this->send_payment_link_email(
                    $share['participant_email'],
                    $share['payment_link'],
                    [
                        'name' => $share['participant_name'] ?? 'Partecipante',
                        'amount' => $share['amount']
                    ]
                );
                
                $email_results[] = [
                    'participant_email' => $share['participant_email'],
                    'sent' => $sent,
                    'type' => 'basic_payment_link'
                ];
            }
        }
        
        return $email_results;
    }
    
    /**
     * Log audit trail for operations
     * 
     * @param array $audit_data
     */
    private function log_audit_trail($audit_data) {
        // Log to WordPress debug if available
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'BTR Audit Trail [%s]: %s',
                $audit_data['operation'],
                json_encode($audit_data, JSON_UNESCAPED_UNICODE)
            ));
        }
        
        // Store in dedicated audit table if it exists
        global $wpdb;
        $audit_table = $wpdb->prefix . 'btr_payment_audit_log';
        
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $audit_table)) === $audit_table) {
            $wpdb->insert(
                $audit_table,
                [
                    'operation' => $audit_data['operation'],
                    'order_id' => $audit_data['order_id'] ?? null,
                    'user_id' => $audit_data['created_by'] ?? get_current_user_id(),
                    'data' => json_encode($audit_data, JSON_UNESCAPED_UNICODE),
                    'created_at' => current_time('mysql')
                ],
                ['%s', '%d', '%d', '%s', '%s']
            );
        }
        
        // Trigger action for external logging systems
        do_action('btr_audit_trail_logged', $audit_data);
    }
}