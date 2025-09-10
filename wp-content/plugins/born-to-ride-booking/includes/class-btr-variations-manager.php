<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Evita accessi diretti
}

class BTR_Variations_Manager {

    public function __construct() {
        // DISABILITATO: Hook rimosso per evitare conflitti con BTR_WooCommerce_Sync
        // add_action( 'btr_sync_with_woocommerce', array( $this, 'sync_package_to_product' ), 10, 2 );
        // Collegati all'azione di cancellazione del pacchetto per rimuovere il prodotto
        add_action( 'delete_post', array( $this, 'delete_product_on_package_delete' ), 10, 1 );
    }

    /**
     * Sincronizza il pacchetto di viaggio con il prodotto WooCommerce
     */
    public function sync_package_to_product( $post_id, $meta_values ) {
        // Ottieni o crea il prodotto WooCommerce associato
        $product_id = get_post_meta( $post_id, '_btr_product_id', true );

        if ( ! $product_id ) {
            // Crea un nuovo prodotto
            $product = new WC_Product();
            $product->set_name( get_the_title( $post_id ) );
            $product->set_status( 'publish' );
            $product->set_catalog_visibility( 'hidden' ); // Nascondi il prodotto nel catalogo
            $product->set_description( apply_filters( 'the_content', get_post_field( 'post_content', $post_id ) ) );
            $product->save();

            // Salva l'ID del prodotto nel metadato del pacchetto
            update_post_meta( $post_id, '_btr_product_id', $product->get_id() );
        } else {
            // Recupera il prodotto esistente
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                // Se il prodotto non esiste, creane uno nuovo
                $product = new WC_Product();
                $product->set_name( get_the_title( $post_id ) );
                $product->set_status( 'publish' );
                $product->set_catalog_visibility( 'hidden' );
                $product->set_description( apply_filters( 'the_content', get_post_field( 'post_content', $post_id ) ) );
                $product->save();

                // Aggiorna il metadato
                update_post_meta( $post_id, '_btr_product_id', $product->get_id() );
            }
        }

        // Aggiorna il titolo e la descrizione del prodotto
        $product->set_name( get_the_title( $post_id ) );
        $product->set_description( apply_filters( 'the_content', get_post_field( 'post_content', $post_id ) ) );
        $product->save();

        // Imposta il tipo di prodotto come 'variable'
        $product->set_type( 'variable' );
        $product->save();

        // Gestisci gli attributi del prodotto
        $this->set_product_attributes( $product, $meta_values );

        // Genera le varianti del prodotto
        $this->generate_variations( $product->get_id(), $post_id, $meta_values );

