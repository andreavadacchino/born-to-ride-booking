<?php

/**
 * Ottiene l'etichetta del supplemento base in base al tipo di camera
 *
 * @param string $tipo_camera Tipo di camera
 * @return string Etichetta del supplemento
 */
function btr_get_supplemento_base_label($tipo_camera) {
    $tipo_lower = strtolower($tipo_camera);
    
    if (strpos($tipo_lower, 'singola') !== false) {
        return 'Supplemento Singola';
    } elseif (strpos($tipo_lower, 'doppia') !== false || strpos($tipo_lower, 'matrimoniale') !== false) {
        return 'Supplemento Doppia';
    } elseif (strpos($tipo_lower, 'tripla') !== false) {
        return 'Supplemento Tripla';
    } elseif (strpos($tipo_lower, 'quadrupla') !== false) {
        return 'Supplemento Quadrupla';
    } else {
        return 'Supplemento base';
    }
}

/**
 * Ottiene l'etichetta del supplemento notti extra in base al numero di notti e al tipo di camera
 *
 * @param int $numero_notti Numero di notti extra
 * @param string $tipo_camera Tipo di camera
 * @return string Etichetta del supplemento
 */
function btr_get_supplemento_notti_extra_label($numero_notti, $tipo_camera) {
    $tipo_lower = strtolower($tipo_camera);
    $notte_text = $numero_notti == 1 ? 'notte' : 'notti';
    
    if (strpos($tipo_lower, 'singola') !== false) {
        return "Supplemento $notte_text extra Singola";
    } elseif (strpos($tipo_lower, 'doppia') !== false || strpos($tipo_lower, 'matrimoniale') !== false) {
        return "Supplemento $notte_text extra Doppia";
    } elseif (strpos($tipo_lower, 'tripla') !== false) {
        return "Supplemento $notte_text extra Tripla";
    } elseif (strpos($tipo_lower, 'quadrupla') !== false) {
        return "Supplemento $notte_text extra Quadrupla";
    } else {
        return "Supplemento $notte_text extra";
    }
}

/**
 * Server-side render per il blocco «btr/checkout-summary».
 *
 * @param array  $attributes  Attributi del blocco (vuoto, per ora).
 * @param string $content     Contenuto salvato (non usato).
 * @param object $block       Oggetto WP_Block (non usato).
 */
