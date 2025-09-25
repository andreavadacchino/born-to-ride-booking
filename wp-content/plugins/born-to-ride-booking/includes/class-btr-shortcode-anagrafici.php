<?php
if (!defined('ABSPATH')) {
    exit; // Impedisce l'accesso diretto al file
}

class BTR_Anagrafici_Shortcode
{
    // Definizione delle costanti per i nonces e gli hook AJAX
    private const NONCE_ACTION_UPDATE = 'btr_update_anagrafici_nonce';
    private const NONCE_FIELD_UPDATE = 'btr_update_anagrafici_nonce_field';
    private const SHORTCODE_TAG = 'btr_inserisci_anagrafici';
    private const AJAX_ACTION_SAVE = 'btr_save_anagrafici';

    public function __construct()
    {
        // Registrazione dello shortcode
        add_shortcode(self::SHORTCODE_TAG, [$this, 'render_anagrafici_form']);

        // Enqueue di scripts e stili
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

        // Registrazione delle azioni AJAX
        add_action('wp_ajax_' . self::AJAX_ACTION_SAVE, [$this, 'save_anagrafici']);
        add_action('wp_ajax_nopriv_' . self::AJAX_ACTION_SAVE, [$this, 'save_anagrafici']);
        add_action('wp_ajax_btr_toggle_assicurazione_cart', [$this, 'btr_toggle_assicurazione_cart']);
        add_action('wp_ajax_nopriv_btr_toggle_assicurazione_cart', [$this, 'btr_toggle_assicurazione_cart']);
        
        // AJAX per multi-step form
        add_action('wp_ajax_btr_save_anagrafici_temp', [$this, 'save_anagrafici_temp']);
        add_action('wp_ajax_nopriv_btr_save_anagrafici_temp', [$this, 'save_anagrafici_temp']);
        add_action('wp_ajax_btr_get_payment_data', [$this, 'get_payment_data']);
        add_action('wp_ajax_nopriv_btr_get_payment_data', [$this, 'get_payment_data']);

        // Aggiunta di alert e pulsanti nel riepilogo ordine
        //add_action('woocommerce_thankyou', [$this, 'maybe_display_anagrafici_alert'], 20);
        //add_action('woocommerce_order_details_after_order_table', [$this, 'maybe_display_anagrafici_alert'], 20);

        // Aggiunta di alert e pulsanti nelle email di WooCommerce
        //add_action('woocommerce_email_after_order_table', [$this, 'maybe_add_email_anagrafici_alert'], 20, 4);

        // Registrazione del metabox nella schermata degli ordini
        add_action('add_meta_boxes', [$this, 'add_order_anagrafici_metabox']);

        // Aggiungi preventivo_id al checkout
        add_filter('woocommerce_checkout_fields', [$this, 'add_hidden_preventivo_id_field']);

        // Copia i dati anagrafici dal preventivo all'ordine durante la creazione dell'ordine
        add_action('woocommerce_checkout_create_order', [$this, 'copy_anagrafici_from_preventivo'], 10, 2);
        add_action('woocommerce_checkout_create_order', [$this, 'btr_save_order_meta'], 10, 2);

        // Mostra preventivo_id nell'admin order data
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'display_preventivo_id_in_admin'], 10, 1);
    }

    /**
     * Enqueue di scripts e stili necessari
     */
    public function enqueue_scripts()
    {
        // Enqueue solo nelle pagine necessarie
        if (is_page() || is_checkout() || is_order_received_page()) {
            // Script JavaScript per gestire il modulo AJAX
            wp_enqueue_script(
                'btr-anagrafici-js',
                plugin_dir_url(__FILE__) . '../assets/js/anagrafici-scripts.js',
                ['jquery'],
                BTR_VERSION,
                true
            );
            
            // Script per multi-step form - DISABILITATO: la selezione pagamento avviene nella pagina successiva
            // wp_enqueue_script(
            //     'btr-anagrafici-payment-steps',
            //     plugin_dir_url(__FILE__) . '../assets/js/anagrafici-payment-steps.js',
            //     ['jquery'],
            //     BTR_VERSION,
            //     true
            // );

            // Stili CSS per il modulo
            // wp_enqueue_style('btr-anagrafici-css', plugin_dir_url(__FILE__) . '../assets/css/anagrafici-styles.css', [], BTR_VERSION);
            
            // Stili per multi-step form - DISABILITATO: la selezione pagamento avviene nella pagina successiva
            // wp_enqueue_style(
            //     'btr-anagrafici-payment-steps',
            //     plugin_dir_url(__FILE__) . '../assets/css/anagrafici-payment-steps.css',
            //     [],
            //     BTR_VERSION
            // );

            // Localizzazione dello script con dati AJAX e altri dati dinamici
            wp_localize_script('btr-anagrafici-js', 'btr_anagrafici', [
                'ajax_url'         => admin_url('admin-ajax.php'),
                'nonce'            => wp_create_nonce(self::NONCE_ACTION_UPDATE),
                'error_message'    => __('Errore durante il salvataggio dei dati anagrafici.', 'born-to-ride-booking'),
                'success_message'  => __('Dati anagrafici salvati con successo.', 'born-to-ride-booking'),
                'redirect_message' => __('I tuoi dati anagrafici sono stati salvati. Grazie!', 'born-to-ride-booking'),
                'checkout_url'     => wc_get_checkout_url(),
                'tempo_scaduto'    => __('Tempo scaduto!', 'born-to-ride-booking'),
            ]);
        }
    }

    /**
     * Aggiungi un campo nascosto preventivo_id al checkout
     */
    public function add_hidden_preventivo_id_field($fields)
    {
        // Recupera preventivo_id dalla query string
        $preventivo_id = isset($_GET['preventivo_id']) ? intval($_GET['preventivo_id']) : 0;

        // Aggiungi il campo nascosto
        $fields['billing']['preventivo_id'] = [
            'type'     => 'hidden',
            'default'  => $preventivo_id,
            'class'    => ['hidden'],
            'priority' => 999, // Posizione
        ];

        return $fields;
    }

    function btr_save_order_meta($order, $data) {
        $preventivo_id = WC()->session->get('_preventivo_id');

        if ($preventivo_id) {
            $order->update_meta_data('_preventivo_id', $preventivo_id);
            error_log("Preventivo ID {$preventivo_id} salvato nell'ordine {$order->get_id()}.");
        } else {
            error_log("Preventivo ID non trovato per l'ordine {$order->get_id()}.");
        }
    }

    /**
     * Copia i dati anagrafici dal preventivo all'ordine durante la creazione dell'ordine
     */
    public function copy_anagrafici_from_preventivo($order, $data)
    {
        // Recupera il preventivo_id dal checkout data
        // FIX CRITICO: Multi-level fallback per recuperare preventivo_id
        $preventivo_id = isset($data['preventivo_id']) ? intval($data['preventivo_id']) : 0;
        
        // FALLBACK 1: Cerca in WooCommerce session
        if (!$preventivo_id && WC()->session) {
            $preventivo_id = WC()->session->get('_preventivo_id');
            if ($preventivo_id) {
                error_log('RECOVERY: Preventivo ID recuperato da WC session: ' . $preventivo_id);
            }
        }
        
        // FALLBACK 2: Cerca in transient backup (se implementato)
        if (!$preventivo_id && WC()->session) {
            $session_id = WC()->session->get_customer_id();
            if ($session_id) {
                $preventivo_id = get_transient('btr_preventivo_backup_' . $session_id);
                if ($preventivo_id) {
                    error_log('RECOVERY: Preventivo ID recuperato da transient backup: ' . $preventivo_id);
                }
            }
        }
        
        // FALLBACK 3: Cerca negli item del carrello
        if (!$preventivo_id && isset($data['line_items'])) {
            foreach ($data['line_items'] as $item) {
                if (isset($item['preventivo_id'])) {
                    $preventivo_id = intval($item['preventivo_id']);
                    error_log('RECOVERY: Preventivo ID recuperato da line items: ' . $preventivo_id);
                    break;
                }
            }
        }

        error_log('copy_anagrafici_from_preventivo: Final Preventivo ID: ' . $preventivo_id);
        
        // VALIDATION: Verifica che il preventivo esista davvero
        if ($preventivo_id && !get_post($preventivo_id)) {
            error_log('ERROR: Preventivo ID ' . $preventivo_id . ' non esiste nel database!');
            $preventivo_id = 0;
        }

        if ($preventivo_id) {
            // Recupera i dati anagrafici dal preventivo
            $anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);

            error_log('copy_anagrafici_from_preventivo: Dati anagrafici recuperati: ' . print_r($anagrafici, true));

            if (!empty($anagrafici)) {
                // Salva i dati anagrafici sull'ordine
                $order->update_meta_data('_anagrafici', $anagrafici);

                // Salva il preventivo_id nell'ordine
                $order->update_meta_data('_preventivo_id', $preventivo_id);

                // Salva l'ordine
                $order->save();

                error_log('copy_anagrafici_from_preventivo: Dati anagrafici salvati sull\'ordine ID: ' . $order->get_id());
            } else {
                error_log('copy_anagrafici_from_preventivo: Nessun dato anagrafico trovato per preventivo ID: ' . $preventivo_id);
            }
        } else {
            error_log('copy_anagrafici_from_preventivo: Preventivo ID non valido.');
        }
    }

    /**
     * Mostra preventivo_id nell'admin order data
     */
    public function display_preventivo_id_in_admin($order)
    {
        $preventivo_id = $order->get_meta('_preventivo_id');

        if ($preventivo_id) {
            echo '<p><strong>' . __('Preventivo ID:', 'born-to-ride-booking') . '</strong> ' . esc_html($preventivo_id) . '</p>';
        }
    }

    /**
     * Renderizza il modulo per i dati anagrafici
     */
    public function render_anagrafici_formX($atts)
    {
        $atts = shortcode_atts(['order_id' => 0, 'preventivo_id' => 0], $atts, self::SHORTCODE_TAG);
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : intval($atts['order_id']);
        $preventivo_id = isset($_GET['preventivo_id']) ? intval($_GET['preventivo_id']) : intval($atts['preventivo_id']);

        // Se abbiamo un order_id, recuperiamo il preventivo_id se non presente
        if ($order_id) {
            $order = wc_get_order($order_id);
            if (!$order) {
                return '<p>' . esc_html__('Ordine non trovato.', 'born-to-ride-booking') . '</p>';
            }
            $preventivo_id_meta = intval($order->get_meta('_preventivo_id'));
            if (!$preventivo_id_meta) {
                $preventivo_id_session = WC()->session->get('_preventivo_id');
                if ($preventivo_id_session) {
                    $preventivo_id = $preventivo_id_session;
                    $order->update_meta_data('_preventivo_id', $preventivo_id);
                    $order->save();
                }
            } else {
                $preventivo_id = $preventivo_id_meta;
            }
        } else {
            // Nessun ordine, tentiamo dalla sessione se non abbiamo il preventivo_id
            if (!$preventivo_id) {
                $preventivo_id = WC()->session->get('_preventivo_id');
            }
        }

        // Verifica che abbiamo almeno un preventivo_id
        if (!$preventivo_id) {
            return '<p>' . esc_html__('ID preventivo non trovato o non valido.', 'born-to-ride-booking') . '</p>';
        }

        // Carichiamo i dati anagrafici
        $anagrafici = [];
        if ($order_id > 0) {
            // Priorità all'ordine
            //$anagrafici = get_post_meta($order_id, '_anagrafici', true);
            $anagrafici = $order->get_meta('_anagrafici', true);
            if (!is_array($anagrafici)) {
                $anagrafici = [];
            }

            // Se nell'ordine non ci sono dati, proviamo dal preventivo
            if (empty($anagrafici)) {
                $anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
                if (!is_array($anagrafici)) {
                    $anagrafici = [];
                }
            }
        } else {
            // Nessun ordine, lavoriamo solo con il preventivo
            $anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
            if (!is_array($anagrafici)) {
                $anagrafici = [];
            }
        }

        // Calcolo del totale persone
        if ($order_id > 0) {
            $totale_persone = $this->get_totale_persone_from_order($order_id);
        } else {
            $totale_persone = $this->get_totale_persone_from_preventivo($preventivo_id);
        }

        // Fallback se tot_persone è 0 ma abbiamo dati
        if ($totale_persone === 0 && !empty($anagrafici)) {
            $totale_persone = count($anagrafici);
        }

        // Completa i dati mancanti
        for ($i = count($anagrafici); $i < $totale_persone; $i++) {
            $anagrafici[] = ['nome' => '', 'cognome' => '', 'eta' => '', 'documento' => ''];
        }

        // Countdown se c'è un ordine
        $remaining_time = 0;
        if ($order_id > 0) {
            $order = wc_get_order($order_id);
            if ($order && $order->get_date_created()) {
                $order_date = $order->get_date_created();
                $deadline = $order_date->getTimestamp() + (24 * 60 * 60);
                $current_time = current_time('timestamp');
                $remaining_time = max(0, $deadline - $current_time);
            }
        }

        ob_start();
        ?>


        <?php if ($remaining_time > 0): ?>
        <div id="btr-countdown" style="font-size: 16px; color: red; margin-bottom: 20px;" data-remaining-time="<?php echo esc_attr($remaining_time); ?>">
            <?php esc_html_e('Tempo rimanente per completare i dati:', 'born-to-ride-booking'); ?>
            <span id="btr-countdown-timer"></span>
        </div>
    <?php endif; ?>

        <form id="btr-anagrafici-form" <?php if ($remaining_time > 0) { echo 'data-remaining-time="' . esc_attr($remaining_time) . '"'; } ?>>
            <?php wp_nonce_field(self::NONCE_ACTION_UPDATE, self::NONCE_FIELD_UPDATE); ?>
            <input type="hidden" name="action" value="btr_save_anagrafici">
            <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>">
            <input type="hidden" name="preventivo_id" value="<?php echo esc_attr($preventivo_id); ?>">

            <h2><?php esc_html_e('Inserisci i Dati Anagrafici', 'born-to-ride-booking'); ?></h2>
            <p><?php printf(__('Totale Persone: %d', 'born-to-ride-booking'), $totale_persone); ?></p>

            <div id="btr-anagrafici-container">
                <?php foreach ($anagrafici as $index => $persona):
                    $nome = $persona['nome'] ?? '';
                    $cognome = $persona['cognome'] ?? '';
                    $eta = $persona['eta'] ?? '';
                    $documento = $persona['documento'] ?? '';
                    ?>
                    <div class="btr-anagrafici-person">
                        <h4><?php printf(__('Persona %d', 'born-to-ride-booking'), $index + 1); ?></h4>
                        <label for="btr_nome_<?php echo $index; ?>"><?php esc_html_e('Nome', 'born-to-ride-booking'); ?></label>
                        <input type="text" id="btr_nome_<?php echo $index; ?>" name="anagrafici[<?php echo $index; ?>][nome]" value="<?php echo esc_attr($nome); ?>">

                        <label for="btr_cognome_<?php echo $index; ?>"><?php esc_html_e('Cognome', 'born-to-ride-booking'); ?></label>
                        <input type="text" id="btr_cognome_<?php echo $index; ?>" name="anagrafici[<?php echo $index; ?>][cognome]" value="<?php echo esc_attr($cognome); ?>">

                        <label for="btr_eta_<?php echo $index; ?>"><?php esc_html_e('Età', 'born-to-ride-booking'); ?></label>
                        <input type="number" id="btr_eta_<?php echo $index; ?>" name="anagrafici[<?php echo $index; ?>][eta]" min="0" value="<?php echo esc_attr($eta); ?>">

                        <label for="btr_documento_<?php echo $index; ?>"><?php esc_html_e('Documento di Identità', 'born-to-ride-booking'); ?></label>
                        <input type="text" id="btr_documento_<?php echo $index; ?>" name="anagrafici[<?php echo $index; ?>][documento]" value="<?php echo esc_attr($documento); ?>">
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="button">
                <?php echo $order_id ? esc_html__('Salva e Completa', 'born-to-ride-booking') : esc_html__('Salva e Vai al Checkout', 'born-to-ride-booking'); ?>
            </button>
        </form>

        <div id="btr-anagrafici-response"></div>
        <?php
        return ob_get_clean();
    }





    private function get_camere_acquistate($id, $is_order = true)
    {
        $camere = [];

        if ($is_order) {
            // Recupera l'ordine WooCommerce
            $order = wc_get_order($id);

            if ($order) {
                foreach ($order->get_items() as $item) {
                    $room_type = $item->get_meta('pa_tipologia_camere', true);
                    $date_range = $item->get_meta('pa_date_disponibili', true);
                    $supplemento = $item->get_meta('_btr_supplemento', true);
                    $sconto_percentuale = $item->get_meta('_btr_sconto_percentuale', true);
                    $quantita = $item->get_quantity();
                    $room_types = strtolower($room_type);
                    $capacity = self::determine_number_of_persons($room_types);

                    if ($room_type) {
                        $camere[] = [
                            'tipo' => $room_type,
                            'quantita' => $quantita,
                            'supplemento' => $supplemento,
                            'sconto' => $sconto_percentuale,
                            'date' => $date_range,
                            'capacita' => $capacity,
                        ];
                    }
                }
            }
        } else {
            // Recupera le camere dal preventivo
            $camere_selezionate = get_post_meta($id, '_camere_selezionate', true);

            if (is_array($camere_selezionate)) {
                foreach ($camere_selezionate as $camera) {
                    $room_type = isset($camera['tipo']) ? $camera['tipo'] : '';
                    $quantita = isset($camera['quantita']) ? intval($camera['quantita']) : 0;
                    $supplemento = isset($camera['supplemento']) ? $camera['supplemento'] : '';
                    $sconto = isset($camera['sconto']) ? $camera['sconto'] : '';

                    // Determina la capacità della camera
                    $capacity = self::determine_number_of_persons(strtolower($room_type));

                    $camere[] = [
                        'tipo' => $room_type,
                        'quantita' => $quantita,
                        'supplemento' => $supplemento,
                        'sconto' => $sconto,
                        'capacita' => $capacity,
                    ];
                }
            }
        }

        return $camere;
    }



    /**
     * Renderizza il modulo per i dati anagrafici
     * 
     * @param array $atts Attributi dello shortcode
     * @return string HTML del modulo
     */
    public function render_anagrafici_form($atts)
    {
        // Sanitizza gli attributi dello shortcode
        $atts = shortcode_atts(['order_id' => 0, 'preventivo_id' => 0], $atts, self::SHORTCODE_TAG);

        // Recupera e sanitizza gli ID dall'URL o dagli attributi
        $order_id = isset($_GET['order_id']) ? absint($_GET['order_id']) : absint($atts['order_id']);
        $preventivo_id = isset($_GET['preventivo_id']) ? absint($_GET['preventivo_id']) : absint($atts['preventivo_id']);

        // NUOVO: Pulisci carrello e sessione quando si arriva alla pagina anagrafici
        // Solo se non c'è un order_id (quindi non stiamo modificando un ordine esistente)
        if (!$order_id && $preventivo_id && WC()->cart && WC()->session) {
            // Svuota il carrello
            WC()->cart->empty_cart();
            
            // Rimuovi le fee BTR dalla sessione
            WC()->session->__unset('btr_cart_fees');
            
            // Rimuovi tutte le fees dal carrello
            if (method_exists(WC()->cart->fees_api(), 'remove_all_fees')) {
                WC()->cart->fees_api()->remove_all_fees();
            }
            
            // Log per debug
            error_log('BTR: Carrello e sessione puliti per preventivo #' . $preventivo_id);
        }

        // Inizializza le variabili che verranno passate al template
        $anagrafici = [];
        $totale_persone = 0;
        $remaining_time = 0;
        $camere_acquistate = [];
        $error_message = '';

        // Recupera l'ordine se esiste
        $order = null;
        if ($order_id > 0) {
            $order = wc_get_order($order_id);
            if (!$order) {
                return '<div class="btr-error-message">' . 
                    esc_html__('Ordine non trovato. Verifica il link o contatta l\'assistenza.', 'born-to-ride-booking') . 
                '</div>';
            }

            // Recupera il preventivo_id dall'ordine se non specificato
            $preventivo_id_meta = absint($order->get_meta('_preventivo_id'));
            if ($preventivo_id_meta > 0) {
                $preventivo_id = $preventivo_id_meta;
            } elseif (!$preventivo_id) {
                // Prova a recuperare dalla sessione
                $preventivo_id_session = WC()->session ? absint(WC()->session->get('_preventivo_id')) : 0;
                if ($preventivo_id_session > 0) {
                    $preventivo_id = $preventivo_id_session;
                    // Salva il preventivo_id nell'ordine
                    $order->update_meta_data('_preventivo_id', $preventivo_id);
                    $order->save();
                }
            }
        } elseif (!$preventivo_id && WC()->session) {
            // Nessun ordine, tenta di recuperare il preventivo_id dalla sessione
            $preventivo_id = absint(WC()->session->get('_preventivo_id'));
        }

        // Verifica che abbiamo un preventivo_id valido
        if (!$preventivo_id) {
            return '<div class="btr-error-message">' . 
                esc_html__('ID preventivo non trovato o non valido. Verifica il link o contatta l\'assistenza.', 'born-to-ride-booking') . 
            '</div>';
        }

        // Verifica che il preventivo esista
        $preventivo = get_post($preventivo_id);
        if (!$preventivo || $preventivo->post_type !== 'btr_preventivi') {
            return '<div class="btr-error-message">' . 
                esc_html__('Preventivo non trovato o non valido. Verifica il link o contatta l\'assistenza.', 'born-to-ride-booking') . 
            '</div>';
        }

        // Carica i dati anagrafici
        try {
            if ($order_id > 0 && $order) {
                // Prima prova a caricare dall'ordine
                $anagrafici = $order->get_meta('_anagrafici', true);
                if (!is_array($anagrafici) || empty($anagrafici)) {
                    // Se non ci sono dati nell'ordine, prova dal preventivo
                    $anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
                }
            } else {
                // Carica dal preventivo
                $anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
            }

            // Assicurati che $anagrafici sia un array
            if (!is_array($anagrafici)) {
                $anagrafici = [];
            }

            // Recupera le camere acquistate
            $camere_acquistate = $this->get_camere_acquistate(
                $order_id > 0 ? $order_id : $preventivo_id,
                $order_id > 0
            );

            // Calcolo del totale persone
            if ($order_id > 0) {
                $totale_persone = $this->get_totale_persone_from_order($order_id);
            } else {
                $totale_persone = $this->get_totale_persone_from_preventivo($preventivo_id);
            }

            // Fallback se totale_persone è 0 ma abbiamo dati
            if ($totale_persone === 0 && !empty($anagrafici)) {
                $totale_persone = count($anagrafici);
            }

            // Completa i dati mancanti
            for ($i = count($anagrafici); $i < $totale_persone; $i++) {
                $anagrafici[] = [
                    'nome'                => '',
                    'cognome'             => '',
                    'data_nascita'        => '',
                    'citta_nascita'       => '',
                    'citta_residenza'     => '',
                    'provincia_residenza' => '',
                    'email'               => '',
                    'telefono'            => '',
                    'rc_skipass'          => false,
                    'ass_annullamento'    => false,
                    'ass_bagaglio'        => false,
                ];
            }

            // Calcola il tempo rimanente per il countdown
            if ($order_id > 0 && $order && $order->get_date_created()) {
                $order_date = $order->get_date_created();
                $deadline = $order_date->getTimestamp() + (24 * 60 * 60); // 24 ore
                $current_time = current_time('timestamp');
                $remaining_time = max(0, $deadline - $current_time);
            }
        } catch (Exception $e) {
            error_log('Errore nel recupero dei dati anagrafici: ' . $e->getMessage());
            $error_message = esc_html__('Si è verificato un errore nel recupero dei dati. Ricarica la pagina o contatta l\'assistenza.', 'born-to-ride-booking');
        }

        // Carica il template
        $template_path = plugin_dir_path(__FILE__) . '../templates/admin/btr-form-anagrafici.php';
        if (!file_exists($template_path)) {
            return '<div class="btr-error-message">' . 
                esc_html__('Template del form non trovato. Contatta l\'amministratore del sito.', 'born-to-ride-booking') . 
            '</div>';
        }

        // Passa le variabili al template
        $template_vars = [
            'order_id' => $order_id,
            'preventivo_id' => $preventivo_id,
            'anagrafici' => $anagrafici,
            'totale_persone' => $totale_persone,
            'remaining_time' => $remaining_time,
            'camere_acquistate' => $camere_acquistate,
            'error_message' => $error_message,
        ];

        // Estrai le variabili per renderle disponibili nel template
        extract($template_vars);

        ob_start();
        include $template_path;
        return ob_get_clean();
    }



    /**
     * Salva i dati anagrafici via AJAX, includendo la gestione delle camere assegnate.
     * 
     * @return void
     */
    public function save_anagrafici() {
        try {
            // Verifica il nonce per la sicurezza
            if (!isset($_POST[self::NONCE_FIELD_UPDATE]) || !wp_verify_nonce($_POST[self::NONCE_FIELD_UPDATE], self::NONCE_ACTION_UPDATE)) {
                wp_send_json_error([
                    'message' => __('Errore di sicurezza. Ricarica la pagina e riprova.', 'born-to-ride-booking'),
                    'code' => 'invalid_nonce'
                ]);
                return;
            }

            // Recupera e sanitizza gli ID
            $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
            $preventivo_id = isset($_POST['preventivo_id']) ? absint($_POST['preventivo_id']) : 0;

            // Verifica che almeno uno degli ID sia valido
            if (!$order_id && !$preventivo_id) {
                wp_send_json_error([
                    'message' => __('Nessun ID ordine o preventivo fornito.', 'born-to-ride-booking'),
                    'code' => 'missing_ids'
                ]);
                return;
            }

            // Recupera i dati anagrafici
            $anagrafici = isset($_POST['anagrafici']) ? $_POST['anagrafici'] : [];

            // Gestisci il caso in cui i dati siano inviati come JSON
            if (!is_array($anagrafici) && is_string($anagrafici)) {
                $anagrafici = json_decode(stripslashes($anagrafici), true);
            }

            // Verifica che i dati siano validi
            if (empty($anagrafici) || !is_array($anagrafici)) {
                wp_send_json_error([
                    'message' => __('Dati anagrafici vuoti o non validi.', 'born-to-ride-booking'),
                    'code' => 'invalid_data'
                ]);
                return;
            }

            // Recupera configurazione assicurazioni dal pacchetto
            $package_id = get_post_meta($preventivo_id, '_pacchetto_id', true);
            $assicurazioni_config = get_post_meta($package_id, 'btr_assicurazione_importi', true);
            if (!is_array($assicurazioni_config)) {
                $assicurazioni_config = [];
            }

            // Recupera configurazione costi extra dal pacchetto
            $extra_costs_config_raw = get_post_meta($package_id, 'btr_costi_extra', true);
            if (is_string($extra_costs_config_raw)) {
                $maybe_unserialized = maybe_unserialize($extra_costs_config_raw);
                if (false !== $maybe_unserialized) {
                    $extra_costs_config_raw = $maybe_unserialized;
                }
            }
            $extra_costs_config = is_array($extra_costs_config_raw) ? $extra_costs_config_raw : [];
            $extra_costs_config_map = [];

            foreach ($extra_costs_config as $config_item) {
                if (!is_array($config_item)) {
                    continue;
                }

                $slug = sanitize_key($config_item['slug'] ?? sanitize_title($config_item['nome'] ?? ''));
                if (empty($slug)) {
                    continue;
                }

                $extra_costs_config_map[$slug] = [
                    'nome' => sanitize_text_field($config_item['nome'] ?? $slug),
                    'descrizione' => sanitize_text_field($config_item['tooltip_text'] ?? ($config_item['descrizione'] ?? '')),
                    'importo' => floatval($config_item['importo'] ?? 0),
                    'moltiplica_persone' => !empty($config_item['moltiplica_persone']) && $config_item['moltiplica_persone'] !== '0',
                    'moltiplica_durata' => !empty($config_item['moltiplica_durata']) && $config_item['moltiplica_durata'] !== '0',
                ];
            }

            // Prezzi costi extra inviati dal frontend
            $extra_costs_prices = [];
            if (isset($_POST['extra_costs_prices']) && is_array($_POST['extra_costs_prices'])) {
                foreach ($_POST['extra_costs_prices'] as $slug => $price) {
                    $extra_costs_prices[sanitize_key($slug)] = floatval($price);
                }
            }

            // Costi extra per durata (JSON dal frontend)
            $costi_extra_durata_raw = [];
            if (!empty($_POST['costi_extra_durata'])) {
                $durata_payload = wp_unslash($_POST['costi_extra_durata']);
                if (is_string($durata_payload)) {
                    $decoded = json_decode($durata_payload, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $costi_extra_durata_raw = $decoded;
                    } else {
                        error_log('[BTR WARNING] Impossibile decodificare costi_extra_durata: ' . json_last_error_msg());
                    }
                }
            }

            // Sanitizza i dati e include il salvataggio delle assicurazioni dettagliate
            $sanitized_anagrafici = array_map(function ($persona) use ($assicurazioni_config, $extra_costs_config_map, $extra_costs_prices) {
                // Sanitizza i valori booleani
                $rc_skipass_val = isset($persona['rc_skipass']) && ($persona['rc_skipass'] === '1' || $persona['rc_skipass'] === true || $persona['rc_skipass'] === 'true') ? '1' : '0';
                $annullamento_val = isset($persona['ass_annullamento']) && ($persona['ass_annullamento'] === '1' || $persona['ass_annullamento'] === true || $persona['ass_annullamento'] === 'true') ? '1' : '0';
                $medico_bagaglio_val = isset($persona['ass_bagaglio']) && ($persona['ass_bagaglio'] === '1' || $persona['ass_bagaglio'] === true || $persona['ass_bagaglio'] === 'true') ? '1' : '0';

                // Crea array sanitizzato con tutti i campi
                $sanitized = [
                    'nome' => sanitize_text_field($persona['nome'] ?? ''),
                    'cognome' => sanitize_text_field($persona['cognome'] ?? ''),
                    'data_nascita' => sanitize_text_field($persona['data_nascita'] ?? ''),
                    'email' => sanitize_email($persona['email'] ?? ''),
                    'telefono' => sanitize_text_field($persona['telefono'] ?? ''),
                    'citta_nascita' => sanitize_text_field($persona['citta_nascita'] ?? ''),
                    'citta_residenza' => sanitize_text_field($persona['citta_residenza'] ?? ''),
                    'provincia_residenza' => sanitize_text_field($persona['provincia_residenza'] ?? ''),
                    'indirizzo_residenza' => sanitize_text_field($persona['indirizzo_residenza'] ?? ''),
                    'numero_civico' => sanitize_text_field($persona['numero_civico'] ?? ''),
                    'cap_residenza' => sanitize_text_field($persona['cap_residenza'] ?? ''),
                    'codice_fiscale' => sanitize_text_field($persona['codice_fiscale'] ?? ''),
                    'camera' => sanitize_text_field($persona['camera'] ?? ''),
                    'camera_tipo' => sanitize_text_field($persona['camera_tipo'] ?? ''),
                    'tipo_letto' => sanitize_text_field($persona['tipo_letto'] ?? ''),
                    'tipo_persona' => sanitize_text_field($persona['tipo_persona'] ?? ''),
                    'rc_skipass' => $rc_skipass_val,
                    'ass_annullamento' => $annullamento_val,
                    'ass_bagaglio' => $medico_bagaglio_val,
                    'fascia' => sanitize_text_field($persona['fascia'] ?? ''),
                ];

                // Gestisci le assicurazioni se presenti
                if (!empty($persona['assicurazioni']) && is_array($persona['assicurazioni'])) {
                    $sanitized['assicurazioni'] = [];
                    $sanitized['assicurazioni_dettagliate'] = [];

                    foreach ($persona['assicurazioni'] as $slug => $val) {
                        $clean_slug = sanitize_key($slug);
                        if ('' === $clean_slug) {
                            continue;
                        }

                        $selected = in_array($val, ['1', 1, true, 'true'], true) ? '1' : '0';
                        $sanitized['assicurazioni'][$clean_slug] = $selected;

                        $posted_details = [];
                        if (!empty($persona['assicurazioni_dettagliate'][$slug]) && is_array($persona['assicurazioni_dettagliate'][$slug])) {
                            $posted_details = $persona['assicurazioni_dettagliate'][$slug];
                        }

                        $config = null;
                        foreach ($assicurazioni_config as $config_item) {
                            if (sanitize_title($config_item['descrizione']) === $clean_slug) {
                                $config = $config_item;
                                break;
                            }
                        }

                        $descrizione = sanitize_text_field($posted_details['descrizione'] ?? $config['descrizione'] ?? $clean_slug);
                        $product_id = absint($posted_details['product_id'] ?? $config['id'] ?? 0);
                        $percentuale = floatval($posted_details['percentuale'] ?? $posted_details['importo_percentuale'] ?? $config['importo_perentuale'] ?? 0);
                        $tipo_importo = sanitize_text_field($posted_details['tipo_importo'] ?? ($percentuale > 0 ? 'percentuale' : 'fisso'));

                        $importo = 0.0;
                        if (isset($posted_details['importo'])) {
                            $importo = floatval($posted_details['importo']);
                        } elseif (isset($config['importo'])) {
                            $importo = floatval($config['importo']);
                        }

                        $sanitized['assicurazioni_dettagliate'][$clean_slug] = [
                            'id' => $product_id,
                            'descrizione' => $descrizione,
                            'importo' => $importo,
                            'percentuale' => $percentuale,
                            'tipo_importo' => $tipo_importo,
                        ];
                    }
                }

                // Gestisci costi extra selezionati dal partecipante
                if (!empty($persona['costi_extra']) && is_array($persona['costi_extra'])) {
                    $sanitized['costi_extra'] = [];
                    $sanitized['costi_extra_dettagliate'] = [];

                    foreach ($persona['costi_extra'] as $slug => $extra_data) {
                        $clean_slug = sanitize_key($slug);
                        if (empty($clean_slug)) {
                            continue;
                        }

                        $selected = false;
                        $importo = 0.0;
                        $count = 1;

                        if (is_array($extra_data)) {
                            $selected = !empty($extra_data['selected']) && $extra_data['selected'] !== 'false';
                            if (isset($extra_data['price'])) {
                                $importo = floatval($extra_data['price']);
                            }
                            if (isset($extra_data['count'])) {
                                $count = max(1, intval($extra_data['count']));
                            }
                        } else {
                            $selected = in_array($extra_data, ['1', 1, true, 'true'], true);
                        }

                        if (!$selected) {
                            continue;
                        }

                        if (0.0 === $importo && isset($extra_costs_prices[$clean_slug])) {
                            $importo = floatval($extra_costs_prices[$clean_slug]);
                        }

                        if (0.0 === $importo && isset($extra_costs_config_map[$clean_slug]['importo'])) {
                            $importo = floatval($extra_costs_config_map[$clean_slug]['importo']);
                        }

                        $config = $extra_costs_config_map[$clean_slug] ?? null;
                        $nome_extra = $config['nome'] ?? ucfirst(str_replace('-', ' ', $clean_slug));
                        $descrizione_extra = $config['descrizione'] ?? $nome_extra;
                        $moltiplica_persone = !empty($config['moltiplica_persone']) ? 1 : 0;
                        $moltiplica_durata = !empty($config['moltiplica_durata']) ? 1 : 0;

                        $sanitized['costi_extra'][$clean_slug] = [
                            'selected' => '1',
                            'importo' => $importo,
                            'price' => $importo,
                            'count' => $count,
                            'attivo' => 1,
                        ];

                        $sanitized['costi_extra_dettagliate'][$clean_slug] = [
                            'nome' => $nome_extra,
                            'descrizione' => $descrizione_extra,
                            'importo' => $importo,
                            'slug' => $clean_slug,
                            'count' => $count,
                            'moltiplica_persone' => $moltiplica_persone,
                            'moltiplica_durata' => $moltiplica_durata,
                            'attivo' => 1,
                        ];
                    }
                }

                return $sanitized;
            }, $anagrafici);

            // Sanifica costi extra per durata
            $costi_extra_durata_sanitized = [];
            if (!empty($costi_extra_durata_raw) && is_array($costi_extra_durata_raw)) {
                foreach ($costi_extra_durata_raw as $slug => $extra_data) {
                    $clean_slug = sanitize_key($slug);
                    if (empty($clean_slug) || !is_array($extra_data)) {
                        continue;
                    }

                    $selezionato = !empty($extra_data['selected']) || !empty($extra_data['selezionato']);
                    $importo = floatval($extra_data['importo'] ?? $extra_data['price'] ?? ($extra_costs_prices[$clean_slug] ?? ($extra_costs_config_map[$clean_slug]['importo'] ?? 0)));
                    $nome_extra = sanitize_text_field($extra_data['nome'] ?? $extra_data['descrizione'] ?? ($extra_costs_config_map[$clean_slug]['nome'] ?? ucfirst(str_replace('-', ' ', $clean_slug))));

                    $costi_extra_durata_sanitized[$clean_slug] = [
                        'nome' => $nome_extra,
                        'importo' => $importo,
                        'selezionato' => $selezionato ? 1 : 0,
                        'moltiplica_durata' => !empty($extra_data['moltiplica_durata']) || (!empty($extra_costs_config_map[$clean_slug]['moltiplica_durata'])),
                        'moltiplica_persone' => !empty($extra_data['moltiplica_persone']) || (!empty($extra_costs_config_map[$clean_slug]['moltiplica_persone'])),
                    ];

                    if (isset($extra_data['count'])) {
                        $costi_extra_durata_sanitized[$clean_slug]['count'] = intval($extra_data['count']);
                    }
                }
            }

            // Fix: Prima di salvare, verifica il numero corretto di neonati
            $num_neonati_attesi = intval(get_post_meta($preventivo_id, '_num_neonati', true));
            $neonati_count = 0;
            $anagrafici_filtrati = [];

            foreach ($sanitized_anagrafici as $persona) {
                // Se è un neonato
                if (($persona['tipo_persona'] ?? '') === 'neonato' || 
                    (!empty($persona['fascia']) && $persona['fascia'] === 'neonato')) {
                    
                    // Conta solo se non supera il numero atteso
                    if ($neonati_count < $num_neonati_attesi) {
                        $anagrafici_filtrati[] = $persona;
                        $neonati_count++;
                    }
                    // Altrimenti scarta il neonato in eccesso
                } else {
                    // Non è un neonato, mantieni sempre
                    $anagrafici_filtrati[] = $persona;
                }
            }

            // Usa l'array filtrato invece di quello originale
            $sanitized_anagrafici = $anagrafici_filtrati;

            // Calcola e aggiorna i totali (assicurazioni e costi extra) dopo il salvataggio anagrafici
            $totale_assicurazioni_calc = 0.0;
            $totale_costi_extra_calc   = 0.0;
            $totale_aggiunte_calc      = 0.0;
            $totale_riduzioni_calc     = 0.0;

            $summary_room_total = (isset($_POST['summary_room_total']) && $_POST['summary_room_total'] !== '')
                ? floatval($_POST['summary_room_total'])
                : null;
            $summary_insurance_total = (isset($_POST['summary_insurance_total']) && $_POST['summary_insurance_total'] !== '')
                ? floatval($_POST['summary_insurance_total'])
                : null;
            $summary_extra_total = (isset($_POST['summary_extra_total']) && $_POST['summary_extra_total'] !== '')
                ? floatval($_POST['summary_extra_total'])
                : null;
            $summary_grand_total = (isset($_POST['summary_grand_total']) && $_POST['summary_grand_total'] !== '')
                ? floatval($_POST['summary_grand_total'])
                : null;

            // Assicurazioni per persona
            foreach ($sanitized_anagrafici as $persona) {
                if (!empty($persona['assicurazioni_dettagliate']) && is_array($persona['assicurazioni_dettagliate'])) {
                    $assicurazioni_attive = isset($persona['assicurazioni']) ? $persona['assicurazioni'] : [];

                    foreach ($persona['assicurazioni_dettagliate'] as $slug => $dettagli) {
                        if (!empty($assicurazioni_attive[$slug]) && $assicurazioni_attive[$slug] === '1') {
                            $importo_ass = floatval($dettagli['importo'] ?? 0);

                            if ($importo_ass <= 0 && !empty($dettagli['percentuale'])) {
                                $percentuale = floatval($dettagli['percentuale']);
                                if ($percentuale > 0) {
                                    $base_price = floatval(get_post_meta($preventivo_id, '_prezzo_totale', true));
                                    if ($base_price <= 0) {
                                        $base_price = floatval(get_post_meta($preventivo_id, '_totale_camere', true));
                                    }
                                    if ($base_price > 0) {
                                        $importo_ass = ($base_price * $percentuale) / 100;
                                    }
                                }
                            }

                            $totale_assicurazioni_calc += $importo_ass;
                        }
                    }
                }
            }

            // Determina giorni di durata per eventuali moltiplicatori
            $durata_giorni = intval(get_post_meta($preventivo_id, '_duration_days', true));
            if ($durata_giorni <= 0) {
                $durata_giorni = intval(get_post_meta($preventivo_id, '_btr_durata_giorni', true));
            }
            if ($durata_giorni <= 0) {
                $durata_string = get_post_meta($preventivo_id, '_durata', true);
                if (!empty($durata_string) && preg_match('/(\d+)/', $durata_string, $matches)) {
                    $durata_giorni = intval($matches[1]);
                }
            }
            if ($durata_giorni <= 0) {
                $durata_giorni = 1;
            }

            // Costi extra per persona
            foreach ($sanitized_anagrafici as $persona) {
                if (empty($persona['costi_extra_dettagliate']) || !is_array($persona['costi_extra_dettagliate'])) {
                    continue;
                }

                foreach ($persona['costi_extra_dettagliate'] as $slug => $dettagli) {
                    $importo_extra = floatval($dettagli['importo'] ?? 0);

                    if (0.0 === $importo_extra && isset($persona['costi_extra'][$slug]['importo'])) {
                        $importo_extra = floatval($persona['costi_extra'][$slug]['importo']);
                    }
                    if (0.0 === $importo_extra && isset($persona['costi_extra'][$slug]['price'])) {
                        $importo_extra = floatval($persona['costi_extra'][$slug]['price']);
                    }

                    if (0.0 === $importo_extra) {
                        continue;
                    }

                    $count = isset($dettagli['count']) ? intval($dettagli['count']) : 1;
                    if ($count <= 0 && isset($persona['costi_extra'][$slug]['count'])) {
                        $count = intval($persona['costi_extra'][$slug]['count']);
                    }
                    if ($count <= 0) {
                        $count = 1;
                    }

                    $importo_finale = $importo_extra * $count;

                    if (!empty($dettagli['moltiplica_durata'])) {
                        $importo_finale *= max(1, $durata_giorni);
                    }

                    if ($importo_finale >= 0) {
                        $totale_aggiunte_calc += $importo_finale;
                    } else {
                        $totale_riduzioni_calc += abs($importo_finale);
                    }
                }
            }

            $totale_costi_extra_calc = $totale_aggiunte_calc - $totale_riduzioni_calc;

            if (null !== $summary_insurance_total) {
                $totale_assicurazioni_calc = round($summary_insurance_total, 2);
            }
            if (null !== $summary_extra_total) {
                $totale_costi_extra_calc = round($summary_extra_total, 2);
                if ($totale_costi_extra_calc >= 0) {
                    $totale_aggiunte_calc = $totale_costi_extra_calc;
                    $totale_riduzioni_calc = 0.0;
                } else {
                    $totale_aggiunte_calc = 0.0;
                    $totale_riduzioni_calc = abs($totale_costi_extra_calc);
                }
            }

            $prezzo_totale_camere = floatval(get_post_meta($preventivo_id, '_prezzo_totale', true));
            if ($prezzo_totale_camere <= 0) {
                $prezzo_totale_camere = floatval(get_post_meta($preventivo_id, '_totale_camere', true));
            }

            $gran_totale_calcolato = round($prezzo_totale_camere + $totale_assicurazioni_calc + $totale_costi_extra_calc, 2);
            if (null !== $summary_grand_total && $summary_grand_total > 0) {
                $gran_totale_calcolato = round($summary_grand_total, 2);
            }

            $has_summary_totals = (null !== $summary_grand_total)
                || (null !== $summary_room_total)
                || (null !== $summary_insurance_total)
                || (null !== $summary_extra_total);

            // Dopo il calcolo server-side consideriamo i totali attendibili
            $has_summary_totals = true;

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[BTR Anagrafici] Totale camere base: ' . $prezzo_totale_camere);
                error_log('[BTR Anagrafici] Totale assicurazioni calcolato: ' . $totale_assicurazioni_calc);
                error_log('[BTR Anagrafici] Totale costi extra calcolato: ' . $totale_costi_extra_calc . ' (aggiunte: ' . $totale_aggiunte_calc . ', riduzioni: ' . $totale_riduzioni_calc . ')');
                error_log('[BTR Anagrafici] Gran totale calcolato: ' . $gran_totale_calcolato);
            }

            update_post_meta($preventivo_id, '_totale_assicurazioni', $totale_assicurazioni_calc);
            update_post_meta($preventivo_id, '_totale_costi_extra', $totale_costi_extra_calc);
            update_post_meta($preventivo_id, '_totale_aggiunte_extra', $totale_aggiunte_calc);
            update_post_meta($preventivo_id, '_totale_sconti_riduzioni', $totale_riduzioni_calc);
            update_post_meta($preventivo_id, '_totale_preventivo', $gran_totale_calcolato);
            update_post_meta($preventivo_id, '_btr_grand_total', $gran_totale_calcolato);
            update_post_meta($preventivo_id, '_prezzo_totale_completo', $gran_totale_calcolato);

            delete_post_meta($preventivo_id, '_price_snapshot');
            update_post_meta($preventivo_id, '_has_price_snapshot', false);

            // Salvataggio dei dati in base al contesto (ordine o preventivo)
            if ($order_id > 0) {
                // Salvataggio nell'ordine
                $order = wc_get_order($order_id);
                if (!$order) {
                    wp_send_json_error([
                        'message' => __('Ordine non valido o non trovato.', 'born-to-ride-booking'),
                        'code' => 'invalid_order'
                    ]);
                    return;
                }

                // Salva i dati anagrafici nell'ordine
                $order->update_meta_data('_anagrafici', $sanitized_anagrafici);
                $order->save();

                // Verifica il salvataggio
                $verifica = $order->get_meta('_anagrafici', true);
                if (is_array($verifica) && !empty($verifica)) {
                    wp_send_json_success([
                        'message' => __('Dati anagrafici salvati con successo.', 'born-to-ride-booking'),
                        'redirect_url' => $order->get_checkout_payment_url()
                    ]);
                } else {
                    wp_send_json_error([
                        'message' => __('Errore durante il salvataggio dei dati nell\'ordine.', 'born-to-ride-booking'),
                        'code' => 'save_error'
                    ]);
                }
            } else {
                // Salvataggio nel preventivo
                $post = get_post($preventivo_id);
                if (!$post || $post->post_type !== 'btr_preventivi') {
                    wp_send_json_error([
                        'message' => __('Preventivo non valido o non trovato.', 'born-to-ride-booking'),
                        'code' => 'invalid_preventivo'
                    ]);
                    return;
                }

                // Salva i dati anagrafici nel preventivo
                update_post_meta($preventivo_id, '_anagrafici_preventivo', $sanitized_anagrafici);
                update_post_meta($preventivo_id, '_costi_extra_durata', $costi_extra_durata_sanitized);
                wp_cache_delete('btr_preventivo_data_' . $preventivo_id, 'btr_preventivi');
            if (function_exists('btr_price_calculator')) {
                btr_price_calculator()->clear_cache();
            }

            // Log per debug
            error_log('[BTR DEBUG] save_anagrafici: Dati salvati per preventivo ' . $preventivo_id);

            // IMPORTANTE: Ricalcola i totali del preventivo dopo il salvataggio anagrafici
            // Questo include assicurazioni e costi extra
            if (!$has_summary_totals && method_exists($this, 'recalculate_preventivo_totals')) {
                error_log('[BTR DEBUG] save_anagrafici: Chiamo recalculate_preventivo_totals');
                $this->recalculate_preventivo_totals($preventivo_id, $sanitized_anagrafici);
            } elseif (!$has_summary_totals) {
                error_log('[BTR DEBUG] save_anagrafici: ERRORE - metodo recalculate_preventivo_totals non trovato!');
            } else {
                error_log('[BTR DEBUG] save_anagrafici: Totali già forniti dal frontend, salto recalculate_preventivo_totals');
            }
                
                // Salva anche nella sessione per il checkout summary
                if (WC()->session) {
                    WC()->session->set('btr_anagrafici_data', $sanitized_anagrafici);
                    WC()->session->set('btr_preventivo_id', $preventivo_id);
                    WC()->session->set('_preventivo_id', $preventivo_id); // Compatibilità con chiave vecchia
                    
                    // FIX CRITICO: Salva backup in transient per recovery
                    $session_id = WC()->session->get_customer_id();
                    if ($session_id) {
                        set_transient('btr_preventivo_backup_' . $session_id, $preventivo_id, 3600); // 1 ora
                        error_log('BACKUP: Preventivo ID ' . $preventivo_id . ' salvato in transient per session ' . $session_id);
                    }
                }

                // Verifica il salvataggio
                $verifica = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
                if (is_array($verifica) && !empty($verifica)) {
                    // Gestisci le assicurazioni nel carrello se necessario
                    $this->process_insurance_products($sanitized_anagrafici);
                    
                    // Trigger hook per integrazione sistema pagamenti
                    do_action('btr_after_anagrafici_saved', $preventivo_id, $sanitized_anagrafici);

                    // Reimposta i totali e snapshot dopo eventuali ricalcoli esterni
                    update_post_meta($preventivo_id, '_totale_assicurazioni', $totale_assicurazioni_calc);
                    update_post_meta($preventivo_id, '_totale_costi_extra', $totale_costi_extra_calc);
                    update_post_meta($preventivo_id, '_totale_aggiunte_extra', $totale_aggiunte_calc);
                    update_post_meta($preventivo_id, '_totale_sconti_riduzioni', $totale_riduzioni_calc);
                    update_post_meta($preventivo_id, '_totale_preventivo', $gran_totale_calcolato);
                    update_post_meta($preventivo_id, '_btr_grand_total', $gran_totale_calcolato);
                    update_post_meta($preventivo_id, '_prezzo_totale_completo', $gran_totale_calcolato);

                    $price_snapshot = [
                        'version' => 'anagrafici-sync-1',
                        'timestamp' => current_time('mysql'),
                        'rooms_total' => $prezzo_totale_camere,
                        'extra_costs' => [
                            'total' => $totale_costi_extra_calc,
                            'aggiunte' => $totale_aggiunte_calc,
                            'riduzioni' => $totale_riduzioni_calc,
                        ],
                        'insurance' => [
                            'total' => $totale_assicurazioni_calc,
                        ],
                        'totals' => [
                            'grand_total' => $gran_totale_calcolato,
                            'supplements_total' => 0,
                        ],
                        'participants' => $sanitized_anagrafici,
                    ];

                    update_post_meta($preventivo_id, '_price_snapshot', $price_snapshot);
                    update_post_meta($preventivo_id, '_has_price_snapshot', true);

                    // Verifica se mostrare modal selezione pagamento
                    $show_payment_modal = false;
                    $payment_modal_options = [];
                    
                    // Logica soglia pagamento di gruppo
                    $totale = get_post_meta($preventivo_id, '_totale_preventivo', true);
                    $enable_group = (bool) get_option('btr_enable_group_split', true);
                    $threshold = max(1, (int) get_option('btr_group_split_threshold', 10));
                    $default_mode = get_option('btr_default_payment_mode', 'full');

                    $num_adults   = (int) get_post_meta($preventivo_id, '_numero_adulti', true);
                    if ($num_adults === 0) { $num_adults = (int) get_post_meta($preventivo_id, '_num_adults', true); }
                    $num_children = (int) get_post_meta($preventivo_id, '_numero_bambini', true);
                    if ($num_children === 0) { $num_children = (int) get_post_meta($preventivo_id, '_num_children', true); }
                    $num_infants  = (int) get_post_meta($preventivo_id, '_num_neonati', true);
                    $total_persons = max(0, $num_adults + $num_children + $num_infants);

                    // Se gruppo disabilitato o soglia non raggiunta: vai direttamente al checkout
                    if (!$enable_group || $total_persons < $threshold) {
                        $show_payment_modal = false;
                        // Memorizza modalità default in sessione per il checkout
                        if (function_exists('WC') && WC()->session) {
                            WC()->session->set('btr_payment_plan_type', $default_mode);
                            if ('deposit_balance' === $default_mode) {
                                WC()->session->set('btr_deposit_percentage', (int) get_option('btr_default_deposit_percentage', 30));
                            }
                        }
                    } else {
                        // Mostra pagina selezione con opzioni (incluso gruppo)
                        $show_payment_modal = true;
                    }
                    $payment_modal_options = [
                        'bankTransferEnabled' => get_option('btr_enable_bank_transfer_plans', true),
                        'bankTransferInfo' => get_option('btr_bank_transfer_info', ''),
                        'depositPercentage' => intval(get_option('btr_default_deposit_percentage', 30))
                    ];
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('BTR: Redirect verso pagina selezione pagamento per preventivo ' . $preventivo_id . ' (totale: €' . $totale . ')');
                    }

                    // Determina l'URL di redirect
                    $redirect_url = wc_get_checkout_url(); // Default
                    
                    // Se deve mostrare il modal di selezione pagamento, redirect alla pagina dedicata
                    if ($show_payment_modal) {
                        // Ottieni l'ID della pagina di selezione pagamento
                        $payment_selection_page_id = get_option('btr_payment_selection_page_id');
                        
                        if ($payment_selection_page_id && get_post($payment_selection_page_id)) {
                            // Usa la pagina configurata
                            $redirect_url = add_query_arg([
                                'preventivo_id' => $preventivo_id
                            ], get_permalink($payment_selection_page_id));
                        } else {
                            // Fallback intelligenti: tenta più strategie per trovare la pagina selezione pagamento
                            $candidates = [
                                'selezione-piano-pagamento',
                                'payment-selection',
                                'selezione-pagamento',
                            ];
                            $found_link = '';
                            foreach ($candidates as $slug) {
                                $page = get_page_by_path($slug);
                                if ($page) {
                                    $found_link = get_permalink($page->ID);
                                    break;
                                }
                            }

                            // Se non trovato per slug, cerca una pagina che contenga lo shortcode [btr_payment_selection]
                            if (!$found_link) {
                                $pages = get_posts([
                                    'post_type'      => 'page',
                                    'posts_per_page' => 50,
                                    'post_status'    => 'publish',
                                ]);
                                foreach ($pages as $p) {
                                    if (has_shortcode($p->post_content ?? '', 'btr_payment_selection')) {
                                        $found_link = get_permalink($p->ID);
                                        break;
                                    }
                                }
                            }

                            if ($found_link) {
                                $redirect_url = add_query_arg([
                                    'preventivo_id' => $preventivo_id
                                ], $found_link);
                            }
                            // Se non trova nulla, mantiene il checkout come fallback
                        }
                    }
                    
                    // Invia risposta di successo con info modal
                    wp_send_json_success([
                        'message' => __('Dati anagrafici salvati con successo.', 'born-to-ride-booking'),
                        'redirect_url' => $redirect_url,
                        'show_payment_modal' => $show_payment_modal,
                        'payment_modal_options' => $payment_modal_options,
                        'preventivo_id' => $preventivo_id
                    ]);
                } else {
                    wp_send_json_error([
                        'message' => __('Errore durante il salvataggio dei dati nel preventivo.', 'born-to-ride-booking'),
                        'code' => 'save_error'
                    ]);
                }
            }
        } catch (Exception $e) {
            // Log dell'errore per il debug
            error_log('Errore in save_anagrafici: ' . $e->getMessage());

            // Invia risposta di errore
            wp_send_json_error([
                'message' => __('Si è verificato un errore durante il salvataggio. Riprova più tardi o contatta l\'assistenza.', 'born-to-ride-booking'),
                'code' => 'exception',
                'details' => WP_DEBUG ? $e->getMessage() : null
            ]);
        }
    }

    /**
     * Processa i prodotti assicurativi e li aggiunge al carrello
     * 
     * @param array $anagrafici Dati anagrafici sanitizzati
     * @return void
     */
    private function process_insurance_products($anagrafici) {
        // Verifica che WooCommerce sia attivo e il carrello sia disponibile
        if (!function_exists('WC') || !WC()->cart) {
            return;
        }

        // Non eseguire in contesto admin
        if (is_admin()) {
            return;
        }

        // Processa ogni persona
        foreach ($anagrafici as $persona) {
            // Verifica se ci sono assicurazioni da aggiungere
            if (empty($persona['assicurazioni']) || !is_array($persona['assicurazioni'])) {
                continue;
            }

            // Processa ogni assicurazione
            foreach ($persona['assicurazioni'] as $slug => $valore) {
                // Salta se non è selezionata
                if ($valore !== '1') {
                    continue;
                }

                // Recupera i dettagli dell'assicurazione
                $info = $persona['assicurazioni_dettagliate'][$slug] ?? null;
                if (!$info) {
                    continue;
                }

                // Recupera l'ID del prodotto
                $product_id = absint($info['id'] ?? 0);
                if (!$product_id) {
                    continue;
                }

                // Verifica se l'assicurazione è già nel carrello
                $already_in_cart = false;
                foreach (WC()->cart->get_cart() as $key => $item) {
                    if (
                        isset($item['from_anagrafica']) &&
                        $item['from_anagrafica'] === true &&
                        $item['label_assicurazione'] === $slug
                    ) {
                        $already_in_cart = true;
                        break;
                    }
                }

                // Aggiungi al carrello se non è già presente
                if (!$already_in_cart) {
                    // Crea un nome personalizzato per l'assicurazione
                    $custom_name = sprintf(
                        __('Assicurazione %s per %s %s', 'born-to-ride-booking'),
                        $info['descrizione'],
                        $persona['nome'],
                        $persona['cognome']
                    );

                    // Aggiungi al carrello
                    WC()->cart->add_to_cart($product_id, 1, 0, [], [
                        'from_anagrafica'     => true,
                        'custom_price'        => floatval($info['importo']),
                        'custom_name'         => $custom_name,
                        'label_assicurazione' => $slug,
                        'person_name'         => $persona['nome'] . ' ' . $persona['cognome'],
                    ]);
                }
            }
        }
    }






    /**
     * Recupera i dati anagrafici associati a un ordine o preventivo
     *
     * @param int $id ID dell'ordine o preventivo
     * @return array Array dei dati anagrafici
     */
    private function get_anagrafici($id)
    {
        $anagrafici = get_post_meta($id, '_anagrafici', true);
        if (!is_array($anagrafici)) {
            $anagrafici = get_post_meta($id, '_anagrafici_preventivo', true);
            if (!is_array($anagrafici)) {
                $anagrafici = [];
            }
        }
        return $anagrafici;
    }

    /**
     * Recupera il numero totale di persone prenotate da un ordine
     *
     * @param int $order_id ID dell'ordine
     * @return int Numero totale di persone
     */
    private function get_totale_persone_from_order($order_id)
    {
        $order = wc_get_order($order_id);
        $totale_persone = 0;

        if ($order) {
            foreach ($order->get_items() as $item) {
                $number_of_persons = $item->get_meta('number_of_persons');
                $quantita = $item->get_quantity();
                if ($number_of_persons) {
                    $totale_persone += intval($number_of_persons) * intval($quantita);
                }
            }
        }

        return $totale_persone;
    }

    /**
     * Recupera il numero totale di persone prenotate da un preventivo
     *
     * @param int $preventivo_id ID del preventivo
     * @return int Numero totale di persone
     */
    private function get_totale_persone_from_preventivo($preventivo_id)
    {
        // Recupera i dati delle camere selezionate
        $camere_selezionate = get_post_meta($preventivo_id, '_camere_selezionate', true);

        $totale_persone = 0;

        // Verifica se i dati sono un array
        if (is_array($camere_selezionate)) {
            foreach ($camere_selezionate as $camera) {
                // Recupera il tipo di camera e la quantità
                $tipo = strtolower($camera['tipo'] ?? '');
                $quantita = intval($camera['quantita'] ?? 0);

                // Determina il numero di persone per tipo di camera
                $numero_persone = $this->determine_number_of_persons($tipo);

                // Somma il totale delle persone
                $totale_persone += $numero_persone * $quantita;
            }
        } else {
            error_log("get_totale_persone_from_preventivo: Camere selezionate non è un array: " . print_r($camere_selezionate, true));
        }

        error_log("get_totale_persone_from_preventivo: Totale persone calcolato dal preventivo: $totale_persone");
        return $totale_persone;
    }

    /**
     * Determina il numero di persone in base al tipo di stanza.
     *
     * @param string $tipo Tipo di stanza (es. 'singola', 'doppia').
     * @return int Numero di persone.
     */
    private function determine_number_of_persons($tipo)
    {
        $room_capacity_map = [
            'singola'   => 1,
            'doppia'    => 2,
            'doppia/matrimoniale'    => 2,
            'tripla'    => 3,
            'quadrupla' => 4,
            'quintupla' => 5,
            'condivisa' => 1, // Modifica se necessario
        ];

        return $room_capacity_map[$tipo] ?? 1;
    }

    /**
     * Mostra un alert e un pulsante nel riepilogo dell'ordine se i dati anagrafici sono mancanti
     *
     * @param int $order_id ID dell'ordine
     */
    public function maybe_display_anagrafici_alert($order_id)
    {
        if (!$order_id) {
            return;
        }

        // Recupera l'ordine
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        // Recupera i dati anagrafici
        $anagrafici = $this->get_anagrafici($order_id);

        // Recupera il totale delle persone prenotate
        $totale_persone = $this->get_totale_persone_from_order($order_id);

        // Controlla se i dati anagrafici sono completi
        $completo = is_array($anagrafici) && count($anagrafici) === $totale_persone;

        if (!$completo) {
            // Genera l'URL della pagina del form
            $form_url = add_query_arg('order_id', $order_id, home_url('/inserisci-anagrafici/')); // Assicurati che questa pagina esista e contenga lo shortcode

            ?>
            <div class="woocommerce-message btr-anagrafici-alert">
                <p><?php esc_html_e('Per completare l\'ordine, è necessario inserire i dati anagrafici delle persone prenotate.', 'born-to-ride-booking'); ?></p>
                <a href="<?php echo esc_url($form_url); ?>" class="button"><?php esc_html_e('Inserisci Dati Anagrafici', 'born-to-ride-booking'); ?></a>
            </div>
            <?php
        }
    }

    /**
     * Aggiunge un alert e un pulsante nelle email di WooCommerce se i dati anagrafici sono mancanti
     *
     * @param WC_Order $order Oggetto ordine
     * @param bool $sent_to_admin Indica se l'email è inviata all'amministratore
     * @param bool $plain_text Indica se l'email è in formato testo semplice
     * @param WC_Email $email Oggetto email
     */
    public function maybe_add_email_anagrafici_alert($order, $sent_to_admin, $plain_text, $email)
    {
        if ($sent_to_admin) {
            return;
        }

        if (!$order || !$order->get_id()) {
            return;
        }

        $order_id = $order->get_id();

        // Recupera i dati anagrafici
        $anagrafici = $this->get_anagrafici($order_id);

        // Recupera il totale delle persone prenotate
        $totale_persone = $this->get_totale_persone_from_order($order_id);

        // Controlla se i dati anagrafici sono completi
        $completo = is_array($anagrafici) && count($anagrafici) === $totale_persone;

        if (!$completo) {
            // Genera l'URL della pagina del form
            $form_url = add_query_arg('order_id', $order_id, home_url('/inserisci-anagrafici/')); // Assicurati che questa pagina esista e contenga lo shortcode

            if ($plain_text) {
                echo "\n\n" . __('Per completare l\'ordine, è necessario inserire i dati anagrafici delle persone prenotate.', 'born-to-ride-booking') . "\n";
                echo $form_url . "\n";
            } else {
                echo '<p>' . esc_html__('Per completare l\'ordine, è necessario inserire i dati anagrafici delle persone prenotate.', 'born-to-ride-booking') . '</p>';
                echo '<a href="' . esc_url($form_url) . '" class="button">' . esc_html__('Inserisci Dati Anagrafici', 'born-to-ride-booking') . '</a>';
            }
        }
    }

    /**
     * Aggiunge un metabox nella schermata di amministrazione dell'ordine WooCommerce.
     */
    public function add_order_anagrafici_metabox()
    {
        add_meta_box(
            'btr_anagrafici_metabox',
            __('Dati Anagrafici', 'born-to-ride-booking'),
            [$this, 'render_anagrafici_metabox'],
            'shop_order', // Post type specifico per gli ordini di WooCommerce
            'side', // Posizione: laterale
            'high' // Priorità alta
        );
    }

    /**
     * Renderizza il contenuto del metabox.
     *
     * @param WP_Post $post L'oggetto post dell'ordine.
     */
    public function render_anagrafici_metabox($post)
    {
        $order_id = $post->ID;
        error_log('Rendering metabox per ordine ID: ' . $order_id); // Log di debug

        // Recupera i dati anagrafici dall'ordine
        $anagrafici = $this->get_anagrafici($order_id);

        if (empty($anagrafici)) {
            echo '<p>' . esc_html__('Nessun dato anagrafico disponibile.', 'born-to-ride-booking') . '</p>';
            return;
        }

        echo '<ul style="list-style: none; padding: 0;">';
        foreach ($anagrafici as $index => $persona) {
            echo '<li style="margin-bottom: 15px; border-bottom: 1px solid #ddd; padding-bottom: 10px;">';
            echo '<strong>' . sprintf(__('Persona %d', 'born-to-ride-booking'), $index + 1) . '</strong><br>';
            echo '<span>' . esc_html__('Nome:', 'born-to-ride-booking') . ' ' . esc_html($persona['nome'] ?? '') . '</span><br>';
            echo '<span>' . esc_html__('Cognome:', 'born-to-ride-booking') . ' ' . esc_html($persona['cognome'] ?? '') . '</span><br>';
            echo '<span>' . esc_html__('Età:', 'born-to-ride-booking') . ' ' . esc_html($persona['eta'] ?? '') . '</span><br>';
            echo '<span>' . esc_html__('Documento:', 'born-to-ride-booking') . ' ' . esc_html($persona['documento'] ?? '') . '</span>';
            echo '</li>';
        }
        echo '</ul>';
    }
    
    /**
     * Salva temporaneamente i dati anagrafici (per multi-step form)
     * 
     * @since 1.0.99
     */
    public function save_anagrafici_temp() {
        // Verifica nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], self::NONCE_ACTION_UPDATE)) {
            wp_send_json_error(['message' => 'Nonce non valido']);
            return;
        }
        
        // Salva i dati in sessione
        if (isset($_POST['data'])) {
            parse_str($_POST['data'], $form_data);
            WC()->session->set('btr_anagrafici_temp', $form_data);
            wp_send_json_success(['message' => 'Dati salvati temporaneamente']);
        } else {
            wp_send_json_error(['message' => 'Nessun dato da salvare']);
        }
    }
    
    /**
     * Ricalcola i totali del preventivo includendo assicurazioni e costi extra
     * Utilizza il calcolatore centralizzato per garantire coerenza
     * 
     * @param int $preventivo_id ID del preventivo
     * @param array $anagrafici Dati anagrafici con le assicurazioni
     * @since 1.0.99
     * @since 1.0.100 Usa BTR_Cost_Calculator centralizzato
     */
    private function recalculate_preventivo_totals($preventivo_id, $anagrafici) {
        // Fallback legacy (da eliminare dopo migrazione completa)
        // Recupera i dati base del preventivo
        $pacchetto_id = get_post_meta($preventivo_id, '_pacchetto_id', true);
        $prezzo_base = floatval(get_post_meta($preventivo_id, '_prezzo_base', true));
        $supplementi = floatval(get_post_meta($preventivo_id, '_supplementi', true));
        $sconti = floatval(get_post_meta($preventivo_id, '_sconti', true));
        
        // Calcola il totale delle assicurazioni dai dati anagrafici
        $totale_assicurazioni = 0;
        if (!empty($anagrafici) && is_array($anagrafici)) {
            foreach ($anagrafici as $persona) {
                if (!empty($persona['assicurazioni_dettagliate']) && is_array($persona['assicurazioni_dettagliate'])) {
                    foreach ($persona['assicurazioni_dettagliate'] as $slug => $dettagli) {
                        // Verifica se l'assicurazione è selezionata
                        if (isset($persona['assicurazioni'][$slug]) && $persona['assicurazioni'][$slug] === '1') {
                            $importo = floatval($dettagli['importo'] ?? 0);
                            $percentuale = floatval($dettagli['percentuale'] ?? 0);
                            
                            // Se c'è una percentuale, calcola l'importo sul prezzo base
                            if ($percentuale > 0 && $prezzo_base > 0) {
                                $importo = ($prezzo_base * $percentuale) / 100;
                            }
                            
                            $totale_assicurazioni += $importo;
                        }
                    }
                }
            }
        }
        
        // Recupera i costi extra dal preventivo
        $costi_extra_meta = get_post_meta($preventivo_id, '_costi_extra_durata', true);
        $totale_costi_extra = 0;
        
        if (!empty($costi_extra_meta) && is_array($costi_extra_meta)) {
            foreach ($costi_extra_meta as $costo) {
                if (isset($costo['importo'])) {
                    $totale_costi_extra += floatval($costo['importo']);
                }
            }
        }
        
        // Calcola il gran totale
        $gran_totale = $prezzo_base + $supplementi - $sconti + $totale_assicurazioni + $totale_costi_extra;
        
        // Aggiorna i meta del preventivo
        update_post_meta($preventivo_id, '_totale_assicurazioni', $totale_assicurazioni);
        update_post_meta($preventivo_id, '_totale_costi_extra', $totale_costi_extra);
        update_post_meta($preventivo_id, '_totale_aggiunte_extra', max($totale_costi_extra, 0));
        update_post_meta($preventivo_id, '_totale_sconti_riduzioni', abs(min($totale_costi_extra, 0)));
        update_post_meta($preventivo_id, '_totale_preventivo', $gran_totale);
        
        // Log per debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[BTR] Ricalcolo totali preventivo ' . $preventivo_id . ' (metodo fallback)');
            error_log('[BTR] Prezzo base: ' . $prezzo_base);
            error_log('[BTR] Supplementi: ' . $supplementi);
            error_log('[BTR] Sconti: ' . $sconti);
            error_log('[BTR] Totale assicurazioni: ' . $totale_assicurazioni);
            error_log('[BTR] Totale costi extra: ' . $totale_costi_extra);
            error_log('[BTR] Gran totale: ' . $gran_totale);
        }
        
        return $gran_totale;
    }
    
    /**
     * Recupera i dati per la selezione pagamento
     * 
     * @since 1.0.99
     */
    public function get_payment_data() {
        try {
            // Debug log
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[BTR] get_payment_data chiamato');
                error_log('[BTR] POST data: ' . print_r($_POST, true));
            }
            
            // Verifica nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], self::NONCE_ACTION_UPDATE)) {
                wp_send_json_error(['message' => 'Nonce non valido']);
                return;
            }
            
            $preventivo_id = isset($_POST['preventivo_id']) ? intval($_POST['preventivo_id']) : 0;
            
            if (!$preventivo_id) {
                wp_send_json_error(['message' => 'ID preventivo mancante']);
                return;
            }
            
            // Verifica che il preventivo esista
            $preventivo = get_post($preventivo_id);
            if (!$preventivo || $preventivo->post_type !== 'preventivi') {
                wp_send_json_error(['message' => 'Preventivo non trovato']);
                return;
            }
            
            // Recupera i dati del preventivo
            $totale = get_post_meta($preventivo_id, '_totale_preventivo', true);
            $numero_adulti = get_post_meta($preventivo_id, '_numero_adulti', true);
            $numero_bambini = get_post_meta($preventivo_id, '_numero_bambini', true);
            
            // Converti il totale in numero
            $totale = floatval($totale);
            
            // Calcola acconto (default 30%)
            $deposit_percentage = intval(get_option('btr_default_deposit_percentage', 30));
            $deposit_amount = $totale * ($deposit_percentage / 100);
            
            // Recupera anagrafici temporanei dalla sessione (con controllo WC)
            $participants = [];
            if (class_exists('WooCommerce') && WC()->session) {
                $temp_data = WC()->session->get('btr_anagrafici_temp');
                
                if ($temp_data && isset($temp_data['anagrafici'])) {
                    foreach ($temp_data['anagrafici'] as $persona) {
                        if (!empty($persona['nome']) && !empty($persona['cognome'])) {
                            $participants[] = [
                                'nome' => $persona['nome'],
                                'cognome' => $persona['cognome'],
                                'tipo' => $persona['tipo_persona'] ?? 'adulto'
                            ];
                        }
                    }
                }
            }
        
            wp_send_json_success([
                'total' => floatval($totale),
                'total_formatted' => number_format($totale, 2, ',', '.'),
                'deposit_amount' => floatval($deposit_amount),
                'deposit_formatted' => number_format($deposit_amount, 2, ',', '.'),
                'deposit_percentage' => $deposit_percentage,
                'numero_adulti' => intval($numero_adulti),
                'numero_bambini' => intval($numero_bambini),
                'participants' => $participants
            ]);
            
        } catch (Exception $e) {
            error_log('[BTR] Errore in get_payment_data: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Errore durante il recupero dei dati: ' . $e->getMessage()]);
        }
    }

}
