<?php
/**
 * Classe per la gestione amministrativa dei piani di pagamento
 * 
 * @package BornToRideBooking
 * @since 1.0.98
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Payment_Plans_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('admin_init', [$this, 'handle_admin_actions']);
        
        // AJAX handlers
        add_action('wp_ajax_btr_get_payment_details', [$this, 'ajax_get_payment_details']);
        add_action('wp_ajax_btr_update_payment_status', [$this, 'ajax_update_payment_status']);
        
        // Export CSV
        add_action('admin_init', [$this, 'maybe_export_csv']);
    }
    
    /**
     * Aggiunge voci di menu admin
     */
    public function add_admin_menu() {
        // Menu principale
        add_submenu_page(
            'btr-booking',
            __('Piani di Pagamento', 'born-to-ride-booking'),
            __('Piani di Pagamento', 'born-to-ride-booking'),
            'manage_options',
            'btr-payment-plans',
            [$this, 'render_admin_page']
        );
        
        // Impostazioni
        add_submenu_page(
            'btr-booking',
            __('Impostazioni Pagamenti', 'born-to-ride-booking'),
            __('Impostazioni Pagamenti', 'born-to-ride-booking'),
            'manage_options',
            'btr-payment-plans-settings',
            [$this, 'render_settings_page']
        );
    }
    
    /**
     * Carica script admin
     */
    public function enqueue_admin_scripts($hook) {
        if (!in_array($hook, ['btr-booking_page_btr-payment-plans', 'btr-booking_page_btr-payment-plans-settings'])) {
            return;
        }
        
        wp_enqueue_script(
            'btr-payment-plans-admin',
            BTR_PLUGIN_URL . 'admin/js/payment-plans-admin.js',
            ['jquery'],
            BTR_VERSION,
            true
        );
        
        wp_localize_script('btr-payment-plans-admin', 'btr_payment_admin', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('btr_payment_admin_nonce'),
            'strings' => [
                'confirm_send_reminder' => __('Sei sicuro di voler inviare un promemoria di pagamento?', 'born-to-ride-booking'),
                'confirm_bulk_reminders' => __('Sei sicuro di voler inviare promemoria a tutti i pagamenti selezionati?', 'born-to-ride-booking'),
                'processing' => __('Elaborazione...', 'born-to-ride-booking'),
                'error' => __('Si è verificato un errore', 'born-to-ride-booking')
            ]
        ]);
        
        wp_enqueue_style(
            'btr-payment-plans-admin',
            BTR_PLUGIN_URL . 'admin/css/payment-plans-admin.css',
            [],
            BTR_VERSION
        );
    }
    
    /**
     * Gestisce azioni admin
     */
    public function handle_admin_actions() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'btr-payment-plans') {
            return;
        }
        
        if (!isset($_GET['action'])) {
            return;
        }
        
        $action = sanitize_text_field($_GET['action']);
        
        switch ($action) {
            case 'send_reminder':
                $this->handle_send_reminder();
                break;
                
            case 'export':
                $this->export_csv();
                break;
        }
    }
    
    /**
     * Gestisce invio promemoria singolo
     */
    private function handle_send_reminder() {
        if (!isset($_GET['payment_id']) || !isset($_GET['_wpnonce'])) {
            return;
        }
        
        $payment_id = intval($_GET['payment_id']);
        
        if (!wp_verify_nonce($_GET['_wpnonce'], 'send_reminder_' . $payment_id)) {
            wp_die(__('Nonce non valido', 'born-to-ride-booking'));
        }
        
        if (self::send_payment_reminder($payment_id)) {
            wp_redirect(add_query_arg([
                'page' => 'btr-payment-plans',
                'message' => 'reminder_sent'
            ], admin_url('admin.php')));
            exit;
        } else {
            wp_redirect(add_query_arg([
                'page' => 'btr-payment-plans',
                'error' => 'reminder_failed'
            ], admin_url('admin.php')));
            exit;
        }
    }
    
    /**
     * Invia promemoria di pagamento
     * 
     * @param int $payment_id
     * @return bool
     */
    public static function send_payment_reminder($payment_id) {
        global $wpdb;
        
        // Recupera dati pagamento
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}btr_group_payments WHERE payment_id = %d AND payment_status = 'pending'",
            $payment_id
        ));
        
        if (!$payment) {
            return false;
        }
        
        // Recupera dati preventivo
        $preventivo_id = $payment->preventivo_id;
        $package_id = get_post_meta($preventivo_id, '_pacchetto_id', true);
        $package_title = get_the_title($package_id);
        
        // Prepara dati email
        $to = $payment->participant_email;
        $subject = sprintf(
            __('Promemoria pagamento - %s', 'born-to-ride-booking'),
            $package_title
        );
        
        // Genera link pagamento
        $payment_url = home_url('/pagamento-gruppo/' . $payment->payment_hash);
        
        // Prepara contenuto email
        $email_content = self::get_reminder_email_template($payment, $package_title, $payment_url);
        
        // Invia email
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];
        
        $sent = wp_mail($to, $subject, $email_content, $headers);
        
        if ($sent) {
            // Registra invio nel database
            $wpdb->insert(
                $wpdb->prefix . 'btr_payment_reminders',
                [
                    'payment_id' => $payment_id,
                    'reminder_type' => 'payment_due',
                    'sent_at' => current_time('mysql'),
                    'status' => 'sent'
                ],
                ['%d', '%s', '%s', '%s']
            );
            
            // Log
            if (defined('BTR_DEBUG') && BTR_DEBUG) {
                error_log(sprintf(
                    '[BTR Payment Reminder] Promemoria inviato: Payment ID=%d, Email=%s',
                    $payment_id,
                    $to
                ));
            }
        }
        
        return $sent;
    }
    
    /**
     * Template email promemoria
     * 
     * @param object $payment
     * @param string $package_title
     * @param string $payment_url
     * @return string
     */
    private static function get_reminder_email_template($payment, $package_title, $payment_url) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php esc_html_e('Promemoria Pagamento', 'born-to-ride-booking'); ?></title>
        </head>
        <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px 0;">
                <tr>
                    <td align="center">
                        <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <!-- Header -->
                            <tr>
                                <td style="background-color: #0097c5; padding: 30px; text-align: center;">
                                    <h1 style="color: #ffffff; margin: 0; font-size: 28px;">
                                        <?php echo esc_html(get_bloginfo('name')); ?>
                                    </h1>
                                </td>
                            </tr>
                            
                            <!-- Content -->
                            <tr>
                                <td style="padding: 40px 30px;">
                                    <h2 style="color: #333; margin: 0 0 20px; font-size: 24px;">
                                        <?php esc_html_e('Promemoria Pagamento', 'born-to-ride-booking'); ?>
                                    </h2>
                                    
                                    <p style="color: #666; font-size: 16px; line-height: 1.6; margin: 0 0 20px;">
                                        <?php 
                                        printf(
                                            __('Ciao %s,', 'born-to-ride-booking'),
                                            esc_html($payment->group_member_name ?: $payment->participant_name)
                                        );
                                        ?>
                                    </p>
                                    
                                    <p style="color: #666; font-size: 16px; line-height: 1.6; margin: 0 0 20px;">
                                        <?php 
                                        printf(
                                            __('Ti ricordiamo che hai una quota in sospeso per il viaggio "%s".', 'born-to-ride-booking'),
                                            esc_html($package_title)
                                        );
                                        ?>
                                    </p>
                                    
                                    <table width="100%" cellpadding="15" cellspacing="0" style="background-color: #f9f9f9; border-radius: 4px; margin: 20px 0;">
                                        <tr>
                                            <td>
                                                <p style="margin: 0 0 10px; color: #333;">
                                                    <strong><?php esc_html_e('Importo da pagare:', 'born-to-ride-booking'); ?></strong>
                                                    <span style="font-size: 24px; color: #0097c5;">
                                                        <?php echo btr_format_price_i18n($payment->amount); ?>
                                                    </span>
                                                </p>
                                                
                                                <p style="margin: 0; color: #666;">
                                                    <strong><?php esc_html_e('Scadenza:', 'born-to-ride-booking'); ?></strong>
                                                    <?php echo esc_html(date_i18n('d F Y', strtotime($payment->expires_at))); ?>
                                                </p>
                                            </td>
                                        </tr>
                                    </table>
                                    
                                    <p style="color: #666; font-size: 16px; line-height: 1.6; margin: 20px 0;">
                                        <?php esc_html_e('Clicca sul pulsante sottostante per completare il pagamento:', 'born-to-ride-booking'); ?>
                                    </p>
                                    
                                    <table width="100%" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td align="center" style="padding: 20px 0;">
                                                <a href="<?php echo esc_url($payment_url); ?>" 
                                                   style="display: inline-block; padding: 15px 40px; background-color: #0097c5; color: #ffffff; text-decoration: none; border-radius: 4px; font-size: 18px; font-weight: bold;">
                                                    <?php esc_html_e('Paga Ora', 'born-to-ride-booking'); ?>
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                    
                                    <p style="color: #999; font-size: 14px; line-height: 1.6; margin: 20px 0 0;">
                                        <?php esc_html_e('Se il pulsante non funziona, copia e incolla questo link nel tuo browser:', 'born-to-ride-booking'); ?><br>
                                        <a href="<?php echo esc_url($payment_url); ?>" style="color: #0097c5;">
                                            <?php echo esc_html($payment_url); ?>
                                        </a>
                                    </p>
                                </td>
                            </tr>
                            
                            <!-- Footer -->
                            <tr>
                                <td style="background-color: #f9f9f9; padding: 30px; text-align: center; border-top: 1px solid #e0e0e0;">
                                    <p style="margin: 0 0 10px; color: #666; font-size: 14px;">
                                        <?php esc_html_e('Hai bisogno di aiuto?', 'born-to-ride-booking'); ?>
                                    </p>
                                    <p style="margin: 0; color: #666; font-size: 14px;">
                                        <a href="mailto:<?php echo esc_attr(get_option('admin_email')); ?>" style="color: #0097c5; text-decoration: none;">
                                            <?php echo esc_html(get_option('admin_email')); ?>
                                        </a>
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Esporta CSV
     */
    public function export_csv() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Non hai i permessi per eseguire questa azione', 'born-to-ride-booking'));
        }
        
        global $wpdb;
        
        // Query per export
        $payments = $wpdb->get_results("
            SELECT 
                gp.*,
                p.post_title as preventivo_title,
                pp.plan_type,
                pp.total_amount as plan_total
            FROM {$wpdb->prefix}btr_group_payments gp
            LEFT JOIN {$wpdb->posts} p ON gp.preventivo_id = p.ID
            LEFT JOIN {$wpdb->prefix}btr_payment_plans pp ON gp.preventivo_id = pp.preventivo_id
            ORDER BY gp.created_at DESC
        ");
        
        // Headers CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="pagamenti-gruppo-' . date('Y-m-d') . '.csv"');
        
        // Output CSV
        $output = fopen('php://output', 'w');
        
        // BOM per Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Intestazioni
        fputcsv($output, [
            'ID Pagamento',
            'Preventivo',
            'Partecipante',
            'Email',
            'Piano',
            'Importo',
            'Quota %',
            'Stato',
            'Data Creazione',
            'Data Pagamento',
            'Scadenza',
            'ID Ordine WC'
        ]);
        
        // Dati
        foreach ($payments as $payment) {
            $plan_labels = [
                'full' => 'Completo',
                'deposit_balance' => 'Caparra+Saldo',
                'group_split' => 'Gruppo'
            ];
            
            $status_labels = [
                'pending' => 'In attesa',
                'paid' => 'Pagato',
                'failed' => 'Fallito',
                'expired' => 'Scaduto'
            ];
            
            fputcsv($output, [
                $payment->payment_id,
                $payment->preventivo_title ?: 'Preventivo #' . $payment->preventivo_id,
                $payment->group_member_name ?: $payment->participant_name,
                $payment->participant_email,
                $plan_labels[$payment->payment_plan_type] ?? $payment->payment_plan_type,
                $payment->amount,
                $payment->share_percentage ? $payment->share_percentage . '%' : '',
                $status_labels[$payment->payment_status] ?? $payment->payment_status,
                date_i18n('d/m/Y H:i', strtotime($payment->created_at)),
                $payment->paid_at ? date_i18n('d/m/Y H:i', strtotime($payment->paid_at)) : '',
                date_i18n('d/m/Y', strtotime($payment->expires_at)),
                $payment->wc_order_id ?: ''
            ]);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Verifica se esportare CSV
     */
    public function maybe_export_csv() {
        if (isset($_GET['page']) && $_GET['page'] === 'btr-payment-plans' && isset($_GET['action']) && $_GET['action'] === 'export') {
            $this->export_csv();
        }
    }
    
    /**
     * Render pagina admin principale
     */
    public function render_admin_page() {
        // Mostra messaggi
        if (isset($_GET['message'])) {
            $message = sanitize_text_field($_GET['message']);
            switch ($message) {
                case 'reminder_sent':
                    echo '<div class="notice notice-success is-dismissible"><p>' . __('Promemoria inviato con successo.', 'born-to-ride-booking') . '</p></div>';
                    break;
            }
        }
        
        if (isset($_GET['error'])) {
            $error = sanitize_text_field($_GET['error']);
            switch ($error) {
                case 'reminder_failed':
                    echo '<div class="notice notice-error is-dismissible"><p>' . __('Errore nell\'invio del promemoria.', 'born-to-ride-booking') . '</p></div>';
                    break;
            }
        }
        
        // Include vista
        include BTR_PLUGIN_DIR . 'admin/views/payment-plans-admin.php';
    }
    
    /**
     * Render pagina impostazioni
     */
    public function render_settings_page() {
        // Salva impostazioni se inviate
        if (isset($_POST['submit'])) {
            check_admin_referer('btr_payment_settings');
            
            update_option('btr_default_deposit_percentage', intval($_POST['deposit_percentage']));
            update_option('btr_payment_expiry_days', intval($_POST['expiry_days']));
            update_option('btr_enable_auto_reminders', isset($_POST['enable_auto_reminders']) ? '1' : '0');
            update_option('btr_reminder_days_before', intval($_POST['reminder_days_before']));
            
            // Nuove opzioni bonifico
            update_option('btr_enable_payment_plans', isset($_POST['enable_payment_plans']) ? '1' : '0');
            update_option('btr_enable_bank_transfer_plans', isset($_POST['enable_bank_transfer_plans']) ? '1' : '0');
            update_option('btr_bank_transfer_info', wp_kses_post($_POST['bank_transfer_info'] ?? ''));
            
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Impostazioni salvate.', 'born-to-ride-booking') . '</p></div>';
        }
        
        // Recupera impostazioni correnti
        $deposit_percentage = get_option('btr_default_deposit_percentage', 30);
        $expiry_days = get_option('btr_payment_expiry_days', 30);
        $enable_auto_reminders = get_option('btr_enable_auto_reminders', '0');
        $reminder_days_before = get_option('btr_reminder_days_before', 7);
        
        // Nuove opzioni
        $enable_payment_plans = get_option('btr_enable_payment_plans', '1');
        $enable_bank_transfer_plans = get_option('btr_enable_bank_transfer_plans', '1');
        $bank_transfer_info = get_option('btr_bank_transfer_info', __('Il bonifico bancario supporta il pagamento con caparra o la suddivisione in gruppo. Seleziona la modalità preferita prima di procedere.', 'born-to-ride-booking'));
        ?>
        
        <div class="wrap">
            <h1><?php esc_html_e('Configurazione Sistema Pagamenti', 'born-to-ride-booking'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('btr_payment_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Abilita Piani di Pagamento', 'born-to-ride-booking'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="enable_payment_plans" 
                                       value="1"
                                       <?php checked($enable_payment_plans, '1'); ?>>
                                <?php esc_html_e('Abilita il sistema di piani di pagamento (caparra, gruppo, ecc.)', 'born-to-ride-booking'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Se disabilitato, i clienti potranno pagare solo l\'importo completo.', 'born-to-ride-booking'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Abilita Caparra/Gruppo con Bonifico', 'born-to-ride-booking'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="enable_bank_transfer_plans" 
                                       value="1"
                                       <?php checked($enable_bank_transfer_plans, '1'); ?>>
                                <?php esc_html_e('Permetti pagamento con caparra o suddivisione gruppo anche utilizzando bonifico bancario', 'born-to-ride-booking'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Se abilitato, i clienti potranno scegliere caparra/gruppo anche quando selezionano il bonifico come metodo di pagamento.', 'born-to-ride-booking'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="deposit_percentage">
                                <?php esc_html_e('Percentuale Caparra Default', 'born-to-ride-booking'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="deposit_percentage" 
                                   name="deposit_percentage" 
                                   value="<?php echo esc_attr($deposit_percentage); ?>"
                                   min="10"
                                   max="90"
                                   step="5"
                                   class="small-text">
                            <span>%</span>
                            <p class="description">
                                <?php esc_html_e('Percentuale di caparra predefinita per la modalità Caparra + Saldo', 'born-to-ride-booking'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="expiry_days">
                                <?php esc_html_e('Giorni Scadenza Pagamento', 'born-to-ride-booking'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="expiry_days" 
                                   name="expiry_days" 
                                   value="<?php echo esc_attr($expiry_days); ?>"
                                   min="1"
                                   max="365"
                                   class="small-text">
                            <span><?php esc_html_e('giorni', 'born-to-ride-booking'); ?></span>
                            <p class="description">
                                <?php esc_html_e('Numero di giorni di validità per i link di pagamento', 'born-to-ride-booking'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <?php esc_html_e('Promemoria Automatici', 'born-to-ride-booking'); ?>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="enable_auto_reminders" 
                                       value="1"
                                       <?php checked($enable_auto_reminders, '1'); ?>>
                                <?php esc_html_e('Abilita invio automatico promemoria', 'born-to-ride-booking'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Invia automaticamente promemoria prima della scadenza del pagamento', 'born-to-ride-booking'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="reminder_days_before">
                                <?php esc_html_e('Giorni Anticipo Promemoria', 'born-to-ride-booking'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="reminder_days_before" 
                                   name="reminder_days_before" 
                                   value="<?php echo esc_attr($reminder_days_before); ?>"
                                   min="1"
                                   max="30"
                                   class="small-text">
                            <span><?php esc_html_e('giorni prima della scadenza', 'born-to-ride-booking'); ?></span>
                            <p class="description">
                                <?php esc_html_e('Quando inviare il promemoria automatico prima della scadenza', 'born-to-ride-booking'); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="bank_transfer_info">
                                <?php esc_html_e('Testo Informativo Bonifico', 'born-to-ride-booking'); ?>
                            </label>
                        </th>
                        <td>
                            <textarea id="bank_transfer_info" 
                                      name="bank_transfer_info" 
                                      rows="3" 
                                      cols="50" 
                                      class="large-text"><?php echo esc_textarea($bank_transfer_info); ?></textarea>
                            <p class="description">
                                <?php esc_html_e('Testo mostrato ai clienti quando selezionano bonifico bancario con opzioni caparra/gruppo.', 'born-to-ride-booking'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * AJAX: Ottieni dettagli pagamento
     */
    public function ajax_get_payment_details() {
        check_ajax_referer('btr_payment_admin_nonce', 'nonce');
        
        $payment_id = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;
        
        if (!$payment_id) {
            wp_send_json_error(['message' => __('ID pagamento non valido', 'born-to-ride-booking')]);
        }
        
        global $wpdb;
        
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}btr_group_payments WHERE payment_id = %d",
            $payment_id
        ));
        
        if (!$payment) {
            wp_send_json_error(['message' => __('Pagamento non trovato', 'born-to-ride-booking')]);
        }
        
        // Aggiungi informazioni aggiuntive
        $payment->preventivo_title = get_the_title($payment->preventivo_id);
        $payment->package_id = get_post_meta($payment->preventivo_id, '_pacchetto_id', true);
        $payment->package_title = get_the_title($payment->package_id);
        
        wp_send_json_success(['payment' => $payment]);
    }
    
    /**
     * AJAX: Aggiorna stato pagamento
     */
    public function ajax_update_payment_status() {
        check_ajax_referer('btr_payment_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permessi insufficienti', 'born-to-ride-booking')]);
        }
        
        $payment_id = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;
        $new_status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
        
        $valid_statuses = ['pending', 'paid', 'failed', 'expired'];
        
        if (!$payment_id || !in_array($new_status, $valid_statuses)) {
            wp_send_json_error(['message' => __('Dati non validi', 'born-to-ride-booking')]);
        }
        
        global $wpdb;
        
        $updated = $wpdb->update(
            $wpdb->prefix . 'btr_group_payments',
            [
                'payment_status' => $new_status,
                'paid_at' => ($new_status === 'paid') ? current_time('mysql') : null
            ],
            ['payment_id' => $payment_id],
            ['%s', '%s'],
            ['%d']
        );
        
        if ($updated !== false) {
            wp_send_json_success(['message' => __('Stato aggiornato con successo', 'born-to-ride-booking')]);
        } else {
            wp_send_json_error(['message' => __('Errore nell\'aggiornamento', 'born-to-ride-booking')]);
        }
    }
}

// Nota: Classe inizializzata nel file principale born-to-ride-booking.php linea 330