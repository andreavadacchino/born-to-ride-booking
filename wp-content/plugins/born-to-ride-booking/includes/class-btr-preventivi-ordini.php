<?php

class BTR_Preventivo_To_Order
{
    private const NONCE_ACTION_CONVERT = 'btr_convert_to_checkout_nonce';
    private const NONCE_ANAGRAFICA_COMPILE = 'btr_anagrafica_compile_nonce';

    /**
     * Register preventivo_id field for WooCommerce Blocks checkout
     * FIX DEFINITIVO per errore "field label is required" in WC 8.6+
     */
    public function register_preventivo_checkout_field() {
        // Verifica che la funzione esista
        if (!function_exists('woocommerce_register_additional_checkout_field')) {
            return;
        }
        
        // Usa hook corretto con priorità alta per assicurarsi che WC sia inizializzato
        add_action('woocommerce_init', function() {
            // Double-check che la funzione sia ancora disponibile
            if (!function_exists('woocommerce_register_additional_checkout_field')) {
                return;
            }
            
            // Evita doppia registrazione
            static $registered = false;
            if ($registered) {
                return;
            }
            $registered = true;
            
            // Get default preventivo_id from session if available
            $default = '';
            if (function_exists('WC') && null !== WC()->session) {
                $default = WC()->session->get('_preventivo_id');
            }
            
            // Registra il campo con parametri completi per WC 8.6+
            try {
                woocommerce_register_additional_checkout_field([
                    'id'          => 'btr/preventivo_id',
                    'label'       => __('Preventivo ID', 'born-to-ride-booking'), // Label obbligatoria localizzata
                    'location'    => 'order',     // Location specifica richiesta in WC 8.6+
                    'type'        => 'text',      // 'hidden' deprecato, usa 'text' con show_in_order
                    'show_in_order' => false,     // Nasconde il campo nell'ordine
                    'show_in_admin' => false,     // Nasconde nell'admin WC
                    'default'     => $default,
                    'required'    => false,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function($value) {
                        // Validazione preventivo_id - deve essere numerico se presente
                        if (!empty($value) && !is_numeric($value)) {
                            return new WP_Error('invalid_preventivo_id', __('ID Preventivo non valido', 'born-to-ride-booking'));
                        }
                        return true;
                    }
                ]);
            } catch (Exception $e) {
                // Log errore senza bloccare l'esecuzione
                error_log('BTR: Errore registrazione campo checkout - ' . $e->getMessage());
            }
        }, 999); // Priorità alta per assicurarsi che WC sia completamente inizializzato
    }
    
    public function __construct()
    {
        // Registra gli hook
        add_action('admin_post_btr_convert_to_checkout', [$this, 'convert_to_checkout']);
        add_action('admin_post_nopriv_btr_convert_to_checkout', [$this, 'convert_to_checkout']);
        add_action('admin_post_btr_goto_anagrafica_compile', [$this, 'goto_anagrafica_compile']);
        add_action('admin_post_nopriv_btr_goto_anagrafica_compile', [$this, 'goto_anagrafica_compile']);
        add_filter('woocommerce_checkout_fields', [$this, 'populate_checkout_fields']);
        add_action('woocommerce_checkout_create_order', [$this, 'set_customer_details_on_order'], 10, 2);
        
        // NUOVO: Redirect dal carrello alla pagina anagrafici se c'è un preventivo in sessione
        add_action('template_redirect', [$this, 'redirect_cart_to_anagrafici'], 99);
        add_action('wp', [$this, 'redirect_cart_to_anagrafici'], 99);
        // Hook specifico di WooCommerce per il carrello
        add_action('woocommerce_before_cart', [$this, 'redirect_cart_to_anagrafici_wc'], 1);
        // JavaScript redirect come fallback
        add_action('wp_footer', [$this, 'inject_cart_redirect_js'], 999);
        // Hook aggiuntivo per carrello con rilevamento alternativo
        add_action('wp_head', [$this, 'early_cart_detection_redirect'], 1);
        
        // Rimuove l'azione su thankyou (se definita in un altro punto)
        remove_action('woocommerce_thankyou', [$this, 'update_preventivo_status']);

        // Aggiunge l'azione su "checkout_order_processed"
        add_action('woocommerce_checkout_order_processed', [$this, 'update_preventivo_status'], 20, 1);
        add_action('woocommerce_checkout_order_processed', [$this, 'clear_btr_fees_from_session'], 25, 1);
        // NUOVO APPROCCIO 2025-01-20: Modifica prezzi per riflettere custom summary
        add_action('woocommerce_before_calculate_totals', [$this, 'sync_cart_with_custom_summary'], 5, 1);
        add_action('woocommerce_before_calculate_totals', [$this, 'adjust_cart_item_price'], 20, 1);
        
        // Filtri per aggiungere dettagli nel carrello e nell'ordine
        add_filter('woocommerce_cart_item_name', [$this, 'add_custom_details_to_cart_item_name'], 10, 3);
        add_action('woocommerce_order_item_meta_end', [$this, 'display_custom_details_in_order'], 10, 3);
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_order_item_meta'], 10, 4);
        //add_action('woocommerce_checkout_update_order_meta', [$this, 'save_order_meta']);
        add_action('woocommerce_checkout_create_order', [$this, 'save_preventivo_id_to_order'], 10, 2);
        
        // Support Blocks/React checkout: save preventivo meta when order is processed via Store API
        add_action('woocommerce_store_api_checkout_order_processed', [$this, 'save_preventivo_id_to_order'], 20, 1);
        
        // Include preventivo_id as hidden input in checkout form
        add_action('woocommerce_checkout_after_customer_details', [$this, 'render_hidden_preventivo_field']);
        add_filter('woocommerce_get_item_data', [$this, 'show_assicurazione_in_cart_summary_or_extra'], 10, 2);
        
        // Register hidden preventivo_id for Blocks/React checkout - FIX DEFINITIVO WC 8.6+
        add_action('woocommerce_blocks_loaded', [$this, 'register_preventivo_checkout_field'], 10);
        
        // Save additional field values (Blocks checkout)
        add_action(
            'woocommerce_set_additional_field_value',
            [$this, 'set_preventivo_additional_field'],
            10,
            4
        );
        
        // For Blocks checkout: inject hidden preventivo_id via JS
        add_action('wp_footer', [$this, 'inject_preventivo_hidden_js']);
        
        // Personalizzazione visualizzazione carrello per prodotti BTR
        add_filter('woocommerce_cart_item_thumbnail', [$this, 'customize_btr_cart_item_thumbnail'], 10, 3);
        add_filter('woocommerce_cart_item_name', [$this, 'customize_btr_cart_item_name'], 20, 3);
        add_filter('woocommerce_cart_item_remove_link', [$this, 'customize_btr_cart_item_remove_link'], 10, 2);
        add_action('woocommerce_after_cart_item_name', [$this, 'add_btr_cart_item_description'], 10, 2);
        
        // Carica CSS per miglioramenti carrello
        add_action('wp_enqueue_scripts', [$this, 'enqueue_cart_styles']);
        
        // Gestione fees persistenti per sconti/riduzioni - Approccio robusto con hook multipli
        add_action('woocommerce_cart_calculate_fees', [$this, 'apply_btr_cart_fees'], 999);
        add_action('woocommerce_before_calculate_totals', [$this, 'ensure_btr_fees_applied'], 5);
        add_action('woocommerce_after_calculate_totals', [$this, 'validate_and_reapply_btr_fees'], 999);
        
        // Hook aggiuntivi per WooCommerce Blocks
        add_action('woocommerce_store_api_cart_update_cart_from_request', [$this, 'handle_store_api_cart_update'], 10, 2);
        add_action('woocommerce_store_api_cart_update_order_from_request', [$this, 'ensure_fees_in_order'], 10, 3);
        
        /*
        add_action('woocommerce_thankyou', function($order_id) {
            WC()->session->__unset('_preventivo_id');
            error_log("Preventivo ID rimosso dalla sessione per l'ordine $order_id.");
        });
        */

        add_action('woocommerce_admin_order_data_after_order_details', function ($order) {
            $preventivo_id = $order->get_meta('_preventivo_id');
            if ($preventivo_id) {
                echo '<p><strong>' . __('Preventivo ID:', 'born-to-ride-booking') . '</strong> ' . esc_html($preventivo_id) . '</p>';
            }
        });

        add_filter(
            'woocommerce_store_api_cart_item_response',
            [ $this, 'filter_store_api_cart_item_response' ],
            10,
            3
        );
        
        // ——————————————————————————————————————————————
        // Checkout Blocks: nascondi l'order‑summary nativo e
        // mostra un riepilogo personalizzato con tutti i costi
        // ——————————————————————————————————————————————
        add_action( 'woocommerce_checkout_after_terms_block', [ $this, 'btr_render_custom_summary_block' ], 15 );
    }

    /**
     * Filters the cart item response for the WooCommerce Store API (used by React/Blocks checkout).
     *
     * This method ensures that all additional costs (extra nights, insurance, extra costs)
     * are properly included in the cart item response for the React checkout.
     * This maintains consistency between the order summary and the checkout process.
     *
     * @param array $response  The cart item response data.
     * @param array $cart_item The cart item data.
     * @param object $request  The request object.
     * @return array Modified cart item response with extra data.
     */
    public function filter_store_api_cart_item_response( $response, $cart_item, $request ) {
        // Get child category labels from preventivo or use defaults
        $preventivo_id = isset($cart_item['preventivo_id']) ? intval($cart_item['preventivo_id']) : 0;
        $child_labels = [];
        
        if ($preventivo_id > 0) {
            $child_labels = get_post_meta($preventivo_id, '_child_category_labels', true);
        }
        
        // Fallback to dynamic categories if not saved in preventivo
        if (empty($child_labels) && class_exists('BTR_Dynamic_Child_Categories')) {
            $child_categories_manager = new BTR_Dynamic_Child_Categories();
            $child_categories = $child_categories_manager->get_categories(true);
            
            foreach ($child_categories as $category) {
                $child_labels[$category['id']] = $category['label'];
            }
        }
        
        // Default fallback labels
        $label_f1 = isset($child_labels['f1']) ? $child_labels['f1'] : __('Bambini (fascia 1)', 'born-to-ride-booking');
        $label_f2 = isset($child_labels['f2']) ? $child_labels['f2'] : __('Bambini (fascia 2)', 'born-to-ride-booking');
        $label_f3 = isset($child_labels['f3']) ? $child_labels['f3'] : __('Bambini (fascia 3)', 'born-to-ride-booking');
        $label_f4 = isset($child_labels['f4']) ? $child_labels['f4'] : __('Bambini (fascia 4)', 'born-to-ride-booking');
        
        // Add extra night data if present
        if ( ! empty( $cart_item['extra_night_flag'] ) && intval( $cart_item['extra_night_flag'] ) === 1 ) {
            $persons = intval( $cart_item['number_of_persons'] ) * intval( $cart_item['quantity'] );
            $tot     = floatval( $cart_item['extra_night_pp'] ) * $persons;
            
            $response['extra_data']['notte_extra'] = [
                'label'      => __( 'Notte extra', 'born-to-ride-booking' ),
                'unit_price' => wc_format_localized_price( $cart_item['extra_night_pp'] ),
                'quantity'   => $persons,
                'total'      => wc_format_localized_price( $tot ),
            ];
        }
        
        // Add room supplement if present
        if ( isset( $cart_item['supplemento'] ) && floatval( $cart_item['supplemento'] ) > 0 ) {
            $supplemento = floatval( $cart_item['supplemento'] );
            $quantity = intval( $cart_item['quantity'] );
            $tot_supplemento = $supplemento * $quantity;
            
            $response['extra_data']['supplemento'] = [
                'label'      => __( 'Supplemento camera', 'born-to-ride-booking' ),
                'unit_price' => wc_format_localized_price( $supplemento ),
                'quantity'   => $quantity,
                'total'      => wc_format_localized_price( $tot_supplemento ),
            ];
        }
        
        // Add discount information if present
        if ( isset( $cart_item['sconto_percent'] ) && floatval( $cart_item['sconto_percent'] ) > 0 ) {
            $sconto_percent = floatval( $cart_item['sconto_percent'] );
            
            $response['extra_data']['sconto'] = [
                'label'      => __( 'Sconto', 'born-to-ride-booking' ),
                'percentage' => $sconto_percent . '%',
            ];
        }
        
        // Add child pricing information if present - using dynamic labels
        if ( isset( $cart_item['assigned_child_f1'] ) && intval( $cart_item['assigned_child_f1'] ) > 0 && isset( $cart_item['price_child_f1'] ) ) {
            $child_f1_count = intval( $cart_item['assigned_child_f1'] );
            $price_child_f1 = floatval( $cart_item['price_child_f1'] );
            $tot_child_f1 = $price_child_f1 * $child_f1_count;
            
            $response['extra_data']['bambini_f1'] = [
                'label'      => $label_f1,
                'unit_price' => wc_format_localized_price( $price_child_f1 ),
                'quantity'   => $child_f1_count,
                'total'      => wc_format_localized_price( $tot_child_f1 ),
            ];
        }
        
        if ( isset( $cart_item['assigned_child_f2'] ) && intval( $cart_item['assigned_child_f2'] ) > 0 && isset( $cart_item['price_child_f2'] ) ) {
            $child_f2_count = intval( $cart_item['assigned_child_f2'] );
            $price_child_f2 = floatval( $cart_item['price_child_f2'] );
            $tot_child_f2 = $price_child_f2 * $child_f2_count;
            
            $response['extra_data']['bambini_f2'] = [
                'label'      => $label_f2,
                'unit_price' => wc_format_localized_price( $price_child_f2 ),
                'quantity'   => $child_f2_count,
                'total'      => wc_format_localized_price( $tot_child_f2 ),
            ];
        }
        
        if ( isset( $cart_item['assigned_child_f3'] ) && intval( $cart_item['assigned_child_f3'] ) > 0 && isset( $cart_item['price_child_f3'] ) ) {
            $child_f3_count = intval( $cart_item['assigned_child_f3'] );
            $price_child_f3 = floatval( $cart_item['price_child_f3'] );
            $tot_child_f3 = $price_child_f3 * $child_f3_count;
            
            $response['extra_data']['bambini_f3'] = [
                'label'      => $label_f3,
                'unit_price' => wc_format_localized_price( $price_child_f3 ),
                'quantity'   => $child_f3_count,
                'total'      => wc_format_localized_price( $tot_child_f3 ),
            ];
        }
        
        if ( isset( $cart_item['assigned_child_f4'] ) && intval( $cart_item['assigned_child_f4'] ) > 0 && isset( $cart_item['price_child_f4'] ) ) {
            $child_f4_count = intval( $cart_item['assigned_child_f4'] );
            $price_child_f4 = floatval( $cart_item['price_child_f4'] );
            $tot_child_f4 = $price_child_f4 * $child_f4_count;
            
            $response['extra_data']['bambini_f4'] = [
                'label'      => $label_f4,
                'unit_price' => wc_format_localized_price( $price_child_f4 ),
                'quantity'   => $child_f4_count,
                'total'      => wc_format_localized_price( $tot_child_f4 ),
            ];
        }
        
        // Add insurance data if present
        if ( isset( $cart_item['from_anagrafica'], $cart_item['label_assicurazione'] ) ) {
            $price = isset( $cart_item['custom_price'] ) ? floatval( $cart_item['custom_price'] ) : 0;
            $quantity = intval( $cart_item['quantity'] );
            $total = $price * $quantity;
            
            $response['extra_data']['assicurazione'] = [
                'label'      => __( 'Assicurazione', 'born-to-ride-booking' ),
                'description' => $cart_item['label_assicurazione'],
                'unit_price' => wc_format_localized_price( $price ),
                'quantity'   => $quantity,
                'total'      => wc_format_localized_price( $total ),
            ];
        }
        
        // Add extra costs data if present
        if ( isset( $cart_item['from_extra'], $cart_item['label_extra'] ) ) {
            $price = isset( $cart_item['custom_price'] ) ? floatval( $cart_item['custom_price'] ) : 0;
            $quantity = intval( $cart_item['quantity'] );
            $total = $price * $quantity;
            
            $response['extra_data']['costo_extra'] = [
                'label'      => __( 'Costo Extra', 'born-to-ride-booking' ),
                'description' => $cart_item['label_extra'],
                'unit_price' => wc_format_localized_price( $price ),
                'quantity'   => $quantity,
                'total'      => wc_format_localized_price( $total ),
            ];
        }
        
        /*
         * -----------------------------------------------------------------
         *  Rinomina gli attributi della variazione con etichette leggibili
         *  così che il React / Blocks checkout mostri "Data" e "Camera"
         *  (o il label dell'attributo) al posto dello slug grezzo.
         * -----------------------------------------------------------------
         */
        if ( isset( $response['variation']['attributes'] ) && is_array( $response['variation']['attributes'] ) ) {
            $pretty_attrs = [];
            foreach ( $response['variation']['attributes'] as $slug => $val ) {
                switch ( $slug ) {
                    case 'pa_date_disponibili':
                        $pretty_attrs[ __( 'Data', 'born-to-ride-booking' ) ] = $val;
                        break;
                    case 'pa_tipologia_camere':
                        $pretty_attrs[ __( 'Camera', 'born-to-ride-booking' ) ] = $val;
                        break;
                    default:
                        // Fallback: usa l'etichetta definita in WooCommerce
                        $pretty_attrs[ wc_attribute_label( $slug ) ] = $val;
                        break;
                }
            }
            // Sostituisce gli attributi originali con quelli leggibili
            $response['variation']['attributes'] = $pretty_attrs;
        }
        
        /*
         * ---------------------------------------------------------
         *  Enrich the item "name" so that it already contains the
         *  most important optional costs (Blocks/React checkout
         *  shows only the raw name, therefore we append the extras
         *  directly here).
         * ---------------------------------------------------------
         */
        $extra_parts = [];
        
        // ‑‑ Supplemento camera
        if ( isset( $cart_item['supplemento'] ) && floatval( $cart_item['supplemento'] ) > 0 ) {
            $extra_parts[] = sprintf(
                /* translators: %s is a price like €10,00 */
                __( 'Supplemento: %s', 'born-to-ride-booking' ),
                wc_format_localized_price( floatval( $cart_item['supplemento'] ) )
            );
        }
        
        // ‑‑ Notte extra
        if (
            isset( $cart_item['extra_night_flag'], $cart_item['extra_night_pp'], $cart_item['number_of_persons'], $cart_item['quantity'] )
            && intval( $cart_item['extra_night_flag'] ) === 1
            && floatval( $cart_item['extra_night_pp'] ) > 0
        ) {
            $persons  = intval( $cart_item['number_of_persons'] ) * intval( $cart_item['quantity'] );
            $unit     = floatval( $cart_item['extra_night_pp'] );
            $totale   = $unit * $persons;
            
            $extra_parts[] = sprintf(
                /* translators: 1: price per person, 2: persons, 3: total */
                __( 'Notte extra: %1$s × %2$d = %3$s', 'born-to-ride-booking' ),
                wc_format_localized_price( $unit ),
                $persons,
                wc_format_localized_price( $totale )
            );
        }
        
        // Assemble and append to the name (only for Blocks – classic checkout already handles this elsewhere).
        if ( ! empty( $extra_parts ) ) {
            // Use a simple separator – Blocks renders the string as raw HTML
            $response['name'] .= ' – ' . implode( ' – ', $extra_parts );
        }
        
        return $response;
    }

