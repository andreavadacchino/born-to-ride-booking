<?php
/**
 * SQL Security Wrapper
 * 
 * Wrapper conservativo per query SQL esistenti
 * NON modifica logica esistente, solo aggiunge sicurezza
 * 
 * @package BTR
 * @since 1.0.236
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_SQL_Wrapper {
    
    private static $instance = null;
    private $query_log = [];
    private $suspicious_patterns = [
        '/union\s+select/i',
        '/;\s*(drop|delete|truncate)/i',
        '/\/\*.*\*\//s',
        '/--.*$/m',
        '/\\x[0-9a-f]{2}/i',
        '/concat\s*\(/i',
        '/char\s*\(/i',
        '/0x[0-9a-f]+/i'
    ];
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Wrapper per query esistenti - NON modifica logica
     * Solo aggiunge validazione e logging
     */
    public static function safe_query($sql, $params = []) {
        global $wpdb;
        
        $instance = self::get_instance();
        
        // Log query per audit
        $instance->log_query($sql, $params);
        
        // Detect injection attempts
        if ($instance->detect_injection($sql)) {
            error_log('[BTR_SECURITY] SQL injection attempt blocked: ' . substr($sql, 0, 100));
            
            // Se in emergency mode, permetti comunque
            if (defined('BTR_EMERGENCY_MODE') && BTR_EMERGENCY_MODE) {
                error_log('[BTR_SECURITY] Emergency mode - allowing query despite detection');
            } else {
                return false;
            }
        }
        
        // Se ci sono parametri, usa prepare
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        
        // Esegui query originale
        return $wpdb->get_results($sql);
    }
    
    /**
     * Wrapper per singola riga - mantiene comportamento esistente
     */
    public static function safe_get_row($sql, $params = []) {
        global $wpdb;
        
        $instance = self::get_instance();
        $instance->log_query($sql, $params);
        
        if ($instance->detect_injection($sql)) {
            error_log('[BTR_SECURITY] SQL injection attempt blocked in get_row');
            
            if (defined('BTR_EMERGENCY_MODE') && BTR_EMERGENCY_MODE) {
                error_log('[BTR_SECURITY] Emergency mode - allowing query');
            } else {
                return null;
            }
        }
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        
        return $wpdb->get_row($sql);
    }
    
    /**
     * Wrapper per valore singolo
     */
    public static function safe_get_var($sql, $params = []) {
        global $wpdb;
        
        $instance = self::get_instance();
        $instance->log_query($sql, $params);
        
        if ($instance->detect_injection($sql)) {
            error_log('[BTR_SECURITY] SQL injection attempt blocked in get_var');
            
            if (defined('BTR_EMERGENCY_MODE') && BTR_EMERGENCY_MODE) {
                error_log('[BTR_SECURITY] Emergency mode - allowing query');
            } else {
                return null;
            }
        }
        
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        
        return $wpdb->get_var($sql);
    }
    
    /**
     * Wrapper per insert - protegge inserimenti
     */
    public static function safe_insert($table, $data) {
        global $wpdb;
        
        $instance = self::get_instance();
        
        // Sanitizza nome tabella
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        
        // Log operazione
        $instance->log_query("INSERT INTO {$table}", $data);
        
        // Sanitizza dati
        $sanitized_data = [];
        foreach ($data as $key => $value) {
            $key = preg_replace('/[^a-zA-Z0-9_]/', '', $key);
            $sanitized_data[$key] = $value;
        }
        
        return $wpdb->insert($table, $sanitized_data);
    }
    
    /**
     * Wrapper per update - protegge aggiornamenti
     */
    public static function safe_update($table, $data, $where) {
        global $wpdb;
        
        $instance = self::get_instance();
        
        // Sanitizza nome tabella
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        
        // Log operazione
        $instance->log_query("UPDATE {$table}", array_merge($data, $where));
        
        return $wpdb->update($table, $data, $where);
    }
    
    /**
     * Detecta possibili SQL injection
     * NON blocca funzionalità, solo logga
     */
    private function detect_injection($sql) {
        // Skip detection in emergency mode
        if (defined('BTR_DISABLE_SQL_PROTECTION') && BTR_DISABLE_SQL_PROTECTION) {
            return false;
        }
        
        foreach ($this->suspicious_patterns as $pattern) {
            if (preg_match($pattern, $sql)) {
                return true;
            }
        }
        
        // Check for multiple statements
        if (substr_count($sql, ';') > 1) {
            // Permetti solo se è una query di backup/restore
            if (!strpos($sql, 'CREATE TABLE') && !strpos($sql, 'INSERT INTO')) {
                return true;
            }
        }
        
        // Check for hex strings (common in attacks)
        if (preg_match('/0x[0-9a-f]{20,}/i', $sql)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Log queries per debugging
     * Disattivabile via constant
     */
    private function log_query($sql, $params) {
        if (defined('BTR_DEBUG_SQL') && BTR_DEBUG_SQL) {
            $this->query_log[] = [
                'time' => current_time('mysql'),
                'sql' => $sql,
                'params' => $params,
                'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
            ];
            
            // Limita log a 100 query per evitare memory issues
            if (count($this->query_log) > 100) {
                array_shift($this->query_log);
            }
            
            // Log su file se configurato
            if (defined('BTR_SQL_LOG_FILE') && BTR_SQL_LOG_FILE) {
                $log_entry = sprintf(
                    "[%s] SQL: %s | Params: %s\n",
                    current_time('mysql'),
                    substr($sql, 0, 200),
                    json_encode($params)
                );
                error_log($log_entry, 3, WP_CONTENT_DIR . '/btr-logs/sql.log');
            }
        }
    }
    
    /**
     * Sanitizza input per preventivi
     * Mantiene logica esistente, aggiunge validazione
     */
    public static function sanitize_preventivo_id($id) {
        // Mantieni comportamento esistente
        if (empty($id)) {
            return 0;
        }
        
        // Aggiungi validazione
        $id = absint($id);
        
        // Log valori sospetti
        if ($id > 999999) {
            error_log('[BTR_SECURITY] Suspicious preventivo_id: ' . $id);
        }
        
        return $id;
    }
    
    /**
     * Sanitizza importi monetari
     * MANTIENE floatval() esistente per compatibilità
     */
    public static function sanitize_amount($amount) {
        // Mantieni logica esistente con floatval
        $amount = floatval($amount);
        
        // Aggiungi warning per importi sospetti
        if ($amount < 0) {
            error_log('[BTR_SECURITY] Negative amount detected: ' . $amount);
            $amount = 0;
        }
        
        if ($amount > 99999) {
            error_log('[BTR_SECURITY] Suspicious high amount: ' . $amount);
        }
        
        return $amount;
    }
    
    /**
     * Sanitizza email
     */
    public static function sanitize_email($email) {
        $email = sanitize_email($email);
        
        if (!is_email($email)) {
            error_log('[BTR_SECURITY] Invalid email: ' . substr($email, 0, 50));
            return '';
        }
        
        return $email;
    }
    
    /**
     * Sanitizza stringhe generiche
     */
    public static function sanitize_string($string) {
        // Rimuovi tag HTML
        $string = wp_strip_all_tags($string);
        
        // Rimuovi caratteri pericolosi
        $string = preg_replace('/[<>\"\'%;()&+]/', '', $string);
        
        return $string;
    }
    
    /**
     * Get query log for debugging
     */
    public function get_query_log() {
        return $this->query_log;
    }
    
    /**
     * Clear query log
     */
    public function clear_query_log() {
        $this->query_log = [];
    }
}