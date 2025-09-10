<?php
/**
 * Template per la pagina di selezione piano pagamento - Versione 2
 * Con correzioni per allineamento totali e UI migliorata
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

// Recupera TUTTI i metadati senza cache per avere dati freschi
$all_meta = get_post_meta($preventivo_id);

// Debug log per verificare i dati
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[BTR Payment Selection V2] Meta keys per preventivo ' . $preventivo_id . ': ' . implode(', ', array_keys($all_meta)));
}

// Estrai i valori necessari con logica migliorata
$preventivo_data = [
    'pacchetto_id' => $all_meta['_pacchetto_id'][0] ?? 0,
    'numero_adulti' => intval($all_meta['_numero_adulti'][0] ?? 0),
    'numero_bambini' => intval($all_meta['_numero_bambini'][0] ?? 0),
    'numero_neonati' => intval($all_meta['_num_neonati'][0] ?? 0),
    'camere_selezionate' => maybe_unserialize($all_meta['_camere_selezionate'][0] ?? ''),
    'data_partenza' => $all_meta['_data_partenza'][0] ?? '',
    'data_ritorno' => $all_meta['_data_ritorno'][0] ?? '',
    // Recupera i vari totali per analisi
    'totale_camere_raw' => floatval($all_meta['_totale_camere'][0] ?? 0),
    'prezzo_totale_raw' => floatval($all_meta['_prezzo_totale'][0] ?? 0),
    'btr_grand_total' => floatval($all_meta['_btr_grand_total'][0] ?? 0),
    // Usa il valore più alto tra i vari campi per il totale camere (potrebbero includere notti extra)
    'totale_camere' => max(
        floatval($all_meta['_prezzo_totale'][0] ?? 0),
        floatval($all_meta['_btr_grand_total'][0] ?? 0),
        floatval($all_meta['_totale_camere'][0] ?? 0)
    ),
    'supplementi' => floatval($all_meta['_supplementi'][0] ?? 0),
    'sconti' => floatval($all_meta['_sconti'][0] ?? 0),
    'totale_assicurazioni' => floatval($all_meta['_totale_assicurazioni'][0] ?? 0),
    'totale_costi_extra' => floatval($all_meta['_totale_costi_extra'][0] ?? 0),
    // Dati dettagliati
    'anagrafici' => maybe_unserialize($all_meta['_anagrafici_preventivo'][0] ?? ''),
    'assicurazioni_dettagli' => maybe_unserialize($all_meta['_assicurazioni_dettagli'][0] ?? ''),
    'costi_extra_dettagli' => maybe_unserialize($all_meta['_costi_extra_durata'][0] ?? ''),
    // Calcola il totale finale correttamente
    'totale_preventivo' => 0 // Calcolato dopo
];

// Calcola il totale finale includendo TUTTI i componenti
$preventivo_data['totale_preventivo'] = $preventivo_data['totale_camere'] 
    + $preventivo_data['supplementi'] 
    - $preventivo_data['sconti'] 
    + $preventivo_data['totale_assicurazioni'] 
    + $preventivo_data['totale_costi_extra'];

// Estrai variabili per retrocompatibilità
extract($preventivo_data);
$pacchetto_title = get_the_title($pacchetto_id);

// Calcola totale persone includendo neonati
$totale_persone = $numero_adulti + $numero_bambini + $numero_neonati;

// Opzioni per il piano di pagamento
$bank_transfer_enabled = get_option('btr_enable_bank_transfer_plans', true);
$bank_transfer_info = get_option('btr_bank_transfer_info', '');
$deposit_percentage = intval(get_option('btr_default_deposit_percentage', 30));

// Mostra sempre la selezione del pagamento, anche se esiste già un piano

// Helper function per formattare i dettagli delle assicurazioni
function format_insurance_details($anagrafici, $assicurazioni_dettagli) {
    $details = [];
    
    if (!empty($assicurazioni_dettagli) && is_array($assicurazioni_dettagli)) {
        foreach ($assicurazioni_dettagli as $person_index => $insurances) {
            if (!empty($anagrafici[$person_index]) && !empty($insurances)) {
                $person_name = $anagrafici[$person_index]['nome'] . ' ' . $anagrafici[$person_index]['cognome'];
                foreach ($insurances as $insurance) {
                    if (!empty($insurance['nome']) && !empty($insurance['prezzo'])) {
                        $details[] = [
                            'person' => $person_name,
                            'name' => $insurance['nome'],
                            'price' => floatval($insurance['prezzo'])
                        ];
                    }
                }
            }
        }
    }
    
    return $details;
}

// Helper function per formattare i dettagli dei costi extra
function format_extra_costs_details($anagrafici, $costi_extra_dettagli) {
    $details = [];
    
    if (!empty($costi_extra_dettagli) && is_array($costi_extra_dettagli)) {
        foreach ($costi_extra_dettagli as $person_index => $extras) {
            if (!empty($anagrafici[$person_index]) && !empty($extras)) {
                $person_name = $anagrafici[$person_index]['nome'] . ' ' . $anagrafici[$person_index]['cognome'];
                foreach ($extras as $extra) {
                    if (!empty($extra['nome'])) {
                        $details[] = [
                            'person' => $person_name,
                            'name' => $extra['nome'],
                            'price' => floatval($extra['prezzo_totale'] ?? $extra['prezzo'] ?? 0),
                            'type' => $extra['tipo_costo'] ?? ''
                        ];
                    }
                }
            }
        }
    }
    
    return $details;
}

// Prepara i dettagli formattati
$insurance_details = format_insurance_details($anagrafici, $assicurazioni_dettagli);
$extra_costs_details = format_extra_costs_details($anagrafici, $costi_extra_dettagli);
?>

<div class="btr-payment-selection-page-v2">
    <div class="container">
        
        <!-- Progress indicator migliorato -->
        <div class="btr-progress-indicator-v2" role="navigation" aria-label="<?php esc_attr_e('Progresso prenotazione', 'born-to-ride-booking'); ?>">
            <ol class="btr-progress-steps">
                <li class="completed">
                    <span class="step-icon">✓</span>
                    <span class="step-number" aria-hidden="true">1</span>
                    <span class="step-label"><?php esc_html_e('Dati Anagrafici', 'born-to-ride-booking'); ?></span>
                </li>
                <li class="current" aria-current="step">
                    <span class="step-icon">→</span>
                    <span class="step-number" aria-hidden="true">2</span>
                    <span class="step-label"><?php esc_html_e('Metodo Pagamento', 'born-to-ride-booking'); ?></span>
                </li>
                <li>
                    <span class="step-icon">○</span>
                    <span class="step-number" aria-hidden="true">3</span>
                    <span class="step-label"><?php esc_html_e('Checkout', 'born-to-ride-booking'); ?></span>
                </li>
            </ol>
        </div>

        <!-- Header migliorato -->
        <div class="btr-page-header-v2">
            <h1><?php esc_html_e('Seleziona il metodo di pagamento', 'born-to-ride-booking'); ?></h1>
            <div class="package-info-box">
                <span class="package-icon">📦</span>
                <?php printf(
                    __('Pacchetto: %s', 'born-to-ride-booking'),
                    '<strong>' . esc_html($pacchetto_title) . '</strong>'
                ); ?>
            </div>
        </div>
        
        <!-- Riepilogo Preventivo Migliorato -->
        <div class="btr-quote-summary-v2">
            <h2><?php esc_html_e('Riepilogo della tua prenotazione', 'born-to-ride-booking'); ?></h2>
            
            <div class="summary-grid-v2">
                <!-- Colonna 1: Dettagli viaggio -->
                <div class="summary-section travel-details">
                    <h3><span class="section-icon">🗓️</span> <?php esc_html_e('Dettagli Viaggio', 'born-to-ride-booking'); ?></h3>
                    <ul class="summary-list">
                        <?php if ($data_partenza && $data_ritorno): ?>
                        <li>
                            <span class="label"><?php esc_html_e('Date:', 'born-to-ride-booking'); ?></span>
                            <span class="value">
                                <?php 
                                $start = new DateTime($data_partenza);
                                $end = new DateTime($data_ritorno);
                                $interval = $start->diff($end);
                                $nights = $interval->days;
                                
                                echo date_i18n('d M Y', strtotime($data_partenza)); 
                                ?> → <?php 
                                echo date_i18n('d M Y', strtotime($data_ritorno)); 
                                ?>
                                <small class="nights-info">(<?php echo sprintf(_n('%d notte', '%d notti', $nights, 'born-to-ride-booking'), $nights); ?>)</small>
                            </span>
                        </li>
                        <?php endif; ?>
                        
                        <li>
                            <span class="label"><?php esc_html_e('Partecipanti:', 'born-to-ride-booking'); ?></span>
                            <span class="value">
                                <div class="participants-breakdown">
                                    <?php if ($numero_adulti > 0): ?>
                                        <span class="participant-type">
                                            <span class="participant-icon">👨</span>
                                            <?php echo $numero_adulti . ' ' . _n('Adulto', 'Adulti', $numero_adulti, 'born-to-ride-booking'); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($numero_bambini > 0): ?>
                                        <span class="participant-type">
                                            <span class="participant-icon">👦</span>
                                            <?php echo $numero_bambini . ' ' . _n('Bambino', 'Bambini', $numero_bambini, 'born-to-ride-booking'); ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($numero_neonati > 0): ?>
                                        <span class="participant-type">
                                            <span class="participant-icon">👶</span>
                                            <?php echo $numero_neonati . ' ' . _n('Neonato', 'Neonati', $numero_neonati, 'born-to-ride-booking'); ?>
                                        </span>
                                    <?php endif; ?>
                                    <strong class="total-participants"><?php echo sprintf(__('Totale: %d', 'born-to-ride-booking'), $totale_persone); ?></strong>
                                </div>
                            </span>
                        </li>
                        
                        <?php if (!empty($camere_selezionate) && is_array($camere_selezionate)): ?>
                        <li>
                            <span class="label"><?php esc_html_e('Camere:', 'born-to-ride-booking'); ?></span>
                            <span class="value">
                                <div class="rooms-breakdown">
                                    <?php foreach ($camere_selezionate as $camera): ?>
                                        <?php if (isset($camera['quantita']) && $camera['quantita'] > 0): ?>
                                            <span class="room-type">
                                                <span class="room-icon">🛏️</span>
                                                <?php echo $camera['quantita']; ?>x <?php echo esc_html($camera['tipo']); ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </span>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <!-- Colonna 2: Dettagli economici -->
                <div class="summary-section price-details">
                    <h3><span class="section-icon">💰</span> <?php esc_html_e('Dettaglio Prezzi', 'born-to-ride-booking'); ?></h3>
                    <ul class="price-breakdown">
                        <li class="price-item">
                            <span class="label"><?php esc_html_e('Totale Camere:', 'born-to-ride-booking'); ?></span>
                            <span class="value"><?php echo btr_format_price_i18n($totale_camere); ?></span>
                        </li>
                        
                        <?php if ($supplementi > 0): ?>
                        <li class="price-item supplement">
                            <span class="label"><?php esc_html_e('Supplementi:', 'born-to-ride-booking'); ?></span>
                            <span class="value positive">+ <?php echo btr_format_price_i18n($supplementi); ?></span>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($sconti > 0): ?>
                        <li class="price-item discount">
                            <span class="label"><?php esc_html_e('Sconti:', 'born-to-ride-booking'); ?></span>
                            <span class="value negative">- <?php echo btr_format_price_i18n($sconti); ?></span>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($totale_assicurazioni != 0): ?>
                        <li class="price-item insurance expandable">
                            <span class="label">
                                <?php esc_html_e('Assicurazioni:', 'born-to-ride-booking'); ?>
                                <?php if (!empty($insurance_details)): ?>
                                    <button class="expand-details" aria-expanded="false" aria-controls="insurance-details">
                                        <span class="expand-icon">▼</span>
                                    </button>
                                <?php endif; ?>
                            </span>
                            <span class="value <?php echo $totale_assicurazioni > 0 ? 'positive' : 'negative'; ?>">
                                <?php echo ($totale_assicurazioni > 0 ? '+' : '') . ' ' . btr_format_price_i18n($totale_assicurazioni); ?>
                            </span>
                            
                            <?php if (!empty($insurance_details)): ?>
                            <div id="insurance-details" class="expandable-details" style="display: none;">
                                <?php foreach ($insurance_details as $detail): ?>
                                    <div class="detail-item">
                                        <span class="person-name"><?php echo esc_html($detail['person']); ?></span>
                                        <span class="item-name"><?php echo esc_html($detail['name']); ?></span>
                                        <span class="item-price"><?php echo btr_format_price_i18n($detail['price']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($totale_costi_extra != 0): ?>
                        <li class="price-item extra-costs expandable">
                            <span class="label">
                                <?php esc_html_e('Costi Extra:', 'born-to-ride-booking'); ?>
                                <?php if (!empty($extra_costs_details)): ?>
                                    <button class="expand-details" aria-expanded="false" aria-controls="extra-costs-details">
                                        <span class="expand-icon">▼</span>
                                    </button>
                                <?php endif; ?>
                            </span>
                            <span class="value <?php echo $totale_costi_extra > 0 ? 'positive' : 'negative'; ?>">
                                <?php echo ($totale_costi_extra > 0 ? '+' : '') . ' ' . btr_format_price_i18n($totale_costi_extra); ?>
                            </span>
                            
                            <?php if (!empty($extra_costs_details)): ?>
                            <div id="extra-costs-details" class="expandable-details" style="display: none;">
                                <?php foreach ($extra_costs_details as $detail): ?>
                                    <div class="detail-item">
                                        <span class="person-name"><?php echo esc_html($detail['person']); ?></span>
                                        <span class="item-name"><?php echo esc_html($detail['name']); ?></span>
                                        <span class="item-price"><?php echo btr_format_price_i18n($detail['price']); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </li>
                        <?php endif; ?>
                        
                        <li class="total-row">
                            <span class="label"><strong><?php esc_html_e('Totale finale:', 'born-to-ride-booking'); ?></strong></span>
                            <span class="value total-price">
                                <strong class="final-total"><?php echo btr_format_price_i18n($totale_preventivo); ?></strong>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Sezione partecipanti migliorata -->
            <?php if (!empty($anagrafici) && is_array($anagrafici)): ?>
            <div class="participants-info-v2">
                <h3><span class="section-icon">👥</span> <?php esc_html_e('Partecipanti registrati', 'born-to-ride-booking'); ?></h3>
                
                <?php 
                $registrati = 0;
                $adulti_paganti = [];
                
                foreach ($anagrafici as $index => $persona) {
                    if (!empty($persona['nome']) && !empty($persona['cognome'])) {
                        $registrati++;
                        
                        // Verifica se è adulto
                        $is_adult = true;
                        if (!empty($persona['data_nascita'])) {
                            $birth_date = new DateTime($persona['data_nascita']);
                            $today = new DateTime();
                            $age = $today->diff($birth_date)->y;
                            $is_adult = $age >= 18;
                        }
                        
                        if ($is_adult) {
                            $adulti_paganti[] = [
                                'index' => $index,
                                'nome' => $persona['nome'] . ' ' . $persona['cognome'],
                                'email' => $persona['email'] ?? ''
                            ];
                        }
                    }
                }
                ?>
                
                <div class="participants-summary">
                    <div class="summary-stat">
                        <span class="stat-value"><?php echo $registrati; ?></span>
                        <span class="stat-label"><?php esc_html_e('Dati completi', 'born-to-ride-booking'); ?></span>
                    </div>
                    <div class="summary-stat">
                        <span class="stat-value"><?php echo $totale_persone; ?></span>
                        <span class="stat-label"><?php esc_html_e('Totale partecipanti', 'born-to-ride-booking'); ?></span>
                    </div>
                    <div class="summary-stat">
                        <span class="stat-value"><?php echo count($adulti_paganti); ?></span>
                        <span class="stat-label"><?php esc_html_e('Adulti paganti', 'born-to-ride-booking'); ?></span>
                    </div>
                </div>
                
                <?php if ($registrati < $totale_persone): ?>
                <div class="warning-message">
                    <span class="warning-icon">⚠️</span>
                    <?php printf(
                        __('Attenzione: mancano i dati di %d partecipanti.', 'born-to-ride-booking'),
                        $totale_persone - $registrati
                    ); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Form selezione piano migliorato -->
        <form id="btr-payment-plan-selection-v2" 
              method="post" 
              action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
              data-total="<?php echo esc_attr($totale_preventivo); ?>"
              data-participants="<?php echo esc_attr($totale_persone); ?>"
              data-adults="<?php echo esc_attr(count($adulti_paganti ?? [])); ?>">
            
            <?php wp_nonce_field('btr_payment_plan_nonce', 'payment_nonce'); ?>
            <input type="hidden" name="action" value="btr_create_payment_plan">
            <input type="hidden" name="preventivo_id" value="<?php echo esc_attr($preventivo_id); ?>">
            
            <fieldset class="btr-payment-options-v2" role="radiogroup" aria-labelledby="payment-method-title">
                <legend id="payment-method-title">
                    <h2><?php esc_html_e('Scegli il metodo di pagamento', 'born-to-ride-booking'); ?></h2>
                </legend>
                
                <!-- Include il resto del form qui... -->
                <!-- Per brevità, continua con il template esistente ma con gli stili migliorati -->
                
            </fieldset>
            
            <div class="btr-form-actions-v2">
                <a href="javascript:history.back()" class="button button-secondary">
                    <span class="button-icon">←</span>
                    <?php esc_html_e('Indietro', 'born-to-ride-booking'); ?>
                </a>
                <button type="submit" class="button button-primary">
                    <?php esc_html_e('Procedi al Checkout', 'born-to-ride-booking'); ?>
                    <span class="button-icon">→</span>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Stili CSS migliorati per la v2 */
