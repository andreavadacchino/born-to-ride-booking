<?php
/**
 * Template per la pagina di selezione piano pagamento
 * 
 * @package BornToRideBooking
 * @since 1.0.98
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Recupera preventivo ID
$preventivo_id = isset($_GET['preventivo_id']) ? intval($_GET['preventivo_id']) : 0;

// Se non c'è preventivo, prova dalla sessione
if (!$preventivo_id && WC()->session) {
    $preventivo_id = WC()->session->get('_preventivo_id');
}

if (!$preventivo_id) {
    wp_die(__('Preventivo non trovato. Torna indietro e riprova.', 'born-to-ride-booking'));
}

// Verifica che il preventivo esista
$preventivo = get_post($preventivo_id);
if (!$preventivo || $preventivo->post_type !== 'btr_preventivi') {
    wp_die(__('Preventivo non valido.', 'born-to-ride-booking'));
}

// Ottimizzazione: Recupera tutti i metadati con cache
$cache_key = 'btr_preventivo_data_' . $preventivo_id;
$preventivo_data = wp_cache_get($cache_key, 'btr_preventivi');

if (false === $preventivo_data) {
    // Recupera tutti i meta in una sola query
    $all_meta = get_post_meta($preventivo_id);
    
    // PRIORITA': Usa il breakdown dettagliato se disponibile (come fa il checkout)
    $riepilogo_dettagliato = maybe_unserialize($all_meta['_riepilogo_calcoli_dettagliato'][0] ?? '');
    $totale_preventivo_meta = isset($all_meta['_totale_preventivo'][0]) ? floatval($all_meta['_totale_preventivo'][0]) : 0;
    
    // Determina il prezzo base correttamente
    $prezzo_base_calcolato = 0;
    if (!empty($riepilogo_dettagliato) && is_array($riepilogo_dettagliato) && 
        !empty($riepilogo_dettagliato['totali'])) {
        // USA LA STESSA LOGICA DEL CHECKOUT
        $totali = $riepilogo_dettagliato['totali'];
        // IMPORTANTE: Il totale camere NON include i supplementi extra
        // che devono essere conteggiati separatamente nei costi extra
        $prezzo_base_calcolato = floatval($totali['subtotale_prezzi_base'] ?? 0) + 
                                floatval($totali['subtotale_supplementi_base'] ?? 0) + 
                                floatval($totali['subtotale_notti_extra'] ?? 0);
    } else {
        // FALLBACK: usa _prezzo_totale che include camere + supplementi + notti extra
        $prezzo_base_calcolato = floatval($all_meta['_prezzo_totale'][0] ?? 0);
    }

    $totale_preventivo_calcolato = $totale_preventivo_meta;
    if (!empty($totali) && is_array($totali) && !empty($totali['totale_generale'])) {
        $totale_preventivo_calcolato = floatval($totali['totale_generale']);
    }
    
    // Recupera i dati anagrafici e costi extra
    $anagrafici = maybe_unserialize($all_meta['_anagrafici_preventivo'][0] ?? '');
    $costi_extra_durata = maybe_unserialize($all_meta['_costi_extra_durata'][0] ?? '');

    // FIX: Recupera i costi extra da meta alternativi se _costi_extra_durata è vuoto
    if (empty($costi_extra_durata) || !is_array($costi_extra_durata)) {
        // Prova prima con i totali calcolati
        $totale_costi_extra_meta = floatval($all_meta['_btr_totale_costi_extra'][0] ?? 0);
        if ($totale_costi_extra_meta == 0) {
            $totale_costi_extra_meta = floatval($all_meta['_totale_costi_extra'][0] ?? 0);
        }
        if ($totale_costi_extra_meta == 0) {
            $totale_costi_extra_meta = floatval($all_meta['_pricing_totale_costi_extra'][0] ?? 0);
        }
    }

    // USA BTR_Price_Calculator come fa il checkout per calcolare dinamicamente assicurazioni e costi extra
    $price_calculator = btr_price_calculator();
    $extra_costs_result = $price_calculator->calculate_extra_costs($anagrafici, $costi_extra_durata);

    // Se il calculator non trova costi extra ma abbiamo un totale nei meta, usa quello
    if (($extra_costs_result['totale'] == 0) && isset($totale_costi_extra_meta) && $totale_costi_extra_meta != 0) {
        $extra_costs_result['totale'] = $totale_costi_extra_meta;
        $extra_costs_result['totale_aggiunte'] = max(0, $totale_costi_extra_meta);
        $extra_costs_result['totale_riduzioni'] = min(0, $totale_costi_extra_meta);

        // Debug log
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[BTR Payment Selection] FIX applicato: recuperato totale costi extra = ' . $totale_costi_extra_meta . ' per preventivo ' . $preventivo_id);
        }
    }
    
    // Calcola il totale assicurazioni dinamicamente
    $totale_assicurazioni_calcolato = 0;
    if (!empty($anagrafici) && is_array($anagrafici)) {
        foreach ($anagrafici as $persona) {
            // Usa la stessa logica del debug script che funziona correttamente
            if (isset($persona['assicurazioni_dettagliate']) && is_array($persona['assicurazioni_dettagliate'])) {
                $assicurazioni_attive = isset($persona['assicurazioni']) ? $persona['assicurazioni'] : [];
                
                foreach ($persona['assicurazioni_dettagliate'] as $key => $assicurazione) {
                    if (isset($assicurazioni_attive[$key]) && $assicurazioni_attive[$key] == '1') {
                        $importo = floatval($assicurazione['importo'] ?? 0);
                        $totale_assicurazioni_calcolato += $importo;
                    }
                }
            }
        }
    }
    
    // Recupera i supplementi extra che vanno aggiunti al totale ma non mostrati nel "Totale Camere"
    $supplementi_extra = 0;
    if (!empty($riepilogo_dettagliato['totali']['subtotale_supplementi_extra'])) {
        $supplementi_extra = floatval($riepilogo_dettagliato['totali']['subtotale_supplementi_extra']);
    }
    
    // Calcola un totale normalizzato che includa supplementi/assicurazioni moderni
    $normalized_total = round(
        $prezzo_base_calcolato
        + $supplementi_extra
        + $totale_assicurazioni_calcolato
        + floatval($extra_costs_result['totale'] ?? 0),
        2
    );

    if ($normalized_total > 0 && abs($totale_preventivo_calcolato - $normalized_total) > 0.01) {
        $totale_preventivo_calcolato = $normalized_total;
    }

    // Estrai i valori necessari
    $preventivo_data = [
        'pacchetto_id' => $all_meta['_pacchetto_id'][0] ?? 0,
        'numero_adulti' => intval($all_meta['_num_adults'][0] ?? $all_meta['_numero_adulti'][0] ?? 0),
        'numero_bambini' => intval($all_meta['_num_children'][0] ?? $all_meta['_numero_bambini'][0] ?? 0),
        'numero_neonati' => intval($all_meta['_num_neonati'][0] ?? 0),
        'numero_paganti' => intval($all_meta['_num_paganti'][0] ?? 0),
        'camere_selezionate' => maybe_unserialize($all_meta['_camere_selezionate'][0] ?? ''),
        'data_partenza' => $all_meta['_data_partenza'][0] ?? '',
        'data_ritorno' => $all_meta['_data_ritorno'][0] ?? '',
        'prezzo_base' => $prezzo_base_calcolato,
        'supplementi_extra' => $supplementi_extra, // Supplementi per notti extra
        'anagrafici' => $anagrafici,
        'costi_extra' => $costi_extra_durata,
        'totale_assicurazioni' => $totale_assicurazioni_calcolato, // Calcolato dinamicamente
        'totale_costi_extra' => $extra_costs_result['totale'], // Usa il risultato del calculator
        'totale_riduzioni' => $extra_costs_result['totale_riduzioni'], // Separa riduzioni
        'totale_aggiunte' => $extra_costs_result['totale_aggiunte'], // Separa aggiunte
        'extra_costs_detail' => $extra_costs_result, // Mantieni il dettaglio completo
        'totale_preventivo' => $totale_preventivo_calcolato // Calcolato dopo se necessario
    ];
    
    // FALLBACK: Se i meta dei partecipanti sono vuoti, conta dagli anagrafici
    if (($preventivo_data['numero_adulti'] + $preventivo_data['numero_bambini'] + $preventivo_data['numero_neonati']) == 0) {
        if (!empty($anagrafici) && is_array($anagrafici)) {
            $count_adulti = 0;
            $count_bambini = 0;
            $count_neonati = 0;
            
            foreach ($anagrafici as $persona) {
                if (!empty($persona['nome']) && !empty($persona['cognome'])) {
                    // Determina se è adulto, bambino o neonato
                    if (!empty($persona['data_nascita'])) {
                        $birth_date = new DateTime($persona['data_nascita']);
                        $today = new DateTime();
                        $age = $today->diff($birth_date)->y;
                        
                        if ($age >= 18) {
                            $count_adulti++;
                        } elseif ($age >= 2) {
                            $count_bambini++;
                        } else {
                            $count_neonati++;
                        }
                    } else {
                        // Se non c'è data di nascita, assume adulto
                        $count_adulti++;
                    }
                }
            }
            
            // Aggiorna i conteggi se trovati
            if ($count_adulti > 0 || $count_bambini > 0 || $count_neonati > 0) {
                $preventivo_data['numero_adulti'] = $count_adulti;
                $preventivo_data['numero_bambini'] = $count_bambini;
                $preventivo_data['numero_neonati'] = $count_neonati;
                
                // Log per debug
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[BTR Payment Selection] Fallback partecipanti da anagrafici: ' . 
                             "Adulti: $count_adulti, Bambini: $count_bambini, Neonati: $count_neonati");
                }
            }
        }
    }
    
    // Non calcolare qui il totale perché verrà sovrascritto da extract()
    
    // Cache per 5 minuti
    wp_cache_set($cache_key, $preventivo_data, 'btr_preventivi', 300);
}

// Estrai variabili per retrocompatibilità
extract($preventivo_data);

// Se il totale non è disponibile nei meta, calcolalo manualmente per retrocompatibilità
$calculated_total_runtime = round(
    floatval($prezzo_base)
    + floatval($supplementi_extra)
    + floatval($totale_assicurazioni)
    + floatval($totale_costi_extra),
    2
);

if ($calculated_total_runtime > 0 && (empty($totale_preventivo) || $totale_preventivo <= 0)) {
    $totale_preventivo = $calculated_total_runtime;
} elseif ($calculated_total_runtime > 0 && abs($totale_preventivo - $calculated_total_runtime) > 0.01) {
    $totale_preventivo = $calculated_total_runtime;
}

$totale_persone = intval($numero_adulti) + intval($numero_bambini) + intval($numero_neonati ?? 0);
$totale_paganti_meta = intval($numero_paganti ?? 0);
if ($totale_paganti_meta > 0) {
    $totale_paganti = $totale_paganti_meta;
} elseif (intval($numero_adulti) > 0) {
    $totale_paganti = intval($numero_adulti);
} else {
    $totale_paganti = $totale_persone;
}

// Garantisce che il numero dei paganti non sia inferiore agli adulti effettivi
$adulti_contati = 0;
if (!empty($anagrafici) && is_array($anagrafici)) {
    foreach ($anagrafici as $persona) {
        if (empty($persona['nome']) || empty($persona['cognome'])) {
            continue;
        }

        $is_adult = true;
        if (!empty($persona['data_nascita'])) {
            try {
                $birth_date = new DateTime($persona['data_nascita']);
                $today = new DateTime();
                $is_adult = $today->diff($birth_date)->y >= 18;
            } catch (Exception $e) {
                $is_adult = true;
            }
        }

        if ($is_adult) {
            $adulti_contati++;
        }
    }
}

$base_adulti = max(intval($numero_adulti), $adulti_contati);
if ($base_adulti > 0) {
    $totale_paganti = max($totale_paganti, $base_adulti);
}

// Costruisci dataset dettagliato per pagamenti di gruppo
$adult_details = [];
$child_details = [];

if (!empty($anagrafici) && is_array($anagrafici)) {
    $totale_camere = floatval($riepilogo_dettagliato['totali']['subtotale_camere'] ?? $prezzo_base_calcolato);
    $num_adulti_riepilogo = intval($riepilogo_dettagliato['partecipanti']['adulti']['quantita'] ?? $numero_adulti);
    $adult_unit = $num_adulti_riepilogo > 0 ? $totale_camere / $num_adulti_riepilogo : 0;
    if ($adult_unit <= 0 && $numero_adulti > 0) {
        $adult_unit = $totale_camere / $numero_adulti;
    }

    $assignments = get_post_meta($preventivo_id, '_assignments_partecipanti', true);
    $assignments = is_array($assignments) ? $assignments : [];

    $get_person_addons = function($persona, $index) use ($preventivo_id) {
        global $wpdb;

        $sum_extra = 0.0;
        $sum_ins = 0.0;
        $extra_items = [];
        $insurance_items = [];

        // Meta personalizzati (v1.0.239: include extra individuali salvati dal backend)
        $meta_query = $wpdb->prepare(
            "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s",
            $preventivo_id,
            "_anagrafico_{$index}_extra_%"
        );
        $meta_results = $wpdb->get_results($meta_query);

        if (!empty($meta_results)) {
            $extra_meta = [];

            foreach ($meta_results as $meta) {
                if (!preg_match('/^_anagrafico_' . $index . '_extra_(.+)_(selected|price)$/', $meta->meta_key, $matches)) {
                    continue;
                }

                $slug = $matches[1];
                $field = $matches[2];

                if (!isset($extra_meta[$slug])) {
                    $extra_meta[$slug] = [
                        'selected' => '0',
                        'price'    => 0,
                    ];
                }

                if ('selected' === $field) {
                    $extra_meta[$slug]['selected'] = (string) $meta->meta_value;
                } elseif ('price' === $field) {
                    $extra_meta[$slug]['price'] = floatval($meta->meta_value);
                }
            }

            foreach ($extra_meta as $slug => $data) {
                $is_selected = in_array($data['selected'], ['1', 'yes', 'true'], true) || 0.0 !== floatval($data['price']);
                if (!$is_selected) {
                    continue;
                }

                $amount = floatval($data['price']);
                $label = $persona['costi_extra_dettagliate'][$slug]['nome']
                    ?? $persona['costi_extra_dettagliate'][$slug]['descrizione']
                    ?? ucwords(str_replace('_', ' ', $slug));

                if (false !== strpos($slug, 'assicuraz')) {
                    // Alcuni vecchi flussi salvano le assicurazioni come extra
                    $sum_ins += $amount;
                    $insurance_items[$slug] = [
                        'slug' => $slug,
                        'label' => $label,
                        'amount' => $amount,
                    ];
                } else {
                    $sum_extra += $amount;
                    $extra_items[$slug] = [
                        'slug' => $slug,
                        'label' => $label,
                        'amount' => $amount,
                    ];
                }
            }
        }

        if (empty($meta_results)) {
            // Fallback: dati serializzati nell'anagrafica
            if (!empty($persona['costi_extra_dettagliate']) && is_array($persona['costi_extra_dettagliate'])) {
                foreach ($persona['costi_extra_dettagliate'] as $slug => $info) {
                    $selected = !empty($persona['costi_extra'][$slug])
                        || (!empty($info['selected']) && '1' === $info['selected'])
                        || (!empty($info['attivo']) && in_array($info['attivo'], [true, 1, '1', 'yes'], true));

                    if (!$selected) {
                        continue;
                    }

                    $amount = floatval($info['importo'] ?? 0);
                    if (false !== strpos($slug, 'assicuraz')) {
                        $sum_ins += $amount;
                        $insurance_items[$slug] = [
                            'slug' => $slug,
                            'label' => $info['nome'] ?? $info['descrizione'] ?? $slug,
                            'amount' => $amount,
                        ];
                    } else {
                        $sum_extra += $amount;
                        $extra_items[$slug] = [
                            'slug' => $slug,
                            'label' => $info['nome'] ?? $info['descrizione'] ?? $slug,
                            'amount' => $amount,
                        ];
                    }
                }
            }
        }

        // Assicurazioni dedicate (v1.0.239 - sempre fuori dal fallback)
        if (!empty($persona['assicurazioni_dettagliate']) && is_array($persona['assicurazioni_dettagliate'])) {
            foreach ($persona['assicurazioni_dettagliate'] as $slug => $info) {
                $selected = !empty($persona['assicurazioni'][$slug])
                    || (!empty($info['selected']) && '1' === $info['selected']);
                if (!$selected) {
                    continue;
                }

                $amount = floatval($info['importo'] ?? 0);
                $sum_ins += $amount;
                $insurance_items[$slug] = [
                    'slug' => $slug,
                    'label' => $info['descrizione'] ?? $info['nome'] ?? $slug,
                    'amount' => $amount,
                ];
            }
        }

        return [
            $sum_extra,
            $sum_ins,
            array_values($extra_items),
            array_values($insurance_items)
        ];
    };

    foreach ($anagrafici as $index => $persona) {
        if (empty($persona['nome']) || empty($persona['cognome'])) {
            continue;
        }

        $is_adult = true;
        $age = null;
        if (!empty($persona['data_nascita'])) {
            try {
                $birth_date = new DateTime($persona['data_nascita']);
                $today = new DateTime();
                $age = $today->diff($birth_date)->y;
                $is_adult = $age >= 18;
            } catch (Exception $e) {
                $is_adult = true;
            }
        }

        list($sum_extra, $sum_ins, $extra_items, $insurance_items) = $get_person_addons($persona, $index);

        if ($is_adult) {
            $personal_total = $adult_unit + $sum_extra + $sum_ins;

            // Somma costi dei bambini assegnati a questo adulto
            $assigned_children_total = 0;
            $assigned_children_list = [];
            foreach ($assignments as $child_index => $assignment) {
                if (isset($assignment['adulto']) && intval($assignment['adulto']) === $index) {
                    $child_total = floatval($assignment['totale'] ?? 0);
                    $assigned_children_total += $child_total;
                    if (!empty($assignment['label'])) {
                        $assigned_children_list[] = [
                            'label' => $assignment['label'],
                            'amount' => $child_total,
                        ];
                    }
                }
            }

            $adult_details[] = [
                'index' => $index,
                'name' => trim(($persona['nome'] ?? '') . ' ' . ($persona['cognome'] ?? '')),
                'email' => $persona['email'] ?? '',
                'base' => $adult_unit,
                'extras' => $sum_extra,
                'insurance' => $sum_ins,
                'assignedChildren' => $assigned_children_total,
                'personalTotal' => $personal_total + $assigned_children_total,
                'extraItems' => $extra_items,
                'insuranceItems' => $insurance_items,
                'assignedChildrenItems' => $assigned_children_list,
            ];
        } else {
            $child_details[] = [
                'index' => $index,
                'label' => trim(($persona['nome'] ?? '') . ' ' . ($persona['cognome'] ?? '')), 
                'age' => $age,
            ];
        }
    }
}

$pacchetto_title = get_the_title($pacchetto_id);

// Opzioni per il piano di pagamento
$bank_transfer_enabled = get_option('btr_enable_bank_transfer_plans', true);
$bank_transfer_info = get_option('btr_bank_transfer_info', '');
$deposit_percentage = intval(get_option('btr_default_deposit_percentage', 30));

// Mostra sempre la selezione del pagamento, anche se esiste già un piano

// Debug rimosso per produzione
// printr(get_post_meta($preventivo_id));
// Non usiamo get_header() perché siamo in uno shortcode
?>

<div class="btr-payment-selection-page">
    <div class="container" style="max-width: 800px; margin: 40px auto; padding: 0 20px;">
        
        <!-- Progress indicator -->
        <div class="btr-progress-indicator" role="navigation" aria-label="<?php esc_attr_e('Progresso prenotazione', 'born-to-ride-booking'); ?>">
            <ol class="btr-progress-steps">
                <li class="completed">
                    <span class="step-number" aria-hidden="true">1</span>
                    <span class="step-label"><?php esc_html_e('Dati Anagrafici', 'born-to-ride-booking'); ?></span>
                </li>
                <li class="current" aria-current="step">
                    <span class="step-number" aria-hidden="true">2</span>
                    <span class="step-label"><?php esc_html_e('Metodo Pagamento', 'born-to-ride-booking'); ?></span>
                </li>
                <li>
                    <span class="step-number" aria-hidden="true">3</span>
                    <span class="step-label"><?php esc_html_e('Checkout', 'born-to-ride-booking'); ?></span>
                </li>
            </ol>
        </div>

        <!-- Titolo e info pacchetto -->
        <div class="btr-page-header">
            <h1><?php esc_html_e('Seleziona il metodo di pagamento', 'born-to-ride-booking'); ?></h1>
            <p class="package-info">
                <?php printf(
                    __('Per il pacchetto: %s', 'born-to-ride-booking'),
                    '<strong>' . esc_html($pacchetto_title) . '</strong>'
                ); ?>
            </p>
        </div>
        
        <!-- Riepilogo Preventivo -->
        <div class="btr-quote-summary">
            <h2><?php esc_html_e('Riepilogo della tua prenotazione', 'born-to-ride-booking'); ?></h2>
            
            <div class="summary-grid">
                <!-- Colonna 1: Dettagli viaggio -->
                <div class="summary-section">
                    <h3><?php esc_html_e('Dettagli Viaggio', 'born-to-ride-booking'); ?></h3>
                    <ul class="summary-list">
                        <?php if ($data_partenza && $data_ritorno): ?>
                        <li>
                            <span class="label"><?php esc_html_e('Date:', 'born-to-ride-booking'); ?></span>
                            <span class="value">
                                <?php echo date_i18n('d M Y', strtotime($data_partenza)); ?> - 
                                <?php echo date_i18n('d M Y', strtotime($data_ritorno)); ?>
                            </span>
                        </li>
                        <?php endif; ?>
                        
                        <li>
                            <span class="label"><?php esc_html_e('Partecipanti:', 'born-to-ride-booking'); ?></span>
                            <span class="value">
                                <?php 
                                $partecipanti = [];
                                if ($numero_adulti > 0) $partecipanti[] = $numero_adulti . ' ' . _n('Adulto', 'Adulti', $numero_adulti, 'born-to-ride-booking');
                                if ($numero_bambini > 0) $partecipanti[] = $numero_bambini . ' ' . _n('Bambino', 'Bambini', $numero_bambini, 'born-to-ride-booking');
                                if ($numero_neonati > 0) $partecipanti[] = $numero_neonati . ' ' . _n('Neonato', 'Neonati', $numero_neonati, 'born-to-ride-booking');
                                echo implode(' + ', $partecipanti);
                                ?>
                            </span>
                        </li>
                        
                        <?php if (!empty($camere_selezionate) && is_array($camere_selezionate)): ?>
                        <li>
                            <span class="label"><?php esc_html_e('Camere:', 'born-to-ride-booking'); ?></span>
                            <span class="value">
                                <?php 
                                $camere_list = [];
                                foreach ($camere_selezionate as $camera) {
                                    if (isset($camera['quantita']) && $camera['quantita'] > 0) {
                                        $camere_list[] = $camera['quantita'] . 'x ' . $camera['tipo'];
                                    }
                                }
                                echo implode(', ', $camere_list);
                                ?>
                            </span>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <!-- Colonna 2: Dettagli economici -->
                <div class="summary-section">
                    <h3><?php esc_html_e('Dettaglio Prezzi', 'born-to-ride-booking'); ?></h3>
                    <ul class="price-breakdown">
                        <?php if ($prezzo_base): ?>
                        <li>
                            <span class="label"><?php esc_html_e('Totale Camere:', 'born-to-ride-booking'); ?></span>
                            <span class="value"><?php echo btr_format_price_i18n($prezzo_base); ?></span>
                        </li>
                        <?php endif; ?>
                        
                        <?php 
                        // FIX v1.0.230: Rimossi supplementi extra dalla visualizzazione - già inclusi nel totale camere
                        ?>
                        
                        <?php if ($totale_assicurazioni && $totale_assicurazioni > 0): ?>
                        <li>
                            <span class="label"><?php esc_html_e('Assicurazioni:', 'born-to-ride-booking'); ?></span>
                            <span class="value">+ <?php echo btr_format_price_i18n($totale_assicurazioni); ?></span>
                        </li>
                        <?php endif; ?>
                        
                        <?php 
                        // FIX v1.0.230: Mostra i costi extra come singola voce per evitare duplicazioni
                        if (isset($totale_costi_extra) && $totale_costi_extra != 0): 
                        ?>
                        <li>
                            <span class="label"><?php esc_html_e('Costi Extra:', 'born-to-ride-booking'); ?></span>
                            <span class="value<?php echo $totale_costi_extra < 0 ? ' discount' : ''; ?>">
                                <?php echo $totale_costi_extra < 0 ? '' : '+ '; ?><?php echo btr_format_price_i18n($totale_costi_extra); ?>
                            </span>
                        </li>
                        <?php endif; ?>
                        
                        <li class="total-row">
                            <span class="label"><strong><?php esc_html_e('Totale:', 'born-to-ride-booking'); ?></strong></span>
                            <span class="value total-price"><strong><?php echo btr_format_price_i18n($totale_preventivo); ?></strong></span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <?php if (!empty($anagrafici) && is_array($anagrafici)): ?>
            <div class="participants-info">
                <h3><?php esc_html_e('Partecipanti registrati', 'born-to-ride-booking'); ?></h3>
                <p class="participants-count">
                    <?php 
                    $registrati = count(array_filter($anagrafici, function($p) {
                        return !empty($p['nome']) && !empty($p['cognome']);
                    }));
                    printf(
                        __('%d di %d partecipanti con dati completi', 'born-to-ride-booking'),
                        $registrati,
                        $totale_persone
                    );
                    ?>
                </p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Form selezione piano -->
        <form id="btr-payment-plan-selection" 
              method="post" 
              action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
              data-total="<?php echo esc_attr($totale_preventivo); ?>"
              data-participants="<?php echo esc_attr($totale_paganti); ?>"
              data-travellers="<?php echo esc_attr($totale_persone); ?>">
            <?php wp_nonce_field('btr_payment_plan_nonce', 'payment_nonce'); ?>
            <input type="hidden" name="action" value="btr_create_payment_plan">
            <input type="hidden" name="preventivo_id" value="<?php echo esc_attr($preventivo_id); ?>">
            
            <fieldset class="btr-payment-options" role="radiogroup" aria-labelledby="payment-method-title">
                <legend id="payment-method-title" class="screen-reader-text">
                    <?php esc_html_e('Scegli il metodo di pagamento', 'born-to-ride-booking'); ?>
                </legend>
                
                <!-- Pagamento completo -->
                <div class="btr-payment-option" data-plan="full">
                    <input type="radio" 
                           name="payment_plan" 
                           id="plan_full" 
                           value="full" 
                           checked
                           aria-describedby="full-description">
                    <label for="plan_full">
                        <span class="option-icon" aria-hidden="true">💳</span>
                        <span class="option-content">
                            <span class="option-title"><?php esc_html_e('Pagamento Completo', 'born-to-ride-booking'); ?></span>
                            <span id="full-description" class="option-description">
                                <?php esc_html_e('Paga l\'intero importo in un\'unica soluzione', 'born-to-ride-booking'); ?>
                            </span>
                        </span>
                    </label>
                </div>
                
                <!-- Caparra + Saldo -->
                <?php
                $deposit_amount = $totale_preventivo * $deposit_percentage / 100;
                $balance_amount = $totale_preventivo - $deposit_amount;
                $deposit_per_person = $totale_paganti > 0 ? $deposit_amount / $totale_paganti : 0;
                $balance_per_person = $totale_paganti > 0 ? $balance_amount / $totale_paganti : 0;
                $quota_per_persona = $totale_paganti > 0 ? $totale_preventivo / $totale_paganti : 0;
                ?>
                <div class="btr-payment-option" data-plan="deposit_balance">
                    <input type="radio" 
                           name="payment_plan" 
                           id="plan_deposit" 
                           value="deposit_balance"
                           aria-describedby="deposit-description deposit-config">
                    <label for="plan_deposit">
                        <span class="option-icon" aria-hidden="true">📊</span>
                        <span class="option-content">
                            <span class="option-title"><?php esc_html_e('Caparra + Saldo', 'born-to-ride-booking'); ?></span>
                            <span id="deposit-description" class="option-description">
                                <?php esc_html_e('Paga una caparra ora e il saldo successivamente', 'born-to-ride-booking'); ?>
                            </span>
                        </span>
                    </label>
                    
                    <div id="deposit-config" class="deposit-config collapsible-panel" style="display: none;" aria-live="polite">
                        <div class="deposit-summary-grid" role="group" aria-label="<?php esc_attr_e('Ripartizione importi tra caparra e saldo', 'born-to-ride-booking'); ?>">
                            <div class="amount-card due-now">
                                <span class="amount-label"><?php esc_html_e('Paghi adesso', 'born-to-ride-booking'); ?></span>
                                <span class="amount-value deposit-amount"><?php echo btr_format_price_i18n($deposit_amount); ?></span>
                                <span class="per-person">
                                    <?php esc_html_e('Quota per partecipante', 'born-to-ride-booking'); ?>
                                    <strong class="deposit-per-person-amount"><?php echo btr_format_price_i18n($deposit_per_person); ?></strong>
                                </span>
                            </div>
                            <div class="amount-card due-later">
                                <span class="amount-label"><?php esc_html_e('Da saldare più avanti', 'born-to-ride-booking'); ?></span>
                                <span class="amount-value balance-amount"><?php echo btr_format_price_i18n($balance_amount); ?></span>
                                <span class="per-person">
                                    <?php esc_html_e('Quota per partecipante', 'born-to-ride-booking'); ?>
                                    <strong class="balance-per-person-amount"><?php echo btr_format_price_i18n($balance_per_person); ?></strong>
                                </span>
                            </div>
                        </div>

                        <div class="deposit-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr($deposit_percentage); ?>">
                            <div class="deposit-progress-track">
                                <span class="deposit-progress-bar" style="width: <?php echo esc_attr($deposit_percentage); ?>%;"></span>
                            </div>
                            <div class="deposit-progress-labels">
                                <span class="label-now">
                                    <?php esc_html_e('Caparra', 'born-to-ride-booking'); ?>
                                    <strong class="deposit-percentage"><?php echo esc_html($deposit_percentage); ?>%</strong>
                                </span>
                                <span class="label-later">
                                    <?php esc_html_e('Saldo', 'born-to-ride-booking'); ?>
                                    <strong class="balance-percentage"><?php echo esc_html(100 - $deposit_percentage); ?>%</strong>
                                </span>
                            </div>
                        </div>

                        <label for="deposit_percentage" class="deposit-slider-label">
                            <?php esc_html_e('Regola la percentuale di caparra', 'born-to-ride-booking'); ?>
                            <span class="deposit-value" aria-live="polite"><?php echo esc_html($deposit_percentage); ?>%</span>
                        </label>
                        <input type="range" 
                               id="deposit_percentage"
                               name="deposit_percentage" 
                               min="10" 
                               max="90" 
                               value="<?php echo esc_attr($deposit_percentage); ?>" 
                               step="5"
                               aria-valuemin="10"
                               aria-valuemax="90"
                               aria-valuenow="<?php echo esc_attr($deposit_percentage); ?>">

                        <div class="deposit-details-grid">
                            <div class="detail-card">
                                <span class="detail-icon" aria-hidden="true">🛡️</span>
                                <div class="detail-content">
                                    <span class="detail-title"><?php esc_html_e('Prenotazione garantita', 'born-to-ride-booking'); ?></span>
                                    <p><?php esc_html_e('La caparra blocca posti, camere e condizioni del preventivo.', 'born-to-ride-booking'); ?></p>
                                </div>
                            </div>
                            <div class="detail-card">
                                <span class="detail-icon" aria-hidden="true">⏳</span>
                                <div class="detail-content">
                                    <span class="detail-title"><?php esc_html_e('Saldo flessibile', 'born-to-ride-booking'); ?></span>
                                    <p><?php esc_html_e("Potrai saldare l'importo restante con lo stesso metodo di pagamento prima della partenza.", 'born-to-ride-booking'); ?></p>
                                </div>
                            </div>
                            <div class="detail-card">
                                <span class="detail-icon" aria-hidden="true">👥</span>
                                <div class="detail-content">
                                    <span class="detail-title"><?php esc_html_e('Ideale per gruppi', 'born-to-ride-booking'); ?></span>
                                    <p><?php esc_html_e('Concedi tempo agli altri partecipanti per inviarti le rispettive quote prima di chiudere il saldo.', 'born-to-ride-booking'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pagamento di Gruppo -->
                <div class="btr-payment-option" data-plan="group_split">
                    <input type="radio" 
                           name="payment_plan" 
                           id="plan_group" 
                           value="group_split"
                           aria-describedby="group-description">
                    <label for="plan_group">
                        <span class="option-icon" aria-hidden="true">👥</span>
                        <span class="option-content">
                            <span class="option-title"><?php esc_html_e('Pagamento di Gruppo', 'born-to-ride-booking'); ?></span>
                            <span id="group-description" class="option-description">
                                <?php esc_html_e('Ogni partecipante paga la propria quota individualmente', 'born-to-ride-booking'); ?>
                            </span>
                        </span>
                    </label>
                    
                    <!-- Configurazione pagamento di gruppo -->
                    <div id="group-payment-config" class="group-payment-config collapsible-panel" style="display: none;" aria-live="polite">
                        <h4><?php esc_html_e('Seleziona chi effettuerà il pagamento', 'born-to-ride-booking'); ?></h4>

                        <div class="group-dashboard" aria-live="polite">
                            <div class="dashboard-card total">
                                <span class="dashboard-label"><?php esc_html_e('Totale prenotazione', 'born-to-ride-booking'); ?></span>
                                <span class="dashboard-value"><?php echo btr_format_price_i18n($totale_preventivo); ?></span>
                                <span class="dashboard-subtext">
                                    <?php esc_html_e('Paganti attesi', 'born-to-ride-booking'); ?>
                                    <strong class="total-participants-count"><?php echo esc_html($totale_paganti); ?></strong>
                                    <?php if ($totale_persone !== $totale_paganti): ?>
                                        <span class="total-travellers-note">
                                            <?php printf(
                                                /* translators: %d is the number of total travellers */
                                                __('Viaggiatori totali: %d', 'born-to-ride-booking'),
                                                intval($totale_persone)
                                            ); ?>
                                        </span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="dashboard-card assigned">
                                <span class="dashboard-label"><?php esc_html_e('Quote assegnate', 'born-to-ride-booking'); ?></span>
                                <span class="dashboard-value js-assigned-amount"><?php echo btr_format_price_i18n(0); ?></span>
                                <span class="dashboard-subtext">
                                    <strong class="total-shares">0</strong> / <span class="total-participants-count"><?php echo esc_html($totale_paganti); ?></span> <?php esc_html_e('quote', 'born-to-ride-booking'); ?>
                                </span>
                            </div>
                            <div class="dashboard-card remaining">
                                <span class="dashboard-label"><?php esc_html_e('Da assegnare', 'born-to-ride-booking'); ?></span>
                                <span class="dashboard-value js-remaining-amount"><?php echo btr_format_price_i18n($totale_preventivo); ?></span>
                                <span class="dashboard-subtext"><?php esc_html_e('Copertura', 'born-to-ride-booking'); ?> <strong class="js-coverage-percentage">0%</strong></span>
                            </div>
                        </div>

                        <div class="group-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                            <div class="group-progress-track">
                                <span class="group-progress-bar js-group-progress-bar" style="width: 0%;"></span>
                            </div>
                        </div>

                        <div class="group-actions">
                            <button type="button" class="button button-secondary group-auto-assign" data-behavior="auto-assign">
                                <?php esc_html_e('Seleziona e dividi in parti uguali', 'born-to-ride-booking'); ?>
                            </button>
                            <button type="button" class="button button-link group-reset" data-behavior="reset">
                                <?php esc_html_e('Azzera selezioni', 'born-to-ride-booking'); ?>
                            </button>
                        </div>

                        <p class="group-helper">
                            <?php
                            echo wp_kses(
                                sprintf(
                                    /* translators: %s is the formatted individual quota */
                                    __('Ogni quota vale %s. Se un adulto paga per più persone, aumenta il numero di quote nella tabella.', 'born-to-ride-booking'),
                                    '<strong>' . btr_format_price_i18n($quota_per_persona) . '</strong>'
                                ),
                                [
                                    'strong' => []
                                ]
                            );
                            ?>
                        </p>

                        <?php 
                        // Recupera gli adulti dal preventivo
                        $adulti_paganti = [];
                        
                        // Debug per verificare i dati anagrafici
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('[BTR Payment Selection] Anagrafici per preventivo ' . $preventivo_id . ': ' . print_r($anagrafici, true));
                        }
                        
                        if (!empty($anagrafici) && is_array($anagrafici)) {
                            foreach ($anagrafici as $index => $persona) {
                                // Considera solo gli adulti (assumendo che i bambini abbiano età < 18)
                                $is_adult = true;
                                if (!empty($persona['data_nascita'])) {
                                    $birth_date = new DateTime($persona['data_nascita']);
                                    $today = new DateTime();
                                    $age = $today->diff($birth_date)->y;
                                    $is_adult = $age >= 18;
                                }
                                
                                if ($is_adult && !empty($persona['nome']) && !empty($persona['cognome'])) {
                                    $adulti_paganti[] = [
                                        'index' => $index,
                                        'nome' => $persona['nome'] . ' ' . $persona['cognome'],
                                        'email' => $persona['email'] ?? ''
                                    ];
                                }
                            }
                        }
                        
                        ?>
                        
                        <div class="group-participants">
                            <?php if (!empty($adult_details)): ?>
                                <p class="description"><?php esc_html_e('Puoi selezionare quali adulti pagheranno e per quante quote ciascuno.', 'born-to-ride-booking'); ?></p>
                                
                                <div class="btr-participants-grid">
                            <?php foreach ($adult_details as $adulto): ?>
                            <div class="btr-participant-selection"
                                 data-participant-index="<?php echo esc_attr($adulto['index']); ?>"
                                 data-base="<?php echo esc_attr(number_format($adulto['base'], 2, '.', '')); ?>"
                                 data-extra="<?php echo esc_attr(number_format($adulto['extras'], 2, '.', '')); ?>"
                                 data-ins="<?php echo esc_attr(number_format($adulto['insurance'], 2, '.', '')); ?>"
                                 data-assigned-children="<?php echo esc_attr(number_format($adulto['assignedChildren'], 2, '.', '')); ?>"
                                 data-personal-total="<?php echo esc_attr(number_format($adulto['personalTotal'], 2, '.', '')); ?>">
                                <div class="btr-participant-header">
                                    <div class="btr-checkbox-wrapper">
                                        <input type="checkbox" 
                                               class="participant-checkbox btr-form-check-input"
                                               name="group_participants[<?php echo $adulto['index']; ?>][selected]"
                                               id="participant_<?php echo $adulto['index']; ?>"
                                               value="1"
                                               data-index="<?php echo $adulto['index']; ?>">
                                    </div>
                                    <div class="btr-participant-info">
                                        <label for="participant_<?php echo $adulto['index']; ?>" class="btr-participant-name">
                                            <strong><?php echo esc_html($adulto['name']); ?></strong>
                                            <?php if (!empty($adulto['email'])): ?>
                                                <small><?php echo esc_html($adulto['email']); ?></small>
                                            <?php endif; ?>
                                        </label>
                                        <div class="btr-participant-breakdown">
                                            <span><?php esc_html_e('Base', 'born-to-ride-booking'); ?>: <strong class="bd-base"><?php echo btr_format_price_i18n($adulto['base']); ?></strong></span>
                                            <span>· <?php esc_html_e('Extra', 'born-to-ride-booking'); ?>: <strong class="bd-extra"><?php echo btr_format_price_i18n($adulto['extras']); ?></strong></span>
                                            <span>· <?php esc_html_e('Assicurazioni', 'born-to-ride-booking'); ?>: <strong class="bd-ins"><?php echo btr_format_price_i18n($adulto['insurance']); ?></strong></span>
                                            <span class="bd-children"<?php echo $adulto['assignedChildren'] > 0 ? '' : ' style="display:none;"'; ?>>· <?php esc_html_e('Figli assegnati', 'born-to-ride-booking'); ?>: <strong><?php echo btr_format_price_i18n($adulto['assignedChildren']); ?></strong></span>
                                            <?php if (!empty($adulto['extraItems'])): ?>
                                                <div class="btr-breakdown-list" aria-label="<?php esc_attr_e('Dettaglio costi extra', 'born-to-ride-booking'); ?>">
                                                    <span class="breakdown-title"><?php esc_html_e('Extra selezionati', 'born-to-ride-booking'); ?></span>
                                                    <ul class="breakdown-items">
                                                        <?php foreach ($adulto['extraItems'] as $item): ?>
                                                            <?php
                                                            $amount_value = floatval($item['amount']);
                                                            $amount_class = 'item-amount';
                                                            if ($amount_value < 0) {
                                                                $amount_class .= ' is-discount';
                                                            } elseif ($amount_value > 0) {
                                                                $amount_class .= ' is-addition';
                                                            }
                                                            ?>
                                                            <li>
                                                                <span class="item-label"><?php echo esc_html($item['label']); ?></span>
                                                                <span class="<?php echo esc_attr($amount_class); ?>"><?php echo btr_format_price_i18n($item['amount']); ?></span>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($adulto['insuranceItems'])): ?>
                                                <div class="btr-breakdown-list" aria-label="<?php esc_attr_e('Dettaglio assicurazioni', 'born-to-ride-booking'); ?>">
                                                    <span class="breakdown-title"><?php esc_html_e('Assicurazioni attive', 'born-to-ride-booking'); ?></span>
                                                    <ul class="breakdown-items">
                                                        <?php foreach ($adulto['insuranceItems'] as $item): ?>
                                                            <?php
                                                            $amount_value = floatval($item['amount']);
                                                            $amount_class = 'item-amount';
                                                            if ($amount_value < 0) {
                                                                $amount_class .= ' is-discount';
                                                            } elseif ($amount_value > 0) {
                                                                $amount_class .= ' is-addition';
                                                            }
                                                            ?>
                                                            <li>
                                                                <span class="item-label"><?php echo esc_html($item['label']); ?></span>
                                                                <span class="<?php echo esc_attr($amount_class); ?>"><?php echo btr_format_price_i18n($item['amount']); ?></span>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($adulto['assignedChildrenItems'])): ?>
                                                <div class="btr-breakdown-list" aria-label="<?php esc_attr_e('Minori collegati a questo pagante', 'born-to-ride-booking'); ?>">
                                                    <span class="breakdown-title"><?php esc_html_e('Quote bambini collegati', 'born-to-ride-booking'); ?></span>
                                                    <ul class="breakdown-items">
                                                        <?php foreach ($adulto['assignedChildrenItems'] as $item): ?>
                                                            <?php
                                                            $amount_value = floatval($item['amount']);
                                                            $amount_class = 'item-amount';
                                                            if ($amount_value < 0) {
                                                                $amount_class .= ' is-discount';
                                                            } elseif ($amount_value > 0) {
                                                                $amount_class .= ' is-addition';
                                                            }
                                                            ?>
                                                            <li>
                                                                <span class="item-label"><?php echo esc_html($item['label']); ?></span>
                                                                <span class="<?php echo esc_attr($amount_class); ?>"><?php echo btr_format_price_i18n($item['amount']); ?></span>
                                                            </li>
                                                        <?php endforeach; ?>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="btr-participant-controls">
                                    <div class="btr-shares-control">
                                        <label for="shares_<?php echo $adulto['index']; ?>" class="btr-text-sm"><?php esc_html_e('Quote', 'born-to-ride-booking'); ?></label>
                                        <input type="number" 
                                               class="participant-shares"
                                               name="group_participants[<?php echo $adulto['index']; ?>][shares]"
                                               id="shares_<?php echo $adulto['index']; ?>"
                                               min="0"
                                               max="<?php echo $totale_paganti; ?>"
                                               value="1"
                                               disabled
                                               data-index="<?php echo $adulto['index']; ?>"
                                               data-quota="<?php echo $quota_per_persona; ?>">
                                    </div>
                                    <div class="btr-participant-amount">
                                        <strong class="amount-total"><?php echo btr_format_price_i18n($adulto['personalTotal']); ?></strong>
                                        <small class="amount-label"><?php esc_html_e('Totale personale', 'born-to-ride-booking'); ?></small>
                                    </div>
                                </div>
                                <input type="hidden" name="group_participants[<?php echo $adulto['index']; ?>][name]" value="<?php echo esc_attr($adulto['name']); ?>">
                                <input type="hidden" name="group_participants[<?php echo $adulto['index']; ?>][email]" value="<?php echo esc_attr($adulto['email']); ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="btr-group-summary">
                            <div class="btr-summary-item"><span class="summary-label"><?php esc_html_e('Partecipanti selezionati', 'born-to-ride-booking'); ?></span><strong class="selected-participants">0</strong></div>
                            <div class="btr-summary-item"><span class="summary-label"><?php esc_html_e('Quote assegnate', 'born-to-ride-booking'); ?></span><strong class="total-shares">0</strong></div>
                            <div class="btr-summary-item"><span class="summary-label"><?php esc_html_e('Copertura', 'born-to-ride-booking'); ?></span><strong class="js-coverage-percentage">0%</strong></div>
                            <div class="btr-summary-item"><span class="summary-label"><?php esc_html_e('Totale assegnato', 'born-to-ride-booking'); ?></span><strong class="js-assigned-amount"><?php echo btr_format_price_i18n(0); ?></strong></div>
                            <div class="btr-summary-item"><span class="summary-label"><?php esc_html_e('Da assegnare', 'born-to-ride-booking'); ?></span><strong class="js-remaining-amount"><?php echo btr_format_price_i18n($totale_preventivo); ?></strong></div>
                        </div>
                                
                                <div class="group-payment-info">
                                    <p class="info-message">
                                        <span class="info-icon" aria-hidden="true">ℹ️</span>
                                        <?php esc_html_e('Ogni partecipante selezionato riceverà un link personalizzato per effettuare il proprio pagamento.', 'born-to-ride-booking'); ?>
                                    </p>
                                    <p class="success-message" id="shares-success" style="display: none;">
                                        <span class="success-icon" aria-hidden="true">✅</span>
                                        <span class="success-text"><?php esc_html_e('Ottimo! Le quote coprono tutti i partecipanti.', 'born-to-ride-booking'); ?></span>
                                    </p>
                                    <p class="warning-message" id="shares-warning" style="display: none;">
                                        <span class="warning-icon" aria-hidden="true">⚠️</span>
                                        <span class="warning-text"></span>
                                    </p>
                                </div>
                                
                                <?php 
                                // Include il template per l'assegnazione dettagliata se esiste
                                $detailed_template = BTR_PLUGIN_DIR . 'templates/parts/group-payment-detailed-assignment.php';
                                if (file_exists($detailed_template)) {
                                    // Prepara i dati necessari per il template
                                    $costi_extra_meta = get_post_meta($preventivo_id, '_costi_extra_durata', true) ?: [];
                                    include $detailed_template;
                                }
                                ?>
                                
                            <?php else: ?>
                                <p class="no-participants">
                                    <?php esc_html_e('Nessun partecipante adulto trovato. Completa prima i dati anagrafici.', 'born-to-ride-booking'); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </fieldset>
            
            <?php if ($bank_transfer_info): ?>
                <div class="btr-bank-transfer-info" role="note">
                    <span class="info-icon" aria-hidden="true">💡</span>
                    <p><?php echo wp_kses_post($bank_transfer_info); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="btr-form-actions">
                <a href="javascript:history.back()" class="button button-secondary">
                    <?php esc_html_e('Indietro', 'born-to-ride-booking'); ?>
                </a>
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Procedi al Checkout', 'born-to-ride-booking'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Gli stili CSS sono caricati dal file payment-selection-modern.css -->

