<?php
/**
 * Template per la pagina di selezione piano pagamento - Stile Riepilogo
 * Utilizza il design system unificato basato sul riepilogo preventivo
 * 
 * @package BornToRideBooking
 * @since 1.1.0
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
$calculator_totals_valid = false;

if (false === $preventivo_data) {
    // Recupera tutti i meta in una sola query
    $all_meta = get_post_meta($preventivo_id);
    
    // Meta critici utilizzati più volte
    $anagrafici_meta = maybe_unserialize($all_meta['_anagrafici_preventivo'][0] ?? '');
    if (!is_array($anagrafici_meta)) {
        $anagrafici_meta = [];
    }
    
    // Recupera e deserializza il riepilogo calcoli dettagliato
    $riepilogo_calcoli = maybe_unserialize($all_meta['_riepilogo_calcoli_dettagliato'][0] ?? '');
    
    // Prima controlla se ci sono i meta diretti per assicurazioni e costi extra
    $totale_assicurazioni_meta = floatval($all_meta['_totale_assicurazioni'][0] ?? 0);
    $totale_aggiunte_extra_meta = floatval($all_meta['_totale_aggiunte_extra'][0] ?? 0);
    $extra_costs_total_meta = floatval($all_meta['_extra_costs_total'][0] ?? 0);
    
    // Se non esiste il riepilogo calcoli, usa il calcolatore come fallback
    if (empty($riepilogo_calcoli) || !isset($riepilogo_calcoli['totali'])) {
        $calculator = BTR_Cost_Calculator::get_instance();
        $totals = $calculator->calculate_all_totals($preventivo_id, false);
        
        // Estrai i valori dal calcolatore
        $prezzo_base = $totals['totale_camere'];
        $totale_assicurazioni = $totale_assicurazioni_meta ?: $totals['totale_assicurazioni'];
        $totale_costi_extra = $totale_aggiunte_extra_meta ?: $extra_costs_total_meta ?: $totals['totale_costi_extra'];
        $totale_preventivo = $totals['totale_preventivo'];
        $extra_costs_detail = $totals['dettagli']['costi_extra'] ?? [];
        $totale_camere = $prezzo_base;
        $supplementi_extra = 0; // Nel fallback non abbiamo i supplementi extra separati
    } else {
        // Usa i dati dal riepilogo calcoli dettagliato
        $totali = $riepilogo_calcoli['totali'] ?? [];
        
        // Il totale camere include: prezzi base + supplementi base + notti extra
        // NON include i supplementi extra che vanno conteggiati a parte
        $totale_camere = ($totali['subtotale_prezzi_base'] ?? 0) + 
                        ($totali['subtotale_supplementi_base'] ?? 0) +
                        ($totali['subtotale_notti_extra'] ?? 0);
        
        // 🚨 CRITICAL FIX v1.0.218: DB meta fallback quando riepilogo è vuoto
        if ($totale_camere <= 0) {
            $db_totale_camere = floatval($all_meta['_pricing_totale_camere'][0] ?? 0);
            if ($db_totale_camere > 0) {
                $totale_camere = $db_totale_camere;
                btr_debug_log('[BTR] FIXED: Totale camere da DB: €' . number_format($totale_camere, 2));
            }
        }
        
        // I supplementi extra vanno aggiunti al totale finale ma non al totale camere
        $supplementi_extra = $totali['subtotale_supplementi_extra'] ?? 0;
        
        // 🔧 INSURANCE FIX v1.0.218: Force calcolo assicurazioni dai partecipanti
        $totale_assicurazioni = 0; // Reset per forzare calcolo dai partecipanti
        $totale_costi_extra = $totale_aggiunte_extra_meta ?: $extra_costs_total_meta;
        
        // SEMPRE estrai dai partecipanti per assicurazione completezza dati
        if (true) { // Forza sempre il calcolo
            $extra_costs_detail = [];
            
            // Estrai assicurazioni e costi extra dai partecipanti
            if (isset($riepilogo_calcoli['partecipanti'])) {
                foreach ($riepilogo_calcoli['partecipanti'] as $categoria => $dati_categoria) {
                    // Ogni categoria può avere costi_extra e assicurazioni
                    if (isset($dati_categoria['costi_extra']) && $totale_costi_extra == 0) {
                        foreach ($dati_categoria['costi_extra'] as $extra) {
                            $nome = $extra['nome'] ?? '';
                            $importo = $extra['importo'] ?? 0;
                            $totale_costi_extra += $importo;
                            
                            if (!isset($extra_costs_detail[$nome])) {
                                $extra_costs_detail[$nome] = 0;
                            }
                            $extra_costs_detail[$nome] += $importo;
                        }
                    }
                    
                    // 🔧 INSURANCE FIX: Sempre calcola assicurazioni dai partecipanti
                    if (isset($dati_categoria['assicurazioni'])) {
                        foreach ($dati_categoria['assicurazioni'] as $assicurazione) {
                            $totale_assicurazioni += $assicurazione['importo'] ?? 0;
                        }
                    }
                }
            }
        }
        
        // UNIFIED CALCULATOR v1.0.216: Single source of truth per calcoli
        if (class_exists('BTR_Unified_Calculator')) {
            // BACKUP dei valori originali per debug trace
            $totale_camere_orig = $totale_camere;
            $supplementi_extra_orig = $supplementi_extra;
            $totale_assicurazioni_orig = $totale_assicurazioni;
            $totale_costi_extra_orig = $totale_costi_extra;
            
            $calc_data = [
                'pricing_totale_camere' => $totale_camere,
                'supplementi_extra' => $supplementi_extra,
                'totale_notti_extra' => $supplementi_extra, // 🔧 FIX €3 Gap: Unified Calculator cerca totale_notti_extra
                'totale_assicurazioni' => $totale_assicurazioni,
                'totale_costi_extra' => $totale_costi_extra,
                'partecipanti' => $riepilogo_calcoli['partecipanti'] ?? [],
                'preventivo_id' => $preventivo_id
            ];
            
            // 🗺️ CACHE INVALIDATION: Clear cache dopo fix assicurazioni
            if (method_exists('BTR_Unified_Calculator', 'clear_cache')) {
                $calculator_instance = BTR_Unified_Calculator::get_instance();
                $calculator_instance->clear_cache();
            }
            
            $unified_result = BTR_Unified_Calculator::calculate($calc_data);
            
            // AGGIORNA tutti i componenti con i risultati dell'Unified Calculator
            $totale_camere = $unified_result['totale_camere'];
            $supplementi_extra = $unified_result['totale_supplementi'];
            $totale_assicurazioni = $unified_result['totale_assicurazioni'];
            $totale_costi_extra = $unified_result['totale_costi_extra'];
            $totale_preventivo = $unified_result['totale_finale'];
            
            // DEBUG TRACE: €3 Mystery Gap Detection v1.0.218
            $pre_unified_total = $totale_camere + $supplementi_extra + $totale_assicurazioni + $totale_costi_extra;
            $post_unified_total = $unified_result['totale_finale'];
            $gap_amount = abs($pre_unified_total - $post_unified_total);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                btr_debug_log('[BTR Payment Selection] 🔍 €3 MYSTERY GAP TRACE v1.0.218:');
                btr_debug_log('PRE-UNIFIED: €' . number_format($pre_unified_total, 2));
                btr_debug_log('- Camere (orig): €' . number_format($totale_camere_orig ?? $totale_camere, 2));
                btr_debug_log('- Supplementi (orig): €' . number_format($supplementi_extra_orig ?? $supplementi_extra, 2)); 
                btr_debug_log('- Assicurazioni (orig): €' . number_format($totale_assicurazioni_orig ?? $totale_assicurazioni, 2));
                btr_debug_log('- Costi extra (orig): €' . number_format($totale_costi_extra_orig ?? $totale_costi_extra, 2));
                btr_debug_log('POST-UNIFIED: €' . number_format($post_unified_total, 2));
                btr_debug_log('- Camere (unified): €' . number_format($unified_result['totale_camere'], 2));
                btr_debug_log('- Supplementi (unified): €' . number_format($unified_result['totale_supplementi'], 2)); 
                btr_debug_log('- Assicurazioni (unified): €' . number_format($unified_result['totale_assicurazioni'], 2));
                btr_debug_log('- Costi extra (unified): €' . number_format($unified_result['totale_costi_extra'], 2));
                btr_debug_log('🚨 GAP DETECTED: €' . number_format($gap_amount, 2) . ($gap_amount > 0.01 ? ' - CRITICAL!' : ' - OK'));
                btr_debug_log('- Calculation time: ' . ($unified_result['calculation_time_ms'] ?? 0) . 'ms');
            }
        } else {
            // FALLBACK: Vecchio sistema (da rimuovere dopo migrazione)
            $totale_preventivo = $totale_camere + $supplementi_extra + $totale_assicurazioni + $totale_costi_extra;
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                btr_debug_log('[BTR Payment Selection] FALLBACK CALCULATION:');
                btr_debug_log('[BTR Payment Selection] - Totale camere: €' . number_format($totale_camere, 2));
                btr_debug_log('[BTR Payment Selection] - Supplementi extra: €' . number_format($supplementi_extra, 2));
                btr_debug_log('[BTR Payment Selection] - Totale assicurazioni: €' . number_format($totale_assicurazioni, 2));
                btr_debug_log('[BTR Payment Selection] - Totale costi extra: €' . number_format($totale_costi_extra, 2));
                btr_debug_log('[BTR Payment Selection] - TOTALE CALCOLATO: €' . number_format($totale_preventivo, 2));
            }
        }
        
        // Verifica con il grand total dai meta se disponibile
        if (isset($all_meta['_btr_grand_total'][0])) {
            $grand_total = floatval($all_meta['_btr_grand_total'][0]);
            // Log per debug
            if (defined('WP_DEBUG') && WP_DEBUG && abs($grand_total - $totale_preventivo) > 0.01) {
                btr_debug_log('[BTR Payment Selection] Differenza tra totale calcolato (' . $totale_preventivo . ') e grand total (' . $grand_total . ')');
            }
        }
        
        // Assegna i valori per retrocompatibilità
        $prezzo_base = $totale_camere;
    }

    // Calcolo centralizzato tramite BTR_Price_Calculator per garantire coerenza con checkout
    $calculator_totals_valid = false;
    if (function_exists('btr_price_calculator')) {
        $calculator_instance = btr_price_calculator();
        $calculator_input = [
            'preventivo_id' => $preventivo_id,
            'anagrafici'    => $anagrafici_meta,
        ];

        $calculator_totals = $calculator_instance->calculate_preventivo_total($calculator_input);

        if (!empty($calculator_totals['valid'])) {
            $calculator_totals_valid   = true;
            $totale_preventivo         = round(floatval($calculator_totals['totale_finale']), 2);
            $totale_assicurazioni      = round(floatval($calculator_totals['assicurazioni']), 2);
            $totale_costi_extra        = round(floatval($calculator_totals['extra_costs']), 2);
            $supplementi_extra         = round(floatval($calculator_totals['supplementi'] ?? 0), 2);

            $totale_camere = round(floatval($calculator_totals['base']) + floatval($calculator_totals['extra_nights']), 2);
        }
    }

    // Recupera i dati anagrafici e altri meta necessari
    $anagrafici = $anagrafici_meta;
    $costi_extra_durata = maybe_unserialize($all_meta['_costi_extra_durata'][0] ?? '');
    
    // Usa gli helper per ottenere i dati dei partecipanti
    $participants = btr_get_participants_data($preventivo_id);
    
    // Estrai i valori necessari
    $preventivo_data = [
        'pacchetto_id' => $all_meta['_pacchetto_id'][0] ?? 0,
        'numero_adulti' => $participants['adults'],
        'numero_bambini' => $participants['children'],
        'numero_neonati' => $participants['infants'],
        'camere_selezionate' => maybe_unserialize($all_meta['_camere_selezionate'][0] ?? ''),
        'data_partenza' => $all_meta['_data_partenza'][0] ?? '',
        'data_ritorno' => $all_meta['_data_ritorno'][0] ?? '',
        'prezzo_base' => $prezzo_base,
        'totale_camere' => $totale_camere ?? $prezzo_base, // Aggiungo totale_camere
        'supplementi_extra' => $supplementi_extra ?? 0, // Aggiungo supplementi_extra
        'anagrafici' => $anagrafici,
        'costi_extra' => $costi_extra_durata,
        'totale_assicurazioni' => $totale_assicurazioni,
        'totale_costi_extra' => $totale_costi_extra,
        'totale_riduzioni' => abs(min($totale_costi_extra, 0)),
        'totale_aggiunte' => max($totale_costi_extra, 0),
        'extra_costs_detail' => $extra_costs_detail,
        'totale_preventivo' => $totale_preventivo,
        'riepilogo_calcoli' => $riepilogo_calcoli // Aggiungo anche i dati completi per debug
    ];
    
    // Log per debug se necessario
    if (defined('WP_DEBUG') && WP_DEBUG) {
        if (($participants['adults'] + $participants['children'] + $participants['infants']) == 0) {
            btr_debug_log('[BTR Payment Selection Riepilogo] Nessun partecipante trovato per preventivo ' . $preventivo_id);
        }
        if (!empty($riepilogo_calcoli)) {
            btr_debug_log('[BTR Payment Selection Riepilogo] Usando dati da _riepilogo_calcoli_dettagliato per preventivo ' . $preventivo_id);
            btr_debug_log('[BTR Payment Selection Riepilogo] Totale camere: €' . number_format($totale_camere, 2) . ', Assicurazioni: €' . number_format($totale_assicurazioni, 2) . ', Costi extra: €' . number_format($totale_costi_extra, 2) . ', Totale finale: €' . number_format($totale_preventivo, 2));
            if (isset($all_meta['_btr_grand_total'][0])) {
                btr_debug_log('[BTR Payment Selection Riepilogo] Grand total dai meta: €' . number_format(floatval($all_meta['_btr_grand_total'][0]), 2));
            }
        } else {
            btr_debug_log('[BTR Payment Selection Riepilogo] Fallback al calcolatore per preventivo ' . $preventivo_id);
        }
    }
    
    // Override totali da meta _btr_* se presenti e validati per allineamento esatto
    if (!$calculator_totals_valid && isset($all_meta['_btr_totale_generale'][0])) {
        $meta_total = floatval($all_meta['_btr_totale_generale'][0]);
        $calculated_total = $totale_preventivo;
        $difference = abs($meta_total - $calculated_total);
        
        // Solo se la differenza è ragionevole (< 10€) usa il meta, altrimenti mantieni il calcolo
        if ($difference < 10.00) {
            $preventivo_data['totale_preventivo'] = $meta_total;
            $totale_preventivo = $preventivo_data['totale_preventivo'];
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                btr_debug_log('[BTR Payment Selection] Usando meta total: €' . number_format($meta_total, 2) . ' (diff: €' . number_format($difference, 2) . ')');
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                btr_debug_log('[BTR Payment Selection] Meta total sospetto: €' . number_format($meta_total, 2) . ' vs calcolato: €' . number_format($calculated_total, 2) . ' (diff: €' . number_format($difference, 2) . ') - mantengo il calcolo');
            }
        }
    }
    if (!$calculator_totals_valid && isset($all_meta['_btr_totale_camere'][0])) {
        $preventivo_data['totale_camere'] = floatval($all_meta['_btr_totale_camere'][0]);
        $totale_camere = $preventivo_data['totale_camere'];
    }
    if (!$calculator_totals_valid && isset($all_meta['_btr_totale_costi_extra'][0])) {
        $preventivo_data['totale_costi_extra'] = floatval($all_meta['_btr_totale_costi_extra'][0]);
        $totale_costi_extra = $preventivo_data['totale_costi_extra'];
    }
    if (!$calculator_totals_valid && isset($all_meta['_btr_totale_assicurazioni'][0])) {
        $preventivo_data['totale_assicurazioni'] = floatval($all_meta['_btr_totale_assicurazioni'][0]);
        $totale_assicurazioni = $preventivo_data['totale_assicurazioni'];
    }

    // FIX v1.0.231: Recupera il valore corretto delle assicurazioni dal campo senza prefisso _btr
    if (!$calculator_totals_valid && isset($all_meta['_totale_assicurazioni'][0])) {
        $preventivo_data['totale_assicurazioni'] = floatval($all_meta['_totale_assicurazioni'][0]);
        $totale_assicurazioni = $preventivo_data['totale_assicurazioni'];
    }
    
    // Mantieni il segno originale dei costi extra (niente normalizzazione)

    // Cache per 5 minuti
    wp_cache_set($cache_key, $preventivo_data, 'btr_preventivi', 300);
}

// Estrai variabili per retrocompatibilità
extract($preventivo_data);

// FIX v1.0.234: Calcola correttamente il totale includendo TUTTE le componenti
if (!$calculator_totals_valid) {
    $totale_preventivo = $totale_camere + $totale_assicurazioni + $totale_costi_extra;
}

$pacchetto_title = get_the_title($pacchetto_id);

// Recupera configurazione pagamenti dal pacchetto
$payment_mode = get_post_meta($pacchetto_id, '_btr_payment_mode', true) ?: 'full';
$deposit_percentage = intval(get_post_meta($pacchetto_id, '_btr_deposit_percentage', true)) ?: 30;
$enable_group_payment = get_post_meta($pacchetto_id, '_btr_enable_group_payment', true);
$group_payment_threshold = intval(get_post_meta($pacchetto_id, '_btr_group_payment_threshold', true)) ?: 10;

// Calcola totale persone includendo neonati se presenti
$totale_persone = intval($numero_adulti) + intval($numero_bambini) + intval($numero_neonati ?? 0);

// Calcola quota per persona
$quota_per_persona = $totale_persone > 0 ? ($totale_preventivo / $totale_persone) : 0;

// Opzioni per il piano di pagamento - usa configurazione del pacchetto
$bank_transfer_enabled = get_option('btr_enable_bank_transfer_plans', true);
$bank_transfer_info = get_option('btr_bank_transfer_info', '');
// $deposit_percentage è già recuperato dalla configurazione del pacchetto sopra

// Verifica se esiste già un piano e recupera le sue impostazioni per pre-selezione
$existing_plan = class_exists('BTR_Payment_Plans') ? BTR_Payment_Plans::get_payment_plan($preventivo_id) : null;
$current_plan_type = 'full'; // Default
$current_deposit_percentage = $deposit_percentage; // Default

if ($existing_plan) {
    // Pre-seleziona l'opzione attuale invece di reindirizzare
    $current_plan_type = $existing_plan->plan_type ?? 'full';
    $current_deposit_percentage = $existing_plan->deposit_percentage ?? $deposit_percentage;
    
    // Log per debug
    if (defined('WP_DEBUG') && WP_DEBUG) {
        btr_debug_log('BTR Payment Selection: Piano esistente trovato - Tipo: ' . $current_plan_type . ', Deposito: ' . $current_deposit_percentage . '%');
    }
}

// Applica la logica di soglia per l'opzione gruppo
$enable_group = (bool) get_option('btr_enable_group_split', true);
$threshold = max(1, (int) get_option('btr_group_split_threshold', 10));
$can_show_group = $enable_group && ($totale_persone >= $threshold);



?>

<div class="btr-app btr-riepilogo-container">
    <!-- Header -->
    <div class="btr-riepilogo-header">
        <h1 class="btr-riepilogo-title"><?php esc_html_e('Selezione Metodo di Pagamento', 'born-to-ride-booking'); ?></h1>
        <p class="btr-riepilogo-subtitle">
            <?php printf(
                __('Scegli come preferisci pagare per il pacchetto %s', 'born-to-ride-booking'),
                '<strong>' . esc_html($pacchetto_title) . '</strong>'
            ); ?>
        </p>
    </div>

    <!-- Progress Indicator -->
    <div class="btr-section-card">
        <div class="btr-progress-steps">
            <div class="btr-step completed">
                <span class="btr-step-number">1</span>
                <span class="btr-step-label"><?php esc_html_e('Dati Anagrafici', 'born-to-ride-booking'); ?></span>
            </div>
            <div class="btr-step-connector completed"></div>
            <div class="btr-step current">
                <span class="btr-step-number">2</span>
                <span class="btr-step-label"><?php esc_html_e('Metodo Pagamento', 'born-to-ride-booking'); ?></span>
            </div>
            <div class="btr-step-connector"></div>
            <div class="btr-step">
                <span class="btr-step-number">3</span>
                <span class="btr-step-label"><?php esc_html_e('Checkout', 'born-to-ride-booking'); ?></span>
            </div>
        </div>
    </div>

    <!-- Riepilogo Preventivo -->
    <div class="btr-section-card">
        <div class="btr-section-header">
            <svg class="btr-section-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
            <h2 class="btr-section-title"><?php esc_html_e('Riepilogo della tua prenotazione', 'born-to-ride-booking'); ?></h2>
            <span class="btr-section-badge"><?php echo '#' . $preventivo_id; ?></span>
        </div>

        <div class="btr-info-grid">
            <!-- Dettagli viaggio -->
            <div class="btr-info-item">
                <svg class="btr-info-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <span class="btr-info-label"><?php esc_html_e('Date del viaggio', 'born-to-ride-booking'); ?></span>
                <span class="btr-info-value">
                    <?php 
                    $data_partenza_formatted = date_i18n('j F Y', strtotime($data_partenza));
                    $data_ritorno_formatted = date_i18n('j F Y', strtotime($data_ritorno));
                    echo $data_partenza_formatted . ' → ' . $data_ritorno_formatted;
                    ?>
                </span>
            </div>

            <div class="btr-info-item">
                <svg class="btr-info-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                <span class="btr-info-label"><?php esc_html_e('Partecipanti', 'born-to-ride-booking'); ?></span>
                <span class="btr-info-value">
                    <?php 
                    $partecipanti = [];
                    if ($numero_adulti > 0) $partecipanti[] = $numero_adulti . ' ' . _n('Adulto', 'Adulti', $numero_adulti, 'born-to-ride-booking');
                    if ($numero_bambini > 0) $partecipanti[] = $numero_bambini . ' ' . _n('Bambino', 'Bambini', $numero_bambini, 'born-to-ride-booking');
                    if ($numero_neonati > 0) $partecipanti[] = $numero_neonati . ' ' . _n('Neonato', 'Neonati', $numero_neonati, 'born-to-ride-booking');
                    
                    if (!empty($partecipanti)) {
                        echo implode(' + ', $partecipanti);
                    } else {
                        // Se non ci sono dati sui partecipanti, mostra il totale persone
                        echo $totale_persone . ' ' . _n('Persona', 'Persone', $totale_persone, 'born-to-ride-booking');
                    }
                    ?>
                </span>
            </div>

            <?php if (!empty($camere_selezionate) && is_array($camere_selezionate)): ?>
            <div class="btr-info-item">
                <svg class="btr-info-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                <span class="btr-info-label"><?php esc_html_e('Camere', 'born-to-ride-booking'); ?></span>
                <span class="btr-info-value">
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
            </div>
            <?php endif; ?>
        </div>

        <!-- Dettaglio prezzi -->
        <table class="btr-data-table btr-mt-4">
            <thead>
                <tr>
                    <th><?php esc_html_e('Descrizione', 'born-to-ride-booking'); ?></th>
                    <th class="text-right"><?php esc_html_e('Importo', 'born-to-ride-booking'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // I totali sono già stati calcolati nella sezione precedente
                // $totale_camere contiene già il valore corretto (dalla riga 60 o dal fallback)
                // $totale_assicurazioni e $totale_costi_extra sono già pronti
                ?>
                
                <!-- Totale Camere -->
                <tr>
                    <td><?php esc_html_e('Totale Camere', 'born-to-ride-booking'); ?></td>
                    <td class="text-right btr-price"><?php echo btr_format_price_i18n($totale_camere); ?></td>
                </tr>
                
                <?php 
                // FIX v1.0.230: Rimossi supplementi extra dalla visualizzazione - già inclusi nel totale camere
                ?>
                
                <!-- Totale assicurazioni -->
                <?php if ($totale_assicurazioni > 0): ?>
                <tr>
                    <td><?php esc_html_e('Totale assicurazioni', 'born-to-ride-booking'); ?></td>
                    <td class="text-right btr-price"><?php echo btr_format_price_i18n($totale_assicurazioni); ?></td>
                </tr>
                <?php endif; ?>
                
                <!-- Totale costi extra -->
                <?php if ($totale_costi_extra != 0): ?>
                <tr>
                    <td><?php esc_html_e('Totale costi extra', 'born-to-ride-booking'); ?></td>
                    <td class="text-right btr-price <?php echo $totale_costi_extra > 0 ? '' : 'btr-price-discount'; ?>">
                        <?php echo btr_format_price_i18n($totale_costi_extra); ?>
                    </td>
                </tr>
                <?php endif; ?>
                
                <!-- Riga separatore prima del totale finale -->
                <tr class="btr-separator-row">
                    <td colspan="2"><hr class="btr-separator"></td>
                </tr>
                
                <!-- Totale finale -->
                <tr class="btr-total-final-row">
                    <td><strong><?php esc_html_e('Totale finale', 'born-to-ride-booking'); ?></strong></td>
                    <td class="text-right btr-price"><strong><?php echo btr_format_price_i18n($totale_preventivo); ?></strong></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Form selezione piano -->
    <form id="btr-payment-plan-selection" 
          method="post" 
          action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
          data-total="<?php echo esc_attr($totale_preventivo); ?>"
          data-participants="<?php echo esc_attr(intval($totale_persone ?? 0)); ?>">
        <?php wp_nonce_field('btr_payment_plan_nonce', 'payment_nonce'); ?>
        <input type="hidden" name="action" value="btr_create_payment_plan">
        <input type="hidden" name="preventivo_id" value="<?php echo esc_attr($preventivo_id); ?>">

        <!-- Opzioni di pagamento -->
        <div class="btr-section-card">
            <div class="btr-section-header">
                <svg class="btr-section-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                </svg>
                <h2 class="btr-section-title"><?php esc_html_e('Scegli il metodo di pagamento', 'born-to-ride-booking'); ?></h2>
            </div>

            <div class="btr-payment-options">
                <?php if ($payment_mode === 'full' || $payment_mode === 'both'): ?>
                <!-- Pagamento completo -->
                <div class="btr-payment-option-card" data-plan="full">
                    <label class="btr-payment-option-label">
                        <input type="radio" 
                               name="payment_plan" 
                               id="plan_full" 
                               value="full" 
                               <?php checked($current_plan_type, 'full'); ?>
                               class="btr-payment-radio">
                        <div class="btr-payment-option-content">
                            <div class="btr-payment-option-header">
                                <svg class="btr-payment-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                <div>
                                    <h3 class="btr-payment-option-title"><?php esc_html_e('Pagamento Completo', 'born-to-ride-booking'); ?></h3>
                                    <p class="btr-payment-option-description"><?php esc_html_e('Paga l\'intero importo in un\'unica soluzione', 'born-to-ride-booking'); ?></p>
                                </div>
                            </div>
                        </div>
                    </label>
                </div>
                <?php endif; ?>

                <?php if ($payment_mode === 'deposit' || $payment_mode === 'both'): ?>
                <!-- Caparra + Saldo -->
                <div class="btr-payment-option-card" data-plan="deposit_balance">
                    <label class="btr-payment-option-label">
                        <input type="radio" 
                               name="payment_plan" 
                               id="plan_deposit" 
                               value="deposit_balance"
                               <?php checked($current_plan_type, 'deposit_balance'); ?>
                               class="btr-payment-radio">
                        <div class="btr-payment-option-content">
                            <div class="btr-payment-option-header">
                                <svg class="btr-payment-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                                <div>
                                    <h3 class="btr-payment-option-title"><?php esc_html_e('Caparra + Saldo', 'born-to-ride-booking'); ?></h3>
                                    <p class="btr-payment-option-description"><?php esc_html_e('Paga una caparra ora e il saldo successivamente', 'born-to-ride-booking'); ?></p>
                                </div>
                            </div>
                        </div>
                    </label>
                    
                    <div id="deposit-config" class="btr-deposit-config" style="display: none;">
                        <div class="btr-form-group">
                            <div class="btr-deposit-label-wrapper">
                                <label for="deposit_percentage" class="btr-form-label">
                                    <?php esc_html_e('Seleziona la percentuale di caparra', 'born-to-ride-booking'); ?>
                                </label>
                                <span class="btr-deposit-value"><?php echo $current_deposit_percentage; ?>%</span>
                            </div>
                            
                            <!-- Pulsanti preset rapidi -->
                            <div class="btr-deposit-presets">
                                <button type="button" class="btr-preset-btn <?php echo $current_deposit_percentage == 10 ? 'active' : ''; ?>" data-value="10"><span>10%</span></button>
                                <button type="button" class="btr-preset-btn <?php echo $current_deposit_percentage == 20 ? 'active' : ''; ?>" data-value="20"><span>20%</span></button>
                                <button type="button" class="btr-preset-btn <?php echo $current_deposit_percentage == 30 ? 'active' : ''; ?>" data-value="30"><span>30%</span></button>
                                <button type="button" class="btr-preset-btn <?php echo $current_deposit_percentage == 50 ? 'active' : ''; ?>" data-value="50"><span>50%</span></button>
                                <button type="button" class="btr-preset-btn <?php echo $current_deposit_percentage == 70 ? 'active' : ''; ?>" data-value="70"><span>70%</span></button>
                            </div>
                            
                            <!-- Range slider con wrapper -->
                            <div class="btr-range-wrapper">
                                <div class="btr-range-progress" style="width: <?php echo (($current_deposit_percentage - 10) / 80) * 100; ?>%"></div>
                                <input type="range" 
                                       id="deposit_percentage"
                                       name="deposit_percentage" 
                                       min="10" 
                                       max="90" 
                                       value="<?php echo $current_deposit_percentage; ?>" 
                                       step="5"
                                       class="btr-form-range"
                                       aria-label="<?php esc_attr_e('Percentuale caparra', 'born-to-ride-booking'); ?>"
                                       aria-valuemin="10"
                                       aria-valuemax="90"
                                       aria-valuenow="<?php echo $current_deposit_percentage; ?>">
                                <div class="btr-range-tooltip" style="left: <?php echo (($current_deposit_percentage - 10) / 80) * 100; ?>%"><?php echo $current_deposit_percentage; ?>%</div>
                            </div>
                            
                            <!-- Marcatori range -->
                            <div class="btr-range-markers">
                                <span class="btr-range-marker">10%</span>
                                <span class="btr-range-marker">30%</span>
                                <span class="btr-range-marker">50%</span>
                                <span class="btr-range-marker">70%</span>
                                <span class="btr-range-marker">90%</span>
                            </div>
                            
                            <!-- Display importi -->
                            <div class="btr-deposit-amounts">
                                <div class="btr-deposit-item">
                                    <span class="btr-deposit-label">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: inline-block; vertical-align: -2px; margin-right: 4px;">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                        </svg>
                                        <?php esc_html_e('Caparra', 'born-to-ride-booking'); ?>
                                    </span>
                                    <span class="btr-deposit-amount btr-price-total"><?php echo btr_format_price_i18n($totale_preventivo * $current_deposit_percentage / 100); ?></span>
                                </div>
                                <div class="btr-deposit-item">
                                    <span class="btr-deposit-label">
                                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: inline-block; vertical-align: -2px; margin-right: 4px;">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                        <?php esc_html_e('Saldo', 'born-to-ride-booking'); ?>
                                    </span>
                                    <span class="btr-balance-amount btr-price"><?php echo btr_format_price_i18n($totale_preventivo * (100 - $current_deposit_percentage) / 100); ?></span>
                                </div>
                            </div>
                            <div class="btr-deposit-per-person">
                                <span class="btr-deposit-label"><?php esc_html_e('Caparra per partecipante', 'born-to-ride-booking'); ?></span>
                                <strong class="deposit-per-person-amount"><?php echo btr_format_price_i18n(($total_amount = $totale_preventivo * $current_deposit_percentage / 100) / max(1, $totale_persone ?: 1)); ?></strong>
                            </div>
                            
                            <div class="btr-alert btr-alert-info btr-mt-3">
                                <svg class="btr-alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span><?php esc_html_e('Il saldo dovrà essere pagato prima della partenza secondo i termini concordati.', 'born-to-ride-booking'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Pagamento di Gruppo -->
                <?php 
                // Mostra opzione gruppo solo se abilitata nel pacchetto E sopra la soglia
                $show_group_option = $enable_group_payment && $totale_persone >= $group_payment_threshold;
                if ($show_group_option): 
                ?>
                <div class="btr-payment-option-card" data-plan="group_split">
                    <label class="btr-payment-option-label">
                        <input type="radio" 
                               name="payment_plan" 
                               id="plan_group" 
                               value="group_split"
                               <?php checked($current_plan_type, 'group_split'); ?>
                               class="btr-payment-radio">
                        <div class="btr-payment-option-content">
                            <div class="btr-payment-option-header">
                                <svg class="btr-payment-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                                <div>
                                    <h3 class="btr-payment-option-title"><?php esc_html_e('Pagamento di Gruppo', 'born-to-ride-booking'); ?></h3>
                                    <p class="btr-payment-option-description"><?php esc_html_e('Ogni partecipante paga la propria quota individualmente', 'born-to-ride-booking'); ?></p>
                                </div>
                            </div>
                        </div>
                    </label>

                    <!-- Configurazione pagamento di gruppo -->
                    <div id="group-payment-config" class="btr-group-config" style="display: none;">
                        <?php 
                        // Recupera gli adulti dal preventivo
                        // Costruisci dataset per-calcolo per partecipante
                        $adulti_paganti = [];
                        $bambini_neonati = [];
                        $adult_unit = 0.0;
                        if (!empty($riepilogo_calcoli['partecipanti']['adulti'])) {
                            $ad_q = intval($riepilogo_calcoli['partecipanti']['adulti']['quantita'] ?? 0);
                            $ad_tot = floatval($riepilogo_calcoli['partecipanti']['adulti']['totale'] ?? 0);
                            if ($ad_q>0) { $adult_unit = $ad_tot / $ad_q; }
                        }
                        if ($adult_unit <= 0 && $numero_adulti > 0) { $adult_unit = $totale_camere / max(1,$numero_adulti); }

                        // Struttura dati completa per JavaScript
                        $booking_data_complete = [
                            'grandTotal' => $totale_preventivo,
                            'breakdown' => [
                                'rooms' => $totale_camere,
                                'insurance' => $totale_assicurazioni,
                                'extras' => $totale_costi_extra,
                                'supplements' => $supplementi_extra ?? 0
                            ],
                            'participants' => [
                                'adults' => [],
                                'children' => []
                            ],
                            'assignments' => [] // Assegnazioni originali bambini->adulti
                        ];

                        // Helper per costi extra/assicurazioni per persona - v1.0.239
                        $get_person_addons = function($p, $person_index) use ($preventivo_id) {
                            global $wpdb;
                            
                            // PRIORITÀ 1: Controlla meta fields individuali per questa persona
                            $meta_query = $wpdb->prepare(
                                "SELECT meta_key, meta_value FROM {$wpdb->postmeta} 
                                 WHERE post_id = %d AND meta_key LIKE %s",
                                $preventivo_id,
                                "_anagrafico_{$person_index}_extra_%"
                            );
                            $meta_results = $wpdb->get_results($meta_query);
                            
                            $sum_extra = 0.0;
                            $sum_ins = 0.0;
                            $extra_items = [];
                            $insurance_items = [];
                            
                            // Verifica meta fields individuali
                            if (!empty($meta_results)) {
                                foreach ($meta_results as $meta) {
                                    if (strpos($meta->meta_key, '_price') !== false) {
                                        $extra_name = str_replace(['_anagrafico_'.$person_index.'_extra_', '_price'], '', $meta->meta_key);
                                        $selected_key = "_anagrafico_{$person_index}_extra_{$extra_name}_selected";
                                        
                                        $is_selected = get_post_meta($preventivo_id, $selected_key, true);
                                        if ($is_selected === 'yes' || $is_selected === '1') {
                                            $price = floatval($meta->meta_value);
                                            $label = ucwords(str_replace('_', ' ', $extra_name));
                                            if (strpos($extra_name, 'assicuraz') !== false) {
                                                $sum_ins += $price;
                                                $insurance_items[] = [
                                                    'label' => $label,
                                                    'amount' => $price,
                                                ];
                                            } else {
                                                $sum_extra += $price;
                                                $extra_items[] = [
                                                    'label' => $label,
                                                    'amount' => $price,
                                                ];
                                            }
                                        }
                                    }
                                }
                            }
                            
                            // PRIORITÀ 2: Se non ci sono meta individuali, usa array serializzati come fallback
                            if (empty($meta_results)) {
                                if (!empty($p['costi_extra_dettagliate']) && is_array($p['costi_extra_dettagliate'])) {
                                    foreach ($p['costi_extra_dettagliate'] as $slug => $info) {
                                        $selected = !empty($p['costi_extra'][$slug]) || (!empty($info['importo']) && floatval($info['importo'])!=0);
                                        if ($selected) {
                                            $amount = floatval($info['importo'] ?? 0);
                                            $sum_extra += $amount;
                                            $extra_items[] = [
                                                'label' => $info['nome'] ?? $info['descrizione'] ?? $slug,
                                                'amount' => $amount,
                                            ];
                                        }
                                    }
                                }
                            }
                            
                            // FASE 3: Verifica assicurazioni (v1.0.239 - separata da costi extra)
                            if (!empty($p['assicurazioni_dettagliate']) && is_array($p['assicurazioni_dettagliate'])) {
                                foreach ($p['assicurazioni_dettagliate'] as $slug => $info) {
                                    $selected = !empty($p['assicurazioni'][$slug]);
                                    if ($selected) {
                                        $amount = floatval($info['importo'] ?? 0);
                                        $sum_ins += $amount;
                                        $insurance_items[] = [
                                            'label' => $info['descrizione'] ?? $info['nome'] ?? $slug,
                                            'amount' => $amount,
                                        ];
                                    }
                                }
                            }
                            
                            return [$sum_extra, $sum_ins, $extra_items, $insurance_items];
                        };

                        // Recupera assegnazioni originali dalle camere del preventivo
                        $original_assignments = [];
                        $camere_selezionate = get_post_meta($preventivo_id, '_camere_selezionate', true);
                        
                        // DEBUG: Verifica dati camere raw dal database
                        btr_debug_log('DEBUG ROOM INFO - Raw _camere_selezionate data: ' . print_r($camere_selezionate, true));
                        btr_debug_log('DEBUG ROOM INFO - Is array: ' . (is_array($camere_selezionate) ? 'yes' : 'no'));
                        btr_debug_log('DEBUG ROOM INFO - Count: ' . (is_array($camere_selezionate) ? count($camere_selezionate) : 'not an array'));
                        
                        // v1.0.233: Nuova logica per assegnazioni basata su distribuzione camere
                        // Poiché i dati non hanno 'occupanti', distribuiamo basandoci su adulti/bambini per camera
                        if (!empty($camere_selezionate) && is_array($camere_selezionate)) {
                            // Prima, identifica tutti gli adulti e bambini
                            $adulti_indices = [];
                            $bambini_indices = [];
                            
                            foreach ($anagrafici as $index => $persona) {
                                $tipo = strtolower(trim($persona['tipo_persona'] ?? ''));
                                $fascia = strtolower(trim($persona['fascia'] ?? ''));
                                $is_adult = ($tipo === 'adulto') || ($fascia === 'adulto');
                                
                                if ($is_adult) {
                                    $adulti_indices[] = $index;
                                } else {
                                    $bambini_indices[] = $index;
                                }
                            }
                            
                            // Distribuisci i bambini nelle camere in base alla capacità
                            $bambino_cursor = 0;
                            $adulto_cursor = 0;
                            
                            foreach ($camere_selezionate as $camera_index => $camera) {
                                $tipo_camera = $camera['tipo'] ?? 'Camera';
                                $num_adulti_camera = intval($camera['adulti'] ?? 0);
                                $num_bambini_camera = intval($camera['bambini'] ?? 0) + intval($camera['neonati'] ?? 0);
                                
                                // Assegna adulti a questa camera
                                $adulti_in_camera = [];
                                for ($i = 0; $i < $num_adulti_camera && $adulto_cursor < count($adulti_indices); $i++) {
                                    $adulti_in_camera[] = $adulti_indices[$adulto_cursor];
                                    $adulto_cursor++;
                                }
                                
                                // Assegna bambini agli adulti di questa camera
                                if (!empty($adulti_in_camera) && $num_bambini_camera > 0) {
                                    $adulto_principale = $adulti_in_camera[0]; // Usa il primo adulto della camera
                                    
                                    for ($i = 0; $i < $num_bambini_camera && $bambino_cursor < count($bambini_indices); $i++) {
                                        $bambino_index = $bambini_indices[$bambino_cursor];
                                        $original_assignments[$bambino_index] = [
                                            'adulto' => $adulto_principale,
                                            'tipo_camera' => $tipo_camera,
                                            'adulto_nome' => isset($anagrafici[$adulto_principale]) ? 
                                                trim(($anagrafici[$adulto_principale]['nome'] ?? '') . ' ' . ($anagrafici[$adulto_principale]['cognome'] ?? '')) : ''
                                        ];
                                        btr_debug_log('[DEBUG ASSIGNMENTS] Bambino ' . $bambino_index . ' assegnato ad adulto ' . $adulto_principale . ' in camera ' . $tipo_camera);
                                        $bambino_cursor++;
                                    }
                                }
                            }
                        }

                        if (!empty($anagrafici) && is_array($anagrafici)) {
                            foreach ($anagrafici as $index => $persona) {
                                $tipo = strtolower(trim($persona['tipo_persona'] ?? ''));
                                $fascia = strtolower(trim($persona['fascia'] ?? ''));
                                $is_adult = ($tipo === 'adulto') || ($fascia === 'adulto');
                                
                                // FIX v1.0.241: Calcolo età corretto per date future/invalide
                                if (!$is_adult && !empty($persona['data_nascita'])) {
                                    try {
                                        $birth_date = new DateTime($persona['data_nascita']);
                                        $now = new DateTime();
                                        
                                        // Controlla se la data di nascita è nel futuro
                                        if ($birth_date <= $now) {
                                            $age = $now->diff($birth_date)->y;
                                            $is_adult = ($age >= 18);
                                            btr_debug_log("Calcolo età per {$persona['nome']}: data_nascita={$persona['data_nascita']}, età=$age, adulto=" . ($is_adult ? 'SI' : 'NO'));
                                        } else {
                                            btr_debug_log("Data di nascita futura per {$persona['nome']}: {$persona['data_nascita']} - ignoro calcolo età");
                                        }
                                    } catch (Exception $e) {
                                        btr_debug_log("Errore calcolo età per {$persona['nome']}: " . $e->getMessage());
                                    }
                                }
                                $label = trim(($persona['nome'] ?? '') . ' ' . ($persona['cognome'] ?? ''));
                                
                                // Fix v1.0.234: Usa etichetta fascia dinamica quando nome è vuoto
                                $fascia_label = '';
                                if (!$is_adult && empty($label) && !empty($fascia)) {
                                    // Recupera etichetta dinamica dal post meta
                                    $fascia_label = get_post_meta($preventivo_id, "_child_label_{$fascia}", true);
                                    if ($fascia_label) {
                                        $label = $fascia_label; // Usa l'etichetta della fascia come label
                                        btr_debug_log("Fix v1.0.234: Usando etichetta dinamica per bambino index $index, fascia $fascia: $fascia_label");
                                    }
                                }
                                
                                // DEBUG: Verifica dati persona
                                if (!$is_adult) {
                                    btr_debug_log('DEBUG CHILD NAME - Index ' . $index . ':');
                                    btr_debug_log('  - Raw persona data: ' . print_r($persona, true));
                                    btr_debug_log('  - Computed label: "' . $label . '"');
                                    if ($fascia_label) {
                                        btr_debug_log('  - Using dynamic fascia label: "' . $fascia_label . '"');
                                    }
                                }
                                
                                list($sum_extra, $sum_ins, $extra_items, $insurance_items) = $get_person_addons($persona, $index);
                                
                                // DEBUG v1.0.240: Traccia classificazione partecipanti
                                btr_debug_log("=== DEBUG PARTECIPANTE $index ===");
                                btr_debug_log("Nome: $label | Tipo: $tipo | Fascia: $fascia");
                                btr_debug_log("is_adult: " . ($is_adult ? 'TRUE' : 'FALSE') . " | label: '$label'");
                                btr_debug_log("Condizione (\$is_adult && \$label): " . (($is_adult && $label) ? 'PASSA - AGGIUNTO A ADULTI_PAGANTI' : 'NON PASSA'));
                                
                                if ($is_adult && $label) {
                                    $adult_data = [
                                        'index' => $index,
                                        'nome' => $label,
                                        'email' => $persona['email'] ?? '',
                                        'base' => $adult_unit,
                                        'extra' => $sum_extra,
                                        'ins' => $sum_ins,
                                        'extra_items' => $extra_items,
                                        'insurance_items' => $insurance_items,
                                    ];
                                    $adulti_paganti[] = $adult_data;
                                    btr_debug_log("✅ AGGIUNTO ADULTO PAGANTE: Index $index, Nome: $label");
                                    
                                    // Aggiungi ai dati completi
                                    $booking_data_complete['participants']['adults'][] = [
                                        'index' => $index,
                                        'name' => $label,
                                        'email' => $persona['email'] ?? '',
                                        'baseCost' => $adult_unit,
                                        'extrasCost' => $sum_extra,
                                        'insuranceCost' => $sum_ins,
                                        'personalTotal' => $adult_unit + $sum_extra + $sum_ins,
                                        'assignedChildren' => [],
                                        'extraItems' => $extra_items,
                                        'insuranceItems' => $insurance_items,
                                    ];
                                } else {
                                    // Stima costo bambino: usa fascia dal riepilogo, altrimenti 0 + eventuali extra/assicurazioni
                                    $child_unit = 0.0;
                                    $child_notte_extra = 0.0;
                                    $map = [ 'f1'=>'bambini_f1','f2'=>'bambini_f2','f3'=>'bambini_f3','f4'=>'bambini_f4','bambino'=>'bambini' ];
                                    $key = $map[$fascia] ?? '';
                                    if ($key && !empty($riepilogo_calcoli['partecipanti'][$key])) {
                                        $cq=intval($riepilogo_calcoli['partecipanti'][$key]['quantita'] ?? 0);
                                        $ct=floatval($riepilogo_calcoli['partecipanti'][$key]['totale'] ?? 0);
                                        if ($cq>0) { $child_unit = $ct/$cq; }

                                        // Aggiungi notti extra se presenti
                                        $notte_extra_tot = floatval($riepilogo_calcoli['partecipanti'][$key]['subtotale_notte_extra'] ?? 0);
                                        if ($cq>0 && $notte_extra_tot > 0) { $child_notte_extra = $notte_extra_tot/$cq; }
                                    }

                                    $child_data = [
                                        'index' => $index,
                                        'label' => $label ?: ('Persona #'.($index+1)),
                                        'total' => $child_unit + $child_notte_extra + $sum_extra + $sum_ins,
                                        'fascia' => $fascia,
                                        'original_adult' => isset($original_assignments[$index]) ? $original_assignments[$index]['adulto'] : null,
                                        'original_camera' => isset($original_assignments[$index]) ? $original_assignments[$index]['tipo_camera'] : null,
                                        'original_adult_nome' => isset($original_assignments[$index]) ? $original_assignments[$index]['adulto_nome'] : null,
                                        'extra_items' => $extra_items,
                                        'insurance_items' => $insurance_items,
                                    ];
                                    
                                    // Debug assegnazioni
                                    if (isset($original_assignments[$index])) {
                                        btr_debug_log('[DEBUG] Bambino index ' . $index . ' (' . $label . ') dovrebbe essere assegnato ad adulto index ' . $original_assignments[$index]['adulto'] . ' in camera ' . $original_assignments[$index]['tipo_camera']);
                                    } else {
                                        btr_debug_log('[DEBUG] Bambino index ' . $index . ' (' . $label . ') NON ha assegnazione originale');
                                    }
                                    
                                    $bambini_neonati[] = $child_data;
                                    
                                    // Aggiungi ai dati completi
                                    $booking_data_complete['participants']['children'][] = [
                                        'index' => $index,
                                        'name' => $child_data['label'],
                                        'fascia' => $fascia,
                                        'baseCost' => $child_unit,
                                        'extrasCost' => $sum_extra,
                                        'insuranceCost' => $sum_ins,
                                        'totalCost' => $child_unit + $sum_extra + $sum_ins,
                                        'originalAssignment' => isset($original_assignments[$index]) ? $original_assignments[$index]['adulto'] : null,
                                        'originalCamera' => isset($original_assignments[$index]) ? $original_assignments[$index]['tipo_camera'] : null,
                                        'originalAdultName' => isset($original_assignments[$index]) ? $original_assignments[$index]['adulto_nome'] : null,
                                        'extraItems' => $extra_items,
                                        'insuranceItems' => $insurance_items,
                                    ];
                                    
                                    // Salva assegnazione originale
                                    if (isset($original_assignments[$index])) {
                                        $booking_data_complete['assignments'][$index] = $original_assignments[$index];
                                    }
                                }
                            }
                        }
                        
                        // DEBUG v1.0.240: Log finale dopo classificazione
                        btr_debug_log("=== DEBUG FINALE CLASSIFICAZIONE v1.0.240 ===");
                        btr_debug_log("Totale persone: " . count($anagrafici));
                        btr_debug_log("Totale adulti_paganti: " . count($adulti_paganti));
                        btr_debug_log("Totale bambini_neonati: " . count($bambini_neonati));
                        btr_debug_log("Dettaglio adulti_paganti:");
                        foreach ($adulti_paganti as $idx => $adult) {
                            $total = ($adult['base'] ?? 0) + ($adult['extra'] ?? 0) + ($adult['ins'] ?? 0);
                            btr_debug_log("  - [$idx] Index: {$adult['index']}, Nome: {$adult['nome']}, Base: {$adult['base']}, Extra: {$adult['extra']}, Ins: {$adult['ins']}, Total: $total");
                        }
                        btr_debug_log("Dettaglio bambini_neonati:");
                        foreach ($bambini_neonati as $idx => $child) {
                            btr_debug_log("  - [$idx] Index: {$child['index']}, Label: {$child['label']}, Fascia: {$child['fascia']}");
                        }
                        ?>

                        <?php if (!empty($adulti_paganti)): ?>
                            <?php
                            // Debug adulti_paganti
                            btr_debug_log('[DEBUG ADULTI_PAGANTI] Numero adulti: ' . count($adulti_paganti));
                            foreach ($adulti_paganti as $adulto) {
                                btr_debug_log('[DEBUG ADULTI_PAGANTI] Adulto index: ' . $adulto['index'] . ' - Nome: ' . $adulto['nome']);
                            }
                            ?>

                            <!-- Dashboard Riepilogo Pagamento Gruppo -->
                            <div class="group-dashboard" aria-live="polite">
                                <div class="dashboard-card total">
                                    <span class="dashboard-label"><?php esc_html_e('Totale prenotazione', 'born-to-ride-booking'); ?></span>
                                    <span class="dashboard-value"><?php echo btr_format_price_i18n($totale_preventivo); ?></span>
                                    <span class="dashboard-subtext">
                                        <?php esc_html_e('Paganti attesi', 'born-to-ride-booking'); ?>
                                        <strong class="total-participants-count"><?php echo esc_html($totale_persone); ?></strong>
                                    </span>
                                </div>
                                <div class="dashboard-card assigned">
                                    <span class="dashboard-label"><?php esc_html_e('Quote assegnate', 'born-to-ride-booking'); ?></span>
                                    <span class="dashboard-value js-assigned-amount"><?php echo btr_format_price_i18n(0); ?></span>
                                    <span class="dashboard-subtext">
                                        <strong class="total-shares">0</strong> / <span class="total-participants-count"><?php echo esc_html($totale_persone); ?></span> <?php esc_html_e('quote', 'born-to-ride-booking'); ?>
                                    </span>
                                </div>
                                <div class="dashboard-card remaining">
                                    <span class="dashboard-label"><?php esc_html_e('Da assegnare', 'born-to-ride-booking'); ?></span>
                                    <span class="dashboard-value js-remaining-amount"><?php echo btr_format_price_i18n($totale_preventivo); ?></span>
                                </div>
                            </div>

                            <h4 class="btr-h4 btr-mb-3"><?php esc_html_e('Seleziona chi effettuerà il pagamento', 'born-to-ride-booking'); ?></h4>
                            <p class="btr-text-sm btr-text-muted btr-mb-3"><?php esc_html_e('Puoi selezionare quali adulti pagheranno e per quante quote ciascuno.', 'born-to-ride-booking'); ?></p>
                            
                            <div class="btr-participants-list">
                                <?php foreach ($adulti_paganti as $adulto): ?>
                                <div class="btr-participant-selection" data-participant-index="<?php echo esc_attr($adulto['index']); ?>"
                                     data-base="<?php echo esc_attr(number_format($adulto['base'],2,'.','')); ?>"
                                     data-extra="<?php echo esc_attr(number_format($adulto['extra'],2,'.','')); ?>"
                                     data-ins="<?php echo esc_attr(number_format($adulto['ins'],2,'.','')); ?>"
                                     data-personal-total="<?php echo esc_attr(number_format(($adulto['base'] + $adulto['extra'] + $adulto['ins']),2,'.','')); ?>"
                                     data-assigned-children="0"
                                >
                                    <div class="btr-participant-info">
                                        <input type="checkbox" 
                                               class="btr-form-check-input participant-checkbox"
                                               name="group_participants[<?php echo $adulto['index']; ?>][selected]"
                                               id="participant_<?php echo $adulto['index']; ?>"
                                               value="1"
                                               data-index="<?php echo $adulto['index']; ?>">
                                        <label for="participant_<?php echo $adulto['index']; ?>" class="btr-participant-label">
                                            <strong><?php echo esc_html($adulto['nome']); ?></strong>
                                            <?php if ($adulto['email']): ?>
                                                <small class="btr-text-muted"><?php echo esc_html($adulto['email']); ?></small>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                    <div class="btr-participant-shares">
                                        <label class="btr-text-sm"><?php esc_html_e('Quote:', 'born-to-ride-booking'); ?></label>
                                        <input type="number" 
                                               class="btr-form-control btr-form-control-sm participant-shares"
                                               name="group_participants[<?php echo $adulto['index']; ?>][shares]"
                                               id="shares_<?php echo $adulto['index']; ?>"
                                               min="0"
                                               max="<?php echo $totale_persone; ?>"
                                               value="1"
                                               disabled
                                               data-index="<?php echo $adulto['index']; ?>"
                                               data-quota="<?php echo $quota_per_persona; ?>">
                                        <span class="btr-participant-amount btr-price"><?php echo btr_format_price_i18n($adulto['base'] + $adulto['extra'] + $adulto['ins']); ?></span>
                                    </div>
                                    <div class="btr-participant-breakdown btr-text-xs btr-text-muted">
                                        <span><?php esc_html_e('Base', 'born-to-ride-booking'); ?>: <strong class="bd-base"><?php echo btr_format_price_i18n($adulto['base']); ?></strong></span>
                                        <span> · <?php esc_html_e('Extra', 'born-to-ride-booking'); ?>: <strong class="bd-extra"><?php echo btr_format_price_i18n($adulto['extra']); ?></strong></span>
                                        <span> · <?php esc_html_e('Ass.', 'born-to-ride-booking'); ?>: <strong class="bd-ins"><?php echo btr_format_price_i18n($adulto['ins']); ?></strong></span>
                                        <span class="bd-child d-none"></span>
                                    </div>
                                    <?php if (!empty($adulto['extra_items']) || !empty($adulto['insurance_items'])): ?>
                                    <div class="btr-participant-details">
                                        <?php if (!empty($adulto['extra_items'])): ?>
                                            <div class="btr-detail-group">
                                                <span class="btr-detail-label"><?php esc_html_e('Servizi extra', 'born-to-ride-booking'); ?></span>
                                                <div class="btr-detail-badges">
                                                    <?php foreach ($adulto['extra_items'] as $item): ?>
                                                        <span class="btr-detail-badge btr-detail-extra">
                                                            <?php echo esc_html($item['label']); ?> · <span class="btr-detail-amount"><?php echo btr_format_price_i18n($item['amount']); ?></span>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($adulto['insurance_items'])): ?>
                                            <div class="btr-detail-group">
                                                <span class="btr-detail-label"><?php esc_html_e('Assicurazioni', 'born-to-ride-booking'); ?></span>
                                                <div class="btr-detail-badges">
                                                    <?php foreach ($adulto['insurance_items'] as $item): ?>
                                                        <span class="btr-detail-badge btr-detail-ins">
                                                            <?php echo esc_html($item['label']); ?> · <span class="btr-detail-amount"><?php echo btr_format_price_i18n($item['amount']); ?></span>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                    <input type="hidden" 
                                           name="group_participants[<?php echo $adulto['index']; ?>][name]"
                                           value="<?php echo esc_attr($adulto['nome']); ?>">
                                    <input type="hidden" 
                                           name="group_participants[<?php echo $adulto['index']; ?>][email]"
                                           value="<?php echo esc_attr($adulto['email']); ?>">
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <?php if (!empty($bambini_neonati)): ?>
                            <div class="btr-assignments btr-mt-3 children-assignment-section">
                                <h4 class="btr-h4"><?php esc_html_e('Assegna bambini/neonati a un adulto pagante', 'born-to-ride-booking'); ?></h4>
                                
                                <!-- Pannello di Stato Assegnazioni -->
                                <div class="btr-assignment-status-panel">
                                    <div class="btr-assignment-status-header">
                                        <span class="btr-status-icon">
                                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                                                <path d="M10 18a8 8 0 100-16 8 8 0 000 16zM10 7v4M10 14h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                                            </svg>
                                        </span>
                                        <span class="btr-status-text"><?php esc_html_e('Stato Assegnazioni', 'born-to-ride-booking'); ?></span>
                                    </div>
                                    <div class="btr-assignment-status-content">
                                        <div class="btr-status-item">
                                            <span class="btr-status-label"><?php esc_html_e('Bambini da assegnare:', 'born-to-ride-booking'); ?></span>
                                            <span class="btr-status-value btr-children-total"><?php echo count($bambini_neonati); ?></span>
                                        </div>
                                        <div class="btr-status-item">
                                            <span class="btr-status-label"><?php esc_html_e('Assegnati:', 'born-to-ride-booking'); ?></span>
                                            <span class="btr-status-value btr-children-assigned">0</span>
                                        </div>
                                        <div class="btr-status-item">
                                            <span class="btr-status-label"><?php esc_html_e('Non assegnati:', 'born-to-ride-booking'); ?></span>
                                            <span class="btr-status-value btr-children-unassigned"><?php echo count($bambini_neonati); ?></span>
                                        </div>
                                    </div>
                                    <div class="btr-assignment-progress">
                                        <div class="btr-progress-bar">
                                            <div class="btr-progress-fill" style="width: 0%"></div>
                                        </div>
                                        <span class="btr-progress-text">0%</span>
                                    </div>
                                    <div class="btr-assignment-message">
                                        <p class="btr-message-warning">
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                                <path d="M8 0a8 8 0 100 16A8 8 0 008 0zm0 12a1 1 0 110-2 1 1 0 010 2zm0-3a1 1 0 01-1-1V4a1 1 0 012 0v4a1 1 0 01-1 1z"/>
                                            </svg>
                                            <?php esc_html_e('Tutti i bambini devono essere assegnati a un adulto prima di procedere', 'born-to-ride-booking'); ?>
                                        </p>
                                    </div>
                                </div>

                                <?php foreach ($bambini_neonati as $child): ?>
                                <div class="btr-assignment-row" data-child-index="<?php echo esc_attr($child['index']); ?>" data-child-total="<?php echo esc_attr(number_format($child['total'],2,'.','')); ?>">
                                    <div class="btr-assignment-row-content">
                                        <div class="btr-child-info">
                                            <span class="btr-child-status-icon" data-status="unassigned">
                                                <svg class="icon-unassigned" width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                                                    <path d="M10 0C4.48 0 0 4.48 0 10s4.48 10 10 10 10-4.48 10-10S15.52 0 10 0zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm-1-4h2v2H9v-2zm0-10h2v8H9V4z"/>
                                                </svg>
                                                <svg class="icon-assigned" width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                                                    <path d="M10 0C4.48 0 0 4.48 0 10s4.48 10 10 10 10-4.48 10-10S15.52 0 10 0zm-2 15l-5-5 1.41-1.41L8 12.17l7.59-7.59L17 6l-9 9z"/>
                                                </svg>
                                            </span>
                                            <div>
                                                <label class="btr-text-sm"><?php echo esc_html($child['label']); ?></label>
                                                <?php 
                                                // DEBUG: Verifica dati disponibili nell'UI
                                                btr_debug_log('DEBUG ROOM INFO - UI data for child ' . $child['index'] . ':');
                                                btr_debug_log('  - Label in UI: ' . $child['label']);
                                                btr_debug_log('  - Has original_camera: ' . (!empty($child['original_camera']) ? 'yes: ' . $child['original_camera'] : 'no'));
                                                btr_debug_log('  - Has original_adult_nome: ' . (!empty($child['original_adult_nome']) ? 'yes: ' . $child['original_adult_nome'] : 'no'));
                                                ?>
                                                <?php if (!empty($child['original_camera']) && !empty($child['original_adult_nome'])): ?>
                                                    <div class="btr-original-assignment" style="font-size: 11px; color: #6c757d; margin-top: 2px;">
                                                        <?php echo esc_html(sprintf(__('Camera %s con %s', 'born-to-ride-booking'), $child['original_camera'], $child['original_adult_nome'])); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($child['extra_items']) || !empty($child['insurance_items'])): ?>
                                                    <div class="btr-child-meta">
                                                        <?php if (!empty($child['extra_items'])): ?>
                                                            <div class="btr-detail-badges">
                                                                <?php foreach ($child['extra_items'] as $item): ?>
                                                                    <span class="btr-detail-badge btr-detail-extra">
                                                                        <?php echo esc_html($item['label']); ?> · <span class="btr-detail-amount"><?php echo btr_format_price_i18n($item['amount']); ?></span>
                                                                    </span>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if (!empty($child['insurance_items'])): ?>
                                                            <div class="btr-detail-badges">
                                                                <?php foreach ($child['insurance_items'] as $item): ?>
                                                                    <span class="btr-detail-badge btr-detail-ins">
                                                                        <?php echo esc_html($item['label']); ?> · <span class="btr-detail-amount"><?php echo btr_format_price_i18n($item['amount']); ?></span>
                                                                    </span>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <select class="btr-form-control btr-child-assignment" name="child_assignment[<?php echo esc_attr($child['index']); ?>]">
                                            <option value=""><?php esc_html_e('Seleziona adulto', 'born-to-ride-booking'); ?></option>
                                            <?php foreach ($adulti_paganti as $adulto): ?>
                                                <?php 
                                                $is_selected = ($child['original_adult'] !== null && $child['original_adult'] == $adulto['index']);
                                                btr_debug_log('[DEBUG SELECT] Child ' . $child['index'] . ' - Adulto ' . $adulto['index'] . ' - Original: ' . $child['original_adult'] . ' - Selected: ' . ($is_selected ? 'YES' : 'NO'));
                                                ?>
                                                <option value="<?php echo esc_attr($adulto['index']); ?>" <?php echo $is_selected ? 'selected' : ''; ?>><?php echo esc_html($adulto['nome']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="btr-assignment-feedback">
                                        <span class="btr-feedback-text"></span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <p class="btr-text-sm btr-text-muted"><?php esc_html_e('Le quote degli adulti selezionati aumenteranno in base alle assegnazioni.', 'born-to-ride-booking'); ?></p>
                            </div>
                            <?php endif; ?>

                            <div class="btr-addon-summary-card btr-mt-4">
                                <h4 class="btr-h4"><?php esc_html_e('Servizi e assicurazioni per partecipante', 'born-to-ride-booking'); ?></h4>
                                <p class="btr-text-sm btr-text-muted btr-mb-3"><?php esc_html_e('Panoramica dei costi collegati a ciascun partecipante pagante.', 'born-to-ride-booking'); ?></p>
                                <div class="btr-addon-grid">
                                    <?php foreach ($adulti_paganti as $adulto): ?>
                                        <div class="btr-addon-card">
                                            <div class="btr-addon-card-header">
                                                <strong><?php echo esc_html($adulto['nome']); ?></strong>
                                                <?php if ($adulto['email']): ?>
                                                    <span class="btr-addon-email"><?php echo esc_html($adulto['email']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <ul class="btr-addon-metric-list">
                                                <li>
                                                    <span><?php esc_html_e('Quota base', 'born-to-ride-booking'); ?></span>
                                                    <strong><?php echo btr_format_price_i18n($adulto['base']); ?></strong>
                                                </li>
                                                <?php if ($adulto['extra'] > 0): ?>
                                                    <li>
                                                        <span><?php esc_html_e('Servizi extra', 'born-to-ride-booking'); ?></span>
                                                        <strong><?php echo btr_format_price_i18n($adulto['extra']); ?></strong>
                                                    </li>
                                                <?php endif; ?>
                                                <?php if ($adulto['ins'] > 0): ?>
                                                    <li>
                                                        <span><?php esc_html_e('Assicurazioni', 'born-to-ride-booking'); ?></span>
                                                        <strong><?php echo btr_format_price_i18n($adulto['ins']); ?></strong>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                            <?php if (!empty($adulto['extra_items']) || !empty($adulto['insurance_items'])): ?>
                                                <div class="btr-addon-detail">
                                                    <?php if (!empty($adulto['extra_items'])): ?>
                                                        <div class="btr-detail-group">
                                                            <span class="btr-detail-label"><?php esc_html_e('Servizi extra', 'born-to-ride-booking'); ?></span>
                                                            <div class="btr-detail-badges">
                                                                <?php foreach ($adulto['extra_items'] as $item): ?>
                                                                    <span class="btr-detail-badge btr-detail-extra">
                                                                        <?php echo esc_html($item['label']); ?> · <span class="btr-detail-amount"><?php echo btr_format_price_i18n($item['amount']); ?></span>
                                                                    </span>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($adulto['insurance_items'])): ?>
                                                        <div class="btr-detail-group">
                                                            <span class="btr-detail-label"><?php esc_html_e('Assicurazioni', 'born-to-ride-booking'); ?></span>
                                                            <div class="btr-detail-badges">
                                                                <?php foreach ($adulto['insurance_items'] as $item): ?>
                                                                    <span class="btr-detail-badge btr-detail-ins">
                                                                        <?php echo esc_html($item['label']); ?> · <span class="btr-detail-amount"><?php echo btr_format_price_i18n($item['amount']); ?></span>
                                                                    </span>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="btr-group-summary">
                                <div class="btr-group-total">
                                    <span><?php esc_html_e('Totale quote assegnate:', 'born-to-ride-booking'); ?></span>
                                    <span><strong class="total-shares">0</strong> / <?php echo $totale_persone; ?></span>
                                </div>
                                <div class="btr-group-total">
                                    <span><?php esc_html_e('Partecipanti selezionati:', 'born-to-ride-booking'); ?></span>
                                    <span><strong class="selected-participants">0</strong> <?php esc_html_e('selezionati', 'born-to-ride-booking'); ?></span>
                                </div>                                <div class="btr-group-total">
                                    <span><?php esc_html_e('Totale importo:', 'born-to-ride-booking'); ?></span>
                                    <span class="btr-price-total total-amount"><?php echo btr_format_price_i18n(0); ?></span>
                                </div>
                            </div>

                            <div class="btr-alert btr-alert-primary btr-mt-3">
                                <svg class="btr-alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span><?php esc_html_e('Ogni partecipante selezionato riceverà un link personalizzato per effettuare il proprio pagamento.', 'born-to-ride-booking'); ?></span>
                            </div>

                            <div class="btr-alert btr-alert-warning btr-mt-3" id="shares-warning" style="display: none;">
                                <svg class="btr-alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                                <span class="warning-text"></span>
                            </div>
                        <?php else: ?>
                            <div class="btr-empty-state">
                                <svg class="btr-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                                </svg>
                                <p><?php esc_html_e('Nessun partecipante adulto trovato. Completa prima i dati anagrafici.', 'born-to-ride-booking'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($bank_transfer_info): ?>
            <div class="btr-alert btr-alert-primary">
                <svg class="btr-alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div><?php echo wp_kses_post($bank_transfer_info); ?></div>
            </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="btr-action-footer">
            <div class="btr-btn-group">
                <a href="javascript:history.back()" class="btr-btn btr-btn-secondary btr-btn-lg">
                    <?php esc_html_e('Indietro', 'born-to-ride-booking'); ?>
                </a>
                <button type="submit" class="btr-btn btr-btn-primary btr-btn-lg">
                    <?php esc_html_e('Procedi al Checkout', 'born-to-ride-booking'); ?>
                    <svg class="btr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                    </svg>
                </button>
            </div>
        </div>
    </form>
</div>

<style>
/* Progress Steps */
.btr-progress-steps {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
}

