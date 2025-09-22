<?php
/**
 * Sistema di Diagnostica per Born to Ride Booking
 * 
 * Verifica lo stato di installazione di tutti i componenti del plugin
 * 
 * @package BornToRideBooking
 * @since 1.0.107
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_System_Diagnostics {
    
    /**
     * Instance singleton
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_diagnostics_page'], 99);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_btr_run_diagnostics', [$this, 'ajax_run_diagnostics']);
    }
    
    /**
     * Aggiungi pagina al menu admin
     */
    public function add_diagnostics_page() {
        add_submenu_page(
            'btr-booking',
            __('Diagnostica Sistema', 'born-to-ride-booking'),
            __('Diagnostica', 'born-to-ride-booking'),
            'manage_options',
            'btr-diagnostics',
            [$this, 'render_diagnostics_page']
        );
    }
    
    /**
     * Enqueue assets
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, 'btr-diagnostics') === false) {
            return;
        }
        
        wp_enqueue_style(
            'btr-diagnostics-css',
            BTR_PLUGIN_URL . 'admin/css/btr-diagnostics.css',
            [],
            BTR_VERSION
        );
        
        wp_enqueue_script(
            'btr-diagnostics-js',
            BTR_PLUGIN_URL . 'admin/js/btr-diagnostics.js',
            ['jquery'],
            BTR_VERSION,
            true
        );
        
        wp_localize_script('btr-diagnostics-js', 'btrDiagnostics', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('btr_diagnostics_nonce'),
            'strings' => [
                'running' => __('Esecuzione diagnostica...', 'born-to-ride-booking'),
                'error' => __('Errore durante la diagnostica', 'born-to-ride-booking')
            ]
        ]);
    }
    
    /**
     * Render pagina diagnostica
     */
    public function render_diagnostics_page() {
        ?>
        <div class="wrap btr-diagnostics-wrap">
            <h1><?php _e('Diagnostica Sistema - Born to Ride Booking', 'born-to-ride-booking'); ?></h1>
            
            <div class="btr-diagnostics-intro">
                <p><?php _e('Questa pagina verifica lo stato di installazione di tutti i componenti del plugin.', 'born-to-ride-booking'); ?></p>
                <button class="button button-primary" id="btr-run-diagnostics">
                    <?php _e('Esegui Diagnostica Completa', 'born-to-ride-booking'); ?>
                </button>
            </div>
            
            <div id="btr-diagnostics-results" style="display:none;">
                <!-- I risultati verranno inseriti qui via AJAX -->
            </div>
            
            <!-- Sezione Info Rapide -->
            <div class="btr-quick-info">
                <h2><?php _e('Informazioni Rapide', 'born-to-ride-booking'); ?></h2>
                <?php $this->render_quick_info(); ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render informazioni rapide
     */
    private function render_quick_info() {
        ?>
        <div class="btr-info-grid">
            <div class="btr-info-box">
                <h3><?php _e('Versione Plugin', 'born-to-ride-booking'); ?></h3>
                <p class="btr-info-value"><?php echo BTR_VERSION; ?></p>
            </div>
            
            <div class="btr-info-box">
                <h3><?php _e('Versione Database', 'born-to-ride-booking'); ?></h3>
                <p class="btr-info-value"><?php echo get_option('btr_db_version', '0'); ?></p>
            </div>
            
            <div class="btr-info-box">
                <h3><?php _e('WooCommerce', 'born-to-ride-booking'); ?></h3>
                <p class="btr-info-value <?php echo class_exists('WooCommerce') ? 'status-ok' : 'status-error'; ?>">
                    <?php echo class_exists('WooCommerce') ? __('Attivo', 'born-to-ride-booking') : __('Non attivo', 'born-to-ride-booking'); ?>
                </p>
            </div>
            
            <div class="btr-info-box">
                <h3><?php _e('PHP Version', 'born-to-ride-booking'); ?></h3>
                <p class="btr-info-value"><?php echo PHP_VERSION; ?></p>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler per diagnostica
     */
    public function ajax_run_diagnostics() {
        // Verifica nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'btr_diagnostics_nonce')) {
            wp_send_json_error(['message' => __('Errore di sicurezza', 'born-to-ride-booking')]);
        }
        
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Non autorizzato', 'born-to-ride-booking')]);
        }
        
        $results = $this->run_full_diagnostics();
        
        ob_start();
        $this->render_diagnostics_results($results);
        $html = ob_get_clean();
        
        wp_send_json_success(['html' => $html]);
    }
    
    /**
     * Esegui diagnostica completa
     */
    private function run_full_diagnostics() {
        $results = [
            'sistema' => $this->check_system_requirements(),
            'database' => $this->check_database(),
            'pagine' => $this->check_pages(),
            'componenti' => $this->check_components(),
            'integrazioni' => $this->check_integrations(),
            'cron' => $this->check_cron_jobs(),
            'permessi' => $this->check_permissions()
        ];
        
        return $results;
    }
    
    /**
     * Verifica requisiti di sistema
     */
    private function check_system_requirements() {
        $checks = [];
        
        // PHP Version
        $php_ok = version_compare(PHP_VERSION, '7.2', '>=');
        $checks[] = [
            'label' => 'PHP Version',
            'status' => $php_ok,
            'value' => PHP_VERSION,
            'required' => '7.2+',
            'message' => $php_ok ? 'OK' : __('Versione PHP troppo vecchia', 'born-to-ride-booking')
        ];
        
        // WordPress Version
        global $wp_version;
        $wp_ok = version_compare($wp_version, '5.0', '>=');
        $checks[] = [
            'label' => 'WordPress Version',
            'status' => $wp_ok,
            'value' => $wp_version,
            'required' => '5.0+',
            'message' => $wp_ok ? 'OK' : __('Versione WordPress troppo vecchia', 'born-to-ride-booking')
        ];
        
        // WooCommerce
        $wc_active = class_exists('WooCommerce');
        $wc_version = $wc_active ? WC()->version : 'N/A';
        $wc_ok = $wc_active && version_compare($wc_version, '3.0', '>=');
        $checks[] = [
            'label' => 'WooCommerce',
            'status' => $wc_ok,
            'value' => $wc_active ? $wc_version : __('Non installato', 'born-to-ride-booking'),
            'required' => '3.0+',
            'message' => $wc_ok ? 'OK' : __('WooCommerce non attivo o versione non compatibile', 'born-to-ride-booking')
        ];
        
        // Memory Limit
        $memory_limit = ini_get('memory_limit');
        $memory_bytes = wp_convert_hr_to_bytes($memory_limit);
        $memory_ok = $memory_bytes >= 128 * 1024 * 1024; // 128MB
        $checks[] = [
            'label' => 'Memory Limit',
            'status' => $memory_ok,
            'value' => $memory_limit,
            'required' => '128M',
            'message' => $memory_ok ? 'OK' : __('Memory limit troppo basso', 'born-to-ride-booking')
        ];
        
        return $checks;
    }
    
    /**
     * Verifica database
     */
    private function check_database() {
        global $wpdb;
        $checks = [];
        
        // Tabelle principali
        $tables = [
            'btr_payment_plans' => __('Piani di Pagamento', 'born-to-ride-booking'),
            'btr_group_payments' => __('Pagamenti di Gruppo', 'born-to-ride-booking'),
            'btr_payment_reminders' => __('Promemoria Pagamenti', 'born-to-ride-booking'),
            'btr_order_shares' => __('Quote Ordini', 'born-to-ride-booking'),
            'btr_payment_webhook_dlq' => __('Webhook Dead Letter Queue', 'born-to-ride-booking')
        ];
        
        foreach ($tables as $table => $label) {
            $full_table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$full_table_name'") === $full_table_name;
            
            // Conta record se la tabella esiste
            $count = 0;
            if ($exists) {
                $count = $wpdb->get_var("SELECT COUNT(*) FROM $full_table_name");
            }
            
            $checks[] = [
                'label' => $label,
                'status' => $exists,
                'value' => $exists ? sprintf(__('%d record', 'born-to-ride-booking'), $count) : __('Non trovata', 'born-to-ride-booking'),
                'required' => __('Richiesta', 'born-to-ride-booking'),
                'message' => $exists ? 'OK' : __('Tabella mancante', 'born-to-ride-booking')
            ];
        }
        
        // Verifica versione database
        $db_version = get_option('btr_db_version', '0');
        $target_version = '1.1.0'; // Versione target del database
        $version_ok = version_compare($db_version, $target_version, '>=');
        
        $checks[] = [
            'label' => __('Versione Database', 'born-to-ride-booking'),
            'status' => $version_ok,
            'value' => $db_version,
            'required' => $target_version,
            'message' => $version_ok ? 'OK' : __('Database non aggiornato', 'born-to-ride-booking')
        ];
        
        return $checks;
    }
    
    /**
     * Verifica pagine
     */
    private function check_pages() {
        $checks = [];

        // Pagine con opzioni salvate e shortcode richiesti
        $pages = [
            'btr_payment_selection_page_id' => [
                'label' => __('Selezione Piano Pagamento', 'born-to-ride-booking'),
                'shortcode' => 'btr_payment_selection',
                'critical' => true
            ],
            'btr_checkout_deposit_page' => [
                'label' => __('Checkout Caparra', 'born-to-ride-booking'),
                'shortcode' => 'btr_checkout_deposit',
                'critical' => true
            ],
            'btr_group_payment_summary_page' => [
                'label' => __('Riepilogo Pagamento Gruppo', 'born-to-ride-booking'),
                'shortcode' => 'btr_group_payment_summary',
                'critical' => true
            ],
            'btr_booking_confirmation_page' => [
                'label' => __('Conferma Prenotazione', 'born-to-ride-booking'),
                'shortcode' => null, // Pagina informativa, non richiede shortcode specifico
                'critical' => false
            ]
        ];

        foreach ($pages as $option => $config) {
            $page_id = get_option($option);
            $page = $page_id ? get_post($page_id) : null;
            $page_exists = $page && $page->ID;

            // Informazioni base pagina
            if ($page_exists) {
                $page_issues = [];
                $page_warnings = [];

                // Verifica stato pubblicazione
                if ($page->post_status !== 'publish') {
                    $page_issues[] = sprintf(__('Stato: %s', 'born-to-ride-booking'), $page->post_status);
                }

                // Verifica shortcode richiesto
                if ($config['shortcode']) {
                    $has_shortcode = has_shortcode($page->post_content, $config['shortcode']);
                    if (!$has_shortcode) {
                        $page_issues[] = sprintf(__('Manca shortcode [%s]', 'born-to-ride-booking'), $config['shortcode']);
                    }
                }

                // Verifica contenuto minimo
                $content_length = strlen(strip_tags($page->post_content));
                if ($content_length < 20) {
                    $page_warnings[] = __('Contenuto molto breve', 'born-to-ride-booking');
                }

                // Verifica titolo pagina
                if (empty($page->post_title)) {
                    $page_issues[] = __('Titolo mancante', 'born-to-ride-booking');
                }

                // Verifica permalink funzionante
                $permalink = get_permalink($page_id);
                if (!$permalink || $permalink === get_home_url()) {
                    $page_issues[] = __('Permalink non valido', 'born-to-ride-booking');
                }

                // Costruisci messaggio risultato
                $status_ok = empty($page_issues);
                $value_parts = [
                    sprintf(__('ID: %d', 'born-to-ride-booking'), $page_id),
                    $page->post_title
                ];

                if ($config['shortcode']) {
                    $shortcode_status = has_shortcode($page->post_content, $config['shortcode']) ? '✓' : '✗';
                    $value_parts[] = sprintf(__('Shortcode: %s', 'born-to-ride-booking'), $shortcode_status);
                }

                $value_parts[] = sprintf(__('Stato: %s', 'born-to-ride-booking'), $page->post_status);

                $value = implode(' | ', $value_parts);

                // Costruisci messaggio
                if (!empty($page_issues)) {
                    $message = implode(', ', $page_issues);
                } elseif (!empty($page_warnings)) {
                    $message = sprintf(__('OK - %s', 'born-to-ride-booking'), implode(', ', $page_warnings));
                } else {
                    $message = 'OK';
                }

            } else {
                $status_ok = false;
                $value = $page_id ? __('Pagina non trovata', 'born-to-ride-booking') : __('Non configurata', 'born-to-ride-booking');
                $message = __('Pagina mancante o non configurata', 'born-to-ride-booking');
            }

            $checks[] = [
                'label' => $config['label'],
                'status' => $status_ok,
                'value' => $value,
                'required' => $config['critical'] ? __('Critica', 'born-to-ride-booking') : __('Consigliata', 'born-to-ride-booking'),
                'message' => $message
            ];
        }

        // Verifica aggiuntiva: pagine orfane con shortcode BTR
        $checks = array_merge($checks, $this->check_orphan_btr_pages());

        // Verifica accessibilità pagine critiche
        $checks = array_merge($checks, $this->check_page_accessibility());

        return $checks;
    }

    /**
     * Verifica pagine orfane con shortcode BTR
     */
    private function check_orphan_btr_pages() {
        global $wpdb;

        $checks = [];

        // Cerca pagine con shortcode BTR non configurate
        $btr_shortcodes = ['btr_payment_selection', 'btr_anagrafici', 'btr_checkout_deposit', 'btr_group_payment_summary'];
        $shortcode_pattern = implode('|', $btr_shortcodes);

        $orphan_pages = $wpdb->get_results($wpdb->prepare("
            SELECT ID, post_title, post_content, post_status
            FROM {$wpdb->posts}
            WHERE post_type = 'page'
            AND post_content REGEXP %s
            AND post_status IN ('publish', 'draft', 'private')
            ORDER BY post_title
        ", "\\[({$shortcode_pattern})"));

        if ($orphan_pages) {
            $configured_pages = [
                get_option('btr_payment_selection_page_id'),
                get_option('btr_checkout_deposit_page'),
                get_option('btr_group_payment_summary_page'),
                get_option('btr_booking_confirmation_page')
            ];

            $orphan_count = 0;
            $orphan_details = [];

            foreach ($orphan_pages as $page) {
                if (!in_array($page->ID, $configured_pages)) {
                    $orphan_count++;

                    // Trova quale shortcode contiene
                    preg_match_all('/\[(btr_[^\]]+)/', $page->post_content, $matches);
                    $found_shortcodes = $matches[1] ?? [];

                    $orphan_details[] = sprintf(
                        __('ID %d: %s [%s]', 'born-to-ride-booking'),
                        $page->ID,
                        $page->post_title,
                        implode(', ', $found_shortcodes)
                    );
                }
            }

            if ($orphan_count > 0) {
                $checks[] = [
                    'label' => __('Pagine BTR Non Configurate', 'born-to-ride-booking'),
                    'status' => false,
                    'value' => sprintf(__('%d pagine trovate', 'born-to-ride-booking'), $orphan_count),
                    'required' => __('Verifica', 'born-to-ride-booking'),
                    'message' => sprintf(__('Trovate pagine con shortcode BTR non configurate: %s', 'born-to-ride-booking'), implode('; ', array_slice($orphan_details, 0, 3)))
                ];
            } else {
                $checks[] = [
                    'label' => __('Pagine BTR Non Configurate', 'born-to-ride-booking'),
                    'status' => true,
                    'value' => __('Nessuna pagina orfana', 'born-to-ride-booking'),
                    'required' => __('Verifica', 'born-to-ride-booking'),
                    'message' => 'OK'
                ];
            }
        }

        return $checks;
    }

    /**
     * Verifica accessibilità pagine critiche
     */
    private function check_page_accessibility() {
        $checks = [];

        // Pagine critiche che devono essere accessibili
        $critical_pages = [
            get_option('btr_payment_selection_page_id') => __('Selezione Pagamento', 'born-to-ride-booking'),
            get_option('btr_checkout_deposit_page') => __('Checkout Caparra', 'born-to-ride-booking')
        ];

        $inaccessible_pages = [];
        $accessible_count = 0;

        foreach ($critical_pages as $page_id => $label) {
            if (!$page_id) continue;

            $page = get_post($page_id);
            if (!$page) continue;

            $is_accessible = true;
            $issues = [];

            // Verifica che sia pubblicata
            if ($page->post_status !== 'publish') {
                $is_accessible = false;
                $issues[] = sprintf(__('non pubblicata (%s)', 'born-to-ride-booking'), $page->post_status);
            }

            // Verifica che non sia protetta da password
            if (!empty($page->post_password)) {
                $is_accessible = false;
                $issues[] = __('protetta da password', 'born-to-ride-booking');
            }

            // Verifica che il titolo non sia vuoto (importante per SEO)
            if (empty($page->post_title)) {
                $issues[] = __('titolo mancante', 'born-to-ride-booking');
            }

            if ($is_accessible) {
                $accessible_count++;
            } else {
                $inaccessible_pages[] = sprintf(__('%s: %s', 'born-to-ride-booking'), $label, implode(', ', $issues));
            }
        }

        $total_critical = count(array_filter($critical_pages, function($id) { return !empty($id); }, ARRAY_FILTER_USE_KEY));

        if ($total_critical > 0) {
            $all_accessible = empty($inaccessible_pages);

            $checks[] = [
                'label' => __('Accessibilità Pagine Critiche', 'born-to-ride-booking'),
                'status' => $all_accessible,
                'value' => sprintf(__('%d/%d accessibili', 'born-to-ride-booking'), $accessible_count, $total_critical),
                'required' => __('Tutte', 'born-to-ride-booking'),
                'message' => $all_accessible ? 'OK' : implode('; ', $inaccessible_pages)
            ];
        }

        return $checks;
    }

    /**
     * Verifica componenti
     */
    private function check_components() {
        $checks = [];
        
        // Classi principali
        $classes = [
            'BTR_Pacchetti_CPT' => __('Custom Post Type Pacchetti', 'born-to-ride-booking'),
            'BTR_WooCommerce_Sync' => __('Sincronizzazione WooCommerce', 'born-to-ride-booking'),
            'BTR_Preventivi' => __('Sistema Preventivi', 'born-to-ride-booking'),
            'BTR_Checkout' => __('Gestione Checkout', 'born-to-ride-booking'),
            'BTR_Payment_Plans' => __('Piani di Pagamento', 'born-to-ride-booking'),
            'BTR_Group_Payments' => __('Pagamenti di Gruppo', 'born-to-ride-booking'),
            'BTR_Database_Auto_Installer' => __('Installer Database', 'born-to-ride-booking'),
            'BTR_Store_API_Integration' => __('Integrazione Store API', 'born-to-ride-booking')
        ];
        
        foreach ($classes as $class => $label) {
            $exists = class_exists($class);
            
            $checks[] = [
                'label' => $label,
                'status' => $exists,
                'value' => $exists ? __('Caricato', 'born-to-ride-booking') : __('Non trovato', 'born-to-ride-booking'),
                'required' => __('Richiesto', 'born-to-ride-booking'),
                'message' => $exists ? 'OK' : __('Componente mancante', 'born-to-ride-booking')
            ];
        }
        
        // Shortcodes
        $shortcodes = [
            'btr_payment_selection' => __('Shortcode Selezione Pagamento', 'born-to-ride-booking'),
            'btr_anagrafici' => __('Shortcode Anagrafici', 'born-to-ride-booking'),
            'btr_checkout_deposit' => __('Shortcode Checkout Caparra', 'born-to-ride-booking'),
            'btr_group_payment_summary' => __('Shortcode Riepilogo Gruppo', 'born-to-ride-booking')
        ];
        
        foreach ($shortcodes as $shortcode => $label) {
            $exists = shortcode_exists($shortcode);
            
            $checks[] = [
                'label' => $label,
                'status' => $exists,
                'value' => $exists ? __('Registrato', 'born-to-ride-booking') : __('Non registrato', 'born-to-ride-booking'),
                'required' => __('Richiesto', 'born-to-ride-booking'),
                'message' => $exists ? 'OK' : __('Shortcode non registrato', 'born-to-ride-booking')
            ];
        }
        
        return $checks;
    }
    
    /**
     * Verifica integrazioni
     */
    private function check_integrations() {
        $checks = [];
        
        // WooCommerce Blocks
        $blocks_active = class_exists('Automattic\WooCommerce\Blocks\Package');
        $blocks_version = $blocks_active ? \Automattic\WooCommerce\Blocks\Package::get_version() : 'N/A';
        
        $checks[] = [
            'label' => 'WooCommerce Blocks',
            'status' => $blocks_active,
            'value' => $blocks_active ? $blocks_version : __('Non attivo', 'born-to-ride-booking'),
            'required' => __('Consigliato', 'born-to-ride-booking'),
            'message' => $blocks_active ? 'OK' : __('WooCommerce Blocks non attivo', 'born-to-ride-booking')
        ];
        
        // TCPDF
        $tcpdf_exists = class_exists('TCPDF');
        $checks[] = [
            'label' => 'TCPDF (PDF Generator)',
            'status' => $tcpdf_exists,
            'value' => $tcpdf_exists ? __('Disponibile', 'born-to-ride-booking') : __('Non trovato', 'born-to-ride-booking'),
            'required' => __('Richiesto per PDF', 'born-to-ride-booking'),
            'message' => $tcpdf_exists ? 'OK' : __('TCPDF non disponibile', 'born-to-ride-booking')
        ];
        
        // Payment Gateways
        if (class_exists('WooCommerce')) {
            $gateways = WC()->payment_gateways->get_available_payment_gateways();
            $gateway_count = count($gateways);
            $has_gateways = $gateway_count > 0;
            
            $checks[] = [
                'label' => __('Gateway di Pagamento', 'born-to-ride-booking'),
                'status' => $has_gateways,
                'value' => sprintf(__('%d gateway attivi', 'born-to-ride-booking'), $gateway_count),
                'required' => __('Almeno 1', 'born-to-ride-booking'),
                'message' => $has_gateways ? 'OK' : __('Nessun gateway di pagamento attivo', 'born-to-ride-booking')
            ];
        }
        
        return $checks;
    }
    
    /**
     * Verifica cron jobs
     */
    private function check_cron_jobs() {
        $checks = [];
        
        // Cron jobs del plugin
        $cron_hooks = [
            'btr_payment_reminders_cron' => __('Promemoria Pagamenti', 'born-to-ride-booking'),
            'btr_payment_expiry_check' => __('Controllo Scadenze', 'born-to-ride-booking'),
            'btr_cleanup_old_data' => __('Pulizia Dati Vecchi', 'born-to-ride-booking')
        ];
        
        foreach ($cron_hooks as $hook => $label) {
            $scheduled = wp_next_scheduled($hook);
            
            if ($scheduled) {
                $next_run = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $scheduled);
                $value = sprintf(__('Prossima esecuzione: %s', 'born-to-ride-booking'), $next_run);
            } else {
                $value = __('Non programmato', 'born-to-ride-booking');
            }
            
            $checks[] = [
                'label' => $label,
                'status' => (bool) $scheduled,
                'value' => $value,
                'required' => __('Consigliato', 'born-to-ride-booking'),
                'message' => $scheduled ? 'OK' : __('Cron non programmato', 'born-to-ride-booking')
            ];
        }
        
        return $checks;
    }
    
    /**
     * Verifica permessi
     */
    private function check_permissions() {
        $checks = [];
        
        // Directory uploads
        $upload_dir = wp_upload_dir();
        $uploads_writable = wp_is_writable($upload_dir['basedir']);
        
        $checks[] = [
            'label' => __('Directory Uploads', 'born-to-ride-booking'),
            'status' => $uploads_writable,
            'value' => $uploads_writable ? __('Scrivibile', 'born-to-ride-booking') : __('Non scrivibile', 'born-to-ride-booking'),
            'required' => __('Scrivibile', 'born-to-ride-booking'),
            'message' => $uploads_writable ? 'OK' : __('Directory uploads non scrivibile', 'born-to-ride-booking')
        ];
        
        // Directory plugin logs (se esiste)
        $log_dir = BTR_PLUGIN_DIR . 'logs';
        if (file_exists($log_dir)) {
            $logs_writable = wp_is_writable($log_dir);
            
            $checks[] = [
                'label' => __('Directory Logs', 'born-to-ride-booking'),
                'status' => $logs_writable,
                'value' => $logs_writable ? __('Scrivibile', 'born-to-ride-booking') : __('Non scrivibile', 'born-to-ride-booking'),
                'required' => __('Scrivibile', 'born-to-ride-booking'),
                'message' => $logs_writable ? 'OK' : __('Directory logs non scrivibile', 'born-to-ride-booking')
            ];
        }
        
        return $checks;
    }
    
    /**
     * Render risultati diagnostica
     */
    private function render_diagnostics_results($results) {
        ?>
        <div class="btr-diagnostics-results">
            <h2><?php _e('Risultati Diagnostica', 'born-to-ride-booking'); ?></h2>
            
            <?php foreach ($results as $section => $checks): ?>
                <div class="btr-diagnostic-section">
                    <h3><?php echo $this->get_section_title($section); ?></h3>
                    <table class="btr-diagnostic-table">
                        <thead>
                            <tr>
                                <th><?php _e('Componente', 'born-to-ride-booking'); ?></th>
                                <th><?php _e('Stato', 'born-to-ride-booking'); ?></th>
                                <th><?php _e('Valore', 'born-to-ride-booking'); ?></th>
                                <th><?php _e('Richiesto', 'born-to-ride-booking'); ?></th>
                                <th><?php _e('Note', 'born-to-ride-booking'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($checks as $check): ?>
                                <tr>
                                    <td><?php echo esc_html($check['label']); ?></td>
                                    <td class="status-cell">
                                        <span class="status-indicator <?php echo $check['status'] ? 'status-ok' : 'status-error'; ?>">
                                            <?php echo $check['status'] ? '✓' : '✗'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($check['value']); ?></td>
                                    <td><?php echo esc_html($check['required']); ?></td>
                                    <td><?php echo esc_html($check['message']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
            
            <div class="btr-diagnostic-actions">
                <h3><?php _e('Azioni', 'born-to-ride-booking'); ?></h3>
                <p>
                    <button class="button" onclick="window.print()">
                        <?php _e('Stampa Report', 'born-to-ride-booking'); ?>
                    </button>
                    <button class="button" id="btr-export-diagnostics">
                        <?php _e('Esporta JSON', 'born-to-ride-booking'); ?>
                    </button>
                </p>
            </div>
        </div>
        
        <script>
        // Dati per export JSON
        var diagnosticsData = <?php echo json_encode($results); ?>;
        </script>
        <?php
    }
    
    /**
     * Get section title
     */
    private function get_section_title($section) {
        $titles = [
            'sistema' => __('Requisiti di Sistema', 'born-to-ride-booking'),
            'database' => __('Database', 'born-to-ride-booking'),
            'pagine' => __('Pagine WordPress', 'born-to-ride-booking'),
            'componenti' => __('Componenti Plugin', 'born-to-ride-booking'),
            'integrazioni' => __('Integrazioni', 'born-to-ride-booking'),
            'cron' => __('Cron Jobs', 'born-to-ride-booking'),
            'permessi' => __('Permessi File System', 'born-to-ride-booking')
        ];
        
        return isset($titles[$section]) ? $titles[$section] : ucfirst($section);
    }
}

// Inizializza
BTR_System_Diagnostics::get_instance();