<?php
/**
 * Plugin Name: Born to Ride Booking
 * Description: Plugin per la gestione delle prenotazioni di pacchetti viaggio con WooCommerce.
 * Version: 1.0.251
 * Author: LabUIX
 * Text Domain: born-to-ride-booking
 * Update URI: https://github.com/andreavadacchino/born-to-ride-booking
 * GitHub Plugin URI: https://github.com/andreavadacchino/born-to-ride-booking
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evita accessi diretti
}

// Definisci il percorso del plugin (filesystem path)
if ( ! defined( 'BTR_PLUGIN_DIR' ) ) {
    define( 'BTR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

// Definisci l'URL del plugin
if ( ! defined( 'BTR_PLUGIN_URL' ) ) {
    define( 'BTR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Definisci la versione del plugin
if ( ! defined( 'BTR_VERSION' ) ) {
    define( 'BTR_VERSION', '1.0.250' );
}

// Definisci il file principale del plugin
if ( ! defined( 'BTR_PLUGIN_FILE' ) ) {
    define( 'BTR_PLUGIN_FILE', __FILE__ );
}

// Definisci se abilitare il debug logging
// Per abilitare il debug, impostare a true o definire BTR_DEBUG nel wp-config.php
// Esempio in wp-config.php: define('BTR_DEBUG', true);
if ( ! defined( 'BTR_DEBUG' ) ) {
    define( 'BTR_DEBUG', false ); // Temporarily enabled for GitHub updater debugging
}

// Definisci se usare la versione refactored di create_preventivo
// Per abilitare, definire BTR_USE_REFACTORED_QUOTE nel wp-config.php
// Esempio in wp-config.php: define('BTR_USE_REFACTORED_QUOTE', true);
if ( ! defined( 'BTR_USE_REFACTORED_QUOTE' ) ) {
    // Abilita la versione V4 (refactored) del flusso preventivi con parsing/rounding robusto
    define( 'BTR_USE_REFACTORED_QUOTE', true );
}

/**
 * Funzione helper per il debug logging condizionale
 * @param string $message Il messaggio da loggare
 */
if ( ! function_exists( 'btr_debug_log' ) ) {
    function btr_debug_log( $message ) {
        if ( defined( 'BTR_DEBUG' ) && BTR_DEBUG ) {
            error_log( $message );
        }
    }
}

// RIMOSSA PRIMA DEFINIZIONE DI btr_format_price PER EVITARE ERRORE FATALE
// La seconda definizione più completa è mantenuta sotto

/**
 * Funzione helper per formattazione prezzi in formato italiano
 * Formato: €1.000,50 (punto per migliaia, virgola per decimali, € prefisso)
 * 
 * @param float $amount Importo da formattare
 * @param int $decimals Numero di decimali (default: 2)
 * @param bool $show_currency Mostra simbolo € (default: true)
 * @param bool $prefix_currency € come prefisso se true, suffisso se false (default: true)
 * @return string Prezzo formattato in formato italiano
 */
if ( ! function_exists( 'btr_format_price' ) ) {
    function btr_format_price( $amount, $decimals = 2, $show_currency = true, $prefix_currency = true ) {
        // Assicura che l'importo sia un numero valido
        $amount = (float) $amount;
        
        // Gestione valori negativi
        $is_negative = $amount < 0;
        $abs_amount = abs( $amount );
        
        // Formatta con separatori italiani: punto per migliaia, virgola per decimali
        $formatted = number_format( $abs_amount, $decimals, ',', '.' );
        
        // Aggiungi simbolo € se richiesto
        if ( $show_currency ) {
            if ( $prefix_currency ) {
                $formatted = $is_negative ? '-€' . $formatted : '€' . $formatted;
            } else {
                $formatted = $is_negative ? '-' . $formatted . ' €' : $formatted . ' €';
            }
        } else {
            $formatted = $is_negative ? '-' . $formatted : $formatted;
        }
        
        return $formatted;
    }
}

