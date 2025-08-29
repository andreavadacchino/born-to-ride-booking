<?php
/**
 * Webhook Queue Manager - Handles webhook retry and dead letter queue
 * 
 * Provides robust webhook processing with exponential backoff,
 * retry mechanisms, and dead letter queue for failed webhooks
 * 
 * @package BornToRideBooking
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Webhook_Queue_Manager {
    
    /**
     * Maximum retry attempts
     */
    const MAX_RETRY_ATTEMPTS = 5;
    
    /**
     * Base retry delay in seconds
     */
    const BASE_RETRY_DELAY = 60;
    
    /**
     * Dead letter queue table name
     */
    private $dlq_table;
    
    /**
     * Security instance
     */
    private $security;
    
    /**
     * Database manager instance
     */
    private $db_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->dlq_table = $wpdb->prefix . 'btr_webhook_dlq';
        $this->security = new BTR_Payment_Security();
        $this->db_manager = BTR_Database_Manager::get_instance();
        
        // Hook into WordPress cron for retry processing
        add_action('btr_process_webhook_retries', [$this, 'process_webhook_retries']);
        
        // Schedule cron if not already scheduled
        if (!wp_next_scheduled('btr_process_webhook_retries')) {
            wp_schedule_event(time(), 'btr_every_5_minutes', 'btr_process_webhook_retries');
        }
        
        // Add AJAX handlers for testing
        add_action('wp_ajax_btr_test_webhook_queue', [$this, 'ajax_test_webhook_queue']);
        add_action('wp_ajax_btr_process_webhook_retries', [$this, 'ajax_process_webhook_retries']);
        add_action('wp_ajax_btr_cleanup_old_webhooks', [$this, 'ajax_cleanup_old_webhooks']);
    }
    
    /**
     * Register custom cron schedule
     */
    public static function register_cron_schedules($schedules) {
        $schedules['btr_every_5_minutes'] = [
            'interval' => 300, // 5 minutes
            'display' => __('Every 5 Minutes', 'born-to-ride-booking')
        ];
        return $schedules;
    }
    
    /**
     * Create dead letter queue table
     */
    public function create_dlq_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->dlq_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            payment_hash varchar(64) NOT NULL,
            webhook_data longtext NOT NULL,
            webhook_signature varchar(255),
            original_timestamp datetime NOT NULL,
            retry_count int(11) NOT NULL DEFAULT 0,
            last_retry_at datetime,
            next_retry_at datetime,
            failure_reason text,
            status enum('pending', 'retrying', 'failed', 'completed') NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY payment_hash (payment_hash),
            KEY status (status),
            KEY next_retry_at (next_retry_at),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        
        // Log table creation
        BTR_Payment_Security::log_security_event('dlq_table_created', [
            'table_name' => $this->dlq_table
        ]);
    }
    
    /**
     * Queue webhook for retry
     * 
     * @param string $payment_hash
     * @param array $webhook_data
     * @param string $webhook_signature
     * @param string $failure_reason
     * @return bool
     */
    public function queue_webhook_for_retry($payment_hash, $webhook_data, $webhook_signature = '', $failure_reason = '') {
        global $wpdb;
        
        // Check if this webhook is already in queue
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->dlq_table} 
             WHERE payment_hash = %s 
             AND status IN ('pending', 'retrying') 
             ORDER BY created_at DESC LIMIT 1",
            $payment_hash
        ));
        
        if ($existing) {
            // Update existing entry
            $retry_count = intval($existing->retry_count) + 1;
            $next_retry = $this->calculate_next_retry_time($retry_count);
            
            if ($retry_count >= self::MAX_RETRY_ATTEMPTS) {
                // Move to failed status
                $result = $wpdb->update(
                    $this->dlq_table,
                    [
                        'status' => 'failed',
                        'failure_reason' => $failure_reason,
                        'retry_count' => $retry_count,
                        'last_retry_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ],
                    ['id' => $existing->id],
                    ['%s', '%s', '%d', '%s', '%s'],
                    ['%d']
                );
                
                // Trigger alert for permanently failed webhook
                do_action('btr_webhook_permanently_failed', $payment_hash, $webhook_data, $retry_count);
                
                BTR_Payment_Security::log_security_event('webhook_permanently_failed', [
                    'payment_hash' => $payment_hash,
                    'retry_count' => $retry_count,
                    'failure_reason' => $failure_reason
                ], 'error');
                
            } else {
                // Update for next retry
                $result = $wpdb->update(
                    $this->dlq_table,
                    [
                        'status' => 'pending',
                        'failure_reason' => $failure_reason,
                        'retry_count' => $retry_count,
                        'next_retry_at' => $next_retry,
                        'last_retry_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ],
                    ['id' => $existing->id],
                    ['%s', '%s', '%d', '%s', '%s', '%s'],
                    ['%d']
                );
            }
            
        } else {
            // Create new entry
            $next_retry = $this->calculate_next_retry_time(1);
            
            $result = $wpdb->insert(
                $this->dlq_table,
                [
                    'payment_hash' => $payment_hash,
                    'webhook_data' => json_encode($webhook_data),
                    'webhook_signature' => $webhook_signature,
                    'original_timestamp' => current_time('mysql'),
                    'retry_count' => 1,
                    'next_retry_at' => $next_retry,
                    'failure_reason' => $failure_reason,
                    'status' => 'pending',
                    'created_at' => current_time('mysql')
                ],
                ['%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
            );
        }
        
        if ($result === false) {
            BTR_Payment_Security::log_security_event('dlq_queue_error', [
                'payment_hash' => $payment_hash,
                'error' => $wpdb->last_error
            ], 'error');
            return false;
        }
        
        BTR_Payment_Security::log_security_event('webhook_queued_for_retry', [
            'payment_hash' => $payment_hash,
            'retry_count' => isset($retry_count) ? $retry_count : 1,
            'next_retry_at' => $next_retry
        ]);
        
        return true;
    }
    
    /**
     * Process webhook retries
     */
    public function process_webhook_retries() {
        global $wpdb;
        
        // Get webhooks ready for retry
        $webhooks_to_retry = $wpdb->get_results(
            "SELECT * FROM {$this->dlq_table} 
             WHERE status = 'pending' 
             AND next_retry_at <= NOW() 
             AND retry_count < " . self::MAX_RETRY_ATTEMPTS . "
             ORDER BY next_retry_at ASC 
             LIMIT 10"
        );
        
        if (empty($webhooks_to_retry)) {
            return;
        }
        
        BTR_Payment_Security::log_security_event('processing_webhook_retries', [
            'count' => count($webhooks_to_retry)
        ]);
        
        foreach ($webhooks_to_retry as $webhook) {
            $this->retry_webhook($webhook);
        }
    }
    
    /**
     * Retry individual webhook
     * 
     * @param object $webhook
     */
    private function retry_webhook($webhook) {
        global $wpdb;
        
        // Update status to retrying
        $wpdb->update(
            $this->dlq_table,
            [
                'status' => 'retrying',
                'last_retry_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['id' => $webhook->id],
            ['%s', '%s', '%s'],
            ['%d']
        );
        
        try {
            // Parse webhook data
            $webhook_data = json_decode($webhook->webhook_data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid webhook data JSON');
            }
            
            // Get payment data
            $payment_data = $this->db_manager->get_payment_by_hash($webhook->payment_hash);
            if (!$payment_data) {
                throw new Exception('Payment not found');
            }
            
            // Process webhook through REST controller
            $rest_controller = new BTR_Payment_REST_Controller();
            $result = $rest_controller->process_webhook_event(
                $webhook_data['type'] ?? 'unknown',
                $payment_data,
                $webhook_data
            );
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            
            // Success - mark as completed
            $wpdb->update(
                $this->dlq_table,
                [
                    'status' => 'completed',
                    'updated_at' => current_time('mysql')
                ],
                ['id' => $webhook->id],
                ['%s', '%s'],
                ['%d']
            );
            
            BTR_Payment_Security::log_security_event('webhook_retry_success', [
                'payment_hash' => $webhook->payment_hash,
                'retry_count' => $webhook->retry_count
            ]);
            
        } catch (Exception $e) {
            // Failure - queue for next retry or mark as failed
            $next_retry_count = intval($webhook->retry_count) + 1;
            
            if ($next_retry_count >= self::MAX_RETRY_ATTEMPTS) {
                // Permanently failed
                $wpdb->update(
                    $this->dlq_table,
                    [
                        'status' => 'failed',
                        'failure_reason' => $e->getMessage(),
                        'retry_count' => $next_retry_count,
                        'updated_at' => current_time('mysql')
                    ],
                    ['id' => $webhook->id],
                    ['%s', '%s', '%d', '%s'],
                    ['%d']
                );
                
                // Trigger alert
                do_action('btr_webhook_permanently_failed', $webhook->payment_hash, $webhook_data, $next_retry_count);
                
            } else {
                // Schedule next retry
                $next_retry_at = $this->calculate_next_retry_time($next_retry_count);
                
                $wpdb->update(
                    $this->dlq_table,
                    [
                        'status' => 'pending',
                        'failure_reason' => $e->getMessage(),
                        'retry_count' => $next_retry_count,
                        'next_retry_at' => $next_retry_at,
                        'updated_at' => current_time('mysql')
                    ],
                    ['id' => $webhook->id],
                    ['%s', '%s', '%d', '%s', '%s'],
                    ['%d']
                );
            }
            
            BTR_Payment_Security::log_security_event('webhook_retry_failed', [
                'payment_hash' => $webhook->payment_hash,
                'retry_count' => $next_retry_count,
                'error' => $e->getMessage()
            ], 'error');
        }
    }
    
    /**
     * Calculate next retry time with exponential backoff
     * 
     * @param int $retry_count
     * @return string
     */
    private function calculate_next_retry_time($retry_count) {
        // Exponential backoff: base_delay * (2 ^ (retry_count - 1))
        // With jitter to prevent thundering herd
        $delay = self::BASE_RETRY_DELAY * pow(2, $retry_count - 1);
        $jitter = rand(0, min($delay * 0.1, 300)); // Max 5 minutes jitter
        $total_delay = $delay + $jitter;
        
        // Cap at 24 hours
        $total_delay = min($total_delay, 86400);
        
        return date('Y-m-d H:i:s', time() + $total_delay);
    }
    
    /**
     * Get webhook statistics
     * 
     * @return array
     */
    public function get_webhook_stats() {
        global $wpdb;
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'retrying' THEN 1 ELSE 0 END) as retrying,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                AVG(retry_count) as avg_retry_count
             FROM {$this->dlq_table}
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
            ARRAY_A
        );
        
        return $stats ?: [
            'total' => 0,
            'pending' => 0,
            'retrying' => 0,
            'completed' => 0,
            'failed' => 0,
            'avg_retry_count' => 0
        ];
    }
    
    /**
     * Cleanup old webhook records
     */
    public function cleanup_old_webhooks() {
        global $wpdb;
        
        // Delete completed webhooks older than 7 days
        $deleted_completed = $wpdb->query(
            "DELETE FROM {$this->dlq_table} 
             WHERE status = 'completed' 
             AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );
        
        // Delete failed webhooks older than 30 days
        $deleted_failed = $wpdb->query(
            "DELETE FROM {$this->dlq_table} 
             WHERE status = 'failed' 
             AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        if ($deleted_completed > 0 || $deleted_failed > 0) {
            BTR_Payment_Security::log_security_event('webhook_cleanup', [
                'deleted_completed' => $deleted_completed,
                'deleted_failed' => $deleted_failed
            ]);
        }
    }
    
    /**
     * Handle webhook processing with idempotency
     * 
     * @param string $payment_hash
     * @param array $webhook_data
     * @param string $signature
     * @return bool|WP_Error
     */
    public function handle_webhook_with_retry($payment_hash, $webhook_data, $signature = '') {
        try {
            // Create idempotency key from webhook data
            $idempotency_key = $this->create_idempotency_key($webhook_data);
            
            // Check if this webhook was already processed
            if ($this->is_webhook_already_processed($payment_hash, $idempotency_key)) {
                BTR_Payment_Security::log_security_event('webhook_duplicate_ignored', [
                    'payment_hash' => $payment_hash,
                    'idempotency_key' => $idempotency_key
                ]);
                return true;
            }
            
            // Mark webhook as being processed
            $this->mark_webhook_processing($payment_hash, $idempotency_key);
            
            // Get payment data
            $payment_data = $this->db_manager->get_payment_by_hash($payment_hash);
            if (!$payment_data) {
                throw new Exception('Payment not found');
            }
            
            // Process webhook
            $rest_controller = new BTR_Payment_REST_Controller();
            $result = $rest_controller->process_webhook_event(
                $webhook_data['type'] ?? 'unknown',
                $payment_data,
                $webhook_data
            );
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            
            // Mark webhook as successfully processed
            $this->mark_webhook_completed($payment_hash, $idempotency_key);
            
            return true;
            
        } catch (Exception $e) {
            // Queue for retry
            $this->queue_webhook_for_retry($payment_hash, $webhook_data, $signature, $e->getMessage());
            
            return new WP_Error(
                'webhook_processing_failed',
                $e->getMessage()
            );
        }
    }
    
    /**
     * Create idempotency key from webhook data
     * 
     * @param array $webhook_data
     * @return string
     */
    private function create_idempotency_key($webhook_data) {
        // Create a unique key based on webhook data
        $key_data = [
            'type' => $webhook_data['type'] ?? '',
            'id' => $webhook_data['id'] ?? '',
            'created' => $webhook_data['created'] ?? time()
        ];
        
        return hash('sha256', json_encode($key_data));
    }
    
    /**
     * Check if webhook was already processed
     * 
     * @param string $payment_hash
     * @param string $idempotency_key
     * @return bool
     */
    private function is_webhook_already_processed($payment_hash, $idempotency_key) {
        $transient_key = "btr_webhook_processed_{$payment_hash}_{$idempotency_key}";
        return get_transient($transient_key) !== false;
    }
    
    /**
     * Mark webhook as being processed
     * 
     * @param string $payment_hash
     * @param string $idempotency_key
     */
    private function mark_webhook_processing($payment_hash, $idempotency_key) {
        $transient_key = "btr_webhook_processed_{$payment_hash}_{$idempotency_key}";
        set_transient($transient_key, 'processing', 3600); // 1 hour
    }
    
    /**
     * Mark webhook as completed
     * 
     * @param string $payment_hash
     * @param string $idempotency_key
     */
    private function mark_webhook_completed($payment_hash, $idempotency_key) {
        $transient_key = "btr_webhook_processed_{$payment_hash}_{$idempotency_key}";
        set_transient($transient_key, 'completed', 86400); // 24 hours
    }
    
    /**
     * AJAX handler for testing webhook queue
     */
    public function ajax_test_webhook_queue() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'btr_webhook_test')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $payment_hash = sanitize_text_field($_POST['payment_hash']);
        $webhook_data = $_POST['webhook_data'];
        
        $result = $this->queue_webhook_for_retry(
            $payment_hash,
            $webhook_data,
            '',
            'Test webhook queued from admin interface'
        );
        
        wp_send_json([
            'success' => $result,
            'message' => $result ? 'Webhook queued successfully' : 'Failed to queue webhook',
            'payment_hash' => $payment_hash
        ]);
    }
    
    /**
     * AJAX handler for processing webhook retries
     */
    public function ajax_process_webhook_retries() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'btr_webhook_test')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $this->process_webhook_retries();
        
        wp_send_json([
            'success' => true,
            'message' => 'Webhook retries processed',
            'stats' => $this->get_webhook_stats()
        ]);
    }
    
    /**
     * AJAX handler for cleaning up old webhooks
     */
    public function ajax_cleanup_old_webhooks() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'btr_webhook_test')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $this->cleanup_old_webhooks();
        
        wp_send_json([
            'success' => true,
            'message' => 'Old webhooks cleaned up',
            'stats' => $this->get_webhook_stats()
        ]);
    }
}

// Hook into cron schedules
add_filter('cron_schedules', ['BTR_Webhook_Queue_Manager', 'register_cron_schedules']);