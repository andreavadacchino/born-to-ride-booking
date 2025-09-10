<?php
/**
 * Template per la pagina di selezione piano pagamento - Design Unificato
 * 
 * @package BornToRideBooking
 * @since 1.0.102
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
    
    // Determina il prezzo base correttamente
    $prezzo_base_calcolato = 0;
    if (!empty($riepilogo_dettagliato) && is_array($riepilogo_dettagliato) && 
        !empty($riepilogo_dettagliato['totali'])) {
        // USA LA STESSA LOGICA DEL CHECKOUT
        $totali = $riepilogo_dettagliato['totali'];
        // IMPORTANTE: Il totale camere NON include i supplementi extra
        $prezzo_base_calcolato = floatval($totali['subtotale_prezzi_base'] ?? 0) + 
                                floatval($totali['subtotale_supplementi_base'] ?? 0) + 
                                floatval($totali['subtotale_notti_extra'] ?? 0);
    } else {
        // FALLBACK: usa _prezzo_totale che include camere + supplementi + notti extra
        $prezzo_base_calcolato = floatval($all_meta['_prezzo_totale'][0] ?? 0);
    }
    
    // Recupera i dati anagrafici e costi extra
    $anagrafici = maybe_unserialize($all_meta['_anagrafici_preventivo'][0] ?? '');
    $costi_extra_durata = maybe_unserialize($all_meta['_costi_extra_durata'][0] ?? '');
    
    // USA BTR_Price_Calculator come fa il checkout per calcolare dinamicamente assicurazioni e costi extra
    $price_calculator = btr_price_calculator();
    $extra_costs_result = $price_calculator->calculate_extra_costs($anagrafici, $costi_extra_durata);
    
    // Calcola il totale assicurazioni dinamicamente
    $totale_assicurazioni_calcolato = 0;
    if (!empty($anagrafici) && is_array($anagrafici)) {
        foreach ($anagrafici as $persona) {
            if (!empty($persona['assicurazione']) && is_array($persona['assicurazione'])) {
                $totale_assicurazioni_calcolato += floatval($persona['assicurazione']['prezzo'] ?? 0);
            }
        }
    }
    
    // Recupera i supplementi extra che vanno aggiunti al totale ma non mostrati nel "Totale Camere"
    $supplementi_extra = 0;
    if (!empty($riepilogo_dettagliato['totali']['subtotale_supplementi_extra'])) {
        $supplementi_extra = floatval($riepilogo_dettagliato['totali']['subtotale_supplementi_extra']);
    }
    
    // Estrai i valori necessari
    $preventivo_data = [
        'pacchetto_id' => $all_meta['_pacchetto_id'][0] ?? 0,
        'numero_adulti' => intval($all_meta['_numero_adulti'][0] ?? 0),
        'numero_bambini' => intval($all_meta['_numero_bambini'][0] ?? 0),
        'numero_neonati' => intval($all_meta['_num_neonati'][0] ?? 0),
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
        'totale_preventivo' => 0 // Calcolato dopo
    ];
    
    // Calcola il totale finale includendo TUTTI i componenti (come fa il checkout)
    // IMPORTANTE: i supplementi_extra vanno aggiunti al totale finale ma NON sono inclusi in prezzo_base
    $preventivo_data['totale_preventivo'] = $preventivo_data['prezzo_base'] 
        + $preventivo_data['supplementi_extra']
        + $preventivo_data['totale_assicurazioni'] 
        + $preventivo_data['totale_costi_extra'];
    
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

// Mostra sempre la selezione del pagamento, anche se esiste già un piano

// Non usiamo get_header() perché siamo in uno shortcode
?>

<div class="btr-payment-selection-page">
    <div class="container">
        
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
                        
                        
                        <?php if ($totale_assicurazioni && $totale_assicurazioni > 0): ?>
                        <li>
                            <span class="label"><?php esc_html_e('Assicurazioni:', 'born-to-ride-booking'); ?></span>
                            <span class="value">+ <?php echo btr_format_price_i18n($totale_assicurazioni); ?></span>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (isset($totale_riduzioni) && $totale_riduzioni < 0): ?>
                        <li>
                            <span class="label"><?php esc_html_e('Riduzioni:', 'born-to-ride-booking'); ?></span>
                            <span class="value discount"><?php echo btr_format_price_i18n($totale_riduzioni); ?></span>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (isset($totale_aggiunte) && $totale_aggiunte > 0): ?>
                        <li>
                            <span class="label"><?php esc_html_e('Aggiunte:', 'born-to-ride-booking'); ?></span>
                            <span class="value">+ <?php echo btr_format_price_i18n($totale_aggiunte); ?></span>
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
              data-participants="<?php echo esc_attr($totale_persone); ?>">
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
                        <span class="option-icon icon-full" aria-hidden="true"></span>
                        <span class="option-content">
                            <span class="option-title"><?php esc_html_e('Pagamento Completo', 'born-to-ride-booking'); ?></span>
                            <span id="full-description" class="option-description">
                                <?php esc_html_e('Paga l\'intero importo in un\'unica soluzione', 'born-to-ride-booking'); ?>
                            </span>
                        </span>
                    </label>
                </div>
                
                <!-- Caparra + Saldo -->
                <div class="btr-payment-option" data-plan="deposit_balance">
                    <input type="radio" 
                           name="payment_plan" 
                           id="plan_deposit" 
                           value="deposit_balance"
                           aria-describedby="deposit-description deposit-config">
                    <label for="plan_deposit">
                        <span class="option-icon icon-deposit" aria-hidden="true"></span>
                        <span class="option-content">
                            <span class="option-title"><?php esc_html_e('Caparra + Saldo', 'born-to-ride-booking'); ?></span>
                            <span id="deposit-description" class="option-description">
                                <?php esc_html_e('Paga una caparra ora e il saldo successivamente', 'born-to-ride-booking'); ?>
                            </span>
                        </span>
                    </label>
                    
                    <div id="deposit-config" class="deposit-config" style="display: none;" aria-live="polite">
                        <label for="deposit_percentage">
                            <?php esc_html_e('Percentuale caparra:', 'born-to-ride-booking'); ?>
                            <span class="deposit-value" aria-live="polite"><?php echo $deposit_percentage; ?>%</span>
                        </label>
                        <input type="range" 
                               id="deposit_percentage"
                               name="deposit_percentage" 
                               min="10" 
                               max="90" 
                               value="<?php echo $deposit_percentage; ?>" 
                               step="5"
                               aria-valuemin="10"
                               aria-valuemax="90"
                               aria-valuenow="<?php echo $deposit_percentage; ?>">
                        <div class="deposit-amounts" aria-live="polite">
                            <span><?php esc_html_e('Caparra:', 'born-to-ride-booking'); ?> 
                                <strong class="deposit-amount"><?php echo btr_format_price_i18n($totale_preventivo * $deposit_percentage / 100); ?></strong>
                            </span>
                            <span><?php esc_html_e('Saldo:', 'born-to-ride-booking'); ?> 
                                <strong class="balance-amount"><?php echo btr_format_price_i18n($totale_preventivo * (100 - $deposit_percentage) / 100); ?></strong>
                            </span>
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
                        <span class="option-icon icon-group" aria-hidden="true"></span>
                        <span class="option-content">
                            <span class="option-title"><?php esc_html_e('Pagamento di Gruppo', 'born-to-ride-booking'); ?></span>
                            <span id="group-description" class="option-description">
                                <?php esc_html_e('Ogni partecipante paga la propria quota individualmente', 'born-to-ride-booking'); ?>
                            </span>
                        </span>
                    </label>
                    
                    <!-- Configurazione pagamento di gruppo -->
                    <div id="group-payment-config" class="group-payment-config" style="display: none;" aria-live="polite">
                        <h4><?php esc_html_e('Seleziona chi effettuerà il pagamento', 'born-to-ride-booking'); ?></h4>
                        
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
                        
                        // Calcola quota base per persona
                        $quota_per_persona = $totale_persone > 0 ? $totale_preventivo / $totale_persone : 0;
                        ?>
                        
                        <div class="group-participants">
                            <?php if (!empty($adulti_paganti)): ?>
                                <p class="description"><?php esc_html_e('Puoi selezionare quali adulti pagheranno e per quante quote ciascuno.', 'born-to-ride-booking'); ?></p>
                                
                                <table class="participants-table">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Partecipante', 'born-to-ride-booking'); ?></th>
                                            <th><?php esc_html_e('Seleziona', 'born-to-ride-booking'); ?></th>
                                            <th><?php esc_html_e('Quote da pagare', 'born-to-ride-booking'); ?></th>
                                            <th><?php esc_html_e('Importo', 'born-to-ride-booking'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($adulti_paganti as $adulto): ?>
                                        <tr class="participant-row" data-participant-index="<?php echo esc_attr($adulto['index']); ?>">
                                            <td>
                                                <strong><?php echo esc_html($adulto['nome']); ?></strong>
                                                <?php if ($adulto['email']): ?>
                                                    <br><small><?php echo esc_html($adulto['email']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <input type="checkbox" 
                                                       class="participant-checkbox"
                                                       name="group_participants[<?php echo $adulto['index']; ?>][selected]"
                                                       id="participant_<?php echo $adulto['index']; ?>"
                                                       value="1"
                                                       data-index="<?php echo $adulto['index']; ?>">
                                            </td>
                                            <td>
                                                <input type="number" 
                                                       class="participant-shares"
                                                       name="group_participants[<?php echo $adulto['index']; ?>][shares]"
                                                       id="shares_<?php echo $adulto['index']; ?>"
                                                       min="0"
                                                       max="<?php echo $totale_persone; ?>"
                                                       value="1"
                                                       disabled
                                                       data-index="<?php echo $adulto['index']; ?>"
                                                       data-quota="<?php echo $quota_per_persona; ?>">
                                                <input type="hidden" 
                                                       name="group_participants[<?php echo $adulto['index']; ?>][name]"
                                                       value="<?php echo esc_attr($adulto['nome']); ?>">
                                                <input type="hidden" 
                                                       name="group_participants[<?php echo $adulto['index']; ?>][email]"
                                                       value="<?php echo esc_attr($adulto['email']); ?>">
                                            </td>
                                            <td class="participant-amount">
                                                <strong><?php echo btr_format_price_i18n($quota_per_persona); ?></strong>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="total-row">
                                            <td colspan="2"><strong><?php esc_html_e('Totale', 'born-to-ride-booking'); ?></strong></td>
                                            <td><strong class="total-shares">0</strong> <?php esc_html_e('quote', 'born-to-ride-booking'); ?></td>
                                            <td><strong class="total-amount"><?php echo btr_format_price_i18n(0); ?></strong></td>
                                        </tr>
                                    </tfoot>
                                </table>
                                
                                <div class="group-payment-info">
                                    <p class="info-message">
                                        <span class="info-icon" aria-hidden="true"></span>
                                        <?php esc_html_e('Ogni partecipante selezionato riceverà un link personalizzato per effettuare il proprio pagamento.', 'born-to-ride-booking'); ?>
                                    </p>
                                    <p class="warning-message" id="shares-warning" style="display: none;">
                                        <span class="warning-icon" aria-hidden="true"></span>
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
                    <span class="info-icon" aria-hidden="true"></span>
                    <p><?php echo wp_kses_post($bank_transfer_info); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="btr-form-actions">
                <a href="javascript:history.back();" class="button button-secondary">
                    <?php esc_html_e('Indietro', 'born-to-ride-booking'); ?>
                </a>
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Procedi al Checkout', 'born-to-ride-booking'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Gli stili CSS sono caricati dal file payment-selection-unified.css -->

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
            $('#group-payment-config').slideDown();
            $('#deposit-config').slideUp();
        } else {
            $('.deposit-config').slideUp();
            $('.group-payment-config').slideUp();
        }
    });
    
    // Aggiorna valori caparra
    const totalAmount = <?php echo floatval($totale_preventivo); ?>;
    
    $('#deposit_percentage').on('input', function() {
        const percentage = parseInt($(this).val());
        const deposit = totalAmount * percentage / 100;
        const balance = totalAmount - deposit;
        
        $('.deposit-value').text(percentage + '%');
        $('.deposit-amount').text(formatPrice(deposit));
        $('.balance-amount').text(formatPrice(balance));
        
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
        const shares = parseInt($(this).val()) || 0;
        const quota = parseFloat($(this).data('quota'));
        const amount = shares * quota;
        
        $(this).closest('tr').find('.participant-amount strong').text(formatPrice(amount));
        
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
            totalAmount += shares * quotaPerPerson;
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
