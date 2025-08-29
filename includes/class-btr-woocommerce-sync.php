<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Impedisce l'accesso diretto al file
}

class BTR_WooCommerce_Sync
{
    /**
     * Aggiorna le giacenze di allotment dopo l'ordine (Store API checkout React).
     *
     * @param int      $order_id
     * @param WC_Order $maybe_order
     * @param WC_Order $maybe_request
     */
    public function update_allotment_after_order( $order_id, $maybe_order = null, $maybe_request = null ) {
        // Determine the WC_Order object
        if ( $maybe_order instanceof WC_Order ) {
            $order = $maybe_order;
        } elseif ( $maybe_request instanceof WC_Order ) {
            $order = $maybe_request;
        } else {
            $order = wc_get_order( $order_id );
        }
        if ( ! $order ) {
            return;
        }
        // Static guard to prevent double-processing of the same order
        $actual_order_id = $order->get_id();
        static $processed_orders = [];
        if ( isset( $processed_orders[ $actual_order_id ] ) ) {
            return;
        }
        $processed_orders[ $actual_order_id ] = true;
        foreach ( $order->get_items() as $item ) {
            $variation_id = $item->get_variation_id();
            if ( $variation_id && get_post_meta( $variation_id, '_btr_date_name', true ) ) {
                $qty = $item->get_quantity();

                // Aggiorna giacenza scalata della variante (data principale)
                $scaled_var = intval( get_post_meta( $variation_id, '_btr_giacenza_scalata', true ) );
                update_post_meta( $variation_id, '_btr_giacenza_scalata', $scaled_var + $qty );

                // ID del prodotto padre e nome data principale
                $parent_id = wp_get_post_parent_id( $variation_id );
                $date_name = get_post_meta( $variation_id, '_btr_date_name', true );

                // —— Gestione scalatura “notte extra” (giorno precedente) —— //
                $extra_night_flag = $item->get_meta( '_btr_extra_night', true );
                $extra_date_meta  = $item->get_meta( '_btr_extra_date',  true );

                if ( $extra_night_flag && $extra_date_meta ) {
                    // Allotment globale per la data extra
                    $global_scaled_meta_extra = '_btr_giacenza_scalata_globale_' . $extra_date_meta;
                    $scaled_global_extra      = intval( get_post_meta( $parent_id, $global_scaled_meta_extra, true ) );
                    update_post_meta( $parent_id, $global_scaled_meta_extra, $scaled_global_extra + $qty );

                    // Allotment variante per la data extra
                    $scaled_var_extra = intval( get_post_meta( $variation_id, '_btr_giacenza_scalata_' . $extra_date_meta, true ) );
                    update_post_meta( $variation_id, '_btr_giacenza_scalata_' . $extra_date_meta, $scaled_var_extra + $qty );
                }

                // —— Gestione scalatura globale per la data principale —— //
                if ( $date_name ) {
                    $global_scaled_meta = '_btr_giacenza_scalata_globale_' . $date_name;
                    $scaled_global      = intval( get_post_meta( $parent_id, $global_scaled_meta, true ) );
                    update_post_meta( $parent_id, $global_scaled_meta, $scaled_global + $qty );
                }
            }
        }
    }
    private $post_type = 'btr_pacchetti';
    private $meta_key_product_id = '_btr_product_id';
    private $is_syncing = false;

    // Mapping room types to meta keys for quantities and supplements (per_tipologia_camere)
    private $room_type_meta_keys = [
        'Singola' => 'btr_num_singole',
        'Doppia' => 'btr_num_doppie',
        'Tripla' => 'btr_num_triple',
        'Quadrupla' => 'btr_num_quadruple',
        'Quintupla' => 'btr_num_quintuple',
        'Condivisa' => 'btr_num_condivisa',
    ];

    // Mapping room types to meta keys for quantities (per_numero_persone)
    private $room_type_max_meta_keys = [
        'Singola' => 'btr_num_singole_max',
        'Doppia' => 'btr_num_doppie_max',
        'Tripla' => 'btr_num_triple_max',
        'Quadrupla' => 'btr_num_quadruple_max',
        'Quintupla' => 'btr_num_quintuple_max',
        'Condivisa' => 'btr_num_condivisa_max',
    ];

    // Mapping room types to supplement keys for 'per_tipologia_camere'
    private $room_type_supplement_keys = [
        'Singola' => 'btr_supplemento_singole',
        'Doppia' => 'btr_supplemento_doppie',
        'Tripla' => 'btr_supplemento_triple',
        'Quadrupla' => 'btr_supplemento_quadruple',
        'Quintupla' => 'btr_supplemento_quintuple',
        'Condivisa' => 'btr_supplemento_condivisa',
    ];

    // Mapping room types to supplement keys for 'per_numero_persone'
    private $room_type_supplement_max_keys = [
        'Singola' => 'btr_supplemento_singole_max',
        'Doppia' => 'btr_supplemento_doppie_max',
        'Tripla' => 'btr_supplemento_triple_max',
        'Quadrupla' => 'btr_supplemento_quadruple_max',
        'Quintupla' => 'btr_supplemento_quintuple_max',
        'Condivisa' => 'btr_supplemento_condivisa_max',
    ];

    // Mapping room types to exclusion meta keys
    private $room_type_exclude_meta_keys = [
        'Singola' => 'btr_exclude_singole_max',
        'Doppia' => 'btr_exclude_doppie_max',
        'Tripla' => 'btr_exclude_triple_max',
        'Quadrupla' => 'btr_exclude_quadruple_max',
        'Quintupla' => 'btr_exclude_quintuple_max',
        'Condivisa' => 'btr_exclude_condivisa_max',
    ];

    // Mapping room types to discount meta keys for 'per_tipologia_camere'
    private $room_type_discount_meta_keys = [
        'Singola' => 'btr_sconto_singole',
        'Doppia' => 'btr_sconto_doppie',
        'Tripla' => 'btr_sconto_triple',
        'Quadrupla' => 'btr_sconto_quadruple',
        'Quintupla' => 'btr_sconto_quintuple',
        'Condivisa' => 'btr_sconto_condivisa',
    ];

    // Mapping room types to price meta keys for 'per_tipologia_camere'
    private $room_type_price_meta_keys = [
        'Singola' => 'btr_prezzo_singole',
        'Doppia' => 'btr_prezzo_doppie',
        'Tripla' => 'btr_prezzo_triple',
        'Quadrupla' => 'btr_prezzo_quadruple',
        'Quintupla' => 'btr_prezzo_quintuple',
        'Condivisa' => 'btr_prezzo_condivisa',
    ];

    // Mapping room types to price meta keys for 'per_numero_persone'
    private $room_type_price_max_meta_keys = [
        'Singola' => 'btr_prezzo_singole_max',
        'Doppia' => 'btr_prezzo_doppie_max',
        'Tripla' => 'btr_prezzo_triple_max',
        'Quadrupla' => 'btr_prezzo_quadruple_max',
        'Quintupla' => 'btr_prezzo_quintuple_max',
        'Condivisa' => 'btr_prezzo_condivisa_max',
    ];

    // Mapping room types to discount meta keys for 'per_numero_persone'
    private $room_type_discount_max_meta_keys = [
        'Singola' => 'btr_sconto_singole_max',
        'Doppia' => 'btr_sconto_doppie_max',
        'Tripla' => 'btr_sconto_triple_max',
        'Quadrupla' => 'btr_sconto_quadruple_max',
        'Quintupla' => 'btr_sconto_quintuple_max',
        'Condivisa' => 'btr_sconto_condivisa_max',
    ];

    // Capacità (posti letto) per ogni tipologia di camera
    private $room_type_capacity = [
        'Singola'   => 1,
        'Doppia'    => 2,
        'Tripla'    => 3,
        'Quadrupla' => 4,
        'Quintupla' => 5,
        'Condivisa' => 1, // default
    ];

    /**
     * Restituisce il numero di persone occupabili dalla tipologia camera.
     *
     * @param string $room_type
     * @return int
     */
    private function get_room_capacity( $room_type ) {
        return isset( $this->room_type_capacity[ $room_type ] )
            ? intval( $this->room_type_capacity[ $room_type ] )
            : 1;
    }

    /**
     * Restituisce l’ultimo valore disponibile per un meta-field (array di array).
     * Utile quando lo stesso meta viene salvato più volte e get_post_meta()
     * restituisce un array con più versioni.
     *
     * @param array  $meta_values Array restituito da get_post_meta( $post_id ).
     * @param string $key         Chiave meta da estrarre.
     * @return mixed              Ultimo valore (unserialized se necessario) oppure null.
     */
    private function get_latest_meta_value( $meta_values, $key ) {
        if ( empty( $meta_values[ $key ] ) || ! is_array( $meta_values[ $key ] ) ) {
            return null;
        }
        $raw = end( $meta_values[ $key ] );        // prende l’ultimo valore salvato
        return maybe_unserialize( $raw );
    }

    public function __construct()
    {
        // Hook per sincronizzazione
        add_action('btr_sync_with_woocommerce', [$this, 'sync_with_woocommerce'], 10, 2);
        // Hook per eliminazione del prodotto associato
        add_action('delete_post', [$this, 'delete_product_on_package_delete'], 10, 1);
        // Sincronizza automaticamente al salvataggio del pacchetto
        add_action('save_post_' . $this->post_type, [$this, 'trigger_sync_on_save'], 10, 3);
        // Aggiungi supplementi e altri campi come meta dati nelle varianti
        add_action('woocommerce_save_product_variation', [$this, 'save_price_and_supplemento_field_variation'], 10, 2);

        // —— Hook per checkout Store API (nuovo checkout React) —— //
        add_action(
            'woocommerce_store_api_checkout_before_processing',
            [$this, 'validate_allotment_before_checkout'],
            10,
            2
        );
        add_action(
            'woocommerce_store_api_checkout_order_processed',
            [$this, 'update_allotment_after_order'],
            10,
            3
        );
        // Roll‑back in caso di annullamento / rimborso ordine
        add_action(
            'woocommerce_order_status_cancelled',
            [$this, 'rollback_allotment_on_cancel'],
            10,
            2
        );
        add_action(
            'woocommerce_order_status_refunded',
            [$this, 'rollback_allotment_on_cancel'],
            10,
            2
        );
        add_filter(
            'woocommerce_add_cart_item_data',
            [ $this, 'add_extra_night_cart_item_meta' ],
            10,
            3
        );
    }

    /**
     * Trigger di sincronizzazione al salvataggio del pacchetto
     */
    public function trigger_sync_on_save($post_id, $post, $update)
    {
        
        if (wp_is_post_revision($post_id)) {
            return;
        }
        // Recupera tutti i metadati del pacchetto in formato array di array
        // (es. ['chiave' => ['valore']]); questo è il formato atteso dalle funzioni
        // di sincronizzazione che accedono con indice [0].
        $meta_values = get_post_meta($post_id);
        // Esegue la sincronizzazione
        do_action('btr_sync_with_woocommerce', $post_id, $meta_values);
    }