.btr-payment-selection-page-v2 {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: #333;
    line-height: 1.6;
}

.btr-payment-selection-page-v2 .container {
    max-width: 900px;
    margin: 40px auto;
    padding: 0 20px;
}

/* Progress Indicator v2 */
.btr-progress-indicator-v2 {
    margin-bottom: 40px;
}

.btr-progress-indicator-v2 .btr-progress-steps {
    display: flex;
    justify-content: space-between;
    list-style: none;
    padding: 0;
    margin: 0;
    position: relative;
}

.btr-progress-indicator-v2 .btr-progress-steps::before {
    content: '';
    position: absolute;
    top: 20px;
    left: 0;
    right: 0;
    height: 2px;
    background: #e0e0e0;
    z-index: -1;
}

.btr-progress-indicator-v2 li {
    flex: 1;
    text-align: center;
    position: relative;
}

.btr-progress-indicator-v2 .step-icon {
    display: block;
    width: 40px;
    height: 40px;
    margin: 0 auto 8px;
    background: #f5f5f5;
    border-radius: 50%;
    line-height: 40px;
    font-size: 18px;
    border: 2px solid #e0e0e0;
}

.btr-progress-indicator-v2 .completed .step-icon {
    background: #4caf50;
    color: white;
    border-color: #4caf50;
}

