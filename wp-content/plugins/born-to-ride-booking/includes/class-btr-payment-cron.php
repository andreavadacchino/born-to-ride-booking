<?php
/**
 * Gestione cron job per reminder pagamenti
 * 
 * @package BornToRideBooking
 * @since 1.0.98
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Payment_Cron {
    
    /**
     * Hook del cron
     */
    const CRON_HOOK = 'btr_payment_reminders_cron';
    
    /**
     * Constructor
     */
    public function __construct() {
        // Registra schedule
        add_filter('cron_schedules', [$this, 'add_cron_schedules']);
        
        // Hook per esecuzione cron
        add_action(self::CRON_HOOK, [$this, 'process_payment_reminders']);
        
        // Attivazione/disattivazione plugin
        register_activation_hook(BTR_PLUGIN_FILE, [$this, 'schedule_cron']);
        register_deactivation_hook(BTR_PLUGIN_FILE, [$this, 'unschedule_cron']);
        
        // Admin actions
        add_action('update_option_btr_enable_auto_reminders', [$this, 'handle_reminder_toggle'], 10, 3);
        
        // Test cron (solo in debug mode)
        if (defined('BTR_DEBUG') && BTR_DEBUG) {
            add_action('admin_init', [$this, 'maybe_test_cron']);
        }
    }
    
    /**
     * Aggiunge schedule personalizzati
     */
    public function add_cron_schedules($schedules) {
        // Ogni 6 ore
        $schedules['btr_six_hours'] = [
            'interval' => 6 * HOUR_IN_SECONDS,
            'display' => __('Ogni 6 ore', 'born-to-ride-booking')
        ];
        
        // Due volte al giorno
        $schedules['btr_twice_daily'] = [
            'interval' => 12 * HOUR_IN_SECONDS,
            'display' => __('Due volte al giorno', 'born-to-ride-booking')
        ];
        
        return $schedules;
    }
    
    /**
     * Schedula cron job
     */
    public function schedule_cron() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            // Schedula alle 9:00 ora locale con timezone WordPress
            $timezone = wp_timezone();
            $datetime = new DateTime('today 9:00am', $timezone);
            $timestamp = $datetime->getTimestamp();
            
            // Se è già passata l'ora, schedula per domani
            if ($timestamp < current_time('timestamp')) {
                $datetime->modify('+1 day');
                $timestamp = $datetime->getTimestamp();
            }
            
            $scheduled = wp_schedule_event($timestamp, 'btr_twice_daily', self::CRON_HOOK);
            
            if ($scheduled === false) {
                btr_debug_log('[BTR Cron] Errore durante scheduling cron job');
            } else {
                btr_debug_log('[BTR Cron] Cron job schedulato con successo per: ' . 
                    date_i18n('d/m/Y H:i:s', $timestamp));
            }
        }
    }
    
    /**
     * Rimuove cron job
     */
    public function unschedule_cron() {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }
    
    /**
     * Processa reminder pagamenti
     */
    public function process_payment_reminders() {
        // Verifica se i reminder automatici sono abilitati
        if (get_option('btr_enable_auto_reminders', '0') !== '1') {
            return;
        }
        
        // Log inizio processo
        if (defined('BTR_DEBUG') && BTR_DEBUG) {
            error_log('[BTR Cron] Inizio processo reminder pagamenti: ' . current_time('mysql'));
        }
        
        // Processa reminder programmati
        $this->process_scheduled_reminders();
        
        // Crea nuovi reminder per pagamenti in scadenza
        $this->create_upcoming_reminders();
        
        // Gestisci pagamenti scaduti
        $this->handle_expired_payments();
        
        // Pulizia vecchi reminder
        $this->cleanup_old_reminders();
        
        // Log fine processo
        if (defined('BTR_DEBUG') && BTR_DEBUG) {
            error_log('[BTR Cron] Fine processo reminder pagamenti: ' . current_time('mysql'));
        }
    }
    
    /**
     * Processa reminder programmati
     */
    private function process_scheduled_reminders() {
        global $wpdb;
        
        // Verifica esistenza tabella e auto-installa se necessario
        $table_name = $wpdb->prefix . 'btr_payment_reminders';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
            btr_debug_log('[BTR Cron] Tabella payment_reminders non trovata. Tentativo auto-installazione...');
            
            // Tenta auto-installazione
            if (class_exists('BTR_Database_Auto_Installer')) {
                $installer = BTR_Database_Auto_Installer::get_instance();
                if ($installer->check_and_install_tables()) {
                    btr_debug_log('[BTR Cron] Tabelle create con successo.');
                } else {
                    btr_debug_log('[BTR Cron] Impossibile creare tabelle automaticamente.');
                    return;
                }
            } else {
                return;
            }
        }
        
        try {
            // Recupera reminder da inviare
            $reminders = $wpdb->get_results($wpdb->prepare("
                SELECT * FROM {$wpdb->prefix}btr_payment_reminders
                WHERE status = 'pending'
                AND scheduled_for <= %s
                AND attempts < 3
                ORDER BY scheduled_for ASC
                LIMIT 50
            ", current_time('mysql')));
            
            if (empty($reminders)) {
                return;
            }
        } catch (Exception $e) {
            btr_debug_log('[BTR Cron] Errore query reminder: ' . $e->getMessage());
            return;
        }
        
        $email_manager = BTR_Payment_Email_Manager::get_instance();
        $sent_count = 0;
        $failed_count = 0;
        
        foreach ($reminders as $reminder) {
            // Invia reminder
            $sent = $email_manager->send_scheduled_reminder($reminder->id);
            
            if ($sent) {
                $sent_count++;
            } else {
                $failed_count++;
            }
            
            // Pausa tra invii per non sovraccaricare
            if (($sent_count + $failed_count) % 10 === 0) {
                sleep(1);
            }
        }
        
        // Log risultati
        if (defined('BTR_DEBUG') && BTR_DEBUG && ($sent_count > 0 || $failed_count > 0)) {
            error_log(sprintf(
                '[BTR Cron] Reminder processati: %d inviati, %d falliti',
                $sent_count,
                $failed_count
            ));
        }
    }
    
    /**
     * Crea reminder per pagamenti in scadenza
     */
    private function create_upcoming_reminders() {
        global $wpdb;
        
        // Verifica esistenza tabelle
        $payments_table = $wpdb->prefix . 'btr_group_payments';
        $reminders_table = $wpdb->prefix . 'btr_payment_reminders';
        
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $payments_table)) !== $payments_table ||
            $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $reminders_table)) !== $reminders_table) {
            btr_debug_log('[BTR Cron] Tabelle necessarie non trovate. Eseguire update database.');
            return;
        }
        
        // Giorni anticipo per reminder
        $days_before = get_option('btr_reminder_days_before', 7);
        $reminder_date = date('Y-m-d', strtotime("+{$days_before} days"));
        
        try {
            // Trova pagamenti in scadenza senza reminder
            $payments = $wpdb->get_results($wpdb->prepare("
                SELECT p.* 
                FROM {$wpdb->prefix}btr_group_payments p
                LEFT JOIN {$wpdb->prefix}btr_payment_reminders r 
                    ON p.payment_id = r.payment_id 
                    AND r.reminder_type = 'payment_due'
                    AND r.status IN ('pending', 'sent')
                WHERE p.payment_status = 'pending'
                AND DATE(p.expires_at) = %s
                AND r.id IS NULL
                LIMIT 100
            ", $reminder_date));
            
            if (empty($payments)) {
                return;
            }
        } catch (Exception $e) {
            btr_debug_log('[BTR Cron] Errore query pagamenti in scadenza: ' . $e->getMessage());
            return;
        }
        
        $created_count = 0;
        
        foreach ($payments as $payment) {
            // Crea reminder
            $inserted = $wpdb->insert(
                $wpdb->prefix . 'btr_payment_reminders',
                [
                    'payment_id' => $payment->payment_id,
                    'reminder_type' => 'payment_due',
                    'scheduled_for' => date('Y-m-d 09:00:00'), // Invia alle 9:00
                    'status' => 'pending'
                ],
                ['%d', '%s', '%s', '%s']
            );
            
            if ($inserted) {
                $created_count++;
            }
        }
        
        // Log creazione
        if (defined('BTR_DEBUG') && BTR_DEBUG && $created_count > 0) {
            error_log(sprintf(
                '[BTR Cron] Creati %d nuovi reminder per pagamenti in scadenza il %s',
                $created_count,
                $reminder_date
            ));
        }
    }
    
    /**
     * Gestisce pagamenti scaduti
     */
    private function handle_expired_payments() {
        global $wpdb;
        
        // Verifica esistenza tabella
        $table_name = $wpdb->prefix . 'btr_group_payments';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
            btr_debug_log('[BTR Cron] Tabella group_payments non trovata.');
            return;
        }
        
        try {
            // Trova pagamenti scaduti
            $expired_payments = $wpdb->get_results($wpdb->prepare("
                SELECT * FROM {$wpdb->prefix}btr_group_payments
                WHERE payment_status = 'pending'
                AND expires_at < %s
                LIMIT 50
            ", current_time('mysql')));
            
            if (empty($expired_payments)) {
                return;
            }
        } catch (Exception $e) {
            btr_debug_log('[BTR Cron] Errore query pagamenti scaduti: ' . $e->getMessage());
            return;
        }
        
        $updated_count = 0;
        
        foreach ($expired_payments as $payment) {
            // Aggiorna stato a expired
            $updated = $wpdb->update(
                $wpdb->prefix . 'btr_group_payments',
                ['payment_status' => 'expired'],
                ['payment_id' => $payment->payment_id],
                ['%s'],
                ['%d']
            );
            
            if ($updated) {
                $updated_count++;
                
                // Trigger action per altre integrazioni
                do_action('btr_payment_expired', $payment->payment_id, $payment);
                
                // Verifica se inviare reminder scaduto
                $send_overdue = get_option('btr_send_overdue_reminders', '1');
                if ($send_overdue === '1') {
                    $this->create_overdue_reminder($payment->payment_id);
                }
            }
        }
        
        // Log aggiornamenti
        if (defined('BTR_DEBUG') && BTR_DEBUG && $updated_count > 0) {
            error_log(sprintf(
                '[BTR Cron] Aggiornati %d pagamenti scaduti',
                $updated_count
            ));
        }
    }
    
    /**
     * Crea reminder per pagamento scaduto
     */
    private function create_overdue_reminder($payment_id) {
        global $wpdb;
        
        // Verifica se esiste già un reminder overdue
        $exists = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}btr_payment_reminders
            WHERE payment_id = %d
            AND reminder_type = 'payment_overdue'
            AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        ", $payment_id));
        
        if ($exists > 0) {
            return;
        }
        
        // Crea reminder overdue
        $wpdb->insert(
            $wpdb->prefix . 'btr_payment_reminders',
            [
                'payment_id' => $payment_id,
                'reminder_type' => 'payment_overdue',
                'scheduled_for' => current_time('mysql'),
                'status' => 'pending'
            ],
            ['%d', '%s', '%s', '%s']
        );
    }
    
    /**
     * Pulizia vecchi reminder
     */
    private function cleanup_old_reminders() {
        global $wpdb;
        
        // Verifica esistenza tabella
        $table_name = $wpdb->prefix . 'btr_payment_reminders';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
            return;
        }
        
        try {
            // Rimuovi reminder più vecchi di 90 giorni
            $deleted = $wpdb->query($wpdb->prepare("
                DELETE FROM {$wpdb->prefix}btr_payment_reminders
                WHERE created_at < DATE_SUB(%s, INTERVAL 90 DAY)
                AND status IN ('sent', 'failed', 'cancelled')
            ", current_time('mysql')));
            
            if ($deleted > 0) {
                btr_debug_log(sprintf(
                    '[BTR Cron] Rimossi %d reminder vecchi',
                    $deleted
                ));
            }
        } catch (Exception $e) {
            btr_debug_log('[BTR Cron] Errore pulizia reminder: ' . $e->getMessage());
        }
    }
    
    /**
     * Gestisce toggle reminder automatici
     */
    public function handle_reminder_toggle($old_value, $new_value, $option) {
        if ($new_value === '1' && $old_value !== '1') {
            // Attiva cron
            $this->schedule_cron();
        } elseif ($new_value !== '1' && $old_value === '1') {
            // Disattiva cron
            $this->unschedule_cron();
        }
    }
    
    /**
     * Test cron manuale (solo debug)
     */
    public function maybe_test_cron() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (isset($_GET['btr_test_cron']) && $_GET['btr_test_cron'] === '1') {
            check_admin_referer('btr_test_cron');
            
            // Esegui cron manualmente
            $this->process_payment_reminders();
            
            // Redirect con messaggio
            wp_redirect(add_query_arg([
                'page' => 'btr-payment-plans-settings',
                'message' => 'cron_test_complete'
            ], admin_url('admin.php')));
            exit;
        }
    }
    
    /**
     * Ottieni prossima esecuzione cron
     */
    public static function get_next_scheduled_time() {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if (!$timestamp) {
            return false;
        }
        
        return [
            'timestamp' => $timestamp,
            'formatted' => date_i18n('d/m/Y H:i:s', $timestamp),
            'relative' => human_time_diff(current_time('timestamp'), $timestamp)
        ];
    }
    
    /**
     * Test scheduling cron
     */
    public function test_cron_schedule() {
        $results = [
            'current_time' => current_time('mysql'),
            'next_scheduled' => self::get_next_scheduled_time(),
            'cron_schedules' => wp_get_schedules(),
            'is_enabled' => get_option('btr_enable_auto_reminders', '0') === '1',
            'wp_cron_enabled' => !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON
        ];
        
        // Test scheduling
        $this->unschedule_cron();
        $this->schedule_cron();
        $results['rescheduled'] = wp_next_scheduled(self::CRON_HOOK) !== false;
        
        return $results;
    }
    
    /**
     * Force run cron (per test manuali)
     */
    public function force_run() {
        if (!current_user_can('manage_options')) {
            return [
                'success' => false,
                'message' => __('Permessi insufficienti', 'born-to-ride-booking')
            ];
        }
        
        $start_time = microtime(true);
        
        // Forza esecuzione
        $this->process_payment_reminders();
        
        $execution_time = microtime(true) - $start_time;
        
        return [
            'success' => true,
            'execution_time' => round($execution_time, 2),
            'stats' => self::get_reminder_stats(),
            'next_run' => self::get_next_scheduled_time()
        ];
    }
    
    /**
     * Verifica integrità sistema cron
     */
    public static function check_cron_health() {
        global $wpdb;
        
        $health = [
            'tables_exist' => true,
            'cron_scheduled' => false,
            'cron_enabled' => false,
            'last_run' => null,
            'issues' => []
        ];
        
        // Verifica tabelle
        $required_tables = [
            'btr_group_payments',
            'btr_payment_reminders'
        ];
        
        foreach ($required_tables as $table) {
            $full_table = $wpdb->prefix . $table;
            if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $full_table)) !== $full_table) {
                $health['tables_exist'] = false;
                $health['issues'][] = sprintf(
                    __('Tabella %s non trovata', 'born-to-ride-booking'),
                    $table
                );
            }
        }
        
        // Verifica scheduling
        $next_run = wp_next_scheduled(self::CRON_HOOK);
        $health['cron_scheduled'] = $next_run !== false;
        if (!$health['cron_scheduled']) {
            $health['issues'][] = __('Cron job non schedulato', 'born-to-ride-booking');
        }
        
        // Verifica abilitazione
        $health['cron_enabled'] = get_option('btr_enable_auto_reminders', '0') === '1';
        if (!$health['cron_enabled']) {
            $health['issues'][] = __('Reminder automatici disabilitati', 'born-to-ride-booking');
        }
        
        // Verifica ultima esecuzione
        $last_reminder = $wpdb->get_var("
            SELECT MAX(created_at) 
            FROM {$wpdb->prefix}btr_payment_reminders
            WHERE status = 'sent'
        ");
        
        if ($last_reminder) {
            $health['last_run'] = $last_reminder;
        }
        
        return $health;
    }
    
    /**
     * Ottieni statistiche reminder
     */
    public static function get_reminder_stats() {
        global $wpdb;
        
        // Verifica esistenza tabella
        $table_name = $wpdb->prefix . 'btr_payment_reminders';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
            return (object) [
                'total' => 0,
                'pending' => 0,
                'sent' => 0,
                'failed' => 0,
                'payment_due' => 0,
                'payment_overdue' => 0,
                'last_week' => 0
            ];
        }
        
        try {
            $stats = $wpdb->get_row("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN reminder_type = 'payment_due' THEN 1 ELSE 0 END) as payment_due,
                    SUM(CASE WHEN reminder_type = 'payment_overdue' THEN 1 ELSE 0 END) as payment_overdue,
                    SUM(CASE WHEN created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as last_week
                FROM {$wpdb->prefix}btr_payment_reminders
            ");
            
            return $stats;
        } catch (Exception $e) {
            btr_debug_log('[BTR Cron] Errore recupero statistiche: ' . $e->getMessage());
            return (object) [
                'total' => 0,
                'pending' => 0,
                'sent' => 0,
                'failed' => 0,
                'payment_due' => 0,
                'payment_overdue' => 0,
                'last_week' => 0
            ];
        }
    }
}

// Inizializza cron
new BTR_Payment_Cron();