    /**
     * Sincronizza il pacchetto con WooCommerce
     */
    public function sync_with_woocommerce($post_id, $meta_values)
    {
        
        
        if ($this->is_syncing) {
            return; // Evita sincronizzazioni multiple
        }
        $this->is_syncing = true;
        try {
            if (!class_exists('WooCommerce')) {
                throw new Exception('WooCommerce non è attivo.');
            }
            if (get_post_type($post_id) !== $this->post_type) {
                throw new Exception("Il post ID {$post_id} non è del tipo '{$this->post_type}'.");
            }
            $product_id = get_post_meta($post_id, $this->meta_key_product_id, true);
            if ($product_id) {
                $product = wc_get_product($product_id);
                if ($product && $product->is_type('variable')) {
                    $this->update_woocommerce_product($product_id, $post_id, $meta_values);
                } else {
                    delete_post_meta($post_id, $this->meta_key_product_id);
                    $product_id = $this->create_woocommerce_product($post_id, $meta_values);
                }
            } else {
                $product_id = $this->create_woocommerce_product($post_id, $meta_values);
            }
            if ($product_id) {
                update_post_meta($post_id, $this->meta_key_product_id, $product_id);
                // Recupera la tipologia di prenotazione
                $tipologia_prenotazione = isset($meta_values['btr_tipologia_prenotazione'][0]) ? sanitize_text_field($meta_values['btr_tipologia_prenotazione'][0]) : 'per_tipologia_camere';
                $this->generate_variations($product_id, $meta_values, $tipologia_prenotazione);
                
            }
            $this->sync_assicurazioni_for_package($post_id);
            // Sincronizza anche i costi extra per il pacchetto
            $this->sync_costi_extra_for_package($post_id);
        } catch (Exception $e) {
            error_log('[SYNC ERROR] ' . $e->getMessage());
        }
        $this->is_syncing = false;
    }

    /**
     * Crea un nuovo prodotto WooCommerce
     */
    private function create_woocommerce_product($post_id, $meta_values)
    {
        $product = new WC_Product_Variable();
        $destinazione = isset($meta_values['btr_destinazione'][0]) ? sanitize_text_field($meta_values['btr_destinazione'][0]) : get_the_title($post_id);
        $product->set_name($destinazione);
        $this->set_product_data($product, $post_id);
        $this->add_additional_product_meta($product, $post_id); // Aggiungi i metadati extra
        $product_id = $product->save();
        if (!$product_id) {
            error_log("[CREATE] Errore nel salvataggio del prodotto per il pacchetto ID: $post_id");
            return false;
        }
        // Recupera la tipologia di prenotazione
        $tipologia_prenotazione = isset($meta_values['btr_tipologia_prenotazione'][0]) ? sanitize_text_field($meta_values['btr_tipologia_prenotazione'][0]) : 'per_tipologia_camere';
        $this->set_product_attributes($product_id, $meta_values, $tipologia_prenotazione);
        return $product_id;
    }

    /**
     * Aggiorna un prodotto WooCommerce esistente
     */
    private function update_woocommerce_product($product_id, $post_id, $meta_values)
    {
        
        $product = wc_get_product($product_id);
        if ($product && $product->is_type('variable')) {
            $destinazione = isset($meta_values['btr_destinazione'][0]) ? sanitize_text_field($meta_values['btr_destinazione'][0]) : get_the_title($post_id);
            $product->set_name($destinazione);
            $this->set_product_data($product, $post_id);
            $this->add_additional_product_meta($product, $post_id); // Aggiungi i metadati extra
            $product->save();
            // Recupera la tipologia di prenotazione
            $tipologia_prenotazione = isset($meta_values['btr_tipologia_prenotazione'][0]) ? sanitize_text_field($meta_values['btr_tipologia_prenotazione'][0]) : 'per_tipologia_camere';
            $this->set_product_attributes($product_id, $meta_values, $tipologia_prenotazione);
            // Le varianti obsolete verranno rimosse in modo selettivo all’interno di generate_variations()
        }
        
    }

    /**
     * Imposta i dati comuni del prodotto WooCommerce.
     *
     * @param WC_Product $product Oggetto prodotto WooCommerce.
     * @param int $post_id ID del post CPT.
     */
    private function set_product_data($product, $post_id)
    {
        // Recupera i dettagli dal pacchetto
        $prezzo_base = floatval(get_post_meta($post_id, 'btr_prezzo_base', true));
        $tariffa_base_fissa = floatval(get_post_meta($post_id, 'btr_tariffa_base_fissa', true));
        $descrizione = get_post_field('post_content', $post_id);
        $destinazione = sanitize_text_field(get_post_meta($post_id, 'btr_destinazione', true));
        $sconto_percentuale = floatval(get_post_meta($post_id, 'btr_sconto_percentuale', true));
        // Imposta descrizione e short description
        $product->set_description($descrizione);
        $product->set_short_description('Destinazione: ' . $destinazione);
        // Imposta lo stato del prodotto
        $product->set_status('publish');
        // Imposta virtuale e scaricabile
        $product->set_virtual(true);
        $product->set_downloadable(false);
    }

    /**
     * Imposta gli attributi del prodotto WooCommerce
     */
    private function set_product_attributes($product_id, $meta_values, $tipologia_prenotazione)
    {
        $product = wc_get_product($product_id);
        if (!$product) {
            error_log("[ATTRIBUTES] Impossibile recuperare il prodotto ID {$product_id}.");
            return;
        }
        $attributes = [];
        // PATCH: Controllo che $meta_values sia un array
        if (!is_array($meta_values)) {
            error_log('BTR WARN: meta_values non è un array in set_product_attributes');
            $meta_values = [];
        }
        // Attributo "Date Disponibili"
        $date_ranges_meta = $this->get_latest_meta_value( $meta_values, 'btr_date_ranges' );
        $date_ranges_meta = is_array($date_ranges_meta) ? $date_ranges_meta : [];
        $date_ranges = array_filter(array_map(function ($range) {
            if (!is_array($range)) return null;
            $start = isset($range['start']) ? sanitize_text_field($range['start']) : '';
            $end = isset($range['end']) ? sanitize_text_field($range['end']) : '';
            $name = isset($range['name']) ? sanitize_text_field($range['name']) : '';
            //return ($start && $end) ? "{$start} - {$end}" : null;
            return $name ? "$name" : null;
        }, $date_ranges_meta));
        if (!empty($date_ranges)) {
            $unique_date_ranges = array_unique($date_ranges);
            $date_attribute = $this->create_attribute('Date Disponibili', 'date_disponibili', $unique_date_ranges);
            if ($date_attribute) {
                $attributes[] = $date_attribute;
                error_log("[ATTRIBUTES] Attributo 'Date Disponibili' creato con opzioni: " . implode(', ', $unique_date_ranges));
            }
        } else {
            error_log("[ATTRIBUTES] Nessuna data disponibile per 'Date Disponibili'.");
        }
        // Attributo "Tipologia Camere"
        $available_room_types = [];
        if ($tipologia_prenotazione === 'per_tipologia_camere') {
            foreach ($this->room_type_meta_keys as $room_label => $meta_key) {
                $room_quantity = isset($meta_values[$meta_key][0]) ? intval($meta_values[$meta_key][0]) : 0;
                $exclude_key = isset($this->room_type_exclude_meta_keys[$room_label]) ? $this->room_type_exclude_meta_keys[$room_label] : '';
                $is_excluded = $exclude_key && isset($meta_values[$exclude_key][0]) && $meta_values[$exclude_key][0] === 'on';
                if ($room_quantity > 0 && !$is_excluded) {
                    $available_room_types[] = $room_label;
                }
            }
        } elseif ($tipologia_prenotazione === 'per_numero_persone') {
            foreach ($this->room_type_max_meta_keys as $room_label => $meta_key) {
                $exclude_key = isset($this->room_type_exclude_meta_keys[$room_label]) ? $this->room_type_exclude_meta_keys[$room_label] : '';
                $is_excluded = $exclude_key && isset($meta_values[$exclude_key][0]) && $meta_values[$exclude_key][0] === 'on';
                if (!$is_excluded) {
                    $available_room_types[] = $room_label;
                }
            }
        } elseif ($tipologia_prenotazione === 'allotment_camere') {
            $allotment_data = maybe_unserialize($this->get_latest_meta_value($meta_values, 'btr_camere_allotment'));
            if (is_array($allotment_data)) {
                foreach ($allotment_data as $day => $rooms) {
                    foreach ($rooms as $type => $info) {
                        if ($type === 'totale' || !empty($info['esclusa'])) {
                            continue;
                        }
                        $available_room_types[] = ucfirst($type);
                    }
                }
            }
        }
        // Rimuovi duplicati
        $available_room_types = array_unique($available_room_types);
        if (!empty($available_room_types)) {
            $room_attribute = $this->create_attribute('Tipologia Camere', 'tipologia_camere', $available_room_types);
            if ($room_attribute) {
                $attributes[] = $room_attribute;
                error_log("[ATTRIBUTES] Attributo 'Tipologia Camere' creato con opzioni: " . implode(', ', $available_room_types));
            }
        } elseif ($tipologia_prenotazione !== 'allotment_camere') {
            error_log("[ATTRIBUTES] Nessuna tipologia di camera disponibile per 'Tipologia Camere'.");
        }
        if (!empty($attributes)) {
            $product->set_attributes($attributes);
            $product->save();
            error_log("[ATTRIBUTES] Attributi salvati per il prodotto ID {$product_id}: " . print_r($attributes, true));
        } else {
            error_log("[ATTRIBUTES] Nessun attributo salvato per il prodotto ID {$product_id}.");
        }
        // PATCH: Controllo che $attributes sia un array prima del log finale
        if (!is_array($attributes)) {
            error_log('BTR WARN: Attributi non validi (non array) – forzati a vuoto');
            $attributes = [];
        }
        
    }

    /**
     * Crea un attributo WooCommerce
     */
    private function create_attribute($label, $slug, $options)
    {
        if ($slug === 'tipologia_camere') {
            $taxonomy = 'pa_tipologia_camere';
            if (!taxonomy_exists($taxonomy)) {
                register_taxonomy($taxonomy, 'product', [
                    'hierarchical' => false,
                    'label' => 'Tipologia Camere',
                    'query_var' => true,
                    'rewrite' => ['slug' => 'tipologia_camere'],
                ]);
                error_log("[ATTRIBUTES] Taxonomy '{$taxonomy}' registrata per Tipologia Camere.");
            }
        } else {
            $taxonomy = 'pa_' . sanitize_title($slug);
            // Check if taxonomy exists
            if (!taxonomy_exists($taxonomy)) {
                // Register the taxonomy
                register_taxonomy($taxonomy, 'product', [
                    'hierarchical' => false,
                    'label' => $label,
                    'query_var' => true,
                    'rewrite' => ['slug' => sanitize_title($slug)],
                ]);
                error_log("[ATTRIBUTES] Taxonomy '{$taxonomy}' registrata.");
            }
        }
        // Add terms to taxonomy
        foreach ($options as $option) {
            if (!term_exists($option, $taxonomy)) {
                $term = wp_insert_term($option, $taxonomy);
                if (!is_wp_error($term)) {
                    error_log("[ATTRIBUTES] Term '{$option}' aggiunto alla taxonomy '{$taxonomy}'.");
                } else {
                    error_log("[ATTRIBUTES] Errore nell'aggiungere il term '{$option}' alla taxonomy '{$taxonomy}': " . $term->get_error_message());
                }
            }
        }
        // Create attribute object
        $attribute = new WC_Product_Attribute();
        $attribute->set_id(0);
        $attribute->set_name($taxonomy);
        $attribute->set_options($options);
        $attribute->set_position(0);
        $attribute->set_visible(true);
        $attribute->set_variation(true);
        return $attribute;
    }

