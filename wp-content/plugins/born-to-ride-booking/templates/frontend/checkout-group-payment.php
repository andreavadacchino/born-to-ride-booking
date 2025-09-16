<?php
/**
 * Template pagamento gruppo con privacy fix e design system BTR
 * v1.0.244 - Fix jQuery loading con WordPress header/footer
 */

if (!defined('ABSPATH')) {
    exit;
}

// Carica header WordPress per includere jQuery e altri script
get_header();

// Recupera dati pagamento
$payment_hash = get_query_var('hash');
if (empty($payment_hash)) {
    echo '<div class="btr-error-message">Link di pagamento non valido</div>';
    return;
}

global $wpdb;
$payment = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}btr_group_payments WHERE payment_hash = %s",
    $payment_hash
));

if (!$payment) {
    echo '<div class="btr-error-message">Link di pagamento non valido o scaduto.</div>';
    return;
}

// Verifica se già pagato
if ($payment->payment_status === 'paid') {
    echo '<div class="btr-success-message">
        <i class="dashicons dashicons-yes-alt"></i>
        Questo pagamento è già stato completato. Grazie!
    </div>';
    return;
}

// Recupera dati preventivo
$preventivo_id = $payment->preventivo_id;
$package_id = get_post_meta($preventivo_id, '_pacchetto_id', true);
$package_title = $package_id ? get_the_title($package_id) : 'Viaggio';
$data_partenza = get_post_meta($preventivo_id, '_data_partenza', true) ?: get_post_meta($preventivo_id, '_data_pacchetto', true);
$anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);

// Pre-compila dati se disponibili
$user_data = [];
if ($anagrafici && is_array($anagrafici)) {
    foreach ($anagrafici as $persona) {
        if (!empty($persona['email']) && $persona['email'] === $payment->participant_email) {
            $user_data = [
                'first_name' => $persona['nome'] ?? '',
                'last_name' => $persona['cognome'] ?? '',
                'email' => $persona['email'] ?? '',
                'phone' => $persona['telefono'] ?? ''
            ];
            break;
        }
    }
}

// Calcola dettagli pagamento completi
$totale_viaggio = floatval(get_post_meta($preventivo_id, '_totale_preventivo', true) ?: get_post_meta($preventivo_id, '_prezzo_totale', true));
$totale_assicurazioni = floatval(get_post_meta($preventivo_id, '_totale_assicurazioni', true));
$totale_extra = floatval(get_post_meta($preventivo_id, '_totale_costi_extra', true));
$totale_notti_extra = floatval(get_post_meta($preventivo_id, '_btr_totale_notti_extra', true));
$prezzo_base = floatval(get_post_meta($preventivo_id, '_prezzo_base', true));
$num_adulti = intval(get_post_meta($preventivo_id, '_num_adults', true));
$num_bambini = intval(get_post_meta($preventivo_id, '_num_children', true));
$num_neonati = intval(get_post_meta($preventivo_id, '_num_neonati', true));
$totale_partecipanti = $num_adulti + $num_bambini + $num_neonati;
$durata_viaggio = get_post_meta($preventivo_id, '_durata_viaggio', true) ?: '3 giorni';

// Recupera anagrafici per trovare i costi extra del partecipante specifico
$anagrafici_data = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
$dettagli_extra = [];
$assicurazioni_partecipante = [];
$partecipante_index = -1;
$partecipante_data = null;

if ($anagrafici_data) {
    if (is_string($anagrafici_data)) {
        $anagrafici_data = @unserialize($anagrafici_data);
    }
    
    // Trova l'indice del partecipante che sta pagando
    if (is_array($anagrafici_data)) {
        foreach ($anagrafici_data as $index => $anagrafico) {
            $nome_completo = trim($anagrafico['nome'] . ' ' . $anagrafico['cognome']);
            if (strcasecmp($nome_completo, $payment->participant_name) === 0) {
                $partecipante_index = $index;
                $partecipante_data = $anagrafico;
                break;
            }
        }
    }
}

