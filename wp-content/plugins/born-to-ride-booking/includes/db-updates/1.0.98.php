<?php
/**
 * Database update per versione 1.0.98
 * Crea tabelle per sistema pagamenti
 * 
 * @package BornToRideBooking
 * @since 1.0.98
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Esegue update database per versione 1.0.98
 * 
 * @return bool True se completato con successo
 */
function btr_update_database_1_0_98() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    try {
        // Tabella piani di pagamento
        $table_payment_plans = $wpdb->prefix . 'btr_payment_plans';
        $sql_payment_plans = "CREATE TABLE IF NOT EXISTS $table_payment_plans (
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
        
        // Tabella pagamenti gruppo
        $table_group_payments = $wpdb->prefix . 'btr_group_payments';
        $sql_group_payments = "CREATE TABLE IF NOT EXISTS $table_group_payments (
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
            PRIMARY KEY (payment_id),
            UNIQUE KEY payment_hash (payment_hash),
            KEY preventivo_id (preventivo_id),
            KEY payment_status (payment_status),
            KEY wc_order_id (wc_order_id)
        ) $charset_collate;";
        
        // Tabella promemoria pagamenti
        $table_payment_reminders = $wpdb->prefix . 'btr_payment_reminders';
        $sql_payment_reminders = "CREATE TABLE IF NOT EXISTS $table_payment_reminders (
            reminder_id bigint(20) NOT NULL AUTO_INCREMENT,
            payment_id bigint(20) NOT NULL,
            reminder_type varchar(50) NOT NULL,
            scheduled_date datetime NOT NULL,
            sent_date datetime DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'scheduled',
            attempts int(11) DEFAULT 0,
            last_error text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (reminder_id),
            KEY payment_id (payment_id),
            KEY scheduled_date (scheduled_date),
            KEY status (status)
        ) $charset_collate;";
        
        // Esegui creazione tabelle
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_payment_plans);
        dbDelta($sql_group_payments);
        dbDelta($sql_payment_reminders);
        
        // Verifica che le tabelle siano state create - use prepared statements
        $tables_created = [
            $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_payment_plans)) === $table_payment_plans,
            $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_group_payments)) === $table_group_payments,
            $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_payment_reminders)) === $table_payment_reminders
        ];
        
        if (in_array(false, $tables_created, true)) {
            throw new Exception('Una o più tabelle non sono state create correttamente');
        }
        
        // Crea pagine WordPress necessarie
        $pages_created = btr_create_payment_pages();
        if (!$pages_created) {
            btr_debug_log('Avviso: alcune pagine potrebbero non essere state create', 'payment-system');
        }
        
        // Log successo
        btr_debug_log('Database update 1.0.98 completato con successo', 'payment-system');
        
        return true;
        
    } catch (Exception $e) {
        btr_debug_log('Errore in database update 1.0.98: ' . $e->getMessage(), 'payment-system');
        return false;
    }
}

/**
 * Crea pagine WordPress per il sistema pagamenti
 * 
 * @return bool
 */
function btr_create_payment_pages() {
    $pages_created = 0;
    
    // Definizione pagine da creare
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
                $pages_created++;
            }
        } else {
            // Aggiorna opzione con ID esistente
            update_option($page_data['option_name'], $existing_page->ID);
            $pages_created++;
        }
    }
    
    return $pages_created === count($pages);
}