.btr-step {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    position: relative;
}

.btr-step-number {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    background: var(--btr-gray-200);
    color: var(--btr-gray-600);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    transition: all var(--btr-transition);
}

.btr-step.completed .btr-step-number {
    background: var(--btr-success);
    color: white;
}

.btr-step.current .btr-step-number {
    background: var(--btr-primary);
    color: white;
    box-shadow: 0 0 0 4px rgba(0, 151, 197, 0.2);
}

.btr-step-label {
    font-weight: 500;
    color: var(--btr-gray-600);
}

.btr-step.completed .btr-step-label,
.btr-step.current .btr-step-label {
    color: var(--btr-gray-900);
}

/* Group Dashboard */
.group-dashboard {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
    margin-bottom: 2rem;
}

.dashboard-card {
    background: white;
    border: 1px solid var(--btr-gray-200);
    border-radius: var(--btr-radius);
    padding: 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    position: relative;
    transition: all var(--btr-transition);
}

.dashboard-card:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    transform: translateY(-1px);
}

.dashboard-card.total {
    background: var(--btr-primary);
    color: white;
    border-color: var(--btr-primary);
}

.dashboard-card.assigned {
    background: var(--btr-gray-50);
}

.dashboard-card.remaining {
    background: #fef9e7;
    border-color: #f1c40f;
}

