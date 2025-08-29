<?php
/**
 * Database Migration System
 * 
 * Sistema di gestione migrations per aggiornamenti incrementali del database
 * 
 * @package BornToRideBooking
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class BTR_Database_Migration
 * 
 * Gestisce le migrations del database con tracking versioni e rollback
 */
class BTR_Database_Migration {
    
    /**
     * Option name per tracking versione database
     */
    const VERSION_OPTION = 'btr_database_version';
    
    /**
     * Option name per tracking migrations eseguite
     */
    const MIGRATIONS_OPTION = 'btr_database_migrations';
    
    /**
     * Directory contenente i file di migration
     */
    const MIGRATIONS_DIR = BTR_PLUGIN_DIR . 'includes/db-updates/';
    
    /**
     * Versione target del database
     */
    const TARGET_VERSION = '1.1.0';
    
    /**
     * Lista delle migrations disponibili
     * @var array
     */
    private static $migrations = [
        '1.0.0' => [
            'file' => 'migration-1.0.0-initial-setup.php',
            'description' => 'Initial database setup',
            'batch_size' => 100
        ],
        '1.1.0' => [
            'file' => 'migration-1.1.0-order-shares.php',
            'description' => 'Add order shares table for group payments',
            'batch_size' => 500
        ]
    ];
    
    /**
     * Run all pending migrations
     * 
     * @return array Results of migration
     */
    public static function run() {
        $current_version = get_option(self::VERSION_OPTION, '0.0.0');
        $executed_migrations = get_option(self::MIGRATIONS_OPTION, []);
        $results = [];
        
        btr_debug_log("Starting database migration from version $current_version to " . self::TARGET_VERSION);
        
        // Ordina le migrations per versione
        uksort(self::$migrations, 'version_compare');
        
        foreach (self::$migrations as $version => $migration_info) {
            // Skip se già eseguita o versione inferiore
            if (version_compare($version, $current_version, '<=')) {
                continue;
            }
            
            // Skip se già nelle migrations eseguite
            if (in_array($version, $executed_migrations)) {
                continue;
            }
            
            $result = self::run_single_migration($version, $migration_info);
            $results[$version] = $result;
            
            if ($result['success']) {
                // Aggiorna versione corrente
                update_option(self::VERSION_OPTION, $version);
                
                // Aggiungi a migrations eseguite
                $executed_migrations[] = $version;
                update_option(self::MIGRATIONS_OPTION, $executed_migrations);
                
                btr_debug_log("Migration $version completed successfully");
            } else {
                btr_debug_log("Migration $version failed: " . $result['error'], 'error');
                break; // Stop su errore
            }
        }
        
        return $results;
    }
    
