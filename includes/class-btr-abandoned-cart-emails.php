<?php
/**
 * BTR Abandoned Cart Email System
 * 
 * Gestisce l'invio automatico di email reminder per ordini abbandonati
 * 
 * @package Born_To_Ride_Booking
 * @since 1.0.235
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class BTR_Abandoned_Cart_Emails
 * 
 * Sistema email a 3 livelli per recuperare ordini abbandonati
 */
class BTR_Abandoned_Cart_Emails {
    
    /**
     * Instance singleton
     */
    private static $instance = null;
    
    /**
     * Get instance
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
    public function __construct() {
        // Hook per ordine abbandonato
        add_action('btr_order_abandoned', [$this, 'schedule_reminder_emails'], 10, 2);
        
        // Hook per invio email programmate
        add_action('btr_send_abandoned_cart_email', [$this, 'send_reminder_email'], 10, 3);
        
        // Hook per pulizia scheduled events quando ordine completato
        add_action('woocommerce_order_status_changed', [$this, 'cancel_scheduled_emails'], 10, 3);
    }
    
    /**
     * Programma email reminder quando un ordine viene marcato come abbandonato
     */
    public function schedule_reminder_emails($order_id, $recovery_token) {
        // Primo reminder: 1 ora (immediato poiché già passata 1 ora)
        wp_schedule_single_event(time() + 60, 'btr_send_abandoned_cart_email', [$order_id, 'first', $recovery_token]);
        
        // Secondo reminder: 24 ore
        wp_schedule_single_event(time() + DAY_IN_SECONDS, 'btr_send_abandoned_cart_email', [$order_id, 'second', $recovery_token]);
        
        // Terzo reminder: 48 ore
        wp_schedule_single_event(time() + (2 * DAY_IN_SECONDS), 'btr_send_abandoned_cart_email', [$order_id, 'third', $recovery_token]);
        
        btr_debug_log('BTR Emails: Scheduled reminders per ordine ' . $order_id);
    }
    
    /**
     * Invia email reminder
     */
    public function send_reminder_email($order_id, $reminder_type, $recovery_token) {
        $order = wc_get_order($order_id);
        if (!$order || $order->get_status() !== 'draft') {
            return; // Ordine non più draft, non inviare
        }
        
        // Recupera dati necessari
        $user_id = $order->get_customer_id();
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return;
        }
        
        $preventivo_id = $order->get_meta('_btr_preventivo_id');
        $pacchetto_id = get_post_meta($preventivo_id, '_pacchetto_id', true);
        $pacchetto_title = get_the_title($pacchetto_id);
        $data_partenza = get_post_meta($preventivo_id, '_data_partenza', true);
        $total_amount = $order->get_meta('_btr_total_amount');
        
        // Recovery URL
        $recovery_url = BTR_Order_Recovery::get_recovery_url($order_id, $recovery_token);
        
        // Prepara email in base al tipo
        $email_data = $this->prepare_email_content($reminder_type, [
            'user_name' => $user->display_name,
            'package_title' => $pacchetto_title,
            'departure_date' => $data_partenza,
            'total_amount' => $total_amount,
            'recovery_url' => $recovery_url
        ]);
        
        // Invia email
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $sent = wp_mail(
            $user->user_email,
            $email_data['subject'],
            $email_data['content'],
            $headers
        );
        
