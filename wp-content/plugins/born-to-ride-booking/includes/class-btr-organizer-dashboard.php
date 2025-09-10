<?php
/**
 * Dashboard Frontend per Organizzatori Gruppo
 * 
 * Gestisce la visualizzazione e le funzionalità della dashboard
 * per gli organizzatori dei pagamenti di gruppo
 * 
 * @package BornToRideBooking
 * @since 1.0.240
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Organizer_Dashboard {
    
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
        // Hook WooCommerce My Account
        add_action('init', [$this, 'add_endpoints']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_filter('woocommerce_account_menu_items', [$this, 'add_menu_items']);
        add_action('woocommerce_account_group-payments_endpoint', [$this, 'display_dashboard']);
        
        // AJAX actions
        add_action('wp_ajax_btr_send_group_reminder', [$this, 'ajax_send_reminder']);
        add_action('wp_ajax_btr_export_group_payments', [$this, 'ajax_export_payments']);
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }
    
    /**
     * Aggiungi endpoint
     */
    public function add_endpoints() {
        add_rewrite_endpoint('group-payments', EP_ROOT | EP_PAGES);
        
        // Flush rewrite rules se necessario
        if (get_option('btr_flush_rewrite_rules')) {
            flush_rewrite_rules();
            delete_option('btr_flush_rewrite_rules');
        }
    }
    
    /**
     * Aggiungi query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'group-payments';
        $vars[] = 'payment-group';
        return $vars;
    }
    
    /**
     * Aggiungi voci menu account
     */
    public function add_menu_items($items) {
        // Aggiungi dopo gli ordini
        $new_items = [];
        foreach ($items as $key => $value) {
            $new_items[$key] = $value;
            if ($key === 'orders') {
                $new_items['group-payments'] = __('Pagamenti Gruppo', 'born-to-ride-booking');
            }
        }
        
        return $new_items;
    }
    
    /**
     * Mostra dashboard organizzatore
     */
    public function display_dashboard() {
        $current_user_id = get_current_user_id();
        
        if (!$current_user_id) {
            echo '<div class="woocommerce-message">' . __('Devi effettuare l\'accesso per vedere questa pagina.', 'born-to-ride-booking') . '</div>';
            return;
        }
        
        // Recupera ordini organizzatore dell'utente
        $organizer_orders = $this->get_user_organizer_orders($current_user_id);
        
        if (empty($organizer_orders)) {
            echo '<div class="woocommerce-info">' . __('Non hai ancora creato nessun pagamento di gruppo.', 'born-to-ride-booking') . '</div>';
            return;
        }
        
        // Se c'è un parametro payment-group, mostra dettaglio
        $payment_group_id = get_query_var('payment-group');
        
        if ($payment_group_id) {
            $this->display_payment_group_detail($payment_group_id);
        } else {
            $this->display_groups_list($organizer_orders);
        }
    }
    
    /**
     * Recupera ordini organizzatore dell'utente
     */
    private function get_user_organizer_orders($user_id) {
        $args = [
            'customer_id' => $user_id,
            'meta_key' => '_btr_is_group_organizer',
            'meta_value' => 'yes',
            'limit' => -1,
            'return' => 'objects'
        ];
        
        return wc_get_orders($args);
    }
    
    /**
     * Mostra lista gruppi
     */
    private function display_groups_list($orders) {
        ?>
        <div class="btr-organizer-dashboard">
            <h2><?php esc_html_e('I Tuoi Pagamenti di Gruppo', 'born-to-ride-booking'); ?></h2>
            
            <table class="woocommerce-orders-table woocommerce-MyAccount-orders shop_table shop_table_responsive my_account_orders account-orders-table">
                <thead>
                    <tr>
                        <th class="order-number"><span><?php esc_html_e('Ordine', 'born-to-ride-booking'); ?></span></th>
                        <th class="order-package"><span><?php esc_html_e('Pacchetto', 'born-to-ride-booking'); ?></span></th>
                        <th class="order-date"><span><?php esc_html_e('Data', 'born-to-ride-booking'); ?></span></th>
                        <th class="order-status"><span><?php esc_html_e('Stato', 'born-to-ride-booking'); ?></span></th>
                        <th class="order-progress"><span><?php esc_html_e('Progresso', 'born-to-ride-booking'); ?></span></th>
                        <th class="order-total"><span><?php esc_html_e('Totale', 'born-to-ride-booking'); ?></span></th>
                        <th class="order-actions"><span><?php esc_html_e('Azioni', 'born-to-ride-booking'); ?></span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): 
                        $preventivo_id = $order->get_meta('_btr_preventivo_id');
                        $package_id = get_post_meta($preventivo_id, '_pacchetto_id', true);
                        $package_title = get_the_title($package_id);
                        $stats = $this->get_payment_stats($preventivo_id);
                        $progress_percentage = $stats['completion_percentage'];
                    ?>
                    <tr>
                        <td class="order-number" data-title="<?php esc_attr_e('Ordine', 'born-to-ride-booking'); ?>">
                            <a href="<?php echo esc_url(wc_get_endpoint_url('view-order', $order->get_id())); ?>">
                                #<?php echo esc_html($order->get_order_number()); ?>
                            </a>
                        </td>
                        <td class="order-package" data-title="<?php esc_attr_e('Pacchetto', 'born-to-ride-booking'); ?>">
                            <?php echo esc_html($package_title); ?>
                        </td>
                        <td class="order-date" data-title="<?php esc_attr_e('Data', 'born-to-ride-booking'); ?>">
                            <time datetime="<?php echo esc_attr($order->get_date_created()->date('c')); ?>">
                                <?php echo esc_html(wc_format_datetime($order->get_date_created())); ?>
                            </time>
                        </td>
                        <td class="order-status" data-title="<?php esc_attr_e('Stato', 'born-to-ride-booking'); ?>">
                            <?php echo esc_html(wc_get_order_status_name($order->get_status())); ?>
                        </td>
                        <td class="order-progress" data-title="<?php esc_attr_e('Progresso', 'born-to-ride-booking'); ?>">
                            <div class="btr-progress-bar">
                                <div class="btr-progress-fill" style="width: <?php echo esc_attr($progress_percentage); ?>%;">
                                    <span><?php echo esc_html($progress_percentage); ?>%</span>
                                </div>
                            </div>
                            <small><?php echo esc_html(sprintf(
                                __('%d su %d pagati', 'born-to-ride-booking'),
                                $stats['paid_count'],
                                $stats['total_participants']
                            )); ?></small>
                        </td>
                        <td class="order-total" data-title="<?php esc_attr_e('Totale', 'born-to-ride-booking'); ?>">
                            <?php echo wc_price($order->get_meta('_btr_total_amount')); ?>
                        </td>
                        <td class="order-actions" data-title="<?php esc_attr_e('Azioni', 'born-to-ride-booking'); ?>">
                            <a href="<?php echo esc_url(add_query_arg('payment-group', $preventivo_id, wc_get_endpoint_url('group-payments'))); ?>" 
                               class="woocommerce-button button view">
                                <?php esc_html_e('Gestisci', 'born-to-ride-booking'); ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <style>
        .btr-progress-bar {
            background: #f0f0f0;
            border-radius: 10px;
            height: 20px;
            overflow: hidden;
            position: relative;
            margin: 5px 0;
        }
        .btr-progress-fill {
            background: #0097c5;
            height: 100%;
            border-radius: 10px;
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .btr-progress-fill span {
            color: #fff;
            font-size: 12px;
            font-weight: bold;
        }
        </style>
        <?php
    }
    
    /**
     * Mostra dettaglio gruppo pagamento
     */
    private function display_payment_group_detail($preventivo_id) {
        global $wpdb;
        
        // Verifica che l'utente sia l'organizzatore
        $organizer_order_id = get_post_meta($preventivo_id, '_btr_organizer_order_id', true);
        if (!$organizer_order_id) {
            echo '<div class="woocommerce-error">' . __('Gruppo di pagamento non trovato.', 'born-to-ride-booking') . '</div>';
            return;
        }
        
        $order = wc_get_order($organizer_order_id);
        if (!$order || $order->get_customer_id() != get_current_user_id()) {
            echo '<div class="woocommerce-error">' . __('Non hai i permessi per vedere questo gruppo.', 'born-to-ride-booking') . '</div>';
            return;
        }
        
        // Recupera dati
        $package_id = get_post_meta($preventivo_id, '_pacchetto_id', true);
        $package_title = get_the_title($package_id);
        $stats = $this->get_payment_stats($preventivo_id);
        
        // Recupera dettagli pagamenti
        $payments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}btr_group_payments 
            WHERE preventivo_id = %d 
            ORDER BY participant_index ASC",
            $preventivo_id
        ));
        
        ?>
        <div class="btr-payment-group-detail">
            <p class="btr-back-link">
                <a href="<?php echo esc_url(wc_get_endpoint_url('group-payments')); ?>">
                    &larr; <?php esc_html_e('Torna alla lista', 'born-to-ride-booking'); ?>
                </a>
            </p>
            
            <h2><?php echo esc_html($package_title); ?></h2>
            
            <!-- Riepilogo -->
            <div class="btr-payment-summary">
                <h3><?php esc_html_e('Riepilogo Pagamenti', 'born-to-ride-booking'); ?></h3>
                
                <div class="btr-summary-grid">
                    <div class="btr-summary-item">
                        <span class="label"><?php esc_html_e('Partecipanti Totali:', 'born-to-ride-booking'); ?></span>
                        <span class="value"><?php echo esc_html($stats['total_participants']); ?></span>
                    </div>
                    <div class="btr-summary-item">
                        <span class="label"><?php esc_html_e('Hanno Pagato:', 'born-to-ride-booking'); ?></span>
                        <span class="value success"><?php echo esc_html($stats['paid_count']); ?></span>
                    </div>
                    <div class="btr-summary-item">
                        <span class="label"><?php esc_html_e('In Attesa:', 'born-to-ride-booking'); ?></span>
                        <span class="value warning"><?php echo esc_html($stats['pending_count']); ?></span>
                    </div>
                    <div class="btr-summary-item">
                        <span class="label"><?php esc_html_e('Totale Raccolto:', 'born-to-ride-booking'); ?></span>
                        <span class="value"><?php echo wc_price($stats['total_paid']); ?></span>
                    </div>
                    <div class="btr-summary-item">
                        <span class="label"><?php esc_html_e('Ancora da Raccogliere:', 'born-to-ride-booking'); ?></span>
                        <span class="value"><?php echo wc_price($stats['total_pending']); ?></span>
                    </div>
                    <div class="btr-summary-item">
                        <span class="label"><?php esc_html_e('Completamento:', 'born-to-ride-booking'); ?></span>
                        <span class="value"><?php echo esc_html($stats['completion_percentage']); ?>%</span>
                    </div>
                </div>
                
                <div class="btr-progress-bar large">
                    <div class="btr-progress-fill" style="width: <?php echo esc_attr($stats['completion_percentage']); ?>%;">
                        <span><?php echo esc_html($stats['completion_percentage']); ?>%</span>
                    </div>
                </div>
            </div>
            
            <!-- Dettagli Partecipanti -->
            <div class="btr-participants-detail">
                <h3><?php esc_html_e('Dettaglio Partecipanti', 'born-to-ride-booking'); ?></h3>
                
                <table class="shop_table shop_table_responsive">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Partecipante', 'born-to-ride-booking'); ?></th>
                            <th><?php esc_html_e('Email', 'born-to-ride-booking'); ?></th>
                            <th><?php esc_html_e('Importo', 'born-to-ride-booking'); ?></th>
                            <th><?php esc_html_e('Stato', 'born-to-ride-booking'); ?></th>
                            <th><?php esc_html_e('Data Pagamento', 'born-to-ride-booking'); ?></th>
                            <th><?php esc_html_e('Azioni', 'born-to-ride-booking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                        <tr class="payment-status-<?php echo esc_attr($payment->payment_status); ?>">
                            <td data-title="<?php esc_attr_e('Partecipante', 'born-to-ride-booking'); ?>">
                                <?php echo esc_html($payment->participant_name); ?>
                            </td>
                            <td data-title="<?php esc_attr_e('Email', 'born-to-ride-booking'); ?>">
                                <?php echo esc_html($payment->participant_email); ?>
                            </td>
                            <td data-title="<?php esc_attr_e('Importo', 'born-to-ride-booking'); ?>">
                                <?php echo wc_price($payment->amount); ?>
                            </td>
                            <td data-title="<?php esc_attr_e('Stato', 'born-to-ride-booking'); ?>">
                                <?php 
                                $status_labels = [
                                    'pending' => __('In Attesa', 'born-to-ride-booking'),
                                    'paid' => __('Pagato', 'born-to-ride-booking'),
                                    'failed' => __('Fallito', 'born-to-ride-booking'),
                                    'expired' => __('Scaduto', 'born-to-ride-booking')
                                ];
                                $status_class = [
                                    'pending' => 'warning',
                                    'paid' => 'success',
                                    'failed' => 'error',
                                    'expired' => 'error'
                                ];
                                ?>
                                <span class="payment-status <?php echo esc_attr($status_class[$payment->payment_status] ?? ''); ?>">
                                    <?php echo esc_html($status_labels[$payment->payment_status] ?? $payment->payment_status); ?>
                                </span>
                            </td>
                            <td data-title="<?php esc_attr_e('Data Pagamento', 'born-to-ride-booking'); ?>">
                                <?php 
                                if ($payment->paid_at && $payment->paid_at !== '0000-00-00 00:00:00') {
                                    echo esc_html(wp_date(get_option('date_format'), strtotime($payment->paid_at)));
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td data-title="<?php esc_attr_e('Azioni', 'born-to-ride-booking'); ?>">
                                <?php if ($payment->payment_status === 'pending'): ?>
                                    <button class="button btn-send-reminder" 
                                            data-payment-id="<?php echo esc_attr($payment->payment_id); ?>"
                                            data-email="<?php echo esc_attr($payment->participant_email); ?>"
                                            data-name="<?php echo esc_attr($payment->participant_name); ?>">
                                        <?php esc_html_e('Invia Promemoria', 'born-to-ride-booking'); ?>
                                    </button>
                                <?php elseif ($payment->payment_status === 'paid' && $payment->wc_order_id): ?>
                                    <a href="<?php echo esc_url(wc_get_endpoint_url('view-order', $payment->wc_order_id)); ?>" 
                                       class="button">
                                        <?php esc_html_e('Vedi Ordine', 'born-to-ride-booking'); ?>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Azioni -->
            <div class="btr-group-actions">
                <button class="button" id="btn-export-payments" data-preventivo-id="<?php echo esc_attr($preventivo_id); ?>">
                    <?php esc_html_e('Esporta CSV', 'born-to-ride-booking'); ?>
                </button>
                
                <?php if ($stats['pending_count'] > 0): ?>
                <button class="button" id="btn-send-all-reminders" data-preventivo-id="<?php echo esc_attr($preventivo_id); ?>">
                    <?php esc_html_e('Invia Promemoria a Tutti', 'born-to-ride-booking'); ?>
                </button>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .btr-payment-group-detail {
            margin-bottom: 30px;
        }
        .btr-back-link {
            margin-bottom: 20px;
        }
        .btr-payment-summary {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        .btr-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .btr-summary-item {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            background: white;
            border-radius: 3px;
        }
        .btr-summary-item .label {
            color: #666;
        }
        .btr-summary-item .value {
            font-weight: bold;
        }
        .btr-summary-item .value.success {
            color: #28a745;
        }
        .btr-summary-item .value.warning {
            color: #ffc107;
        }
        .btr-progress-bar.large {
            height: 30px;
        }
        .payment-status {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .payment-status.success {
            background: #d4edda;
            color: #155724;
        }
        .payment-status.warning {
            background: #fff3cd;
            color: #856404;
        }
        .payment-status.error {
            background: #f8d7da;
            color: #721c24;
        }
        .btr-group-actions {
            margin-top: 30px;
            display: flex;
            gap: 10px;
        }
        tr.payment-status-paid {
            background-color: #f0f8ff;
        }
        </style>
        <?php
    }
    
    /**
     * Recupera statistiche pagamenti
     */
    private function get_payment_stats($preventivo_id) {
        global $wpdb;
        
        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_participants,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as total_paid,
                SUM(amount) as total_expected
            FROM {$wpdb->prefix}btr_group_payments
            WHERE preventivo_id = %d
        ", $preventivo_id));
        
        return [
            'total_participants' => intval($stats->total_participants),
            'paid_count' => intval($stats->paid_count),
            'pending_count' => intval($stats->pending_count),
            'total_paid' => floatval($stats->total_paid),
            'total_expected' => floatval($stats->total_expected),
            'total_pending' => floatval($stats->total_expected - $stats->total_paid),
            'completion_percentage' => $stats->total_expected > 0 
                ? round(($stats->total_paid / $stats->total_expected) * 100) 
                : 0
        ];
    }
    
    /**
     * AJAX: Invia promemoria
     */
    public function ajax_send_reminder() {
        // Verifica nonce
        if (!check_ajax_referer('btr_organizer_dashboard', 'nonce', false)) {
            wp_send_json_error(['message' => __('Sessione scaduta.', 'born-to-ride-booking')]);
        }
        
        $payment_id = intval($_POST['payment_id'] ?? 0);
        if (!$payment_id) {
            wp_send_json_error(['message' => __('ID pagamento non valido.', 'born-to-ride-booking')]);
        }
        
        // Verifica che l'utente sia l'organizzatore
        // ... (implementazione verifica permessi)
        
        // Invia email
        if (class_exists('BTR_Group_Payments')) {
            $group_payments = new BTR_Group_Payments();
            $result = $group_payments->send_payment_link_email($payment_id);
            
            if ($result) {
                wp_send_json_success(['message' => __('Promemoria inviato con successo.', 'born-to-ride-booking')]);
            }
        }
        
        wp_send_json_error(['message' => __('Errore nell\'invio del promemoria.', 'born-to-ride-booking')]);
    }
    
    /**
     * AJAX: Esporta pagamenti
     */
    public function ajax_export_payments() {
        // Verifica nonce
        if (!check_ajax_referer('btr_organizer_dashboard', 'nonce', false)) {
            wp_send_json_error(['message' => __('Sessione scaduta.', 'born-to-ride-booking')]);
        }
        
        $preventivo_id = intval($_POST['preventivo_id'] ?? 0);
        if (!$preventivo_id) {
            wp_send_json_error(['message' => __('ID preventivo non valido.', 'born-to-ride-booking')]);
        }
        
        global $wpdb;
        
        // Recupera pagamenti
        $payments = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}btr_group_payments 
            WHERE preventivo_id = %d 
            ORDER BY participant_index ASC",
            $preventivo_id
        ));
        
        // Genera CSV
        $csv_data = [];
        $csv_data[] = ['Partecipante', 'Email', 'Importo', 'Stato', 'Data Pagamento'];
        
        foreach ($payments as $payment) {
            $csv_data[] = [
                $payment->participant_name,
                $payment->participant_email,
                number_format($payment->amount, 2, ',', '.'),
                $payment->payment_status,
                $payment->paid_at && $payment->paid_at !== '0000-00-00 00:00:00' 
                    ? wp_date('d/m/Y', strtotime($payment->paid_at)) 
                    : ''
            ];
        }
        
        // Converti in CSV string
        $csv_content = '';
        foreach ($csv_data as $row) {
            $csv_content .= implode(';', array_map('esc_attr', $row)) . "\n";
        }
        
        wp_send_json_success([
            'filename' => 'pagamenti-gruppo-' . $preventivo_id . '-' . date('Y-m-d') . '.csv',
            'content' => $csv_content
        ]);
    }
    
    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        if (!is_account_page()) {
            return;
        }
        
        global $wp;
        
        if (isset($wp->query_vars['group-payments'])) {
            wp_enqueue_script(
                'btr-organizer-dashboard',
                BTR_PLUGIN_URL . 'assets/js/organizer-dashboard.js',
                ['jquery'],
                BTR_VERSION,
                true
            );
            
            wp_localize_script('btr-organizer-dashboard', 'btr_organizer', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('btr_organizer_dashboard'),
                'i18n' => [
                    'confirm_reminder' => __('Inviare un promemoria a %s?', 'born-to-ride-booking'),
                    'confirm_all_reminders' => __('Inviare un promemoria a tutti i partecipanti in attesa?', 'born-to-ride-booking'),
                    'sending' => __('Invio in corso...', 'born-to-ride-booking'),
                    'error' => __('Si è verificato un errore. Riprova.', 'born-to-ride-booking')
                ]
            ]);
        }
    }
}

// Inizializza
BTR_Organizer_Dashboard::get_instance();