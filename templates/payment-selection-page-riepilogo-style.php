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

if (false === $preventivo_data) {
    // Recupera tutti i meta in una sola query
    $all_meta = get_post_meta($preventivo_id);
    
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
        
        // I supplementi extra vanno aggiunti al totale finale ma non al totale camere
        $supplementi_extra = $totali['subtotale_supplementi_extra'] ?? 0;
        
        // Usa i valori dai meta se disponibili, altrimenti cerca nei partecipanti
        $totale_assicurazioni = $totale_assicurazioni_meta;
        $totale_costi_extra = $totale_aggiunte_extra_meta ?: $extra_costs_total_meta;
        
        // Se non ci sono valori nei meta, estrai dai partecipanti
        if ($totale_assicurazioni == 0 || $totale_costi_extra == 0) {
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
                    
                    if (isset($dati_categoria['assicurazioni']) && $totale_assicurazioni == 0) {
                        foreach ($dati_categoria['assicurazioni'] as $assicurazione) {
                            $totale_assicurazioni += $assicurazione['importo'] ?? 0;
                        }
                    }
                }
            }
        }
        
        // Il totale preventivo finale deve includere:
        // totale camere + supplementi extra + assicurazioni + costi extra
        $totale_preventivo = $totale_camere + $supplementi_extra + $totale_assicurazioni + $totale_costi_extra;
        
        // Verifica con il grand total dai meta se disponibile
        if (isset($all_meta['_btr_grand_total'][0])) {
            $grand_total = floatval($all_meta['_btr_grand_total'][0]);
            // Log per debug
            if (defined('WP_DEBUG') && WP_DEBUG && abs($grand_total - $totale_preventivo) > 0.01) {
                error_log('[BTR Payment Selection] Differenza tra totale calcolato (' . $totale_preventivo . ') e grand total (' . $grand_total . ')');
            }
        }
        
        // Assegna i valori per retrocompatibilità
        $prezzo_base = $totale_camere;
    }
    
    // Recupera i dati anagrafici e altri meta necessari
    $anagrafici = maybe_unserialize($all_meta['_anagrafici_preventivo'][0] ?? '');
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
            error_log('[BTR Payment Selection Riepilogo] Nessun partecipante trovato per preventivo ' . $preventivo_id);
        }
        if (!empty($riepilogo_calcoli)) {
            error_log('[BTR Payment Selection Riepilogo] Usando dati da _riepilogo_calcoli_dettagliato per preventivo ' . $preventivo_id);
            error_log('[BTR Payment Selection Riepilogo] Totale camere: €' . number_format($totale_camere, 2) . ', Assicurazioni: €' . number_format($totale_assicurazioni, 2) . ', Costi extra: €' . number_format($totale_costi_extra, 2) . ', Totale finale: €' . number_format($totale_preventivo, 2));
            if (isset($all_meta['_btr_grand_total'][0])) {
                error_log('[BTR Payment Selection Riepilogo] Grand total dai meta: €' . number_format(floatval($all_meta['_btr_grand_total'][0]), 2));
            }
        } else {
            error_log('[BTR Payment Selection Riepilogo] Fallback al calcolatore per preventivo ' . $preventivo_id);
        }
    }
    
    // Override totali da meta _btr_* se presenti per allineamento esatto
    if (isset($all_meta['_btr_totale_generale'][0])) {
        $preventivo_data['totale_preventivo'] = floatval($all_meta['_btr_totale_generale'][0]);
        $totale_preventivo = $preventivo_data['totale_preventivo'];
    }
    if (isset($all_meta['_btr_totale_camere'][0])) {
        $preventivo_data['totale_camere'] = floatval($all_meta['_btr_totale_camere'][0]);
        $totale_camere = $preventivo_data['totale_camere'];
    }
    if (isset($all_meta['_btr_totale_costi_extra'][0])) {
        $preventivo_data['totale_costi_extra'] = floatval($all_meta['_btr_totale_costi_extra'][0]);
        $totale_costi_extra = $preventivo_data['totale_costi_extra'];
    }
    if (isset($all_meta['_btr_totale_assicurazioni'][0])) {
        $preventivo_data['totale_assicurazioni'] = floatval($all_meta['_btr_totale_assicurazioni'][0]);
        $totale_assicurazioni = $preventivo_data['totale_assicurazioni'];
    }

    // Cache per 5 minuti
    wp_cache_set($cache_key, $preventivo_data, 'btr_preventivi', 300);
}