.dashboard-label {
    font-size: 0.875rem;
    font-weight: 500;
    opacity: 0.9;
}

.dashboard-card.total .dashboard-label {
    opacity: 1;
}

.dashboard-value {
    font-size: 1.75rem;
    font-weight: 700;
    line-height: 1;
}

.dashboard-subtext {
    font-size: 0.75rem;
    opacity: 0.8;
    margin-top: 0.25rem;
}

.dashboard-card.total .dashboard-subtext {
    opacity: 0.9;
}

@media (max-width: 768px) {
    .group-dashboard {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    .dashboard-card {
        padding: 1rem;
    }

    .dashboard-value {
        font-size: 1.5rem;
    }
}

.btr-step-connector {
    width: 3rem;
    height: 2px;
    background: var(--btr-gray-200);
    margin: 0 1rem;
}

.btr-step-connector.completed {
    background: var(--btr-success);
}

/* Payment Options */
.btr-payment-options {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.btr-payment-option-card {
    border: 2px solid var(--btr-gray-200);
    border-radius: var(--btr-radius);
    transition: all var(--btr-transition);
}

.btr-payment-option-card:hover {
    border-color: var(--btr-gray-300);
}

.btr-payment-option-card:has(.btr-payment-radio:checked) {
    border-color: var(--btr-primary);
    background: var(--btr-primary-lightest);
}

.btr-payment-option-label {
    display: block;
    padding: 1.5rem;
    cursor: pointer;
}

.btr-payment-radio {
    position: absolute;
    opacity: 0;
}

.btr-payment-option-content {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.btr-payment-option-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex: 1;
}

.btr-payment-icon {
    width: 2.5rem;
    height: 2.5rem;
    color: var(--btr-primary);
}

.btr-payment-option-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--btr-gray-900);
    margin: 0 0 0.25rem;
}

