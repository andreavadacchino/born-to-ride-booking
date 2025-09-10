<?php
/**
 * Aggiornamento database per versione 1.0.98
 * Aggiunge supporto per piani di pagamento estesi
 * 
 * @package BornToRideBooking
 * @since 1.0.98
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Esegue l'aggiornamento del database
 */
function btr_update_database_1_0_98() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // 1. Estendi tabella btr_group_payments
    $table_name = $wpdb->prefix . 'btr_group_payments';
    
    // Aggiungi colonne per supportare piani di pagamento
    $columns_to_add = [
        "payment_plan_type ENUM('full', 'deposit_balance', 'group_split') DEFAULT 'full'",
        "installment_number INT DEFAULT NULL",
        "total_installments INT DEFAULT NULL", 
        "group_member_id INT DEFAULT NULL",
        "group_member_name VARCHAR(255) DEFAULT NULL",
        "share_percentage DECIMAL(5,2) DEFAULT NULL",
        "parent_payment_id INT DEFAULT NULL"
    ];
    
    foreach ($columns_to_add as $column_def) {
        $column_name = explode(' ', $column_def)[0];
        
        // Verifica se la colonna esiste già
        $sanitized_table_name = preg_replace('/[^a-zA-Z0-9_]/', '', $table_name);
        $column_exists = $wpdb->get_results(
            $wpdb->prepare(
                "SHOW COLUMNS FROM `{$sanitized_table_name}` LIKE %s",
                $column_name
            )
        );
        
        if (empty($column_exists)) {
            // $column_def is safe as it comes from predefined array
            $wpdb->query("ALTER TABLE `{$sanitized_table_name}` ADD COLUMN {$column_def}");
        }
    }
    
    // 2. Crea nuova tabella per i piani di pagamento
    $table_name = $wpdb->prefix . 'btr_payment_plans';
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id int(11) NOT NULL AUTO_INCREMENT,
        preventivo_id int(11) NOT NULL,
        plan_type ENUM('full', 'deposit_balance', 'group_split') NOT NULL,
        total_amount decimal(10,2) NOT NULL,
        deposit_percentage int(11) DEFAULT 30,
        total_participants int(11) DEFAULT 1,
        payment_distribution TEXT DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY preventivo_id (preventivo_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // 3. Crea tabella per notifiche e reminder
    $table_name = $wpdb->prefix . 'btr_payment_reminders';
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id int(11) NOT NULL AUTO_INCREMENT,
        payment_id int(11) NOT NULL,
        reminder_type ENUM('payment_due', 'payment_overdue', 'balance_due') NOT NULL,
        sent_at datetime DEFAULT NULL,
        scheduled_for datetime NOT NULL,
        status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
        attempts int(11) DEFAULT 0,
        PRIMARY KEY (id),
        KEY payment_id (payment_id),
        KEY scheduled_for (scheduled_for),
        KEY status (status)
    ) $charset_collate;";
    
    dbDelta($sql);
    
    // 4. Aggiorna versione database
    update_option('btr_db_version', '1.0.98');
    
    // Log completamento
    if (defined('BTR_DEBUG') && BTR_DEBUG) {
        error_log('[BTR Database Update] Aggiornamento a versione 1.0.98 completato');
    }
    
    return true;
}

/**
 * Verifica se l'aggiornamento è necessario
 */
function btr_needs_update_1_0_98() {
    $current_version = get_option('btr_db_version', '1.0.0');
    return version_compare($current_version, '1.0.98', '<');
}

// Esegui aggiornamento se necessario
if (btr_needs_update_1_0_98()) {
    btr_update_database_1_0_98();
}