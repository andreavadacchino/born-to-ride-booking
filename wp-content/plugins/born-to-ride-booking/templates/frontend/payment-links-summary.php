<?php
/**
 * Template per mostrare i link di pagamento generati per gruppo
 * 
 * @package BornToRideBooking
 * @since 1.0.98
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ottieni i dati dalla sessione o dai parametri
$preventivo_id = isset($_GET['preventivo_id']) ? intval($_GET['preventivo_id']) : 0;
$payment_links = [];

// Prima prova a ottenere i link dalla sessione
if (WC()->session) {
    $payment_links = WC()->session->get('btr_generated_payment_links', []);
    $session_preventivo_id = WC()->session->get('btr_payment_preventivo_id', 0);
    
    // Verifica che il preventivo corrisponda
    if ($session_preventivo_id != $preventivo_id) {
        $payment_links = [];
    }
}

// Se non ci sono link in sessione, prova a recuperarli dal database
if (empty($payment_links) && $preventivo_id && class_exists('BTR_Group_Payments')) {
    $group_payments = new BTR_Group_Payments();
    $stats = $group_payments->get_payment_stats($preventivo_id);
    
    // Se ci sono pagamenti esistenti, recuperali
    if ($stats && $stats['total_payments'] > 0) {
        global $wpdb;
        $table_payments = $wpdb->prefix . 'btr_group_payments';
        $table_links = $wpdb->prefix . 'btr_payment_links';
        
        $payment_links = $wpdb->get_results($wpdb->prepare("
            SELECT 
                p.payment_id,
                p.participant_name,
                p.participant_email,
                p.amount,
                p.payment_status,
                p.expires_at,
                CONCAT(%s, l.link_hash) as payment_url
            FROM {$table_payments} p
            LEFT JOIN {$table_links} l ON p.payment_id = l.payment_id
            WHERE p.preventivo_id = %d 
            AND l.is_active = 1
            ORDER BY p.participant_name
        ", home_url('/pay-individual/'), $preventivo_id), ARRAY_A);
    }
}

// Ottieni informazioni del preventivo
$preventivo = get_post($preventivo_id);
$nome_pacchetto = get_post_meta($preventivo_id, '_nome_pacchetto', true);

// FIX v1.0.243: Usa _totale_preventivo che include assicurazioni invece di _prezzo_totale
$prezzo_totale = get_post_meta($preventivo_id, '_totale_preventivo', true);
if (!$prezzo_totale || $prezzo_totale <= 0) {
    // Fallback: calcola manualmente se _totale_preventivo non esiste
    $prezzo_base = floatval(get_post_meta($preventivo_id, '_prezzo_totale', true));
    $totale_assicurazioni = floatval(get_post_meta($preventivo_id, '_totale_assicurazioni', true));
    $totale_costi_extra = floatval(get_post_meta($preventivo_id, '_totale_costi_extra', true));
    $prezzo_totale = $prezzo_base + $totale_assicurazioni + $totale_costi_extra;
}

$date_range = get_post_meta($preventivo_id, '_date_ranges', true);

?>

<style>
/* BTR Payment Links Summary - Stile Minimale Professionale */
.btr-payment-links-summary {
    font-family: var(--btr-font-sans, 'Archivo', -apple-system, sans-serif);
    color: var(--btr-gray-900, #111827);
    line-height: 1.6;
    animation: btr-fadeIn 0.5s ease-out;
}

@keyframes btr-fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.btr-payment-links-summary .btr-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem 1rem;
}

