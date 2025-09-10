<?php
/**
 * Aggiornamento database per versione 1.1.0
 * Aggiunge tabella btr_order_shares per gestione quote pagamento
 * 
 * @package BornToRideBooking
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Esegue l'aggiornamento del database alla versione 1.1.0
 * 
 * @return bool True se completato con successo
 */
function btr_update_database_1_1_0() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'btr_order_shares';
    
    // Crea tabella btr_order_shares
    $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id bigint(20) UNSIGNED NOT NULL COMMENT 'ID ordine WooCommerce',
        participant_id bigint(20) UNSIGNED NOT NULL COMMENT 'ID partecipante dal preventivo',
        participant_name varchar(255) NOT NULL COMMENT 'Nome completo partecipante',
        participant_email varchar(255) NOT NULL COMMENT 'Email per invio link pagamento',
        participant_phone varchar(50) DEFAULT NULL COMMENT 'Telefono opzionale',
        amount_assigned decimal(10,2) NOT NULL COMMENT 'Importo assegnato da pagare',
        amount_paid decimal(10,2) DEFAULT 0.00 COMMENT 'Importo già pagato',
        currency varchar(3) DEFAULT 'EUR' COMMENT 'Valuta pagamento',
        payment_method varchar(50) DEFAULT NULL COMMENT 'Metodo pagamento utilizzato',
        payment_status enum('pending','processing','paid','failed','expired','cancelled','refunded') DEFAULT 'pending' COMMENT 'Stato pagamento',
        payment_link varchar(500) DEFAULT NULL COMMENT 'Link pagamento individuale',
        payment_token varchar(64) DEFAULT NULL COMMENT 'Token sicuro per validazione',
        token_expires_at datetime DEFAULT NULL COMMENT 'Scadenza token sicurezza',
        transaction_id varchar(255) DEFAULT NULL COMMENT 'ID transazione gateway',
        paid_at datetime DEFAULT NULL COMMENT 'Data/ora pagamento completato',
        failed_at datetime DEFAULT NULL COMMENT 'Data/ora ultimo fallimento',
        failure_reason text DEFAULT NULL COMMENT 'Motivo fallimento pagamento',
        reminder_sent_at datetime DEFAULT NULL COMMENT 'Data/ora ultimo reminder',
        reminder_count int(11) DEFAULT 0 COMMENT 'Numero reminder inviati',
        next_reminder_at datetime DEFAULT NULL COMMENT 'Data/ora prossimo reminder',
        notes text DEFAULT NULL COMMENT 'Note interne admin',
        metadata longtext DEFAULT NULL COMMENT 'Dati aggiuntivi JSON',
        created_at datetime DEFAULT CURRENT_TIMESTAMP COMMENT 'Data creazione record',
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Ultimo aggiornamento',
        deleted_at datetime DEFAULT NULL COMMENT 'Soft delete timestamp',
        PRIMARY KEY (id),
        UNIQUE KEY idx_payment_token (payment_token),
        KEY idx_order_id (order_id),
        KEY idx_participant_email (participant_email),
        KEY idx_payment_status (payment_status),
        KEY idx_created_at (created_at),
        KEY idx_deleted_at (deleted_at),
        KEY idx_composite_order_status (order_id, payment_status, deleted_at),
        KEY idx_reminder_schedule (next_reminder_at, payment_status)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Verifica che la tabella sia stata creata
    $table_exists = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.tables 
             WHERE table_schema = %s 
             AND table_name = %s",
            DB_NAME,
            $table_name
        )
    );
    
    if (!$table_exists) {
        throw new Exception("Failed to create table {$table_name}");
    }
    
    // Aggiungi indice foreign key se non esiste già
    // Nota: questo potrebbe fallire se la tabella posts non usa InnoDB
    // quindi lo facciamo in try-catch separato
    try {
        $fk_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE 
                 WHERE TABLE_SCHEMA = %s 
                 AND TABLE_NAME = %s 
                 AND CONSTRAINT_NAME = 'fk_order_shares_order'",
                DB_NAME,
                $table_name
            )
        );
        
        if (!$fk_exists) {
            $wpdb->query(
                "ALTER TABLE {$table_name} 
                 ADD CONSTRAINT fk_order_shares_order 
                 FOREIGN KEY (order_id) 
                 REFERENCES {$wpdb->posts} (ID) 
                 ON DELETE CASCADE ON UPDATE CASCADE"
            );
        }
    } catch (Exception $e) {
        // Log ma non fallire l'update se FK non può essere creata
        if (defined('BTR_DEBUG') && BTR_DEBUG) {
            error_log('[BTR Database Update] Warning: Could not create foreign key: ' . $e->getMessage());
        }
    }
    
    // Crea opzione per tracciare versione della tabella
    update_option('btr_order_shares_db_version', '1.0.0');
    
    // Log completamento
    if (defined('BTR_DEBUG') && BTR_DEBUG) {
        error_log('[BTR Database Update] Table btr_order_shares created successfully');
        error_log('[BTR Database Update] Aggiornamento a versione 1.1.0 completato');
    }
    
    return true;
}