// Se abbiamo trovato il partecipante, recupera i suoi costi extra personali
if ($partecipante_index >= 0) {
    // Recupera assicurazioni del partecipante
    if (!empty($partecipante_data['assicurazioni_dettagliate'])) {
        foreach ($partecipante_data['assicurazioni_dettagliate'] as $key => $assicurazione) {
            if (!empty($assicurazione['descrizione']) && $assicurazione['importo'] > 0) {
                $assicurazioni_partecipante[] = [
                    'nome' => $assicurazione['descrizione'],
                    'importo' => floatval($assicurazione['importo'])
                ];
            }
        }
    }
    
    // Recupera costi extra specifici del partecipante
    $listino_costi_extra = get_post_meta($preventivo_id, '_btr_listino_costi_extra', true);
    if (is_string($listino_costi_extra)) {
        $listino_costi_extra = @unserialize($listino_costi_extra);
    }
    
    // Cerca i costi extra selezionati per questo partecipante
    $extra_keys = ['animale_domestico', 'culla_per_neonati', 'no_skipass'];
    foreach ($extra_keys as $extra_key) {
        $meta_key = "_anagrafico_{$partecipante_index}_extra_{$extra_key}_selected";
        $selected = get_post_meta($preventivo_id, $meta_key, true);
        
        if ($selected == '1') {
            $price_key = "_anagrafico_{$partecipante_index}_extra_{$extra_key}_price";
            $price = floatval(get_post_meta($preventivo_id, $price_key, true));
            
            if ($price != 0) {
                $nome = ucwords(str_replace('_', ' ', $extra_key));
                $dettagli_extra[] = [
                    'nome' => $nome,
                    'importo' => $price
                ];
            }
        }
    }
}

// Recupera notti extra (se applicabili al partecipante)
$totale_notti_extra = floatval(get_post_meta($preventivo_id, '_btr_totale_notti_extra', true));
$data_notte_extra = get_post_meta($preventivo_id, '_btr_notti_extra_data', true);
$prezzo_notte_extra_pp = floatval(get_post_meta($preventivo_id, '_btr_notti_extra_prezzo_pp', true));

// Calcola la quota notti extra per questo partecipante
$quota_notte_extra_partecipante = 0;
if ($totale_notti_extra > 0 && $partecipante_data) {
    // Le notti extra sono per persona, quindi calcola in base al tipo di persona
    if ($partecipante_data['tipo_persona'] == 'adulto') {
        $quota_notte_extra_partecipante = $prezzo_notte_extra_pp;
    } else if ($partecipante_data['tipo_persona'] == 'bambino') {
        // Per i bambini potrebbe esserci un prezzo diverso
        $quota_notte_extra_partecipante = $prezzo_notte_extra_pp; // Adattare se c'è prezzo bambini
    }
    // I neonati di solito non pagano notti extra
}

// Verifica se pagamento multiplo
$pagamenti_per_persona = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}btr_group_payments WHERE preventivo_id = %d AND participant_name = %s",
    $preventivo_id,
    $payment->participant_name
));

// Conta partecipanti totali (privacy: solo numero)
$total_participants = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}btr_group_payments WHERE preventivo_id = %d",
    $preventivo_id
));

$paid_count = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}btr_group_payments WHERE preventivo_id = %d AND payment_status = 'paid'",
    $preventivo_id
));

// Ottieni metodi di pagamento disponibili
$available_gateways = [];
if (function_exists('WC')) {
    $payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
    foreach ($payment_gateways as $gateway_id => $gateway) {
        if ($gateway->enabled === 'yes') {
            $available_gateways[$gateway_id] = [
                'title' => $gateway->get_title(),
                'description' => $gateway->get_description(),
                'icon' => $gateway->get_icon()
            ];
        }
    }
}

// Se non ci sono gateway, usa bonifico come default
if (empty($available_gateways)) {
    $available_gateways['bacs'] = [
        'title' => 'Bonifico Bancario',
        'description' => 'Paga tramite bonifico bancario',
        'icon' => ''
    ];
}
?>

