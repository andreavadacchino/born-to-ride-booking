<?php
/**
 * Gestione rewrite rules per le pagine di pagamento
 * 
 * @package BornToRideBooking
 * @since 1.0.98
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Payment_Rewrite {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', [$this, 'add_rewrite_rules']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('template_redirect', [$this, 'handle_payment_pages']);
        
        // Flush rewrite rules on activation
        register_activation_hook(BTR_PLUGIN_FILE, [$this, 'flush_rewrite_rules']);
    }
    
    /**
     * Aggiunge rewrite rules
     */
    public function add_rewrite_rules() {
        // Pagina checkout gruppo
        add_rewrite_rule(
            '^pagamento-gruppo/([a-f0-9]{64})/?$',
            'index.php?btr_payment_page=group&payment_hash=$matches[1]',
            'top'
        );
        
        // Pagina conferma pagamento
        add_rewrite_rule(
            '^pagamento-confermato/([a-f0-9]{64})/?$',
            'index.php?btr_payment_page=confirmation&payment_hash=$matches[1]',
            'top'
        );
        
        // Pagina stato pagamento (per polling AJAX)
        add_rewrite_rule(
            '^stato-pagamento/([a-f0-9]{64})/?$',
            'index.php?btr_payment_page=status&payment_hash=$matches[1]',
            'top'
        );
    }
    
    /**
     * Aggiunge query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'btr_payment_page';
        $vars[] = 'payment_hash';
        return $vars;
    }
    
    /**
     * Gestisce template per pagine pagamento
     */
    public function handle_payment_pages() {
        $payment_page = get_query_var('btr_payment_page');
        $payment_hash = get_query_var('payment_hash');
        
        if (!$payment_page) {
            return;
        }
        
        // Verifica hash valido
        if (!btr_is_valid_payment_hash($payment_hash)) {
            wp_die(__('Link di pagamento non valido', 'born-to-ride-booking'));
        }
        
        switch ($payment_page) {
            case 'group':
                $this->render_group_payment_page($payment_hash);
                break;
                
            case 'confirmation':
                $this->render_confirmation_page($payment_hash);
                break;
                
            case 'status':
                $this->handle_status_check($payment_hash);
                break;
                
            default:
                wp_die(__('Pagina non trovata', 'born-to-ride-booking'));
        }
        
        exit;
    }
    
    /**
     * Render pagina checkout gruppo
     */
    private function render_group_payment_page($payment_hash) {
        // Imposta query var per template
        set_query_var('hash', $payment_hash);
        
        // Cerca template nel tema
        $template_paths = [
            get_stylesheet_directory() . '/born-to-ride-booking/checkout-group-payment.php',
            get_template_directory() . '/born-to-ride-booking/checkout-group-payment.php',
            BTR_PLUGIN_DIR . 'templates/frontend/checkout-group-payment.php'
        ];
        
        $template = '';
        foreach ($template_paths as $path) {
            if (file_exists($path)) {
                $template = $path;
                break;
            }
        }
        
        if (!$template) {
            wp_die(__('Template non trovato', 'born-to-ride-booking'));
        }
        
        // Carica template
        include $template;
    }
    
    /**
     * Render pagina conferma
     */
    private function render_confirmation_page($payment_hash) {
        global $wpdb;
        
        // Recupera dati pagamento
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}btr_group_payments WHERE payment_hash = %s",
            $payment_hash
        ));
        
        if (!$payment || $payment->payment_status !== 'paid') {
            wp_redirect(home_url());
            exit;
        }
        
        // Imposta query vars
        set_query_var('payment', $payment);
        
        // Cerca template
        $template_paths = [
            get_stylesheet_directory() . '/born-to-ride-booking/payment-confirmation.php',
            get_template_directory() . '/born-to-ride-booking/payment-confirmation.php',
            BTR_PLUGIN_DIR . 'templates/frontend/payment-confirmation.php'
        ];
        
        $template = '';
        foreach ($template_paths as $path) {
            if (file_exists($path)) {
                $template = $path;
                break;
            }
        }
        
        if (!$template) {
            // Usa template di default
            $this->render_default_confirmation($payment);
            return;
        }
        
        include $template;
    }
    
    /**
     * Gestisce check stato pagamento
     */
    private function handle_status_check($payment_hash) {
        global $wpdb;
        
        // Headers JSON
        header('Content-Type: application/json');
        
        // Recupera stato
        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT payment_status FROM {$wpdb->prefix}btr_group_payments WHERE payment_hash = %s",
            $payment_hash
        ));
        
        if (!$status) {
            wp_send_json_error(['message' => 'Payment not found']);
        }
        
        wp_send_json_success(['status' => $status]);
    }
    
    /**
     * Render conferma di default
     */
    private function render_default_confirmation($payment) {
        get_header();
        ?>
        <div class="btr-payment-confirmation">
            <div class="container">
                <div class="success-message">
                    <h1><?php esc_html_e('Pagamento Confermato!', 'born-to-ride-booking'); ?></h1>
                    <p><?php esc_html_e('Il tuo pagamento è stato ricevuto con successo.', 'born-to-ride-booking'); ?></p>
                    
                    <div class="payment-details">
                        <p><strong><?php esc_html_e('Importo pagato:', 'born-to-ride-booking'); ?></strong> 
                           <?php echo btr_format_price_i18n($payment->amount); ?></p>
                        
                        <?php if ($payment->wc_order_id): ?>
                            <?php $order = wc_get_order($payment->wc_order_id); ?>
                            <?php if ($order): ?>
                                <p><strong><?php esc_html_e('Numero ordine:', 'born-to-ride-booking'); ?></strong> 
                                   <?php echo esc_html($order->get_order_number()); ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <p><?php esc_html_e('Riceverai una email di conferma a breve.', 'born-to-ride-booking'); ?></p>
                    
                    <a href="<?php echo esc_url(home_url()); ?>" class="button">
                        <?php esc_html_e('Torna alla Home', 'born-to-ride-booking'); ?>
                    </a>
                </div>
            </div>
        </div>
        
        <style>
        .btr-payment-confirmation {
            padding: 60px 0;
            text-align: center;
        }
        .success-message {
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success-message h1 {
            color: #4caf50;
            margin-bottom: 20px;
        }
        .payment-details {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 4px;
            margin: 30px 0;
        }
        .payment-details p {
            margin: 10px 0;
        }
        </style>
        <?php
        get_footer();
    }
    
    /**
     * Flush rewrite rules
     */
    public function flush_rewrite_rules() {
        $this->add_rewrite_rules();
        flush_rewrite_rules();
    }
}

// Inizializza rewrite - DISABILITATO per conflitto con BTR_Group_Payments
// new BTR_Payment_Rewrite();