.btr-payment-option-description {
    font-size: 0.875rem;
    color: var(--btr-gray-600);
    margin: 0;
}

/* Deposit Config */
.btr-deposit-config {
    padding: 1.5rem;
    background: var(--btr-gray-50);
    border-top: 1px solid var(--btr-gray-200);
}

.btr-form-range {
    width: 100%;
    margin: 1rem 0;
}

.btr-deposit-value {
    float: right;
    font-weight: 600;
    color: var(--btr-primary);
}

.btr-deposit-amounts {
    display: flex;
    justify-content: space-between;
    gap: 2rem;
    margin-top: 1rem;
}

.btr-deposit-item {
    flex: 1;
    text-align: center;
}

.btr-deposit-label {
    display: block;
    font-size: 0.875rem;
    color: var(--btr-gray-600);
    margin-bottom: 0.25rem;
}

/* Group Payment Config */
.btr-group-config {
    padding: 1.5rem;
    background: var(--btr-gray-50);
    border-top: 1px solid var(--btr-gray-200);
}

.btr-participants-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.btr-participant-selection {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: white;
    border: 1px solid var(--btr-gray-200);
    border-radius: var(--btr-radius);
}

.btr-participant-info {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.btr-participant-label {
    display: flex;
    flex-direction: column;
    cursor: pointer;
}

.btr-participant-shares {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btr-participant-shares .btr-form-control {
    width: 60px;
}

.btr-participant-amount {
    min-width: 100px;
    text-align: right;
}

.btr-group-summary {
    margin-top: 1.5rem;
    padding: 1rem;
    background: white;
    border: 1px solid var(--btr-gray-200);
    border-radius: var(--btr-radius);
}

.btr-group-total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
}

.btr-group-total:first-child {
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--btr-gray-200);
}