<style>
/* Layout BTR standard con design system plugin */
.btr-payment-container {
    max-width: 600px;
    margin: 0 auto;
    padding: 20px;
    font-family: inherit;
}

.btr-payment-header {
    text-align: center;
    margin-bottom: 40px;
    padding: 30px 20px;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
    border: 1px solid #e0e0e0;
    color: #2c3e50;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    position: relative;
}

.btr-logo-wrapper {
    margin-bottom: 20px;
}

.btr-logo-wrapper img {
    max-height: 60px;
    width: auto;
}

.btr-payment-header h1 {
    font-size: 28px;
    margin-bottom: 10px;
    color: #2c3e50;
    font-weight: 700;
}

.btr-payment-header .btr-trip-title {
    font-size: 20px;
    color: #0097c5;
    margin-bottom: 5px;
    font-weight: 600;
}

.btr-payment-header .btr-trip-date {
    font-size: 16px;
    color: #6c757d;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
}

/* Trip info section */
.btr-trip-info {
    background: #fff;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    border: 1px solid #e0e0e0;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
}

.btr-trip-info h3 {
    color: #2c3e50;
    margin-bottom: 15px;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btr-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.btr-info-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.btr-info-item .label {
    font-size: 14px;
    color: #6c757d;
}

.btr-info-item strong {
    font-size: 16px;
    color: #2c3e50;
}

.btr-info-item .detail {
    font-size: 13px;
    color: #6c757d;
}

.btr-payment-summary {
    background: #fff;
    border-radius: 8px;
    padding: 25px;
    margin-bottom: 30px;
    border: 2px solid #0097c5;
    box-shadow: 0 2px 8px rgba(0,151,197,0.1);
}

.btr-payment-summary h3 {
    color: #2c3e50;
    margin-bottom: 20px;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btr-summary-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #e9ecef;
}

.btr-cost-breakdown {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 6px;
    margin: 15px 0;
}

.btr-divider {
    height: 2px;
    background: #e0e0e0;
    margin: 20px 0;
}

.btr-summary-item:last-child {
    border-bottom: none;
    font-weight: 600;
    font-size: 20px;
    padding-top: 20px;
    color: #0097c5;
}

.btr-badge {
    background: #0097c5;
    color: white;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 12px;
    margin-left: 10px;
}

.btr-progress-bar {
    background: #e9ecef;
    border-radius: 4px;
    height: 8px;
    margin: 20px 0;
    overflow: hidden;
}

.btr-progress-fill {
    background: #28a745;
    height: 100%;
    transition: width 0.3s ease;
}

.btr-form-section {
    background: white;
    border-radius: 8px;
    padding: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    border: 1px solid #e0e0e0;
}

.btr-form-group {
    margin-bottom: 20px;
}

.btr-form-group label {
    display: block;
    font-weight: 600;
    margin-bottom: 8px;
    color: #495057;
    font-size: 15px;
}

.btr-form-group input,
.btr-form-group select {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e9ecef;
    border-radius: 8px;
    font-size: 16px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.btr-form-group input:focus,
.btr-form-group select:focus {
    outline: none;
    border-color: #0097c5;
    box-shadow: 0 0 0 3px rgba(0, 151, 197, 0.1);
}

.btr-required {
    color: #dc3545;
}

/* Payment method selection BTR style */
.btr-payment-methods {
    margin-bottom: 25px;
}

.btr-payment-method-option {
    border: 2px solid #e9ecef;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btr-payment-method-option:hover {
    border-color: #0097c5;
    background: #f8f9fa;
}

.btr-payment-method-option.selected {
    border-color: #0097c5;
    background: #e3f5fb;
}

.btr-payment-method-option label {
    display: flex;
    align-items: center;
    cursor: pointer;
    margin: 0;
}

.btr-payment-method-option input[type="radio"] {
    margin-right: 12px;
    width: auto;
}

.btr-payment-method-title {
    font-weight: 600;
    color: #2c3e50;
}

.btr-payment-method-desc {
    font-size: 14px;
    color: #6c757d;
    margin-top: 5px;
    margin-left: 28px;
}

.btr-submit-button {
    width: 100%;
    padding: 16px 28px;
    background: #0097c5;
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 18px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.btr-submit-button:hover {
    background: #0087b3;
    transform: translateY(-1px);
    box-shadow: 0 5px 25px rgba(0, 151, 197, 0.25);
}

.btr-submit-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
    background: #6c757d;
}

.btr-privacy-notice {
    text-align: center;
    margin-top: 20px;
    font-size: 14px;
    color: #6c757d;
}

/* Trust indicators */
.btr-trust-indicators {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 30px;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #e0e0e0;
}

.btr-trust-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #6c757d;
    font-size: 14px;
}

.btr-trust-item .dashicons {
    color: #28a745;
    font-size: 20px;
}

.btr-loading {
    display: none;
    text-align: center;
    margin-top: 20px;
}

.btr-loading.active {
    display: block;
}

/* Sticky button mobile */
@media (max-width: 768px) {
    .btr-submit-button {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        border-radius: 0;
        z-index: 9999;
        width: 100%;
        padding: 18px;
    }
    
    .btr-form-section {
        padding-bottom: 80px; /* Space for sticky button */
    }
}

/* Mobile responsive BTR standard */
@media (max-width: 768px) {
    .btr-payment-container {
        padding: 10px;
    }
    
    .btr-payment-header {
        padding: 20px 15px;
        border-radius: 8px;
    }
    
    .btr-payment-header h1 {
        font-size: 24px;
    }
    
    .btr-form-section {
        padding: 20px 15px;
        border-radius: 8px;
    }
    
    /* Larger touch targets for mobile */
    .btr-form-group input,
    .btr-form-group select {
        min-height: 48px;
        font-size: 16px;
    }
    
    .btr-payment-method-option {
        padding: 18px 12px;
    }
    
    .btr-trust-indicators {
        flex-direction: column;
        gap: 15px;
    }
    
    /* Remove confusing sticky for total on mobile */
}

/* Messaggi BTR standard */
.btr-error-message,
.btr-success-message {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 500;
}

.btr-error-message {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.btr-success-message {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

/* Details BTR style */
details summary {
    cursor: pointer;
    font-weight: 600;
    color: #0097c5;
    padding: 10px 0;
    transition: color 0.3s ease;
}

details summary:hover {
    color: #0087b3;
}

details[open] summary {
    margin-bottom: 15px;
    color: #0087b3;
}

/* Progress info BTR style */
.btr-progress-info {
    margin: 20px 0;
}

.btr-progress-info p {
    color: #6c757d;
    margin-bottom: 10px;
    font-size: 15px;
}

/* Icons BTR compatibility */
.dashicons {
    width: 20px;
    height: 20px;
    font-size: 20px;
}

/* Spinner animation */
.spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(0, 151, 197, 0.3);
    border-radius: 50%;
    border-top-color: #0097c5;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>

<div class="btr-payment-container">
    <!-- Header con Logo -->
    <div class="btr-payment-header">
        <?php 
        $site_logo = get_custom_logo();
        if ($site_logo) {
            echo '<div class="btr-logo-wrapper">' . $site_logo . '</div>';
        }
        ?>
        <h1>Pagamento Sicuro</h1>
        <p class="btr-trip-title">
            <span style="font-size: 1.1em;">🏔️</span> <?php echo esc_html($package_title); ?>
        </p>
        <p style="font-size: 18px; color: #2c3e50; margin: 10px 0;">
            <span style="font-size: 1.1em;">📍</span> 
            <strong><?php echo esc_html(get_post_meta($pacchetto_id, 'btr_localita_destinazione', true) ?: 'Italia'); ?></strong>
        </p>
        <?php if ($data_partenza): ?>
        <p class="btr-trip-date">
            <span class="dashicons dashicons-calendar-alt"></span>
            <?php echo date_i18n('d F Y', strtotime($data_partenza)); ?> • <?php echo esc_html($durata_viaggio); ?>
        </p>
        <?php endif; ?>
    </div>
    
    <!-- Chi sta pagando - CHIARO e VISIBILE -->
    <div class="btr-payment-for" style="background: #e8f4f8; border: 2px solid #0097c5; border-radius: 8px; padding: 20px; margin-bottom: 25px; text-align: center;">
        <h3 style="color: #0097c5; margin-bottom: 10px; font-size: 20px;">✓ Stai pagando per:</h3>
        <p style="font-size: 24px; font-weight: bold; color: #2c3e50; margin: 0;"><?php echo esc_html($payment->participant_name); ?></p>
    </div>
    
    <!-- Informazioni disponibili sul viaggio -->
    <div class="btr-trip-info">
        <h3><span class="dashicons dashicons-info"></span> Dettagli del Viaggio</h3>
        <div class="btr-info-grid">
            <div class="btr-info-item">
                <span class="label">Destinazione</span>
                <strong><?php echo esc_html(get_post_meta($pacchetto_id, 'btr_localita_destinazione', true) ?: 'Italia'); ?></strong>
                <span class="detail"><?php echo esc_html($durata_viaggio); ?></span>
            </div>
            <?php if ($data_partenza): ?>
            <div class="btr-info-item">
                <span class="label">Data partenza</span>
                <strong><?php echo date_i18n('d F Y', strtotime($data_partenza)); ?></strong>
            </div>
            <?php endif; ?>
            <div class="btr-info-item">
                <span class="label">Partecipanti totali</span>
                <strong><?php echo $totale_partecipanti; ?> persone</strong>
                <span class="detail">(<?php echo $num_adulti; ?> adulti<?php if($num_bambini > 0) echo ', ' . $num_bambini . ' bambini'; ?><?php if($num_neonati > 0) echo ', ' . $num_neonati . ' neonati'; ?>)</span>
            </div>
            <div class="btr-info-item">
                <span class="label">Pacchetto</span>
                <strong><?php echo esc_html($package_title); ?></strong>
            </div>
        </div>
    </div>
    
    <!-- Riepilogo pagamento dettagliato -->
    <div class="btr-payment-summary">
        <h3><span class="dashicons dashicons-cart"></span> Dettaglio del tuo pagamento</h3>
        
        <div class="btr-summary-item">
            <span>Pagamento per</span>
            <strong><?php echo esc_html($payment->participant_name); ?></strong>
            <?php if ($pagamenti_per_persona > 1): ?>
            <span class="btr-badge">+ <?php echo ($pagamenti_per_persona - 1); ?> altri partecipanti</span>
            <?php endif; ?>
        </div>
        
        <!-- Breakdown dettagliato costi -->
        <div class="btr-cost-breakdown">
            <div class="btr-summary-item">
                <span>Quota base viaggio</span>
                <span><?php echo wc_price($prezzo_base / $totale_partecipanti); ?></span>
            </div>
            
            <?php 
            // Mostra assicurazioni personali del partecipante
            foreach ($assicurazioni_partecipante as $assicurazione): 
            ?>
            <div class="btr-summary-item">
                <span><?php echo esc_html($assicurazione['nome']); ?></span>
                <span><?php echo wc_price($assicurazione['importo']); ?></span>
            </div>
            <?php endforeach; ?>
            
            <?php 
            // Mostra notte extra personale se applicabile
            if ($quota_notte_extra_partecipante > 0 && $data_notte_extra): ?>
            <div class="btr-summary-item">
                <span>Notte extra (<?php echo esc_html($data_notte_extra); ?>)</span>
                <span><?php echo wc_price($quota_notte_extra_partecipante); ?></span>
            </div>
            <?php endif; ?>
            
            <?php 
            // Mostra costi extra personali del partecipante
            foreach ($dettagli_extra as $extra): 
                if ($extra['importo'] != 0):
            ?>
            <div class="btr-summary-item">
                <span><?php echo esc_html($extra['nome']); ?></span>
                <span><?php echo wc_price($extra['importo']); ?></span>
            </div>
            <?php 
                endif;
            endforeach; 
            ?>
        </div>
        
        <!-- Progress bar privacy-compliant -->
        <div class="btr-progress-info">
            <p><?php echo $paid_count; ?> su <?php echo $total_participants; ?> partecipanti hanno completato il pagamento</p>
            <div class="btr-progress-bar">
                <div class="btr-progress-fill" style="width: <?php echo ($total_participants > 0 ? ($paid_count / $total_participants * 100) : 0); ?>%"></div>
            </div>
        </div>
        
        <div class="btr-divider"></div>
        
        <?php if (!empty($payment->notes)): ?>
        <div class="btr-summary-item">
            <span>Note</span>
            <span><?php echo esc_html($payment->notes); ?></span>
        </div>
        <?php endif; ?>
        
        <div class="btr-summary-item" style="border-top: 2px solid #0097c5; padding-top: 20px; margin-top: 10px;">
            <span style="font-size: 20px; font-weight: 600;">Totale da pagare</span>
            <span style="font-size: 26px; font-weight: bold; color: #0097c5;"><?php echo wc_price($payment->amount); ?></span>
        </div>
    </div>
    
    <!-- Form pagamento semplificato -->
    <div class="btr-form-section">
        <h3>Dati per il pagamento</h3>
        <p style="color: #6c757d; margin-bottom: 20px;">Completa i dati richiesti e seleziona il metodo di pagamento</p>
        
        <form id="btr-group-payment-form" method="post">
            <?php wp_nonce_field('btr_group_payment_' . $payment_hash, 'btr_payment_nonce'); ?>
            <input type="hidden" name="payment_hash" value="<?php echo esc_attr($payment_hash); ?>">
            <input type="hidden" name="action" value="btr_process_group_payment">
            
            <!-- Campi essenziali pre-compilati -->
            <div class="btr-form-group">
                <label for="billing_first_name">Nome <span class="btr-required">*</span></label>
                <input type="text" id="billing_first_name" name="billing_first_name" 
                       value="<?php echo esc_attr($user_data['first_name'] ?? ''); ?>" required>
            </div>
            
            <div class="btr-form-group">
                <label for="billing_last_name">Cognome <span class="btr-required">*</span></label>
                <input type="text" id="billing_last_name" name="billing_last_name" 
                       value="<?php echo esc_attr($user_data['last_name'] ?? ''); ?>" required>
            </div>
            
            <div class="btr-form-group">
                <label for="billing_email">Email <span class="btr-required">*</span></label>
                <input type="email" id="billing_email" name="billing_email" 
                       value="<?php echo esc_attr($user_data['email'] ?? $payment->participant_email); ?>" required>
            </div>
            
            <!-- Selezione metodo di pagamento -->
            <div class="btr-form-group btr-payment-methods">
                <label>Metodo di pagamento <span class="btr-required">*</span></label>
                <?php 
                $first = true;
                foreach ($available_gateways as $gateway_id => $gateway): 
                ?>
                <div class="btr-payment-method-option <?php echo $first ? 'selected' : ''; ?>">
                    <label>
                        <input type="radio" name="payment_method" value="<?php echo esc_attr($gateway_id); ?>" 
                               <?php echo $first ? 'checked' : ''; ?> required>
                        <div>
                            <div class="btr-payment-method-title">
                                <?php echo esc_html($gateway['title']); ?>
                            </div>
                            <?php if (!empty($gateway['description'])): ?>
                            <div class="btr-payment-method-desc">
                                <?php echo esc_html($gateway['description']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </label>
                </div>
                <?php 
                $first = false;
                endforeach; 
                ?>
            </div>
            
            <!-- Campi opzionali nascosti di default -->
            <details style="margin-top: 20px;">
                <summary>
                    Aggiungi dati fatturazione (opzionale)
                </summary>
                
                <div class="btr-form-group">
                    <label for="billing_phone">Telefono</label>
                    <input type="tel" id="billing_phone" name="billing_phone" 
                           value="<?php echo esc_attr($user_data['phone'] ?? ''); ?>">
                </div>
                
                <div class="btr-form-group">
                    <label for="billing_address">Indirizzo</label>
                    <input type="text" id="billing_address" name="billing_address">
                </div>
                
                <div class="btr-form-group">
                    <label for="billing_city">Città</label>
                    <input type="text" id="billing_city" name="billing_city">
                </div>
                
                <div class="btr-form-group">
                    <label for="billing_postcode">CAP</label>
                    <input type="text" id="billing_postcode" name="billing_postcode">
                </div>
                
                <div class="btr-form-group">
                    <label for="billing_cf">Codice Fiscale / P.IVA</label>
                    <input type="text" id="billing_cf" name="billing_cf">
                </div>
            </details>
            
            <!-- Checkbox termini e condizioni -->
            <div class="btr-form-group" style="margin-top: 20px; margin-bottom: 20px;">
                <label style="display: flex; align-items: flex-start; gap: 10px;">
                    <input type="checkbox" name="terms" id="terms" required style="margin-top: 3px;">
                    <span style="font-size: 14px;">
                        Accetto i <a href="/termini-condizioni/" target="_blank" style="color: #0097c5;">termini e condizioni</a> 
                        e la <a href="/privacy-policy/" target="_blank" style="color: #0097c5;">privacy policy</a> *
                    </span>
                </label>
            </div>
            
            <!-- Totale ripetuto vicino al bottone -->
            <div style="text-align: center; margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                <p style="margin: 0; font-size: 18px; color: #2c3e50;">Importo totale:</p>
                <p style="margin: 5px 0 0 0; font-size: 32px; font-weight: bold; color: #0097c5;"><?php echo wc_price($payment->amount); ?></p>
            </div>
            
            <button type="submit" class="btr-submit-button">
                Procedi al Pagamento di <?php echo wc_price($payment->amount); ?>
            </button>
            
            <div class="btr-loading">
                <span class="spinner is-active"></span>
                <p>Elaborazione in corso...</p>
            </div>
            
            <div class="btr-privacy-notice">
                <p>🔒 Transazione protetta SSL • Dati trattati secondo GDPR</p>
                <p style="margin-top: 10px;">Riceverai conferma via email entro 24 ore</p>
            </div>
        </form>
        
        <!-- Indicatori di fiducia con dati reali -->
        <div class="btr-trust-indicators">
            <div class="btr-trust-item">
                <span class="dashicons dashicons-shield"></span>
                <span>Pagamento SSL Sicuro</span>
            </div>
            <div class="btr-trust-item">
                <span class="dashicons dashicons-email"></span>
                <span>Supporto: <?php echo esc_html(get_option('admin_email', '')); ?></span>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Selezione metodo pagamento
    $('.btr-payment-method-option').on('click', function() {
        $('.btr-payment-method-option').removeClass('selected');
        $(this).addClass('selected');
        $(this).find('input[type="radio"]').prop('checked', true);
    });
    
    // Form submission handler
    $('#btr-group-payment-form').on('submit', function(e) {
        e.preventDefault();
        
        var $form = $(this);
        var $button = $form.find('.btr-submit-button');
        var $loading = $form.find('.btr-loading');
        
        // Disabilita submit
        $button.prop('disabled', true);
        $loading.addClass('active');
        
        // Invia richiesta
        var formData = $form.serialize() + '&action=btr_process_group_payment&nonce=' + btr_ajax_object.nonce;
        
        $.ajax({
            url: btr_ajax_object.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success && response.data.redirect) {
                    window.location.href = response.data.redirect;
                } else {
                    alert(response.data?.message || 'Errore durante l\'elaborazione del pagamento.');
                    $button.prop('disabled', false);
                    $loading.removeClass('active');
                }
            },
            error: function() {
                alert('Errore di connessione. Riprova.');
                $button.prop('disabled', false);
                $loading.removeClass('active');
            }
        });
    });
    
    // Auto-focus sul primo campo
    $('#billing_first_name').focus();
});
</script>

<?php
// Carica footer WordPress
get_footer();
