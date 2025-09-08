<?php
/**
 * Gestione email per il sistema di pagamenti
 * 
 * @package BornToRideBooking
 * @since 1.0.98
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Payment_Email_Manager {
    
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
        // Email templates
        add_filter('woocommerce_email_classes', [$this, 'add_email_classes']);
        
        // Email triggers
        add_action('btr_payment_plan_created', [$this, 'send_payment_plan_confirmation'], 10, 2);
        add_action('btr_group_payment_created', [$this, 'send_payment_link'], 10, 2);
        add_action('btr_payment_completed', [$this, 'send_payment_confirmation'], 10, 2);
        add_action('btr_payment_failed', [$this, 'send_payment_failed_notification'], 10, 2);
        
        // Scheduled reminders
        add_action('btr_send_payment_reminder', [$this, 'send_scheduled_reminder']);
        
        // Enhanced reminders for new system
        add_action('btr_send_enhanced_reminder', [$this, 'send_enhanced_reminder'], 10, 3);
    }
    
    /**
     * Invia conferma creazione piano di pagamento
     */
    public function send_payment_plan_confirmation($preventivo_id, $plan_type) {
        // Recupera dati necessari
        $anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
        $package_id = get_post_meta($preventivo_id, '_pacchetto_id', true);
        $package_title = get_the_title($package_id);
        
        if (empty($anagrafici[0]['email'])) {
            return false;
        }
        
        $to = $anagrafici[0]['email'];
        $subject = sprintf(
            __('Conferma Piano di Pagamento - %s', 'born-to-ride-booking'),
            $package_title
        );
        
        // Prepara dati per template
        $template_data = [
            'preventivo_id' => $preventivo_id,
            'plan_type' => $plan_type,
            'package_title' => $package_title,
            'anagrafici' => $anagrafici,
            'plan_type_label' => $this->get_plan_type_label($plan_type)
        ];
        
        // Genera contenuto email
        $content = $this->get_email_template('payment-plan-confirmation', $template_data);
        
        // Invia email
        return $this->send_email($to, $subject, $content);
    }
    
    /**
     * Invia link di pagamento individuale
     */
    public function send_payment_link($payment_id, $payment_data) {
        global $wpdb;
        
        // Recupera dati pagamento
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}btr_group_payments WHERE payment_id = %d",
            $payment_id
        ));
        
        if (!$payment) {
            return false;
        }
        
        // Recupera dati preventivo e pacchetto
        $preventivo_id = $payment->preventivo_id;
        $package_id = get_post_meta($preventivo_id, '_pacchetto_id', true);
        $package_title = get_the_title($package_id);
        $data_pacchetto = get_post_meta($preventivo_id, '_data_pacchetto', true);
        
        $to = $payment->participant_email;
        $subject = sprintf(
            __('Il tuo link di pagamento per %s', 'born-to-ride-booking'),
            $package_title
        );
        
        // Genera URL pagamento
        $payment_url = home_url('/pagamento-gruppo/' . $payment->payment_hash);
        
        // Prepara dati template
        $template_data = [
            'payment' => $payment,
            'payment_url' => $payment_url,
            'package_title' => $package_title,
            'data_pacchetto' => $data_pacchetto,
            'amount_formatted' => btr_format_price_i18n($payment->amount),
            'expires_date' => date_i18n('d F Y', strtotime($payment->expires_at))
        ];
        
        // Genera contenuto
        $content = $this->get_email_template('payment-link', $template_data);
        
        // Invia email
        $sent = $this->send_email($to, $subject, $content);
        
        // Log invio
        if ($sent && defined('BTR_DEBUG') && BTR_DEBUG) {
            error_log(sprintf(
                '[BTR Email] Link pagamento inviato: Payment ID=%d, Email=%s',
                $payment_id,
                $to
            ));
        }
        
        return $sent;
    }
    
    /**
     * Invia conferma pagamento completato
     */
    public function send_payment_confirmation($payment_id, $order_id) {
        global $wpdb;
        
        // Recupera dati pagamento
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}btr_group_payments WHERE payment_id = %d",
            $payment_id
        ));
        
        if (!$payment) {
            return false;
        }
        
        // Recupera dati ordine WooCommerce
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }
        
        $preventivo_id = $payment->preventivo_id;
        $package_id = get_post_meta($preventivo_id, '_pacchetto_id', true);
        $package_title = get_the_title($package_id);
        
        $to = $payment->participant_email;
        $subject = sprintf(
            __('Pagamento Confermato - %s', 'born-to-ride-booking'),
            $package_title
        );
        
        // Prepara dati template
        $template_data = [
            'payment' => $payment,
            'order' => $order,
            'package_title' => $package_title,
            'amount_formatted' => btr_format_price_i18n($payment->amount),
            'order_number' => $order->get_order_number(),
            'payment_method' => $order->get_payment_method_title()
        ];
        
        // Genera contenuto
        $content = $this->get_email_template('payment-confirmation', $template_data);
        
        // Invia email
        return $this->send_email($to, $subject, $content);
    }
    
    /**
     * Invia notifica pagamento fallito
     */
    public function send_payment_failed_notification($payment_id, $reason = '') {
        global $wpdb;
        
        $payment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}btr_group_payments WHERE payment_id = %d",
            $payment_id
        ));
        
        if (!$payment) {
            return false;
        }
        
        $preventivo_id = $payment->preventivo_id;
        $package_id = get_post_meta($preventivo_id, '_pacchetto_id', true);
        $package_title = get_the_title($package_id);
        
        $to = $payment->participant_email;
        $subject = sprintf(
            __('Pagamento Non Riuscito - %s', 'born-to-ride-booking'),
            $package_title
        );
        
        // Genera nuovo link di pagamento
        $payment_url = home_url('/pagamento-gruppo/' . $payment->payment_hash);
        
        // Prepara dati template
        $template_data = [
            'payment' => $payment,
            'payment_url' => $payment_url,
            'package_title' => $package_title,
            'reason' => $reason,
            'support_email' => get_option('admin_email')
        ];
        
        // Genera contenuto
        $content = $this->get_email_template('payment-failed', $template_data);
        
        // Invia email
        return $this->send_email($to, $subject, $content);
    }
    
    /**
     * Invia reminder programmato
     */
    public function send_scheduled_reminder($reminder_id) {
        global $wpdb;
        
        // Recupera dati reminder
        $reminder = $wpdb->get_row($wpdb->prepare(
            "SELECT r.*, p.* 
             FROM {$wpdb->prefix}btr_payment_reminders r
             JOIN {$wpdb->prefix}btr_group_payments p ON r.payment_id = p.payment_id
             WHERE r.id = %d AND r.status = 'pending'",
            $reminder_id
        ));
        
        if (!$reminder) {
            return false;
        }
        
        // Verifica che il pagamento sia ancora pending
        if ($reminder->payment_status !== 'pending') {
            // Cancella reminder se pagamento già completato
            $wpdb->update(
                $wpdb->prefix . 'btr_payment_reminders',
                ['status' => 'cancelled'],
                ['id' => $reminder_id]
            );
            return false;
        }
        
        // Invia reminder basato sul tipo
        $sent = false;
        switch ($reminder->reminder_type) {
            case 'payment_due':
                $sent = $this->send_payment_due_reminder($reminder);
                break;
                
            case 'payment_overdue':
                $sent = $this->send_payment_overdue_reminder($reminder);
                break;
                
            case 'balance_due':
                $sent = $this->send_balance_due_reminder($reminder);
                break;
        }
        
        // Aggiorna stato reminder
        $wpdb->update(
            $wpdb->prefix . 'btr_payment_reminders',
            [
                'status' => $sent ? 'sent' : 'failed',
                'sent_at' => $sent ? current_time('mysql') : null,
                'attempts' => $reminder->attempts + 1
            ],
            ['id' => $reminder_id]
        );
        
        return $sent;
    }
    
    /**
     * Invia reminder pagamento in scadenza
     */
    private function send_payment_due_reminder($reminder) {
        $preventivo_id = $reminder->preventivo_id;
        $package_id = get_post_meta($preventivo_id, '_pacchetto_id', true);
        $package_title = get_the_title($package_id);
        
        $to = $reminder->participant_email;
        $subject = sprintf(
            __('Promemoria: Pagamento in scadenza - %s', 'born-to-ride-booking'),
            $package_title
        );
        
        // Calcola giorni alla scadenza
        $days_until_expiry = ceil((strtotime($reminder->expires_at) - current_time('timestamp')) / DAY_IN_SECONDS);
        
        // Genera URL pagamento
        $payment_url = home_url('/pagamento-gruppo/' . $reminder->payment_hash);
        
        // Prepara dati template
        $template_data = [
            'payment' => $reminder,
            'payment_url' => $payment_url,
            'package_title' => $package_title,
            'days_until_expiry' => $days_until_expiry,
            'amount_formatted' => btr_format_price_i18n($reminder->amount),
            'expires_date' => date_i18n('d F Y', strtotime($reminder->expires_at))
        ];
        
        // Genera contenuto
        $content = $this->get_email_template('payment-due-reminder', $template_data);
        
        // Invia email
        return $this->send_email($to, $subject, $content);
    }
    
    /**
     * Invia reminder pagamento scaduto
     */
    private function send_payment_overdue_reminder($reminder) {
        $preventivo_id = $reminder->preventivo_id;
        $package_id = get_post_meta($preventivo_id, '_pacchetto_id', true);
        $package_title = get_the_title($package_id);
        
        $to = $reminder->participant_email;
        $subject = sprintf(
            __('Urgente: Pagamento Scaduto - %s', 'born-to-ride-booking'),
            $package_title
        );
        
        // Prepara dati template
        $template_data = [
            'payment' => $reminder,
            'package_title' => $package_title,
            'amount_formatted' => btr_format_price_i18n($reminder->amount),
            'support_email' => get_option('admin_email'),
            'support_phone' => get_option('btr_support_phone', '')
        ];
        
        // Genera contenuto
        $content = $this->get_email_template('payment-overdue-reminder', $template_data);
        
        // Invia email
        return $this->send_email($to, $subject, $content);
    }
    
    /**
     * Invia reminder saldo in scadenza
     */
    private function send_balance_due_reminder($reminder) {
        // Implementazione specifica per reminder saldo
        // (per modalità caparra + saldo)
        return $this->send_payment_due_reminder($reminder);
    }
    
    /**
     * Carica template email
     */
    private function get_email_template($template_name, $data = []) {
        // Estrai variabili per il template
        extract($data);
        
        // Cerca template personalizzato nel tema
        $template_paths = [
            get_stylesheet_directory() . '/born-to-ride-booking/emails/' . $template_name . '.php',
            get_template_directory() . '/born-to-ride-booking/emails/' . $template_name . '.php',
            BTR_PLUGIN_DIR . 'templates/emails/' . $template_name . '.php'
        ];
        
        $template_file = '';
        foreach ($template_paths as $path) {
            if (file_exists($path)) {
                $template_file = $path;
                break;
            }
        }
        
        if (empty($template_file)) {
            // Usa template inline di default
            return $this->get_default_email_template($template_name, $data);
        }
        
        ob_start();
        include $template_file;
        return ob_get_clean();
    }
    
    /**
     * Template email di default
     */
    private function get_default_email_template($template_name, $data) {
        $html = $this->get_email_header($data);
        
        switch ($template_name) {
            case 'payment-link':
                $html .= $this->get_payment_link_content($data);
                break;
                
            case 'payment-confirmation':
                $html .= $this->get_payment_confirmation_content($data);
                break;
                
            case 'payment-due-reminder':
                $html .= $this->get_payment_reminder_content($data);
                break;
                
            default:
                $html .= $this->get_generic_content($data);
        }
        
        $html .= $this->get_email_footer($data);
        
        return $html;
    }
    
    /**
     * Header email HTML
     */
    private function get_email_header($data) {
        $site_name = get_bloginfo('name');
        $logo_url = get_option('btr_email_logo', '');
        
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html($site_name); ?></title>
        </head>
        <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px 0;">
                <tr>
                    <td align="center">
                        <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <!-- Header -->
                            <tr>
                                <td style="background-color: #0097c5; padding: 30px; text-align: center;">
                                    <?php if ($logo_url): ?>
                                        <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($site_name); ?>" style="max-height: 60px; max-width: 200px;">
                                    <?php else: ?>
                                        <h1 style="color: #ffffff; margin: 0; font-size: 28px;">
                                            <?php echo esc_html($site_name); ?>
                                        </h1>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <!-- Content -->
                            <tr>
                                <td style="padding: 40px 30px;">
        <?php
        return ob_get_clean();
    }
    
    /**
     * Footer email HTML
     */
    private function get_email_footer($data) {
        $support_email = get_option('admin_email');
        $support_phone = get_option('btr_support_phone', '');
        
        ob_start();
        ?>
                                </td>
                            </tr>
                            <!-- Footer -->
                            <tr>
                                <td style="background-color: #f9f9f9; padding: 30px; text-align: center; border-top: 1px solid #e0e0e0;">
                                    <p style="margin: 0 0 10px; color: #666; font-size: 14px;">
                                        <?php esc_html_e('Hai bisogno di aiuto?', 'born-to-ride-booking'); ?>
                                    </p>
                                    <p style="margin: 0; color: #666; font-size: 14px;">
                                        <a href="mailto:<?php echo esc_attr($support_email); ?>" style="color: #0097c5; text-decoration: none;">
                                            <?php echo esc_html($support_email); ?>
                                        </a>
                                        <?php if ($support_phone): ?>
                                            <br>
                                            <a href="tel:<?php echo esc_attr($support_phone); ?>" style="color: #0097c5; text-decoration: none;">
                                                <?php echo esc_html($support_phone); ?>
                                            </a>
                                        <?php endif; ?>
                                    </p>
                                    <p style="margin: 20px 0 0; color: #999; font-size: 12px;">
                                        © <?php echo date('Y'); ?> <?php echo esc_html(get_bloginfo('name')); ?>. 
                                        <?php esc_html_e('Tutti i diritti riservati.', 'born-to-ride-booking'); ?>
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
     * Contenuto email link pagamento
     */
    private function get_payment_link_content($data) {
        extract($data);
        ob_start();
        ?>
        <h2 style="color: #333; margin: 0 0 20px; font-size: 24px;">
            <?php esc_html_e('Il tuo link di pagamento è pronto', 'born-to-ride-booking'); ?>
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
                __('Ecco il tuo link personale per completare il pagamento della quota per il viaggio "%s".', 'born-to-ride-booking'),
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
                            <?php echo $amount_formatted; ?>
                        </span>
                    </p>
                    
                    <p style="margin: 0 0 10px; color: #333;">
                        <strong><?php esc_html_e('Data partenza:', 'born-to-ride-booking'); ?></strong>
                        <?php echo esc_html(date_i18n('d F Y', strtotime($data_pacchetto))); ?>
                    </p>
                    
                    <p style="margin: 0; color: #333;">
                        <strong><?php esc_html_e('Scadenza pagamento:', 'born-to-ride-booking'); ?></strong>
                        <?php echo esc_html($expires_date); ?>
                    </p>
                </td>
            </tr>
        </table>
        
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
        <?php
        return ob_get_clean();
    }
    
    /**
     * Contenuto email conferma pagamento
     */
    private function get_payment_confirmation_content($data) {
        extract($data);
        ob_start();
        ?>
        <h2 style="color: #333; margin: 0 0 20px; font-size: 24px;">
            <?php esc_html_e('Pagamento Confermato!', 'born-to-ride-booking'); ?>
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
                __('Il tuo pagamento per il viaggio "%s" è stato ricevuto con successo.', 'born-to-ride-booking'),
                esc_html($package_title)
            );
            ?>
        </p>
        
        <table width="100%" cellpadding="15" cellspacing="0" style="background-color: #e8f5e9; border-radius: 4px; margin: 20px 0;">
            <tr>
                <td>
                    <h3 style="margin: 0 0 15px; color: #2e7d32; font-size: 18px;">
                        <?php esc_html_e('Dettagli del pagamento', 'born-to-ride-booking'); ?>
                    </h3>
                    
                    <p style="margin: 0 0 8px; color: #333;">
                        <strong><?php esc_html_e('Numero ordine:', 'born-to-ride-booking'); ?></strong>
                        <?php echo esc_html($order_number); ?>
                    </p>
                    
                    <p style="margin: 0 0 8px; color: #333;">
                        <strong><?php esc_html_e('Importo pagato:', 'born-to-ride-booking'); ?></strong>
                        <?php echo $amount_formatted; ?>
                    </p>
                    
                    <p style="margin: 0 0 8px; color: #333;">
                        <strong><?php esc_html_e('Metodo di pagamento:', 'born-to-ride-booking'); ?></strong>
                        <?php echo esc_html($payment_method); ?>
                    </p>
                    
                    <p style="margin: 0; color: #333;">
                        <strong><?php esc_html_e('Data pagamento:', 'born-to-ride-booking'); ?></strong>
                        <?php echo esc_html(date_i18n('d F Y alle H:i')); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <p style="color: #666; font-size: 16px; line-height: 1.6; margin: 20px 0;">
            <?php esc_html_e('Conserva questa email come ricevuta del tuo pagamento. Riceverai ulteriori informazioni sul viaggio man mano che si avvicina la data di partenza.', 'born-to-ride-booking'); ?>
        </p>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Contenuto email reminder pagamento
     */
    private function get_payment_reminder_content($data) {
        extract($data);
        ob_start();
        ?>
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
            if ($days_until_expiry > 0) {
                printf(
                    __('Ti ricordiamo che hai ancora %d giorni per completare il pagamento della tua quota per il viaggio "%s".', 'born-to-ride-booking'),
                    $days_until_expiry,
                    esc_html($package_title)
                );
            } else {
                printf(
                    __('Il tuo pagamento per il viaggio "%s" è scaduto oggi. Completa il pagamento al più presto per non perdere il tuo posto.', 'born-to-ride-booking'),
                    esc_html($package_title)
                );
            }
            ?>
        </p>
        
        <table width="100%" cellpadding="15" cellspacing="0" style="background-color: #fff3cd; border-radius: 4px; margin: 20px 0;">
            <tr>
                <td>
                    <p style="margin: 0 0 10px; color: #856404;">
                        <strong><?php esc_html_e('Importo da pagare:', 'born-to-ride-booking'); ?></strong>
                        <span style="font-size: 24px; color: #0097c5;">
                            <?php echo $amount_formatted; ?>
                        </span>
                    </p>
                    
                    <p style="margin: 0; color: #856404;">
                        <strong><?php esc_html_e('Scadenza:', 'born-to-ride-booking'); ?></strong>
                        <?php echo esc_html($expires_date); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <table width="100%" cellpadding="0" cellspacing="0">
            <tr>
                <td align="center" style="padding: 20px 0;">
                    <a href="<?php echo esc_url($payment_url); ?>" 
                       style="display: inline-block; padding: 15px 40px; background-color: #0097c5; color: #ffffff; text-decoration: none; border-radius: 4px; font-size: 18px; font-weight: bold;">
                        <?php esc_html_e('Completa il Pagamento', 'born-to-ride-booking'); ?>
                    </a>
                </td>
            </tr>
        </table>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Contenuto email generico
     */
    private function get_generic_content($data) {
        return '<p>' . __('Contenuto email non disponibile.', 'born-to-ride-booking') . '</p>';
    }
    
    /**
     * Invia email
     */
    private function send_email($to, $subject, $content, $headers = []) {
        // Headers di default
        $default_headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];
        
        $headers = array_merge($default_headers, $headers);
        
        // Filtra contenuto
        $content = apply_filters('btr_payment_email_content', $content, $subject, $to);
        
        // Invia email
        $sent = wp_mail($to, $subject, $content, $headers);
        
        // Log per debug
        if (defined('BTR_DEBUG') && BTR_DEBUG) {
            error_log(sprintf(
                '[BTR Email] %s - To: %s, Subject: %s',
                $sent ? 'Sent' : 'Failed',
                $to,
                $subject
            ));
        }
        
        return $sent;
    }
    
    /**
     * Ottieni label tipo piano
     */
    private function get_plan_type_label($plan_type) {
        $labels = [
            'full' => __('Pagamento Completo', 'born-to-ride-booking'),
            'deposit_balance' => __('Caparra + Saldo', 'born-to-ride-booking'),
            'group_split' => __('Suddivisione Gruppo', 'born-to-ride-booking')
        ];
        
        return $labels[$plan_type] ?? $plan_type;
    }
    
    /**
     * Send enhanced payment reminder for new order shares system
     * 
     * @param string $email Recipient email
     * @param string $reminder_type Type of reminder
     * @param array $email_data Email template data
     * @return bool Success status
     */
    public function send_payment_reminder_enhanced($email, $reminder_type, $email_data) {
        if (empty($email) || !is_email($email)) {
            btr_debug_log('[BTR Email] Invalid email address: ' . $email);
            return false;
        }
        
        try {
            // Determine language from email data or site settings
            $language = $email_data['language'] ?? $this->get_user_language($email);
            
            // Get template data for specific reminder type
            $template_config = $this->get_reminder_template_config($reminder_type, $language);
            
            if (!$template_config) {
                btr_debug_log('[BTR Email] Unknown reminder type: ' . $reminder_type);
                return false;
            }
            
            // Prepare subject
            $subject = sprintf(
                $template_config['subject_template'],
                $this->get_package_title_from_order($email_data['order_id'])
            );
            
            // Prepare template data with localization
            $template_data = array_merge($email_data, [
                'language' => $language,
                'template_config' => $template_config,
                'site_name' => get_bloginfo('name'),
                'site_url' => home_url(),
                'formatted_amount' => $this->format_currency($email_data['amount_assigned'], $email_data['currency']),
                'formatted_expiry' => $this->format_date($email_data['token_expires_at'], $language),
                'days_to_expiry' => $this->calculate_days_to_expiry($email_data['token_expires_at'])
            ]);
            
            // Generate email content
            $content = $this->get_enhanced_email_template($reminder_type, $template_data, $language);
            
            // Send email with proper headers
            $headers = $this->get_email_headers($language);
            $sent = wp_mail($email, $subject, $content, $headers);
            
            if ($sent) {
                btr_debug_log(sprintf(
                    '[BTR Email] Enhanced reminder sent: %s to %s (Type: %s, Lang: %s)',
                    $reminder_type,
                    $email,
                    $reminder_type,
                    $language
                ));
                
                // Track email statistics
                $this->track_email_sent($reminder_type, $language);
            } else {
                btr_debug_log(sprintf(
                    '[BTR Email] Failed to send enhanced reminder: %s to %s',
                    $reminder_type,
                    $email
                ));
            }
            
            return $sent;
            
        } catch (Exception $e) {
            btr_debug_log('[BTR Email] Error sending enhanced reminder: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get reminder template configuration
     * 
     * @param string $reminder_type Type of reminder
     * @param string $language Language code (it|en)
     * @return array|null Template configuration
     */
    private function get_reminder_template_config($reminder_type, $language = 'it') {
        $configs = [
            'payment_reminder' => [
                'it' => [
                    'subject_template' => 'Promemoria Pagamento - %s',
                    'urgency_level' => 'normal',
                    'color' => '#0097c5',
                    'title' => 'Promemoria Pagamento',
                    'urgency_text' => 'Ti ricordiamo che il pagamento per il viaggio è ancora in attesa.',
                    'cta_text' => 'Paga Ora'
                ],
                'en' => [
                    'subject_template' => 'Payment Reminder - %s',
                    'urgency_level' => 'normal',
                    'color' => '#0097c5',
                    'title' => 'Payment Reminder',
                    'urgency_text' => 'We remind you that the payment for your trip is still pending.',
                    'cta_text' => 'Pay Now'
                ]
            ],
            'expires_tomorrow' => [
                'it' => [
                    'subject_template' => 'Pagamento in Scadenza Domani - %s',
                    'urgency_level' => 'high',
                    'color' => '#ff9800',
                    'title' => 'Pagamento in Scadenza',
                    'urgency_text' => 'Il tuo pagamento scade domani! Non perdere l\'opportunità.',
                    'cta_text' => 'Paga Subito'
                ],
                'en' => [
                    'subject_template' => 'Payment Expires Tomorrow - %s',
                    'urgency_level' => 'high',
                    'color' => '#ff9800',
                    'title' => 'Payment Expiring',
                    'urgency_text' => 'Your payment expires tomorrow! Don\'t miss the opportunity.',
                    'cta_text' => 'Pay Now'
                ]
            ],
            'expires_today' => [
                'it' => [
                    'subject_template' => '🚨 Pagamento Scade Oggi - %s',
                    'urgency_level' => 'urgent',
                    'color' => '#f44336',
                    'title' => 'Pagamento Scade Oggi',
                    'urgency_text' => 'ATTENZIONE: Il tuo pagamento scade oggi! Paga immediatamente per non perdere il posto.',
                    'cta_text' => 'Paga Immediatamente'
                ],
                'en' => [
                    'subject_template' => '🚨 Payment Expires Today - %s',
                    'urgency_level' => 'urgent',
                    'color' => '#f44336',
                    'title' => 'Payment Expires Today',
                    'urgency_text' => 'WARNING: Your payment expires today! Pay immediately to secure your spot.',
                    'cta_text' => 'Pay Immediately'
                ]
            ],
            'expires_soon' => [
                'it' => [
                    'subject_template' => 'Pagamento in Scadenza - %s',
                    'urgency_level' => 'medium',
                    'color' => '#ff9800',
                    'title' => 'Pagamento in Scadenza',
                    'urgency_text' => 'Il tuo pagamento scade tra pochi giorni. Completa ora per garantire il tuo posto.',
                    'cta_text' => 'Paga Ora'
                ],
                'en' => [
                    'subject_template' => 'Payment Expiring Soon - %s',
                    'urgency_level' => 'medium',
                    'color' => '#ff9800',
                    'title' => 'Payment Expiring Soon',
                    'urgency_text' => 'Your payment expires in a few days. Complete it now to secure your spot.',
                    'cta_text' => 'Pay Now'
                ]
            ],
            'overdue' => [
                'it' => [
                    'subject_template' => '⚠️ Pagamento Scaduto - %s',
                    'urgency_level' => 'critical',
                    'color' => '#d32f2f',
                    'title' => 'Pagamento Scaduto',
                    'urgency_text' => 'Il tuo pagamento è scaduto. Contatta l\'organizzatore per verificare la disponibilità.',
                    'cta_text' => 'Contatta Organizzatore'
                ],
                'en' => [
                    'subject_template' => '⚠️ Payment Overdue - %s',
                    'urgency_level' => 'critical',
                    'color' => '#d32f2f',
                    'title' => 'Payment Overdue',
                    'urgency_text' => 'Your payment is overdue. Contact the organizer to check availability.',
                    'cta_text' => 'Contact Organizer'
                ]
            ],
            'payment_reminder_retry' => [
                'it' => [
                    'subject_template' => 'Secondo Promemoria - %s',
                    'urgency_level' => 'medium',
                    'color' => '#ff9800',
                    'title' => 'Secondo Promemoria',
                    'urgency_text' => 'Non abbiamo ancora ricevuto il tuo pagamento. Ti ricordiamo di completarlo al più presto.',
                    'cta_text' => 'Completa Pagamento'
                ],
                'en' => [
                    'subject_template' => 'Second Reminder - %s',
                    'urgency_level' => 'medium',
                    'color' => '#ff9800',
                    'title' => 'Second Reminder',
                    'urgency_text' => 'We haven\'t received your payment yet. Please complete it as soon as possible.',
                    'cta_text' => 'Complete Payment'
                ]
            ]
        ];
        
        return $configs[$reminder_type][$language] ?? null;
    }
    
    /**
     * Get user language based on email or site settings
     * 
     * @param string $email User email
     * @return string Language code
     */
    private function get_user_language($email) {
        // Check if user has language preference stored
        $user = get_user_by('email', $email);
        if ($user) {
            $user_lang = get_user_meta($user->ID, 'locale', true);
            if ($user_lang) {
                return substr($user_lang, 0, 2);
            }
        }
        
        // Check site locale
        $site_locale = get_locale();
        if (strpos($site_locale, 'en') === 0) {
            return 'en';
        }
        
        // Default to Italian
        return 'it';
    }
    
    /**
     * Get package title from order ID
     * 
     * @param int $order_id WooCommerce order ID
     * @return string Package title
     */
    private function get_package_title_from_order($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return 'Viaggio';
        }
        
        // Get first product name as package title
        $items = $order->get_items();
        if (!empty($items)) {
            $first_item = reset($items);
            return $first_item->get_name();
        }
        
        return 'Viaggio';
    }
    
    /**
     * Format currency amount
     * 
     * @param float $amount Amount
     * @param string $currency Currency code
     * @return string Formatted amount
     */
    private function format_currency($amount, $currency) {
        return number_format($amount, 2, ',', '.') . ' ' . strtoupper($currency);
    }
    
    /**
     * Format date according to language
     * 
     * @param string $date Date string
     * @param string $language Language code
     * @return string Formatted date
     */
    private function format_date($date, $language) {
        $timestamp = strtotime($date);
        
        if ($language === 'en') {
            return date('F j, Y \a\t H:i', $timestamp);
        }
        
        // Italian format
        $months = [
            1 => 'gennaio', 2 => 'febbraio', 3 => 'marzo', 4 => 'aprile',
            5 => 'maggio', 6 => 'giugno', 7 => 'luglio', 8 => 'agosto',
            9 => 'settembre', 10 => 'ottobre', 11 => 'novembre', 12 => 'dicembre'
        ];
        
        $day = date('j', $timestamp);
        $month = $months[(int)date('n', $timestamp)];
        $year = date('Y', $timestamp);
        $time = date('H:i', $timestamp);
        
        return "{$day} {$month} {$year} alle {$time}";
    }
    
    /**
     * Calculate days to expiry
     * 
     * @param string $expires_at Expiry datetime
     * @return int Days to expiry
     */
    private function calculate_days_to_expiry($expires_at) {
        $expiry_time = strtotime($expires_at);
        $current_time = current_time('timestamp');
        $diff_seconds = $expiry_time - $current_time;
        
        return max(0, floor($diff_seconds / DAY_IN_SECONDS));
    }
    
    /**
     * Get email headers with proper charset and language
     * 
     * @param string $language Language code
     * @return array Email headers
     */
    private function get_email_headers($language = 'it') {
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];
        
        if ($language === 'en') {
            $headers[] = 'Content-Language: en';
        } else {
            $headers[] = 'Content-Language: it';
        }
        
        return $headers;
    }
    
    /**
     * Track email statistics
     * 
     * @param string $reminder_type Type of reminder
     * @param string $language Language used
     */
    private function track_email_sent($reminder_type, $language) {
        $stats = get_option('btr_email_stats', []);
        
        $today = current_time('Y-m-d');
        if (!isset($stats[$today])) {
            $stats[$today] = [];
        }
        
        $key = "{$reminder_type}_{$language}";
        $stats[$today][$key] = ($stats[$today][$key] ?? 0) + 1;
        
        // Keep only last 30 days of stats
        $cutoff_date = date('Y-m-d', strtotime('-30 days'));
        foreach ($stats as $date => $data) {
            if ($date < $cutoff_date) {
                unset($stats[$date]);
            }
        }
        
        update_option('btr_email_stats', $stats);
    }
    
    /**
     * Get enhanced email template
     * 
     * @param string $reminder_type Type of reminder
     * @param array $template_data Template data
     * @param string $language Language code
     * @return string Email HTML content
     */
    private function get_enhanced_email_template($reminder_type, $template_data, $language) {
        // Try to load custom template first
        $custom_template = BTR_PLUGIN_DIR . "templates/emails/enhanced-reminder-{$reminder_type}-{$language}.php";
        $fallback_template = BTR_PLUGIN_DIR . "templates/emails/enhanced-reminder-{$language}.php";
        $default_template = BTR_PLUGIN_DIR . 'templates/emails/enhanced-reminder.php';
        
        $template_file = null;
        
        if (file_exists($custom_template)) {
            $template_file = $custom_template;
        } elseif (file_exists($fallback_template)) {
            $template_file = $fallback_template;
        } elseif (file_exists($default_template)) {
            $template_file = $default_template;
        }
        
        if ($template_file) {
            // Extract template data for use in template
            extract($template_data);
            
            ob_start();
            include $template_file;
            return ob_get_clean();
        }
        
        // Fallback to inline template
        return $this->get_inline_email_template($template_data, $language);
    }
    
    /**
     * Get inline email template as fallback
     * 
     * @param array $data Template data
     * @param string $language Language code
     * @return string Email HTML content
     */
    private function get_inline_email_template($data, $language) {
        $config = $data['template_config'];
        $color = $config['color'];
        
        $translations = [
            'it' => [
                'hello' => 'Ciao',
                'amount_label' => 'Importo da pagare:',
                'expiry_label' => 'Scadenza pagamento:',
                'link_text' => 'Se il pulsante non funziona, copia e incolla questo link nel tuo browser:',
                'footer_text' => 'Questa email è stata inviata da',
                'copyright' => 'Tutti i diritti riservati.'
            ],
            'en' => [
                'hello' => 'Hello',
                'amount_label' => 'Amount to pay:',
                'expiry_label' => 'Payment due:',
                'link_text' => 'If the button doesn\'t work, copy and paste this link in your browser:',
                'footer_text' => 'This email was sent by',
                'copyright' => 'All rights reserved.'
            ]
        ];
        
        $t = $translations[$language] ?? $translations['it'];
        
        return "
        <!DOCTYPE html>
        <html lang='{$language}'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$config['title']} - {$data['site_name']}</title>
        </head>
        <body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;'>
            <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='background-color: #f4f4f4;'>
                <tr>
                    <td align='center' style='padding: 40px 0;'>
                        <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='600' style='background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>
                            <tr>
                                <td style='background-color: {$color}; padding: 40px 30px; border-radius: 8px 8px 0 0;'>
                                    <h1 style='margin: 0; color: #ffffff; font-size: 28px; text-align: center;'>
                                        {$config['title']}
                                    </h1>
                                </td>
                            </tr>
                            <tr>
                                <td style='padding: 40px 30px;'>
                                    <p style='margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #333333;'>
                                        {$t['hello']} <strong>{$data['participant_name']}</strong>,
                                    </p>
                                    <p style='margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #333333;'>
                                        {$config['urgency_text']}
                                    </p>
                                    <div style='text-align: center; margin: 30px 0;'>
                                        <p style='margin: 0 0 10px 0; font-size: 16px; color: #666666;'>{$t['amount_label']}</p>
                                        <p style='margin: 0; font-size: 32px; font-weight: bold; color: {$color};'>{$data['formatted_amount']}</p>
                                    </div>
                                    <div style='text-align: center; margin: 30px 0;'>
                                        <a href='{$data['payment_link']}' style='display: inline-block; padding: 15px 40px; background-color: {$color}; color: #ffffff; text-decoration: none; font-size: 18px; font-weight: bold; border-radius: 50px;'>
                                            {$config['cta_text']}
                                        </a>
                                    </div>
                                    <p style='margin: 20px 0; font-size: 14px; line-height: 1.6; color: #666666; text-align: center;'>
                                        {$t['link_text']}
                                    </p>
                                    <p style='margin: 0; font-size: 12px; color: {$color}; text-align: center; word-break: break-all;'>
                                        {$data['payment_link']}
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <td style='background-color: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; text-align: center;'>
                                    <p style='margin: 0 0 10px 0; font-size: 14px; color: #666666;'>
                                        {$t['footer_text']} {$data['site_name']}
                                    </p>
                                    <p style='margin: 0; font-size: 12px; color: #999999;'>
                                        © " . date('Y') . " {$data['site_name']}. {$t['copyright']}
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>";
    }
    
    /**
     * Get email statistics
     * 
     * @return array Email statistics
     */
    public static function get_email_statistics() {
        $stats = get_option('btr_email_stats', []);
        $summary = [
            'total_sent' => 0,
            'last_7_days' => 0,
            'by_type' => [],
            'by_language' => ['it' => 0, 'en' => 0]
        ];
        
        $seven_days_ago = date('Y-m-d', strtotime('-7 days'));
        
        foreach ($stats as $date => $daily_stats) {
            foreach ($daily_stats as $key => $count) {
                $summary['total_sent'] += $count;
                
                if ($date >= $seven_days_ago) {
                    $summary['last_7_days'] += $count;
                }
                
                // Parse type and language
                $parts = explode('_', $key);
                if (count($parts) >= 2) {
                    $language = array_pop($parts);
                    $type = implode('_', $parts);
                    
                    $summary['by_type'][$type] = ($summary['by_type'][$type] ?? 0) + $count;
                    $summary['by_language'][$language] = ($summary['by_language'][$language] ?? 0) + $count;
                }
            }
        }
        
        return $summary;
    }
    
    /**
     * Aggiungi classi email custom a WooCommerce
     * 
     * @param array $email_classes
     * @return array
     */
    public function add_email_classes($email_classes) {
        // Per ora non aggiungiamo classi custom, solo placeholder
        // In futuro possiamo aggiungere classi email WooCommerce personalizzate
        return $email_classes;
    }
}

// Inizializza singleton
BTR_Payment_Email_Manager::get_instance();