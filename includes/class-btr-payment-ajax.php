<?php
/**
 * AJAX handlers per il sistema di pagamenti
 * 
 * @package BornToRideBooking
 * @since 1.0.98
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Payment_Ajax {
    
    /**
     * Constructor
     */
    public function __construct() {
        // Frontend AJAX
        add_action('wp_ajax_btr_create_payment_plan', [$this, 'handle_create_payment_plan']);
        add_action('wp_ajax_nopriv_btr_create_payment_plan', [$this, 'handle_create_payment_plan']);
        
        add_action('wp_ajax_btr_process_group_payment', [$this, 'handle_process_group_payment']);
        add_action('wp_ajax_nopriv_btr_process_group_payment', [$this, 'handle_process_group_payment']);
        
        add_action('wp_ajax_btr_check_payment_status', [$this, 'handle_check_payment_status']);
        add_action('wp_ajax_nopriv_btr_check_payment_status', [$this, 'handle_check_payment_status']);
        
        // Admin AJAX
        add_action('wp_ajax_btr_send_payment_reminder', [$this, 'handle_send_payment_reminder']);
        add_action('wp_ajax_btr_get_payment_stats', [$this, 'handle_get_payment_stats']);
        add_action('wp_ajax_btr_update_payment_note', [$this, 'handle_update_payment_note']);
    }
    
    /**
     * Gestisce creazione piano di pagamento
     */
    public function handle_create_payment_plan() {
        // Verifica nonce - fix field name
        if (!check_ajax_referer('btr_payment_plan_nonce', 'payment_nonce', false)) {
            wp_send_json_error(['message' => __('Sessione scaduta. Ricarica la pagina.', 'born-to-ride-booking')]);
        }
        
        // Sanitizza input
        $data = BTR_Payment_Security::sanitize_input($_POST);
        
        // Debug: Log dati ricevuti
        error_log('BTR Payment AJAX Data: ' . print_r($data, true));
        
        // Valida dati
        $validation = BTR_Payment_Security::validate_payment_plan($data);
        if (is_wp_error($validation)) {
            error_log('BTR Payment Validation Errors: ' . print_r($validation->get_error_messages(), true));
            wp_send_json_error([
                'message' => __('Errori di validazione', 'born-to-ride-booking'),
                'errors' => $validation->get_error_messages()
            ]);
        }
        
        // Rate limiting per sicurezza
        $user_identifier = is_user_logged_in() ? 'user_' . get_current_user_id() : 'ip_' . BTR_Payment_Security::get_client_ip();
        if (!BTR_Payment_Security::check_rate_limit('create_payment_plan', $user_identifier, 10, 3600)) {
            wp_send_json_error(['message' => __('Troppe richieste. Riprova più tardi.', 'born-to-ride-booking')]);
        }
        
        try {
            error_log('BTR Payment: Starting plan creation');
            $payment_plans = BTR_Payment_Plans::get_instance();
            
            // Prepara argomenti - fix mapping from form
            $plan_type = isset($data['payment_plan']) ? $data['payment_plan'] : 'full';
            $args = [
                'plan_type' => $plan_type,
                'deposit_percentage' => isset($data['deposit_percentage']) ? $data['deposit_percentage'] : 30
            ];
            
            error_log('BTR Payment: Creating plan with args: ' . print_r($args, true));
            
            // Crea piano
            $result = $payment_plans->create_payment_plan($data['preventivo_id'], $args);
            
            error_log('BTR Payment: Plan creation result: ' . print_r($result, true));
            
            if (is_wp_error($result)) {
                error_log('BTR Payment: Plan creation failed: ' . $result->get_error_message());
                throw new Exception($result->get_error_message());
            }
            
            // Se è gruppo, genera link pagamento
            if ($plan_type === 'group_split' && !empty($data['group_participants'])) {
                $links = $payment_plans->generate_group_payment_links($data['preventivo_id'], $data['group_participants']);
                
                if (is_wp_error($links)) {
                    throw new Exception($links->get_error_message());
                }
                
                $result['payment_links'] = $links;
            }
            
            // Log evento
            BTR_Payment_Security::log_security_event('payment_plan_created', [
                'preventivo_id' => $data['preventivo_id'],
                'plan_type' => $plan_type
            ], 'success');
            
            // Trigger action
            do_action('btr_payment_plan_created', $data['preventivo_id'], $plan_type);
            
            // Imposta dati di sessione WooCommerce per l'integrazione caparra
            if ($plan_type === 'deposit_balance') {
                if (function_exists('WC')) {
                    WC()->session->set('btr_payment_type', 'deposit');
                    WC()->session->set('btr_payment_plan', 'deposit_balance');
                    WC()->session->set('btr_preventivo_id', $data['preventivo_id']);
                    WC()->session->set('btr_deposit_percentage', $args['deposit_percentage']);
                    
                    // Log per debug
                    error_log('BTR Payment: Sessione WooCommerce impostata per caparra - Preventivo: ' . $data['preventivo_id'] . ', Percentuale: ' . $args['deposit_percentage'] . '%');
                }
            }
            
            // Determina URL di redirect
            $redirect_url = wc_get_checkout_url();
            
            // Se è un pagamento di gruppo con link generati, usa pagina riepilogo
            if ($plan_type === 'group_split' && isset($result['payment_links'])) {
                $links_summary_page = get_option('btr_payment_links_summary_page');
                if ($links_summary_page) {
                    $redirect_url = add_query_arg('preventivo_id', $data['preventivo_id'], get_permalink($links_summary_page));
                } else {
                    $redirect_url = add_query_arg([
                        'preventivo_id' => $data['preventivo_id'],
                        'show_payment_links' => 'true'
                    ], home_url('/payment-links-summary/'));
                }
            }
            
            wp_send_json_success([
                'message' => __('Piano di pagamento creato con successo', 'born-to-ride-booking'),
                'data' => $result,
                'redirect_url' => $redirect_url
            ]);
            
        } catch (Exception $e) {
            BTR_Payment_Security::log_security_event('payment_plan_creation_failed', [
                'error' => $e->getMessage()
            ], 'error');
            
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * Gestisce processo pagamento gruppo
     */
    public function handle_process_group_payment() {
        // Sanitizza input
        $data = BTR_Payment_Security::sanitize_input($_POST);
        
        // Valida dati checkout
        $validation = BTR_Payment_Security::validate_group_checkout($data);
        if (is_wp_error($validation)) {
            wp_send_json_error([
                'message' => __('Errori di validazione', 'born-to-ride-booking'),
                'errors' => $validation->get_error_messages()
            ]);
        }
        
        $payment = $validation['payment'];
        $preventivo = $validation['preventivo'];
        
        try {
            // Crea ordine WooCommerce
            $order = $this->create_wc_order_for_payment($payment, $data);
            
            if (is_wp_error($order)) {
                throw new Exception($order->get_error_message());
            }
            
            // Aggiorna record pagamento con order ID
            global $wpdb;
            $wpdb->update(
                $wpdb->prefix . 'btr_group_payments',
                ['wc_order_id' => $order->get_id()],
                ['payment_id' => $payment->payment_id],
                ['%d'],
                ['%d']
            );
            
            // Processa pagamento
            $payment_gateway = WC()->payment_gateways->get_available_payment_gateways()[$data['payment_method']];
            
            if (!$payment_gateway) {
                throw new Exception(__('Metodo di pagamento non disponibile', 'born-to-ride-booking'));
            }
            
            // Log tentativo pagamento
            BTR_Payment_Security::log_security_event('payment_attempt', [
                'payment_id' => $payment->payment_id,
                'order_id' => $order->get_id(),
                'payment_method' => $data['payment_method']
            ]);
            
            // Ottieni URL pagamento
            $result = $payment_gateway->process_payment($order->get_id());
            
            if ($result['result'] === 'success') {
                wp_send_json_success([
                    'redirect' => $result['redirect']
                ]);
            } else {
                throw new Exception(__('Errore nel processo di pagamento', 'born-to-ride-booking'));
            }
            
        } catch (Exception $e) {
            BTR_Payment_Security::log_security_event('payment_failed', [
                'payment_id' => $payment->payment_id,
                'error' => $e->getMessage()
            ], 'error');
            
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    /**
     * Crea ordine WooCommerce per pagamento
     */
    private function create_wc_order_for_payment($payment, $billing_data) {
        // Recupera dati necessari
        $preventivo_id = $payment->preventivo_id;
        $package_id = get_post_meta($preventivo_id, '_pacchetto_id', true);
        $package_title = get_the_title($package_id);
        
        // Crea ordine
        $order = wc_create_order();
        
        if (is_wp_error($order)) {
            return $order;
        }
        
        // Aggiungi prodotto virtuale per la quota
        $product_args = [
            'name' => sprintf(__('Quota viaggio - %s', 'born-to-ride-booking'), $package_title),
            'price' => $payment->amount,
            'qty' => 1,
            'tax_status' => 'none'
        ];
        
        $item_id = $order->add_product(null, 1, $product_args);
        
        // Imposta dati fatturazione
        $order->set_billing_first_name($billing_data['billing_first_name']);
        $order->set_billing_last_name($billing_data['billing_last_name']);
        $order->set_billing_email($billing_data['billing_email']);
        $order->set_billing_phone($billing_data['billing_phone']);
        $order->set_billing_address_1($billing_data['billing_address']);
        $order->set_billing_city($billing_data['billing_city']);
        $order->set_billing_postcode($billing_data['billing_postcode']);
        $order->set_billing_country('IT');
        
        // Aggiungi codice fiscale
        $order->update_meta_data('_billing_cf', $billing_data['billing_cf']);
        
        // Meta dati pagamento
        $order->update_meta_data('_btr_payment_id', $payment->payment_id);
        $order->update_meta_data('_btr_preventivo_id', $preventivo_id);
        $order->update_meta_data('_btr_payment_type', 'group_payment');
        $order->update_meta_data('_btr_participant_name', $payment->group_member_name ?: $payment->participant_name);
        
        // Imposta metodo pagamento
        $order->set_payment_method($billing_data['payment_method']);
        
        // Calcola totali
        $order->calculate_totals();
        
        // Salva ordine
        $order->save();
        
        return $order;
    }
    
    /**
     * Verifica stato pagamento
     */
    public function handle_check_payment_status() {
        // Verifica nonce
        if (!check_ajax_referer('btr_check_payment_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Sessione scaduta', 'born-to-ride-booking')]);
        }
        
        $payment_hash = sanitize_text_field($_POST['payment_hash']);
        
        if (!btr_is_valid_payment_hash($payment_hash)) {
            wp_send_json_error(['message' => __('Hash non valido', 'born-to-ride-booking')]);
        }
        
        global $wpdb;
        
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT payment_status, wc_order_id FROM {$wpdb->prefix}btr_group_payments WHERE payment_hash = %s",
            $payment_hash
        ));
        
        if (!$payment) {
            wp_send_json_error(['message' => __('Pagamento non trovato', 'born-to-ride-booking')]);
        }
        
        $response = [
            'status' => $payment->payment_status
        ];
        
        if ($payment->payment_status === 'paid' && $payment->wc_order_id) {
            $order = wc_get_order($payment->wc_order_id);
            if ($order) {
                $response['order_number'] = $order->get_order_number();
                $response['thank_you_url'] = $order->get_checkout_order_received_url();
            }
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * Invia reminder pagamento (admin)
     */
    public function handle_send_payment_reminder() {
        // Verifica permessi
        if (!BTR_Payment_Security::validate_admin_permission('send_reminder')) {
            wp_send_json_error(['message' => __('Permessi insufficienti', 'born-to-ride-booking')]);
        }
        
        // Verifica nonce
        if (!check_ajax_referer('btr_payment_admin_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Sessione scaduta', 'born-to-ride-booking')]);
        }
        
        $payment_id = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;
        
        if (!$payment_id) {
            wp_send_json_error(['message' => __('ID pagamento non valido', 'born-to-ride-booking')]);
        }
        
        // Invia reminder
        $sent = BTR_Payment_Plans_Admin::send_payment_reminder($payment_id);
        
        if ($sent) {
            wp_send_json_success(['message' => __('Promemoria inviato con successo', 'born-to-ride-booking')]);
        } else {
            wp_send_json_error(['message' => __('Errore nell\'invio del promemoria', 'born-to-ride-booking')]);
        }
    }
    
    /**
     * Ottieni statistiche pagamenti (admin)
     */
    public function handle_get_payment_stats() {
        // Verifica permessi
        if (!BTR_Payment_Security::validate_admin_permission('view_payments')) {
            wp_send_json_error(['message' => __('Permessi insufficienti', 'born-to-ride-booking')]);
        }
        
        // Verifica nonce
        if (!check_ajax_referer('btr_payment_admin_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Sessione scaduta', 'born-to-ride-booking')]);
        }
        
        global $wpdb;
        
        // Statistiche generali
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_payments,
                SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                SUM(CASE WHEN payment_status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                SUM(CASE WHEN payment_status = 'expired' THEN 1 ELSE 0 END) as expired_count,
                SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as total_paid,
                SUM(CASE WHEN payment_status = 'pending' THEN amount ELSE 0 END) as total_pending,
                AVG(CASE WHEN payment_status = 'paid' THEN amount ELSE NULL END) as avg_payment
            FROM {$wpdb->prefix}btr_group_payments
        ");
        
        // Statistiche per piano
        $by_plan = $wpdb->get_results("
            SELECT 
                payment_plan_type,
                COUNT(*) as count,
                SUM(amount) as total_amount
            FROM {$wpdb->prefix}btr_group_payments
            WHERE payment_status = 'paid'
            GROUP BY payment_plan_type
        ");
        
        // Statistiche temporali (ultimi 30 giorni)
        $daily_stats = $wpdb->get_results("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as payments,
                SUM(amount) as amount
            FROM {$wpdb->prefix}btr_group_payments
            WHERE payment_status = 'paid'
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ");
        
        wp_send_json_success([
            'general' => $stats,
            'by_plan' => $by_plan,
            'daily' => $daily_stats
        ]);
    }
    
    /**
     * Aggiorna nota pagamento (admin)
     */
    public function handle_update_payment_note() {
        // Verifica permessi
        if (!BTR_Payment_Security::validate_admin_permission('edit_payment')) {
            wp_send_json_error(['message' => __('Permessi insufficienti', 'born-to-ride-booking')]);
        }
        
        // Verifica nonce
        if (!check_ajax_referer('btr_payment_admin_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Sessione scaduta', 'born-to-ride-booking')]);
        }
        
        $payment_id = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;
        $note = isset($_POST['note']) ? sanitize_textarea_field($_POST['note']) : '';
        
        if (!$payment_id) {
            wp_send_json_error(['message' => __('ID pagamento non valido', 'born-to-ride-booking')]);
        }
        
        global $wpdb;
        
        $updated = $wpdb->update(
            $wpdb->prefix . 'btr_group_payments',
            ['notes' => $note],
            ['payment_id' => $payment_id],
            ['%s'],
            ['%d']
        );
        
        if ($updated !== false) {
            wp_send_json_success(['message' => __('Nota aggiornata con successo', 'born-to-ride-booking')]);
        } else {
            wp_send_json_error(['message' => __('Errore nell\'aggiornamento', 'born-to-ride-booking')]);
        }
    }
}

// Inizializza AJAX handlers
new BTR_Payment_Ajax();