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
    echo '<div class="btr-error-message">' . esc_html__('Link di pagamento non valido', 'born-to-ride-booking') . '</div>';
    return;
}

global $wpdb;
$payment = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}btr_group_payments WHERE payment_hash = %s",
        $payment_hash
    )
);

if (!$payment) {
    echo '<div class="btr-error-message">' . esc_html__('Link di pagamento non valido o scaduto.', 'born-to-ride-booking') . '</div>';
    return;
}

// Verifica se già pagato
if ($payment->payment_status === 'paid') {
    echo '<div class="btr-success-message"><i class="dashicons dashicons-yes-alt"></i>' . esc_html__('Questo pagamento è già stato completato. Grazie!', 'born-to-ride-booking') . '</div>';
    return;
}

// Recupera dati preventivo
$preventivo_id = $payment->preventivo_id;
$package_id    = get_post_meta($preventivo_id, '_pacchetto_id', true);
$package_title = $package_id ? get_the_title($package_id) : esc_html__('Viaggio', 'born-to-ride-booking');
$destinazione  = $package_id ? get_post_meta($package_id, 'btr_localita_destinazione', true) : '';
$data_partenza = get_post_meta($preventivo_id, '_data_partenza', true) ?: get_post_meta($preventivo_id, '_data_pacchetto', true);
$anagrafici    = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);

// Pre-compila dati se disponibili
$user_data = [];
if ($anagrafici && is_array($anagrafici)) {
    foreach ($anagrafici as $persona) {
        if (!empty($persona['email']) && $persona['email'] === $payment->participant_email) {
            $user_data = [
                'first_name' => $persona['nome'] ?? '',
                'last_name'  => $persona['cognome'] ?? '',
                'email'      => $persona['email'] ?? '',
                'phone'      => $persona['telefono'] ?? '',
            ];
            break;
        }
    }
}

// Calcola dettagli pagamento completi
$totale_viaggio       = floatval(get_post_meta($preventivo_id, '_totale_preventivo', true) ?: get_post_meta($preventivo_id, '_prezzo_totale', true));
$totale_assicurazioni = floatval(get_post_meta($preventivo_id, '_totale_assicurazioni', true));
$totale_extra         = floatval(get_post_meta($preventivo_id, '_totale_costi_extra', true));
$totale_notti_extra   = floatval(get_post_meta($preventivo_id, '_btr_totale_notti_extra', true));
$prezzo_base          = floatval(get_post_meta($preventivo_id, '_prezzo_base', true));
$num_adulti           = intval(get_post_meta($preventivo_id, '_num_adults', true));
$num_bambini          = intval(get_post_meta($preventivo_id, '_num_children', true));
$num_neonati          = intval(get_post_meta($preventivo_id, '_num_neonati', true));
$totale_partecipanti  = $num_adulti + $num_bambini + $num_neonati;
$durata_viaggio       = get_post_meta($preventivo_id, '_durata_viaggio', true) ?: esc_html__('3 giorni', 'born-to-ride-booking');

// Recupera anagrafici per trovare i costi extra del partecipante specifico
$anagrafici_data = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
$dettagli_extra  = [];
$assicurazioni_partecipante = [];
$partecipante_index       = -1;
$partecipante_data        = null;

if ($anagrafici_data) {
    if (is_string($anagrafici_data)) {
        $anagrafici_data = @unserialize($anagrafici_data);
    }

    if (is_array($anagrafici_data)) {
        foreach ($anagrafici_data as $index => $anagrafico) {
            $nome_completo = trim(($anagrafico['nome'] ?? '') . ' ' . ($anagrafico['cognome'] ?? ''));
            if (strcasecmp($nome_completo, $payment->participant_name) === 0) {
                $partecipante_index = $index;
                $partecipante_data  = $anagrafico;
                break;
            }
        }
    }
}