        // Sync product in WooCommerce
        WC_Product_Variable::sync( $product->get_id() );
    }

    /**
     * Imposta gli attributi del prodotto WooCommerce
     */
    private function set_product_attributes( $product, $meta_values ) {
        $attributes = array();

        // Attributo "Numero Persone"
        if ( isset( $meta_values['btr_tipologia_prenotazione'] ) && $meta_values['btr_tipologia_prenotazione'] === 'per_numero_persone' ) {
            $attribute = new WC_Product_Attribute();
            $attribute->set_name( 'pa_numero_persone' );
            $attribute->set_options( range( 1, $meta_values['btr_num_persone_max_case2'] ) );
            $attribute->set_position( 0 );
            $attribute->set_visible( true );
            $attribute->set_variation( true );
            $attributes[] = $attribute;
        }

        // Attributo "Tipo Camera"
        if ( isset( $meta_values['btr_tipologia_prenotazione'] ) && $meta_values['btr_tipologia_prenotazione'] === 'per_tipologia_camere' ) {
            $room_types = array();
            if ( isset( $meta_values['btr_num_singole'] ) && $meta_values['btr_num_singole'] > 0 ) {
                $room_types[] = 'Singola';
            }
            if ( isset( $meta_values['btr_num_doppie'] ) && $meta_values['btr_num_doppie'] > 0 ) {
                $room_types[] = 'Doppia';
            }
            if ( isset( $meta_values['btr_num_triple'] ) && $meta_values['btr_num_triple'] > 0 ) {
                $room_types[] = 'Tripla';
            }
            if ( isset( $meta_values['btr_num_quadruple'] ) && $meta_values['btr_num_quadruple'] > 0 ) {
                $room_types[] = 'Quadrupla';
            }
            if ( isset( $meta_values['btr_num_quintuple'] ) && $meta_values['btr_num_quintuple'] > 0 ) {
                $room_types[] = 'Quintupla';
            }

            if ( ! empty( $room_types ) ) {
                $attribute = new WC_Product_Attribute();
                $attribute->set_name( 'pa_tipologia_camere' );
                $attribute->set_options( $room_types );
                $attribute->set_position( 1 );
                $attribute->set_visible( true );
                $attribute->set_variation( true );
                $attributes[] = $attribute;
            }
        }

        // Aggiungi altri attributi se necessario

        // Imposta gli attributi al prodotto
        $product->set_attributes( $attributes );
        $product->save();
    }

    /**
     * Genera le varianti del prodotto WooCommerce
     */
    public function generate_variations( $product_id, $post_id, $meta_values ) {
        $product = wc_get_product( $product_id );

        if ( ! $product || 'variable' !== $product->get_type() ) {
            return;
        }

        // Mappa esistente delle varianti per confronto
        $existing_variations = $product->get_children();
        $existing_map = [];
        foreach ( $existing_variations as $variation_id ) {
            $variation_obj = wc_get_product( $variation_id );
            if ( $variation_obj ) {
                $existing_map[ maybe_serialize( $variation_obj->get_attributes() ) ] = $variation_obj;
            }
        }

        // Genera combinazioni di varianti basate sugli attributi
        $combinations = $this->generate_combinations( $meta_values );

        // Gestione stock per variazione
        $room_quantities = array(
            'Singola'   => intval( $meta_values['btr_num_singole'] ),
            'Doppia'    => intval( $meta_values['btr_num_doppie'] ),
            'Tripla'    => intval( $meta_values['btr_num_triple'] ),
            'Quadrupla' => intval( $meta_values['btr_num_quadruple'] ),
            'Quintupla' => intval( $meta_values['btr_num_quintuple'] ),
        );

        foreach ( $combinations as $combination ) {
            // Filtra combinazioni che non hanno attributi
            if ( ! isset( $combination['pa_numero_persone'] ) && ! isset( $combination['pa_tipologia_camere'] ) ) {
                continue;
            }

            $key = maybe_serialize( $combination );
            if ( isset( $existing_map[ $key ] ) ) {
                $variation = $existing_map[ $key ];
                $variation_id = $variation->get_id();
            } else {
                $variation = new WC_Product_Variation();
                $variation->set_parent_id( $product_id );
                $variation->set_attributes( $combination );
                $variation_id = $variation->save();
            }

            // Calcola il prezzo della variazione
            $price = $this->calculate_variation_price( $post_id, $meta_values, $combination );
            $variation->set_regular_price( $price );

            // Gestione stock per variazione
            if ( isset( $combination['pa_tipologia_camere'] ) ) {
                $room_type = $combination['pa_tipologia_camere'];
                $stock_quantity = isset( $room_quantities[ $room_type ] ) ? $room_quantities[ $room_type ] : 0;
                $variation->set_manage_stock( true );

                // Recupera metadati
                $giacenza_origine = get_post_meta( $variation_id, '_btr_giacenza_origine', true );
                $giacenza_scalata = get_post_meta( $variation_id, '_btr_giacenza_scalata', true );
                $giacenza_scalata = is_numeric($giacenza_scalata) ? intval($giacenza_scalata) : 0;
                update_post_meta( $variation_id, '_btr_giacenza_scalata', $giacenza_scalata );

                if ( $giacenza_origine === '' || $giacenza_origine === false ) {
                    update_post_meta( $variation_id, '_btr_giacenza_origine', $stock_quantity );
                    $giacenza_origine = $stock_quantity;
                } elseif ( intval( $giacenza_origine ) !== $stock_quantity ) {
                    update_post_meta( $variation_id, '_btr_giacenza_origine', $stock_quantity );
                    $giacenza_origine = $stock_quantity;
                }

                // Calcola la giacenza finale
                $giacenza_finale = max( 0, intval( $giacenza_origine ) - intval( $giacenza_scalata ) );

                // Imposta lo stock solo se è cambiato
                $current_stock = $variation->get_stock_quantity();
                if ( $current_stock != $giacenza_finale ) {
                    $variation->set_stock_quantity( $giacenza_finale );
                    $variation->set_stock_status( $giacenza_finale > 0 ? 'instock' : 'outofstock' );
                    $variation->save();
                }
            } else {
                $variation->set_manage_stock( false );
                $variation->set_stock_status( 'instock' );
                $variation->save();
            }
        }
    }

    /**
     * Calcola il prezzo della variazione basato sugli attributi selezionati.
     *
     * @param int   $post_id    ID del post CPT.
     * @param array $meta_values Metadati del pacchetto.
     * @param array $combination Combinazione di attributi.
     *
     * @return float Prezzo calcolato.
     */
    private function calculate_variation_price( $post_id, $meta_values, $combination ) {
        // Recupera il prezzo base dal metadato del pacchetto
        $prezzo_base = floatval( $meta_values['btr_prezzo_base'] );
        $tariffa_base_fissa = floatval( $meta_values['btr_tariffa_base_fissa'] );
        $sconto_percentuale = floatval( $meta_values['btr_sconto_percentuale'] );
        $moltiplica_persone = isset( $meta_values['btr_moltiplica_prezzo_persone'] ) && $meta_values['btr_moltiplica_prezzo_persone'] === '1' ? true : false;

        // Inizializza il prezzo con la tariffa base fissa o il prezzo base
        $price = $tariffa_base_fissa > 0 ? $tariffa_base_fissa : $prezzo_base;

        // Applica lo sconto percentuale se presente
        if ( $sconto_percentuale > 0 ) {
            $price -= ( ( $sconto_percentuale / 100 ) * $price );
        }

        // Numero di persone
        if ( isset( $combination['pa_numero_persone'] ) && $moltiplica_persone ) {
            $num_persone = intval( $combination['pa_numero_persone'] );
            $price *= $num_persone;
        }

        // Tipologia di Camera
        if ( isset( $combination['pa_tipologia_camere'] ) ) {
            $camere = sanitize_text_field( $combination['pa_tipologia_camere'] );
            // Logica per modificare il prezzo basata sul tipo di camera
            switch ( $camere ) {
                case 'Singola':
                    // Nessuna modifica
                    break;
                case 'Doppia':
                    $price += 20; // Ad esempio, aggiungi €20
                    break;
                case 'Tripla':
                    $price += 40; // Ad esempio, aggiungi €40
                    break;
                case 'Quadrupla':
                    $price += 60; // Ad esempio, aggiungi €60
                    break;
                case 'Quintupla':
                    $price += 80; // Ad esempio, aggiungi €80
                    break;
                // Aggiungi altri tipi di camere se necessario
            }
        }

        // Riduzioni
        if ( isset( $meta_values['btr_riduzioni'] ) && is_array( $meta_values['btr_riduzioni'] ) ) {
            foreach ( $meta_values['btr_riduzioni'] as $riduzione ) {
                if ( isset( $riduzione['nome'], $riduzione['prezzo'] ) ) {
                    // Implementa la logica per applicare la riduzione
                    // Ad esempio, se la riduzione si applica alla combinazione corrente
                    // Potresti aggiungere condizioni qui
                    $price -= floatval( $riduzione['prezzo'] );
                }
            }
        }

        // Supplementi
        if ( isset( $meta_values['btr_supplementi'] ) && is_array( $meta_values['btr_supplementi'] ) ) {
            foreach ( $meta_values['btr_supplementi'] as $supplemento ) {
                if ( isset( $supplemento['nome'], $supplemento['prezzo'] ) ) {
                    // Implementa la logica per applicare il supplemento
                    // Ad esempio, se il supplemento si applica alla combinazione corrente
                    // Potresti aggiungere condizioni qui
                    $price += floatval( $supplemento['prezzo'] );
                }
            }
        }

        // Assicurazione Annullamento
        if ( isset( $meta_values['btr_assicurazione_importi'] ) && is_array( $meta_values['btr_assicurazione_importi'] ) ) {
            foreach ( $meta_values['btr_assicurazione_importi'] as $assicurazione ) {
                if ( isset( $assicurazione['descrizione'], $assicurazione['importo'] ) ) {
                    // Implementa la logica per applicare l'assicurazione
                    $price += floatval( $assicurazione['importo'] );
                }
            }
        }

        // Condizioni di Pagamento o Altri Costi Extra possono essere aggiunti qui

        // Assicurati che il prezzo non sia negativo
        if ( $price < 0 ) {
            $price = 0;
        }

        // Arrotonda il prezzo a due decimali
        $price = round( $price, 2 );

        return $price;
    }

    /**
     * Genera tutte le possibili combinazioni di varianti in base agli attributi
     *
     * @param array $meta_values Metadati del pacchetto.
     *
     * @return array Combinazioni di attributi.
     */
    private function generate_combinations( $meta_values ) {
        $attributes = array();

        // Attributo "Numero Persone" se applicabile
        if ( isset( $meta_values['btr_tipologia_prenotazione'] ) && $meta_values['btr_tipologia_prenotazione'] === 'per_numero_persone' ) {
            if ( isset( $meta_values['btr_num_persone_max_case2'] ) && $meta_values['btr_num_persone_max_case2'] > 0 ) {
                $attributes['pa_numero_persone'] = range( 1, $meta_values['btr_num_persone_max_case2'] );
            }
        }

        // Attributo "Tipo Camera" se applicabile
        if ( isset( $meta_values['btr_tipologia_prenotazione'] ) && $meta_values['btr_tipologia_prenotazione'] === 'per_tipologia_camere' ) {
            $room_types = array();

            if ( isset( $meta_values['btr_num_singole'] ) && $meta_values['btr_num_singole'] > 0 ) {
                $room_types[] = 'Singola';
            }
            if ( isset( $meta_values['btr_num_doppie'] ) && $meta_values['btr_num_doppie'] > 0 ) {
                $room_types[] = 'Doppia';
            }
            if ( isset( $meta_values['btr_num_triple'] ) && $meta_values['btr_num_triple'] > 0 ) {
                $room_types[] = 'Tripla';
            }
            if ( isset( $meta_values['btr_num_quadruple'] ) && $meta_values['btr_num_quadruple'] > 0 ) {
                $room_types[] = 'Quadrupla';
            }
            if ( isset( $meta_values['btr_num_quintuple'] ) && $meta_values['btr_num_quintuple'] > 0 ) {
                $room_types[] = 'Quintupla';
            }

            if ( ! empty( $room_types ) ) {
                $attributes['pa_tipologia_camere'] = $room_types;
            }
        }

        // Genera il prodotto cartesiano degli attributi
        $combinations = $this->cartesian_product( $attributes );

        return $combinations;
    }

    /**
     * Genera il prodotto cartesiano di array
     *
     * @param array $arrays Array di array.
     *
     * @return array Prodotto cartesiano.
     */
    private function cartesian_product( $arrays ) {
        $result = array( array() );

        foreach ( $arrays as $key => $values ) {
            $tmp = array();
            foreach ( $result as $result_item ) {
                foreach ( $values as $value ) {
                    $tmp[] = array_merge( $result_item, array( $key => $value ) );
                }
            }
            $result = $tmp;
        }

        return $result;
    }

    /**
     * Elimina il prodotto WooCommerce associato quando viene cancellato il pacchetto
     */
    public function delete_product_on_package_delete( $post_id ) {
        // Verifica se il post è del tipo 'pacchetti_viaggio'
        if ( get_post_type( $post_id ) !== 'pacchetti_viaggio' ) {
            return;
        }

        // Recupera l'ID del prodotto associato
        $product_id = get_post_meta( $post_id, '_btr_product_id', true );

        if ( $product_id ) {
            // Elimina il prodotto WooCommerce
            wp_delete_post( $product_id, true );

            // Rimuovi il metadato
            delete_post_meta( $post_id, '_btr_product_id' );
        }
    }

}
