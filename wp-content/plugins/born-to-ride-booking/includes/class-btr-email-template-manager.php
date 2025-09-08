<?php
/**
 * Email Template Manager - Professional email template system
 * 
 * Manages email templates for payment system with:
 * - Responsive HTML/Text templates
 * - Multi-language support (IT/EN)
 * - Dynamic personalization
 * - A/B testing capability
 * - Preview and testing tools
 * 
 * @package BornToRideBooking
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Email_Template_Manager {
    
    /**
     * Template directory path
     */
    private $template_dir;
    
    /**
     * Supported languages
     */
    private $supported_languages = ['it', 'en'];
    
    /**
     * Default language
     */
    private $default_language = 'it';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->template_dir = BTR_PLUGIN_DIR . 'templates/emails/';
        
        // Ensure template directory exists
        $this->create_template_directory();
        
        // Add AJAX handlers for admin interface
        add_action('wp_ajax_btr_preview_email_template', [$this, 'ajax_preview_email_template']);
        add_action('wp_ajax_btr_send_test_email', [$this, 'ajax_send_test_email']);
        add_action('wp_ajax_btr_get_email_template_data', [$this, 'ajax_get_email_template_data']);
    }
    
    /**
     * Create template directory structure
     */
    private function create_template_directory() {
        if (!file_exists($this->template_dir)) {
            wp_mkdir_p($this->template_dir);
        }
        
        foreach ($this->supported_languages as $lang) {
            $lang_dir = $this->template_dir . $lang . '/';
            if (!file_exists($lang_dir)) {
                wp_mkdir_p($lang_dir);
            }
        }
        
        // Create default templates if they don't exist
        $this->create_default_templates();
    }
    
    /**
     * Create default email templates
     */
    private function create_default_templates() {
        $templates = [
            'payment_reminder' => [
                'subject' => [
                    'it' => 'Promemoria Pagamento #{reminder_count} - {site_name}',
                    'en' => 'Payment Reminder #{reminder_count} - {site_name}'
                ],
                'description' => [
                    'it' => 'Template per reminder di pagamento',
                    'en' => 'Payment reminder template'
                ]
            ],
            'payment_confirmation' => [
                'subject' => [
                    'it' => 'Pagamento Ricevuto - {site_name}',
                    'en' => 'Payment Received - {site_name}'
                ],
                'description' => [
                    'it' => 'Conferma di pagamento ricevuto',
                    'en' => 'Payment confirmation template'
                ]
            ],
            'payment_failed' => [
                'subject' => [
                    'it' => 'Pagamento Non Riuscito - {site_name}',
                    'en' => 'Payment Failed - {site_name}'
                ],
                'description' => [
                    'it' => 'Notifica di pagamento fallito',
                    'en' => 'Payment failure notification'
                ]
            ],
            'group_payment_invitation' => [
                'subject' => [
                    'it' => 'Invito Pagamento Gruppo - {site_name}',
                    'en' => 'Group Payment Invitation - {site_name}'
                ],
                'description' => [
                    'it' => 'Invito per pagamento di gruppo',
                    'en' => 'Group payment invitation'
                ]
            ]
        ];
        
        foreach ($templates as $template_id => $template_data) {
            foreach ($this->supported_languages as $lang) {
                $this->create_template_files($template_id, $lang, $template_data);
            }
        }
    }
    
    /**
     * Create template files for a specific template and language
     * 
     * @param string $template_id
     * @param string $lang
     * @param array $template_data
     */
    private function create_template_files($template_id, $lang, $template_data) {
        $lang_dir = $this->template_dir . $lang . '/';
        
        // HTML template
        $html_file = $lang_dir . $template_id . '.html';
        if (!file_exists($html_file)) {
            $html_content = $this->generate_default_html_template($template_id, $lang, $template_data);
            file_put_contents($html_file, $html_content);
        }
        
        // Text template
        $text_file = $lang_dir . $template_id . '.txt';
        if (!file_exists($text_file)) {
            $text_content = $this->generate_default_text_template($template_id, $lang, $template_data);
            file_put_contents($text_file, $text_content);
        }
        
        // Template config
        $config_file = $lang_dir . $template_id . '.json';
        if (!file_exists($config_file)) {
            $config = [
                'subject' => $template_data['subject'][$lang],
                'description' => $template_data['description'][$lang],
                'variables' => $this->get_template_variables($template_id),
                'created_at' => current_time('mysql'),
                'version' => '1.0.0'
            ];
            file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT));
        }
    }
    
    /**
     * Generate default HTML template
     * 
     * @param string $template_id
     * @param string $lang
     * @param array $template_data
     * @return string
     */
    private function generate_default_html_template($template_id, $lang, $template_data) {
        switch ($template_id) {
            case 'payment_reminder':
                return $this->get_payment_reminder_html_template($lang);
            case 'payment_confirmation':
                return $this->get_payment_confirmation_html_template($lang);
            case 'payment_failed':
                return $this->get_payment_failed_html_template($lang);
            case 'group_payment_invitation':
                return $this->get_group_payment_invitation_html_template($lang);
            default:
                return $this->get_generic_html_template($lang);
        }
    }
    
    /**
     * Get payment reminder HTML template
     * 
     * @param string $lang
     * @return string
     */
    private function get_payment_reminder_html_template($lang) {
        $texts = [
            'it' => [
                'greeting' => 'Caro/a {participant_name},',
                'reminder_text' => 'Ti ricordiamo che hai un pagamento in sospeso per la tua prenotazione:',
                'amount_label' => 'Importo:',
                'type_label' => 'Tipo:',
                'expires_label' => 'Scadenza:',
                'button_text' => 'Paga Ora',
                'already_paid' => 'Se hai già effettuato il pagamento, puoi ignorare questo messaggio.',
                'support_text' => 'Per assistenza contattaci all\'indirizzo',
                'reminder_count_text' => 'Promemoria #{reminder_count}'
            ],
            'en' => [
                'greeting' => 'Dear {participant_name},',
                'reminder_text' => 'We remind you that you have a pending payment for your booking:',
                'amount_label' => 'Amount:',
                'type_label' => 'Type:',
                'expires_label' => 'Expires:',
                'button_text' => 'Pay Now',
                'already_paid' => 'If you have already made the payment, you can ignore this message.',
                'support_text' => 'For support contact us at',
                'reminder_count_text' => 'Reminder #{reminder_count}'
            ]
        ];
        
        $text = $texts[$lang];
        
        return '<!DOCTYPE html>
<html lang="' . $lang . '">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{subject}</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
            margin: 0; 
            padding: 0; 
            background-color: #f5f5f5;
            line-height: 1.6;
        }
        .container { 
            max-width: 600px; 
            margin: 0 auto; 
            background: #ffffff; 
            padding: 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header { 
            background: linear-gradient(135deg, #0097c5 0%, #007ba3 100%);
            color: white; 
            padding: 30px; 
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .header h1 { 
            margin: 0; 
            font-size: 24px; 
            font-weight: 600;
        }
        .content { 
            padding: 30px; 
        }
        .amount-box { 
            background: #f8f9fa; 
            padding: 20px; 
            border-radius: 6px; 
            margin: 20px 0;
            border-left: 4px solid #0097c5;
        }
        .amount { 
            font-size: 28px; 
            font-weight: bold; 
            color: #0097c5; 
            margin-bottom: 10px;
        }
        .button { 
            display: inline-block; 
            background: #0097c5; 
            color: white; 
            padding: 15px 30px; 
            text-decoration: none; 
            border-radius: 6px; 
            margin: 20px 0;
            font-weight: 600;
            transition: background-color 0.3s ease;
        }
        .button:hover {
            background: #007ba3;
        }
        .footer { 
            background: #f8f9fa;
            padding: 20px 30px; 
            font-size: 14px; 
            color: #6c757d;
            border-radius: 0 0 8px 8px;
        }
        .urgent {
            background: #fff3cd;
            border-left-color: #ffc107;
            color: #856404;
        }
        @media only screen and (max-width: 600px) {
            .container { margin: 10px; }
            .content { padding: 20px; }
            .header { padding: 20px; }
            .button { display: block; text-align: center; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>' . $text['reminder_count_text'] . '</h1>
        </div>
        
        <div class="content">
            <p>' . $text['greeting'] . '</p>
            
            <p>' . $text['reminder_text'] . '</p>
            
            <div class="amount-box{if reminder_count >= 3} urgent{endif}">
                <div class="amount">{amount} {currency}</div>
                <p><strong>' . $text['type_label'] . '</strong> {payment_type_text}</p>
                {if expires_at}
                <p><strong>' . $text['expires_label'] . '</strong> {expires_at_formatted}</p>
                {endif}
            </div>
            
            <div style="text-align: center;">
                <a href="{payment_link}" class="button">
                    ' . $text['button_text'] . '
                </a>
            </div>
            
            <p style="margin-top: 30px; color: #6c757d;">' . $text['already_paid'] . '</p>
        </div>
        
        <div class="footer">
            <p>' . $text['support_text'] . ' {support_email}</p>
            <p style="margin: 0;">© {current_year} {site_name}. ' . ($lang === 'it' ? 'Tutti i diritti riservati.' : 'All rights reserved.') . '</p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Get payment confirmation HTML template
     * 
     * @param string $lang
     * @return string
     */
    private function get_payment_confirmation_html_template($lang) {
        $texts = [
            'it' => [
                'title' => 'Pagamento Ricevuto',
                'greeting' => 'Caro/a {participant_name},',
                'confirmation_text' => 'Abbiamo ricevuto il tuo pagamento. Ecco i dettagli:',
                'amount_label' => 'Importo Pagato:',
                'transaction_label' => 'ID Transazione:',
                'date_label' => 'Data Pagamento:',
                'next_steps' => 'Riceverai presto ulteriori informazioni sulla tua prenotazione.',
                'thanks' => 'Grazie per aver scelto i nostri servizi!'
            ],
            'en' => [
                'title' => 'Payment Received',
                'greeting' => 'Dear {participant_name},',
                'confirmation_text' => 'We have received your payment. Here are the details:',
                'amount_label' => 'Amount Paid:',
                'transaction_label' => 'Transaction ID:',
                'date_label' => 'Payment Date:',
                'next_steps' => 'You will soon receive more information about your booking.',
                'thanks' => 'Thank you for choosing our services!'
            ]
        ];
        
        $text = $texts[$lang];
        
        return '<!DOCTYPE html>
<html lang="' . $lang . '">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $text['title'] . '</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
            margin: 0; 
            padding: 0; 
            background-color: #f5f5f5;
            line-height: 1.6;
        }
        .container { 
            max-width: 600px; 
            margin: 0 auto; 
            background: #ffffff; 
            padding: 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header { 
            background: linear-gradient(135deg, #28a745 0%, #20783d 100%);
            color: white; 
            padding: 30px; 
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .header h1 { 
            margin: 0; 
            font-size: 24px; 
            font-weight: 600;
        }
        .success-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        .content { 
            padding: 30px; 
        }
        .payment-details { 
            background: #f8f9fa; 
            padding: 20px; 
            border-radius: 6px; 
            margin: 20px 0;
            border-left: 4px solid #28a745;
        }
        .amount { 
            font-size: 28px; 
            font-weight: bold; 
            color: #28a745; 
            margin-bottom: 15px;
        }
        .footer { 
            background: #f8f9fa;
            padding: 20px 30px; 
            font-size: 14px; 
            color: #6c757d;
            border-radius: 0 0 8px 8px;
        }
        @media only screen and (max-width: 600px) {
            .container { margin: 10px; }
            .content { padding: 20px; }
            .header { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="success-icon">✅</div>
            <h1>' . $text['title'] . '</h1>
        </div>
        
        <div class="content">
            <p>' . $text['greeting'] . '</p>
            
            <p>' . $text['confirmation_text'] . '</p>
            
            <div class="payment-details">
                <div class="amount">{amount} {currency}</div>
                <p><strong>' . $text['transaction_label'] . '</strong> {transaction_id}</p>
                <p><strong>' . $text['date_label'] . '</strong> {paid_at_formatted}</p>
            </div>
            
            <p>' . $text['next_steps'] . '</p>
            
            <p style="margin-top: 30px; font-weight: 600; color: #28a745;">' . $text['thanks'] . '</p>
        </div>
        
        <div class="footer">
            <p>© {current_year} {site_name}. ' . ($lang === 'it' ? 'Tutti i diritti riservati.' : 'All rights reserved.') . '</p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Generate default text template
     * 
     * @param string $template_id
     * @param string $lang
     * @param array $template_data
     * @return string
     */
    private function generate_default_text_template($template_id, $lang, $template_data) {
        switch ($template_id) {
            case 'payment_reminder':
                return $this->get_payment_reminder_text_template($lang);
            case 'payment_confirmation':
                return $this->get_payment_confirmation_text_template($lang);
            case 'payment_failed':
                return $this->get_payment_failed_text_template($lang);
            case 'group_payment_invitation':
                return $this->get_group_payment_invitation_text_template($lang);
            default:
                return $this->get_generic_text_template($lang);
        }
    }
    
    /**
     * Get payment reminder text template
     * 
     * @param string $lang
     * @return string
     */
    private function get_payment_reminder_text_template($lang) {
        if ($lang === 'en') {
            return 'Dear {participant_name},

We remind you that you have a pending payment for your booking:

Amount: {amount} {currency}
Type: {payment_type_text}
{if expires_at}Expires: {expires_at_formatted}{endif}

To complete your payment, visit: {payment_link}

If you have already made the payment, you can ignore this message.

For support contact us at {support_email}

Best regards,
{site_name}';
        }
        
        return 'Caro/a {participant_name},

Ti ricordiamo che hai un pagamento in sospeso per la tua prenotazione:

Importo: {amount} {currency}
Tipo: {payment_type_text}
{if expires_at}Scadenza: {expires_at_formatted}{endif}

Per completare il pagamento, visita: {payment_link}

Se hai già effettuato il pagamento, puoi ignorare questo messaggio.

Per assistenza contattaci all\'indirizzo {support_email}

Cordiali saluti,
{site_name}';
    }
    
    /**
     * Get payment confirmation text template
     * 
     * @param string $lang
     * @return string
     */
    private function get_payment_confirmation_text_template($lang) {
        if ($lang === 'en') {
            return 'Dear {participant_name},

We have received your payment. Here are the details:

Amount Paid: {amount} {currency}
Transaction ID: {transaction_id}
Payment Date: {paid_at_formatted}

You will soon receive more information about your booking.

Thank you for choosing our services!

Best regards,
{site_name}';
        }
        
        return 'Caro/a {participant_name},

Abbiamo ricevuto il tuo pagamento. Ecco i dettagli:

Importo Pagato: {amount} {currency}
ID Transazione: {transaction_id}
Data Pagamento: {paid_at_formatted}

Riceverai presto ulteriori informazioni sulla tua prenotazione.

Grazie per aver scelto i nostri servizi!

Cordiali saluti,
{site_name}';
    }
    
    /**
     * Get payment failed text template
     */
    private function get_payment_failed_text_template($lang) {
        if ($lang === 'en') {
            return 'Dear {participant_name},

Your payment was not completed. Here are the details:

Amount: {amount} {currency}
Reason: {failure_reason}

You can retry the payment using the following link:
{payment_link}

If you continue to have problems, please contact our support.

Best regards,
{site_name}';
        }
        
        return 'Caro/a {participant_name},

Il tuo pagamento non è stato completato. Ecco i dettagli:

Importo: {amount} {currency}
Motivo: {failure_reason}

Puoi riprovare il pagamento utilizzando il seguente link:
{payment_link}

Se continui ad avere problemi, contatta il nostro supporto.

Cordiali saluti,
{site_name}';
    }
    
    /**
     * Get group payment invitation text template
     */
    private function get_group_payment_invitation_text_template($lang) {
        if ($lang === 'en') {
            return 'Dear {participant_name},

You have been invited to participate in a group payment for a booking.

Your share: {amount} {currency}
Due date: {expires_at}

Click the following link to complete your payment:
{payment_link}

Important: This link is valid only for you and will expire on {expires_at}.

Best regards,
{site_name}';
        }
        
        return 'Caro/a {participant_name},

Sei stato invitato a partecipare a un pagamento di gruppo per una prenotazione.

La tua quota: {amount} {currency}
Scadenza: {expires_at}

Clicca il seguente link per completare il tuo pagamento:
{payment_link}

Importante: Questo link è valido solo per te e scadrà il {expires_at}.

Cordiali saluti,
{site_name}';
    }
    
    /**
     * Get payment failed HTML template
     */
    private function get_payment_failed_html_template($lang) {
        $texts = [
            'it' => [
                'title' => 'Pagamento Non Riuscito',
                'greeting' => 'Caro/a {participant_name},',
                'failed_text' => 'Il tuo pagamento non è stato completato. Ecco i dettagli:',
                'amount_label' => 'Importo:',
                'reason_label' => 'Motivo:',
                'retry_text' => 'Puoi riprovare il pagamento utilizzando il link sottostante:',
                'retry_button' => 'Riprova Pagamento',
                'support_text' => 'Se continui ad avere problemi, contatta il nostro supporto.'
            ],
            'en' => [
                'title' => 'Payment Failed',
                'greeting' => 'Dear {participant_name},',
                'failed_text' => 'Your payment was not completed. Here are the details:',
                'amount_label' => 'Amount:',
                'reason_label' => 'Reason:',
                'retry_text' => 'You can retry the payment using the link below:',
                'retry_button' => 'Retry Payment',
                'support_text' => 'If you continue to have problems, please contact our support.'
            ]
        ];
        
        $text = $texts[$lang];
        
        return '<!DOCTYPE html>
<html lang="' . $lang . '">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $text['title'] . '</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
            margin: 0; 
            padding: 0; 
            background-color: #f5f5f5;
            line-height: 1.6;
        }
        .container { 
            max-width: 600px; 
            margin: 0 auto; 
            background: #ffffff; 
            padding: 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header { 
            background: linear-gradient(135deg, #dc3545 0%, #a71e2a 100%);
            color: white; 
            padding: 30px 40px; 
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content { 
            padding: 40px; 
        }
        .payment-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
            border-left: 4px solid #dc3545;
        }
        .retry-button {
            display: inline-block;
            background: #dc3545;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
            font-weight: 600;
        }
        .footer { 
            background: #f8f9fa; 
            padding: 20px 40px; 
            text-align: center; 
            font-size: 14px; 
            color: #6c757d;
            border-radius: 0 0 8px 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚠️ ' . $text['title'] . '</h1>
        </div>
        <div class="content">
            <p>' . $text['greeting'] . '</p>
            <p>' . $text['failed_text'] . '</p>
            
            <div class="payment-details">
                <p><strong>' . $text['amount_label'] . '</strong> {amount} {currency}</p>
                <p><strong>' . $text['reason_label'] . '</strong> {failure_reason}</p>
            </div>
            
            <p>' . $text['retry_text'] . '</p>
            <p><a href="{payment_link}" class="retry-button">' . $text['retry_button'] . '</a></p>
            
            <p><small>' . $text['support_text'] . '</small></p>
        </div>
        <div class="footer">
            <p>{site_name} - {current_year}</p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Get group payment invitation HTML template
     */
    private function get_group_payment_invitation_html_template($lang) {
        $texts = [
            'it' => [
                'title' => 'Invito Pagamento di Gruppo',
                'greeting' => 'Caro/a {participant_name},',
                'invitation_text' => 'Sei stato invitato a partecipare a un pagamento di gruppo per:',
                'share_label' => 'La tua quota:',
                'due_date_label' => 'Scadenza:',
                'pay_text' => 'Clicca il pulsante sottostante per completare il tuo pagamento:',
                'pay_button' => 'Paga la Tua Quota',
                'important_note' => 'Importante: Questo link è valido solo per te e scadrà il {expires_at}.'
            ],
            'en' => [
                'title' => 'Group Payment Invitation',
                'greeting' => 'Dear {participant_name},',
                'invitation_text' => 'You have been invited to participate in a group payment for:',
                'share_label' => 'Your share:',
                'due_date_label' => 'Due date:',
                'pay_text' => 'Click the button below to complete your payment:',
                'pay_button' => 'Pay Your Share',
                'important_note' => 'Important: This link is valid only for you and will expire on {expires_at}.'
            ]
        ];
        
        $text = $texts[$lang];
        
        return '<!DOCTYPE html>
<html lang="' . $lang . '">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $text['title'] . '</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
            margin: 0; 
            padding: 0; 
            background-color: #f5f5f5;
            line-height: 1.6;
        }
        .container { 
            max-width: 600px; 
            margin: 0 auto; 
            background: #ffffff; 
            padding: 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header { 
            background: linear-gradient(135deg, #17a2b8 0%, #117a8b 100%);
            color: white; 
            padding: 30px 40px; 
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content { 
            padding: 40px; 
        }
        .payment-details {
            background: #e8f4f8;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
            border-left: 4px solid #17a2b8;
        }
        .pay-button {
            display: inline-block;
            background: #17a2b8;
            color: white;
            padding: 15px 40px;
            text-decoration: none;
            border-radius: 6px;
            margin: 20px 0;
            font-weight: 600;
            font-size: 16px;
        }
        .important-note {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
            color: #856404;
        }
        .footer { 
            background: #f8f9fa; 
            padding: 20px 40px; 
            text-align: center; 
            font-size: 14px; 
            color: #6c757d;
            border-radius: 0 0 8px 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>👥 ' . $text['title'] . '</h1>
        </div>
        <div class="content">
            <p>' . $text['greeting'] . '</p>
            <p>' . $text['invitation_text'] . '</p>
            
            <div class="payment-details">
                <p><strong>' . $text['share_label'] . '</strong> {amount} {currency}</p>
                <p><strong>' . $text['due_date_label'] . '</strong> {expires_at}</p>
            </div>
            
            <p>' . $text['pay_text'] . '</p>
            <p style="text-align: center;"><a href="{payment_link}" class="pay-button">' . $text['pay_button'] . '</a></p>
            
            <div class="important-note">
                <p><strong>ℹ️</strong> ' . $text['important_note'] . '</p>
            </div>
        </div>
        <div class="footer">
            <p>{site_name} - {current_year}</p>
        </div>
    </div>
</body>
</html>';
    }
    
    /**
     * Get generic HTML template
     */
    private function get_generic_html_template($lang) {
        return '<!DOCTYPE html>
<html lang="' . $lang . '">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{subject}</title>
</head>
<body>
    <h1>{subject}</h1>
    <p>' . ($lang === 'en' ? 'Dear {participant_name},' : 'Caro/a {participant_name},') . '</p>
    <p>{content}</p>
    <p>' . ($lang === 'en' ? 'Best regards,' : 'Cordiali saluti,') . '<br>{site_name}</p>
</body>
</html>';
    }
    
    /**
     * Get generic text template
     */
    private function get_generic_text_template($lang) {
        return ($lang === 'en' ? 'Dear {participant_name},' : 'Caro/a {participant_name},') . '

{content}

' . ($lang === 'en' ? 'Best regards,' : 'Cordiali saluti,') . '
{site_name}';
    }
    
    /**
     * Get template variables for a specific template
     * 
     * @param string $template_id
     * @return array
     */
    private function get_template_variables($template_id) {
        $common_variables = [
            'site_name' => 'Nome del sito',
            'current_year' => 'Anno corrente',
            'support_email' => 'Email di supporto',
            'participant_name' => 'Nome partecipante',
            'participant_email' => 'Email partecipante'
        ];
        
        $template_specific = [
            'payment_reminder' => [
                'amount' => 'Importo pagamento',
                'currency' => 'Valuta',
                'payment_type' => 'Tipo pagamento',
                'payment_type_text' => 'Tipo pagamento (testo)',
                'payment_link' => 'Link per pagamento',
                'expires_at' => 'Data scadenza',
                'expires_at_formatted' => 'Data scadenza formattata',
                'reminder_count' => 'Numero reminder'
            ],
            'payment_confirmation' => [
                'amount' => 'Importo pagato',
                'currency' => 'Valuta',
                'transaction_id' => 'ID transazione',
                'paid_at' => 'Data pagamento',
                'paid_at_formatted' => 'Data pagamento formattata'
            ],
            'payment_failed' => [
                'amount' => 'Importo tentativo',
                'currency' => 'Valuta',
                'failure_reason' => 'Motivo fallimento',
                'retry_link' => 'Link per nuovo tentativo'
            ],
            'group_payment_invitation' => [
                'amount' => 'Importo quota',
                'currency' => 'Valuta',
                'total_amount' => 'Importo totale',
                'payment_link' => 'Link pagamento quota',
                'group_organizer' => 'Organizzatore gruppo'
            ]
        ];
        
        return array_merge($common_variables, $template_specific[$template_id] ?? []);
    }
    
    /**
     * Load email template
     * 
     * @param string $template_id
     * @param string $format (html|text)
     * @param string $lang
     * @return string|false
     */
    public function load_template($template_id, $format = 'html', $lang = null) {
        if ($lang === null) {
            $lang = $this->default_language;
        }
        
        if (!in_array($lang, $this->supported_languages)) {
            $lang = $this->default_language;
        }
        
        $template_file = $this->template_dir . $lang . '/' . $template_id . '.' . $format;
        
        if (!file_exists($template_file)) {
            return false;
        }
        
        return file_get_contents($template_file);
    }
    
    /**
     * Render email template with variables
     * 
     * @param string $template_id
     * @param array $variables
     * @param string $format
     * @param string $lang
     * @return array
     */
    public function render_template($template_id, $variables = [], $format = 'html', $lang = null) {
        // Load template content
        $template_content = $this->load_template($template_id, $format, $lang);
        if ($template_content === false) {
            return [
                'success' => false,
                'error' => 'Template not found'
            ];
        }
        
        // Load template config
        $config = $this->load_template_config($template_id, $lang);
        if (!$config) {
            return [
                'success' => false,
                'error' => 'Template config not found'
            ];
        }
        
        // Prepare variables
        $variables = $this->prepare_variables($variables);
        
        // Render subject
        $subject = $this->process_template_variables($config['subject'], $variables);
        
        // Render content
        $content = $this->process_template_variables($template_content, $variables);
        
        // Process conditional blocks
        $content = $this->process_conditional_blocks($content, $variables);
        
        return [
            'success' => true,
            'subject' => $subject,
            'content' => $content,
            'format' => $format,
            'language' => $lang,
            'variables_used' => array_keys($variables)
        ];
    }
    
    /**
     * Load template configuration
     * 
     * @param string $template_id
     * @param string $lang
     * @return array|false
     */
    private function load_template_config($template_id, $lang) {
        $config_file = $this->template_dir . $lang . '/' . $template_id . '.json';
        
        if (!file_exists($config_file)) {
            return false;
        }
        
        $config_content = file_get_contents($config_file);
        return json_decode($config_content, true);
    }
    
    /**
     * Prepare variables with defaults
     * 
     * @param array $variables
     * @return array
     */
    private function prepare_variables($variables) {
        $defaults = [
            'site_name' => get_bloginfo('name'),
            'current_year' => date('Y'),
            'support_email' => get_option('admin_email')
        ];
        
        // Format dates if present
        if (isset($variables['expires_at']) && $variables['expires_at']) {
            $variables['expires_at_formatted'] = date('d/m/Y H:i', strtotime($variables['expires_at']));
        }
        
        if (isset($variables['paid_at']) && $variables['paid_at']) {
            $variables['paid_at_formatted'] = date('d/m/Y H:i', strtotime($variables['paid_at']));
        }
        
        // Format payment type
        if (isset($variables['payment_type'])) {
            $payment_types = [
                'full' => ['it' => 'Pagamento Completo', 'en' => 'Full Payment'],
                'deposit' => ['it' => 'Caparra', 'en' => 'Deposit'],
                'balance' => ['it' => 'Saldo', 'en' => 'Balance'],
                'group_share' => ['it' => 'Quota Gruppo', 'en' => 'Group Share']
            ];
            
            $lang = $variables['language'] ?? $this->default_language;
            $variables['payment_type_text'] = $payment_types[$variables['payment_type']][$lang] ?? $variables['payment_type'];
        }
        
        return array_merge($defaults, $variables);
    }
    
    /**
     * Process template variables
     * 
     * @param string $content
     * @param array $variables
     * @return string
     */
    private function process_template_variables($content, $variables) {
        foreach ($variables as $key => $value) {
            $content = str_replace('{' . $key . '}', $value, $content);
        }
        
        return $content;
    }
    
    /**
     * Process conditional blocks in template
     * 
     * @param string $content
     * @param array $variables
     * @return string
     */
    private function process_conditional_blocks($content, $variables) {
        // Process {if variable} blocks
        $content = preg_replace_callback(
            '/\{if\s+([^}]+)\}(.*?)\{endif\}/s',
            function($matches) use ($variables) {
                $condition = trim($matches[1]);
                $block_content = $matches[2];
                
                // Simple condition check
                if (isset($variables[$condition]) && !empty($variables[$condition])) {
                    return $block_content;
                }
                
                return '';
            },
            $content
        );
        
        return $content;
    }
    
    /**
     * Send email using template
     * 
     * @param string $to
     * @param string $template_id
     * @param array $variables
     * @param string $lang
     * @return bool
     */
    public function send_email($to, $template_id, $variables = [], $lang = null) {
        // Render HTML template
        $html_result = $this->render_template($template_id, $variables, 'html', $lang);
        if (!$html_result['success']) {
            return false;
        }
        
        // Render text template as fallback
        $text_result = $this->render_template($template_id, $variables, 'text', $lang);
        
        // Prepare headers
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>'
        ];
        
        // Add text alternative if available
        if ($text_result['success']) {
            // WordPress doesn't support multipart emails easily, so we'll use HTML only
            // but store text version for future use
        }
        
        // Send email
        $sent = wp_mail($to, $html_result['subject'], $html_result['content'], $headers);
        
        // Log email sending
        BTR_Payment_Security::log_security_event('email_sent', [
            'to' => BTR_Payment_Security::mask_email($to),
            'template_id' => $template_id,
            'language' => $lang,
            'success' => $sent
        ]);
        
        return $sent;
    }
    
    /**
     * Get available templates
     * 
     * @param string $lang
     * @return array
     */
    public function get_available_templates($lang = null) {
        if ($lang === null) {
            $lang = $this->default_language;
        }
        
        $lang_dir = $this->template_dir . $lang . '/';
        if (!file_exists($lang_dir)) {
            return [];
        }
        
        $templates = [];
        $files = glob($lang_dir . '*.html');
        
        foreach ($files as $file) {
            $template_id = basename($file, '.html');
            $config = $this->load_template_config($template_id, $lang);
            
            $templates[] = [
                'id' => $template_id,
                'description' => $config['description'] ?? $template_id,
                'variables' => $config['variables'] ?? [],
                'version' => $config['version'] ?? '1.0.0',
                'has_html' => file_exists($lang_dir . $template_id . '.html'),
                'has_text' => file_exists($lang_dir . $template_id . '.txt')
            ];
        }
        
        return $templates;
    }
    
    /**
     * AJAX handler for template preview
     */
    public function ajax_preview_email_template() {
        if (!wp_verify_nonce($_POST['nonce'], 'btr_email_template_admin')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $template_id = sanitize_text_field($_POST['template_id']);
        $format = sanitize_text_field($_POST['format'] ?? 'html');
        $lang = sanitize_text_field($_POST['lang'] ?? $this->default_language);
        
        // Sample variables for preview
        $sample_variables = [
            'participant_name' => 'Mario Rossi',
            'participant_email' => 'mario.rossi@example.com',
            'amount' => '250.00',
            'currency' => 'EUR',
            'payment_type' => 'deposit',
            'payment_link' => 'https://example.com/payment/abc123',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'reminder_count' => '1',
            'transaction_id' => 'TXN_123456789',
            'paid_at' => current_time('mysql'),
            'language' => $lang
        ];
        
        $result = $this->render_template($template_id, $sample_variables, $format, $lang);
        
        wp_send_json($result);
    }
    
    /**
     * AJAX handler for sending test email
     */
    public function ajax_send_test_email() {
        if (!wp_verify_nonce($_POST['nonce'], 'btr_email_template_admin')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $template_id = sanitize_text_field($_POST['template_id']);
        $test_email = sanitize_email($_POST['test_email']);
        $lang = sanitize_text_field($_POST['lang'] ?? $this->default_language);
        
        if (!$test_email) {
            wp_send_json([
                'success' => false,
                'error' => 'Invalid email address'
            ]);
            return;
        }
        
        // Sample variables for test
        $sample_variables = [
            'participant_name' => 'Test User',
            'participant_email' => $test_email,
            'amount' => '150.00',
            'currency' => 'EUR',
            'payment_type' => 'full',
            'payment_link' => 'https://example.com/payment/test123',
            'expires_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'reminder_count' => '1',
            'language' => $lang
        ];
        
        $sent = $this->send_email($test_email, $template_id, $sample_variables, $lang);
        
        wp_send_json([
            'success' => $sent,
            'message' => $sent ? 'Test email sent successfully' : 'Failed to send test email'
        ]);
    }
    
    /**
     * AJAX handler for getting template data
     */
    public function ajax_get_email_template_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'btr_email_template_admin')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission denied');
        }
        
        $lang = sanitize_text_field($_POST['lang'] ?? $this->default_language);
        
        wp_send_json([
            'success' => true,
            'templates' => $this->get_available_templates($lang),
            'supported_languages' => $this->supported_languages,
            'default_language' => $this->default_language
        ]);
    }
}