    /**
     * Reindirizza l'utente alla pagina di inserimento anagrafici dopo aver popolato il carrello.
     */
    public function convert_to_checkout() {
        // FIX: Salva gli anagrafici prima di procedere
        if (isset($_POST['anagrafici']) && is_array($_POST['anagrafici'])) {
            $preventivo_id = isset($_POST['preventivo_id']) ? intval($_POST['preventivo_id']) : 0;
            
            if ($preventivo_id) {
                // Sanitizza e salva gli anagrafici
                $anagrafici = array_map(function($persona) {
                    return [
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
                        'rc_skipass' => sanitize_text_field($persona['rc_skipass'] ?? '0'),
                        'ass_annullamento' => sanitize_text_field($persona['ass_annullamento'] ?? '0'),
                        'ass_bagaglio' => sanitize_text_field($persona['ass_bagaglio'] ?? '0'),
                        'fascia' => sanitize_text_field($persona['fascia'] ?? ''),
                    ];
                }, $_POST['anagrafici']);
                
                // Salva nel preventivo
                update_post_meta($preventivo_id, '_anagrafici_preventivo', $anagrafici);
                
                // Log per debug
                error_log('[BTR Payment] Anagrafici salvati per preventivo #' . $preventivo_id . ' prima del checkout');
            }
        }
        // FINE FIX
        
        // NUOVO: Gestione dati pagamento dal multi-step form
        if (isset($_POST['payment_plan_type'])) {
            $payment_type = sanitize_text_field($_POST['payment_plan_type']);
            
            // Salva il tipo di piano selezionato
            WC()->session->set('btr_payment_plan_type', $payment_type);
            
            // Se è pagamento di gruppo, salva i dati dei paganti
            if ($payment_type === 'group' && isset($_POST['payment_group_payers'])) {
                $group_data = [
                    'payers' => array_map('intval', $_POST['payment_group_payers']),
                    'shares' => []
                ];
                
                if (isset($_POST['payment_group_shares'])) {
                    foreach ($_POST['payment_group_shares'] as $index => $shares) {
                        $group_data['shares'][intval($index)] = intval($shares);
                    }
                }
                
                WC()->session->set('btr_payment_group_data', $group_data);
                error_log('[BTR Payment] Dati gruppo salvati: ' . print_r($group_data, true));
            }
            
            // Crea il piano di pagamento se necessario
            if (class_exists('BTR_Payment_Plans')) {
                $payment_plans = new BTR_Payment_Plans();
                
                if ($payment_type === 'deposit') {
                    // Crea piano acconto + saldo
                    $payment_plans->create_deposit_plan($preventivo_id);
                } elseif ($payment_type === 'group') {
                    // I link di gruppo verranno creati dopo il checkout
                    update_post_meta($preventivo_id, '_payment_type', 'group');
                }
            }
        }
        // FINE gestione pagamento
        
        // Verifica il nonce per proteggere la richiesta
        if (
            !isset($_POST['btr_convert_nonce']) ||
            !wp_verify_nonce($_POST['btr_convert_nonce'], self::NONCE_ACTION_CONVERT)
        ) {
            wp_die(__('Nonce non valido.', 'born-to-ride-booking'));
        }

        // $save_anagrafici = new BTR_Anagrafici_Shortcode();
        // $save_anagrafici->save_anagrafici();

        // Recupera e valida l'ID del preventivo
        $preventivo_id = isset($_POST['preventivo_id']) ? intval($_POST['preventivo_id']) : 0;
        if (!$preventivo_id) {
            wp_die(__('Preventivo non valido o mancante.', 'born-to-ride-booking'));
        }

        // Aggiorna lo stato del preventivo in "convertito"
        update_post_meta($preventivo_id, '_stato_preventivo', 'convertito');

        // Inizializza la sessione WooCommerce se necessario
        if (null === WC()->session) {
            if (class_exists('WC_Session_Handler')) {
                WC()->session = new WC_Session_Handler();
                WC()->session->init();
            } else {
                wp_die(__('Errore nella gestione della sessione di WooCommerce.', 'born-to-ride-booking'));
            }
        }

        // Verifica e assegna il preventivo ID dalla sessione
        $session_preventivo_id = WC()->session->get('_preventivo_id');
        if (!$session_preventivo_id) {
            WC()->session->set('_preventivo_id', $preventivo_id);
            error_log("Preventivo ID {$preventivo_id} salvato nella sessione.");
        }

        // Inizializza il carrello WooCommerce
        if (null === WC()->cart) {
            wc_load_cart();
        }
        if (!WC()->cart) {
            wp_die(__('Errore nella gestione del carrello di WooCommerce.', 'born-to-ride-booking'));
        }

        // Pulisce il carrello per evitare conflitti
        WC()->cart->empty_cart();
        error_log("Carrello svuotato.");

        // Recupera i dati del preventivo
        $camere_selezionate = get_post_meta($preventivo_id, '_camere_selezionate', true);
        $cliente_nome = get_post_meta($preventivo_id, '_cliente_nome', true);
        $cliente_email = get_post_meta($preventivo_id, '_cliente_email', true);

        // Valida e deserializza le camere selezionate
        if (!is_array($camere_selezionate)) {
            $camere_selezionate = maybe_unserialize($camere_selezionate);
            if (!is_array($camere_selezionate)) {
                $camere_selezionate = [];
                error_log("Deserializzazione fallita per le camere selezionate.");
            }
        }

        if (empty($camere_selezionate)) {
            wp_die(__('Non ci sono camere selezionate nel preventivo.', 'born-to-ride-booking'));
        }

        // ------------------------------------------------------------------
        //  Aggiorna (o crea) i dati anagrafici del preventivo e ricalcola
        //  i totali di assicurazioni e costi extra realmente selezionati.
        // ------------------------------------------------------------------
        $dati_anagrafici = [];
        if ( isset( $_POST['anagrafici'] ) ) {
            if ( is_string( $_POST['anagrafici'] ) ) {
                $dati_anagrafici = json_decode( stripslashes( $_POST['anagrafici'] ), true );
            } elseif ( is_array( $_POST['anagrafici'] ) ) {
                $dati_anagrafici = $_POST['anagrafici'];
            }
        }

        // Salva sempre sia _anagrafici_preventivo che _anagrafici
        update_post_meta( $preventivo_id, '_anagrafici_preventivo', $dati_anagrafici );
        update_post_meta( $preventivo_id, '_anagrafici',             $dati_anagrafici );
        error_log( "🔁 Anagrafici salvati nel preventivo $preventivo_id: " . print_r( $dati_anagrafici, true ) );

        /* -------- Ricalcolo totali opzionali -------- */
        $totale_assicurazioni = 0;
        $totale_costi_extra   = 0;

        foreach ( $dati_anagrafici as $persona ) {
            // Assicurazioni
            if ( ! empty( $persona['assicurazioni'] ) && is_array( $persona['assicurazioni'] ) ) {
                foreach ( $persona['assicurazioni'] as $slug => $flag ) {
                    if ( intval( $flag ) !== 1 ) {
                        continue;
                    }
                    if ( isset( $persona['assicurazioni_dettagliate'][ $slug ]['importo'] ) ) {
                        $totale_assicurazioni += floatval(
                            $persona['assicurazioni_dettagliate'][ $slug ]['importo']
                        );
                    }
                }
            }

            // Costi extra
            if ( ! empty( $persona['costi_extra'] ) && is_array( $persona['costi_extra'] ) ) {
                foreach ( $persona['costi_extra'] as $slug => $flag ) {
                    if ( intval( $flag ) !== 1 ) {
                        continue;
                    }
                    if ( isset( $persona['costi_extra_dettagliate'][ $slug ]['importo'] ) ) {
                        $totale_costi_extra += floatval(
                            $persona['costi_extra_dettagliate'][ $slug ]['importo']
                        );
                    }
                }
            }
        }

        update_post_meta( $preventivo_id, '_totale_assicurazioni', $totale_assicurazioni );
        update_post_meta( $preventivo_id, '_totale_costi_extra',   $totale_costi_extra );

        // (Opzionale) totale completo del preventivo
        $prezzo_pacchetto = floatval( get_post_meta( $preventivo_id, '_prezzo_totale', true ) );
        if ( $prezzo_pacchetto > 0 ) {
            update_post_meta(
                $preventivo_id,
                '_prezzo_totale_completo',
                $prezzo_pacchetto + $totale_assicurazioni + $totale_costi_extra
            );
        }

        // CORREZIONE 2025-01-20: Usa sistema dettagliato che corrisponde al custom summary
        $this->add_detailed_cart_items($preventivo_id, $dati_anagrafici);

        // NOTA: Assicurazioni e costi extra ora gestiti dalla funzione add_detailed_cart_items()

        if (!empty($anagrafici) && is_array($anagrafici)) {
            $insurance_to_add = [];
            foreach ($anagrafici as $persona) {
                if (!empty($persona['assicurazioni']) && is_array($persona['assicurazioni'])) {
                    foreach ($persona['assicurazioni'] as $slug => $value) {
                        if (intval($value) !== 1) continue;

                        $dettagli = $persona['assicurazioni_dettagliate'][$slug] ?? null;
                        if (!$dettagli || !is_array($dettagli)) continue;

                        error_log("👁 Analisi assicurazione: " . print_r($dettagli, true));

                        $product_id = isset( $dettagli['id'] ) ? intval( $dettagli['id'] ) : 0;

                        // Fallback: se l'ID non è presente, prova a trovarlo per titolo prodotto
                        $descrizione = $dettagli['descrizione'] ?? '';
                        if ( $product_id <= 0 && $descrizione ) {
                            $query = new WP_Query( [
                                'post_type'      => 'product',
                                'title'          => $descrizione,
                                'posts_per_page' => 1,
                                'post_status'    => 'publish',
                            ] );
                            if ( $query->have_posts() ) {
                                $product_id = $query->posts[0]->ID;
                            }
                            wp_reset_postdata();
                        }

                        // Secondo fallback: prova a mappare lo slug con
                        // l'array "btr_assicurazioni_prodotti" (pacchetto o preventivo)
                        if ( $product_id <= 0 && ! empty( $prodotti_assicurazioni ) ) {
                            foreach ( $prodotti_assicurazioni as $prod_ass ) {
                                // Se nel mapping esiste lo slug usalo, altrimenti deriva dallo slug del nome
                                $slug_check = isset( $prod_ass['slug'] )
                                    ? $prod_ass['slug']
                                    : sanitize_title( $prod_ass['nome'] ?? '' );
                                if ( $slug_check === $slug && ! empty( $prod_ass['id'] ) ) {
                                    $product_id = intval( $prod_ass['id'] );
                                    // Se la descrizione è vuota, usa il nome del mapping
                                    if ( empty( $descrizione ) && ! empty( $prod_ass['nome'] ) ) {
                                        $descrizione = $prod_ass['nome'];
                                    }
                                    break;
                                }
                            }
                        }

                        // Terzo fallback: ricerca prodotto per corrispondenza parziale del titolo
                        if ( $product_id <= 0 && $descrizione ) {
                            $query = new WP_Query( [
                                'post_type'      => 'product',
                                'posts_per_page' => 1,
                                'post_status'    => 'publish',
                                's'              => $descrizione,
                                'fields'         => 'ids',
                            ] );
                            if ( $query->have_posts() ) {
                                $product_id = $query->posts[0];
                            }
                            wp_reset_postdata();
                        }

                        $importo = floatval($dettagli['importo'] ?? 0);

                        /*
                         * ------------------------------------------------------------------
                         *  Prezzo dinamico assicurazione:
                         *  se il campo "importo" non è valorizzato, calcola il prezzo
                         *  come percentuale del prezzo pacchetto salvato sul preventivo.
                         *  – La percentuale può arrivare da:
                         *      • $dettagli['percentuale']  (preferito, se presente)
                         *      • meta _btr_percentuale     salvato sul prodotto assicurazione
                         * ------------------------------------------------------------------
                         */
                        if ( $importo <= 0 ) {
                            $percentuale = 0;
                            // 1) Percentuale indicata nei dettagli dell'assicurazione
                            if ( isset( $dettagli['percentuale'] ) ) {
                                $percentuale = floatval( $dettagli['percentuale'] );
                            }
                            // 2) Fallback: percentuale salvata come meta del prodotto
                            if ( $percentuale <= 0 && $product_id > 0 ) {
                                $percentuale = floatval( get_post_meta( $product_id, '_btr_percentuale', true ) );
                            }
                            // Calcola l'importo solo se abbiamo sia percentuale che prezzo pacchetto
                            if ( $percentuale > 0 && isset( $prezzo_pacchetto ) && $prezzo_pacchetto > 0 ) {
                                $importo = round( ( $percentuale / 100 ) * $prezzo_pacchetto, 2 );
                            }
                        }

                        error_log("➡️ Tentativo di aggiunta assicurazione con ID: $product_id, descrizione: $descrizione, importo: $importo");

                        if ($product_id <= 0 || $importo <= 0 || empty($descrizione)) {
                            error_log("❌ Skipped assicurazione: dati non validi -> ID: $product_id, descrizione: $descrizione, importo: $importo");
                            continue;
                        }

                        $hash = md5($product_id . $descrizione . $importo);
                        if (!isset($insurance_to_add[$hash])) {
                            $insurance_to_add[$hash] = [
                                'product_id'  => $product_id,
                                'descrizione' => $descrizione,
                                'importo'     => $importo,
                                'quantity'    => 1
                            ];
                        } else {
                            $insurance_to_add[$hash]['quantity'] += 1;
                        }
                    }
                }
            }

            foreach ($insurance_to_add as $hash => $insurance) {
                $added = WC()->cart->add_to_cart($insurance['product_id'], $insurance['quantity'], 0, [], [
                    'label_assicurazione' => sanitize_text_field($insurance['descrizione']),
                    'custom_price'        => $insurance['importo'],
                    'custom_name'         => 'Assicurazione: ' . $insurance['descrizione'],
                    'from_anagrafica'     => true,
                    'preventivo_id'       => $preventivo_id,
                ]);
                
                if ($added) {
                    error_log("✅ Assicurazione AGGIUNTA AL CARRELLO: " . $insurance['descrizione'] . " (€" . $insurance['importo'] . "), ID: " . $insurance['product_id']);
                } else {
                    error_log("❌ Errore aggiunta assicurazione: " . $insurance['descrizione'] . " (€" . $insurance['importo'] . "), ID: " . $insurance['product_id']);
                }
            }
        }

        // Aggiunta costi extra sincronizzati per partecipante
        $pacchetto_id = get_post_meta($preventivo_id, '_pacchetto_id', true);
        $extra_prodotti = get_post_meta($pacchetto_id, 'btr_extra_costi_prodotti', true);
        if (!is_array($extra_prodotti)) {
            $extra_prodotti = [];
        }

        // Array per tracciare gli extra già aggiunti e evitare duplicazioni
        $extra_to_add = [];
        
        foreach ($anagrafici as $persona) {
            // Prima verifica se ci sono costi extra selezionati
            if (!empty($persona['costi_extra']) && is_array($persona['costi_extra'])) {
                foreach ($persona['costi_extra'] as $slug => $selected) {
                    // Verifica se questo extra è stato selezionato (valore '1')
                    if ($selected !== '1' && intval($selected) !== 1) {
                        continue;
                    }
                    
                    // Ora cerca i dettagli di questo extra selezionato
                    if (!empty($persona['costi_extra_dettagliate'][$slug])) {
                        $extra_details = $persona['costi_extra_dettagliate'][$slug];
                        
                        // Cerca il prodotto corrispondente
                        foreach ($extra_prodotti as $prodotto) {
                            $slug_check = sanitize_title($prodotto['nome']);
                            if ($slug_check !== $slug) {
                                continue;
                            }

                            $id_prodotto = intval($prodotto['id']);
                            if (!$id_prodotto) {
                                error_log("⚠️ Extra senza ID prodotto: " . $prodotto['nome']);
                                continue;
                            }

                            $importo = floatval($prodotto['importo']);
                            $sconto = floatval($prodotto['sconto'] ?? 0);
                            $netto = $sconto > 0 ? round($importo - ($importo * $sconto / 100), 2) : $importo;

                            // Determina se moltiplicare per durata
                            $moltiplica_durata = !empty($prodotto['moltiplica_durata']);
                            $durata_num = 1;
                            if ($moltiplica_durata) {
                                $durata_str = get_post_meta($preventivo_id, '_durata', true);
                                $durata_num = intval(preg_replace('/[^0-9]/', '', $durata_str));
                                $durata_num = max(1, $durata_num);
                            }
                            
                            // Crea una chiave univoca per questo extra
                            $extra_key = $id_prodotto . '_' . ($moltiplica_durata ? 'durata' : 'persona');
                            
                            if (!isset($extra_to_add[$extra_key])) {
                                $extra_to_add[$extra_key] = [
                                    'id' => $id_prodotto,
                                    'nome' => $prodotto['nome'],
                                    'prezzo' => $netto,
                                    'quantita' => 0,
                                    'moltiplica_durata' => $moltiplica_durata,
                                    'durata' => $durata_num,
                                    'persone' => []
                                ];
                            }
                            
                            // Se moltiplica per durata, la quantità è la durata
                            // Altrimenti, incrementa per ogni persona che lo seleziona
                            if ($moltiplica_durata) {
                                $extra_to_add[$extra_key]['quantita'] = $durata_num;
                            } else {
                                $extra_to_add[$extra_key]['quantita']++;
                            }
                            
                            $extra_to_add[$extra_key]['persone'][] = $persona['nome'] ?? 'N/A';
                            
                            error_log("📝 Extra raccolto: " . $prodotto['nome'] . " per persona: " . ($persona['nome'] ?? 'N/A'));
                            break; // Esci dal loop dei prodotti una volta trovato
                        }
                    }
                }
            }
        }
        
        // Ora aggiungi tutti gli extra raccolti al carrello
        foreach ($extra_to_add as $extra) {
            $added = WC()->cart->add_to_cart($extra['id'], $extra['quantita'], 0, [], [
                'label_extra'      => $extra['nome'],
                'custom_price'     => $extra['prezzo'],
                'custom_name'      => 'Extra: ' . $extra['nome'],
                'from_extra'       => true,
                'preventivo_id'    => $preventivo_id,
            ]);
            
            if ($added) {
                $persone_list = implode(', ', $extra['persone']);
                error_log("✅ Extra AGGIUNTO: " . $extra['nome'] . " x" . $extra['quantita'] . " per: " . $persone_list);
            } else {
                error_log("❌ Errore aggiunta extra: " . $extra['nome']);
            }
        }

        // NOTA: Commento questa sezione perché i costi extra vengono già aggiunti sopra
        // basandosi sui dati anagrafici e solo se selezionati dall'utente.
        // Questa sezione potrebbe causare duplicazioni o aggiungere extra non selezionati.
        /*
        // Aggiunta costi extra dal preventivo
        $costi_extra = get_post_meta($preventivo_id, '_btr_costi_extra', true);
        if (!empty($costi_extra) && is_array($costi_extra)) {
            foreach ($costi_extra as $extra) {
                if (!isset($extra['id']) || !isset($extra['importo'])) {
                    continue;
                }

                $product_id = intval($extra['id']);
                $importo = floatval($extra['importo']);
                $descrizione = $extra['label'] ?? __('Costo Extra', 'born-to-ride-booking');
                $moltiplica_persone = !empty($extra['moltiplica_persone']) ? intval($extra['moltiplica_persone']) : 1;

                if ($product_id > 0 && $importo > 0) {
                    $added = WC()->cart->add_to_cart($product_id, $moltiplica_persone, 0, [], [
                        'label_extra'      => sanitize_text_field($descrizione),
                        'custom_price'     => $importo,
                        'custom_name'      => 'Extra: ' . $descrizione,
                        'from_extra'       => true,
                        'preventivo_id'    => $preventivo_id,
                    ]);
                    
                    if ($added) {
                        error_log("✅ Extra AGGIUNTO AL CARRELLO: $descrizione (€$importo), x$moltiplica_persone");
                    } else {
                        error_log("❌ Errore aggiunta extra: $descrizione");
                    }
                }
            }
        }
        */

        // Calcola i totali del carrello
        WC()->cart->calculate_totals();
        WC()->cart->set_session();
        error_log("Totali del carrello calcolati e sessione impostata.");

        // Salva i dati del cliente nella sessione
        WC()->session->set('btr_cliente_nome', sanitize_text_field($cliente_nome));
        WC()->session->set('btr_cliente_email', sanitize_email($cliente_email));

        // Imposta i dati del cliente su WC()->customer
        if ($cliente_nome) {
            WC()->customer->set_billing_first_name($cliente_nome);
        }
        if ($cliente_email) {
            WC()->customer->set_billing_email($cliente_email);
        }
        WC()->customer->save();
        error_log("Dati del cliente aggiornati: Nome - {$cliente_nome}, Email - {$cliente_email}");

        // Log dello stato attuale del carrello
        error_log("Carrello Attuale: " . print_r(WC()->cart->get_cart(), true));

        // Reindirizza alla pagina di checkout con debug e fallback
        $redirect_url = wc_get_checkout_url();
        
        // Debug: verifica URL checkout
        error_log("[BTR DEBUG] URL Checkout WooCommerce: " . $redirect_url);
        
        // Verifica se l'URL è valido, altrimenti usa fallback
        if (empty($redirect_url) || $redirect_url === home_url('/')) {
            // Prova con l'ID della pagina checkout
            $checkout_page_id = wc_get_page_id('checkout');
            error_log("[BTR DEBUG] Checkout page ID: " . $checkout_page_id);
            
            if ($checkout_page_id > 0) {
                $redirect_url = get_permalink($checkout_page_id);
            } else {
                // Fallback finale
                $redirect_url = home_url('/checkout/');
            }
            error_log("[BTR DEBUG] Fallback URL usato: " . $redirect_url);
        }
        
        // Salva anche i dati in un transient come backup
        set_transient('btr_temp_preventivo_' . $preventivo_id, [
            'preventivo_id' => $preventivo_id,
            'cart_contents' => WC()->cart->get_cart(),
        ], 3600); // 1 ora
        
        // Trigger hook per integrazione sistema pagamenti
        do_action('btr_after_anagrafici_saved', $preventivo_id, $anagrafici);
        
        // SEMPRE mostra la selezione pagamento dopo gli anagrafici
        // Modificato per garantire che il flusso sia: Anagrafici -> Selezione Pagamento -> Checkout
        
        // Verifica se preventivo ha già un piano
        $existing_plan = class_exists('BTR_Payment_Plans') ? BTR_Payment_Plans::get_payment_plan($preventivo_id) : null;
        
        if (!$existing_plan) {
            // SEMPRE mostra selezione pagamento dopo anagrafici
            // Il totale viene calcolato dinamicamente dopo la creazione dei prodotti
            
            // Salva flag in sessione per mostrare modal dopo redirect
            WC()->session->set('btr_show_payment_modal', true);
            WC()->session->set('btr_payment_modal_preventivo', $preventivo_id);
            WC()->session->set('btr_payment_modal_options', [
                'bankTransferEnabled' => get_option('btr_enable_bank_transfer_plans', true),
                'bankTransferInfo' => get_option('btr_bank_transfer_info', ''),
                'depositPercentage' => intval(get_option('btr_default_deposit_percentage', 30))
            ]);
            
            // Redirect SEMPRE alla pagina di selezione pagamento
            $payment_selection_page_id = get_option('btr_payment_selection_page_id');
            
            if ($payment_selection_page_id && get_post($payment_selection_page_id)) {
                // Usa la pagina configurata
                $redirect_url = add_query_arg([
                    'preventivo_id' => $preventivo_id
                ], get_permalink($payment_selection_page_id));
                
                error_log('[BTR Payment] Redirect a pagina selezione configurata: ' . $redirect_url);
            } else {
                // Fallback: cerca per slug
                $payment_page = get_page_by_path('selezione-piano-pagamento');
                if ($payment_page) {
                    $redirect_url = add_query_arg([
                        'preventivo_id' => $preventivo_id
                    ], get_permalink($payment_page->ID));
                    
                    error_log('[BTR Payment] Redirect a pagina selezione (fallback slug): ' . $redirect_url);
                } else {
                    // Se non trova la pagina, crea un messaggio di errore
                    error_log('[BTR Payment] ERRORE: Pagina di selezione pagamento non trovata!');
                    
                    // Ultima opzione: usa il checkout con parametro modal
                    $redirect_url = add_query_arg('show_payment_modal', 1, $redirect_url);
                }
            }
        }
        
        wp_safe_redirect($redirect_url);
        exit;
    }

