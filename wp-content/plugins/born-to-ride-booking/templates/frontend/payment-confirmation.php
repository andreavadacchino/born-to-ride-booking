<?php
/**
 * Template per conferma pagamento
 * 
 * @package BornToRideBooking
 * @since 1.0.98
 */

if (!defined('ABSPATH')) {
    exit;
}

// Recupera payment dal query var
$payment = get_query_var('payment');
if (!$payment) {
    wp_redirect(home_url());
    exit;
}

// Recupera dati preventivo
$preventivo_id = $payment->preventivo_id;
$package_title = get_the_title(get_post_meta($preventivo_id, '_pacchetto_id', true));
$dates = get_post_meta($preventivo_id, '_date_viaggio', true);

// Recupera ordine WooCommerce se disponibile
$order = null;
if ($payment->wc_order_id) {
    $order = wc_get_order($payment->wc_order_id);
}

// Header del tema
get_header();
?>

<div class="btr-payment-confirmation-wrapper">
    <div class="container">
        <div class="confirmation-content">
            <!-- Success message -->
            <div class="success-header">
                <div class="success-icon">
                    <i class="fa fa-check-circle"></i>
                </div>
                <h1><?php esc_html_e('Pagamento Confermato!', 'born-to-ride-booking'); ?></h1>
                <p class="lead">
                    <?php esc_html_e('Il tuo pagamento è stato ricevuto con successo.', 'born-to-ride-booking'); ?>
                </p>
            </div>

            <!-- Dettagli pagamento -->
            <div class="confirmation-details">
                <div class="detail-card">
                    <h3><?php esc_html_e('Dettagli del Pagamento', 'born-to-ride-booking'); ?></h3>
                    
                    <div class="detail-row">
                        <span class="label"><?php esc_html_e('Nome:', 'born-to-ride-booking'); ?></span>
                        <span class="value"><?php echo esc_html($payment->group_member_name); ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="label"><?php esc_html_e('Importo pagato:', 'born-to-ride-booking'); ?></span>
                        <span class="value price"><?php echo btr_format_price_i18n($payment->amount); ?></span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="label"><?php esc_html_e('Data pagamento:', 'born-to-ride-booking'); ?></span>
                        <span class="value"><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($payment->paid_at))); ?></span>
                    </div>
                    
                    <?php if ($order): ?>
                    <div class="detail-row">
                        <span class="label"><?php esc_html_e('Numero ordine:', 'born-to-ride-booking'); ?></span>
                        <span class="value">#<?php echo esc_html($order->get_order_number()); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Dettagli viaggio -->
                <div class="detail-card">
                    <h3><?php esc_html_e('Dettagli del Viaggio', 'born-to-ride-booking'); ?></h3>
                    
                    <div class="trip-info">
                        <h4><?php echo esc_html($package_title); ?></h4>
                        <?php if ($dates): ?>
                        <p class="trip-dates">
                            <i class="fa fa-calendar"></i> 
                            <?php echo esc_html($dates); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Prossimi passi -->
                <div class="next-steps">
                    <h3><?php esc_html_e('Cosa succede ora?', 'born-to-ride-booking'); ?></h3>
                    <ul>
                        <li><?php esc_html_e('Riceverai una email di conferma con tutti i dettagli del pagamento', 'born-to-ride-booking'); ?></li>
                        <li><?php esc_html_e('L\'organizzatore del viaggio sarà notificato del tuo pagamento', 'born-to-ride-booking'); ?></li>
                        <li><?php esc_html_e('Riceverai ulteriori informazioni sul viaggio man mano che si avvicina la data di partenza', 'born-to-ride-booking'); ?></li>
                    </ul>
                </div>

                <!-- Actions -->
                <div class="confirmation-actions">
                    <a href="<?php echo esc_url(home_url()); ?>" class="btn btn-primary">
                        <?php esc_html_e('Torna alla Home', 'born-to-ride-booking'); ?>
                    </a>
                    <?php if ($order && $order->get_view_order_url()): ?>
                    <a href="<?php echo esc_url($order->get_view_order_url()); ?>" class="btn btn-secondary">
                        <?php esc_html_e('Visualizza Ordine', 'born-to-ride-booking'); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.btr-payment-confirmation-wrapper {
    padding: 60px 0;
    background-color: #f8f9fa;
    min-height: 100vh;
}

.confirmation-content {
    max-width: 800px;
    margin: 0 auto;
}

.success-header {
    text-align: center;
    margin-bottom: 50px;
}

.success-icon {
    font-size: 80px;
    color: #4caf50;
    margin-bottom: 20px;
}

.success-header h1 {
    color: #333;
    margin-bottom: 15px;
}

.success-header .lead {
    font-size: 18px;
    color: #666;
}

.confirmation-details {
    margin-bottom: 40px;
}

.detail-card {
    background: #fff;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 30px;
}

.detail-card h3 {
    margin-bottom: 25px;
    color: #333;
    font-size: 22px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid #eee;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-row .label {
    color: #666;
    font-weight: 500;
}

.detail-row .value {
    color: #333;
    font-weight: 600;
}

.detail-row .price {
    color: #0097c5;
    font-size: 18px;
}

.trip-info h4 {
    color: #0097c5;
    margin-bottom: 10px;
}

.trip-dates {
    color: #666;
}

.trip-dates i {
    margin-right: 8px;
    color: #0097c5;
}

.next-steps {
    background: #e3f2fd;
    padding: 30px;
    border-radius: 8px;
    margin-bottom: 40px;
}

.next-steps h3 {
    margin-bottom: 20px;
    color: #333;
}

.next-steps ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.next-steps li {
    padding: 10px 0 10px 30px;
    position: relative;
    color: #666;
    line-height: 1.6;
}

.next-steps li:before {
    content: "✓";
    position: absolute;
    left: 0;
    color: #4caf50;
    font-weight: bold;
    font-size: 18px;
}

.confirmation-actions {
    text-align: center;
}

.confirmation-actions .btn {
    padding: 12px 30px;
    font-size: 16px;
    border-radius: 50px;
    text-decoration: none;
    display: inline-block;
    margin: 0 10px;
    transition: all 0.3s ease;
}

.btn-primary {
    background-color: #0097c5;
    color: #fff;
    border: none;
}

.btn-primary:hover {
    background-color: #0086ad;
}

.btn-secondary {
    background-color: transparent;
    color: #0097c5;
    border: 2px solid #0097c5;
}

.btn-secondary:hover {
    background-color: #0097c5;
    color: #fff;
}

@media (max-width: 768px) {
    .confirmation-content {
        padding: 0 15px;
    }
    
    .detail-card {
        padding: 20px;
    }
    
    .detail-row {
        flex-direction: column;
    }
    
    .detail-row .label {
        margin-bottom: 5px;
    }
    
    .confirmation-actions .btn {
        display: block;
        width: 100%;
        margin: 10px 0;
    }
}
</style>

<?php
get_footer();