.btr-group-total:last-child {
    padding-top: 0.75rem;
}

/* Alert Icons */
.btr-alert {
    display: flex;
    gap: 0.75rem;
}

.btr-alert-icon {
    width: 1.25rem;
    height: 1.25rem;
    flex-shrink: 0;
}

/* Button Icon */
.btr-btn-icon {
    margin-left: 0.5rem;
}

/* Responsive */
@media (max-width: 768px) {
    .btr-progress-steps {
        padding: 1rem;
    }
    
    .btr-step-label {
        display: none;
    }
    
    .btr-step-connector {
        width: 2rem;
        margin: 0 0.5rem;
    }
    
    .btr-payment-option-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .btr-deposit-amounts {
        flex-direction: column;
        gap: 1rem;
    }
    
    .btr-participant-selection {
        flex-direction: column;
        align-items: stretch;
    }
    
    .btr-participant-shares {
        justify-content: space-between;
        width: 100%;
    }
}

/* Stili per la tabella dei prezzi */
.btr-separator-row td {
    padding: 0.5rem 0;
}

.btr-separator {
    border: none;
    border-top: 1px solid #e0e0e0;
    margin: 0;
}

.btr-total-final-row td {
    font-size: 1.1rem;
    padding-top: 0.75rem;
}

.btr-data-table .btr-price-discount {
    color: #dc3545;
}