    public function goto_anagrafica_compile()
    {
        // Verifica il nonce
        if (
            !isset($_POST['btr_anagrafica_compile']) ||
            !wp_verify_nonce($_POST['btr_anagrafica_compile'], 'btr_anagrafica_compile_nonce')
        ) {
            wp_die(__('Nonce non valido.', 'born-to-ride-booking'));
        }

        // Recupera e valida l'ID del preventivo
        $preventivo_id = isset($_POST['preventivo_id']) ? intval($_POST['preventivo_id']) : 0;
        if (!$preventivo_id) {
            wp_die(__('Preventivo non valido o mancante.', 'born-to-ride-booking'));
        }

        // Reindirizza alla pagina di inserimento anagrafici
        $redirect_url = add_query_arg('preventivo_id', $preventivo_id, home_url('/inserisci-anagrafici/'));
        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Aggiunge i prodotti al carrello con i dati del preventivo.
     *
     * @param int $preventivo_id ID del preventivo.
     * @param array $camere_selezionate Array delle camere selezionate.
     * @param string $cliente_nome Nome del cliente.
     * @param string $cliente_email Email del cliente.
     */
    public function add_products_to_cart($preventivo_id, $camere_selezionate, $cliente_nome, $cliente_email)
    {
        if (!empty($camere_selezionate) && is_array($camere_selezionate)) {
            foreach ($camere_selezionate as $camera) {
                if (isset($camera['variation_id']) && isset($camera['quantita'])) {
                    $variation_id = intval($camera['variation_id']);
                    // CORREZIONE 2025-01-20: Usa la quantità effettiva di camere dal preventivo
                    // La quantità rappresenta il numero di camere dello stesso tipo
                    $quantity = isset($camera['quantita']) ? intval($camera['quantita']) : 1;

                    // Recupera la variante
                    $variation = wc_get_product($variation_id);
                    if ($variation) {
                        $parent_product_id = $variation->get_parent_id();
                        if ($parent_product_id > 0) {
                            // Recupera dettagli dalla variante
                            $tipo = isset($camera['tipo']) ? $camera['tipo'] : '';
                            $number_of_persons = $this->determine_number_of_persons($tipo);

                            // ---- prezzi già calcolati nel preventivo ----
                            $prezzo_per_persona = isset( $camera['prezzo_per_persona'] ) ? floatval( $camera['prezzo_per_persona'] ) : 0;
                            $price_child_f1     = isset( $camera['price_child_f1']     ) ? floatval( $camera['price_child_f1']     ) : 0;
                            $price_child_f2     = isset( $camera['price_child_f2']     ) ? floatval( $camera['price_child_f2']     ) : 0;
                            $price_child_f3     = isset( $camera['price_child_f3']     ) ? floatval( $camera['price_child_f3']     ) : 0;
                            $price_child_f4     = isset( $camera['price_child_f4']     ) ? floatval( $camera['price_child_f4']     ) : 0;
                            $assigned_child_f1  = isset( $camera['assigned_child_f1']  ) ? intval( $camera['assigned_child_f1']    ) : 0;
                            $assigned_child_f2  = isset( $camera['assigned_child_f2']  ) ? intval( $camera['assigned_child_f2']    ) : 0;
                            $assigned_child_f3  = isset( $camera['assigned_child_f3']  ) ? intval( $camera['assigned_child_f3']    ) : 0;
                            $assigned_child_f4  = isset( $camera['assigned_child_f4']  ) ? intval( $camera['assigned_child_f4']    ) : 0;
                            $sconto_percent     = isset( $camera['sconto']             ) ? floatval( $camera['sconto']             ) : 0;
                            $supplemento        = isset( $camera['supplemento']        ) ? floatval( $camera['supplemento']        ) : 0;

                            // --- notte extra -------------------------------------------------------
                            // CORREZIONE 2025-01-20: Calcolo corretto notti extra per adulti e bambini
                            $extra_night_flag  = intval( get_post_meta( $preventivo_id, '_extra_night',     true ) );
                            $numero_notti_extra = intval( get_post_meta( $preventivo_id, '_numero_notti_extra', true ) );
                            
                            // Prezzi notte extra per persona
                            $extra_night_pp    = floatval( get_post_meta( $preventivo_id, '_extra_night_pp', true ) );
                            
                            // Supplementi notte extra
                            $supplemento_extra_night = floatval( get_post_meta( $preventivo_id, '_supplemento_extra_night', true ) );
                            
                            $extra_night_total = 0;

                            /**
                             * CORREZIONE 2025-01-20: Calcola il prezzo PER CAMERA (non totale preventivo)
                             * Il prezzo della camera include:
                             * - Prezzo base per persona * numero persone
                             * - Supplemento camera
                             * - Notte extra (se applicabile)
                             * 
                             * Assicurazioni e costi extra vengono aggiunti come prodotti separati
                             */
                            
                            // Calcola numero adulti e bambini assegnati a questa camera
                            $assigned_adults = $number_of_persons; // Default: capacità camera
                            $total_children_assigned = $assigned_child_f1 + $assigned_child_f2 + $assigned_child_f3 + $assigned_child_f4;
                            
                            // Se ci sono bambini assegnati, sottrai dal numero adulti
                            if ($total_children_assigned > 0) {
                                $assigned_adults = max(0, $number_of_persons - $total_children_assigned);
                            }
                            
                            // Calcola prezzo base camera
                            $price_per_camera = 0;
                            
                            // Prezzo adulti
                            if ($assigned_adults > 0) {
                                $price_per_camera += $prezzo_per_persona * $assigned_adults;
                            }
                            
                            // Prezzo bambini F1
                            if ($assigned_child_f1 > 0 && $price_child_f1 > 0) {
                                $price_per_camera += $price_child_f1 * $assigned_child_f1;
                            }
                            
                            // Prezzo bambini F2
                            if ($assigned_child_f2 > 0 && $price_child_f2 > 0) {
                                $price_per_camera += $price_child_f2 * $assigned_child_f2;
                            }
                            
                            // Prezzo bambini F3
                            if ($assigned_child_f3 > 0 && $price_child_f3 > 0) {
                                $price_per_camera += $price_child_f3 * $assigned_child_f3;
                            }
                            
                            // Prezzo bambini F4
                            if ($assigned_child_f4 > 0 && $price_child_f4 > 0) {
                                $price_per_camera += $price_child_f4 * $assigned_child_f4;
                            }
                            
                            // Aggiungi supplemento camera (moltiplicato per persone nella camera)
                            $supplemento_totale = 0;
                            if ($supplemento > 0) {
                                // Il supplemento va moltiplicato per il numero di persone
                                $supplemento_totale = $supplemento * ($assigned_adults + $total_children_assigned);
                                $price_per_camera += $supplemento_totale;
                            }
                            
                            // Calcola notti extra se applicabili
                            if ($extra_night_flag === 1 && $numero_notti_extra > 0) {
                                // Notti extra adulti
                                if ($assigned_adults > 0 && $extra_night_pp > 0) {
                                    $extra_night_total += $extra_night_pp * $assigned_adults * $numero_notti_extra;
                                }
                                
                                // Notti extra bambini (usa i prezzi ridotti per fasce)
                                if ($assigned_child_f1 > 0) {
                                    // Prezzo notte extra bambino F1 (basato su proporzione del prezzo base)
                                    $extra_f1_price = ($price_child_f1 > 0 && $prezzo_per_persona > 0) 
                                        ? ($extra_night_pp * ($price_child_f1 / $prezzo_per_persona))
                                        : $extra_night_pp * 0.7; // default 70% del prezzo adulto
                                    $extra_night_total += $extra_f1_price * $assigned_child_f1 * $numero_notti_extra;
                                }
                                
                                // Supplementi notte extra
                                if ($supplemento_extra_night > 0) {
                                    $extra_night_total += $supplemento_extra_night * ($assigned_adults + $total_children_assigned) * $numero_notti_extra;
                                }
                                
                                $price_per_camera += $extra_night_total;
                            }
                            
                            // Applica eventuale sconto
                            if ($sconto_percent > 0) {
                                $price_per_camera = $price_per_camera * (1 - $sconto_percent / 100);
                            }
                            
                            // CORREZIONE 2025-01-20: NON distribuire gli sconti nel prezzo delle camere
                            // Gli sconti (es. No Skipass) verranno gestiti come fee separate da add_detailed_cart_items
                            // Questo evita la doppia applicazione degli sconti
                            /*
                            $totale_sconti_riduzioni = floatval(get_post_meta($preventivo_id, '_totale_sconti_riduzioni', true));
                            $numero_camere_totali = 0;
                            $sconto_per_camera = 0; // Inizializza la variabile
                            
                            // Conta il numero totale di camere per distribuire gli sconti
                            foreach ($camere_selezionate as $cam) {
                                $numero_camere_totali += isset($cam['quantita']) ? intval($cam['quantita']) : 1;
                            }
                            
                            if ($totale_sconti_riduzioni != 0 && $numero_camere_totali > 0) {
                                // Distribuisci gli sconti/riduzioni equamente tra le camere
                                $sconto_per_camera = $totale_sconti_riduzioni / $numero_camere_totali;
                                $price_per_camera += $sconto_per_camera; // Nota: se è negativo, sottrae
                                
                                if (defined('WP_DEBUG') && WP_DEBUG) {
                                    error_log('  SCONTI/RIDUZIONI METADATA: €' . $totale_sconti_riduzioni . ' (dal preventivo)');
                                    error_log('  Numero camere totali: ' . $numero_camere_totali);
                                    error_log('  Sconto distribuito per camera: €' . $sconto_per_camera);
                                    error_log('  RISULTATO: I valori negativi dovrebbero ora essere applicati al carrello');
                                }
                            }
                            */
                            
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                error_log('[BTR DEBUG] Calcolo prezzo camera:');
                                error_log('  Tipo camera: ' . $tipo);
                                error_log('  Adulti: ' . $assigned_adults . ' x €' . $prezzo_per_persona);
                                error_log('  Bambini F1: ' . $assigned_child_f1 . ' x €' . $price_child_f1);
                                error_log('  Bambini F2: ' . $assigned_child_f2 . ' x €' . $price_child_f2);
                                error_log('  Bambini F3: ' . $assigned_child_f3 . ' x €' . $price_child_f3);
                                error_log('  Bambini F4: ' . $assigned_child_f4 . ' x €' . $price_child_f4);
                                error_log('  Supplemento: €' . $supplemento . ' x ' . ($assigned_adults + $total_children_assigned) . ' persone = €' . ($supplemento_totale ?? 0));
                                error_log('  Notte extra totale: €' . $extra_night_total . ' (' . $numero_notti_extra . ' notti)');
                                error_log('  Sconto percentuale: ' . $sconto_percent . '%');
                                error_log('  Sconti/riduzioni applicati: €' . ($sconto_per_camera ?? 0));
                                error_log('  TOTALE CAMERA FINALE: €' . $price_per_camera);
                            }

                            // DEBUG: Log dettagliato prima di add_to_cart
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                error_log('BTR Cart - Tentativo add_to_cart:');
                                error_log('  Parent ID: ' . $parent_product_id);
                                error_log('  Variation ID: ' . $variation_id);
                                error_log('  Quantity: ' . $quantity);
                                error_log('  Price: €' . $price_per_camera);
                                
                                // Verifica prodotto esistente
                                $parent_product = wc_get_product($parent_product_id);
                                $variation_product = wc_get_product($variation_id);
                                error_log('  Parent exists: ' . ($parent_product ? 'YES' : 'NO'));
                                error_log('  Variation exists: ' . ($variation_product ? 'YES' : 'NO'));
                                error_log('  Parent status: ' . ($parent_product ? $parent_product->get_status() : 'N/A'));
                                error_log('  Variation status: ' . ($variation_product ? $variation_product->get_status() : 'N/A'));
                            }
                            
                            // Aggiungi al carrello con metadati personalizzati (include breakdown completo)
                            $added = WC()->cart->add_to_cart($parent_product_id, $quantity, $variation_id, [], [
                                'preventivo_id'      => $preventivo_id,
                                'btr_cliente_nome'   => sanitize_text_field($cliente_nome),
                                'btr_cliente_email'  => sanitize_email($cliente_email),
                                // IMPORTANTE: Passa il prezzo personalizzato calcolato
                                'custom_price'       => $price_per_camera,
                                'totale_camera'      => $price_per_camera, // Backup per compatibilità
                                // prezzi già calcolati nel preventivo
                                'prezzo_per_persona' => $prezzo_per_persona,
                                'price_child_f1'     => $price_child_f1,
                                'price_child_f2'     => $price_child_f2,
                                'price_child_f3'     => $price_child_f3,
                                'price_child_f4'     => $price_child_f4,
                                'assigned_child_f1'  => $assigned_child_f1,
                                'assigned_child_f2'  => $assigned_child_f2,
                                'assigned_child_f3'  => $assigned_child_f3,
                                'assigned_child_f4'  => $assigned_child_f4,
                                'sconto_percent'     => $sconto_percent,
                                'supplemento'        => $supplemento,
                                'extra_night_flag'   => $extra_night_flag,
                                'extra_night_pp'     => $extra_night_pp,
                                'extra_night_total'  => $extra_night_total,
                                'number_of_persons'  => $number_of_persons,
                                'totale_camera'      => $price_per_camera,
                                'tipo'               => strtolower($tipo),
                                // CORREZIONE: Breakdown aggiornato (assicurazioni separate)
                                'preventivo_total_breakdown' => [
                                    'prezzo_base' => $prezzo_base,
                                    'assicurazioni' => $tot_assic,
                                    'extra_costs_net' => $total_extra_costs_net,
                                    'prodotto_principale' => $price_per_camera,
                                    'totale_completo' => $price_per_camera + $tot_assic
                                ],
                            ]);
                            
                            if ($added) {
                                error_log("✅ AGGIUNTO carrello: Prodotto padre $parent_product_id, Variation ID: $variation_id, Quantità: $quantity");
                            } else {
                                error_log("❌ ERRORE aggiunta carrello: Prodotto padre $parent_product_id, Variation ID: $variation_id, Quantità: $quantity");
                            }
                        }
                    }
                }
            }
        } else {
            error_log("Camere selezionate non presenti o non sono array.");
        }
    }

    /**
     * Regola il prezzo degli articoli nel carrello basato sul numero di persone e applica lo sconto percentuale.
     *
     * @param WC_Cart $cart Il carrello di WooCommerce.
     */
    public function adjust_cart_item_price($cart)
    {
        // Controlla se il contesto è amministrativo e non è un'operazione AJAX
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        // Itera su tutti gli articoli nel carrello
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            // CORREZIONE 2025-01-20: Gestione prodotti BTR dettagliati
            if (isset($cart_item['from_btr_detailed']) && isset($cart_item['custom_price'])) {
                $cart_item['data']->set_price(floatval($cart_item['custom_price']));
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[BTR DEBUG] Prezzo BTR dettagliato applicato: ' . $cart_item['custom_name'] . ' = €' . $cart_item['custom_price']);
                }
                continue;
            }

            // CORREZIONE 2025-01-20: Se il prezzo personalizzato è già stato calcolato, usa quello
            if ( isset( $cart_item['custom_price'] ) && floatval($cart_item['custom_price']) > 0 ) {
                $cart_item['data']->set_price( floatval( $cart_item['custom_price'] ) );
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[BTR DEBUG] Prezzo personalizzato applicato: €' . $cart_item['custom_price']);
                }
                continue;
            }
            
            // Se il totale camera è già stato passato dal preventivo, usa quello.
            if ( isset( $cart_item['totale_camera'] ) ) {
                $cart_item['data']->set_price( floatval( $cart_item['totale_camera'] ) );
                continue;
            }

            // Gestione assicurazioni con prezzo personalizzato
            if (isset($cart_item['from_anagrafica']) && !empty($cart_item['custom_price'])) {
                $cart_item['data']->set_price(floatval($cart_item['custom_price']));
                error_log("💡 Prezzo assicurazione forzato a: " . $cart_item['custom_price']);
                continue;
            }

            // Gestione extra con prezzo personalizzato
            if (isset($cart_item['from_extra']) && !empty($cart_item['custom_price'])) {
                $cart_item['data']->set_price(floatval($cart_item['custom_price']));
                error_log("💡 Prezzo extra forzato a: " . $cart_item['custom_price']);
                continue;
            }

            if (isset($cart_item['variation_id'], $cart_item['quantity'])) {
                $variation_id = $cart_item['variation_id'];
                $quantity = intval($cart_item['quantity']);

                // Recupera i dati della variante
                $variation = wc_get_product($variation_id);
                if ($variation) {
                    // Recupera i prezzi regolare e in saldo
                    $regular_price = floatval($variation->get_regular_price());
                    $sale_price = floatval($variation->get_sale_price());

                    // Determina il prezzo base
                    $base_price = ($sale_price > 0) ? $sale_price : $regular_price;

                    // Recupera il supplemento dalla variante
                    $supplemento = floatval(get_post_meta($variation_id, '_btr_supplemento', true));

                    // Recupera il tipo di camera per calcolare il numero di persone
                    $tipo = isset($cart_item['tipo']) ? strtolower($cart_item['tipo']) : '';
                    $number_of_persons = $this->determine_number_of_persons($tipo);

                    // Calcola il prezzo per persona includendo il supplemento
                    $price_per_person = isset($cart_item['prezzo_per_persona']) ? floatval($cart_item['prezzo_per_persona']) : ($base_price + $supplemento);

                    // Applica lo sconto, se presente
                    if (isset($cart_item['sconto_percent']) && $cart_item['sconto_percent'] > 0) {
                        $price_per_person = $price_per_person * (1 - ($cart_item['sconto_percent'] / 100));
                    }

                    // Calcola il totale per una singola camera
                    $price_per_camera = $price_per_person * $number_of_persons;

                    // Imposta il prezzo dell'articolo nel carrello (prezzo per camera)
                    $cart_item['data']->set_price($price_per_camera);

                    // Log per debug
                    error_log("Adjust Cart Item Price - Variation ID: $variation_id, Tipo Camera: $tipo, Prezzo Base: $base_price, Supplemento: $supplemento, Numero di Persone: $number_of_persons, Prezzo Totale Camera: $price_per_camera");
                }
            }
        }
    }

    /**
     * Aggiunge i dettagli personalizzati al nome del prodotto nel carrello e nel checkout.
     *
     * @param string $item_name Nome dell'articolo.
     * @param array $cart_item Dati dell'articolo nel carrello.
     * @param string $cart_item_key Chiave dell'articolo nel carrello.
     * @return string Nome dell'articolo con dettagli aggiuntivi.
     */
    public function add_custom_details_to_cart_item_name($item_name, $cart_item, $cart_item_key)
    {
        if (isset($cart_item['custom_name'])) {
            return esc_html($cart_item['custom_name']);
        }

        if (
            isset($cart_item['variation_id'], $cart_item['prezzo_per_persona'], $cart_item['number_of_persons'])
        ) {
            $variation_id = $cart_item['variation_id'];
            $variation = wc_get_product($variation_id);
            
            if ($variation) {
                $regular_price = floatval($variation->get_regular_price());
                $sale_price = floatval($variation->get_sale_price());
                $base_price = ($sale_price > 0) ? $sale_price : $regular_price;
                $supplemento = floatval(get_post_meta($variation_id, '_btr_supplemento', true));
                
                $tipo = isset($cart_item['tipo']) ? strtolower($cart_item['tipo']) : '';
                $number_of_persons = intval($cart_item['number_of_persons']);
                $quantity = intval($cart_item['quantity']);
                
                // Calcola i prezzi
                $price_per_person = floatval($cart_item['prezzo_per_persona']); // già include supplemento
                $price_per_camera = $price_per_person * $number_of_persons;
                $total_price = $price_per_camera * $quantity;
                
                // Aggiungi dettagli al nome del prodotto
                $item_name .= '<br><small>';
                if ($sale_price > 0 && $sale_price < $regular_price) {
                    $item_name .= '<del>' . __('Prezzo Regolare: €', 'born-to-ride-booking') . number_format($regular_price, 2) . '</del><br>';
                    $item_name .= '<strong>' . __('Prezzo Scontato: €', 'born-to-ride-booking') . number_format($sale_price, 2) . '</strong><br>';
                } else {
                    $item_name .= __('Prezzo: €', 'born-to-ride-booking') . number_format($base_price, 2) . '<br>';
                }
                
                if ( $supplemento > 0 ) {
                    $item_name .= __('Supplemento: €', 'born-to-ride-booking') . number_format( $supplemento, 2 ) . '<br>';
                }
                
                $item_name .= __('Numero di Persone: ', 'born-to-ride-booking') . $number_of_persons . '<br>';
                $item_name .= __('Totale Camera: €', 'born-to-ride-booking') . number_format($price_per_camera, 2) . '<br>';
                $item_name .= __('Totale (', 'born-to-ride-booking') . $quantity . __(' camere): €', 'born-to-ride-booking') . number_format($total_price, 2);
                
                // Notte extra (solo se presente)
                if ( isset( $cart_item['extra_night_flag'] ) && intval( $cart_item['extra_night_flag'] ) === 1 ) {
                    $pp_extra = floatval( $cart_item['extra_night_pp'] );
                    $persone  = intval( $cart_item['number_of_persons'] ) * intval( $cart_item['quantity'] );
                    if ( $pp_extra > 0 && $persone > 0 ) {
                        $tot_extra = $pp_extra * $persone;
                        $item_name .= '<br>' .
                            __( 'Notte extra: €', 'born-to-ride-booking' ) .
                            number_format( $pp_extra, 2 ) .
                            ' × ' . $persone .
                            ' = €' . number_format( $tot_extra, 2 );
                    }
                }
                
                $item_name .= '</small>';
            }
            
            // Nuovo blocco per etichette e valori leggibili degli attributi della variazione
            if ($variation && $variation->get_type() === 'variation') {
                $variation_attributes = $variation->get_attributes();
                foreach ($variation_attributes as $key => $value) {
                    $nice_label = $key;
                    if ($key === 'pa_date_disponibili') {
                        $nice_label = __('Data', 'born-to-ride-booking');
                    } elseif ($key === 'pa_tipologia_camere') {
                        $nice_label = __('Camera', 'born-to-ride-booking');
                    }
                    
                    $term = get_term_by('slug', $value, $key);
                    $display_value = $term && !is_wp_error($term) ? $term->name : $value;
                    
                    $item_name .= '<br><small>' . esc_html($nice_label) . ': ' . esc_html($display_value) . '</small>';
                }
            }
        }
        
        // Assicurazioni: aggiungi etichetta al nome
        if (isset($cart_item['from_anagrafica'], $cart_item['label_assicurazione'])) {
            $item_name .= '<br><small>' . esc_html__('Assicurazione:', 'born-to-ride-booking') . ' ' . esc_html($cart_item['label_assicurazione']) . '</small>';
        }
        
        // Extra: aggiungi etichetta al nome
        if (isset($cart_item['from_extra'], $cart_item['label_extra'])) {
            $item_name .= '<br><small>' . esc_html__('Costo Extra:', 'born-to-ride-booking') . ' ' . esc_html($cart_item['label_extra']) . '</small>';
        }
        
        return $item_name;
    }

    public function show_assicurazione_in_cart_summary_or_extra($item_data, $cart_item)
    {
        // Rinomina attributi di variazione
        foreach ($item_data as &$data) {
            if ($data['key'] === 'pa_date_disponibili') {
                $data['key'] = __('Data', 'born-to-ride-booking');
            } elseif ($data['key'] === 'pa_tipologia_camere') {
                $data['key'] = __('Camera', 'born-to-ride-booking');
            }
        }
        unset($data);

        if (isset($cart_item['from_anagrafica'], $cart_item['label_assicurazione'])) {
            $item_data[] = [
                'key'     => __('Assicurazione', 'born-to-ride-booking'),
                'value'   => esc_html($cart_item['label_assicurazione']),
                'display' => esc_html($cart_item['label_assicurazione']),
            ];
        }

        if (isset($cart_item['from_extra'], $cart_item['label_extra'])) {
            $item_data[] = [
                'key'     => __('Costo Extra', 'born-to-ride-booking'),
                'value'   => esc_html($cart_item['label_extra']),
                'display' => esc_html($cart_item['label_extra']),
            ];
        }

        return $item_data;
    }

    /**
     * Aggiunge i metadati personalizzati agli elementi dell'ordine durante la creazione dell'ordine.
     *
     * @param WC_Order_Item_Product $item Elemento dell'ordine.
     * @param string $cart_item_key Chiave dell'elemento del carrello.
     * @param array $values Dati dell'elemento del carrello.
     * @param WC_Order $order Ordine.
     */
    public function add_order_item_meta($item, $cart_item_key, $values, $order)
    {
        $meta_keys = [
            'camere_selezionate', 'prezzo_per_persona', 'supplemento',
            'number_of_persons', 'tipo', 'label_extra',
            'extra_night_flag', 'extra_night_pp', 'extra_night_total',
            'price_child_f1', 'price_child_f2', 'price_child_f3', 'price_child_f4',
            'assigned_child_f1', 'assigned_child_f2', 'assigned_child_f3', 'assigned_child_f4'
        ];

        foreach ($meta_keys as $key) {
            if (isset($values[$key])) {
                $item->add_meta_data($key, $values[$key], true);
            }
        }

        // Esempio per log di debug (opzionale)
        error_log("Aggiunti metadati all'ordine: " . print_r($values, true));
    }

    /**
     * Mostra i dettagli personalizzati negli elementi dell'ordine.
     *
     * @param int $item_id ID dell'elemento dell'ordine.
     * @param WC_Order_Item $item Oggetto elemento dell'ordine.
     * @param WC_Order $order Oggetto ordine.
     */
    public function display_custom_details_in_order($item_id, $item, $order)
    {
        $prezzo_per_persona = wc_get_order_item_meta($item_id, 'prezzo_per_persona', true);
        $number_of_persons = wc_get_order_item_meta($item_id, 'number_of_persons', true);
        $supplemento = wc_get_order_item_meta($item_id, 'supplemento', true);
        $sconto_percent = wc_get_order_item_meta($item_id, 'sconto_percent', true);
        $tipo = wc_get_order_item_meta($item_id, 'tipo', true);

        if ($prezzo_per_persona && $number_of_persons && $supplemento !== '' && $supplemento !== null) {
            error_log("Ordine ID {$order->get_id()} - Item ID $item_id - Supplemento: €$supplemento");
            
            echo '<br><small>';
            if ($sconto_percent > 0) {
                $regular_price = wc_get_order_item_meta($item_id, 'regular_price', true);
                $sale_price = wc_get_order_item_meta($item_id, 'sale_price', true);
                echo '<del>' . __('Prezzo Regolare: €', 'born-to-ride-booking') . number_format($regular_price, 2) . '</del><br>';
                echo '<strong>' . __('Prezzo Scontato: €', 'born-to-ride-booking') . number_format($sale_price, 2) . '</strong><br>';
            } else {
                echo __('Prezzo per Persona: €', 'born-to-ride-booking') . number_format($prezzo_per_persona, 2) . '<br>';
            }
            
            if ( $supplemento > 0 ) {
                echo __('Supplemento: €', 'born-to-ride-booking') . number_format( $supplemento, 2 ) . '<br>';
            }
            
            echo __('Numero di Persone: x', 'born-to-ride-booking') . intval($number_of_persons) . '<br>';
            
            // Calcola il totale senza aggiungere supplemento di nuovo
            $totale = $prezzo_per_persona * $number_of_persons * intval($item->get_quantity());
            echo __('Totale per Camera: €', 'born-to-ride-booking') . number_format($totale, 2);
            
            // Notte extra
            $extra_flag = wc_get_order_item_meta( $item_id, 'extra_night_flag', true );
            $extra_pp   = wc_get_order_item_meta( $item_id, 'extra_night_pp', true );
            if ( intval( $extra_flag ) === 1 && floatval( $extra_pp ) > 0 ) {
                $persone = intval( $number_of_persons ) * intval( $item->get_quantity() );
                $tot_extra = floatval( $extra_pp ) * $persone;
                echo '<br>' .
                    __( 'Notte extra: €', 'born-to-ride-booking' ) .
                    number_format( $extra_pp, 2 ) .
                    ' × ' . $persone .
                    ' = €' . number_format( $tot_extra, 2 );
            }
            
            echo '</small>';
        }

        // Mostra dettagli dei costi extra nel riepilogo ordine
        $label_extra = wc_get_order_item_meta($item_id, 'label_extra', true);
        if (!empty($label_extra)) {
            echo '<br><small>' . __('Costo Extra: ', 'born-to-ride-booking') . esc_html($label_extra) . '</small>';
            
            // Calcolo e visualizzazione del totale extra
            $quantita = $item->get_quantity();
            $custom_price = $item->get_total() / $quantita;
            $totale_extra = $custom_price * $quantita;
            echo '<br><strong>' . __('Totale Extra: €', 'born-to-ride-booking') . number_format($totale_extra, 2) . '</strong>';
        }
    }

    /**
     * Salva i metadati generali dell'ordine durante il checkout.
     *
     * @param WC_Order $order Ordine.
     */
    public function save_order_meta($order)
    {
        // Recupera l'ID del preventivo dalla sessione o dal carrello
        $preventivo_id = isset($_POST['preventivo_id']) ? intval($_POST['preventivo_id']) : 0;
        if ($preventivo_id) {
            update_post_meta($order->get_id(), '_preventivo_id', $preventivo_id);
            error_log("Salvato preventivo_id $preventivo_id nell'ordine {$order->get_id()}");
        } else {
            $preventivo_id_sessione = WC()->session->get('_preventivo_id');
            update_post_meta($order->get_id(), '_preventivo_id', $preventivo_id_sessione);
            error_log("Salvato session preventivo_id $preventivo_id nell'ordine {$order->get_id()}");
        }

        // Salva altri metadati se necessario
    }

    /**
     * Imposta i dettagli del cliente durante la creazione dell'ordine.
     *
     * @param WC_Order $order Ordine.
     * @param array $data Dati dell'ordine.
     */
    public function set_customer_details_on_order($order, $data)
    {
        // Recupera i dati dalla sessione
        $cliente_nome = WC()->session->get('btr_cliente_nome');
        $cliente_email = WC()->session->get('btr_cliente_email');

        // Log per debug
        error_log("Set Customer Details - Nome: $cliente_nome");
        error_log("Set Customer Details - Email: $cliente_email");

        if ($cliente_nome) {
            $order->set_billing_first_name($cliente_nome);
        }
        if ($cliente_email) {
            $order->set_billing_email($cliente_email);
        }

        // Aggiungi meta dati personalizzati all'ordine
        if ($cliente_nome) {
            $order->update_meta_data('btr_cliente_nome', sanitize_text_field($cliente_nome));
        }
        if ($cliente_email) {
            $order->update_meta_data('btr_cliente_email', sanitize_email($cliente_email));
        }
    }

    /**
     * Aggiorna lo stato del preventivo dopo che l'ordine è stato creato.
     *
     * @param int $order_id ID dell'ordine.
     */
    public function update_preventivo_status($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log("Update Preventivo Status - Ordine non trovato: $order_id");
            return;
        }

        // Trova l'ID del preventivo salvato nell'ordine
        $preventivo_id = get_post_meta($order_id, '_preventivo_id', true);
        if ($preventivo_id) {
            update_post_meta($preventivo_id, '_stato_preventivo', 'ordine');
            error_log("Update Preventivo Status - Preventivo ID $preventivo_id aggiornato a 'ordine'");
        } else {
            error_log("Update Preventivo Status - preventivo_id non trovato nell'ordine $order_id");
        }

        // Pulizia sessione se vuoi
        WC()->session->__unset('btr_cliente_nome');
        WC()->session->__unset('btr_cliente_email');
        error_log("Sessione Pulita dopo l'aggiornamento del preventivo.");
    }

    /**
     * Popola i campi del checkout con i dati dalla sessione.
     *
     * @param array $fields Campi del checkout.
     * @return array Campi del checkout modificati.
     */
    public function populate_checkout_fields($fields)
    {
        if (is_admin() || !is_checkout()) {
            return $fields;
        }

        // Log per debug
        error_log("Entrando in populate_checkout_fields");

        // Recupera i dati dalla sessione
        $cliente_nome = WC()->session->get('btr_cliente_nome');
        $cliente_email = WC()->session->get('btr_cliente_email');
        $preventivo_id = WC()->session->get('_preventivo_id');

        // Log per debug
        error_log("Populate Checkout Fields - Nome dalla sessione: $cliente_nome");
        error_log("Populate Checkout Fields - Email dalla sessione: $cliente_email");
        error_log("Populate Checkout Fields - Preventivo ID dalla sessione: $preventivo_id");

        if ($cliente_nome) {
            $fields['billing']['billing_first_name']['default'] = sanitize_text_field($cliente_nome);
            $fields['billing']['billing_first_name']['value'] = sanitize_text_field($cliente_nome); // Aggiunto
        }

        if ($cliente_email) {
            $fields['billing']['billing_email']['default'] = sanitize_email($cliente_email);
            $fields['billing']['billing_email']['value'] = sanitize_email($cliente_email); // Aggiunto
        }

        // Aggiungi un campo nascosto per preventivo_id
        $fields['billing']['preventivo_id'] = [
            'type' => 'hidden',
            'default' => $preventivo_id,
        ];

        // Log the modified fields
        error_log("Checkout Fields dopo modifica: " . print_r($fields, true));

        return $fields;
    }

    /**
     * Determina il numero di persone in base al tipo di stanza.
     *
     * @param string $tipo Tipo di stanza (es. 'Singola', 'Doppia').
     * @return int Numero di persone.
     */
    private function determine_number_of_persons($tipo)
    {
        switch ( strtolower( $tipo ) ) {
            case 'singola':
                return 1;
            case 'doppia':
            case 'doppia/matrimoniale':
            case 'matrimoniale':
                return 2;
            case 'tripla':
                return 3;
            case 'quadrupla':
                return 4;
            case 'quintupla':
                return 5;
            case 'condivisa':
                // camera condivisa: posti venduti singolarmente
                return 1;
            default:
                return 1;
        }
    }

    /**
     * Salva i dati del preventivo come meta dell'ordine durante il checkout.
     * @param WC_Order $order L'ordine in creazione.
     * @param array    $data  Dati inviati dal checkout (dati di fatturazione, ecc.).
     */
    public function save_preventivo_id_to_order($order, $data = []) {
        // Determine preventivo ID: first from order meta (Blocks checkout), then session
        $preventivo_id = 0;
        if ($order->get_meta('preventivo_id', true)) {
            $preventivo_id = intval($order->get_meta('preventivo_id', true));
        } else {
            $session_id = WC()->session->get('_preventivo_id');
            $preventivo_id = $session_id ? intval($session_id) : 0;
        }

        if ($preventivo_id > 0) {
            // Salva il preventivo_id
            $order->update_meta_data('_preventivo_id', $preventivo_id);

            // Copia tutti i meta del preventivo nell'ordine
            $preventivo_meta = get_post_meta($preventivo_id);
            foreach ($preventivo_meta as $meta_key => $meta_values) {
                // Escludi meta core di WordPress
                if (in_array($meta_key, ['_edit_lock','_edit_last'], true)) {
                    continue;
                }

                foreach ($meta_values as $meta_value) {
                    // Aggiunge ciascun valore meta (deserializzato quando necessario)
                    $order->update_meta_data(
                        $meta_key,
                        maybe_unserialize($meta_value)
                    );
                }
            }

            // Salva l'ordine con i nuovi meta
            $order->save();
        }
    }

    /**
     * Renders a hidden input with the preventivo_id in the checkout form.
     */
    public function render_hidden_preventivo_field() {
        if ( function_exists('WC') && WC()->session ) {
            $pid = WC()->session->get('_preventivo_id');
            if ( $pid ) {
                echo '<input type="hidden" name="preventivo_id" value="' . esc_attr( $pid ) . '" />';
            }
        }
    }

    /**
     * Injects a hidden preventivo_id input into the checkout form via JavaScript
     */
    public function inject_preventivo_hidden_js() {
        if ( is_checkout() && ! is_order_received_page() && function_exists('WC') && WC()->session ) {
            $pid = WC()->session->get('_preventivo_id');
            if ( $pid ) : ?>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var form = document.querySelector('form.woocommerce-checkout');
                if ( form && ! form.querySelector('input[name="preventivo_id"]') ) {
                    var input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'preventivo_id';
                    input.value = '<?php echo esc_js( $pid ); ?>';
                    form.appendChild(input);
                }
            });
            </script>
            <?php endif;
        }
    }

    /**
     * Handle saving of the additional checkout field 'preventivo_id'.
     *
     * @param string   $key       Field key (namespace/name).
     * @param mixed    $value     Field value.
     * @param string   $group     Field group (contact, address, order).
     * @param WC_Data  $wc_object WC_Order or WC_Customer object.
     */
    public function set_preventivo_additional_field( $key, $value, $group, $wc_object ) {
        if ( 'preventivo_id' === $key && $wc_object instanceof WC_Order ) {
            $order = $wc_object;
            // Save the preventivo ID as order meta
            $order->update_meta_data( '_preventivo_id', intval( $value ) );
            $order->save();
        }
    }

    /**
     * Renderizza il riepilogo personalizzato nel checkout Blocks
     */
    public function btr_render_custom_summary_block() {
        // Implementazione se necessaria
    }

    /**
     * CORREZIONE 2025-01-20: Aggiunge prodotti dettagliati al carrello che corrispondono al custom summary
     * 
     * @param int $preventivo_id ID del preventivo
     * @param array $anagrafici_data Dati anagrafici con tutte le selezioni
     */
    public function add_detailed_cart_items($preventivo_id, $anagrafici_data) {
        if (empty($anagrafici_data) || !is_array($anagrafici_data)) {
            error_log('BTR: Dati anagrafici vuoti per preventivo #' . $preventivo_id);
            return;
        }

        // Recupera il riepilogo dettagliato (stessa logica del custom summary)
        $riepilogo_dettagliato = get_post_meta($preventivo_id, '_riepilogo_calcoli_dettagliato', true);
        
        if (empty($riepilogo_dettagliato['partecipanti'])) {
            error_log('BTR: Riepilogo dettagliato non trovato per preventivo #' . $preventivo_id);
            return;
        }

        $partecipanti = $riepilogo_dettagliato['partecipanti'];
        $nome_pacchetto = get_post_meta($preventivo_id, '_nome_pacchetto', true) ?: 'Pacchetto Viaggio';

        // 1. AGGIUNGI PRODOTTI PER CATEGORIA (Adulti, Bambini, etc.)
        foreach ($partecipanti as $categoria => $dati) {
            if (empty($dati['quantita']) || $dati['quantita'] <= 0) {
                continue;
            }

            $nome_categoria = $this->get_categoria_display_name($categoria);
            $quantita = intval($dati['quantita']);

            // Prezzo base
            if (!empty($dati['prezzo_base_unitario']) && $dati['prezzo_base_unitario'] > 0) {
                $prezzo_base_totale = $quantita * floatval($dati['prezzo_base_unitario']);
                $this->add_virtual_cart_item(
                    $nome_categoria . ' - Prezzo Base',
                    $prezzo_base_totale,
                    1,
                    [
                        'categoria' => $categoria,
                        'quantita_persone' => $quantita,
                        'prezzo_unitario' => $dati['prezzo_base_unitario'],
                        'preventivo_id' => $preventivo_id,
                        'type' => 'prezzo_base'
                    ]
                );
            }

            // Supplemento
            if (!empty($dati['supplemento_base_unitario']) && $dati['supplemento_base_unitario'] > 0) {
                $supplemento_totale = $quantita * floatval($dati['supplemento_base_unitario']);
                $this->add_virtual_cart_item(
                    $nome_categoria . ' - Supplemento',
                    $supplemento_totale,
                    1,
                    [
                        'categoria' => $categoria,
                        'quantita_persone' => $quantita,
                        'prezzo_unitario' => $dati['supplemento_base_unitario'],
                        'preventivo_id' => $preventivo_id,
                        'type' => 'supplemento'
                    ]
                );
            }

            // Notte extra
            if (!empty($dati['notte_extra_unitario']) && $dati['notte_extra_unitario'] > 0) {
                $notte_extra_totale = $quantita * floatval($dati['notte_extra_unitario']);
                $this->add_virtual_cart_item(
                    $nome_categoria . ' - Notte Extra',
                    $notte_extra_totale,
                    1,
                    [
                        'categoria' => $categoria,
                        'quantita_persone' => $quantita,
                        'prezzo_unitario' => $dati['notte_extra_unitario'],
                        'preventivo_id' => $preventivo_id,
                        'type' => 'notte_extra'
                    ]
                );
            }

            // Supplemento extra
            if (!empty($dati['supplemento_extra_unitario']) && $dati['supplemento_extra_unitario'] > 0) {
                $supplemento_extra_totale = $quantita * floatval($dati['supplemento_extra_unitario']);
                $this->add_virtual_cart_item(
                    $nome_categoria . ' - Suppl. Extra',
                    $supplemento_extra_totale,
                    1,
                    [
                        'categoria' => $categoria,
                        'quantita_persone' => $quantita,
                        'prezzo_unitario' => $dati['supplemento_extra_unitario'],
                        'preventivo_id' => $preventivo_id,
                        'type' => 'supplemento_extra'
                    ]
                );
            }
        }

        // 2. AGGIUNGI ASSICURAZIONI (solo quelle selezionate)
        foreach ($anagrafici_data as $persona) {
            if (empty($persona['assicurazioni_dettagliate'])) {
                continue;
            }

            foreach ($persona['assicurazioni_dettagliate'] as $slug => $ass) {
                $importo = isset($ass['importo']) ? (float) $ass['importo'] : 0;
                
                // Verifica se la checkbox era selezionata
                $checkbox_selected = !empty($persona['assicurazioni'][$slug]) && $persona['assicurazioni'][$slug] == '1';
                
                if ($importo > 0 && $checkbox_selected) {
                    $nome_persona = trim(($persona['nome'] ?? '') . ' ' . ($persona['cognome'] ?? ''));
                    $descrizione = $ass['descrizione'] ?? 'Assicurazione';
                    
                    $this->add_virtual_cart_item(
                        $nome_persona . ' - ' . $descrizione,
                        $importo,
                        1,
                        [
                            'nome_persona' => $nome_persona,
                            'assicurazione_slug' => $slug,
                            'preventivo_id' => $preventivo_id,
                            'type' => 'assicurazione'
                        ]
                    );
                }
            }
        }

        // 3. AGGIUNGI COSTI EXTRA (usa BTR_Price_Calculator per coerenza)
        if (function_exists('btr_price_calculator')) {
            $price_calculator = btr_price_calculator();
            $costi_extra_durata = get_post_meta($preventivo_id, '_costi_extra_durata', true);
            $extra_costs_result = $price_calculator->calculate_extra_costs($anagrafici_data, $costi_extra_durata);
            
            // Aggiungi costi extra positivi (aggiunte)
            if (!empty($extra_costs_result['aggiunte']) && is_array($extra_costs_result['aggiunte'])) {
                foreach ($extra_costs_result['aggiunte'] as $key => $costo_extra) {
                    if (!empty($costo_extra['totale']) && $costo_extra['totale'] > 0) {
                        $nome_costo = $costo_extra['nome'] ?? 'Costo Extra';
                        $totale = floatval($costo_extra['totale']);
                        $count = intval($costo_extra['count'] ?? 1);
                        
                        // Se ci sono partecipanti specifici, aggiungi dettaglio
                        $dettaglio_nome = $nome_costo;
                        if (!empty($costo_extra['partecipanti']) && is_array($costo_extra['partecipanti'])) {
                            $dettaglio_nome .= ' (' . $count . 'x)';
                        }
                        
                        $this->add_virtual_cart_item(
                            $dettaglio_nome,
                            $totale,
                            1,
                            [
                                'costo_extra_key' => $key,
                                'count' => $count,
                                'importo_unitario' => $costo_extra['importo_unitario'] ?? 0,
                                'partecipanti' => $costo_extra['partecipanti'] ?? [],
                                'preventivo_id' => $preventivo_id,
                                'type' => 'costo_extra'
                            ]
                        );
                        
                        error_log('BTR: Aggiunto costo extra "' . $nome_costo . '" per €' . $totale);
                    }
                }
            }
            
            // Aggiungi riduzioni (valori negativi) come fees
            if (!empty($extra_costs_result['riduzioni']) && is_array($extra_costs_result['riduzioni'])) {
                foreach ($extra_costs_result['riduzioni'] as $key => $riduzione) {
                    if (!empty($riduzione['totale']) && $riduzione['totale'] < 0) {
                        $nome_riduzione = $riduzione['nome'] ?? 'Sconto';
                        $totale = floatval($riduzione['totale']);
                        $count = intval($riduzione['count'] ?? 1);
                        
                        // Aggiungi dettaglio count se più di uno
                        if ($count > 1) {
                            $nome_riduzione .= ' (' . $count . 'x)';
                        }
                        
                        // Salva come fee
                        $this->add_btr_fee_to_session($nome_riduzione, $totale);
                        
                        error_log('BTR: Aggiunta riduzione "' . $nome_riduzione . '" per €' . $totale);
                    }
                }
            }
            
            // Aggiungi costi extra per durata
            if (!empty($extra_costs_result['per_durata']) && is_array($extra_costs_result['per_durata'])) {
                foreach ($extra_costs_result['per_durata'] as $key => $costo_durata) {
                    $importo = floatval($costo_durata['importo'] ?? 0);
                    $nome = $costo_durata['nome'] ?? 'Costo per Durata';
                    
                    // Evita duplicazione: se è No Skipass e già gestito nelle riduzioni, salta
                    if (stripos($nome, 'no skipass') !== false && !empty($extra_costs_result['riduzioni'])) {
                        $gia_gestito = false;
                        foreach ($extra_costs_result['riduzioni'] as $riduzione) {
                            if (stripos($riduzione['nome'] ?? '', 'no skipass') !== false) {
                                $gia_gestito = true;
                                break;
                            }
                        }
                        if ($gia_gestito) {
                            error_log('BTR: Skipping duplicate No Skipass from per_durata');
                            continue;
                        }
                    }
                    
                    if ($importo > 0) {
                        // Costo positivo - aggiungi come prodotto
                        $this->add_virtual_cart_item(
                            $nome,
                            $importo,
                            1,
                            [
                                'costo_durata_key' => $key,
                                'preventivo_id' => $preventivo_id,
                                'type' => 'costo_durata'
                            ]
                        );
                    } elseif ($importo < 0) {
                        // Costo negativo - aggiungi come fee
                        $this->add_btr_fee_to_session($nome, $importo);
                    }
                }
            }
        }

        error_log('BTR: Prodotti dettagliati aggiunti al carrello per preventivo #' . $preventivo_id);
    }

    /**
     * Aggiunge un prodotto virtuale al carrello
     * CAMBIATO A PROTECTED PER PERMETTERE OVERRIDE IN CLASSI FIGLIE
     */
    protected function add_virtual_cart_item($nome, $prezzo, $quantity = 1, $cart_data = []) {
        // Crea un prodotto virtuale al volo
        $product_id = $this->get_or_create_virtual_product($nome, 'btr-booking-item');
        
        if (!$product_id) {
            error_log('BTR: Impossibile creare prodotto virtuale per: ' . $nome);
            return false;
        }

        $cart_data['custom_price'] = $prezzo;
        $cart_data['custom_name'] = $nome;
        $cart_data['from_btr_detailed'] = true;

        $cart_key = WC()->cart->add_to_cart(
            $product_id,
            $quantity,
            0, // variation_id
            [], // variation attributes
            $cart_data
        );

        if ($cart_key) {
            error_log("BTR: Aggiunto al carrello: {$nome} - €{$prezzo}");
        }

        return $cart_key;
    }

    /**
     * Crea o recupera un prodotto virtuale per il carrello
     * 
     * @param string $nome Nome del prodotto
     * @param string $sku_prefix Prefisso per lo SKU
     * @return int|false ID del prodotto o false in caso di errore
     */
    protected function get_or_create_virtual_product($nome, $sku_prefix = 'btr-booking') {
        // Crea uno SKU univoco basato sul nome
        $sku = $sku_prefix . '-' . sanitize_title($nome);
        
        // Verifica se esiste già un prodotto con questo SKU
        $existing_id = wc_get_product_id_by_sku($sku);
        if ($existing_id) {
            return $existing_id;
        }
        
        // Crea un nuovo prodotto virtuale
        $product = new WC_Product_Simple();
        $product->set_name($nome);
        $product->set_sku($sku);
        $product->set_status('publish');
        $product->set_virtual(true);
        $product->set_downloadable(false);
        $product->set_sold_individually(true);
        $product->set_manage_stock(false);
        $product->set_price(0); // Il prezzo sarà gestito dinamicamente
        $product->set_regular_price(0);
        $product->set_catalog_visibility('hidden');
        $product->set_short_description('Prodotto di servizio per prenotazione Born to Ride');
        
        // Salva il prodotto
        $product_id = $product->save();
        
        if ($product_id) {
            // Aggiungi meta per identificare che è un prodotto BTR
            update_post_meta($product_id, '_btr_virtual_product', true);
            update_post_meta($product_id, '_btr_created_date', current_time('mysql'));
            
            error_log("BTR: Creato prodotto virtuale #{$product_id}: {$nome}");
            return $product_id;
        }
        
        error_log("BTR: Errore nella creazione del prodotto virtuale: {$nome}");
        return false;
    }

    /**
     * Ottiene il nome visualizzabile per la categoria
     */
    protected function get_categoria_display_name($categoria) {
        $nomi = [
            'adulti' => 'Adulti',
            'bambini_f1' => 'Bambini 3-6 anni',
            'bambini_f2' => 'Bambini 6-8 anni', 
            'bambini_f3' => 'Bambini 8-10 anni',
            'bambini_f4' => 'Bambini 11-12 anni',
            'neonati' => 'Neonati'
        ];
        
        return $nomi[$categoria] ?? ucfirst($categoria);
    }

    /**
     * Rimuove l'immagine del prodotto per gli item BTR nel carrello
     */
    public function customize_btr_cart_item_thumbnail($product_image, $cart_item, $cart_item_key) {
        // Controlla se è un prodotto BTR
        if (!empty($cart_item['from_btr_detailed'])) {
            return ''; // Rimuove completamente l'immagine
        }
        
        return $product_image;
    }

    /**
     * Personalizza il nome dell'item nel carrello per prodotti BTR
     */
    public function customize_btr_cart_item_name($product_name, $cart_item, $cart_item_key) {
        // Controlla se è un prodotto BTR
        if (!empty($cart_item['from_btr_detailed'])) {
            $custom_name = $cart_item['custom_name'] ?? $product_name;
            
            // Aggiungi icone per migliorare la visualizzazione
            $icon = $this->get_btr_item_icon($custom_name);
            
            return '<span class="btr-cart-item-name">' . $icon . ' ' . esc_html($custom_name) . '</span>';
        }
        
        return $product_name;
    }

    /**
     * Personalizza il link di rimozione per prodotti BTR
     */
    public function customize_btr_cart_item_remove_link($remove_link, $cart_item_key) {
        $cart_item = WC()->cart->get_cart_item($cart_item_key);
        
        // Controlla se è un prodotto BTR
        if (!empty($cart_item['from_btr_detailed'])) {
            // Nasconde il link di rimozione per i prodotti BTR (sono gestiti come pacchetto)
            return '<span class="btr-cart-item-locked" title="Elemento del pacchetto - non rimovibile singolarmente">🔒</span>';
        }
        
        return $remove_link;
    }

    /**
     * Aggiunge una descrizione personalizzata sotto il nome dell'item
     */
    public function add_btr_cart_item_description($cart_item, $cart_item_key) {
        // Controlla se è un prodotto BTR
        if (!empty($cart_item['from_btr_detailed'])) {
            $description = $this->get_btr_item_description($cart_item);
            
            if ($description) {
                echo '<div class="btr-cart-item-description">' . $description . '</div>';
            }
        }
    }

    /**
     * Ottiene l'icona appropriata per il tipo di item BTR
     */
    private function get_btr_item_icon($item_name) {
        $item_name_lower = strtolower($item_name);
        
        if (strpos($item_name_lower, 'prezzo base') !== false) {
            return '🏨'; // Hotel per prezzo base
        } elseif (strpos($item_name_lower, 'supplemento') !== false) {
            return '➕'; // Plus per supplementi
        } elseif (strpos($item_name_lower, 'notte extra') !== false) {
            return '🌙'; // Luna per notti extra
        } elseif (strpos($item_name_lower, 'assicurazione') !== false) {
            return '🛡️'; // Scudo per assicurazioni
        } elseif (strpos($item_name_lower, 'skipass') !== false) {
            return '⛷️'; // Sci per skipass
        } elseif (strpos($item_name_lower, 'adulti') !== false) {
            return '👨'; // Uomo per adulti
        } elseif (strpos($item_name_lower, 'bambini') !== false || strpos($item_name_lower, 'bambino') !== false) {
            return '👶'; // Bambino
        }
        
        return '📦'; // Pacchetto generico
    }

    /**
     * Genera una descrizione dettagliata per l'item BTR
     */
    private function get_btr_item_description($cart_item) {
        $descriptions = [];
        
        // Aggiungi informazioni sulla categoria se disponibili
        if (!empty($cart_item['categoria'])) {
            $categoria_name = $this->get_categoria_display_name($cart_item['categoria']);
            
            if (!empty($cart_item['quantita_persone'])) {
                $descriptions[] = sprintf(
                    'Categoria: %s (%d %s)',
                    $categoria_name,
                    $cart_item['quantita_persone'],
                    $cart_item['quantita_persone'] == 1 ? 'persona' : 'persone'
                );
            }
        }
        
        // Aggiungi prezzo unitario se disponibile
        if (!empty($cart_item['prezzo_unitario'])) {
            $descriptions[] = 'Prezzo unitario: €' . number_format($cart_item['prezzo_unitario'], 2, ',', '.');
        }
        
        // Aggiungi tipo di costo
        if (!empty($cart_item['type'])) {
            $type_labels = [
                'prezzo_base' => 'Costo base del soggiorno',
                'supplemento' => 'Supplemento camera/servizi',
                'notte_extra' => 'Notte aggiuntiva',
                'supplemento_extra' => 'Supplemento notte extra'
            ];
            
            if (isset($type_labels[$cart_item['type']])) {
                $descriptions[] = $type_labels[$cart_item['type']];
            }
        }
        
        if (empty($descriptions)) {
            return '<small class="text-muted">Componente del pacchetto viaggio</small>';
        }
        
        return '<small class="btr-item-details">' . implode(' • ', $descriptions) . '</small>';
    }

    /**
     * Carica gli stili per i miglioramenti del carrello
     */
    public function enqueue_cart_styles() {
        // Carica solo nelle pagine del carrello e checkout
        if (is_cart() || is_checkout()) {
            wp_enqueue_style(
                'btr-cart-improvements',
                BTR_PLUGIN_URL . 'assets/css/btr-cart-improvements.css',
                [],
                BTR_VERSION
            );
            
            // Aggiungi una classe al body per identificare carrelli con prodotti BTR
            add_action('wp_footer', [$this, 'add_cart_body_class']);
        }
    }

    /**
     * Aggiunge una classe CSS al body se il carrello contiene solo prodotti BTR
     */
    public function add_cart_body_class() {
        if (!WC()->cart || WC()->cart->is_empty()) {
            return;
        }

        $has_btr_items = false;
        $has_regular_items = false;

        foreach (WC()->cart->get_cart() as $cart_item) {
            if (!empty($cart_item['from_btr_detailed'])) {
                $has_btr_items = true;
            } else {
                $has_regular_items = true;
            }
        }

        if ($has_btr_items && !$has_regular_items) {
            echo '<script>document.body.classList.add("cart-btr-only");</script>';
        } elseif ($has_btr_items) {
            echo '<script>document.body.classList.add("cart-has-btr");</script>';
        }
    }

    /**
     * Aggiunge una fee BTR alla sessione per applicazione successiva
     */
    protected function add_btr_fee_to_session($name, $amount) {
        $session_fees = WC()->session->get('btr_cart_fees', []);
        
        // Evita duplicati - sovrascrive se già esiste
        $session_fees[$name] = [
            'name' => $name,
            'amount' => floatval($amount),
            'taxable' => false
        ];
        
        WC()->session->set('btr_cart_fees', $session_fees);
        error_log("BTR: Fee salvata in sessione - {$name}: €{$amount}");
    }

    /**
     * Applica le fees BTR salvate in sessione al carrello
     * Hook: woocommerce_cart_calculate_fees
     */
    public function apply_btr_cart_fees() {
        $session_fees = WC()->session->get('btr_cart_fees', []);
        
        if (empty($session_fees)) {
            return;
        }

        // Verifica che il carrello contenga prodotti BTR per evitare di applicare fees a carrelli non BTR
        $has_btr_products = false;
        foreach (WC()->cart->get_cart() as $cart_item) {
            if (!empty($cart_item['from_btr_detailed'])) {
                $has_btr_products = true;
                break;
            }
        }

        if (!$has_btr_products) {
            return;
        }

        // Applica ogni fee salvata
        foreach ($session_fees as $fee_data) {
            WC()->cart->add_fee(
                $fee_data['name'],
                $fee_data['amount'],
                $fee_data['taxable']
            );
            
            error_log("BTR: Fee applicata al carrello - {$fee_data['name']}: €{$fee_data['amount']}");
        }
    }

    /**
     * Pulisce le fees BTR dalla sessione (da chiamare dopo ordine completato)
     */
    public function clear_btr_fees_from_session() {
        WC()->session->__unset('btr_cart_fees');
        error_log("BTR: Fees pulite dalla sessione");
    }

    /**
     * Assicura che le fees BTR siano applicate prima del calcolo totali
     * Hook: woocommerce_before_calculate_totals (priorità 5)
     */
    public function ensure_btr_fees_applied() {
        if (!$this->should_apply_btr_fees()) {
            return;
        }
        
        $session_fees = WC()->session->get('btr_cart_fees', []);
        if (empty($session_fees)) {
            return;
        }
        
        // Rimuovi fees BTR esistenti per evitare duplicati
        $this->remove_existing_btr_fees();
        
        // Applica fees
        foreach ($session_fees as $fee_data) {
            WC()->cart->add_fee(
                $fee_data['name'],
                $fee_data['amount'],
                $fee_data['taxable']
            );
            error_log("BTR: Fee applicata (ensure): {$fee_data['name']} = €{$fee_data['amount']}");
        }
    }

    /**
     * Valida e riapplica le fees BTR dopo il calcolo totali se necessario
     * Hook: woocommerce_after_calculate_totals (priorità 999)
     */
    public function validate_and_reapply_btr_fees() {
        if (!$this->should_apply_btr_fees()) {
            return;
        }
        
        $session_fees = WC()->session->get('btr_cart_fees', []);
        if (empty($session_fees)) {
            return;
        }
        
        // Verifica se le fees sono presenti
        $current_fees = WC()->cart->get_fees();
        $fee_names = array_map(function($fee) { return $fee->name; }, $current_fees);
        
        $missing_fees = [];
        foreach ($session_fees as $fee_data) {
            if (!in_array($fee_data['name'], $fee_names)) {
                $missing_fees[] = $fee_data;
                error_log("BTR: Fee mancante rilevata dopo calcolo: " . $fee_data['name']);
            }
        }
        
        // Se mancano fees, riapplicale SENZA ricalcolare per evitare loop
        if (!empty($missing_fees)) {
            error_log("BTR: Re-applicando " . count($missing_fees) . " fees mancanti dopo calcolo...");
            
            foreach ($missing_fees as $fee_data) {
                WC()->cart->add_fee(
                    $fee_data['name'],
                    $fee_data['amount'],
                    $fee_data['taxable']
                );
                error_log("BTR: Fee ri-applicata: {$fee_data['name']} = €{$fee_data['amount']}");
            }
            
            // Forza aggiornamento totali senza triggare tutti gli hook
            WC()->cart->set_total(WC()->cart->get_subtotal() + WC()->cart->get_fee_total());
        }
    }

    /**
     * Rimuove le fees BTR esistenti dal carrello per evitare duplicati
     */
    private function remove_existing_btr_fees() {
        $session_fees = WC()->session->get('btr_cart_fees', []);
        if (empty($session_fees)) {
            return;
        }
        
        $session_fee_names = array_column($session_fees, 'name');
        $current_fees = WC()->cart->get_fees();
        
        foreach ($current_fees as $fee_id => $fee) {
            if (in_array($fee->name, $session_fee_names)) {
                WC()->cart->fees_api()->remove_fee($fee_id);
                error_log("BTR: Rimossa fee duplicata: " . $fee->name);
            }
        }
    }

    /**
     * Verifica se dobbiamo applicare le fees BTR
     */
    private function should_apply_btr_fees() {
        // Verifica che il carrello esista e non sia vuoto
        if (!WC()->cart || WC()->cart->is_empty()) {
            return false;
        }
        
        // Verifica che il carrello contenga prodotti BTR
        foreach (WC()->cart->get_cart() as $cart_item) {
            if (!empty($cart_item['from_btr_detailed'])) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Gestisce aggiornamento carrello via Store API
     * Hook: woocommerce_store_api_cart_update_cart_from_request
     */
    public function handle_store_api_cart_update($cart, $request) {
        // Applica fees BTR quando il carrello viene aggiornato via Store API
        $this->apply_btr_cart_fees();
        error_log("BTR: Store API cart update - fees applicate");
    }

    /**
     * Redirect dal carrello alla pagina anagrafici se c'è un preventivo in sessione
     * ma non sono stati compilati gli anagrafici
     * Hook: template_redirect
     */
    public function redirect_cart_to_anagrafici() {
        // Controlla se siamo nella pagina del carrello in vari modi
        $is_cart_page = false;
        
        // Metodo 1: Funzione WordPress
        if (function_exists('is_cart') && is_cart()) {
            $is_cart_page = true;
        }
        
        // Metodo 2: Controlla l'ID della pagina
        $cart_page_id = wc_get_page_id('cart');
        if ($cart_page_id > 0 && is_page($cart_page_id)) {
            $is_cart_page = true;
        }
        
        // Metodo 3: Controlla l'URL
        $current_url = $_SERVER['REQUEST_URI'];
        if (strpos($current_url, '/carrello') !== false || strpos($current_url, '/cart') !== false) {
            $is_cart_page = true;
        }
        
        if (!$is_cart_page) {
            return;
        }
        
        error_log('BTR: Siamo nella pagina carrello, verifico redirect...');
        
        // Controlla se c'è una sessione WooCommerce
        if (!WC()->session) {
            error_log('BTR: Sessione WooCommerce non disponibile');
            return;
        }
        
        // Controlla se c'è un preventivo in sessione
        $preventivo_id = WC()->session->get('_preventivo_id');
        if (!$preventivo_id) {
            error_log('BTR: Nessun preventivo in sessione');
            return;
        }
        
        error_log('BTR: Preventivo in sessione: #' . $preventivo_id);
        
        // Controlla se gli anagrafici sono già stati compilati
        $anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
        if (!empty($anagrafici)) {
            // Anagrafici già compilati, lascia procedere al carrello
            error_log('BTR: Anagrafici già compilati, procedo al carrello');
            return;
        }
        
        // Controlla se il carrello contiene prodotti BTR
        $has_btr_products = false;
        foreach (WC()->cart->get_cart() as $cart_item) {
            if (!empty($cart_item['preventivo_id']) || !empty($cart_item['from_btr_detailed'])) {
                $has_btr_products = true;
                break;
            }
        }
        
        // Se ci sono prodotti BTR ma non ci sono anagrafici, redirect
        if ($has_btr_products) {
            $redirect_url = add_query_arg(
                'preventivo_id', 
                $preventivo_id, 
                home_url('/inserisci-anagrafici/')
            );
            
            error_log('BTR: Redirect dal carrello agli anagrafici per preventivo #' . $preventivo_id);
            
            wp_safe_redirect($redirect_url);
            exit;
        }
    }

    /**
     * Redirect dal carrello agli anagrafici usando hook WooCommerce
     * Hook: woocommerce_before_cart
     */
    public function redirect_cart_to_anagrafici_wc() {
        // Log immediato
        error_log('BTR: woocommerce_before_cart hook triggered');
        
        // Controlla se c'è un preventivo in sessione
        $preventivo_id = WC()->session ? WC()->session->get('_preventivo_id') : null;
        
        if (!$preventivo_id) {
            error_log('BTR: Nessun preventivo in sessione (WC hook)');
            return;
        }
        
        error_log('BTR: Preventivo trovato in sessione: #' . $preventivo_id . ' (WC hook)');
        
        // Controlla se gli anagrafici sono già stati compilati
        $anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
        if (!empty($anagrafici)) {
            error_log('BTR: Anagrafici già compilati, procedo (WC hook)');
            return;
        }
        
        // Se arriviamo qui, dobbiamo fare redirect
        $redirect_url = add_query_arg(
            'preventivo_id', 
            $preventivo_id, 
            home_url('/inserisci-anagrafici/')
        );
        
        error_log('BTR: Eseguo redirect agli anagrafici: ' . $redirect_url);
        
        wp_safe_redirect($redirect_url);
        exit;
    }

    /**
     * Assicura che le fees siano presenti nell'ordine (per WooCommerce Blocks)
     * Hook: woocommerce_store_api_cart_update_order_from_request
     */
    public function ensure_fees_in_order($order, $customer, $request = null) {
        if (!$this->should_apply_btr_fees()) {
            return;
        }
        
        $session_fees = WC()->session->get('btr_cart_fees', []);
        if (empty($session_fees)) {
            return;
        }
        
        // Verifica se l'ordine ha già le fees
        $order_fees = $order->get_fees();
        $order_fee_names = array_map(function($fee) { return $fee->get_name(); }, $order_fees);
        
        foreach ($session_fees as $fee_data) {
            if (!in_array($fee_data['name'], $order_fee_names)) {
                // Aggiungi fee all'ordine
                $fee = new WC_Order_Item_Fee();
                $fee->set_name($fee_data['name']);
                $fee->set_amount($fee_data['amount']);
                $fee->set_tax_status('none');
                $fee->set_total($fee_data['amount']);
                
                $order->add_item($fee);
                error_log("BTR: Fee aggiunta all'ordine: {$fee_data['name']} = €{$fee_data['amount']}");
            }
        }
    }

    /**
     * NUOVO APPROCCIO 2025-01-20: Sincronizza carrello con custom summary
     * Modifica prezzi prodotti per riflettere esattamente il calcolo BTR_Price_Calculator
     * Hook: woocommerce_before_calculate_totals (priorità 5)
     */
    public function sync_cart_with_custom_summary($cart_object)
    {
        // Evita esecuzione in admin (eccetto AJAX)
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        // Recupera preventivo_id dalla sessione
        $preventivo_id = WC()->session ? WC()->session->get('_preventivo_id') : null;
        if (!$preventivo_id) {
            return;
        }

        // Calcola totale corretto usando BTR_Price_Calculator
        if (!function_exists('btr_price_calculator')) {
            return;
        }

        $calculator = btr_price_calculator();
        
        // Recupera i dati necessari per il calcolo
        $anagrafici_data = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
        $anagrafici = [];
        if ($anagrafici_data) {
            // Verifica se è già un array o una stringa JSON
            if (is_array($anagrafici_data)) {
                $anagrafici = $anagrafici_data;
            } else {
                $anagrafici = json_decode($anagrafici_data, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $anagrafici = [];
                }
            }
        }
        
        $params = [
            'preventivo_id' => $preventivo_id,
            'anagrafici' => $anagrafici
        ];
        
        $totali = $calculator->calculate_preventivo_total($params);
        $totale_corretto = floatval($totali['totale_finale'] ?? 0);

        if ($totale_corretto <= 0) {
            return;
        }

        // Ottieni articoli carrello
        $cart_items = $cart_object->get_cart();
        $num_items = count($cart_items);

        if ($num_items <= 0) {
            return;
        }

        // Calcola prezzo per articolo per raggiungere il totale corretto
        $prezzo_per_articolo = $totale_corretto / $num_items;

        // Applica prezzo a ogni articolo del carrello
        foreach ($cart_items as $cart_item_key => $cart_item) {
            // Non sovrascrivere articoli BTR dettagliati con prezzi specifici
            if (isset($cart_item['from_btr_detailed']) && isset($cart_item['custom_price'])) {
                continue;
            }

            // Non sovrascrivere articoli con prezzi personalizzati già definiti
            if (isset($cart_item['custom_price']) && floatval($cart_item['custom_price']) > 0) {
                continue;
            }

            // Imposta il prezzo per raggiungere il totale target
            $cart_item['data']->set_price($prezzo_per_articolo);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[BTR SYNC] Prezzo sincronizzato con custom summary: €' . number_format($prezzo_per_articolo, 2) . ' (Totale target: €' . number_format($totale_corretto, 2) . ')');
            }
        }
    }
    
    /**
     * Inject JavaScript per redirect dal carrello
     * Fallback method che funziona sempre
     */
    public function inject_cart_redirect_js() {
        error_log('BTR: inject_cart_redirect_js chiamato');
        
        // Solo sulla pagina del carrello
        if (!is_cart()) {
            error_log('BTR: Non siamo nella pagina carrello (is_cart = false)');
            return;
        }
        
        error_log('BTR: Siamo nella pagina carrello');
        
        // Verifica se c'è un preventivo in sessione
        $preventivo_id = WC()->session ? WC()->session->get('_preventivo_id') : null;
        if (!$preventivo_id) {
            error_log('BTR: Nessun preventivo in sessione per JS redirect');
            return;
        }
        
        error_log('BTR: Preventivo in sessione per JS: #' . $preventivo_id);
        
        // Verifica se anagrafici non compilati
        $anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
        if (!empty($anagrafici)) {
            error_log('BTR: Anagrafici già compilati, no redirect JS');
            return;
        }
        
        error_log('BTR: Anagrafici vuoti, procedo con JS redirect');
        
        // Genera URL di redirect
        $redirect_url = add_query_arg('preventivo_id', $preventivo_id, home_url('/inserisci-anagrafici/'));
        ?>
        <script type="text/javascript">
        console.log('BTR: Script redirect caricato');
        console.log('BTR: Preventivo ID:', <?php echo $preventivo_id; ?>);
        console.log('BTR: Redirect URL:', '<?php echo esc_js($redirect_url); ?>');
        
        // Redirect immediato
        window.location.href = '<?php echo esc_js($redirect_url); ?>';
        </script>
        <?php
    }
    
    /**
     * Early cart detection and redirect
     * Metodo alternativo che rileva il carrello prima
     */
    public function early_cart_detection_redirect() {
        // Rilevamento alternativo basato su URL
        $current_url = $_SERVER['REQUEST_URI'];
        $cart_page_id = wc_get_page_id('cart');
        $cart_slug = get_post_field('post_name', $cart_page_id);
        
        $is_cart = false;
        
        // Check various cart URL patterns
        if (strpos($current_url, '/carrello') !== false || 
            strpos($current_url, '/cart') !== false ||
            ($cart_slug && strpos($current_url, '/' . $cart_slug) !== false)) {
            $is_cart = true;
        }
        
        // Check if we're on the cart page by ID
        global $post;
        if ($post && $post->ID == $cart_page_id) {
            $is_cart = true;
        }
        
        if (!$is_cart) {
            return;
        }
        
        error_log('BTR: Early detection - siamo nella pagina carrello');
        
        // Inizializza sessione se necessaria
        if (!WC()->session || !WC()->session->has_session()) {
            WC()->initialize_session();
        }
        
        // Verifica preventivo
        $preventivo_id = WC()->session ? WC()->session->get('_preventivo_id') : null;
        if (!$preventivo_id) {
            error_log('BTR: Early detection - nessun preventivo');
            return;
        }
        
        // Verifica anagrafici
        $anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
        if (!empty($anagrafici)) {
            return;
        }
        
        // Redirect
        $redirect_url = add_query_arg('preventivo_id', $preventivo_id, home_url('/inserisci-anagrafici/'));
        
        error_log('BTR: Early detection - eseguo redirect a: ' . $redirect_url);
        
        // Output meta refresh come backup
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta http-equiv="refresh" content="0;url=<?php echo esc_url($redirect_url); ?>">
            <script type="text/javascript">
                window.location.href = '<?php echo esc_js($redirect_url); ?>';
            </script>
        </head>
        <body>
            <p>Reindirizzamento in corso...</p>
        </body>
        </html>
        <?php
        exit;
    }
}