// Funzione per formattazione prezzi in formato italiano internazionale
if ( ! function_exists( 'btr_format_price_i18n' ) ) {
    function btr_format_price_i18n( $amount, $show_currency = true, $prefix_currency = true ) {
        // Usa la funzione base con formato italiano standard
        return btr_format_price( $amount, 2, $show_currency, $prefix_currency );
    }
}

// Initialize GitHub Updater (must be early and outside of main class)
if ( is_admin() ) {
    $updater_file = BTR_PLUGIN_DIR . 'includes/class-btr-github-updater.php';
    if ( file_exists( $updater_file ) ) {
        require_once $updater_file;
        // Initialize updater immediately
        BTR_GitHub_Updater::get_instance( BTR_PLUGIN_FILE );
        btr_debug_log( 'BTR GitHub Updater: Initialized early from main plugin file' );
    }
}

// Assicurati che WooCommerce sia attivo
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    class BornToRideBooking {

        public function __construct() {
            // Carica le dipendenze
            $this->load_dependencies();

            // Inizializza le classi principali
            $this->initialize_classes();
        }

        private function load_dependencies() {
            // Assicurati di includere tutti i file delle classi necessari

            // GitHub Updater is now initialized early in the main plugin file

            require_once BTR_PLUGIN_DIR . 'includes/class-btr-custom-post-type.php';
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-pacchetti-cpt.php';
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-admin-interface.php';
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-prenotazioni.php';
            // Carica sempre la classe principale dei preventivi
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-preventivi.php';
            
            // Se il flag è attivo, carica anche la V4 per sovrascrivere solo create_preventivo
            if (defined('BTR_USE_REFACTORED_QUOTE') && BTR_USE_REFACTORED_QUOTE === true) {
                require_once BTR_PLUGIN_DIR . 'includes/class-btr-preventivi-v4.php';
                btr_debug_log('[BTR v' . BTR_VERSION . '] Usando versione V4 OTTIMIZZATA di create_preventivo');
            } else {
                btr_debug_log('[BTR v' . BTR_VERSION . '] Usando versione ORIGINALE di create_preventivo');
            }
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-preventivi-admin.php';
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-pdf-generator.php';
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-email-manager.php';
            
            // FEATURE FLAGS: Rollout conservativo funzionalità
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-feature-flags.php';
            
            // UNIFIED CALCULATOR v2.0: Single Source of Truth per calcoli
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-unified-calculator.php';
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-shortcodes.php';
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-metabox.php';
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-woocommerce-sync.php';
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-variations-manager.php';
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-frontend-display.php';
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-preventivi-ordini.php';
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-preventivi-ordini-v2.php';
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-shortcode-anagrafici.php';
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-prenotazioni-orderview.php';
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-checkout.php';
            // Include della nuova classe dashboard
            require_once BTR_PLUGIN_DIR . 'admin/class-btr-dashboard.php';
            // Include del gestore menu riorganizzato
            require_once BTR_PLUGIN_DIR . 'admin/class-btr-menu-manager.php';
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-wpbakery.php';
            // Include sistema pagamenti di gruppo
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-group-payments.php';
            // Include sistema caparra + saldo
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-deposit-balance.php';
            // Include sistema categorie child dinamiche
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-dynamic-child-categories.php';
            // Include sistema prezzi notte extra bambini
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-child-extra-night-pricing.php';
            // Include sistema validazione età bambini
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-child-age-validator.php';
            // Include sistema date range manager
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-date-range-manager.php';
            // Include sistema prezzi bambini nelle camere
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-child-room-pricing.php';
            // Include AJAX handlers
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-ajax-handlers.php';
            // Include gestore extra nel carrello
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-cart-extras-manager.php';
            // Include gestore etichette dinamiche bambini
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-child-labels-manager.php';
            // Include gestore drag & drop extra costs
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-extra-costs-sortable.php';
            // Include gestore display notti extra
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-extra-nights-display.php';
            // Include helper filtro No Skipass
            require_once BTR_PLUGIN_DIR . 'includes/helpers/class-btr-no-skipass-filter.php';
            // Include fix per pacchetti 1 notte
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-single-night-fix.php';
            // Include gestione condizionale campi indirizzo
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-conditional-address-fields.php';
            // Include blocco custom contesto pagamento checkout
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-payment-context-block.php';
            
            // Include revisione etichette e testi
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-labels-revision.php';
            // Include database installer (v1.1.0)
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-database-installer.php';
            // Include database migration system (v1.1.0)
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-database-migration.php';
            // Include database manager per btr_order_shares (v1.0.99)
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-database-manager.php';
            // Include fix totali checkout
            // DISABILITATO: require_once BTR_PLUGIN_DIR . 'includes/class-btr-checkout-totals-fix.php';
            // Include Store API Integration per WooCommerce Blocks
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-store-api-integration.php';
            // Include Checkout Context Manager per persistenza modalità pagamento (v1.0.238)
            if (file_exists(BTR_PLUGIN_DIR . 'includes/class-btr-checkout-context-manager.php')) {
                require_once BTR_PLUGIN_DIR . 'includes/class-btr-checkout-context-manager.php';
            }
            // Include debug admin (solo in modalità debug)
            // RIMOSSO: if (WP_DEBUG) {
            //     require_once BTR_PLUGIN_DIR . 'includes/class-btr-debug-admin.php';
            // }
            // Include interfacce admin
            require_once BTR_PLUGIN_DIR . 'admin/views/payments-metabox.php';
            require_once BTR_PLUGIN_DIR . 'admin/views/payment-settings-page.php';
            
            // Include developer menu SOLO in ambiente di sviluppo
            // Questo file NON deve essere incluso nella distribuzione finale
            if (file_exists(BTR_PLUGIN_DIR . 'admin/class-btr-developer-menu.php')) {
                require_once BTR_PLUGIN_DIR . 'admin/class-btr-developer-menu.php';
            }
            
            // Include admin AJAX handler per gestire richieste AJAX admin
            if (is_admin() && file_exists(BTR_PLUGIN_DIR . 'admin/class-btr-admin-ajax.php')) {
                require_once BTR_PLUGIN_DIR . 'admin/class-btr-admin-ajax.php';
            }

            // Include sistema diagnostico BTR (v1.0.240)
            if (is_admin()) {
                if (file_exists(BTR_PLUGIN_DIR . 'admin/class-btr-diagnostic-ajax.php')) {
                    require_once BTR_PLUGIN_DIR . 'admin/class-btr-diagnostic-ajax.php';
                }
                if (file_exists(BTR_PLUGIN_DIR . 'admin/class-btr-diagnostic-menu.php')) {
                    require_once BTR_PLUGIN_DIR . 'admin/class-btr-diagnostic-menu.php';
                }
                if (file_exists(BTR_PLUGIN_DIR . 'admin/class-btr-system-diagnostics.php')) {
                    require_once BTR_PLUGIN_DIR . 'admin/class-btr-system-diagnostics.php';
                }
            }
            
            // Include payment setup helper (temporaneo per configurazione)
            if (is_admin() && file_exists(BTR_PLUGIN_DIR . 'admin/payment-setup-helper.php')) {
                require_once BTR_PLUGIN_DIR . 'admin/payment-setup-helper.php';
            }
            
            // Nota: Pannello impostazioni pagamento integrato in class-btr-payment-plans-admin.php
            
            // Include hotfix loader per patch temporanee
            if (file_exists(BTR_PLUGIN_DIR . 'includes/class-btr-hotfix-loader.php')) {
                require_once BTR_PLUGIN_DIR . 'includes/class-btr-hotfix-loader.php';
            }
            
            // Include fix per la visualizzazione dei costi extra
            if (file_exists(BTR_PLUGIN_DIR . 'includes/class-btr-extra-costs-display-fix.php')) {
                require_once BTR_PLUGIN_DIR . 'includes/class-btr-extra-costs-display-fix.php';
            }
            
            
            // Include calcolatore centralizzato dei costi (v1.0.100)
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-cost-calculator.php';
            
            // Include helper functions per meta fields (v1.0.104)
            require_once BTR_PLUGIN_DIR . 'includes/helpers/btr-meta-helpers.php';
            
            // Include gestore rewrite rules (v1.0.99)
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-rewrite-rules-manager.php';
            
            // Include sistema di pagamenti esteso (v1.0.98)
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-payment-plans.php';
            // Include payment plans extended (v1.1.0)
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-payment-plans-extended.php';
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-payment-email-manager.php';
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-payment-selection-shortcode.php';
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-payment-cron.php';
            // Include enhanced cron system (v1.1.0)
            if (file_exists(BTR_PLUGIN_DIR . 'includes/class-btr-payment-cron-enhanced.php')) {
                require_once BTR_PLUGIN_DIR . 'includes/class-btr-payment-cron-enhanced.php';
            }
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-payment-security.php';
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-payment-ajax.php';
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-payment-rewrite.php';
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-payment-integration.php';
            
            // Include integrazione WooCommerce per sistema caparra/saldo
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-woocommerce-deposit-integration.php';
            
            // Include shortcodes per sistema pagamenti
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-payment-shortcodes.php';
            
            // Include dashboard organizzatore per pagamenti di gruppo
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-organizer-dashboard.php';
            
            // Include sistema recovery ordini abbandonati (v1.0.235)
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-order-recovery.php';
            
            // Include sistema email carrelli abbandonati (v1.0.235)
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-abandoned-cart-emails.php';
            
            // Include integrazione gateway pagamento ottimizzata
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-payment-gateway-integration-v2.php';
            
            // Include AJAX handler per gateway
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-payment-gateway-ajax.php';
            
            // Include REST API controller per pagamenti individuali (v1.1.0)
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-payment-rest-controller.php';
            
            // Include gateway API manager per integrazione diretta (v1.1.0)
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-gateway-api-manager.php';
            
            // Include webhook queue manager per retry e dead letter queue (v1.1.0)
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-webhook-queue-manager.php';
            
            // Include payment cron manager per reminder e automazione (v1.1.0)
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-payment-cron-manager.php';
            
            // Include email template manager per template multilingua (v1.1.0)
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-email-template-manager.php';
            
            // Include admin per gestione pagamenti
            if (is_admin()) {
                require_once BTR_PLUGIN_DIR . 'admin/class-btr-payment-plans-admin.php';
                require_once BTR_PLUGIN_DIR . 'admin/class-btr-gateway-settings-admin.php';
                // Include sistema diagnostica
                require_once BTR_PLUGIN_DIR . 'admin/class-btr-system-diagnostics.php';
            }
        }

        private function initialize_classes() {
            
            // Inizializza le classi legacy
            new BTR_Pacchetti_CPT();
            new BTR_Metabox();
            new BTR_WooCommerce_Sync();
            new BTR_Frontend_Display();
            new BTR_Admin_Interface();
            new BTR_Shortcodes();
            
            // Inizializza SEMPRE la classe principale per tutte le sue funzioni
            // (riepilogo preventivo, rendering, etc)
            $preventivi_originale = new BTR_Preventivi();
            
            // Se il flag è attivo, sovrascriviamo SOLO gli hook AJAX con V4
            if (defined('BTR_USE_REFACTORED_QUOTE') && BTR_USE_REFACTORED_QUOTE === true) {
                // Prima rimuoviamo gli hook AJAX della versione originale
                remove_action('wp_ajax_btr_create_preventivo', [$preventivi_originale, 'create_preventivo']);
                remove_action('wp_ajax_nopriv_btr_create_preventivo', [$preventivi_originale, 'create_preventivo']);
                
                // Poi registriamo la versione V4 per gestire create_preventivo (già inclusa in load_dependencies)
                $preventivi_v4 = new BTR_Preventivi_V4();
                $preventivi_v4->register_ajax_hooks();
                
                btr_debug_log('[BTR] Hook AJAX sovrascritti con versione V4 (WordPress best practices, nomi italiani)');
            }
            
            new BTR_Preventivi_Admin();
            new BTR_Preventivo_To_Order();
            new BTR_Anagrafici_Shortcode();
            new BTR_Prenotazioni_Manager();
            // Initialize custom checkout handler
            new BTR_Checkout();
            new BTR_Dashboard();
            // Initialize menu manager
            new BTR_Menu_Manager();
            // Initialize group payments system
            new BTR_Group_Payments();
            // Initialize deposit balance system
            new BTR_Deposit_Balance();
            // Initialize dynamic child categories system
            new BTR_Dynamic_Child_Categories();
            // Initialize child extra night pricing system
            new BTR_Child_Extra_Night_Pricing();
            // Initialize child age validation system
            new BTR_Child_Age_Validator();
            // Initialize child room pricing system
            new BTR_Child_Room_Pricing();
            // Initialize centralized price calculator
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-price-calculator.php';
            // Initialize AJAX handlers
            new BTR_AJAX_Handlers();
            // Initialize checkout totals fix
            // DISABILITATO: new BTR_Checkout_Totals_Fix();
            // Initialize debug admin interface (only if WP_DEBUG is enabled)
            // RIMOSSO: if (defined('WP_DEBUG') && WP_DEBUG) {
            //     new BTR_Debug_Admin();
            // }
            
            // Initialize payment plans system (v1.0.98)
            BTR_Payment_Plans::get_instance();
            BTR_Payment_Email_Manager::get_instance();
            new BTR_Payment_Cron();
            new BTR_Payment_Integration();

            // FIX v1.0.246: Initialize missing checkout context classes
            BTR_Checkout_Context_Manager::get_instance();
            new BTR_Payment_Context_Block();

            // Initialize payment shortcodes
            BTR_Payment_Shortcodes::get_instance();
            
            // Initialize payment selection shortcode
            new BTR_Payment_Selection_Shortcode();
            
            // Initialize WooCommerce deposit integration
            BTR_WooCommerce_Deposit_Integration::get_instance();
            
            // Initialize admin interface for payments
            if (is_admin()) {
                new BTR_Payment_Plans_Admin();
            }
            
            
            // Initialize database tables
            $this->maybe_create_tables();
            
            // Initialize automatic database installer
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-database-auto-installer.php';
            BTR_Database_Auto_Installer::get_instance();
            
            // Initialize webhook queue manager and create DLQ table
            $webhook_manager = new BTR_Webhook_Queue_Manager();
            $webhook_manager->create_dlq_table();
            
            // Initialize payment cron manager for reminders and automation
            new BTR_Payment_Cron_Manager();
            
            // Initialize email template manager for multi-language templates
            new BTR_Email_Template_Manager();
            
            // Initialize REST API endpoints (v1.1.0)
            add_action('rest_api_init', [$this, 'register_rest_api_routes']);
        }

        /**
         * Crea le tabelle del database se necessario
         */
        private function maybe_create_tables() {
            // Usa il nuovo sistema di update automatico
            require_once BTR_PLUGIN_DIR . 'includes/class-btr-database-updater.php';
            
            $updater = new BTR_Database_Updater();
            
            // Hook per update automatici (solo admin)
            if (is_admin()) {
                add_action('admin_init', [$updater, 'check_and_run_updates']);
            }
            
            // Fallback per installazione iniziale
            $db_version = get_option('btr_db_version', '0');
            if ($db_version === '0') {
                require_once BTR_PLUGIN_DIR . 'includes/class-btr-database.php';
                $database = new BTR_Database();
                $database->create_tables();
                
                // Imposta versione iniziale
                update_option('btr_db_version', '1.0.14');
            }
        }
        
        /**
         * Register REST API routes for payment endpoints
         */
        public function register_rest_api_routes() {
            $controller = new BTR_Payment_REST_Controller();
            $controller->register_routes();
        }
    }


    new BornToRideBooking();

} else {
    // Mostra un messaggio di errore nell'admin se WooCommerce non è attivo
    add_action( 'admin_notices', 'btr_wc_inactive_notice' );
    function btr_wc_inactive_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e( 'Il plugin Born to Ride Booking richiede WooCommerce per funzionare correttamente. Per favore, attiva WooCommerce.', 'born-to-ride-booking' ); ?></p>
        </div>
        <?php
    }
}





