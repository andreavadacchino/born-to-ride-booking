<?php
/**
 * BTR Diagnostic AJAX Handler
 *
 * @package Born_To_Ride_Booking
 * @version 1.0.240
 * @since 1.0.240
 *
 * Gestisce tutte le richieste AJAX per il sistema diagnostico BTR
 */

class BTR_Diagnostic_Ajax {

    /**
     * Initialize AJAX handlers
     */
    public static function init() {
        // Fix handlers
        add_action('wp_ajax_btr_diagnostic_fix', [__CLASS__, 'handle_diagnostic_fix']);

        // Test handlers
        add_action('wp_ajax_btr_test_calculation', [__CLASS__, 'handle_test_calculation']);

        // Monitoring handlers
        add_action('wp_ajax_btr_get_live_metrics', [__CLASS__, 'handle_get_live_metrics']);

        // Export handlers
        add_action('wp_ajax_btr_export_diagnostic', [__CLASS__, 'handle_export_diagnostic']);
    }

    /**
     * Handle diagnostic fix requests
     */
    public static function handle_diagnostic_fix() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'btr_diagnostic_action')) {
            wp_send_json_error(['message' => 'Controllo sicurezza fallito']);
            return;
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permessi insufficienti']);
            return;
        }

        $fix_type = sanitize_text_field($_POST['fix_type'] ?? '');

        switch ($fix_type) {
            case 'clear_cache':
            case 'clear_all_cache':
                self::fix_clear_cache();
                break;

            case 'sync_versions':
            case 'update_database_version':
                self::fix_sync_versions();
                break;

            case 'create_tables':
            case 'create_missing_tables':
                self::fix_create_tables();
                break;

            case 'fix_meta_keys':
                self::fix_meta_keys();
                break;

            case 'optimize_database':
                self::fix_optimize_database();
                break;

            case 'reset_calculator_soft':
                self::fix_reset_calculator('soft');
                break;

            case 'reset_calculator_hard':
                self::fix_reset_calculator('hard');
                break;

            case 'compatibility_mode':
                self::fix_compatibility_mode();
                break;

            case 'rebuild_indexes':
                self::fix_rebuild_indexes();
                break;

            case 'reset_permissions':
                self::fix_reset_permissions();
                break;

            case 'emergency_reset':
                self::fix_emergency_reset();
                break;

            default:
                wp_send_json_error(['message' => 'Tipo di fix sconosciuto: ' . $fix_type]);
        }
    }

    /**
     * Clear cache fix
     */
    private static function fix_clear_cache() {
        global $wpdb;

        // Clear BTR transients
        $deleted = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_btr_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_btr_%'");

        // Clear object cache if available
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        // Clear BTR specific caches
        delete_option('btr_cache_timestamp');
        delete_option('btr_calculation_cache');

        // Log the action
        error_log("[BTR Diagnostic] Cache cleared: {$deleted} transients removed");

        wp_send_json_success([
            'message' => "Cache pulita con successo. Rimosse {$deleted} voci.",
            'items_cleared' => $deleted
        ]);
    }

    /**
     * Sync versions fix
     */
    private static function fix_sync_versions() {
        $current_version = '1.0.240';

        // Update database version
        update_option('btr_plugin_version', $current_version);

        // Update calculator version
        update_option('btr_calculator_version', 'v2.0');

        // Set unified calculator as default
        update_option('btr_use_unified_calculator', true);
        update_option('btr_calculator_mode', 'unified');

        // Clear version cache
        wp_cache_delete('btr_versions', 'options');

        error_log("[BTR Diagnostic] Versions synced to {$current_version}");

        wp_send_json_success([
            'message' => "Versioni sincronizzate a {$current_version}",
            'version' => $current_version
        ]);
    }

    /**
     * Create missing tables
     */
    private static function fix_create_tables() {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $created_tables = [];
        $charset_collate = $wpdb->get_charset_collate();

        // Table definitions
        $tables = [
            'btr_group_payments' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}btr_group_payments (
                payment_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                preventivo_id bigint(20) UNSIGNED NOT NULL,
                group_name varchar(255) DEFAULT NULL,
                amount decimal(10,2) NOT NULL DEFAULT '0.00',
                currency varchar(3) DEFAULT 'EUR',
                payment_type varchar(50) DEFAULT 'deposit',
                status varchar(20) DEFAULT 'pending',
                payment_date datetime DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (payment_id),
                KEY preventivo_id (preventivo_id),
                KEY status (status),
                KEY payment_type (payment_type)
            ) $charset_collate;",

            'btr_payment_links' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}btr_payment_links (
                link_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                preventivo_id bigint(20) UNSIGNED NOT NULL,
                link_token varchar(64) NOT NULL,
                payment_type varchar(50) DEFAULT 'deposit',
                amount decimal(10,2) DEFAULT NULL,
                status varchar(20) DEFAULT 'active',
                access_count int(11) DEFAULT '0',
                expires_at datetime DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (link_id),
                UNIQUE KEY link_token (link_token),
                KEY preventivo_id (preventivo_id),
                KEY status (status)
            ) $charset_collate;",

            'btr_deposit_balance' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}btr_deposit_balance (
                balance_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                preventivo_id bigint(20) UNSIGNED NOT NULL,
                total_amount decimal(10,2) NOT NULL DEFAULT '0.00',
                paid_amount decimal(10,2) NOT NULL DEFAULT '0.00',
                balance_amount decimal(10,2) NOT NULL DEFAULT '0.00',
                currency varchar(3) DEFAULT 'EUR',
                last_payment_date datetime DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (balance_id),
                UNIQUE KEY preventivo_id (preventivo_id)
            ) $charset_collate;",

            'btr_order_shares' => "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}btr_order_shares (
                share_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                order_id bigint(20) UNSIGNED NOT NULL,
                preventivo_id bigint(20) UNSIGNED NOT NULL,
                participant_name varchar(255) DEFAULT NULL,
                participant_email varchar(255) DEFAULT NULL,
                share_amount decimal(10,2) NOT NULL DEFAULT '0.00',
                status varchar(20) DEFAULT 'pending',
                payment_link varchar(255) DEFAULT NULL,
                paid_at datetime DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (share_id),
                KEY order_id (order_id),
                KEY preventivo_id (preventivo_id),
                KEY status (status)
            ) $charset_collate;"
        ];

        foreach ($tables as $table_name => $sql) {
            dbDelta($sql);
            $created_tables[] = $table_name;
            error_log("[BTR Diagnostic] Table created/verified: {$wpdb->prefix}{$table_name}");
        }

        wp_send_json_success([
            'message' => 'Tabelle database create con successo',
            'tables' => $created_tables
        ]);
    }

    /**
     * Fix meta keys
     */
    private static function fix_meta_keys() {
        global $wpdb;

        $fixed_count = 0;

        // Get all preventivi
        $preventivi = $wpdb->get_results(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'preventivi' AND post_status != 'trash'"
        );

        foreach ($preventivi as $preventivo) {
            $needs_fix = false;

            // Check for legacy meta keys
            $legacy_keys = [
                '_payload_prezzo_totale' => '_prezzo_totale_completo',
                '_old_prezzo_totale' => '_prezzo_totale',
                '_calculator_v1' => '_calculator_version'
            ];

            foreach ($legacy_keys as $old_key => $new_key) {
                $old_value = get_post_meta($preventivo->ID, $old_key, true);
                $new_value = get_post_meta($preventivo->ID, $new_key, true);

                if ($old_value && !$new_value) {
                    update_post_meta($preventivo->ID, $new_key, $old_value);
                    $needs_fix = true;
                }
            }

            // Ensure calculator version is set
            $calc_version = get_post_meta($preventivo->ID, '_calculator_version', true);
            if (!$calc_version) {
                update_post_meta($preventivo->ID, '_calculator_version', 'v2.0');
                $needs_fix = true;
            }

            if ($needs_fix) {
                $fixed_count++;
            }
        }

        error_log("[BTR Diagnostic] Fixed meta keys for {$fixed_count} preventivi");

        wp_send_json_success([
            'message' => "Meta keys normalizzate per {$fixed_count} preventivi",
            'fixed_count' => $fixed_count
        ]);
    }

    /**
     * Optimize database
     */
    private static function fix_optimize_database() {
        global $wpdb;

        $optimized_tables = [];

        // Get all BTR tables
        $tables = $wpdb->get_results("SHOW TABLES LIKE '{$wpdb->prefix}btr_%'", ARRAY_N);

        foreach ($tables as $table) {
            $table_name = $table[0];
            $wpdb->query("OPTIMIZE TABLE {$table_name}");
            $optimized_tables[] = $table_name;
        }

        // Clean old transients
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP()");

        // Clean orphaned meta
        $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE post_id NOT IN (SELECT ID FROM {$wpdb->posts})");

        error_log("[BTR Diagnostic] Database optimized: " . implode(', ', $optimized_tables));

        wp_send_json_success([
            'message' => 'Database ottimizzato con successo',
            'tables_optimized' => count($optimized_tables)
        ]);
    }

    /**
     * Reset calculator
     */
    private static function fix_reset_calculator($mode = 'soft') {
        if ($mode === 'soft') {
            // Clear calculator cache only
            delete_transient('btr_calculator_cache');
            delete_option('btr_calculation_cache');

            $message = 'Cache calcolatore pulita';
        } else {
            // Hard reset - reset to v2.0
            update_option('btr_calculator_version', 'v2.0');
            update_option('btr_use_unified_calculator', true);
            update_option('btr_calculator_mode', 'unified');

            // Clear all calculator related options
            $wpdb = $GLOBALS['wpdb'];
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'btr_calc_%'");

            $message = 'Calcolatore resettato a v2.0 Unificato';
        }

        error_log("[BTR Diagnostic] Calculator reset: {$mode}");

        wp_send_json_success([
            'message' => $message,
            'mode' => $mode
        ]);
    }

    /**
     * Enable compatibility mode
     */
    private static function fix_compatibility_mode() {
        update_option('btr_compatibility_mode', true);
        update_option('btr_legacy_support', true);
        update_option('btr_strict_mode', false);

        error_log("[BTR Diagnostic] Compatibility mode enabled");

        wp_send_json_success([
            'message' => 'Modalità compatibilità attivata'
        ]);
    }

    /**
     * Rebuild indexes
     */
    private static function fix_rebuild_indexes() {
        global $wpdb;

        // Add indexes for better performance
        $indexes = [
            "ALTER TABLE {$wpdb->prefix}btr_group_payments ADD INDEX idx_preventivo_status (preventivo_id, status)",
            "ALTER TABLE {$wpdb->prefix}btr_payment_links ADD INDEX idx_token_status (link_token, status)",
            "ALTER TABLE {$wpdb->postmeta} ADD INDEX idx_btr_meta (meta_key(20), post_id)"
        ];

        $added = 0;
        foreach ($indexes as $index_sql) {
            $wpdb->query($index_sql);
            $added++;
        }

        error_log("[BTR Diagnostic] Indexes rebuilt: {$added}");

        wp_send_json_success([
            'message' => "Indici ricostruiti con successo",
            'indexes_added' => $added
        ]);
    }

    /**
     * Reset permissions
     */
    private static function fix_reset_permissions() {
        // Reset BTR capabilities
        $admin_role = get_role('administrator');

        $capabilities = [
            'manage_btr_settings',
            'view_btr_reports',
            'edit_btr_preventivi',
            'delete_btr_preventivi',
            'manage_btr_payments'
        ];

        foreach ($capabilities as $cap) {
            $admin_role->add_cap($cap);
        }

        // Flush rewrite rules
        flush_rewrite_rules();

        error_log("[BTR Diagnostic] Permissions reset");

        wp_send_json_success([
            'message' => 'Permessi ripristinati con successo'
        ]);
    }

    /**
     * Emergency reset
     */
    private static function fix_emergency_reset() {
        // Full system reset

        // 1. Clear all caches
        self::fix_clear_cache();

        // 2. Reset versions
        self::fix_sync_versions();

        // 3. Create tables
        self::fix_create_tables();

        // 4. Fix meta keys
        self::fix_meta_keys();

        // 5. Reset calculator
        self::fix_reset_calculator('hard');

        // 6. Optimize database
        self::fix_optimize_database();

        // 7. Reset options
        update_option('btr_emergency_reset_timestamp', current_time('mysql'));

        error_log("[BTR Diagnostic] Emergency reset completed");

        wp_send_json_success([
            'message' => 'Reset di emergenza completato con successo. Aggiorna la pagina.'
        ]);
    }

    /**
     * Handle calculation test
     */
    public static function handle_test_calculation() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'btr_diagnostic_action')) {
            wp_send_json_error(['message' => 'Controllo sicurezza fallito']);
            return;
        }

        $preventivo_id = intval($_POST['preventivo_id'] ?? 0);

        if (!$preventivo_id) {
            wp_send_json_error(['message' => 'ID preventivo non valido']);
            return;
        }

        // Test calculation
        $calculator = new BTR_Price_Calculator();
        $unified = new BTR_Unified_Calculator();

        // Get preventivo data
        $pacchetto_data = get_post_meta($preventivo_id, '_pacchetto_data', true);
        $anagrafici = get_post_meta($preventivo_id, '_btr_anagrafici', true);

        $results = [
            'preventivo_id' => $preventivo_id,
            'legacy_calculator' => null,
            'unified_calculator' => null,
            'meta_values' => [
                '_prezzo_totale' => get_post_meta($preventivo_id, '_prezzo_totale', true),
                '_prezzo_totale_completo' => get_post_meta($preventivo_id, '_prezzo_totale_completo', true),
                '_calculator_version' => get_post_meta($preventivo_id, '_calculator_version', true)
            ],
            'guest_count' => is_array($anagrafici) ? count($anagrafici) : 0,
            'package_data' => !empty($pacchetto_data)
        ];

        // Test legacy calculator
        if (method_exists($calculator, 'calculate_total')) {
            try {
                $legacy_result = $calculator->calculate_total($preventivo_id);
                $results['legacy_calculator'] = $legacy_result;
            } catch (Exception $e) {
                $results['legacy_calculator'] = 'Error: ' . $e->getMessage();
            }
        }

        // Test unified calculator
        if (method_exists($unified, 'calculateTotalPrice')) {
            try {
                $unified_result = $unified->calculateTotalPrice($preventivo_id);
                $results['unified_calculator'] = $unified_result;
            } catch (Exception $e) {
                $results['unified_calculator'] = 'Error: ' . $e->getMessage();
            }
        }

        // Compare results
        $results['comparison'] = [
            'match' => false,
            'difference' => null
        ];

        if (is_numeric($results['legacy_calculator']) && is_numeric($results['unified_calculator'])) {
            $diff = abs($results['legacy_calculator'] - $results['unified_calculator']);
            $results['comparison'] = [
                'match' => $diff < 0.01,
                'difference' => $diff
            ];
        }

        wp_send_json_success([
            'results' => $results
        ]);
    }

    /**
     * Get live metrics
     */
    public static function handle_get_live_metrics() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'btr_diagnostic_action')) {
            wp_send_json_error(['message' => 'Controllo sicurezza fallito']);
            return;
        }

        global $wpdb;

        // Get current metrics
        $metrics = [
            'active_users' => count(get_users(['role' => 'customer', 'number' => 100])),
            'memory' => size_format(memory_get_usage()),
            'peak_memory' => size_format(memory_get_peak_usage()),
            'queries' => get_num_queries(),
            'load' => sys_getloadavg()[0] ?? 'N/A',
            'timestamp' => current_time('mysql'),
            'cache_hits' => wp_cache_get('cache_hits', 'btr') ?? 0,
            'cache_misses' => wp_cache_get('cache_misses', 'btr') ?? 0
        ];

        // Recent activity
        $recent_orders = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts}
             WHERE post_type = 'shop_order'
             AND post_date > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );

        $metrics['recent_orders'] = $recent_orders;

        wp_send_json_success([
            'metrics' => $metrics
        ]);
    }

    /**
     * Export diagnostic report
     */
    public static function handle_export_diagnostic() {
        // Verify nonce and permissions
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'btr_diagnostic_action')) {
            wp_send_json_error(['message' => 'Controllo sicurezza fallito']);
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permessi insufficienti']);
            return;
        }

        // Generate report
        $report = self::generate_diagnostic_report();

        wp_send_json_success([
            'report' => $report,
            'filename' => 'btr-diagnostic-' . date('Y-m-d-His') . '.json'
        ]);
    }

    /**
     * Generate diagnostic report
     */
    private static function generate_diagnostic_report() {
        global $wpdb;

        $report = [
            'timestamp' => current_time('mysql'),
            'plugin_version' => '1.0.240',
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => phpversion(),
            'mysql_version' => $wpdb->db_version(),
            'site_url' => get_site_url(),
            'issues' => [],
            'system' => [
                'memory_limit' => WP_MEMORY_LIMIT,
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size')
            ],
            'database' => [
                'tables' => [],
                'preventivi_count' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'preventivi'"),
                'orders_count' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order'")
            ],
            'cache' => [
                'object_cache' => wp_using_ext_object_cache(),
                'transients_count' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'"),
                'btr_cache_count' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_btr_%'")
            ]
        ];

        // Check tables
        $tables = ['btr_group_payments', 'btr_payment_links', 'btr_deposit_balance', 'btr_order_shares'];
        foreach ($tables as $table) {
            $exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}{$table}'");
            $report['database']['tables'][$table] = $exists ? 'present' : 'missing';
        }

        return $report;
    }
}

// Initialize the AJAX handlers
add_action('init', ['BTR_Diagnostic_Ajax', 'init']);