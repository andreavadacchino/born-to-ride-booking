<?php
/**
 * Migration 1.1.0 - Order Shares Table
 * 
 * Aggiunge la tabella btr_order_shares per gestire i pagamenti di gruppo
 * 
 * @package BornToRideBooking
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BTR_Migration_1_1_0
 */
class BTR_Migration_1_1_0 {
    
    /**
     * Run the migration
     */
    public function up() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'btr_order_shares';
        $charset_collate = $wpdb->get_charset_collate();
        
        // SQL per creare la tabella
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
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
            PRIMARY KEY (id),
            KEY idx_order_id (order_id),
            KEY idx_participant_email (participant_email),
            KEY idx_payment_status (payment_status),
            KEY idx_payment_token (payment_token),
            KEY idx_created_at (created_at),
            KEY idx_deleted_at (deleted_at),
            KEY idx_composite_order_status (order_id, payment_status, deleted_at),
            KEY idx_reminder_schedule (next_reminder_at, payment_status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Verifica che la tabella sia stata creata
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        if (!$table_exists) {
            throw new Exception("Failed to create table $table_name");
        }
        
        // Aggiungi foreign key se possibile
        $this->add_foreign_keys();
        
        // Aggiungi trigger per updated_at se MySQL lo supporta
        $this->add_update_trigger();
        
        // Crea tabella per log migrations se non esiste
        $this->create_migration_log_table();
        
        // Aggiungi opzioni per tracking
        add_option('btr_order_shares_version', '1.1.0');
        
        btr_debug_log('Migration 1.1.0 completed - Order shares table created');
    }
    
    /**
     * Rollback the migration
     */
    public function down() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'btr_order_shares';
        
        // Backup data prima di eliminare
        $backup_table = $table_name . '_rollback_' . date('YmdHis');
        $wpdb->query("CREATE TABLE IF NOT EXISTS $backup_table AS SELECT * FROM $table_name");
        
        // Rimuovi foreign keys
        $this->remove_foreign_keys();
        
        // Elimina tabella
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        
        // Rimuovi opzioni
        delete_option('btr_order_shares_version');
        
        btr_debug_log('Migration 1.1.0 rolled back - Order shares table removed (backup: ' . $backup_table . ')');
    }
    
    /**
     * Add foreign keys
     */
    private function add_foreign_keys() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'btr_order_shares';
        
        // Verifica se foreign key esiste già
        $fk_exists = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM information_schema.TABLE_CONSTRAINTS 
            WHERE CONSTRAINT_SCHEMA = DATABASE() 
            AND TABLE_NAME = '$table_name' 
            AND CONSTRAINT_NAME = 'fk_order_shares_order'
        ");
        
        if (!$fk_exists) {
            try {
                $wpdb->query("ALTER TABLE $table_name 
                    ADD CONSTRAINT fk_order_shares_order 
                    FOREIGN KEY (order_id) REFERENCES {$wpdb->prefix}posts(ID) 
                    ON DELETE CASCADE ON UPDATE CASCADE");
                    
                btr_debug_log('Foreign key constraint added successfully');
            } catch (Exception $e) {
                // Non critico se fallisce (alcuni hosting non supportano FK)
                btr_debug_log('Could not add foreign key constraint: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Remove foreign keys
     */
    private function remove_foreign_keys() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'btr_order_shares';
        
        try {
            $wpdb->query("ALTER TABLE $table_name DROP FOREIGN KEY IF EXISTS fk_order_shares_order");
        } catch (Exception $e) {
            // Non critico
            btr_debug_log('Could not remove foreign key: ' . $e->getMessage());
        }
    }
    
    /**
     * Add update trigger for updated_at
     */
    private function add_update_trigger() {
        global $wpdb;
        
        // Verifica versione MySQL
        $mysql_version = $wpdb->db_version();
        
        if (version_compare($mysql_version, '5.6.5', '>=')) {
            $table_name = $wpdb->prefix . 'btr_order_shares';
            
            try {
                // MySQL 5.6.5+ supporta ON UPDATE CURRENT_TIMESTAMP
                $wpdb->query("ALTER TABLE $table_name 
                    MODIFY COLUMN updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
                    
                btr_debug_log('Updated_at trigger added via column definition');
            } catch (Exception $e) {
                btr_debug_log('Could not add update trigger: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * Create migration log table
     */
    private function create_migration_log_table() {
        global $wpdb;
        
        $log_table = $wpdb->prefix . 'btr_migration_log';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $log_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            version varchar(20) NOT NULL,
            description text,
            status varchar(50) NOT NULL,
            error_message text,
            executed_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_version (version),
            KEY idx_status (status)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}