    /**
     * Run a single migration
     * 
     * @param string $version Version number
     * @param array $migration_info Migration details
     * @return array Result
     */
    private static function run_single_migration($version, $migration_info) {
        $file_path = self::MIGRATIONS_DIR . $migration_info['file'];
        
        if (!file_exists($file_path)) {
            return [
                'success' => false,
                'error' => "Migration file not found: {$migration_info['file']}"
            ];
        }
        
        // Backup database before migration
        $backup_result = self::backup_before_migration($version);
        if (!$backup_result['success']) {
            return $backup_result;
        }
        
        // Start transaction
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        
        try {
            // Include e esegui migration
            require_once $file_path;
            
            $migration_class = 'BTR_Migration_' . str_replace('.', '_', $version);
            if (!class_exists($migration_class)) {
                throw new Exception("Migration class $migration_class not found");
            }
            
            $migration = new $migration_class();
            
            // Run up method
            if (!method_exists($migration, 'up')) {
                throw new Exception("Migration class missing 'up' method");
            }
            
            $migration->up();
            
            // Log migration
            self::log_migration($version, $migration_info['description'], 'completed');
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            return [
                'success' => true,
                'message' => "Migration $version completed"
            ];
            
        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            
            // Log error
            self::log_migration($version, $migration_info['description'], 'failed', $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Rollback to a specific version
     * 
     * @param string $target_version Version to rollback to
     * @return array Results
     */
    public static function rollback($target_version = '0.0.0') {
        $current_version = get_option(self::VERSION_OPTION, '0.0.0');
        $executed_migrations = get_option(self::MIGRATIONS_OPTION, []);
        $results = [];
        
        btr_debug_log("Starting rollback from version $current_version to $target_version");
        
        // Ordina migrations in ordine decrescente per rollback
        krsort(self::$migrations);
        
        foreach (self::$migrations as $version => $migration_info) {
            // Skip se versione target o inferiore
            if (version_compare($version, $target_version, '<=')) {
                continue;
            }
            
            // Skip se non nelle migrations eseguite
            if (!in_array($version, $executed_migrations)) {
                continue;
            }
            
            $result = self::rollback_single_migration($version, $migration_info);
            $results[$version] = $result;
            
            if ($result['success']) {
                // Rimuovi da migrations eseguite
                $executed_migrations = array_diff($executed_migrations, [$version]);
                update_option(self::MIGRATIONS_OPTION, $executed_migrations);
                
                btr_debug_log("Rollback $version completed successfully");
            } else {
                btr_debug_log("Rollback $version failed: " . $result['error'], 'error');
                break; // Stop su errore
            }
        }
        
        // Aggiorna versione corrente
        update_option(self::VERSION_OPTION, $target_version);
        
        return $results;
    }
    
    /**
     * Rollback a single migration
     * 
     * @param string $version Version number
     * @param array $migration_info Migration details
     * @return array Result
     */
    private static function rollback_single_migration($version, $migration_info) {
        $file_path = self::MIGRATIONS_DIR . $migration_info['file'];
        
        if (!file_exists($file_path)) {
            return [
                'success' => false,
                'error' => "Migration file not found: {$migration_info['file']}"
            ];
        }
        
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        
        try {
            // Include migration class
            require_once $file_path;
            
            $migration_class = 'BTR_Migration_' . str_replace('.', '_', $version);
            if (!class_exists($migration_class)) {
                throw new Exception("Migration class $migration_class not found");
            }
            
            $migration = new $migration_class();
            
            // Run down method
            if (!method_exists($migration, 'down')) {
                throw new Exception("Migration class missing 'down' method");
            }
            
            $migration->down();
            
            // Log rollback
            self::log_migration($version, $migration_info['description'], 'rolled_back');
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            return [
                'success' => true,
                'message' => "Rollback $version completed"
            ];
            
        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            
            // Log error
            self::log_migration($version, $migration_info['description'], 'rollback_failed', $e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Backup tables before migration
     * 
     * @param string $version Migration version
     * @return array Result
     */
    private static function backup_before_migration($version) {
        global $wpdb;
        
        // Lista tabelle da backuppare
        $tables_to_backup = [
            $wpdb->prefix . 'btr_order_shares'
        ];
        
        $backup_suffix = '_backup_' . date('Ymd_His') . '_v' . str_replace('.', '', $version);
        
        try {
            foreach ($tables_to_backup as $table) {
                // Verifica se la tabella esiste
                if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
                    continue;
                }
                
                $backup_table = $table . $backup_suffix;
                
                // Crea backup
                $wpdb->query("CREATE TABLE IF NOT EXISTS $backup_table LIKE $table");
                $wpdb->query("INSERT INTO $backup_table SELECT * FROM $table");
                
                btr_debug_log("Backup created for table $table as $backup_table");
            }
            
            return ['success' => true];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Backup failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Log migration activity
     * 
     * @param string $version Version
     * @param string $description Description
     * @param string $status Status
     * @param string $error Error message if any
     */
    private static function log_migration($version, $description, $status, $error = '') {
        global $wpdb;
        
        $log_table = $wpdb->prefix . 'btr_migration_log';
        
        // Crea tabella log se non esiste
        $wpdb->query("
            CREATE TABLE IF NOT EXISTS $log_table (
                id int(11) NOT NULL AUTO_INCREMENT,
                version varchar(20) NOT NULL,
                description text,
                status varchar(50) NOT NULL,
                error_message text,
                executed_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_version (version),
                KEY idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Inserisci log
        $wpdb->insert($log_table, [
            'version' => $version,
            'description' => $description,
            'status' => $status,
            'error_message' => $error
        ]);
    }
    
    /**
     * Get migration status
     * 
     * @return array Status info
     */
    public static function get_status() {
        $current_version = get_option(self::VERSION_OPTION, '0.0.0');
        $executed_migrations = get_option(self::MIGRATIONS_OPTION, []);
        $pending_migrations = [];
        
        foreach (self::$migrations as $version => $info) {
            if (version_compare($version, $current_version, '>') && 
                !in_array($version, $executed_migrations)) {
                $pending_migrations[$version] = $info;
            }
        }
        
        return [
            'current_version' => $current_version,
            'target_version' => self::TARGET_VERSION,
            'executed_migrations' => $executed_migrations,
            'pending_migrations' => $pending_migrations,
            'up_to_date' => empty($pending_migrations)
        ];
    }
    
    /**
     * Check if migrations are needed
     * 
     * @return bool
     */
    public static function needs_migration() {
        $status = self::get_status();
        return !$status['up_to_date'];
    }
    
    /**
     * Get migration history
     * 
     * @param int $limit Limit results
     * @return array History
     */
    public static function get_history($limit = 50) {
        global $wpdb;
        
        $log_table = $wpdb->prefix . 'btr_migration_log';
        
        // Verifica se tabella esiste
        if ($wpdb->get_var("SHOW TABLES LIKE '$log_table'") !== $log_table) {
            return [];
        }
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM $log_table 
            ORDER BY executed_at DESC 
            LIMIT %d
        ", $limit), ARRAY_A);
    }
    
    /**
     * Clean old backups
     * 
     * @param int $days_to_keep Days to keep backups
     * @return int Number of tables dropped
     */
    public static function clean_old_backups($days_to_keep = 30) {
        global $wpdb;
        
        $count = 0;
        $cutoff_date = date('Ymd', strtotime("-$days_to_keep days"));
        
        // Pattern per trovare tabelle backup
        $pattern = $wpdb->prefix . 'btr_%_backup_%';
        $tables = $wpdb->get_col("SHOW TABLES LIKE '$pattern'");
        
        foreach ($tables as $table) {
            // Estrai data dal nome tabella
            if (preg_match('/_backup_(\d{8})_/', $table, $matches)) {
                $backup_date = $matches[1];
                
                if ($backup_date < $cutoff_date) {
                    $wpdb->query("DROP TABLE IF EXISTS $table");
                    $count++;
                    btr_debug_log("Dropped old backup table: $table");
                }
            }
        }
        
        return $count;
    }
}

// Hook per controllo migrations su admin init
add_action('admin_init', function() {
    if (BTR_Database_Migration::needs_migration()) {
        // Mostra notice admin
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-warning">
                <p><?php _e('Born to Ride Booking: Il database richiede aggiornamento.', 'born-to-ride-booking'); ?>
                <a href="<?php echo admin_url('admin.php?page=btr-database-migration'); ?>" class="button button-primary">
                    <?php _e('Aggiorna Database', 'born-to-ride-booking'); ?>
                </a></p>
            </div>
            <?php
        });
    }
});

// WP-CLI commands se disponibile
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('btr migrate', function($args, $assoc_args) {
        $results = BTR_Database_Migration::run();
        
        foreach ($results as $version => $result) {
            if ($result['success']) {
                WP_CLI::success("Migration $version completed");
            } else {
                WP_CLI::error("Migration $version failed: " . $result['error']);
            }
        }
    });
    
    WP_CLI::add_command('btr migrate:rollback', function($args, $assoc_args) {
        $target = isset($args[0]) ? $args[0] : '0.0.0';
        $results = BTR_Database_Migration::rollback($target);
        
        foreach ($results as $version => $result) {
            if ($result['success']) {
                WP_CLI::success("Rollback $version completed");
            } else {
                WP_CLI::error("Rollback $version failed: " . $result['error']);
            }
        }
    });
    
    WP_CLI::add_command('btr migrate:status', function() {
        $status = BTR_Database_Migration::get_status();
        
        WP_CLI::line("Current version: " . $status['current_version']);
        WP_CLI::line("Target version: " . $status['target_version']);
        
        if ($status['up_to_date']) {
            WP_CLI::success("Database is up to date");
        } else {
            WP_CLI::warning("Pending migrations:");
            foreach ($status['pending_migrations'] as $version => $info) {
                WP_CLI::line("  - $version: " . $info['description']);
            }
        }
    });
}