<?php
/**
 * Template per il checkout di una quota individuale di gruppo
 * 
 * @package BornToRideBooking
 * @since 1.0.98
 */

if (!defined('ABSPATH')) {
    exit;
}

// Recupera hash dal parametro URL
$payment_hash = isset($_GET['hash']) ? sanitize_text_field($_GET['hash']) : '';

if (empty($payment_hash)) {
    wp_die(__('Link di pagamento non valido', 'born-to-ride-booking'));
}

// Recupera dati pagamento dal database
global $wpdb;
$payment = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}btr_group_payments WHERE payment_hash = %s AND payment_status = 'pending'",
    $payment_hash
));

if (!$payment) {
    wp_die(__('Link di pagamento non valido o già utilizzato', 'born-to-ride-booking'));
}

// Verifica scadenza
if (strtotime($payment->expires_at) < current_time('timestamp')) {
    wp_die(__('Link di pagamento scaduto', 'born-to-ride-booking'));
}

// Recupera dati preventivo
$preventivo_id = $payment->preventivo_id;
$preventivo = get_post($preventivo_id);

if (!$preventivo) {
    wp_die(__('Preventivo non trovato', 'born-to-ride-booking'));
}

// Recupera dettagli pacchetto
$package_id = get_post_meta($preventivo_id, '_pacchetto_id', true);
$package_title = get_the_title($package_id);
$data_pacchetto = get_post_meta($preventivo_id, '_data_pacchetto', true);
$destinazione = get_post_meta($package_id, 'btr_destinazione', true);

// Formatta importo
$amount_formatted = btr_format_price_i18n($payment->amount);

get_header();
?>

