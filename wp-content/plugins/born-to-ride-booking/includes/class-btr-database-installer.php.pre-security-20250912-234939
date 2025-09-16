<?php
/**
 * Database Installer for Born to Ride Booking
 *
 * Gestisce l'installazione e l'aggiornamento delle tabelle custom del database
 *
 * @package BornToRideBooking
 * @since 1.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BTR_Database_Installer
 * 
 * Gestisce installazione, aggiornamento e rollback delle tabelle database custom
 */
class BTR_Database_Installer {
    
    /**
     * Current database version
     */
    const DB_VERSION = '1.0.0';
    
    /**
     * Option name for database version tracking
     */
    const VERSION_OPTION = 'btr_db_version';
    
    /**
     * Check and create/update tables if needed
     * 
     * @return bool True on success, false on failure
     */
    public static function check_and_create_tables() {
        $current_version = get_option(self::VERSION_OPTION, '0');
        
        // Se la versione è aggiornata, non fare nulla
        if (version_compare($current_version, self::DB_VERSION, '>=')) {
            return true;
        }
        
        // Esegui installazione o aggiornamento
        $result = self::install();
        
        if ($result) {
            update_option(self::VERSION_OPTION, self::DB_VERSION);
            btr_debug_log('Database tables installed/updated successfully to version ' . self::DB_VERSION);
        } else {
            btr_debug_log('Failed to install/update database tables', 'error');
        }
        
        return $result;
    }
    
    /**
     * Install or update database tables
     * 
     * @return bool Success status
     */
    public static function install() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_prefix = $wpdb->prefix;
        
        // Prepara SQL per order shares table
        $sql_order_shares = self::get_order_shares_table_sql($table_prefix, $charset_collate);
        
        // Richiede wp-admin/includes/upgrade.php per dbDelta
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Esegui creazione/aggiornamento tabella
        $result = dbDelta($sql_order_shares);
        
        // Log del risultato
        if (!empty($result)) {
            foreach ($result as $table => $message) {
                btr_debug_log("Database update - $table: $message");
            }
        }
        
        // Verifica che la tabella esista
        $table_name = $table_prefix . 'btr_order_shares';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if (!$table_exists) {
            return false;
        }
        
        // Esegui migrations incrementali se necessario
        self::run_incremental_migrations($wpdb->get_var("SELECT VERSION()"));
        
