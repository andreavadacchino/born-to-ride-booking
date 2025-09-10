<?php
/**
 * Revisione centralizzata di tutte le etichette e testi del plugin
 * 
 * @package Born_To_Ride_Booking
 * @since 1.0.55
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Labels_Revision {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Array di tutte le etichette del sistema
     */
    private $labels = [];
    
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
        // Carica etichette di default
        $this->load_default_labels();
        
        // Hook per sostituire etichette
        add_filter('gettext', [$this, 'filter_text'], 10, 3);
        add_filter('ngettext', [$this, 'filter_plural_text'], 10, 5);
        
        // Hook per etichette specifiche del plugin
        add_filter('btr_label', [$this, 'get_label'], 10, 2);
        
        // Admin page per gestione etichette
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        
        // AJAX per export/import
        add_action('wp_ajax_btr_export_labels', [$this, 'ajax_export_labels']);
        add_action('wp_ajax_btr_import_labels', [$this, 'ajax_import_labels']);
    }
    
    /**
     * Carica etichette di default
     */
    private function load_default_labels() {
        $this->labels = [
            // Etichette generali
            'general' => [
                'plugin_name' => 'Born to Ride Booking',
                'booking' => 'Prenotazione',
                'quote' => 'Preventivo',
                'package' => 'Pacchetto',
                'participant' => 'Partecipante',
                'participants' => 'Partecipanti',
                'room' => 'Camera',
                'rooms' => 'Camere',
                'total' => 'Totale',
                'subtotal' => 'Subtotale',
                'price' => 'Prezzo',
                'supplement' => 'Supplemento',
                'discount' => 'Sconto',
                'reduction' => 'Riduzione',
                'addition' => 'Aggiunta',
                'continue' => 'Continua',
                'back' => 'Indietro',
                'save' => 'Salva',
                'cancel' => 'Annulla',
                'confirm' => 'Conferma',
                'select' => 'Seleziona',
                'selected' => 'Selezionato',
                'available' => 'Disponibile',
                'not_available' => 'Non disponibile',
                'required' => 'Obbligatorio',
                'optional' => 'Facoltativo'
            ],
            
            // Etichette partecipanti
            'participants' => [
                'adult' => 'Adulto',
                'adults' => 'Adulti',
                'child' => 'Bambino',
                'children' => 'Bambini',
                'infant' => 'Neonato',
                'infants' => 'Neonati',
                'age' => 'Età',
                'years' => 'anni',
                'birth_date' => 'Data di nascita',
                'fiscal_code' => 'Codice Fiscale',
                'full_name' => 'Nome completo',
                'first_name' => 'Nome',
                'last_name' => 'Cognome',
                'email' => 'Email',
                'phone' => 'Telefono',
                'address' => 'Indirizzo',
                'city' => 'Città',
                'province' => 'Provincia',
                'zip_code' => 'CAP',
                'country' => 'Nazione',
                'residence' => 'Residenza',
                'birth_city' => 'Città di nascita'
            ],
            
            // Etichette camere
            'rooms' => [
                'single' => 'Singola',
                'double' => 'Doppia',
                'triple' => 'Tripla',
                'quadruple' => 'Quadrupla',
                'family' => 'Familiare',
                'suite' => 'Suite',
                'bed_type' => 'Tipo letto',
                'double_bed' => 'Letto matrimoniale',
                'twin_beds' => 'Letti singoli',
                'capacity' => 'Capacità',
                'assign_room' => 'Assegna camera',
                'room_assignment' => 'Assegnazione camere',
                'available_spots' => 'posti disponibili',
                'room_full' => 'Camera completa',
                'select_bed_type' => 'Seleziona tipo letto'
            ],
            
            // Etichette prezzi
            'pricing' => [
                'per_person' => 'a persona',
                'per_night' => 'a notte',
                'per_room' => 'a camera',
                'base_price' => 'Prezzo base',
                'final_price' => 'Prezzo finale',
                'total_price' => 'Prezzo totale',
                'night' => 'notte',
                'nights' => 'notti',
                'extra_night' => 'Notte extra',
                'extra_nights' => 'Notti extra',
                'supplement_per_person' => 'Supplemento a persona',
                'supplement_per_night' => 'Supplemento a notte',
                'price_breakdown' => 'Dettaglio prezzi',
                'price_includes' => 'Il prezzo include',
                'price_excludes' => 'Il prezzo non include'
            ],
            
            // Etichette costi extra
            'extras' => [
                'extra_costs' => 'Costi extra',
                'additional_services' => 'Servizi aggiuntivi',
                'insurance' => 'Assicurazione',
                'insurances' => 'Assicurazioni',
                'no_extras_selected' => 'Nessun costo extra selezionato',
                'no_insurance_selected' => 'Nessuna assicurazione selezionata',
                'manage_extras' => 'Gestisci Extra',
                'update_extras' => 'Aggiorna Extra',
                'total_extras' => 'Totale Extra',
                'additions' => 'Aggiunte',
                'reductions' => 'Riduzioni'
            ],
            
            // Etichette form
            'forms' => [
                'fill_form' => 'Compila il form',
                'personal_data' => 'Dati personali',
                'contact_data' => 'Dati di contatto',
                'billing_data' => 'Dati di fatturazione',
                'all_fields_required' => 'Tutti i campi sono obbligatori',
                'check_data' => 'Verifica i dati',
                'confirm_and_continue' => 'Conferma e continua',
                'go_back_and_edit' => 'Torna indietro e modifica',
                'processing' => 'Elaborazione in corso...',
                'please_wait' => 'Attendere prego...'
            ],
            
            // Messaggi di errore
            'errors' => [
                'generic_error' => 'Si è verificato un errore',
                'connection_error' => 'Errore di connessione',
                'validation_error' => 'Errore di validazione',
                'required_field' => 'Campo obbligatorio',
                'invalid_email' => 'Email non valida',
                'invalid_phone' => 'Numero di telefono non valido',
                'invalid_date' => 'Data non valida',
                'invalid_fiscal_code' => 'Codice fiscale non valido',
                'no_availability' => 'Nessuna disponibilità',
                'max_capacity_reached' => 'Capacità massima raggiunta',
                'min_participants' => 'Numero minimo di partecipanti non raggiunto',
                'room_assignment_incomplete' => 'Assegnazione camere non completa'
            ],
            
            // Messaggi di successo
            'success' => [
                'saved_successfully' => 'Salvato con successo',
                'updated_successfully' => 'Aggiornato con successo',
                'deleted_successfully' => 'Eliminato con successo',
                'quote_created' => 'Preventivo creato con successo',
                'booking_confirmed' => 'Prenotazione confermata',
                'email_sent' => 'Email inviata con successo',
                'data_valid' => 'Dati validi'
            ],
            
            // Etichette checkout
            'checkout' => [
                'order_summary' => 'Riepilogo ordine',
                'booking_details' => 'Dettagli prenotazione',
                'payment_method' => 'Metodo di pagamento',
                'terms_conditions' => 'Termini e condizioni',
                'privacy_policy' => 'Privacy policy',
                'accept_terms' => 'Accetto i termini e condizioni',
                'complete_order' => 'Completa ordine',
                'order_total' => 'Totale ordine'
            ]
        ];
        
        // Carica etichette personalizzate salvate
        $custom_labels = get_option('btr_custom_labels', []);
        if (!empty($custom_labels)) {
            $this->labels = array_replace_recursive($this->labels, $custom_labels);
        }
    }
    
    /**
     * Ottieni un'etichetta specifica
     */
    public function get_label($key, $default = '') {
        // Dividi la chiave per navigare nell'array multidimensionale
        $keys = explode('.', $key);
        $value = $this->labels;
        
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default ?: $key;
            }
        }
        
        return is_string($value) ? $value : ($default ?: $key);
    }
    
    /**
     * Filtra i testi del plugin
     */
    public function filter_text($translated, $text, $domain) {
        if ($domain !== 'born-to-ride-booking') {
            return $translated;
        }
        
        // Mappa di sostituzione veloce per testi comuni
        $quick_map = [
            'Adulti' => $this->get_label('participants.adults'),
            'Bambini' => $this->get_label('participants.children'),
            'Neonati' => $this->get_label('participants.infants'),
            'Camera' => $this->get_label('rooms.room'),
            'Camere' => $this->get_label('rooms.rooms'),
            'Totale' => $this->get_label('general.total'),
            'Supplemento' => $this->get_label('general.supplement'),
            'Notte extra' => $this->get_label('pricing.extra_night'),
            'Notti extra' => $this->get_label('pricing.extra_nights')
        ];
        
        if (isset($quick_map[$text])) {
            return $quick_map[$text];
        }
        
        return $translated;
    }
    
    /**
     * Filtra i testi plurali
     */
    public function filter_plural_text($translated, $single, $plural, $number, $domain) {
        if ($domain !== 'born-to-ride-booking') {
            return $translated;
        }
        
        // Gestione plurali personalizzati
        if ($single === '%d notte' && $plural === '%d notti') {
            $label = $number === 1 ? $this->get_label('pricing.night') : $this->get_label('pricing.nights');
            return sprintf($number . ' ' . $label, $number);
        }
        
        return $translated;
    }
    
    /**
     * Aggiungi menu admin
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=preventivo',
            'Etichette e Testi',
            'Etichette e Testi',
            'manage_options',
            'btr-labels',
            [$this, 'admin_page']
        );
    }
    
    /**
     * Pagina admin per gestione etichette
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Gestione Etichette e Testi', 'born-to-ride-booking'); ?></h1>
            
            <div class="notice notice-info">
                <p><?php esc_html_e('Personalizza tutte le etichette e i testi del plugin. Le modifiche saranno applicate immediatamente.', 'born-to-ride-booking'); ?></p>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields('btr_labels_settings'); ?>
                
                <div class="btr-labels-tabs">
                    <h2 class="nav-tab-wrapper">
                        <?php foreach ($this->labels as $section => $labels): ?>
                            <a href="#<?php echo esc_attr($section); ?>" class="nav-tab">
                                <?php echo esc_html(ucfirst(str_replace('_', ' ', $section))); ?>
                            </a>
                        <?php endforeach; ?>
                    </h2>
                    
                    <?php foreach ($this->labels as $section => $labels): ?>
                        <div id="<?php echo esc_attr($section); ?>" class="btr-labels-section">
                            <h3><?php echo esc_html(ucfirst(str_replace('_', ' ', $section))); ?></h3>
                            
                            <table class="form-table">
                                <?php foreach ($labels as $key => $value): ?>
                                    <tr>
                                        <th scope="row">
                                            <label for="btr_labels_<?php echo esc_attr($section . '_' . $key); ?>">
                                                <?php echo esc_html(ucfirst(str_replace('_', ' ', $key))); ?>
                                            </label>
                                        </th>
                                        <td>
                                            <input type="text" 
                                                   id="btr_labels_<?php echo esc_attr($section . '_' . $key); ?>"
                                                   name="btr_custom_labels[<?php echo esc_attr($section); ?>][<?php echo esc_attr($key); ?>]"
                                                   value="<?php echo esc_attr($value); ?>"
                                                   class="regular-text">
                                            <p class="description">
                                                Default: <?php echo esc_html($this->get_default_label($section . '.' . $key)); ?>
                                            </p>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php submit_button(); ?>
            </form>
            
            <hr>
            
            <h3><?php esc_html_e('Import/Export', 'born-to-ride-booking'); ?></h3>
            
            <p>
                <button class="button" id="btr-export-labels">
                    <?php esc_html_e('Esporta etichette', 'born-to-ride-booking'); ?>
                </button>
                
                <button class="button" id="btr-import-labels">
                    <?php esc_html_e('Importa etichette', 'born-to-ride-booking'); ?>
                </button>
            </p>
            
            <style>
                .btr-labels-section { display: none; }
                .btr-labels-section:first-of-type { display: block; }
                .nav-tab-active { background: #fff; border-bottom: 1px solid #fff; }
            </style>
            
            <script>
            jQuery(function($) {
                // Tab navigation
                $('.nav-tab').on('click', function(e) {
                    e.preventDefault();
                    var target = $(this).attr('href');
                    
                    $('.nav-tab').removeClass('nav-tab-active');
                    $(this).addClass('nav-tab-active');
                    
                    $('.btr-labels-section').hide();
                    $(target).show();
                });
                
                $('.nav-tab:first').addClass('nav-tab-active');
                
                // Export
                $('#btr-export-labels').on('click', function() {
                    $.post(ajaxurl, {
                        action: 'btr_export_labels',
                        nonce: '<?php echo wp_create_nonce('btr_labels'); ?>'
                    }, function(response) {
                        if (response.success) {
                            var blob = new Blob([JSON.stringify(response.data, null, 2)], {type: 'application/json'});
                            var url = URL.createObjectURL(blob);
                            var a = document.createElement('a');
                            a.href = url;
                            a.download = 'btr-labels-' + new Date().toISOString().split('T')[0] + '.json';
                            a.click();
                        }
                    });
                });
                
                // Import
                $('#btr-import-labels').on('click', function() {
                    var input = $('<input type="file" accept=".json">');
                    input.on('change', function(e) {
                        var file = e.target.files[0];
                        if (file) {
                            var reader = new FileReader();
                            reader.onload = function(e) {
                                try {
                                    var labels = JSON.parse(e.target.result);
                                    
                                    $.post(ajaxurl, {
                                        action: 'btr_import_labels',
                                        nonce: '<?php echo wp_create_nonce('btr_labels'); ?>',
                                        labels: labels
                                    }, function(response) {
                                        if (response.success) {
                                            alert('Etichette importate con successo!');
                                            location.reload();
                                        }
                                    });
                                } catch (err) {
                                    alert('File non valido');
                                }
                            };
                            reader.readAsText(file);
                        }
                    });
                    input.click();
                });
            });
            </script>
        </div>
        <?php
    }
    
    /**
     * Ottieni etichetta di default
     */
    private function get_default_label($key) {
        // Implementazione per ottenere l'etichetta di default dal file di lingua
        return $key;
    }
    
    /**
     * Registra impostazioni
     */
    public function register_settings() {
        register_setting('btr_labels_settings', 'btr_custom_labels');
    }
    
    /**
     * AJAX export etichette
     */
    public function ajax_export_labels() {
        check_ajax_referer('btr_labels', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error();
        }
        
        wp_send_json_success($this->labels);
    }
    
    /**
     * AJAX import etichette
     */
    public function ajax_import_labels() {
        check_ajax_referer('btr_labels', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error();
        }
        
        $labels = $_POST['labels'] ?? [];
        
        if (!empty($labels) && is_array($labels)) {
            update_option('btr_custom_labels', $labels);
            wp_send_json_success();
        }
        
        wp_send_json_error();
    }
}

// Funzione helper globale
if (!function_exists('btr_label')) {
    function btr_label($key, $default = '') {
        return BTR_Labels_Revision::get_instance()->get_label($key, $default);
    }
}

// Inizializza
add_action('init', function() {
    BTR_Labels_Revision::get_instance();
});