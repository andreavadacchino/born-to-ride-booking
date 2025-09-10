<?php
/**
 * Gestione automatica degli aggiornamenti del database
 * 
 * Questa classe gestisce in modo sicuro e automatico gli aggiornamenti
 * del database del plugin Born to Ride Booking.
 * 
 * @package BornToRideBooking
 * @since 1.0.98
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Database_Updater {
    
    /**
     * Versione corrente del database
     * @var string
     */
    private $current_db_version;
    
    /**
     * Array degli update disponibili
     * @var array
     */
    private $available_updates = [];
    
    /**
     * Progress tracking per gli update
     * @var array
     */
    private $update_progress = [];
    
    /**
     * Nome del transient per il lock
     * @var string
     */
    private const LOCK_TRANSIENT = 'btr_db_updating';
    
    /**
     * Nome dell'option per il log
     * @var string
     */
    private const LOG_OPTION = 'btr_db_update_log';
    
    /**
     * Numero massimo di log entries da mantenere
     * @var int
     */
    private const MAX_LOG_ENTRIES = 100;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->current_db_version = get_option('btr_db_version', '0');
        $this->discover_updates();
    }
    
    /**
     * Hook di bootstrap per controllo e esecuzione update
     * Da chiamare su plugins_loaded o admin_init
     */
    public function check_and_run_updates() {
        // Verifica se ci sono update da eseguire
        if (!$this->has_pending_updates()) {
            return false;
        }
        
        // Verifica permessi (solo admin può triggerare update)
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        // Esegui gli update
        return $this->run_updates();
    }
    
    /**
     * Scopre automaticamente gli update disponibili
     * nella cartella includes/db-updates/
     */
    private function discover_updates() {
        $update_dir = BTR_PLUGIN_DIR . 'includes/db-updates/';
        
        // Verifica che la directory esista
        if (!is_dir($update_dir)) {
            $this->log_event('error', 'discovery', [
                'message' => 'Update directory not found: ' . $update_dir
            ]);
            return;
        }
        
        // Scansiona i file di update
        $update_files = glob($update_dir . 'update-*.php');
        
        if (empty($update_files)) {
            return;
        }
        
        foreach ($update_files as $file) {
            // Estrai versione dal nome file
            if (preg_match('/update-(.+)\.php$/', basename($file), $matches)) {
                $version = $matches[1];
                
                // Valida che sia una versione valida
                if ($this->is_valid_version($version)) {
                    $this->available_updates[$version] = $file;
                }
            }
        }
        
        // Ordina per versione (dal più vecchio al più recente)
        uksort($this->available_updates, 'version_compare');
        
        $this->log_event('info', 'discovery', [
            'found_updates' => array_keys($this->available_updates)
        ]);
    }
    
    /**
     * Esegue gli update in sequenza dalla versione corrente
     * 
     * @return bool True se tutti gli update sono stati completati con successo
     */
    public function run_updates() {
        // Previeni esecuzioni multiple con transient lock
        if (get_transient(self::LOCK_TRANSIENT)) {
            $this->log_event('warning', 'run', [
                'message' => 'Update already in progress'
            ]);
            return false;
        }
        
        // Imposta lock (valido per 1 ora)
        set_transient(self::LOCK_TRANSIENT, true, HOUR_IN_SECONDS);
        
        // Aumenta limiti per operazioni lunghe
        $this->set_resource_limits();
        
        $all_success = true;
        $executed_updates = [];
        
        try {
            foreach ($this->available_updates as $version => $file) {
                // Salta versioni già applicate
                if (version_compare($this->current_db_version, $version, '>=')) {
                    continue;
                }
                
                $this->update_progress[] = [
                    'version' => $version,
                    'status' => 'starting',
                    'timestamp' => current_time('mysql')
                ];
                
                try {
                    $this->execute_update($version, $file);
                    $executed_updates[] = $version;
                    
                    $this->update_progress[] = [
                        'version' => $version,
                        'status' => 'completed',
                        'timestamp' => current_time('mysql')
                    ];
                    
                } catch (Exception $e) {
                    $all_success = false;
                    
                    $this->update_progress[] = [
                        'version' => $version,
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                        'timestamp' => current_time('mysql')
                    ];
                    
                    // Interrompi la catena di update in caso di errore
                    break;
                }
            }
            
        } finally {
            // Rimuovi sempre il lock
            delete_transient(self::LOCK_TRANSIENT);
        }
        
        // Log riepilogo finale
        $this->log_event('info', 'run_complete', [
            'executed' => $executed_updates,
            'success' => $all_success,
            'progress' => $this->update_progress
        ]);
        
        return $all_success;
    }
    
    /**
     * Esegue singolo update con supporto transazionale
     * 
     * @param string $version Versione dell'update
     * @param string $file Path del file di update
     * @throws Exception In caso di errore
     */
    private function execute_update($version, $file) {
        global $wpdb;
        
        $this->log_event('info', 'update_start', [
            'version' => $version,
            'file' => basename($file)
        ]);
        
        // Verifica che il file esista
        if (!file_exists($file)) {
            throw new Exception("Update file not found: {$file}");
        }
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Include update file
            require_once $file;
            
            // Costruisci nome della funzione secondo convenzione
            $function_name = 'btr_update_database_' . str_replace('.', '_', $version);
            
            if (!function_exists($function_name)) {
                throw new Exception("Update function not found: {$function_name}");
            }
            
            // Esegui la funzione di update
            $result = call_user_func($function_name);
            
            if ($result !== true) {
                throw new Exception('Update function returned false or invalid result');
            }
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            // Aggiorna versione database
            update_option('btr_db_version', $version);
            $this->current_db_version = $version;
            
            $this->log_event('success', 'update_complete', [
                'version' => $version
            ]);
            
        } catch (Exception $e) {
            // Rollback in caso di errore
            $wpdb->query('ROLLBACK');
            
            $this->log_event('error', 'update_failed', [
                'version' => $version,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Marca come fallito per retry manuale
            update_option('btr_failed_update_' . $version, [
                'error' => $e->getMessage(),
                'timestamp' => current_time('timestamp'),
                'file' => basename($file)
            ]);
            
            // Rilancia l'eccezione
            throw $e;
        }
    }
    
    /**
     * Verifica se ci sono update pendenti
     * 
     * @return bool
     */
    private function has_pending_updates() {
        foreach ($this->available_updates as $version => $file) {
            if (version_compare($this->current_db_version, $version, '<')) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Valida formato versione
     * 
     * @param string $version
     * @return bool
     */
    private function is_valid_version($version) {
        // Accetta formati come 1.0.98, 2.0, 1.0.0-beta1
        return (bool) preg_match('/^\d+\.\d+(\.\d+)?(-\w+)?$/', $version);
    }
    
    /**
     * Imposta limiti di risorse per operazioni lunghe
     */
    private function set_resource_limits() {
        // Aumenta memory limit se possibile
        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit('admin');
        }
        
        // Rimuovi time limit se possibile
        if (!ini_get('safe_mode')) {
            @set_time_limit(0);
        }
    }
    
    /**
     * Log evento nel sistema di logging
     * 
     * @param string $type Tipo di evento (info, warning, error, success)
     * @param string $action Azione eseguita
     * @param array $data Dati aggiuntivi
     */
    private function log_event($type, $action, $data = []) {
        $log_entry = [
            'type' => $type,
            'action' => $action,
            'timestamp' => current_time('mysql'),
            'version' => $this->current_db_version,
            'data' => $data
        ];
        
        // Recupera log esistente
        $log = get_option(self::LOG_OPTION, []);
        
        // Aggiungi nuovo entry
        $log[] = $log_entry;
        
        // Mantieni solo ultimi MAX_LOG_ENTRIES
        if (count($log) > self::MAX_LOG_ENTRIES) {
            $log = array_slice($log, -self::MAX_LOG_ENTRIES);
        }
        
        // Salva log aggiornato
        update_option(self::LOG_OPTION, $log);
        
        // Log anche in error_log se debug attivo
        if (defined('BTR_DEBUG') && BTR_DEBUG) {
            error_log(sprintf(
                '[BTR DB Update] %s - %s: %s',
                strtoupper($type),
                $action,
                json_encode($data)
            ));
        }
    }
    
    /**
     * Ottieni log degli update
     * 
     * @param int $limit Numero di entries da recuperare
     * @return array
     */
    public function get_update_log($limit = 50) {
        $log = get_option(self::LOG_OPTION, []);
        
        // Ritorna ultimi $limit entries
        return array_slice($log, -$limit);
    }
    
    /**
     * Ottieni lista degli update pendenti
     * 
     * @return array
     */
    public function get_pending_updates() {
        $pending = [];
        
        foreach ($this->available_updates as $version => $file) {
            if (version_compare($this->current_db_version, $version, '<')) {
                $pending[] = [
                    'version' => $version,
                    'file' => basename($file),
                    'path' => $file
                ];
            }
        }
        
        return $pending;
    }
    
    /**
     * Ottieni informazioni sugli update falliti
     * 
     * @return array
     */
    public function get_failed_updates() {
        global $wpdb;
        
        $failed = [];
        
        // Cerca tutte le option che iniziano con 'btr_failed_update_'
        $options = $wpdb->get_results(
            "SELECT option_name, option_value 
             FROM {$wpdb->options} 
             WHERE option_name LIKE 'btr_failed_update_%'",
            ARRAY_A
        );
        
        foreach ($options as $option) {
            $version = str_replace('btr_failed_update_', '', $option['option_name']);
            $data = maybe_unserialize($option['option_value']);
            
            $failed[$version] = $data;
        }
        
        return $failed;
    }
    
    /**
     * Pulisce i log degli update falliti
     * 
     * @param string|null $version Versione specifica o null per tutti
     */
    public function clear_failed_updates($version = null) {
        if ($version) {
            delete_option('btr_failed_update_' . $version);
        } else {
            global $wpdb;
            $wpdb->query(
                "DELETE FROM {$wpdb->options} 
                 WHERE option_name LIKE 'btr_failed_update_%'"
            );
        }
    }
}