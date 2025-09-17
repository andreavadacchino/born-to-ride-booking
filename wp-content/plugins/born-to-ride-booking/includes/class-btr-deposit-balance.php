<?php
/**
 * Sistema Caparra + Saldo per gestione pagamenti turistici
 * 
 * Gestisce pagamenti in due fasi:
 * - Caparra (30-50% del totale)
 * - Saldo (rimanente, pagabile entro scadenza)
 * 
 * @since 1.0.14
 * @author Born To Ride Booking
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Deposit_Balance {
    
    /**
     * Percentuale default caparra
     */
    const DEFAULT_DEPOSIT_PERCENTAGE = 30;
    
    /**
     * Giorni default per scadenza saldo
     */
    const DEFAULT_BALANCE_DAYS = 30;

    public function __construct() {
        // Hook per gestione pagamenti a rate
        add_action('init', [$this, 'init_hooks']);
        
        // AJAX endpoints per caparra/saldo
        add_action('wp_ajax_btr_generate_deposit_links', [$this, 'ajax_generate_deposit_links']);
        add_action('wp_ajax_btr_generate_balance_links', [$this, 'ajax_generate_balance_links']);
        add_action('wp_ajax_btr_update_deposit_settings', [$this, 'ajax_update_deposit_settings']);
        
        // Notifiche automatiche scadenze
        add_action('btr_check_balance_deadlines', [$this, 'check_balance_deadlines']);
        if (!wp_next_scheduled('btr_check_balance_deadlines')) {
            wp_schedule_event(time(), 'daily', 'btr_check_balance_deadlines');
        }
        
        // WooCommerce integration
        add_action('woocommerce_thankyou', [$this, 'handle_deposit_completion'], 5, 1);
        
        // Admin interface
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    public function init_hooks() {
        // Gestione URL per pagamenti caparra/saldo
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('parse_request', [$this, 'parse_deposit_request']);
        add_action('template_redirect', [$this, 'handle_deposit_page']);
    }

    public function add_query_vars($vars) {
        $vars[] = 'btr_deposit_hash';
        $vars[] = 'btr_balance_hash';
        $vars[] = 'btr_deposit_action';
        return $vars;
    }

    public function parse_deposit_request($wp) {
        // URL per caparre: /deposit/{hash}
        if (isset($wp->request) && preg_match('#^deposit/([a-f0-9]{64})/?$#i', $wp->request, $matches)) {
            $wp->query_vars['btr_deposit_hash'] = $matches[1];
            $wp->query_vars['btr_deposit_action'] = 'deposit';
        }
        
        // URL per saldi: /balance/{hash}
        if (isset($wp->request) && preg_match('#^balance/([a-f0-9]{64})/?$#i', $wp->request, $matches)) {
            $wp->query_vars['btr_balance_hash'] = $matches[1];
            $wp->query_vars['btr_deposit_action'] = 'balance';
        }
    }

    public function handle_deposit_page() {
        $deposit_hash = get_query_var('btr_deposit_hash');
        $balance_hash = get_query_var('btr_balance_hash');
        $action = get_query_var('btr_deposit_action');

        if ($action === 'deposit' && $deposit_hash) {
            $this->render_deposit_payment_page($deposit_hash);
            exit;
        } elseif ($action === 'balance' && $balance_hash) {
            $this->render_balance_payment_page($balance_hash);
            exit;
        }
    }

    /**
     * Genera link di pagamento per caparreKEY
     */
    public function generate_deposit_payment_links($preventivo_id, $deposit_percentage = null) {
        global $wpdb;

        $anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
        if (empty($anagrafici) || !is_array($anagrafici)) {
            return new WP_Error('no_participants', 'Nessun partecipante trovato');
        }

        $prezzo_totale = 0.0;
        if (function_exists('btr_price_calculator')) {
            $calculator = btr_price_calculator();
            $totals = $calculator->calculate_preventivo_total([
                'preventivo_id' => $preventivo_id,
                'anagrafici' => $anagrafici,
            ]);

            if (!empty($totals['valid']) && !empty($totals['totale_finale'])) {
                $prezzo_totale = floatval($totals['totale_finale']);
                error_log('[BTR CALCULATOR] Deposit Balance: totale da calcolatore = €' . $prezzo_totale);
            }
        }

        if ($prezzo_totale <= 0) {
            // Fallback legacy se il calcolatore non fornisce dati
            $prezzo_totale = (float) get_post_meta($preventivo_id, '_totale_preventivo', true);
            if (!$prezzo_totale) {
                $prezzo_totale = (float) get_post_meta($preventivo_id, '_prezzo_totale', true);
            }
            error_log('[BTR LEGACY] Deposit Balance: Usando totale legacy - €' . $prezzo_totale);
        }
        $deposit_percentage = $deposit_percentage ?? $this->get_deposit_percentage($preventivo_id);
        $deposit_amount_total = ($prezzo_totale * $deposit_percentage) / 100;
        $deposit_per_person = $deposit_amount_total / count($anagrafici);
        
        $table_payments = $wpdb->prefix . 'btr_group_payments';
        $links = [];

        foreach ($anagrafici as $index => $participant) {
            $participant_name = trim(($participant['nome'] ?? '') . ' ' . ($participant['cognome'] ?? ''));
            $participant_email = $participant['email'] ?? '';

            if (empty($participant_email)) {
                continue;
            }

            // Genera hash per caparra
            $deposit_hash = $this->generate_secure_hash($preventivo_id, $index, $participant_email, 'deposit');
            
            // Inserisci record caparra
            $deposit_data = [
                'preventivo_id' => $preventivo_id,
                'participant_index' => $index,
                'participant_name' => $participant_name,
                'participant_email' => $participant_email,
                'amount' => $deposit_per_person,
                'payment_status' => 'pending',
                'payment_type' => 'deposit',
                'payment_hash' => $deposit_hash,
                'created_at' => current_time('mysql'),
                'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')), // 7 giorni per caparra
                'notes' => json_encode([
                    'deposit_percentage' => $deposit_percentage,
                    'total_amount' => $prezzo_totale / count($anagrafici),
                    'balance_amount' => ($prezzo_totale / count($anagrafici)) - $deposit_per_person
                ])
            ];

            $result = $wpdb->insert($table_payments, $deposit_data);
            
            if ($result !== false) {
                $payment_id = $wpdb->insert_id;
                
                // Crea link nella tabella links
                $this->create_payment_link($payment_id, $deposit_hash, 'deposit');
                
                $links[] = [
                    'payment_id' => $payment_id,
                    'type' => 'deposit',
                    'participant_name' => $participant_name,
                    'participant_email' => $participant_email,
                    'amount' => $deposit_per_person,
                    'payment_url' => home_url('/deposit/' . $deposit_hash),
                    'expires_at' => $deposit_data['expires_at']
                ];
            }
        }

        // Salva configurazione caparra nel preventivo
        update_post_meta($preventivo_id, '_btr_deposit_percentage', $deposit_percentage);
        update_post_meta($preventivo_id, '_btr_payment_mode', 'deposit_balance');

        return $links;
    }

    /**
     * Genera link di pagamento per saldi
     */
    public function generate_balance_payment_links($preventivo_id, $balance_deadline = null) {
        global $wpdb;

        $anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
        if (empty($anagrafici)) {
            return new WP_Error('no_participants', 'Nessun partecipante trovato');
        }

        $prezzo_totale = 0.0;
        if (function_exists('btr_price_calculator')) {
            $calculator = btr_price_calculator();
            $totals = $calculator->calculate_preventivo_total([
                'preventivo_id' => $preventivo_id,
                'anagrafici' => $anagrafici,
            ]);

            if (!empty($totals['valid']) && !empty($totals['totale_finale'])) {
                $prezzo_totale = floatval($totals['totale_finale']);
                error_log('[BTR CALCULATOR] Balance: totale da calcolatore = €' . $prezzo_totale);
            }
        }

        if ($prezzo_totale <= 0) {
            $prezzo_totale = (float) get_post_meta($preventivo_id, '_totale_preventivo', true);
            if (!$prezzo_totale) {
                $prezzo_totale = (float) get_post_meta($preventivo_id, '_prezzo_totale', true);
            }
            error_log('[BTR LEGACY] Deposit Balance: Usando totale legacy - €' . $prezzo_totale);
        }
        $deposit_percentage = $this->get_deposit_percentage($preventivo_id);
        $balance_percentage = 100 - $deposit_percentage;
        $balance_amount_total = ($prezzo_totale * $balance_percentage) / 100;
        $balance_per_person = $balance_amount_total / count($anagrafici);
        
        $balance_deadline = $balance_deadline ?? date('Y-m-d H:i:s', strtotime('+' . self::DEFAULT_BALANCE_DAYS . ' days'));
        
        $table_payments = $wpdb->prefix . 'btr_group_payments';
        $links = [];

        foreach ($anagrafici as $index => $participant) {
            $participant_name = trim(($participant['nome'] ?? '') . ' ' . ($participant['cognome'] ?? ''));
            $participant_email = $participant['email'] ?? '';

            if (empty($participant_email)) {
                continue;
            }

            // Verifica se esiste caparra pagata
            $deposit_paid = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_payments} 
                 WHERE preventivo_id = %d AND participant_index = %d 
                 AND payment_type = 'deposit' AND payment_status = 'paid'",
                $preventivo_id, $index
            ));

            if (!$deposit_paid) {
                continue; // Salta se caparra non pagata
            }

            // Genera hash per saldo
            $balance_hash = $this->generate_secure_hash($preventivo_id, $index, $participant_email, 'balance');
            
            // Inserisci record saldo
            $balance_data = [
                'preventivo_id' => $preventivo_id,
                'participant_index' => $index,
                'participant_name' => $participant_name,
                'participant_email' => $participant_email,
                'amount' => $balance_per_person,
                'payment_status' => 'pending',
                'payment_type' => 'balance',
                'payment_hash' => $balance_hash,
                'created_at' => current_time('mysql'),
                'expires_at' => $balance_deadline,
                'notes' => json_encode([
                    'balance_percentage' => $balance_percentage,
                    'total_amount' => $prezzo_totale / count($anagrafici),
                    'deposit_amount' => ($prezzo_totale / count($anagrafici)) - $balance_per_person
                ])
            ];

            $result = $wpdb->insert($table_payments, $balance_data);
            
            if ($result !== false) {
                $payment_id = $wpdb->insert_id;
                
                // Crea link
                $this->create_payment_link($payment_id, $balance_hash, 'balance');
                
                $links[] = [
                    'payment_id' => $payment_id,
                    'type' => 'balance',
                    'participant_name' => $participant_name,
                    'participant_email' => $participant_email,
                    'amount' => $balance_per_person,
                    'payment_url' => home_url('/balance/' . $balance_hash),
                    'expires_at' => $balance_deadline
                ];
            }
        }

        return $links;
    }

    /**
     * Renderizza pagina pagamento caparra
     */
    private function render_deposit_payment_page($deposit_hash) {
        $payment_data = $this->get_payment_data_by_hash($deposit_hash, 'deposit');
        
        if (!$payment_data) {
            wp_die('Link di pagamento caparra non valido o scaduto.', 'Errore Pagamento', ['response' => 404]);
        }

        $notes = json_decode($payment_data['notes'], true);
        $total_amount = $notes['total_amount'] ?? 0;
        $balance_amount = $notes['balance_amount'] ?? 0;
        
        get_header();
        ?>
        <div class="btr-deposit-payment-page">
            <div class="container">
                <h1>Pagamento Caparra</h1>
                
                <div class="payment-summary">
                    <h3>Riepilogo Pagamento</h3>
                    <table class="payment-details">
                        <tr>
                            <td><strong>Partecipante:</strong></td>
                            <td><?= esc_html($payment_data['participant_name']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Totale Viaggio:</strong></td>
                            <td>€<?= number_format($total_amount, 2, ',', '.') ?></td>
                        </tr>
                        <tr class="highlight">
                            <td><strong>Caparra (<?= $notes['deposit_percentage'] ?>%):</strong></td>
                            <td><strong>€<?= number_format($payment_data['amount'], 2, ',', '.') ?></strong></td>
                        </tr>
                        <tr>
                            <td><strong>Saldo Rimanente:</strong></td>
                            <td>€<?= number_format($balance_amount, 2, ',', '.') ?></td>
                        </tr>
                        <tr>
                            <td><strong>Scadenza Caparra:</strong></td>
                            <td><?= date('d/m/Y', strtotime($payment_data['expires_at'])) ?></td>
                        </tr>
                    </table>
                </div>

                <div class="payment-info">
                    <div class="info-box">
                        <h4>ℹ️ Informazioni Importanti</h4>
                        <ul>
                            <li>Questa è la <strong>caparra confirmatoria</strong> del tuo viaggio</li>
                            <li>Il saldo rimanente sarà richiesto successivamente</li>
                            <li>Riceverai un link dedicato per il pagamento del saldo</li>
                            <li>La caparra deve essere pagata entro la scadenza indicata</li>
                        </ul>
                    </div>
                </div>

                <div class="payment-form">
                    <form id="btr-deposit-payment-form" method="post" action="<?= esc_url(wc_get_checkout_url()) ?>">
                        <input type="hidden" name="btr_payment_hash" value="<?= esc_attr($payment_data['payment_hash']) ?>">
                        <input type="hidden" name="btr_payment_type" value="deposit">
                        
                        <button type="submit" class="btn-pay-deposit">
                            Paga Caparra - €<?= number_format($payment_data['amount'], 2, ',', '.') ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <style>
        .btr-deposit-payment-page {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
        }
        
        .payment-summary {
            background: #f9f9f9;
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .payment-details {
            width: 100%;
            border-collapse: collapse;
        }
        
        .payment-details td {
            padding: 0.75rem;
            border-bottom: 1px solid #eee;
        }
        
        .payment-details tr.highlight {
            background: #e8f4fd;
            font-weight: bold;
        }
        
        .info-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 1rem;
            margin-bottom: 2rem;
        }
        
        .info-box h4 {
            margin: 0 0 0.5rem;
            color: #856404;
        }
        
        .info-box ul {
            margin: 0;
            color: #856404;
        }
        
        .btn-pay-deposit {
            background: #0073aa;
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 4px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            transition: background 0.3s;
        }
        
        .btn-pay-deposit:hover {
            background: #005a87;
        }
        </style>
        <?php
        get_footer();
    }

    /**
     * Renderizza pagina pagamento saldo
     */
    private function render_balance_payment_page($balance_hash) {
        $payment_data = $this->get_payment_data_by_hash($balance_hash, 'balance');
        
        if (!$payment_data) {
            wp_die('Link di pagamento saldo non valido o scaduto.', 'Errore Pagamento', ['response' => 404]);
        }

        $notes = json_decode($payment_data['notes'], true);
        $total_amount = $notes['total_amount'] ?? 0;
        $deposit_amount = $notes['deposit_amount'] ?? 0;
        
        get_header();
        ?>
        <div class="btr-balance-payment-page">
            <div class="container">
                <h1>Pagamento Saldo Finale</h1>
                
                <div class="payment-summary">
                    <h3>Riepilogo Pagamento</h3>
                    <table class="payment-details">
                        <tr>
                            <td><strong>Partecipante:</strong></td>
                            <td><?= esc_html($payment_data['participant_name']) ?></td>
                        </tr>
                        <tr>
                            <td><strong>Totale Viaggio:</strong></td>
                            <td>€<?= number_format($total_amount, 2, ',', '.') ?></td>
                        </tr>
                        <tr class="paid">
                            <td><strong>Caparra Pagata:</strong></td>
                            <td>€<?= number_format($deposit_amount, 2, ',', '.') ?></td>
                        </tr>
                        <tr class="highlight">
                            <td><strong>Saldo Finale:</strong></td>
                            <td><strong>€<?= number_format($payment_data['amount'], 2, ',', '.') ?></strong></td>
                        </tr>
                        <tr>
                            <td><strong>Scadenza Saldo:</strong></td>
                            <td><?= date('d/m/Y', strtotime($payment_data['expires_at'])) ?></td>
                        </tr>
                    </table>
                </div>

                <div class="payment-info">
                    <div class="info-box success">
                        <h4>✅ Caparra Confermata</h4>
                        <p>La tua caparra è stata pagata con successo. Ora puoi completare il pagamento con il saldo finale.</p>
                    </div>
                </div>

                <div class="payment-form">
                    <form id="btr-balance-payment-form" method="post" action="<?= esc_url(wc_get_checkout_url()) ?>">
                        <input type="hidden" name="btr_payment_hash" value="<?= esc_attr($payment_data['payment_hash']) ?>">
                        <input type="hidden" name="btr_payment_type" value="balance">
                        
                        <button type="submit" class="btn-pay-balance">
                            Paga Saldo Finale - €<?= number_format($payment_data['amount'], 2, ',', '.') ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <style>
        .btr-balance-payment-page {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
        }
        
        .payment-details tr.paid {
            background: #d4edda;
            color: #155724;
        }
        
        .info-box.success {
            background: #d4edda;
            border-color: #c3e6cb;
        }
        
        .info-box.success h4 {
            color: #155724;
        }
        
        .info-box.success p {
            color: #155724;
            margin: 0;
        }
        
        .btn-pay-balance {
            background: #28a745;
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 4px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            transition: background 0.3s;
        }
        
        .btn-pay-balance:hover {
            background: #218838;
        }
        </style>
        <?php
        get_footer();
    }

    /**
     * Utility methods
     */
    private function generate_secure_hash($preventivo_id, $participant_index, $email, $type) {
        $data = $preventivo_id . '|' . $participant_index . '|' . $email . '|' . $type . '|' . time() . '|' . wp_generate_password(32, false);
        return hash('sha256', $data);
    }

    private function create_payment_link($payment_id, $hash, $type) {
        global $wpdb;
        
        $table_links = $wpdb->prefix . 'btr_payment_links';
        
        $link_data = [
            'payment_id' => $payment_id,
            'link_hash' => $hash,
            'link_type' => $type,
            'created_at' => current_time('mysql'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days'))
        ];

        return $wpdb->insert($table_links, $link_data);
    }

    private function get_payment_data_by_hash($hash, $type) {
        global $wpdb;
        
        $table_payments = $wpdb->prefix . 'btr_group_payments';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_payments} 
             WHERE payment_hash = %s AND payment_type = %s 
             AND payment_status = 'pending' AND expires_at > NOW()",
            $hash, $type
        ), ARRAY_A);
    }

    private function get_deposit_percentage($preventivo_id) {
        $percentage = get_post_meta($preventivo_id, '_btr_deposit_percentage', true);
        return $percentage ? (int)$percentage : self::DEFAULT_DEPOSIT_PERCENTAGE;
    }

    /**
     * AJAX Handlers
     */
    public function ajax_generate_deposit_links() {
        check_ajax_referer('btr_group_payments', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die('Autorizzazione negata');
        }

        $preventivo_id = intval($_POST['preventivo_id'] ?? 0);
        $deposit_percentage = intval($_POST['deposit_percentage'] ?? self::DEFAULT_DEPOSIT_PERCENTAGE);

        if (!$preventivo_id) {
            wp_send_json_error('ID preventivo non valido');
        }

        $links = $this->generate_deposit_payment_links($preventivo_id, $deposit_percentage);

        if (is_wp_error($links)) {
            wp_send_json_error($links->get_error_message());
        }

        // Invia email automaticamente
        $email_count = 0;
        foreach ($links as $link) {
            if ($this->send_deposit_email($link['payment_id'])) {
                $email_count++;
            }
        }

        wp_send_json_success([
            'links' => $links,
            'emails_sent' => $email_count,
            'message' => "Generati {$email_count} link caparra e inviate email"
        ]);
    }

    public function ajax_generate_balance_links() {
        check_ajax_referer('btr_group_payments', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die('Autorizzazione negata');
        }

        $preventivo_id = intval($_POST['preventivo_id'] ?? 0);
        $balance_deadline = sanitize_text_field($_POST['balance_deadline'] ?? '');

        if (!$preventivo_id) {
            wp_send_json_error('ID preventivo non valido');
        }

        $links = $this->generate_balance_payment_links($preventivo_id, $balance_deadline);

        if (is_wp_error($links)) {
            wp_send_json_error($links->get_error_message());
        }

        // Invia email automaticamente
        $email_count = 0;
        foreach ($links as $link) {
            if ($this->send_balance_email($link['payment_id'])) {
                $email_count++;
            }
        }

        wp_send_json_success([
            'links' => $links,
            'emails_sent' => $email_count,
            'message' => "Generati {$email_count} link saldo e inviate email"
        ]);
    }

    public function ajax_update_deposit_settings() {
        check_ajax_referer('btr_group_payments', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_die('Autorizzazione negata');
        }

        $preventivo_id = intval($_POST['preventivo_id'] ?? 0);
        $deposit_percentage = intval($_POST['deposit_percentage'] ?? self::DEFAULT_DEPOSIT_PERCENTAGE);

        if (!$preventivo_id) {
            wp_send_json_error('ID preventivo non valido');
        }

        if ($deposit_percentage < 10 || $deposit_percentage > 90) {
            wp_send_json_error('Percentuale caparra deve essere tra 10% e 90%');
        }

        update_post_meta($preventivo_id, '_btr_deposit_percentage', $deposit_percentage);
        
        wp_send_json_success('Configurazione aggiornata con successo');
    }

    /**
     * Email functions
     */
    private function send_deposit_email($payment_id) {
        global $wpdb;
        
        $table_payments = $wpdb->prefix . 'btr_group_payments';
        $payment_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_payments} WHERE payment_id = %d",
            $payment_id
        ), ARRAY_A);

        if (!$payment_data) {
            return false;
        }

        $preventivo_id = $payment_data['preventivo_id'];
        $nome_pacchetto = get_post_meta($preventivo_id, '_nome_pacchetto', true);
        $notes = json_decode($payment_data['notes'], true);
        
        $payment_url = home_url('/deposit/' . $payment_data['payment_hash']);
        $expires_date = date('d/m/Y', strtotime($payment_data['expires_at']));
        
        $to = $payment_data['participant_email'];
        $subject = 'Pagamento Caparra - ' . $nome_pacchetto;
        
        $message = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #0097c5;'>Pagamento Caparra Confirmatoria</h2>
                
                <p>Ciao <strong>{$payment_data['participant_name']}</strong>,</p>
                
                <p>È ora di confermare la tua partecipazione al viaggio con il pagamento della caparra.</p>
                
                <div style='background: #f9f9f9; padding: 20px; border-left: 4px solid #0097c5; margin: 20px 0;'>
                    <h3 style='margin: 0 0 15px;'>{$nome_pacchetto}</h3>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr><td><strong>Totale viaggio:</strong></td><td>€" . number_format($notes['total_amount'], 2, ',', '.') . "</td></tr>
                        <tr><td><strong>Caparra ({$notes['deposit_percentage']}%):</strong></td><td><strong>€" . number_format($payment_data['amount'], 2, ',', '.') . "</strong></td></tr>
                        <tr><td><strong>Saldo rimanente:</strong></td><td>€" . number_format($notes['balance_amount'], 2, ',', '.') . "</td></tr>
                    </table>
                </div>
                
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='{$payment_url}' 
                       style='background: #0097c5; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>
                        Paga Caparra Ora
                    </a>
                </p>
                
                <div style='background: #fff3cd; padding: 15px; border-radius: 4px; margin: 20px 0;'>
                    <h4 style='margin: 0 0 10px; color: #856404;'>📅 Scadenza Importante</h4>
                    <p style='margin: 0; color: #856404;'>La caparra deve essere pagata entro il <strong>{$expires_date}</strong> per confermare la prenotazione.</p>
                </div>
                
                <div style='background: #e8f4fd; padding: 15px; border-radius: 4px; margin: 20px 0;'>
                    <h4 style='margin: 0 0 10px; color: #0c5460;'>ℹ️ Cosa succede dopo?</h4>
                    <ul style='margin: 5px 0; padding-left: 20px; color: #0c5460;'>
                        <li>Una volta pagata la caparra, riceverai la conferma</li>
                        <li>Il saldo rimanente sarà richiesto successivamente</li>
                        <li>Riceverai un nuovo link per il pagamento del saldo</li>
                    </ul>
                </div>
                
                <hr style='margin: 30px 0; border: none; height: 1px; background: #ddd;'>
                
                <p style='font-size: 14px; color: #666;'>
                    Se hai problemi con il pagamento, contattaci immediatamente.<br>
                    Questo è un messaggio automatico.
                </p>
            </div>
        </body>
        </html>
        ";

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];

        return wp_mail($to, $subject, $message, $headers);
    }

    private function send_balance_email($payment_id) {
        global $wpdb;
        
        $table_payments = $wpdb->prefix . 'btr_group_payments';
        $payment_data = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_payments} WHERE payment_id = %d",
            $payment_id
        ), ARRAY_A);

        if (!$payment_data) {
            return false;
        }

        $preventivo_id = $payment_data['preventivo_id'];
        $nome_pacchetto = get_post_meta($preventivo_id, '_nome_pacchetto', true);
        $notes = json_decode($payment_data['notes'], true);
        
        $payment_url = home_url('/balance/' . $payment_data['payment_hash']);
        $expires_date = date('d/m/Y', strtotime($payment_data['expires_at']));
        
        $to = $payment_data['participant_email'];
        $subject = 'Pagamento Saldo Finale - ' . $nome_pacchetto;
        
        $message = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
                <h2 style='color: #28a745;'>Pagamento Saldo Finale</h2>
                
                <p>Ciao <strong>{$payment_data['participant_name']}</strong>,</p>
                
                <p>È tempo di completare il pagamento del tuo viaggio! La caparra è stata confermata con successo.</p>
                
                <div style='background: #f9f9f9; padding: 20px; border-left: 4px solid #28a745; margin: 20px 0;'>
                    <h3 style='margin: 0 0 15px;'>{$nome_pacchetto}</h3>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr><td><strong>Totale viaggio:</strong></td><td>€" . number_format($notes['total_amount'], 2, ',', '.') . "</td></tr>
                        <tr style='background: #d4edda;'><td><strong>Caparra pagata:</strong></td><td>€" . number_format($notes['deposit_amount'], 2, ',', '.') . " ✅</td></tr>
                        <tr><td><strong>Saldo finale:</strong></td><td><strong>€" . number_format($payment_data['amount'], 2, ',', '.') . "</strong></td></tr>
                    </table>
                </div>
                
                <p style='text-align: center; margin: 30px 0;'>
                    <a href='{$payment_url}' 
                       style='background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>
                        Paga Saldo Finale
                    </a>
                </p>
                
                <div style='background: #fff3cd; padding: 15px; border-radius: 4px; margin: 20px 0;'>
                    <h4 style='margin: 0 0 10px; color: #856404;'>📅 Scadenza Saldo</h4>
                    <p style='margin: 0; color: #856404;'>Il saldo deve essere completato entro il <strong>{$expires_date}</strong>.</p>
                </div>
                
                <hr style='margin: 30px 0; border: none; height: 1px; background: #ddd;'>
                
                <p style='font-size: 14px; color: #666;'>
                    Grazie per aver scelto i nostri servizi!<br>
                    Per qualsiasi domanda, contattaci immediatamente.
                </p>
            </div>
        </body>
        </html>
        ";

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];

        return wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Gestisce completamento caparra
     */
    public function handle_deposit_completion($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $payment_hash = $order->get_meta('_btr_payment_hash');
        $payment_type = $order->get_meta('_btr_payment_type');
        
        if ($payment_type === 'deposit' && $payment_hash) {
            // Aggiorna status caparra
            global $wpdb;
            $table_payments = $wpdb->prefix . 'btr_group_payments';
            
            $wpdb->update(
                $table_payments,
                [
                    'payment_status' => 'paid',
                    'paid_at' => current_time('mysql'),
                    'wc_order_id' => $order_id
                ],
                ['payment_hash' => $payment_hash]
            );

            // TODO: Opzionalmente genera automaticamente link saldo
            // $this->auto_generate_balance_link($payment_hash);
        }
    }

    /**
     * Controlla scadenze saldi
     */
    public function check_balance_deadlines() {
        global $wpdb;
        
        $table_payments = $wpdb->prefix . 'btr_group_payments';
        
        // Trova saldi in scadenza (prossimi 7 giorni)
        $upcoming_deadlines = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$table_payments}
            WHERE payment_type = %s 
            AND payment_status = %s
            AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL %d DAY)",
            'balance',
            'pending',
            7
        ));

        foreach ($upcoming_deadlines as $payment) {
            // Invia reminder email
            $this->send_balance_reminder_email($payment->payment_id);
        }
    }

    /**
     * Admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=preventivo',
            'Gestione Caparre e Saldi',
            'Caparre e Saldi',
            'edit_posts',
            'btr-deposit-balance',
            [$this, 'admin_page']
        );
    }

    public function admin_page() {
        include BTR_PLUGIN_DIR . 'admin/views/deposit-balance-admin.php';
    }
}