        return true;
    }
    
    /**
     * Get SQL for order shares table
     * 
     * @param string $prefix Table prefix
     * @param string $charset_collate Charset and collation
     * @return string SQL query
     */
    private static function get_order_shares_table_sql($prefix, $charset_collate) {
        $table_name = $prefix . 'btr_order_shares';
        
        // Note: dbDelta è molto particolare sulla formattazione
        // - Due spazi dopo PRIMARY KEY
        // - Nessuno spazio tra tipo e parentesi per decimal(10,2)
        // - KEY invece di INDEX
        $sql = "CREATE TABLE $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            order_id bigint(20) UNSIGNED NOT NULL,
            participant_id bigint(20) UNSIGNED NOT NULL,
            participant_name varchar(255) NOT NULL,
            participant_email varchar(255) NOT NULL,
            participant_phone varchar(50) DEFAULT NULL,
            amount_assigned decimal(10,2) NOT NULL,
            amount_paid decimal(10,2) DEFAULT 0.00,
            currency varchar(3) DEFAULT 'EUR',
            payment_method varchar(50) DEFAULT NULL,
            payment_status varchar(20) DEFAULT 'pending',
            payment_link varchar(500) DEFAULT NULL,
            payment_token varchar(64) DEFAULT NULL,
            token_expires_at datetime DEFAULT NULL,
            transaction_id varchar(255) DEFAULT NULL,
            paid_at datetime DEFAULT NULL,
            failed_at datetime DEFAULT NULL,
            failure_reason text DEFAULT NULL,
            reminder_sent_at datetime DEFAULT NULL,
            reminder_count int(11) DEFAULT 0,
            next_reminder_at datetime DEFAULT NULL,
            notes text DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP,
            deleted_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_order_id (order_id),
            KEY idx_participant_email (participant_email),
            KEY idx_payment_status (payment_status),
            KEY idx_payment_token (payment_token),
            KEY idx_created_at (created_at),
            KEY idx_deleted_at (deleted_at),
            KEY idx_composite_order_status (order_id, payment_status, deleted_at),
            KEY idx_reminder_schedule (next_reminder_at, payment_status)
        ) $charset_collate;";
        
        return $sql;
    }
    
    /**
     * Run incremental migrations based on MySQL version
     * 
     * @param string $mysql_version MySQL version string
     */
    private static function run_incremental_migrations($mysql_version) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'btr_order_shares';
        
        // Se MySQL supporta ON UPDATE CURRENT_TIMESTAMP (5.6+)
        if (version_compare($mysql_version, '5.6.5', '>=')) {
            // Prova ad aggiungere ON UPDATE se non esiste già
            $wpdb->query("ALTER TABLE $table_name 
                MODIFY COLUMN updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        }
        
        // Aggiungi foreign key se non esiste
        // Prima verifica se esiste già
        $fk_exists = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM information_schema.TABLE_CONSTRAINTS 
            WHERE CONSTRAINT_SCHEMA = DATABASE() 
            AND TABLE_NAME = '$table_name' 
            AND CONSTRAINT_NAME = 'fk_order_shares_order'
        ");
        
        if (!$fk_exists) {
            // Verifica che la tabella posts esista (dovrebbe sempre esistere in WP)
            $posts_table = $wpdb->prefix . 'posts';
            $posts_exists = $wpdb->get_var("SHOW TABLES LIKE '$posts_table'") === $posts_table;
            
            if ($posts_exists) {
                $wpdb->query("ALTER TABLE $table_name 
                    ADD CONSTRAINT fk_order_shares_order 
                    FOREIGN KEY (order_id) REFERENCES {$wpdb->prefix}posts(ID) 
                    ON DELETE CASCADE ON UPDATE CASCADE");
            }
        }
    }
    
    /**
     * Rollback database changes
     * 
     * @param string $version Version to rollback to
     * @return bool Success status
     */
    public static function rollback($version = '0') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'btr_order_shares';
        
        // Backup data before rollback (optional)
        $backup_table = $table_name . '_backup_' . date('Ymd_His');
        // Sanitize table names to prevent SQL injection
        $sanitized_backup_table = preg_replace('/[^a-zA-Z0-9_]/', '', $backup_table);
        $sanitized_table_name = preg_replace('/[^a-zA-Z0-9_]/', '', $table_name);
        
        $wpdb->query("CREATE TABLE IF NOT EXISTS `{$sanitized_backup_table}` AS SELECT * FROM `{$sanitized_table_name}`");
        
        // Remove foreign key constraint
        $sanitized_table_name = preg_replace('/[^a-zA-Z0-9_]/', '', $table_name);
        $wpdb->query("ALTER TABLE `{$sanitized_table_name}` DROP FOREIGN KEY IF EXISTS fk_order_shares_order");
        
        // Drop table
        $sanitized_table_name = preg_replace('/[^a-zA-Z0-9_]/', '', $table_name);
        $wpdb->query("DROP TABLE IF EXISTS `{$sanitized_table_name}`");
        
        // Update version
        if ($version === '0') {
            delete_option(self::VERSION_OPTION);
        } else {
            update_option(self::VERSION_OPTION, $version);
        }
        
        btr_debug_log("Database rolled back to version $version");
        
        return true;
    }
    
    /**
     * Get current database version
     * 
     * @return string Current version
     */
    public static function get_version() {
        return get_option(self::VERSION_OPTION, '0');
    }
    
    /**
     * Check if tables need update
     * 
     * @return bool True if update needed
     */
    public static function needs_update() {
        $current_version = self::get_version();
        return version_compare($current_version, self::DB_VERSION, '<');
    }
}

// Hook per attivazione plugin
register_activation_hook(BTR_PLUGIN_FILE, ['BTR_Database_Installer', 'check_and_create_tables']);

// Hook per admin init - controlla aggiornamenti
add_action('admin_init', function() {
    if (BTR_Database_Installer::needs_update()) {
        BTR_Database_Installer::check_and_create_tables();
    }
});

// Aggiungi pagina admin per gestione database (opzionale)
add_action('admin_menu', function() {
    if (current_user_can('manage_options')) {
        add_submenu_page(
            null, // Hidden page
            'BTR Database Management',
            'Database',
            'manage_options',
            'btr-database-management',
            function() {
                include BTR_PLUGIN_DIR . 'admin/views/database-management.php';
            }
        );
    }
});