<script>
jQuery(document).ready(function($) {
    // Gestione cambio piano
    $('input[name="payment_plan"]').on('change', function() {
        $('.btr-payment-option').removeClass('selected');
        $(this).closest('.btr-payment-option').addClass('selected');
        
        // Mostra/nascondi configurazioni basate sul piano selezionato
        if ($(this).val() === 'deposit_balance') {
            $('#deposit-config').slideDown();
            $('#group-payment-config').slideUp();
        } else if ($(this).val() === 'group_split') {
            $('#group-payment-config').slideDown(() => updateGroupTotals());
            $('#deposit-config').slideUp();
        } else {
            $('.deposit-config').slideUp();
            $('.group-payment-config').slideUp();
        }
    });
    
    const grandTotal = <?php echo floatval($totale_preventivo); ?>;
    const totalParticipants = <?php echo intval($totale_paganti); ?>;
    const totalTravellers = <?php echo intval($totale_persone); ?>;
    const quotaPerPerson = <?php echo floatval($quota_per_persona); ?>;
    const manualShares = new Set();

    const participantData = {};
    $('.btr-participant-selection').each(function() {
        const $row = $(this);
        const idx = $row.data('participant-index');
        participantData[idx] = {
            base: parseFloat($row.data('base') || '0'),
            extras: parseFloat($row.data('extra') || '0'),
            insurance: parseFloat($row.data('ins') || '0'),
            children: parseFloat($row.data('assigned-children') || '0'),
            total: parseFloat($row.data('personal-total') || '0')
        };
    });

    // Aggiorna valori caparra
    $('#deposit_percentage').on('input', function() {
        const percentage = parseInt($(this).val(), 10) || 0;
        const deposit = grandTotal * percentage / 100;
        const balance = grandTotal - deposit;
        const perPersonDeposit = totalParticipants > 0 ? deposit / totalParticipants : 0;
        const perPersonBalance = totalParticipants > 0 ? balance / totalParticipants : 0;
        const balancePercentage = Math.max(0, 100 - percentage);

        $('.deposit-value').text(percentage + '%');
        $('.deposit-amount').text(formatPrice(deposit));
        $('.balance-amount').text(formatPrice(balance));
        $('.deposit-per-person-amount').text(formatPrice(perPersonDeposit));
        $('.balance-per-person-amount').text(formatPrice(perPersonBalance));
        $('.deposit-percentage').text(percentage + '%');
        $('.balance-percentage').text(balancePercentage + '%');
        $('.deposit-progress-bar').css('width', percentage + '%');
        $('.deposit-progress').attr('aria-valuenow', percentage);

        $(this).attr('aria-valuenow', percentage);
    });

    // Formatta prezzo
    function formatPrice(amount) {
        return new Intl.NumberFormat('it-IT', {
            style: 'currency',
            currency: 'EUR'
        }).format(amount);
    }

    // Gestione partecipanti gruppo
    
    // Abilita/disabilita input quote quando checkbox è selezionato
    $('.participant-checkbox').on('change', function() {
        const index = $(this).data('index');
        const $row = $('.btr-participant-selection[data-participant-index="' + index + '"]');
        const sharesInput = $('#shares_' + index);

        if ($(this).is(':checked')) {
            sharesInput.prop('disabled', false);
            $row.addClass('is-selected');
        } else {
            sharesInput.prop('disabled', true).val(1);
            $row.removeClass('is-selected');
            manualShares.delete(index);
        }

        if (manualShares.size === 0) {
            distributeSharesEvenly();
        }

        updateGroupTotals();
    });

    // Aggiorna importi quando cambiano le quote
    $('.participant-shares').on('input', function() {
        const shares = parseInt($(this).val(), 10) || 0;
        const amount = shares * quotaPerPerson;

        manualShares.add($(this).data('index'));

        const $row = $(this).closest('.btr-participant-selection');
        $row.find('.amount-total').text(formatPrice(amount));

        updateGroupTotals();
    });

    $('.group-auto-assign').on('click', function() {
        manualShares.clear();
        autoAssignShares();
    });

    $('.group-reset').on('click', function(e) {
        e.preventDefault();
        resetGroupAssignments();
    });

    function resetGroupAssignments() {
        $('.participant-checkbox').prop('checked', false);
        $('.participant-shares').each(function() {
            $(this).val(1).prop('disabled', true);
            const index = $(this).data('index');
            const data = participantData[index] || null;
            const fallback = data ? data.total : 0;
            const $row = $('.btr-participant-selection[data-participant-index="' + index + '"]');
            $row.removeClass('is-selected');
            $row.data('computed-total', fallback);
            $row.find('.amount-total').text(formatPrice(fallback));
            manualShares.delete(index);
        });

        manualShares.clear();
        updateGroupTotals();
    }

    function autoAssignShares() {
        const $checkboxes = $('.participant-checkbox');
        if (!$checkboxes.length || totalParticipants === 0) {
            return;
        }

        const maxSelectable = Math.min(totalParticipants || $checkboxes.length, $checkboxes.length);

        $checkboxes.each(function(index) {
            const $checkbox = $(this);
            const participantIndex = $checkbox.data('index');
            const $sharesInput = $('#shares_' + participantIndex);
            const $row = $('.btr-participant-selection[data-participant-index="' + participantIndex + '"]');
            const data = participantData[participantIndex] || null;
            const fallback = data ? data.total : 0;

            if (index < maxSelectable) {
                $checkbox.prop('checked', true);
                $sharesInput.prop('disabled', false);
                $row.addClass('is-selected');
            } else {
                $checkbox.prop('checked', false);
                $sharesInput.prop('disabled', true).val(1);
                $row.removeClass('is-selected');
                $row.data('computed-total', fallback);
                $row.find('.amount-total').text(formatPrice(fallback));
                manualShares.delete(participantIndex);
            }
        });

        distributeSharesEvenly();
        updateGroupTotals();
    }

    function distributeSharesEvenly() {
        const $selected = $('.participant-checkbox:checked');
        const selectedCount = $selected.length;

        if (!selectedCount || totalParticipants === 0) {
            return;
        }

        let sharesRemaining = totalParticipants;
        const baseShare = Math.floor(totalParticipants / selectedCount);
        let remainder = totalParticipants % selectedCount;

        $selected.each(function(index) {
            const participantIndex = $(this).data('index');
            const $sharesInput = $('#shares_' + participantIndex);
            let sharesForThis = baseShare;

            if (remainder > 0) {
                sharesForThis += 1;
                remainder -= 1;
            }

            if (sharesForThis <= 0) {
                sharesForThis = 1;
            }

            if (sharesForThis > sharesRemaining) {
                sharesForThis = sharesRemaining;
            }

            sharesRemaining -= sharesForThis;

            $sharesInput.val(sharesForThis).prop('disabled', false);
            manualShares.delete(participantIndex);
        });

        if (sharesRemaining > 0) {
            const $firstSelected = $selected.first();
            if ($firstSelected.length) {
                const firstIndex = $firstSelected.data('index');
                const $firstInput = $('#shares_' + firstIndex);
                const currentShares = parseInt($firstInput.val(), 10) || 0;
                const updatedShares = currentShares + sharesRemaining;
                $firstInput.val(updatedShares);
                manualShares.delete(firstIndex);
            }
        }
    }

    // Funzione per aggiornare i totali del gruppo
    function updateGroupTotals() {
        let totalShares = 0;
        let totalAssignedAmount = 0;
        let selectedCount = 0;

        const selectedEntries = [];

        $('.participant-checkbox:checked').each(function() {
            selectedCount++;
            const index = $(this).data('index');
            const shares = parseInt($('#shares_' + index).val(), 10) || 0;
            totalShares += shares;
            const $row = $('.btr-participant-selection[data-participant-index="' + index + '"]');
            selectedEntries.push({
                index,
                shares,
                $row,
                data: participantData[index] || null
            });
        });

        const sharesMatch = selectedCount > 0 && totalShares === totalParticipants;

        const personalSum = selectedEntries.reduce((sum, entry) => {
            if (entry.data) {
                return sum + entry.data.total;
            }
            return sum;
        }, 0);

        const canUsePersonalTotals = selectedEntries.length > 0 && selectedEntries.every((entry) => entry.shares <= 1 && !!entry.data) && Math.abs(personalSum - grandTotal) <= 0.01 && sharesMatch;

        selectedEntries.forEach((entry) => {
            const { index, shares, $row, data } = entry;
            let computed;

            if (canUsePersonalTotals && data) {
                computed = data.total;
            } else if (sharesMatch && grandTotal > 0) {
                const shareRatio = totalShares > 0 ? shares / totalShares : 0;
                computed = grandTotal * shareRatio;
            } else if (data) {
                computed = data.total;
            } else {
                computed = shares * quotaPerPerson;
            }

            $row.data('computed-total', computed);
            $row.find('.amount-total').text(formatPrice(computed));
            totalAssignedAmount += computed;
        });

        if (canUsePersonalTotals) {
            totalAssignedAmount = personalSum;
        } else if (sharesMatch && grandTotal > 0 && selectedEntries.length) {
            const diff = grandTotal - totalAssignedAmount;
            if (Math.abs(diff) > 0.01) {
                const entry = selectedEntries[0];
                const current = parseFloat(entry.$row.data('computed-total')) || 0;
                const corrected = current + diff;
                entry.$row.data('computed-total', corrected);
                entry.$row.find('.amount-total').text(formatPrice(corrected));
                totalAssignedAmount += diff;
            } else {
                totalAssignedAmount = grandTotal;
            }
        }

        $('.btr-participant-selection').each(function() {
            const $row = $(this);
            if ($row.hasClass('is-selected')) {
                return;
            }
            const idx = $row.data('participant-index');
            const data = participantData[idx] || null;
            const fallback = data ? data.total : 0;
            $row.data('computed-total', fallback);
            $row.find('.amount-total').text(formatPrice(fallback));
        });

        const remainingAmount = Math.max(grandTotal - totalAssignedAmount, 0);
        const coverage = grandTotal > 0 ? Math.min((totalAssignedAmount / grandTotal) * 100, 100) : 0;
        const warningEl = $('#shares-warning');
        const successEl = $('#shares-success');
        const coverageComplete = sharesMatch && grandTotal > 0 && remainingAmount <= 1;

        successEl.hide();

        $('.selected-participants').text(selectedCount);
        $('.total-shares').text(totalShares);
        $('.js-assigned-amount').text(formatPrice(totalAssignedAmount));
        $('.js-remaining-amount').text(formatPrice(remainingAmount));
        $('.js-coverage-percentage').text(Math.round(coverage) + '%');
        $('.js-group-progress-bar').css('width', coverage + '%');
        $('.group-progress').attr('aria-valuenow', Math.round(coverage));
        $('.group-progress').toggleClass('is-complete', coverageComplete);

        if (!selectedCount) {
            clearGroupWarning();
            return;
        }

        if (sharesMatch) {
            showGroupWarning('success', '<?php echo esc_js(__('✔ Tutti i partecipanti risultano coperti.', 'born-to-ride-booking')); ?>');
        } else if (totalShares < totalParticipants) {
            showGroupWarning('warning', '<?php echo esc_js(__('Mancano quote per coprire tutti i partecipanti.', 'born-to-ride-booking')); ?>');
        } else if (totalShares > totalParticipants) {
            showGroupWarning('warning', '<?php echo esc_js(__('Le quote assegnate superano il numero totale di partecipanti.', 'born-to-ride-booking')); ?>');
        } else {
            clearGroupWarning();
        }
    }

// Inizializza stato gruppo
    resetGroupAssignments();

    // Gestione submit form
    $('#btr-payment-plan-selection').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $submitBtn = $form.find('button[type="submit"]');
        const selectedPlan = $('input[name="payment_plan"]:checked').val();
        
        // Debug: Mostra dati form
        console.log('Form data:', $form.serialize());
        console.log('Selected plan:', selectedPlan);
        
        // Validazione specifica per pagamento di gruppo
        if (selectedPlan === 'group_split') {
            const selectedParticipants = $('.participant-checkbox:checked').length;
            
            if (selectedParticipants === 0) {
                alert('<?php esc_attr_e('Seleziona almeno un partecipante per il pagamento di gruppo.', 'born-to-ride-booking'); ?>');
                return false;
            }
            
            // Verifica che le quote totali corrispondano
            let totalShares = 0;
            $('.participant-checkbox:checked').each(function() {
                const index = $(this).data('index');
                const shares = parseInt($('#shares_' + index).val()) || 0;
                totalShares += shares;
            });
            
            if (totalShares !== totalParticipants) {
                if (!confirm('<?php esc_attr_e('Le quote assegnate non corrispondono al numero totale di partecipanti. Vuoi continuare comunque?', 'born-to-ride-booking'); ?>')) {
                    return false;
                }
            }
        }
        
        // Disabilita pulsante
        $submitBtn.prop('disabled', true).text('<?php esc_attr_e('Elaborazione...', 'born-to-ride-booking'); ?>');
        
        // Invia form via AJAX
        $.ajax({
            url: $form.attr('action'),
            type: 'POST',
            data: $form.serialize(),
            success: function(response) {
                console.log('AJAX response:', response);
                if (response.success) {
                    // Redirect al checkout o pagina successiva
                    if (response.data && response.data.redirect_url) {
                        window.location.href = response.data.redirect_url;
                    } else {
                        window.location.href = '<?php echo wc_get_checkout_url(); ?>';
                    }
                } else {
                    console.error('AJAX error:', response);
                    const message = (response.data && response.data.message) 
                        ? response.data.message 
                        : '<?php esc_attr_e('Si è verificato un errore', 'born-to-ride-booking'); ?>';
                    alert(message);
                    $submitBtn.prop('disabled', false).text('<?php esc_attr_e('Procedi al Checkout', 'born-to-ride-booking'); ?>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX connection error:', {xhr, status, error});
                alert('<?php esc_attr_e('Errore di connessione. Riprova.', 'born-to-ride-booking'); ?>');
                $submitBtn.prop('disabled', false).text('<?php esc_attr_e('Procedi al Checkout', 'born-to-ride-booking'); ?>');
            }
        });
    });
});
</script>

<?php
// Non usiamo get_footer() perché siamo in uno shortcode
?>