// Estrai variabili per retrocompatibilità
extract($preventivo_data);
$pacchetto_title = get_the_title($pacchetto_id);

// Calcola totale persone includendo neonati se presenti
$totale_persone = intval($numero_adulti) + intval($numero_bambini) + intval($numero_neonati ?? 0);

// Opzioni per il piano di pagamento
$bank_transfer_enabled = get_option('btr_enable_bank_transfer_plans', true);
$bank_transfer_info = get_option('btr_bank_transfer_info', '');
$deposit_percentage = intval(get_option('btr_default_deposit_percentage', 30));

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
        error_log('BTR Payment Selection: Piano esistente trovato - Tipo: ' . $current_plan_type . ', Deposito: ' . $current_deposit_percentage . '%');
    }
}

// Applica la logica di soglia per l'opzione gruppo
$enable_group = (bool) get_option('btr_enable_group_split', true);
$threshold = max(1, (int) get_option('btr_group_split_threshold', 10));
$can_show_group = $enable_group && ($totale_persone >= $threshold);

printr(get_post_meta($preventivo_id));
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
                
                <!-- Supplementi notti extra (se presenti) -->
                <?php if (isset($supplementi_extra) && $supplementi_extra > 0): ?>
                <tr>
                    <td><?php esc_html_e('Supplementi notti extra', 'born-to-ride-booking'); ?></td>
                    <td class="text-right btr-price"><?php echo btr_format_price_i18n($supplementi_extra); ?></td>
                </tr>
                <?php endif; ?>
                
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
          data-participants="<?php echo esc_attr($totale_persone); ?>">
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
                            
                            <div class="btr-alert btr-alert-info btr-mt-3">
                                <svg class="btr-alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span><?php esc_html_e('Il saldo dovrà essere pagato prima della partenza secondo i termini concordati.', 'born-to-ride-booking'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagamento di Gruppo -->
                <?php if ($can_show_group): ?>
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

                        // Helper per costi extra/assicurazioni per persona
                        $get_person_addons = function($p){
                            $sum_extra = 0.0; $sum_ins = 0.0;
                            if (!empty($p['costi_extra_dettagliate']) && is_array($p['costi_extra_dettagliate'])) {
                                foreach ($p['costi_extra_dettagliate'] as $slug => $info) {
                                    // attivo se presente in costi_extra o importo>0
                                    $selected = !empty($p['costi_extra'][$slug]) || (!empty($info['importo']) && floatval($info['importo'])!=0);
                                    if ($selected) { $sum_extra += floatval($info['importo'] ?? 0); }
                                }
                            }
                            if (!empty($p['assicurazioni_dettagliate']) && is_array($p['assicurazioni_dettagliate'])) {
                                foreach ($p['assicurazioni_dettagliate'] as $slug => $info) {
                                    $selected = !empty($p['assicurazioni'][$slug]);
                                    if ($selected) { $sum_ins += floatval($info['importo'] ?? 0); }
                                }
                            }
                            return [$sum_extra,$sum_ins];
                        };

                        if (!empty($anagrafici) && is_array($anagrafici)) {
                            foreach ($anagrafici as $index => $persona) {
                                $tipo = strtolower(trim($persona['tipo_persona'] ?? ''));
                                $fascia = strtolower(trim($persona['fascia'] ?? ''));
                                $is_adult = ($tipo === 'adulto') || ($fascia === 'adulto');
                                if (!$is_adult && !empty($persona['data_nascita'])) {
                                    try { $age=(new DateTime())->diff(new DateTime($persona['data_nascita']))->y; $is_adult = ($age>=18); } catch (Exception $e) {}
                                }
                                $label = trim(($persona['nome'] ?? '') . ' ' . ($persona['cognome'] ?? ''));
                                list($sum_extra,$sum_ins) = $get_person_addons($persona);
                                if ($is_adult && $label) {
                                    $adulti_paganti[] = [
                                        'index'=>$index,
                                        'nome'=>$label,
                                        'email'=>$persona['email'] ?? '',
                                        'base'=> $adult_unit,
                                        'extra'=> $sum_extra,
                                        'ins'=> $sum_ins
                                    ];
                                } else {
                                    // Stima costo bambino: usa fascia dal riepilogo, altrimenti 0 + eventuali extra/assicurazioni
                                    $child_unit = 0.0;
                                    $map = [ 'f1'=>'bambini_f1','f2'=>'bambini_f2','f3'=>'bambini_f3','f4'=>'bambini_f4','bambino'=>'bambini' ];
                                    $key = $map[$fascia] ?? '';
                                    if ($key && !empty($riepilogo_calcoli['partecipanti'][$key])) {
                                        $cq=intval($riepilogo_calcoli['partecipanti'][$key]['quantita'] ?? 0);
                                        $ct=floatval($riepilogo_calcoli['partecipanti'][$key]['totale'] ?? 0);
                                        if ($cq>0) { $child_unit = $ct/$cq; }
                                    }
                                    $bambini_neonati[] = [
                                        'index'=>$index,
                                        'label'=>$label ?: ('Persona #'.($index+1)),
                                        'total'=> $child_unit + $sum_extra + $sum_ins
                                    ];
                                }
                            }
                        }
                        ?>

                        <?php if (!empty($adulti_paganti)): ?>
                            <h4 class="btr-h4 btr-mb-3"><?php esc_html_e('Seleziona chi effettuerà il pagamento', 'born-to-ride-booking'); ?></h4>
                            <p class="btr-text-sm btr-text-muted btr-mb-3"><?php esc_html_e('Puoi selezionare quali adulti pagheranno e per quante quote ciascuno.', 'born-to-ride-booking'); ?></p>
                            
                            <div class="btr-participants-list">
                                <?php foreach ($adulti_paganti as $adulto): ?>
                                <div class="btr-participant-selection" data-participant-index="<?php echo esc_attr($adulto['index']); ?>"
                                     data-base="<?php echo esc_attr(number_format($adulto['base'],2,'.','')); ?>"
                                     data-extra="<?php echo esc_attr(number_format($adulto['extra'],2,'.','')); ?>"
                                     data-ins="<?php echo esc_attr(number_format($adulto['ins'],2,'.','')); ?>"
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
                            <div class="btr-assignments btr-mt-3">
                                <h4 class="btr-h4"><?php esc_html_e('Assegna bambini/neonati a un adulto pagante', 'born-to-ride-booking'); ?></h4>
                                <?php foreach ($bambini_neonati as $child): ?>
                                <div class="btr-assignment-row" data-child-index="<?php echo esc_attr($child['index']); ?>" data-child-total="<?php echo esc_attr(number_format($child['total'],2,'.','')); ?>">
                                    <label class="btr-text-sm"><?php echo esc_html($child['label']); ?></label>
                                    <select class="btr-form-control btr-child-assignment" name="child_assignment[<?php echo esc_attr($child['index']); ?>]">
                                        <option value=""><?php esc_html_e('Seleziona adulto', 'born-to-ride-booking'); ?></option>
                                        <?php foreach ($adulti_paganti as $adulto): ?>
                                            <option value="<?php echo esc_attr($adulto['index']); ?>"><?php echo esc_html($adulto['nome']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endforeach; ?>
                                <p class="btr-text-sm btr-text-muted"><?php esc_html_e('Le quote degli adulti selezionati aumenteranno in base alle assegnazioni.', 'born-to-ride-booking'); ?></p>
                            </div>
                            <?php endif; ?>

                            <div class="btr-group-summary">
                                <div class="btr-group-total">
                                    <span><?php esc_html_e('Totale quote assegnate:', 'born-to-ride-booking'); ?></span>
                                    <span><strong class="total-shares">0</strong> / <?php echo $totale_persone; ?></span>
                                </div>
                                <div class="btr-group-total">
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
</style>

<script>
jQuery(document).ready(function($) {
    // Gestione cambio piano
    $('input[name="payment_plan"]').on('change', function() {
        const selectedPlan = $(this).val();
        
        // Nascondi tutte le configurazioni
        $('#deposit-config, #group-payment-config').slideUp(300);
        
        // Mostra la configurazione appropriata
        if (selectedPlan === 'deposit_balance') {
            $('#deposit-config').slideDown(300);
        } else if (selectedPlan === 'group_split') {
            $('#group-payment-config').slideDown(300);
        }
    });
    
    // Aggiorna valori caparra
    const totalAmount = <?php echo floatval($totale_preventivo); ?>;
    
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
    function formatPrice(amount) {
        return new Intl.NumberFormat('it-IT', {
            style: 'currency',
            currency: 'EUR'
        }).format(amount);
    }
    
    // Gestione partecipanti gruppo
    const totalParticipants = <?php echo intval($totale_persone); ?>;
    const quotaPerPerson = <?php echo floatval($quota_per_persona); ?>;
    
    // Abilita/disabilita input quote quando checkbox è selezionato
    $('.participant-checkbox').on('change', function() {
        const index = $(this).data('index');
        const sharesInput = $('#shares_' + index);
        
        if ($(this).is(':checked')) {
            sharesInput.prop('disabled', false);
        } else {
            sharesInput.prop('disabled', true).val(1);
        }
        
        updateGroupTotals();
    });
    
    // Aggiorna importi quando cambiano le quote
    $('.participant-shares').on('input', function() {
        recalcAdultsTotals();

        updateGroupTotals();
    });
    
    // Funzione per aggiornare i totali del gruppo
    function updateGroupTotals() {
        let totalShares = 0;
        let totalAmount = 0;
        let selectedCount = 0;
        
        $('.participant-checkbox:checked').each(function() {
            selectedCount++;
            const index = $(this).data('index');
            const shares = parseInt($('#shares_' + index).val()) || 0;
            totalShares += shares;
            const $row = $(this).closest('.btr-participant-selection');
            const rowAmount = parseFloat($row.data('computed-total') || '0');
            totalAmount += rowAmount;
        });
        
        // Aggiorna UI totali
        $('.total-shares').text(totalShares);
        $('.total-amount').text(formatPrice(totalAmount));
        
        // Mostra avviso se le quote non corrispondono al totale partecipanti
        const warningEl = $('#shares-warning');
        if (selectedCount > 0) {
            if (totalShares < totalParticipants) {
                warningEl.find('.warning-text').text(
                    'Attenzione: sono state assegnate solo ' + totalShares + ' quote su ' + totalParticipants + ' partecipanti totali.'
                );
                warningEl.show();
            } else if (totalShares > totalParticipants) {
                warningEl.find('.warning-text').text(
                    'Attenzione: sono state assegnate ' + totalShares + ' quote ma ci sono solo ' + totalParticipants + ' partecipanti.'
                );
                warningEl.show();
            } else {
                warningEl.hide();
            }
        } else {
            warningEl.hide();
        }
    }

    // Ricalcolo per-adulto (base+extra+assicurazioni+figli assegnati) con logica selezione
    function recalcAdultsTotals(){
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
            const idx = ($row.data('participant-index')||'').toString();
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
            $('.btr-participant-selection').each(function(){
                const $row = $(this);
                const idx = ($row.data('participant-index')||'').toString();
                const amount = (idx===only) ? grandTotal : 0;
                $row.data('computed-total', amount.toFixed(2));
                if (idx===only){
                    $row.find('.btr-participant-amount').text(formatPrice(amount));
                } else {
                    // mostra personale e memorizza il personale come computed per coerenza di somma
                    const p = personal[idx] || 0;
                    $row.data('computed-total', p.toFixed(2));
                    $row.find('.btr-participant-amount').text(formatPrice(p));
                }
            });
            return;
        }

        if (selectedAdults.length === totalAdults && totalAdults>0){
            // Tutti gli adulti pagano la propria quota personale
            $('.btr-participant-selection').each(function(){
                const $row = $(this);
                const idx = ($row.data('participant-index')||'').toString();
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
            const idx = ($row.data('participant-index')||'').toString();
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

    // Trigger ricalcolo quando cambia selezione/adulti/assegnazione/quote
    $(document).on('change', '.btr-child-assignment', function(){
        recalcAdultsTotals();
        updateGroupTotals();
    });
    $(document).on('change', '.participant-checkbox', function(){ recalcAdultsTotals(); updateGroupTotals(); });
    
    // Gestione submit form
    $('#btr-payment-plan-selection').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $submitBtn = $form.find('button[type="submit"]');
        const selectedPlan = $('input[name="payment_plan"]:checked').val();
        
        // Validazione specifica per pagamento di gruppo
        if (selectedPlan === 'group_split') {
            const selectedParticipants = $('.participant-checkbox:checked').length;
            
            if (selectedParticipants === 0) {
                alert('<?php esc_attr_e('Seleziona almeno un partecipante per il pagamento di gruppo.', 'born-to-ride-booking'); ?>');
                return false;
            }
            
            // Assegnazioni obbligatorie
            let allAssigned = true;
            $('.btr-assignment-row .btr-child-assignment').each(function(){ if (!$(this).val()) { allAssigned = false; } });
            if (!allAssigned) {
                alert('<?php esc_attr_e('Assegna tutti i bambini/neonati a un adulto pagante prima di proseguire.', 'born-to-ride-booking'); ?>');
                return false;
            }

            // Aggiorna automaticamente le quote in base alle assegnazioni
            const assignments = {};
            $('.btr-assignment-row .btr-child-assignment').each(function(){ const a=$(this).val(); if(a!==null && a!==''){ assignments[a]=(assignments[a]||0)+1; }});
            $('.participant-checkbox:checked').each(function(){
                const idx = $(this).data('index').toString();
                const add = assignments[idx] || 0;
                if (add>0){ const $s=$('#shares_'+idx); $s.prop('disabled', false); $s.val((parseInt($s.val()||'0')+add)); $s.trigger('input'); }
            });

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
                if (response.success) {
                    // Redirect al checkout o pagina successiva
                    if (response.data && response.data.redirect_url) {
                        window.location.href = response.data.redirect_url;
                    } else {
                        window.location.href = '<?php echo wc_get_checkout_url(); ?>';
                    }
                } else {
                    const message = (response.data && response.data.message) 
                        ? response.data.message 
                        : '<?php esc_attr_e('Si è verificato un errore', 'born-to-ride-booking'); ?>';
                    alert(message);
                    $submitBtn.prop('disabled', false).html('<?php esc_attr_e('Procedi al Checkout', 'born-to-ride-booking'); ?> <svg class="btr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>');
                }
            },
            error: function(xhr, status, error) {
                alert('<?php esc_attr_e('Errore di connessione. Riprova.', 'born-to-ride-booking'); ?>');
                $submitBtn.prop('disabled', false).html('<?php esc_attr_e('Procedi al Checkout', 'born-to-ride-booking'); ?> <svg class="btr-btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" width="20" height="20"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path></svg>');
            }
        });
    });
});
</script>