function info_desc($value){

    $desc = '';
    if(!empty($value)) {
        $desc = '<span class="info-icon">
                        <svg width="14px" height="14px" viewBox="0 0 14 14" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                <g class="info-circle" transform="translate(-565, -159)" fill-rule="nonzero">
                                    <g transform="translate(508, 45)">
                                        <g id="icon-info" transform="translate(57, 114)">
                                            <path d="M7,0 C10.8659932,0 14,3.13400675 14,7 C14,10.8659932 10.8659932,14 7,14 C3.13400675,14 0,10.8659932 0,7 C0,3.13400675 3.13400675,0 7,0 Z M7.25,7 L5.75,7 C5.33578644,7 5,7.33578644 5,7.75 C5,8.16421356 5.33578644,8.5 5.75,8.5 L6.5,8.5 L6.5,10.25 C6.5,10.6642136 6.83578644,11 7.25,11 C7.66421356,11 8,10.6642136 8,10.25 L8,7.75 C8,7.33578644 7.66421356,7 7.25,7 Z M7,3 C6.44771525,3 6,3.44771525 6,4 C6,4.55228475 6.44771525,5 7,5 C7.55228475,5 8,4.55228475 8,4 C8,3.44771525 7.55228475,3 7,3 Z"></path>
                                        </g>
                                    </g>
                                </g>
                            </g>
                        </svg>
                        <div class="tooltip">
                            <span>' . $value . '</span>
                        </div>
                    </span>';
    }
    print $desc;

}








