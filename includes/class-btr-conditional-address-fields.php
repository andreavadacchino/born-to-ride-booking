<?php
/**
 * Gestione condizionale dei campi indirizzo per i partecipanti
 * 
 * @package Born_To_Ride_Booking
 * @since 1.0.55
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Conditional_Address_Fields {
    
    /**
     * Singleton instance
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
        // Hook per modificare i campi nel form anagrafici
        add_filter('btr_anagrafici_form_fields', [$this, 'filter_form_fields'], 10, 3);
        
        // Hook per aggiungere opzioni nell'admin
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // Hook per salvare le impostazioni
        add_action('admin_init', [$this, 'register_settings']);
        
        // Script per gestione frontend
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
        
        // AJAX per recuperare configurazione
        add_action('wp_ajax_btr_get_address_config', [$this, 'ajax_get_config']);
        add_action('wp_ajax_nopriv_btr_get_address_config', [$this, 'ajax_get_config']);
    }
    
    /**
     * Filtra i campi del form in base alla configurazione
     */
    public function filter_form_fields($fields, $index, $context) {
        $config = $this->get_address_config();
        
        // Se gli indirizzi sono disabilitati globalmente
        if (!$config['enabled']) {
            // Rimuovi i campi indirizzo
            $address_fields = [
                'indirizzo_residenza',
                'numero_civico',
                'citta_residenza',
                'cap_residenza',
                'provincia_residenza',
                'nazione_residenza'
            ];
            
            foreach ($address_fields as $field) {
                if (isset($fields[$field])) {
                    unset($fields[$field]);
                }
            }
        } else {
            // Applica regole condizionali
            if ($config['only_first'] && $index > 0) {
                // Solo per il primo partecipante
                $this->hide_address_fields($fields);
            }
            
            if ($config['only_with_insurance'] && empty($context['has_insurance'])) {
                // Solo per chi ha assicurazione
                $this->hide_address_fields($fields);
            }
            
            if ($config['only_adults'] && !empty($context['is_child'])) {
                // Solo per adulti
                $this->hide_address_fields($fields);
            }
        }
        
        return $fields;
    }
    
    /**
     * Nasconde i campi indirizzo
     */
    private function hide_address_fields(&$fields) {
        $address_fields = [
            'indirizzo_residenza',
            'numero_civico',
            'citta_residenza',
            'cap_residenza',
            'provincia_residenza',
            'nazione_residenza'
        ];
        
        foreach ($address_fields as $field) {
            if (isset($fields[$field])) {
                $fields[$field]['type'] = 'hidden';
                $fields[$field]['wrapper_class'] = 'btr-hidden-field';
            }
        }
    }
    
    /**
     * Ottieni la configurazione corrente
     */
    public function get_address_config() {
        $defaults = [
            'enabled' => true,
            'only_first' => false,
            'only_with_insurance' => false,
            'only_adults' => false,
            'required_fields' => [
                'indirizzo_residenza' => true,
                'citta_residenza' => true,
                'cap_residenza' => true,
                'provincia_residenza' => true
            ]
        ];
        
        $config = get_option('btr_address_fields_config', $defaults);
        return wp_parse_args($config, $defaults);
    }
    
    /**
     * Aggiungi menu admin
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=preventivo',
            'Campi Indirizzo',
            'Campi Indirizzo',
            'manage_options',
            'btr-address-fields',
            [$this, 'admin_page']
        );
    }
    
    /**
     * Pagina admin
     */
    public function admin_page() {
        $config = $this->get_address_config();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Configurazione Campi Indirizzo', 'born-to-ride-booking'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('btr_address_fields_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Abilita campi indirizzo', 'born-to-ride-booking'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" name="btr_address_fields_config[enabled]" 
                                       value="1" <?php checked($config['enabled'], true); ?>>
                                <?php esc_html_e('Richiedi indirizzo di residenza ai partecipanti', 'born-to-ride-booking'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Regole condizionali', 'born-to-ride-booking'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="btr_address_fields_config[only_first]" 
                                           value="1" <?php checked($config['only_first'], true); ?>>
                                    <?php esc_html_e('Solo per il primo partecipante', 'born-to-ride-booking'); ?>
                                </label>
                                <br>
                                
                                <label>
                                    <input type="checkbox" name="btr_address_fields_config[only_with_insurance]" 
                                           value="1" <?php checked($config['only_with_insurance'], true); ?>>
                                    <?php esc_html_e('Solo per partecipanti con assicurazione', 'born-to-ride-booking'); ?>
                                </label>
                                <br>
                                
                                <label>
                                    <input type="checkbox" name="btr_address_fields_config[only_adults]" 
                                           value="1" <?php checked($config['only_adults'], true); ?>>
                                    <?php esc_html_e('Solo per adulti (non bambini)', 'born-to-ride-booking'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Campi obbligatori', 'born-to-ride-booking'); ?>
                        </th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="checkbox" name="btr_address_fields_config[required_fields][indirizzo_residenza]" 
                                           value="1" <?php checked($config['required_fields']['indirizzo_residenza'] ?? true, true); ?>>
                                    <?php esc_html_e('Indirizzo', 'born-to-ride-booking'); ?>
                                </label>
                                <br>
                                
                                <label>
                                    <input type="checkbox" name="btr_address_fields_config[required_fields][citta_residenza]" 
                                           value="1" <?php checked($config['required_fields']['citta_residenza'] ?? true, true); ?>>
                                    <?php esc_html_e('Città', 'born-to-ride-booking'); ?>
                                </label>
                                <br>
                                
                                <label>
                                    <input type="checkbox" name="btr_address_fields_config[required_fields][cap_residenza]" 
                                           value="1" <?php checked($config['required_fields']['cap_residenza'] ?? true, true); ?>>
                                    <?php esc_html_e('CAP', 'born-to-ride-booking'); ?>
                                </label>
                                <br>
                                
                                <label>
                                    <input type="checkbox" name="btr_address_fields_config[required_fields][provincia_residenza]" 
                                           value="1" <?php checked($config['required_fields']['provincia_residenza'] ?? true, true); ?>>
                                    <?php esc_html_e('Provincia', 'born-to-ride-booking'); ?>
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <div class="notice notice-info">
                <p>
                    <?php esc_html_e('Nota: Le modifiche si applicheranno solo ai nuovi form. I preventivi esistenti mantengono i dati già inseriti.', 'born-to-ride-booking'); ?>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Registra impostazioni
     */
    public function register_settings() {
        register_setting('btr_address_fields_settings', 'btr_address_fields_config', [
            'sanitize_callback' => [$this, 'sanitize_config']
        ]);
    }
    
    /**
     * Sanitizza la configurazione
     */
    public function sanitize_config($input) {
        $output = [];
        
        $output['enabled'] = !empty($input['enabled']);
        $output['only_first'] = !empty($input['only_first']);
        $output['only_with_insurance'] = !empty($input['only_with_insurance']);
        $output['only_adults'] = !empty($input['only_adults']);
        
        $output['required_fields'] = [];
        if (isset($input['required_fields'])) {
            foreach ($input['required_fields'] as $field => $value) {
                $output['required_fields'][$field] = !empty($value);
            }
        }
        
        return $output;
    }
    
    /**
     * Enqueue scripts frontend
     */
    public function enqueue_frontend_scripts() {
        if (!is_page() || !has_shortcode(get_post()->post_content, 'btr_form_anagrafici')) {
            return;
        }
        
        $config = $this->get_address_config();
        
        // JavaScript per gestione condizionale
        $js = "
        jQuery(function($) {
            var addressConfig = " . json_encode($config) . ";
            
            // Funzione per mostrare/nascondere campi indirizzo
            function toggleAddressFields(personIndex, show) {
                var fields = [
                    'indirizzo_residenza',
                    'numero_civico',
                    'citta_residenza', 
                    'cap_residenza',
                    'provincia_residenza',
                    'nazione_residenza'
                ];
                
                fields.forEach(function(field) {
                    var fieldRow = $('[name=\"anagrafici[' + personIndex + '][' + field + ']\"]').closest('.btr-field-group');
                    if (show) {
                        fieldRow.show();
                    } else {
                        fieldRow.hide();
                        // Svuota il campo se nascosto
                        fieldRow.find('input, select').val('');
                    }
                });
            }
            
            // Applica regole all'inizializzazione
            function applyAddressRules() {
                if (!addressConfig.enabled) {
                    // Nascondi tutti i campi indirizzo
                    $('.btr-person-card').each(function(index) {
                        toggleAddressFields(index, false);
                    });
                    return;
                }
                
                $('.btr-person-card').each(function(index) {
                    var show = true;
                    var card = $(this);
                    
                    // Solo primo partecipante
                    if (addressConfig.only_first && index > 0) {
                        show = false;
                    }
                    
                    // Solo con assicurazione
                    if (addressConfig.only_with_insurance) {
                        var hasInsurance = card.find('input[name*=\"[assicurazioni]\"]').is(':checked');
                        if (!hasInsurance) {
                            show = false;
                        }
                    }
                    
                    // Solo adulti
                    if (addressConfig.only_adults) {
                        var isChild = card.find('[name*=\"[tipo_partecipante]\"]').val() !== 'adulto';
                        if (isChild) {
                            show = false;
                        }
                    }
                    
                    toggleAddressFields(index, show);
                });
            }
            
            // Applica regole all'avvio
            applyAddressRules();
            
            // Riapplica quando cambiano assicurazioni
            $(document).on('change', 'input[name*=\"[assicurazioni]\"]', function() {
                if (addressConfig.only_with_insurance) {
                    applyAddressRules();
                }
            });
            
            // Rimuovi validazione required per campi nascosti
            $(document).on('submit', 'form', function() {
                $('.btr-field-group:hidden').find('input, select').removeAttr('required');
            });
        });
        ";
        
        wp_add_inline_script('jquery', $js);
    }
    
    /**
     * AJAX handler per recuperare configurazione
     */
    public function ajax_get_config() {
        $config = $this->get_address_config();
        wp_send_json_success($config);
    }
}

// Funzione helper globale
if (!function_exists('btr_should_show_address_fields')) {
    function btr_should_show_address_fields($index = 0, $context = []) {
        $config = BTR_Conditional_Address_Fields::get_instance()->get_address_config();
        
        if (!$config['enabled']) {
            return false;
        }
        
        if ($config['only_first'] && $index > 0) {
            return false;
        }
        
        if ($config['only_with_insurance'] && empty($context['has_insurance'])) {
            return false;
        }
        
        if ($config['only_adults'] && !empty($context['is_child'])) {
            return false;
        }
        
        return true;
    }
}

// Inizializza
add_action('init', function() {
    BTR_Conditional_Address_Fields::get_instance();
});