function btr_render_checkout_summary_block( $attributes, $content, $block ) {
    // Previeni rendering multipli
    static $already_rendered = false;
    if ( $already_rendered ) {
        return '<!-- BTR Checkout Summary already rendered -->';
    }
    
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        return '';
    }

    $cart = WC()->cart;
    $already_rendered = true;
    ob_start();

    // Initialize totals for insurance and extra costs
    $insurance_total = 0;
    $extra_cost_total = 0;
    
    // Recupera i dati anagrafici - priorità alla sessione, poi preventivo
    $anagrafici_data = [];
    
    // Prova prima con underscore, poi senza
    $preventivo_id = WC()->session->get('_preventivo_id', 0);
    if (!$preventivo_id) {
        $preventivo_id = WC()->session->get('btr_preventivo_id', 0);
    }
    
    // Se ancora non trovato, cercalo nel carrello
    if (!$preventivo_id && !empty($cart->get_cart())) {
        foreach ($cart->get_cart() as $cart_item) {
            if (isset($cart_item['preventivo_id'])) {
                $preventivo_id = intval($cart_item['preventivo_id']);
                break;
            }
        }
    }
    
    // Recupera i dati anagrafici dalla fonte più affidabile
    // FIX v1.0.225: Priorità database > sessione per evitare dati corrotti
    if ($preventivo_id) {
        // PRIMA: Controlla database (fonte di verità)
        $preventivo_data_raw = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
        
        if (!empty($preventivo_data_raw) && is_array($preventivo_data_raw)) {
            $anagrafici_data = $preventivo_data_raw;
            
            // Log per debug
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[BTR FIX v1.0.225] Usando anagrafici dal database per preventivo ' . $preventivo_id);
            }
        } else {
            // FALLBACK: Solo se database vuoto, usa sessione
            $session_data = WC()->session->get('btr_anagrafici_data', []);
            if (!empty($session_data) && is_array($session_data)) {
                // Verifica coerenza con numero persone atteso
                $totale_persone = intval(get_post_meta($preventivo_id, '_btr_totale_persone', true));
                if ($totale_persone > 0 && count($session_data) > $totale_persone) {
                    // Taglia array se troppo lungo
                    $anagrafici_data = array_slice($session_data, 0, $totale_persone);
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[BTR FIX v1.0.225] Sessione aveva ' . count($session_data) . ' persone, tagliato a ' . $totale_persone);
                    }
                } else {
                    $anagrafici_data = $session_data;
                }
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[BTR FIX v1.0.225] Usando anagrafici dalla sessione (fallback) per preventivo ' . $preventivo_id);
                }
            }
        }
        
        // IMPORTANTE: Applica il filtro No Skipass prima di qualsiasi elaborazione
        if (!empty($anagrafici_data) && class_exists('BTR_No_Skipass_Filter')) {
            $anagrafici_data = BTR_No_Skipass_Filter::filter_rc_skipass($anagrafici_data, $preventivo_id);
        }
        
        // FILTRO MIRATO: Rimuovi solo i veri phantom participants (neonati duplicati)
        if (!empty($anagrafici_data)) {
            $anagrafici_filtrati = [];
            $neonati_visti = [];
            
            foreach ($anagrafici_data as $persona) {
                $nome_lower = strtolower(trim($persona['nome'] ?? ''));
                $cognome_lower = strtolower(trim($persona['cognome'] ?? ''));
                $tipo_persona = strtolower(trim($persona['tipo_persona'] ?? ''));
                $camera_tipo = strtolower(trim($persona['camera_tipo'] ?? ''));
                
                // Identifica i neonati
                $is_neonato = $tipo_persona === 'neonato' || 
                             $camera_tipo === 'culla per neonati' || 
                             $camera_tipo === 'neonato';
                
                if ($is_neonato) {
                    // Per i neonati, verifica duplicati esatti
                    $chiave_neonato = $nome_lower . '|' . $cognome_lower . '|' . $tipo_persona . '|' . $camera_tipo;
                    
                    // Skip solo se è un duplicato ESATTO di un neonato già visto
                    if (in_array($chiave_neonato, $neonati_visti)) {
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('BTR - SKIP neonato duplicato: ' . $nome_lower . ' ' . $cognome_lower);
                        }
                        continue;
                    }
                    
                    $neonati_visti[] = $chiave_neonato;
                }
                
                // MANTIENI tutti gli altri partecipanti (adulti e bambini)
                // Solo skip se completamente vuoti
                if (empty($nome_lower) && empty($cognome_lower)) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('BTR - SKIP partecipante completamente vuoto');
                    }
                    continue;
                }
                
                $anagrafici_filtrati[] = $persona;
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('BTR - MANTIENI: ' . $nome_lower . ' ' . $cognome_lower . ' (tipo: ' . $tipo_persona . ')');
                }
            }
            
            $anagrafici_data = $anagrafici_filtrati;
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('BTR - Filtro mirato: ' . count($anagrafici_filtrati) . ' partecipanti dopo filtro duplicati neonati');
            }
        }
    }
    
    // Recupera i dati del preventivo per i calcoli corretti
    $preventivo_data = [];
    if ($preventivo_id) {
        // Recupera tutti i meta del preventivo
        $all_meta = get_post_meta($preventivo_id);
        
        // Estrai i valori necessari
        $preventivo_data = [
            'prezzo_totale' => isset($all_meta['_prezzo_totale'][0]) ? floatval($all_meta['_prezzo_totale'][0]) : 0,
            'supplemento_totale' => isset($all_meta['_supplemento_totale'][0]) ? floatval($all_meta['_supplemento_totale'][0]) : 0,
            'extra_night_cost' => isset($all_meta['_extra_night_cost'][0]) ? floatval($all_meta['_extra_night_cost'][0]) : 0,
            'riepilogo_calcoli_dettagliato' => isset($all_meta['_riepilogo_calcoli_dettagliato'][0]) ? $all_meta['_riepilogo_calcoli_dettagliato'][0] : '',
            'package_price_no_extra' => isset($all_meta['_package_price_no_extra'][0]) ? floatval($all_meta['_package_price_no_extra'][0]) : 0,
            'costi_extra_totale' => isset($all_meta['_costi_extra_totale'][0]) ? floatval($all_meta['_costi_extra_totale'][0]) : 0,
            'durata' => isset($all_meta['_durata'][0]) ? $all_meta['_durata'][0] : '',
            'numero_notti_extra' => isset($all_meta['_numero_notti_extra'][0]) ? intval($all_meta['_numero_notti_extra'][0]) : 0,
            'nome_pacchetto' => isset($all_meta['_nome_pacchetto'][0]) ? $all_meta['_nome_pacchetto'][0] : '',
            'data_partenza' => isset($all_meta['_data_partenza'][0]) ? $all_meta['_data_partenza'][0] : '',
            'date_ranges_name' => isset($all_meta['_date_ranges_name'][0]) ? $all_meta['_date_ranges_name'][0] : '',
            'camere_selezionate' => isset($all_meta['_camere_selezionate'][0]) ? maybe_unserialize($all_meta['_camere_selezionate'][0]) : [],
            'date_ranges' => isset($all_meta['_date_ranges'][0]) ? $all_meta['_date_ranges'][0] : '',
            'data_pacchetto' => isset($all_meta['_data_pacchetto'][0]) ? $all_meta['_data_pacchetto'][0] : '',
            'btr_extra_night_date' => isset($all_meta['_btr_extra_night_date'][0]) ? maybe_unserialize($all_meta['_btr_extra_night_date'][0]) : [],
        ];
        
        $num_adults_meta  = isset($all_meta['_num_adults'][0]) ? intval($all_meta['_num_adults'][0]) : 0;
        $num_children_meta = isset($all_meta['_num_children'][0]) ? intval($all_meta['_num_children'][0]) : 0;
        $num_infants_meta = isset($all_meta['_num_neonati'][0]) ? intval($all_meta['_num_neonati'][0]) : 0;

        // Debug log per verificare i dati
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BTR Checkout Summary - Preventivo ID: ' . $preventivo_id);
            error_log('BTR Checkout Summary - Dati recuperati: ' . print_r($preventivo_data, true));
        }
    }
    // Pre-compute data for redesigned checkout summary
    $package_name = trim($preventivo_data['nome_pacchetto'] ?? '');
    $package_summary_items = array();

    // Determine primary date range
    $package_dates_value = '';
    if (!empty($preventivo_data['data_pacchetto'])) {
        $package_dates_value = $preventivo_data['data_pacchetto'];
    } elseif (!empty($preventivo_data['date_ranges'])) {
        $package_dates_value = $preventivo_data['date_ranges'];
    } elseif (!empty($preventivo_data['data_partenza'])) {
        $package_dates_value = date_i18n('d F Y', strtotime($preventivo_data['data_partenza']));
    }

    if ($package_dates_value) {
        $package_summary_items[] = array(
            'label' => __('Date', 'born-to-ride-booking'),
            'value' => $package_dates_value,
        );
    }

    // Occupancy snapshot
    $occupancy_parts = array();
    if (!empty($num_adults_meta)) {
        $occupancy_parts[] = sprintf(
            _n('%d adulto', '%d adulti', $num_adults_meta, 'born-to-ride-booking'),
            $num_adults_meta
        );
    }
    if (!empty($num_children_meta)) {
        $occupancy_parts[] = sprintf(
            _n('%d bambino', '%d bambini', $num_children_meta, 'born-to-ride-booking'),
            $num_children_meta
        );
    }
    if (!empty($num_infants_meta)) {
        $occupancy_parts[] = sprintf(
            _n('%d neonato', '%d neonati', $num_infants_meta, 'born-to-ride-booking'),
            $num_infants_meta
        );
    }
    if (!empty($occupancy_parts)) {
        $package_summary_items[] = array(
            'label' => __('Occupazione', 'born-to-ride-booking'),
            'value' => implode(', ', $occupancy_parts),
        );
    }

    // Duration badge including extra nights
    $duration_label = '';
    if (!empty($preventivo_data['durata'])) {
        $duration_label = $preventivo_data['durata'];
    }
    $numero_notti_extra = intval($preventivo_data['numero_notti_extra'] ?? 0);
    if ($numero_notti_extra > 0) {
        $extra_text = sprintf(
            _n('%d notte extra', '%d notti extra', $numero_notti_extra, 'born-to-ride-booking'),
            $numero_notti_extra
        );
        $duration_label = $duration_label ? $duration_label . ' · ' . $extra_text : $extra_text;
    }
    if ($duration_label) {
        $package_summary_items[] = array(
            'label' => __('Durata', 'born-to-ride-booking'),
            'value' => $duration_label,
        );
    }

    // Extra nights detail
    $extra_nights_label = '';
    if (!empty($preventivo_data['btr_extra_night_date']) && is_array($preventivo_data['btr_extra_night_date'])) {
        $date_extra = array();
        foreach ($preventivo_data['btr_extra_night_date'] as $extra_date_string) {
            if (!empty($extra_date_string)) {
                $dates = explode(',', $extra_date_string);
                foreach ($dates as $date_value) {
                    $date_value = trim($date_value);
                    if (!empty($date_value)) {
                        $date_extra[] = date_i18n('d F', strtotime($date_value));
                    }
                }
            }
        }
        if (!empty($date_extra)) {
            $extra_nights_label = sprintf(
                _n('%d notte extra (%s)', '%d notti extra (%s)', count($date_extra), 'born-to-ride-booking'),
                count($date_extra),
                implode(', ', $date_extra)
            );
        }
    } elseif ($numero_notti_extra > 0) {
        $extra_nights_label = sprintf(
            _n('%d notte extra', '%d notti extra', $numero_notti_extra, 'born-to-ride-booking'),
            $numero_notti_extra
        );
    }
    if ($extra_nights_label) {
        $package_summary_items[] = array(
            'label' => __('Notti extra', 'born-to-ride-booking'),
            'value' => $extra_nights_label,
        );
    }

    // Rooms summary
    $rooms_description = array();
    if (!empty($preventivo_data['camere_selezionate']) && is_array($preventivo_data['camere_selezionate'])) {
        foreach ($preventivo_data['camere_selezionate'] as $camera) {
            $tipo_camera = $camera['tipo'] ?? $camera['tipo_camera'] ?? '';
            if (!empty($tipo_camera)) {
                $quantita = intval($camera['quantita'] ?? 1);
                $rooms_description[] = $quantita > 1
                    ? sprintf('%s ×%d', $tipo_camera, $quantita)
                    : $tipo_camera;
            }
        }
    }
    if (!empty($rooms_description)) {
        $package_summary_items[] = array(
            'label' => __('Sistemazioni', 'born-to-ride-booking'),
            'value' => implode(', ', $rooms_description),
        );
    }

    if ($preventivo_id) {
        $package_summary_items[] = array(
            'label' => __('Preventivo', 'born-to-ride-booking'),
            'value' => '#' . $preventivo_id,
        );
    }

    // Prepare breakdown data
    $riepilogo_dettagliato = !empty($preventivo_data['riepilogo_calcoli_dettagliato'])
        ? maybe_unserialize($preventivo_data['riepilogo_calcoli_dettagliato'])
        : null;
    if (!is_array($riepilogo_dettagliato)) {
        $riepilogo_dettagliato = null;
    }

    // BTR TOTALS RESOLVER v1.0.244: usa il totale consolidato del preventivo
    $price_snapshot = $preventivo_id ? get_post_meta($preventivo_id, '_price_snapshot', true) : array();
    $has_snapshot = $preventivo_id ? get_post_meta($preventivo_id, '_has_price_snapshot', true) : false;
    $totale_preventivo_meta = $preventivo_id ? get_post_meta($preventivo_id, '_totale_preventivo', true) : 0;

    $totals_source = 'manual';
    if ($totale_preventivo_meta && floatval($totale_preventivo_meta) > 0) {
        $total_from_preventivo = floatval($totale_preventivo_meta);
        $totals_source = 'preventivo';
    } elseif ($has_snapshot && !empty($price_snapshot) && isset($price_snapshot['totals']['grand_total'])) {
        $total_from_preventivo = floatval($price_snapshot['totals']['grand_total']);
        $totals_source = 'snapshot';
    } else {
        $base = floatval($preventivo_data['prezzo_totale'] ?? 0);
        $ins = $preventivo_id ? floatval(get_post_meta($preventivo_id, '_totale_assicurazioni', true)) : 0;
        $extra = $preventivo_id ? floatval(get_post_meta($preventivo_id, '_totale_costi_extra', true)) : 0;
        $total_from_preventivo = round($base + $ins + $extra, 2);
        $totals_source = 'manual';
    }

    // Totali carrello numerici
    $cart_totals = is_object($cart) ? $cart->get_totals() : array();
    $cart_calculated_total = isset($cart_totals['total']) ? floatval($cart_totals['total']) : 0;
    $cart_subtotal = isset($cart_totals['subtotal']) ? floatval($cart_totals['subtotal']) : 0;

    // Baseline assicurazioni/extra
    $cart_insurance_total = $preventivo_id ? floatval(get_post_meta($preventivo_id, '_totale_assicurazioni', true)) : 0;
    $cart_extra_total = $preventivo_id ? floatval(get_post_meta($preventivo_id, '_totale_costi_extra', true)) : 0;

    // Calculator defaults (se non viene iniettato nessun calcolatore, evita undefined)
    $calculator_totals_valid = false;
    $calculator_base_total = 0;
    $calculator_extra_nights_total = 0;
    $calculator_insurance_total = 0;
    $calculator_extra_costs_total = 0;
    $calculator_total_final = 0;

    // Diagnostica discrepanze
    $discrepancy = abs($total_from_preventivo - ($cart_calculated_total > 0 ? $cart_calculated_total : $total_from_preventivo));

    $has_dettaglio_persone = is_array($riepilogo_dettagliato) && !empty($riepilogo_dettagliato['dettaglio_persone_per_categoria']);
    $has_partecipanti = is_array($riepilogo_dettagliato) && !empty($riepilogo_dettagliato['partecipanti']);
    $categorie_da_mostrare = array();

    if ($has_dettaglio_persone) {
        $categorie_da_mostrare = $riepilogo_dettagliato['dettaglio_persone_per_categoria'];
    } elseif ($has_partecipanti) {
        foreach ($riepilogo_dettagliato['partecipanti'] as $categoria => $dati) {
            if (!empty($dati['quantita']) && $dati['quantita'] > 0) {
                $categorie_da_mostrare[$categoria] = array(
                    'count' => intval($dati['quantita']),
                    'prezzo_unitario' => floatval($dati['prezzo_base_unitario'] ?? 0),
                    'totale_prezzo' => floatval($dati['subtotale_base'] ?? 0),
                    'supplemento_unitario' => floatval($dati['supplemento_base_unitario'] ?? 0),
                    'totale_supplemento' => floatval($dati['subtotale_supplemento_base'] ?? 0),
                    'notte_extra_unitario' => floatval($dati['notte_extra_unitario'] ?? 0),
                    'totale_notte_extra' => floatval($dati['subtotale_notte_extra'] ?? 0),
                    'supplemento_extra_unitario' => floatval($dati['supplemento_extra_unitario'] ?? 0),
                    'totale_supplemento_extra' => floatval($dati['subtotale_supplemento_extra'] ?? 0),
                );
            }
        }
    }

    $tipo_camera_predominante = '';
    if (!empty($preventivo_data['camere_selezionate']) && is_array($preventivo_data['camere_selezionate'])) {
        $tipi_camera_count = array();
        foreach ($preventivo_data['camere_selezionate'] as $camera) {
            $tipo = $camera['tipo'] ?? '';
            if (!empty($tipo)) {
                $tipi_camera_count[$tipo] = ($tipi_camera_count[$tipo] ?? 0) + intval($camera['quantita'] ?? 1);
            }
        }
        if (!empty($tipi_camera_count)) {
            arsort($tipi_camera_count);
            $tipo_camera_predominante = key($tipi_camera_count);
        }
    }

    // Participants cards rendering data
    $participants_cards = array();
    if (!empty($anagrafici_data) && is_array($anagrafici_data)) {
        $participant_counter = 0;
        $package_id_reference = $preventivo_id ? get_post_meta($preventivo_id, '_pacchetto_id', true) : 0;
        $assicurazioni_config_cache = null;
        $extra_config_cache = null;

        foreach ($anagrafici_data as $persona) {
            $nome = trim((string)($persona['nome'] ?? ''));
            $cognome = trim((string)($persona['cognome'] ?? ''));
            if ($nome === '' && $cognome === '') {
                continue;
            }

            $participant_counter++;
            $full_name = trim($nome . ' ' . $cognome);
            $tipo_persona_display = strtolower((string)($persona['tipo_persona'] ?? ''));
            $fascia = strtolower((string)($persona['fascia'] ?? ''));

            if ($tipo_persona_display === 'neonato' || $fascia === 'neonato') {
                $type_label = __('Neonato', 'born-to-ride-booking');
            } elseif ($tipo_persona_display === 'bambino' || $fascia !== '') {
                if ($fascia !== '' && function_exists('btr_get_child_label')) {
                    $type_label = btr_get_child_label($fascia, $preventivo_id);
                } else {
                    $type_label = __('Bambino', 'born-to-ride-booking');
                }
            } else {
                $type_label = __('Adulto', 'born-to-ride-booking');
            }

            $room_label = '';
            if (!empty($persona['camera_tipo'])) {
                $room_label = $persona['camera_tipo'];
                if (!empty($persona['camera']) && $persona['camera'] !== 'neonato-no-room') {
                    $room_label .= ' · ' . $persona['camera'];
                }
            }

            $insurance_items = array();
            if (!empty($persona['assicurazioni_dettagliate']) && is_array($persona['assicurazioni_dettagliate'])) {
                foreach ($persona['assicurazioni_dettagliate'] as $slug => $assicurazione) {
                    $is_active = !empty($assicurazione['attivo'])
                        || !empty($assicurazione['selezionata'])
                        || (!isset($assicurazione['attivo']) && !empty($assicurazione['descrizione']) && isset($assicurazione['importo']));
                    if (!$is_active) {
                        continue;
                    }

                    $descrizione = $assicurazione['descrizione'] ?? $assicurazione['nome'] ?? ucfirst(str_replace('-', ' ', (string)$slug));
                    $importo = floatval($assicurazione['importo'] ?? 0);
                    if ($importo <= 0) {
                        continue;
                    }
                    $insurance_total += $importo;
                    $insurance_items[] = array(
                        'label' => $descrizione,
                        'amount' => $importo,
                    );
                }
            }

            if (empty($insurance_items) && !empty($persona['assicurazioni']) && is_array($persona['assicurazioni'])) {
                if ($package_id_reference && $assicurazioni_config_cache === null) {
                    $assicurazioni_config_cache = get_post_meta($package_id_reference, 'btr_assicurazione_importi', true);
                }
                if (is_array($assicurazioni_config_cache)) {
                    foreach ($persona['assicurazioni'] as $slug => $selected) {
                        if (!$selected) {
                            continue;
                        }
                        $config = $assicurazioni_config_cache[$slug] ?? null;
                        if (!$config) {
                            continue;
                        }
                        $descrizione = $config['nome'] ?? $config['descrizione'] ?? ucfirst(str_replace('-', ' ', (string)$slug));
                        $importo = floatval($config['importo'] ?? 0);
                        if ($importo <= 0) {
                            continue;
                        }
                        $insurance_total += $importo;
                        $insurance_items[] = array(
                            'label' => $descrizione,
                            'amount' => $importo,
                        );
                    }
                }
            }

            $extra_items = array();
            if (!empty($persona['costi_extra_dettagliate']) && is_array($persona['costi_extra_dettagliate'])) {
                foreach ($persona['costi_extra_dettagliate'] as $slug => $extra) {
                    $is_active = !empty($extra['attivo'])
                        || !empty($extra['selezionato'])
                        || (!isset($extra['attivo']) && isset($extra['importo']));
                    if (!$is_active) {
                        continue;
                    }
                    $nome_extra = $extra['nome'] ?? $extra['descrizione'] ?? ucfirst(str_replace('-', ' ', (string)$slug));
                    $importo = floatval($extra['importo'] ?? 0);
                    if ($importo == 0.0) {
                        continue;
                    }
                    $extra_cost_total += $importo;
                    $extra_items[] = array(
                        'label' => $nome_extra,
                        'amount' => $importo,
                    );
                }
            }

            if (empty($extra_items) && !empty($persona['costi_extra']) && is_array($persona['costi_extra'])) {
                if ($package_id_reference && $extra_config_cache === null) {
                    $extra_config_cache = get_post_meta($package_id_reference, 'btr_costi_extra', true);
                }
                if (is_array($extra_config_cache)) {
                    foreach ($persona['costi_extra'] as $slug => $selected) {
                        if (!$selected) {
                            continue;
                        }
                        $found_config = null;
                        foreach ($extra_config_cache as $config) {
                            if (($config['slug'] ?? '') === $slug || sanitize_title($config['nome'] ?? '') === $slug) {
                                $found_config = $config;
                                break;
                            }
                        }
                        if (!$found_config) {
                            continue;
                        }
                        $nome_extra = $found_config['nome'] ?? ucfirst(str_replace('-', ' ', (string)$slug));
                        $importo = floatval($found_config['importo'] ?? 0);
                        if ($importo == 0.0) {
                            continue;
                        }
                        $extra_cost_total += $importo;
                        $extra_items[] = array(
                            'label' => $nome_extra,
                            'amount' => $importo,
                        );
                    }
                }
            }

            $participants_cards[] = array(
                'index' => $participant_counter,
                'name' => $full_name,
                'type' => $type_label,
                'room' => $room_label,
                'insurances' => $insurance_items,
                'extras' => $extra_items,
            );
        }
    }

    // Prepare category cards for pricing breakdown
    $category_cards = array();
    if (!empty($categorie_da_mostrare) && is_array($categorie_da_mostrare)) {
        foreach ($categorie_da_mostrare as $categoria => $dati) {
            $categoria_label = ucwords(str_replace('_', ' ', (string)$categoria));
            $lines = array();

            $count = intval($dati['count'] ?? 0);
            if (!empty($dati['totale_prezzo'])) {
                $lines[] = array(
                    'label' => __('Prezzo base', 'born-to-ride-booking'),
                    'value' => $dati['totale_prezzo'],
                );
            }
            if (!empty($dati['totale_supplemento'])) {
                $supp_label = $tipo_camera_predominante
                    ? btr_get_supplemento_base_label($tipo_camera_predominante)
                    : __('Supplemento', 'born-to-ride-booking');
                $lines[] = array(
                    'label' => $supp_label,
                    'value' => $dati['totale_supplemento'],
                );
            }
            if (!empty($dati['totale_notte_extra'])) {
                $supp_extra_label = $tipo_camera_predominante
                    ? btr_get_supplemento_notti_extra_label($numero_notti_extra, $tipo_camera_predominante)
                    : __('Notti extra', 'born-to-ride-booking');
                $lines[] = array(
                    'label' => $supp_extra_label,
                    'value' => $dati['totale_notte_extra'],
                );
            }
            if (!empty($dati['totale_supplemento_extra'])) {
                $lines[] = array(
                    'label' => __('Supplementi extra', 'born-to-ride-booking'),
                    'value' => $dati['totale_supplemento_extra'],
                );
            }

            $category_cards[] = array(
                'label' => $categoria_label,
                'count' => $count,
                'lines' => $lines,
            );
        }
    }

    // Compute totals for summary
    $totale_camere_checkout = $cart_subtotal;
    if (!empty($riepilogo_dettagliato['totali']) && is_array($riepilogo_dettagliato['totali'])) {
        $totale_camere_checkout = floatval($riepilogo_dettagliato['totali']['subtotale_prezzi_base'] ?? 0)
            + floatval($riepilogo_dettagliato['totali']['subtotale_supplementi_base'] ?? 0)
            + floatval($riepilogo_dettagliato['totali']['subtotale_notti_extra'] ?? 0)
            + floatval($riepilogo_dettagliato['totali']['subtotale_supplementi_extra'] ?? 0);
    }
    if ($calculator_totals_valid) {
        $totale_camere_checkout = $calculator_base_total + $calculator_extra_nights_total;
    }

    if ($calculator_totals_valid) {
        $totale_assicurazioni_display = $calculator_insurance_total;
        $totale_extra_display = $calculator_extra_costs_total;
    } elseif (function_exists('btr_price_calculator') && !empty($anagrafici_data)) {
        $price_calculator = btr_price_calculator();
        $costi_extra_durata = $preventivo_id ? get_post_meta($preventivo_id, '_costi_extra_durata', true) : array();
        $extra_costs_result = $price_calculator->calculate_extra_costs($anagrafici_data, $costi_extra_durata);
        $totale_extra_display = floatval($extra_costs_result['totale'] ?? 0);
        $totale_assicurazioni_display = $cart_insurance_total;
    } else {
        $totale_assicurazioni_display = $cart_insurance_total;
        $totale_extra_display = $cart_extra_total;
    }

    if ($calculator_totals_valid) {
        $totale_finale_checkout = $calculator_total_final;
        $cart_insurance_total = $calculator_insurance_total;
        $cart_extra_total = $calculator_extra_costs_total;
    } else {
        $totale_finale_checkout = $totale_camere_checkout + $cart_insurance_total + $cart_extra_total;
    }
    if ($totale_finale_checkout <= 0) {
        $totale_finale_checkout = $total_from_preventivo;
    }

    // BTR: garantisci che il totale finale non sia inferiore al totale preventivo consolidato
    if (isset($total_from_preventivo) && $total_from_preventivo > 0 && $totale_finale_checkout < $total_from_preventivo) {
        $totale_finale_checkout = $total_from_preventivo;
    }

    $summary_totals_rows = array();
    $summary_totals_rows[] = array(
        'label' => __('Camere', 'born-to-ride-booking'),
        'amount' => $totale_camere_checkout,
        'variant' => 'base',
    );
    if ($totale_assicurazioni_display > 0) {
        $summary_totals_rows[] = array(
            'label' => __('Assicurazioni', 'born-to-ride-booking'),
            'amount' => $totale_assicurazioni_display,
            'variant' => 'add',
        );
    }
    if ($totale_extra_display != 0) {
        $summary_totals_rows[] = array(
            'label' => $totale_extra_display > 0
                ? __('Costi extra', 'born-to-ride-booking')
                : __('Sconti/Riduzioni', 'born-to-ride-booking'),
            'amount' => $totale_extra_display,
            'variant' => $totale_extra_display > 0 ? 'add' : 'discount',
        );
    }

    $display_cart_total = $totale_finale_checkout;
    if ($cart_calculated_total > 0) {
        $display_cart_total = max($totale_finale_checkout, $cart_calculated_total);
    }

    $final_total_caption = __('Totale da pagare', 'born-to-ride-booking');
    if ($cart_calculated_total > 0 && abs($display_cart_total - $cart_calculated_total) <= 0.01) {
        $final_total_subline = __('Importo aggiornato dal carrello.', 'born-to-ride-booking');
    } elseif ($totals_source === 'preventivo' && abs($display_cart_total - $totale_finale_checkout) <= 0.01) {
        $final_total_subline = __('Allineato al preventivo confermato.', 'born-to-ride-booking');
    } else {
        $final_total_subline = __('Calcolo combinato (valore più alto).', 'born-to-ride-booking');
    }

    $discrepancy_notice = '';
    if ($discrepancy > 0.01) {
        $discrepancy_notice = sprintf(
            __('Nota: rilevata una differenza di %s tra preventivo e carrello. Il sistema mostra il valore più aggiornato.', 'born-to-ride-booking'),
            wc_price($discrepancy)
        );
    }

    // Checkout adjustments (shipping, coupons, fees, taxes)
    $adjustment_lines = array();
    if (WC()->cart->needs_shipping() && WC()->cart->show_shipping()) {
        $adjustment_lines[] = array(
            'label' => __('Spedizione', 'woocommerce'),
            'value' => WC()->cart->get_cart_shipping_total(),
        );
    }
    foreach (WC()->cart->get_coupons() as $code => $coupon) {
        $adjustment_lines[] = array(
            'label' => sprintf(__('Sconto: %s', 'woocommerce'), wc_cart_totals_coupon_label($coupon)),
            'value' => wc_cart_totals_coupon_html($coupon),
        );
    }
    foreach (WC()->cart->get_fees() as $fee) {
        $adjustment_lines[] = array(
            'label' => $fee->name,
            'value' => wc_price($fee->total),
        );
    }
    if (wc_tax_enabled() && !WC()->cart->display_prices_including_tax()) {
        foreach (WC()->cart->get_tax_totals() as $tax) {
            $adjustment_lines[] = array(
                'label' => $tax->label,
                'value' => wp_kses_post($tax->formatted_amount),
            );
        }
    }

    // Fallback summary when preventivo data is missing
    $fallback_summary = array();
    if ((!$preventivo_id || empty($preventivo_data)) && $cart) {
        $fallback_insurance = 0;
        $fallback_extra = 0;
        $fallback_rooms = 0;
        foreach ($cart->get_cart() as $cart_item) {
            if (isset($cart_item['from_anagrafica']) && isset($cart_item['custom_price'])) {
                $fallback_insurance += floatval($cart_item['custom_price']) * intval($cart_item['quantity']);
            } elseif (isset($cart_item['from_extra']) && isset($cart_item['custom_price'])) {
                $fallback_extra += floatval($cart_item['custom_price']) * intval($cart_item['quantity']);
            } else {
                $product = $cart_item['data'];
                if ($product) {
                    $fallback_rooms += $product->get_price() * $cart_item['quantity'];
                }
            }
        }
        $fallback_total = WC()->cart->get_total('raw');
        if ($fallback_total <= 0) {
            $fallback_total = $fallback_rooms + $fallback_insurance + $fallback_extra;
        }
        $fallback_summary = array(
            'rooms' => $fallback_rooms,
            'insurance' => $fallback_insurance,
            'extra' => $fallback_extra,
            'total' => $fallback_total,
        );
    }