    /**
     * Genera le varianti del prodotto WooCommerce
     */
    private function generate_variations($product_id, $meta_values, $tipologia_prenotazione)
    {
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_type('variable')) {
            error_log('[VARIATIONS] Il prodotto ID: ' . $product_id . ' non è di tipo variabile.');
            return;
        }
        if ($tipologia_prenotazione === 'per_tipologia_camere') {
            $this->generate_variations_per_tipologia_camere($product_id, $meta_values);
        } elseif ($tipologia_prenotazione === 'per_numero_persone') {
            $this->generate_variations_per_numero_persone($product_id, $meta_values);
        } elseif ($tipologia_prenotazione === 'allotment_camere') {
            $this->generate_variations_per_allotment($product_id, $meta_values);
        }
    }

    /**
     * Genera varianti per la tipologia di prenotazione 'per_allotment'
     */
    private function generate_variations_per_allotment($product_id, $meta_values)
    {
        error_log('[VARIATIONS] Generazione varianti per "allotment_camere"');
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_type('variable')) {
            error_log('[VARIATIONS] Il prodotto ID ' . $product_id . ' non è di tipo variabile.');
            return;
        }

        $this->remove_existing_variations($product_id);

        $date_ranges   = $this->get_latest_meta_value( $meta_values, 'btr_date_ranges' );
        $allotment_data = $this->get_latest_meta_value( $meta_values, 'btr_camere_allotment' );

        // Array per tracciare le varianti create in questa sessione
        $created_variations = [];

        foreach ($date_ranges as $range) {
            if (!is_array($range)) continue;
            $range_name = $range['name'] ?? '';
            if (!$range_name) continue;

            // —— Inizializza/allinea giacenza globale per la data —— //
            $totale_allotment = isset($allotment_data[$range['start']]['totale'])
                ? intval($allotment_data[$range['start']]['totale'])
                : 0;

            // Se l'allotment totale è 0, creiamo comunque UNA variante
            // "placeholder" con stock 0, così la data appare come Sold Out
            if ( $totale_allotment === 0 ) {
                error_log('[VARIATIONS] Allotment totale è 0 per la data ' . $range['start'] . '. Creo variante Sold Out.');

                $variation = new WC_Product_Variation();
                $variation->set_parent_id( $product_id );
                $variation->set_virtual( true );
                $variation->set_attributes( [ 'pa_date_disponibili' => $range_name ] );

                // Stock 0 e out‑of‑stock
                $variation->set_manage_stock( true );
                $variation->set_stock_quantity( 0 );
                $variation->set_stock_status( 'outofstock' );

                $variation_id = $variation->save();

                // Metadati di tracking
                update_post_meta( $variation_id, '_btr_giacenza_origine', 0 );
                update_post_meta( $variation_id, '_btr_giacenza_scalata', 0 );
                update_post_meta( $variation_id, '_btr_supplemento', 0 );

                // Flag chiusura + label personalizzata se presente
                $is_closed     = '1';
                $custom_label  = sanitize_text_field( $range['label'] ?? 'Sold Out' );

                update_post_meta( $variation_id, '_btr_closed',        $is_closed );
                update_post_meta( $variation_id, '_btr_closed_label',  $custom_label );
                update_post_meta( $variation_id, '_btr_date_name',     $range_name );

                error_log("[VARIATIONS] ➤ Variante Sold Out ID {$variation_id} creata per data {$range_name}");

                // Passa alla prossima data range
                continue;
            }

            // Chiave meta basata sul "nome" leggibile della data (es. 9 - 12 Luglio 2025)
            $global_origin_meta  = '_btr_giacenza_origine_globale_'  . $range_name;
            $global_scaled_meta  = '_btr_giacenza_scalata_globale_'  . $range_name;

            // Origine (solo se non presente o se è stato cambiato il totale)
            $origin_saved = get_post_meta($product_id, $global_origin_meta, true);
            if ( $origin_saved === '' || intval($origin_saved) !== $totale_allotment ) {
                update_post_meta($product_id, $global_origin_meta, $totale_allotment);
            }
            // Inizializza lo “scalato” se non esiste
            if ( ! metadata_exists('post', $product_id, $global_scaled_meta) ) {
                update_post_meta($product_id, $global_scaled_meta, 0);
            }

            // Se ci sono tipologie di camere per questa data, crea una variante per ciascuna
            if (!empty($allotment_data[$range['start']])) {
                $found = false;
                foreach ($allotment_data[$range['start']] as $room_type => $info) {
                    // ignore excluded rooms and ensure 'limite' key exists
                    if ($room_type === 'totale' || !isset($info['limite']) || !empty($info['esclusa'])) {
                        continue;
                    }
                    $limit = intval($info['limite']); // 0 means unlimited
                    $found = true;
                    
                    // Controllo anti-duplicazione: verifica se la variante è già stata creata in questa sessione
                    $combination = [
                        'pa_date_disponibili' => $range_name,
                        'pa_tipologia_camere' => ucfirst($room_type)
                    ];
                    $key = maybe_serialize($combination);
                    if (isset($created_variations[$key])) {
                        error_log("[VARIATIONS] Variante già creata in questa sessione per combinazione: {$key}. Saltata.");
                        continue;
                    }
                    
                    $variation = new WC_Product_Variation();
                    $variation->set_parent_id($product_id);
                    $variation->set_virtual(true);
                    $variation->set_attributes($combination);
                     
                     // Traccia la variante come creata in questa sessione
                     $created_variations[$key] = true;
                    if ($limit === 0) {
                        $variation->set_manage_stock(false);
                    } else {
                        $variation->set_manage_stock(true);
                    }
                    $stock_quantity = $limit;
                    $variation_id = $variation->save();

                    // Gestione giacenza origine
                    $giacenza_origine = get_post_meta($variation_id, '_btr_giacenza_origine', true);
                    if ($limit === 0) {
                        // Unlimited: don't set stock quantity, but set origin for tracking as 0
                        update_post_meta($variation_id, '_btr_giacenza_origine', 0);
                        error_log("[GIACENZA] Variante ID {$variation_id} senza limite di stock (illimitato).");
                    } else {
                        if ($giacenza_origine === '' || $giacenza_origine === null) {
                            update_post_meta($variation_id, '_btr_giacenza_origine', $stock_quantity);
                            $giacenza_origine = $stock_quantity;
                            error_log("[GIACENZA] Inizializzata giacenza origine per variante ID {$variation_id} a {$stock_quantity}.");
                        } elseif ($stock_quantity != intval($giacenza_origine)) {
                            $delta = $stock_quantity - intval($giacenza_origine);
                            $current_stock = intval($variation->get_stock_quantity());
                            $new_stock = max(0, $current_stock + $delta);
                            $variation->set_stock_quantity($new_stock);
                            $variation->set_stock_status($new_stock > 0 ? 'instock' : 'outofstock');
                            error_log("[GIACENZA] Origine modificata da {$giacenza_origine} a {$stock_quantity}, variante {$variation_id} aggiornata da {$current_stock} a {$new_stock}");
                            update_post_meta($variation_id, '_btr_giacenza_origine', $stock_quantity);
                        } else {
                            error_log("[GIACENZA] Origine invariata per variante {$variation_id} ({$giacenza_origine})");
                        }
                    }

                    // Dopo questo blocco, assicurati che anche _btr_giacenza_scalata sia inizializzato se non presente
                    $giacenza_scalata = get_post_meta($variation_id, '_btr_giacenza_scalata', true);
                    $giacenza_scalata = is_numeric($giacenza_scalata) ? intval($giacenza_scalata) : 0;
                    update_post_meta($variation_id, '_btr_giacenza_scalata', $giacenza_scalata);
                    if (!metadata_exists('post', $variation_id, '_btr_giacenza_scalata')) {
                        update_post_meta($variation_id, '_btr_giacenza_scalata', 0);
                        error_log("[GIACENZA] Inizializzata giacenza scalata per variante ID {$variation_id} a 0.");
                    }

                    // Imposta il prezzo
                    $prezzo = isset($info['prezzo']) ? floatval($info['prezzo']) : 0;
                    $supplemento = isset($info['supplemento']) ? floatval($info['supplemento']) : 0;
                    $sconto = isset($info['sconto']) ? floatval($info['sconto']) : 0;
                    // Prezzo della CAMERA intera (nessuna divisione per capacità)
                    $regular = $prezzo;
                    if ($sconto > 0) {
                        $sale = $regular - ($regular * $sconto / 100);
                        $variation->set_sale_price($sale);
                    } else {
                        $variation->set_sale_price('');
                    }
                    $variation->set_regular_price($regular);
                    // Imposta stock_quantity e status solo se limitato
                    if ($limit > 0 && $giacenza_origine !== null) {
                        if ($stock_quantity == intval($giacenza_origine)) {
                            $variation->set_stock_quantity($stock_quantity);
                            $variation->set_stock_status($stock_quantity > 0 ? 'instock' : 'outofstock');
                        }
                    }

                    // Salva il supplemento dopo averlo calcolato
                    update_post_meta( $variation_id, '_btr_supplemento', $supplemento );
                    // Salva di nuovo la variante con i nuovi dati
                    $variation->save();

                    update_post_meta($variation_id, '_btr_date_name', $range_name);
                    
                    // Debug: Gestione campo 'closed' per allotment_camere
                    $closed_value = $range['closed'] ?? 'NON_PRESENTE';

                    
                    $is_closed = !empty($range['closed']) ? '1' : '0';
                    $custom_label = sanitize_text_field($range['label'] ?? '');
                    
                    update_post_meta($variation_id, '_btr_closed', $is_closed);
                    update_post_meta($variation_id, '_btr_closed_label', $custom_label);
                    
                    if ($is_closed === '1') {
                        $variation->set_manage_stock(true);
                        $variation->set_stock_quantity(0);
                        $variation->set_stock_status('outofstock');
                        $variation->save();
                    }
                    
                    error_log("[VARIATIONS] Variante 'allotment_camere' creata ID: {$variation_id} - Data: {$range_name} - Camera: " . ucfirst($room_type) . " - Stock: {$stock_quantity}");
                    // break; // una sola tipologia per variante
                }
                if (!$found) {
                    // Nessuna tipologia valida, fallback su variante solo per data
                    $variation = new WC_Product_Variation();
                    $variation->set_parent_id($product_id);
                    $variation->set_virtual(true);
                    $variation->set_attributes(['pa_date_disponibili' => $range_name]);
                    $variation->set_manage_stock(true);
                    $stock_quantity = isset($allotment_data[$range['start']]['totale']) ? intval($allotment_data[$range['start']]['totale']) : 0;
                    $variation_id = $variation->save();
                    $giacenza_origine = get_post_meta($variation_id, '_btr_giacenza_origine', true);
                    if ($giacenza_origine === '' || $giacenza_origine === null) {
                        update_post_meta($variation_id, '_btr_giacenza_origine', $stock_quantity);
                        $giacenza_origine = $stock_quantity;
                        error_log("[GIACENZA] Inizializzata giacenza origine per variante ID {$variation_id} a {$stock_quantity}.");
                    } elseif ($stock_quantity != intval($giacenza_origine)) {
                        $delta = $stock_quantity - intval($giacenza_origine);
                        $current_stock = intval($variation->get_stock_quantity());
                        $new_stock = max(0, $current_stock + $delta);
                        $variation->set_stock_quantity($new_stock);
                        $variation->set_stock_status($new_stock > 0 ? 'instock' : 'outofstock');
                        error_log("[GIACENZA] Origine modificata da {$giacenza_origine} a {$stock_quantity}, variante {$variation_id} aggiornata da {$current_stock} a {$new_stock}");
                        update_post_meta($variation_id, '_btr_giacenza_origine', $stock_quantity);
                    } else {
                        error_log("[GIACENZA] Origine invariata per variante {$variation_id} ({$giacenza_origine})");
                    }
                    $giacenza_scalata = get_post_meta($variation_id, '_btr_giacenza_scalata', true);
                    $giacenza_scalata = is_numeric($giacenza_scalata) ? intval($giacenza_scalata) : 0;
                    update_post_meta($variation_id, '_btr_giacenza_scalata', $giacenza_scalata);
                    if (!metadata_exists('post', $variation_id, '_btr_giacenza_scalata')) {
                        update_post_meta($variation_id, '_btr_giacenza_scalata', 0);
                        error_log("[GIACENZA] Inizializzata giacenza scalata per variante ID {$variation_id} a 0.");
                    }
                    // Prezzo: usa i dati dell'allotment_data['totale'] se disponibili, altrimenti fallback a 0
                    $prezzo = isset($allotment_data[$range['start']]['prezzo']) ? floatval($allotment_data[$range['start']]['prezzo']) : 0;
                    $supplemento = isset($allotment_data[$range['start']]['supplemento']) ? floatval($allotment_data[$range['start']]['supplemento']) : 0;
                    $sconto = isset($allotment_data[$range['start']]['sconto']) ? floatval($allotment_data[$range['start']]['sconto']) : 0;
                    $room_type = ''; // non specificata
                    // Prezzo CAMERA intera (supplemento escluso, già gestito a parte)
                    $regular = $prezzo;
                    if ($sconto > 0) {
                        $sale = $regular - ($regular * $sconto / 100);
                        $variation->set_sale_price($sale);
                    } else {
                        $variation->set_sale_price('');
                    }
                    $variation->set_regular_price($regular);
                    if ($giacenza_origine !== null) {
                        if ($stock_quantity == intval($giacenza_origine)) {
                            $variation->set_stock_quantity($stock_quantity);
                            $variation->set_stock_status($stock_quantity > 0 ? 'instock' : 'outofstock');
                        }
                    }
                    // Salva il supplemento dopo averlo calcolato
                    update_post_meta( $variation_id, '_btr_supplemento', $supplemento );
                    $variation->save();
                    update_post_meta($variation_id, '_btr_date_name', $range_name);
                    
                    // Debug: Gestione campo 'closed' per allotment_camere (fallback)
                    $closed_value = $range['closed'] ?? 'NON_PRESENTE';

                    
                    $is_closed = !empty($range['closed']) ? '1' : '0';
                    $custom_label = sanitize_text_field($range['label'] ?? '');
                    
                    update_post_meta($variation_id, '_btr_closed', $is_closed);
                    update_post_meta($variation_id, '_btr_closed_label', $custom_label);
                    
                    if ($is_closed === '1') {
                        $variation->set_manage_stock(true);
                        $variation->set_stock_quantity(0);
                        $variation->set_stock_status('outofstock');
                        $variation->save();
                    }
                    
                    error_log("[VARIATIONS] Variante 'allotment_camere' creata ID: {$variation_id} - Data: {$range_name} - Stock: {$stock_quantity}");
                }
            } else {
                // Fallback: nessun allotment_data per questa data, crea variante solo per data
                $variation = new WC_Product_Variation();
                $variation->set_parent_id($product_id);
                $variation->set_virtual(true);
                $variation->set_attributes(['pa_date_disponibili' => $range_name]);
                $variation->set_manage_stock(true);
                $stock_quantity = isset($allotment_data[$range['start']]['totale']) ? intval($allotment_data[$range['start']]['totale']) : 0;
                $variation_id = $variation->save();
                $giacenza_origine = get_post_meta($variation_id, '_btr_giacenza_origine', true);
                if ($giacenza_origine === '' || $giacenza_origine === null) {
                    update_post_meta($variation_id, '_btr_giacenza_origine', $stock_quantity);
                    $giacenza_origine = $stock_quantity;
                    error_log("[GIACENZA] Inizializzata giacenza origine per variante ID {$variation_id} a {$stock_quantity}.");
                } elseif ($stock_quantity != intval($giacenza_origine)) {
                    $delta = $stock_quantity - intval($giacenza_origine);
                    $current_stock = intval($variation->get_stock_quantity());
                    $new_stock = max(0, $current_stock + $delta);
                    $variation->set_stock_quantity($new_stock);
                    $variation->set_stock_status($new_stock > 0 ? 'instock' : 'outofstock');
                    error_log("[GIACENZA] Origine modificata da {$giacenza_origine} a {$stock_quantity}, variante {$variation_id} aggiornata da {$current_stock} a {$new_stock}");
                    update_post_meta($variation_id, '_btr_giacenza_origine', $stock_quantity);
                } else {
                    error_log("[GIACENZA] Origine invariata per variante {$variation_id} ({$giacenza_origine})");
                }
                $giacenza_scalata = get_post_meta($variation_id, '_btr_giacenza_scalata', true);
                $giacenza_scalata = is_numeric($giacenza_scalata) ? intval($giacenza_scalata) : 0;
                update_post_meta($variation_id, '_btr_giacenza_scalata', $giacenza_scalata);
                if (!metadata_exists('post', $variation_id, '_btr_giacenza_scalata')) {
                    update_post_meta($variation_id, '_btr_giacenza_scalata', 0);
                    error_log("[GIACENZA] Inizializzata giacenza scalata per variante ID {$variation_id} a 0.");
                }
                // Prezzo: usa i dati dell'allotment_data['totale'] se disponibili, altrimenti fallback a 0
                $prezzo = isset($allotment_data[$range['start']]['prezzo']) ? floatval($allotment_data[$range['start']]['prezzo']) : 0;
                $supplemento = isset($allotment_data[$range['start']]['supplemento']) ? floatval($allotment_data[$range['start']]['supplemento']) : 0;
                $sconto = isset($allotment_data[$range['start']]['sconto']) ? floatval($allotment_data[$range['start']]['sconto']) : 0;
                $regular = $prezzo;
                if ($sconto > 0) {
                    $sale = $regular - ($regular * $sconto / 100);
                    $variation->set_sale_price($sale);
                } else {
                    $variation->set_sale_price('');
                }
                $variation->set_regular_price($regular);
                if ($giacenza_origine !== null) {
                    if ($stock_quantity == intval($giacenza_origine)) {
                        $variation->set_stock_quantity($stock_quantity);
                        $variation->set_stock_status($stock_quantity > 0 ? 'instock' : 'outofstock');
                    }
                }
                // Salva il supplemento dopo averlo calcolato
                update_post_meta( $variation_id, '_btr_supplemento', $supplemento );
                $variation->save();
                update_post_meta($variation_id, '_btr_date_name', $range_name);
                
                // Debug: Gestione campo 'closed' per allotment_camere (ultimo fallback)
                $closed_value = $range['closed'] ?? 'NON_PRESENTE';

                
                $is_closed = !empty($range['closed']) ? '1' : '0';
                $custom_label = sanitize_text_field($range['label'] ?? '');
                
                update_post_meta($variation_id, '_btr_closed', $is_closed);
                update_post_meta($variation_id, '_btr_closed_label', $custom_label);
                
                if ($is_closed === '1') {
                    $variation->set_manage_stock(true);
                    $variation->set_stock_quantity(0);
                    $variation->set_stock_status('outofstock');
                    $variation->save();
                }
                
                error_log("[VARIATIONS] Variante 'allotment_camere' creata ID: {$variation_id} - Data: {$range_name} - Stock: {$stock_quantity}");
            }
        }

        WC_Product_Variable::sync($product_id);
        $product->set_manage_stock(false);
        $product->save();
        error_log('[VARIATIONS] Varianti "allotment_camere" generate per prodotto ID: ' . $product_id);
    }

    /**
     * Genera varianti per la tipologia di prenotazione 'per_tipologia_camere'
     */
    private function generate_variations_per_tipologia_camere($product_id, $meta_values)
    {
        $product = wc_get_product($product_id);
        // Mappa variazioni esistenti: chiave attributi => oggetto variante
        $existing_variations = [];
        foreach (wc_get_products(['parent' => $product_id, 'limit' => -1, 'type' => 'variation']) as $var) {
            $key = maybe_serialize($var->get_attributes());
            $existing_variations[$key] = $var;
        }

        // Recupera gli attributi del prodotto
        $attributes = $product->get_attributes();
        // Genera tutte le combinazioni possibili
        $combinations = $this->generate_combinations($attributes);

        // Trova e rimuovi solo le varianti obsolete (non più valide)
        $valid_keys = [];
        foreach ($combinations as $combination) {
            $valid_keys[] = maybe_serialize($combination);
        }
        // Rimuove solo le varianti non più valide
        foreach ($existing_variations as $key => $variation) {
            if (!in_array($key, $valid_keys)) {
                $variation_id = $variation->get_id();
                wp_delete_post($variation_id, true);
                error_log("[VARIATIONS] Variante obsoleta ID {$variation_id} rimossa per combinazione non più valida.");
                unset($existing_variations[$key]); // evita che venga riutilizzata più avanti
            }
        }
        // Prepara mappa stock delle varianti esistenti rimaste
        $existing_variations_stock = [];
        foreach ($existing_variations as $key => $var) {
            $variant_key = implode('|', $var->get_attributes());
            $existing_variations_stock[$variant_key] = $var->get_stock_quantity();
        }
        
        // Array per tracciare le varianti create in questa sessione
        $created_variations = [];
        // Recupera sconto generale
        $discount_percentage = isset($meta_values['btr_sconto_percentuale'][0]) ? floatval($meta_values['btr_sconto_percentuale'][0]) : 0;
        // Recupera tariffa base fissa, se presente
        $tariffa_base_fissa = isset($meta_values['btr_tariffa_base_fissa'][0]) ? floatval($meta_values['btr_tariffa_base_fissa'][0]) : 0;
        foreach ($combinations as $combination) {
            // Verifica se la combinazione include una tipologia di camera esclusa
            if (isset($combination['pa_tipologia_camere'])) {
                $room_type = $combination['pa_tipologia_camere'];
                if (isset($this->room_type_exclude_meta_keys[$room_type])) {
                    $exclude_key = $this->room_type_exclude_meta_keys[$room_type];
                    $is_excluded = isset($meta_values[$exclude_key][0]) && $meta_values[$exclude_key][0] === 'on';
                    if ($is_excluded) {
                        error_log("[VARIATIONS] Tipologia di camera '{$room_type}' esclusa. Variante ignorata.");
                        continue; // Salta la creazione della variante
                    }
                }
            }
            // PATCH: Riutilizza o crea la variante solo se non esiste già
            $key = maybe_serialize($combination);
            
            // Controllo anti-duplicazione: verifica se la variante è già stata creata in questa sessione
            if (isset($created_variations[$key])) {
                error_log("[VARIATIONS] Variante già creata in questa sessione per combinazione: {$key}. Saltata.");
                continue;
            }
            
            if (isset($existing_variations[$key])) {
                $variation = $existing_variations[$key];
                $variation_id = $variation->get_id();
                error_log("[VARIATIONS] Riutilizzo variante esistente ID {$variation_id} per combinazione: {$key}");
            } else {
                $variation = new WC_Product_Variation();
                $variation->set_parent_id($product_id);
                $variation->set_virtual(true);
                $variation->set_attributes($combination);
                $variation_id = $variation->save();
                error_log("[VARIATIONS] Creata nuova variante ID {$variation_id} per combinazione: {$key}");
            }
            
            // Traccia la variante come creata/processata in questa sessione
            $created_variations[$key] = $variation_id;
            // Imposta gestione magazzino sempre per ogni variante
            $variation->set_manage_stock(true);
            // Se nuova variante, imposta stock_quantity se non già impostata
            if (isset($combination['pa_tipologia_camere'])) {
                $room_type = $combination['pa_tipologia_camere'];
                $stock_key = $this->room_type_meta_keys[$room_type] ?? null;
                if ($stock_key && isset($meta_values[$stock_key][0])) {
                    $new_origine = intval($meta_values[$stock_key][0]);
                    if (!$variation->get_stock_quantity()) {
                        $variation->set_stock_quantity($new_origine);
                        $variation->set_stock_status($new_origine > 0 ? 'instock' : 'outofstock');
                    }
                }
            }
            // Recupera il prezzo individuale per la tipologia di camera utilizzando la mappatura specifica
            $room_type = isset($combination['pa_tipologia_camere']) ? $combination['pa_tipologia_camere'] : '';
            $price_meta_key = isset($this->room_type_price_meta_keys[$room_type]) ? $this->room_type_price_meta_keys[$room_type] : 'btr_prezzo_base';
            $individual_price = isset($meta_values[$price_meta_key][0]) ? floatval($meta_values[$price_meta_key][0]) : 0;
            // Calcola il prezzo della variante
            $variation_price = $individual_price + $tariffa_base_fissa;
            // Aggiungi supplemento per tipologia camera utilizzando la mappatura specifica
            $supplement = 0;
            if ($room_type && isset($this->room_type_supplement_keys[$room_type])) {
                $supplement_key = $this->room_type_supplement_keys[$room_type];
                if (isset($meta_values[$supplement_key][0])) {
                    $supplement = floatval($meta_values[$supplement_key][0]);
                }
            }
            // ➜ Il prezzo della variante NON include il supplemento
            $variation_price = round($variation_price, 2);
            // Recupera il sconto individuale per la tipologia di camera utilizzando la mappatura specifica
            $individual_discount = 0;
            if ($room_type && isset($this->room_type_discount_meta_keys[$room_type])) {
                $discount_meta_key = $this->room_type_discount_meta_keys[$room_type];
                if (isset($meta_values[$discount_meta_key][0])) {
                    $individual_discount = floatval($meta_values[$discount_meta_key][0]);
                    $individual_discount = max(0, min($individual_discount, 100)); // Limita tra 0 e 100
                }
            }
            // Applica lo sconto individuale se presente, altrimenti applica lo sconto generale
            if ($individual_discount > 0) {
                $discount_amount = round(($variation_price * $individual_discount) / 100, 2);
                $discounted_price = round($variation_price - $discount_amount, 2);
                $variation->set_sale_price($discounted_price);
            } elseif ($discount_percentage > 0) {
                // Se non c'è sconto individuale, applica lo sconto generale
                $discount_amount = round(($variation_price * $discount_percentage) / 100, 2);
                $discounted_price = round($variation_price - $discount_amount, 2);
                $variation->set_sale_price($discounted_price);
            } else {
                $variation->set_sale_price('');
            }
            // Imposta il prezzo regolare
            $variation->set_regular_price($variation_price);
            // Salva sempre la variante dopo il prezzo
            $variation->save();
            // Imposta la quantità in base alla disponibilità della camera
            $variant_key = implode('|', $combination);
            $existing_stock = $existing_variations_stock[$variant_key] ?? null;
            // --- GESTIONE GIACENZA ORIGINALE E CONFRONTO ---
            $stock_key = $this->room_type_meta_keys[$room_type] ?? null;
            if ($stock_key && isset($meta_values[$stock_key][0])) {
                $new_origine = intval($meta_values[$stock_key][0]);
                $giacenza_origine = get_post_meta($variation_id, '_btr_giacenza_origine', true);
                $giacenza_origine = is_numeric($giacenza_origine) ? intval($giacenza_origine) : null;

                if ($giacenza_origine === null) {
                    // Prima sincronizzazione
                    update_post_meta($variation_id, '_btr_giacenza_origine', $new_origine);
                    $giacenza_origine = $new_origine;
                    error_log("[GIACENZA] Inizializzata origine per variante {$variation_id} a {$new_origine}.");
                } elseif ($new_origine !== $giacenza_origine) {
                    // Calcola delta
                    $delta = $new_origine - $giacenza_origine;
                    $current_stock = intval($variation->get_stock_quantity());
                    $new_stock = max(0, $current_stock + $delta);
                    $variation->set_stock_quantity($new_stock);
                    $variation->set_stock_status($new_stock > 0 ? 'instock' : 'outofstock');
                    $variation->save();
                    error_log("[GIACENZA] Origine modificata da {$giacenza_origine} a {$new_origine}, variante {$variation_id} aggiornata da {$current_stock} a {$new_stock}");
                    update_post_meta($variation_id, '_btr_giacenza_origine', $new_origine);
                } else {
                    // Nessun cambiamento
                    error_log("[GIACENZA] Origine invariata per variante {$variation_id} ({$giacenza_origine})");
                }
            }

            // Recupera eventuale giacenza scalata e normalizza sempre il valore
            $giacenza_scalata = get_post_meta($variation_id, '_btr_giacenza_scalata', true);
            $giacenza_scalata = is_numeric($giacenza_scalata) ? intval($giacenza_scalata) : 0;
            update_post_meta($variation_id, '_btr_giacenza_scalata', $giacenza_scalata);
            if (!metadata_exists('post', $variation_id, '_btr_giacenza_scalata')) {
                update_post_meta($variation_id, '_btr_giacenza_scalata', 0);
                $giacenza_scalata = 0;
                error_log("[GIACENZA] Inizializzata giacenza scalata per variante ID {$variation_id} a 0.");
            }

            // Salva il supplemento come meta dato della variante
            update_post_meta($variation_id, '_btr_supplemento', $supplement);
            // Salva anche il sconto individuale come meta dato della variante, se presente
            if ($individual_discount > 0) {
                update_post_meta($variation_id, '_btr_sconto_percentuale', $individual_discount);
            } elseif ($discount_percentage > 0) {
                update_post_meta($variation_id, '_btr_sconto_percentuale', $discount_percentage);
            }
            error_log("[VARIATIONS] Variante creata con ID: {$variation_id}, Prezzo: €{$variation_price}, Sconto: {$individual_discount}%");

            // Salva metadati 'closed', 'label' e 'name' dalla data range corrispondente
            $date_ranges_meta = $this->get_latest_meta_value( $meta_values, 'btr_date_ranges' );
            $date_name = $combination['pa_date_disponibili'] ?? '';
            
            // Debug: Log del contenuto di date_ranges_meta
            
            
            
            
            if (is_array($date_ranges_meta)) {
                
            }

            foreach ($date_ranges_meta as $range) {
                if (!is_array($range)) continue;
                
                // Debug: Log del contenuto di ogni range
                
                $range_name = $range['name'] ?? '';

                
                if ($range_name === $date_name) {

                    
                    // Debug: Verifica presenza campo closed
                    $closed_value = $range['closed'] ?? 'NON_PRESENTE';

                    
                    $is_closed = !empty($range['closed']) ? '1' : '0';
                    $custom_label = sanitize_text_field($range['label'] ?? '');

                    update_post_meta($variation_id, '_btr_closed', $is_closed);
                    update_post_meta($variation_id, '_btr_closed_label', $custom_label);
                    update_post_meta($variation_id, '_btr_date_name', $range_name); // Aggiunto per tracciamento

                    if ($is_closed === '1') {
                        $variation->set_manage_stock(true);
                        $variation->set_stock_quantity(0);
                        $variation->set_stock_status('outofstock');
                        $variation->save();
                    }
                    break;
                }
            }
            // Salva sempre la variante alla fine del ciclo
            $variation->save();
        }
        // Sincronizza le varianti con il prodotto padre
        WC_Product_Variable::sync($product_id);
        error_log('[VARIATIONS] Varianti generate per il prodotto ID: ' . $product_id);
    }

    /**
     * Genera varianti per la tipologia di prenotazione 'per_numero_persone'
     */
    private function generate_variations_per_numero_persone($product_id, $meta_values)
    {
        error_log('[VARIATIONS] Generazione varianti per "per_numero_persone"');
        $product = wc_get_product($product_id);
        if (!$product || !$product->is_type('variable')) {
            error_log('[VARIATIONS] Il prodotto ID ' . $product_id . ' non è di tipo variabile.');
            return;
        }
        // Rimuove le varianti esistenti
        $this->remove_existing_variations($product_id);
        // Recupera gli attributi del prodotto
        $attributes = $product->get_attributes();
        // Verifica se gli attributi necessari sono presenti
        if (empty($attributes)) {
            error_log('[VARIATIONS] Nessun attributo disponibile per il prodotto ID ' . $product_id);
            return;
        }
        // Genera tutte le combinazioni possibili
        $combinations = $this->generate_combinations($attributes);
        if (empty($combinations)) {
            error_log('[VARIATIONS] Nessuna combinazione generata per "per_numero_persone"');
            return;
        }
        // Recupera i metadati necessari
        $base_price = isset($meta_values['btr_prezzo_base'][0]) ? floatval($meta_values['btr_prezzo_base'][0]) : 0;
        $global_discount = isset($meta_values['btr_sconto_percentuale'][0]) ? floatval($meta_values['btr_sconto_percentuale'][0]) : 0;
        $max_people = isset($meta_values['btr_num_persone_max_case2'][0]) ? intval($meta_values['btr_num_persone_max_case2'][0]) : 0;
        // Se il numero massimo di persone è zero, non creare varianti
        if ($max_people <= 0) {
            error_log('[VARIATIONS] Numero massimo di persone non definito per il prodotto ID ' . $product_id);
            return;
        }
        // Recupera i limiti specifici delle camere
        $room_type_limits = $this->get_room_type_limits($meta_values);
        // Recupera la giacenza globale del prodotto principale
        $global_stock = $max_people;
        
        // Array per tracciare le varianti create in questa sessione
        $created_variations = [];
        
        foreach ($combinations as $combination) {
            // Verifica se la combinazione include una tipologia di camera esclusa
            if (isset($combination['pa_tipologia_camere'])) {
                $room_type = $combination['pa_tipologia_camere'];
                if (isset($this->room_type_exclude_meta_keys[$room_type])) {
                    $exclude_key = $this->room_type_exclude_meta_keys[$room_type];
                    $is_excluded = isset($meta_values[$exclude_key][0]) && $meta_values[$exclude_key][0] === 'on';
                    if ($is_excluded) {
                        error_log("[VARIATIONS] Tipologia di camera '{$room_type}' esclusa. Variante ignorata.");
                        continue; // Salta la creazione della variante
                    }
                }
            }
            
            // Controllo anti-duplicazione: verifica se la variante è già stata creata in questa sessione
            $key = maybe_serialize($combination);
            if (isset($created_variations[$key])) {
                error_log("[VARIATIONS] Variante già creata in questa sessione per combinazione: {$key}. Saltata.");
                continue;
            }
            
            $variation = new WC_Product_Variation();
            $variation->set_parent_id($product_id);
            
            // Traccia la variante come creata in questa sessione
            $created_variations[$key] = true;
            // Imposta la variante come virtuale
            $variation->set_virtual(true);
            $variation->set_attributes($combination);
            // Imposta gestione magazzino sempre per ogni variante
            $variation->set_manage_stock(true);
            // Recupera la tipologia di camera
            $room_type = isset($combination['pa_tipologia_camere']) ? $combination['pa_tipologia_camere'] : '';
            // Recupera il prezzo individuale per la tipologia di camera utilizzando la mappatura specifica
            $price_meta_key = isset($this->room_type_price_max_meta_keys[$room_type]) ? $this->room_type_price_max_meta_keys[$room_type] : 'btr_prezzo_base';
            $individual_price = isset($meta_values[$price_meta_key][0]) ? floatval($meta_values[$price_meta_key][0]) : $base_price;
            // Aggiungi supplemento per tipologia camera utilizzando la mappatura specifica
            $supplement = 0;
            if ($room_type && isset($this->room_type_supplement_max_keys[$room_type])) {
                $supplement_key = $this->room_type_supplement_max_keys[$room_type];
                if (isset($meta_values[$supplement_key][0])) {
                    $supplement = floatval($meta_values[$supplement_key][0]);
                    error_log("[SUPPLEMENTO] Tipologia: {$room_type}, Supplemento: €{$supplement}");
                }
            }
            // Calcola il prezzo della variante
            // Prezzo della variante senza supplemento
            $variation_price = round($individual_price, 2);
            // Recupera il sconto individuale per la tipologia di camera utilizzando la mappatura specifica
            $individual_discount = 0;
            if ($room_type && isset($this->room_type_discount_max_meta_keys[$room_type])) {
                $discount_meta_key = $this->room_type_discount_max_meta_keys[$room_type];
                if (isset($meta_values[$discount_meta_key][0])) {
                    $individual_discount = floatval($meta_values[$discount_meta_key][0]);
                    $individual_discount = max(0, min($individual_discount, 100)); // Limita tra 0 e 100
                }
            }
            // Applica lo sconto individuale se presente, altrimenti applica lo sconto globale
            if ($individual_discount > 0) {
                $discount_amount = round(($variation_price * $individual_discount) / 100, 2);
                $discounted_price = round($variation_price - $discount_amount, 2);
                $variation->set_sale_price($discounted_price);
                error_log("[VARIATIONS] Sconto individuale applicato: {$individual_discount}% su €{$variation_price} -> €{$discounted_price}");
            } elseif ($global_discount > 0) {
                $discount_amount = round(($variation_price * $global_discount) / 100, 2);
                $discounted_price = round($variation_price - $discount_amount, 2);
                $variation->set_sale_price($discounted_price);
                error_log("[VARIATIONS] Sconto globale applicato: {$global_discount}% su €{$variation_price} -> €{$discounted_price}");
            } else {
                $variation->set_sale_price('');
                error_log("[VARIATIONS] Nessuno sconto applicato per la variante ID {$variation->get_id()}");
            }
            // Imposta il prezzo regolare
            $variation->set_regular_price($variation_price);
            // Gestione delle giacenze
            $stock_quantity = 0;
            if ($room_type && isset($this->room_type_max_meta_keys[$room_type])) {
                $stock_key = $this->room_type_max_meta_keys[$room_type];
                if (isset($meta_values[$stock_key][0])) {
                    $stock_quantity = intval($meta_values[$stock_key][0]);
                }
            }


            // Se 'btr_num_*_max' non è definito o è zero, imposta a 'btr_num_persone_max_case2'
            if (empty($meta_values[$this->room_type_max_meta_keys[$room_type]][0]) || intval($meta_values[$this->room_type_max_meta_keys[$room_type]][0]) <= 0) {
                $stock_quantity = $global_stock;
                error_log("[VARIATIONS] Tipologia: {$room_type}, Stock ereditato dal globale: {$stock_quantity}");
            } else {
                error_log("[VARIATIONS] Tipologia: {$room_type}, Stock specifico: {$stock_quantity}");
            }


            // Calcola la giacenza effettiva
            $giacenza_finale = max(0, $stock_quantity - $giacenza_scalata);

            // Imposta lo stock effettivo solo se è una nuova variante o stai aggiornando origine
            $variation->set_manage_stock(true);
            $variation->set_stock_quantity($giacenza_finale);
            $variation->set_stock_status($giacenza_finale > 0 ? 'instock' : 'outofstock');
            // Salva la variante
            $variation_id = $variation->save();
            // Inizializza la giacenza scalata solo se non esiste
            if (!metadata_exists('post', $variation_id, '_btr_giacenza_scalata')) {
                update_post_meta($variation_id, '_btr_giacenza_scalata', 0);
                error_log("[GIACENZA] Inizializzata giacenza scalata per variante ID {$variation_id} a 0.");
            }
            if ($variation_id) {

                // --- GESTIONE GIACENZA AVANZATA ---
                // Recupera eventuale giacenza scalata
                $giacenza_scalata = intval(get_post_meta($variation->get_id(), '_btr_giacenza_scalata', true));
                if (!metadata_exists('post', $variation->get_id(), '_btr_giacenza_scalata')) {
                    update_post_meta($variation->get_id(), '_btr_giacenza_scalata', 0);
                    $giacenza_scalata = 0;
                    error_log("[GIACENZA] Inizializzata giacenza scalata per variante ID {$variation->get_id()} a 0.");
                }

                // Recupera giacenza di origine
                $giacenza_origine = get_post_meta($variation->get_id(), '_btr_giacenza_origine', true);
                if ($giacenza_origine === '' || $giacenza_origine === null) {
                    update_post_meta($variation->get_id(), '_btr_giacenza_origine', $stock_quantity);
                    $giacenza_origine = $stock_quantity;
                    error_log("[GIACENZA] Inizializzata giacenza origine per variante ID {$variation->get_id()} a {$stock_quantity}.");
                } elseif ($stock_quantity !== intval($giacenza_origine)) {
                    update_post_meta($variation->get_id(), '_btr_giacenza_origine', $stock_quantity);
                    $giacenza_origine = $stock_quantity;
                    error_log("[GIACENZA] Giacenza origine aggiornata per variante ID {$variation->get_id()} a {$stock_quantity}.");
                } else {
                    $giacenza_origine = intval($giacenza_origine);
                    error_log("[GIACENZA] Giacenza origine invariata per variante ID {$variation->get_id()}: {$giacenza_origine}");
                }



                // Salva il supplemento come meta dato della variante
                update_post_meta($variation_id, '_btr_supplemento', $supplement);
                // Salva anche il sconto individuale come meta dato della variante, se presente
                if ($individual_discount > 0) {
                    update_post_meta($variation_id, '_btr_sconto_percentuale', $individual_discount);
                } elseif ($global_discount > 0) {
                    update_post_meta($variation_id, '_btr_sconto_percentuale', $global_discount);
                }
                error_log("[VARIATIONS] Variante creata con ID: {$variation_id}, Prezzo: €{$variation_price}, Sconto: {$individual_discount}%");
            }
        }
        // Imposta la giacenza globale per il prodotto principale
        $product->set_manage_stock(true);
        $product->set_stock_quantity($max_people);
        $product->set_stock_status($max_people > 0 ? 'instock' : 'outofstock');
        $product->save();
        // Sincronizza le varianti con il prodotto padre
        WC_Product_Variable::sync($product_id);
        error_log('[VARIATIONS] Varianti generate con successo per "per_numero_persone" per il prodotto ID: ' . $product_id);
    }

    /**
     * Recupera i limiti specifici delle tipologie di camere dal pacchetto
     */
    private function get_room_type_limits($meta_values)
    {
        $limits = [];
        // Definisci qui le chiavi meta che contengono i limiti delle camere
        $room_limit_meta_keys = [
            'Singola' => 'btr_num_singole_max',
            'Doppia' => 'btr_num_doppie_max',
            'Tripla' => 'btr_num_triple_max',
            'Quadrupla' => 'btr_num_quadruple_max',
            'Quintupla' => 'btr_num_quintuple_max',
            'Condivisa' => 'btr_num_condivisa_max',
        ];
        foreach ($room_limit_meta_keys as $room_type => $meta_key) {
            if (isset($meta_values[$meta_key][0])) {
                $limit = intval($meta_values[$meta_key][0]);
                if ($limit > 0) {
                    $limits[$room_type] = $limit;
                }
            }
        }
        return $limits;
    }

    /**
     * Genera tutte le combinazioni di attributi per le varianti.
     */
    private function generate_combinations($attributes)
    {
        $combinations = [[]];
        foreach ($attributes as $attribute) {
            $options = $attribute->get_options();
            $attribute_name = $attribute->get_name();
            $temp_combinations = [];
            foreach ($combinations as $combination) {
                foreach ($options as $option) {
                    $temp_combination = $combination;
                    $temp_combination[$attribute_name] = $option;
                    $temp_combinations[] = $temp_combination;
                }
            }
            $combinations = $temp_combinations;
        }
        error_log("[COMBINATIONS] Combinazioni generate: " . print_r($combinations, true));
        return $combinations;
    }

    /**
     * Rimuove tutte le varianti esistenti di un prodotto variabile
     */
    private function remove_existing_variations($product_id)
    {
        $existing_variations = wc_get_products([
            'parent' => $product_id,
            'limit' => -1,
            'type' => 'variation',
            'return' => 'ids',
        ]);
        foreach ($existing_variations as $variation_id) {
            wp_delete_post($variation_id, true);
            error_log("[VARIATIONS] Variante ID {$variation_id} rimossa per il prodotto ID: {$product_id}");
        }
    }

    /**
     * Cancella il prodotto WooCommerce associato a un pacchetto eliminato
     */
    public function delete_product_on_package_delete($post_id)
    {
        if (get_post_type($post_id) !== $this->post_type) {
            return;
        }
        $product_id = get_post_meta($post_id, $this->meta_key_product_id, true);
        if ($product_id) {
            wp_delete_post($product_id, true);
            delete_post_meta($post_id, $this->meta_key_product_id);
            error_log("[DELETE] Prodotto WooCommerce ID {$product_id} eliminato per il pacchetto ID {$post_id}.");

            // Elimina varianti collegate al prodotto
            $variation_ids = wc_get_products([
                'parent' => $product_id,
                'limit' => -1,
                'return' => 'ids',
                'type' => 'variation',
            ]);

            foreach ($variation_ids as $variation_id) {
                wp_delete_post($variation_id, true);
                error_log("[DELETE] Variante WooCommerce ID {$variation_id} eliminata (prodotto padre ID {$product_id}).");
            }

            // Elimina prodotti assicurazioni collegati
            $prodotti_assicurazioni = get_post_meta($post_id, 'btr_assicurazioni_prodotti', true);
            if (is_array($prodotti_assicurazioni)) {
                foreach ($prodotti_assicurazioni as $item) {
                    if (!empty($item['id'])) {
                        wp_delete_post($item['id'], true);
                        error_log("[DELETE] Prodotto assicurazione ID {$item['id']} eliminato (pacchetto ID {$post_id}).");
                    }
                }
                delete_post_meta($post_id, 'btr_assicurazioni_prodotti');
            }

            // Elimina prodotti extra collegati
            $prodotti_extra = get_post_meta($post_id, 'btr_extra_costi_prodotti', true);
            if (is_array($prodotti_extra)) {
                foreach ($prodotti_extra as $item) {
                    if (!empty($item['id'])) {
                        wp_delete_post($item['id'], true);
                        error_log("[DELETE] Prodotto extra ID {$item['id']} eliminato (pacchetto ID {$post_id}).");
                    }
                }
                delete_post_meta($post_id, 'btr_extra_costi_prodotti');
            }
        }
    }

    /**
     * Salva il valore del prezzo individuale, supplemento e sconto per la variante
     */
    public function save_price_and_supplemento_field_variation($variation_id, $i)
    {
        // Salva il prezzo individuale
        if (isset($_POST['btr_prezzo_individuale'][$i])) {
            $prezzo_individuale = floatval($_POST['btr_prezzo_individuale'][$i]);
            $prezzo_individuale = max(0, $prezzo_individuale); // Evita prezzi negativi
            update_post_meta($variation_id, '_btr_prezzo_individuale', $prezzo_individuale);
            error_log("[VARIATIONS] Prezzo Individuale (€) salvato per la variante ID {$variation_id}: €{$prezzo_individuale}");
        }
        // Salva il supplemento
        if (isset($_POST['btr_supplemento'][$i])) {
            $supplemento = wc_clean($_POST['btr_supplemento'][$i]);
            update_post_meta($variation_id, '_btr_supplemento', $supplemento);
            error_log("[VARIATIONS] Supplemento (€) salvato per la variante ID {$variation_id}: €{$supplemento}");
        }
        // Salva lo sconto
        if (isset($_POST['btr_sconto_percentuale'][$i])) {
            $sconto = floatval($_POST['btr_sconto_percentuale'][$i]);
            $sconto = max(0, min($sconto, 100)); // Limita lo sconto tra 0 e 100
            update_post_meta($variation_id, '_btr_sconto_percentuale', $sconto);
            error_log("[VARIATIONS] Sconto (%) salvato per la variante ID {$variation_id}: {$sconto}%");
        }
    }

    /**
     * Getter per room_type_meta_keys
     */
    public function get_room_type_meta_keys()
    {
        return $this->room_type_meta_keys;
    }

    /**
     * Getter per room_type_supplement_max_keys
     */
    public function get_room_type_supplement_max_keys()
    {
        return $this->room_type_supplement_max_keys;
    }

    /**
     * Aggiungi i dati aggiuntivi del pacchetto come metadati al prodotto WooCommerce.
     *
     * @param WC_Product $product Oggetto prodotto WooCommerce.
     * @param int $post_id ID del post CPT.
     */
    private function add_additional_product_meta($product, $post_id)
    {
        // Recupera i metadati del pacchetto
        $meta_fields = [
            'btr_tipologia_prenotazione',
            'btr_destinazione',
            'btr_tipo_durata',
            'btr_numero_giorni',
            'btr_numero_giorni_libere',
            'btr_numero_giorni_fisse',
            'btr_numero_notti',
            'btr_date_ranges',
            'btr_prezzo_base',
            'btr_sconto_percentuale',
            'btr_include_items',
            'btr_exclude_items',
            'btr_costi_extra',
            'btr_riduzioni',
            'btr_num_persone_max_case2',
            'btr_num_condivisa_max',
            'btr_supplemento_singole_max',
            'btr_supplemento_doppie_max',
            'btr_supplemento_triple_max',
            'btr_supplemento_quadruple_max',
            'btr_supplemento_quintuple_max',
            'btr_supplemento_condivisa_max',
            // Nuovi campi per 'per_tipologia_camere'
            'btr_prezzo_singole',
            'btr_sconto_singole',
            'btr_prezzo_doppie',
            'btr_sconto_doppie',
            'btr_prezzo_triple',
            'btr_sconto_triple',
            'btr_prezzo_quadruple',
            'btr_sconto_quadruple',
            'btr_prezzo_quintuple',
            'btr_sconto_quintuple',
            // Nuovi campi per 'per_numero_persone'
            'btr_prezzo_singole_max',
            'btr_sconto_singola_max',
            'btr_prezzo_doppie_max',
            'btr_sconto_doppie_max',
            'btr_prezzo_triple_max',
            'btr_sconto_triple_max',
            'btr_prezzo_quadruple_max',
            'btr_sconto_quadruple_max',
            'btr_prezzo_quintuple_max',
            'btr_sconto_quintuple_max',
            'btr_prezzo_condivisa_max',
            'btr_sconto_condivisa_max',
            // Sconti bambini
            'btr_bambini_fascia1_sconto',
            'btr_bambini_fascia2_sconto',
        ];
        foreach ($meta_fields as $field) {
            $value = get_post_meta($post_id, $field, true);
            if (!empty($value)) {
                $product->update_meta_data('_' . $field, $value);
                if (strpos($field, 'prezzo') !== false) {
                    error_log("[META DATA] Campo '{$field}' (Prezzo) aggiunto al prodotto ID " . $product->get_id() . " con valore: €" . $value);
                } elseif (strpos($field, 'sconto') !== false) {
                    error_log("[META DATA] Campo '{$field}' (Sconto) aggiunto al prodotto ID " . $product->get_id() . " con valore: " . $value . "%");
                } else {
                    error_log("[META DATA] Campo '{$field}' aggiunto al prodotto ID " . $product->get_id() . " con valore: " . print_r($value, true));
                }
            }
        }
        // Salva i metadati del prodotto
        $product->save();
        error_log("[META DATA] Metadati aggiunti al prodotto ID " . $product->get_id());
    }

    /**
     * Sincronizza le assicurazioni per il pacchetto.
     *
     * @param int $post_id ID del pacchetto.
     */
    private function sync_assicurazioni_for_package($post_id)
    {
        $assicurazioni = get_post_meta($post_id, 'btr_assicurazione_importi', true);
        if (empty($assicurazioni) || !is_array($assicurazioni)) {
            return;
        }
        $prodotti_assicurazioni = [];
        foreach ($assicurazioni as $item) {
            $descrizione = sanitize_text_field($item['descrizione'] ?? '');
            $importo = floatval($item['importo'] ?? 0);
            $hash = md5($descrizione . '-' . $importo);
            // Cerca un prodotto esistente con hash
            $query = new WP_Query([
                'post_type' => 'product',
                'posts_per_page' => 1,
                'meta_query' => [
                    [
                        'key' => '_btr_assicurazione_hash',
                        'value' => $hash,
                        'compare' => '='
                    ]
                ]
            ]);
            if ($query->have_posts()) {
                $product_id = $query->posts[0]->ID;
            } else {
                // Crea nuovo prodotto virtuale invisibile
                $product = new WC_Product_Simple();
                $product->set_name('Assicurazione - ' . $descrizione);
                $product->set_regular_price($importo);
                $product->set_virtual(true);
                $product->set_catalog_visibility('hidden');
                $product_id = $product->save();
                update_post_meta($product_id, '_btr_assicurazione_hash', $hash);
                error_log("🆕 Creato prodotto assicurazione: {$descrizione} (€{$importo}) - ID: {$product_id}");
            }
            $prodotti_assicurazioni[] = [
                'id' => $product_id,
                'descrizione' => $descrizione,
                'importo' => $importo
            ];
        }
        update_post_meta($post_id, 'btr_assicurazioni_prodotti', $prodotti_assicurazioni);
    }

    /**
     * Sincronizza i prodotti per i costi extra definiti nel pacchetto.
     *
     * @param int $post_id ID del pacchetto.
     */
    private function sync_costi_extra_for_package($post_id)
    {
        $costi_extra = get_post_meta($post_id, 'btr_costi_extra', true);
        if (empty($costi_extra) || !is_array($costi_extra)) {
            return;
        }
        $prodotti_extra = [];
        foreach ($costi_extra as $item) {
            // Ignora voci non attive
            if (empty($item['attivo']) || $item['attivo'] !== '1') {
                continue;
            }
            $nome    = sanitize_text_field($item['nome'] ?? '');
            $importo = floatval($item['importo'] ?? 0);
            $sconto  = floatval($item['sconto'] ?? 0);
            $hash    = md5($nome . '-' . $importo . '-' . $sconto);

            // Cerca prodotto esistente
            $query = new WP_Query([
                'post_type'      => 'product',
                'posts_per_page' => 1,
                'meta_query'     => [
                    [
                        'key'     => '_btr_extra_hash',
                        'value'   => $hash,
                        'compare' => '=',
                    ],
                ],
            ]);
            if ($query->have_posts()) {
                $product_id = $query->posts[0]->ID;
            } else {
                // Crea nuovo prodotto virtuale invisibile
                $product = new WC_Product_Simple();
                $product->set_name('Extra - ' . $nome);
                // Applica lo sconto: prezzo di listino e prezzo scontato
                $product->set_regular_price($importo);
                if ($sconto > 0) {
                    $netto = round($importo - ($importo * $sconto / 100), 2);
                    $product->set_sale_price($netto);
                }
                $product->set_virtual(true);
                $product->set_catalog_visibility('hidden');
                $product_id = $product->save();
                update_post_meta($product_id, '_btr_extra_hash', $hash);
                update_post_meta($product_id, '_btr_extra_sconto', $sconto);
            }

            $prodotti_extra[] = [
                'id'                => $product_id,
                'nome'              => $nome,
                'importo'           => $importo,
                'sconto'            => $sconto,
                'moltiplica_persone'=> !empty($item['moltiplica_persone']),
                'moltiplica_durata' => !empty($item['moltiplica_durata']),
            ];
        }
        update_post_meta($post_id, 'btr_extra_costi_prodotti', $prodotti_extra);
    }

    /**
     * Aggiunge metadati al cart-item per la “notte extra”.
     */
    public function add_extra_night_cart_item_meta( $cart_item_data, $product_id, $variation_id ) {
        $extra_night = isset( $_POST['extra_night'] ) ? intval( $_POST['extra_night'] ) : 0;
        if ( $extra_night === 1 && isset( $_POST['selected_date'] ) ) {
            $selected_date = sanitize_text_field( $_POST['selected_date'] );
            $extra_date    = date( 'Y-m-d', strtotime( $selected_date . ' -1 day' ) );
            $cart_item_data['_btr_extra_night'] = 1;
            $cart_item_data['_btr_extra_date']  = $extra_date;
        }
        return $cart_item_data;
    }
}
// Inizializza la classe
new BTR_WooCommerce_Sync();