<div class="btr-group-payment-checkout">
    <div class="container">
        <div class="btr-checkout-header">
            <h1><?php esc_html_e('Pagamento Quota Viaggio', 'born-to-ride-booking'); ?></h1>
            <p class="btr-checkout-subtitle">
                <?php esc_html_e('Completa il pagamento della tua quota per confermare la partecipazione', 'born-to-ride-booking'); ?>
            </p>
        </div>

        <div class="btr-checkout-content">
            <div class="btr-checkout-main">
                <!-- Dettagli Pagamento -->
                <div class="btr-payment-details">
                    <h2><?php esc_html_e('Dettagli del Pagamento', 'born-to-ride-booking'); ?></h2>
                    
                    <div class="btr-detail-card">
                        <div class="btr-detail-row">
                            <span class="btr-detail-label"><?php esc_html_e('Partecipante:', 'born-to-ride-booking'); ?></span>
                            <span class="btr-detail-value"><?php echo esc_html($payment->group_member_name); ?></span>
                        </div>
                        
                        <div class="btr-detail-row">
                            <span class="btr-detail-label"><?php esc_html_e('Pacchetto:', 'born-to-ride-booking'); ?></span>
                            <span class="btr-detail-value"><?php echo esc_html($package_title); ?></span>
                        </div>
                        
                        <div class="btr-detail-row">
                            <span class="btr-detail-label"><?php esc_html_e('Destinazione:', 'born-to-ride-booking'); ?></span>
                            <span class="btr-detail-value"><?php echo esc_html($destinazione); ?></span>
                        </div>
                        
                        <div class="btr-detail-row">
                            <span class="btr-detail-label"><?php esc_html_e('Data partenza:', 'born-to-ride-booking'); ?></span>
                            <span class="btr-detail-value"><?php echo esc_html(date_i18n('d F Y', strtotime($data_pacchetto))); ?></span>
                        </div>
                        
                        <div class="btr-detail-row btr-highlight">
                            <span class="btr-detail-label"><?php esc_html_e('Importo da pagare:', 'born-to-ride-booking'); ?></span>
                            <span class="btr-detail-value btr-amount"><?php echo $amount_formatted; ?></span>
                        </div>
                        
                        <?php if ($payment->share_percentage): ?>
                        <div class="btr-detail-row">
                            <span class="btr-detail-label"><?php esc_html_e('Percentuale quota:', 'born-to-ride-booking'); ?></span>
                            <span class="btr-detail-value"><?php echo esc_html($payment->share_percentage); ?>%</span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="btr-payment-notice">
                        <p>
                            <strong><?php esc_html_e('Scadenza pagamento:', 'born-to-ride-booking'); ?></strong>
                            <?php echo esc_html(date_i18n('d F Y alle H:i', strtotime($payment->expires_at))); ?>
                        </p>
                    </div>
                </div>

                <!-- Form Checkout -->
                <div class="btr-checkout-form">
                    <h2><?php esc_html_e('Completa il Pagamento', 'born-to-ride-booking'); ?></h2>
                    
                    <form id="btr-group-payment-form" method="post" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
                        <input type="hidden" name="action" value="btr_process_group_payment">
                        <input type="hidden" name="payment_hash" value="<?php echo esc_attr($payment_hash); ?>">
                        <?php wp_nonce_field('btr_group_payment_' . $payment_hash, 'btr_payment_nonce'); ?>
                        
                        <!-- Dati fatturazione -->
                        <div class="btr-billing-section">
                            <h3><?php esc_html_e('Dati di Fatturazione', 'born-to-ride-booking'); ?></h3>
                            
                            <div class="btr-form-row">
                                <div class="btr-form-col">
                                    <label for="billing_first_name"><?php esc_html_e('Nome', 'born-to-ride-booking'); ?> <span class="required">*</span></label>
                                    <input type="text" id="billing_first_name" name="billing_first_name" required>
                                </div>
                                <div class="btr-form-col">
                                    <label for="billing_last_name"><?php esc_html_e('Cognome', 'born-to-ride-booking'); ?> <span class="required">*</span></label>
                                    <input type="text" id="billing_last_name" name="billing_last_name" required>
                                </div>
                            </div>
                            
                            <div class="btr-form-row">
                                <div class="btr-form-col">
                                    <label for="billing_email"><?php esc_html_e('Email', 'born-to-ride-booking'); ?> <span class="required">*</span></label>
                                    <input type="email" id="billing_email" name="billing_email" required>
                                </div>
                                <div class="btr-form-col">
                                    <label for="billing_phone"><?php esc_html_e('Telefono', 'born-to-ride-booking'); ?> <span class="required">*</span></label>
                                    <input type="tel" id="billing_phone" name="billing_phone" required>
                                </div>
                            </div>
                            
                            <div class="btr-form-row">
                                <div class="btr-form-col-full">
                                    <label for="billing_address"><?php esc_html_e('Indirizzo', 'born-to-ride-booking'); ?> <span class="required">*</span></label>
                                    <input type="text" id="billing_address" name="billing_address" required>
                                </div>
                            </div>
                            
                            <div class="btr-form-row">
                                <div class="btr-form-col">
                                    <label for="billing_city"><?php esc_html_e('Città', 'born-to-ride-booking'); ?> <span class="required">*</span></label>
                                    <input type="text" id="billing_city" name="billing_city" required>
                                </div>
                                <div class="btr-form-col">
                                    <label for="billing_postcode"><?php esc_html_e('CAP', 'born-to-ride-booking'); ?> <span class="required">*</span></label>
                                    <input type="text" id="billing_postcode" name="billing_postcode" required>
                                </div>
                            </div>
                            
                            <div class="btr-form-row">
                                <div class="btr-form-col-full">
                                    <label for="billing_cf"><?php esc_html_e('Codice Fiscale', 'born-to-ride-booking'); ?> <span class="required">*</span></label>
                                    <input type="text" id="billing_cf" name="billing_cf" required pattern="[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]" style="text-transform: uppercase;">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Metodo di pagamento -->
                        <div class="btr-payment-methods">
                            <h3><?php esc_html_e('Metodo di Pagamento', 'born-to-ride-booking'); ?></h3>
                            
                            <?php
                            // Recupera gateway di pagamento disponibili
                            $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
                            
                            if ($available_gateways):
                                foreach ($available_gateways as $gateway):
                                    if ($gateway->enabled === 'yes'):
                            ?>
                                <div class="btr-payment-method">
                                    <input type="radio" 
                                           id="payment_method_<?php echo esc_attr($gateway->id); ?>" 
                                           name="payment_method" 
                                           value="<?php echo esc_attr($gateway->id); ?>"
                                           <?php checked($gateway->chosen, true); ?>>
                                    <label for="payment_method_<?php echo esc_attr($gateway->id); ?>">
                                        <?php echo $gateway->get_title(); ?>
                                        <?php if ($gateway->get_icon()): ?>
                                            <span class="payment-method-icon"><?php echo $gateway->get_icon(); ?></span>
                                        <?php endif; ?>
                                    </label>
                                    
                                    <?php if ($gateway->has_fields() || $gateway->get_description()): ?>
                                        <div class="payment-method-description" id="payment_method_desc_<?php echo esc_attr($gateway->id); ?>" style="display: none;">
                                            <?php echo wpautop(wptexturize($gateway->get_description())); ?>
                                            <?php if ($gateway->has_fields()): ?>
                                                <div class="payment-method-fields">
                                                    <?php $gateway->payment_fields(); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php
                                    endif;
                                endforeach;
                            endif;
                            ?>
                        </div>
                        
                        <!-- Termini e condizioni -->
                        <div class="btr-terms-section">
                            <label>
                                <input type="checkbox" name="terms" id="terms" required>
                                <?php 
                                printf(
                                    __('Ho letto e accetto i <a href="%s" target="_blank">termini e condizioni</a>', 'born-to-ride-booking'),
                                    esc_url(get_privacy_policy_url())
                                );
                                ?>
                            </label>
                        </div>
                        
                        <!-- Pulsante submit -->
                        <div class="btr-submit-section">
                            <button type="submit" class="btr-btn btr-btn-primary btr-btn-large" id="btr-submit-payment">
                                <?php esc_html_e('Procedi al Pagamento', 'born-to-ride-booking'); ?>
                                <span class="btr-btn-amount"><?php echo $amount_formatted; ?></span>
                            </button>
                            
                            <div class="btr-secure-notice">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                    <path d="M8 0C3.58 0 0 3.58 0 8s3.58 8 8 8 8-3.58 8-8-3.58-8-8-8zM6.5 12L3 8.5l1.41-1.41L6.5 9.17l5.09-5.09L13 5.5 6.5 12z"/>
                                </svg>
                                <?php esc_html_e('Pagamento sicuro e protetto', 'born-to-ride-booking'); ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sidebar riepilogo -->
            <div class="btr-checkout-sidebar">
                <div class="btr-order-summary">
                    <h3><?php esc_html_e('Riepilogo Ordine', 'born-to-ride-booking'); ?></h3>
                    
                    <div class="btr-summary-content">
                        <div class="btr-summary-item">
                            <span><?php echo esc_html($package_title); ?></span>
                            <span><?php echo $amount_formatted; ?></span>
                        </div>
                        
                        <div class="btr-summary-divider"></div>
                        
                        <div class="btr-summary-total">
                            <span><?php esc_html_e('Totale', 'born-to-ride-booking'); ?></span>
                            <span class="btr-total-amount"><?php echo $amount_formatted; ?></span>
                        </div>
                    </div>
                    
                    <!-- Info gruppo -->
                    <?php
                    // Recupera info su altri pagamenti del gruppo
                    $group_payments = $wpdb->get_results($wpdb->prepare(
                        "SELECT * FROM {$wpdb->prefix}btr_group_payments 
                         WHERE preventivo_id = %d AND payment_plan_type = 'group_split'
                         ORDER BY group_member_id",
                        $preventivo_id
                    ));
                    
                    if ($group_payments && count($group_payments) > 1):
                    ?>
                    <div class="btr-group-info">
                        <h4><?php esc_html_e('Stato Pagamenti Gruppo', 'born-to-ride-booking'); ?></h4>
                        <div class="btr-payment-progress">
                            <?php
                            $paid_count = 0;
                            foreach ($group_payments as $gp) {
                                if ($gp->payment_status === 'paid') {
                                    $paid_count++;
                                }
                            }
                            $progress_percentage = ($paid_count / count($group_payments)) * 100;
                            ?>
                            <div class="btr-progress-bar">
                                <div class="btr-progress-fill" style="width: <?php echo $progress_percentage; ?>%"></div>
                            </div>
                            <p class="btr-progress-text">
                                <?php 
                                printf(
                                    __('%d su %d quote pagate', 'born-to-ride-booking'),
                                    $paid_count,
                                    count($group_payments)
                                );
                                ?>
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Assistenza -->
                <div class="btr-support-box">
                    <h4><?php esc_html_e('Hai bisogno di aiuto?', 'born-to-ride-booking'); ?></h4>
                    <p><?php esc_html_e('Contatta il nostro supporto:', 'born-to-ride-booking'); ?></p>
                    <a href="tel:+390123456789" class="btr-support-link">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                            <path d="M3.654 1.328a.678.678 0 0 0-1.015-.063L1.605 2.3c-.483.484-.661 1.169-.45 1.77a17.568 17.568 0 0 0 4.168 6.608 17.569 17.569 0 0 0 6.608 4.168c.601.211 1.286.033 1.77-.45l1.034-1.034a.678.678 0 0 0-.063-1.015l-2.307-1.794a.678.678 0 0 0-.58-.122l-2.19.547a1.745 1.745 0 0 1-1.657-.459L5.482 8.062a1.745 1.745 0 0 1-.46-1.657l.548-2.19a.678.678 0 0 0-.122-.58L3.654 1.328zM1.884.511a1.745 1.745 0 0 1 2.612.163L6.29 2.98c.329.423.445.974.315 1.494l-.547 2.19a.678.678 0 0 0 .178.643l2.457 2.457a.678.678 0 0 0 .644.178l2.189-.547a1.745 1.745 0 0 1 1.494.315l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.634 18.634 0 0 1-7.01-4.42 18.634 18.634 0 0 1-4.42-7.009c-.362-1.03-.037-2.137.703-2.877L1.885.511z"/>
                        </svg>
                        +39 012 345 6789
                    </a>
                    <a href="mailto:info@borntoride.it" class="btr-support-link">
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                            <path d="M.05 3.555A2 2 0 0 1 2 2h12a2 2 0 0 1 1.95 1.555L8 8.414.05 3.555zM0 4.697v7.104l5.803-3.558L0 4.697zM6.761 8.83l-6.57 4.027A2 2 0 0 0 2 14h12a2 2 0 0 0 1.808-1.144l-6.57-4.027L8 9.586l-1.239-.757zm3.436-.586L16 11.801V4.697l-5.803 3.546z"/>
                        </svg>
                        info@borntoride.it
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Stili CSS per la pagina di checkout */
.btr-group-payment-checkout {
    padding: 40px 0;
    background-color: #f5f5f5;
    min-height: 100vh;
}

