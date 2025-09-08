<?php
/**
 * Vista admin ottimizzata per configurazione gateway pagamento
 * 
 * Mostra lo stato dei gateway WooCommerce esistenti e permette
 * configurazioni aggiuntive specifiche per BTR.
 * 
 * @package BornToRideBooking
 * @since 1.0.98
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ottieni istanza gateway integration
$gateway_integration = BTR_Payment_Gateway_Integration::get_instance();

// Verifica disponibilità gateway
$stripe_available = $gateway_integration->is_gateway_available('stripe');
$stripe_config = $stripe_available ? $gateway_integration->get_gateway_config('stripe') : false;

$paypal_available = $gateway_integration->is_gateway_available('paypal');
$paypal_config = $paypal_available ? $gateway_integration->get_gateway_config('paypal') : false;
?>

<div class="wrap">
    <h1><?php esc_html_e('Configurazione Gateway Pagamento BTR', 'born-to-ride-booking'); ?></h1>
    
    <?php if (isset($_GET['updated'])): ?>
    <div class="notice notice-success">
        <p><?php esc_html_e('Impostazioni salvate con successo.', 'born-to-ride-booking'); ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Stato Gateway -->
    <div class="btr-gateway-status">
        <h2><?php esc_html_e('Stato Gateway di Pagamento', 'born-to-ride-booking'); ?></h2>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Gateway', 'born-to-ride-booking'); ?></th>
                    <th><?php esc_html_e('Stato', 'born-to-ride-booking'); ?></th>
                    <th><?php esc_html_e('Configurazione', 'born-to-ride-booking'); ?></th>
                    <th><?php esc_html_e('Azioni', 'born-to-ride-booking'); ?></th>
                </tr>
            </thead>
            <tbody>
                <!-- Stripe -->
                <tr>
                    <td>
                        <strong>Stripe</strong><br>
                        <small><?php esc_html_e('Pagamenti con carta di credito', 'born-to-ride-booking'); ?></small>
                    </td>
                    <td>
                        <?php if ($stripe_available && $stripe_config['enabled']): ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> 
                            <?php esc_html_e('Attivo', 'born-to-ride-booking'); ?>
                            <?php if ($stripe_config['testmode']): ?>
                                <span class="badge" style="background: #ff9800; color: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">TEST</span>
                            <?php endif; ?>
                        <?php elseif (class_exists('WC_Gateway_Stripe')): ?>
                            <span class="dashicons dashicons-warning" style="color: #ff9800;"></span> 
                            <?php esc_html_e('Installato ma non abilitato', 'born-to-ride-booking'); ?>
                        <?php else: ?>
                            <span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span> 
                            <?php esc_html_e('Non installato', 'born-to-ride-booking'); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($stripe_config): ?>
                            <div style="font-size: 12px;">
                                <strong><?php esc_html_e('API Keys:', 'born-to-ride-booking'); ?></strong> ✓<br>
                                <strong><?php esc_html_e('Webhook:', 'born-to-ride-booking'); ?></strong> 
                                <?php echo $stripe_config['webhook_secret'] ? '✓' : '⚠️ ' . __('Da configurare', 'born-to-ride-booking'); ?><br>
                                <strong><?php esc_html_e('Pagamenti futuri:', 'born-to-ride-booking'); ?></strong> ✓
                            </div>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (class_exists('WC_Gateway_Stripe')): ?>
                            <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=stripe'); ?>" class="button button-small">
                                <?php esc_html_e('Configura', 'born-to-ride-booking'); ?>
                            </a>
                        <?php else: ?>
                            <a href="https://wordpress.org/plugins/woocommerce-gateway-stripe/" target="_blank" class="button button-small">
                                <?php esc_html_e('Installa', 'born-to-ride-booking'); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                
                <!-- PayPal -->
                <tr>
                    <td>
                        <strong>PayPal</strong><br>
                        <small><?php esc_html_e('Pagamenti con PayPal', 'born-to-ride-booking'); ?></small>
                    </td>
                    <td>
                        <?php if ($paypal_available && $paypal_config['enabled']): ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> 
                            <?php esc_html_e('Attivo', 'born-to-ride-booking'); ?>
                        <?php elseif (class_exists('WC_Gateway_PPCP') || class_exists('WC_Gateway_PayPal')): ?>
                            <span class="dashicons dashicons-warning" style="color: #ff9800;"></span> 
                            <?php esc_html_e('Installato ma non abilitato', 'born-to-ride-booking'); ?>
                        <?php else: ?>
                            <span class="dashicons dashicons-dismiss" style="color: #dc3232;"></span> 
                            <?php esc_html_e('Non installato', 'born-to-ride-booking'); ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($paypal_config): ?>
                            <div style="font-size: 12px;">
                                <strong><?php esc_html_e('API Credentials:', 'born-to-ride-booking'); ?></strong> ✓<br>
                                <strong><?php esc_html_e('Pagamenti futuri:', 'born-to-ride-booking'); ?></strong> 
                                <?php echo $paypal_config['supports_future_payments'] ? '✓' : '❌'; ?>
                            </div>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (class_exists('WC_Gateway_PPCP')): ?>
                            <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway'); ?>" class="button button-small">
                                <?php esc_html_e('Configura', 'born-to-ride-booking'); ?>
                            </a>
                        <?php elseif (class_exists('WC_Gateway_PayPal')): ?>
                            <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=paypal'); ?>" class="button button-small">
                                <?php esc_html_e('Configura', 'born-to-ride-booking'); ?>
                            </a>
                        <?php else: ?>
                            <a href="https://wordpress.org/plugins/woocommerce-paypal-payments/" target="_blank" class="button button-small">
                                <?php esc_html_e('Installa', 'born-to-ride-booking'); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <form method="post" action="options.php">
        <?php settings_fields('btr_payment_gateway_settings'); ?>
        
        <!-- Configurazioni Aggiuntive BTR -->
        <h2><?php esc_html_e('Configurazioni Aggiuntive BTR', 'born-to-ride-booking'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <?php esc_html_e('Pagamento di Gruppo', 'born-to-ride-booking'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="btr_enable_group_split" value="1" <?php checked(get_option('btr_enable_group_split', '1'), '1'); ?> />
                        <?php esc_html_e('Abilita Pagamento di Gruppo', 'born-to-ride-booking'); ?>
                    </label>
                    <p class="description"><?php esc_html_e('Se abilitato, la modalità gruppo sarà disponibile quando la soglia è raggiunta.', 'born-to-ride-booking'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="btr_group_split_threshold"><?php esc_html_e('Soglia minima partecipanti (gruppo)', 'born-to-ride-booking'); ?></label>
                </th>
                <td>
                    <input type="number" id="btr_group_split_threshold" name="btr_group_split_threshold" value="<?php echo esc_attr(get_option('btr_group_split_threshold', 10)); ?>" min="1" step="1" class="small-text" />
                    <p class="description"><?php esc_html_e('Numero totale partecipanti (adulti+bambini+neonati) a partire dal quale offrire il pagamento di gruppo.', 'born-to-ride-booking'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="btr_default_payment_mode"><?php esc_html_e('Modalità pagamento predefinita', 'born-to-ride-booking'); ?></label>
                </th>
                <td>
                    <select id="btr_default_payment_mode" name="btr_default_payment_mode">
                        <option value="full" <?php selected(get_option('btr_default_payment_mode', 'full'), 'full'); ?>><?php esc_html_e('Pagamento Completo', 'born-to-ride-booking'); ?></option>
                        <option value="deposit_balance" <?php selected(get_option('btr_default_payment_mode', 'full'), 'deposit_balance'); ?>><?php esc_html_e('Caparra + Saldo', 'born-to-ride-booking'); ?></option>
                    </select>
                    <p class="description"><?php esc_html_e('Usata quando il pagamento di gruppo non è disponibile (soglia non raggiunta).', 'born-to-ride-booking'); ?></p>
                </td>
            </tr>
        </table>
        
        <?php if ($stripe_available): ?>
        <h3><?php esc_html_e('Opzioni Stripe', 'born-to-ride-booking'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="btr_stripe_webhook_secret">
                        <?php esc_html_e('Webhook Secret (Opzionale)', 'born-to-ride-booking'); ?>
                    </label>
                </th>
                <td>
                    <input type="text" 
                           id="btr_stripe_webhook_secret" 
                           name="btr_stripe_webhook_secret" 
                           value="<?php echo esc_attr(get_option('btr_stripe_webhook_secret')); ?>" 
                           class="regular-text" />
                    <p class="description">
                        <?php esc_html_e('Solo se vuoi un webhook separato per BTR. Altrimenti viene usato quello di WooCommerce.', 'born-to-ride-booking'); ?><br>
                        <?php esc_html_e('Endpoint webhook BTR:', 'born-to-ride-booking'); ?> 
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
                               name="btr_stripe_save_payment_method" 
                               value="1" 
                               <?php checked(get_option('btr_stripe_save_payment_method', '1'), '1'); ?> />
                        <?php esc_html_e('Salva metodo di pagamento per saldo futuro', 'born-to-ride-booking'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('Permette di addebitare il saldo senza richiedere nuovamente i dati carta.', 'born-to-ride-booking'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php endif; ?>
        
        <?php if ($paypal_available && $paypal_config['supports_future_payments']): ?>
        <h3><?php esc_html_e('Opzioni PayPal', 'born-to-ride-booking'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <?php esc_html_e('Reference Transactions', 'born-to-ride-booking'); ?>
                </th>
                <td>
                    <p class="description" style="color: #46b450;">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e('Reference Transactions sono abilitate nel tuo account PayPal.', 'born-to-ride-booking'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php endif; ?>
        
        <h3><?php esc_html_e('Impostazioni Generali', 'born-to-ride-booking'); ?></h3>
        
        <table class="form-table">
            <tr>
                <th scope="row">
                    <?php esc_html_e('Modalità Debug', 'born-to-ride-booking'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="btr_gateway_debug_logging" 
                               value="1" 
                               <?php checked(get_option('btr_gateway_debug_logging'), '1'); ?> />
                        <?php esc_html_e('Abilita logging dettagliato transazioni', 'born-to-ride-booking'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('I log vengono salvati in:', 'born-to-ride-booking'); ?> 
                        <code>wp-content/debug.log</code>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <?php esc_html_e('Email notifiche', 'born-to-ride-booking'); ?>
                </th>
                <td>
                    <label>
                        <input type="checkbox" 
                               name="btr_gateway_deposit_paid_email" 
                               value="1" 
                               <?php checked(get_option('btr_gateway_deposit_paid_email', '1'), '1'); ?> />
                        <?php esc_html_e('Invia email quando caparra pagata', 'born-to-ride-booking'); ?>
                    </label><br>
                    <label>
                        <input type="checkbox" 
                               name="btr_gateway_balance_reminder_email" 
                               value="1" 
                               <?php checked(get_option('btr_gateway_balance_reminder_email', '1'), '1'); ?> />
                        <?php esc_html_e('Invia reminder automatici per saldo', 'born-to-ride-booking'); ?>
                    </label><br>
                    <label>
                        <input type="checkbox" 
                               name="btr_gateway_fully_paid_email" 
                               value="1" 
                               <?php checked(get_option('btr_gateway_fully_paid_email', '1'), '1'); ?> />
                        <?php esc_html_e('Invia conferma pagamento completo', 'born-to-ride-booking'); ?>
                    </label>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="btr_deposit_percentage_default">
                        <?php esc_html_e('Percentuale Caparra Default', 'born-to-ride-booking'); ?>
                    </label>
                </th>
                <td>
                    <input type="number" 
                           id="btr_deposit_percentage_default" 
                           name="btr_deposit_percentage_default" 
                           value="<?php echo esc_attr(get_option('btr_deposit_percentage_default', 30)); ?>" 
                           min="10" 
                           max="90" 
                           step="5" 
                           style="width: 80px;" />
                    <span>%</span>
                    <p class="description">
                        <?php esc_html_e('Percentuale di caparra richiesta di default (può essere modificata per singolo preventivo).', 'born-to-ride-booking'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <?php submit_button(__('Salva Impostazioni', 'born-to-ride-booking')); ?>
    </form>
    
    <hr>
    
    <!-- Sezione Diagnostica -->
    <h2><?php esc_html_e('Diagnostica Gateway', 'born-to-ride-booking'); ?></h2>
    
    <div class="btr-gateway-diagnostics">
        <p><?php esc_html_e('Verifica il corretto funzionamento dei gateway:', 'born-to-ride-booking'); ?></p>
        
        <p>
            <?php if ($stripe_available): ?>
            <button type="button" class="button" id="btr-test-stripe-connection">
                <?php esc_html_e('Test Connessione Stripe', 'born-to-ride-booking'); ?>
            </button>
            <?php endif; ?>
            
            <?php if ($paypal_available): ?>
            <button type="button" class="button" id="btr-test-paypal-connection">
                <?php esc_html_e('Test Connessione PayPal', 'born-to-ride-booking'); ?>
            </button>
            <?php endif; ?>
            
            <button type="button" class="button" id="btr-check-gateway-status">
                <?php esc_html_e('Verifica Stato Completo', 'born-to-ride-booking'); ?>
            </button>
        </p>
        
        <div id="btr-diagnostic-results" style="display:none;">
            <h3><?php esc_html_e('Risultati Diagnostica', 'born-to-ride-booking'); ?></h3>
            <pre id="btr-diagnostic-output" style="background: #f0f0f0; padding: 10px; max-height: 400px; overflow: auto;"></pre>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Test connessioni gateway
    $('.button[id^="btr-test-"]').on('click', function() {
        const $button = $(this);
        const gateway = $(this).attr('id').includes('stripe') ? 'stripe' : 'paypal';
        const $results = $('#btr-diagnostic-results');
        const $output = $('#btr-diagnostic-output');
        
        $button.prop('disabled', true);
        $output.text('Testing ' + gateway + '...');
        $results.show();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'btr_test_gateway_connection',
                gateway: gateway,
                nonce: '<?php echo wp_create_nonce('btr_test_gateway'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $output.html(formatTestResults(response.data));
                } else {
                    $output.text('Errore: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                $output.text('Errore richiesta: ' + error);
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
    
    // Verifica stato completo
    $('#btr-check-gateway-status').on('click', function() {
        const $button = $(this);
        const $results = $('#btr-diagnostic-results');
        const $output = $('#btr-diagnostic-output');
        
        $button.prop('disabled', true);
        $output.text('Verifica in corso...');
        $results.show();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'btr_check_gateway_status',
                nonce: '<?php echo wp_create_nonce('btr_test_gateway'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $output.html(formatStatusResults(response.data));
                } else {
                    $output.text('Errore: ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                $output.text('Errore richiesta: ' + error);
            },
            complete: function() {
                $button.prop('disabled', false);
            }
        });
    });
    
    // Formatta risultati test
    function formatTestResults(data) {
        let output = '';
        output += 'Gateway: ' + data.gateway + '\n';
        output += 'Stato: ' + (data.connected ? '✓ Connesso' : '✗ Non connesso') + '\n';
        output += 'Modalità: ' + (data.testmode ? 'TEST' : 'LIVE') + '\n';
        
        if (data.details) {
            output += '\nDettagli:\n';
            for (let key in data.details) {
                output += '  ' + key + ': ' + data.details[key] + '\n';
            }
        }
        
        if (data.errors && data.errors.length > 0) {
            output += '\nErrori:\n';
            data.errors.forEach(function(error) {
                output += '  - ' + error + '\n';
            });
        }
        
        return output;
    }
    
    // Formatta risultati stato
    function formatStatusResults(data) {
        let output = '=== STATO SISTEMA GATEWAY PAGAMENTI ===\n\n';
        
        // Plugin installati
        output += 'PLUGIN GATEWAY:\n';
        output += '  Stripe: ' + (data.plugins.stripe ? '✓ Installato' : '✗ Non installato') + '\n';
        output += '  PayPal: ' + (data.plugins.paypal ? '✓ Installato' : '✗ Non installato') + '\n\n';
        
        // Gateway configurati
        output += 'GATEWAY CONFIGURATI:\n';
        for (let gateway in data.gateways) {
            let config = data.gateways[gateway];
            output += '  ' + gateway.toUpperCase() + ':\n';
            output += '    Abilitato: ' + (config.enabled ? '✓' : '✗') + '\n';
            output += '    API configurate: ' + (config.api_configured ? '✓' : '✗') + '\n';
            if (config.testmode !== undefined) {
                output += '    Modalità: ' + (config.testmode ? 'TEST' : 'LIVE') + '\n';
            }
            if (config.supports_future_payments !== undefined) {
                output += '    Pagamenti futuri: ' + (config.supports_future_payments ? '✓' : '✗') + '\n';
            }
        }
        
        // Impostazioni BTR
        output += '\nIMPOSTAZIONI BTR:\n';
        output += '  Caparra default: ' + data.settings.deposit_percentage + '%\n';
        output += '  Email caparra: ' + (data.settings.deposit_email ? '✓' : '✗') + '\n';
        output += '  Email reminder: ' + (data.settings.reminder_email ? '✓' : '✗') + '\n';
        output += '  Email completo: ' + (data.settings.complete_email ? '✓' : '✗') + '\n';
        output += '  Debug logging: ' + (data.settings.debug_logging ? '✓' : '✗') + '\n';
        
        // Raccomandazioni
        if (data.recommendations && data.recommendations.length > 0) {
            output += '\nRACCOMANDAZIONI:\n';
            data.recommendations.forEach(function(rec) {
                output += '  ⚠️ ' + rec + '\n';
            });
        }
        
        return output;
    }
});
</script>

<style>
.btr-gateway-status {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.btr-gateway-status table {
    margin-top: 15px;
}

.btr-gateway-diagnostics {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    margin-top: 20px;
}

.form-table code {
    background: #f0f0f0;
    padding: 3px 5px;
    font-size: 12px;
}

#btr-diagnostic-output {
    font-family: 'Courier New', monospace;
    font-size: 13px;
    line-height: 1.5;
}

.dashicons {
    font-size: 20px;
    width: 20px;
    height: 20px;
    vertical-align: middle;
}
</style>
