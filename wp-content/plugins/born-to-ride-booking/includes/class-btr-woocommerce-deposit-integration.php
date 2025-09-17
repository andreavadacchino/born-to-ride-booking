<?php
/**
 * Integrazione WooCommerce per sistema caparra/saldo
 * 
 * Gestisce l'integrazione tra il sistema di caparra BTR e WooCommerce checkout
 * 
 * @package BornToRideBooking
 * @since 1.0.98
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_WooCommerce_Deposit_Integration {
    
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
        // Hook checkout process
        add_action('woocommerce_checkout_init', [$this, 'init_deposit_checkout']);
        add_filter('woocommerce_checkout_fields', [$this, 'add_deposit_fields'], 20);
        
        // Reset payment selection quando l'utente torna alla pagina di selezione
        add_action('wp', [$this, 'maybe_reset_payment_selection'], 5);
        
        // Modifica calcolo totali per caparra
        add_action('woocommerce_cart_calculate_fees', [$this, 'calculate_deposit_fee']);
        add_filter('woocommerce_cart_totals_order_total_html', [$this, 'modify_order_total_display'], 20);
        
        // Gestione creazione ordine
        add_action('woocommerce_checkout_create_order', [$this, 'save_deposit_meta'], 10, 2);
        add_action('woocommerce_checkout_order_processed', [$this, 'process_deposit_order'], 10, 3);
        
        // Hook per ordini organizzatore gruppo - v1.0.239
        add_filter('woocommerce_cart_item_price', [$this, 'modify_organizer_cart_item_price'], 10, 3);
        add_filter('woocommerce_checkout_cart_item_quantity', [$this, 'modify_organizer_cart_item_quantity'], 20, 3);
        add_action('woocommerce_before_checkout_form', [$this, 'display_organizer_notice'], 5);
        
        // Stati ordine custom
        add_action('init', [$this, 'register_deposit_order_statuses']);
        add_filter('wc_order_statuses', [$this, 'add_deposit_order_statuses']);
        
        // Email custom
        add_filter('woocommerce_email_classes', [$this, 'add_deposit_emails']);
        
        // Admin
        add_filter('woocommerce_admin_order_preview_get_order_details', [$this, 'add_deposit_info_to_preview'], 10, 2);
        add_action('woocommerce_admin_order_data_after_order_details', [$this, 'display_deposit_info_in_admin']);
        
        // Frontend
        add_action('woocommerce_order_details_after_order_table', [$this, 'display_deposit_info_frontend']);
        add_action('woocommerce_thankyou', [$this, 'display_deposit_thank_you'], 5);
        
        // AJAX handlers
        add_action('wp_ajax_btr_toggle_deposit_mode', [$this, 'ajax_toggle_deposit_mode']);
        add_action('wp_ajax_nopriv_btr_toggle_deposit_mode', [$this, 'ajax_toggle_deposit_mode']);
        
        // Script e stili
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }
    
    /**
     * Reset payment selection se l'utente torna alla pagina di selezione
     */
    public function maybe_reset_payment_selection() {
        // Verifica se siamo sulla pagina di selezione pagamento
        if (!$this->is_payment_selection_page()) {
            return;
        }
        
        // Se l'utente è sulla pagina di selezione, resetta la sessione per permettere nuova scelta
        if (function_exists('WC') && WC()->session) {
            // Log per debug
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('BTR Deposit Integration: Reset sessione pagamento - utente sulla pagina selezione');
            }
            
            // Pulisci dati di sessione del piano di pagamento precedente
            WC()->session->__unset('btr_payment_type');
            WC()->session->__unset('btr_payment_plan');
            WC()->session->__unset('btr_deposit_mode');
            WC()->session->__unset('btr_deposit_percentage');
            WC()->session->__unset('btr_deposit_amount');
            WC()->session->__unset('btr_balance_amount');
            WC()->session->__unset('btr_full_amount');
            
            // Mantieni solo l'ID del preventivo per riferimento
            // WC()->session->__unset('btr_preventivo_id'); // NON rimuovere questo
            
            // Svuota il carrello per permettere ripopolamento con nuova modalità
            if (WC()->cart && !WC()->cart->is_empty()) {
                WC()->cart->empty_cart();
            }
        }
    }
    
    /**
     * Verifica se siamo sulla pagina di selezione pagamento
     */
    private function is_payment_selection_page() {
        global $post;
        
        // Verifica URL
        if (isset($_SERVER['REQUEST_URI'])) {
            $uri = $_SERVER['REQUEST_URI'];
            if (strpos($uri, 'selezione-piano-pagamento') !== false || 
                strpos($uri, 'payment-selection') !== false) {
                return true;
            }
        }
        
        // Verifica post corrente
        if ($post && $post->post_type === 'page') {
            // Verifica slug
            if ($post->post_name === 'selezione-piano-pagamento' || 
                $post->post_name === 'payment-selection') {
                return true;
            }
            
            // Verifica contenuto shortcode
            if (has_shortcode($post->post_content, 'btr_payment_selection')) {
                return true;
            }
            
            // Verifica ID configurato
            $payment_selection_page_id = get_option('btr_payment_selection_page_id');
            if ($payment_selection_page_id && $post->ID == $payment_selection_page_id) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Inizializza checkout per caparra
     */
    public function init_deposit_checkout($checkout) {
        if ((is_admin() && !wp_doing_ajax()) || !function_exists('WC') || !WC()->session) {
            return;
        }
        // Verifica se siamo in modalità caparra
        $payment_type = WC()->session->get('btr_payment_type');
        $payment_plan = WC()->session->get('btr_payment_plan');
        
        if ($payment_type === 'deposit' || $payment_plan === 'deposit_balance') {
            // Attiva modalità caparra
            WC()->session->set('btr_deposit_mode', true);
            
            // Recupera percentuale caparra e popola carrello
            $preventivo_id = WC()->session->get('btr_preventivo_id');
            if ($preventivo_id) {
                $deposit_percentage = WC()->session->get('btr_deposit_percentage');
                if (!$deposit_percentage) {
                    $deposit_percentage = get_post_meta($preventivo_id, '_btr_deposit_percentage', true);
                    if (!$deposit_percentage) {
                        $deposit_percentage = 30; // Default
                    }
                }
                WC()->session->set('btr_deposit_percentage', $deposit_percentage);
                
                // Popola carrello se vuoto
                if (WC()->cart && WC()->cart->is_empty()) {
                    $this->populate_cart_from_preventivo($preventivo_id);
                }
            }
        }
    }
    
    /**
     * Aggiunge campi checkout per caparra
     */
    public function add_deposit_fields($fields) {
        if (!function_exists('WC') || !WC()->session || !WC()->cart) {
            return $fields;
        }
        $deposit_mode = WC()->session->get('btr_deposit_mode');
        
        if ($deposit_mode) {
            // Aggiungi campo nascosto per tracciare modalità caparra
            $fields['billing']['btr_payment_mode'] = [
                'type' => 'hidden',
                'default' => 'deposit',
                'class' => ['btr-deposit-field']
            ];
            
            // Aggiungi informazioni visibili sulla caparra
            $deposit_percentage = WC()->session->get('btr_deposit_percentage', 30);
            $cart_total = WC()->cart->get_total('raw');
            $deposit_amount = $cart_total * ($deposit_percentage / 100);
            $balance_amount = $cart_total - $deposit_amount;
            
            // Campo informativo (non input)
            $fields['billing']['btr_deposit_info'] = [
                'type' => 'info',
                'label' => __('Modalità Pagamento', 'born-to-ride-booking'),
                'description' => sprintf(
                    __('Stai pagando una caparra del %d%% (€%s). Il saldo di €%s sarà richiesto successivamente.', 'born-to-ride-booking'),
                    $deposit_percentage,
                    number_format($deposit_amount, 2, ',', '.'),
                    number_format($balance_amount, 2, ',', '.')
                ),
                'class' => ['btr-deposit-info', 'form-row-wide'],
                'priority' => 1
            ];
        }
        
        return $fields;
    }
    
    /**
     * Calcola fee negativa per ridurre totale a caparra
     */
    public function calculate_deposit_fee() {
        if ((is_admin() && !wp_doing_ajax()) || !function_exists('WC') || !WC()->session || !WC()->cart) {
            return;
        }
        if (!WC()->session->get('btr_deposit_mode')) {
            return;
        }
        
        $deposit_percentage = WC()->session->get('btr_deposit_percentage', 30);
        $cart_total = WC()->cart->get_subtotal() + WC()->cart->get_cart_contents_tax();
        
        // Aggiungi anche shipping e altre fees
        $shipping_total = WC()->cart->get_shipping_total() + WC()->cart->get_shipping_tax();
        $fees_total = 0;
        foreach (WC()->cart->get_fees() as $fee) {
            $fees_total += $fee->amount + $fee->tax;
        }
        
        $full_total = $cart_total + $shipping_total + $fees_total;
        $deposit_amount = $full_total * ($deposit_percentage / 100);
        $discount_amount = $full_total - $deposit_amount;
        
        // Rimuovi fee esistente se presente
        $fees = WC()->cart->get_fees();
        foreach ($fees as $key => $fee) {
            if ($fee->id === 'btr-deposit-adjustment') {
                unset($fees[$key]);
            }
        }
        
        // Aggiungi fee negativa per ridurre totale
        if ($discount_amount > 0) {
            WC()->cart->add_fee(
                sprintf(__('Saldo da pagare successivamente (-%d%%)', 'born-to-ride-booking'), 100 - $deposit_percentage),
                -$discount_amount,
                false,
                ''
            );
            
            // Salva importi in sessione per riferimento
            WC()->session->set('btr_deposit_amount', $deposit_amount);
            WC()->session->set('btr_balance_amount', $discount_amount);
            WC()->session->set('btr_full_amount', $full_total);
        }
    }
    
    /**
     * Modifica display totale ordine
     */
    public function modify_order_total_display($html) {
        if (!WC()->session->get('btr_deposit_mode')) {
            return $html;
        }
        
        $deposit_amount = WC()->session->get('btr_deposit_amount');
        $balance_amount = WC()->session->get('btr_balance_amount');
        $full_amount = WC()->session->get('btr_full_amount');
        
        if ($deposit_amount && $balance_amount) {
            $html = '<strong>' . wc_price($deposit_amount) . '</strong>';
            $html .= '<div class="btr-deposit-breakdown">';
            $html .= '<small class="btr-full-total">' . 
                     sprintf(__('Totale viaggio: %s', 'born-to-ride-booking'), wc_price($full_amount)) . 
                     '</small><br>';
            $html .= '<small class="btr-deposit-now">' . 
                     sprintf(__('Caparra ora: %s', 'born-to-ride-booking'), wc_price($deposit_amount)) . 
                     '</small><br>';
            $html .= '<small class="btr-balance-later">' . 
                     sprintf(__('Saldo successivo: %s', 'born-to-ride-booking'), wc_price($balance_amount)) . 
                     '</small>';
            $html .= '</div>';
        }
        
        return $html;
    }
    
    /**
     * Salva meta dati caparra nell'ordine
     */
    public function save_deposit_meta($order, $data) {
        // Gestisci prima ordini organizzatore
        if (WC()->session->get('btr_is_organizer_order')) {
            $this->save_organizer_meta($order, $data);
            return;
        }
        
        if (!WC()->session->get('btr_deposit_mode')) {
            return;
        }
        
        // Salva tutti i meta dati caparra
        $order->update_meta_data('_btr_payment_mode', 'deposit');
        $order->update_meta_data('_btr_deposit_percentage', WC()->session->get('btr_deposit_percentage'));
        $order->update_meta_data('_btr_deposit_amount', WC()->session->get('btr_deposit_amount'));
        $order->update_meta_data('_btr_balance_amount', WC()->session->get('btr_balance_amount'));
        $order->update_meta_data('_btr_full_amount', WC()->session->get('btr_full_amount'));
        
        // Collega al preventivo
        $preventivo_id = WC()->session->get('btr_preventivo_id');
        if ($preventivo_id) {
            $order->update_meta_data('_btr_preventivo_id', $preventivo_id);
            $order->update_meta_data('_btr_payment_plan_type', 'deposit_balance');
            
            // Salva stato pagamento nel preventivo
            update_post_meta($preventivo_id, '_btr_deposit_order_id', $order->get_id());
            update_post_meta($preventivo_id, '_btr_deposit_status', 'pending');
        }
        
        // Aggiungi nota all'ordine
        $order->add_order_note(sprintf(
            __('Ordine caparra %d%% di %s. Saldo di %s da pagare.', 'born-to-ride-booking'),
            WC()->session->get('btr_deposit_percentage'),
            wc_price(WC()->session->get('btr_full_amount')),
            wc_price(WC()->session->get('btr_balance_amount'))
        ));
    }
    
    /**
     * Processa ordine caparra
     */
    public function process_deposit_order($order_id, $posted_data, $order) {
        if ($order->get_meta('_btr_payment_mode') !== 'deposit') {
            return;
        }
        
        // Imposta stato ordine custom
        $order->set_status('wc-deposit-paid');
        $order->save();
        
        // Aggiorna preventivo
        $preventivo_id = $order->get_meta('_btr_preventivo_id');
        if ($preventivo_id) {
            update_post_meta($preventivo_id, '_btr_deposit_status', 'paid');
            update_post_meta($preventivo_id, '_btr_deposit_paid_date', current_time('mysql'));
            
            // Trigger per generare link saldo
            do_action('btr_deposit_paid', $preventivo_id, $order_id);
        }
        
        // Pulisci sessione
        WC()->session->__unset('btr_deposit_mode');
        WC()->session->__unset('btr_deposit_percentage');
        WC()->session->__unset('btr_deposit_amount');
        WC()->session->__unset('btr_balance_amount');
        WC()->session->__unset('btr_full_amount');
    }
    
    /**
     * Registra stati ordine custom
     */
    public function register_deposit_order_statuses() {
        // Stato: Caparra Pagata
        register_post_status('wc-deposit-paid', [
            'label' => __('Caparra Pagata', 'born-to-ride-booking'),
            'public' => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list' => true,
            'exclude_from_search' => false,
            'label_count' => _n_noop(
                'Caparra Pagata <span class="count">(%s)</span>',
                'Caparre Pagate <span class="count">(%s)</span>',
                'born-to-ride-booking'
            )
        ]);
        
        // Stato: In Attesa Saldo
        register_post_status('wc-awaiting-balance', [
            'label' => __('In Attesa Saldo', 'born-to-ride-booking'),
            'public' => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list' => true,
            'exclude_from_search' => false,
            'label_count' => _n_noop(
                'In Attesa Saldo <span class="count">(%s)</span>',
                'In Attesa Saldo <span class="count">(%s)</span>',
                'born-to-ride-booking'
            )
        ]);
        
        // Stato: In Attesa Pagamenti Gruppo - v1.0.239
        register_post_status('wc-btr-awaiting-group', [
            'label' => __('In Attesa Pagamenti Gruppo', 'born-to-ride-booking'),
            'public' => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list' => true,
            'exclude_from_search' => false,
            'label_count' => _n_noop(
                'In Attesa Gruppo <span class="count">(%s)</span>',
                'In Attesa Gruppo <span class="count">(%s)</span>',
                'born-to-ride-booking'
            )
        ]);
        
        // Stato: Pagamento Completo
        register_post_status('wc-fully-paid', [
            'label' => __('Pagamento Completo', 'born-to-ride-booking'),
            'public' => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list' => true,
            'exclude_from_search' => false,
            'label_count' => _n_noop(
                'Pagamento Completo <span class="count">(%s)</span>',
                'Pagamenti Completi <span class="count">(%s)</span>',
                'born-to-ride-booking'
            )
        ]);
    }
    
    /**
     * Aggiunge stati al dropdown WooCommerce
     */
    public function add_deposit_order_statuses($order_statuses) {
        $order_statuses['wc-deposit-paid'] = __('Caparra Pagata', 'born-to-ride-booking');
        $order_statuses['wc-awaiting-balance'] = __('In Attesa Saldo', 'born-to-ride-booking');
        $order_statuses['wc-fully-paid'] = __('Pagamento Completo', 'born-to-ride-booking');
        $order_statuses['wc-btr-awaiting-group'] = __('In Attesa Pagamenti Gruppo', 'born-to-ride-booking'); // v1.0.239
        
        return $order_statuses;
    }
    
    /**
     * Display info caparra in admin
     */
    public function display_deposit_info_in_admin($order) {
        $payment_mode = $order->get_meta('_btr_payment_mode');
        
        if ($payment_mode !== 'deposit') {
            return;
        }
        
        $deposit_percentage = $order->get_meta('_btr_deposit_percentage');
        $deposit_amount = $order->get_meta('_btr_deposit_amount');
        $balance_amount = $order->get_meta('_btr_balance_amount');
        $full_amount = $order->get_meta('_btr_full_amount');
        ?>
        <div class="btr-deposit-info-admin">
            <h3><?php esc_html_e('Informazioni Caparra', 'born-to-ride-booking'); ?></h3>
            <table class="btr-deposit-table">
                <tr>
                    <td><strong><?php esc_html_e('Modalità:', 'born-to-ride-booking'); ?></strong></td>
                    <td><?php esc_html_e('Caparra + Saldo', 'born-to-ride-booking'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Percentuale Caparra:', 'born-to-ride-booking'); ?></strong></td>
                    <td><?php echo esc_html($deposit_percentage); ?>%</td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Totale Viaggio:', 'born-to-ride-booking'); ?></strong></td>
                    <td><?php echo wc_price($full_amount); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Caparra Pagata:', 'born-to-ride-booking'); ?></strong></td>
                    <td><?php echo wc_price($deposit_amount); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Saldo Rimanente:', 'born-to-ride-booking'); ?></strong></td>
                    <td><?php echo wc_price($balance_amount); ?></td>
                </tr>
            </table>
            
            <?php
            // Mostra pulsante per generare link saldo se caparra pagata
            if ($order->has_status(['deposit-paid', 'processing', 'completed'])) {
                $preventivo_id = $order->get_meta('_btr_preventivo_id');
                $balance_order_id = get_post_meta($preventivo_id, '_btr_balance_order_id', true);
                
                if (!$balance_order_id && $preventivo_id) {
                    ?>
                    <div class="btr-generate-balance-link">
                        <button type="button" class="button button-primary" 
                                data-preventivo-id="<?php echo esc_attr($preventivo_id); ?>"
                                data-order-id="<?php echo esc_attr($order->get_id()); ?>"
                                id="btr-generate-balance-btn">
                            <?php esc_html_e('Genera Link Pagamento Saldo', 'born-to-ride-booking'); ?>
                        </button>
                    </div>
                    <?php
                }
            }
            ?>
        </div>
        
        <style>
        .btr-deposit-info-admin {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
        }
        
        .btr-deposit-info-admin h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #0097c5;
        }
        
        .btr-deposit-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .btr-deposit-table td {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        
        .btr-deposit-table tr:last-child td {
            border-bottom: none;
        }
        
        .btr-generate-balance-link {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }
        </style>
        <?php
    }
    
    /**
     * Display thank you message per caparra
     */
    public function display_deposit_thank_you($order_id) {
        $order = wc_get_order($order_id);
        if (!$order || $order->get_meta('_btr_payment_mode') !== 'deposit') {
            return;
        }
        
        $balance_amount = $order->get_meta('_btr_balance_amount');
        ?>
        <div class="btr-deposit-thank-you">
            <div class="btr-deposit-notice">
                <h2><?php esc_html_e('Caparra Confermata!', 'born-to-ride-booking'); ?></h2>
                <p><?php esc_html_e('La tua caparra è stata ricevuta con successo.', 'born-to-ride-booking'); ?></p>
                
                <div class="btr-balance-info">
                    <p><strong><?php esc_html_e('Importante:', 'born-to-ride-booking'); ?></strong></p>
                    <p><?php 
                        printf(
                            __('Il saldo di %s sarà richiesto successivamente. Riceverai un\'email con le istruzioni per il pagamento.', 'born-to-ride-booking'),
                            wc_price($balance_amount)
                        ); 
                    ?></p>
                </div>
            </div>
        </div>
        
        <style>
        .btr-deposit-thank-you {
            margin: 30px 0;
        }
        
        .btr-deposit-notice {
            background: #e3f2fd;
            border: 1px solid #0097c5;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
        }
        
        .btr-deposit-notice h2 {
            color: #0097c5;
            margin-bottom: 15px;
        }
        
        .btr-balance-info {
            background: #fff;
            padding: 20px;
            border-radius: 4px;
            margin-top: 20px;
            text-align: left;
        }
        
        .btr-balance-info p {
            margin: 10px 0;
        }
        </style>
        <?php
    }
    
    /**
     * Mostra informazioni deposito/gruppo nella pagina dettagli ordine frontend
     * 
     * @since 1.0.240
     */
    public function display_deposit_info_frontend($order) {
        // Per ordini organizzatore gruppo
        if ($order->get_meta('_btr_is_group_organizer') === 'yes') {
            $total_amount = $order->get_meta('_btr_total_amount');
            ?>
            <div class="btr-group-organizer-info">
                <h2><?php esc_html_e('Ordine Organizzatore Gruppo', 'born-to-ride-booking'); ?></h2>
                <p><?php esc_html_e('Questo è un ordine organizzatore. Il pagamento totale sarà completato dai singoli partecipanti.', 'born-to-ride-booking'); ?></p>
                <p><strong><?php esc_html_e('Importo Totale:', 'born-to-ride-booking'); ?></strong> €<?php echo number_format($total_amount, 2, ',', '.'); ?></p>
            </div>
            <?php
            return;
        }
        
        // Per ordini con caparra
        $payment_mode = $order->get_meta('_btr_payment_mode');
        if ($payment_mode !== 'deposit') {
            return;
        }
        
        $deposit_amount = $order->get_meta('_btr_deposit_amount');
        $balance_amount = $order->get_meta('_btr_balance_amount');
        $full_amount = $order->get_meta('_btr_full_amount');
        
        ?>
        <div class="btr-deposit-info">
            <h2><?php esc_html_e('Dettagli Pagamento Caparra', 'born-to-ride-booking'); ?></h2>
            <table class="woocommerce-table">
                <tr>
                    <th><?php esc_html_e('Importo Totale:', 'born-to-ride-booking'); ?></th>
                    <td>€<?php echo number_format($full_amount, 2, ',', '.'); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Caparra Pagata:', 'born-to-ride-booking'); ?></th>
                    <td>€<?php echo number_format($deposit_amount, 2, ',', '.'); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Saldo da Pagare:', 'born-to-ride-booking'); ?></th>
                    <td>€<?php echo number_format($balance_amount, 2, ',', '.'); ?></td>
                </tr>
            </table>
        </div>
        <style>
        .btr-group-organizer-info,
        .btr-deposit-info {
            margin: 30px 0;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
        }
        
        .btr-group-organizer-info h2,
        .btr-deposit-info h2 {
            color: #0097c5;
            margin-bottom: 15px;
        }
        
        .btr-deposit-info table {
            width: 100%;
            margin-top: 20px;
        }
        
        .btr-deposit-info th,
        .btr-deposit-info td {
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .btr-deposit-info th {
            text-align: left;
            font-weight: 600;
        }
        
        .btr-deposit-info td {
            text-align: right;
        }
        </style>
        <?php
    }
    
    /**
     * Aggiunge email custom per caparra
     */
    public function add_deposit_emails($email_classes) {
        // TODO: Implementare email custom per caparra se necessario
        // Per ora restituisce le classi email senza modifiche
        
        // Esempio di come aggiungere email custom:
        // $email_classes['WC_Email_Deposit_Paid'] = include 'emails/class-wc-email-deposit-paid.php';
        // $email_classes['WC_Email_Balance_Reminder'] = include 'emails/class-wc-email-balance-reminder.php';
        
        return $email_classes;
    }
    
    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        if (!is_checkout()) {
            return;
        }
        
        wp_enqueue_script(
            'btr-deposit-checkout',
            BTR_PLUGIN_URL . 'assets/js/deposit-checkout.js',
            ['jquery'],
            BTR_VERSION,
            true
        );
        
        wp_localize_script('btr-deposit-checkout', 'btr_deposit_checkout', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('btr_deposit_checkout'),
            'strings' => [
                'toggle_deposit' => __('Paga solo la caparra', 'born-to-ride-booking'),
                'toggle_full' => __('Paga importo completo', 'born-to-ride-booking'),
                'deposit_info' => __('Pagherai ora solo la caparra. Il saldo sarà richiesto successivamente.', 'born-to-ride-booking')
            ]
        ]);
        
        wp_add_inline_style('woocommerce-layout', '
            .btr-deposit-info {
                background: #e3f2fd;
                padding: 15px;
                border-radius: 4px;
                margin-bottom: 20px;
                border: 1px solid #0097c5;
            }
            
            .btr-deposit-info .description {
                margin: 0;
                color: #0c5460;
                font-weight: 500;
            }
            
            .btr-deposit-breakdown {
                margin-top: 10px;
                padding-top: 10px;
                border-top: 1px solid #e0e0e0;
            }
            
            .btr-deposit-breakdown small {
                display: block;
                margin: 5px 0;
                color: #666;
            }
            
            .btr-deposit-breakdown .btr-deposit-now {
                color: #0097c5;
                font-weight: 600;
            }
        ');
    }
    
    /**
     * AJAX toggle deposit mode
     */
    public function ajax_toggle_deposit_mode() {
        check_ajax_referer('btr_deposit_checkout', 'nonce');
        
        $enable = isset($_POST['enable']) ? filter_var($_POST['enable'], FILTER_VALIDATE_BOOLEAN) : false;
        
        if ($enable) {
            WC()->session->set('btr_deposit_mode', true);
        } else {
            WC()->session->__unset('btr_deposit_mode');
        }
        
        // Forza ricalcolo carrello
        WC()->cart->calculate_totals();
        
        wp_send_json_success([
            'deposit_mode' => WC()->session->get('btr_deposit_mode'),
            'cart_total' => WC()->cart->get_total(),
            'message' => $enable ? 
                __('Modalità caparra attivata', 'born-to-ride-booking') : 
                __('Modalità pagamento completo attivata', 'born-to-ride-booking')
        ]);
    }
    
    /**
     * Popola il carrello con i prodotti del preventivo
     */
    private function populate_cart_from_preventivo($preventivo_id) {
        // Log per debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BTR Deposit Integration: Popolamento carrello per preventivo ' . $preventivo_id);
        }
        
        // Recupera dati anagrafici
        $anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
        if (empty($anagrafici) || !is_array($anagrafici)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('BTR Deposit Integration: Nessun dato anagrafico trovato per preventivo ' . $preventivo_id);
            }
            return;
        }
        
        // Usa la classe esistente per popolare il carrello
        if (class_exists('BTR_Preventivo_To_Order')) {
            $converter = new BTR_Preventivo_To_Order();
            
            // Pulisci carrello esistente
            WC()->cart->empty_cart();
            BTR_Preventivo_To_Order::clear_detailed_cart_mode();
            
            // Popola con i dati del preventivo
            $detailed_mode = false;
            if (method_exists($converter, 'add_detailed_cart_items')) {
                $detailed_mode = (bool) $converter->add_detailed_cart_items($preventivo_id, $anagrafici);
            }

            if (!$detailed_mode && defined('WP_DEBUG') && WP_DEBUG) {
                error_log('BTR Deposit Integration: add_detailed_cart_items non ha aggiunto elementi (preventivo ' . $preventivo_id . ')');
            }
            
            // Log per debug
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $cart_count = count(WC()->cart->get_cart());
                error_log('BTR Deposit Integration: Carrello popolato con ' . $cart_count . ' prodotti');
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('BTR Deposit Integration: Classe BTR_Preventivo_To_Order non trovata');
            }
        }
    }
    
    /**
     * Modifica prezzo item carrello per ordini organizzatore
     * 
     * @since 1.0.239
     */
    public function modify_organizer_cart_item_price($price, $cart_item, $cart_item_key) {
        if (WC()->session->get('btr_is_organizer_order') && isset($cart_item['btr_order_type']) && $cart_item['btr_order_type'] === 'group_organizer') {
            return wc_price(0) . ' <small class="btr-organizer-note">(' . __('Pagamento gestito dai partecipanti', 'born-to-ride-booking') . ')</small>';
        }
        return $price;
    }
    
    /**
     * Modifica quantità visualizzata per ordini organizzatore
     * 
     * @since 1.0.239
     */
    public function modify_organizer_cart_item_quantity($quantity, $cart_item, $cart_item_key) {
        if (WC()->session->get('btr_is_organizer_order') && isset($cart_item['btr_order_type']) && $cart_item['btr_order_type'] === 'group_organizer') {
            $participants_info = WC()->session->get('btr_participants_info', []);
            $count = count($participants_info);
            return $quantity . ' <small class="btr-organizer-participants">(' . sprintf(_n('%d partecipante', '%d partecipanti', $count, 'born-to-ride-booking'), $count) . ')</small>';
        }
        return $quantity;
    }
    
    /**
     * Mostra avviso per ordini organizzatore
     * 
     * @since 1.0.239
     */
    public function display_organizer_notice() {
        if (!WC()->session->get('btr_is_organizer_order')) {
            return;
        }
        
        $total_amount = WC()->session->get('btr_total_amount', 0);
        $covered_amount = WC()->session->get('btr_covered_amount', 0);
        $participants_info = WC()->session->get('btr_participants_info', []);
        
        ?>
        <div class="woocommerce-info btr-organizer-checkout-notice">
            <h3><?php _e('Ordine Organizzatore Gruppo', 'born-to-ride-booking'); ?></h3>
            <p>
                <?php _e('Stai creando un ordine come organizzatore del gruppo. L\'ordine rimarrà in attesa fino al completamento dei pagamenti dei partecipanti.', 'born-to-ride-booking'); ?>
            </p>
            <div class="btr-payment-summary">
                <p><strong><?php _e('Riepilogo Pagamenti:', 'born-to-ride-booking'); ?></strong></p>
                <ul>
                    <li><?php echo sprintf(__('Totale viaggio: %s', 'born-to-ride-booking'), wc_price($total_amount)); ?></li>
                    <li><?php echo sprintf(__('Totale coperto dai partecipanti: %s', 'born-to-ride-booking'), wc_price($covered_amount)); ?></li>
                    <li><?php echo sprintf(__('Numero partecipanti: %d', 'born-to-ride-booking'), count($participants_info)); ?></li>
                </ul>
                
                <?php if (!empty($participants_info)): ?>
                <details style="margin-top: 1rem;">
                    <summary style="cursor: pointer; font-weight: bold;">
                        <?php _e('Dettagli partecipanti', 'born-to-ride-booking'); ?>
                    </summary>
                    <table style="margin-top: 0.5rem; width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr>
                                <th style="text-align: left; padding: 0.5rem; border-bottom: 1px solid #ddd;"><?php _e('Partecipante', 'born-to-ride-booking'); ?></th>
                                <th style="text-align: left; padding: 0.5rem; border-bottom: 1px solid #ddd;"><?php _e('Email', 'born-to-ride-booking'); ?></th>
                                <th style="text-align: right; padding: 0.5rem; border-bottom: 1px solid #ddd;"><?php _e('Importo', 'born-to-ride-booking'); ?></th>
                                <th style="text-align: center; padding: 0.5rem; border-bottom: 1px solid #ddd;"><?php _e('Stato', 'born-to-ride-booking'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($participants_info as $participant): ?>
                            <tr>
                                <td style="padding: 0.5rem; border-bottom: 1px solid #eee;"><?php echo esc_html($participant['nome']); ?></td>
                                <td style="padding: 0.5rem; border-bottom: 1px solid #eee;"><?php echo esc_html($participant['email']); ?></td>
                                <td style="padding: 0.5rem; border-bottom: 1px solid #eee; text-align: right;"><?php echo wc_price($participant['importo']); ?></td>
                                <td style="padding: 0.5rem; border-bottom: 1px solid #eee; text-align: center;">
                                    <?php if ($participant['stato'] === 'paid'): ?>
                                        <span style="color: #28a745;">✓ <?php _e('Pagato', 'born-to-ride-booking'); ?></span>
                                    <?php else: ?>
                                        <span style="color: #ffc107;">⏳ <?php _e('In attesa', 'born-to-ride-booking'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </details>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .btr-organizer-checkout-notice {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #f0f8ff;
            border-color: #0097c5;
        }
        
        .btr-organizer-checkout-notice h3 {
            margin-top: 0;
            color: #0097c5;
        }
        
        .btr-payment-summary ul {
            margin: 0.5rem 0;
            padding-left: 1.5rem;
        }
        
        .btr-payment-summary li {
            margin: 0.25rem 0;
        }
        
        .btr-organizer-note,
        .btr-organizer-participants {
            display: block;
            color: #666;
            font-style: italic;
        }
        </style>
        <?php
    }
    
    /**
     * Salva i meta dati per ordini organizzatore
     * 
     * Estende il metodo save_deposit_meta per gestire anche gli ordini organizzatore
     * 
     * @since 1.0.239
     */
    public function save_organizer_meta($order, $data) {
        if (WC()->session->get('btr_is_organizer_order')) {
            $preventivo_id = WC()->session->get('btr_preventivo_id', 0);
            
            $order->update_meta_data('_btr_order_type', 'group_organizer');
            $order->update_meta_data('_btr_total_amount', WC()->session->get('btr_total_amount', 0));
            $order->update_meta_data('_btr_covered_amount', WC()->session->get('btr_covered_amount', 0));
            $order->update_meta_data('_btr_participants_info', WC()->session->get('btr_participants_info', []));
            $order->update_meta_data('_btr_preventivo_id', $preventivo_id);
            $order->update_meta_data('_btr_is_group_organizer', 'yes');
            
            // Salva l'ordine per ottenere l'ID
            $order->save();
            
            // CRITICO: Collega l'ordine ai pagamenti del gruppo
            if ($preventivo_id && class_exists('BTR_Group_Payments')) {
                $group_payments = new BTR_Group_Payments();
                $linked = $group_payments->link_organizer_order_to_payments($order->get_id(), $preventivo_id);
                
                if ($linked > 0) {
                    $order->add_order_note(sprintf(
                        __('Ordine organizzatore collegato a %d pagamenti del gruppo.', 'born-to-ride-booking'),
                        $linked
                    ));
                    btr_debug_log('BTR Organizer Order: Collegati ' . $linked . ' pagamenti all\'ordine ' . $order->get_id());
                } else {
                    btr_debug_log('BTR Organizer Order Warning: Nessun pagamento collegato all\'ordine ' . $order->get_id());
                }
            }
            
            // Imposta stato personalizzato
            $order->set_status('wc-btr-awaiting-group', __('In attesa pagamenti gruppo', 'born-to-ride-booking'));
            
            // Pulisci sessione
            WC()->session->__unset('btr_is_organizer_order');
            WC()->session->__unset('btr_total_amount');
            WC()->session->__unset('btr_covered_amount');
            WC()->session->__unset('btr_participants_info');
            
            // Trigger evento
            do_action('btr_organizer_order_created', $order->get_id(), $preventivo_id);
        }
    }
}

// Inizializza
BTR_WooCommerce_Deposit_Integration::get_instance();