?>
    <div class="wp-block-btr-checkout-summary">
        <div class="btr-summary-card">
            <?php if (!empty($package_name) || !empty($package_summary_items)) : ?>
                <div class="btr-summary-hero">
                    <div class="btr-summary-hero__info">
                        <?php if (!empty($package_name)) : ?>
                            <h3 class="btr-summary-hero__title"><?php echo esc_html($package_name); ?></h3>
                        <?php endif; ?>
                        <?php if (!empty($package_summary_items)) : ?>
                            <dl class="btr-summary-hero__meta">
                                <?php foreach ($package_summary_items as $item) : ?>
                                    <div class="btr-summary-hero__meta-item">
                                        <dt class="btr-summary-hero__meta-label"><?php echo esc_html($item['label']); ?></dt>
                                        <dd class="btr-summary-hero__meta-value"><?php echo esc_html($item['value']); ?></dd>
                                    </div>
                                <?php endforeach; ?>
                            </dl>
                        <?php endif; ?>
                    </div>
                    <div class="btr-summary-figure">
                        <span class="btr-summary-figure__label"><?php echo esc_html($final_total_caption); ?></span>
                        <span class="btr-summary-figure__amount"><?php echo wp_kses_post(wc_price($display_cart_total)); ?></span>
                        <span class="btr-summary-figure__sub"><?php echo esc_html($final_total_subline); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($discrepancy_notice) : ?>
                <div class="btr-summary-notice"><?php echo esc_html($discrepancy_notice); ?></div>
            <?php endif; ?>

            <?php if (!empty($category_cards)) : ?>
                <section class="btr-summary-section">
                    <div class="btr-section-header">
                        <h4><?php esc_html_e('Riepilogo per categoria', 'born-to-ride-booking'); ?></h4>
                        <p><?php esc_html_e('Valori totali per ciascuna tipologia di partecipante.', 'born-to-ride-booking'); ?></p>
                    </div>
                    <div class="btr-category-grid">
                        <?php foreach ($category_cards as $card) : ?>
                            <article class="btr-category-card">
                                <header class="btr-category-card__header">
                                    <span class="btr-category-card__title"><?php echo esc_html($card['label']); ?></span>
                                    <?php if (!empty($card['count'])) : ?>
                                        <span class="btr-category-card__count"><?php echo esc_html(sprintf(_n('%d partecipante', '%d partecipanti', $card['count'], 'born-to-ride-booking'), $card['count'])); ?></span>
                                    <?php endif; ?>
                                </header>
                                <?php if (!empty($card['lines'])) : ?>
                                    <ul class="btr-category-card__list">
                                        <?php foreach ($card['lines'] as $line) : ?>
                                            <li>
                                                <span><?php echo esc_html($line['label']); ?></span>
                                                <strong><?php echo wp_kses_post(wc_price($line['value'])); ?></strong>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (!empty($participants_cards)) : ?>
                <section class="btr-summary-section">
                    <div class="btr-section-header">
                        <h4><?php esc_html_e('Partecipanti', 'born-to-ride-booking'); ?></h4>
                        <p><?php esc_html_e('Controlla assicurazioni e servizi collegati a ogni persona.', 'born-to-ride-booking'); ?></p>
                    </div>
                    <div class="btr-participants-grid">
                        <?php foreach ($participants_cards as $card) : ?>
                            <article class="btr-participant-card">
                                <header class="btr-participant-card__header">
                                    <span class="btr-participant-card__index"><?php echo esc_html($card['index']); ?></span>
                                    <div class="btr-participant-card__identity">
                                        <span class="btr-participant-card__name"><?php echo esc_html($card['name']); ?></span>
                                        <span class="btr-participant-card__tag"><?php echo esc_html($card['type']); ?></span>
                                    </div>
                                    <?php if (!empty($card['room'])) : ?>
                                        <span class="btr-participant-card__room"><?php echo esc_html($card['room']); ?></span>
                                    <?php endif; ?>
                                </header>

                                <?php if (!empty($card['insurances'])) : ?>
                                    <div class="btr-participant-card__chips">
                                        <?php foreach ($card['insurances'] as $item) : ?>
                                            <?php $formatted_amount = wc_price($item['amount']); ?>
                                            <span class="btr-chip btr-chip--insurance">
                                                <span><?php echo esc_html($item['label']); ?></span>
                                                <strong>
                                                    <span class="btr-amount-prefix">+</span>
                                                    <?php echo wp_kses_post($formatted_amount); ?>
                                                </strong>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($card['extras'])) : ?>
                                    <div class="btr-participant-card__chips">
                                        <?php foreach ($card['extras'] as $item) : ?>
                                            <?php
                                            $amount = floatval($item['amount']);
                                            $chip_class = $amount < 0 ? ' btr-chip--discount' : ' btr-chip--extra';
                                            $formatted_amount = wc_price(abs($amount));
                                            $prefix = $amount > 0 ? '+' : '-';
                                            ?>
                                            <span class="btr-chip<?php echo esc_attr($chip_class); ?>">
                                                <span><?php echo esc_html($item['label']); ?></span>
                                                <strong>
                                                    <span class="btr-amount-prefix"><?php echo esc_html($prefix); ?></span>
                                                    <?php echo wp_kses_post($formatted_amount); ?>
                                                </strong>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (!empty($summary_totals_rows)) : ?>
                <section class="btr-summary-section btr-summary-section--totals">
                    <div class="btr-section-header">
                        <h4><?php esc_html_e('Riepilogo economico', 'born-to-ride-booking'); ?></h4>
                    </div>
                    <ul class="btr-summary-totals">
                        <?php foreach ($summary_totals_rows as $row) : ?>
                            <?php
                            $variant_class = 'btr-summary-totals__row';
                            if (!empty($row['variant'])) {
                                $variant_class .= ' btr-summary-totals__row--' . esc_attr($row['variant']);
                            }
                            $amount = floatval($row['amount']);
                            $formatted = wc_price(abs($amount));
                            $prefix = '';
                            if ($row['variant'] === 'add' && $amount > 0) {
                                $prefix = '+';
                            } elseif ($row['variant'] === 'discount' && $amount != 0) {
                                $prefix = '-';
                            }
                            ?>
                            <li class="<?php echo $variant_class; ?>">
                                <span><?php echo esc_html($row['label']); ?></span>
                                <strong>
                                    <?php if ($prefix) : ?>
                                        <span class="btr-amount-prefix"><?php echo esc_html($prefix); ?></span>
                                    <?php endif; ?>
                                    <?php echo wp_kses_post($formatted); ?>
                                </strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="btr-summary-total">
                        <span><?php esc_html_e('Totale da pagare', 'born-to-ride-booking'); ?></span>
                        <strong><?php echo wp_kses_post(wc_price($display_cart_total)); ?></strong>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (empty($participants_cards) && empty($category_cards) && !empty($fallback_summary)) : ?>
                <section class="btr-summary-section">
                    <div class="btr-section-header">
                        <h4><?php esc_html_e('Riepilogo carrello', 'born-to-ride-booking'); ?></h4>
                        <p><?php esc_html_e('Non sono disponibili dati di preventivo: valori calcolati dal carrello.', 'born-to-ride-booking'); ?></p>
                    </div>
                    <ul class="btr-summary-totals">
                        <li class="btr-summary-totals__row"><span><?php esc_html_e('Camere', 'born-to-ride-booking'); ?></span><strong><?php echo wp_kses_post(wc_price($fallback_summary['rooms'])); ?></strong></li>
                        <?php if ($fallback_summary['insurance'] > 0) : ?>
                            <li class="btr-summary-totals__row btr-summary-totals__row--add"><span><?php esc_html_e('Assicurazioni', 'born-to-ride-booking'); ?></span><strong><?php echo wp_kses_post(wc_price($fallback_summary['insurance'])); ?></strong></li>
                        <?php endif; ?>
                        <?php if ($fallback_summary['extra'] != 0) : ?>
                            <?php
                            $fallback_prefix = $fallback_summary['extra'] > 0 ? '+' : '-';
                            $fallback_formatted_extra = wc_price(abs($fallback_summary['extra']));
                            $fallback_variant = $fallback_summary['extra'] < 0 ? 'btr-summary-totals__row--discount' : 'btr-summary-totals__row--add';
                            ?>
                            <li class="btr-summary-totals__row <?php echo esc_attr($fallback_variant); ?>">
                                <span><?php echo esc_html($fallback_summary['extra'] > 0 ? __('Costi extra', 'born-to-ride-booking') : __('Sconti/Riduzioni', 'born-to-ride-booking')); ?></span>
                                <strong>
                                    <span class="btr-amount-prefix"><?php echo esc_html($fallback_prefix); ?></span>
                                    <?php echo wp_kses_post($fallback_formatted_extra); ?>
                                </strong>
                            </li>
                        <?php endif; ?>
                    </ul>
                    <div class="btr-summary-total">
                        <span><?php esc_html_e('Totale', 'born-to-ride-booking'); ?></span>
                        <strong><?php echo wp_kses_post(wc_price($display_cart_total)); ?></strong>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (!empty($adjustment_lines)) : ?>
                <section class="btr-summary-section btr-summary-section--adjustments">
                    <div class="btr-section-header">
                        <h4><?php esc_html_e('Altri adeguamenti', 'born-to-ride-booking'); ?></h4>
                    </div>
                    <ul class="btr-adjustments-list">
                        <?php foreach ($adjustment_lines as $line) : ?>
                            <li>
                                <span><?php echo esc_html($line['label']); ?></span>
                                <strong><?php echo wp_kses_post($line['value']); ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// La registrazione del blocco è gestita da class-btr-checkout.php