.btr-data-table .text-right {
    text-align: right;
}

/* Assignment Status Panel Styles */
.btr-assignment-status-panel {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    transition: all 0.3s ease;
}

.btr-assignment-status-panel.all-assigned {
    background: #d4edda;
    border-color: #c3e6cb;
}

.btr-assignment-status-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
    font-weight: 600;
    color: #343a40;
}

.btr-status-icon {
    display: flex;
    align-items: center;
    color: #6c757d;
    transition: color 0.3s ease;
}

.all-assigned .btr-status-icon {
    color: #28a745;
}

.btr-assignment-status-content {
    display: flex;
    gap: 30px;
    margin-bottom: 15px;
}

.btr-status-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.btr-status-label {
    font-size: 14px;
    color: #6c757d;
}

.btr-status-value {
    font-size: 18px;
    font-weight: 700;
    color: #343a40;
    transition: color 0.3s ease;
}

.btr-children-assigned {
    color: #28a745;
}

.btr-children-unassigned {
    color: #dc3545;
}

.all-assigned .btr-children-unassigned {
    color: #28a745;
}

.btr-assignment-progress {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.btr-progress-bar {
    flex: 1;
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
    position: relative;
}

.btr-progress-fill {
    height: 100%;
    background: #dc3545;
    transition: width 0.3s ease, background-color 0.3s ease;
    border-radius: 4px;
}

.btr-progress-fill.complete {
    background: #28a745;
}

.btr-progress-text {
    font-size: 14px;
    font-weight: 600;
    color: #343a40;
    min-width: 40px;
}

.btr-assignment-message {
    margin-top: 10px;
}

.btr-message-warning,
.btr-message-success {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 15px;
    border-radius: 6px;
    font-size: 14px;
    margin: 0;
}

.btr-message-warning {
    background: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.btr-message-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

/* Assignment Row Improvements */
.btr-assignment-row {
    margin-bottom: 15px;
    padding: 15px;
    background: white;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.btr-assignment-row.assigned {
    border-color: #28a745;
    background: #f8fff9;
}

.btr-assignment-row.unassigned {
    border-color: #dc3545;
    background: #fff8f8;
}

.btr-assignment-row-content {
    display: flex;
    align-items: center;
    gap: 15px;
}

.btr-child-info {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
}

.btr-child-status-icon {
    display: flex;
    align-items: center;
    width: 24px;
    height: 24px;
}

.btr-child-status-icon svg {
    transition: all 0.3s ease;
}

.btr-child-status-icon[data-status="unassigned"] .icon-assigned,
.btr-child-status-icon[data-status="assigned"] .icon-unassigned {
    display: none;
}

.btr-child-status-icon[data-status="unassigned"] .icon-unassigned {
    color: #dc3545;
}

.btr-child-status-icon[data-status="assigned"] .icon-assigned {
    color: #28a745;
}

.btr-assignment-row select.btr-child-assignment {
    flex: 0 0 250px;
    transition: border-color 0.3s ease;
}

.btr-assignment-row.assigned select.btr-child-assignment {
    border-color: #28a745;
}

.btr-assignment-row.unassigned select.btr-child-assignment {
    border-color: #dc3545;
}

.btr-assignment-feedback {
    margin-top: 8px;
}

.btr-feedback-text {
    font-size: 13px;
    font-style: italic;
    color: #6c757d;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.btr-feedback-text.show {
    opacity: 1;
}

/* Animazioni */
@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.btr-assignment-row.just-assigned {
    animation: pulse 0.3s ease;
}

/* Disable submit button when children not assigned */
#btr-payment-plan-selection button[type="submit"].disabled-children {
    opacity: 0.6;
    cursor: not-allowed;
    background: #6c757d;
}

#btr-payment-plan-selection button[type="submit"].disabled-children:hover {
    background: #6c757d;
}

/* Payment Summary Box */
.payment-summary-box {
    animation: slideIn 0.3s ease;
}

.payment-summary-box h4 {
    font-size: 1.1rem;
    font-weight: 600;
}

.payment-summary-box h5 {
    color: #6c757d;
    font-size: 0.9rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

#summary-per-person {
    font-weight: 700;
    color: #28a745 !important;
}

.btr-participant-amount {
    transition: all 0.3s ease;
}

.btr-participant-amount.price-updated {
    transform: scale(1.1);
    color: #28a745;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<script>
// Pass booking data from PHP to JavaScript
window.bookingDataComplete = <?php echo json_encode($booking_data_complete ?? []); ?>;

const btrDebugEnabled = Boolean(window.btrDebugMode || false);
const logDebug = (...args) => {
    if (!btrDebugEnabled || typeof window.console === 'undefined') {
        return;
    }
    console.log(...args);
};

jQuery(document).ready(function($) {
    // Funzione unificata per gestire il toggle delle configurazioni
    function togglePaymentConfig(selectedPlan, forceShow) {
        var depositConfig = $('#deposit-config');
        var groupConfig = $('#group-payment-config');
        
        if (selectedPlan === 'deposit_balance') {
            groupConfig.slideUp(300);
            if (!depositConfig.is(':visible') || forceShow) {
                depositConfig.slideDown(300);
            }
        } else if (selectedPlan === 'group_split') {
            depositConfig.slideUp(300);
            if (!groupConfig.is(':visible') || forceShow) {
                groupConfig.slideDown(300);
            }
        } else {
            depositConfig.slideUp(300);
            groupConfig.slideUp(300);
        }
    }
    
    // Handler unificato per cambio radio button
    $('input[name="payment_plan"]').on('change', function() {
        togglePaymentConfig($(this).val(), true);
    });
    
    // Aggiorna valori caparra
    const totalAmount = <?php echo isset($totale_preventivo) ? floatval($totale_preventivo) : 0; ?>;
    
    // Funzione per aggiornare tutti gli elementi UI
    function updateDepositUI(percentage) {
        const deposit = totalAmount * percentage / 100;
        const balance = totalAmount - deposit;
        const progressWidth = ((percentage - 10) / 80) * 100;
        
        // Aggiorna valore percentuale
        $('.btr-deposit-value').text(percentage + '%');
        $('.btr-deposit-amount').text(formatPrice(deposit));
        $('.btr-balance-amount').text(formatPrice(balance));
        
        // Aggiorna barra di progresso
        $('.btr-range-progress').css('width', progressWidth + '%');
        
        // Aggiorna tooltip
        $('.btr-range-tooltip').css('left', progressWidth + '%').text(percentage + '%');
        
        // Aggiorna stato pulsanti preset
        $('.btr-preset-btn').removeClass('active');
        $('.btr-preset-btn[data-value="' + percentage + '"]').addClass('active');
        
        // Aggiorna aria
        $('#deposit_percentage').attr('aria-valuenow', percentage);
    }
    
    // Gestione input range
    $('#deposit_percentage').on('input', function() {
        const percentage = parseInt($(this).val());
        updateDepositUI(percentage);
    });
    
    // Gestione pulsanti preset
    $('.btr-preset-btn').on('click', function() {
        const value = parseInt($(this).data('value'));
        $('#deposit_percentage').val(value);
        updateDepositUI(value);
    });
    
    // Mostra tooltip su hover/focus
    $('#deposit_percentage').on('focus mouseenter', function() {
        $('.btr-range-tooltip').addClass('visible');
    }).on('blur mouseleave', function() {
        $('.btr-range-tooltip').removeClass('visible');
    });
    
    // Migliora l'accessibilità con tastiera
    $('#deposit_percentage').on('keydown', function(e) {
        const currentValue = parseInt($(this).val());
        let newValue = currentValue;
        
        switch(e.keyCode) {
            case 37: // Freccia sinistra
            case 40: // Freccia giù
                e.preventDefault();
                newValue = Math.max(10, currentValue - 5);
                break;
            case 39: // Freccia destra
            case 38: // Freccia su
                e.preventDefault();
                newValue = Math.min(90, currentValue + 5);
                break;
            case 36: // Home
                e.preventDefault();
                newValue = 10;
                break;
            case 35: // End
                e.preventDefault();
                newValue = 90;
                break;
        }
        
        if (newValue !== currentValue) {
            $(this).val(newValue);
            updateDepositUI(newValue);
        }
    });
    
    // Formatta prezzo
    window.formatPrice = function formatPrice(amount) {
        return new Intl.NumberFormat('it-IT', {
            style: 'currency',
            currency: 'EUR'
        }).format(amount);
    }
    
    // Gestione partecipanti gruppo
    // Cache-busting: timestamp <?php echo time(); ?> per forzare reload JS
    const totalParticipants = <?php echo isset($totale_persone) ? intval($totale_persone) : 0; ?>;
    const quotaPerPerson = <?php echo isset($quota_per_persona) ? floatval($quota_per_persona) : 0; ?>;
    
    // Debug: Log valori per verificare cache-busting
    logDebug('[BTR Cache-Bust <?php echo date('H:i:s'); ?>] totalParticipants:', totalParticipants);
    logDebug('[BTR Cache-Bust <?php echo date('H:i:s'); ?>] Adulti: <?php echo intval($numero_adulti ?? 0); ?>, Bambini: <?php echo intval($numero_bambini ?? 0); ?>, Neonati: <?php echo intval($numero_neonati ?? 0); ?>');
    
    /**
     * IMPLEMENTAZIONE updateCounters() - Aggiorna contatori bambini assegnati/non assegnati
     * Segue WordPress best practices per DOM manipulation
     */
    window.updateCounters = function updateCounters() {
        const totalChildren = $('.btr-child-assignment').length;
        let assignedCount = 0;
        let unassignedCount = 0;
        
        // Conta bambini usando event delegation per elementi dinamici
        $('.btr-child-assignment').each(function() {
            const value = $(this).val();
            if (value && value !== '' && value !== '0') {
                assignedCount++;
            } else {
                unassignedCount++;
            }
        });
        
        // Aggiorna elementi DOM con sicurezza
        const $assignedEl = $('.btr-children-assigned');
        const $unassignedEl = $('.btr-children-unassigned');
        const $progressFill = $('.btr-progress-fill');
        const $progressText = $('.btr-progress-text');
        
        if ($assignedEl.length) $assignedEl.text(assignedCount);
        if ($unassignedEl.length) $unassignedEl.text(unassignedCount);
        
        // Calcola percentuale con controllo divisione per zero
        const percentage = totalChildren > 0 ? Math.round((assignedCount / totalChildren) * 100) : 0;
        
        if ($progressFill.length) {
            $progressFill.css('width', percentage + '%');
            // Aggiunge classe complete quando 100%
            if (percentage === 100) {
                $progressFill.addClass('complete');
            } else {
                $progressFill.removeClass('complete');
            }
        }
        
        if ($progressText.length) $progressText.text(percentage + '%');
        
        // WordPress security: Log per debug solo se il debug è attivo
        logDebug('[BTR updateCounters] Assigned:', assignedCount, 'Unassigned:', unassignedCount, 'Progress:', percentage + '%');
    };
    
    /**
     * IMPLEMENTAZIONE distributeChildrenToParticipants() - Distribuzione equa bambini
     * Usa algoritmo round-robin seguendo WordPress coding standards
     */
    window.distributeChildrenToParticipants = function distributeChildrenToParticipants() {
        const selectedParticipants = [];
        
        // Raccoglie partecipanti selezionati
        $('.participant-checkbox:checked').each(function() {
            const index = $(this).attr('data-index') || $(this).closest('tr').attr('data-participant-index');
            if (index) {
                selectedParticipants.push(index);
            }
        });
        
        if (selectedParticipants.length === 0) {
            return; // Nessun partecipante selezionato
        }
        
        // Algoritmo round-robin per distribuzione equa
        let participantIndex = 0;
        $('.btr-child-assignment').each(function() {
            const $select = $(this);
            const targetParticipant = selectedParticipants[participantIndex];
            
            // Assegna bambino al partecipante usando event delegation sicura
            $select.val(targetParticipant).trigger('change');
            
            // Passa al prossimo partecipante (round-robin)
            participantIndex = (participantIndex + 1) % selectedParticipants.length;
        });
        
        // Aggiorna contatori dopo distribuzione
        updateCounters();
        
        // WordPress standard per notifiche utente
        const participantNames = selectedParticipants.map(function(index) {
            const $row = $('.btr-participant-selection[data-participant-index="' + index + '"]');
            return $row.find('.btr-participant-name').text() || 'Partecipante ' + index;
        }).join(', ');
        
        // Mostra notifica di successo seguendo WordPress UI patterns
        if ($('#distribution-notice').length === 0) {
            const notice = $('<div id="distribution-notice" class="notice notice-success" style="margin: 10px 0; padding: 10px; background: #d4edda; border-left: 4px solid #28a745; color: #155724;">' +
                '<strong><?php echo esc_js(__('Distribuzione completata:', 'born-to-ride-booking')); ?></strong> ' +
                '<?php echo esc_js(__('I bambini sono stati distribuiti equamente tra:', 'born-to-ride-booking')); ?> ' + participantNames +
                '</div>');
            $('.children-assignment-section').prepend(notice);
            
            // Auto-remove notice dopo 6 secondi
            setTimeout(function() {
                notice.fadeOut(400, function() {
                    $(this).remove();
                });
            }, 6000);
        }
    };
    
    // Abilita/disabilita input quote quando checkbox è selezionato
    $(document).on('change', '.participant-checkbox', function() {
        const index = $(this).attr('data-index') || $(this).closest('tr').attr('data-participant-index');
        const sharesInput = $('#shares_' + index);
        
        if ($(this).is(':checked')) {
            sharesInput.prop('disabled', false);
        } else {
            sharesInput.prop('disabled', true).val(1);
        }
        
        // Distribuzione automatica delle quote tra i partecipanti selezionati
        distributeSharesAutomatically();
        recalcAdultsTotals(); // Ricalcola i prezzi individuali dopo distribuzione quote
        
        updateGroupTotals();
        
        // Auto-distribuzione intelligente
        const selectedCount = $('.participant-checkbox:checked').length;
        
        if (selectedCount === 1) {
            // Un solo partecipante: assegna tutti i bambini a lui
            const selectedIndex = $('.participant-checkbox:checked').first().attr('data-index') || 
                                 $('.participant-checkbox:checked').first().closest('tr').attr('data-participant-index');
            
            $('.btr-child-assignment').each(function() {
                $(this).val(selectedIndex).trigger('change');
            });
            
            // Mostra messaggio informativo usando WordPress standards
            if (!$('#auto-assignment-notice').length) {
                const notice = $('<div id="auto-assignment-notice" class="notice notice-info" style="margin: 10px 0; padding: 10px; background: #e3f2fd; border-left: 4px solid #2196F3; color: #1976D2;">' +
                    '<strong><?php echo esc_js(__('Nota:', 'born-to-ride-booking')); ?></strong> ' +
                    '<?php echo esc_js(__('Tutti i bambini sono stati automaticamente assegnati al partecipante selezionato.', 'born-to-ride-booking')); ?>' +
                    '</div>');
                $('.children-assignment-section').prepend(notice);
                setTimeout(() => notice.fadeOut(), 5000);
            }
        } else if (selectedCount === 0) {
            // Nessun partecipante: resetta assegnazioni
            $('.btr-child-assignment').val('').trigger('change');
            $('#auto-assignment-notice').remove();
        } else {
            // Più partecipanti: distribuzione automatica
            $('#auto-assignment-notice').remove();
            distributeChildrenToParticipants();
        }
        
        // Aggiorna contatori
        updateCounters();
    });
    
    // Aggiorna importi quando cambiano le quote
    $('.participant-shares').on('input', function() {
        recalcAdultsTotals();

        updateGroupTotals();
    });
    
    // Funzione per distribuire automaticamente le quote tra i partecipanti selezionati
    window.distributeSharesAutomatically = function distributeSharesAutomatically() {
        const selectedCheckboxes = $(".participant-checkbox:checked");
        const selectedCount = selectedCheckboxes.length;
        
        if (selectedCount === 0) return;

        // Calcola quote per partecipante
        const sharesPerParticipant = Math.floor(totalParticipants / selectedCount);
        let remainder = totalParticipants % selectedCount;

        logDebug("[DEBUG] Distribuzione quote:", {
            totalParticipants: totalParticipants,
            selectedCount: selectedCount,
            sharesPerParticipant: sharesPerParticipant,
            remainder: remainder
        });

        // Distribuisci le quote
        selectedCheckboxes.each(function(index) {
            const participantIndex = $(this).data("index");
            const sharesInput = $("#shares_" + participantIndex);

            // Assegna quote base + eventuale resto ai primi partecipanti
            let shares = sharesPerParticipant;
            if (remainder > 0) {
                shares += 1;
                remainder--;
            }

            sharesInput.val(shares);
            logDebug("[DEBUG] Partecipante", participantIndex, "assegnate", shares, "quote");
        });

        // Se un solo partecipante, assegna tutte le quote
        if (selectedCount === 1) {
            const singleIndex = selectedCheckboxes.first().data("index");
            $("#shares_" + singleIndex).val(totalParticipants);
            logDebug("[DEBUG] Un solo partecipante, assegnate tutte le", totalParticipants, "quote");
        }

        // Ricalcola i prezzi dopo la distribuzione delle quote
        recalcAdultsTotals();
    }

    const warningEl = $('#shares-warning');

    function showGroupWarning(type, message) {
        if (!warningEl.length) {
            return;
        }

        warningEl.removeClass('btr-alert-success btr-alert-error btr-alert-warning');

        switch (type) {
            case 'success':
                warningEl.addClass('btr-alert-success');
                break;
            case 'error':
                warningEl.addClass('btr-alert-error');
                break;
            default:
                warningEl.addClass('btr-alert-warning');
                break;
        }

        warningEl.find('.warning-text').text(message);
        warningEl.attr('role', 'alert').fadeIn(150);
    }

    function clearGroupWarning() {
        if (!warningEl.length) {
            return;
        }

        warningEl.stop(true, true).fadeOut(150, function() {
            warningEl.removeClass('btr-alert-success btr-alert-error btr-alert-warning');
            warningEl.find('.warning-text').text('');
            warningEl.removeAttr('role');
        });
    }

    // Funzione per aggiornare i totali del gruppo
    window.updateGroupTotals = function updateGroupTotals() {
        let totalShares = 0;
        let totalAmount = 0;
        const selectedBoxes = $('.participant-checkbox:checked');
        const selectedCount = selectedBoxes.length;

        selectedBoxes.each(function() {
            const index = $(this).data('index');
            const shares = parseInt($('#shares_' + index).val(), 10) || 0;
            totalShares += shares;
            const $row = $(this).closest('.btr-participant-selection');
            const rowAmount = parseFloat($row.data('computed-total') || '0');
            totalAmount += rowAmount;
        });

        // Aggiorna dashboard cards
        const grandTotal = parseFloat($('#btr-payment-plan-selection').data('total') || '0');
        const remainingAmount = grandTotal - totalAmount;

        // Aggiorna i valori nei card della dashboard
        $('.js-assigned-amount').text(formatPrice(totalAmount));
        $('.js-remaining-amount').text(formatPrice(remainingAmount));
        $('.total-shares').text(totalShares);

        if (selectedCount > 0 && totalShares !== totalParticipants) {
            const diff = totalParticipants - totalShares;
            const $target = selectedBoxes.last();
            if ($target.length) {
                const targetIndex = $target.data('index');
                const $sharesInput = $('#shares_' + targetIndex);
                const adjusted = Math.max(0, (parseInt($sharesInput.val(), 10) || 0) + diff);
                $sharesInput.val(adjusted);
            }

            totalShares = 0;
            totalAmount = 0;
            selectedBoxes.each(function() {
                const index = $(this).data('index');
                const shares = parseInt($('#shares_' + index).val(), 10) || 0;
                totalShares += shares;
                const $row = $(this).closest('.btr-participant-selection');
                const rowAmount = parseFloat($row.data('computed-total') || '0');
                totalAmount += rowAmount;
            });
        }

        $('.total-shares').text(totalShares);
        $('.total-amount').text(formatPrice(totalAmount));
        $('.selected-participants').text(selectedCount);

        if (!selectedCount) {
            clearGroupWarning();
            return;
        }

        if (totalShares === totalParticipants) {
            showGroupWarning('success', '<?php echo esc_js(__('✔ Tutti i partecipanti risultano coperti.', 'born-to-ride-booking')); ?>');
        } else if (totalShares < totalParticipants) {
            showGroupWarning('warning', '<?php echo esc_js(__('Mancano quote per coprire tutti i partecipanti.', 'born-to-ride-booking')); ?>');
        } else {
            showGroupWarning('warning', '<?php echo esc_js(__('Le quote assegnate superano il numero totale di partecipanti.', 'born-to-ride-booking')); ?>');
        }
    }

    // Ricalcolo per-adulto (base+extra+assicurazioni+figli assegnati) con logica selezione
    window.recalcAdultsTotals = function recalcAdultsTotals(){
        logDebug('[DEBUG] recalcAdultsTotals chiamata');
        // Mappa assegnazioni bambino->adulto
        const assigns = {};
        $('.btr-assignment-row').each(function(){
            const childTotal = parseFloat($(this).data('child-total') || '0');
            const adultIdx = $(this).find('.btr-child-assignment').val();
            if (adultIdx !== null && adultIdx !== ''){
                assigns[adultIdx] = (assigns[adultIdx]||0) + childTotal;
            }
        });

        const grandTotal = parseFloat($('#btr-payment-plan-selection').data('total') || '0');
        const totalAdults = $('.btr-participant-selection').length;
        const selectedAdults = $('.participant-checkbox:checked').map(function(){return ($(this).data('index')+'');}).get();

        // Calcolo personale (base+extra+ass+child) sempre
        const personal = {};
        $('.btr-participant-selection').each(function(){
            const $row = $(this);
            const idx = $row.attr('data-participant-index') || '';
            const base = parseFloat($row.data('base')||'0');
            const extra = parseFloat($row.data('extra')||'0');
            const ins = parseFloat($row.data('ins')||'0');
            const childAdd = assigns[idx] || 0;
            const tot = base + extra + ins + childAdd;
            personal[idx] = tot;
            $row.find('.bd-child').removeClass('d-none').text(' · + ' + formatPrice(childAdd));
        });

        // Distribuzione in base alla selezione
        if (selectedAdults.length === 1){
            // Un adulto si accolla tutto
            const only = selectedAdults[0];
            logDebug('[DEBUG] Un solo adulto selezionato:', only, 'Grand Total:', grandTotal);
            $('.btr-participant-selection').each(function(){
                const $row = $(this);
                const idx = $row.attr('data-participant-index') || '';
                const amount = (idx===only) ? grandTotal : 0;
                $row.data('computed-total', amount.toFixed(2));
                
                const $amountEl = $row.find('.btr-participant-amount');
                logDebug('[DEBUG] Aggiornamento prezzo per idx:', idx, 'Element found:', $amountEl.length, 'Amount:', amount);
                
                if (idx===only){
                    $amountEl.text(formatPrice(amount));
                    // Aggiungi classe per animazione
                    $amountEl.addClass('price-updated');
                    setTimeout(() => $amountEl.removeClass('price-updated'), 500);
                } else {
                    // mostra personale e memorizza il personale come computed per coerenza di somma
                    const p = personal[idx] || 0;
                    $row.data('computed-total', p.toFixed(2));
                    $amountEl.text(formatPrice(p));
                }
            });
            return;
        }

        if (selectedAdults.length === totalAdults && totalAdults>0){
            // Tutti gli adulti pagano la propria quota personale
            $('.btr-participant-selection').each(function(){
                const $row = $(this);
                const idx = $row.attr('data-participant-index') || '';
                $row.data('computed-total', (personal[idx]).toFixed(2));
                $row.find('.btr-participant-amount').text(formatPrice(personal[idx]));
            });
            return;
        }

        // Caso intermedio: usa le quote per ripartire il grand total tra i selezionati
        let sumShares = 0;
        selectedAdults.forEach(function(idx){ sumShares += (parseInt($('#shares_'+idx).val())||0); });
        if (sumShares <= 0) sumShares = selectedAdults.length;
        $('.btr-participant-selection').each(function(){
            const $row = $(this);
            const idx = $row.attr('data-participant-index') || '';
            if (selectedAdults.indexOf(idx)>=0){
                const shares = parseInt($('#shares_'+idx).val())||1;
                const amount = grandTotal * (shares / sumShares);
                $row.data('computed-total', amount.toFixed(2));
                $row.find('.btr-participant-amount').text(formatPrice(amount));
            } else {
                $row.data('computed-total', '0.00');
            }
        });
        return;
    }

    /**
     * Aggiorna il riepilogo dinamico dei pagamenti
     */
    window.updatePaymentSummary = function updatePaymentSummary() {
        const selectedAdults = $('.participant-checkbox:checked').length;
        const grandTotal = parseFloat($('#btr-payment-plan-selection').data('total') || '0');
        
        // Aggiungi/aggiorna box riepilogo se non esiste
        if ($('#payment-summary-box').length === 0) {
            const summaryHtml = `
                <div id="payment-summary-box" class="payment-summary-box" style="
                    margin: 20px 0;
                    padding: 20px;
                    background: #f8f9fa;
                    border: 1px solid #e9ecef;
                    border-radius: 8px;
                    display: none;
                ">
                    <h4 style="margin-top: 0; margin-bottom: 15px; color: #495057;">
                        Riepilogo Distribuzione Pagamento
                    </h4>
                    <div class="summary-content">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span><strong>Partecipanti selezionati:</strong></span>
                            <span id="summary-selected">0</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                            <span><strong>Totale da pagare:</strong></span>
                            <span id="summary-total">€0,00</span>
                        </div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 10px; padding-top: 10px; border-top: 1px solid #dee2e6;">
                            <span><strong>Quota per partecipante:</strong></span>
                            <span id="summary-per-person" style="font-size: 1.1em; color: #007bff;">€0,00</span>
                        </div>
                    </div>
                    <div id="summary-details" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #dee2e6;">
                    </div>
                </div>
            `;
            $('#group-payment-participants').after(summaryHtml);
        }
        
        // Mostra/nascondi box in base alla selezione
        if (selectedAdults > 0) {
            $('#payment-summary-box').slideDown(300);
            
            // Aggiorna valori
            $('#summary-selected').text(selectedAdults);
            $('#summary-total').text('€' + grandTotal.toFixed(2).replace('.', ','));
            
            const perPerson = grandTotal / selectedAdults;
            $('#summary-per-person').text('€' + perPerson.toFixed(2).replace('.', ','));
            
            // Dettagli per partecipante
            let detailsHtml = '<h5 style="margin-bottom: 10px; font-size: 0.95em;">Dettaglio per partecipante:</h5>';
            $('.participant-checkbox:checked').each(function() {
                const index = $(this).data('index');
                const $row = $('.btr-participant-selection[data-participant-index="' + index + '"]');
                const name = $row.find('.btr-participant-name').text();
                const amount = $row.find('.btr-participant-amount').text();
                
                detailsHtml += `
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px; padding: 5px 0;">
                        <span>${name}</span>
                        <span style="font-weight: 600;">${amount}</span>
                    </div>
                `;
            });
            $('#summary-details').html(detailsHtml);
            
        } else {
            $('#payment-summary-box').slideUp(300);
        }
    }
    
    // Funzione per aggiornare lo stato delle assegnazioni in tempo reale
    window.updateAssignmentStatus = function updateAssignmentStatus() {
        const totalChildren = $('.btr-assignment-row').length;
        let assignedCount = 0;
        let unassignedCount = 0;
        
        // Conta bambini assegnati e non assegnati
        $('.btr-assignment-row').each(function() {
            const $row = $(this);
            const $select = $row.find('.btr-child-assignment');
            const value = $select.val();
            const $statusIcon = $row.find('.btr-child-status-icon');
            
            if (value && value !== '') {
                assignedCount++;
                $row.removeClass('unassigned').addClass('assigned');
                $statusIcon.attr('data-status', 'assigned');
            } else {
                unassignedCount++;
                $row.removeClass('assigned').addClass('unassigned');
                $statusIcon.attr('data-status', 'unassigned');
            }
        });
        
        // Aggiorna pannello di stato
        $('.btr-children-assigned').text(assignedCount);
        $('.btr-children-unassigned').text(unassignedCount);
        
        // Calcola percentuale
        const percentage = totalChildren > 0 ? Math.round((assignedCount / totalChildren) * 100) : 0;
        $('.btr-progress-fill').css('width', percentage + '%');
        $('.btr-progress-text').text(percentage + '%');
        
        // Aggiorna classi e messaggi
        if (percentage === 100) {
            $('.btr-progress-fill').addClass('complete');
            $('.btr-assignment-status-panel').addClass('all-assigned');
            $('.btr-assignment-message').html(
                '<p class="btr-message-success">' +
                '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">' +
                '<path d="M8 0a8 8 0 100 16A8 8 0 008 0zm3.707 6.707l-4 4a1 1 0 01-1.414 0l-2-2a1 1 0 111.414-1.414L7 8.586l3.293-3.293a1 1 0 111.414 1.414z"/>' +
                '</svg>' +
                'Tutti i bambini sono stati assegnati! Puoi procedere al pagamento.' +
                '</p>'
            );
            $('#btr-payment-plan-selection button[type="submit"]').removeClass('disabled-children').prop('disabled', false);
        } else {
            $('.btr-progress-fill').removeClass('complete');
            $('.btr-assignment-status-panel').removeClass('all-assigned');
            $('.btr-assignment-message').html(
                '<p class="btr-message-warning">' +
                '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">' +
                '<path d="M8 0a8 8 0 100 16A8 8 0 008 0zm0 12a1 1 0 110-2 1 1 0 010 2zm0-3a1 1 0 01-1-1V4a1 1 0 012 0v4a1 1 0 01-1 1z"/>' +
                '</svg>' +
                'Tutti i bambini devono essere assegnati a un adulto prima di procedere' +
                '</p>'
            );
            // Solo disabilita se è selezionato il pagamento di gruppo
            if ($('input[name="payment_plan"]:checked').val() === 'group_split') {
                $('#btr-payment-plan-selection button[type="submit"]').addClass('disabled-children');
            }
        }
    }
    
    // Mostra feedback quando un bambino viene assegnato
    window.showAssignmentFeedback = function showAssignmentFeedback($row, adultName) {
        const $feedback = $row.find('.btr-feedback-text');
        if (adultName) {
            $feedback.text('Assegnato a ' + adultName).addClass('show');
            $row.addClass('just-assigned');
            setTimeout(function() {
                $row.removeClass('just-assigned');
            }, 300);
        } else {
            $feedback.text('').removeClass('show');
        }
    }

    // Trigger ricalcolo quando cambia selezione/adulti/assegnazione/quote
    $(document).on('change', '.btr-child-assignment', function(){
        const $select = $(this);
        const $row = $select.closest('.btr-assignment-row');
        const selectedValue = $select.val();
        
        // Mostra feedback
        if (selectedValue) {
            const selectedText = $select.find('option:selected').text();
            showAssignmentFeedback($row, selectedText);
        } else {
            showAssignmentFeedback($row, '');
        }
        
        // Aggiorna stato assegnazioni
        updateAssignmentStatus();
        
        // Funzioni esistenti
        recalcAdultsTotals();
        updateGroupTotals();
    });
    
    $(document).on('change', '.participant-checkbox', function(){ 
        recalcAdultsTotals(); 
        updateGroupTotals();
        updatePaymentSummary(); // Aggiorna riepilogo dinamico
        validateTotalsCoherence(); // Valida coerenza totali
        // Aggiorna anche lo stato quando cambiano i partecipanti selezionati
        updateAssignmentStatus();
    });
    
    // Inizializza stato al caricamento
    if ($('.btr-assignment-row').length > 0) {
        updateAssignmentStatus();
        
        // Trigger il ricalcolo per le assegnazioni pre-compilate
        setTimeout(function() {
            logDebug('[DEBUG] Triggering initial recalc for pre-filled assignments');
            recalcAdultsTotals();
            updateGroupTotals();
        }, 200);
    }
    
    /**
     * FIX validateGroupSelection() - Corregge confronto float con tolleranza
     * Implementa WordPress best practices per validazione
     */
    window.validateGroupSelection = function validateGroupSelection() {
        const grandTotal = parseFloat($('#btr-payment-plan-selection').data('total') || '0');
        let calculatedTotal = 0;
        const selectedCount = $('.participant-checkbox:checked').length;
        
        if (selectedCount === 0) {
            return {
                valid: false,
                message: '<?php echo esc_js(__('Seleziona almeno un partecipante per il pagamento di gruppo.', 'born-to-ride-booking')); ?>'
            };
        }
        
        // Calcola totale con controllo errori
        $('.participant-checkbox:checked').each(function() {
            const index = $(this).data('index');
            const $row = $('.btr-participant-selection[data-participant-index="' + index + '"]');
            const amount = parseFloat($row.data('computed-total') || '0');
            
            // WordPress security: Validazione input
            if (isNaN(amount) || amount < 0) {
                console.error('[BTR validateGroupSelection] Invalid amount for participant:', index, amount);
                return {
                    valid: false,
                    message: '<?php echo esc_js(__('Errore nei calcoli dei partecipanti. Ricarica la pagina.', 'born-to-ride-booking')); ?>'
                };
            }
            
            calculatedTotal += amount;
        });
        
        // FIX CRITICO: Usa tolleranza invece di === per float
        const difference = Math.abs(calculatedTotal - grandTotal);
        const tolerance = 0.01; // Tolleranza di 1 centesimo
        
        const validationResult = {
            valid: difference <= tolerance,
            difference: difference,
            grandTotal: grandTotal,
            calculatedTotal: calculatedTotal,
            selectedCount: selectedCount
        };
        
        // WordPress debug logging
        logDebug('[BTR validateGroupSelection]', validationResult);
        
        return validationResult;
    };
    
    // Validazione coerenza totali (mantiene retrocompatibilità)
    function validateTotalsCoherence() {
        const validation = validateGroupSelection();
        
        if (!validation.valid && validation.difference > 1) {
            // Mostra avviso WordPress-style solo per differenze significative
            if ($('#totals-warning').length === 0) {
                const warningHtml = `
                    <div id="totals-warning" class="notice notice-warning" style="
                        margin: 15px 0;
                        padding: 12px 20px;
                        background: #fff3cd;
                        border: 1px solid #ffeaa7;
                        border-radius: 4px;
                        color: #856404;
                    ">
                        <strong><?php echo esc_js(__('Attenzione:', 'born-to-ride-booking')); ?></strong> 
                        <?php echo esc_js(__('La somma dei pagamenti individuali', 'born-to-ride-booking')); ?> (€${validation.calculatedTotal.toFixed(2)}) 
                        <?php echo esc_js(__('non corrisponde al totale generale', 'born-to-ride-booking')); ?> (€${validation.grandTotal.toFixed(2)}). 
                        <?php echo esc_js(__('Differenza:', 'born-to-ride-booking')); ?> €${validation.difference.toFixed(2)}
                    </div>
                `;
                $('#payment-summary-box').after(warningHtml);
            }
        } else {
            $('#totals-warning').remove();
        }
        
        return validation.valid;
    }
    
    // Gestione submit form
    $('#btr-payment-plan-selection').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $submitBtn = $form.find('button[type="submit"]');
        const selectedPlan = $('input[name="payment_plan"]:checked').val();
        
        // Validazione specifica per pagamento di gruppo
        if (selectedPlan === 'group_split') {
            const selectedParticipants = $('.participant-checkbox:checked');

            if (!selectedParticipants.length) {
                showGroupWarning('error', '<?php echo esc_js(__('Seleziona almeno un partecipante per il pagamento di gruppo.', 'born-to-ride-booking')); ?>');
                $('.participant-checkbox').first().focus();
                return false;
            }

            const firstUnassigned = $('.btr-assignment-row .btr-child-assignment').filter(function() {
                return !$(this).val();
            }).first();

            if (firstUnassigned.length) {
                showGroupWarning('error', '<?php echo esc_js(__('Assegna tutti i bambini/neonati a un adulto pagante prima di proseguire.', 'born-to-ride-booking')); ?>');
                firstUnassigned.focus();
                return false;
            }

            const assignments = {};
            $('.btr-assignment-row .btr-child-assignment').each(function(){
                const assignment = $(this).val();
                if (assignment !== null && assignment !== '') {
                    assignments[assignment] = (assignments[assignment] || 0) + 1;
                }
            });

            selectedParticipants.each(function(){
                const idx = ($(this).data('index') || '').toString();
                const addition = assignments[idx] || 0;
                if (addition > 0) {
                    const $shares = $('#shares_' + idx);
                    $shares.prop('disabled', false).attr('aria-disabled', 'false');
                    $shares.val((parseInt($shares.val() || '0', 10) + addition)).trigger('input');
                }
            });

            let totalShares = 0;
            selectedParticipants.each(function() {
                const index = $(this).data('index');
                totalShares += parseInt($('#shares_' + index).val(), 10) || 0;
            });

            if (totalShares !== totalParticipants) {
                showGroupWarning('error', '<?php echo esc_js(__('Distribuisci le quote in modo da coprire tutti i partecipanti prima di procedere.', 'born-to-ride-booking')); ?>');
                return false;
            }

            if (!validateTotalsCoherence()) {
                const grandTotal = parseFloat($('#btr-payment-plan-selection').data('total') || '0');
                let calculatedTotal = 0;
                selectedParticipants.each(function() {
                    const index = $(this).data('index');
                    const $row = $('.btr-participant-selection[data-participant-index="' + index + '"]');
                    const amount = parseFloat($row.data('computed-total') || '0');
                    calculatedTotal += amount;
                });

                showGroupWarning('error', `<?php echo esc_js(__('La somma dei pagamenti individuali (€', 'born-to-ride-booking')); ?>${calculatedTotal.toFixed(2)} <?php echo esc_js(__('non corrisponde al totale generale (€', 'born-to-ride-booking')); ?>${grandTotal.toFixed(2)}).`);
                return false;
            }

            clearGroupWarning();
        }
        
        // Disabilita pulsante
        $submitBtn.prop('disabled', true).text('<?php echo esc_js(__('Elaborazione...', 'born-to-ride-booking')); ?>');
        
        // IMPLEMENTAZIONE saveToCart() con WordPress AJAX security
        saveToCart($form, function(success, data) {
            if (success) {
                // Redirect sicuro usando WordPress URL
                if (data && data.redirect_url) {
                    window.location.href = data.redirect_url;
                } else {
                    window.location.href = '<?php echo esc_url(wc_get_checkout_url()); ?>';
                }
            } else {
                const message = (data && data.message) 
                    ? data.message 
                    : '<?php echo esc_js(__('Si è verificato un errore durante il salvataggio.', 'born-to-ride-booking')); ?>';
                
                // WordPress-style error display
                if ($('#form-error-notice').length === 0) {
                    const errorNotice = $('<div id="form-error-notice" class="notice notice-error" style="margin: 10px 0; padding: 10px; background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24;">' +
                        '<strong><?php echo esc_js(__('Errore:', 'born-to-ride-booking')); ?></strong> ' + message +
                        '</div>');
                    $form.prepend(errorNotice);
                    
                    // Scroll to error
                    $('html, body').animate({
                        scrollTop: errorNotice.offset().top - 100
                    }, 500);
                    
                    setTimeout(() => errorNotice.fadeOut(), 8000);
                }
                
                // Ripristina pulsante
                $submitBtn.prop('disabled', false).html('<?php echo esc_js(__('Procedi al Checkout', 'born-to-ride-booking')); ?> <svg class="btr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>');
            }
        });
    });
    
    /**
     * IMPLEMENTAZIONE saveToCart() - Salva dati nel WooCommerce session via AJAX
     * Implementa WordPress security best practices
     */
    window.saveToCart = function saveToCart($form, callback) {
        // WordPress nonce per sicurezza CSRF
        const formData = $form.serializeArray();
        formData.push({
            name: 'action',
            value: 'btr_save_group_payment_data'
        });
        formData.push({
            name: '_wpnonce',
            value: '<?php echo wp_create_nonce('btr_group_payment_nonce'); ?>'
        });
        
        // Aggiungi dati specifici del pagamento di gruppo
        if ($('input[name="payment_plan"]:checked').val() === 'group_split') {
            // Raccoglie assegnazioni bambini
            const childAssignments = {};
            $('.btr-child-assignment').each(function() {
                const childName = $(this).attr('name');
                const assignedTo = $(this).val();
                if (childName && assignedTo) {
                    childAssignments[childName] = assignedTo;
                }
            });
            
            formData.push({
                name: 'child_assignments',
                value: JSON.stringify(childAssignments)
            });
            
            // Raccoglie dati partecipanti selezionati
            const selectedParticipants = [];
            $('.participant-checkbox:checked').each(function() {
                const index = $(this).data('index');
                const $row = $('.btr-participant-selection[data-participant-index="' + index + '"]');
                const shares = parseInt($('#shares_' + index).val()) || 1;
                const amount = parseFloat($row.data('computed-total') || '0');
                
                selectedParticipants.push({
                    index: index,
                    name: $row.find('.btr-participant-name').text(),
                    shares: shares,
                    amount: amount
                });
            });
            
            formData.push({
                name: 'selected_participants',
                value: JSON.stringify(selectedParticipants)
            });
        }
        
        // AJAX call con WordPress security
        $.ajax({
            url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
            type: 'POST',
            data: $.param(formData),
            dataType: 'json',
            beforeSend: function(xhr) {
                // WordPress AJAX headers
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            },
            success: function(response) {
                if (typeof callback === 'function') {
                    callback(response.success === true, response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('[BTR saveToCart] AJAX Error:', status, error);
                if (typeof callback === 'function') {
                    callback(false, {
                        message: '<?php echo esc_js(__('Errore di connessione. Controlla la connessione internet e riprova.', 'born-to-ride-booking')); ?>'
                    });
                }
            },
            timeout: 30000 // 30 secondi timeout
        });
    };
    
    // Inizializza contatori al caricamento
    $(document).ready(function() {
        updateCounters();
        
        // Event listener per aggiornare contatori quando cambiano assegnazioni
        $(document).on('change', '.btr-child-assignment', function() {
            updateCounters();
            
            // Feedback visivo per l'utente
            const $row = $(this).closest('.btr-assignment-row');
            const selectedName = $(this).find('option:selected').text();
            
            if ($(this).val()) {
                $row.addClass('assigned').removeClass('unassigned');
                showAssignmentFeedback($row, selectedName);
            } else {
                $row.addClass('unassigned').removeClass('assigned');
                showAssignmentFeedback($row, null);
            }
        });
    });
    
    // Handler per click sulle card - migliora UX
    $('.btr-payment-option-card').on('click', function(e) {
        // Se il click non è sul radio button stesso
        if (!$(e.target).is('input[type="radio"]')) {
            const radio = $(this).find('input[name="payment_plan"]');
            const isCurrentlyChecked = radio.is(':checked');
            const currentPlan = radio.val();
            
            if (!isCurrentlyChecked) {
                // Se non è selezionato, selezionalo
                radio.prop('checked', true).trigger('change');
            } else {
                // Se è già selezionato, usa la funzione unificata per toggle
                togglePaymentConfig(currentPlan, false);
            }
        }
    });
    
    // Trigger iniziale per mostrare configurazione se già selezionata
    const checkedRadio = $('input[name="payment_plan"]:checked');
    if (checkedRadio.length) {
        togglePaymentConfig(checkedRadio.val(), true);
        
        // Se è selezionato il pagamento di gruppo, inizializza il riepilogo
        if (checkedRadio.val() === 'group_split') {
            setTimeout(function() {
                recalcAdultsTotals();
                updatePaymentSummary();
                validateTotalsCoherence();
            }, 100);
        }
    }
    
    // Aggiungi effetto hover sui prezzi dei partecipanti
    $(document).on('mouseenter', '.btr-participant-amount', function() {
        $(this).css('transform', 'scale(1.05)');
    }).on('mouseleave', '.btr-participant-amount', function() {
        $(this).css('transform', 'scale(1)');
    });
});
</script>