/**
 * Aggiunge un fee separato “Supplemento notte extra” calcolato
 * per persona:
 *   • recupera _btr_supplemento (valore per persona)
 *   • moltiplica per la capacità della camera × quantità
 *   • per la Singola (capacità 1) non cambia nulla
 */
add_action( 'woocommerce_cart_calculate_fees', function ( $cart ) {

    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }

    $total_extra_fee = 0.0;

    // Mappa capacità per tipologia (ripresa dalla classe principale)
    $capacity_map = [
        'Singola'   => 1,
        'Doppia'    => 2,
        'Tripla'    => 3,
        'Quadrupla' => 4,
        'Quintupla' => 5,
        'Condivisa' => 1,
    ];

    foreach ( $cart->get_cart() as $cart_item ) {

        // Supplemento per persona salvato in meta
        $supp_pp = isset( $cart_item['_btr_supplemento'] )
            ? floatval( $cart_item['_btr_supplemento'] )
            : 0;

        if ( $supp_pp <= 0 ) {
            continue; // niente da fare
        }

        // Ricava tipologia camera dalla variazione
        $room_type = '';
        if ( ! empty( $cart_item['variation'] ) ) {
            foreach ( $cart_item['variation'] as $attr_key => $attr_val ) {
                if ( strpos( $attr_key, 'pa_tipologia_camere' ) !== false ) {
                    $room_type = wc_attribute_label( $attr_val );
                    break;
                }
            }
        }

        $capacity = isset( $capacity_map[ $room_type ] )
            ? intval( $capacity_map[ $room_type ] )
            : 1;

        // Quante persone copre questa riga carrello
        $num_people = $capacity * intval( $cart_item['quantity'] );

        // Calcola il fee per questa riga
        $fee_for_item = $supp_pp * $num_people;
        $total_extra_fee += $fee_for_item;
    }

    if ( $total_extra_fee > 0 ) {
        // Aggiunge un'unica riga fee, tassabile (true) come il prodotto
        $cart->add_fee( __( 'Supplemento notte extra', 'born-to-ride' ), $total_extra_fee, true );
    }

}, 20 );


