<?php
/**
 * Sistema di installazione e aggiornamento automatico delle tabelle del database
 * 
 * Questa classe crea automaticamente le tabelle del database se non esistono
 * e gestisce gli aggiornamenti dello schema in modo sicuro e incrementale.
 * 
 * @package BornToRideBooking
 * @since 1.0.98
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Database_Auto_Installer {
    
    /**
     * Versione corrente dello schema del database
     * @var string
     */
    const DB_VERSION = '1.0.98';
    
    /**
     * Prefisso per le tabelle del plugin
     * @var string
     */
    private $table_prefix;
    
    /**
     * Istanza del database WordPress
     * @var wpdb
     */
    private $wpdb;
    
    /**
     * Array delle tabelle gestite dal plugin
     * @var array
     */
    private $plugin_tables = [
        'btr_payment_plans',
        'btr_group_payments', 
        'btr_payment_reminders'
    ];
    
    /**
     * Istanza singleton
     * @var BTR_Database_Auto_Installer
     */
    private static $instance = null;
    
    /**
     * Ottieni l'istanza singleton
     * 
     * @return BTR_Database_Auto_Installer
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
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_prefix = $wpdb->prefix;
        
        // Hook per verificare e installare le tabelle
        add_action('plugins_loaded', [$this, 'check_and_install_tables'], 20);
        add_action('admin_init', [$this, 'check_and_install_tables']);
        
        // Hook per attivazione plugin
        register_activation_hook(BTR_PLUGIN_FILE, [$this, 'install_tables']);
        
        // Hook per disattivazione (non rimuove tabelle per sicurezza)
        register_deactivation_hook(BTR_PLUGIN_FILE, [$this, 'deactivation_cleanup']);
    }
    
    /**
     * Verifica e installa le tabelle se necessario
     * 
     * @return bool True se tutto è installato correttamente
     */
    public function check_and_install_tables() {
        // Verifica se dobbiamo eseguire l'installazione
        $installed_version = get_option('btr_db_version', '0');
        
        // Se non ci sono tabelle o la versione è diversa, installa/aggiorna
        if (!$this->all_tables_exist() || version_compare($installed_version, self::DB_VERSION, '<')) {
            return $this->install_tables();
        }
        
        return true;
    }
    
    /**
     * Verifica se tutte le tabelle del plugin esistono
     * 
     * @return bool
     */
    private function all_tables_exist() {
        foreach ($this->plugin_tables as $table) {
            $full_table_name = $this->table_prefix . $table;
            if ($this->wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") !== $full_table_name) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Installa o aggiorna le tabelle del database
     * 
     * @return bool True se l'installazione è riuscita
     */
    public function install_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $charset_collate = $this->wpdb->get_charset_collate();
        $success = true;
        
        try {
            // 1. Tabella piani di pagamento
            $sql_payment_plans = $this->get_payment_plans_schema($charset_collate);
            dbDelta($sql_payment_plans);
            
            // 2. Tabella pagamenti gruppo
            $sql_group_payments = $this->get_group_payments_schema($charset_collate);
            dbDelta($sql_group_payments);
            
            // 3. Tabella promemoria pagamenti
            $sql_payment_reminders = $this->get_payment_reminders_schema($charset_collate);
            dbDelta($sql_payment_reminders);
            
            // 4. Aggiungi colonne mancanti se necessario
            $this->add_missing_columns();
            
            // 5. Verifica che tutte le tabelle siano state create
            if (!$this->all_tables_exist()) {
                throw new Exception('Una o più tabelle non sono state create correttamente');
            }
            
            // 6. Crea pagine WordPress necessarie
            $this->create_payment_pages();
            
            // 7. Aggiorna la versione del database
            update_option('btr_db_version', self::DB_VERSION);
            
            // 8. Log successo
            $this->log_installation('success', 'Installazione completata con successo');
            
        } catch (Exception $e) {
            $success = false;
            $this->log_installation('error', $e->getMessage());
            btr_debug_log('[BTR Database] Errore installazione: ' . $e->getMessage());
        }
        
        return $success;
    }
    
    /**
     * Schema per la tabella payment_plans
     * 
     * @param string $charset_collate
     * @return string
     */
    private function get_payment_plans_schema($charset_collate) {
        $table_name = $this->table_prefix . 'btr_payment_plans';
        
        return "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            preventivo_id bigint(20) NOT NULL,
            plan_type varchar(50) NOT NULL DEFAULT 'full',
            total_amount decimal(10,2) NOT NULL,
            deposit_percentage int(11) DEFAULT 30,
            total_participants int(11) DEFAULT 1,
            payment_distribution text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY preventivo_id (preventivo_id),
            KEY plan_type (plan_type)
        ) $charset_collate;";
    }
    
    /**
     * Schema per la tabella group_payments
     * 
     * @param string $charset_collate
     * @return string
     */
    private function get_group_payments_schema($charset_collate) {
        $table_name = $this->table_prefix . 'btr_group_payments';
        
        return "CREATE TABLE $table_name (
            payment_id bigint(20) NOT NULL AUTO_INCREMENT,
            payment_hash varchar(64) NOT NULL,
            preventivo_id bigint(20) NOT NULL,
            payment_type varchar(50) NOT NULL,
            amount decimal(10,2) NOT NULL,
            payment_status varchar(20) NOT NULL DEFAULT 'pending',
            payment_plan_type varchar(50),
            group_member_id int(11),
            group_member_name varchar(255),
            share_percentage decimal(5,2),
            email_sent tinyint(1) DEFAULT 0,
            paid_at datetime DEFAULT NULL,
            wc_order_id bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            expires_at datetime DEFAULT NULL,
            PRIMARY KEY (payment_id),
            UNIQUE KEY payment_hash (payment_hash),
            KEY preventivo_id (preventivo_id),
            KEY payment_status (payment_status),
            KEY wc_order_id (wc_order_id),
            KEY expires_at (expires_at)
        ) $charset_collate;";
    }
    
    /**
     * Schema per la tabella payment_reminders
     * 
     * @param string $charset_collate
     * @return string
     */
    private function get_payment_reminders_schema($charset_collate) {
        $table_name = $this->table_prefix . 'btr_payment_reminders';
        
        return "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            payment_id bigint(20) NOT NULL,
            reminder_type varchar(50) NOT NULL,
            scheduled_for datetime NOT NULL,
            sent_at datetime DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            attempts int(11) DEFAULT 0,
            last_error text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY payment_id (payment_id),
            KEY scheduled_for (scheduled_for),
            KEY status (status)
        ) $charset_collate;";
    }
    
    /**
     * Aggiunge colonne mancanti alle tabelle esistenti
     * Utile per aggiornamenti incrementali
     */
    private function add_missing_columns() {
        // Verifica e aggiungi expires_at a group_payments se mancante
        $table_name = $this->table_prefix . 'btr_group_payments';
        $column_exists = $this->wpdb->get_results(
            "SHOW COLUMNS FROM $table_name LIKE 'expires_at'"
        );
        
        if (empty($column_exists)) {
            $this->wpdb->query(
                "ALTER TABLE $table_name 
                ADD COLUMN expires_at datetime DEFAULT NULL AFTER updated_at,
                ADD INDEX expires_at (expires_at)"
            );
        }
    }
    
    /**
     * Crea le pagine WordPress necessarie per il sistema pagamenti
     */
    private function create_payment_pages() {
        $pages = [
            [
                'title' => 'Checkout Caparra',
                'slug' => 'checkout-caparra',
                'content' => '[btr_checkout_deposit]',
                'option_name' => 'btr_checkout_deposit_page'
            ],
            [
                'title' => 'Riepilogo Pagamento Gruppo',
                'slug' => 'riepilogo-pagamento-gruppo',
                'content' => '[btr_group_payment_summary]',
                'option_name' => 'btr_group_payment_summary_page'
            ],
            [
                'title' => 'Conferma Prenotazione',
                'slug' => 'conferma-prenotazione',
                'content' => '[btr_booking_confirmation]',
                'option_name' => 'btr_booking_confirmation_page'
            ]
        ];
        
        foreach ($pages as $page_data) {
            // Verifica se la pagina esiste già
            $existing_page = get_page_by_path($page_data['slug']);
            
            if (!$existing_page) {
                $page_id = wp_insert_post([
                    'post_title' => $page_data['title'],
                    'post_name' => $page_data['slug'],
                    'post_content' => $page_data['content'],
                    'post_status' => 'publish',
                    'post_type' => 'page',
                    'post_author' => 1,
                    'comment_status' => 'closed'
                ]);
                
                if ($page_id && !is_wp_error($page_id)) {
                    update_option($page_data['option_name'], $page_id);
                }
            } else {
                // Aggiorna opzione con ID esistente
                update_option($page_data['option_name'], $existing_page->ID);
            }
        }
    }
    
    /**
     * Log eventi di installazione
     * 
     * @param string $type Tipo di evento (success, error, warning)
     * @param string $message Messaggio
     */
    private function log_installation($type, $message) {
        $log = get_option('btr_db_installation_log', []);
        
        $log[] = [
            'timestamp' => current_time('mysql'),
            'type' => $type,
            'message' => $message,
            'version' => self::DB_VERSION
        ];
        
        // Mantieni solo gli ultimi 50 log
        if (count($log) > 50) {
            $log = array_slice($log, -50);
        }
        
        update_option('btr_db_installation_log', $log);
    }
    
    /**
     * Pulizia durante la disattivazione
     * Non rimuove le tabelle per sicurezza
     */
    public function deactivation_cleanup() {
        // Rimuovi eventuali cron jobs
        wp_clear_scheduled_hook('btr_payment_reminders_cron');
        
        // Log disattivazione
        $this->log_installation('info', 'Plugin disattivato - tabelle mantenute');
    }
    
    /**
     * Metodo per verificare lo stato del database
     * 
     * @return array
     */
    public function get_database_status() {
        $status = [
            'version' => get_option('btr_db_version', '0'),
            'target_version' => self::DB_VERSION,
            'tables' => [],
            'pages' => [],
            'is_ready' => true
        ];
        
        // Verifica tabelle
        foreach ($this->plugin_tables as $table) {
            $full_table_name = $this->table_prefix . $table;
            $exists = $this->wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") === $full_table_name;
            
            $status['tables'][$table] = [
                'exists' => $exists,
                'name' => $full_table_name
            ];
            
            if (!$exists) {
                $status['is_ready'] = false;
            }
        }
        
        // Verifica pagine
        $pages = [
            'checkout-caparra' => 'btr_checkout_deposit_page',
            'riepilogo-pagamento-gruppo' => 'btr_group_payment_summary_page',
            'conferma-prenotazione' => 'btr_booking_confirmation_page'
        ];
        
        foreach ($pages as $slug => $option) {
            $page_id = get_option($option);
            $page_exists = $page_id && get_post($page_id);
            
            $status['pages'][$slug] = [
                'exists' => (bool) $page_exists,
                'id' => $page_id
            ];
        }
        
        return $status;
    }
    
    /**
     * Forza reinstallazione completa (utile per debug)
     * 
     * @return bool
     */
    public function force_reinstall() {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        // Reset versione per forzare reinstallazione
        delete_option('btr_db_version');
        
        // Esegui installazione
        return $this->install_tables();
    }
}

// Inizializza il sistema di auto-installazione
BTR_Database_Auto_Installer::get_instance();