.btr-checkout-header {
    text-align: center;
    margin-bottom: 40px;
}

.btr-checkout-header h1 {
    font-size: 32px;
    margin-bottom: 10px;
    color: #333;
}

.btr-checkout-subtitle {
    font-size: 18px;
    color: #666;
}

.btr-checkout-content {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 30px;
    max-width: 1200px;
    margin: 0 auto;
}

.btr-checkout-main {
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.btr-payment-details h2,
.btr-checkout-form h2 {
    font-size: 24px;
    margin-bottom: 20px;
    color: #333;
}

.btr-detail-card {
    background: #f9f9f9;
    padding: 20px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.btr-detail-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #e0e0e0;
}

.btr-detail-row:last-child {
    border-bottom: none;
}

.btr-detail-row.btr-highlight {
    background: #fff3cd;
    margin: 10px -20px -20px;
    padding: 15px 20px;
    border-radius: 0 0 6px 6px;
    border: none;
}

.btr-detail-label {
    color: #666;
}

.btr-detail-value {
    font-weight: 600;
    color: #333;
}

.btr-amount {
    font-size: 24px;
    color: #0097c5;
}

.btr-payment-notice {
    background: #e3f2fd;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 30px;
}

.btr-payment-notice p {
    margin: 0;
    color: #1976d2;
}

/* Form styles */
.btr-billing-section h3,
.btr-payment-methods h3 {
    font-size: 20px;
    margin-bottom: 15px;
    color: #333;
}

.btr-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.btr-form-col-full {
    grid-column: 1 / -1;
}

.btr-form-row label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
    color: #333;
}

