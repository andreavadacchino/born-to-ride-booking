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
    if ($preventivo_id) {
        // Prima prova dalla sessione
        $session_data = WC()->session->get('btr_anagrafici_data', []);
        
        // Se la sessione ha dati validi, usali
        if (!empty($session_data) && is_array($session_data)) {
            $anagrafici_data = $session_data;
        } else {
            // Altrimenti usa i dati dal preventivo
            $preventivo_data_raw = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
            if (!empty($preventivo_data_raw) && is_array($preventivo_data_raw)) {
                $anagrafici_data = $preventivo_data_raw;
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
        
        // Debug log per verificare i dati
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BTR Checkout Summary - Preventivo ID: ' . $preventivo_id);
            error_log('BTR Checkout Summary - Dati recuperati: ' . print_r($preventivo_data, true));
        }
    }
    ?>
    <div class="wp-block-btr-checkout-summary">
        <div class="btr-summary-content">
            
            <?php // Mostra nome pacchetto e dettagli ?>
            <?php if (!empty($preventivo_data['nome_pacchetto']) || !empty($preventivo_data['data_partenza'])) : ?>
                <div class="btr-package-info">
                    <?php if (!empty($preventivo_data['nome_pacchetto'])) : ?>
                        <h3 class="btr-package-name"><?php echo esc_html($preventivo_data['nome_pacchetto']); ?></h3>
                    <?php endif; ?>
                    
                    <div class="btr-package-details">
                        <?php 
                        // Data del pacchetto principale
                        if (!empty($preventivo_data['data_pacchetto'])) {
                            // Usa _data_pacchetto se disponibile (formato: "24 - 25 Gennaio 2026")
                            echo '<div class="btr-detail-line"><span class="btr-detail-label">Date pacchetto:</span> ' . 
                                 esc_html($preventivo_data['data_pacchetto']) . '</div>';
                        } elseif (!empty($preventivo_data['date_ranges'])) {
                            // Altrimenti usa _date_ranges (formato stringa: "24 - 25 Gennaio 2026")
                            echo '<div class="btr-detail-line"><span class="btr-detail-label">Date pacchetto:</span> ' . 
                                 esc_html($preventivo_data['date_ranges']) . '</div>';
                        } elseif (!empty($preventivo_data['data_partenza'])) {
                            // Fallback alla data partenza se nient'altro è disponibile
                            $data_formattata = date_i18n('d F Y', strtotime($preventivo_data['data_partenza']));
                            echo '<div class="btr-detail-line"><span class="btr-detail-label">Data partenza:</span> ' . 
                                 esc_html($data_formattata) . '</div>';
                        }
                        
                        // Durata
                        if (!empty($preventivo_data['durata'])) {
                            echo '<div class="btr-detail-line"><span class="btr-detail-label">Durata:</span> ' . 
                                 esc_html($preventivo_data['durata']) . '</div>';
                        }
                        
                        // Camere selezionate
                        if (!empty($preventivo_data['camere_selezionate']) && is_array($preventivo_data['camere_selezionate'])) {
                            $tipi_camere = [];
                            foreach ($preventivo_data['camere_selezionate'] as $camera) {
                                // La struttura usa 'tipo' invece di 'tipo_camera'
                                $tipo_camera = $camera['tipo'] ?? $camera['tipo_camera'] ?? '';
                                if (!empty($tipo_camera)) {
                                    // Ottieni il numero di persone dal campo capacity o calcolalo
                                    $num_adulti = isset($all_meta['_num_adults'][0]) ? intval($all_meta['_num_adults'][0]) : 0;
                                    $num_bambini = isset($all_meta['_num_children'][0]) ? intval($all_meta['_num_children'][0]) : 0;
                                    $num_neonati = isset($all_meta['_num_neonati'][0]) ? intval($all_meta['_num_neonati'][0]) : 0;
                                    
                                    $occupanti = [];
                                    if ($num_adulti > 0) $occupanti[] = $num_adulti . ' ' . ($num_adulti > 1 ? 'adulti' : 'adulto');
                                    if ($num_bambini > 0) $occupanti[] = $num_bambini . ' ' . ($num_bambini > 1 ? 'bambini' : 'bambino');
                                    if ($num_neonati > 0) $occupanti[] = $num_neonati . ' ' . ($num_neonati > 1 ? 'neonati' : 'neonato');
                                    
                                    $desc_camera = $tipo_camera;
                                    if (!empty($occupanti)) {
                                        $desc_camera .= ' (' . implode(', ', $occupanti) . ')';
                                    }
                                    
                                    $tipi_camere[] = $desc_camera;
                                }
                            }
                            
                            if (!empty($tipi_camere)) {
                                echo '<div class="btr-detail-line"><span class="btr-detail-label">Camere:</span> ' . 
                                     esc_html(implode(', ', $tipi_camere)) . '</div>';
                            }
                        }
                        
                        // Notti extra
                        if (!empty($preventivo_data['btr_extra_night_date']) && is_array($preventivo_data['btr_extra_night_date'])) {
                            $date_extra = [];
                            foreach ($preventivo_data['btr_extra_night_date'] as $extra_date_string) {
                                if (!empty($extra_date_string)) {
                                    // Le date possono essere separate da virgola (es. "2026-01-22, 2026-01-23")
                                    $dates = explode(',', $extra_date_string);
                                    foreach ($dates as $date) {
                                        $date = trim($date);
                                        if (!empty($date)) {
                                            $date_extra[] = date_i18n('d F', strtotime($date));
                                        }
                                    }
                                }
                            }
                            
                            if (!empty($date_extra)) {
                                $num_notti = count($date_extra);
                                echo '<div class="btr-detail-line"><span class="btr-detail-label">Notti extra:</span> ' . 
                                     $num_notti . ' ' . ($num_notti > 1 ? 'notti' : 'notte') . 
                                     ' (' . esc_html(implode(', ', $date_extra)) . ')</div>';
                            }
                        } elseif ($preventivo_data['numero_notti_extra'] > 0) {
                            // Fallback se non abbiamo le date specifiche
                            echo '<div class="btr-detail-line"><span class="btr-detail-label">Notti extra:</span> ' . 
                                 $preventivo_data['numero_notti_extra'] . ' ' . 
                                 ($preventivo_data['numero_notti_extra'] > 1 ? 'notti' : 'notte') . '</div>';
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php // Mostra i dati anagrafici organizzati con costi sotto ogni partecipante ?>
            <?php if (!empty($anagrafici_data) && is_array($anagrafici_data)) : ?>
                <div class="btr-summary-anagrafici">
                    <h4><?php esc_html_e('Partecipanti', 'born-to-ride-booking'); ?></h4>
                    <div class="btr-participants-list">
                        <?php 
                        $participant_counter = 0;
                        foreach ($anagrafici_data as $index => $persona) : ?>
                            <?php 
                            // Filtra neonati phantom e partecipanti senza dati validi
                            $is_neonato = ($persona['tipo_persona'] ?? '') === 'neonato' || 
                                         ($persona['camera_tipo'] ?? '') === 'Culla per Neonati';
                            $has_valid_name = !empty(trim($persona['nome'] ?? '')) && !empty(trim($persona['cognome'] ?? ''));
                            
                            // Controllo finale per partecipanti validi
                            $nome_lower = strtolower(trim($persona['nome'] ?? ''));
                            $cognome_lower = strtolower(trim($persona['cognome'] ?? ''));
                            
                            // Skip solo se entrambi sono vuoti (sicurezza)
                            if (empty($nome_lower) && empty($cognome_lower)) {
                                if (defined('WP_DEBUG') && WP_DEBUG) {
                                    error_log('BTR - SKIP partecipante vuoto nel display');
                                }
                                continue;
                            }
                            
                            $participant_counter++;
                            
                            // Debug output per sviluppo
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                error_log('BTR Debug - Partecipante ' . $participant_counter . ' (' . ($persona['nome'] ?? 'N/A') . '):');
                                error_log('  - Assicurazioni base: ' . print_r($persona['assicurazioni'] ?? [], true));
                                error_log('  - Costi extra base: ' . print_r($persona['costi_extra'] ?? [], true));
                                error_log('  - Ha assicurazioni_dettagliate: ' . (!empty($persona['assicurazioni_dettagliate']) ? 'SÌ' : 'NO'));
                                error_log('  - Ha costi_extra_dettagliate: ' . (!empty($persona['costi_extra_dettagliate']) ? 'SÌ' : 'NO'));
                            }
                            ?>
                                <div class="btr-participant-item">
                                    <div class="btr-participant-header">
                                        <span class="btr-participant-number"><?php echo $participant_counter; ?>.</span>
                                        <span class="btr-participant-name">
                                            <strong><?php echo esc_html($persona['nome'] ?? '') . ' ' . esc_html($persona['cognome'] ?? ''); ?></strong>
                                            <?php 
                                            // Mostra il tipo di partecipante
                                            $tipo_persona_display = $persona['tipo_persona'] ?? '';
                                            $fascia = $persona['fascia'] ?? '';
                                            
                                            // Determina l'etichetta da mostrare
                                            if ($tipo_persona_display === 'neonato' || $fascia === 'neonato') {
                                                echo ' <em>(Neonato)</em>';
                                            } elseif ($tipo_persona_display === 'bambino' || !empty($fascia)) {
                                                // v1.0.182 - Usa etichette dinamiche, NO hardcoded
                                                if (!empty($fascia) && function_exists('btr_get_child_label')) {
                                                    $child_label = btr_get_child_label($fascia, $preventivo_id);
                                                    echo ' <em>(' . esc_html($child_label) . ')</em>';
                                                } else {
                                                    echo ' <em>(Bambino)</em>';
                                                }
                                            } else {
                                                echo ' <em>(Adulto)</em>';
                                            }
                                            ?>
                                        </span>
                                        <?php if (!empty($persona['camera_tipo'])) : ?>
                                            <span class="btr-participant-room">
                                                - <?php 
                                                echo esc_html($persona['camera_tipo']);
                                                if (!empty($persona['camera']) && $persona['camera'] !== 'neonato-no-room') {
                                                    echo ' (' . esc_html($persona['camera']) . ')';
                                                }
                                                ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php 
                                    // Debug per verificare struttura dati assicurazioni
                                    if (defined('WP_DEBUG') && WP_DEBUG && !empty($persona['assicurazioni_dettagliate'])) {
                                        error_log('BTR Debug - Assicurazioni per ' . ($persona['nome'] ?? 'N/A') . ': ' . print_r($persona['assicurazioni_dettagliate'], true));
                                    }
                                    
                                    $has_insurance = false;
                                    if (!empty($persona['assicurazioni_dettagliate']) && is_array($persona['assicurazioni_dettagliate'])) {
                                        foreach ($persona['assicurazioni_dettagliate'] as $slug => $assicurazione) {
                                            // Verifica se l'assicurazione è attiva o selezionata
                                            $is_active = !empty($assicurazione['attivo']) || 
                                                        !empty($assicurazione['selezionata']) ||
                                                        (!isset($assicurazione['attivo']) && !empty($assicurazione['descrizione']) && !empty($assicurazione['importo']));
                                            
                                            if ($is_active) {
                                                if (!$has_insurance) {
                                                    echo '<div class="btr-participant-insurances">';
                                                    $has_insurance = true;
                                                }
                                                
                                                $descrizione = $assicurazione['descrizione'] ?? $assicurazione['nome'] ?? ucfirst(str_replace('-', ' ', $slug));
                                                $importo = floatval($assicurazione['importo'] ?? 0);
                                                
                                                if ($importo > 0) {
                                                    echo '<div class="btr-cost-item btr-insurance-item">';
                                                    echo '<span class="btr-cost-label">🛡️ ' . esc_html($descrizione) . '</span>';
                                                    echo '<span class="btr-cost-amount">+' . wc_price($importo) . '</span>';
                                                    echo '</div>';
                                                    
                                                    // Accumula il totale assicurazioni
                                                    $insurance_total += $importo;
                                                }
                                            }
                                        }
                                        if ($has_insurance) {
                                            echo '</div>';
                                        }
                                    }
                                    
                                    // FALLBACK: Se non ci sono assicurazioni dettagliate ma ci sono selections base
                                    if (!$has_insurance && !empty($persona['assicurazioni']) && is_array($persona['assicurazioni'])) {
                                        $package_id = $preventivo_id ? get_post_meta($preventivo_id, '_pacchetto_id', true) : 0;
                                        if ($package_id) {
                                            $assicurazioni_config = get_post_meta($package_id, 'btr_assicurazione_importi', true);
                                            if (is_array($assicurazioni_config)) {
                                                foreach ($persona['assicurazioni'] as $slug => $selected) {
                                                    if ($selected && !empty($assicurazioni_config[$slug])) {
                                                        if (!$has_insurance) {
                                                            echo '<div class="btr-participant-insurances">';
                                                            $has_insurance = true;
                                                        }
                                                        
                                                        $config = $assicurazioni_config[$slug];
                                                        $nome = $config['nome'] ?? $config['descrizione'] ?? ucfirst(str_replace('-', ' ', $slug));
                                                        $importo = floatval($config['importo'] ?? 0);
                                                        
                                                        if ($importo > 0) {
                                                            echo '<div class="btr-cost-item btr-insurance-item">';
                                                            echo '<span class="btr-cost-label">🛡️ ' . esc_html($nome) . '</span>';
                                                            echo '<span class="btr-cost-amount">+' . wc_price($importo) . '</span>';
                                                            echo '</div>';
                                                        }
                                                    }
                                                }
                                                if ($has_insurance) {
                                                    echo '</div>';
                                                }
                                            }
                                        }
                                    }
                                    ?>
                                    
                                    <?php 
                                    // Debug per verificare struttura dati costi extra
                                    if (defined('WP_DEBUG') && WP_DEBUG && !empty($persona['costi_extra_dettagliate'])) {
                                        error_log('BTR Debug - Costi extra per ' . ($persona['nome'] ?? 'N/A') . ': ' . print_r($persona['costi_extra_dettagliate'], true));
                                    }
                                    
                                    $has_extras = false;
                                    if (!empty($persona['costi_extra_dettagliate']) && is_array($persona['costi_extra_dettagliate'])) {
                                        foreach ($persona['costi_extra_dettagliate'] as $slug => $extra) {
                                            // Verifica se il costo extra è attivo o selezionato
                                            $is_active = !empty($extra['attivo']) || 
                                                        !empty($extra['selezionato']) ||
                                                        (!isset($extra['attivo']) && !empty($extra['nome']) && isset($extra['importo']));
                                            
                                            if ($is_active) {
                                                if (!$has_extras) {
                                                    echo '<div class="btr-participant-extras">';
                                                    $has_extras = true;
                                                }
                                                
                                                $nome = $extra['nome'] ?? $extra['descrizione'] ?? ucfirst(str_replace('-', ' ', $slug));
                                                $importo = floatval($extra['importo'] ?? 0);
                                                
                                                if ($importo != 0) {
                                                    $color = $importo < 0 ? 'color: #d32f2f;' : 'color: #2e7d32;';
                                                    echo '<div class="btr-cost-item btr-extra-item">';
                                                    echo '<span class="btr-cost-label">⚡ ' . esc_html($nome) . '</span>';
                                                    echo '<span class="btr-cost-amount"><span style="' . $color . '">';
                                                    echo ($importo > 0 ? '+' : '') . wc_price($importo);
                                                    echo '</span></span>';
                                                    echo '</div>';
                                                }
                                            }
                                        }
                                        if ($has_extras) {
                                            echo '</div>';
                                        }
                                    }
                                    
                                    // FALLBACK: Se non ci sono costi extra dettagliati ma ci sono selections base
                                    if (!$has_extras && !empty($persona['costi_extra']) && is_array($persona['costi_extra'])) {
                                        $package_id = $preventivo_id ? get_post_meta($preventivo_id, '_pacchetto_id', true) : 0;
                                        if ($package_id) {
                                            $costi_extra_config = get_post_meta($package_id, 'btr_costi_extra', true);
                                            if (is_array($costi_extra_config)) {
                                                foreach ($persona['costi_extra'] as $slug => $selected) {
                                                    if ($selected) {
                                                        // Cerca il costo extra nella configurazione
                                                        $found_config = null;
                                                        foreach ($costi_extra_config as $config) {
                                                            if (($config['slug'] ?? '') === $slug || 
                                                                sanitize_title($config['nome'] ?? '') === $slug) {
                                                                $found_config = $config;
                                                                break;
                                                            }
                                                        }
                                                        
                                                        if ($found_config) {
                                                            if (!$has_extras) {
                                                                echo '<div class="btr-participant-extras">';
                                                                $has_extras = true;
                                                            }
                                                            
                                                            $nome = $found_config['nome'] ?? ucfirst(str_replace('-', ' ', $slug));
                                                            $importo = floatval($found_config['importo'] ?? 0);
                                                            
                                                            if ($importo != 0) {
                                                                $color = $importo < 0 ? 'color: #d32f2f;' : 'color: #2e7d32;';
                                                                echo '<div class="btr-cost-item btr-extra-item">';
                                                                echo '<span class="btr-cost-label">⚡ ' . esc_html($nome) . '</span>';
                                                                echo '<span class="btr-cost-amount"><span style="' . $color . '">';
                                                                echo ($importo > 0 ? '+' : '') . wc_price($importo);
                                                                echo '</span></span>';
                                                                echo '</div>';
                                                            }
                                                        }
                                                    }
                                                }
                                                if ($has_extras) {
                                                    echo '</div>';
                                                }
                                            }
                                        }
                                    }
                                    ?>
                                </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php
            
            // Calcola i totali reali dal carrello con breakdown dettagliato
            $cart_subtotal = 0;
            $cart_insurance_total = 0;
            $cart_extra_total = 0;
            $cart_base_total = 0;
            $cart_supplement_total = 0;
            $cart_extra_nights_total = 0;
            
            // CORREZIONE 2025-01-20: Calcola prima i totali dal carrello
            foreach ($cart->get_cart() as $cart_item) {
                if (isset($cart_item['from_anagrafica']) && isset($cart_item['custom_price'])) {
                    $cart_insurance_total += floatval($cart_item['custom_price']) * intval($cart_item['quantity']);
                } elseif (isset($cart_item['from_extra']) && isset($cart_item['custom_price'])) {
                    // Include valori negativi per sconti/riduzioni
                    $cart_extra_total += floatval($cart_item['custom_price']) * intval($cart_item['quantity']);
                } else {
                    // Per camere, calcola il breakdown
                    $product = $cart_item['data'];
                    $item_price = $product->get_price() * $cart_item['quantity'];
                    $cart_subtotal += $item_price;
                    
                    // Se abbiamo i dati dettagliati, calcoliamo il breakdown
                    if (isset($cart_item['extra_night_pp']) && isset($cart_item['number_of_persons'])) {
                        $num_persons = intval($cart_item['number_of_persons']);
                        $extra_night_pp = floatval($cart_item['extra_night_pp']);
                        $cart_extra_nights_total += $extra_night_pp * $num_persons * $cart_item['quantity'];
                    }
                }
            }
            
            // CORREZIONE 2025-01-20: Usa btr_price_calculator per calcolo coerente con pagina preventivo
            if ($preventivo_id) {
                $anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
                $costi_extra_durata = get_post_meta($preventivo_id, '_costi_extra_durata', true);
                
                // CORREZIONE 2025-01-20: Non sovrascrivere il totale dei costi extra dal carrello
                // Il totale corretto viene già calcolato dai cart items con flag 'from_extra'
                /*
                $price_calculator = btr_price_calculator();
                $extra_costs_result = $price_calculator->calculate_extra_costs($anagrafici, $costi_extra_durata);
                
                // Usa il totale netto (include aggiunte e riduzioni)
                $cart_extra_total = $extra_costs_result['totale'];
                */
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('BTR Checkout Summary - Costi extra dal carrello: €' . $cart_extra_total);
                }
            }
            
            // Usa i totali del preventivo se disponibili per maggiore accuratezza
            if ($preventivo_id && !empty($preventivo_data['extra_night_cost'])) {
                $cart_extra_nights_total = floatval($preventivo_data['extra_night_cost']);
            }
            
            // Se non abbiamo le notti extra ma c'è differenza tra subtotale e prezzo base
            // assumiamo che la differenza siano le notti extra (per retrocompatibilità)
            if ($cart_extra_nights_total == 0 && $preventivo_id) {
                $expected_base = floatval($preventivo_data['package_price_no_extra'] ?? 0);
                if ($expected_base > 0 && $cart_subtotal > $expected_base) {
                    $cart_extra_nights_total = $cart_subtotal - $expected_base - $cart_extra_total - $cart_insurance_total;
                }
            }
            
            // Calcola il totale del pacchetto base (senza notti extra)
            // Se abbiamo il valore dal preventivo, usalo per maggiore accuratezza
            if ($preventivo_id && !empty($preventivo_data['package_price_no_extra'])) {
                $cart_base_total = floatval($preventivo_data['package_price_no_extra']);
            } else {
                $cart_base_total = $cart_subtotal - $cart_extra_nights_total;
            }
            
            // Calcola il totale finale accurato usando sempre il preventivo come fonte di verità
            if ($preventivo_id && !empty($preventivo_data['prezzo_totale'])) {
                // Usa il totale del preventivo come base - questo è la fonte di verità
                $total_from_preventivo = floatval($preventivo_data['prezzo_totale']);
                
                // Se abbiamo il riepilogo dettagliato, usa il totale_generale che include le notti extra
                if (!empty($riepilogo_dettagliato['totali']['totale_generale'])) {
                    $total_from_preventivo = floatval($riepilogo_dettagliato['totali']['totale_generale']);
                }
                
                // Calcola il totale dal carrello per confronto
                $cart_calculated_total = WC()->cart->get_total('raw');
                
                // Se c'è discrepanza significativa, registrala nel debug
                $discrepancy = abs($total_from_preventivo - $cart_calculated_total);
                if ($discrepancy > 0.01 && defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('BTR Checkout Summary - ATTENZIONE: Discrepanza nei totali!');
                    error_log('  - Totale Preventivo (fonte di verità): €' . $total_from_preventivo);
                    error_log('  - Totale Carrello WooCommerce: €' . $cart_calculated_total);
                    error_log('  - Discrepanza: €' . $discrepancy);
                    error_log('  - Dettagli breakdown:');
                    error_log('    * Subtotale camere: €' . $cart_subtotal);
                    error_log('    * Assicurazioni: €' . $cart_insurance_total);
                    error_log('    * Costi extra: €' . $cart_extra_total);
                    error_log('    * Notti extra: €' . $cart_extra_nights_total);
                }
                
                // Usa sempre il totale del preventivo come fonte di verità
                $total_finale = $total_from_preventivo;
                
                // Forza l'aggiornamento del carrello se c'è discrepanza
                if ($discrepancy > 0.01 && WC()->cart && !WC()->cart->is_empty()) {
                    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                        // Solo per camere (non extra/assicurazioni)
                        if (!isset($cart_item['from_extra']) && !isset($cart_item['from_anagrafica'])) {
                            if (isset($cart_item['preventivo_id']) && $cart_item['preventivo_id'] == $preventivo_id) {
                                // Aggiorna il prezzo per riflettere il totale corretto
                                WC()->cart->cart_contents[$cart_item_key]['totale_camera'] = $total_finale;
                            }
                        }
                    }
                }
            } else {
                // Fallback: usa il totale del carrello solo se non abbiamo il preventivo
                $cart_total = WC()->cart->get_total('raw');
                if ($cart_total > 0) {
                    $total_finale = $cart_total;
                } else {
                    $total_finale = $cart_subtotal + $cart_insurance_total + $cart_extra_total;
                }
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('BTR Checkout Summary - Usando fallback carrello, preventivo non disponibile');
                }
            }
            
            // Se abbiamo i dati del preventivo, mostra un riepilogo compatto
            if ($preventivo_id && !empty($preventivo_data)) : 
                ?>
                <?php 
                // Mostra avviso se c'è discrepanza nei calcoli
                if (isset($discrepancy) && $discrepancy > 0.01) : ?>
                    <div class="btr-notice btr-notice-info" style="background: #e3f2fd; border-left: 4px solid #2196f3; padding: 12px; margin-bottom: 20px;">
                        <p style="margin: 0;">
                            <strong><?php esc_html_e('Nota:', 'born-to-ride-booking'); ?></strong>
                            <?php esc_html_e('Il totale mostrato è basato sul preventivo originale. Se hai apportato modifiche durante il checkout, il totale finale potrebbe essere aggiornato.', 'born-to-ride-booking'); ?>
                        </p>
                    </div>
                <?php endif; ?>
                
                <?php 
                // Recupera il riepilogo dettagliato se disponibile
                $riepilogo_dettagliato = !empty($preventivo_data['riepilogo_calcoli_dettagliato']) ? 
                    maybe_unserialize($preventivo_data['riepilogo_calcoli_dettagliato']) : null;
                
                // Controlla quale struttura dati è disponibile (supporta entrambe per compatibilità)
                $has_dettaglio_persone = !empty($riepilogo_dettagliato['dettaglio_persone_per_categoria']);
                $has_partecipanti = !empty($riepilogo_dettagliato['partecipanti']);
                
                // Se abbiamo il riepilogo dettagliato in una delle due strutture, mostra il breakdown per categoria
                if (!empty($riepilogo_dettagliato) && ($has_dettaglio_persone || $has_partecipanti)) : 
                    
                    // Recupera anche le date delle notti extra
                    $date_notti_extra = get_post_meta($preventivo_id, '_date_notti_extra', true);
                    $date_extra_formatted = '';
                    if (!empty($date_notti_extra) && is_array($date_notti_extra)) {
                        $date_list = array_map(function($date) {
                            return date_i18n('d/m/Y', strtotime($date));
                        }, $date_notti_extra);
                        $date_extra_formatted = implode(', ', $date_list);
                    }
                    ?>
                    
                    <div class="btr-summary-section btr-summary-detailed">
                        <div class="btr-summary-header">
                            <h3><?php esc_html_e('Riepilogo dettagliato dell\'ordine', 'born-to-ride-booking'); ?></h3>
                            <?php if (!empty($preventivo_data['durata'])) : ?>
                                <span class="btr-summary-duration">
                                    <?php 
                                    echo esc_html($preventivo_data['durata']);
                                    if ($preventivo_data['numero_notti_extra'] > 0) {
                                        echo ' + ' . sprintf(
                                            _n('%d notte extra', '%d notti extra', $preventivo_data['numero_notti_extra'], 'born-to-ride-booking'),
                                            $preventivo_data['numero_notti_extra']
                                        );
                                    }
                                    ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <?php 
                        // Prepara un array unificato per il breakdown, supportando entrambe le strutture
                        $categorie_da_mostrare = [];
                        
                        if ($has_dettaglio_persone) {
                            // Struttura vecchia: dettaglio_persone_per_categoria
                            $categorie_da_mostrare = $riepilogo_dettagliato['dettaglio_persone_per_categoria'];
                        } elseif ($has_partecipanti) {
                            // Struttura nuova: partecipanti - convertiamo al formato vecchio per compatibilità
                            $partecipanti = $riepilogo_dettagliato['partecipanti'];
                            $notti_extra = $riepilogo_dettagliato['notti_extra'] ?? [];
                            
                            // Converti la struttura partecipanti nel formato dettaglio_persone_per_categoria
                            foreach ($partecipanti as $categoria => $dati) {
                                if (!empty($dati['quantita']) && $dati['quantita'] > 0) {
                                    // Mappa i campi dalla struttura nuova a quella vecchia
                                    $categorie_da_mostrare[$categoria] = [
                                        'count' => intval($dati['quantita']),
                                        'prezzo_unitario' => floatval($dati['prezzo_base_unitario'] ?? 0),
                                        'totale_prezzo' => floatval($dati['subtotale_base'] ?? 0),
                                        'supplemento_unitario' => floatval($dati['supplemento_base_unitario'] ?? 0),
                                        'totale_supplemento' => floatval($dati['subtotale_supplemento_base'] ?? 0),
                                        'notte_extra_unitario' => floatval($dati['notte_extra_unitario'] ?? 0),
                                        'totale_notte_extra' => floatval($dati['subtotale_notte_extra'] ?? 0),
                                        'supplemento_extra_unitario' => floatval($dati['supplemento_extra_unitario'] ?? 0),
                                        'totale_supplemento_extra' => floatval($dati['subtotale_supplemento_extra'] ?? 0)
                                    ];
                                }
                            }
                        }
                        
                        
                        // Determina il tipo di camera predominante per le etichette dei supplementi
                        $tipo_camera_predominante = '';
                        if (!empty($preventivo_data['camere_selezionate']) && is_array($preventivo_data['camere_selezionate'])) {
                            // Conta i tipi di camera
                            $tipi_camera_count = [];
                            foreach ($preventivo_data['camere_selezionate'] as $camera) {
                                $tipo = $camera['tipo'] ?? '';
                                $quantita = intval($camera['quantita'] ?? 1);
                                if (!empty($tipo)) {
                                    if (!isset($tipi_camera_count[$tipo])) {
                                        $tipi_camera_count[$tipo] = 0;
                                    }
                                    $tipi_camera_count[$tipo] += $quantita;
                                }
                            }
                            // Trova il tipo più comune
                            if (!empty($tipi_camera_count)) {
                                arsort($tipi_camera_count);
                                $tipo_camera_predominante = key($tipi_camera_count);
                            }
                        }
                        
                        // Recupera il numero di notti extra per le etichette
                        $numero_notti_extra = $preventivo_data['numero_notti_extra'] ?? 1;
                        
                        // Mostra breakdown per categoria
                        foreach ($categorie_da_mostrare as $categoria => $dettaglio) : 
                            if ($dettaglio['count'] > 0) :
                                // Determina il nome della categoria
                                $nome_categoria = '';
                                if ($categoria === 'adulti') {
                                    $nome_categoria = 'Adulti';
                                } elseif ($categoria === 'bambini_f1') {
                                    $nome_categoria = 'Bambini 3-6 anni';
                                } elseif ($categoria === 'bambini_f2') {
                                    $nome_categoria = 'Bambini 6-8 anni';
                                } elseif ($categoria === 'bambini_f3') {
                                    $nome_categoria = 'Bambini 8-10 anni';
                                } elseif ($categoria === 'bambini_f4') {
                                    $nome_categoria = 'Bambini 11-12 anni';
                                } elseif ($categoria === 'neonati') {
                                    $nome_categoria = 'Neonati';
                                }
                        ?>
                            <div class="btr-category-breakdown">
                                <h4><?php echo esc_html($nome_categoria); ?> (<?php echo $dettaglio['count']; ?>)</h4>
                                <ul class="btr-price-details">
                                    <?php if ($dettaglio['prezzo_unitario'] > 0) : ?>
                                        <li>Prezzo pacchetto: <?php echo $dettaglio['count']; ?>×€<?php echo number_format($dettaglio['prezzo_unitario'], 2, ',', '.'); ?> = €<?php echo number_format($dettaglio['totale_prezzo'], 2, ',', '.'); ?></li>
                                    <?php endif; ?>
                                    
                                    <?php if ($dettaglio['supplemento_unitario'] > 0) : ?>
                                        <?php $supplemento_label = btr_get_supplemento_base_label($tipo_camera_predominante); ?>
                                        <li><?php echo esc_html($supplemento_label); ?>: <?php echo $dettaglio['count']; ?>×€<?php echo number_format($dettaglio['supplemento_unitario'], 2, ',', '.'); ?> = €<?php echo number_format($dettaglio['count'] * $dettaglio['supplemento_unitario'], 2, ',', '.'); ?></li>
                                    <?php endif; ?>
                                    
                                    <?php if ($dettaglio['notte_extra_unitario'] > 0) : ?>
                                        <li>Notte extra: <?php echo $dettaglio['count']; ?>×€<?php echo number_format($dettaglio['notte_extra_unitario'], 2, ',', '.'); ?> = €<?php echo number_format($dettaglio['count'] * $dettaglio['notte_extra_unitario'], 2, ',', '.'); ?></li>
                                    <?php endif; ?>
                                    
                                    <?php if ($dettaglio['supplemento_extra_unitario'] > 0) : ?>
                                        <?php $supplemento_notti_label = btr_get_supplemento_notti_extra_label($numero_notti_extra, $tipo_camera_predominante); ?>
                                        <li><?php echo esc_html($supplemento_notti_label); ?>: <?php echo $dettaglio['count']; ?>×€<?php echo number_format($dettaglio['supplemento_extra_unitario'], 2, ',', '.'); ?> = €<?php echo number_format($dettaglio['count'] * $dettaglio['supplemento_extra_unitario'], 2, ',', '.'); ?></li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                        
                        <div class="btr-summary-totals-breakdown">
                            <h4>Riepilogo totali</h4>
                            <ul class="btr-totals-list">
                                <?php 
                                // CORREZIONE 2025-01-20: FORZA sempre il ricalcolo corretto dei totali
                                // Non usare mai i totali pre-aggregati dal riepilogo_dettagliato perché potrebbero essere scorretti
                                $totali = [
                                    'subtotale_prezzi_base' => 0,
                                    'subtotale_supplementi_base' => 0,
                                    'subtotale_notti_extra' => 0,
                                    'subtotale_supplementi_extra' => 0
                                ];
                                
                                if ($has_partecipanti) {
                                    foreach ($partecipanti as $cat => $dati) {
                                        if (!empty($dati['quantita']) && $dati['quantita'] > 0) {
                                            $quantita = intval($dati['quantita']);
                                            
                                            // CORREZIONE 2025-01-20: Ricalcola i totali con la formula matematica corretta
                                            $totali['subtotale_prezzi_base'] += floatval($dati['subtotale_base'] ?? 0);
                                            $totali['subtotale_supplementi_base'] += $quantita * floatval($dati['supplemento_base_unitario'] ?? 0);
                                            $totali['subtotale_notti_extra'] += $quantita * floatval($dati['notte_extra_unitario'] ?? 0);
                                            $totali['subtotale_supplementi_extra'] += $quantita * floatval($dati['supplemento_extra_unitario'] ?? 0);
                                        }
                                    }
                                }
                                ?>
                                
                                <?php if (!empty($totali['subtotale_prezzi_base'])) : ?>
                                    <li>Prezzi base: €<?php echo number_format($totali['subtotale_prezzi_base'], 2, ',', '.'); ?></li>
                                <?php endif; ?>
                                
                                <?php if (!empty($totali['subtotale_supplementi_base'])) : ?>
                                    <li>Supplementi: €<?php echo number_format($totali['subtotale_supplementi_base'], 2, ',', '.'); ?></li>
                                <?php endif; ?>
                                
                                <?php if (!empty($totali['subtotale_notti_extra']) && $totali['subtotale_notti_extra'] > 0) : ?>
                                    <li>Notti extra: €<?php echo number_format($totali['subtotale_notti_extra'], 2, ',', '.'); ?></li>
                                <?php endif; ?>
                                
                                <?php if (!empty($totali['subtotale_supplementi_extra']) && $totali['subtotale_supplementi_extra'] > 0) : ?>
                                    <li>Suppl. extra: €<?php echo number_format($totali['subtotale_supplementi_extra'], 2, ',', '.'); ?></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        
                        <?php 
                        // CORREZIONE 2025-01-20: Mostra correttamente assicurazioni e costi extra usando BTR_Price_Calculator
                        $show_additional_costs = false;
                        $totale_assicurazioni_display = 0;
                        $totale_extra_display = 0;
                        
                        if (function_exists('btr_price_calculator') && !empty($anagrafici_data)) {
                            $price_calculator = btr_price_calculator();
                            $costi_extra_durata = get_post_meta($preventivo_id, '_costi_extra_durata', true);
                            $extra_costs_result = $price_calculator->calculate_extra_costs($anagrafici_data, $costi_extra_durata);
                            
                            $totale_extra_display = $extra_costs_result['totale'] ?? 0;
                            
                            // CORREZIONE 2025-01-20: Calcola assicurazioni SOLO se checkbox selezionata nel form
                            if (is_array($anagrafici_data)) {
                                foreach ($anagrafici_data as $persona) {
                                    if (!empty($persona['assicurazioni_dettagliate'])) {
                                        foreach ($persona['assicurazioni_dettagliate'] as $slug => $ass) {
                                            $importo = isset($ass['importo']) ? (float) $ass['importo'] : 0;
                                            
                                            // CHIAVE: Verifica se la checkbox era selezionata nel form
                                            $checkbox_selected = !empty($persona['assicurazioni'][$slug]) && $persona['assicurazioni'][$slug] == '1';
                                            
                                            // Conta solo se importo > 0 E checkbox selezionata
                                            if ($importo > 0 && $checkbox_selected) {
                                                $totale_assicurazioni_display += $importo;
                                            }
                                        }
                                    }
                                }
                            }
                            
                            $show_additional_costs = ($totale_assicurazioni_display > 0 || $totale_extra_display != 0);
                        } else {
                            // Fallback: usa i valori dal carrello
                            $totale_assicurazioni_display = $cart_insurance_total;
                            $totale_extra_display = $cart_extra_total;
                            $show_additional_costs = ($cart_insurance_total > 0 || $cart_extra_total != 0);
                        }
                        
                        if ($show_additional_costs) : ?>
                            <div class="btr-summary-additional-costs">
                                <?php if ($totale_assicurazioni_display > 0) : ?>
                                    <div class="btr-summary-line">
                                        <span><?php esc_html_e('Totale Assicurazioni', 'born-to-ride-booking'); ?></span>
                                        <span><?php echo wc_price($totale_assicurazioni_display); ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($totale_extra_display != 0) : ?>
                                    <div class="btr-summary-line">
                                        <span>
                                            <?php if($totale_extra_display > 0): ?>
                                                + <?php esc_html_e('Costi Extra', 'born-to-ride-booking'); ?>
                                            <?php else: ?>
                                                <?php esc_html_e('Sconti/Riduzioni', 'born-to-ride-booking'); ?>
                                            <?php endif; ?>
                                        </span>
                                        <span>
                                            <?php if($totale_extra_display > 0): ?>
                                                <?php echo wc_price($totale_extra_display); ?>
                                            <?php else: ?>
                                                -<?php echo wc_price(abs($totale_extra_display)); ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="btr-summary-line btr-summary-total">
                            <strong><?php esc_html_e('TOTALE DA PAGARE', 'born-to-ride-booking'); ?></strong>
                            <strong><?php 
                            // CORREZIONE 2025-01-20: Calcola il totale corretto includendo tutti i costi extra (positivi e negativi)
                            $totale_corretto_finale = 0;
                            
                            // CORREZIONE 2025-01-20: Usa i totali ricalcolati correttamente (variabile $totali già calcolata sopra)
                            $totale_corretto_finale += floatval($totali['subtotale_prezzi_base'] ?? 0);
                            $totale_corretto_finale += floatval($totali['subtotale_supplementi_base'] ?? 0);
                            $totale_corretto_finale += floatval($totali['subtotale_notti_extra'] ?? 0);
                            $totale_corretto_finale += floatval($totali['subtotale_supplementi_extra'] ?? 0);
                            
                            // Usa BTR_Price_Calculator per calcolo corretto dei costi extra (include valori negativi)
                            if (function_exists('btr_price_calculator') && !empty($anagrafici_data)) {
                                $price_calculator = btr_price_calculator();
                                $costi_extra_durata = get_post_meta($preventivo_id, '_costi_extra_durata', true);
                                $extra_costs_result = $price_calculator->calculate_extra_costs($anagrafici_data, $costi_extra_durata);
                                
                                $totale_extra_corretto = $extra_costs_result['totale'] ?? 0; // Include sia aggiunte che riduzioni
                                $totale_assicurazioni_corretto = 0;
                                
                                // CORREZIONE 2025-01-20: Calcola assicurazioni SOLO se checkbox selezionata nel form
                                if (is_array($anagrafici_data)) {
                                    foreach ($anagrafici_data as $persona) {
                                        if (!empty($persona['assicurazioni_dettagliate'])) {
                                            foreach ($persona['assicurazioni_dettagliate'] as $slug => $ass) {
                                                $importo = isset($ass['importo']) ? (float) $ass['importo'] : 0;
                                                
                                                // CHIAVE: Verifica se la checkbox era selezionata nel form
                                                $checkbox_selected = !empty($persona['assicurazioni'][$slug]) && $persona['assicurazioni'][$slug] == '1';
                                                
                                                // Conta solo se importo > 0 E checkbox selezionata
                                                if ($importo > 0 && $checkbox_selected) {
                                                    $totale_assicurazioni_corretto += $importo;
                                                }
                                            }
                                        }
                                    }
                                }
                                
                                $totale_corretto_finale += $totale_assicurazioni_corretto + $totale_extra_corretto;
                                
                                if (defined('WP_DEBUG') && WP_DEBUG) {
                                    error_log('BTR Summary - Calcolo corretto: Camere=€' . ($totale_corretto_finale - $totale_assicurazioni_corretto - $totale_extra_corretto) . ', Assic=€' . $totale_assicurazioni_corretto . ', Extra=€' . $totale_extra_corretto . ', TOTALE=€' . $totale_corretto_finale);
                                }
                            } else {
                                // Fallback: usa i totali dal carrello se BTR_Price_Calculator non disponibile
                                $totale_corretto_finale += $cart_insurance_total + $cart_extra_total;
                            }
                            
                            // Usa il totale calcolato correttamente invece di $total_finale
                            echo wc_price($totale_corretto_finale); 
                            ?></strong>
                        </div>
                    </div>
                    
                <?php else : ?>
                    <!-- Fallback al riepilogo compatto se non abbiamo i dettagli -->
                    <div class="btr-summary-section btr-summary-compact">
                        <div class="btr-summary-header">
                            <h3><?php esc_html_e('Riepilogo dell\'ordine', 'born-to-ride-booking'); ?></h3>
                            <?php if (!empty($preventivo_data['durata'])) : ?>
                                <span class="btr-summary-duration">
                                    <?php 
                                    echo esc_html($preventivo_data['durata']);
                                    if ($preventivo_data['numero_notti_extra'] > 0) {
                                        echo ' + ' . sprintf(
                                            _n('%d notte extra', '%d notti extra', $preventivo_data['numero_notti_extra'], 'born-to-ride-booking'),
                                            $preventivo_data['numero_notti_extra']
                                        );
                                    }
                                    ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="btr-summary-details">
                            <?php 
                            // Calcola il totale camere (base + notti extra) come nel riepilogo preventivo
                            $totale_camere_checkout = 0;
                            
                            // Se abbiamo il riepilogo dettagliato, usa quello per calcolare il totale camere
                            if (!empty($riepilogo_dettagliato['totali'])) {
                                $totale_camere_checkout = floatval($riepilogo_dettagliato['totali']['subtotale_prezzi_base'] ?? 0);
                                $totale_camere_checkout += floatval($riepilogo_dettagliato['totali']['subtotale_supplementi_base'] ?? 0);
                                $totale_camere_checkout += floatval($riepilogo_dettagliato['totali']['subtotale_notti_extra'] ?? 0);
                                $totale_camere_checkout += floatval($riepilogo_dettagliato['totali']['subtotale_supplementi_extra'] ?? 0);
                            } else {
                                // Fallback: usa i dati del carrello
                                $totale_camere_checkout = $cart_subtotal;
                            }
                            ?>
                            
                            <div class="btr-summary-line">
                                <span><?php esc_html_e('Totale Camere', 'born-to-ride-booking'); ?></span>
                                <span><?php echo wc_price($totale_camere_checkout); ?></span>
                            </div>
                            
                            <?php 
                            // Aggiungi totali assicurazioni e costi extra
                            if ($cart_insurance_total > 0 || $cart_extra_total != 0) : ?>
                                <div class="btr-summary-additional-costs">
                                    <?php if ($cart_insurance_total > 0) : ?>
                                        <div class="btr-summary-line">
                                            <span><?php esc_html_e('Totale Assicurazioni', 'born-to-ride-booking'); ?></span>
                                            <span><?php echo wc_price($cart_insurance_total); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($cart_extra_total != 0) : ?>
                                        <div class="btr-summary-line">
                                            <span>
                                                <?php if($cart_extra_total > 0): ?>
                                                    + <?php esc_html_e('Costi Extra', 'born-to-ride-booking'); ?>
                                                <?php else: ?>
                                                    <?php esc_html_e('Sconti/Riduzioni', 'born-to-ride-booking'); ?>
                                                <?php endif; ?>
                                            </span>
                                            <span>
                                                <?php if($cart_extra_total > 0): ?>
                                                    <?php echo wc_price($cart_extra_total); ?>
                                                <?php else: ?>
                                                    -<?php echo wc_price(abs($cart_extra_total)); ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="btr-summary-line btr-summary-total">
                            <strong><?php esc_html_e('TOTALE DA PAGARE', 'born-to-ride-booking'); ?></strong>
                            <strong><?php 
                            // Calcola il totale finale come nel riepilogo preventivo
                            $totale_finale_checkout = $totale_camere_checkout + $cart_insurance_total + $cart_extra_total;
                            
                            // Log per debug
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                error_log('[BTR Checkout] Calcolo totale finale:');
                                error_log('  - Totale camere: €' . $totale_camere_checkout);
                                error_log('  - Totale assicurazioni: €' . $cart_insurance_total);
                                error_log('  - Totale costi extra: €' . $cart_extra_total);
                                error_log('  - TOTALE FINALE: €' . $totale_finale_checkout);
                            }
                            
                            echo wc_price($totale_finale_checkout); 
                            ?></strong>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else : ?>
                <?php // Se non abbiamo i dati del preventivo, mostra almeno il totale del carrello ?>
                <div class="btr-summary-section">
                    <h3><?php esc_html_e('Riepilogo dell\'ordine', 'born-to-ride-booking'); ?></h3>
                    
                    <?php if (!$preventivo_id) : ?>
                        <p style="color: #856404; background: #fff3cd; padding: 10px; border-radius: 4px;">
                            <?php esc_html_e('Attenzione: Dati del preventivo non trovati. Mostrando il riepilogo del carrello.', 'born-to-ride-booking'); ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php
                    // Calcola totali dal carrello
                    $subtotal = 0;
                    $insurance_total = 0;
                    $extra_total = 0;
                    
                    foreach ($cart->get_cart() as $cart_item) {
                        if (isset($cart_item['from_anagrafica']) && isset($cart_item['custom_price'])) {
                            $insurance_total += floatval($cart_item['custom_price']) * intval($cart_item['quantity']);
                        } elseif (isset($cart_item['from_extra']) && isset($cart_item['custom_price'])) {
                            $extra_total += floatval($cart_item['custom_price']) * intval($cart_item['quantity']);
                        } else {
                            // Per camere, usa il prezzo che è stato impostato
                            $product = $cart_item['data'];
                            $subtotal += $product->get_price() * $cart_item['quantity'];
                        }
                    }
                    ?>
                    
                    <div class="btr-summary-line">
                        <span><?php esc_html_e('Subtotale camere', 'born-to-ride-booking'); ?></span>
                        <span><?php echo wc_price($subtotal); ?></span>
                    </div>
                    
                    <?php if ($insurance_total > 0) : ?>
                    <div class="btr-summary-line">
                        <span><?php esc_html_e('Assicurazioni', 'born-to-ride-booking'); ?></span>
                        <span><?php echo wc_price($insurance_total); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($extra_total != 0) : ?>
                    <div class="btr-summary-line">
                        <span><?php esc_html_e('Costi extra', 'born-to-ride-booking'); ?></span>
                        <span<?php if ($extra_total < 0) echo ' style="color: #d32f2f;"'; ?>><?php echo wc_price($extra_total); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="btr-summary-line btr-summary-total">
                        <strong><?php esc_html_e('Totale', 'born-to-ride-booking'); ?></strong>
                        <strong><?php echo wc_price($cart->get_total('raw')); ?></strong>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>
                <div class="btr-summary-shipping">
                    <span><?php esc_html_e( 'Spedizione', 'woocommerce' ); ?></span>
                    <span><?php echo WC()->cart->get_cart_shipping_total(); ?></span>
                </div>
            <?php endif; ?>

            <?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
                <div class="btr-summary-discount">
                    <span><?php esc_html_e( 'Sconto:', 'woocommerce' ); ?> <?php echo wc_cart_totals_coupon_label( $coupon ); ?></span>
                    <span><?php echo wc_cart_totals_coupon_html( $coupon ); ?></span>
                </div>
            <?php endforeach; ?>

            <?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
                <div class="btr-summary-fee">
                    <span><?php echo esc_html( $fee->name ); ?></span>
                    <span><?php echo wc_price( $fee->total ); ?></span>
                </div>
            <?php endforeach; ?>

            <?php if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) : ?>
                <?php foreach ( WC()->cart->get_tax_totals() as $code => $tax ) : ?>
                    <div class="btr-summary-tax">
                        <span><?php echo esc_html( $tax->label ); ?></span>
                        <span><?php echo wp_kses_post( $tax->formatted_amount ); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// La registrazione del blocco è gestita da class-btr-checkout.php