        if ($sent) {
            // Marca email come inviata
            update_post_meta($order_id, '_btr_reminder_' . $reminder_type . '_sent', current_time('timestamp'));
            btr_debug_log('BTR Emails: Inviato reminder ' . $reminder_type . ' per ordine ' . $order_id);
        }
    }
    
    /**
     * Prepara contenuto email in base al tipo di reminder
     */
    private function prepare_email_content($reminder_type, $data) {
        $subject = '';
        $content = '';
        
        // Template base
        $template = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #0097c5; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f4f4f4; }
                .button { display: inline-block; padding: 15px 30px; background: #0097c5; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .warning { color: #ff9800; font-weight: bold; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Born to Ride</h1>
                </div>
                <div class="content">
                    {{CONTENT}}
                </div>
                <div class="footer">
                    <p>Non vuoi più ricevere questi promemoria? <a href="#">Cancella iscrizione</a></p>
                </div>
            </div>
        </body>
        </html>';
        
        switch ($reminder_type) {
            case 'first':
                $subject = sprintf(__('🏍️ %s, il tuo ordine per %s è quasi pronto!', 'born-to-ride-booking'), 
                    $data['user_name'], $data['package_title']);
                    
                $content = sprintf('
                    <h2>Ciao %s!</h2>
                    <p>Hai iniziato a organizzare un viaggio di gruppo per <strong>%s</strong> con partenza il <strong>%s</strong>, ma non hai completato l\'ordine.</p>
                    <p>I tuoi amici stanno aspettando i link per pagare la loro quota!</p>
                    <p><strong>Totale viaggio: %s</strong></p>
                    <center>
                        <a href="%s" class="button">Completa il tuo ordine ora</a>
                    </center>
                    <p><small>Bastano solo 2 minuti per completare e ricevere i link da inviare ai partecipanti.</small></p>
                ',
                    $data['user_name'],
                    $data['package_title'],
                    date_i18n('d F Y', strtotime($data['departure_date'])),
                    wc_price($data['total_amount']),
                    $data['recovery_url']
                );
                break;
                
            case 'second':
                $subject = sprintf(__('⏰ %s, i tuoi amici stanno aspettando per %s', 'born-to-ride-booking'), 
                    $data['user_name'], $data['package_title']);
                    
                $content = sprintf('
                    <h2>Ciao %s,</h2>
                    <p>Sono passate 24 ore da quando hai iniziato a organizzare il viaggio <strong>%s</strong>.</p>
                    <p class="warning">⚠️ I tuoi compagni di viaggio stanno ancora aspettando di ricevere i link per pagare!</p>
                    <p>Non farli aspettare oltre, completa subito l\'ordine:</p>
                    <center>
                        <a href="%s" class="button">Attiva i link di pagamento</a>
                    </center>
                    <p><strong>Ricorda:</strong> Il preventivo potrebbe scadere presto!</p>
                ',
                    $data['user_name'],
                    $data['package_title'],
                    $data['recovery_url']
                );
                break;
                
            case 'third':
                $subject = sprintf(__('🚨 Ultimo promemoria per %s - Azione richiesta', 'born-to-ride-booking'), 
                    $data['package_title']);
                    
                $content = sprintf('
                    <h2>%s, è il tuo ultimo promemoria!</h2>
                    <p class="warning">Sono passate 48 ore e il tuo ordine per <strong>%s</strong> non è ancora stato completato.</p>
                    <p><strong>⏰ Il tempo sta per scadere!</strong></p>
                    <p>Se non completi l\'ordine presto:</p>
                    <ul>
                        <li>I prezzi potrebbero cambiare</li>
                        <li>Il preventivo potrebbe scadere</li>
                        <li>Dovrai ricominciare da capo</li>
                    </ul>
                    <center>
                        <a href="%s" class="button">Completa ora (ultima possibilità)</a>
                    </center>
                ',
                    $data['user_name'],
                    $data['package_title'],
                    $data['recovery_url']
                );
                break;
        }
        
        // Sostituisci contenuto nel template
        $html = str_replace('{{CONTENT}}', $content, $template);
        
        return [
            'subject' => $subject,
            'content' => $html
        ];
    }
    
    /**
     * Cancella email programmate quando ordine viene completato
     */
    public function cancel_scheduled_emails($order_id, $from_status, $to_status) {
        // Se l'ordine non è più draft, cancella email programmate
        if ($from_status === 'draft' && $to_status !== 'draft') {
            // Cancella tutti gli eventi programmati per questo ordine
            wp_clear_scheduled_hook('btr_send_abandoned_cart_email', [$order_id, 'first', get_post_meta($order_id, '_btr_recovery_token', true)]);
            wp_clear_scheduled_hook('btr_send_abandoned_cart_email', [$order_id, 'second', get_post_meta($order_id, '_btr_recovery_token', true)]);
            wp_clear_scheduled_hook('btr_send_abandoned_cart_email', [$order_id, 'third', get_post_meta($order_id, '_btr_recovery_token', true)]);
            
            btr_debug_log('BTR Emails: Cancellati reminder per ordine ' . $order_id . ' (status: ' . $to_status . ')');
        }
    }
}

// Inizializza
BTR_Abandoned_Cart_Emails::get_instance();