.btr-form-row input {
    width: 100%;
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 16px;
}

.btr-form-row input:focus {
    outline: none;
    border-color: #0097c5;
    box-shadow: 0 0 0 3px rgba(0, 151, 197, 0.1);
}

.required {
    color: #d32f2f;
}

/* Payment methods */
.btr-payment-methods {
    margin: 30px 0;
}

.btr-payment-method {
    margin-bottom: 15px;
    padding: 15px;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btr-payment-method:hover {
    border-color: #0097c5;
}

.btr-payment-method input[type="radio"] {
    margin-right: 10px;
}

.btr-payment-method label {
    display: flex;
    align-items: center;
    cursor: pointer;
    font-weight: 500;
}

.payment-method-icon {
    margin-left: auto;
}

.payment-method-icon img {
    max-height: 30px;
}

.payment-method-description {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e0e0e0;
    font-size: 14px;
    color: #666;
}

/* Terms */
.btr-terms-section {
    margin: 30px 0;
}

.btr-terms-section label {
    display: flex;
    align-items: flex-start;
}

.btr-terms-section input[type="checkbox"] {
    margin-right: 10px;
    margin-top: 2px;
}

/* Submit button */
.btr-submit-section {
    text-align: center;
    margin-top: 30px;
}

.btr-btn {
    display: inline-block;
    padding: 12px 30px;
    border: none;
    border-radius: 4px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btr-btn-primary {
    background: #0097c5;
    color: white;
}

.btr-btn-primary:hover {
    background: #007aa3;
}

.btr-btn-large {
    padding: 16px 40px;
    font-size: 18px;
}

.btr-btn-amount {
    margin-left: 10px;
    padding: 5px 10px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 20px;
}

.btr-secure-notice {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    margin-top: 15px;
    color: #666;
    font-size: 14px;
}

.btr-secure-notice svg {
    color: #4caf50;
}

/* Sidebar */
.btr-checkout-sidebar {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.btr-order-summary,
.btr-support-box {
    background: white;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.btr-order-summary h3,
.btr-support-box h4 {
    font-size: 20px;
    margin-bottom: 20px;
    color: #333;
}

.btr-summary-item {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
}

.btr-summary-divider {
    height: 1px;
    background: #e0e0e0;
    margin: 20px 0;
}

.btr-summary-total {
    display: flex;
    justify-content: space-between;
    font-size: 20px;
    font-weight: 600;
}

.btr-total-amount {
    color: #0097c5;
}

/* Group info */
.btr-group-info {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #e0e0e0;
}

.btr-group-info h4 {
    font-size: 16px;
    margin-bottom: 15px;
}

.btr-progress-bar {
    height: 10px;
    background: #e0e0e0;
    border-radius: 5px;
    overflow: hidden;
    margin-bottom: 10px;
}

.btr-progress-fill {
    height: 100%;
    background: #4caf50;
    transition: width 0.3s ease;
}

.btr-progress-text {
    font-size: 14px;
    color: #666;
    text-align: center;
    margin: 0;
}

/* Support box */
.btr-support-box p {
    margin-bottom: 15px;
    color: #666;
}

.btr-support-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    margin-bottom: 10px;
    background: #f5f5f5;
    border-radius: 4px;
    text-decoration: none;
    color: #333;
    transition: all 0.3s ease;
}

.btr-support-link:hover {
    background: #e0e0e0;
}

.btr-support-link svg {
    color: #0097c5;
}

/* Responsive */
@media (max-width: 968px) {
    .btr-checkout-content {
        grid-template-columns: 1fr;
    }
    
    .btr-checkout-sidebar {
        order: -1;
    }
    
    .btr-form-row {
        grid-template-columns: 1fr;
    }
}

/* Loading state */
.btr-loading {
    opacity: 0.6;
    pointer-events: none;
}

.btr-loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 40px;
    height: 40px;
    margin: -20px 0 0 -20px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #0097c5;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Gestione selezione metodo di pagamento
    $('input[name="payment_method"]').on('change', function() {
        $('.payment-method-description').hide();
        $('#payment_method_desc_' + $(this).val()).show();
    });
    
    // Mostra il metodo selezionato di default
    $('input[name="payment_method"]:checked').trigger('change');
    
    // Gestione submit form
    $('#btr-group-payment-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $submitBtn = $('#btr-submit-payment');
        
        // Valida form
        if (!$form[0].checkValidity()) {
            $form[0].reportValidity();
            return;
        }
        
        // Disabilita submit e mostra loading
        $submitBtn.prop('disabled', true).addClass('btr-loading').text('Elaborazione...');
        
        // Invia form via AJAX
        $.ajax({
            url: $form.attr('action'),
            type: 'POST',
            data: $form.serialize(),
            success: function(response) {
                if (response.success) {
                    // Redirect alla pagina di pagamento o conferma
                    if (response.data.redirect) {
                        window.location.href = response.data.redirect;
                    } else {
                        // Mostra messaggio di successo
                        alert('Pagamento completato con successo!');
                        window.location.href = '<?php echo home_url(); ?>';
                    }
                } else {
                    // Mostra errore
                    alert('Errore: ' + response.data.message);
                    $submitBtn.prop('disabled', false).removeClass('btr-loading').html('Procedi al Pagamento <span class="btr-btn-amount"><?php echo $amount_formatted; ?></span>');
                }
            },
            error: function() {
                alert('Si è verificato un errore. Riprova più tardi.');
                $submitBtn.prop('disabled', false).removeClass('btr-loading').html('Procedi al Pagamento <span class="btr-btn-amount"><?php echo $amount_formatted; ?></span>');
            }
        });
    });
    
    // Formattazione codice fiscale
    $('#billing_cf').on('input', function() {
        $(this).val($(this).val().toUpperCase());
    });
});
</script>

<?php get_footer(); ?>