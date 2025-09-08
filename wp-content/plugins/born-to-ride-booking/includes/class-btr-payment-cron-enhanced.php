<?php
/**
 * Enhanced Payment Cron System for Born to Ride Booking
 * 
 * Gestione avanzata dei cron job per reminder pagamenti
 * integrata con la nuova tabella btr_order_shares
 * 
 * @package BornToRideBooking
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include dependencies
if (!class_exists('BTR_Database_Manager')) {
    require_once BTR_PLUGIN_DIR . 'includes/class-btr-database-manager.php';
}

if (!class_exists('BTR_Payment_Email_Manager')) {
    require_once BTR_PLUGIN_DIR . 'includes/class-btr-payment-email-manager.php';
}

/**
 * Enhanced Payment Cron System
 * 
 * Sistema cron avanzato per gestione automatica reminder pagamenti
 * con supporto batch processing, retry logic ed error handling robusto
 */
class BTR_Payment_Cron_Enhanced {
    
    /**
     * Hook del cron principale
     */
    const CRON_HOOK = 'btr_payment_reminders_enhanced';
    
    /**
     * Hook per batch processing
     */
    const BATCH_HOOK = 'btr_payment_batch_process';
    
    /**
     * Hook per cleanup
     */
    const CLEANUP_HOOK = 'btr_payment_cleanup';
    
    /**
     * Dimensione batch per processing
     */
    const BATCH_SIZE = 50;
    
    /**
     * Max tentativi per reminder falliti
     */
    const MAX_RETRY_ATTEMPTS = 3;
    
    /**
     * Database manager instance
     * @var BTR_Database_Manager
     */
    private $db_manager;
    
    /**
     * Email manager instance
     * @var BTR_Payment_Email_Manager
     */
    private $email_manager;
    
    /**
     * Singleton instance
     * @var BTR_Payment_Cron_Enhanced
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     * @return BTR_Payment_Cron_Enhanced
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
        $this->db_manager = BTR_Database_Manager::get_instance();
        $this->email_manager = BTR_Payment_Email_Manager::get_instance();
        
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Custom cron schedules
        add_filter('cron_schedules', [$this, 'add_cron_schedules']);
        
        // Main cron hooks
        add_action(self::CRON_HOOK, [$this, 'process_payment_reminders']);
        add_action(self::BATCH_HOOK, [$this, 'process_batch_reminders']);
        add_action(self::CLEANUP_HOOK, [$this, 'cleanup_old_data']);
        
        // Plugin lifecycle hooks
        register_activation_hook(BTR_PLUGIN_FILE, [$this, 'schedule_all_crons']);
        register_deactivation_hook(BTR_PLUGIN_FILE, [$this, 'unschedule_all_crons']);
        
        // Settings change hooks
        add_action('update_option_btr_enable_auto_reminders', [$this, 'handle_reminder_toggle'], 10, 3);
        add_action('update_option_btr_reminder_interval_hours', [$this, 'reschedule_on_interval_change'], 10, 3);
        
        // Admin hooks for manual testing
        if (defined('BTR_DEBUG') && BTR_DEBUG) {
            add_action('wp_ajax_btr_test_enhanced_cron', [$this, 'ajax_test_cron']);
            add_action('wp_ajax_btr_force_reminder_check', [$this, 'ajax_force_check']);
        }
        
        // Health check hook
        add_action('admin_init', [$this, 'maybe_auto_repair']);
    }
    
    /**
     * Add custom cron schedules
     * @param array $schedules
     * @return array
     */
    public function add_cron_schedules($schedules) {
        // Ogni 3 ore (più frequente per reminder tempestivi)
        $schedules['btr_three_hours'] = [
            'interval' => 3 * HOUR_IN_SECONDS,
            'display' => __('Ogni 3 ore', 'born-to-ride-booking')
        ];
        
        // Ogni 6 ore
        $schedules['btr_six_hours'] = [
            'interval' => 6 * HOUR_IN_SECONDS,
            'display' => __('Ogni 6 ore', 'born-to-ride-booking')
        ];
        
        // Ogni 30 minuti per batch processing
        $schedules['btr_thirty_minutes'] = [
            'interval' => 30 * MINUTE_IN_SECONDS,
            'display' => __('Ogni 30 minuti', 'born-to-ride-booking')
        ];
        
        // Daily per cleanup
        $schedules['daily'] = $schedules['daily'] ?? [
            'interval' => DAY_IN_SECONDS,
            'display' => __('Una volta al giorno', 'born-to-ride-booking')
        ];
        
        return $schedules;
    }
    