if ($partecipante_index >= 0 && $partecipante_data) {
    if (!empty($partecipante_data['assicurazioni_dettagliate'])) {
        foreach ($partecipante_data['assicurazioni_dettagliate'] as $assicurazione) {
            $importo = isset($assicurazione['importo']) ? floatval($assicurazione['importo']) : 0;
            if (!empty($assicurazione['descrizione']) && $importo > 0) {
                $assicurazioni_partecipante[] = [
                    'nome'    => $assicurazione['descrizione'],
                    'importo' => $importo,
                ];
            }
        }
    }

    $extra_keys = ['animale_domestico', 'culla_per_neonati', 'no_skipass'];
    foreach ($extra_keys as $extra_key) {
        $meta_key = "_anagrafico_{$partecipante_index}_extra_{$extra_key}_selected";
        if ('1' === get_post_meta($preventivo_id, $meta_key, true)) {
            $price_key = "_anagrafico_{$partecipante_index}_extra_{$extra_key}_price";
            $price     = floatval(get_post_meta($preventivo_id, $price_key, true));
            if (0 !== $price) {
                $label = str_replace(['_', '-'], ' ', $extra_key);
                $label = ucwords($label);
                $dettagli_extra[] = [
                    'nome'    => $label,
                    'importo' => $price,
                ];
            }
        }
    }
}

$totale_assicurazioni_partecipante = 0.0;
foreach ($assicurazioni_partecipante as $assicurazione_item) {
    $totale_assicurazioni_partecipante += floatval($assicurazione_item['importo']);
}

$totale_extra_partecipante = 0.0;
foreach ($dettagli_extra as $extra_item) {
    $totale_extra_partecipante += floatval($extra_item['importo']);
}

$data_notte_extra             = get_post_meta($preventivo_id, '_btr_notti_extra_data', true);
$prezzo_notte_extra_pp        = floatval(get_post_meta($preventivo_id, '_btr_notti_extra_prezzo_pp', true));
$quota_notte_extra_partecipante = 0;
if ($totale_notti_extra > 0 && $partecipante_data) {
    if (($partecipante_data['tipo_persona'] ?? '') === 'adulto') {
        $quota_notte_extra_partecipante = $prezzo_notte_extra_pp;
    } elseif (($partecipante_data['tipo_persona'] ?? '') === 'bambino') {
        $quota_notte_extra_partecipante = $prezzo_notte_extra_pp;
    }
}

$quota_base_calcolata = round($payment->amount - ($totale_assicurazioni_partecipante + $totale_extra_partecipante + $quota_notte_extra_partecipante), 2);
if ($quota_base_calcolata < 0) {
    $quota_base_calcolata = 0.0;
} elseif (abs($quota_base_calcolata) < 0.01) {
    $quota_base_calcolata = 0.0;
}

$pagamenti_per_persona = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}btr_group_payments WHERE preventivo_id = %d AND participant_name = %s",
        $preventivo_id,
        $payment->participant_name
    )
);

$total_participants = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}btr_group_payments WHERE preventivo_id = %d",
        $preventivo_id
    )
);

$paid_count = $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}btr_group_payments WHERE preventivo_id = %d AND payment_status = 'paid'",
        $preventivo_id
    )
);

$payment_expires_label = $payment->expires_at
    ? date_i18n('d F Y H:i', strtotime($payment->expires_at))
    : esc_html__('Nessuna scadenza impostata', 'born-to-ride-booking');

$progress_percent = $total_participants > 0 ? round(($paid_count / $total_participants) * 100) : 0;
$progress_percent = max(0, min(100, $progress_percent));

$participants_breakdown_parts = [];
if ($num_adulti > 0) {
    $participants_breakdown_parts[] = sprintf(_n('%d adulto', '%d adulti', $num_adulti, 'born-to-ride-booking'), $num_adulti);
}
if ($num_bambini > 0) {
    $participants_breakdown_parts[] = sprintf(_n('%d bambino', '%d bambini', $num_bambini, 'born-to-ride-booking'), $num_bambini);
}
if ($num_neonati > 0) {
    $participants_breakdown_parts[] = sprintf(_n('%d neonato', '%d neonati', $num_neonati, 'born-to-ride-booking'), $num_neonati);
}
$participants_breakdown_text = implode(' · ', $participants_breakdown_parts);

$quota_base_partecipante = $totale_partecipanti > 0 ? ($prezzo_base / $totale_partecipanti) : 0;

$available_gateways = [];
if (function_exists('WC')) {
    $payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
    foreach ($payment_gateways as $gateway_id => $gateway) {
        if ('yes' === $gateway->enabled) {
            $available_gateways[$gateway_id] = [
                'title'       => $gateway->get_title(),
                'description' => $gateway->get_description(),
                'icon'        => $gateway->get_icon(),
            ];
        }
    }
}

if (empty($available_gateways)) {
    $available_gateways['bacs'] = [
        'title'       => esc_html__('Bonifico bancario', 'born-to-ride-booking'),
        'description' => esc_html__('Paga tramite bonifico bancario.', 'born-to-ride-booking'),
        'icon'        => '',
    ];
}

