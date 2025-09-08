<?php
/**
 * Database Manager per la tabella btr_order_shares
 * 
 * Gestisce tutte le operazioni CRUD sulla tabella delle quote di pagamento
 * utilizzando il pattern Singleton per garantire un'istanza unica.
 * 
 * @package BornToRideBooking
 * @since 1.0.99
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Database_Manager {
    
    /**
     * Istanza singleton
     * @var BTR_Database_Manager
     */
    private static $instance = null;
    
    /**
     * Nome della tabella (senza prefisso)
     * @var string
     */
    private $table_name = 'btr_order_shares';
    
    /**
     * Nome completo della tabella con prefisso
     * @var string
     */
    private $full_table_name;
    
    /**
     * Cache per query frequenti
     * @var array
     */
    private $cache = [];
    
    /**
     * Durata cache in secondi
     * @var int
     */
    private $cache_duration = 300; // 5 minuti
    
    /**
     * Versione schema database
     * @var string
     */
    private $db_version = '1.0.0';
    
    /**
     * Constructor privato per pattern Singleton
     */
    private function __construct() {
        global $wpdb;
        $this->full_table_name = $wpdb->prefix . $this->table_name;
        
        // Hook per installazione/aggiornamento
        add_action('plugins_loaded', [$this, 'check_database_version']);
        
        // Hook per pulizia cache
        add_action('btr_clear_database_cache', [$this, 'clear_cache']);
    }
    
    /**
     * Ottieni istanza singleton thread-safe
     * 
     * @return BTR_Database_Manager
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Previeni clonazione
     */
    private function __clone() {}
    
    /**
     * Previeni unserialize
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
    
    /**
     * Ottieni il nome completo della tabella con prefisso
     * 
     * @return string Nome completo della tabella
     */
    public function get_table_name() {
        return $this->full_table_name;
    }
    
    /**
     * Controlla e aggiorna versione database se necessario
     */
    public function check_database_version() {
        $installed_version = get_option('btr_order_shares_db_version', '0');
        
        if (version_compare($installed_version, $this->db_version, '<')) {
            $this->install_or_update_table();
            update_option('btr_order_shares_db_version', $this->db_version);
        }
    }
    
    /**
     * Installa o aggiorna la tabella
     */
    private function install_or_update_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$this->full_table_name} (
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
            payment_status enum('pending','processing','paid','failed','expired','cancelled','refunded') DEFAULT 'pending',
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
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at datetime DEFAULT NULL,
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
        
        $this->log_operation('install', 'Table installed or updated');
    }
    
    /**
     * Crea nuova quota di pagamento
     * 
     * @param array $data Dati della quota
     * @return int|false ID inserito o false in caso di errore
     */
    public function create($data) {
        global $wpdb;
        
        // Validazione dati
        $validated_data = $this->validate_data($data);
        if (is_wp_error($validated_data)) {
            $this->log_error('create', $validated_data->get_error_message());
            return false;
        }
        
        // Genera token sicuro se non fornito
        if (empty($validated_data['payment_token'])) {
            $validated_data['payment_token'] = $this->generate_secure_token();
            $validated_data['token_expires_at'] = date('Y-m-d H:i:s', strtotime('+7 days'));
        }
        
        // Serializza metadata se presente
        if (isset($validated_data['metadata']) && is_array($validated_data['metadata'])) {
            $validated_data['metadata'] = json_encode($validated_data['metadata']);
        }
        
        // Inserimento con prepared statement
        $result = $wpdb->insert(
            $this->full_table_name,
            $validated_data,
            $this->get_data_formats($validated_data)
        );
        
        if (false === $result) {
            $this->log_error('create', $wpdb->last_error);
            return false;
        }
        
        $insert_id = $wpdb->insert_id;
        
        // Clear cache
        $this->clear_cache_for_order($validated_data['order_id']);
        
        $this->log_operation('create', "Created share ID: {$insert_id}");
        
        return $insert_id;
    }
    
    /**
     * Leggi quota per ID
     * 
     * @param int $id ID della quota
     * @param bool $include_deleted Include record eliminati
     * @return object|null
     */
    public function read($id, $include_deleted = false) {
        global $wpdb;
        
        $cache_key = "share_{$id}_{$include_deleted}";
        
        // Check cache
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }
        
        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->full_table_name} WHERE id = %d",
            $id
        );
        
        if (!$include_deleted) {
            $sql .= " AND deleted_at IS NULL";
        }
        
        $result = $wpdb->get_row($sql);
        
        if ($result) {
            $result = $this->process_result($result);
            $this->cache[$cache_key] = $result;
        }
        
        return $result;
    }
    
    /**
     * Aggiorna quota esistente
     * 
     * @param int $id ID della quota
     * @param array $data Dati da aggiornare
     * @return bool True se aggiornato con successo
     */
    public function update($id, $data) {
        global $wpdb;
        
        // Rimuovi campi che non devono essere aggiornati
        unset($data['id'], $data['created_at']);
        
        // Validazione dati
        $validated_data = $this->validate_data($data, true);
        if (is_wp_error($validated_data)) {
            $this->log_error('update', $validated_data->get_error_message());
            return false;
        }
        
        // Serializza metadata se presente
        if (isset($validated_data['metadata']) && is_array($validated_data['metadata'])) {
            $validated_data['metadata'] = json_encode($validated_data['metadata']);
        }
        
        $result = $wpdb->update(
            $this->full_table_name,
            $validated_data,
            ['id' => $id],
            $this->get_data_formats($validated_data),
            ['%d']
        );
        
        if (false === $result) {
            $this->log_error('update', $wpdb->last_error);
            return false;
        }
        
        // Clear cache
        $share = $this->read($id, true);
        if ($share) {
            $this->clear_cache_for_order($share->order_id);
        }
        
        $this->log_operation('update', "Updated share ID: {$id}");
        
        return true;
    }
    
    /**
     * Elimina quota (soft delete)
     * 
     * @param int $id ID della quota
     * @param bool $hard_delete Se true, elimina fisicamente
     * @return bool
     */
    public function delete($id, $hard_delete = false) {
        global $wpdb;
        
        if ($hard_delete) {
            // Hard delete
            $result = $wpdb->delete(
                $this->full_table_name,
                ['id' => $id],
                ['%d']
            );
        } else {
            // Soft delete
            $result = $wpdb->update(
                $this->full_table_name,
                ['deleted_at' => current_time('mysql')],
                ['id' => $id],
                ['%s'],
                ['%d']
            );
        }
        
        if (false === $result) {
            $this->log_error('delete', $wpdb->last_error);
            return false;
        }
        
        // Clear cache
        $share = $this->read($id, true);
        if ($share) {
            $this->clear_cache_for_order($share->order_id);
        }
        
        $this->log_operation('delete', "Deleted share ID: {$id}");
        
        return true;
    }
    
    /**
     * Ripristina quota da soft delete
     * 
     * @param int $id ID della quota
     * @return bool
     */
    public function restore($id) {
        global $wpdb;
        
        $result = $wpdb->update(
            $this->full_table_name,
            ['deleted_at' => null],
            ['id' => $id],
            ['%s'],
            ['%d']
        );
        
        if (false === $result) {
            $this->log_error('restore', $wpdb->last_error);
            return false;
        }
        
        // Clear cache
        $share = $this->read($id, true);
        if ($share) {
            $this->clear_cache_for_order($share->order_id);
        }
        
        $this->log_operation('restore', "Restored share ID: {$id}");
        
        return true;
    }
    
    /**
     * Ottieni quote eliminate (soft deleted)
     * 
     * @param array $args Argomenti di query
     * @return array
     */
    public function get_deleted($args = []) {
        global $wpdb;
        
        $defaults = [
            'orderby' => 'deleted_at',
            'order' => 'DESC',
            'limit' => -1,
            'offset' => 0,
            'days_ago' => null // Filtro per giorni fa (es. 30 per ultime 30 giorni)
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = "SELECT * FROM {$this->full_table_name} WHERE deleted_at IS NOT NULL";
        $sql_args = [];
        
        // Filtro per giorni fa
        if ($args['days_ago'] && is_numeric($args['days_ago'])) {
            $sql .= " AND deleted_at >= %s";
            $sql_args[] = date('Y-m-d H:i:s', strtotime("-{$args['days_ago']} days"));
        }
        
        $sql .= $this->build_order_clause($args['orderby'], $args['order']);
        
        if ($args['limit'] > 0) {
            $sql .= " LIMIT %d OFFSET %d";
            $sql_args[] = $args['limit'];
            $sql_args[] = $args['offset'];
        }
        
        if (!empty($sql_args)) {
            $results = $wpdb->get_results($wpdb->prepare($sql, $sql_args));
        } else {
            $results = $wpdb->get_results($sql);
        }
        
        if ($results) {
            $results = array_map([$this, 'process_result'], $results);
        }
        
        return $results ?: [];
    }
    
    /**
     * Elimina definitivamente quote soft deleted oltre X giorni
     * 
     * @param int $days_old Numero di giorni (default: 90)
     * @return int Numero di record eliminati
     */
    public function cleanup_old_deleted($days_old = 90) {
        global $wpdb;
        
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$days_old} days"));
        
        $result = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->full_table_name} 
             WHERE deleted_at IS NOT NULL 
             AND deleted_at < %s",
            $cutoff_date
        ));
        
        if (false === $result) {
            $this->log_error('cleanup_old_deleted', $wpdb->last_error);
            return 0;
        }
        
        $this->log_operation('cleanup_old_deleted', "Cleaned up {$result} old deleted records older than {$days_old} days");
        
        return $result;
    }
    
    /**
     * Conta quote per stato (incluse eliminate)
     * 
     * @param bool $include_deleted Includere quote eliminate
     * @return array
     */
    public function count_by_status($include_deleted = false) {
        global $wpdb;
        
        $sql = "
            SELECT 
                payment_status,
                COUNT(*) as count,
                SUM(CASE WHEN deleted_at IS NOT NULL THEN 1 ELSE 0 END) as deleted_count
            FROM {$this->full_table_name}
        ";
        
        if (!$include_deleted) {
            $sql .= " WHERE deleted_at IS NULL";
        }
        
        $sql .= " GROUP BY payment_status";
        
        $results = $wpdb->get_results($sql, ARRAY_A);
        
        $counts = [];
        foreach ($results as $row) {
            $counts[$row['payment_status']] = [
                'total' => intval($row['count']),
                'deleted' => intval($row['deleted_count'])
            ];
        }
        
        // Aggiungi conteggio totale eliminati
        if ($include_deleted) {
            $total_deleted = $wpdb->get_var("SELECT COUNT(*) FROM {$this->full_table_name} WHERE deleted_at IS NOT NULL");
            $counts['_deleted_total'] = intval($total_deleted);
        }
        
        return $counts;
    }
    
    /**
     * Ottieni tutte le quote per un ordine
     * 
     * @param int $order_id ID ordine WooCommerce
     * @param array $args Argomenti di query
     * @return array
     */
    public function get_by_order($order_id, $args = []) {
        global $wpdb;
        
        $defaults = [
            'status' => null,
            'include_deleted' => false,
            'orderby' => 'created_at',
            'order' => 'ASC',
            'limit' => -1,
            'offset' => 0
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $cache_key = 'order_' . md5(serialize([$order_id, $args]));
        
        // Check cache
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }
        
        $sql = "SELECT * FROM {$this->full_table_name} WHERE order_id = %d";
        $sql_args = [$order_id];
        
        if (!$args['include_deleted']) {
            $sql .= " AND deleted_at IS NULL";
        }
        
        if ($args['status']) {
            $sql .= " AND payment_status = %s";
            $sql_args[] = $args['status'];
        }
        
        $sql .= $this->build_order_clause($args['orderby'], $args['order']);
        
        if ($args['limit'] > 0) {
            $sql .= " LIMIT %d OFFSET %d";
            $sql_args[] = $args['limit'];
            $sql_args[] = $args['offset'];
        }
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $sql_args));
        
        if ($results) {
            $results = array_map([$this, 'process_result'], $results);
            $this->cache[$cache_key] = $results;
        }
        
        return $results ?: [];
    }
    
    /**
     * Ottieni quote per email partecipante
     * 
     * @param string $email Email partecipante
     * @param array $args Argomenti di query
     * @return array
     */
    public function get_by_email($email, $args = []) {
        global $wpdb;
        
        $defaults = [
            'status' => null,
            'include_deleted' => false,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => -1,
            'offset' => 0
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = "SELECT * FROM {$this->full_table_name} WHERE participant_email = %s";
        $sql_args = [$email];
        
        if (!$args['include_deleted']) {
            $sql .= " AND deleted_at IS NULL";
        }
        
        if ($args['status']) {
            $sql .= " AND payment_status = %s";
            $sql_args[] = $args['status'];
        }
        
        $sql .= $this->build_order_clause($args['orderby'], $args['order']);
        
        if ($args['limit'] > 0) {
            $sql .= " LIMIT %d OFFSET %d";
            $sql_args[] = $args['limit'];
            $sql_args[] = $args['offset'];
        }
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $sql_args));
        
        if ($results) {
            $results = array_map([$this, 'process_result'], $results);
        }
        
        return $results ?: [];
    }
    
    /**
     * Ottieni quote per stato pagamento
     * 
     * @param string $status Stato pagamento
     * @param array $args Argomenti di query
     * @return array
     */
    public function get_by_status($status, $args = []) {
        global $wpdb;
        
        $defaults = [
            'include_deleted' => false,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 50,
            'offset' => 0
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = "SELECT * FROM {$this->full_table_name} WHERE payment_status = %s";
        $sql_args = [$status];
        
        if (!$args['include_deleted']) {
            $sql .= " AND deleted_at IS NULL";
        }
        
        $sql .= $this->build_order_clause($args['orderby'], $args['order']);
        $sql .= " LIMIT %d OFFSET %d";
        $sql_args[] = $args['limit'];
        $sql_args[] = $args['offset'];
        
        $results = $wpdb->get_results($wpdb->prepare($sql, $sql_args));
        
        if ($results) {
            $results = array_map([$this, 'process_result'], $results);
        }
        
        return $results ?: [];
    }
    
    /**
     * Ottieni quote da token di pagamento
     * 
     * @param string $token Token di pagamento
     * @return object|null
     */
    public function get_by_token($token) {
        global $wpdb;
        
        $cache_key = "token_{$token}";
        
        // Check cache
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }
        
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->full_table_name} 
             WHERE payment_token = %s 
             AND deleted_at IS NULL 
             AND (token_expires_at IS NULL OR token_expires_at > NOW())",
            $token
        ));
        
        if ($result) {
            $result = $this->process_result($result);
            $this->cache[$cache_key] = $result;
        }
        
        return $result;
    }
    
    /**
     * Ottieni quote che necessitano di reminder
     * 
     * @param int $limit Numero massimo di risultati
     * @return array
     */
    public function get_pending_reminders($limit = 100) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->full_table_name} 
             WHERE payment_status IN ('pending', 'processing')
             AND deleted_at IS NULL
             AND next_reminder_at <= NOW()
             ORDER BY next_reminder_at ASC
             LIMIT %d",
            $limit
        ));
        
        if ($results) {
            $results = array_map([$this, 'process_result'], $results);
        }
        
        return $results ?: [];
    }
    
    /**
     * Esegue transazione atomica
     * 
     * @param callable $callback Funzione da eseguire
     * @return mixed Risultato della callback
     * @throws Exception In caso di errore
     */
    public function transaction($callback) {
        global $wpdb;
        
        $wpdb->query('START TRANSACTION');
        
        try {
            $result = call_user_func($callback, $this);
            
            $wpdb->query('COMMIT');
            
            return $result;
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            
            $this->log_error('transaction', $e->getMessage());
            
            throw $e;
        }
    }
    
    /**
     * Crea multiple quote in transazione
     * 
     * @param array $shares_data Array di dati quote
     * @return array IDs inseriti
     * @throws Exception In caso di errore
     */
    public function create_bulk($shares_data) {
        return $this->transaction(function($manager) use ($shares_data) {
            $ids = [];
            
            foreach ($shares_data as $data) {
                $id = $manager->create($data);
                
                if (!$id) {
                    throw new Exception('Failed to create share');
                }
                
                $ids[] = $id;
            }
            
            return $ids;
        });
    }
    
    /**
     * Aggiorna stato pagamento
     * 
     * @param int $id ID quota
     * @param string $status Nuovo stato
     * @param array $additional_data Dati aggiuntivi
     * @return bool
     */
    public function update_payment_status($id, $status, $additional_data = []) {
        $update_data = array_merge($additional_data, [
            'payment_status' => $status
        ]);
        
        // Aggiungi timestamp specifici per stato
        switch ($status) {
            case 'paid':
                $update_data['paid_at'] = current_time('mysql');
                break;
            case 'failed':
                $update_data['failed_at'] = current_time('mysql');
                break;
        }
        
        return $this->update($id, $update_data);
    }
    
    /**
     * Incrementa contatore reminder
     * 
     * @param int $id ID quota
     * @param DateTime|null $next_reminder Data prossimo reminder
     * @return bool
     */
    public function increment_reminder_count($id, $next_reminder = null) {
        global $wpdb;
        
        $sql = "UPDATE {$this->full_table_name} 
                SET reminder_count = reminder_count + 1,
                    reminder_sent_at = NOW()";
        
        $values = [];
        
        if ($next_reminder) {
            $sql .= ", next_reminder_at = %s";
            $values[] = $next_reminder->format('Y-m-d H:i:s');
        }
        
        $sql .= " WHERE id = %d";
        $values[] = $id;
        
        $result = $wpdb->query($wpdb->prepare($sql, $values));
        
        if (false !== $result) {
            $this->clear_cache();
        }
        
        return false !== $result;
    }
    
    /**
     * Ottieni statistiche per ordine
     * 
     * @param int $order_id ID ordine
     * @return object
     */
    public function get_order_statistics($order_id) {
        global $wpdb;
        
        $cache_key = "stats_order_{$order_id}";
        
        // Check cache
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }
        
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_shares,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_shares,
                SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_shares,
                SUM(CASE WHEN payment_status = 'failed' THEN 1 ELSE 0 END) as failed_shares,
                SUM(amount_assigned) as total_amount,
                SUM(amount_paid) as total_paid,
                SUM(CASE WHEN payment_status = 'paid' THEN amount_assigned ELSE 0 END) as confirmed_amount
             FROM {$this->full_table_name}
             WHERE order_id = %d AND deleted_at IS NULL",
            $order_id
        ));
        
        if ($stats) {
            // Calcola percentuali
            $stats->completion_percentage = $stats->total_shares > 0 
                ? round(($stats->paid_shares / $stats->total_shares) * 100, 2)
                : 0;
            
            $stats->payment_percentage = $stats->total_amount > 0
                ? round(($stats->total_paid / $stats->total_amount) * 100, 2)
                : 0;
            
            $this->cache[$cache_key] = $stats;
        }
        
        return $stats;
    }
    
    /**
     * Query personalizzata
     * 
     * @param string $where Clausola WHERE
     * @param array $args Argomenti di query
     * @return array
     */
    public function query($where, $args = []) {
        global $wpdb;
        
        $defaults = [
            'select' => '*',
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => -1,
            'offset' => 0,
            'include_deleted' => false
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $sql = "SELECT {$args['select']} FROM {$this->full_table_name}";
        
        if ($where) {
            $sql .= " WHERE {$where}";
            
            if (!$args['include_deleted']) {
                $sql .= " AND deleted_at IS NULL";
            }
        } elseif (!$args['include_deleted']) {
            $sql .= " WHERE deleted_at IS NULL";
        }
        
        $sql .= $this->build_order_clause($args['orderby'], $args['order']);
        
        if ($args['limit'] > 0) {
            $sql .= $wpdb->prepare(" LIMIT %d OFFSET %d", $args['limit'], $args['offset']);
        }
        
        $results = $wpdb->get_results($sql);
        
        if ($results && $args['select'] === '*') {
            $results = array_map([$this, 'process_result'], $results);
        }
        
        return $results ?: [];
    }
    
    /**
     * Conta record
     * 
     * @param array $conditions Condizioni WHERE
     * @return int
     */
    public function count($conditions = []) {
        global $wpdb;
        
        $sql = "SELECT COUNT(*) FROM {$this->full_table_name}";
        $where_clauses = [];
        $values = [];
        
        if (!empty($conditions)) {
            foreach ($conditions as $field => $value) {
                if ($field === 'deleted_at' && $value === null) {
                    $where_clauses[] = "deleted_at IS NULL";
                } else {
                    $where_clauses[] = "{$field} = %s";
                    $values[] = $value;
                }
            }
            
            if ($where_clauses) {
                $sql .= " WHERE " . implode(' AND ', $where_clauses);
            }
        }
        
        if ($values) {
            $sql = $wpdb->prepare($sql, $values);
        }
        
        return (int) $wpdb->get_var($sql);
    }
    
    /**
     * Genera token sicuro
     * 
     * @return string
     */
    private function generate_secure_token() {
        return wp_hash(uniqid('btr_share_', true) . wp_rand());
    }
    
    /**
     * Valida dati input
     * 
     * @param array $data Dati da validare
     * @param bool $is_update Se è un update (alcuni campi opzionali)
     * @return array|WP_Error
     */
    private function validate_data($data, $is_update = false) {
        $errors = new WP_Error();
        
        // Campi richiesti per creazione
        if (!$is_update) {
            $required_fields = [
                'order_id' => 'ID ordine',
                'participant_id' => 'ID partecipante',
                'participant_name' => 'Nome partecipante',
                'participant_email' => 'Email partecipante',
                'amount_assigned' => 'Importo assegnato'
            ];
            
            foreach ($required_fields as $field => $label) {
                if (empty($data[$field])) {
                    $errors->add('missing_field', sprintf('%s è richiesto', $label));
                }
            }
        }
        
        // Validazione email
        if (!empty($data['participant_email']) && !is_email($data['participant_email'])) {
            $errors->add('invalid_email', 'Email non valida');
        }
        
        // Validazione importi
        if (isset($data['amount_assigned']) && $data['amount_assigned'] < 0) {
            $errors->add('invalid_amount', 'Importo assegnato non può essere negativo');
        }
        
        if (isset($data['amount_paid']) && $data['amount_paid'] < 0) {
            $errors->add('invalid_amount', 'Importo pagato non può essere negativo');
        }
        
        // Validazione stato pagamento
        $valid_statuses = ['pending', 'processing', 'paid', 'failed', 'expired', 'cancelled', 'refunded'];
        if (isset($data['payment_status']) && !in_array($data['payment_status'], $valid_statuses)) {
            $errors->add('invalid_status', 'Stato pagamento non valido');
        }
        
        if ($errors->has_errors()) {
            return $errors;
        }
        
        // Sanitizza dati
        $sanitized = [];
        
        // Interi
        $int_fields = ['order_id', 'participant_id', 'reminder_count'];
        foreach ($int_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = absint($data[$field]);
            }
        }
        
        // Stringhe
        $string_fields = [
            'participant_name', 'participant_phone', 'currency', 
            'payment_method', 'payment_status', 'payment_link', 
            'payment_token', 'transaction_id', 'failure_reason', 'notes'
        ];
        foreach ($string_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = sanitize_text_field($data[$field]);
            }
        }
        
        // Email
        if (isset($data['participant_email'])) {
            $sanitized['participant_email'] = sanitize_email($data['participant_email']);
        }
        
        // Decimali
        $decimal_fields = ['amount_assigned', 'amount_paid'];
        foreach ($decimal_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = floatval($data[$field]);
            }
        }
        
        // Date
        $date_fields = [
            'token_expires_at', 'paid_at', 'failed_at', 
            'reminder_sent_at', 'next_reminder_at', 'deleted_at'
        ];
        foreach ($date_fields as $field) {
            if (isset($data[$field])) {
                $sanitized[$field] = $data[$field]; // Assumiamo formato MySQL
            }
        }
        
        // Metadata (JSON)
        if (isset($data['metadata'])) {
            $sanitized['metadata'] = $data['metadata']; // Sarà serializzato dopo
        }
        
        return $sanitized;
    }
    
    /**
     * Ottieni formati dati per prepared statements
     * 
     * @param array $data Dati
     * @return array Formati
     */
    private function get_data_formats($data) {
        $formats = [];
        
        $format_map = [
            'order_id' => '%d',
            'participant_id' => '%d',
            'participant_name' => '%s',
            'participant_email' => '%s',
            'participant_phone' => '%s',
            'amount_assigned' => '%f',
            'amount_paid' => '%f',
            'currency' => '%s',
            'payment_method' => '%s',
            'payment_status' => '%s',
            'payment_link' => '%s',
            'payment_token' => '%s',
            'token_expires_at' => '%s',
            'transaction_id' => '%s',
            'paid_at' => '%s',
            'failed_at' => '%s',
            'failure_reason' => '%s',
            'reminder_sent_at' => '%s',
            'reminder_count' => '%d',
            'next_reminder_at' => '%s',
            'notes' => '%s',
            'metadata' => '%s',
            'deleted_at' => '%s'
        ];
        
        foreach ($data as $field => $value) {
            if (isset($format_map[$field])) {
                $formats[] = $format_map[$field];
            }
        }
        
        return $formats;
    }
    
    /**
     * Processa risultato da database
     * 
     * @param object $result Risultato raw
     * @return object Risultato processato
     */
    private function process_result($result) {
        if (!$result) {
            return $result;
        }
        
        // Deserializza metadata
        if (!empty($result->metadata)) {
            $result->metadata = json_decode($result->metadata, true);
        }
        
        // Converti stringhe numeriche
        $result->id = (int) $result->id;
        $result->order_id = (int) $result->order_id;
        $result->participant_id = (int) $result->participant_id;
        $result->amount_assigned = (float) $result->amount_assigned;
        $result->amount_paid = (float) $result->amount_paid;
        $result->reminder_count = (int) $result->reminder_count;
        
        return $result;
    }
    
    /**
     * Costruisci clausola ORDER BY
     * 
     * @param string $orderby Campo ordinamento
     * @param string $order Direzione (ASC/DESC)
     * @return string
     */
    private function build_order_clause($orderby, $order) {
        $valid_orderby = [
            'id', 'order_id', 'participant_name', 'participant_email',
            'amount_assigned', 'amount_paid', 'payment_status',
            'created_at', 'updated_at', 'paid_at'
        ];
        
        if (!in_array($orderby, $valid_orderby)) {
            $orderby = 'created_at';
        }
        
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        
        return " ORDER BY {$orderby} {$order}";
    }
    
    /**
     * Pulisci cache
     */
    public function clear_cache() {
        $this->cache = [];
    }
    
    /**
     * Pulisci cache per ordine specifico
     * 
     * @param int $order_id ID ordine
     */
    private function clear_cache_for_order($order_id) {
        foreach ($this->cache as $key => $value) {
            if (strpos($key, "order_{$order_id}") === 0 || strpos($key, "stats_order_{$order_id}") === 0) {
                unset($this->cache[$key]);
            }
        }
    }
    
    /**
     * Log operazione
     * 
     * @param string $operation Tipo operazione
     * @param string $message Messaggio
     */
    private function log_operation($operation, $message) {
        if (defined('BTR_DEBUG') && BTR_DEBUG) {
            btr_debug_log("[BTR_Database_Manager] {$operation}: {$message}");
        }
    }
    
    /**
     * Log errore
     * 
     * @param string $operation Tipo operazione
     * @param string $error Messaggio errore
     */
    private function log_error($operation, $error) {
        error_log("[BTR_Database_Manager] Error in {$operation}: {$error}");
    }
}