<?php
class BTR_Database {
    public function create_tables() {
        global $wpdb;

        $this->create_quotes_table();
        $this->create_group_payments_table();
        $this->create_payment_links_table();
        $this->create_package_date_ranges_table();
    }

    private function create_quotes_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'btr_quotes';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            quote_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            hash CHAR(32) NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL,
            start_date DATETIME NOT NULL,
            end_date DATETIME NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            configuration LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            PRIMARY KEY  (quote_id),
            UNIQUE KEY hash (hash),
            KEY expires_at (expires_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Crea la tabella per i pagamenti di gruppo individuali
     */
    private function create_group_payments_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'btr_group_payments';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            payment_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            preventivo_id BIGINT UNSIGNED NOT NULL,
            participant_index INT NOT NULL,
            participant_name VARCHAR(255) NOT NULL,
            participant_email VARCHAR(255) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            payment_status ENUM('pending', 'paid', 'failed', 'expired') DEFAULT 'pending',
            payment_type ENUM('full', 'deposit', 'balance') DEFAULT 'full',
            wc_order_id BIGINT UNSIGNED NULL,
            payment_hash CHAR(64) NOT NULL,
            created_at DATETIME NOT NULL,
            paid_at DATETIME NULL,
            expires_at DATETIME NOT NULL,
            notes TEXT NULL,
            PRIMARY KEY (payment_id),
            UNIQUE KEY payment_hash (payment_hash),
            KEY preventivo_participant (preventivo_id, participant_index),
            KEY payment_status (payment_status),
            KEY expires_at (expires_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Crea la tabella per i link di pagamento personalizzati
     */
    private function create_payment_links_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'btr_payment_links';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            link_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            payment_id BIGINT UNSIGNED NOT NULL,
            link_hash CHAR(64) NOT NULL,
            link_type ENUM('individual', 'group', 'deposit', 'balance') DEFAULT 'individual',
            access_count INT DEFAULT 0,
            last_access_at DATETIME NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            PRIMARY KEY (link_id),
            UNIQUE KEY link_hash (link_hash),
            KEY payment_id (payment_id),
            KEY expires_at (expires_at),
            KEY is_active (is_active)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Crea la tabella per le date ranges continue dei pacchetti
     */
    private function create_package_date_ranges_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'btr_package_date_ranges';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            range_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            package_id BIGINT UNSIGNED NOT NULL,
            range_start_date DATE NOT NULL,
            range_end_date DATE NOT NULL,
            single_date DATE NOT NULL,
            day_index INT NOT NULL,
            is_available TINYINT(1) DEFAULT 1,
            max_capacity INT DEFAULT NULL,
            current_bookings INT DEFAULT 0,
            price_modifier DECIMAL(5,2) DEFAULT 0.00,
            notes TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (range_id),
            KEY package_id (package_id),
            KEY range_dates (range_start_date, range_end_date),
            KEY single_date (single_date),
            KEY availability (is_available),
            UNIQUE KEY package_range_day (package_id, range_start_date, range_end_date, day_index)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

