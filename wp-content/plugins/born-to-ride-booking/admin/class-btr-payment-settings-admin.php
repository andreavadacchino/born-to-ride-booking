<?php
/**
 * Pannello amministrazione impostazioni pagamento
 * 
 * @package BornToRideBooking
 * @since 1.0.98+
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Payment_Settings_Admin {
    
    /**
     * Instance singleton
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
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
    private function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        
        // AJAX handlers
        add_action('wp_ajax_btr_test_payment_system', [$this, 'ajax_test_payment_system']);
    }
    
    /**
     * Aggiunge voce al menu amministrazione
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=preventivi',
            __('Impostazioni Pagamento', 'born-to-ride-booking'),
            __('Impostazioni Pagamento', 'born-to-ride-booking'),
            'manage_options',
            'btr-payment-settings',
            [$this, 'render_settings_page']
        );
    }
    
    /**
     * Registra le impostazioni
     */
    public function register_settings() {
        // Sezione principale
        add_settings_section(
            'btr_payment_general',
            __('Impostazioni Generali', 'born-to-ride-booking'),
            [$this, 'render_general_section'],
            'btr-payment-settings'
        );
        
        // Abilita sistema pagamenti
        add_settings_field(
            'btr_enable_payment_plans',
            __('Abilita Piani di Pagamento', 'born-to-ride-booking'),
            [$this, 'render_enable_payment_plans_field'],
            'btr-payment-settings',
            'btr_payment_general'
        );
        
        // Sezione bonifico bancario
        add_settings_section(
            'btr_payment_bank_transfer',
            __('Impostazioni Bonifico Bancario', 'born-to-ride-booking'),
            [$this, 'render_bank_transfer_section'],
            'btr-payment-settings'
        );
        
        // Abilita caparra/gruppo con bonifico
        add_settings_field(
            'btr_enable_bank_transfer_plans',
            __('Abilita Caparra/Gruppo con Bonifico', 'born-to-ride-booking'),
            [$this, 'render_bank_transfer_plans_field'],
            'btr-payment-settings',
            'btr_payment_bank_transfer'
        );
        
        // Percentuale caparra default
        add_settings_field(
            'btr_default_deposit_percentage',
            __('Percentuale Caparra Default (%)', 'born-to-ride-booking'),
            [$this, 'render_deposit_percentage_field'],
            'btr-payment-settings',
            'btr_payment_bank_transfer'
        );

        // Abilita pagamento di gruppo
        add_settings_field(
            'btr_enable_group_split',
            __('Abilita Pagamento di Gruppo', 'born-to-ride-booking'),
            [$this, 'render_enable_group_split_field'],
            'btr-payment-settings',
            'btr_payment_general'
        );

        // Soglia minima partecipanti per gruppo
        add_settings_field(
            'btr_group_split_threshold',
            __('Soglia minima partecipanti (gruppo)', 'born-to-ride-booking'),
            [$this, 'render_group_split_threshold_field'],
            'btr-payment-settings',
            'btr_payment_general'
        );

        // Modalità pagamento predefinita
        add_settings_field(
            'btr_default_payment_mode',
            __('Modalità pagamento predefinita', 'born-to-ride-booking'),
            [$this, 'render_default_payment_mode_field'],
            'btr-payment-settings',
            'btr_payment_general'
        );
        
        // Testo informativo bonifico
        add_settings_field(
            'btr_bank_transfer_info',
            __('Testo Informativo Bonifico', 'born-to-ride-booking'),
            [$this, 'render_bank_transfer_info_field'],
            'btr-payment-settings',
            'btr_payment_bank_transfer'
        );
        
        // Registra le opzioni
        register_setting('btr-payment-settings', 'btr_enable_payment_plans', [
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ]);
        
        register_setting('btr-payment-settings', 'btr_enable_bank_transfer_plans', [
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ]);
        
        register_setting('btr-payment-settings', 'btr_default_deposit_percentage', [
            'type' => 'integer',
            'default' => 30,
            'sanitize_callback' => [$this, 'sanitize_percentage']
        ]);
        
        register_setting('btr-payment-settings', 'btr_bank_transfer_info', [
            'type' => 'string',
            'default' => __('Il bonifico bancario supporta il pagamento con caparra o la suddivisione in gruppo. Seleziona la modalità preferita prima di procedere.', 'born-to-ride-booking'),
            'sanitize_callback' => 'wp_kses_post'
        ]);

        register_setting('btr-payment-settings', 'btr_enable_group_split', [
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'rest_sanitize_boolean'
        ]);

        register_setting('btr-payment-settings', 'btr_group_split_threshold', [
            'type' => 'integer',
            'default' => 10,
            'sanitize_callback' => 'absint'
        ]);

        register_setting('btr-payment-settings', 'btr_default_payment_mode', [
            'type' => 'string',
            'default' => 'full',
            'sanitize_callback' => function($v){ return in_array($v, ['full','deposit_balance'], true) ? $v : 'full'; }
        ]);
    }
    
    /**
     * Render sezione generale
     */
    public function render_general_section() {
        echo '<p>' . esc_html__('Configura le impostazioni generali per il sistema di pagamento.', 'born-to-ride-booking') . '</p>';
    }
    
    /**
     * Render sezione bonifico
     */
    public function render_bank_transfer_section() {
        echo '<p>' . esc_html__('Configura le opzioni specifiche per il metodo di pagamento bonifico bancario.', 'born-to-ride-booking') . '</p>';
    }
    
    /**
     * Render campo abilita pagamenti
     */
    public function render_enable_payment_plans_field() {
        $value = get_option('btr_enable_payment_plans', true);
        ?>
        <label>
            <input type="checkbox" name="btr_enable_payment_plans" value="1" <?php checked($value); ?> />
            <?php esc_html_e('Abilita il sistema di piani di pagamento (caparra, gruppo, ecc.)', 'born-to-ride-booking'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('Se disabilitato, i clienti potranno pagare solo l\'importo completo.', 'born-to-ride-booking'); ?>
        </p>
        <?php
    }
    
    /**
     * Render campo bonifico caparra/gruppo
     */
    public function render_bank_transfer_plans_field() {
        $value = get_option('btr_enable_bank_transfer_plans', true);
        ?>
        <label>
            <input type="checkbox" name="btr_enable_bank_transfer_plans" value="1" <?php checked($value); ?> />
            <?php esc_html_e('Permetti pagamento con caparra o suddivisione gruppo anche utilizzando bonifico bancario', 'born-to-ride-booking'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('Se abilitato, i clienti potranno scegliere caparra/gruppo anche quando selezionano il bonifico come metodo di pagamento.', 'born-to-ride-booking'); ?>
        </p>
        <?php
    }
    
    /**
     * Render campo percentuale caparra
     */
    public function render_deposit_percentage_field() {
        $value = get_option('btr_default_deposit_percentage', 30);
        ?>
        <input type="number" name="btr_default_deposit_percentage" value="<?php echo esc_attr($value); ?>" 
               min="10" max="90" step="5" class="small-text" />
        <p class="description">
            <?php esc_html_e('Percentuale di caparra predefinita (da 10% a 90%, incrementi di 5%)', 'born-to-ride-booking'); ?>
        </p>
        <?php
    }
    
    /**
     * Render campo info bonifico
     */
    public function render_bank_transfer_info_field() {
        $value = get_option('btr_bank_transfer_info', __('Il bonifico bancario supporta il pagamento con caparra o la suddivisione in gruppo. Seleziona la modalità preferita prima di procedere.', 'born-to-ride-booking'));
        ?>
        <textarea name="btr_bank_transfer_info" rows="3" cols="50" class="large-text"><?php echo esc_textarea($value); ?></textarea>
        <p class="description">
            <?php esc_html_e('Testo mostrato ai clienti quando selezionano bonifico bancario con opzioni caparra/gruppo.', 'born-to-ride-booking'); ?>
        </p>
        <?php
    }

    /**
     * Render abilita pagamento di gruppo
     */
    public function render_enable_group_split_field() {
        $value = get_option('btr_enable_group_split', true);
        ?>
        <label>
            <input type="checkbox" name="btr_enable_group_split" value="1" <?php checked($value); ?> />
            <?php esc_html_e('Mostra l\'opzione Pagamento di Gruppo quando consentito', 'born-to-ride-booking'); ?>
        </label>
        <p class="description"><?php esc_html_e('Se disabilitato, il pagamento di gruppo non sarà disponibile.', 'born-to-ride-booking'); ?></p>
        <?php
    }

    /**
     * Render soglia partecipanti
     */
    public function render_group_split_threshold_field() {
        $value = get_option('btr_group_split_threshold', 10);
        ?>
        <input type="number" name="btr_group_split_threshold" value="<?php echo esc_attr($value); ?>" min="1" step="1" class="small-text" />
        <p class="description"><?php esc_html_e('Numero minimo di partecipanti per offrire il pagamento di gruppo.', 'born-to-ride-booking'); ?></p>
        <?php
    }

    /**
     * Render modalità pagamento predefinita
     */
    public function render_default_payment_mode_field() {
        $value = get_option('btr_default_payment_mode', 'full');
        ?>
        <select name="btr_default_payment_mode">
            <option value="full" <?php selected($value, 'full'); ?>><?php esc_html_e('Pagamento Completo', 'born-to-ride-booking'); ?></option>
            <option value="deposit_balance" <?php selected($value, 'deposit_balance'); ?>><?php esc_html_e('Caparra + Saldo', 'born-to-ride-booking'); ?></option>
        </select>
        <p class="description"><?php esc_html_e('Usata quando il pagamento di gruppo non è disponibile (soglia non raggiunta).', 'born-to-ride-booking'); ?></p>
        <?php
    }
    
    /**
     * Sanitize percentuale
     */
    public function sanitize_percentage($input) {
        $value = intval($input);
        if ($value < 10) $value = 10;
        if ($value > 90) $value = 90;
        return $value;
    }
    
    /**
     * Render pagina impostazioni
     */
    public function render_settings_page() {
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            wp_die(__('Non hai i permessi necessari per accedere a questa pagina.', 'born-to-ride-booking'));
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Impostazioni Sistema Pagamento', 'born-to-ride-booking'); ?></h1>
            
            <?php
            // Mostra messaggi di aggiornamento
            if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
                ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php esc_html_e('Impostazioni salvate con successo!', 'born-to-ride-booking'); ?></p>
                </div>
                <?php
            }
            ?>
            
            <div class="btr-settings-wrapper">
                <div class="btr-settings-main">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('btr-payment-settings');
                        do_settings_sections('btr-payment-settings');
                        submit_button();
                        ?>
                    </form>
                </div>
                
                <div class="btr-settings-sidebar">
                    <div class="btr-settings-box">
                        <h3><?php esc_html_e('Informazioni Sistema', 'born-to-ride-booking'); ?></h3>
                        <ul>
                            <li><strong><?php esc_html_e('Versione Plugin:', 'born-to-ride-booking'); ?></strong> <?php echo esc_html(BTR_VERSION); ?></li>
                            <li><strong><?php esc_html_e('Stato Database:', 'born-to-ride-booking'); ?></strong> 
                                <?php echo $this->check_database_status(); ?>
                            </li>
                            <li><strong><?php esc_html_e('WooCommerce:', 'born-to-ride-booking'); ?></strong> 
                                <?php echo class_exists('WooCommerce') ? '✅ ' . esc_html__('Attivo', 'born-to-ride-booking') : '❌ ' . esc_html__('Non attivo', 'born-to-ride-booking'); ?>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="btr-settings-box">
                        <h3><?php esc_html_e('Gateway Pagamento Disponibili', 'born-to-ride-booking'); ?></h3>
                        <?php $this->render_available_gateways(); ?>
                    </div>
                    
                    <div class="btr-settings-box">
                        <h3><?php esc_html_e('Azioni Rapide', 'born-to-ride-booking'); ?></h3>
                        <p>
                            <a href="<?php echo admin_url('edit.php?post_type=preventivi&page=btr-payment-plans'); ?>" class="button">
                                <?php esc_html_e('Gestisci Piani Pagamento', 'born-to-ride-booking'); ?>
                            </a>
                        </p>
                        <p>
                            <a href="#" class="button" onclick="btrTestPaymentSystem()">
                                <?php esc_html_e('Test Sistema', 'born-to-ride-booking'); ?>
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .btr-settings-wrapper {
            display: flex;
            gap: 30px;
        }
        .btr-settings-main {
            flex: 2;
        }
        .btr-settings-sidebar {
            flex: 1;
        }
        .btr-settings-box {
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            margin-bottom: 20px;
            padding: 20px;
        }
        .btr-settings-box h3 {
            margin-top: 0;
            font-size: 14px;
            font-weight: 600;
        }
        .btr-settings-box ul {
            margin: 0;
            padding: 0;
        }
        .btr-settings-box li {
            margin: 10px 0;
            padding: 0;
            list-style: none;
        }
        .btr-gateway-status {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 5px 0;
            padding: 8px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        .btr-gateway-enabled {
            background: #d4edda;
            color: #155724;
        }
        .btr-gateway-disabled {
            background: #f8d7da;
            color: #721c24;
        }
        @media (max-width: 782px) {
            .btr-settings-wrapper {
                flex-direction: column;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Verifica stato database
     */
    private function check_database_status() {
        global $wpdb;
        
        $tables = [
            'btr_payment_plans',
            'btr_group_payments', 
            'btr_payment_reminders'
        ];
        
        $missing = [];
        foreach ($tables as $table) {
            $table_name = $wpdb->prefix . $table;
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
                $missing[] = $table;
            }
        }
        
        if (empty($missing)) {
            return '✅ ' . esc_html__('Tutte le tabelle presenti', 'born-to-ride-booking');
        } else {
            return '⚠️ ' . sprintf(esc_html__('Mancano %d tabelle', 'born-to-ride-booking'), count($missing));
        }
    }
    
    /**
     * Render gateway disponibili
     */
    private function render_available_gateways() {
        if (!class_exists('WooCommerce')) {
            echo '<p>' . esc_html__('WooCommerce non è attivo.', 'born-to-ride-booking') . '</p>';
            return;
        }
        
        $gateways = WC()->payment_gateways ? WC()->payment_gateways->payment_gateways() : [];
        
        if (empty($gateways)) {
            echo '<p>' . esc_html__('Nessun gateway trovato.', 'born-to-ride-booking') . '</p>';
            return;
        }
        
        foreach ($gateways as $id => $gateway) {
            $enabled = $gateway->enabled === 'yes';
            $class = $enabled ? 'btr-gateway-enabled' : 'btr-gateway-disabled';
            $icon = $enabled ? '✅' : '❌';
            
            echo '<div class="btr-gateway-status ' . esc_attr($class) . '">';
            echo '<span>' . esc_html($icon) . '</span>';
            echo '<strong>' . esc_html($gateway->get_method_title()) . '</strong>';
            echo '</div>';
        }
    }
    
    /**
     * AJAX test sistema pagamento
     */
    public function ajax_test_payment_system() {
        check_ajax_referer('btr_payment_settings_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permessi insufficienti.', 'born-to-ride-booking')]);
        }
        
        $test_results = [];
        
        // Test 1: Database tables
        global $wpdb;
        $tables = ['btr_payment_plans', 'btr_group_payments', 'btr_payment_reminders'];
        $tables_status = [];
        
        foreach ($tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            $tables_status[$table] = $exists;
        }
        
        $test_results['database'] = [
            'status' => array_filter($tables_status) === $tables_status,
            'details' => $tables_status
        ];
        
        // Test 2: WooCommerce integration
        $test_results['woocommerce'] = [
            'active' => class_exists('WooCommerce'),
            'gateways_count' => class_exists('WooCommerce') ? count(WC()->payment_gateways->payment_gateways()) : 0
        ];
        
        // Test 3: Settings options
        $test_results['settings'] = [
            'payment_plans_enabled' => get_option('btr_enable_payment_plans', true),
            'bank_transfer_plans_enabled' => get_option('btr_enable_bank_transfer_plans', true),
            'deposit_percentage' => get_option('btr_default_deposit_percentage', 30)
        ];
        
        // Test 4: Required classes
        $required_classes = [
            'BTR_Payment_Plans',
            'BTR_Payment_Integration',
            'BTR_Group_Payments',
            'BTR_Deposit_Balance'
        ];
        
        $classes_status = [];
        foreach ($required_classes as $class) {
            $classes_status[$class] = class_exists($class);
        }
        
        $test_results['classes'] = [
            'status' => array_filter($classes_status) === $classes_status,
            'details' => $classes_status
        ];
        
        // Overall status
        $overall_status = $test_results['database']['status'] && 
                         $test_results['woocommerce']['active'] && 
                         $test_results['classes']['status'];
        
        wp_send_json_success([
            'overall_status' => $overall_status,
            'message' => $overall_status ? 
                __('Tutti i test sono passati con successo!', 'born-to-ride-booking') :
                __('Alcuni test hanno rilevato problemi. Controlla i dettagli.', 'born-to-ride-booking'),
            'details' => $test_results
        ]);
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook_suffix) {
        if ($hook_suffix !== 'preventivi_page_btr-payment-settings') {
            return;
        }
        
        wp_enqueue_script('btr-payment-settings-admin', BTR_PLUGIN_URL . 'assets/js/payment-settings-admin.js', ['jquery'], BTR_VERSION, true);
        wp_localize_script('btr-payment-settings-admin', 'btrPaymentSettings', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('btr_payment_settings_nonce'),
            'strings' => [
                'testing' => __('Test in corso...', 'born-to-ride-booking'),
                'test_success' => __('Test completato con successo!', 'born-to-ride-booking'),
                'test_error' => __('Errore durante il test.', 'born-to-ride-booking')
            ]
        ]);
    }
}

// Inizializza
BTR_Payment_Settings_Admin::get_instance();