    /**
     * Schedule all cron jobs
     */
    public function schedule_all_crons() {
        // Main reminder processing (ogni 6 ore alle ore 6, 12, 18, 24)
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            $timezone = wp_timezone();
            $next_run = new DateTime('today 06:00', $timezone);
            
            // Se è già passata l'ora, prendi la prossima finestra
            $current = new DateTime('now', $timezone);
            if ($next_run <= $current) {
                $hour = (int) $current->format('H');
                if ($hour < 6) {
                    $next_run = new DateTime('today 06:00', $timezone);
                } elseif ($hour < 12) {
                    $next_run = new DateTime('today 12:00', $timezone);
                } elseif ($hour < 18) {
                    $next_run = new DateTime('today 18:00', $timezone);
                } else {
                    $next_run = new DateTime('tomorrow 06:00', $timezone);
                }
            }
            
            wp_schedule_event($next_run->getTimestamp(), 'btr_six_hours', self::CRON_HOOK);
            btr_debug_log('[BTR Enhanced Cron] Scheduled main cron for: ' . $next_run->format('Y-m-d H:i:s'));
        }
        
        // Batch processing (ogni 30 minuti)
        if (!wp_next_scheduled(self::BATCH_HOOK)) {
            wp_schedule_event(time() + 1800, 'btr_thirty_minutes', self::BATCH_HOOK);
            btr_debug_log('[BTR Enhanced Cron] Scheduled batch processing cron');
        }
        