/* ----------------------------------------------------------------------
 *  Sconti automatici bambini (fascia 1: 3–12 / fascia 2: 12–14)
 * -------------------------------------------------------------------- */

/**
 * Applica il prezzo ridotto alle righe di carrello marcate con
 *  – btr_role = child_f1  (Bambini 3–12)
 *  – btr_role = child_f2  (Bambini 12–14)
 */
add_action( 'woocommerce_before_calculate_totals', 'btr_apply_child_discount', 10 );
function btr_apply_child_discount( $cart ) {

    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
        return;
    }

    foreach ( $cart->get_cart() as $cart_item ) {

        // Le righe “adulti” non hanno btr_role
        if ( empty( $cart_item['btr_role'] ) ) {
            continue;
        }

        // Percentuali di riduzione salvate sul prodotto variabile
        $discount_f1 = (float) get_post_meta( $cart_item['product_id'], '_btr_bambini_fascia1_sconto', true );
        $discount_f2 = (float) get_post_meta( $cart_item['product_id'], '_btr_bambini_fascia2_sconto', true );

        // Prezzo pieno (adulto) preso dalla variante
        $base_price = (float) $cart_item['data']->get_regular_price();

        switch ( $cart_item['btr_role'] ) {
            case 'child_f1':
                $cart_item['data']->set_price( round( $base_price * ( 1 - $discount_f1 / 100 ), 2 ) );
                break;

            case 'child_f2':
                $cart_item['data']->set_price( round( $base_price * ( 1 - $discount_f2 / 100 ), 2 ) );
                break;
        }
    }
}

