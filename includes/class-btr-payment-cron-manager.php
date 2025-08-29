<?php
/**
 * Payment Cron Manager - Handles scheduled tasks for payment system
 * 
 * Manages all cron jobs related to payment processing including:
 * - Payment reminder emails
 * - Payment expiry checks
 * - Status synchronization
 * - Cleanup tasks
 * 
 * @package BornToRideBooking
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Payment_Cron_Manager {
    
    /**
     * Database manager instance
     */
    private $db_manager;
    
    /**
     * Security instance
     */
    private $security;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db_manager = BTR_Database_Manager::get_instance();
        $this->security = new BTR_Payment_Security();
        
        // Register cron hooks
        add_action('btr_send_payment_reminders', [$this, 'send_payment_reminders']);
        add_action('btr_expire_old_payments', [$this, 'expire_old_payments']);
        add_action('btr_sync_payment_statuses', [$this, 'sync_payment_statuses']);
        add_action('btr_cleanup_payment_data', [$this, 'cleanup_payment_data']);
        add_action('btr_process_auto_cancellations', [$this, 'process_auto_cancellations']);
        
        // Schedule cron jobs if not already scheduled
        $this->schedule_cron_jobs();
        
        // Add AJAX handlers for manual execution
        add_action('wp_ajax_btr_send_payment_reminders', [$this, 'ajax_send_payment_reminders']);
        add_action('wp_ajax_btr_expire_old_payments', [$this, 'ajax_expire_old_payments']);
        add_action('wp_ajax_btr_sync_payment_statuses', [$this, 'ajax_sync_payment_statuses']);
        add_action('wp_ajax_btr_cleanup_payment_data', [$this, 'ajax_cleanup_payment_data']);
        add_action('wp_ajax_btr_process_auto_cancellations', [$this, 'ajax_process_auto_cancellations']);
    }
    
    /**
     * Register custom cron schedules
     */
    public static function register_cron_schedules($schedules) {
        // Every 15 minutes for time-sensitive tasks
        $schedules['btr_every_15_minutes'] = [
            'interval' => 900, // 15 minutes
            'display' => __('Every 15 Minutes', 'born-to-ride-booking')
        ];
        
        // Every hour for regular tasks
        $schedules['btr_hourly'] = [
            'interval' => 3600, // 1 hour
            'display' => __('Every Hour', 'born-to-ride-booking')
        ];
        
        // Every 6 hours for maintenance tasks
        $schedules['btr_every_6_hours'] = [
            'interval' => 21600, // 6 hours
            'display' => __('Every 6 Hours', 'born-to-ride-booking')
        ];
        
        return $schedules;
    }
    
    /**
     * Schedule all cron jobs
     */
    private function schedule_cron_jobs() {
        // Payment reminders - every 15 minutes
        if (!wp_next_scheduled('btr_send_payment_reminders')) {
            wp_schedule_event(time(), 'btr_every_15_minutes', 'btr_send_payment_reminders');
        }
        
        // Expire old payments - every hour
        if (!wp_next_scheduled('btr_expire_old_payments')) {
            wp_schedule_event(time(), 'btr_hourly', 'btr_expire_old_payments');
        }
        
        // Sync payment statuses - every hour
        if (!wp_next_scheduled('btr_sync_payment_statuses')) {
            wp_schedule_event(time(), 'btr_hourly', 'btr_sync_payment_statuses');
        }
        
        // Cleanup old data - every 6 hours
        if (!wp_next_scheduled('btr_cleanup_payment_data')) {
            wp_schedule_event(time(), 'btr_every_6_hours', 'btr_cleanup_payment_data');
        }
        
        // Process auto-cancellations - every hour
        if (!wp_next_scheduled('btr_process_auto_cancellations')) {
            wp_schedule_event(time(), 'btr_hourly', 'btr_process_auto_cancellations');
        }
    }
    
    /**
     * Send payment reminders
     */
    public function send_payment_reminders() {
        global $wpdb;
        
        BTR_Payment_Security::log_security_event('cron_send_payment_reminders_started');
        
        $table_name = $this->db_manager->get_table_name();
        
        // Get payments that need reminders
        $payments_needing_reminders = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$table_name}
            WHERE payment_status = 'pending'
            AND next_reminder_at IS NOT NULL
            AND next_reminder_at <= %s
            AND (expires_at IS NULL OR expires_at > %s)
            ORDER BY next_reminder_at ASC
            LIMIT 50
        ", current_time('mysql'), current_time('mysql')));
        
        if (empty($payments_needing_reminders)) {
            BTR_Payment_Security::log_security_event('cron_no_reminders_needed');
            return;
        }
        
        $sent_count = 0;
        $error_count = 0;
        
        foreach ($payments_needing_reminders as $payment) {
            try {
                $this->send_individual_reminder($payment);
                $sent_count++;
                
                // Update next reminder time
                $this->update_next_reminder_time($payment);
                
            } catch (Exception $e) {
                $error_count++;
                BTR_Payment_Security::log_security_event('reminder_send_failed', [
                    'payment_id' => $payment->payment_id,
                    'error' => $e->getMessage()
                ], 'error');
            }
        }
        
        BTR_Payment_Security::log_security_event('cron_send_payment_reminders_completed', [
            'sent_count' => $sent_count,
            'error_count' => $error_count,
            'total_processed' => count($payments_needing_reminders)
        ]);
        
        // Trigger alerts if too many errors
        if ($error_count > 0 && ($error_count / count($payments_needing_reminders)) > 0.2) {
            do_action('btr_high_reminder_error_rate', $error_count, count($payments_needing_reminders));
        }
    }
    
    /**
     * Send individual payment reminder
     * 
     * @param object $payment
     */
    private function send_individual_reminder($payment) {
        // Get order details
        $order = wc_get_order($payment->order_id);
        if (!$order) {
            throw new Exception("Order {$payment->order_id} not found");
        }
        
        // Get payment link
        $payment_link = $this->generate_payment_link($payment);
        
        // Prepare email data
        $email_data = [
            'participant_name' => $payment->participant_name,
            'participant_email' => $payment->participant_email,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'payment_type' => $payment->payment_type,
            'payment_link' => $payment_link,
            'expires_at' => $payment->expires_at,
            'order' => $order,
            'reminder_count' => intval($payment->reminder_count) + 1
        ];
        
        // Send email based on payment type
        $email_sent = $this->send_reminder_email($email_data);
        
        if (!$email_sent) {
            throw new Exception('Failed to send reminder email');
        }
        
        // Update reminder count
        $this->db_manager->increment_reminder_count($payment->payment_id);
        
        BTR_Payment_Security::log_security_event('payment_reminder_sent', [
            'payment_id' => $payment->payment_id,
            'payment_hash' => $payment->payment_hash,
            'participant_email' => $this->security->mask_email($payment->participant_email),
            'reminder_count' => $email_data['reminder_count']
        ]);
    }
    
    /**
     * Update next reminder time based on escalation logic
     * 
     * @param object $payment
     */
    private function update_next_reminder_time($payment) {
        $reminder_count = intval($payment->reminder_count) + 1;
        
        // Escalation schedule: 1 day, 3 days, 7 days, then stop
        $escalation_schedule = [
            1 => 1,  // 1 day after first reminder
            2 => 3,  // 3 days after second reminder
            3 => 7,  // 7 days after third reminder
        ];
        
        if ($reminder_count >= 3) {
            // Stop sending reminders after 3 attempts
            $next_reminder_at = null;
            
            // Send admin notification for failed reminders
            $this->send_admin_notification_failed_reminders($payment);
            
            // Schedule auto-cancellation if configured
            $this->schedule_auto_cancellation($payment);
        } else {
            $days_to_add = $escalation_schedule[$reminder_count] ?? 1;
            $next_reminder_at = date('Y-m-d H:i:s', strtotime("+{$days_to_add} days"));
            
            // Don't schedule reminders after payment expires
            if ($payment->expires_at && strtotime($next_reminder_at) >= strtotime($payment->expires_at)) {
                $next_reminder_at = null;
            }
        }
        
        // Update database
        global $wpdb;
        $table_name = $this->db_manager->get_table_name();
        
        $wpdb->update(
            $table_name,
            [
                'next_reminder_at' => $next_reminder_at,
                'updated_at' => current_time('mysql')
            ],
            ['payment_id' => $payment->payment_id],
            ['%s', '%s'],
            ['%d']
        );
    }
    
    /**
     * Generate payment link for reminder
     * 
     * @param object $payment
     * @return string
     */
    private function generate_payment_link($payment) {
        $base_url = site_url('/payment-page/');
        return add_query_arg('payment_hash', $payment->payment_hash, $base_url);
    }
    
    /**
     * Send reminder email using Email Template Manager
     * 
     * @param array $email_data
     * @return bool
     */
    private function send_reminder_email($email_data) {
        // Initialize email template manager
        $email_manager = new BTR_Email_Template_Manager();
        
        // Prepare template variables
        $template_variables = [
            'participant_name' => $email_data['participant_name'],
            'participant_email' => $email_data['participant_email'],
            'amount' => number_format($email_data['amount'], 2),
            'currency' => $email_data['currency'],
            'payment_type' => $email_data['payment_type'],
            'payment_link' => $email_data['payment_link'],
            'expires_at' => $email_data['expires_at'],
            'reminder_count' => $email_data['reminder_count'],
            'language' => 'it' // Default to Italian, could be made configurable
        ];
        
        // Send email using template system
        $sent = $email_manager->send_email(
            $email_data['participant_email'],
            'payment_reminder',
            $template_variables,
            'it'
        );
        
        return $sent;
    }
    
    
    /**
     * Expire old payments
     */
    public function expire_old_payments() {
        global $wpdb;
        
        BTR_Payment_Security::log_security_event('cron_expire_old_payments_started');
        
        $table_name = $this->db_manager->get_table_name();
        
        // Find expired payments
        $expired_payments = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$table_name}
            WHERE payment_status = 'pending'
            AND expires_at IS NOT NULL
            AND expires_at <= %s
        ", current_time('mysql')));
        
        $expired_count = 0;
        
        foreach ($expired_payments as $payment) {
            // Update status to expired
            $updated = $this->db_manager->update_payment_status($payment->payment_id, 'expired', [
                'expired_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ]);
            
            if ($updated) {
                $expired_count++;
                
                // Trigger expired payment action
                do_action('btr_payment_expired', $payment);
                
                BTR_Payment_Security::log_security_event('payment_expired', [
                    'payment_id' => $payment->payment_id,
                    'payment_hash' => $payment->payment_hash,
                    'amount' => $payment->amount
                ]);
            }
        }
        
        BTR_Payment_Security::log_security_event('cron_expire_old_payments_completed', [
            'expired_count' => $expired_count,
            'total_checked' => count($expired_payments)
        ]);
    }
    
    /**
     * Sync payment statuses with gateway
     */
    public function sync_payment_statuses() {
        global $wpdb;
        
        BTR_Payment_Security::log_security_event('cron_sync_payment_statuses_started');
        
        $table_name = $this->db_manager->get_table_name();
        
        // Get payments in processing status that might need status update
        $processing_payments = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$table_name}
            WHERE payment_status = 'processing'
            AND updated_at <= %s
            LIMIT 20
        ", date('Y-m-d H:i:s', strtotime('-1 hour'))));
        
        $synced_count = 0;
        
        foreach ($processing_payments as $payment) {
            try {
                // Try to sync status with gateway
                $this->sync_individual_payment_status($payment);
                $synced_count++;
                
            } catch (Exception $e) {
                BTR_Payment_Security::log_security_event('payment_sync_failed', [
                    'payment_id' => $payment->payment_id,
                    'error' => $e->getMessage()
                ], 'error');
            }
        }
        
        BTR_Payment_Security::log_security_event('cron_sync_payment_statuses_completed', [
            'synced_count' => $synced_count,
            'total_checked' => count($processing_payments)
        ]);
    }
    
    /**
     * Sync individual payment status
     * 
     * @param object $payment
     */
    private function sync_individual_payment_status($payment) {
        // This would integrate with the gateway API to check payment status
        // For now, we'll mark old processing payments as failed
        
        $hours_in_processing = (time() - strtotime($payment->updated_at)) / 3600;
        
        if ($hours_in_processing > 24) {
            // Mark as failed if processing for more than 24 hours
            $this->db_manager->update_payment_status($payment->payment_id, 'failed', [
                'failure_reason' => 'Payment processing timeout',
                'failed_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ]);
            
            BTR_Payment_Security::log_security_event('payment_timeout_failed', [
                'payment_id' => $payment->payment_id,
                'hours_in_processing' => $hours_in_processing
            ]);
        }
    }
    
    /**
     * Cleanup old payment data
     */
    public function cleanup_payment_data() {
        global $wpdb;
        
        BTR_Payment_Security::log_security_event('cron_cleanup_payment_data_started');
        
        $table_name = $this->db_manager->get_table_name();
        
        // Delete completed payments older than 90 days
        $deleted_completed = $wpdb->query($wpdb->prepare("
            DELETE FROM {$table_name}
            WHERE payment_status = 'completed'
            AND paid_at IS NOT NULL
            AND paid_at < %s
        ", date('Y-m-d H:i:s', strtotime('-90 days'))));
        
        // Delete failed/expired payments older than 30 days
        $deleted_failed = $wpdb->query($wpdb->prepare("
            DELETE FROM {$table_name}
            WHERE payment_status IN ('failed', 'expired', 'cancelled')
            AND updated_at < %s
        ", date('Y-m-d H:i:s', strtotime('-30 days'))));
        
        // Cleanup old soft deleted records (GDPR compliance)
        $deleted_soft = $this->db_manager->cleanup_old_deleted(90);
        
        // Update deposit/balance payment tracking
        $this->update_deposit_payment_tracking();
        
        BTR_Payment_Security::log_security_event('cron_cleanup_payment_data_completed', [
            'deleted_completed' => $deleted_completed,
            'deleted_failed' => $deleted_failed,
            'deleted_soft' => $deleted_soft,
            'total_deleted' => $deleted_completed + $deleted_failed + $deleted_soft
        ]);
    }
    
    /**
     * Get cron job statistics
     * 
     * @return array
     */
    public function get_cron_stats() {
        $stats = [
            'next_reminder_check' => wp_next_scheduled('btr_send_payment_reminders'),
            'next_expiry_check' => wp_next_scheduled('btr_expire_old_payments'),
            'next_sync_check' => wp_next_scheduled('btr_sync_payment_statuses'),
            'next_cleanup' => wp_next_scheduled('btr_cleanup_payment_data'),
        ];
        
        // Convert timestamps to readable format
        foreach ($stats as $key => $timestamp) {
            if ($timestamp) {
                $stats[$key] = date('Y-m-d H:i:s', $timestamp);
            } else {
                $stats[$key] = 'Not scheduled';
            }
        }
        
        return $stats;
    }
    
    /**
     * AJAX handlers for manual execution
     */
    public function ajax_send_payment_reminders() {
        if (!wp_verify_nonce($_POST['nonce'], 'btr_cron_test')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $this->send_payment_reminders();
        
        wp_send_json([
            'success' => true,
            'message' => 'Payment reminders processed'
        ]);
    }
    
    public function ajax_expire_old_payments() {
        if (!wp_verify_nonce($_POST['nonce'], 'btr_cron_test')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $this->expire_old_payments();
        
        wp_send_json([
            'success' => true,
            'message' => 'Old payments expired'
        ]);
    }
    
    public function ajax_sync_payment_statuses() {
        if (!wp_verify_nonce($_POST['nonce'], 'btr_cron_test')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $this->sync_payment_statuses();
        
        wp_send_json([
            'success' => true,
            'message' => 'Payment statuses synced'
        ]);
    }
    
    public function ajax_cleanup_payment_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'btr_cron_test')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $this->cleanup_payment_data();
        
        wp_send_json([
            'success' => true,
            'message' => 'Payment data cleaned up'
        ]);
    }
    
    /**
     * Process auto-cancellations for overdue payments
     */
    public function process_auto_cancellations() {
        global $wpdb;
        
        BTR_Payment_Security::log_security_event('cron_process_auto_cancellations_started');
        
        $table_name = $this->db_manager->get_table_name();
        
        // Find payments that should be auto-cancelled
        $payments_to_cancel = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$table_name}
            WHERE payment_status = 'pending'
            AND auto_cancel_at IS NOT NULL
            AND auto_cancel_at <= %s
        ", current_time('mysql')));
        
        $cancelled_count = 0;
        
        foreach ($payments_to_cancel as $payment) {
            // Update payment status to cancelled
            $updated = $this->db_manager->update_payment_status($payment->payment_id, 'cancelled', [
                'cancelled_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
                'auto_cancel_at' => null // Clear the auto-cancel date
            ]);
            
            if ($updated) {
                $cancelled_count++;
                
                // Cancel the associated WooCommerce order if it exists
                if ($payment->order_id) {
                    $order = wc_get_order($payment->order_id);
                    if ($order && !in_array($order->get_status(), ['cancelled', 'refunded', 'completed'])) {
                        $order->update_status('cancelled', 
                            sprintf(__('Auto-cancelled after failed payment reminders for payment hash: %s', 'born-to-ride-booking'), 
                            $payment->payment_hash)
                        );
                    }
                }
                
                // Trigger auto-cancellation action
                do_action('btr_payment_auto_cancelled', $payment);
                
                BTR_Payment_Security::log_security_event('payment_auto_cancelled', [
                    'payment_id' => $payment->payment_id,
                    'payment_hash' => $payment->payment_hash,
                    'order_id' => $payment->order_id,
                    'amount' => $payment->amount,
                    'participant_email' => BTR_Payment_Security::mask_email($payment->participant_email)
                ]);
                
                // Send admin notification about auto-cancellation
                $this->send_admin_auto_cancellation_notification($payment);
            }
        }
        
        BTR_Payment_Security::log_security_event('cron_process_auto_cancellations_completed', [
            'cancelled_count' => $cancelled_count,
            'total_checked' => count($payments_to_cancel)
        ]);
    }
    
    public function ajax_process_auto_cancellations() {
        if (!wp_verify_nonce($_POST['nonce'], 'btr_cron_test')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $this->process_auto_cancellations();
        
        wp_send_json([
            'success' => true,
            'message' => 'Auto-cancellations processed'
        ]);
    }
    
    /**
     * Send admin notification for auto-cancelled payment
     * 
     * @param object $payment
     */
    private function send_admin_auto_cancellation_notification($payment) {
        $admin_email = get_option('admin_email');
        
        $subject = sprintf('[%s] Pagamento auto-cancellato - %s', 
            get_bloginfo('name'), 
            $payment->participant_name
        );
        
        $admin_message = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2 style='color: #6c757d;'>🗑️ Pagamento Auto-Cancellato</h2>
            <p><strong>Partecipante:</strong> {$payment->participant_name} ({$payment->participant_email})</p>
            <p><strong>Importo:</strong> €" . number_format($payment->amount, 2) . "</p>
            <p><strong>Tipo Pagamento:</strong> " . ucfirst($payment->payment_type) . "</p>
            <p><strong>Hash Pagamento:</strong> {$payment->payment_hash}</p>
            <p><strong>Ordine ID:</strong> {$payment->order_id}</p>
            <p><strong>Creato il:</strong> {$payment->created_at}</p>
            <p><strong>Cancellato il:</strong> " . current_time('mysql') . "</p>
            <hr>
            <p><small>Questo pagamento è stato automaticamente cancellato dopo il periodo di grazia configurato.</small></p>
        </body>
        </html>";
        
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];
        
        wp_mail($admin_email, $subject, $admin_message, $headers);
    }
    
    /**
     * Send admin notification for failed reminders
     * 
     * @param object $payment
     */
    private function send_admin_notification_failed_reminders($payment) {
        // Check if admin notifications are enabled
        $admin_notifications_enabled = get_option('btr_admin_notifications_enabled', true);
        if (!$admin_notifications_enabled) {
            return;
        }
        
        // Initialize email template manager
        $email_manager = new BTR_Email_Template_Manager();
        
        // Prepare admin email data
        $admin_email = get_option('admin_email');
        $admin_variables = [
            'participant_name' => $payment->participant_name,
            'participant_email' => $payment->participant_email,
            'amount' => number_format($payment->amount, 2),
            'currency' => $payment->currency,
            'payment_type' => $payment->payment_type,
            'payment_hash' => $payment->payment_hash,
            'reminder_count' => intval($payment->reminder_count) + 1,
            'order_id' => $payment->order_id,
            'created_at' => $payment->created_at,
            'admin_name' => 'Amministratore',
            'language' => 'it'
        ];
        
        // Create subject and basic HTML for admin notification
        $subject = sprintf('[%s] Pagamento non completato dopo 3 reminder - %s', 
            get_bloginfo('name'), 
            $payment->participant_name
        );
        
        $admin_message = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2 style='color: #dc3545;'>⚠️ Pagamento Non Completato</h2>
            <p><strong>Partecipante:</strong> {$payment->participant_name} ({$payment->participant_email})</p>
            <p><strong>Importo:</strong> €" . number_format($payment->amount, 2) . "</p>
            <p><strong>Tipo Pagamento:</strong> " . ucfirst($payment->payment_type) . "</p>
            <p><strong>Hash Pagamento:</strong> {$payment->payment_hash}</p>
            <p><strong>Ordine ID:</strong> {$payment->order_id}</p>
            <p><strong>Reminder Inviati:</strong> " . (intval($payment->reminder_count) + 1) . "</p>
            <p><strong>Creato il:</strong> {$payment->created_at}</p>
            <hr>
            <p><small>Questo pagamento ha raggiunto il limite massimo di reminder (3) senza essere completato.</small></p>
        </body>
        </html>";
        
        // Send admin notification
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];
        
        $sent = wp_mail($admin_email, $subject, $admin_message, $headers);
        
        // Log admin notification
        BTR_Payment_Security::log_security_event('admin_notification_sent', [
            'payment_id' => $payment->payment_id,
            'payment_hash' => $payment->payment_hash,
            'admin_email' => BTR_Payment_Security::mask_email($admin_email),
            'sent' => $sent
        ]);
        
        return $sent;
    }
    
    /**
     * Schedule auto-cancellation for unpaid orders
     * 
     * @param object $payment
     */
    private function schedule_auto_cancellation($payment) {
        // Check if auto-cancellation is enabled
        $auto_cancel_enabled = get_option('btr_auto_cancel_enabled', false);
        $auto_cancel_days = get_option('btr_auto_cancel_days', 14);
        
        if (!$auto_cancel_enabled) {
            return;
        }
        
        // Calculate cancellation date
        $cancel_date = date('Y-m-d H:i:s', strtotime("+{$auto_cancel_days} days"));
        
        // Update payment with cancellation schedule
        global $wpdb;
        $table_name = $this->db_manager->get_table_name();
        
        $wpdb->update(
            $table_name,
            [
                'auto_cancel_at' => $cancel_date,
                'updated_at' => current_time('mysql')
            ],
            ['payment_id' => $payment->payment_id],
            ['%s', '%s'],
            ['%d']
        );
        
        // Log auto-cancellation scheduling
        BTR_Payment_Security::log_security_event('auto_cancellation_scheduled', [
            'payment_id' => $payment->payment_id,
            'payment_hash' => $payment->payment_hash,
            'cancel_date' => $cancel_date,
            'days_delay' => $auto_cancel_days
        ]);
        
        // Trigger hook for other plugins
        do_action('btr_payment_auto_cancellation_scheduled', $payment, $cancel_date);
    }
    
    /**
     * Get reminder effectiveness metrics
     * 
     * @return array
     */
    public function get_reminder_effectiveness_metrics() {
        global $wpdb;
        $table_name = $this->db_manager->get_table_name();
        
        // Get reminder statistics for the last 30 days
        $stats = $wpdb->get_results("
            SELECT 
                reminder_count,
                payment_status,
                COUNT(*) as count,
                AVG(CASE WHEN payment_status = 'completed' THEN 1 ELSE 0 END) as conversion_rate
            FROM {$table_name}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND reminder_count > 0
            GROUP BY reminder_count, payment_status
            ORDER BY reminder_count, payment_status
        ");
        
        // Get overall metrics
        $overall_metrics = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_payments_with_reminders,
                SUM(CASE WHEN payment_status = 'completed' THEN 1 ELSE 0 END) as completed_after_reminders,
                AVG(reminder_count) as avg_reminders_per_payment,
                MAX(reminder_count) as max_reminders_sent
            FROM {$table_name}
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND reminder_count > 0
        ");
        
        return [
            'period' => 'Last 30 days',
            'detailed_stats' => $stats,
            'overall_metrics' => $overall_metrics,
            'effectiveness_by_reminder' => $this->calculate_effectiveness_by_reminder_number($stats)
        ];
    }
    
    /**
     * Calculate effectiveness by reminder number
     * 
     * @param array $stats
     * @return array
     */
    private function calculate_effectiveness_by_reminder_number($stats) {
        $effectiveness = [];
        
        foreach ($stats as $stat) {
            $reminder_num = $stat->reminder_count;
            if (!isset($effectiveness[$reminder_num])) {
                $effectiveness[$reminder_num] = [
                    'total' => 0,
                    'completed' => 0,
                    'conversion_rate' => 0
                ];
            }
            
            $effectiveness[$reminder_num]['total'] += $stat->count;
            if ($stat->payment_status === 'completed') {
                $effectiveness[$reminder_num]['completed'] += $stat->count;
            }
        }
        
        // Calculate conversion rates
        foreach ($effectiveness as $reminder_num => &$data) {
            if ($data['total'] > 0) {
                $data['conversion_rate'] = round(($data['completed'] / $data['total']) * 100, 2);
            }
        }
        
        return $effectiveness;
    }
    
    /**
     * Update deposit/balance payment tracking and order states
     */
    private function update_deposit_payment_tracking() {
        global $wpdb;
        $table_name = $this->db_manager->get_table_name();
        
        // Find completed deposit payments that need order state updates
        $completed_deposits = $wpdb->get_results("
            SELECT * FROM {$table_name}
            WHERE payment_status = 'completed'
            AND payment_type = 'deposit'
            AND metadata LIKE '%\"updated_order_state\":false%'
            OR metadata NOT LIKE '%\"updated_order_state\"%'
            LIMIT 10
        ");
        
        foreach ($completed_deposits as $deposit) {
            $order = wc_get_order($deposit->order_id);
            if (!$order) continue;
            
            // Update order status for paid deposit
            if ($order->get_status() !== 'deposit-paid') {
                $order->update_status('deposit-paid', 
                    sprintf(__('Deposito pagato il %s', 'born-to-ride-booking'), 
                        $deposit->paid_at ?: $deposit->updated_at)
                );
            }
            
            // Mark deposit tracking as updated
            $metadata = json_decode($deposit->metadata, true) ?: [];
            $metadata['updated_order_state'] = true;
            $metadata['order_state_updated_at'] = current_time('mysql');
            
            $wpdb->update(
                $table_name,
                ['metadata' => json_encode($metadata)],
                ['payment_id' => $deposit->payment_id],
                ['%s'],
                ['%d']
            );
        }
        
        // Find orders where both deposit and balance are completed
        $completed_orders = $wpdb->get_results("
            SELECT order_id, COUNT(*) as completed_count
            FROM {$table_name}
            WHERE payment_status = 'completed'
            AND payment_type IN ('deposit', 'balance')
            GROUP BY order_id
            HAVING completed_count = 2
        ");
        
        foreach ($completed_orders as $order_data) {
            $order = wc_get_order($order_data->order_id);
            if (!$order) continue;
            
            // Update order to completed if both deposit and balance are paid
            if (!in_array($order->get_status(), ['completed', 'processing'])) {
                $order->update_status('processing', 
                    __('Deposito e saldo completati - pronto per elaborazione', 'born-to-ride-booking')
                );
            }
            
            // Update order meta
            update_post_meta($order_data->order_id, '_btr_deposit_paid', true);
            update_post_meta($order_data->order_id, '_btr_balance_paid', true);
            update_post_meta($order_data->order_id, '_btr_payment_completed_at', current_time('mysql'));
        }
        
        BTR_Payment_Security::log_security_event('deposit_tracking_updated', [
            'deposits_processed' => count($completed_deposits),
            'orders_completed' => count($completed_orders)
        ]);
    }
    
    /**
     * Get dashboard metrics for admin
     * 
     * @return array
     */
    public function get_dashboard_metrics() {
        global $wpdb;
        $table_name = $this->db_manager->get_table_name();
        
        // Pending payments requiring reminders
        $pending_reminders = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$table_name}
            WHERE payment_status = 'pending'
            AND next_reminder_at IS NOT NULL
            AND next_reminder_at <= NOW()
        ");
        
        // Expired payments
        $expired_payments = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$table_name}
            WHERE payment_status = 'pending'
            AND expires_at IS NOT NULL
            AND expires_at <= NOW()
        ");
        
        // Payments scheduled for auto-cancellation
        $auto_cancel_scheduled = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$table_name}
            WHERE payment_status = 'pending'
            AND auto_cancel_at IS NOT NULL
            AND auto_cancel_at <= DATE_ADD(NOW(), INTERVAL 7 DAY)
        ");
        
        // Success rate today
        $today_stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_today,
                SUM(CASE WHEN payment_status = 'completed' THEN 1 ELSE 0 END) as completed_today
            FROM {$table_name}
            WHERE DATE(created_at) = CURDATE()
        ");
        
        $success_rate_today = $today_stats->total_today > 0 
            ? round(($today_stats->completed_today / $today_stats->total_today) * 100, 1)
            : 0;
        
        return [
            'pending_reminders' => $pending_reminders,
            'expired_payments' => $expired_payments,
            'auto_cancel_scheduled' => $auto_cancel_scheduled,
            'success_rate_today' => $success_rate_today,
            'total_payments_today' => $today_stats->total_today,
            'completed_today' => $today_stats->completed_today,
            'last_updated' => current_time('mysql')
        ];
    }
}

// Hook into cron schedules
add_filter('cron_schedules', ['BTR_Payment_Cron_Manager', 'register_cron_schedules']);