        // Daily cleanup (alle 2:00 AM)
        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            $cleanup_time = new DateTime('tomorrow 02:00', wp_timezone());
            wp_schedule_event($cleanup_time->getTimestamp(), 'daily', self::CLEANUP_HOOK);
            btr_debug_log('[BTR Enhanced Cron] Scheduled cleanup cron for: ' . $cleanup_time->format('Y-m-d H:i:s'));
        }
    }
    
    /**
     * Unschedule all cron jobs
     */
    public function unschedule_all_crons() {
        $hooks = [self::CRON_HOOK, self::BATCH_HOOK, self::CLEANUP_HOOK];
        
        foreach ($hooks as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
                btr_debug_log("[BTR Enhanced Cron] Unscheduled: {$hook}");
            }
        }
    }
    
    /**
     * Main payment reminders processing
     * Orchestrator che gestisce tutti i tipi di reminder
     */
    public function process_payment_reminders() {
        // Verifica se i reminder automatici sono abilitati
        if (get_option('btr_enable_auto_reminders', '0') !== '1') {
            btr_debug_log('[BTR Enhanced Cron] Reminder automatici disabilitati, skip');
            return;
        }
        
        $start_time = microtime(true);
        btr_debug_log('[BTR Enhanced Cron] === INIZIO PROCESSO REMINDER ===');
        
        // Verifica salute del sistema
        if (!$this->check_system_health()) {
            btr_debug_log('[BTR Enhanced Cron] Sistema non in salute, skip processo');
            return;
        }
        
        $stats = [
            'processed' => 0,
            'sent' => 0,
            'failed' => 0,
            'scheduled' => 0,
            'expired' => 0
        ];
        
        try {
            // 1. Processa reminder programmati e pronti per invio
            $stats['sent'] += $this->process_scheduled_reminders();
            
            // 2. Crea nuovi reminder per pagamenti in scadenza
            $stats['scheduled'] += $this->schedule_upcoming_reminders();
            
            // 3. Gestisci pagamenti scaduti
            $stats['expired'] += $this->handle_expired_payments();
            
            // 4. Processa retry per reminder falliti
            $stats['sent'] += $this->process_failed_reminders();
            
            // 5. Aggiorna statistiche sistema
            $this->update_system_stats($stats);
            
        } catch (Exception $e) {
            btr_debug_log('[BTR Enhanced Cron] Errore durante processo: ' . $e->getMessage());
            $this->log_error('process_payment_reminders', $e);
        }
        
        $execution_time = microtime(true) - $start_time;
        btr_debug_log(sprintf(
            '[BTR Enhanced Cron] === FINE PROCESSO REMINDER === Tempo: %.2fs, Stats: %s',
            $execution_time,
            json_encode($stats)
        ));
        
        // Trigger action per integrazioni esterne
        do_action('btr_payment_reminders_processed', $stats, $execution_time);
    }
    
    /**
     * Process scheduled reminders ready to be sent
     * @return int Number of reminders sent
     */
    private function process_scheduled_reminders() {
        global $wpdb;
        
        btr_debug_log('[BTR Enhanced Cron] Processing scheduled reminders...');
        
        try {
            // Query per trovare pagamenti che necessitano reminder
            $shares_to_remind = $wpdb->get_results($wpdb->prepare("
                SELECT * FROM {$wpdb->prefix}btr_order_shares
                WHERE payment_status IN ('pending', 'processing')
                AND deleted_at IS NULL
                AND next_reminder_at IS NOT NULL
                AND next_reminder_at <= %s
                AND (reminder_count < %d OR reminder_count IS NULL)
                ORDER BY next_reminder_at ASC
                LIMIT %d
            ", current_time('mysql'), self::MAX_RETRY_ATTEMPTS, self::BATCH_SIZE));
            
            if (empty($shares_to_remind)) {
                btr_debug_log('[BTR Enhanced Cron] Nessun reminder programmato da inviare');
                return 0;
            }
            
            btr_debug_log(sprintf('[BTR Enhanced Cron] Trovati %d reminder da inviare', count($shares_to_remind)));
            
            $sent_count = 0;
            
            foreach ($shares_to_remind as $share) {
                if ($this->send_payment_reminder($share)) {
                    $sent_count++;
                    $this->update_reminder_status($share->id, 'sent');
                } else {
                    $this->update_reminder_status($share->id, 'failed');
                }
                
                // Pausa tra invii per non sovraccaricare email server
                if ($sent_count % 5 === 0) {
                    usleep(500000); // 0.5 secondi
                }
            }
            
            btr_debug_log(sprintf('[BTR Enhanced Cron] Inviati %d reminder su %d programmati', $sent_count, count($shares_to_remind)));
            
            return $sent_count;
            
        } catch (Exception $e) {
            btr_debug_log('[BTR Enhanced Cron] Errore processing scheduled reminders: ' . $e->getMessage());
            $this->log_error('process_scheduled_reminders', $e);
            return 0;
        }
    }
    
    /**
     * Schedule new reminders for payments nearing expiration
     * @return int Number of reminders scheduled
     */
    private function schedule_upcoming_reminders() {
        global $wpdb;
        
        btr_debug_log('[BTR Enhanced Cron] Scheduling upcoming reminders...');
        
        try {
            // Configurazione reminder (giorni di anticipo)
            $reminder_days = [
                7,  // 7 giorni prima
                3,  // 3 giorni prima  
                1   // 1 giorno prima
            ];
            
            $scheduled_count = 0;
            
            foreach ($reminder_days as $days_before) {
                $target_date = date('Y-m-d', strtotime("+{$days_before} days"));
                
                // Trova pagamenti che scadono nella data target senza reminder già schedulati
                $shares_needing_reminder = $wpdb->get_results($wpdb->prepare("
                    SELECT s.* FROM {$wpdb->prefix}btr_order_shares s
                    WHERE s.payment_status IN ('pending', 'processing')
                    AND s.deleted_at IS NULL
                    AND DATE(s.token_expires_at) = %s
                    AND (s.next_reminder_at IS NULL OR s.next_reminder_at > s.token_expires_at)
                    AND s.reminder_count < %d
                    LIMIT %d
                ", $target_date, self::MAX_RETRY_ATTEMPTS, self::BATCH_SIZE));
                
                foreach ($shares_needing_reminder as $share) {
                    // Calcola orario reminder (ore 9:00 del giorno corrente)
                    $reminder_time = date('Y-m-d 09:00:00');
                    
                    // Aggiorna next_reminder_at
                    $updated = $wpdb->update(
                        $wpdb->prefix . 'btr_order_shares',
                        ['next_reminder_at' => $reminder_time],
                        ['id' => $share->id],
                        ['%s'],
                        ['%d']
                    );
                    
                    if ($updated) {
                        $scheduled_count++;
                        btr_debug_log(sprintf(
                            '[BTR Enhanced Cron] Schedulato reminder per share ID %d, scadenza %s',
                            $share->id,
                            $share->token_expires_at
                        ));
                    }
                }
            }
            
            btr_debug_log(sprintf('[BTR Enhanced Cron] Schedulati %d nuovi reminder', $scheduled_count));
            
            return $scheduled_count;
            
        } catch (Exception $e) {
            btr_debug_log('[BTR Enhanced Cron] Errore scheduling upcoming reminders: ' . $e->getMessage());
            $this->log_error('schedule_upcoming_reminders', $e);
            return 0;
        }
    }
    
    /**
     * Handle expired payments
     * @return int Number of payments marked as expired
     */
    private function handle_expired_payments() {
        global $wpdb;
        
        btr_debug_log('[BTR Enhanced Cron] Handling expired payments...');
        
        try {
            // Trova pagamenti scaduti
            $expired_shares = $wpdb->get_results($wpdb->prepare("
                SELECT * FROM {$wpdb->prefix}btr_order_shares
                WHERE payment_status IN ('pending', 'processing')
                AND deleted_at IS NULL
                AND token_expires_at < %s
                LIMIT %d
            ", current_time('mysql'), self::BATCH_SIZE));
            
            if (empty($expired_shares)) {
                btr_debug_log('[BTR Enhanced Cron] Nessun pagamento scaduto trovato');
                return 0;
            }
            
            $expired_count = 0;
            
            foreach ($expired_shares as $share) {
                // Aggiorna stato a expired
                $updated = $wpdb->update(
                    $wpdb->prefix . 'btr_order_shares',
                    [
                        'payment_status' => 'expired',
                        'failed_at' => current_time('mysql'),
                        'failure_reason' => 'Payment expired: token_expires_at exceeded'
                    ],
                    ['id' => $share->id],
                    ['%s', '%s', '%s'],
                    ['%d']
                );
                
                if ($updated) {
                    $expired_count++;
                    
                    // Trigger action per notifiche esterne
                    do_action('btr_payment_share_expired', $share->id, $share);
                    
                    // Invia notifica admin se configurato
                    if (get_option('btr_notify_admin_expired', '1') === '1') {
                        $this->send_admin_expired_notification($share);
                    }
                    
                    btr_debug_log(sprintf(
                        '[BTR Enhanced Cron] Marcato come scaduto share ID %d (Order: %d)',
                        $share->id,
                        $share->order_id
                    ));
                }
            }
            
            btr_debug_log(sprintf('[BTR Enhanced Cron] Marcati come scaduti %d pagamenti', $expired_count));
            
            return $expired_count;
            
        } catch (Exception $e) {
            btr_debug_log('[BTR Enhanced Cron] Errore handling expired payments: ' . $e->getMessage());
            $this->log_error('handle_expired_payments', $e);
            return 0;
        }
    }
    
    /**
     * Process failed reminders for retry
     * @return int Number of reminders retried
     */
    private function process_failed_reminders() {
        global $wpdb;
        
        btr_debug_log('[BTR Enhanced Cron] Processing failed reminders...');
        
        try {
            // Trova reminder falliti che possono essere ritentati
            // Utilizza exponential backoff: 1h, 4h, 12h
            $retry_intervals = [
                1 => 1,    // 1 hour
                2 => 4,    // 4 hours  
                3 => 12    // 12 hours
            ];
            
            $retried_count = 0;
            
            foreach ($retry_intervals as $attempt => $hours_delay) {
                $retry_after = date('Y-m-d H:i:s', strtotime("-{$hours_delay} hours"));
                
                $failed_shares = $wpdb->get_results($wpdb->prepare("
                    SELECT * FROM {$wpdb->prefix}btr_order_shares
                    WHERE payment_status IN ('pending', 'processing')
                    AND deleted_at IS NULL
                    AND reminder_count = %d
                    AND reminder_sent_at IS NOT NULL
                    AND reminder_sent_at <= %s
                    AND (next_reminder_at IS NULL OR next_reminder_at <= %s)
                    LIMIT %d
                ", $attempt, $retry_after, current_time('mysql'), 20));
                
                foreach ($failed_shares as $share) {
                    if ($this->send_payment_reminder($share, true)) {
                        $retried_count++;
                        btr_debug_log(sprintf(
                            '[BTR Enhanced Cron] Retry riuscito per share ID %d (tentativo %d)',
                            $share->id,
                            $attempt + 1
                        ));
                    }
                    
                    // Pausa tra retry
                    usleep(250000); // 0.25 secondi
                }
            }
            
            btr_debug_log(sprintf('[BTR Enhanced Cron] Processati %d retry di reminder falliti', $retried_count));
            
            return $retried_count;
            
        } catch (Exception $e) {
            btr_debug_log('[BTR Enhanced Cron] Errore processing failed reminders: ' . $e->getMessage());
            $this->log_error('process_failed_reminders', $e);
            return 0;
        }
    }
    
    /**
     * Send payment reminder for a specific share
     * @param object $share Share data from database
     * @param bool $is_retry Whether this is a retry attempt
     * @return bool Success status
     */
    private function send_payment_reminder($share, $is_retry = false) {
        try {
            // Prepara dati per email
            $email_data = [
                'share_id' => $share->id,
                'order_id' => $share->order_id,
                'participant_name' => $share->participant_name,
                'participant_email' => $share->participant_email,
                'amount_assigned' => $share->amount_assigned,
                'currency' => $share->currency,
                'payment_link' => $share->payment_link,
                'token_expires_at' => $share->token_expires_at,
                'reminder_count' => $share->reminder_count ?? 0,
                'is_retry' => $is_retry
            ];
            
            // Determina tipo di reminder in base alla scadenza
            $days_to_expiry = $this->calculate_days_to_expiry($share->token_expires_at);
            $reminder_type = $this->determine_reminder_type($days_to_expiry, $is_retry);
            
            // Invia email tramite email manager
            $sent = $this->email_manager->send_payment_reminder_enhanced(
                $share->participant_email,
                $reminder_type,
                $email_data
            );
            
            if ($sent) {
                btr_debug_log(sprintf(
                    '[BTR Enhanced Cron] Reminder inviato: Share ID %d, Type: %s, Email: %s',
                    $share->id,
                    $reminder_type,
                    $share->participant_email
                ));
                
                // Trigger action per tracking
                do_action('btr_payment_reminder_sent', $share->id, $reminder_type, $email_data);
                
                return true;
            } else {
                btr_debug_log(sprintf(
                    '[BTR Enhanced Cron] Fallimento invio reminder: Share ID %d, Email: %s',
                    $share->id,
                    $share->participant_email
                ));
                
                return false;
            }
            
        } catch (Exception $e) {
            btr_debug_log(sprintf(
                '[BTR Enhanced Cron] Errore invio reminder share ID %d: %s',
                $share->id,
                $e->getMessage()
            ));
            
            $this->log_error('send_payment_reminder', $e, ['share_id' => $share->id]);
            return false;
        }
    }
    
    /**
     * Update reminder status in database
     * @param int $share_id Share ID
     * @param string $status New status (sent|failed)
     */
    private function update_reminder_status($share_id, $status) {
        global $wpdb;
        
        $current_time = current_time('mysql');
        $update_data = [];
        
        if ($status === 'sent') {
            $update_data = [
                'reminder_sent_at' => $current_time,
                'reminder_count' => new WP_Query("reminder_count + 1"), // Increment
                'next_reminder_at' => null // Reset per evitare reinvii
            ];
            $format = ['%s', '%s', '%s'];
        } else {
            $update_data = [
                'reminder_count' => new WP_Query("reminder_count + 1"), // Increment anche per failed
                'failed_at' => $current_time,
                'failure_reason' => 'Reminder email failed to send'
            ];
            $format = ['%s', '%s', '%s'];
        }
        
        // Gestisci increment manualmente per reminder_count
        if ($status === 'sent') {
            $wpdb->query($wpdb->prepare("
                UPDATE {$wpdb->prefix}btr_order_shares 
                SET reminder_sent_at = %s,
                    reminder_count = COALESCE(reminder_count, 0) + 1,
                    next_reminder_at = NULL,
                    updated_at = %s
                WHERE id = %d
            ", $current_time, $current_time, $share_id));
        } else {
            $wpdb->query($wpdb->prepare("
                UPDATE {$wpdb->prefix}btr_order_shares 
                SET reminder_count = COALESCE(reminder_count, 0) + 1,
                    failed_at = %s,
                    failure_reason = %s,
                    updated_at = %s
                WHERE id = %d
            ", $current_time, 'Reminder email failed to send', $current_time, $share_id));
        }
    }
    
    /**
     * Calculate days to expiry
     * @param string $expires_at Expiry datetime string
     * @return int Days to expiry (negative if expired)
     */
    private function calculate_days_to_expiry($expires_at) {
        $expiry_time = strtotime($expires_at);
        $current_time = current_time('timestamp');
        $diff_seconds = $expiry_time - $current_time;
        
        return floor($diff_seconds / DAY_IN_SECONDS);
    }
    
    /**
     * Determine reminder type based on expiry and retry status
     * @param int $days_to_expiry Days to expiry
     * @param bool $is_retry Whether this is a retry
     * @return string Reminder type
     */
    private function determine_reminder_type($days_to_expiry, $is_retry = false) {
        if ($days_to_expiry < 0) {
            return 'overdue';
        } elseif ($days_to_expiry === 0) {
            return 'expires_today';
        } elseif ($days_to_expiry === 1) {
            return 'expires_tomorrow';
        } elseif ($days_to_expiry <= 3) {
            return 'expires_soon';
        } elseif ($is_retry) {
            return 'payment_reminder_retry';
        } else {
            return 'payment_reminder';
        }
    }
    
    /**
     * Send admin notification for expired payment
     * @param object $share Share data
     */
    private function send_admin_expired_notification($share) {
        $admin_email = get_option('admin_email');
        $subject = sprintf(
            __('[%s] Pagamento scaduto - Ordine #%d', 'born-to-ride-booking'),
            get_bloginfo('name'),
            $share->order_id
        );
        
        $message = sprintf(
            __("Un pagamento è scaduto:\n\nOrdine: #%d\nPartecipante: %s\nEmail: %s\nImporto: %s %s\nScadenza: %s\n\nControlla il pannello admin per maggiori dettagli.", 'born-to-ride-booking'),
            $share->order_id,
            $share->participant_name,
            $share->participant_email,
            $share->amount_assigned,
            $share->currency,
            $share->token_expires_at
        );
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Check system health before processing
     * @return bool System is healthy
     */
    private function check_system_health() {
        global $wpdb;
        
        // Verifica esistenza tabella principale
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}btr_order_shares'") === $wpdb->prefix . 'btr_order_shares';
        
        if (!$table_exists) {
            btr_debug_log('[BTR Enhanced Cron] Tabella btr_order_shares non esiste');
            return false;
        }
        
        // Verifica che email manager sia disponibile
        if (!$this->email_manager || !method_exists($this->email_manager, 'send_payment_reminder_enhanced')) {
            btr_debug_log('[BTR Enhanced Cron] Email manager non disponibile o metodo mancante');
            return false;
        }
        
        // Verifica configurazione WordPress mail
        if (!function_exists('wp_mail')) {
            btr_debug_log('[BTR Enhanced Cron] Funzione wp_mail non disponibile');
            return false;
        }
        
        return true;
    }
    
    /**
     * Update system statistics
     * @param array $stats Processing statistics
     */
    private function update_system_stats($stats) {
        $current_stats = get_option('btr_cron_stats', []);
        
        $new_stats = [
            'last_run' => current_time('mysql'),
            'last_run_stats' => $stats,
            'total_runs' => ($current_stats['total_runs'] ?? 0) + 1,
            'total_sent' => ($current_stats['total_sent'] ?? 0) + $stats['sent'],
            'total_failed' => ($current_stats['total_failed'] ?? 0) + $stats['failed'],
            'total_scheduled' => ($current_stats['total_scheduled'] ?? 0) + $stats['scheduled'],
            'total_expired' => ($current_stats['total_expired'] ?? 0) + $stats['expired']
        ];
        
        update_option('btr_cron_stats', $new_stats);
    }
    
    /**
     * Batch processing cron (runs more frequently)
     */
    public function process_batch_reminders() {
        // Verifica se ci sono task in coda ad alta priorità
        if (get_option('btr_batch_processing_enabled', '1') !== '1') {
            return;
        }
        
        // Processing leggero per task urgenti
        $urgent_count = $this->process_urgent_reminders();
        
        if ($urgent_count > 0) {
            btr_debug_log(sprintf('[BTR Enhanced Cron] Batch processing: %d reminder urgenti processati', $urgent_count));
        }
    }
    
    /**
     * Process urgent reminders (expiring today)
     * @return int Number of urgent reminders processed
     */
    private function process_urgent_reminders() {
        global $wpdb;
        
        try {
            // Trova pagamenti che scadono oggi e non hanno avuto reminder nelle ultime 2 ore
            $urgent_shares = $wpdb->get_results($wpdb->prepare("
                SELECT * FROM {$wpdb->prefix}btr_order_shares
                WHERE payment_status IN ('pending', 'processing')
                AND deleted_at IS NULL
                AND DATE(token_expires_at) = %s
                AND (reminder_sent_at IS NULL OR reminder_sent_at < DATE_SUB(NOW(), INTERVAL 2 HOUR))
                AND reminder_count < %d
                ORDER BY token_expires_at ASC
                LIMIT 10
            ", current_time('Y-m-d'), self::MAX_RETRY_ATTEMPTS));
            
            $sent_count = 0;
            
            foreach ($urgent_shares as $share) {
                if ($this->send_payment_reminder($share)) {
                    $sent_count++;
                    $this->update_reminder_status($share->id, 'sent');
                    
                    // Pausa più lunga per reminder urgenti
                    sleep(1);
                }
            }
            
            return $sent_count;
            
        } catch (Exception $e) {
            btr_debug_log('[BTR Enhanced Cron] Errore processing urgent reminders: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Daily cleanup of old data
     */
    public function cleanup_old_data() {
        global $wpdb;
        
        btr_debug_log('[BTR Enhanced Cron] === INIZIO CLEANUP GIORNALIERO ===');
        
        try {
            $cleaned_stats = [
                'old_shares_soft_deleted' => 0,
                'expired_tokens_cleaned' => 0,
                'old_logs_removed' => 0
            ];
            
            // 1. Soft delete shares molto vecchie (90+ giorni) già pagate o scadute
            $old_shares_deleted = $wpdb->query($wpdb->prepare("
                UPDATE {$wpdb->prefix}btr_order_shares 
                SET deleted_at = %s
                WHERE deleted_at IS NULL
                AND payment_status IN ('paid', 'expired', 'cancelled', 'refunded')
                AND created_at < DATE_SUB(%s, INTERVAL 90 DAY)
            ", current_time('mysql'), current_time('mysql')));
            
            $cleaned_stats['old_shares_soft_deleted'] = $old_shares_deleted;
            
            // 2. Reset token scaduti per evitare confusion
            $expired_tokens = $wpdb->query($wpdb->prepare("
                UPDATE {$wpdb->prefix}btr_order_shares
                SET payment_token = NULL,
                    payment_link = NULL
                WHERE payment_status NOT IN ('paid', 'processing')
                AND token_expires_at < DATE_SUB(%s, INTERVAL 7 DAY)
                AND payment_token IS NOT NULL
            ", current_time('mysql')));
            
            $cleaned_stats['expired_tokens_cleaned'] = $expired_tokens;
            
            // 3. Cleanup opzioni temporanee
            $wpdb->query("DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE 'btr_temp_%' AND autoload = 'no'");
            
            // 4. Cleanup transients scaduti
            $wpdb->query($wpdb->prepare("
                DELETE FROM {$wpdb->prefix}options 
                WHERE option_name LIKE '_transient_timeout_btr_%' 
                AND option_value < %d
            ", time()));
            
            // Aggiorna statistiche cleanup
            update_option('btr_last_cleanup', [
                'date' => current_time('mysql'),
                'stats' => $cleaned_stats
            ]);
            
            btr_debug_log(sprintf(
                '[BTR Enhanced Cron] === CLEANUP COMPLETATO === Stats: %s',
                json_encode($cleaned_stats)
            ));
            
        } catch (Exception $e) {
            btr_debug_log('[BTR Enhanced Cron] Errore durante cleanup: ' . $e->getMessage());
            $this->log_error('cleanup_old_data', $e);
        }
    }
    
    /**
     * Handle reminder toggle setting change
     */
    public function handle_reminder_toggle($old_value, $new_value, $option) {
        if ($new_value === '1' && $old_value !== '1') {
            // Attiva tutti i cron
            $this->schedule_all_crons();
            btr_debug_log('[BTR Enhanced Cron] Reminder automatici attivati');
        } elseif ($new_value !== '1' && $old_value === '1') {
            // Disattiva tutti i cron
            $this->unschedule_all_crons();
            btr_debug_log('[BTR Enhanced Cron] Reminder automatici disattivati');
        }
    }
    
    /**
     * Reschedule when interval changes
     */
    public function reschedule_on_interval_change($old_value, $new_value, $option) {
        if ($old_value !== $new_value) {
            btr_debug_log('[BTR Enhanced Cron] Intervallo reminder cambiato, rescheduling...');
            $this->unschedule_all_crons();
            $this->schedule_all_crons();
        }
    }
    
    /**
     * Auto-repair system if issues detected
     */
    public function maybe_auto_repair() {
        // Esegui check salute una volta al giorno
        $last_health_check = get_option('btr_last_health_check', 0);
        if ((time() - $last_health_check) < DAY_IN_SECONDS) {
            return;
        }
        
        update_option('btr_last_health_check', time());
        
        // Verifica se i cron sono schedulati
        if (get_option('btr_enable_auto_reminders', '0') === '1') {
            $main_scheduled = wp_next_scheduled(self::CRON_HOOK);
            $batch_scheduled = wp_next_scheduled(self::BATCH_HOOK);
            
            if (!$main_scheduled || !$batch_scheduled) {
                btr_debug_log('[BTR Enhanced Cron] Auto-repair: Re-scheduling missing cron jobs');
                $this->schedule_all_crons();
            }
        }
    }
    
    /**
     * Log error with context
     * @param string $function Function name where error occurred
     * @param Exception $exception Exception object
     * @param array $context Additional context data
     */
    private function log_error($function, $exception, $context = []) {
        $error_data = [
            'function' => $function,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'context' => $context,
            'timestamp' => current_time('mysql')
        ];
        
        // Log nel debug log WordPress
        error_log('[BTR Enhanced Cron Error] ' . json_encode($error_data));
        
        // Salva in opzione per review admin
        $errors = get_option('btr_cron_errors', []);
        $errors[] = $error_data;
        
        // Mantieni solo ultimi 50 errori
        if (count($errors) > 50) {
            $errors = array_slice($errors, -50);
        }
        
        update_option('btr_cron_errors', $errors);
    }
    
    /**
     * AJAX handler for manual cron testing
     */
    public function ajax_test_cron() {
        check_ajax_referer('btr_test_cron', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $start_time = microtime(true);
        
        // Force run main process
        $this->process_payment_reminders();
        
        $execution_time = microtime(true) - $start_time;
        
        wp_send_json_success([
            'message' => 'Cron test completato',
            'execution_time' => round($execution_time, 2),
            'stats' => get_option('btr_cron_stats', []),
            'next_run' => wp_next_scheduled(self::CRON_HOOK)
        ]);
    }
    
    /**
     * AJAX handler for force reminder check
     */
    public function ajax_force_check() {
        check_ajax_referer('btr_force_check', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        // Force check urgent reminders
        $urgent_count = $this->process_urgent_reminders();
        
        wp_send_json_success([
            'message' => 'Check forzato completato',
            'urgent_processed' => $urgent_count
        ]);
    }
    
    /**
     * Get comprehensive system status
     * @return array System status data
     */
    public static function get_system_status() {
        $instance = self::get_instance();
        
        return [
            'cron_enabled' => get_option('btr_enable_auto_reminders', '0') === '1',
            'main_cron_scheduled' => wp_next_scheduled(self::CRON_HOOK),
            'batch_cron_scheduled' => wp_next_scheduled(self::BATCH_HOOK),
            'cleanup_cron_scheduled' => wp_next_scheduled(self::CLEANUP_HOOK),
            'last_run' => get_option('btr_cron_stats', [])['last_run'] ?? null,
            'last_cleanup' => get_option('btr_last_cleanup', [])['date'] ?? null,
            'system_health' => $instance->check_system_health(),
            'stats' => get_option('btr_cron_stats', []),
            'recent_errors' => array_slice(get_option('btr_cron_errors', []), -5)
        ];
    }
}

// Initialize enhanced cron system
add_action('plugins_loaded', function() {
    if (class_exists('BTR_Database_Manager') && class_exists('BTR_Payment_Email_Manager')) {
        BTR_Payment_Cron_Enhanced::get_instance();
    }
});