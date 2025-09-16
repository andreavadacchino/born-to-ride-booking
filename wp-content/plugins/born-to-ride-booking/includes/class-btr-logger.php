<?php
/**
 * Sistema di Logging Centralizzato BTR
 * 
 * Logger che NON rompe mai nulla
 * Fallback silenzioso sempre
 * 
 * @package BTR
 * @since 1.0.236
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Logger {
    
    const CRITICAL = 'critical';
    const ERROR = 'error';
    const WARNING = 'warning';
    const INFO = 'info';
    const DEBUG = 'debug';
    
    private static $instance = null;
    private $log_dir;
    private $max_log_size = 10485760; // 10MB
    private $log_retention_days = 30;
    
    private function __construct() {
        $this->log_dir = WP_CONTENT_DIR . '/btr-logs';
        $this->ensure_log_directory();
        
        // Pulizia log vecchi (solo una volta al giorno)
        if (get_transient('btr_log_cleanup_done') === false) {
            $this->cleanup_old_logs();
            set_transient('btr_log_cleanup_done', true, DAY_IN_SECONDS);
        }
    }
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Log generico - NON blocca mai
     */
    public function log($message, $level = self::INFO, $context = []) {
        // Non fare nulla se disabilitato
        if (defined('BTR_DISABLE_LOGGING') && BTR_DISABLE_LOGGING) {
            return;
        }
        
        try {
            $log_entry = $this->format_log_entry($message, $level, $context);
            $log_file = $this->get_log_file($level);
            
            // Rotazione log se troppo grande
            $this->rotate_log_if_needed($log_file);
            
            // Scrivi su file
            error_log($log_entry, 3, $log_file);
            
            // Se critico, notifica anche in altro modo
            if ($level === self::CRITICAL) {
                $this->handle_critical($message, $context);
            }
            
        } catch (Exception $e) {
            // Fallisce silenziosamente - non rompere MAI
            // Prova almeno error_log standard
            error_log('[BTR_LOGGER_FAIL] ' . $message);
        }
    }
    
    /**
     * Log errore critico
     */
    public function critical($message, $context = []) {
        $this->log($message, self::CRITICAL, $context);
    }
    
    /**
     * Log errore
     */
    public function error($message, $context = []) {
        $this->log($message, self::ERROR, $context);
    }
    
    /**
     * Log warning
     */
    public function warning($message, $context = []) {
        $this->log($message, self::WARNING, $context);
    }
    
    /**
     * Log info
     */
    public function info($message, $context = []) {
        $this->log($message, self::INFO, $context);
    }
    
    /**
     * Log debug
     */
    public function debug($message, $context = []) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->log($message, self::DEBUG, $context);
        }
    }
    
    /**
     * Log operazione di pagamento
     */
    public function log_payment($operation, $payment_id, $data = []) {
        $context = array_merge([
            'payment_id' => $payment_id,
            'operation' => $operation,
            'user_id' => get_current_user_id(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ], $data);
        
        $message = sprintf('Payment %s: %s', $operation, $payment_id);
        $this->log($message, self::INFO, $context);
    }
    
    /**
     * Log query SQL (per debug)
     */
    public function log_sql($sql, $params = [], $execution_time = null) {
        if (!defined('BTR_DEBUG_SQL') || !BTR_DEBUG_SQL) {
            return;
        }
        
        $context = [
            'sql' => substr($sql, 0, 500),
            'params' => $params,
            'execution_time' => $execution_time,
            'backtrace' => $this->get_simplified_backtrace()
        ];
        
        $this->debug('SQL Query', $context);
    }
    
    /**
     * Log eccezione
     */
    public function log_exception($exception, $context = []) {
        $context['exception'] = [
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];
        
        $this->error('Exception: ' . $exception->getMessage(), $context);
    }
    
    /**
     * Formatta entry di log
     */
    private function format_log_entry($message, $level, $context) {
        $entry = [
            'timestamp' => current_time('mysql'),
            'level' => strtoupper($level),
            'message' => $message,
            'user_id' => get_current_user_id(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'cli',
            'url' => $_SERVER['REQUEST_URI'] ?? 'cli',
            'context' => $context
        ];
        
        // Formato leggibile per file
        $formatted = sprintf(
            "[%s] [%s] %s | User: %d | IP: %s | %s\n",
            $entry['timestamp'],
            $entry['level'],
            $entry['message'],
            $entry['user_id'],
            $entry['ip'],
            !empty($context) ? json_encode($context) : ''
        );
        
        return $formatted;
    }
    
    /**
     * Ottieni file di log per livello
     */
    private function get_log_file($level) {
        $date = date('Y-m-d');
        
        switch ($level) {
            case self::CRITICAL:
            case self::ERROR:
                return $this->log_dir . "/error-{$date}.log";
            
            case self::WARNING:
                return $this->log_dir . "/warning-{$date}.log";
            
            case self::DEBUG:
                return $this->log_dir . "/debug-{$date}.log";
            
            default:
                return $this->log_dir . "/info-{$date}.log";
        }
    }
    
    /**
     * Assicura che directory log esista
     */
    private function ensure_log_directory() {
        if (!file_exists($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
            
            // Crea .htaccess per proteggere i log
            $htaccess = $this->log_dir . '/.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, "Deny from all\n");
            }
            
            // Crea index.php vuoto
            $index = $this->log_dir . '/index.php';
            if (!file_exists($index)) {
                file_put_contents($index, "<?php // Silence is golden\n");
            }
        }
    }
    
    /**
     * Rotazione log se troppo grande
     */
    private function rotate_log_if_needed($log_file) {
        if (file_exists($log_file) && filesize($log_file) > $this->max_log_size) {
            $backup = $log_file . '.' . time() . '.bak';
            rename($log_file, $backup);
            
            // Comprimi backup
            if (function_exists('gzopen')) {
                $gz = gzopen($backup . '.gz', 'w9');
                gzwrite($gz, file_get_contents($backup));
                gzclose($gz);
                unlink($backup);
            }
        }
    }
    
    /**
     * Pulizia log vecchi
     */
    private function cleanup_old_logs() {
        try {
            $files = glob($this->log_dir . '/*.log*');
            $threshold = time() - ($this->log_retention_days * DAY_IN_SECONDS);
            
            foreach ($files as $file) {
                if (filemtime($file) < $threshold) {
                    unlink($file);
                }
            }
        } catch (Exception $e) {
            // Ignora errori di pulizia
        }
    }
    
    /**
     * Gestione errori critici
     */
    private function handle_critical($message, $context) {
        // Notifica admin via email (se abilitato)
        if (defined('BTR_NOTIFY_CRITICAL') && BTR_NOTIFY_CRITICAL) {
            $admin_email = get_option('admin_email');
            $subject = '[BTR CRITICAL] ' . substr($message, 0, 50);
            $body = "Errore critico rilevato:\n\n";
            $body .= "Messaggio: {$message}\n";
            $body .= "Timestamp: " . current_time('mysql') . "\n";
            $body .= "Contesto: " . json_encode($context, JSON_PRETTY_PRINT);
            
            // Non bloccare se email fallisce
            @wp_mail($admin_email, $subject, $body);
        }
        
        // Salva in option per dashboard admin
        $critical_errors = get_option('btr_critical_errors', []);
        $critical_errors[] = [
            'time' => current_time('mysql'),
            'message' => $message,
            'context' => $context
        ];
        
        // Mantieni solo ultimi 10 errori
        if (count($critical_errors) > 10) {
            array_shift($critical_errors);
        }
        
        update_option('btr_critical_errors', $critical_errors);
    }
    
    /**
     * Ottieni backtrace semplificato
     */
    private function get_simplified_backtrace($limit = 5) {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $limit + 2);
        $simplified = [];
        
        // Salta le prime 2 entry (questo metodo e il chiamante)
        for ($i = 2; $i < count($backtrace) && $i < $limit + 2; $i++) {
            $frame = $backtrace[$i];
            $simplified[] = sprintf(
                '%s:%d %s%s%s()',
                $frame['file'] ?? 'unknown',
                $frame['line'] ?? 0,
                $frame['class'] ?? '',
                $frame['type'] ?? '',
                $frame['function'] ?? 'unknown'
            );
        }
        
        return $simplified;
    }
    
    /**
     * Ottieni ultimi log (per admin dashboard)
     */
    public function get_recent_logs($level = null, $limit = 100) {
        $logs = [];
        $pattern = $level ? "/{$level}-*.log" : "/*.log";
        $files = glob($this->log_dir . $pattern);
        
        // Ordina per data modifica (più recenti prima)
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        foreach ($files as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $logs = array_merge($logs, array_slice($lines, -$limit));
            
            if (count($logs) >= $limit) {
                break;
            }
        }
        
        return array_slice($logs, 0, $limit);
    }
    
    /**
     * Clear all logs (admin only)
     */
    public function clear_logs() {
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        $files = glob($this->log_dir . '/*.log*');
        foreach ($files as $file) {
            unlink($file);
        }
        
        return true;
    }
}

// Helper functions globali per facile accesso
if (!function_exists('btr_log')) {
    function btr_log($message, $level = 'info', $context = []) {
        BTR_Logger::get_instance()->log($message, $level, $context);
    }
}

if (!function_exists('btr_log_error')) {
    function btr_log_error($message, $context = []) {
        BTR_Logger::get_instance()->error($message, $context);
    }
}

if (!function_exists('btr_log_critical')) {
    function btr_log_critical($message, $context = []) {
        BTR_Logger::get_instance()->critical($message, $context);
    }
}