$destinazione_label = $destinazione ?: $package_title;
?>

<style>
.btr-group-payment {
    background: linear-gradient(180deg, #f1f5f9 0%, #e2e8f0 100%);
    padding: clamp(2.5rem, 5vw, 4rem) clamp(1rem, 4vw, 2.8rem);
    font-family: var(--btr-font-sans, 'Archivo', -apple-system, sans-serif);
    color: #0f172a;
    line-height: 1.6;
}

.btr-group-shell {
    max-width: 960px;
    margin: 0 auto;
    background: #ffffff;
    border-radius: 24px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    box-shadow: 0 32px 70px rgba(15, 23, 42, 0.12);
    overflow: hidden;
}

.btr-group-hero {
    position: relative;
    padding: clamp(2rem, 5vw, 3rem) clamp(1.8rem, 5vw, 3rem) clamp(1.6rem, 4vw, 2.3rem);
    background: linear-gradient(135deg, rgba(14, 165, 233, 0.14) 0%, rgba(255, 255, 255, 0.95) 70%);
}

.btr-group-hero::after {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(circle at top right, rgba(14, 165, 233, 0.22), transparent 65%);
    pointer-events: none;
}

.btr-hero-content {
    position: relative;
    z-index: 1;
}

.btr-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.35rem 0.85rem;
    border-radius: 999px;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #0369a1;
    background: rgba(14, 165, 233, 0.18);
}

.btr-group-hero h1 {
    margin: 1.1rem 0 0.4rem;
    font-size: clamp(1.8rem, 3vw, 2.3rem);
    letter-spacing: -0.02em;
}

.btr-hero-dates {
    margin: 0;
    font-size: 0.95rem;
    color: rgba(15, 23, 42, 0.7);
}

.btr-hero-meta {
    margin-top: clamp(1.4rem, 3vw, 2rem);
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.btr-meta-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 1rem 1.1rem;
    border-radius: 16px;
    background: rgba(255, 255, 255, 0.65);
    border: 1px solid rgba(14, 165, 233, 0.2);
    box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.35);
}

.btr-meta-label {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: rgba(15, 23, 42, 0.6);
    font-weight: 600;
}

.btr-meta-value {
    font-size: 1rem;
    font-weight: 600;
}

.btr-meta-sub {
    font-size: 0.85rem;
    color: rgba(15, 23, 42, 0.6);
}

.btr-summary-grid {
    padding: clamp(1.6rem, 4vw, 2.4rem);
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.25rem;
}

