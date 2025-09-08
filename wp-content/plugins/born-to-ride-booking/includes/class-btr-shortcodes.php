<?php
if (!defined('ABSPATH')) {
    exit; // Impedisce l'accesso diretto al file
}

class BTR_Shortcodes
{
    private const NONCE_ACTION = 'btr_booking_form_nonce';
    private const NONCE_FIELD = 'btr_booking_form_nonce_field';

    public function __construct()
    {
        // Registra lo shortcode
        add_shortcode('btr_booking_form', [$this, 'render_booking_form']);

        // Enqueue degli script e degli stili
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

        // Azioni AJAX
        add_action('wp_ajax_btr_check_availability', [$this, 'check_availability']);
        add_action('wp_ajax_nopriv_btr_check_availability', [$this, 'check_availability']);
        add_action('wp_ajax_btr_check_extra_night_availability', [$this, 'check_extra_night_availability']);
        add_action('wp_ajax_nopriv_btr_check_extra_night_availability', [$this, 'check_extra_night_availability']);
        add_action('wp_ajax_btr_get_rooms', [$this, 'get_rooms']);
        add_action('wp_ajax_nopriv_btr_get_rooms', [$this, 'get_rooms']);
        add_action('wp_ajax_btr_process_booking', [$this, 'process_booking']);
        add_action('wp_ajax_nopriv_btr_process_booking', [$this, 'process_booking']);


        add_shortcode('btr_seleziona_assicurazioni', [$this, 'render_form_assicurazioni']);

        add_action('wp_ajax_btr_get_assicurazioni_config', [$this, 'ajax_get_assicurazioni_config']);
        add_action('wp_ajax_nopriv_btr_get_assicurazioni_config', [$this, 'ajax_get_assicurazioni_config']);
        add_action('wp_ajax_btr_render_assicurazioni_form', [$this, 'ajax_render_assicurazioni_form']);
        add_action('wp_ajax_nopriv_btr_render_assicurazioni_form', [$this, 'ajax_render_assicurazioni_form']);
    }



    /**
     * Enqueue degli script e degli stili necessari
     */
    public function enqueue_scripts()
    {
        wp_enqueue_script(
            'inputmask',
            'https://cdn.jsdelivr.net/npm/inputmask@5.0.8/dist/inputmask.min.js',
            [],
            null,
            true
        );

        wp_enqueue_script(
            'btr-booking-form-js',
            plugin_dir_url(__FILE__) . '../assets/js/frontend-scripts.js' . '?v=' . BTR_VERSION . '.' . 1752611518,
            ['jquery'],
            BTR_VERSION,
            true
        );
        
        
        
        // Dynamic Summary Panel - Gestione dinamica del pannello riepilogo (v1.0.111)
        wp_enqueue_script(
            'btr-dynamic-summary',
            plugin_dir_url(__FILE__) . '../assets/js/dynamic-summary-panel.js',
            ['jquery', 'btr-booking-form-js'],
            '1.0.111',
            true
        );

        wp_enqueue_style(
            'btr-booking-form-css',
            plugin_dir_url(__FILE__) . '../assets/css/frontend-styles.css' . '?v=' . BTR_VERSION . '.' . 1752611518,
            [],
            BTR_VERSION
        );

        wp_enqueue_style(
            'btr-booking-inport-salinet-css',
            plugin_dir_url(__FILE__) . '../assets/css/inport-salinet-frontend-styles.css' . '?v=' . BTR_VERSION . '.' . 1752611518,
            [],
            BTR_VERSION
        );

        wp_enqueue_style(
            'btr-costi-extra-css',
            plugin_dir_url(__FILE__) . '../assets/css/costi-extra-styles.css' . '?v=' . BTR_VERSION . '.' . 1752611518,
            [],
            BTR_VERSION
        );

        wp_enqueue_style(
            'btr-room-subtype-selector-css',
            plugin_dir_url(__FILE__) . '../assets/css/room-subtype-selector.css',
            ['btr-booking-form-css'],
            BTR_VERSION
        );
        
        // Hotfix temporaneo per correzione 3→2 notti (v1.0.43)
        wp_enqueue_script(
            'btr-hotfix-3-to-2',
            plugin_dir_url(__FILE__) . '../assets/js/hotfix-3-to-2.js',
            ['jquery'],
            BTR_VERSION,
            false // Carica nell'header per intercettare subito
        );

        // Tentativo di recupero dell'ID pacchetto direttamente dallo shortcode
        $package_id = 0;
        if (!empty($_GET['btr_package_id'])) {
            $package_id = intval($_GET['btr_package_id']);
        } elseif (is_singular('btr_pacchetti')) {
            $package_id = get_the_ID();
        }

        // Se non è stato trovato nulla, fallback a get_the_ID
        if (!$package_id) {
            $package_id = get_the_ID();
        }

        $product_id = get_post_meta($package_id, '_btr_product_id', true);
        $available_dates = [];

        // Recupera la tipologia di prenotazione
        $tipologia_prenotazione = get_post_meta($package_id, 'btr_tipologia_prenotazione', true);

        if ($product_id) {
            $product = wc_get_product($product_id);
            $available_dates = $this->get_available_dates($product);
        }

        // Build the children‑fascia array (label, age range, discount) to expose to JS
        $child_fasce = array();
        for ($i = 1; $i <= 4; $i++) {
            if (get_post_meta($package_id, "btr_bambini_fascia{$i}_sconto_enabled", true) !== '1') {
                continue; // skip disabled fascia
            }
            $child_fasce[] = array(
                'id'       => $i,
                'label'    => get_post_meta($package_id, "btr_bambini_fascia{$i}_label", true),
                'age_min'  => (int) get_post_meta($package_id, "btr_bambini_fascia{$i}_eta_min", true),
                'age_max'  => (int) get_post_meta($package_id, "btr_bambini_fascia{$i}_eta_max", true),
                'discount' => (float) get_post_meta($package_id, "btr_bambini_fascia{$i}_sconto", true),
            );
        }

        wp_localize_script('btr-booking-form-js', 'btr_booking_form', [
            'ajax_url'       => admin_url('admin-ajax.php'),
            'nonce'          => wp_create_nonce(self::NONCE_ACTION),
            'availableDates' => $available_dates,
            'package_id'     => $package_id,
            'tipologia_prenotazione' => $tipologia_prenotazione,
            'badge_rules'    => get_post_meta($package_id, 'btr_badge_rules', true),
            'base_nights'    => intval(get_post_meta($package_id, 'btr_numero_notti', true)) || 1,
            'extra_nights'   => 1, // Configurabile: numero di notti extra quando selezionate
            'labels'         => array(
                'adult_singular'  => get_option('btr_label_adult_singular', 'Adulto'),
                'adult_plural'    => get_option('btr_label_adult_plural', 'Adulti'),
                'child_singular'  => get_option('btr_label_child_singular', 'Bambino'),
                'child_plural'    => get_option('btr_label_child_plural', 'Bambini'),
                'participant'     => get_option('btr_label_participant', 'Partecipante'),
                'infant_singular' => get_option('btr_label_infant_singular', 'Neonato'),
                'infant_plural'   => get_option('btr_label_infant_plural', 'Neonati'),
            ),
        ]);
        // v1.0.160 - Ottieni etichette dinamiche per i bambini dal package
        $dynamic_child_labels = [];
        // Solo se abbiamo un package_id valido (che sia un pacchetto BTR)
        if ($package_id && get_post_type($package_id) === 'btr_pacchetti') {
            if (class_exists('BTR_Preventivi')) {
                $dynamic_child_labels = BTR_Preventivi::btr_get_child_age_labels($package_id);
            } elseif (function_exists('btr_get_all_child_labels')) {
                $dynamic_child_labels = btr_get_all_child_labels($package_id);
            }
        } else {
            // Se non abbiamo un package valido, usa i valori di default generici
            if (class_exists('BTR_Preventivi')) {
                $dynamic_child_labels = BTR_Preventivi::btr_get_child_age_labels(null);
            }
        }
        
        // Aggiorna child_fasce con etichette dinamiche
        foreach ($child_fasce as $key => &$fascia) {
            if (isset($dynamic_child_labels[$key]) && !empty($dynamic_child_labels[$key])) {
                $fascia['label'] = $dynamic_child_labels[$key];
            }
        }
        
        // Pass the bambini fascia configuration to JS
        wp_add_inline_script(
            'btr-booking-form-js',
            'window.btrChildFasce = ' . wp_json_encode($child_fasce) . ';' .
            'window.btrDynamicChildLabels = ' . wp_json_encode($dynamic_child_labels) . ';',
            'before'
        );
        
        // ===== BTR PAYLOAD MONITOR - DEBUG MODE ONLY =====
        // Carica il monitor di payload solo in modalità debug per sviluppatori
        if ((defined('WP_DEBUG') && WP_DEBUG) || (defined('BTR_DEBUG') && BTR_DEBUG)) {
            // CSS del monitor
            wp_enqueue_style(
                'btr-debug-monitor-css',
                plugin_dir_url(__FILE__) . '../assets/css/debug-monitor.css',
                [],
                BTR_VERSION . '.monitor.v1'
            );
            
            // JavaScript del monitor
            wp_enqueue_script(
                'btr-debug-monitor-js',
                plugin_dir_url(__FILE__) . '../assets/js/debug-monitor.js',
                ['jquery', 'btr-booking-form-js'],
                BTR_VERSION . '.monitor.v1',
                true
            );
            
            // Passa dati di configurazione al monitor
            wp_add_inline_script(
                'btr-debug-monitor-js',
                'window.btr_booking_form = window.btr_booking_form || {};' .
                'window.btr_booking_form.debug_mode = true;' .
                'window.btr_booking_form.monitor_version = "1.0.158";' .
                'window.btr_booking_form.features = {' .
                    'search: true,' .
                    'filtering: true,' .
                    'export: true,' .
                    'minimize: true,' .
                    'performance_metrics: true' .
                '};',
                'before'
            );
            
            // Log per conferma caricamento
            if (defined('BTR_DEBUG') && BTR_DEBUG) {
                error_log('[BTR Monitor] Assets caricati - Monitor payload attivo');
            }
        }
    }

    /**
     * Renderizza il modulo di prenotazione
     */
    public function render_booking_form($atts)
    {
        $atts = shortcode_atts(['id' => 0], $atts, 'btr_booking_form');
        $package_id = intval($atts['id']);

        if (!$package_id || get_post_type($package_id) !== 'btr_pacchetti') {
            return '<p>Pacchetto non valido o non specificato.</p>';
        }

        $product_id = get_post_meta($package_id, '_btr_product_id', true);
        if (!$product_id || !($product = wc_get_product($product_id))) {
            return '<p>Prodotto WooCommerce non trovato per questo pacchetto.</p>';
        }

        $dates = $this->get_available_dates($product);

        // Recupera la tipologia di prenotazione
        $tipologia_prenotazione = get_post_meta($package_id, 'btr_tipologia_prenotazione', true);
        $btr_destinazione = get_post_meta($package_id, 'btr_destinazione', true);
        if (is_array($btr_destinazione)) {
            $btr_destinazione = implode(', ', $btr_destinazione);
        }
        $localita_destinazione = get_post_meta($package_id, 'btr_localita_destinazione', true);
        if (is_array($localita_destinazione)) {
            $localita_destinazione = implode(', ', $localita_destinazione);
        }
        $btr_tipo_durata = get_post_meta($package_id, 'btr_tipo_durata', true);
        $btr_numero_giorni = get_post_meta($package_id, 'btr_numero_giorni', true);
        $btr_numero_giorni_libere = get_post_meta($package_id, 'btr_numero_giorni_libere', true);
        $btr_numero_giorni_fisse = get_post_meta($package_id, 'btr_numero_giorni_fisse', true);
        $btr_numero_notti = get_post_meta($package_id, 'btr_numero_notti', true);
        // Recupera flag "ammessi" per adulti e bambini.
        // Se la prenotazione è di tipo allotment_camere – o se la chiave legacy non è stata compilata –
        // tenta la lettura delle nuove chiavi con suffisso _allotment.
        $btr_ammessi_adulti   = get_post_meta($package_id, 'btr_ammessi_adulti', true);
        $btr_ammessi_bambini  = get_post_meta($package_id, 'btr_ammessi_bambini', true);

        if ($tipologia_prenotazione === 'allotment_camere' || $btr_ammessi_adulti === '') {
            $val = get_post_meta($package_id, 'btr_ammessi_adulti_allotment', true);
            if ($val !== '') {
                $btr_ammessi_adulti = $val;
            }
        }

        if ($tipologia_prenotazione === 'allotment_camere' || $btr_ammessi_bambini === '') {
            $val = get_post_meta($package_id, 'btr_ammessi_bambini_allotment', true);
            if ($val !== '') {
                $btr_ammessi_bambini = $val;
            }
        }
        $camere_extra_allotment_by_date = get_post_meta($package_id, 'btr_camere_extra_allotment_by_date', true);

        // Calcola il prezzo minimo adulto per "Prezzo a partire da"
        $min_adult_price = $this->get_minimum_adult_price($product);

        ob_start();

        // Recupera le info bambini/infanti dal backend
        $include_infant = get_post_meta($package_id, 'include_infant', true);
        $include_child = get_post_meta($package_id, 'include_child', true);
        $infant_price = get_post_meta($package_id, 'infant_price', true);
        $child_price = get_post_meta($package_id, 'child_price', true);
        $infant_note = get_post_meta($package_id, 'infant_note', true);
        $child_note = get_post_meta($package_id, 'child_note', true);



        //printr(get_post_meta($package_id));

        if ($include_infant === '1' || $include_child === '1') : ?>
            <section class="btr-dettagli-infanzia" style="margin-bottom: 30px;">
                <h3 style="color: #0097c5;">Bambini e Infanti</h3>
                <ul style="list-style: none; padding: 0;">
                    <?php if ($include_infant === '1') : ?>
                        <li><strong>Infanti (0–2 anni):</strong>
                            <?php echo !empty($infant_price) ? '€ ' . number_format_i18n((float) $infant_price, 2) : 'Inclusi'; ?>
                            <?php if (!empty($infant_note)) : ?><br><em><?php echo esc_html($infant_note); ?></em><?php endif; ?>
                        </li>
                    <?php endif; ?>

                    <?php if ($include_child === '1') : ?>
                        <li><strong>Bambini (3–12 anni):</strong>
                            <?php echo !empty($child_price) ? '€ ' . number_format_i18n((float) $child_price, 2) : 'Inclusi'; ?>
                            <?php if (!empty($child_note)) : ?><br><em><?php echo esc_html($child_note); ?></em><?php endif; ?>
                        </li>
                    <?php endif; ?>
                </ul>
            </section>
        <?php endif; ?>

        <div id="fws_67db4c14c470b" data-column-margin="default" data-midnight="dark"
             class="wpb_row vc_row-fluid vc_row top-level vc_row-o-equal-height vc_row-flex vc_row-o-content-middle" style="padding-top: 0px; padding-bottom: 0px;">
            <div class="row-bg-wrap" data-bg-animation="none" data-bg-animation-delay="" data-bg-overlay="false">
                <div class="inner-wrap row-bg-layer">
                    <div class="row-bg viewport-desktop" style=""></div>
                </div>
            </div>
            <div class="row_col_wrap_12 col span_12 dark left">
                <div class="vc_col-sm-4 wpb_column column_container vc_column_container col no-extra-padding inherit_tablet inherit_phone border_right_desktop_1px border_color_cccccc border_style_solid "
                     data-padding-pos="all" data-has-bg-color="false" data-bg-color="" data-bg-opacity="1" data-animation="" data-delay="0">
                    <div class="vc_column-inner">
                        <div class="wpb_wrapper">
                            <h1 style="color: #0097c5;text-align: left; letter-spacing: 0;font-size: 50px;" class="vc_custom_heading vc_do_custom_heading"><?= esc_html
                                ($btr_destinazione ?? ''); ?></h1>
                            <h3 style="color: #000000;text-align: left" class="vc_custom_heading vc_do_custom_heading"><?= esc_html($localita_destinazione ?? ''); ?></h3>
                           <!--
                            <div class="wpb_text_column wpb_content_element ">
                                <div class="wpb_wrapper">
                                    <p>
                                        <?php foreach ($dates as $date => $data) : ?>
                                            <?php echo esc_html($date); ?><br>
                                        <?php endforeach; ?>
                                        </p>
                                </div>
                            </div>

                            -->
                        </div>
                    </div>
                </div>
                <div class="vc_col-sm-8 wpb_column column_container vc_column_container col padding-2-percent inherit_tablet inherit_phone " data-padding-pos="left"
                     data-has-bg-color="false" data-bg-color="" data-bg-opacity="1" data-animation="" data-delay="0">
                    <div class="vc_column-inner">
                        <div class="wpb_wrapper">
                            <div class="iwithtext">
                                <div class="iwt-icon"><img decoding="async" src="https://borntoride.labuix.com/wp-content/uploads/2024/10/ICON-calendario-300x300.png" alt=""></div>
                                <div class="iwt-text"><strong>DURATA</strong>:
                                    <?= esc_html($btr_numero_giorni ? $btr_numero_giorni .' giorni - ' : ''); ?>
                                    <?= esc_html($btr_numero_giorni_libere ? $btr_numero_giorni_libere .' giorni - ' : ''); ?>
                                    <?= esc_html($btr_numero_giorni_fisse ? $btr_numero_giorni_fisse .' giorni - ' : ''); ?>
                                    <?= esc_html($btr_numero_notti ? $btr_numero_notti . ' notti' : ''); ?></div>
                                <div class="clear"></div>
                            </div>
                            <div class="iwithtext">
                                <div class="iwt-icon"><img decoding="async" src="https://borntoride.labuix.com/wp-content/uploads/2024/10/ICON-prezzo-300x300.png" alt=""></div>
                                <div class="iwt-text"><strong>PREZZO</strong>: a partire da <?= $min_adult_price ? '€' . number_format($min_adult_price, 0, ',', '.') : '749€'; ?></div>
                                <div class="clear"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <div class="timeline">
            <div class="line"></div>
            <div class="step step1 active">
                <span class="step-number">01</span>
            </div>
            <div class="step step2">
                <span class="step-number">02</span>
            </div>
            <div class="step step3">
                <span class="step-number">03</span>
            </div>
            <div class="step step4">
                <span class="step-number">04</span>
            </div>
        </div>




        <div class="wpb_wrapper ps-1">
            <h2 id="title-step" style="color: #0097c5;text-align: left; font-size: 30px; margin-bottom:0" class="vc_custom_heading vc_do_custom_heading">Quando vorresti
                partire?</h2>
            <p id="desc-step">Scegli la partenza più adatta alle tue esigenze</p>
        </div>

        <form id="btr-booking-form" class="btr-booking-form">
            <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD); ?>
            <input type="hidden" name="btr_package_id" value="<?php echo esc_attr($package_id); ?>">
            <input type="hidden" name="btr_product_id" value="<?php echo esc_attr($product_id); ?>">
            <input type="hidden" name="btr_tipologia_prenotazione" value="<?php echo esc_attr($tipologia_prenotazione); ?>">
            <?php
            $durata_str = '';
            $durata_str .= $btr_numero_giorni ? $btr_numero_giorni . ' giorni - ' : '';
            $durata_str .= $btr_numero_giorni_libere ? $btr_numero_giorni_libere . ' giorni - ' : '';
            $durata_str .= $btr_numero_giorni_fisse ? $btr_numero_giorni_fisse . ' giorni - ' : '';
            $durata_str .= $btr_numero_notti ? $btr_numero_notti . ' notti' : '';
            ?>
            <input type="hidden" name="btr_durata" value="<?php echo esc_attr(trim($durata_str)); ?>">
            <?php
            $destinazione = is_array($btr_destinazione) ? implode(', ', $btr_destinazione) : $btr_destinazione;
            ?>
            <input type="hidden" name="btr_nome_pacchetto" value="<?php echo esc_attr($destinazione . ' - ' . ($localita_destinazione ?? '')); ?>">

            <input type="hidden" name="btr_date_ranges_id" id="btr_date_ranges_id" value="">
            <input type="hidden" id="btr_selected_variant_id" name="selected_variant_id" value="">
            <input type="hidden" id="btr_selected_date" name="btr_selected_date" value="">


            <!-- Step 1: Modern Date Selector (2025 Style) -->
           <div class="btr-field-group">
                <div class="btr-date-selector-2025">
                    <div class="btr-carousel-wrapper" style="position: relative;">
                      <button class="btr-carousel-prev" aria-label="Previous date" style="position:absolute; left:-2%; top:40%; transform:translateY(-50%); z-index:2;">&lt;
                      </button>
                    <div class="btr-date-card-container">
                        <?php
                        foreach ($dates as $date => $data) :
                            // Dati della variante
                            $variant_id     = $data['variant_id'] ?? '';
                            $is_sold_out    = !empty($data['is_closed']);
                            $label_to_show  = !empty($data['label']) ? $data['label'] : 'Sold Out';

                            // Calcola chiusura basata su end_date e close_days
                            $close_days     = get_post_meta($package_id, 'btr_booking_close_days', true);
                            $close_days     = is_numeric($close_days) ? intval($close_days) : 7;
                            $raw_ranges     = get_post_meta($package_id, 'btr_date_ranges', true);
                            $btr_date_ranges = is_string($raw_ranges) ? maybe_unserialize($raw_ranges) : $raw_ranges;
                            if (!is_array($btr_date_ranges)) {
                                $btr_date_ranges = [];
                            }
                            $end_date = '';
                            foreach ($btr_date_ranges as $range) {
                                if (($range['name'] ?? '') === $date) {
                                    $end_date = $range['end'] ?? '';
                                    break;
                                }
                            }
                            // Determina se la data è passata o rientra nel periodo di chiusura
                            $is_past = false;
                            $is_soon_closed = false;
                            if ($end_date) {
                                try {
                                    $now     = new DateTime('now', wp_timezone());
                                    $end_obj = new DateTime($end_date, wp_timezone());
                                    $diff    = $now->diff($end_obj);
                                    if ($diff->invert === 1) {
                                        $is_past = true;
                                    } elseif ($diff->days <= $close_days) {
                                        $is_soon_closed = true;
                                    }
                                } catch (Exception $e) {
                                    // in caso di errore, lascia valori a false
                                }
                            }

                            /* --------------------------------------------------------------
                             *  ALL / SOLD OUT based on global allotment (allotment_camere)
                             * -------------------------------------------------------------- */
                            if ( $tipologia_prenotazione === 'allotment_camere' ) {
                                // Trova la data di inizio del range corrente (YYYY-MM-DD)
                                $start_date = '';
                                foreach ( $btr_date_ranges as $range_tmp ) {
                                    if ( ($range_tmp['name'] ?? '') === $date ) {
                                        $start_date = $range_tmp['start'] ?? '';
                                        break;
                                    }
                                }

                                if ( $start_date !== '' ) {
                                    // Recupera allotment dal meta avanzato "btr_camere_allotment"
                                    $camere_allotment = get_post_meta( $package_id, 'btr_camere_allotment', true );
                                    $camere_allotment = is_array( $camere_allotment ) ? $camere_allotment : [];

                                    // Fallback legacy: array semplice btr_allotment_totale
                                    if ( empty( $camere_allotment ) ) {
                                        $legacy_allot = get_post_meta( $package_id, 'btr_allotment_totale', true );
                                        $legacy_allot = is_array( $legacy_allot ) ? $legacy_allot : [];
                                        if ( isset( $legacy_allot[ $start_date ] ) ) {
                                            $camere_allotment[ $start_date ] = [ 'totale' => intval( $legacy_allot[ $start_date ] ) ];
                                        }
                                    }

                                    $totale_allot = isset( $camere_allotment[ $start_date ]['totale'] )
                                        ? intval( $camere_allotment[ $start_date ]['totale'] )
                                        : null;

                                    if ( $totale_allot !== null && $totale_allot === 0 ) {
                                        $is_closed     = 1;
                                        $is_sold_out   = 1;
                                        $label_to_show = 'Sold Out';
                                    }
                                }
                            }