/* Header con stile professionale */
.btr-payment-links-summary .btr-header {
    background: linear-gradient(135deg, var(--btr-primary-lightest, #f0f9fc) 0%, transparent 100%);
    padding: 2.5rem;
    border-radius: var(--btr-radius-lg, 12px);
    margin-bottom: 2rem;
    border: 1px solid var(--btr-gray-200, #e5e7eb);
    animation: btr-slideDown 0.6s ease-out 0.1s both;
}

@keyframes btr-slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.btr-payment-links-summary h1 {
    font-size: 2rem;
    font-weight: 700;
    color: var(--btr-gray-900, #111827);
    margin: 0 0 1.5rem 0;
    letter-spacing: -0.02em;
}

.btr-payment-links-summary .btr-package-info h2 {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--btr-primary, #0097c5);
    margin: 0 0 0.5rem 0;
    transition: color var(--btr-transition, 300ms ease-in-out);
}

.btr-payment-links-summary .dates {
    color: var(--btr-gray-600, #4b5563);
    margin: 0 0 0.5rem 0;
    font-size: 0.95rem;
}

.btr-payment-links-summary .total-amount {
    font-size: 1.125rem;
    color: var(--btr-gray-700, #374151);
    margin: 0;
}

.btr-payment-links-summary .total-amount strong {
    color: var(--btr-primary-dark, #005177);
    font-weight: 700;
}

/* Success message con animazione sottile */
.btr-payment-links-summary .btr-success-message {
    background: var(--btr-success-light, #d1fae5);
    border: 1px solid var(--btr-success, #10b981);
    color: var(--btr-gray-800, #1f2937);
    padding: 1.25rem;
    border-radius: var(--btr-radius, 8px);
    margin-bottom: 2rem;
    animation: btr-fadeIn 0.7s ease-out 0.3s both;
}

.btr-payment-links-summary .btr-success-message p {
    margin: 0;
    line-height: 1.6;
}

.btr-payment-links-summary .btr-success-message p:first-child {
    font-weight: 600;
    margin-bottom: 0.5rem;
}

/* Tabella minimale e professionale */
.btr-payment-links-summary .btr-payment-links-table {
    background: white;
    border-radius: var(--btr-radius-lg, 12px);
    padding: 1.5rem;
    box-shadow: var(--btr-shadow-sm, 0 1px 3px 0 rgba(0, 0, 0, 0.1));
    margin-bottom: 2rem;
    animation: btr-slideUp 0.7s ease-out 0.4s both;
}

@keyframes btr-slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.btr-payment-links-summary .btr-payment-links-table h3 {
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--btr-gray-900, #111827);
    margin: 0 0 1.5rem 0;
}

.btr-payment-links-summary .btr-links-table {
    width: 100%;
    border-collapse: collapse;
}

.btr-payment-links-summary .btr-links-table thead th {
    text-align: left;
    font-weight: 600;
    color: var(--btr-gray-700, #374151);
    padding: 0.75rem 1rem;
    border-bottom: 2px solid var(--btr-gray-200, #e5e7eb);
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.btr-payment-links-summary .btr-links-table tbody td {
    padding: 1rem;
    border-bottom: 1px solid var(--btr-gray-100, #f3f4f6);
    transition: background-color var(--btr-transition-fast, 150ms ease-in-out);
}

.btr-payment-links-summary .btr-links-table tbody tr {
    transition: all var(--btr-transition-fast, 150ms ease-in-out);
}

.btr-payment-links-summary .btr-links-table tbody tr:hover {
    background-color: var(--btr-gray-50, #f9fafb);
}

.btr-payment-links-summary .btr-links-table tbody tr.paid {
    opacity: 0.7;
}

/* Stati con colori sottili */
.btr-payment-links-summary .status-paid {
    color: var(--btr-success, #10b981);
    font-weight: 500;
}

.btr-payment-links-summary .status-pending {
    color: var(--btr-warning, #f59e0b);
    font-weight: 500;
}

/* Pulsanti minimali con hover sottile */
.btr-payment-links-summary .action-buttons {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.btr-payment-links-summary .action-buttons button,
.btr-payment-links-summary .btn-send-all-emails,
.btr-payment-links-summary .btn-print,
.btr-payment-links-summary .btn-organizer-proceed,
.btr-payment-links-summary .btn-admin-view {
    background: white;
    color: var(--btr-gray-700, #374151);
    border: 1px solid var(--btr-gray-300, #d1d5db);
    padding: 0.5rem 1rem;
    border-radius: var(--btr-radius-sm, 4px);
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all var(--btr-transition-fast, 150ms ease-in-out);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
}

.btr-payment-links-summary .action-buttons button:hover,
.btr-payment-links-summary .btn-send-all-emails:hover,
.btr-payment-links-summary .btn-print:hover,
.btr-payment-links-summary .btn-admin-view:hover {
    background: var(--btr-gray-50, #f9fafb);
    border-color: var(--btr-gray-400, #9ca3af);
    transform: translateY(-1px);
    box-shadow: var(--btr-shadow-sm, 0 1px 3px 0 rgba(0, 0, 0, 0.1));
}

/* Pulsante primario con stile BTR */
.btr-payment-links-summary .btn-organizer-proceed {
    background: var(--btr-primary, #0097c5);
    color: white;
    border-color: var(--btr-primary, #0097c5);
    font-weight: 600;
    padding: 0.75rem 1.5rem;
}

.btr-payment-links-summary .btn-organizer-proceed:hover {
    background: var(--btr-primary-hover, #007ba3);
    border-color: var(--btr-primary-hover, #007ba3);
    transform: translateY(-2px);
    box-shadow: var(--btr-shadow, 0 4px 6px -1px rgba(0, 0, 0, 0.1));
}

/* Footer tabella */
.btr-payment-links-summary .btr-links-table tfoot td {
    padding: 1rem;
    border-top: 2px solid var(--btr-gray-200, #e5e7eb);
    font-weight: 600;
    color: var(--btr-gray-800, #1f2937);
}

/* Azioni bottom */
.btr-payment-links-summary .btr-actions-bottom {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    justify-content: center;
    margin-bottom: 2rem;
    animation: btr-fadeIn 0.8s ease-out 0.5s both;
}

/* Alert info */
.btr-payment-links-summary .btr-alert {
    padding: 1rem 1.5rem;
    border-radius: var(--btr-radius, 8px);
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    animation: btr-fadeIn 0.8s ease-out 0.6s both;
}

.btr-payment-links-summary .btr-alert-info {
    background: var(--btr-info-light, #dbeafe);
    border: 1px solid var(--btr-info, #3b82f6);
    color: var(--btr-gray-800, #1f2937);
}

.btr-payment-links-summary .btr-alert-icon {
    width: 24px;
    height: 24px;
    flex-shrink: 0;
    color: var(--btr-info, #3b82f6);
}

.btr-payment-links-summary .btr-alert-content h4 {
    margin: 0 0 0.5rem 0;
    font-weight: 600;
    color: var(--btr-gray-900, #111827);
}

.btr-payment-links-summary .btr-alert-content p {
    margin: 0;
    font-size: 0.95rem;
    line-height: 1.6;
}

/* Responsive design pulito */
@media (max-width: 768px) {
    .btr-payment-links-summary .btr-container {
        padding: 1rem;
    }

    .btr-payment-links-summary .btr-header {
        padding: 1.5rem;
    }

    .btr-payment-links-summary h1 {
        font-size: 1.5rem;
    }

    .btr-payment-links-summary .btr-links-table {
        font-size: 0.875rem;
    }

    .btr-payment-links-summary .btr-links-table thead th,
    .btr-payment-links-summary .btr-links-table tbody td {
        padding: 0.5rem;
    }

    .btr-payment-links-summary .action-buttons {
        flex-direction: column;
        width: 100%;
    }

    .btr-payment-links-summary .action-buttons button {
        width: 100%;
        justify-content: center;
    }

    .btr-payment-links-summary .btr-actions-bottom {
        flex-direction: column;
        align-items: stretch;
    }
}

/* Effetto hover sottile per righe */
@media (hover: hover) {
    .btr-payment-links-summary .btr-links-table tbody tr {
        position: relative;
    }

    .btr-payment-links-summary .btr-links-table tbody tr::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        width: 3px;
        height: 100%;
        background: var(--btr-primary, #0097c5);
        opacity: 0;
        transition: opacity var(--btr-transition-fast, 150ms ease-in-out);
    }

    .btr-payment-links-summary .btr-links-table tbody tr:hover::before {
        opacity: 1;
    }
}

/* Animazione di caricamento per i pulsanti */
.btr-payment-links-summary button:active {
    transform: scale(0.98);
}

/* Focus states accessibili */
.btr-payment-links-summary button:focus,
.btr-payment-links-summary a:focus {
    outline: 2px solid var(--btr-primary, #0097c5);
    outline-offset: 2px;
}

/* Print styles */
@media print {
    .btr-payment-links-summary .btr-actions-bottom,
    .btr-payment-links-summary .action-buttons {
        display: none;
    }

    .btr-payment-links-summary .btr-header {
        background: none;
        border: none;
    }
}

/* Modal styles - Design Moderno BTR */
.btr-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    animation: btr-modal-fade-in 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes btr-modal-fade-in {
    from {
        opacity: 0;
        backdrop-filter: blur(0px);
    }
    to {
        opacity: 1;
        backdrop-filter: blur(8px);
    }
}

.btr-modal-content {
    background: linear-gradient(145deg, #ffffff 0%, #fafbfc 100%);
    border-radius: 20px;
    max-width: 480px;
    width: 90%;
    max-height: 90vh;
    overflow: hidden;
    box-shadow:
        0 20px 25px -5px rgba(0, 0, 0, 0.1),
        0 10px 10px -5px rgba(0, 0, 0, 0.04),
        0 0 0 1px rgba(0, 151, 197, 0.1);
    animation: btr-modal-slide-up 0.5s cubic-bezier(0.16, 1, 0.3, 1);
    transform-origin: center;
}

@keyframes btr-modal-slide-up {
    from {
        transform: translateY(40px) scale(0.95);
        opacity: 0;
    }
    to {
        transform: translateY(0) scale(1);
        opacity: 1;
    }
}

/* Modal di conferma con design premium */
.btr-modal-confirm {
    padding: 0;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.btr-modal-confirm::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 120px;
    background: linear-gradient(135deg, rgba(0, 151, 197, 0.08) 0%, rgba(0, 151, 197, 0.02) 100%);
    z-index: 0;
}

.btr-modal-header {
    padding: 2rem 2rem 1rem;
    position: relative;
    z-index: 1;
}

.btr-modal-header h3 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--btr-gray-900, #111827);
    letter-spacing: -0.02em;
}

.btr-modal-body {
    padding: 1.5rem 2.5rem 2rem;
    position: relative;
    z-index: 1;
}

.btr-modal-icon {
    margin: 0 auto 1.75rem;
    width: 72px;
    height: 72px;
    background: linear-gradient(135deg, rgba(0, 151, 197, 0.1) 0%, rgba(0, 151, 197, 0.05) 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    animation: btr-pulse-soft 2s ease-in-out infinite;
}

.btr-modal-icon svg {
    width: 36px;
    height: 36px;
    color: var(--btr-primary, #0097c5);
    filter: drop-shadow(0 2px 4px rgba(0, 151, 197, 0.2));
}

@keyframes btr-pulse-soft {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

.btr-modal-message {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--btr-gray-800, #1f2937);
    margin: 0 0 0.75rem 0;
    line-height: 1.5;
}

.btr-modal-submessage {
    font-size: 0.95rem;
    color: var(--btr-gray-600, #4b5563);
    margin: 0 auto;
    max-width: 90%;
    line-height: 1.6;
}

.btr-modal-footer {
    padding: 1.5rem 2rem 2rem;
    background: rgba(249, 250, 251, 0.5);
    display: flex;
    gap: 1rem;
    justify-content: center;
    position: relative;
}

.btr-modal-cancel {
    background: white;
    color: var(--btr-gray-700, #374151);
    border: 2px solid var(--btr-gray-200, #e5e7eb);
    padding: 0.75rem 2rem;
    border-radius: 12px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    overflow: hidden;
}

.btr-modal-cancel::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(0, 0, 0, 0.05);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.btr-modal-cancel:hover {
    background: white;
    border-color: var(--btr-gray-300, #d1d5db);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.btr-modal-cancel:hover::before {
    width: 250px;
    height: 250px;
}

.btr-modal-cancel:active {
    transform: translateY(0);
}

.btr-modal-confirm-btn {
    background: linear-gradient(135deg, var(--btr-primary, #0097c5) 0%, #007ba3 100%);
    color: white;
    border: 2px solid transparent;
    padding: 0.75rem 2rem;
    border-radius: 12px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    overflow: hidden;
    box-shadow:
        0 4px 14px 0 rgba(0, 151, 197, 0.3),
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.btr-modal-confirm-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    transition: left 0.5s;
}

.btr-modal-confirm-btn:hover {
    background: linear-gradient(135deg, #00a8d8 0%, #0088b3 100%);
    transform: translateY(-2px);
    box-shadow:
        0 7px 20px 0 rgba(0, 151, 197, 0.4),
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.btr-modal-confirm-btn:hover::before {
    left: 100%;
}

.btr-modal-confirm-btn:active {
    transform: translateY(0);
    box-shadow:
        0 2px 8px 0 rgba(0, 151, 197, 0.3),
        inset 0 1px 2px rgba(0, 0, 0, 0.1);
}

/* Responsiveness per mobile */
@media (max-width: 480px) {
    .btr-modal-content {
        width: 95%;
        border-radius: 16px;
    }

    .btr-modal-header {
        padding: 1.5rem 1.5rem 1rem;
    }

    .btr-modal-body {
        padding: 1rem 1.5rem 1.5rem;
    }

    .btr-modal-footer {
        flex-direction: column;
        padding: 1.25rem 1.5rem 1.5rem;
    }

    .btr-modal-cancel,
    .btr-modal-confirm-btn {
        width: 100%;
    }
}

/* QR Code modal styles */
#btr-qr-modal .btr-modal-content {
    padding: 2rem;
    text-align: center;
    position: relative;
}

.btr-modal-close {
    position: absolute;
    right: 1rem;
    top: 1rem;
    width: 32px;
    height: 32px;
    cursor: pointer;
    color: var(--btr-gray-500, #6b7280);
    font-size: 28px;
    line-height: 1;
    transition: color var(--btr-transition-fast, 150ms ease-in-out);
}

.btr-modal-close:hover {
    color: var(--btr-gray-700, #374151);
}
</style>

<div class="btr-payment-links-summary">
    <div class="btr-container">
        <div id="btr-toast" class="btr-toast" aria-live="polite" style="display:none"></div>
        
        <?php if (!$preventivo || empty($payment_links)) : ?>
            <div class="btr-error-message">
                <h2><?php _e('Errore', 'born-to-ride-booking'); ?></h2>
                <p><?php _e('Nessun link di pagamento trovato per questo preventivo.', 'born-to-ride-booking'); ?></p>
            </div>
        <?php else : ?>
            
            <div class="btr-header">
                <h1><?php _e('Link di Pagamento Generati', 'born-to-ride-booking'); ?></h1>
                <div class="btr-package-info">
                    <h2><?php echo esc_html($nome_pacchetto); ?></h2>
                    <?php if ($date_range) : ?>
                        <p class="dates"><?php echo esc_html($date_range); ?></p>
                    <?php endif; ?>
                    <p class="total-amount">
                        <?php _e('Totale preventivo:', 'born-to-ride-booking'); ?> 
                        <strong>€<?php echo number_format($prezzo_totale, 2, ',', '.'); ?></strong>
                    </p>
                </div>
            </div>

            <div class="btr-success-message">
                <p>✅ <?php _e('I link di pagamento sono stati generati con successo!', 'born-to-ride-booking'); ?></p>
                <p><?php _e('Ogni partecipante riceverà il proprio link personale via email.', 'born-to-ride-booking'); ?></p>
            </div>

            <div class="btr-payment-links-table">
                <h3><?php _e('Riepilogo Link di Pagamento', 'born-to-ride-booking'); ?></h3>
                
                <table class="btr-links-table">
                    <thead>
                        <tr>
                            <th><?php _e('Partecipante', 'born-to-ride-booking'); ?></th>
                            <th><?php _e('Email', 'born-to-ride-booking'); ?></th>
                            <th><?php _e('Importo', 'born-to-ride-booking'); ?></th>
                            <th><?php _e('Stato', 'born-to-ride-booking'); ?></th>
                            <th><?php _e('Azioni', 'born-to-ride-booking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payment_links as $link) : 
                            $is_paid = isset($link['payment_status']) && $link['payment_status'] === 'paid';
                        ?>
                            <tr class="<?php echo $is_paid ? 'paid' : 'pending'; ?>">
                                <td class="participant-name">
                                    <?php echo esc_html($link['participant_name']); ?>
                                </td>
                                <td class="participant-email">
                                    <?php echo esc_html($link['participant_email']); ?>
                                </td>
                                <td class="amount">
                                    €<?php echo number_format($link['amount'], 2, ',', '.'); ?>
                                </td>
                                <td class="status">
                                    <?php if ($is_paid) : ?>
                                        <span class="status-paid">✅ <?php _e('Pagato', 'born-to-ride-booking'); ?></span>
                                    <?php else : ?>
                                        <span class="status-pending">⏳ <?php _e('In attesa', 'born-to-ride-booking'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions">
                                    <div class="action-buttons">
                                        <?php if (!$is_paid) : ?>
                                            <button class="btn-copy-link" 
                                                    data-link="<?php echo esc_attr($link['payment_url']); ?>"
                                                    title="<?php _e('Copia link', 'born-to-ride-booking'); ?>">
                                                📋 <?php _e('Copia', 'born-to-ride-booking'); ?>
                                            </button>
                                            <button class="btn-send-email" 
                                                    data-payment-id="<?php echo esc_attr($link['payment_id']); ?>"
                                                    title="<?php _e('Invia email', 'born-to-ride-booking'); ?>">
                                                ✉️ <?php _e('Invia Email', 'born-to-ride-booking'); ?>
                                            </button>
                                            <button class="btn-show-qr" 
                                                    data-link="<?php echo esc_attr($link['payment_url']); ?>"
                                                    data-name="<?php echo esc_attr($link['participant_name']); ?>"
                                                    title="<?php _e('Mostra QR Code', 'born-to-ride-booking'); ?>">
                                                📱 <?php _e('QR', 'born-to-ride-booking'); ?>
                                            </button>
                                        <?php else : ?>
                                            <span class="payment-completed"><?php _e('Completato', 'born-to-ride-booking'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2"><strong><?php _e('Totale', 'born-to-ride-booking'); ?></strong></td>
                            <td><strong>€<?php echo number_format($prezzo_totale, 2, ',', '.'); ?></strong></td>
                            <td colspan="2">
                                <?php 
                                $paid_count = 0;
                                foreach ($payment_links as $link) {
                                    if (isset($link['payment_status']) && $link['payment_status'] === 'paid') {
                                        $paid_count++;
                                    }
                                }
                                echo sprintf(__('%d di %d pagati', 'born-to-ride-booking'), $paid_count, count($payment_links));
                                ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="btr-actions-bottom">
                <button class="btn-send-all-emails" id="send-all-emails">
                    ✉️ <?php _e('Invia Email a Tutti i Partecipanti', 'born-to-ride-booking'); ?>
                </button>
                <button class="btn-print" onclick="window.print()">
                    🖨️ <?php _e('Stampa Riepilogo', 'born-to-ride-booking'); ?>
                </button>
                
                <!-- NUOVO: Pulsante per procedere come organizzatore -->
                <button class="btn-organizer-proceed" id="organizer-proceed" data-preventivo-id="<?php echo esc_attr($preventivo_id); ?>">
                    🛒 <?php _e('Procedi come Organizzatore', 'born-to-ride-booking'); ?>
                </button>
                
                <?php if (current_user_can('edit_posts')) : ?>
                    <a href="<?php echo admin_url('edit.php?post_type=btr_preventivi&page=btr-group-payments&preventivo_id=' . $preventivo_id); ?>" 
                       class="btn-admin-view">
                        ⚙️ <?php _e('Gestione Admin', 'born-to-ride-booking'); ?>
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Info aggiuntiva per organizzatore -->
            <div class="btr-organizer-info">
                <div class="btr-alert btr-alert-info">
                    <svg class="btr-alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div class="btr-alert-content">
                        <strong><?php _e('Nota per l\'Organizzatore:', 'born-to-ride-booking'); ?></strong>
                        <p><?php _e('Dopo aver inviato i link ai partecipanti, puoi procedere con la creazione dell\'ordine cliccando su "Procedi come Organizzatore". L\'ordine verrà creato e rimarrà in attesa fino a quando tutti i partecipanti non avranno completato il loro pagamento.', 'born-to-ride-booking'); ?></p>
                    </div>
                </div>
            </div>

        <?php endif; ?>
        
    </div>
</div>

<!-- Modal per QR Code -->
<div id="btr-qr-modal" class="btr-modal" style="display: none;">
    <div class="btr-modal-content">
        <span class="btr-modal-close">&times;</span>
        <h3><?php _e('QR Code per', 'born-to-ride-booking'); ?> <span id="qr-participant-name"></span></h3>
        <div id="qr-code-container"></div>
        <p class="qr-link-text"><small id="qr-link-url"></small></p>
    </div>
</div>

<!-- Modal di conferma organizzatore -->
<div id="btr-organizer-modal" class="btr-modal" style="display: none;">
    <div class="btr-modal-content btr-modal-confirm">
        <div class="btr-modal-header">
            <h3><?php _e('Conferma Creazione Ordine', 'born-to-ride-booking'); ?></h3>
        </div>
        <div class="btr-modal-body">
            <div class="btr-modal-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <p class="btr-modal-message">
                <?php _e('Vuoi procedere con la creazione dell\'ordine come organizzatore?', 'born-to-ride-booking'); ?>
            </p>
            <p class="btr-modal-submessage">
                <?php _e('L\'ordine rimarrà in attesa fino al completamento dei pagamenti dei partecipanti.', 'born-to-ride-booking'); ?>
            </p>
        </div>
        <div class="btr-modal-footer">
            <button class="btr-modal-cancel" type="button">
                <?php _e('Annulla', 'born-to-ride-booking'); ?>
            </button>
            <button class="btr-modal-confirm-btn" type="button">
                <?php _e('Procedi', 'born-to-ride-booking'); ?>
            </button>
        </div>
    </div>
</div>

<!-- Legacy duplicate styles removed to avoid conflicts with the new unified design -->
<style>
/* Toast notifications */
.btr-toast {
    position: fixed;
    right: 24px;
    bottom: 24px;
    background: var(--btr-gray-900, #111827);
    color: #fff;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    box-shadow: 0 10px 15px -3px rgba(0,0,0,.1), 0 4px 6px -4px rgba(0,0,0,.1);
    opacity: 0;
    transform: translateY(10px);
    transition: opacity .25s ease, transform .25s ease;
    z-index: 9999;
    pointer-events: none;
    font-size: 0.95rem;
    font-weight: 600;
}
.btr-toast.show { opacity: 1; transform: translateY(0); }
.btr-toast.success { background: var(--btr-success, #10b981); }
.btr-toast.error { background: var(--btr-danger, #ef4444); }
.btr-toast.info { background: var(--btr-primary, #0097c5); }
</style>

<script>
jQuery(document).ready(function($) {
    // Debug: verifica che jQuery sia caricato
    console.log('BTR Payment Links: jQuery loaded, version:', $.fn.jquery);
    console.log('BTR Payment Links: Document ready fired');
    
    // Toast helper
    function showToast(message, type) {
        var $toast = $('#btr-toast');
        if (!$toast.length) return;
        $toast.removeClass('success error info').addClass(type || 'info').text(message);
        $toast.show(0, function(){
            var el = $(this);
            requestAnimationFrame(function(){ el.addClass('show'); });
        });
        clearTimeout($toast.data('timeout'));
        var t = setTimeout(function(){
            $toast.removeClass('show');
            setTimeout(function(){ $toast.hide(); }, 250);
        }, 2500);
        $toast.data('timeout', t);
    }
    
    // Debug: verifica che il pulsante esista
    var $organizerButton = $('#organizer-proceed');
    console.log('BTR Payment Links: Organizer button found:', $organizerButton.length > 0);
    if ($organizerButton.length > 0) {
        console.log('BTR Payment Links: Button data-preventivo-id:', $organizerButton.data('preventivo-id'));
    }
    
    // Copia link
    $('.btn-copy-link').on('click', function() {
        var link = $(this).data('link');
        var $button = $(this);
        
        // Crea un elemento temporaneo per copiare
        var $temp = $('<input>');
        $('body').append($temp);
        $temp.val(link).select();
        document.execCommand('copy');
        $temp.remove();
        
        // Feedback visivo
        var originalText = $button.text();
        $button.text('✅ Copiato!');
        showToast('Link copiato negli appunti', 'success');
        setTimeout(function() {
            $button.text(originalText);
        }, 2000);
    });
    
    // Invia email singola
    $('.btn-send-email').on('click', function() {
        var $button = $(this);
        var paymentId = $button.data('payment-id');
        
        $button.prop('disabled', true).text('📤 Invio...');
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'btr_send_payment_email',
                payment_id: paymentId,
                nonce: '<?php echo wp_create_nonce('btr_group_payments'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $button.text('✅ Inviata!');
                    showToast('Email inviata', 'success');
                    setTimeout(function() {
                        $button.text('✉️ Invia Email').prop('disabled', false);
                    }, 3000);
                } else {
                    console.error('Errore nell\'invio dell\'email:', response.data);
                    showToast('Errore nell\'invio dell\'email: ' + (response.data || 'Errore sconosciuto'), 'error');
                    $button.text('✉️ Invia Email').prop('disabled', false);
                }
            },
            error: function() {
                console.error('Errore di comunicazione con il server');
                showToast('Errore di comunicazione con il server', 'error');
                $button.text('✉️ Invia Email').prop('disabled', false);
            }
        });
    });
    
    // Invia email a tutti
    $('#send-all-emails').on('click', function() {
        if (!confirm('<?php echo esc_js(__('Sei sicuro di voler inviare le email a tutti i partecipanti?', 'born-to-ride-booking')); ?>')) {
            return;
        }
        
        var $button = $(this);
        var $emailButtons = $('.btn-send-email:not(:disabled)');
        var total = $emailButtons.length;
        var sent = 0;
        
        if (total === 0) {
            showToast('<?php echo esc_js(__('Nessuna email da inviare', 'born-to-ride-booking')); ?>', 'info');
            return;
        }
        
        $button.prop('disabled', true).text('📤 Invio in corso... 0/' + total);
        
        // Invia email una alla volta con delay
        $emailButtons.each(function(index) {
            setTimeout(function() {
                $(this).click();
                sent++;
                $button.text('📤 Invio in corso... ' + sent + '/' + total);
                
                if (sent === total) {
                    setTimeout(function() {
                        $button.text('✅ Tutte le email inviate!').prop('disabled', false);
                        showToast('Tutte le email inviate', 'success');
                        setTimeout(function() {
                            $button.text('✉️ Invia Email a Tutti i Partecipanti');
                        }, 3000);
                    }, 1000);
                }
            }.bind(this), index * 1000); // 1 secondo di delay tra ogni invio
        });
    });
    
    // Mostra QR Code
    $('.btn-show-qr').on('click', function() {
        var link = $(this).data('link');
        var name = $(this).data('name');

        // Mostra i dati nel modal
        $('#qr-participant-name').text(name);
        $('#qr-link-url').text(link);

        // Genera QR Code effettivo
        $('#qr-code-container').empty();

        // Verifica se jQuery QRCode è disponibile
        if (typeof $.fn.qrcode !== 'undefined') {
            $('#qr-code-container').qrcode({
                text: link,
                width: 200,
                height: 200,
                render: 'canvas' // Usa canvas per migliore compatibilità
            });
        } else {
            console.error('jQuery QRCode library non trovata');
            $('#qr-code-container').html('<p style="color: red;">Errore: Libreria QR Code non caricata</p>');
        }

        // Mostra modal con fade in
        $('#btr-qr-modal').fadeIn(300);
    });

    // Chiudi modal
    $('.btr-modal-close, .btr-modal').on('click', function(e) {
        if (e.target === this) {
            $('#btr-qr-modal').fadeOut(300);
        }
    });
    
    // Gestione pulsante "Procedi come Organizzatore"
    // Usa event delegation per essere sicuri che funzioni anche se il DOM cambia
    $(document).on('click', '#organizer-proceed', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('BTR Payment Links: Organizer button clicked!');
        
        var $button = $(this);
        var preventivoId = $button.data('preventivo-id');
        
        console.log('BTR Payment Links: Preventivo ID:', preventivoId);
        
        if (!preventivoId) {
            alert('<?php echo esc_js(__('Errore: ID preventivo non trovato.', 'born-to-ride-booking')); ?>');
            return;
        }
        
        // Mostra il modal di conferma invece dell'alert
        $('#btr-organizer-modal').fadeIn(300);

        // Salva il riferimento al pulsante e al preventivo ID per l'uso nel modal
        var organizerData = {
            button: $button,
            preventivoId: preventivoId
        };

        // Gestione conferma nel modal
        $('#btr-organizer-modal .btr-modal-confirm-btn').off('click').on('click', function() {
            // Chiudi il modal
            $('#btr-organizer-modal').fadeOut(300);

            // Disabilita il pulsante
            organizerData.button.prop('disabled', true).html('<?php echo esc_js(__('Creazione ordine in corso...', 'born-to-ride-booking')); ?>');

            console.log('BTR Payment Links: Sending AJAX request...');
            console.log('BTR Payment Links: AJAX URL:', '<?php echo admin_url('admin-ajax.php'); ?>');
            console.log('BTR Payment Links: Action:', 'btr_create_organizer_order');
            console.log('BTR Payment Links: Nonce:', '<?php echo wp_create_nonce('btr_payment_organizer_nonce'); ?>');

            // Chiamata AJAX per creare l'ordine organizzatore
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'btr_create_organizer_order',
                    preventivo_id: organizerData.preventivoId,
                    nonce: '<?php echo wp_create_nonce('btr_payment_organizer_nonce'); ?>'
                },
                success: function(response) {
                    console.log('BTR Payment Links: AJAX Success:', response);

                    if (response.success) {
                        // Redirect all'ordine creato o alla pagina di conferma
                        if (response.data.redirect_url) {
                            console.log('BTR Payment Links: Redirecting to:', response.data.redirect_url);
                            window.location.href = response.data.redirect_url;
                        } else {
                            showToast('<?php echo esc_js(__('Ordine creato con successo!', 'born-to-ride-booking')); ?>', 'success');
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        }
                    } else {
                        var errorMsg = response.data && response.data.message ? response.data.message : '<?php echo esc_js(__('Errore sconosciuto', 'born-to-ride-booking')); ?>';
                        showToast('<?php echo esc_js(__('Errore nella creazione dell\'ordine: ', 'born-to-ride-booking')); ?>' + errorMsg, 'error');
                        organizerData.button.prop('disabled', false).html('🛒 <?php echo esc_js(__('Procedi come Organizzatore', 'born-to-ride-booking')); ?>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('BTR Payment Links: AJAX Error:', status, error);
                    console.error('BTR Payment Links: Response:', xhr.responseText);
                    showToast('<?php echo esc_js(__('Si è verificato un errore di comunicazione con il server. Riprova.', 'born-to-ride-booking')); ?>', 'error');
                    organizerData.button.prop('disabled', false).html('🛒 <?php echo esc_js(__('Procedi come Organizzatore', 'born-to-ride-booking')); ?>');
                }
            });
        });

        // Gestione annullamento nel modal
        $('#btr-organizer-modal .btr-modal-cancel, #btr-organizer-modal').off('click').on('click', function(e) {
            if (e.target === document.getElementById('btr-organizer-modal') ||
                $(e.target).hasClass('btr-modal-cancel')) {
                $('#btr-organizer-modal').fadeOut(300);
            }
        });
    });
    
    // Test alternativo: bind diretto sul pulsante se esiste
    if ($organizerButton.length > 0) {
        console.log('BTR Payment Links: Attempting direct bind on organizer button');
        $organizerButton.off('click').on('click', function(e) {
            console.log('BTR Payment Links: Direct bind click triggered!');
            // Trigger the delegated handler
            $(this).trigger('click');
        });
    }
});

// Test se jQuery è disponibile globalmente
if (typeof jQuery === 'undefined') {
    console.error('BTR Payment Links: jQuery not loaded!');
} else {
    console.log('BTR Payment Links: jQuery is available globally');
}
</script>

<!-- Include jQuery QR Code se necessario -->
<?php if (!wp_script_is('jquery-qrcode', 'enqueued')) : ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.qrcode/1.0/jquery.qrcode.min.js"></script>
<?php endif; ?>