function printr($data)
{
    // Removed the admin check to allow all users to see debug output
    // if (!current_user_can('manage_options')) {
    //     return;
    // }

    ob_start();
    print_r($data);
    $output = ob_get_clean();

    $panel_id = 'debug-panel-' . uniqid();

    echo '<div id="' . esc_attr($panel_id) . '" style="
        background: #1e1e1e;
        color: #dcdcdc;
        font-family: Consolas, monospace;
        padding: 15px;
        border-radius: 5px;
        margin: 20px 0;
        max-height: max-content;
        overflow: auto;
        position: relative;
    ">
        <h3 style="margin: 0 0 15px; font-size: 18px; border-bottom: 1px solid #333; color: #DCDCDE;">Pannello di Debug</h3>
        <div style="display: flex; align-items: center; margin-bottom: 10px;">
            <input type="text" id="' . esc_attr($panel_id) . '-search" placeholder="Cerca e premi Invio..." style="
                flex: 1;
                padding: 10px;
                background-color: #2d2d2d;
                border: 1px solid #444;
                border-radius: 5px;
                color: #dcdcdc;
                font-size: 14px;
            ">
            <button id="' . esc_attr($panel_id) . '-clear" style="
                margin-left: 10px;
                background-color: #444;
                color: #dcdcdc;
                padding: 5px 10px;
                border-radius: 5px;
                border: none;
                font-size: 14px;
                cursor: pointer;
            "><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#dcdcdc" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg></button>
            <span id="' . esc_attr($panel_id) . '-counter" style="
                margin-left: 10px;
                background: #444;
                color: #dcdcdc;
                padding: 5px 10px;
                border-radius: 5px;
                font-size: 14px;
            ">0/0 corrispondenze</span>
        </div>
        <pre id="' . esc_attr($panel_id) . '-content" style="
            max-height: 500px;
            overflow: auto;
            padding: 10px;
            border-radius: 5px;
            background-color: #1e1e1e;
            border: 1px solid #444;
            white-space: pre-wrap;
        ">' . htmlspecialchars($output) . '</pre>
    </div>';

    echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/mark.js/8.11.1/mark.min.js"></script>';

    echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            const searchInput = document.getElementById("' . esc_js($panel_id) . '-search");
            const clearButton = document.getElementById("' . esc_js($panel_id) . '-clear");
            const contentDiv = document.getElementById("' . esc_js($panel_id) . '-content");
            const counter = document.getElementById("' . esc_js($panel_id) . '-counter");
            const markInstance = new Mark(contentDiv);
            let currentMatchIndex = -1;
            let matches = [];

            function updateCounter() {
                if (matches.length > 0) {
                    counter.textContent = (currentMatchIndex + 1) + "/" + matches.length + " corrispondenze";
                } else {
                    counter.textContent = "0/0 corrispondenze";
                }
            }

            function scrollToMatch(index) {
                if (matches.length === 0) {
                    return;
                }
                const match = matches[index];
                if (match) {
                    match.scrollIntoView({ behavior: "smooth", block: "center" });
                    matches.forEach(m => m.style.backgroundColor = "yellow");
                    match.style.backgroundColor = "orange";
                }
            }

            function performSearch() {
                const searchTerm = searchInput.value.trim();

                if (searchTerm === "") {
                    alert("Inserisci un termine di ricerca.");
                    return;
                }

                markInstance.unmark({
                    done: function() {
                        currentMatchIndex = -1;
                        matches = [];
                        markInstance.mark(searchTerm, {
                            className: "highlight",
                            separateWordSearch: false,
                            done: function(totalMatches) {
                                matches = contentDiv.querySelectorAll(".highlight");
                                updateCounter();
                                if (matches.length > 0) {
                                    currentMatchIndex = 0;
                                    scrollToMatch(currentMatchIndex);
                                } else {
                                    alert("Nessuna corrispondenza trovata.");
                                }
                            }
                        });
                    }
                });
            }

            function clearSearch() {
                searchInput.value = "";
                markInstance.unmark();
                matches = [];
                currentMatchIndex = -1;
                updateCounter();
            }

            searchInput.addEventListener("keydown", function(event) {
                if (event.key === "Enter") {
                    event.preventDefault();
                    if (matches.length === 0) {
                        performSearch();
                    } else {
                        currentMatchIndex = (currentMatchIndex + 1) % matches.length;
                        scrollToMatch(currentMatchIndex);
                        updateCounter();
                    }
                } else if (event.key === "ArrowDown" && matches.length > 0) {
                    event.preventDefault();
                    currentMatchIndex = (currentMatchIndex + 1) % matches.length;
                    scrollToMatch(currentMatchIndex);
                    updateCounter();
                } else if (event.key === "ArrowUp" && matches.length > 0) {
                    event.preventDefault();
                    currentMatchIndex = (currentMatchIndex - 1 + matches.length) % matches.length;
                    scrollToMatch(currentMatchIndex);
                    updateCounter();
                }
            });

            clearButton.addEventListener("click", clearSearch);
        });
    </script>';

    echo '<style>
        .highlight {
            background: yellow;
            color: black;
            padding: 0 3px;
            border-radius: 3px;
        }
        button:hover {
            background-color: #666;
        }
    </style>';
}

?>