/**
 * Riporta in ordine il tipo di partecipante (Adulto / Bambino …)
 */
add_action( 'woocommerce_checkout_create_order_line_item', function ( $item, $cart_item_key, $values, $order ) {

    if ( ! empty( $values['btr_role_desc'] ) ) {
        $item->add_meta_data( __( 'Partecipante', 'born-to-ride-booking' ), $values['btr_role_desc'], true );
    }

}, 10, 4 );

/*********************************************************************
 *  ADMIN UI – GIACENZE ALLOTMENT
 *********************************************************************/

/**
 * ▸ META‑BOX riepilogo giacenze globali (origine / scalata / residua)
 *   visualizzato nella pagina di modifica prodotto (tab “Prodotto” di WC).
 */
add_action( 'add_meta_boxes', function () {
    add_meta_box(
        'btr_allotment_global_stock',
        __( 'Allotment – Disponibilità per data', 'born-to-ride-booking' ),
        'btr_render_allotment_global_stock_box',
        'product',
        'normal',
        'high'
    );
} );

/**
 * Callback che stampa la tabella con le giacenze.
 *
 * @param WP_Post $post
 */
function btr_render_allotment_global_stock_box( $post ) {

    // Mostra solo su prodotti variabili che hanno meta _btr_giacenza_origine_globale_*
    if ( 'product' !== $post->post_type ) {
        return;
    }
    $origin_metas = array_filter(
        get_post_meta( $post->ID ),
        function ( $value, $key ) {
            return str_starts_with( $key, '_btr_giacenza_origine_globale_' );
        },
        ARRAY_FILTER_USE_BOTH
    );

    if ( empty( $origin_metas ) ) {
        echo '<p>' . esc_html__( 'Nessuna giacenza allotment registrata.', 'born-to-ride-booking' ) . '</p>';
        return;
    }

    echo '<table class="widefat striped"><thead><tr>';
    echo '<th>' . esc_html__( 'Data', 'born-to-ride-booking' ) . '</th>';
    echo '<th style="text-align:right;">' . esc_html__( 'Origine', 'born-to-ride-booking' ) . '</th>';
    echo '<th style="text-align:right;">' . esc_html__( 'Vendute', 'born-to-ride-booking' ) . '</th>';
    echo '<th style="text-align:right;">' . esc_html__( 'Residue', 'born-to-ride-booking' ) . '</th>';
    echo '</tr></thead><tbody>';

    foreach ( $origin_metas as $meta_key => $values ) {
        $date_name  = str_replace( '_btr_giacenza_origine_globale_', '', $meta_key );
        $origin     = intval( end( $values ) );
        $scaled     = intval( get_post_meta( $post->ID, '_btr_giacenza_scalata_globale_' . $date_name, true ) );
        $residua    = max( 0, $origin - $scaled );

        echo '<tr>';
        echo '<td>' . esc_html( $date_name ) . '</td>';
        echo '<td style="text-align:right;">' . esc_html( $origin ) . '</td>';
        echo '<td style="text-align:right;">' . esc_html( $scaled ) . '</td>';
        echo '<td style="text-align:right;font-weight:600;">' . esc_html( $residua ) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

/**
 * ▸ Colonna aggiuntiva nella lista Ordini per vedere quante camere allotment
 *   sono state scalate dall’ordine.
 */
add_filter( 'manage_edit-shop_order_columns', function ( $columns ) {
    $columns['btr_allotment_qty'] = __( 'Allotment', 'born-to-ride-booking' );
    return $columns;
} );

add_action( 'manage_shop_order_posts_custom_column', function ( $column ) {
    if ( 'btr_allotment_qty' !== $column ) {
        return;
    }

    $order = wc_get_order( get_the_ID() );
    if ( ! $order ) {
        echo '–';
        return;
    }

    $qty = 0;
    foreach ( $order->get_items() as $item ) {
        $variation_id = $item->get_variation_id();
        if ( $variation_id && get_post_meta( $variation_id, '_btr_date_name', true ) ) {
            $qty += $item->get_quantity();
        }
    }
    echo $qty ? intval( $qty ) : '–';
}, 10, 1 );

/**
 * Aggiungi il prezzo, il supplemento e lo sconto come campi personalizzati nelle varianti
 */
add_action('woocommerce_product_after_variable_attributes', 'btr_add_price_and_supplemento_fields_to_variation', 10, 3);
function btr_add_price_and_supplemento_fields_to_variation($loop, $variation_data, $variation)
{
    $supplemento = get_post_meta($variation->ID, '_btr_supplemento', true);
    $sconto = get_post_meta($variation->ID, '_btr_sconto_percentuale', true);
    $giacenza_origine = get_post_meta($variation->ID, '_btr_giacenza_origine', true);
    // Calculate giacenza scalata and residua
    $giacenza_scalata = get_post_meta( $variation->ID, '_btr_giacenza_scalata', true );
    $giacenza_scalata = is_numeric( $giacenza_scalata ) ? intval( $giacenza_scalata ) : 0;
    $giacenza_residua = max( 0, intval( $giacenza_origine ) - $giacenza_scalata );
    ?>
    <div class="form-row form-row-full">
        <label for="btr_supplemento_<?php echo $loop; ?>">
            Supplemento (€)
            <input type="number" step="0.01" min="0" name="btr_supplemento[<?php echo $loop; ?>]" id="btr_supplemento_<?php echo $loop; ?>"
                   value="<?php echo esc_attr($supplemento); ?>"/>
        </label>
    </div>
    <div class="form-row form-row-full">
        <label for="btr_sconto_percentuale_<?php echo $loop; ?>">
            Sconto (%)
            <input type="number" step="0.01" min="0" max="100" name="btr_sconto_percentuale[<?php echo $loop; ?>]" id="btr_sconto_percentuale_<?php echo $loop; ?>"
                   value="<?php echo esc_attr($sconto); ?>"/>
        </label>
    </div>
    <div class="form-row form-row-full">
        <label for="btr_giacenza_origine_<?php echo $loop; ?>">
            Giacenza originale
            <input type="number" step="1" min="0" readonly disabled
                   name="btr_giacenza_origine[<?php echo $loop; ?>]"
                   id="btr_giacenza_origine_<?php echo $loop; ?>"
                   value="<?php echo esc_attr($giacenza_origine); ?>" />
        </label>
    </div>
    <div class="form-row form-row-full">
        <label>
            <?php _e( 'Giacenza venduta', 'born-to-ride-booking' ); ?>
            <input type="number" step="1" min="0" readonly disabled
                   value="<?php echo esc_attr( $giacenza_scalata ); ?>" />
        </label>
    </div>
    <div class="form-row form-row-full">
        <label>
            <?php _e( 'Giacenza residua', 'born-to-ride-booking' ); ?>
            <input type="number" step="1" min="0" readonly disabled
                   value="<?php echo esc_attr( $giacenza_residua ); ?>" />
        </label>
    </div>
    <?php
}

/**
 * Salva il valore del prezzo individuale, supplemento e sconto per la variante
 */
add_action('woocommerce_save_product_variation', 'btr_save_price_and_supplemento_field_variation', 10, 2);
function btr_save_price_and_supplemento_field_variation($variation_id, $i)
{

    // Salva il supplemento
    if (isset($_POST['btr_supplemento'][$i])) {
        $supplemento = wc_clean($_POST['btr_supplemento'][$i]);
        update_post_meta($variation_id, '_btr_supplemento', $supplemento);
        error_log("[VARIATIONS] Supplemento (€) salvato per la variante ID {$variation_id}: €{$supplemento}");
    }

    // Salva lo sconto
    if (isset($_POST['btr_sconto_percentuale'][$i])) {
        $sconto = floatval($_POST['btr_sconto_percentuale'][$i]);
        $sconto = max(0, min($sconto, 100)); // Limita lo sconto tra 0 e 100
        update_post_meta($variation_id, '_btr_sconto_percentuale', $sconto);
        error_log("[VARIATIONS] Sconto (%) salvato per la variante ID {$variation_id}: {$sconto}%");
    }
}

/**
 * Aggiungi il prezzo, il supplemento e lo sconto come dato personalizzato nelle varianti
 */
add_filter('woocommerce_available_variation', 'btr_add_price_and_supplemento_to_variation_data');
function btr_add_price_and_supplemento_to_variation_data($variation)
{
    $variation['_btr_supplemento'] = get_post_meta($variation['variation_id'], '_btr_supplemento', true);
    $variation['_btr_sconto_percentuale'] = get_post_meta($variation['variation_id'], '_btr_sconto_percentuale', true);
    return $variation;
}

add_filter('woocommerce_order_item_get_formatted_meta_data', function ($formatted_meta, $item) {
    foreach ($formatted_meta as $meta_id => $meta) {
        if ($meta->key === 'pa_tipologia_camere') {
            $formatted_meta[$meta_id]->display_key = __('Camera', 'born-to-ride-booking');
        }
        if ($meta->key === 'pa_date_disponibili') {
            $formatted_meta[$meta_id]->display_key = __('Data', 'born-to-ride-booking');
        }
    }
    return $formatted_meta;
}, 10, 2);


// ─────────────────────────────────────────────────────────
// Filtro per impedire l’over‑booking di varianti e allotment
// ─────────────────────────────────────────────────────────
add_filter(
    'woocommerce_add_to_cart_validation',
    function ( $passed, $product_id, $quantity, $variation_id = 0, $variations = null ) {

        // 1. Se non è una variante esci subito
        if ( ! $variation_id ) {
            return $passed;
        }

        $variation = wc_get_product( $variation_id );
        if ( ! $variation ) {
            return $passed;
        }

        // 2. Controllo stock della singola variante
        if ( $variation->managing_stock() ) {
            $stock_quantity = $variation->get_stock_quantity();

            error_log( "[BTR] Tentativo add‑to‑cart – variante {$variation_id}, stock disponibile: {$stock_quantity}" );

            if ( $stock_quantity < $quantity ) {
                wc_add_notice(
                    __( 'La quantità richiesta non è più disponibile.', 'born-to-ride-booking' ),
                    'error'
                );
                return false;
            }
        }

        // 3. Controllo allotment globale per la data (solo se presente)
        $attributes = $variation->get_attributes();
        $date_name  = $attributes['pa_date_disponibili'] ?? '';

        if ( $date_name ) {

            $parent_id   = $variation->get_parent_id();
            $meta_origin = '_btr_giacenza_origine_globale_' . $date_name;
            $meta_scaled = '_btr_giacenza_scalata_globale_' . $date_name;

            $origin = intval( get_post_meta( $parent_id, $meta_origin, true ) );
            $scaled = intval( get_post_meta( $parent_id, $meta_scaled, true ) );

            // origin == 0  → illimitato
            if ( $origin > 0 ) {
                $remaining = max( 0, $origin - $scaled );

                error_log( "[BTR] Allotment globale '{$date_name}' – origin: {$origin}, scalato: {$scaled}, rimasti: {$remaining}" );

                if ( $remaining < $quantity ) {
                    wc_add_notice(
                        __( 'Non ci sono più camere disponibili per questa data.', 'born-to-ride-booking' ),
                        'error'
                    );
                    return false;
                }
            }
        }

        return $passed;
    },
    10,
    5
);