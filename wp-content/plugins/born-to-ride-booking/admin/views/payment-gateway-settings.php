<?php
/**
 * Vista admin per configurazione gateway pagamento
 * 
 * @package BornToRideBooking
 * @since 1.0.98
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php esc_html_e('Configurazione Gateway Pagamento BTR', 'born-to-ride-booking'); ?></h1>
    
    <?php if (isset($_GET['updated'])): ?>
    <div class="notice notice-success">
        <p><?php esc_html_e('Impostazioni salvate con successo.', 'born-to-ride-booking'); ?></p>
    </div>
    <?php endif; ?>
    
    <form method="post" action="options.php">
        <?php settings_fields('btr_payment_gateway_settings'); ?>
        
        <h2><?php esc_html_e('Integrazione Stripe', 'born-to-ride-booking'); ?></h2>
        
        <?php if (class_exists('WC_Gateway_Stripe')): ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="btr_stripe_webhook_secret">
                        <?php esc_html_e('Stripe Webhook Secret', 'born-to-ride-booking'); ?>
                    </label>
                </th>
                <td>
                    <input type="text" 
                           id="btr_stripe_webhook_secret" 
                           name="btr_stripe_webhook_secret" 
                           value="<?php echo esc_attr(get_option('btr_stripe_webhook_secret')); ?>" 
                           class="regular-text" />
                    <p class="description">
                        <?php esc_html_e('Endpoint webhook:', 'born-to-ride-booking'); ?> 
                        <code><?php echo esc_url(home_url('/wc-api/btr_payment_webhook?gateway=stripe')); ?></code>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <?php esc_html_e('Pagamenti Futuri', 'born-to-ride-booking'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="btr_stripe_future_payments" 
                               value="1" 
                               <?php checked(get_option('btr_stripe_future_payments'), '1'); ?> />
                        <?php esc_html_e('Abilita SetupIntent per pagamenti saldo futuri', 'born-to-ride-booking'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('Permette di salvare il metodo di pagamento per il saldo senza richiedere nuovamente i dati carta.', 'born-to-ride-booking'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php else: ?>
        <div class="notice notice-warning inline">
            <p><?php esc_html_e('WooCommerce Stripe non è installato o attivo.', 'born-to-ride-booking'); ?></p>
        </div>
        <?php endif; ?>
        
        <h2><?php esc_html_e('Integrazione PayPal', 'born-to-ride-booking'); ?></h2>
        
        <?php if (class_exists('WC_Gateway_PPCP') || class_exists('WC_Gateway_PayPal')): ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <?php esc_html_e('Reference Transactions', 'born-to-ride-booking'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="btr_paypal_reference_transactions" 
                               value="1" 
                               <?php checked(get_option('btr_paypal_reference_transactions'), '1'); ?> />
                        <?php esc_html_e('Abilita Reference Transactions per pagamenti ricorrenti', 'born-to-ride-booking'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('Richiede abilitazione sul conto PayPal merchant.', 'born-to-ride-booking'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="btr_paypal_ipn_url">
                        <?php esc_html_e('URL IPN Personalizzato', 'born-to-ride-booking'); ?>
                    </label>
                </th>
                <td>
                    <input type="url" 
                           id="btr_paypal_ipn_url" 
                           name="btr_paypal_ipn_url" 
                           value="<?php echo esc_attr(get_option('btr_paypal_ipn_url', home_url('/wc-api/btr_payment_webhook?gateway=paypal'))); ?>" 
                           class="regular-text" />
                </td>
            </tr>
        </table>
        <?php else: ?>
        <div class="notice notice-warning inline">
            <p><?php esc_html_e('WooCommerce PayPal Payments non è installato o attivo.', 'born-to-ride-booking'); ?></p>
        </div>
        <?php endif; ?>
        
        <h2><?php esc_html_e('Impostazioni Generali', 'born-to-ride-booking'); ?></h2>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <?php esc_html_e('Logging', 'born-to-ride-booking'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="btr_gateway_debug_logging" 
                               value="1" 
                               <?php checked(get_option('btr_gateway_debug_logging'), '1'); ?> />
                        <?php esc_html_e('Abilita logging dettagliato transazioni', 'born-to-ride-booking'); ?>
                    </label>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <?php esc_html_e('Retry automatico', 'born-to-ride-booking'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="btr_gateway_auto_retry" 
                               value="1" 
                               <?php checked(get_option('btr_gateway_auto_retry'), '1'); ?> />
                        <?php esc_html_e('Riprova automaticamente pagamenti falliti', 'born-to-ride-booking'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('Numero tentativi:', 'born-to-ride-booking'); ?>
                        <input type="number" 
                               name="btr_gateway_retry_attempts" 
                               value="<?php echo esc_attr(get_option('btr_gateway_retry_attempts', 3)); ?>" 
                               min="1" 
                               max="5" 
                               style="width: 60px;" />
                    </p>
                </td>
            </tr>
        </table>
        
        <h2><?php esc_html_e('Stati Ordine Personalizzati', 'born-to-ride-booking'); ?></h2>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <?php esc_html_e('Email notifiche', 'born-to-ride-booking'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="btr_gateway_deposit_paid_email" 
                               value="1" 
                               <?php checked(get_option('btr_gateway_deposit_paid_email'), '1'); ?> />
                        <?php esc_html_e('Invia email quando caparra pagata', 'born-to-ride-booking'); ?>
                    </label><br>
                    <label>
                        <input type="checkbox" 
                               name="btr_gateway_balance_reminder_email" 
                               value="1" 
                               <?php checked(get_option('btr_gateway_balance_reminder_email'), '1'); ?> />
                        <?php esc_html_e('Invia reminder automatici per saldo', 'born-to-ride-booking'); ?>
                    </label><br>
                    <label>
                        <input type="checkbox" 
                               name="btr_gateway_fully_paid_email" 
                               value="1" 
                               <?php checked(get_option('btr_gateway_fully_paid_email'), '1'); ?> />
                        <?php esc_html_e('Invia conferma pagamento completo', 'born-to-ride-booking'); ?>
                    </label>
                </td>
            </tr>
        </table>
        
        <?php submit_button(__('Salva Impostazioni', 'born-to-ride-booking')); ?>
    </form>
    
    <hr>
    
    <h2><?php esc_html_e('Test Gateway', 'born-to-ride-booking'); ?></h2>
    
    <div class="btr-gateway-test-section">
        <p><?php esc_html_e('Usa questi strumenti per testare l\'integrazione gateway:', 'born-to-ride-booking'); ?></p>
        
        <p>
            <button type="button" class="button" id="btr-test-stripe-webhook">
                <?php esc_html_e('Test Webhook Stripe', 'born-to-ride-booking'); ?>
            </button>
            <button type="button" class="button" id="btr-test-paypal-ipn">
                <?php esc_html_e('Test IPN PayPal', 'born-to-ride-booking'); ?>
            </button>
        </p>
        
        <div id="btr-test-results" style="display:none;">
            <h3><?php esc_html_e('Risultati Test', 'born-to-ride-booking'); ?></h3>
            <pre id="btr-test-output" style="background: #f0f0f0; padding: 10px; max-height: 300px; overflow: auto;"></pre>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Test webhook
    $('#btr-test-stripe-webhook, #btr-test-paypal-ipn').on('click', function() {
        const gateway = $(this).attr('id').includes('stripe') ? 'stripe' : 'paypal';
        const $button = $(this);
        const $results = $('#btr-test-results');
        const $output = $('#btr-test-output');
        
        $button.prop('disabled', true);
        $output.text('Testing...');
        $results.show();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'btr_test_gateway_webhook',
                gateway: gateway,
                nonce: '<?php echo wp_create_nonce('btr_test_gateway'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $output.text(response.data.message);
                } else {
                    $output.text('Error: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                $output.text('Request failed: ' + error);
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
});
</script>

<style>
.btr-gateway-test-section {
    background: #fff;
    padding: 20px;
    border: 1px solid #ddd;
    margin-top: 20px;
}

.form-table code {
    background: #f0f0f0;
    padding: 3px 5px;
    font-size: 12px;
}

.notice.inline {
    margin: 10px 0;
}
</style>