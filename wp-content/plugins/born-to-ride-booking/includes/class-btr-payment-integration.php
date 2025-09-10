<?php
/**
 * Integrazione sistema pagamenti con flusso esistente
 * 
 * @package BornToRideBooking
 * @since 1.0.98
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Payment_Integration {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Hook dopo completamento anagrafici
        add_action('btr_after_anagrafici_saved', [$this, 'maybe_show_payment_selection'], 10, 2);
        
        // Modifica processo checkout standard
        add_filter('btr_checkout_redirect_url', [$this, 'modify_checkout_redirect'], 10, 2);
        
        // Hook pagamento completato
        add_action('woocommerce_order_status_completed', [$this, 'handle_payment_completed']);
        add_action('woocommerce_order_status_processing', [$this, 'handle_payment_completed']);
        
        // Aggiunge info pagamento gruppo all'ordine
        add_action('woocommerce_checkout_order_processed', [$this, 'link_order_to_payment'], 10, 3);
        
        // Modifica email WooCommerce
        add_filter('woocommerce_email_recipient_new_order', [$this, 'modify_email_recipients'], 10, 2);
        
        // Aggiunge info al preventivo
        add_action('add_meta_boxes', [$this, 'add_payment_info_metabox']);
        
        // Script frontend
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }
    
    /**
     * Mostra selezione modalità pagamento dopo anagrafici
     */
    public function maybe_show_payment_selection($preventivo_id, $anagrafici_data) {
        // NOTA: Questo metodo non dovrebbe più outputtare JavaScript direttamente
        // perché viene chiamato nel contesto di una richiesta AJAX o durante
        // il processo di redirect. La logica è stata spostata in convert_to_checkout
        // e enqueue_scripts per gestire correttamente il modal.
        
        // Manteniamo il metodo vuoto per compatibilità con eventuali altri hook
        // che potrebbero chiamarlo, ma non fa nulla.
        return;
    }
    
    /**
     * Modifica redirect checkout basato su piano pagamento
     */
    public function modify_checkout_redirect($redirect_url, $preventivo_id) {
        // Se siamo già nel processo di selezione pagamento, non modificare
        if (isset($_GET['from_payment_selection'])) {
            return $redirect_url;
        }
        
        // Verifica se esiste piano pagamento
        if (class_exists('BTR_Payment_Plans')) {
            $payment_plan = BTR_Payment_Plans::get_payment_plan($preventivo_id);
            
            if (!$payment_plan) {
                // Se non esiste piano, redirect alla selezione pagamento
                $payment_selection_page = $this->get_payment_selection_page();
                if ($payment_selection_page) {
                    return add_query_arg('preventivo_id', $preventivo_id, get_permalink($payment_selection_page));
                }
            } else {
                // Se è gruppo con link individuali, redirect a pagina riepilogo
                if ($payment_plan->plan_type === 'group_split') {
                    $summary_page_id = get_option('btr_group_payment_summary_page');
                    if ($summary_page_id) {
                        return get_permalink($summary_page_id) . '?preventivo=' . $preventivo_id;
                    }
                }
                
                // Se è deposit+balance, modifica checkout per mostrare solo caparra
                if ($payment_plan->plan_type === 'deposit_balance') {
                    return add_query_arg('payment_type', 'deposit', $redirect_url);
                }
            }
        }
        
        return $redirect_url;
    }
    
    /**
     * Trova la pagina di selezione pagamento
     */
    private function get_payment_selection_page() {
        // Prima cerca per slug
        $page = get_page_by_path('selezione-piano-pagamento');
        if (!$page) {
            $page = get_page_by_path('payment-selection');
        }
        
        // Poi cerca per shortcode
        if (!$page) {
            global $wpdb;
            $page_id = $wpdb->get_var(
                "SELECT ID FROM {$wpdb->posts} 
                WHERE post_type = 'page' 
                AND post_status = 'publish' 
                AND post_content LIKE '%[btr_payment_selection]%' 
                LIMIT 1"
            );
            if ($page_id) {
                $page = get_post($page_id);
            }
        }
        
        return $page ? $page->ID : null;
    }
    
    /**
     * Gestisce pagamento completato
     */
    public function handle_payment_completed($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Verifica se è un pagamento gruppo
        $payment_id = $order->get_meta('_btr_payment_id');
        if (!$payment_id) {
            return;
        }
        
        global $wpdb;
        
        // Aggiorna stato pagamento
        $updated = $wpdb->update(
            $wpdb->prefix . 'btr_group_payments',
            [
                'payment_status' => 'paid',
                'paid_at' => current_time('mysql'),
                'wc_order_id' => $order_id
            ],
            ['payment_id' => $payment_id],
            ['%s', '%s', '%d'],
            ['%d']
        );
        
        if ($updated) {
            // Trigger evento
            do_action('btr_payment_completed', $payment_id, $order_id);
            
            // Invia email conferma personalizzata
            $email_manager = BTR_Payment_Email_Manager::get_instance();
            $email_manager->send_payment_confirmation($payment_id, $order_id);
            
            // Verifica se tutti i pagamenti del gruppo sono completati
            $this->check_group_completion($payment_id);
        }
    }
    
    /**
     * Verifica completamento gruppo
     */
    private function check_group_completion($payment_id) {
        global $wpdb;
        
        // Recupera info pagamento
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}btr_group_payments WHERE payment_id = %d",
            $payment_id
        ));
        
        if (!$payment || $payment->payment_plan_type !== 'group_split') {
            return;
        }
        
        // Conta pagamenti gruppo
        $total_payments = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}btr_group_payments 
             WHERE preventivo_id = %d AND payment_plan_type = 'group_split'",
            $payment->preventivo_id
        ));
        
        $paid_payments = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}btr_group_payments 
             WHERE preventivo_id = %d AND payment_plan_type = 'group_split' AND payment_status = 'paid'",
            $payment->preventivo_id
        ));
        
        // Se tutti pagati, trigger evento
        if ($total_payments > 0 && $total_payments === $paid_payments) {
            do_action('btr_group_payment_completed', $payment->preventivo_id);
            
            // Aggiorna stato preventivo
            update_post_meta($payment->preventivo_id, '_stato_pagamento', 'completato');
            update_post_meta($payment->preventivo_id, '_data_completamento_pagamento', current_time('mysql'));
        }
    }
    
    /**
     * Collega ordine a pagamento durante checkout
     */
    public function link_order_to_payment($order_id, $posted_data, $order) {
        // Verifica se è checkout gruppo
        if (isset($_POST['payment_hash'])) {
            $payment_hash = sanitize_text_field($_POST['payment_hash']);
            
            global $wpdb;
            $payment = $wpdb->get_row($wpdb->prepare(
                "SELECT payment_id FROM {$wpdb->prefix}btr_group_payments WHERE payment_hash = %s",
                $payment_hash
            ));
            
            if ($payment) {
                $order->update_meta_data('_btr_payment_id', $payment->payment_id);
                $order->save();
            }
        }
    }
    
    /**
     * Modifica destinatari email ordine
     */
    public function modify_email_recipients($recipient, $order) {
        // Verifica che l'ordine esista (NULL quando si visualizza la pagina settings)
        if (!$order || !is_a($order, 'WC_Order')) {
            return $recipient;
        }
        
        // Se è pagamento gruppo, aggiungi organizzatore
        $payment_id = $order->get_meta('_btr_payment_id');
        if (!$payment_id) {
            return $recipient;
        }
        
        global $wpdb;
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT preventivo_id FROM {$wpdb->prefix}btr_group_payments WHERE payment_id = %d",
            $payment_id
        ));
        
        if ($payment) {
            $anagrafici = get_post_meta($payment->preventivo_id, '_anagrafici_preventivo', true);
            if (!empty($anagrafici[0]['email'])) {
                $recipient .= ',' . $anagrafici[0]['email'];
            }
        }
        
        return $recipient;
    }
    
    /**
     * Aggiunge metabox info pagamento
     */
    public function add_payment_info_metabox() {
        add_meta_box(
            'btr_payment_plan_info',
            __('Piano di Pagamento', 'born-to-ride-booking'),
            [$this, 'render_payment_info_metabox'],
            'preventivi',
            'side',
            'high'
        );
    }
    
    /**
     * Render metabox info pagamento
     */
    public function render_payment_info_metabox($post) {
        $payment_plan = BTR_Payment_Plans::get_payment_plan($post->ID);
        
        if (!$payment_plan) {
            echo '<p>' . __('Nessun piano di pagamento configurato', 'born-to-ride-booking') . '</p>';
            return;
        }
        
        $plan_labels = [
            'full' => __('Pagamento Completo', 'born-to-ride-booking'),
            'deposit_balance' => __('Caparra + Saldo', 'born-to-ride-booking'),
            'group_split' => __('Suddivisione Gruppo', 'born-to-ride-booking')
        ];
        ?>
        <div class="btr-payment-plan-info">
            <p>
                <strong><?php esc_html_e('Tipo:', 'born-to-ride-booking'); ?></strong>
                <?php echo esc_html($plan_labels[$payment_plan->plan_type] ?? $payment_plan->plan_type); ?>
            </p>
            
            <p>
                <strong><?php esc_html_e('Totale:', 'born-to-ride-booking'); ?></strong>
                <?php echo btr_format_price_i18n($payment_plan->total_amount); ?>
            </p>
            
            <?php if ($payment_plan->plan_type === 'deposit_balance'): ?>
                <p>
                    <strong><?php esc_html_e('Caparra:', 'born-to-ride-booking'); ?></strong>
                    <?php echo esc_html($payment_plan->deposit_percentage); ?>%
                </p>
            <?php endif; ?>
            
            <?php if ($payment_plan->plan_type === 'group_split'): ?>
                <p>
                    <strong><?php esc_html_e('Partecipanti:', 'born-to-ride-booking'); ?></strong>
                    <?php echo esc_html($payment_plan->total_participants); ?>
                </p>
                
                <?php
                // Mostra stato pagamenti
                global $wpdb;
                $payment_stats = $wpdb->get_row($wpdb->prepare("
                    SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid
                    FROM {$wpdb->prefix}btr_group_payments
                    WHERE preventivo_id = %d AND payment_plan_type = 'group_split'
                ", $post->ID));
                
                if ($payment_stats && $payment_stats->total > 0):
                ?>
                <p>
                    <strong><?php esc_html_e('Pagamenti:', 'born-to-ride-booking'); ?></strong>
                    <?php 
                    printf(
                        __('%d su %d completati', 'born-to-ride-booking'),
                        $payment_stats->paid,
                        $payment_stats->total
                    );
                    ?>
                </p>
                
                <div class="btr-payment-progress">
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo ($payment_stats->paid / $payment_stats->total) * 100; ?>%"></div>
                    </div>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <p class="btr-payment-actions">
                <a href="<?php echo admin_url('admin.php?page=btr-payment-plans&preventivo_id=' . $post->ID); ?>" class="button button-small">
                    <?php esc_html_e('Gestisci Pagamenti', 'born-to-ride-booking'); ?>
                </a>
            </p>
        </div>
        
        <style>
        .btr-payment-plan-info p {
            margin: 8px 0;
        }
        .btr-payment-progress {
            margin: 10px 0;
        }
        .progress-bar {
            background: #e0e0e0;
            height: 10px;
            border-radius: 5px;
            overflow: hidden;
        }
        .progress-fill {
            background: #4caf50;
            height: 100%;
            transition: width 0.3s ease;
        }
        .btr-payment-actions {
            margin-top: 15px !important;
            text-align: center;
        }
        </style>
        <?php
    }
    
    /**
     * Carica script frontend
     */
    public function enqueue_scripts() {
        if (!is_page() && !is_single() && !is_checkout()) {
            return;
        }
        
        wp_enqueue_script(
            'btr-payment-integration',
            BTR_PLUGIN_URL . 'assets/js/payment-integration.js',
            ['jquery'],
            BTR_VERSION,
            true
        );
        
        wp_localize_script('btr-payment-integration', 'btr_payment_integration', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('btr_payment_plan_nonce'),
            'strings' => [
                'loading' => __('Caricamento...', 'born-to-ride-booking'),
                'error' => __('Si è verificato un errore', 'born-to-ride-booking')
            ]
        ]);
        
        // Se siamo nel checkout e c'è il flag per mostrare il modal
        if (is_checkout() && (isset($_GET['show_payment_modal']) || (WC()->session && WC()->session->get('btr_show_payment_modal')))) {
            $preventivo_id = 0;
            $options = [];
            
            // Recupera dati dalla sessione
            if (WC()->session) {
                if (WC()->session->get('btr_show_payment_modal')) {
                    $preventivo_id = WC()->session->get('btr_payment_modal_preventivo');
                    $options = WC()->session->get('btr_payment_modal_options', []);
                    
                    // Pulisci la sessione
                    WC()->session->__unset('btr_show_payment_modal');
                    WC()->session->__unset('btr_payment_modal_preventivo');
                    WC()->session->__unset('btr_payment_modal_options');
                }
            }
            
            // Fallback se non ci sono dati in sessione
            if (!$preventivo_id && isset($_GET['preventivo_id'])) {
                $preventivo_id = intval($_GET['preventivo_id']);
                $options = [
                    'bankTransferEnabled' => get_option('btr_enable_bank_transfer_plans', true),
                    'bankTransferInfo' => get_option('btr_bank_transfer_info', ''),
                    'depositPercentage' => intval(get_option('btr_default_deposit_percentage', 30))
                ];
            }
            
            if ($preventivo_id > 0) {
                ?>
                <script>
                jQuery(document).ready(function($) {
                    console.log('[BTR Payment] Document ready - inizializzazione modal');
                    console.log('[BTR Payment] Preventivo ID:', <?php echo intval($preventivo_id); ?>);
                    console.log('[BTR Payment] Options:', <?php echo json_encode($options); ?>);
                    
                    // Verifica se la funzione esiste
                    if (typeof showPaymentPlanSelection === 'function') {
                        console.log('[BTR Payment] Funzione showPaymentPlanSelection trovata');
                        
                        // Aggiungi un piccolo delay per assicurarsi che tutto sia caricato
                        setTimeout(function() {
                            console.log('[BTR Payment] Mostrando modal...');
                            try {
                                showPaymentPlanSelection(<?php echo intval($preventivo_id); ?>, <?php echo json_encode($options); ?>);
                                console.log('[BTR Payment] Modal chiamato con successo');
                            } catch (error) {
                                console.error('[BTR Payment] Errore nel mostrare modal:', error);
                            }
                        }, 500);
                    } else {
                        console.error('[BTR Payment] showPaymentPlanSelection NON trovata!');
                        console.log('[BTR Payment] Scripts caricati:', Object.keys(window).filter(k => k.includes('btr')));
                    }
                });
                </script>
                <?php
            }
        }
    }
}

// Inizializza integrazione
new BTR_Payment_Integration();