.btr-summary-card {
    background: #ffffff;
    border-radius: 16px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    padding: 1.25rem 1.35rem;
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06);
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.btr-summary-card--amount {
    border-left: 3px solid var(--btr-primary, #0a7be4);
}

.btr-summary-card--status {
    border-left: 3px solid var(--btr-success, #22c55e);
}

.btr-summary-card--info {
    border-left: 3px solid var(--btr-warning, #f59e0b);
}

.btr-summary-label {
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: rgba(71, 85, 105, 0.85);
}

.btr-summary-value {
    font-size: 1.5rem;
    font-weight: 600;
}

.btr-summary-subtext {
    font-size: 0.82rem;
    color: rgba(100, 116, 139, 0.85);
}

.btr-summary-progress {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.btr-progress-bar {
    position: relative;
    width: 100%;
    height: 10px;
    border-radius: 999px;
    background: rgba(226, 232, 240, 0.8);
    overflow: hidden;
}

.btr-progress-bar span {
    position: absolute;
    inset: 0;
    border-radius: inherit;
    background: linear-gradient(135deg, #22c55e 0%, #15803d 100%);
    transition: width 0.35s ease;
}

.btr-breakdown-card {
    margin: 0 clamp(1.6rem, 4vw, 2.4rem);
    margin-bottom: clamp(1.2rem, 4vw, 2rem);
    padding: clamp(1.6rem, 4vw, 2.2rem);
    background: #ffffff;
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 20px;
    box-shadow: 0 18px 36px rgba(15, 23, 42, 0.07);
}

.btr-breakdown-card h2 {
    margin: 0 0 1.1rem;
    font-size: 1.25rem;
}

.btr-breakdown-list {
    display: grid;
    gap: 0.75rem;
}

.btr-breakdown-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    padding-bottom: 0.65rem;
    border-bottom: 1px solid rgba(226, 232, 240, 0.8);
    font-size: 0.94rem;
}

.btr-breakdown-item:last-child {
    border-bottom: none;
}

.btr-breakdown-item .label {
    color: rgba(15, 23, 42, 0.7);
}

.btr-breakdown-item .value {
    font-weight: 600;
}

.btr-note-banner {
    margin: 0 clamp(1.6rem, 4vw, 2.4rem);
    margin-bottom: clamp(1.2rem, 4vw, 2rem);
    padding: 0.95rem 1.1rem;
    border-radius: 14px;
    background: rgba(255, 237, 213, 0.6);
    border: 1px solid rgba(234, 179, 8, 0.35);
    color: rgba(120, 53, 15, 0.85);
}

.btr-total-highlight {
    margin: 0 clamp(1.6rem, 4vw, 2.4rem);
    margin-bottom: clamp(1.5rem, 4vw, 2.3rem);
    padding: clamp(1.4rem, 4vw, 1.9rem);
    border-radius: 20px;
    background: linear-gradient(135deg, rgba(14, 165, 233, 0.18) 0%, rgba(10, 123, 228, 0.12) 100%);
    border: 1px solid rgba(14, 165, 233, 0.25);
    text-align: center;
}

.btr-total-highlight .btr-total-value {
    font-size: clamp(2rem, 4vw, 2.5rem);
    font-weight: 700;
    color: #0a7be4;
}

.btr-form-section {
    margin: 0 clamp(1.6rem, 4vw, 2.4rem);
    margin-bottom: clamp(1.6rem, 4vw, 2.5rem);
    padding: clamp(1.8rem, 4vw, 2.4rem);
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.9);
    border-radius: 20px;
    box-shadow: 0 20px 40px rgba(15, 23, 42, 0.08);
}

.btr-form-section h3 {
    margin: 0 0 0.6rem;
    font-size: 1.32rem;
}

.btr-form-section .section-lead {
    margin: 0 0 1.4rem;
    color: rgba(15, 23, 42, 0.65);
    font-size: 0.95rem;
}

.btr-form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
}

.btr-form-group {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.btr-form-group label,
.btr-form-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: rgba(15, 23, 42, 0.75);
}

.btr-form-group input,
.btr-form-group select {
    border: 1px solid rgba(15, 23, 42, 0.16);
    border-radius: 10px;
    padding: 0.65rem 0.85rem;
    font-size: 0.95rem;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.btr-form-group input:focus,
.btr-form-group select:focus {
    outline: none;
    border-color: var(--btr-primary, #0a7be4);
    box-shadow: 0 0 0 3px rgba(10, 123, 228, 0.18);
}

.btr-payment-methods {
    margin-top: 1.4rem;
    display: grid;
    gap: 0.75rem;
}

.btr-payment-method-option {
    border: 1px solid rgba(148, 163, 184, 0.5);
    border-radius: 14px;
    padding: 0.85rem 1rem;
    display: flex;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.btr-payment-method-option.selected {
    border-color: var(--btr-primary, #0a7be4);
    box-shadow: 0 10px 24px rgba(10, 123, 228, 0.12);
}

.btr-payment-method-option label {
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
    width: 100%;
    cursor: pointer;
}

.btr-payment-method-option input[type="radio"] {
    margin-top: 0.3rem;
}

.btr-payment-method-title {
    font-weight: 600;
    color: #0f172a;
}

.btr-payment-method-desc {
    font-size: 0.82rem;
    color: rgba(71, 85, 105, 0.85);
    margin-top: 0.25rem;
}

.btr-form-feedback {
    margin-top: 1rem;
    font-size: 0.85rem;
    font-weight: 600;
    color: rgba(15, 23, 42, 0.65);
}

.btr-form-feedback.success {
    color: #15803d;
}

.btr-form-feedback.error {
    color: #b91c1c;
}

.btr-form-total {
    margin: 1.3rem 0;
    padding: 1.1rem;
    border-radius: 16px;
    background: rgba(248, 250, 252, 0.78);
    text-align: center;
}

.btr-form-total strong {
    display: block;
    font-size: 2rem;
    color: #0a7be4;
}

.btr-submit-button {
    width: 100%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    padding: 1.05rem 1.5rem;
    border-radius: 999px;
    border: none;
    background: linear-gradient(135deg, var(--btr-primary, #0a7be4) 0%, var(--btr-primary-dark, #005c99) 100%);
    color: #ffffff;
    font-size: 1.05rem;
    font-weight: 600;
    cursor: pointer;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    transition: transform 0.18s ease, box-shadow 0.18s ease, filter 0.18s ease;
    box-shadow: 0 18px 34px rgba(10, 123, 228, 0.28);
    margin-top: 1.5rem;
}

.btr-submit-button:hover {
    transform: translateY(-2px);
    filter: brightness(1.05);
    box-shadow: 0 22px 40px rgba(10, 123, 228, 0.32);
}

.btr-submit-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}
.btr-submit-button svg {
    width: 1.2rem;
    height: 1.2rem;
}

.btr-loading {
    display: none;
    margin-top: 1rem;
    text-align: center;
}

.btr-loading.active {
    display: block;
}

.btr-privacy-notice {
    margin-top: 1.2rem;
    padding: 0.9rem 1.1rem;
    border-radius: 16px;
    background: rgba(14, 165, 233, 0.1);
    color: rgba(15, 23, 42, 0.7);
    font-size: 0.9rem;
    text-align: center;
}

.btr-trust-indicators {
    margin-top: 1.5rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.8rem;
    justify-content: center;
}

.btr-trust-item {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.45rem 0.75rem;
    border-radius: 999px;
    background: rgba(226, 232, 240, 0.65);
    font-size: 0.85rem;
    color: rgba(30, 41, 59, 0.85);
}

.btr-required {
    color: #ef4444;
}

.btr-optional-fields {
    margin-top: 1.5rem;
    padding: 1.1rem 1.25rem;
    border-radius: 14px;
    border: 1px dashed rgba(15, 23, 42, 0.18);
    background: rgba(248, 250, 252, 0.65);
}

.btr-optional-fields summary {
    cursor: pointer;
    font-weight: 600;
    color: #0a7be4;
    outline: none;
}

.btr-optional-fields[open] {
    border-color: rgba(14, 165, 233, 0.3);
    background: rgba(14, 165, 233, 0.08);
}

.spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(10, 123, 228, 0.25);
    border-radius: 50%;
    border-top-color: var(--btr-primary, #0a7be4);
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

@media (max-width: 768px) {
    .btr-group-payment {
        padding: 2rem 1.1rem;
    }

    .btr-group-shell {
        border-radius: 18px;
    }

    .btr-summary-grid,
    .btr-breakdown-card,
    .btr-total-highlight,
    .btr-form-section {
        margin-left: 1.1rem;
        margin-right: 1.1rem;
    }

    .btr-summary-card {
        padding: 1.1rem 1rem;
    }

    .btr-form-grid {
        grid-template-columns: 1fr;
    }

    .btr-trust-indicators {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<div class="btr-group-payment">
    <div class="btr-group-shell">
        <section class="btr-group-hero">
            <div class="btr-hero-content">
                <span class="btr-hero-badge"><?php esc_html_e('Pagamento di gruppo', 'born-to-ride-booking'); ?></span>
                <h1><?php echo esc_html($package_title); ?></h1>
                <?php if (!empty($data_partenza)) : ?>
                    <p class="btr-hero-dates"><?php echo esc_html(date_i18n('d F Y', strtotime($data_partenza))); ?> · <?php echo esc_html($durata_viaggio); ?></p>
                <?php elseif (!empty($durata_viaggio)) : ?>
                    <p class="btr-hero-dates"><?php echo esc_html($durata_viaggio); ?></p>
                <?php endif; ?>

                <div class="btr-hero-meta">
                    <div class="btr-meta-item">
                        <span class="btr-meta-label"><?php esc_html_e('Destinazione', 'born-to-ride-booking'); ?></span>
                        <span class="btr-meta-value"><?php echo esc_html($destinazione_label); ?></span>
                        <?php if (!empty($participants_breakdown_text)) : ?>
                            <span class="btr-meta-sub"><?php echo esc_html($participants_breakdown_text); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="btr-meta-item">
                        <span class="btr-meta-label"><?php esc_html_e('Preventivo', 'born-to-ride-booking'); ?></span>
                        <span class="btr-meta-value">#<?php echo esc_html($preventivo_id); ?></span>
                        <span class="btr-meta-sub"><?php echo wp_kses_post(wc_price($totale_viaggio)); ?></span>
                    </div>
                    <div class="btr-meta-item">
                        <span class="btr-meta-label"><?php esc_html_e('Scadenza link', 'born-to-ride-booking'); ?></span>
                        <span class="btr-meta-value"><?php echo esc_html($payment_expires_label); ?></span>
                        <span class="btr-meta-sub"><?php printf(esc_html__('%1$s su %2$s paganti confermati', 'born-to-ride-booking'), $paid_count, $total_participants); ?></span>
                    </div>
                </div>
            </div>
        </section>

        <section class="btr-summary-grid">
            <article class="btr-summary-card btr-summary-card--amount">
                <span class="btr-summary-label"><?php esc_html_e('Totale prenotazione', 'born-to-ride-booking'); ?></span>
                <span class="btr-summary-value"><?php echo wp_kses_post(wc_price($totale_viaggio)); ?></span>
                <span class="btr-summary-subtext"><?php echo esc_html($participants_breakdown_text ?: sprintf(__('Totale partecipanti: %d', 'born-to-ride-booking'), $totale_partecipanti)); ?></span>
            </article>
            <article class="btr-summary-card btr-summary-card--status">
                <span class="btr-summary-label"><?php esc_html_e('Avanzamento pagamenti', 'born-to-ride-booking'); ?></span>
                <span class="btr-summary-value"><?php echo esc_html($progress_percent); ?>%</span>
                <div class="btr-summary-progress">
                    <div class="btr-progress-bar"><span style="width: <?php echo esc_attr($progress_percent); ?>%;"></span></div>
                    <span class="btr-summary-subtext"><?php printf(esc_html__('%1$s su %2$s quote saldate', 'born-to-ride-booking'), $paid_count, $total_participants); ?></span>
                </div>
            </article>
            <article class="btr-summary-card btr-summary-card--info">
                <span class="btr-summary-label"><?php esc_html_e('Importo di questo link', 'born-to-ride-booking'); ?></span>
                <span class="btr-summary-value"><?php echo wp_kses_post(wc_price($payment->amount)); ?></span>
                <span class="btr-summary-subtext"><?php printf(esc_html__('Link pagamento #%d', 'born-to-ride-booking'), $payment->payment_id); ?></span>
            </article>
        </section>

        <section class="btr-breakdown-card">
            <h2><?php esc_html_e('Dettaglio della tua quota', 'born-to-ride-booking'); ?></h2>
            <div class="btr-breakdown-list">
                <?php if (abs($quota_base_calcolata) > 0.01) : ?>
                    <div class="btr-breakdown-item">
                        <span class="label"><?php esc_html_e('Quota base viaggio', 'born-to-ride-booking'); ?></span>
                        <span class="value"><?php echo wp_kses_post(wc_price($quota_base_calcolata)); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($quota_notte_extra_partecipante > 0 && $data_notte_extra) : ?>
                    <div class="btr-breakdown-item">
                        <span class="label"><?php printf(esc_html__('Notte extra (%s)', 'born-to-ride-booking'), esc_html($data_notte_extra)); ?></span>
                        <span class="value"><?php echo wp_kses_post(wc_price($quota_notte_extra_partecipante)); ?></span>
                    </div>
                <?php endif; ?>

                <?php foreach ($assicurazioni_partecipante as $assicurazione) : ?>
                    <div class="btr-breakdown-item">
                        <span class="label"><?php echo esc_html($assicurazione['nome']); ?></span>
                        <span class="value"><?php echo wp_kses_post(wc_price($assicurazione['importo'])); ?></span>
                    </div>
                <?php endforeach; ?>

                <?php foreach ($dettagli_extra as $extra) : ?>
                    <div class="btr-breakdown-item">
                        <span class="label"><?php echo esc_html($extra['nome']); ?></span>
                        <span class="value"><?php echo wp_kses_post(wc_price($extra['importo'])); ?></span>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($assicurazioni_partecipante) && empty($dettagli_extra) && $quota_notte_extra_partecipante <= 0) : ?>
                    <div class="btr-breakdown-item">
                        <span class="label"><?php esc_html_e('Nessun servizio aggiuntivo selezionato', 'born-to-ride-booking'); ?></span>
                        <span class="value">—</span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($pagamenti_per_persona > 1) : ?>
                <p class="btr-summary-subtext" style="margin-top: 0.75rem;">
                    <?php printf(esc_html__('Quota suddivisa in %d pagamenti complessivi.', 'born-to-ride-booking'), $pagamenti_per_persona); ?>
                </p>
            <?php endif; ?>
        </section>

        <?php if (!empty($payment->notes)) : ?>
            <div class="btr-note-banner">
                <strong><?php esc_html_e('Note dell\'organizzatore', 'born-to-ride-booking'); ?>:</strong>
                <span><?php echo esc_html($payment->notes); ?></span>
            </div>
        <?php endif; ?>

        <section class="btr-total-highlight">
            <span><?php esc_html_e('Importo da versare con questo pagamento', 'born-to-ride-booking'); ?></span>
            <span class="btr-total-value"><?php echo wp_kses_post(wc_price($payment->amount)); ?></span>
            <span><?php printf(esc_html__('Quota intestata a %s', 'born-to-ride-booking'), esc_html($payment->participant_name)); ?></span>
        </section>

        <section class="btr-form-section">
            <h3><?php esc_html_e('Completa i dati per il pagamento', 'born-to-ride-booking'); ?></h3>
            <p class="section-lead"><?php esc_html_e('I dati verranno utilizzati per la ricevuta e per eventuali comunicazioni di supporto.', 'born-to-ride-booking'); ?></p>

            <form id="btr-group-payment-form" method="post" class="btr-payment-form" novalidate>
                <?php wp_nonce_field('btr_group_payment_' . $payment_hash, 'btr_payment_nonce'); ?>
                <input type="hidden" name="payment_hash" value="<?php echo esc_attr($payment_hash); ?>">
                <input type="hidden" name="action" value="btr_process_group_payment">

                <div class="btr-form-grid">
                    <div class="btr-form-group">
                        <label for="billing_first_name"><?php esc_html_e('Nome', 'born-to-ride-booking'); ?> <span class="btr-required">*</span></label>
                        <input type="text" id="billing_first_name" name="billing_first_name" value="<?php echo esc_attr($user_data['first_name'] ?? ''); ?>" autocomplete="given-name" required>
                    </div>
                    <div class="btr-form-group">
                        <label for="billing_last_name"><?php esc_html_e('Cognome', 'born-to-ride-booking'); ?> <span class="btr-required">*</span></label>
                        <input type="text" id="billing_last_name" name="billing_last_name" value="<?php echo esc_attr($user_data['last_name'] ?? ''); ?>" autocomplete="family-name" required>
                    </div>
                    <div class="btr-form-group">
                        <label for="billing_email"><?php esc_html_e('Email', 'born-to-ride-booking'); ?> <span class="btr-required">*</span></label>
                        <input type="email" id="billing_email" name="billing_email" value="<?php echo esc_attr($user_data['email'] ?? $payment->participant_email); ?>" autocomplete="email" required>
                    </div>
                </div>

                <div class="btr-form-group btr-payment-methods">
                    <span class="btr-form-label"><?php esc_html_e('Metodo di pagamento', 'born-to-ride-booking'); ?> <span class="btr-required">*</span></span>
                    <?php $first = true; ?>
                    <?php foreach ($available_gateways as $gateway_id => $gateway) : ?>
                        <div class="btr-payment-method-option <?php echo $first ? 'selected' : ''; ?>">
                            <label>
                                <input type="radio" name="payment_method" value="<?php echo esc_attr($gateway_id); ?>" <?php echo $first ? 'checked' : ''; ?> required>
                                <div>
                                    <div class="btr-payment-method-title"><?php echo esc_html($gateway['title']); ?></div>
                                    <?php if (!empty($gateway['description'])) : ?>
                                        <div class="btr-payment-method-desc"><?php echo esc_html($gateway['description']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </label>
                        </div>
                        <?php $first = false; ?>
                    <?php endforeach; ?>
                </div>

                <details class="btr-optional-fields">
                    <summary><?php esc_html_e('Aggiungi dati di fatturazione (opzionale)', 'born-to-ride-booking'); ?></summary>
                    <div class="btr-form-grid">
                        <div class="btr-form-group">
                            <label for="billing_phone"><?php esc_html_e('Telefono', 'born-to-ride-booking'); ?></label>
                            <input type="tel" id="billing_phone" name="billing_phone" value="<?php echo esc_attr($user_data['phone'] ?? ''); ?>" autocomplete="tel">
                        </div>
                        <div class="btr-form-group">
                            <label for="billing_address"><?php esc_html_e('Indirizzo', 'born-to-ride-booking'); ?></label>
                            <input type="text" id="billing_address" name="billing_address" autocomplete="address-line1">
                        </div>
                        <div class="btr-form-group">
                            <label for="billing_city"><?php esc_html_e('Città', 'born-to-ride-booking'); ?></label>
                            <input type="text" id="billing_city" name="billing_city" autocomplete="address-level2">
                        </div>
                        <div class="btr-form-group">
                            <label for="billing_postcode"><?php esc_html_e('CAP', 'born-to-ride-booking'); ?></label>
                            <input type="text" id="billing_postcode" name="billing_postcode" autocomplete="postal-code">
                        </div>
                        <div class="btr-form-group">
                            <label for="billing_cf"><?php esc_html_e('Codice fiscale / P. IVA', 'born-to-ride-booking'); ?></label>
                            <input type="text" id="billing_cf" name="billing_cf" autocomplete="off">
                        </div>
                    </div>
                </details>

                <div class="btr-form-group" style="margin-top: 1.25rem;">
                    <label style="display: flex; gap: 0.6rem; align-items: flex-start;">
                        <input type="checkbox" name="terms" id="terms" required>
                        <span style="font-size: 0.85rem;">
                            <?php esc_html_e('Confermo di aver letto e accettato termini e privacy.', 'born-to-ride-booking'); ?>
                        </span>
                    </label>
                </div>

                <div class="btr-form-feedback" role="alert" aria-live="polite"></div>

                <div class="btr-form-total">
                    <span><?php esc_html_e('Importo totale', 'born-to-ride-booking'); ?></span>
                    <strong><?php echo wp_kses_post(wc_price($payment->amount)); ?></strong>
                </div>

                <button type="submit" class="btr-submit-button">
                    <svg class="btn-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6v6l3 3" />
                        <circle cx="12" cy="12" r="9" stroke-width="1.5" />
                    </svg>
                    <?php printf(esc_html__('Paga %s adesso', 'born-to-ride-booking'), wp_kses_post(wc_price($payment->amount))); ?>
                </button>

                <div class="btr-loading" aria-live="polite">
                    <span class="spinner is-active"></span>
                    <p><?php esc_html_e('Elaborazione in corso...', 'born-to-ride-booking'); ?></p>
                </div>

                <p class="btr-privacy-notice">🔒 <?php esc_html_e('Transazione protetta SSL · Dati trattati secondo GDPR', 'born-to-ride-booking'); ?></p>
            </form>

            <div class="btr-trust-indicators">
                <span class="btr-trust-item">🔐 <?php esc_html_e('Standard PSD2 e 3-D Secure', 'born-to-ride-booking'); ?></span>
                <span class="btr-trust-item">💬 <?php printf(esc_html__('Supporto: %s', 'born-to-ride-booking'), esc_html(get_option('admin_email', ''))); ?></span>
            </div>
        </section>
    </div>
</div>

<script>
jQuery(function($) {
    $(document).on('click', '.btr-payment-method-option', function() {
        $('.btr-payment-method-option').removeClass('selected');
        $(this).addClass('selected');
        $(this).find('input[type="radio"]').prop('checked', true);
    });

    $('#btr-group-payment-form').on('submit', function(e) {
        e.preventDefault();

        var $form    = $(this);
        var $button  = $form.find('.btr-submit-button');
        var $loading = $form.find('.btr-loading');
        var $feedback = $form.find('.btr-form-feedback');

        var ajaxSettings = (typeof btr_ajax_object !== 'undefined') ? btr_ajax_object : {};
        var ajaxUrl = ajaxSettings.ajax_url || '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
        var nonce   = ajaxSettings.nonce || '';

        $feedback.removeClass('error success').text('');
        $button.prop('disabled', true);
        $loading.addClass('active');

        var formData = $form.serialize();
        if (nonce) {
            formData += '&ajax_nonce=' + encodeURIComponent(nonce);
        }

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response && response.success && response.data && response.data.redirect) {
                    window.location.href = response.data.redirect;
                    return;
                }

                var message = (response && response.data && response.data.message)
                    ? response.data.message
                    : "<?php echo esc_js(__('Errore durante l\'elaborazione del pagamento. Riprova.', 'born-to-ride-booking')); ?>";
                $feedback.addClass(response && response.success ? 'success' : 'error').text(message);
                $button.prop('disabled', false);
                $loading.removeClass('active');
            },
            error: function() {
                $feedback.addClass('error').text("<?php echo esc_js(__('Errore di connessione. Controlla la rete e riprova.', 'born-to-ride-booking')); ?>");
                $button.prop('disabled', false);
                $loading.removeClass('active');
            }
        });
    });

    $('#billing_first_name').trigger('focus');
});
</script>

<?php
get_footer();