                            $is_date_closed = $is_past || $is_soon_closed;
                            $is_closed = $is_sold_out || $is_date_closed;
                            ?>
                            <div class="btr-date-card <?php echo $is_closed ? ' disabled' : ''; ?>"
                                 data-date="<?php echo esc_attr($date); ?>"
                                 data-variant-id="<?php echo esc_attr($variant_id); ?>"
                                 <?php if ($is_closed): ?>
                                     data-disabled="true"
                                     data-message="<?php echo esc_attr($is_past ? 'Non è possibile prenotare.' : 'Prenotazioni chiuse per questa data.'); ?>"
                                 <?php endif; ?>>
                                <div class="btr-date-day"><?php echo esc_html($date); ?></div>
                                <?php if ($is_sold_out): ?>
                                    <div class="btr-date-badge"><?php echo esc_html($label_to_show); ?></div>
                                <?php elseif ($is_soon_closed): ?>
                                    <div class="btr-date-badge">Chiuso</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                      <button class="btr-carousel-next" aria-label="Next date" style="position:absolute; right:-2%; top:40%; transform:translateY(-50%); z-index:2;">&gt;</button>
                    </div> <!-- .btr-carousel-wrapper -->
                    <div class="btr-carousel-dots" style="text-align:center;    margin-top: -18px;    margin-bottom: 25px;"></div>
                    <div class="btr-date-selection-info">
                        <div class="btr-selected-date-display">
                            <div class="btr-date-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                    <line x1="16" y1="2" x2="16" y2="6"></line>
                                    <line x1="8" y1="2" x2="8" y2="6"></line>
                                    <line x1="3" y1="10" x2="21" y2="10"></line>
                                </svg>
                            </div>
                            <div class="btr-selected-date-text">Seleziona una data di partenza</div>
                        </div>
                    </div>
                </div>
            </div>
            <style>
                #btr-booking-form.btr-booking-form {
                    margin-bottom: 5em;
                }
                /* Date Selector 2025 Styles */
                .btr-date-selector-2025 {
                    margin-bottom: 2.5rem;
                    font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                }

                .btr-date-card-container {
                    display: flex;
                    flex-wrap: nowrap;
                    overflow-x: auto;
                    gap: 14px;
                    padding: 10px 0.2em 26px;
                    margin-bottom: 18px;
                    scrollbar-width: thin;
                    scrollbar-color: #0097c5 #f0f5fa;
                    scroll-behavior: smooth;
                    -webkit-overflow-scrolling: touch;
                }

                .btr-date-card-container::-webkit-scrollbar {
                    height: 6px;
                }

                .btr-date-card-container::-webkit-scrollbar-track {
                    background: #f0f5fa;
                    border-radius: 10px;
                }

                .btr-date-card-container::-webkit-scrollbar-thumb {
                    background: #0097c5;
                    border-radius: 10px;
                }

                .btr-date-card {
                    flex: 0 0 auto;
                    padding: 10px 15px;
                    border-radius: 10px;
                    background: #f8fafe;
                    box-shadow: 0 4px 12px rgba(0, 151, 197, 0.08);
                    text-align: center;
                    cursor: pointer;
                    transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1);
                    position: relative;
                    border: 2px solid #e5e7eb;
                    overflow: hidden;
                    display: flex;
                    flex-direction: row;
                    gap: 10px;
                    flex-wrap: wrap;
                    align-items: center;
                }

                .btr-date-card:hover {
                    transform: translateY(-4px);
                    box-shadow: 0 8px 24px rgba(0, 151, 197, 0.12);
                    background: #f0f9ff;
                }

                .btr-date-card.selected {
                    background: #e1f6ff;
                    border-color: #0097c5;
                    box-shadow: 0 8px 24px rgba(0, 151, 197, 0.2);
                }

                .btr-date-card.selected::after {
                    content: '';
                    position: absolute;
                    top: 0;
                    right: 0;
                    width: 0;
                    height: 0;
                    border-style: solid;
                    border-width: 0 24px 24px 0;
                    border-color: transparent #0097c5 transparent transparent;
                }


                .btr-date-badge {
                    position: relative;
                    top: 0;
                    right: 0;
                    background: #e53935;
                    color: white;
                    font-size: 11px;
                    font-weight: 600;
                    padding: 0px 10px;
                    border-radius: 0 12px 0 12px;
                    z-index: 1;
                    letter-spacing: 0.5px;
                    transform: translateY(-2px) translateX(2px);
                    text-transform: uppercase;
                }

                .btr-badge-soldout span {
                    background: linear-gradient(135deg, #9e0b0f 0%, #cc1c1e 100%);
                    color: #fff;
                    font-size: 0.9rem;
                    font-weight: 700;
                    padding: 0.35em 1.4em;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(204, 28, 30, 0.25);
                    text-transform: uppercase;
                    font-family: 'Inter', sans-serif;
                    transform: rotate(-1.5deg);
                    display: inline-block;
                    margin-left: 0.8em;
                }

                .btr-badge-last-one span {
                    background: linear-gradient(135deg, #ff9800 0%, #ef6c00 100%);
                    color: #fff;
                    font-size: 0.9rem;
                    font-weight: 700;
                    padding: 0.35em 1.4em;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(239, 108, 0, 0.25);
                    text-transform: uppercase;
                    font-family: 'Inter', sans-serif;
                    transform: rotate(-1.5deg);
                    display: inline-block;
                    margin-left: 0.8em;
                }

                .btr-badge-few-left span {
                    background: linear-gradient(135deg, #ffb74d 0%, #ffa726 100%);
                    color: #fff;
                    font-size: 0.9rem;
                    font-weight: 700;
                    padding: 0.35em 1.4em;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(255, 167, 38, 0.25);
                    text-transform: uppercase;
                    font-family: 'Inter', sans-serif;
                    transform: rotate(-1.5deg);
                    display: inline-block;
                    margin-left: 0.8em;
                }

                .btr-badge-available span {
                    background: linear-gradient(135deg, #4caf50 0%, #388e3c 100%);
                    color: #fff;
                    font-size: 0.9rem;
                    font-weight: 700;
                    padding: 0.35em 1.4em;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(56, 142, 60, 0.25);
                    text-transform: uppercase;
                    font-family: 'Inter', sans-serif;
                    transform: rotate(-1.5deg);
                    display: inline-block;
                    margin-left: 0.8em;
                }

                .btr-badge-info span {
                    background: linear-gradient(135deg, #2196f3 0%, #1976d2 100%);
                    color: #fff;
                    font-size: 0.9rem;
                    font-weight: 700;
                    padding: 0.35em 1.4em;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(33, 150, 243, 0.25);
                    text-transform: uppercase;
                    font-family: 'Inter', sans-serif;
                    transform: rotate(-1.5deg);
                    display: inline-block;
                    margin-left: 0.8em;
                }

                .btr-badge-gray-light span {
                    background: #f5f5f5;
                    color: #333;
                    font-size: 0.9rem;
                    font-weight: 700;
                    padding: 0.35em 1.4em;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                    text-transform: uppercase;
                    font-family: 'Inter', sans-serif;
                    transform: rotate(-1.5deg);
                    display: inline-block;
                    margin-left: 0.8em;
                }

                .btr-badge-gray-dark span {
                    background: #616161;
                    color: #fff;
                    font-size: 0.9rem;
                    font-weight: 700;
                    padding: 0.35em 1.4em;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
                    text-transform: uppercase;
                    font-family: 'Inter', sans-serif;
                    transform: rotate(-1.5deg);
                    display: inline-block;
                    margin-left: 0.8em;
                }

                .btr-badge-black span {
                    background: #000000;
                    color: #fff;
                    font-size: 0.9rem;
                    font-weight: 700;
                    padding: 0.35em 1.4em;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
                    text-transform: uppercase;
                    font-family: 'Inter', sans-serif;
                    transform: rotate(-1.5deg);
                    display: inline-block;
                    margin-left: 0.8em;
                }

                .btr-badge-purple span {
                    background: linear-gradient(135deg, #9c27b0 0%, #7b1fa2 100%);
                    color: #fff;
                    font-size: 0.9rem;
                    font-weight: 700;
                    padding: 0.35em 1.4em;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(156, 39, 176, 0.25);
                    text-transform: uppercase;
                    font-family: 'Inter', sans-serif;
                    transform: rotate(-1.5deg);
                    display: inline-block;
                    margin-left: 0.8em;
                }

                .btr-badge-teal span {
                    background: linear-gradient(135deg, #009688 0%, #00796b 100%);
                    color: #fff;
                    font-size: 0.9rem;
                    font-weight: 700;
                    padding: 0.35em 1.4em;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0, 150, 136, 0.25);
                    text-transform: uppercase;
                    font-family: 'Inter', sans-serif;
                    transform: rotate(-1.5deg);
                    display: inline-block;
                    margin-left: 0.8em;
                }

                .btr-badge-yellow span {
                    background: linear-gradient(135deg, #ffeb3b 0%, #fbc02d 100%);
                    color: #333;
                    font-size: 0.9rem;
                    font-weight: 700;
                    padding: 0.35em 1.4em;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(255, 235, 59, 0.25);
                    text-transform: uppercase;
                    font-family: 'Inter', sans-serif;
                    transform: rotate(-1.5deg);
                    display: inline-block;
                    margin-left: 0.8em;
                }

                .btr-badge-pink span {
                    background: linear-gradient(135deg, #ec407a 0%, #d81b60 100%);
                    color: #fff;
                    font-size: 0.9rem;
                    font-weight: 700;
                    padding: 0.35em 1.4em;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(236, 64, 122, 0.25);
                    text-transform: uppercase;
                    font-family: 'Inter', sans-serif;
                    transform: rotate(-1.5deg);
                    display: inline-block;
                    margin-left: 0.8em;
                }

                .btr-badge-indigo span {
                    background: linear-gradient(135deg, #3f51b5 0%, #303f9f 100%);
                    color: #fff;
                    font-size: 0.9rem;
                    font-weight: 700;
                    padding: 0.35em 1.4em;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(63, 81, 181, 0.25);
                    text-transform: uppercase;
                    font-family: 'Inter', sans-serif;
                    transform: rotate(-1.5deg);
                    display: inline-block;
                    margin-left: 0.8em;
                }

                .btr-badge-lime span {
                    background: linear-gradient(135deg, #cddc39 0%, #afb42b 100%);
                    color: #333;
                    font-size: 0.9rem;
                    font-weight: 700;
                    padding: 0.35em 1.4em;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(205, 220, 57, 0.25);
                    text-transform: uppercase;
                    font-family: 'Inter', sans-serif;
                    transform: rotate(-1.5deg);
                    display: inline-block;
                    margin-left: 0.8em;
                }

                .btr-badge-brown span {
                    background: linear-gradient(135deg, #795548 0%, #5d4037 100%);
                    color: #fff;
                    font-size: 0.9rem;
                    font-weight: 700;
                    padding: 0.35em 1.4em;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(121, 85, 72, 0.25);
                    text-transform: uppercase;
                    font-family: 'Inter', sans-serif;
                    transform: rotate(-1.5deg);
                    display: inline-block;
                    margin-left: 0.8em;
                }

                .btr-date-weekday {
                    font-size: 13px;
                    color: #6b7280;
                    text-transform: uppercase;
                    margin-bottom: 4px;
                }

                .btr-date-day {
                    font-size: 1em;
                    font-weight: 700;
                    color: #0097c5;
                    line-height: 1.2;
                }

                .btr-date-month {
                    font-size: 14px;
                    font-weight: 600;
                    text-transform: uppercase;
                    margin-top: 2px;
                    color: #374151;
                }

                .btr-date-year {
                    font-size: 13px;
                    color: #6b7280;
                    margin-top: 2px;
                }

                .btr-date-card.btr-date-special {
                    background: linear-gradient(145deg, #f0f9ff, #e1f6ff);
                }

                .btr-date-card.btr-date-special .btr-date-day {
                    color: #0082ab;
                }

                .btr-date-selection-info {
                    background: #f8fafc;
                    border-radius: 12px;
                    padding: 16px 20px;
                    display: flex;
                    align-items: center;
                    border: 1px solid #e5e7eb;
                    margin: 5px 0 16px;
                }

                .btr-selected-date-display {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                }

                .btr-date-icon {
                    color: #0097c5;
                    display: flex;
                    align-items: center;
                }

                .btr-selected-date-text {
                    font-size: 16px;
                    font-weight: 500;
                    color: #6b7280;
                }

                .btr-selected-date-display.has-selection .btr-selected-date-text {
                    color: #111827;
                    font-weight: 600;
                }

                /* Responsive adjustments */
                @media (max-width: 640px) {
                    .btr-date-card {
                        width: 100px;
                        padding: 12px 6px;
                    }

                    .btr-date-day {
                        font-size: 24px;
                    }

                    .btr-date-month, .btr-date-weekday {
                        font-size: 12px;
                    }

                    .btr-date-year {
                        font-size: 11px;
                    }
                }

                @media (max-width: 480px) {
                    .btr-date-card-container {
                        gap: 10px;
                    }

                    .btr-date-card {
                        width: 90px;
                        padding: 10px 4px;
                    }
                }
                /* Stile per la data chiusa */
                .btr-date-label-closed span {
                    background-color: #e53935;
                    color: #fff;
                    padding: 0px 1.5em;
                    font-weight: bold;
                    display: inline-block;
                    text-transform: uppercase;
                    margin-left: .5em;
                    transform: rotate(-2deg);
                }
                .btr-sold-out-label span {
                    padding: .2em 1.5em;
                }


                .btr-badge-warning span {
                    display: inline-block;
                    padding: 0.35em 1.4em;
                    font-size: 0.9rem;
                    font-weight: 700;
                    color: #fff;
                    background: linear-gradient(135deg, #e53935 0%, #d32f2f 100%);
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(229, 57, 53, 0.25);
                    letter-spacing: 0.05em;
                    text-transform: uppercase;
                    position: relative;
                    transition: all 0.2s ease-in-out;
                    font-family: 'Inter', 'Segoe UI', sans-serif;
                    transform: rotate(-1.5deg);
                    margin-left: 0.8em;
                }

                /* Responsive: migliora visibilità su mobile */
                @media (max-width: 480px) {
                    .btr-badge-warning span {
                        font-size: 0.8rem;
                        padding: 0.3em 1em;
                    }
                }

                /* Stile per date disabilitate */
                .btr-date-card.disabled {
                    opacity: 0.5;
                    cursor: not-allowed;
                }
                .btr-date-card.disabled:hover {
                    transform: none;
                    box-shadow: none;
                    background: #f8f8f8;
                }

                /* Carousel arrows */
                .btr-carousel-prev,
                .btr-carousel-next {
                  position: absolute;
                  top: 40%;
                  transform: translateY(-50%);
                  background: #ffffff;
                  border: 2px solid #0097c5;
                  color: #0097c5;
                  width: 32px;
                  height: 32px;
                  border-radius: 50%;
                  display: flex;
                  align-items: center;
                  justify-content: center;
                  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
                  cursor: pointer;
                  transition: background-color 0.2s, transform 0.2s;
                  z-index: 2;
                }

                .btr-carousel-prev:hover,
                .btr-carousel-next:hover {
                  background: #0097c5;
                  color: #ffffff;
                  transform: translateY(-50%) scale(1.1);
                }

                .btr-carousel-prev {
                  left: 8px;
                }

                .btr-carousel-next {
                  right: 8px;
                }

                /* Carousel dots */
                .btr-carousel-dots {
                  text-align: center;
                  margin-top: 12px;
                }

                .btr-carousel-dot {
                  display: inline-block;
                  width: 10px;
                  height: 10px;
                  margin: 0 6px;
                  background: #e1e1e1;
                  border-radius: 50%;
                  cursor: pointer;
                  transition: background-color 0.3s, transform 0.3s;
                }

                .btr-carousel-dot.active {
                  background: #0097c5;
                  transform: scale(1.2);
                }

                .btr-carousel-dot:hover {
                  background: #0097c5;
                }

                /* Disabled arrow state */
                .btr-carousel-prev:disabled,
                .btr-carousel-next:disabled {
                    cursor: default;
                    pointer-events: none;
                    background: #f8fafc;
                    border: 2px solid #e5e7eb;
                }

                /* Drag-to-scroll cursor */
                .btr-date-card-container.dragging {
                  cursor: grabbing;
                  cursor: -webkit-grabbing;
                }
            </style>


           <script>
           jQuery(document).ready(function($) {


                // Handle the date selection
                $('.btr-date-card').on('click', function() {
                    // Remove selection from all cards
                    $('.btr-date-card').removeClass('selected');

                    // Add selection to clicked card
                    $(this).addClass('selected');

                    // Get date and variant ID
                    const selectedDate = $(this).data('date');
                    const variantId = $(this).data('variant-id');
                    const label = $(this).find('.btr-date-badge').text().trim();
                    const messageText = $(this).data('message') || '';

                    if ($(this).hasClass('disabled') || $(this).data('disabled') === true) {
                        const parts = [selectedDate];
                        if (label) parts.push(`<span class="btr-date-badge">${label}</span>`);
                        if (messageText) parts.push(`<em class="btr-date-message">${messageText}</em>`);
                        $('.btr-selected-date-text').html(parts.join(' '));
                        $('.btr-selected-date-display').addClass('has-selection');

                        // Nasconde le sezioni successive per date disabilitate
                        $('#btr-num-people-section,.custom-dropdown-wrapper').fadeOut(300);

                        // Mostra notifica di errore
                        if (typeof window.showNotification === 'function') {
                            const errorMessage = messageText || 'Questa data non è disponibile per la prenotazione.';
                            window.showNotification(errorMessage, 'error');
                        }
                        return;
                    }

                    // Update hidden inputs
                    $('#btr_date').val(selectedDate);
                    $('#btr_selected_date').val(selectedDate);
                    $('#btr_selected_variant_id').val(variantId);
                    
                    // Trigger change event to activate extra night verification
                    $('#btr_date').trigger('change');

                    // Update display text with selected date
                    const parts = [selectedDate];
                    if (label) parts.push(`<span class="btr-date-badge">${label}</span>`);
                    if (messageText) parts.push(`<em class="btr-date-message">${messageText}</em>`);
                    $('.btr-selected-date-text').html(parts.join(' '));

                    $('.btr-selected-date-display').addClass('has-selection');
                    // Assicurati che venga mostrato il pannello utenti
                    $('#btr-num-people-section').fadeIn(300);

                    // Update step navigation if you have it
                    $('.step').removeClass('active');
                    $('.step2').addClass('active');

                    // Scroll to next section smoothly
                    $('html, body').animate({
                        scrollTop: $('#btr-booking-form').offset().top - 150
                    }, 300);
                });

                /* -------------------------------------------------------------
                 * Auto‑select a date card via URL parameters
                 *   ?dtpc=<slugged-date>&idpc=<variantID>
                 * ----------------------------------------------------------- */
                /* -------------------------------------------------------------
                 * Auto‑select a date card via URL parameters
                 *   ?dtpc=<slugged-date>&idpc=<variantID>
                 * ----------------------------------------------------------- */
                (function autoSelectDateFromQuery () {

                    const params   = new URLSearchParams(window.location.search);
                    const dtParam  = params.get('dtpc');   // es.: "21-23-agosto-2025"
                    const idParam  = params.get('idpc');   // es.: "13323"
                    if (!dtParam && !idParam) { return; }

                    // Slugify helper (accent‑stripping, spaces → dashes, lowercase)
                    const slugify = str => str
                        .toString()
                        .toLowerCase()
                        .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                        .replace(/[^a-z0-9]+/g, '-')
                        .replace(/(^-|-$)+/g, '');

                    const dtSlug = dtParam ? slugify(decodeURIComponent(dtParam)) : null;

                    /**
                     * Attempts to find the relevant card and trigger its click.
                     * Returns true if a card was found and selected.
                     */
                    function trySelect () {
                        const $cards = jQuery('.btr-date-card');
                        if (!$cards.length) { return false; }

                        let $target = null;

                        // 1. Perfect match: date + variant ID
                        if (dtSlug && idParam) {
                            $target = $cards.filter(function () {
                                const $c = jQuery(this);
                                return slugify(($c.data('date')       || '').toString()) === dtSlug &&
                                       (($c.data('variant-id') || '').toString()) === idParam;
                            }).first();
                        }

                        // 2. Fallback: date only
                        if ((!$target || !$target.length) && dtSlug) {
                            $target = $cards.filter(function () {
                                return slugify((jQuery(this).data('date') || '').toString()) === dtSlug;
                            }).first();
                        }

                        // 3. Fallback: variant ID only
                        if ((!$target || !$target.length) && idParam) {
                            $target = $cards.filter(function () {
                                return ((jQuery(this).data('variant-id') || '').toString()) === idParam;
                            }).first();
                        }

                        if ($target && $target.length) {
                            $target.trigger('click'); // mimic manual selection
                            return true;
                        }
                        return false;
                    }

                    // Try immediately, otherwise poll up to ~4.5 s (15×)
                    if (trySelect()) { return; }

                    let attemptsLeft = 15;
                    const interval   = setInterval(() => {
                        if (trySelect() || --attemptsLeft <= 0) {
                            clearInterval(interval);
                        }
                    }, 300);

                })();

                // ► Carousel arrows & dots
                (function(){
                  const $container = $('.btr-date-card-container');
                  const $cards     = $container.find('.btr-date-card');
                  const $prev      = $('.btr-carousel-prev');
                  const $next      = $('.btr-carousel-next');
                  const $dotsWrap  = $('.btr-carousel-dots');

                  // Generate dots
                  $cards.each(function(i){
                    $dotsWrap.append('<span class="btr-carousel-dot" data-index="'+i+'" style="display:inline-block;width:8px;height:8px;margin:0 4px;border-radius:50%;' +
                        'background:#e1e1e1;cursor:pointer;"></span>');
                  });
                  const $dots = $dotsWrap.find('.btr-carousel-dot');

                  function updateDots(idx){
                    $dots.removeClass('active').css('background','#e1e1e1');
                    $dots.filter('[data-index="'+idx+'"]').addClass('active').css('background','#0097c5');
                    // Disabilita frecce agli estremi
                    $prev.prop('disabled', idx === 0);
                    // Disabilita freccia next se siamo all'ultima card o il container è già scrollato fino alla fine
                    const maxScrollLeft = $container[0].scrollWidth - $container.innerWidth();
                    const currentScroll = Math.round($container.scrollLeft());
                    const atEnd = currentScroll >= maxScrollLeft;
                    $next.prop('disabled', idx === $cards.length - 1 || atEnd);
                  }

                  // Helper to scroll to card index
                  function scrollToIndex(idx){
                    const w = $cards.outerWidth(true);
                    $container.animate({scrollLeft: idx * w}, 300, function(){
                      updateDots(idx);
                    });
                  }

                  // Prev/Next
                  $next.on('click', function(){
                    const cur = Math.round($container.scrollLeft() / $cards.outerWidth(true));
                    scrollToIndex(Math.min(cur + 1, $cards.length - 1));
                  });
                  $prev.on('click', function(){
                    const cur = Math.round($container.scrollLeft() / $cards.outerWidth(true));
                    scrollToIndex(Math.max(cur - 1, 0));
                  });

                  // Dot click
                  $dots.on('click', function(){
                    scrollToIndex(parseInt($(this).data('index'), 10));
                  });

                  // Sync dots on scroll
                  $container.on('scroll', function(){
                    const idx = Math.round($container.scrollLeft() / $cards.outerWidth(true));
                    updateDots(idx);
                  });

                  // ► Drag-to-scroll for desktop
                  let isDown = false, startX, scrollLeft;
                  $container.on('mousedown', function(e){
                    isDown = true;
                    $container.addClass('dragging');
                    startX = e.pageX - $container.offset().left;
                    scrollLeft = $container.scrollLeft();
                  });
                  $container.on('mouseleave mouseup', function(){
                    isDown = false;
                    $container.removeClass('dragging');
                  });
                  $container.on('mousemove', function(e){
                    if (!isDown) return;
                    e.preventDefault();
                    const x = e.pageX - $container.offset().left;
                    const walk = (x - startX);
                    $container.scrollLeft(scrollLeft - walk);
                  });

                  // Initialize
                  updateDots(0);
                })();
            });
            </script>




                
<?php if ( $tipologia_prenotazione === 'allotment_camere' ) : ?>
                <style>

                    .custom-dropdown-wrapper {
                        background-color: #e1f6ff;
                        border: 2px solid #0097c5;
                        border-radius: 12px;
                        padding: 20px 25px;
                        box-shadow: 0 8px 24px rgba(0, 151, 197, 0.2);
                        transition: all 0.3s ease-in-out;
                        margin-bottom: 3em;
                    }

                    .custom-dropdown-wrapper:hover {
                        transform: translateY(-2px);
                    }

                    .custom-dropdown-label {
                        margin-bottom: 8px;
                        display: block;
                        font-size: 1.2em !important;
                        font-weight: 700 !important;
                        color: #0097c5 !important;
                        line-height: 1.2 !important;
                    }

                    .custom-dropdown-description {
                        font-size: 0.9rem;
                        color: #007aa3;
                        opacity: 0.8;
                        padding-bottom: 12px;
                    }

                    .custom-dropdown {
                        position: relative;
                        width: 100%;
                        cursor: pointer;
                    }

                    .dropdown-button {
                        width: 100%;
                        padding: 12px 16px;
                        font-size: 1rem;
                        color: #007aa3; /* Colore testo principale */
                        background-color: #f8fafc;
                        border: none;
                        outline: none;
                        text-align: left;
                        border-radius: 8px;
                        transition: background-color 0.3s ease;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                    }

                    .dropdown-button:hover,
                    .dropdown-button:focus {
                        background-color: rgba(0, 151, 197, 0.05); /* Sfondo su hover/focus */
                    }

                    .dropdown-arrow {
                        width: 0;
                        height: 0;
                        border-left: 6px solid transparent;
                        border-right: 6px solid transparent;
                        border-top: 8px solid #007aa3;
                        transition: transform 0.3s ease;
                    }

                    .dropdown-menu {
                        position: absolute;
                        top: 100%;
                        left: 0;
                        right: 0;
                        margin-top: 4px;
                        background-color: #fff;
                        border: 1px solid #cce0f0;
                        border-radius: 8px;
                        overflow: hidden;
                        box-shadow: 0 4px 12px rgba(0, 151, 197, 0.15);
                        z-index: 1000;
                        display: none;
                        animation: fadeIn 0.2s ease forwards;
                    }

                    .dropdown-menu.show {
                        display: block;
                    }

                    .dropdown-item {
                        padding: 10px 16px;
                        color: #007aa3;
                        cursor: pointer;
                        transition: background-color 0.2s ease;
                    }

                    .dropdown-item:hover {
                        background-color: rgba(0, 151, 197, 0.05);
                    }

                    @keyframes fadeIn {
                        from { opacity: 0; transform: translateY(-5px); }
                        to { opacity: 1; transform: translateY(0); }
                    }
                </style>


            <div class="custom-dropdown-wrapper" style="display: none;">
                <label class="custom-dropdown-label" for="dropdown-trigger">
                    Vuoi aggiungere il pernottamento extra del venerdì?
                </label>
                <p class="custom-dropdown-description">
                    Se selezionato, verrà aggiunto un supplemento per persona, soggetto a disponibilità.
                </p>

                <div class="custom-dropdown" id="dropdown-trigger">
                    <div class="dropdown-button" tabindex="0">
                        <span id="dropdown-display">No, solo pacchetto base</span>
                        <div class="dropdown-arrow"></div>
                    </div>
                    <div class="dropdown-menu">
                        <div class="dropdown-item" data-value="0"><strong>No</strong>, solo pacchetto base</div>
                        <div class="dropdown-item" data-value="1" data-date="2025-02-13"><strong>Sì</strong>, aggiungi la notte extra</div>
                    </div>
                </div>

                <!-- Hidden input per invio form -->
                <input type="hidden" id="btr_add_extra_night" name="btr_add_extra_night" value="0" />
            </div>


            <script>
                const dropdown = document.getElementById('dropdown-trigger');
                const dropdownButton = dropdown.querySelector('.dropdown-button');
                const dropdownMenu = dropdown.querySelector('.dropdown-menu');
                const dropdownDisplay = dropdown.querySelector('#dropdown-display');
                const hiddenInput = document.getElementById('btr_add_extra_night');

                dropdownButton.addEventListener('click', () => {
                    dropdownMenu.classList.toggle('show');
                    dropdownButton.querySelector('.dropdown-arrow').style.transform = dropdownMenu.classList.contains('show')
                        ? 'rotate(180deg)'
                        : 'rotate(0deg)';
                });

                document.querySelectorAll('.dropdown-item').forEach(item => {
                    item.addEventListener('click', () => {
                        const value = item.getAttribute('data-value');
                        dropdownDisplay.textContent = item.textContent;
                        hiddenInput.value = value;
                        dropdownMenu.classList.remove('show');
                        dropdownButton.querySelector('.dropdown-arrow').style.transform = 'rotate(0deg)';
                    });
                });

                // Chiudi menu fuori click
                document.addEventListener('click', (e) => {
                    if (!dropdown.contains(e.target)) {
                        dropdownMenu.classList.remove('show');
                        dropdownButton.querySelector('.dropdown-arrow').style.transform = 'rotate(0deg)';
                    }
                });

                // Accessibilità: toggle con tastiera
                dropdownButton.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        dropdownMenu.classList.toggle('show');
                        dropdownButton.querySelector('.dropdown-arrow').style.transform = dropdownMenu.classList.contains('show')
                            ? 'rotate(180deg)'
                            : 'rotate(0deg)';
                    }
                });
            </script>

            <?php endif; // show dropdown only for allotment_camere ?>

            

            <!-- Step 2: Inserisci il numero di persone -->
            <div class="btr-field-group" id="btr-num-people-section" style="display:none;">
                <div class="btr-box-numeri-wrapper">
                    <?php if ($btr_ammessi_adulti): ?>
                        <div class="btr-box-numeri">
                            <label for="btr_num_adults"><?php esc_html_e('Numero di Adulti', 'born-to-ride-booking'); ?></label>
                            <div class="btr-number-control" data-control="btr_num_adults">
                                <button type="button" class="btr-minus" data-target="#btr_num_adults">–</button>
                                <input type="number" id="btr_num_adults" name="btr_num_adults" min="0" value="0" required>
                                <button type="button" class="btr-plus" data-target="#btr_num_adults">+</button>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($btr_ammessi_bambini):

                    $infants_enabled = get_post_meta($package_id, 'btr_infanti_enabled', true) === '1';
                    $f1_enabled = get_post_meta($package_id, 'btr_bambini_fascia1_sconto_enabled', true) === '1';
                    $f2_enabled = get_post_meta($package_id, 'btr_bambini_fascia2_sconto_enabled', true) === '1';

                    $show_children_select = $infants_enabled || $f1_enabled || $f2_enabled;
                    ?>
                    <?php if ($show_children_select) : ?>
                    <div class="btr-select-like btr-box-numeri" id="btr-children-select">
                        <label for="btr_num_adults"><?php esc_html_e('Numero di Bambini', 'born-to-ride-booking'); ?></label>
                        <!-- Area select chiuso ACCESSIBILE -->
                        <button type="button" id="btr-children-display" class="btr-select-display"
                                aria-haspopup="listbox" aria-expanded="false" aria-controls="btr-children-panel">
                            <span class="btr-children-label">Bambini: <strong>0</strong></span>
                            <span class="btr-arrow" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                            </span>
                        </button>

                        <!-- Panel input bambini -->
                        <div class="btr-select-panel" id="btr-children-panel" role="region" aria-label="Seleziona bambini" hidden>
                            <?php if ($infants_enabled == '1') : ?>
                                <div class="btr-box-numeri">
                                    <label for="btr_num_infants">Neonati (0–2)</label>
                                    <div class="btr-number-control" data-control="btr_num_infants">
                                        <button type="button" class="btr-minus" aria-label="Diminuisci neonati">–</button>
                                        <input type="number" id="btr_num_infants" name="btr_num_infants" min="0" value="0" readonly />
                                        <button type="button" class="btr-plus" aria-label="Aumenta neonati">+</button>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php
                            // Ciclo completo per tutte le fasce bambini con variabili pertinenti
                            for ($i = 1; $i <= 4; $i++):
                                $label = get_post_meta($package_id, "btr_bambini_fascia{$i}_label", true);
                                $eta_min = get_post_meta($package_id, "btr_bambini_fascia{$i}_eta_min", true);
                                $eta_max = get_post_meta($package_id, "btr_bambini_fascia{$i}_eta_max", true);
                                $sconto = get_post_meta($package_id, "btr_bambini_fascia{$i}_sconto", true);
                                $enabled = get_post_meta($package_id, "btr_bambini_fascia{$i}_sconto_enabled", true);
                                if ($enabled !== '1') continue;
                                $input_id = "btr_num_child_f{$i}";
                                $input_name = "btr_num_child_f{$i}";
                                ?>
                                <?php
                                // v1.0.188: Genera etichetta corretta per data attributes
                                $dynamic_label = '';
                                if ($eta_min !== '' && $eta_max !== '') {
                                    $dynamic_label = $eta_min . '-' . $eta_max;
                                } elseif ($eta_min !== '') {
                                    $dynamic_label = $eta_min . '+';
                                } elseif ($eta_max !== '') {
                                    $dynamic_label = '0-' . $eta_max;
                                }
                                // Se non c'è range età, usa l'etichetta del pacchetto
                                if (empty($dynamic_label) && !empty($label)) {
                                    $dynamic_label = $label;
                                }
                                ?>
                                <div class="btr-box-numeri btr-child-group" data-fascia="f<?php echo $i; ?>" data-label="<?php echo esc_attr($dynamic_label); ?>">
                                    <label for="<?php echo esc_attr($input_id); ?>">
                                        <?php
                                        echo esc_html( "Bambini ");
                                        if ($eta_min !== '' || $eta_max !== '') {
                                            echo ' (';
                                            if ($eta_min !== '') {
                                                echo esc_html($eta_min);
                                            }
                                            echo '–';
                                            if ($eta_max !== '') {
                                                echo esc_html($eta_max);
                                            }
                                            echo ')';
                                        }
                                        ?>
                                    </label>
                                    <div class="btr-number-control" data-control="<?php echo esc_attr($input_id); ?>">
                                        <button type="button" class="btr-minus" aria-label="Diminuisci <?php echo esc_attr($label ?: "bambini fascia $i"); ?>">–</button>
                                        <input type="number"
                                               id="<?php echo esc_attr($input_id); ?>"
                                               name="<?php echo esc_attr($input_name); ?>"
                                               min="0"
                                               value="0"
                                               data-label="<?php echo esc_attr($dynamic_label); ?>"
                                               readonly />
                                        <button type="button" class="btr-plus" aria-label="Aumenta <?php echo esc_attr($label ?: "bambini fascia $i"); ?>">+</button>
                                    </div>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- CSS MODERNO -->
                    <style>
                            .info-neonati {
                                display: flex;
                                gap: 1rem;
                                padding: 1.25rem;
                                background-color: #e6f7fc;
                                margin-bottom: 1em;
                                border-radius: 8px;
                                color: #006d8f;
                                border: none;
                                box-shadow: 0 4px 6px -1px rgba(0, 151, 197, 0.05), 0 2px 4px -1px rgba(0, 151, 197, 0.03);
                            }
                                .btr-select-like {
                                    font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                                    max-width: 350px;
                                    margin-bottom: 1.8rem;
                                    position: relative;
                                }

                                .btr-select-like .btr-select-display {
                                    width: 100%;
                                    display: flex;
                                    gap: 0.6em;
                                    align-items: center;
                                    justify-content: space-between;
                                    border: 2px solid #e0e3ec;
                                    border-radius: 8px !important;
                                    padding: .6rem 1.4em;
                                    cursor: pointer;
                                    background: #fff;
                                    font-size: 1em;
                                    transition: border-color 0.2s, box-shadow 0.2s !important;
                                    box-shadow: 0 2px 16px #3258ad08 !important;
                                }
                                .btr-select-like .btr-select-display:focus-visible {
                                    box-shadow: 0 0 0 3px #2684ff48;
                                    border-color: #0097c5;
                                    outline: none;
                                }
                                .btr-select-like .btr-select-display strong {
                                    color: #0097c5;
                                    font-weight: 800;
                                    letter-spacing: 0.02em;
                                }
                                .btr-select-like .btr-arrow {
                                    margin-left: 1em;
                                    font-size: 1.3em;
                                    color: #6381c0;
                                    transition: transform .3s cubic-bezier(.4,0,.2,1);
                                    pointer-events: none;
                                }
                                .btr-select-like [aria-expanded="true"] .btr-arrow {
                                    transform: rotate(180deg);
                                }

                                .btr-select-like .btr-select-panel {
                                    z-index: 150;
                                    position: absolute;
                                    left: 0;
                                    top: calc(100% + .2rem);
                                    width: 100%;
                                    background: #fff;
                                    border: 1px solid rgba(244, 244, 244, 1);
                                    border-radius: 10px;
                                    box-shadow: var(--card-shadow);
                                    padding: 1em 1.2em 1em 1.2em;
                                    animation: btr-open .3s cubic-bezier(.4,0,.2,1);
                                }
                                .btr-select-like .btr-select-panel .btr-box-numeri {
                                    flex-direction: row;
                                    display: flex;
                                    flex-wrap: nowrap;
                                    align-items: center;
                                    justify-content: space-between;
                                }
                                @keyframes btr-open {
                                    from { transform: translateY(-10px); opacity:0;}
                                    to { transform: none; opacity:1;}
                                }

                                .btr-select-like .btr-box-numeri {
                                    margin-bottom: 1.2rem;
                                }
                                .btr-select-like .btr-box-numeri:last-child {
                                    margin-bottom: 0;
                                }


                                .btr-select-like .btr-field-group label {
                                    margin-bottom: 0;
                                }
                                .btr-select-like .btr-number-control {
                                    display: flex;
                                    align-items: center;
                                    gap: .2em;
                                    margin-top: 0.2em;
                                    border: none;
                                    width: auto;
                                }

                                .btr-select-like .btr-number-control input[type="number"] {
                                    width: 50px;
                                    height: 44px;
                                    text-align: center;
                                    border: 1px solid #e0e3ec;
                                    border-radius: 4px;
                                    background: #f0f5fa;
                                    font-weight: 500;
                                    pointer-events: none;
                                }
                                .btr-select-like input[type=number]::-webkit-inner-spin-button, input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; }
                                .btr-select-like input[type=number] { -moz-appearance: textfield; }

                                .btr-select-like  .btr-plus, .btr-minus {
                                    background-color: #0097c5;
                                    color: #fff;
                                    border: none;
                                    border-radius: 50%;
                                    width: 42px;
                                    height: 42px;
                                    font-size: 1.4rem;
                                    display: flex;
                                    align-items: center;
                                    justify-content: center;
                                    cursor: pointer;
                                    transition: background 0.22s, box-shadow 0.18s;
                                    box-shadow: 0 2px 6px #2d86fd18;
                                }
                                .btr-select-like .btr-plus:hover, .btr-minus:hover:enabled {
                                    background-color: #0097c5;
                                    box-shadow: 0 0 0 2px #3d9fff26, 0 8px 16px #2684ff22;
                                }
                                .btr-select-like .btr-plus:focus-visible, .btr-minus:focus-visible {
                                    outline: 2.5px solid #0097c5;
                                    outline-offset: 2px;
                                }

                                .btr-select-like .btr-plus[disabled], .btr-minus[disabled] {
                                    opacity: 0.42;
                                    cursor: not-allowed;
                                    box-shadow: none;
                                }

                                @media (max-width: 440px) {
                                    .btr-select-like { max-width: 100%; }
                                    .btr-select-like .btr-select-panel { padding: 1.1em 0.7em 0.6em; }
                                    .btr-select-like .btr-number-control { gap: 0.5em; }
                                    .btr-select-like .btr-number-control input[type="number"] { width: 46px; height: 36px; font-size: 1em;}
                                    .btr-select-like .btr-plus, .btr-minus { width: 34px; height: 34px; font-size: 1.11em;}
                                }
                    </style>

                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            const display = document.getElementById('btr-children-display');
                            const panel = document.getElementById('btr-children-panel');
                            const selectContainer = document.getElementById('btr-children-select');

                            function updateTotal() {
                                let total = 0;
                                panel.querySelectorAll('input[type="number"]').forEach(input => {
                                    total += parseInt(input.value, 10) || 0;
                                });
                                display.querySelector('.btr-children-label strong').textContent = total;
                            }

                            function openPanel() {
                                panel.hidden = false;
                                display.setAttribute('aria-expanded', 'true');
                                display.classList.add('open');
                            }
                            function closePanel() {
                                panel.hidden = true;
                                display.setAttribute('aria-expanded', 'false');
                                display.classList.remove('open');
                            }

                            display.addEventListener('click', function (e) {
                                e.stopPropagation();
                                const expanded = display.getAttribute('aria-expanded') === 'true';
                                if (expanded) {
                                    closePanel();
                                } else {
                                    openPanel();
                                }
                            });



                            // Aggiorna il totale all'avvio
                            updateTotal();

                            // Chiudi il panel se clicco fuori dalla select-like
                            document.addEventListener('mousedown', function(e) {
                                if (!selectContainer.contains(e.target)) {
                                    closePanel();
                                }
                            });

                            // Chiudi con ESC
                            document.addEventListener('keydown', function(e) {
                                if (e.key === 'Escape') {
                                    closePanel();
                                    display.focus();
                                }
                            });

                            // Accessibilità: focus out dal pannello chiude il panel (opzionale)
                            panel.addEventListener('focusout', function(e) {
                                if (!panel.contains(e.relatedTarget)) {
                                    closePanel();
                                }
                            });
                        });
                    </script>
                        <?php endif; ?>


                    <?php endif; ?>
                </div>
                <input type="hidden" id="btr_num_people" name="btr_num_people" min="1" required readonly>




                <div class="tilt-button-wrap action-prev">
                    <div class="tilt-button-inner">
                        <a id="btr-check-people"
                           class="nectar-button medium regular-tilt accent-color tilt regular-button instance-3 ld-ext-right"
                           role="button"
                           href="#"
                           style="visibility: visible;">
                            <span class="text">Verifica Disponibilità</span>
                            <div class="ld ld-ring"></div>
                        </a>
                    </div>
                </div>

            </div>







            <!-- Step 3: Seleziona le camere -->
            <div class="btr-field-group" id="btr-room-types-section" style="display: none;">
                <label>Seleziona le Camere</label>
                <div id="btr-room-types-container">



                    <!-- Le camere verranno caricate dinamicamente -->
                </div>

                <!--
                <div class="btr-room-total-wrap">
                    <p id="btr-total-capacity">Capacità Totale Selezionata: 0/<span id="btr-required-capacity">0</span></p>
                    <p id="btr-total-price">Prezzo Totale: <strong>€0.00</strong></p>
                </div>
                -->

                <style>
                    .btr-summary-box {
                        border-radius: 10px;
                        background: #fff;
                        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
                        padding: 1.5em;
                        margin-top: 2em;
                        max-width: 85%;
                        font-family: 'Inter', sans-serif;
                        border: 1px solid #e0e3ec;
                        transition: border-color 0.2s, box-shadow 0.2s !important;
                    }

                    .btr-summary-header {
                        display: flex;
                        align-items: center;
                        font-size: 1.2em;
                        font-weight: 600;
                        color: #333;
                        gap: 0.5em;
                    }

                    .btr-summary-label {
                        font-weight: 300;
                        font-size: 1em;
                        margin-right: 0.5em;
                    }

                    .btr-summary-value {
                        font-weight: 300;
                        font-size: 1em;
                        color: #333;
                    }

                    .btr-summary-divider {
                        border: none;
                        border-top: 1px solid #e5e5e5;
                        margin: 1em 0;
                    }

                    .btr-room-list {
                        margin-top: 0.3em;
                        margin-left: 2.5em;
                        font-weight: 500;
                        color: #444;
                        text-align: left;
                    }

                    .btr-summary-price {
                        margin-top: 1.5em;
                        background-color: #0097c5;
                        color: white;
                        padding: 0.6em 1em;
                        border-radius: 6px;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        font-weight: 700;
                        font-size: 1.4em;
                    }
                </style>
                <div class="btr-summary-box">
                    <div class="btr-summary-header">
                        <svg xmlns="http://www.w3.org/2000/svg" width="30" height="25" viewBox="0 0 24 24" fill="none" stroke="#0097c5" stroke-width="1.5" stroke-linecap="round"
                             stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                        <span class="btr-summary-label">Posti selezionati:</span>
                        <span class="btr-summary-value" id="btr-total-capacity-visual">0 su <span id="btr-required-capacity-visual">0</span></span>
                    </div>

                    <hr class="btr-summary-divider" />

                    <div class="btr-summary-rooms">
                        <div class="btr-summary-header">
                            <svg id="Raggruppa_22" data-name="Raggruppa 22" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="30" height="30
                            .651" viewBox="0 0 55 50.651"><defs><clipPath id="clip-path"><rect id="Rettangolo_24" data-name="Rettangolo 24" width="40" height="50.651" fill="#0097c5"></rect></clipPath></defs><g id="Raggruppa_21" data-name="Raggruppa 21"><path id="Tracciato_35" data-name="Tracciato 35" d="M55,32.061v14.9A4.408,4.408,0,0,1,47.229,49a5.013,5.013,0,0,1-.91-1.985V44.927H8.812v2.091A5.011,5.011,0,0,1,7.9,49,4.408,4.408,0,0,1,.132,46.964c.311-4.812-.416-10.153,0-14.9a12.943,12.943,0,0,1,3.32-7.132l0-18.121A7.643,7.643,0,0,1,10.148,0H44.663a7.628,7.628,0,0,1,6.8,6.809V24.715A12.905,12.905,0,0,1,55,32.061m-5.68-9.006V6.706c0-1.978-2.837-4.631-4.871-4.561l-34.285.016C8.334,2.241,5.6,4.8,5.6,6.6V23.27a7.043,7.043,0,0,1,2.238-1.084c.126-1.749-.234-3.652.286-5.343a5.747,5.747,0,0,1,4.92-3.977c3.5-.245,7.289.184,10.817.016a6.075,6.075,0,0,1,3.67,2.232c.235.04.466-.48.629-.634a6.244,6.244,0,0,1,3.119-1.6c3.543.19,7.419-.293,10.923-.015a5.816,5.816,0,0,1,4.841,4.055c.485,1.659.13,3.551.258,5.264ZM26.494,21.34V18.07a3.767,3.767,0,0,0-2.781-3.008c-3.39.1-7.1-.3-10.459-.052a3.51,3.51,0,0,0-2.876,1.947,8.707,8.707,0,0,0-.387,1.113v3.484c.66-.064,1.432-.194,2.085-.219,4.761-.185,9.645.148,14.418,0m18.647.214V18.07c0-1.495-1.835-2.953-3.264-3.06-3.335-.249-7,.172-10.372.033a3.853,3.853,0,0,0-2.868,2.92V21.34c4.805.151,9.733-.2,14.525,0,.619.025,1.352.158,1.978.219m7.716,14.8V32.222a11.478,11.478,0,0,0-1.414-4.053,9.946,9.946,0,0,0-8.387-4.689c-10.121-.534-20.7.413-30.873,0-4.469-.339-9.908,4.209-9.908,8.743V36.35h29.1a1.225,1.225,0,0,1,1.1,1.044c.031.328-.342,1.1-.674,1.1H2.275v4.288H52.857V38.494H41.765a1.555,1.555,0,0,1-.615-.563,1.162,1.162,0,0,1,.936-1.581ZM6.669,44.927H2.275V46.7c0,.037.231.6.278.686a2.252,2.252,0,0,0,3.777.154,6.054,6.054,0,0,0,.339-.733Zm46.188,0H48.463V46.8a6.054,6.054,0,0,0,.339.733,2.252,2.252,0,0,0,3.777-.154c.048-.091.278-.65.278-.686Z" transform="translate(0)" fill="#0097c5"></path><path id="Tracciato_36" data-name="Tracciato 36" d="M251.372,255.243a1.062,1.062,0,1,1-1.062-1.063,1.063,1.063,0,0,1,1.062,1.063" transform="translate(-213.548 -217.774)" fill="#0097c5"></path></g></svg>

                            <span class="btr-summary-label">Camere selezionate:</span>
                        </div>
                        <div id="btr-required-room-list" class="btr-room-list">
                            <!-- Qui verrà iniettato dinamicamente es: "1x Singola, 2x Doppia" -->
                        </div>

                        <!-- ▸ Breakdown Adulti/Bambini ------------------------------------ -->
                        <hr class="btr-summary-divider" />

                        <div class="btr-summary-header" id="btr-people-breakdown-header" style="margin-top:0.5em;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#0097c5" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                            <span class="btr-summary-label">Partecipanti:</span>
                        </div>
                        <div id="btr-selected-people-list" class="btr-room-list" style="margin-left:2.5em;"></div>
                    </div>

                    <div class="btr-summary-price">
                        <span class="btr-summary-label">PREZZO TOTALE:</span>
                        <span class="btr-summary-total" id="btr-total-price-visual">€0.00</span>
                    </div>
                </div>


            </div>


            <!-- Bottone di Procedi
            <button type="button" id="btr-proceed" class="button" style="display: none;">Procedi</button>-->
            <!-- Nuovo pulsante per generare la lista dei partecipanti con assicurazioni -->
            <div class="tilt-button-wrap action-prev">
                <div class="tilt-button-inner">
                    <a id="btr-generate-participants"
                       class="nectar-button medium regular-tilt accent-color tilt regular-button instance-3 ld-ext-right"
                       role="button"
                       href="#"
                       data-title="Assicurazioni" data-desc="Seleziona le assicurazioni per i partecipanti"
                       style="display: none;">
                        <span class="text">Continua</span>
                        <div class="ld ld-ring"></div>
                    </a>
                </div>
            </div>


            <!-- Contenitore dove verranno stampati dinamicamente i partecipanti -->
            <?php
            $btr_assicurazione_importi = get_post_meta($package_id, 'btr_assicurazione_importi', true);
            $btr_costi_extra = get_post_meta($package_id, 'btr_costi_extra', true);
            ?>
            <div id="btr-participants-wrapper" class="btr-participants-wrapper" style="margin-top: 2em;">
                <input type="hidden" name="action" value="btr_save_assicurazioni_temp">
                <div id="btr-assicurazioni-container">
                    <?php
                    $totale_partecipanti = 0;
                    $num_adults = intval($btr_ammessi_adulti ? $_GET['adulti'] ?? 0 : 0);
                    $num_children = intval($btr_ammessi_bambini ? $_GET['bambini'] ?? 0 : 0);
                    $totale_partecipanti = $num_adults + $num_children;

                    $ordinali = ['Primo', 'Secondo', 'Terzo', 'Quarto', 'Quinto', 'Sesto'];
                    for ($i = 0; $i < $totale_partecipanti; $i++) :
                        $posizione = $ordinali[$i] ?? sprintf(__('Partecipante %d', 'born-to-ride-booking'), $i + 1);
                        ?>
                        <div class="btr-person-card" data-person-index="<?php echo $i; ?>">
                            <h3 class="person-title">
                                <span class="icona-partecipante">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5.52 19c.64-2.2 1.84-3 3.22-3h6.52c1.38 0 2.58.8 3.22 3"/><circle cx="12" cy="10" r="3"/><circle cx="12" cy="12" r="10"/></svg>
                                </span>
                                <strong><?php echo esc_html($posizione); ?></strong> <?php esc_html_e('partecipante', 'born-to-ride-booking'); ?>
                            </h3>

                            <fieldset class="btr-assicurazioni">
                                <?php if (!empty($btr_assicurazione_importi)) : ?>
                                    <h4><?php esc_html_e('Assicurazioni', 'born-to-ride-booking'); ?></h4>
                                    <?php foreach ($btr_assicurazione_importi as $assicurazione) :
                                        $slug = sanitize_title($assicurazione['descrizione']);
                                        $percentuale = get_post_meta($package_id, 'btr_bambini_fascia1_sconto', true);
                                        $importo = $assicurazione['importo'] ?? '';
                                        ?>
                                        <div class="btr-assicurazione-item">
                                            <label>
                                                <input type="checkbox"
                                                    name="anagrafici[<?php echo esc_attr($i); ?>][assicurazioni][<?php echo esc_attr($slug); ?>]"
                                                    value="1" />
                                                <?php echo esc_html($assicurazione['descrizione']); ?>
                                                <?php if (!empty($assicurazione['assicurazione_view_prezzo'])): ?>
                                                    <strong><?php echo number_format_i18n((float)$importo, 2); ?> €</strong>
                                                <?php endif; ?>
                                                <?php if (!empty($assicurazione['assicurazione_view_percentuale'])): ?>
                                                    <strong>+ <?php echo floatval($percentuale); ?>%</strong>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <p><?php esc_html_e('Nessuna assicurazione disponibile.', 'born-to-ride-booking'); ?></p>
                                <?php endif; ?>
                            </fieldset>

                            <?php if (!empty($btr_costi_extra)) : ?>
                                <fieldset class="btr-costi-extra">
                                    <h4><?php esc_html_e('Costi Extra', 'born-to-ride-booking'); ?></h4>
                                    <?php foreach ($btr_costi_extra as $extra) :
                                        // Fall‑back: create a slug if missing
                                        $slug_extra = sanitize_title($extra['slug'] ?? ($extra['nome'] ?? 'extra'));
                                        $importo_extra      = $extra['importo']      ?? '';
                                        $percentuale_extra  = $extra['percentuale']  ?? '';
                                    ?>
                                        <div class="btr-costo-extra-item">
                                            <label>
                                                <input type="checkbox"
                                                       name="anagrafici[<?php echo esc_attr($i); ?>][costi_extra][<?php echo esc_attr($slug_extra); ?>]"
                                                       value="1" />
                                                <?php echo esc_html($extra['nome'] ?? ($extra['descrizione'] ?? 'Extra')); ?>
                                                <?php if ($importo_extra !== '') : ?>
                                                    <strong><?php echo number_format_i18n((float) $importo_extra, 2); ?> €</strong>
                                                <?php endif; ?>
                                                <?php if ($percentuale_extra !== '') : ?>
                                                    <strong>+ <?php echo floatval($percentuale_extra); ?>%</strong>
                                                <?php endif; ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </fieldset>
                            <?php endif; ?>

                        </div>
                    <?php endfor; ?>
                </div>
            </div>



            <div class="tilt-button-wrap action-prev">
                <div class="tilt-button-inner">
                    <a id="btr-proceed"
                       class="nectar-button medium regular-tilt accent-color tilt regular-button instance-3 ld-ext-right"
                       role="button"
                       href="#"
                       data-title="Inviami il preventivo" data-desc="Inserisci i tuoi dati e ricevi il preventivo"
                       style="display: none;">
                        <span class="text">Procedi</span>
                        <div class="ld ld-ring"></div>
                    </a>
                </div>
            </div>

            <!-- Sezione per i Dati del Cliente -->
            <div class="btr-field-group" id="btr-customer-section" style="display: none;">
                <div class="wpb_wrapper ps-1">
                    <h2 id="title-step" style="color: #0097c5;text-align: left; font-size: 30px; margin-bottom:0" class="vc_custom_heading vc_do_custom_heading">Inviami il preventivo</h2>
                    <p id="desc-step">Inserisci i tuoi dati e ricevi il preventivo</p>
                </div>

                <!-- Campi del Cliente -->
                <div class="btr-field-group">
                    <label for="btr_cliente_nome">Nome Cliente</label>
                    <input type="text" id="btr_cliente_nome" name="cliente_nome">
                </div>

                <div class="btr-field-group">
                    <label for="btr_cliente_email">Email Cliente</label>
                    <input type="email" id="btr_cliente_email" name="cliente_email">
                </div>

                <!-- Bottone di Creazione Preventivo -->
                <button type="submit" id="btr-create-quote" class="button">Crea Preventivo</button>
            </div>

            <!-- Risposta della Prenotazione -->
            <div id="btr-booking-response"></div>
        </form>
        <?php
        // Inietta badge_rules e base_nights nello scope JS globale prima della fine
        $badge_rules = get_post_meta($package_id, 'btr_badge_rules', true);
        $base_nights = intval(get_post_meta($package_id, 'btr_numero_notti', true)) ?: 2; // Default 2 notti se non specificato
        
        // Aggiungi supporto per feature flags
        $feature_flags = array(
            'sendSplit' => apply_filters('btr_enable_split_payload', false), // Di default disattivato
            'includeFlatRawJson' => apply_filters('btr_include_flat_raw_json', false), // Opzionale
        );
        
        // Aggiungi anche configurazione debug
        $debug_enabled = defined('BTR_DEBUG') && BTR_DEBUG;
        
        // Crea oggetto btrBooking completo
        echo "<script>";
        echo "window.btrBooking = window.btrBooking || {};";
        echo "window.btrBooking.flags = " . wp_json_encode($feature_flags) . ";";
        echo "window.btrBooking.debug = " . ($debug_enabled ? 'true' : 'false') . ";";
        echo "window.btr_booking_form = window.btr_booking_form || {};";
        echo "window.btr_booking_form.badge_rules = " . wp_json_encode($badge_rules) . ";";
        echo "window.btr_booking_form.base_nights = " . $base_nights . ";";
        echo "</script>";
        return ob_get_clean();
    }



    /**
     * Converte i nomi dei mesi italiani in inglesi per strtotime()
     */
    private function convert_italian_date_to_english($date_string) {
        $italian_months = [
            'Gennaio' => 'January',
            'Febbraio' => 'February',
            'Marzo' => 'March',
            'Aprile' => 'April',
            'Maggio' => 'May',
            'Giugno' => 'June',
            'Luglio' => 'July',
            'Agosto' => 'August',
            'Settembre' => 'September',
            'Ottobre' => 'October',
            'Novembre' => 'November',
            'Dicembre' => 'December'
        ];

        return str_replace(array_keys($italian_months), array_values($italian_months), $date_string);
    }

    /**
     * Recupera le date disponibili dal prodotto, includendo label personalizzata e stato di chiusura per ciascuna data
     */
    private function get_available_dates($product): array {
        // Giorni prima chiusura prenotazioni
        $package_id = get_the_ID();
        $close_days = get_post_meta($package_id, 'btr_booking_close_days', true);
        $close_days = is_numeric($close_days) ? intval($close_days) : 3;
        // Data odierna
        $now = new DateTime('now', wp_timezone());

        $available_dates = [];
        $variations = $product->get_children();

        foreach ($variations as $variation_id) {
            $variation = wc_get_product($variation_id);
            if ($variation) {
                $attributes = $variation->get_attributes();
                $date = $attributes['pa_date_disponibili'] ?? ''; // Ottieni l'attributo data
                // Se la data è valida, verifica la chiusura anticipata
                $is_closed = get_post_meta($variation_id, '_btr_closed', true) === '1';
                $label = get_post_meta($variation_id, '_btr_closed_label', true) ?: 'Sold Out';
                if ($date) {
                    try {
                        $date_obj = new DateTime($date, wp_timezone());
                        $diff = $now->diff($date_obj);
                        // Chiude prenotazioni se la data è passata o entro $close_days giorni
                        if ($diff->invert === 0 || $diff->days <= $close_days) {
                            $is_closed = true;
                        }
                    } catch (Exception $e) {
                        // In caso di formato data non valido, lascia is_closed preesistente
                    }
                }
                if (!empty($date)) {
                    $available_dates[$date] = [
                        'variant_id' => $variation_id,
                        'is_closed'  => $is_closed,
                        'label'      => $label
                    ];
                }
            }
        }

        /* ------------------------------------------------------------------
         * ►  In modalità allotment_camere includi tutte le date presenti
         *     in btr_camere_allotment, anche se l'allotment totale è 0,
         *     così da mostrarle come "Sold Out".
         * ------------------------------------------------------------------ */
        $tipologia_prenotazione = get_post_meta( $package_id, 'btr_tipologia_prenotazione', true );
        if ( $tipologia_prenotazione === 'allotment_camere' ) {
            $camere_allotment = get_post_meta( $package_id, 'btr_camere_allotment', true );
            if ( is_array( $camere_allotment ) ) {
                foreach ( $camere_allotment as $range_label => $info ) {
                    // se già presente (perché esiste una variazione), salta
                    if ( isset( $available_dates[ $range_label ] ) ) {
                        continue;
                    }

                    $totale = isset( $info['totale'] ) ? intval( $info['totale'] ) : 0;

                    $available_dates[ $range_label ] = [
                        'variant_id' => 0,                 // nessuna variazione reale
                        'is_closed'  => $totale === 0,     // Sold Out se allotment 0
                        'label'      => $totale === 0 ? 'Sold Out' : '',
                    ];
                }
            }
        }
        return $available_dates;
    }

    /**
     * AJAX: Verifica la disponibilità in base al numero di persone
     */
    public function check_availability()
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $product_id = intval($_POST['product_id'] ?? 0);
        $package_id = intval($_POST['package_id'] ?? 0);
        $num_people = intval($_POST['num_people'] ?? 0);
        $extra_night = intval($_POST['extra_night'] ?? 0);

        // Log iniziale
        error_log("AJAX check_availability chiamato con product_id: $product_id, package_id: $package_id, num_people: $num_people, extra_night: $extra_night");

        if (!$product_id || !$package_id || $num_people < 1) {
            error_log("Dati non validi: product_id = $product_id, package_id = $package_id, num_people = $num_people");
            wp_send_json_error(['message' => 'Dati non validi.']);
        }

        // Recupera la tipologia di prenotazione
        $tipologia_prenotazione = get_post_meta($package_id, 'btr_tipologia_prenotazione', true);

        if ($tipologia_prenotazione === 'btr_num_persone_max_case2') {
            $max_people = intval(get_post_meta($package_id, 'btr_num_persone_max_case2', true));
        } elseif ($tipologia_prenotazione === 'btr_num_persone_max') {
            $max_people = intval(get_post_meta($package_id, 'btr_num_persone_max', true));
        }

        if ($max_people <= 0) {
            $max_people = PHP_INT_MAX; // Nessun limite se non definito
        }

        if ($num_people > $max_people) {
            error_log("Numero di persone richiesto ($num_people) supera il massimo ($max_people)");
            wp_send_json_error(['message' => "Massimo numero di persone superato ($max_people)."]);
        }

        if ( $extra_night === 1 ) {
            // Verifica rapida: esiste almeno 1 posto disponibile per la notte extra?
            $selected_date = sanitize_text_field( $_POST['selected_date'] ?? '' );
            if ( $selected_date ) {
                $extra_date = date( 'Y-m-d', strtotime( $selected_date . ' -1 day' ) );
                $origin_extra = intval( get_post_meta( $product_id, '_btr_giacenza_origine_globale_' . $extra_date, true ) );
                $scaled_extra = intval( get_post_meta( $product_id, '_btr_giacenza_scalata_globale_' . $extra_date, true ) );
                $available_extra = ( $origin_extra == 0 ) ? PHP_INT_MAX : max( 0, $origin_extra - $scaled_extra );
                if ( $available_extra < 1 ) {
                    wp_send_json_error( [ 'message' => 'Nessuna disponibilità per la notte extra.' ] );
                }
            }
        }

        wp_send_json_success(['message' => 'Disponibilità confermata.']);
    }





   
    public function get_rooms()
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        // ————————————————————————————————————————————————
        // Helper: converte "24 - 25 Gennaio 2026" → "2026-01-24" (Y-m-d)
        // ————————————————————————————————————————————————
        if ( ! function_exists( 'btr_parse_range_start_yMd' ) ) {
            /**
             * Estrae il giorno di partenza (formato Y-m-d) da un range data "alla italiana".
             *
             * @param string $range_str  Es.: "24 - 25 Gennaio 2026"
             * @return string|false      "2026-01-24" oppure false se non riconosciuto
             */
            function btr_parse_range_start_yMd( $range_str ) {
                $months = [
                    'gennaio'=>'January','febbraio'=>'February','marzo'=>'March','aprile'=>'April',
                    'maggio'=>'May','giugno'=>'June','luglio'=>'July','agosto'=>'August',
                    'settembre'=>'September','ottobre'=>'October','novembre'=>'November','dicembre'=>'December',
                ];
                if ( preg_match( '/(\d{1,2})\s*[–—\-]\s*\d{1,2}\s+([A-Za-zÀ-ÿ]+)\s+(\d{4})/u', $range_str, $m ) ) {
                    $day  = $m[1];
                    $mon  = mb_strtolower( $m[2] );
                    $year = $m[3];
                    if ( isset( $months[ $mon ] ) ) {
                        $ts = strtotime( "$day {$months[$mon]} $year" );
                        return $ts ? date( 'Y-m-d', $ts ) : false;
                    }
                }
                return false;
            }
        }


        $product_id = intval($_POST['product_id'] ?? 0);
        $package_id = intval($_POST['package_id'] ?? 0);
        $num_people = intval($_POST['num_people'] ?? 0);
        // Opzione "notte extra" (0 = no, 1 = sì)
        $extra_night = intval($_POST['extra_night'] ?? 0);
        // Prezzo supplemento per persona della notte extra (di default 0)
        $extra_night_price_pp = 0.0;
        // Modalità di pricing per notte extra (false = per persona, true = per camera)
        $extra_night_pricing_per_room = false;

        // Log iniziale
        error_log("AJAX get_rooms chiamato con product_id: $product_id, package_id: $package_id, num_people: $num_people, extra_night: $extra_night");

        if (!$product_id || !$package_id || $num_people < 1) {
            error_log("Dati non validi: product_id = $product_id, package_id = $package_id, num_people = $num_people");
            wp_send_json_error(['message' => 'Dati non validi.']);
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            error_log("Prodotto non trovato: product_id = $product_id");
            wp_send_json_error(['message' => 'Prodotto non trovato.']);
        }

        if (!$product->is_type('variable')) {
            error_log("Prodotto non è di tipo 'variable': product_id = $product_id");
            wp_send_json_error(['message' => 'Prodotto non valido.']);
        }

        // Recupera le camere disponibili
        //$available_rooms = $this->get_rooms_common($product, $num_people, $package_id);
        $sconto_f1 = floatval(get_post_meta($package_id, 'btr_bambini_fascia1_sconto', true));
        $sconto_f2 = floatval(get_post_meta($package_id, 'btr_bambini_fascia2_sconto', true));
        $sconto_f3 = floatval(get_post_meta($package_id, 'btr_bambini_fascia3_sconto', true));
        $sconto_f4 = floatval(get_post_meta($package_id, 'btr_bambini_fascia4_sconto', true));
        $room_calc       = $this->get_rooms_common( $product, $num_people, $package_id, $sconto_f1, $sconto_f2, $sconto_f3, $sconto_f4 );
        $available_rooms = $room_calc['rooms'];
        $combos          = $room_calc['combos'];
        
        // v1.0.160 - Costruisci l'array child_fasce con le etichette configurate dall'admin
        $child_fasce = array();
        
        // Usa la funzione helper per ottenere le etichette dinamiche corrette
        $dynamic_labels = array();
        if (class_exists('BTR_Preventivi')) {
            $dynamic_labels = BTR_Preventivi::btr_get_child_age_labels($package_id);
        }
        
        for ($i = 1; $i <= 4; $i++) {
            if (get_post_meta($package_id, "btr_bambini_fascia{$i}_sconto_enabled", true) !== '1') {
                continue; // skip disabled fascia
            }
            
            $eta_min = get_post_meta($package_id, "btr_bambini_fascia{$i}_eta_min", true);
            $eta_max = get_post_meta($package_id, "btr_bambini_fascia{$i}_eta_max", true);
            
            // v1.0.160 - Usa l'etichetta configurata dall'admin invece di costruirla
            $fascia_key = 'f' . $i;
            $display_label = isset($dynamic_labels[$fascia_key]) ? $dynamic_labels[$fascia_key] : "Bambini ({$eta_min}–{$eta_max})";
            
            $child_fasce[] = array(
                'id'       => $i,
                'label'    => $display_label,
                'age_min'  => (int) $eta_min,
                'age_max'  => (int) $eta_max,
                'discount' => (float) get_post_meta($package_id, "btr_bambini_fascia{$i}_sconto", true),
            );
        }
        
        // Le etichette sono già nel formato corretto del select frontend

        // Filtra le camere in base alla data selezionata dal front-end
        $selected_date = sanitize_text_field( $_POST['selected_date'] ?? '' );
        if ( $selected_date ) {
            $related_variations = [];
            // Trova tutte le variazioni con l'attributo pa_date_disponibili uguale alla data selezionata (con normalizzazione)
            foreach ( $product->get_children() as $variation_id ) {
                $variation = wc_get_product( $variation_id );
                if ( ! $variation ) {
                    continue;
                }

                $attr_date = $variation->get_attribute( 'pa_date_disponibili' );
                if ( ! $attr_date ) {
                    continue; // variazione senza data: salta
                }

                // ► Normalizza la data dell'attributo variazione
                $var_start = btr_parse_range_start_yMd( $attr_date );
                if ( ! $var_start ) {
                    $var_start = date( 'Y-m-d', strtotime( $this->convert_italian_date_to_english( $attr_date ) ) );
                }

                // ► Normalizza la data selezionata dal front‑end
                $sel_start = btr_parse_range_start_yMd( $selected_date );
                if ( ! $sel_start ) {
                    $sel_start = date( 'Y-m-d', strtotime( $this->convert_italian_date_to_english( $selected_date ) ) );
                }

                //   Se coincidono (stesso giorno di partenza), considera la variazione correlata
                if ( $var_start && $sel_start && $var_start === $sel_start ) {
                    $related_variations[] = $variation_id;
                }
            }
            // Filtra le camere recuperate in get_rooms_common()
            if ( ! empty( $related_variations ) ) {
                // Filtra le camere mantenendo solo le varianti che corrispondono alla data selezionata
                $available_rooms = array_values(
                    array_filter(
                        $available_rooms,
                        static function ( $room ) use ( $related_variations ) {
                            return in_array( $room['variation_id'], $related_variations, true );
                        }
                    )
                );
            }
        }

        // Determine global allotment stock for the selected date (read directly from the WC product)
        $tipologia_prenotazione = get_post_meta( $package_id, 'btr_tipologia_prenotazione', true );
        $global_stock = null;
        // ---- Inizializza variabili per notte extra ----
        $global_stock_extra = null;
        $extra_date = null;

        if ( $tipologia_prenotazione === 'allotment_camere' && ! empty( $selected_date ) ) {

            // Meta keys are saved on the VARIABLE PRODUCT, not on the CPT.
            /* ------------------------------------------------------------------
             * FIRST: try to read the global allotment directly from
             *        btr_camere_allotment on the CPT (package).
             * ------------------------------------------------------------------ */
            $global_stock = null; // reset; will be set by CPT or fallback

            $allotment_globale = get_post_meta( $package_id, 'btr_camere_allotment', true );
            $allotment_globale = is_array( $allotment_globale ) ? $allotment_globale : [];

            // Normalise selected date once (Y‑m‑d)
            $selected_start_yMd = btr_parse_range_start_yMd( $selected_date );
            if ( ! $selected_start_yMd ) {
                $selected_start_yMd = date( 'Y-m-d', strtotime( $this->convert_italian_date_to_english( $selected_date ) ) );
            }

            foreach ( $allotment_globale as $range_lbl => $info ) {
                // Map range label → Y-m-d (start)
                $range_start_yMd = btr_parse_range_start_yMd( $range_lbl );
                if ( ! $range_start_yMd ) {
                    $range_start_yMd = date( 'Y-m-d', strtotime( $this->convert_italian_date_to_english( $range_lbl ) ) );
                }

                if ( $range_start_yMd === $selected_start_yMd ) {
                    // TOTAL may be integer (new structure) or array limiti (old).
                    if ( isset( $info['totale'] ) && is_numeric( $info['totale'] ) ) {
                        $tot = intval( $info['totale'] );
                        $global_stock = ( $tot === 0 ) ? 0 : $tot;
                    } elseif ( isset( $info['totale']['limite'] ) ) {
                        $tot = intval( $info['totale']['limite'] );
                        $global_stock = ( $tot === 0 ) ? 0 : $tot;
                    }
                    break;
                }
            }
            // Format: _btr_giacenza_origine_globale_<date‑label>
            $product_metas = get_post_meta( $product_id );
            $origin_key    = null;

            // Normalise the selected date once for safe comparisons
        $selected_date_english = $this->convert_italian_date_to_english( $selected_date );
        $ts_selected = strtotime( $selected_date_english );
        $norm_select = $ts_selected ? date( 'Y-m-d', $ts_selected ) : '';

            foreach ( $product_metas as $meta_key => $value_arr ) {
                // Consider only global‑origin meta
                if ( strpos( $meta_key, '_btr_giacenza_origine_globale_' ) !== 0 ) {
                    continue;
                }

                // Label without the prefix, eg: "24 - 25 Gennaio 2026" or "2026‑01‑24"
                $meta_raw = str_replace( '_btr_giacenza_origine_globale_', '', $meta_key );

                /* ------------------------------------------------------------------
                 * 1)  Range label (es. "24 - 25 Gennaio 2026")
                 *     – btr_parse_range_start_yMd() → "2026-01-24"
                 * ------------------------------------------------------------------ */
                $range_start = btr_parse_range_start_yMd( $meta_raw );
                if ( $range_start && $range_start === $norm_select ) {
                    $origin_key = $meta_raw;
                    break;
                }

                /* ------------------------------------------------------------------
                 * 2)  Single‑date label ("24 Gennaio 2026" oppure già in Y‑m‑d)
                 * ------------------------------------------------------------------ */
                $meta_raw_eng = $this->convert_italian_date_to_english( $meta_raw );
                if ( date( 'Y-m-d', strtotime( $meta_raw_eng ) ) === $norm_select ) {
                    $origin_key = $meta_raw;
                    break;
                }
            }

            if ( $origin_key !== null ) {
                $origin = intval( get_post_meta( $product_id, '_btr_giacenza_origine_globale_' . $origin_key, true ) );
                $scaled = intval( get_post_meta( $product_id, '_btr_giacenza_scalata_globale_' . $origin_key, true ) );

                // If origin is 0 ⇒ unlimited
                $global_stock = ( $origin == 0 )
                    ? PHP_INT_MAX
                    : max( 0, $origin - $scaled );

            } elseif ( $global_stock === null ) {
                // CPT didn't have info and no product meta found → treat as unlimited
                $global_stock = PHP_INT_MAX;
            }

            /* ------------------------------------------------------------------
             *  Se l'allotment totale per la data è 0 → nessuna camera disponibile
             * ------------------------------------------------------------------ */
            if ( $global_stock !== null && $global_stock !== PHP_INT_MAX && $global_stock === 0 ) {
                error_log("[ALLOTMENT] Disponibilità globale esaurita per {$selected_date}. Nessuna camera restituita.");
                wp_send_json_success( [
                    'rooms'                    => [],
                    'combos'                   => [],
                    'bambini_fascia1_sconto'   => $sconto_f1,
                    'bambini_fascia2_sconto'   => $sconto_f2,
                    'bambini_fascia3_sconto'   => $sconto_f3,
                    'bambini_fascia4_sconto'   => $sconto_f4,
                    'badge_rules'              => [],
                    'show_disabled_rooms'      => false,
                    'global_stock'             => 0,
                    'extra_night'              => ( $extra_night === 1 ),
                    'global_stock_extra'       => null,
                ] );
            }

            // ------------------------------------------------------------------
            //  Se l'utente ha selezionato la "notte extra", calcoliamo la giacenza
            //  globale per la data precedente (es.: venerdì).
            // ------------------------------------------------------------------
            // ------------------------------------------------------------------
            //  NOTTE EXTRA – calcola la giacenza effettiva per il giorno prima
            // ------------------------------------------------------------------
            $global_stock_extra = null; // Mantenuto per output/compatibilità, ma il dettaglio è in $limit_total_physical_rooms_extra
            $stock_per_type_extra = []; // Array per i limiti per tipologia per la notte extra
            $limit_total_physical_rooms_extra = PHP_INT_MAX; // Limite sul numero totale di camere fisiche per la notte extra

            if ( $extra_night === 1 && ! empty( $selected_date ) ) {
                // ---------------------------------------------------------------------------------
                // ► Calcola $extra_date_yMd affidandosi al nuovo helper
                //     – gestisce sia range ("24 - 25 Gennaio 2026") sia singola data
                // ---------------------------------------------------------------------------------
                $start_yMd = btr_parse_range_start_yMd( $selected_date );
                if ( ! $start_yMd ) {
                    // Non era un range → prova con una singola data italiana
                    $sel_en   = $this->convert_italian_date_to_english( $selected_date );
                    $start_ts = strtotime( $sel_en );
                    if ( $start_ts === false ) {
                        error_log( "Data non valida per notte extra: {$selected_date}" );
                        wp_send_json_error( [
                            'message'            => 'Data non valida per il calcolo della notte extra.',
                            'invalid_extra_date' => true,
                        ] );
                    }
                    $start_yMd = date( 'Y-m-d', $start_ts );
                }

                // Giorno precedente
                $extra_date_ts  = strtotime( $start_yMd . ' -1 day' );
                $extra_date_yMd = date( 'Y-m-d', $extra_date_ts );
                $extra_date = $extra_date_yMd; // Assicuriamoci che $extra_date sia la Y-m-d per i meta

                // Inizializza $stock_per_type_extra e $limit_total_physical_rooms_extra
                // basandosi su CPT allotment e poi fallback su meta prodotto.

                // Recupera l'allotment corretto per la notte extra
                if ( intval( $extra_night ) === 1 ) {
                    // 1) Meta dedicato alle notti extra
                    $allotment = get_post_meta( $package_id, 'btr_camere_extra_allotment_by_date', true );

                    // 2) Fallback al meta generico, se quello extra non esiste
                    if ( empty( $allotment ) || ! is_array( $allotment ) ) {
                        $allotment = get_post_meta( $package_id, 'btr_camere_allotment', true );
                    }
                } else {
                    $allotment = get_post_meta( $package_id, 'btr_camere_allotment', true );
                }
                $allotment = is_array( $allotment ) ? $allotment : [];
                $allot_key = null;
                $selected_start_yMd = btr_parse_range_start_yMd( $selected_date ) ?: $norm_select;

                foreach ( $allotment as $k => $info ) {
                    $k_eng = $this->convert_italian_date_to_english( $k );
                    $k_start_yMd = btr_parse_range_start_yMd( $k ) ?: date( 'Y-m-d', strtotime( $k_eng ) );
                    if ( $k_start_yMd === $extra_date_yMd || $k_start_yMd === $selected_start_yMd ) {
                        $allot_key = $k;
                        break;
                    }
                }

                if ( $allot_key !== null && is_array( $allotment[ $allot_key ] ) ) {
                    $current_allotment_info = $allotment[ $allot_key ];
                    $has_total_limit_from_cpt = false;

                    // Prioritize product-level override for total extra night rooms
                    $product_override_active = get_post_meta( $product_id, 'btr_limite_camere_extra_active', true );
                    $product_override_limit = get_post_meta( $product_id, 'btr_limite_camere_extra', true );

                    if ( $product_override_active === 'yes' && is_numeric( $product_override_limit ) && intval( $product_override_limit ) >= 0 ) {
                        $limit_total_physical_rooms_extra = intval( $product_override_limit );
                        $has_total_limit_from_cpt = true; // Consider it as if it came from CPT for logic flow
                    } elseif ( isset( $current_allotment_info['totale'] ) && is_numeric( $current_allotment_info['totale'] ) ) {
                        // Nuova struttura 'btr_camere_extra_allotment_by_date' → 'totale' è un intero
                        $limit_val = intval( $current_allotment_info['totale'] );
                        $limit_total_physical_rooms_extra = $limit_val;   // <— assegna!
                        $has_total_limit_from_cpt = true;                 // <— flag correttamente
                    } elseif ( isset( $current_allotment_info['totale']['limite'] ) ) {
                        // Vecchia struttura: 'totale' è un array con chiave 'limite'
                        $limit_val = intval( $current_allotment_info['totale']['limite'] );
                        // If CPT total limit is 0, it means no limit from CPT, so it should be PHP_INT_MAX unless overridden by product meta.
                        // If product meta is not active or not set, and CPT is 0, then it's unlimited.
                        // If CPT is > 0, that's the limit.
                        // Se l'override del prodotto non era attivo o valido, usa il valore del CPT.
                        // Se CPT limit è 0, $limit_total_physical_rooms_extra sarà 0.
                        // Se CPT limit è N, $limit_total_physical_rooms_extra sarà N.
                        $limit_total_physical_rooms_extra = $limit_val;
                        $has_total_limit_from_cpt = true;
                    } else {
                        // No total limit from CPT and no product override, so effectively unlimited from CPT perspective
                        $limit_total_physical_rooms_extra = PHP_INT_MAX;
                    }

                    $calculated_sum_of_type_limits = 0;
                    $any_type_unlimited_in_cpt = false;

                    foreach ( $current_allotment_info as $type_name => $type_info ) {
                        if ( $type_name === 'totale' || ! is_array( $type_info ) ) {
                            continue;
                        }
                        // Assicurati che $type_name sia normalizzato se necessario (es. ucfirst)
                        $normalized_type_name = ucfirst(strtolower($type_name));

                        if ( ! empty( $type_info['esclusa'] ) ) {
                            $stock_per_type_extra[ $normalized_type_name ] = 0;
                        } else {
                            $type_limit = isset( $type_info['limite'] ) ? intval( $type_info['limite'] ) : 0;
                            if ( $type_limit === 0 ) { // 0 nel CPT per tipo significa illimitato
                                $stock_per_type_extra[ $normalized_type_name ] = PHP_INT_MAX;
                                $any_type_unlimited_in_cpt = true;
                            } else {
                                $stock_per_type_extra[ $normalized_type_name ] = $type_limit;
                                $calculated_sum_of_type_limits += $type_limit;
                            }
                        }
                    }

                    if ( ! $has_total_limit_from_cpt ) {
                        $limit_total_physical_rooms_extra = $any_type_unlimited_in_cpt ? PHP_INT_MAX : $calculated_sum_of_type_limits;
                    }
                    // Salva il prezzo extra per persona se presente
                    if ( isset( $current_allotment_info['prezzo_per_persona'] ) ) {
                        $extra_night_price_pp = floatval( $current_allotment_info['prezzo_per_persona'] );
                    }
                    
                    // Salva la modalità di pricing (per persona o per camera)
                    $extra_night_pricing_per_room = isset( $current_allotment_info['pricing_per_room'] ) && $current_allotment_info['pricing_per_room'];
                }

                // Fallback ai meta del prodotto SE non c'è un override a livello di prodotto per il totale E
                // (il CPT non ha fornito un limite totale > 0 OPPURE non c'era $allot_key e il limite è ancora illimitato)
                $product_override_active_for_total = get_post_meta( $product_id, 'btr_limite_camere_extra_active', true );

                if ( $product_override_active_for_total !== 'yes' &&
                     ( $limit_total_physical_rooms_extra === 0 || ($allot_key === null && $limit_total_physical_rooms_extra === PHP_INT_MAX) )
                   ) {
                    $origin_key_extra = null;
                    foreach ( $product_metas as $meta_key => $value_arr ) {
                        if ( strpos( $meta_key, '_btr_giacenza_origine_globale_' ) !== 0 ) continue;
                        $meta_date_label = str_replace( '_btr_giacenza_origine_globale_', '', $meta_key );

                        // Normalizza meta_date_label a Y-m-d per confronto con $extra_date_yMd
                        $meta_date_for_compare = btr_parse_range_start_yMd( $meta_date_label );
                        if (!$meta_date_for_compare) {
                             $meta_date_for_compare = date('Y-m-d', strtotime($this->convert_italian_date_to_english($meta_date_label)));
                        }

                        if ( $meta_date_for_compare === $extra_date_yMd ) {
                            $origin_key_extra = $meta_date_label;
                            break;
                        }
                    }

                    if ( $origin_key_extra !== null ) {
                        $origin_extra_val = intval( get_post_meta( $product_id, '_btr_giacenza_origine_globale_' . $origin_key_extra, true ) );
                        $scaled_extra_val = intval( get_post_meta( $product_id, '_btr_giacenza_scalata_globale_' . $origin_key_extra, true ) );

                        // Calcola lo stock di fallback dai meta del prodotto.
                        // Se origin_extra_val è 0, significa illimitato per questo meta specifico.
                        $calculated_fallback_stock = ($origin_extra_val == 0) ? PHP_INT_MAX : max(0, $origin_extra_val - $scaled_extra_val);

                        // Applica il fallback SOLO SE $limit_total_physical_rooms_extra è ancora PHP_INT_MAX
                        // (cioè, né l'override del prodotto né l'allotment CPT hanno fornito un limite specifico)
                        // O se non c'era una chiave di allotment ($allot_key === null) e quindi si deve ricorrere ai meta.
                        if ($limit_total_physical_rooms_extra === PHP_INT_MAX || $allot_key === null) {
                            // Se il fallback calcolato dai meta è illimitato (perché origin_extra_val era 0),
                            // allora imposta il limite totale a 0 per evitare disponibilità illimitata non desiderata.
                            if ($calculated_fallback_stock === PHP_INT_MAX) {
                                $limit_total_physical_rooms_extra = 0;
                            } else {
                                $limit_total_physical_rooms_extra = $calculated_fallback_stock;
                            }
                        }
                        // Altrimenti, se $limit_total_physical_rooms_extra aveva già un valore (es. 0 o N dal CPT/override),
                        // quel valore viene mantenuto e non sovrascritto dal fallback dei meta.

                        // Se usiamo il fallback per il numero totale di camere, e non ci sono info per tipo dal CPT,
                        // lo stock per tipo per la notte extra è considerato illimitato (verrà poi limitato dallo stock normale della variante).
                        if ($allot_key === null && $limit_total_physical_rooms_extra > 0 && empty($stock_per_type_extra)) {
                            // Non popolare $stock_per_type_extra qui, la logica nella ricorsione userà PHP_INT_MAX se non trova la chiave.
                        }
                    } else { // $origin_key_extra è null (nessun meta prodotto trovato per la data della notte extra)
                        // Se $limit_total_physical_rooms_extra è ancora PHP_INT_MAX (nessun limite da CPT/override)
                        // e non ci sono meta prodotto per la data extra, allora imposta il limite a 0.
                        if ($limit_total_physical_rooms_extra === PHP_INT_MAX) {
                            $limit_total_physical_rooms_extra = 0;
                        }
                        // Se $limit_total_physical_rooms_extra era già 0 (es. da CPT 'totale' = 0), rimane 0.
                    }
                }
                $global_stock_extra = $limit_total_physical_rooms_extra;
            }
        }

        // Recupera la giacenza globale (allotment) per la data specifica, come da meta WooCommerce
        // e aggiungi a ciascuna camera/variante il campo 'global_stock'
        // NOTA: Questa parte potrebbe essere ridondante o necessitare di revisione se $global_stock è già calcolato bene prima.
        // Per ora la lascio, ma verificane l'impatto.
        foreach ($available_rooms as &$room) {
            // Determina il nome della data per la variante (può essere $selected_date se ogni camera è per una sola data)
            // Se le camere hanno un attributo data, puoi usare quello. Qui assumiamo che sia $selected_date.
            $date_name = $selected_date; // Questo $date_name potrebbe non essere corretto se $selected_date è un range.
                                        // $origin_key trovato prima è più affidabile per la data principale.
            // Assicurati che $product_id sia valorizzato con l'ID prodotto variabile corrente
            // $origin = intval(get_post_meta($product_id, '_btr_giacenza_origine_globale_' . $date_name, true));
            // $scaled = intval(get_post_meta($product_id, '_btr_giacenza_scalata_globale_' . $date_name, true));
            // $available_global = ($origin > 0) ? max(0, $origin - $scaled) : PHP_INT_MAX;
            // Assegna alla camera/variante
            $room['global_stock'] = $global_stock; // Usa $global_stock calcolato in precedenza per la data principale
        }
        unset($room);

        /*
         * ------------------------------------------------------------------
         *  RICALCOLO DELLE COMBINAZIONI DOPO IL FILTRO DATA
         * ------------------------------------------------------------------
         *
         *  Nota: get_rooms_common ha generato $combos utilizzando TUTTE le
         *  variazioni disponibili.   Dopo il filtro per data ($selected_date)
         *  potremmo aver rimosso alcune variazioni, rendendo invalide alcune
         *  combinazioni e — soprattutto — mostrando tipologie che non possono
         *  più coprire davvero i posti richiesti (es.: 1 Singola su 2 persone).
         *  Per garantire coerenza ricalcoliamo le combo sul set filtrato.
         */
        // --------------------------------------------------------------
        // ► Aggiorna lo stock reale delle varianti (prima del filtro)
        //     Serve a calcolare correttamente il numero di camere
        //     fisiche effettivamente prenotabili per la notte extra.
        // --------------------------------------------------------------
        foreach ( $available_rooms as &$room ) {
            $variation_id  = $room['variation_id'];
            $variation_obj = wc_get_product( $variation_id );

            // ------------------------------------------------------------------
            //  Per tipologia 'allotment_camere', una variante SENZA gestione
            //  stock (manage_stock = no) va considerata illimitata.
            // ------------------------------------------------------------------
            if ( $variation_obj ) {
                if ( $variation_obj->managing_stock() ) {
                    // WooCommerce gestisce la giacenza → usa il valore reale
                    $actual_stock = intval( $variation_obj->get_stock_quantity() );
                } else {
                    // Gestione stock disattivata ⇒ treat as unlimited
                    $actual_stock = PHP_INT_MAX;
                }
            } else {
                $actual_stock = 0;
            }

            // Manteniamo 'stock' = 0 se illimitato, ma aggiungiamo flag dedicato
            $room['stock']           = ( $actual_stock === PHP_INT_MAX ) ? 0 : $actual_stock;
            $room['stock_infinite']  = ( $actual_stock === PHP_INT_MAX );
            $room['available_stock'] = $actual_stock; // provvisorio
        }
        unset( $room );

        // --------------------------------------------------------------
        // ▼ Filtro "intelligente" per la notte extra
        //     Limita le tipologie proposte in base al numero massimo
        //     di camere fisiche disponibili per la notte extra.
        //     Se il limite è 1 e i partecipanti sono 4 → servono camere
        //     con capacità ≥ 4 (es. solo Quadrupla).
        //     Se il limite è 2 e i partecipanti sono 4 → capacità ≥ 2
        //     (Singola esclusa, ammesse Doppia/Tripla/Quadrupla, ecc.).
        // --------------------------------------------------------------
        if ( $extra_night === 1 ) {
            // Assicura che la variabile sia definita; se mancante ⇒ illimitato
            $ltpe = $limit_total_physical_rooms_extra ?? PHP_INT_MAX;
            // Calcola quante camere fisiche possiamo realisticamente usare
            $rooms_capable = $ltpe;
            if ( $rooms_capable === PHP_INT_MAX || $rooms_capable === 0 ) {
                // Fallback: somma le camere che hanno stock > 0
                $rooms_capable = 0;
                foreach ( $available_rooms as $r ) {
                    $rooms_capable += max( 0, intval( $r['stock'] ?? 0 ) );
                }
                // Evita divisione per zero
                if ( $rooms_capable === 0 ) {
                    $rooms_capable = 1;
                }
            }
            // Ogni camera deve poter coprire almeno ceil(num_people / camere_utilizzabili)
            $min_capacity_per_room = ( $rooms_capable > 0 )
                ? (int) ceil( $num_people / $rooms_capable )
                : $num_people;

            $available_rooms = array_values( array_filter(
                $available_rooms,
                static function ( $room ) use ( $min_capacity_per_room ) {
                    return $room['capacity'] >= $min_capacity_per_room;
                }
            ) );
        }
        // --------------------------------------------------------------
        // ▼ Filtro "intelligente" per la notte extra (second pass)
        //     Replica del filtro introdotto prima, necessaria perché
        //     questa sezione ricalcola di nuovo le combinazioni dopo
        //     aver ritoccato gli stock delle varianti.
        // --------------------------------------------------------------
        if ( $extra_night === 1 ) {
            // Assicura che la variabile sia definita; se mancante ⇒ illimitato
            $ltpe = $limit_total_physical_rooms_extra ?? PHP_INT_MAX;
            // Calcola quante camere fisiche possiamo realisticamente usare
            $rooms_capable = $ltpe;
            if ( $rooms_capable === PHP_INT_MAX || $rooms_capable === 0 ) {
                // Fallback: somma le camere che hanno stock > 0
                $rooms_capable = 0;
                foreach ( $available_rooms as $r ) {
                    $rooms_capable += max( 0, intval( $r['stock'] ?? 0 ) );
                }
                // Evita divisione per zero
                if ( $rooms_capable === 0 ) {
                    $rooms_capable = 1;
                }
            }
            // Ogni camera deve poter coprire almeno ceil(num_people / camere_utilizzabili)
            $min_capacity_per_room = ( $rooms_capable > 0 )
                ? (int) ceil( $num_people / $rooms_capable )
                : $num_people;

            $available_rooms = array_values( array_filter(
                $available_rooms,
                static function ( $room ) use ( $min_capacity_per_room ) {
                    return $room['capacity'] >= $min_capacity_per_room;
                }
            ) );
        }
        $types_for_combo = $available_rooms; // già filtrate per data
        $solutions       = [];

        // Back‑tracking "inline" (stesso algoritmo di get_rooms_common)
        // Passiamo i nuovi parametri per la notte extra
        $effective_stock_per_type_extra_for_recursion = $stock_per_type_extra;
        $limit_total_physical_rooms_for_recursion = ($extra_night === 1 && isset($global_stock_extra)) ? $global_stock_extra : PHP_INT_MAX;

        $recurse = function ( int $i, int $remain, array $cur, int $physical_rooms_used_count)
            use ( &$recurse, &$solutions, $types_for_combo, $num_people, $extra_night, $effective_stock_per_type_extra_for_recursion, &$limit_total_physical_rooms_extra ) {

            if ( $remain === 0 ) {
                $solutions[] = $cur;
                return;
            }
            if ( $i >= count( $types_for_combo ) || $remain < 0 ) {
                return;
            }

            // Se notte extra e abbiamo già usato il numero massimo di camere fisiche, possiamo solo provare q=0 per le restanti.
            if ($extra_night === 1 && $physical_rooms_used_count >= $limit_total_physical_rooms_extra) {
                $can_still_try_zero = true;
                for ($k = $i; $k < count($types_for_combo); $k++) {
                    if ($types_for_combo[$k]['capacity'] > 0) { // Se una camera futura ha capacità > 0, non possiamo soddisfare $remain se è > 0
                        if ($remain > 0) $can_still_try_zero = false;
                        break;
                    }
                }
                if (!$can_still_try_zero && $remain > 0) return; // Non c'è modo di completare

                // Prova solo con q=0 per questa tipologia (non usare questa camera)
                $temp_cur = $cur; // Evita di modificare $cur direttamente se non si prosegue
                $temp_cur[ $i ] = 0;
                $recurse( $i + 1, $remain, $temp_cur, $physical_rooms_used_count );
                return;
            }

            $current_type_info = $types_for_combo[ $i ];
            $cap    = $current_type_info['capacity'];
            // Lo stock normale della variante (già filtrato per data principale)
            $stock_normale  = $current_type_info['stock_limited'] ?? $current_type_info['stock'];

            $stock_effettivo_per_questa_tipologia = $stock_normale;

            if ($extra_night === 1) {
                $type_name_for_extra_stock = $current_type_info['type']; // Es: 'Doppia'
                // Se non c'è uno stock specifico per tipo per la notte extra, si assume illimitato (verrà limitato da stock_normale)
                $stock_extra_specifico_tipo = $effective_stock_per_type_extra_for_recursion[$type_name_for_extra_stock] ?? PHP_INT_MAX;
                $stock_effettivo_per_questa_tipologia = min($stock_normale, $stock_extra_specifico_tipo);
            }

            $maxQty_based_on_stock_and_capacity = ($cap > 0) ? intdiv( $remain, $cap ) : ( $remain == 0 ? 0 : PHP_INT_MAX );
            $maxQty = min( $stock_effettivo_per_questa_tipologia, $maxQty_based_on_stock_and_capacity );

            for ( $q = 0; $q <= $maxQty; $q ++ ) {
                $additional_physical_rooms_this_step = $q; // Ogni $q è una stanza di questo tipo

                if ($extra_night === 1 && ($physical_rooms_used_count + $additional_physical_rooms_this_step > $limit_total_physical_rooms_extra)) {
                    // Questa quantità $q supererebbe il limite totale di camere fisiche per la notte extra.
                    break; // Poiché $q è in aumento, tutte le $q successive lo supereranno anche.
                }
                $temp_cur = $cur; // Evita di modificare $cur direttamente
                $temp_cur[ $i ] = $q;
                $recurse( $i + 1, $remain - $q * $cap, $temp_cur, $physical_rooms_used_count + $additional_physical_rooms_this_step );
            }
        };

        $recurse( 0, $num_people, array_fill(0, count($types_for_combo), 0), 0 );

        // Trasforma in combo leggibili + individua tipologie realmente usate
        $combos       = [];
        $include_type = array_fill( 0, count( $types_for_combo ), false );

        foreach ( $solutions as $sol ) {
            // Il controllo sul numero di camere usate ($rooms_used > $global_stock_extra)
            // dovrebbe essere già gestito dalla ricorsione se $limit_total_physical_rooms_for_recursion è corretto.
            // Lo lascio commentato per ora come doppia verifica se necessario.
            /*
            $rooms_used = 0;
            foreach($sol as $qty_in_sol) {
                if ($qty_in_sol > 0) $rooms_used += $qty_in_sol;
            }
            if ( $extra_night === 1
                && isset($global_stock_extra) && $global_stock_extra !== PHP_INT_MAX
                && $rooms_used > $global_stock_extra ) {
                error_log("[EXTRA NIGHT DEBUG] Soluzione scartata: rooms_used ($rooms_used) > global_stock_extra ($global_stock_extra). Sol: " . json_encode($sol));
                continue; // scarta la soluzione
            }
            */
            $combo = [
                'rooms'          => [],
                'total_capacity' => 0,
                'total_price'    => 0,
            ];

            foreach ( $sol as $idx => $qty ) {
                $extra_date     = $extra_date_yMd;   // servirà più avanti


                /**
                 * 1) Leggiamo l'allotment configurato sul CPT (btr_camere_allotment)
                 *    così possiamo conoscere quante CAMERE sono state marcate come
                 *    "idonee per notte extra", indipendentemente dal totale letti.
                 */
                $allotment = get_post_meta( $package_id, 'btr_camere_allotment', true );
                $allotment = is_array( $allotment ) ? $allotment : [];

                $rooms_available_extra = 0;

                /* ------------------------------------------------------------------
                 *  Individua la chiave dell'allotment corretta per la NOTTE EXTRA
                 * ------------------------------------------------------------------
                 *  – normalizziamo OGNI chiave in formato Y-m-d (data di INIZIO range)
                 *  – corrispondenza se coincide con $extra_date_yMd  **OPPURE**
                 *    con la data di inizio del range SELEZIONATO
                 *    (es.: l'allotment potrebbe essere salvato sul range principale).
                 */
                $allot_key = null;
                $selected_start_yMd = btr_parse_range_start_yMd( $selected_date ) ?: $norm_select;

                foreach ( $allotment as $k => $info ) {

                    // DEBUG – logga tutte le chiavi allotment e i match calcolati
                    $k_dbg_norm   = btr_parse_range_start_yMd( $k ) ?: date( 'Y-m-d', strtotime( $this->convert_italian_date_to_english( $k ) ) );
                    error_log( "[EXTRA-DEBUG] key='{$k}' | norm='{$k_dbg_norm}' | extra_date_yMd='{$extra_date_yMd}' | selected_start='{$selected_start_yMd}'" );

                    // 1) Normalizza la chiave dell'allotment → YYYY-MM-DD
                    $k_eng      = $this->convert_italian_date_to_english( $k );
                    $k_start_yMd = btr_parse_range_start_yMd( $k ) ?: date( 'Y-m-d', strtotime( $k_eng ) );

                    // 2) Match se:
                    //    a) coincide con la data "venerdì" (extra)  oppure
                    //    b) coincide con l'inizio del range selezionato
                    if ( $k_start_yMd === $extra_date_yMd || $k_start_yMd === $selected_start_yMd ) {
                        $allot_key = $k;
                        break;
                    }
                }

                if ( $allot_key !== null && is_array( $allotment[ $allot_key ] ) ) {

                    // ► 1.a   Priorità al valore "totale→limite" se presente
                    if ( isset( $allotment[ $allot_key ]['totale']['limite'] ) ) {
                        $rooms_available_extra = intval( $allotment[ $allot_key ]['totale']['limite'] );
                    }

                    // ► 1.b   Se non c'è "totale", calcola il NUMERO TOTALE di camere idonee
                    //         (somma dei limiti per singola tipologia).  Se almeno una
                    //         tipologia è illimitata (limite = 0), consideriamo illimitato.
                    if ( $rooms_available_extra === 0 ) {

                        $total_extra_rooms = 0;
                        $unlimited_found   = false;

                        foreach ( $allotment[ $allot_key ] as $type => $info ) {

                            if ( $type === 'totale' || ! empty( $info['esclusa'] ) ) {
                                continue;
                            }

                            $limit = isset( $info['limite'] ) ? intval( $info['limite'] ) : 0;

                            if ( $limit === 0 ) {
                                // Illimitato per almeno una tipologia ⇒ usciamo e segniamo illimitato
                                $unlimited_found = true;
                                break;
                            }

                            $total_extra_rooms += $limit;
                        }

                        $rooms_available_extra = $unlimited_found
                            ? PHP_INT_MAX         // Nessun tetto effettivo
                            : $total_extra_rooms;  // Somma dei limiti per tipologia
                    }
                }

                /**
                 * 2) Se l'allotment CPT non contiene info, fallback ai meta globali
                 *    sul prodotto, come nella logica precedente.
                 */
                if ( $rooms_available_extra === 0 ) {

                    $origin_key_extra = null;
                    foreach ( $product_metas as $meta_key => $value_arr ) {
                        if ( strpos( $meta_key, '_btr_giacenza_origine_globale_' ) !== 0 ) {
                            continue;
                        }
                        $meta_date = str_replace( '_btr_giacenza_origine_globale_', '', $meta_key );

                        // Confronto normalizzato
                    $meta_date_english = $this->convert_italian_date_to_english( $meta_date );
                    if ( date( 'Y-m-d', strtotime( $meta_date_english ) ) === $extra_date_yMd ) {
                        $origin_key_extra = $meta_date;
                        break;
                    }
                    }

                    if ( $origin_key_extra !== null ) {
                        $origin_extra = intval( get_post_meta( $product_id, '_btr_giacenza_origine_globale_' . $origin_key_extra, true ) );
                        $scaled_extra = intval( get_post_meta( $product_id, '_btr_giacenza_scalata_globale_' . $origin_key_extra, true ) );
                        $rooms_available_extra = ( $origin_extra == 0 )
                            ? PHP_INT_MAX
                            : max( 0, $origin_extra - $scaled_extra );
                    }
                }

                /* -----------------------------------------------------------------
                 *  DECIDI IL VALORE FINALE di $global_stock_extra
                 *  -----------------------------------------------------------------
                 *  - Se il CPT/allotment fornisce un limite > 0  → usa quel valore.
                 *  - Se il CPT dice 0                          → nessuna camera.
                 *  - Se il CPT non ha info ma i meta WooCommerce
                 *    (_btr_giacenza_origine_globale_…) indicano un'origine > 0
                 *      → usa (origine – scalato).
                 *  - In tutti gli altri casi (origine = 0 → illimitato) → PHP_INT_MAX.
                 * ----------------------------------------------------------------- */
                if ( $rooms_available_extra > 0 ) {

                    // Valore positivo dal CPT → è il tetto effettivo.
                    $global_stock_extra = $rooms_available_extra;

                } elseif ( $rooms_available_extra === 0 ) {

                    // Esplicitamente nessuna camera idonea per la notte extra.
                    $global_stock_extra = 0;

                } elseif ( isset( $origin_extra ) ) {

                    // Non c'era limite nel CPT: fallback ai meta WooCommerce.
                    // Se origine = 0 ⇒ illimitato, altrimenti (origine - scalato).
                    $global_stock_extra = ( $origin_extra == 0 )
                        ? PHP_INT_MAX
                        : max( 0, $origin_extra - $scaled_extra );

                } else {

                    // Nessuna informazione trovata → consideriamo illimitato.
                    $global_stock_extra = PHP_INT_MAX;
                }
            }
        }

        // Recupera la giacenza globale (allotment) per la data specifica, come da meta WooCommerce
        // e aggiungi a ciascuna camera/variante il campo 'global_stock'
        foreach ($available_rooms as &$room) {
            // Determina il nome della data per la variante (può essere $selected_date se ogni camera è per una sola data)
            // Se le camere hanno un attributo data, puoi usare quello. Qui assumiamo che sia $selected_date.
            $date_name = $selected_date;
            // Assicurati che $product_id sia valorizzato con l'ID prodotto variabile corrente
            $origin = intval(get_post_meta($product_id, '_btr_giacenza_origine_globale_' . $date_name, true));
            $scaled = intval(get_post_meta($product_id, '_btr_giacenza_scalata_globale_' . $date_name, true));
            $available_global = ($origin > 0) ? max(0, $origin - $scaled) : PHP_INT_MAX;
            // Assegna alla camera/variante
            $room['global_stock'] = $available_global;
        }
        unset($room);

        /*
         * ------------------------------------------------------------------
         *  RICALCOLO DELLE COMBINAZIONI DOPO IL FILTRO DATA
         * ------------------------------------------------------------------
         *
         *  Nota: get_rooms_common ha generato $combos utilizzando TUTTE le
         *  variazioni disponibili.   Dopo il filtro per data ($selected_date)
         *  potremmo aver rimosso alcune variazioni, rendendo invalide alcune
         *  combinazioni e — soprattutto — mostrando tipologie che non possono
         *  più coprire davvero i posti richiesti (es.: 1 Singola su 2 persone).
         *  Per garantire coerenza ricalcoliamo le combo sul set filtrato.
         */
        $types_for_combo = $available_rooms; // già filtrate per data
        $solutions       = [];

        // Back‑tracking "inline" (stesso algoritmo di get_rooms_common)
        $recurse = function ( int $i, int $remain, array $cur )
            use ( &$recurse, &$solutions, $types_for_combo ) {

            if ( $remain === 0 ) {
                $solutions[] = $cur;
                return;
            }
            if ( $i >= count( $types_for_combo ) || $remain < 0 ) {
                return;
            }

            $cap    = $types_for_combo[ $i ]['capacity'];
            $stock  = $types_for_combo[ $i ]['stock_limited'] ?? $types_for_combo[ $i ]['stock'];
            $maxQty = min( $stock, intdiv( $remain, $cap ) );

            for ( $q = 0; $q <= $maxQty; $q ++ ) {
                $cur[ $i ] = $q;
                $recurse( $i + 1, $remain - $q * $cap, $cur );
            }
        };

        $recurse( 0, $num_people, [] );

        // Trasforma in combo leggibili + individua tipologie realmente usate
        $combos       = [];
        $include_type = array_fill( 0, count( $types_for_combo ), false );

        foreach ( $solutions as $sol ) {


            $rooms_used = array_sum( $sol );   // quante camere in totale
            // ●  Se è attiva la notte-extra e abbiamo *un tetto reale* (>0)
            //    scarta le soluzioni che usano più camere del consentito.
            if ( $extra_night === 1
                && $global_stock_extra !== PHP_INT_MAX    // 0 ⇒ nessuna camera idonea
                && $rooms_used > $global_stock_extra ) {
                continue; // scarta la soluzione
            }
            $combo = [
                'rooms'          => [],
                'total_capacity' => 0,
                'total_price'    => 0,
            ];

            foreach ( $sol as $idx => $qty ) {
                if ( $qty <= 0 ) {
                    continue;
                }

                $def                              = $types_for_combo[ $idx ];
                $room_total                       = $def['price_per_person'] * $def['capacity'] * $qty;
                $combo['rooms'][]                = [
                    'type'             => $def['type'],
                    'quantity'         => $qty,
                    'capacity'         => $def['capacity'],
                    'price_per_person' => $def['price_per_person'],
                    'room_total_price' => $room_total,
                ];
                $combo['total_capacity'] += $def['capacity'] * $qty;
                $combo['total_price']    += $room_total;

                // Marca la tipologia come usata solo se la combo è valida
                $include_type[ $idx ] = true;
            }

            // Accetta solo le combinazioni che coprono esattamente il numero di persone
            if ( $combo['total_capacity'] === $num_people ) {
                $combos[] = $combo;
            }
        }

        // Se l'utente ha richiesto la notte extra ma non esistono combinazioni valide,
        // interrompi e suggerisci di riprovare senza l'extra.
        if ( $extra_night === 1 && empty( $combos ) ) {
            wp_send_json_error( [
                'message' => __( 'Nessuna combinazione di camere disponibile con la notte extra selezionata. Riprova senza il pernottamento extra.', 'born-to-ride-booking' ),
                'no_combos_extra_night' => true,
            ] );
        }

        usort( $combos, function ( $a, $b ) {
            return $a['total_price'] <=> $b['total_price']
                   ?: count( $a['rooms'] ) <=> count( $b['rooms'] );
        } );

        // Filtra nuovamente $available_rooms per mantenere solo quelle usate
        $available_rooms = array_values( array_filter(
            $types_for_combo,
            function ( $room, $idx ) use ( $include_type ) {
                return ! empty( $include_type[ $idx ] );
            },
            ARRAY_FILTER_USE_BOTH
        ) );

        if (empty($available_rooms)) {
            error_log("Nessuna camera disponibile dopo l'elaborazione.");
            wp_send_json_error(['message' => 'Nessuna camera disponibile.']);
        }

        // Forza la giacenza disponibile da WooCommerce per ogni variante
        // Imposta stock e available_stock tenendo conto dell'allotment globale
        if ($tipologia_prenotazione === 'allotment_camere') {
            foreach ($available_rooms as &$room) {
                $variation_id = $room['variation_id'];
                // Quantità già scalata per questa variante
                $scaled_var = intval(get_post_meta($variation_id, '_btr_giacenza_scalata', true));
                $limit = $room['stock_limited'];

                /**
                 * Se la variante NON ha un limite specifico ($limit === PHP_INT_MAX),
                 * la disponibilità della singola camera coincide con la giacenza
                 * globale residua per la data ($global_stock).  In caso contrario
                 * viene calcolata sottraendo il venduto dal proprio limite.
                 */
                if ( $limit === PHP_INT_MAX ) {
                    $var_stock = $global_stock;
                } else {
                    $var_stock = max( 0, $limit - $scaled_var );
                }

                // ---------------- Notte extra ----------------
                if ( $extra_night === 1 && isset( $global_stock_extra ) ) {
                    // Per la variante consideriamo la MIN tra stock main e stock extra.
                    // $global_stock_extra è il *totale* di camere fisiche per la notte extra.
                    // Dobbiamo usare lo stock specifico per tipo per la notte extra, se disponibile.
                    $stock_specifico_tipo_notte_extra = $stock_per_type_extra[$room['type']] ?? PHP_INT_MAX;

                    // Lo stock della variante per la notte extra è il suo limite specifico per la notte extra (se definito),
                    // altrimenti è limitato dal $global_stock_extra (se questo non è illimitato).
                    // Questo calcolo di $var_stock_extra è complesso e potrebbe non essere necessario se la ricorsione è corretta.
                    // Per ora, lo stock della variante per la notte extra è $stock_specifico_tipo_notte_extra.
                    $var_stock_for_extra_night_type = $stock_specifico_tipo_notte_extra;

                    // Lo stock della variante deve rispettare il suo stock normale E lo stock per tipo per la notte extra.
                    $var_stock = min( $var_stock, $var_stock_for_extra_night_type );

                    // Inoltre, lo stock non può superare il numero totale di camere fisiche per la notte extra ($global_stock_extra)
                    // se $global_stock_extra è un limite effettivo.
                    if (isset($global_stock_extra) && $global_stock_extra !== PHP_INT_MAX) {
                        $var_stock = min($var_stock, $global_stock_extra);
                    }
                }

                // Garantiamo comunque che la disponibilità mostrata/resa prenotabile
                // non superi l'allotment globale residuo (principale + extra se richiesto).
                $room['stock'] = $var_stock;
                // L'available_stock dovrebbe riflettere lo stock utilizzabile. $global_stock è per la data principale.
                // Se notte extra, $global_stock_extra è il limite fisico totale per la notte extra.
                $final_available_stock = min($var_stock, $global_stock); // Limita con lo stock globale della data principale
                if ($extra_night === 1 && isset($global_stock_extra) && $global_stock_extra !== PHP_INT_MAX) {
                    $final_available_stock = min($final_available_stock, $global_stock_extra);
                }
                $room['available_stock'] = $final_available_stock;
            }
            unset($room);
        } else {
            // Comportamento standard per gli altri tipi di prenotazione
            foreach ($available_rooms as &$room) {
                $variation = wc_get_product($room['variation_id']);
                $actual_stock = $variation ? intval($variation->get_stock_quantity()) : 0;
                $room['stock'] = $actual_stock;
                $room['available_stock'] = $actual_stock;
            }
            unset($room);
        }

        // Converti l'array associativo in un array indicizzato
        $available_rooms = array_values($available_rooms);

        /* --------------------------------------------------------------
         *  Supplemento notte extra – NON viene sommato ai prezzi base
         *  Viene solo passato come campo dedicato, così il front‑end
         *  (e il fee carrello) lo gestiscono separatamente.
         *  Applica tariffe specifiche per tipologia camera:
         *  €10 per doppia, €40 per singola a persona/notte
         * -------------------------------------------------------------- */
        foreach ( $available_rooms as &$room ) {
            if ( $extra_night === 1 ) {
                // Usa sempre il prezzo allotment configurato per tutte le tipologie di camera
                $room['extra_night_pp'] = ( $extra_night_price_pp > 0 ) ? $extra_night_price_pp : 0;
                // Passa la modalità di pricing al frontend
                $room['extra_night_pricing_per_room'] = $extra_night_pricing_per_room;
                
                // Aggiungi prezzi specifici per bambini delle notti extra
                // Recupera i prezzi salvati nell'admin per questo pacchetto
                $extra_allotment_child_prices = get_post_meta($package_id, 'btr_extra_allotment_child_prices', true);
                $extra_allotment_child_prices = is_array($extra_allotment_child_prices) ? $extra_allotment_child_prices : [];
                
                // Cerca i prezzi per la data selezionata con normalizzazione
                $child_prices = [];
                if (!empty($selected_date)) {
                    // Normalizza la data selezionata per il confronto
                    $selected_start_yMd = btr_parse_range_start_yMd($selected_date);
                    if (!$selected_start_yMd) {
                        $selected_start_yMd = date('Y-m-d', strtotime($this->convert_italian_date_to_english($selected_date)));
                    }
                    
                    // Cerca nei prezzi salvati usando la data normalizzata
                    foreach ($extra_allotment_child_prices as $date_key => $prices) {
                        $date_key_normalized = btr_parse_range_start_yMd($date_key);
                        if (!$date_key_normalized) {
                            $date_key_normalized = date('Y-m-d', strtotime($this->convert_italian_date_to_english($date_key)));
                        }
                        
                        if ($date_key_normalized === $selected_start_yMd) {
                            $child_prices = $prices;
                            break;
                        }
                    }
                    
                    // Debug log
                    error_log("[EXTRA NIGHT CHILD PRICES] Selected date: $selected_date, Normalized: $selected_start_yMd, Found prices: " . print_r($child_prices, true));
                    error_log("[EXTRA NIGHT DEBUG] All extra allotment child prices: " . print_r($extra_allotment_child_prices, true));
                }
                
                // Se non ci sono prezzi specifici per la data, prova con i prezzi globali
                if (empty($child_prices)) {
                    // Prova a recuperare i prezzi globali per bambini
                    $global_child_prices = [];
                    for ($i = 1; $i <= 4; $i++) {
                        $global_enabled = get_post_meta($package_id, "btr_global_child_pricing_f{$i}_enabled", true);
                        if ($global_enabled === '1') {
                            $global_price = get_post_meta($package_id, "btr_global_child_pricing_f{$i}", true);
                            if (!empty($global_price)) {
                                $global_child_prices["f{$i}"] = floatval($global_price);
                            }
                        }
                    }
                    
                    // Se ci sono prezzi globali, usali, altrimenti fallback 50%
                    if (!empty($global_child_prices)) {
                        $room['extra_night_child_f1'] = isset($global_child_prices['f1']) ? $global_child_prices['f1'] : ($room['extra_night_pp'] / 2);
                        $room['extra_night_child_f2'] = isset($global_child_prices['f2']) ? $global_child_prices['f2'] : ($room['extra_night_pp'] / 2);
                        $room['extra_night_child_f3'] = isset($global_child_prices['f3']) ? $global_child_prices['f3'] : ($room['extra_night_pp'] / 2);
                        $room['extra_night_child_f4'] = isset($global_child_prices['f4']) ? $global_child_prices['f4'] : ($room['extra_night_pp'] / 2);
                    } else {
                        $room['extra_night_child_f1'] = $room['extra_night_pp'] / 2; // fallback 50%
                        $room['extra_night_child_f2'] = $room['extra_night_pp'] / 2; // fallback 50%
                        $room['extra_night_child_f3'] = $room['extra_night_pp'] / 2; // fallback 50%
                        $room['extra_night_child_f4'] = $room['extra_night_pp'] / 2; // fallback 50%
                    }
                } else {
                    // Usa i prezzi specifici salvati nell'admin, con fallback ai globali se mancanti
                    $global_child_prices = [];
                    for ($i = 1; $i <= 4; $i++) {
                        $global_enabled = get_post_meta($package_id, "btr_global_child_pricing_f{$i}_enabled", true);
                        if ($global_enabled === '1') {
                            $global_price = get_post_meta($package_id, "btr_global_child_pricing_f{$i}", true);
                            if (!empty($global_price)) {
                                $global_child_prices["f{$i}"] = floatval($global_price);
                            }
                        }
                    }
                    
                    $room['extra_night_child_f1'] = isset($child_prices['f1']) ? floatval($child_prices['f1']) : (isset($global_child_prices['f1']) ? $global_child_prices['f1'] : ($room['extra_night_pp'] / 2));
                    $room['extra_night_child_f2'] = isset($child_prices['f2']) ? floatval($child_prices['f2']) : (isset($global_child_prices['f2']) ? $global_child_prices['f2'] : ($room['extra_night_pp'] / 2));
                    $room['extra_night_child_f3'] = isset($child_prices['f3']) ? floatval($child_prices['f3']) : (isset($global_child_prices['f3']) ? $global_child_prices['f3'] : ($room['extra_night_pp'] / 2));
                    $room['extra_night_child_f4'] = isset($child_prices['f4']) ? floatval($child_prices['f4']) : (isset($global_child_prices['f4']) ? $global_child_prices['f4'] : ($room['extra_night_pp'] / 2));
                    
                    // Debug log dei prezzi finali per bambini
                    error_log("[EXTRA NIGHT FINAL PRICES] Room type: {$room['type']}, Adult: {$room['extra_night_pp']}, F1: {$room['extra_night_child_f1']}, F2: {$room['extra_night_child_f2']}, F3: {$room['extra_night_child_f3']}, F4: {$room['extra_night_child_f4']}");
                }
            } else {
                $room['extra_night_pp'] = 0;
                $room['extra_night_child_f1'] = 0;
                $room['extra_night_child_f2'] = 0;
                $room['extra_night_child_f3'] = 0;
                $room['extra_night_child_f4'] = 0;
            }
        }
        unset( $room );

        // Ordina le camere per capacità (decrescente) e prezzo per persona (crescente)
        usort($available_rooms, function ($a, $b) {
            if ($b['capacity'] === $a['capacity']) {
                return $a['price_per_person'] <=> $b['price_per_person'];
            }
            return $b['capacity'] <=> $a['capacity'];
        });

        // Restituisci il risultato
        $badge_rules = get_post_meta($package_id, 'btr_badge_rules', true);
        $show_disabled_rooms = get_post_meta($package_id, 'btr_show_disabled_rooms', true);

        /* ------------------------------------------------------------------
         *  ➤ Sincronizza $available_rooms con le combo effettivamente valide
         *     – Mantiene nella risposta solo le tipologie che compaiono
         *       almeno in una combinazione risultante.
         * ------------------------------------------------------------------ */
        if ( ! empty( $combos ) ) {
            $types_in_combos = [];

            foreach ( $combos as $combo ) {
                foreach ( $combo['rooms'] as $r ) {
                    $types_in_combos[ $r['type'] ] = true;
                }
            }

            $available_rooms = array_values( array_filter(
                $available_rooms,
                function ( $room ) use ( $types_in_combos ) {
                    return isset( $types_in_combos[ $room['type'] ] );
                }
            ) );
        }

        /* ------------------------------------------------------------------
         *  ► Calcola la data formattata della notte extra
         * ------------------------------------------------------------------ */
        $extra_night_date_str = '';
        $all_extra_dates = []; // Array per memorizzare tutte le date extra
        if ( $extra_night === 1 && ! empty( $extra_date ) ) {
            // Converte 2026‑02‑06 → 06/02/2026 usando date_i18n per i18n WordPress
            $extra_night_date_str = date_i18n( 'd/m/Y', strtotime( $extra_date ) );
        }

        // Calcola dinamicamente il numero di notti extra disponibili per la data selezionata
        $extra_nights_count = 0;
        error_log("[BTR] 🔍 INIZIO calcolo notti extra - selected_date: $selected_date");
        
        // Carica i dati delle notti extra se non già caricati
        if (!isset($camere_extra_allotment_by_date)) {
            $camere_extra_allotment_by_date = get_post_meta($package_id, 'btr_camere_extra_allotment_by_date', true);
        }
        
        error_log("[BTR] 🔍 camere_extra_allotment_by_date presente: " . (!empty($camere_extra_allotment_by_date) ? 'SI' : 'NO'));
        
        if (!empty($selected_date) && !empty($camere_extra_allotment_by_date)) {
            // Normalizza la data selezionata per il confronto
            $norm_selected = $selected_start_yMd ?: date('Y-m-d', strtotime($this->convert_italian_date_to_english($selected_date)));
            error_log("[BTR] 🔍 Data normalizzata: $norm_selected (da $selected_date)");
            
            foreach ($camere_extra_allotment_by_date as $date_key => $config) {
                // Normalizza la chiave data
                // Prima verifica se è già in formato Y-m-d
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_key)) {
                    $norm_key = $date_key;
                } else {
                    // Prova a normalizzare da formato italiano
                    $norm_key = btr_parse_range_start_yMd($date_key);
                    if (!$norm_key) {
                        $norm_key = date('Y-m-d', strtotime($this->convert_italian_date_to_english($date_key)));
                    }
                }
                error_log("[BTR] 🔍 Confronto: $norm_key vs $norm_selected");
                
                // Se troviamo la data corrispondente
                if ($norm_key === $norm_selected) {
                    error_log("[BTR] ✅ Data corrispondente trovata!");
                    // Conta le date nel campo range, escludendo elementi vuoti
                    if (isset($config['range']) && is_array($config['range'])) {
                        // Gestisci il caso in cui le date sono separate da virgole in una singola stringa
                        $all_dates = [];
                        foreach ($config['range'] as $date_entry) {
                            if (!empty(trim($date_entry))) {
                                // Se contiene virgole, splitta la stringa
                                if (strpos($date_entry, ',') !== false) {
                                    $split_dates = array_map('trim', explode(',', $date_entry));
                                    $all_dates = array_merge($all_dates, $split_dates);
                                } else {
                                    $all_dates[] = trim($date_entry);
                                }
                            }
                        }
                        
                        // Filtra date vuote
                        $valid_dates = array_filter($all_dates, function($date) {
                            return !empty($date);
                        });
                        
                        $extra_nights_count = count($valid_dates);
                        error_log("[BTR] 📊 Notti extra per $selected_date: " . $extra_nights_count . " notti");
                        error_log("[BTR] 📊 Date notti extra valide: " . implode(', ', $valid_dates));
                        error_log("[BTR] 📊 Array range originale: " . print_r($config['range'], true));
                        
                        // Salva tutte le date extra formattate
                        if (!empty($valid_dates)) {
                            foreach ($valid_dates as $extra_date_single) {
                                $all_extra_dates[] = date_i18n('d/m/Y', strtotime($extra_date_single));
                            }
                            // Aggiorna la stringa delle date extra
                            if (count($all_extra_dates) > 1) {
                                // Formato: "21, 22, 23/01/2026"
                                $last_date = array_pop($all_extra_dates);
                                $date_parts = explode('/', $last_date);
                                $month_year = $date_parts[1] . '/' . $date_parts[2];
                                $days = [];
                                foreach ($all_extra_dates as $date) {
                                    $days[] = explode('/', $date)[0];
                                }
                                $days[] = $date_parts[0];
                                $extra_night_date_str = implode(', ', $days) . '/' . $month_year;
                            } else if (count($all_extra_dates) === 1) {
                                $extra_night_date_str = $all_extra_dates[0];
                            }
                        }
                    } else {
                        error_log("[BTR] ❌ Campo range non trovato o non è array");
                    }
                    break;
                }
            }
            
            if ($extra_nights_count === 0) {
                error_log("[BTR] ⚠️ Nessuna corrispondenza trovata per la data $selected_date");
            }
        }
        
        error_log("[BTR] 🏁 FINE calcolo notti extra - risultato: $extra_nights_count");

        $response = [
            'combos'                 => $combos,
            'bambini_fascia1_sconto' => $sconto_f1,
            'bambini_fascia2_sconto' => $sconto_f2,
            'bambini_fascia3_sconto' => $sconto_f3,
            'bambini_fascia4_sconto' => $sconto_f4,
            'badge_rules'            => is_array($badge_rules) ? array_values($badge_rules) : [],
            'show_disabled_rooms'    => $show_disabled_rooms === '1' ? true : false,
            'global_stock'           => $global_stock,
            'extra_night'            => $extra_night === 1,
            'extra_night_date'       => $extra_night_date_str,
            'global_stock_extra'     => ($extra_night === 1 && isset($limit_total_physical_rooms_extra)) ? $limit_total_physical_rooms_extra : null,
            'child_fasce'            => $child_fasce,
            'extra_nights_count'     => $extra_nights_count, // Numero dinamico di notti extra disponibili
        ];

        // ------------------------------------------------------------------
        // Deduplica le tipologie di camere: mantieni una sola entry per tipo
        // ------------------------------------------------------------------
        if ( ! empty( $available_rooms ) ) {
            $unique_rooms = [];
            foreach ( $available_rooms as $room ) {
                // Se questa tipologia non è ancora presente, la salviamo
                if ( ! isset( $unique_rooms[ $room['type'] ] ) ) {
                    $unique_rooms[ $room['type'] ] = $room;
                }
            }
            // Sovrascrivi $available_rooms preservando l'ordine originale
            $available_rooms = array_values( $unique_rooms );
        }
        // Aggiorna il response con le stanze (eventualmente uniche)
        $response['rooms'] = $available_rooms;
        
        // Debug finale prima di inviare la risposta
        error_log("[BTR] 🚀 Risposta AJAX - extra_nights_count: " . $response['extra_nights_count']);
        
        wp_send_json_success($response);
    }




    /**
     * Recupera le camere disponibili con gestione corretta degli sconti
     */
    private function get_rooms_common($product, $num_people, $package_id, $sconto_f1 = 0, $sconto_f2 = 0, $sconto_f3 = 0, $sconto_f4 = 0)
    {
        $tipologia_prenotazione = get_post_meta($package_id, 'btr_tipologia_prenotazione', true);
        $variations = $product->get_children();
        $available_rooms = [];

        // Recupera i limiti specifici delle camere dal pacchetto
        $room_type_limits = $this->get_room_type_limits($package_id);

        foreach ($variations as $variation_id) {
            $variation = wc_get_product($variation_id);
            if ($variation && $variation->is_type('variation')) {
                $attributes = $variation->get_attributes();
                $room_type_key = isset($attributes['pa_tipologia_camere']) ? ucfirst($attributes['pa_tipologia_camere']) : '';

                // Recupera i metadati necessari
                $regular_price = floatval($variation->get_regular_price());
                $sale_price = floatval($variation->get_sale_price());
                $is_on_sale = $variation->is_on_sale();
                $supplemento = floatval(get_post_meta($variation_id, '_btr_supplemento', true));
                $individual_discount = floatval(get_post_meta($variation_id, '_btr_sconto_percentuale', true));
                $global_discount = floatval(get_post_meta($package_id, 'btr_sconto_percentuale', true));
                // Recupera la giacenza disponibile della variante da WooCommerce
                $stock_quantity = intval($variation->get_stock_quantity());
                if ($tipologia_prenotazione === 'allotment_camere' && $stock_quantity === 0) {
                    // In allotment mode, zero stock means unlimited availability
                    $stock = PHP_INT_MAX;
                    $current_stock = PHP_INT_MAX;
                } else {
                    $stock = $stock_quantity;
                    $current_stock = $stock_quantity;
                }
                $capacity = $this->get_room_capacity($room_type_key);

                // Calcola il prezzo per camera: base (eventuale promo) + supplemento UNA TANTUM
                $base_price       = $is_on_sale && $sale_price > 0 ? $sale_price : $regular_price;
                $price_per_camera = $base_price + $supplemento;

                /**
                 * Prezzo unitario per persona:
                 *   – calcolato SOLO sul prezzo base (senza supplemento) suddiviso per la capienza
                 *   – il supplemento resta per‑camera e NON viene ripartito
                 */
                $price_per_person = round( $base_price / max( 1, $capacity ), 2 );

                // DEBUG: Logga i dati dell'ultima camera del ciclo
                if ($variation_id === end($variations)) {
                    error_log("[DEBUG ultima camera] ID: $variation_id | Base price: $base_price | Capacity: $capacity | Supplemento: $supplemento | Price/person: $price_per_person");
                }

                /**
                 * Prezzo bambini: sempre applicato sul prezzo PIENO a persona (prima di sconti adulti)
                 *   – Fascia 1: sconto percentuale $sconto_f1
                 *   – Fascia 2: sconto percentuale $sconto_f2
                 *   – Fascia 3: sconto percentuale $sconto_f3
                 *   – Fascia 4: sconto percentuale $sconto_f4
                 */
                $price_child_f1 = round( $price_per_person * ( 1 - ( $sconto_f1 / 100 ) ), 2 );
                $price_child_f2 = round( $price_per_person * ( 1 - ( $sconto_f2 / 100 ) ), 2 );
                $price_child_f3 = round( $price_per_person * ( 1 - ( $sconto_f3 / 100 ) ), 2 );
                $price_child_f4 = round( $price_per_person * ( 1 - ( $sconto_f4 / 100 ) ), 2 );

                // Applica eventuali sconti ADULTI (priorità: individuale > globale)
                $applied_discount = 0;

                if ( $individual_discount > 0 ) {
                    $applied_discount = $individual_discount;
                    $price_per_person = round( $price_per_person * ( 1 - ( $individual_discount / 100 ) ), 2 );
                } elseif ( $global_discount > 0 ) {
                    $applied_discount = $global_discount;
                    $price_per_person = round( $price_per_person * ( 1 - ( $global_discount / 100 ) ), 2 );
                }

                if ($capacity > 0) {
                    // Controlla se esiste un limite specifico per questa tipologia di camera
                    $room_limit = isset($room_type_limits[$room_type_key]) ? min($room_type_limits[$room_type_key], $current_stock) : $current_stock;

                    // Aggiunge la camera all'elenco delle camere disponibili
                    $available_rooms[] = [
                        'variation_id'      => $variation_id,
                        'type'              => $room_type_key,
                        'regular_price'     => $regular_price,
                        'sale_price'        => $is_on_sale ? $sale_price : null,
                        'price_per_person'  => $price_per_person,
                        'price_child_f1'    => $price_child_f1,
                        'price_child_f2'    => $price_child_f2,
                        'price_child_f3'    => $price_child_f3,
                        'price_child_f4'    => $price_child_f4,
                        'is_on_sale'        => $is_on_sale,
                        'stock'             => $current_stock, // giacenza effettiva rimanente
                        'stock_limited'     => $room_limit, // valore usato se vuoi mostrare limiti da pacchetto
                        'capacity'          => $capacity,
                        'supplemento'       => $supplemento,
                        'sconto'            => $applied_discount,
                    ];
                }
            }
        }

        /* --------------------------------------------------------------------
         *  CALCOLO COMBINAZIONI DISPONIBILI
         * ------------------------------------------------------------------ */

        // Mappa compatta delle tipologie disponibili
        $types     = array_values( $available_rooms );
        $solutions = [];

        // Algoritmo di back‑tracking che rispetta capacità e disponibilità
        $backtrack = function ( int $idx, int $remaining, array $current )
            use ( &$backtrack, &$solutions, $types ) {

            // Capacità raggiunta perfettamente
            if ( $remaining === 0 ) {
                $solutions[] = $current;
                return;
            }

            // Nessun elemento restante o capacità negativa
            if ( $idx >= count( $types ) || $remaining < 0 ) {
                return;
            }

            $cap    = $types[ $idx ]['capacity'];
            $stock  = $types[ $idx ]['stock_limited'] ?? $types[ $idx ]['stock'];
            $maxQty = min( $stock, intdiv( $remaining, $cap ) );

            // Esplora tutte le quantità possibili
            for ( $q = 0; $q <= $maxQty; $q ++ ) {
                $current[ $idx ] = $q;
                $backtrack( $idx + 1, $remaining - ( $q * $cap ), $current );
            }
        };

        // Avvia la ricerca
        $backtrack( 0, $num_people, [] );

        /* --------------------------------------------------------------------
         *  TRASFORMA SOLUZIONI IN COMBINAZIONI "UMANE"
         * ------------------------------------------------------------------ */
        $combos = [];
        foreach ( $solutions as $sol ) {
            $combo = [
                'rooms'          => [],
                'total_capacity' => 0,
                'total_price'    => 0,
            ];

            foreach ( $sol as $i => $qty ) {
                if ( $qty <= 0 ) {
                    continue;
                }

                $def               = $types[ $i ];
                $roomPrice         = $def['price_per_person'] * $def['capacity'] * $qty
                                       + $def['supplemento'] * $qty;
                $combo['rooms'][]  = [
                    'type'              => $def['type'],
                    'quantity'          => $qty,
                    'capacity'          => $def['capacity'],
                    'price_per_person'  => $def['price_per_person'],
                    'room_total_price'  => $roomPrice,
                ];
                $combo['total_capacity'] += $def['capacity'] * $qty;
                $combo['total_price']    += $roomPrice;
            }

            if ( $combo['total_capacity'] === $num_people ) {
                $combos[] = $combo;
            }
        }

        // Ordina per prezzo totale crescente, poi per numero di stanze
        usort( $combos, function ( $a, $b ) {
            return $a['total_price'] <=> $b['total_price']
                   ?: count( $a['rooms'] ) <=> count( $b['rooms'] );
        } );

        /* --------------------------------------------------------------------
         *  TIPOLOGIE REALMENTE UTILIZZABILI
         * ------------------------------------------------------------------ */
        $include = array_fill( 0, count( $types ), false );
        foreach ( $solutions as $comb ) {
            foreach ( $comb as $idx => $qty ) {
                if ( $qty > 0 ) {
                    $include[ $idx ] = true;
                }
            }
        }

        $filtered = [];
        foreach ( $types as $idx => $room ) {
            if ( $include[ $idx ] ) {
                $filtered[] = $room;
            }
        }

        // Restituisce le stanze filtrate e le combinazioni trovate
        return [
            'rooms'  => array_values( $filtered ),
            'combos' => $combos,
        ];
    }

    /**
     * AJAX: Processa la prenotazione
     */
    public function process_booking()
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $package_id = intval($_POST['package_id'] ?? 0);
        $product_id = intval($_POST['product_id'] ?? 0);
        $selected_rooms = $_POST['rooms'] ?? [];
        $num_people = intval($_POST['num_people'] ?? 0);
        $total_price = floatval($_POST['total_price'] ?? 0);
        // AGGIUNTA: Parametro notte extra
        $extra_night = intval($_POST['extra_night'] ?? 0);
        $selected_date = sanitize_text_field($_POST['selected_date'] ?? '');
        
        // Recupera i dati anagrafici e assicurazioni
        $anagrafici = $_POST['anagrafici'] ?? [];
        $cliente_nome = sanitize_text_field($_POST['cliente_nome'] ?? '');
        $cliente_email = sanitize_email($_POST['cliente_email'] ?? '');

        // Log iniziale aggiornato
        error_log("AJAX process_booking chiamato con package_id: $package_id, product_id: $product_id, selected_rooms: " . json_encode($selected_rooms) . ", num_people: $num_people, total_price: $total_price, extra_night: $extra_night, selected_date: $selected_date");
        error_log("Dati cliente: nome = $cliente_nome, email = $cliente_email");
        error_log("Anagrafici ricevuti: " . json_encode($anagrafici));

        if (!$package_id || !$product_id || empty($selected_rooms)) {
            error_log("Dati di prenotazione incompleti: package_id = $package_id, product_id = $product_id, selected_rooms = " . json_encode($selected_rooms));
            wp_send_json_error(['message' => 'Dati di prenotazione incompleti.']);
        }

        // Recupera la tipologia di prenotazione
        $tipologia_prenotazione = get_post_meta($package_id, 'btr_tipologia_prenotazione', true);

        // Processa la prenotazione in base alla tipologia
        $result = $this->process_booking_common($package_id, $product_id, $selected_rooms, $num_people, $total_price);

        if ($result['success']) {
            wp_send_json_success(['message' => 'Prenotazione completata con successo!']);
        } else {
            wp_send_json_error(['message' => $result['message']]);
        }
    }

    /**
     * Processa la prenotazione considerando correttamente gli sconti
     */
    private function process_booking_common($package_id, $product_id, $selected_rooms, $num_people, $total_price)
    {
        // Validate selected rooms
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_type('variable')) {
            error_log("Invalid product or not a variable product: product_id = $product_id");
            return ['success' => false, 'message' => 'Prodotto non valido.'];
        }

        $variations = $product->get_children();
        $available_rooms = [];

        // Crea una mappa delle camere disponibili
        foreach ($variations as $variation_id) {
            $variation = wc_get_product($variation_id);
            if ($variation && $variation->is_type('variation')) {
                $attributes = $variation->get_attributes();
                $room_type = isset($attributes['pa_tipologia_camere']) ? ucfirst($attributes['pa_tipologia_camere']) : '';
                $price_per_person = floatval($variation->get_price()); // Prezzo già scontato
                $stock = intval($variation->get_stock_quantity());
                $capacity = $this->get_room_capacity($room_type);
                $supplemento = floatval(get_post_meta($variation_id, '_btr_supplemento', true));
                $sconto_percentuale = floatval(get_post_meta($variation_id, '_btr_sconto_percentuale', true));

                if ($capacity > 0 && $stock > 0) {
                    $available_rooms[$room_type] = [
                        'id'          => $variation_id,
                        'type'        => $room_type,
                        'price'       => $price_per_person + $supplemento, // Prezzo per persona inclusi supplementi e sconti
                        'supplemento' => $supplemento,
                        'stock'       => $stock,
                        'capacity'    => $capacity,
                        'sconto'      => $sconto_percentuale,
                    ];
                }
            }
        }

        $total_capacity = 0;
        $rooms_to_book = [];
        $calculated_total_price = 0;

        foreach ($selected_rooms as $room) {
            $room_type = $room['type'];
            $quantity = intval($room['quantity']);

            if (!isset($available_rooms[$room_type])) {
                error_log("Selected room type not available: {$room_type}");
                return ['success' => false, 'message' => "Tipo di camera '{$room_type}' non disponibile."];
            }

            if ($available_rooms[$room_type]['stock'] < $quantity) {
                error_log("Selected room type exceeds available stock: {$room_type}");
                return ['success' => false, 'message' => "Stock insufficiente per il tipo di camera '{$room_type}'."];
            }

            $capacity = $available_rooms[$room_type]['capacity'];
            $price_per_person = $available_rooms[$room_type]['price']; // Prezzo già scontato
            $room_total_price = ( $price_per_person * $capacity * $quantity )
                                + ( $available_rooms[$room_type]['supplemento'] * $quantity );

            $rooms_to_book[] = [
                'id'               => $available_rooms[$room_type]['id'],
                'type'             => $room_type,
                'price_per_person' => $price_per_person,
                'supplemento'      => $available_rooms[$room_type]['supplemento'],
                'quantity'         => $quantity,
                'capacity'         => $capacity,
                'room_total_price' => $room_total_price,
            ];

            $total_capacity += $capacity * $quantity;
            $available_rooms[$room_type]['stock'] -= $quantity; // Update stock

            // Somma il prezzo totale
            $calculated_total_price += $room_total_price;
        }

        // Confronta il prezzo totale calcolato con quello inviato dal front-end
        if (abs($calculated_total_price - $total_price) > 0.01) {
            error_log("Discrepanza nel prezzo: Calcolato = €$calculated_total_price, Inviato = €$total_price");
            return ['success' => false, 'message' => "Errore nel calcolo del prezzo. Si prega di riprovare."];
        }

        // Verifica che la capacità totale corrisponda al numero di persone
        if ($total_capacity != $num_people) {
            error_log("Capacità totale ($total_capacity) non corrisponde al numero di persone ($num_people)");
            return ['success' => false, 'message' => "La capacità totale delle camere selezionate ($total_capacity) non corrisponde al numero di persone ($num_people)."];
        }

        // Aggiorna le quantità di stock
        foreach ($rooms_to_book as $room) {
            $variation = wc_get_product($room['id']);
            if ($variation) {
                $new_stock = $variation->get_stock_quantity() - $room['quantity'];
                $variation->set_stock_quantity($new_stock);
                // Aggiorna _btr_giacenza_scalata
                $current_scaled = intval(get_post_meta($room['id'], '_btr_giacenza_scalata', true));
                $new_scaled = $current_scaled + $room['quantity'];
                update_post_meta($room['id'], '_btr_giacenza_scalata', $new_scaled);

                // AGGIUNTA: Se è stata selezionata la notte extra, aggiorna anche quella
                if (isset($_POST['extra_night']) && intval($_POST['extra_night']) === 1) {
                    $selected_date = sanitize_text_field($_POST['selected_date'] ?? '');
                    if (!empty($selected_date)) {
                        $extra_date = date('Y-m-d', strtotime($selected_date . ' -1 day'));
                        $current_scaled_extra = intval(get_post_meta($room['id'], '_btr_giacenza_scalata_' . $extra_date, true));
                        $new_scaled_extra = $current_scaled_extra + $room['quantity'];
                        update_post_meta($room['id'], '_btr_giacenza_scalata_' . $extra_date, $new_scaled_extra);
                        error_log("[GIACENZA BOOKING EXTRA] Variante ID {$room['id']} aggiornata per data extra {$extra_date}: scalato da {$current_scaled_extra} a {$new_scaled_extra}");
                    }
                }
                error_log("[GIACENZA BOOKING] Variante ID {$room['id']} aggiornata: scalato da {$current_scaled} a {$new_scaled}");
                $variation->save();
                error_log("Stock aggiornato per {$room['type']}: nuovo stock = {$new_stock}");
            } else {
                error_log("Errore nell'aggiornamento dello stock per la camera ID: {$room['id']}");
                return ['success' => false, 'message' => "Errore nell'aggiornamento dello stock per la camera '{$room['type']}'."];
            }
        }

        // AGGIUNTA: Aggiorna allotment globale
        if (isset($_POST['extra_night']) && intval($_POST['extra_night']) === 1) {
            $selected_date = sanitize_text_field($_POST['selected_date'] ?? '');
            if (!empty($selected_date)) {
                // Aggiorna allotment globale per data principale
                $current_global_scaled = intval(get_post_meta($product_id, '_btr_giacenza_scalata_globale_' . $selected_date, true));
                $total_rooms_booked = array_sum(array_column($rooms_to_book, 'quantity'));
                $new_global_scaled = $current_global_scaled + $total_rooms_booked;
                update_post_meta($product_id, '_btr_giacenza_scalata_globale_' . $selected_date, $new_global_scaled);
                
                // Aggiorna allotment globale per notte extra
                $extra_date = date('Y-m-d', strtotime($selected_date . ' -1 day'));
                $current_global_scaled_extra = intval(get_post_meta($product_id, '_btr_giacenza_scalata_globale_' . $extra_date, true));
                $new_global_scaled_extra = $current_global_scaled_extra + $total_rooms_booked;
                update_post_meta($product_id, '_btr_giacenza_scalata_globale_' . $extra_date, $new_global_scaled_extra);
                
                error_log("[ALLOTMENT GLOBALE] Aggiornato per {$selected_date}: {$current_global_scaled} -> {$new_global_scaled}");
                error_log("[ALLOTMENT GLOBALE EXTRA] Aggiornato per {$extra_date}: {$current_global_scaled_extra} -> {$new_global_scaled_extra}");
            }
        }

        // Opzionalmente, creare un ordine o eseguire altre azioni

        return ['success' => true];
    }

    /**
     * Mappa il tipo di camera alla sua capacità
     */
    private function get_room_capacity($room_type)
    {
        $room_capacity_map = [
            'Singola'   => 1,
            'Doppia'    => 2,
            'Tripla'    => 3,
            'Quadrupla' => 4,
            'Quintupla' => 5,
            'Condivisa' => 1, // Modifica la capacità se necessario
        ];

        if (isset($room_capacity_map[$room_type])) {
            return $room_capacity_map[$room_type];
        } else {
            error_log("Tipo di camera non mappato: $room_type");
            return 0; // O un valore predefinito
        }
    }

    /**
     * Recupera i limiti delle tipologie di camere dal pacchetto
     */
    private function get_room_type_limits($package_id)
    {
        $limits = [];
        $room_limit_meta_keys = [
            'Singola'   => 'btr_num_singole_max',
            'Doppia'    => 'btr_num_doppie_max',
            'Tripla'    => 'btr_num_triple_max',
            'Quadrupla' => 'btr_num_quadruple_max',
            'Quintupla' => 'btr_num_quintuple_max',
            'Condivisa' => 'btr_num_condivisa_max',
        ];

        foreach ($room_limit_meta_keys as $room_type => $meta_key) {
            $limit = intval(get_post_meta($package_id, $meta_key, true));
            if ($limit > 0) {
                $limits[$room_type] = $limit;
            }
        }

        return $limits;
    }

    public function ajax_get_assicurazioni_config() {
        $package_id = isset($_POST['package_id']) ? intval($_POST['package_id']) : 0;
        if ( ! $package_id ) {
            wp_send_json_error( [ 'message' => 'ID pacchetto non valido.' ] );
        }

        // ---------------- Assicurazioni ----------------
        $assicurazioni = get_post_meta( $package_id, 'btr_assicurazione_importi', true );
        $assicurazioni = is_array( $assicurazioni ) ? $assicurazioni : [];

        foreach ( $assicurazioni as &$a ) {
            if ( empty( $a['slug'] ) && ! empty( $a['descrizione'] ) ) {
                $a['slug'] = sanitize_title( $a['descrizione'] );
            }
        }
        unset( $a );

        // ---------------- Costi extra ----------------
        $costi_extra = get_post_meta( $package_id, 'btr_costi_extra', true );
        $costi_extra = is_array( $costi_extra ) ? $costi_extra : [];

        foreach ( $costi_extra as &$extra ) {
            if ( empty( $extra['slug'] ) && ! empty( $extra['nome'] ) ) {
                $extra['slug'] = sanitize_title( $extra['nome'] );
            }
        }
        unset( $extra );

        wp_send_json_success( [
            'assicurazioni' => array_values( $assicurazioni ),
            'costi_extra'   => array_values( $costi_extra ),
        ] );
    }

    /**
     * Calcola il prezzo minimo adulto per la visualizzazione "Prezzo a partire da"
     * 
     * @param WC_Product $product Prodotto WooCommerce
     * @return float|null Prezzo minimo adulto o null se non trovato
     */
    private function get_minimum_adult_price($product) {
        if (!$product || !$product->is_type('variable')) {
            return null;
        }

        $min_price = null;
        $variations = $product->get_available_variations();

        foreach ($variations as $variation_data) {
            $variation_id = $variation_data['variation_id'];
            $variation = wc_get_product($variation_id);
            
            if (!$variation || !$variation->is_in_stock()) {
                continue;
            }

            // Ottieni il prezzo base della variazione (senza supplementi)
            $price = $variation->get_price();
            
            if ($price > 0) {
                if ($min_price === null || $price < $min_price) {
                    $min_price = $price;
                }
            }
        }

        return $min_price;
    }

    /**
     * Render del form delle assicurazioni per i partecipanti
     * 
     * @param array $atts Attributi shortcode
     * @return string HTML del form assicurazioni
     */
    public function render_form_assicurazioni($atts = []) {
        // Estrai attributi con valori di default
        $atts = shortcode_atts([
            'package_id' => 0,
            'anagrafici' => '[]'
        ], $atts, 'btr_seleziona_assicurazioni');

        $package_id = intval($atts['package_id']);
        if (empty($package_id)) {
            return '<p class="btr-error">Package ID non specificato.</p>';
        }

        // Decodifica i dati anagrafici se passati come JSON
        $anagrafici = [];
        if (is_string($atts['anagrafici'])) {
            $anagrafici = json_decode(stripslashes($atts['anagrafici']), true);
        } elseif (is_array($atts['anagrafici'])) {
            $anagrafici = $atts['anagrafici'];
        }

        if (empty($anagrafici)) {
            return '<p class="btr-error">Nessun partecipante trovato.</p>';
        }

        // Inizia buffer output
        ob_start();
        
        // Include il template delle assicurazioni
        $template_path = BTR_PLUGIN_DIR . 'templates/admin/btr-form-assicurazioni.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<p class="btr-error">Template assicurazioni non trovato.</p>';
        }

        return ob_get_clean();
    }

    /**
     * AJAX handler per il rendering del form assicurazioni
     */
    public function ajax_render_assicurazioni_form() {
        // Debug iniziale
        error_log('[BTR] ajax_render_assicurazioni_form avviato');
        
        // Verifica nonce per sicurezza
        if (!wp_verify_nonce($_POST['nonce'], 'btr_ajax_nonce')) {
            error_log('[BTR] Nonce verification failed');
            wp_die(json_encode(['success' => false, 'data' => 'Nonce verification failed.']));
        }

        $package_id = intval($_POST['package_id']);
        $anagrafici = $_POST['anagrafici'];

        error_log('[BTR] Package ID: ' . $package_id);
        error_log('[BTR] Anagrafici ricevuti: ' . print_r($anagrafici, true));

        if (empty($package_id)) {
            error_log('[BTR] Package ID vuoto');
            wp_die(json_encode(['success' => false, 'data' => 'Package ID non valido.']));
        }

        if (empty($anagrafici) || !is_array($anagrafici)) {
            error_log('[BTR] Dati anagrafici non validi');
            wp_die(json_encode(['success' => false, 'data' => 'Dati partecipanti non validi.']));
        }

        try {
            // Genera il form HTML usando il metodo shortcode
            error_log('[BTR] Chiamata render_form_assicurazioni con ' . count($anagrafici) . ' partecipanti');
            
            $form_html = $this->render_form_assicurazioni([
                'package_id' => $package_id,
                'anagrafici' => $anagrafici
            ]);

            error_log('[BTR] Form HTML generato, lunghezza: ' . strlen($form_html));

            if (empty($form_html)) {
                error_log('[BTR] Form HTML vuoto');
                wp_die(json_encode(['success' => false, 'data' => 'Errore nella generazione del form.']));
            }

            // Aggiungi header e wrapper per il form
            $complete_html = sprintf(
                '<div class="btr-form">
                    <h2 class="form-title">Selezione Assicurazioni</h2>
                    <p class="form-subtitle">Seleziona le assicurazioni desiderate per ogni partecipante</p>
                    %s
                </div>',
                $form_html
            );

            error_log('[BTR] HTML completo generato, lunghezza: ' . strlen($complete_html));
            wp_die($complete_html);

        } catch (Exception $e) {
            error_log('[BTR] Errore nella generazione form assicurazioni: ' . $e->getMessage());
            wp_die(json_encode(['success' => false, 'data' => 'Errore interno del server.']));
        }
    }

    /**
     * AJAX: Verifica se esistono notti extra configurate per una data specifica
     * @since 1.0.18
     */
    public function check_extra_night_availability()
    {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $package_id = intval($_POST['package_id'] ?? 0);
        $selected_date = sanitize_text_field($_POST['selected_date'] ?? '');
        $num_people = intval($_POST['num_people'] ?? 0);
        $num_children = intval($_POST['num_children'] ?? 0);

        if (!$package_id || !$selected_date) {
            wp_send_json_error(['message' => 'Parametri mancanti per la verifica delle notti extra.']);
        }

        // Verifica se esistono notti extra configurate
        $has_extra_nights = $this->has_configured_extra_nights($package_id, $selected_date, $num_people, $num_children);
        
        // Ottieni i dettagli delle notti extra se disponibili
        $extra_night_details = null;
        if ($has_extra_nights) {
            $extra_night_details = $this->get_extra_night_details($package_id, $selected_date);
        }

        wp_send_json_success([
            'has_extra_nights' => $has_extra_nights,
            'extra_night_details' => $extra_night_details,
            'message' => $has_extra_nights ? 'Notti extra disponibili' : 'Nessuna notte extra configurata'
        ]);
    }

    /**
     * Verifica se esistono notti extra configurate per un pacchetto e data specifica
     * 
     * @param int $package_id ID del pacchetto
     * @param string $selected_date Data selezionata (formato Y-m-d o range)
     * @param int $num_people Numero di persone
     * @param int $num_children Numero di bambini
     * @return bool True se ci sono notti extra configurate e disponibili
     * @since 1.0.18
     */
    private function has_configured_extra_nights($package_id, $selected_date, $num_people = 0, $num_children = 0)
    {
        error_log("[BTR] 🔍 Verifica notti extra - Package: $package_id, Data: $selected_date, Persone: $num_people, Bambini: $num_children");
        
        // 1. Verifica meta dedicato alle notti extra
        $extra_allotment = get_post_meta($package_id, 'btr_camere_extra_allotment_by_date', true);
        
        if (is_array($extra_allotment) && !empty($extra_allotment)) {
            error_log("[BTR] ✅ Trovato meta btr_camere_extra_allotment_by_date con " . count($extra_allotment) . " configurazioni");
            
            // Normalizza la data selezionata
            $norm_selected = $this->normalize_date_for_comparison($selected_date);
            error_log("[BTR] 📅 Data normalizzata: $norm_selected (da $selected_date)");
            
            foreach ($extra_allotment as $date_key => $config) {
                $norm_key = $this->normalize_date_for_comparison($date_key);
                error_log("[BTR] 🔄 Confronto date: $norm_key vs $norm_selected");
                
                // Se la data corrisponde e ha configurazioni valide
                if ($norm_key === $norm_selected) {
                    error_log("[BTR] ✓ Data corrispondente trovata: $date_key");
                    error_log("[BTR] 📋 Configurazione completa: " . json_encode($config));
                    
                    // Prima verifica: controlla se esiste il campo 'totale' e ha un valore > 0
                    $totale = intval($config['totale'] ?? 0);
                    error_log("[BTR] 🔢 Campo totale: $totale");
                    
                    if ($totale > 0) {
                        error_log("[BTR] ✅ Campo totale valido ($totale camere), notti extra disponibili");
                        
                        // Verifica disponibilità se specificato numero di persone
                        if ($num_people > 0) {
                            $has_capacity = $this->check_extra_night_capacity($package_id, $date_key, $num_people, $num_children);
                            error_log("[BTR] " . ($has_capacity ? "✅" : "❌") . " Capacità notti extra: " . ($has_capacity ? "Disponibile" : "Non disponibile"));
                            return $has_capacity;
                        }
                        error_log("[BTR] ✅ Notti extra disponibili (senza verifica capacità)");
                        return true;
                    }
                    
                    // Fallback: verifica se ha range di notti extra definite (per compatibilità)
                    if (isset($config['range']) && is_array($config['range']) && !empty(array_filter($config['range']))) {
                        error_log("[BTR] ✓ Configurazioni range notti extra trovate (fallback)");
                        
                        // Verifica disponibilità se specificato numero di persone
                        if ($num_people > 0) {
                            $has_capacity = $this->check_extra_night_capacity($package_id, $date_key, $num_people, $num_children);
                            error_log("[BTR] " . ($has_capacity ? "✅" : "❌") . " Capacità notti extra: " . ($has_capacity ? "Disponibile" : "Non disponibile"));
                            return $has_capacity;
                        }
                        error_log("[BTR] ✅ Notti extra disponibili (senza verifica capacità)");
                        return true;
                    } else {
                        error_log("[BTR] ❌ Nessun campo totale valido o range di notti extra definito per questa data");
                    }
                }
            }
            error_log("[BTR] ❌ Nessuna data corrispondente trovata nel meta btr_camere_extra_allotment_by_date");
        } else {
            error_log("[BTR] ❌ Meta btr_camere_extra_allotment_by_date vuoto o non valido");
        }

        // 2. Fallback: verifica nel meta allotment principale se contiene configurazioni extra
        $main_allotment = get_post_meta($package_id, 'btr_camere_allotment', true);
        
        if (is_array($main_allotment)) {
            error_log("[BTR] 🔍 Fallback: verifica nel meta btr_camere_allotment");
            $norm_selected = $this->normalize_date_for_comparison($selected_date);
            
            foreach ($main_allotment as $date_key => $config) {
                $norm_key = $this->normalize_date_for_comparison($date_key);
                
                if ($norm_key === $norm_selected) {
                    error_log("[BTR] ✓ Data corrispondente trovata in allotment principale: $date_key");
                    
                    // Cerca configurazioni notte extra nelle tipologie camere
                    foreach (['Singola', 'Doppia', 'Tripla', 'Quadrupla', 'Quintupla', 'Condivisa'] as $room_type) {
                        if (isset($config[$room_type]['extra_night_available']) && 
                            $config[$room_type]['extra_night_available'] === '1') {
                            
                            error_log("[BTR] ✓ Configurazione notte extra trovata per camera $room_type");
                            
                            if ($num_people > 0) {
                                $has_capacity = $this->check_extra_night_capacity($package_id, $date_key, $num_people, $num_children);
                                error_log("[BTR] " . ($has_capacity ? "✅" : "❌") . " Capacità notti extra (fallback): " . ($has_capacity ? "Disponibile" : "Non disponibile"));
                                return $has_capacity;
                            }
                            error_log("[BTR] ✅ Notti extra disponibili in fallback (senza verifica capacità)");
                            return true;
                        }
                    }
                    error_log("[BTR] ❌ Nessuna configurazione notte extra trovata nelle tipologie camere");
                }
            }
            error_log("[BTR] ❌ Nessuna data corrispondente trovata nel meta btr_camere_allotment");
        } else {
            error_log("[BTR] ❌ Meta btr_camere_allotment vuoto o non valido");
        }

        error_log("[BTR] ❌ Nessuna configurazione notte extra trovata per questa data");
        return false;
    }

    /**
     * Verifica la capacità disponibile per le notti extra
     * 
     * @param int $package_id ID del pacchetto
     * @param string $date_key Chiave della data
     * @param int $num_people Numero di persone
     * @param int $num_children Numero di bambini
     * @return bool True se c'è capacità sufficiente
     * @since 1.0.18
     */
    private function check_extra_night_capacity($package_id, $date_key, $num_people, $num_children)
    {
        // Calcola la data della notte extra (giorno precedente)
        $norm_date = $this->normalize_date_for_comparison($date_key);
        $extra_date = date('Y-m-d', strtotime($norm_date . ' -1 day'));
        error_log("[BTR] 📅 Data notte extra: $extra_date (da $norm_date)");
        
        // Recupera il product_id associato al pacchetto
        $product_id = get_post_meta($package_id, '_btr_product_id', true);
        if (!$product_id) {
            error_log("[BTR] ❌ Nessun product_id associato al pacchetto $package_id");
            return false;
        }
        error_log("[BTR] 🔗 Product ID associato: $product_id");

        // Verifica giacenza globale per la notte extra
        $origin_extra = intval(get_post_meta($product_id, '_btr_giacenza_origine_globale_' . $extra_date, true));
        $scaled_extra = intval(get_post_meta($product_id, '_btr_giacenza_scalata_globale_' . $extra_date, true));
        error_log("[BTR] 📊 Giacenza notte extra - Origine: $origin_extra, Scalata: $scaled_extra");
        
        // Se non c'è giacenza impostata, considera disponibile
        if ($origin_extra === 0) {
            error_log("[BTR] ✅ Nessuna giacenza impostata, considerata disponibile");
            return true;
        }
        
        $available_extra = max(0, $origin_extra - $scaled_extra);
        error_log("[BTR] 📊 Disponibilità notte extra: $available_extra camere");
        
        // Verifica se c'è capacità sufficiente per il numero di persone richiesto
        // Considera un minimo di 1 camera necessaria, indipendentemente dal numero di persone
        $is_available = $available_extra >= 1;
        error_log("[BTR] " . ($is_available ? "✅" : "❌") . " Capacità notte extra: " . ($is_available ? "Sufficiente" : "Insufficiente"));
        return $is_available;
    }

    /**
     * Ottiene i dettagli delle notti extra per una data specifica
     * 
     * @param int $package_id ID del pacchetto
     * @param string $selected_date Data selezionata
     * @return array|null Dettagli delle notti extra o null se non disponibili
     * @since 1.0.18
     */
    private function get_extra_night_details($package_id, $selected_date)
    {
        error_log("[BTR] 🔍 Recupero dettagli notti extra - Package: $package_id, Data: $selected_date");
        
        $extra_allotment = get_post_meta($package_id, 'btr_camere_extra_allotment_by_date', true);
        
        if (!is_array($extra_allotment)) {
            error_log("[BTR] ❌ Meta btr_camere_extra_allotment_by_date non valido");
            return null;
        }

        $norm_selected = $this->normalize_date_for_comparison($selected_date);
        error_log("[BTR] 📅 Data normalizzata: $norm_selected (da $selected_date)");
        
        foreach ($extra_allotment as $date_key => $config) {
            $norm_key = $this->normalize_date_for_comparison($date_key);
            
            if ($norm_key === $norm_selected) {
                error_log("[BTR] ✓ Data corrispondente trovata: $date_key");
                
                // Calcola la data della notte extra
                $extra_date = date('Y-m-d', strtotime($norm_selected . ' -1 day'));
                $extra_date_formatted = date_i18n('d/m/Y', strtotime($extra_date));
                
                $details = [
                    'extra_date' => $extra_date,
                    'extra_date_formatted' => $extra_date_formatted,
                    'range' => $config['range'] ?? [],
                    'pricing_per_room' => isset($config['pricing_per_room']) && $config['pricing_per_room'] === '1',
                    'has_custom_pricing' => !empty($config['child_pricing']) || !empty($config['adult_pricing'])
                ];
                
                error_log("[BTR] ✅ Dettagli notti extra trovati: " . json_encode($details));
                return $details;
            }
        }

        // Fallback: prova a recuperare i dettagli dall'allotment principale
        $main_allotment = get_post_meta($package_id, 'btr_camere_allotment', true);
        
        if (is_array($main_allotment)) {
            error_log("[BTR] 🔍 Fallback: ricerca dettagli in btr_camere_allotment");
            
            foreach ($main_allotment as $date_key => $config) {
                $norm_key = $this->normalize_date_for_comparison($date_key);
                
                if ($norm_key === $norm_selected) {
                    error_log("[BTR] ✓ Data corrispondente trovata in allotment principale: $date_key");
                    
                    // Verifica se almeno una tipologia camera ha notti extra
                    $has_extra_night = false;
                    foreach (['Singola', 'Doppia', 'Tripla', 'Quadrupla', 'Quintupla', 'Condivisa'] as $room_type) {
                        if (isset($config[$room_type]['extra_night_available']) && 
                            $config[$room_type]['extra_night_available'] === '1') {
                            $has_extra_night = true;
                            break;
                        }
                    }
                    
                    if ($has_extra_night) {
                        $extra_date = date('Y-m-d', strtotime($norm_selected . ' -1 day'));
                        $extra_date_formatted = date_i18n('d/m/Y', strtotime($extra_date));
                        
                        $details = [
                            'extra_date' => $extra_date,
                            'extra_date_formatted' => $extra_date_formatted,
                            'range' => [],
                            'pricing_per_room' => false,
                            'has_custom_pricing' => false
                        ];
                        
                        error_log("[BTR] ✅ Dettagli notti extra trovati (fallback): " . json_encode($details));
                        return $details;
                    }
                }
            }
        }

        error_log("[BTR] ❌ Nessun dettaglio notte extra trovato per questa data");
        return null;
    }

    /**
     * Normalizza una data per il confronto
     * 
     * @param string $date Data da normalizzare
     * @return string Data in formato Y-m-d
     * @since 1.0.18
     */
    private function normalize_date_for_comparison($date)
    {
        error_log("[BTR] 🔄 Normalizzazione data: '$date'");
        
        if (empty($date)) {
            error_log("[BTR] ❌ Data vuota");
            return '';
        }
        
        // Prima prova: gestione diretta formato "DD - DD Mese YYYY"
        if (preg_match('/(\d{1,2})\s*[-–—]\s*(\d{1,2})\s+([A-Za-zÀ-ÿ]+)\s+(\d{4})/u', $date, $matches)) {
            $start_day = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $month_name = strtolower($matches[3]);
            $year = $matches[4];
            
            $months = [
                'gennaio' => '01', 'febbraio' => '02', 'marzo' => '03', 'aprile' => '04',
                'maggio' => '05', 'giugno' => '06', 'luglio' => '07', 'agosto' => '08',
                'settembre' => '09', 'ottobre' => '10', 'novembre' => '11', 'dicembre' => '12'
            ];
            
            if (isset($months[$month_name])) {
                $normalized = "$year-{$months[$month_name]}-$start_day";
                error_log("[BTR] ✅ Normalizzazione diretta riuscita: '$date' -> '$normalized'");
                return $normalized;
            }
        }
        
        // Usa la funzione esistente btr_parse_range_start_yMd se disponibile
        if (function_exists('btr_parse_range_start_yMd')) {
            $normalized = btr_parse_range_start_yMd($date);
            if ($normalized) {
                error_log("[BTR] ✅ Normalizzazione btr_parse_range_start_yMd riuscita: '$date' -> '$normalized'");
                return $normalized;
            }
        }
        
        // Fallback: converte data italiana in inglese e normalizza
        $english_date = $this->convert_italian_date_to_english($date);
        $timestamp = strtotime($english_date);
        
        if ($timestamp !== false) {
            $normalized = date('Y-m-d', $timestamp);
            error_log("[BTR] ✅ Normalizzazione fallback riuscita: '$date' -> '$english_date' -> '$normalized'");
            return $normalized;
        }
        
        error_log("[BTR] ❌ Normalizzazione fallita per: '$date'");
        return '';
    }
}

