<?php
/**
 * Shortcodes per il sistema di pagamento Born to Ride
 * 
 * Gestisce gli shortcodes per checkout deposito, riepilogo pagamenti gruppo
 * e conferma prenotazione.
 * 
 * @package BornToRideBooking
 * @since 1.0.98
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Payment_Shortcodes {
    
    /**
     * Instance singleton
     */
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Registra shortcodes
        add_action('init', [$this, 'register_shortcodes']);
        
        // Enqueue assets quando necessario
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }
    
    /**
     * Registra tutti gli shortcodes
     */
    public function register_shortcodes() {
        add_shortcode('btr_checkout_deposit', [$this, 'render_checkout_deposit']);
        add_shortcode('btr_group_payment_summary', [$this, 'render_group_payment_summary']);
        add_shortcode('btr_booking_confirmation', [$this, 'render_booking_confirmation']);
    }
    
    /**
     * Shortcode [btr_checkout_deposit] - Form checkout per pagamento caparra
     */
    public function render_checkout_deposit($atts) {
        // Attributi shortcode
        $atts = shortcode_atts([
            'percentage' => 30, // Percentuale caparra default
            'show_balance' => 'yes' // Mostra info sul saldo
        ], $atts, 'btr_checkout_deposit');
        
        // Verifica se abbiamo un preventivo in sessione
        $preventivo_id = WC()->session->get('btr_preventivo_id');
        if (!$preventivo_id) {
            return '<div class="btr-error">' . 
                   esc_html__('Nessun preventivo trovato. Torna al preventivo per procedere.', 'born-to-ride-booking') . 
                   '</div>';
        }
        
        // Recupera dati preventivo
        $preventivo = get_post($preventivo_id);
        if (!$preventivo || $preventivo->post_type !== 'preventivo') {
            return '<div class="btr-error">' . 
                   esc_html__('Preventivo non valido.', 'born-to-ride-booking') . 
                   '</div>';
        }
        
        // Recupera totale e calcola caparra
        $totale = get_post_meta($preventivo_id, '_prezzo_totale', true);
        if (!$totale || $totale <= 0) {
            return '<div class="btr-error">' . 
                   esc_html__('Importo preventivo non valido.', 'born-to-ride-booking') . 
                   '</div>';
        }
        
        $deposit_percentage = absint($atts['percentage']);
        $deposit_amount = $totale * ($deposit_percentage / 100);
        $balance_amount = $totale - $deposit_amount;
        
        // Recupera istanza deposit balance per generare link
        $deposit_balance = new BTR_Deposit_Balance();
        
        ob_start();
        ?>
        <div class="btr-checkout-deposit-wrapper">
            <h2><?php esc_html_e('Pagamento Caparra', 'born-to-ride-booking'); ?></h2>
            
            <div class="btr-deposit-summary">
                <h3><?php esc_html_e('Riepilogo Preventivo', 'born-to-ride-booking'); ?></h3>
                <table class="btr-summary-table">
                    <tr>
                        <td><?php esc_html_e('Numero Preventivo:', 'born-to-ride-booking'); ?></td>
                        <td><strong>#<?php echo esc_html($preventivo_id); ?></strong></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Data Preventivo:', 'born-to-ride-booking'); ?></td>
                        <td><?php echo esc_html(get_the_date('d/m/Y', $preventivo)); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Totale Viaggio:', 'born-to-ride-booking'); ?></td>
                        <td class="btr-price"><?php echo wc_price($totale); ?></td>
                    </tr>
                    <?php if ($atts['show_balance'] === 'yes'): ?>
                    <tr class="btr-deposit-row">
                        <td><?php printf(esc_html__('Caparra (%d%%):', 'born-to-ride-booking'), $deposit_percentage); ?></td>
                        <td class="btr-price btr-deposit"><?php echo wc_price($deposit_amount); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Saldo da pagare:', 'born-to-ride-booking'); ?></td>
                        <td class="btr-price"><?php echo wc_price($balance_amount); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            
            <div class="btr-deposit-info">
                <div class="btr-info-box">
                    <h4><?php esc_html_e('Come funziona il pagamento con caparra?', 'born-to-ride-booking'); ?></h4>
                    <ul>
                        <li><?php printf(esc_html__('Paga ora solo il %d%% del totale', 'born-to-ride-booking'), $deposit_percentage); ?></li>
                        <li><?php esc_html_e('Il saldo sarà richiesto successivamente', 'born-to-ride-booking'); ?></li>
                        <li><?php esc_html_e('Riceverai email di conferma e promemoria', 'born-to-ride-booking'); ?></li>
                    </ul>
                </div>
            </div>
            
            <div class="btr-deposit-actions">
                <?php if (is_checkout()): ?>
                    <p class="btr-checkout-active">
                        <?php esc_html_e('Sei già nella pagina di checkout. Procedi con il pagamento.', 'born-to-ride-booking'); ?>
                    </p>
                <?php else: ?>
                    <a href="<?php echo esc_url(wc_get_checkout_url()); ?>" class="button button-primary btr-deposit-button">
                        <?php printf(esc_html__('Procedi al pagamento della caparra (%s)', 'born-to-ride-booking'), wc_price($deposit_amount)); ?>
                    </a>
                    <a href="<?php echo esc_url(wc_get_checkout_url() . '?full_payment=1'); ?>" class="button button-secondary btr-full-button">
                        <?php esc_html_e('Preferisci pagare tutto subito?', 'born-to-ride-booking'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Shortcode [btr_group_payment_summary] - Riepilogo pagamenti gruppo
     */
    public function render_group_payment_summary($atts) {
        // Attributi shortcode
        $atts = shortcode_atts([
            'show_paid' => 'yes', // Mostra chi ha già pagato
            'show_pending' => 'yes' // Mostra chi deve ancora pagare
        ], $atts, 'btr_group_payment_summary');
        
        // Verifica hash nell'URL
        $hash = isset($_GET['payment_hash']) ? sanitize_text_field($_GET['payment_hash']) : '';
        if (empty($hash)) {
            return '<div class="btr-error">' . 
                   esc_html__('Link pagamento non valido.', 'born-to-ride-booking') . 
                   '</div>';
        }
        
        // Recupera piano pagamento dal hash
        global $wpdb;
        $table_name = $wpdb->prefix . 'btr_group_payments';
        
        // Verifica esistenza tabella - use prepared statement
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
            btr_debug_log('Tabella group_payments non trovata per shortcode');
            return '<div class="btr-error">' . 
                   esc_html__('Sistema pagamenti non configurato correttamente.', 'born-to-ride-booking') . 
                   '</div>';
        }
        
        $payment_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE payment_hash = %s AND status = 'active'",
            $hash
        ));
        
        if (!$payment_data) {
            return '<div class="btr-error">' . 
                   esc_html__('Pagamento non trovato o scaduto.', 'born-to-ride-booking') . 
                   '</div>';
        }
        
        // Decodifica dati partecipante
        $participant_data = json_decode($payment_data->participant_data, true);
        if (!$participant_data) {
            return '<div class="btr-error">' . 
                   esc_html__('Dati partecipante non validi.', 'born-to-ride-booking') . 
                   '</div>';
        }
        
        // Recupera preventivo
        $preventivo_id = $payment_data->preventivo_id;
        $preventivo = get_post($preventivo_id);
        if (!$preventivo) {
            return '<div class="btr-error">' . 
                   esc_html__('Preventivo non trovato.', 'born-to-ride-booking') . 
                   '</div>';
        }
        
        // Carica template se esiste
        $template_path = BTR_PLUGIN_DIR . 'templates/frontend/checkout-group-payment.php';
        if (file_exists($template_path)) {
            ob_start();
            include $template_path;
            return ob_get_clean();
        }
        
        // Fallback rendering
        ob_start();
        ?>
        <div class="btr-group-payment-summary">
            <h2><?php esc_html_e('Riepilogo Pagamento Gruppo', 'born-to-ride-booking'); ?></h2>
            
            <div class="btr-payment-details">
                <h3><?php esc_html_e('Dettagli Viaggio', 'born-to-ride-booking'); ?></h3>
                <p><strong><?php esc_html_e('Preventivo:', 'born-to-ride-booking'); ?></strong> #<?php echo esc_html($preventivo_id); ?></p>
                <p><strong><?php esc_html_e('Partecipante:', 'born-to-ride-booking'); ?></strong> 
                   <?php echo esc_html($participant_data['nome'] . ' ' . $participant_data['cognome']); ?></p>
                <p><strong><?php esc_html_e('Quota da pagare:', 'born-to-ride-booking'); ?></strong> 
                   <?php echo wc_price($payment_data->amount); ?></p>
            </div>
            
            <?php if ($payment_data->payment_status === 'pending'): ?>
            <div class="btr-payment-actions">
                <form method="post" action="<?php echo esc_url(wc_get_checkout_url()); ?>">
                    <input type="hidden" name="btr_group_payment_hash" value="<?php echo esc_attr($hash); ?>">
                    <input type="hidden" name="btr_group_payment_amount" value="<?php echo esc_attr($payment_data->amount); ?>">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Procedi al Pagamento', 'born-to-ride-booking'); ?>
                    </button>
                </form>
            </div>
            <?php else: ?>
            <div class="btr-payment-completed">
                <p class="btr-success">
                    <?php esc_html_e('✓ Pagamento già completato', 'born-to-ride-booking'); ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Shortcode [btr_booking_confirmation] - Conferma prenotazione completata
     */
    public function render_booking_confirmation($atts) {
        // Attributi shortcode
        $atts = shortcode_atts([
            'show_details' => 'yes', // Mostra dettagli ordine
            'show_next_steps' => 'yes' // Mostra prossimi passi
        ], $atts, 'btr_booking_confirmation');
        
        // Verifica se siamo in thank you page o abbiamo order ID
        $order_id = 0;
        
        // Check if we're on thank you page
        if (is_wc_endpoint_url('order-received')) {
            global $wp;
            $order_id = absint($wp->query_vars['order-received']);
        } elseif (isset($_GET['order_id'])) {
            $order_id = absint($_GET['order_id']);
        }
        
        if (!$order_id) {
            return '<div class="btr-error">' . 
                   esc_html__('Nessun ordine da confermare.', 'born-to-ride-booking') . 
                   '</div>';
        }
        
        // Recupera ordine
        $order = wc_get_order($order_id);
        if (!$order) {
            return '<div class="btr-error">' . 
                   esc_html__('Ordine non trovato.', 'born-to-ride-booking') . 
                   '</div>';
        }
        
        // Verifica permessi
        if (!current_user_can('manage_options') && $order->get_user_id() !== get_current_user_id()) {
            return '<div class="btr-error">' . 
                   esc_html__('Non hai i permessi per visualizzare questo ordine.', 'born-to-ride-booking') . 
                   '</div>';
        }
        
        // Recupera dati preventivo se presente
        $preventivo_id = $order->get_meta('_btr_preventivo_id');
        $payment_mode = $order->get_meta('_btr_payment_mode');
        
        // Carica template se esiste
        $template_path = BTR_PLUGIN_DIR . 'templates/frontend/payment-confirmation.php';
        if (file_exists($template_path)) {
            ob_start();
            // Passa variabili al template
            $template_args = [
                'order' => $order,
                'order_id' => $order_id,
                'preventivo_id' => $preventivo_id,
                'payment_mode' => $payment_mode,
                'atts' => $atts
            ];
            extract($template_args);
            include $template_path;
            return ob_get_clean();
        }
        
        // Fallback rendering
        ob_start();
        ?>
        <div class="btr-booking-confirmation">
            <div class="btr-confirmation-header">
                <div class="btr-success-icon">✓</div>
                <h2><?php esc_html_e('Prenotazione Confermata!', 'born-to-ride-booking'); ?></h2>
                <p class="btr-order-number">
                    <?php printf(esc_html__('Ordine #%s', 'born-to-ride-booking'), $order->get_order_number()); ?>
                </p>
            </div>
            
            <?php if ($atts['show_details'] === 'yes'): ?>
            <div class="btr-order-details">
                <h3><?php esc_html_e('Dettagli Ordine', 'born-to-ride-booking'); ?></h3>
                <table class="btr-details-table">
                    <tr>
                        <td><?php esc_html_e('Data Ordine:', 'born-to-ride-booking'); ?></td>
                        <td><?php echo esc_html($order->get_date_created()->format('d/m/Y H:i')); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Totale Pagato:', 'born-to-ride-booking'); ?></td>
                        <td><?php echo $order->get_formatted_order_total(); ?></td>
                    </tr>
                    <tr>
                        <td><?php esc_html_e('Metodo Pagamento:', 'born-to-ride-booking'); ?></td>
                        <td><?php echo esc_html($order->get_payment_method_title()); ?></td>
                    </tr>
                    <?php if ($preventivo_id): ?>
                    <tr>
                        <td><?php esc_html_e('Preventivo Riferimento:', 'born-to-ride-booking'); ?></td>
                        <td>#<?php echo esc_html($preventivo_id); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($payment_mode === 'deposit'): ?>
                    <tr>
                        <td><?php esc_html_e('Tipo Pagamento:', 'born-to-ride-booking'); ?></td>
                        <td class="btr-deposit-mode">
                            <?php esc_html_e('Caparra (Saldo da pagare)', 'born-to-ride-booking'); ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            <?php endif; ?>
            
            <?php if ($atts['show_next_steps'] === 'yes'): ?>
            <div class="btr-next-steps">
                <h3><?php esc_html_e('Prossimi Passi', 'born-to-ride-booking'); ?></h3>
                <ol>
                    <li><?php esc_html_e('Riceverai una email di conferma con tutti i dettagli', 'born-to-ride-booking'); ?></li>
                    <?php if ($payment_mode === 'deposit'): ?>
                    <li><?php esc_html_e('Ti contatteremo per il pagamento del saldo prima della partenza', 'born-to-ride-booking'); ?></li>
                    <?php endif; ?>
                    <li><?php esc_html_e('Conserva il numero ordine per future comunicazioni', 'born-to-ride-booking'); ?></li>
                </ol>
            </div>
            <?php endif; ?>
            
            <div class="btr-confirmation-actions">
                <a href="<?php echo esc_url(home_url()); ?>" class="button">
                    <?php esc_html_e('Torna alla Home', 'born-to-ride-booking'); ?>
                </a>
                <?php if (is_user_logged_in()): ?>
                <a href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>" class="button">
                    <?php esc_html_e('I Miei Ordini', 'born-to-ride-booking'); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Enqueue assets per gli shortcodes
     */
    public function enqueue_assets() {
        global $post;
        
        // Verifica se la pagina contiene uno dei nostri shortcodes
        if (!is_a($post, 'WP_Post')) {
            return;
        }
        
        $has_shortcode = has_shortcode($post->post_content, 'btr_checkout_deposit') ||
                        has_shortcode($post->post_content, 'btr_group_payment_summary') ||
                        has_shortcode($post->post_content, 'btr_booking_confirmation');
        
        if (!$has_shortcode) {
            return;
        }
        
        // Enqueue stili
        wp_enqueue_style(
            'btr-payment-shortcodes',
            BTR_PLUGIN_URL . 'assets/css/payment-shortcodes.css',
            ['woocommerce-general'],
            BTR_VERSION
        );
        
        // Inline styles per shortcodes
        $inline_css = '
            .btr-checkout-deposit-wrapper,
            .btr-group-payment-summary,
            .btr-booking-confirmation {
                max-width: 800px;
                margin: 30px auto;
                padding: 30px;
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            
            .btr-summary-table,
            .btr-details-table {
                width: 100%;
                margin: 20px 0;
                border-collapse: collapse;
            }
            
            .btr-summary-table td,
            .btr-details-table td {
                padding: 12px;
                border-bottom: 1px solid #eee;
            }
            
            .btr-summary-table tr:last-child td,
            .btr-details-table tr:last-child td {
                border-bottom: none;
            }
            
            .btr-price {
                font-weight: 600;
                color: #0097c5;
            }
            
            .btr-deposit {
                font-size: 1.2em;
                color: #28a745;
            }
            
            .btr-info-box {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 4px;
                margin: 20px 0;
                border-left: 4px solid #0097c5;
            }
            
            .btr-info-box h4 {
                margin-top: 0;
                color: #0097c5;
            }
            
            .btr-deposit-actions {
                text-align: center;
                margin-top: 30px;
            }
            
            .btr-deposit-button,
            .btr-full-button {
                display: inline-block;
                margin: 10px;
                padding: 15px 30px;
                font-size: 16px;
                text-decoration: none;
                border-radius: 4px;
                transition: all 0.3s;
            }
            
            .btr-deposit-button {
                background: #0097c5;
                color: #fff;
            }
            
            .btr-deposit-button:hover {
                background: #0086ad;
                color: #fff;
            }
            
            .btr-full-button {
                background: #6c757d;
                color: #fff;
            }
            
            .btr-full-button:hover {
                background: #5a6268;
                color: #fff;
            }
            
            .btr-confirmation-header {
                text-align: center;
                margin-bottom: 30px;
            }
            
            .btr-success-icon {
                font-size: 60px;
                color: #28a745;
                margin-bottom: 20px;
            }
            
            .btr-order-number {
                font-size: 20px;
                color: #666;
            }
            
            .btr-next-steps {
                background: #e3f2fd;
                padding: 20px;
                border-radius: 4px;
                margin: 20px 0;
            }
            
            .btr-next-steps ol {
                margin: 10px 0 0 20px;
            }
            
            .btr-next-steps li {
                margin: 10px 0;
            }
            
            .btr-error {
                background: #f8d7da;
                color: #721c24;
                padding: 15px;
                border-radius: 4px;
                border: 1px solid #f5c6cb;
                margin: 20px 0;
            }
            
            .btr-success {
                color: #28a745;
                font-size: 18px;
                font-weight: 600;
            }
            
            .btr-deposit-mode {
                color: #ff6b6b;
                font-weight: 600;
            }
            
            @media (max-width: 768px) {
                .btr-checkout-deposit-wrapper,
                .btr-group-payment-summary,
                .btr-booking-confirmation {
                    padding: 20px;
                    margin: 20px 10px;
                }
                
                .btr-deposit-button,
                .btr-full-button {
                    display: block;
                    width: 100%;
                    margin: 10px 0;
                }
            }
        ';
        
        wp_add_inline_style('btr-payment-shortcodes', $inline_css);
    }
}

// Inizializza
BTR_Payment_Shortcodes::get_instance();