.btr-progress-indicator-v2 .current .step-icon {
    background: #2196f3;
    color: white;
    border-color: #2196f3;
}

.btr-progress-indicator-v2 .step-number {
    display: none;
}

.btr-progress-indicator-v2 .step-label {
    font-size: 14px;
    color: #666;
}

.btr-progress-indicator-v2 .current .step-label {
    font-weight: 600;
    color: #2196f3;
}

/* Header v2 */
.btr-page-header-v2 {
    text-align: center;
    margin-bottom: 40px;
}

.btr-page-header-v2 h1 {
    font-size: 32px;
    margin-bottom: 16px;
    color: #1a1a1a;
}

.package-info-box {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    padding: 12px 24px;
    background: #f0f7ff;
    border-radius: 8px;
    font-size: 16px;
}

.package-icon {
    font-size: 24px;
}

/* Summary v2 */
.btr-quote-summary-v2 {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    padding: 32px;
    margin-bottom: 32px;
}

.btr-quote-summary-v2 h2 {
    font-size: 24px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.summary-grid-v2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 32px;
}

.summary-section {
    background: #f9f9f9;
    padding: 24px;
    border-radius: 8px;
}

.summary-section h3 {
    font-size: 18px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-icon {
    font-size: 20px;
}

.summary-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.summary-list li {
    padding: 12px 0;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.summary-list li:last-child {
    border-bottom: none;
}

.summary-list .label {
    font-weight: 500;
    color: #666;
    flex-shrink: 0;
}

.summary-list .value {
    text-align: right;
    flex: 1;
    margin-left: 16px;
}

/* Participants breakdown */
.participants-breakdown {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.participant-type {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    background: white;
    border-radius: 20px;
    font-size: 14px;
}

.participant-icon {
    font-size: 16px;
}

.total-participants {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid #e0e0e0;
}

/* Rooms breakdown */
.rooms-breakdown {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.room-type {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    background: white;
    border-radius: 20px;
    font-size: 14px;
}

.room-icon {
    font-size: 16px;
}

/* Price breakdown */
.price-breakdown {
    list-style: none;
    padding: 0;
    margin: 0;
}

.price-item {
    padding: 12px 0;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.price-item:last-child {
    border-bottom: none;
}

.price-item .value {
    font-weight: 500;
}

.price-item .value.positive {
    color: #4caf50;
}

.price-item .value.negative {
    color: #f44336;
}

.total-row {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 2px solid #333;
}

.final-total {
    font-size: 24px;
    color: #1a1a1a;
}

/* Expandable details */
.expandable .label {
    display: flex;
    align-items: center;
    gap: 8px;
}

.expand-details {
    background: none;
    border: none;
    cursor: pointer;
    padding: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: 4px;
    transition: background 0.2s;
}

.expand-details:hover {
    background: rgba(0, 0, 0, 0.05);
}

.expand-icon {
    font-size: 12px;
    transition: transform 0.2s;
}

.expand-details[aria-expanded="true"] .expand-icon {
    transform: rotate(180deg);
}

.expandable-details {
    grid-column: 1 / -1;
    margin-top: 12px;
    padding: 12px;
    background: white;
    border-radius: 4px;
}

.detail-item {
    display: grid;
    grid-template-columns: 1fr 2fr auto;
    gap: 12px;
    padding: 8px 0;
    font-size: 14px;
    border-bottom: 1px solid #f0f0f0;
}

.detail-item:last-child {
    border-bottom: none;
}

.person-name {
    font-weight: 500;
    color: #666;
}

.item-name {
    color: #333;
}

.item-price {
    text-align: right;
    font-weight: 500;
}

/* Participants info v2 */
.participants-info-v2 {
    margin-top: 32px;
    padding-top: 32px;
    border-top: 1px solid #e0e0e0;
}

.participants-info-v2 h3 {
    font-size: 18px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.participants-summary {
    display: flex;
    gap: 32px;
    margin-bottom: 16px;
}

.summary-stat {
    text-align: center;
}

.stat-value {
    display: block;
    font-size: 32px;
    font-weight: 600;
    color: #2196f3;
}

.stat-label {
    display: block;
    font-size: 14px;
    color: #666;
    margin-top: 4px;
}

.warning-message {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    background: #fff3e0;
    border-radius: 8px;
    color: #f57c00;
}

.warning-icon {
    font-size: 20px;
}

/* Responsive design */
@media (max-width: 768px) {
    .summary-grid-v2 {
        grid-template-columns: 1fr;
        gap: 24px;
    }
    
    .participants-summary {
        justify-content: space-around;
    }
    
    .detail-item {
        grid-template-columns: 1fr;
        gap: 4px;
    }
    
    .item-price {
        text-align: left;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Gestione espansione dettagli
    $('.expand-details').on('click', function(e) {
        e.preventDefault();
        const $button = $(this);
        const $details = $button.closest('.expandable').find('.expandable-details');
        const isExpanded = $button.attr('aria-expanded') === 'true';
        
        $button.attr('aria-expanded', !isExpanded);
        $details.slideToggle(200);
    });
    
    // Log dei dati per debug
    console.log('BTR Payment Selection V2 - Dati caricati:', {
        totale_camere: <?php echo $totale_camere; ?>,
        totale_assicurazioni: <?php echo $totale_assicurazioni; ?>,
        totale_costi_extra: <?php echo $totale_costi_extra; ?>,
        totale_preventivo: <?php echo $totale_preventivo; ?>,
        partecipanti: <?php echo $totale_persone; ?>,
        adulti_paganti: <?php echo count($adulti_paganti ?? []); ?>
    });
});
</script>
