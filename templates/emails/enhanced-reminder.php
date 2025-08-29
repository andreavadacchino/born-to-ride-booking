<?php
/**
 * Enhanced Email Template for Payment Reminders
 * 
 * Main template that supports multiple languages and reminder types
 * 
 * Available variables:
 * - $template_config: Configuration for this reminder type and language
 * - $participant_name: Name of the participant
 * - $amount_assigned: Amount to be paid
 * - $currency: Currency code
 * - $formatted_amount: Formatted amount with currency
 * - $payment_link: Payment URL
 * - $token_expires_at: Token expiry datetime
 * - $formatted_expiry: Formatted expiry date
 * - $days_to_expiry: Days until expiry
 * - $order_id: WooCommerce order ID
 * - $site_name: Site name
 * - $language: Language code (it|en)
 * 
 * @package BornToRideBooking
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Extract template configuration
$config = $template_config;
$color = $config['color'];
$urgency_level = $config['urgency_level'];

// Language-specific translations
$translations = [
    'it' => [
        'hello' => 'Ciao',
        'amount_label' => 'Importo da pagare',
        'expiry_label' => 'Scadenza pagamento',
        'days_remaining' => 'Giorni rimanenti',
        'expires_today' => 'Scade oggi',
        'expired' => 'Scaduto',
        'link_text' => 'Se il pulsante non funziona, copia e incolla questo link nel tuo browser:',
        'footer_text' => 'Questa email è stata inviata da',
        'copyright' => 'Tutti i diritti riservati.',
        'already_paid' => 'Se hai già effettuato il pagamento, ignora questa email.',
        'questions' => 'Per domande, contatta il nostro supporto.',
        'important_info' => 'Informazioni Importanti',
        'secure_payment' => 'Pagamento sicuro e protetto',
        'guarantee_spot' => 'Garantisci il tuo posto per il viaggio',
        'avoid_complications' => 'Evita complicazioni dell\'ultimo minuto',
        'support_organizer' => 'Aiuta l\'organizzatore a finalizzare le prenotazioni'
    ],
    'en' => [
        'hello' => 'Hello',
        'amount_label' => 'Amount to pay',
        'expiry_label' => 'Payment due',
        'days_remaining' => 'Days remaining',
        'expires_today' => 'Expires today',
        'expired' => 'Expired',
        'link_text' => 'If the button doesn\'t work, copy and paste this link in your browser:',
        'footer_text' => 'This email was sent by',
        'copyright' => 'All rights reserved.',
        'already_paid' => 'If you have already made the payment, please ignore this email.',
        'questions' => 'For questions, contact our support.',
        'important_info' => 'Important Information',
        'secure_payment' => 'Secure and protected payment',
        'guarantee_spot' => 'Guarantee your spot for the trip',
        'avoid_complications' => 'Avoid last-minute complications',
        'support_organizer' => 'Help the organizer finalize the bookings'
    ]
];

$t = $translations[$language] ?? $translations['it'];

// Determine urgency styling
$urgency_styles = [
    'normal' => ['border_color' => '#e3f2fd', 'text_color' => '#1976d2'],
    'medium' => ['border_color' => '#fff3e0', 'text_color' => '#f57c00'],
    'high' => ['border_color' => '#fff3e0', 'text_color' => '#ff9800'],
    'urgent' => ['border_color' => '#ffebee', 'text_color' => '#f44336'],
    'critical' => ['border_color' => '#ffebee', 'text_color' => '#d32f2f']
];

$urgency_style = $urgency_styles[$urgency_level] ?? $urgency_styles['normal'];

// Package title (extracted from order)
$package_title = '';
if ($order_id) {
    $order = wc_get_order($order_id);
    if ($order) {
        $items = $order->get_items();
        if (!empty($items)) {
            $first_item = reset($items);
            $package_title = $first_item->get_name();
        }
    }
}
$package_title = $package_title ?: ($language === 'en' ? 'Trip' : 'Viaggio');

// Determine expiry status
$expiry_status = '';
if ($days_to_expiry < 0) {
    $expiry_status = $t['expired'];
} elseif ($days_to_expiry === 0) {
    $expiry_status = $t['expires_today'];
} else {
    $expiry_status = $days_to_expiry . ' ' . ($days_to_expiry === 1 ? 
        ($language === 'en' ? 'day' : 'giorno') : 
        ($language === 'en' ? 'days' : 'giorni')
    );
}
?>

<!DOCTYPE html>
<html lang="<?php echo esc_attr($language); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($config['title']); ?> - <?php echo esc_html($site_name); ?></title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style type="text/css">
        @media only screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
                margin: 0 !important;
            }
            .content-padding {
                padding: 20px !important;
            }
            .cta-button {
                padding: 12px 30px !important;
                font-size: 16px !important;
            }
            .amount-display {
                font-size: 24px !important;
            }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f5f5f5; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%;">
    
    <!-- Outer table for full-width background -->
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f5f5f5;">
        <tr>
            <td align="center" style="padding: 20px 10px;">
                
                <!-- Main email container -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" class="email-container" style="background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); overflow: hidden;">
                    
                    <!-- Header with dynamic color -->
                    <tr>
                        <td style="background: linear-gradient(135deg, <?php echo esc_attr($color); ?>, <?php echo esc_attr($color); ?>dd); padding: 40px 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 600; text-shadow: 0 1px 2px rgba(0,0,0,0.1);">
                                <?php echo esc_html($config['title']); ?>
                            </h1>
                            <?php if ($urgency_level === 'urgent' || $urgency_level === 'critical'): ?>
                            <p style="margin: 10px 0 0 0; color: #ffffff; font-size: 16px; opacity: 0.9;">
                                <?php echo $urgency_level === 'critical' ? '⚠️' : '🚨'; ?> 
                                <?php echo $language === 'en' ? 'Urgent Action Required' : 'Azione Urgente Richiesta'; ?>
                            </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <!-- Main content -->
                    <tr>
                        <td class="content-padding" style="padding: 40px 30px;">
                            
                            <!-- Greeting -->
                            <p style="margin: 0 0 20px 0; font-size: 18px; line-height: 1.6; color: #333333;">
                                <?php echo esc_html($t['hello']); ?> <strong><?php echo esc_html($participant_name); ?></strong>,
                            </p>
                            
                            <!-- Main message -->
                            <p style="margin: 0 0 30px 0; font-size: 16px; line-height: 1.8; color: #555555;">
                                <?php echo esc_html($config['urgency_text']); ?>
                            </p>
                            
                            <!-- Package info box -->
                            <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 25px 0; border-left: 4px solid <?php echo esc_attr($color); ?>;">
                                <h3 style="margin: 0 0 10px 0; font-size: 18px; color: <?php echo esc_attr($color); ?>; font-weight: 600;">
                                    <?php echo esc_html($package_title); ?>
                                </h3>
                                <p style="margin: 0; font-size: 14px; color: #666666;">
                                    <?php echo $language === 'en' ? 'Order' : 'Ordine'; ?> #<?php echo esc_html($order_id); ?>
                                </p>
                            </div>
                            
                            <!-- Payment details in cards layout -->
                            <div style="display: table; width: 100%; margin: 30px 0;">
                                
                                <!-- Amount card -->
                                <div style="display: table-cell; width: 50%; padding-right: 10px; vertical-align: top;">
                                    <div style="background-color: <?php echo esc_attr($urgency_style['border_color']); ?>; padding: 20px; border-radius: 8px; text-align: center; border: 2px solid <?php echo esc_attr($color); ?>20;">
                                        <p style="margin: 0 0 8px 0; font-size: 14px; color: #666666; text-transform: uppercase; letter-spacing: 0.5px;">
                                            <?php echo esc_html($t['amount_label']); ?>
                                        </p>
                                        <p class="amount-display" style="margin: 0; font-size: 28px; font-weight: bold; color: <?php echo esc_attr($color); ?>;">
                                            <?php echo esc_html($formatted_amount); ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- Expiry card -->
                                <div style="display: table-cell; width: 50%; padding-left: 10px; vertical-align: top;">
                                    <div style="background-color: <?php echo esc_attr($urgency_style['border_color']); ?>; padding: 20px; border-radius: 8px; text-align: center; border: 2px solid <?php echo esc_attr($urgency_style['text_color']); ?>20;">
                                        <p style="margin: 0 0 8px 0; font-size: 14px; color: #666666; text-transform: uppercase; letter-spacing: 0.5px;">
                                            <?php echo esc_html($t['expiry_label']); ?>
                                        </p>
                                        <p style="margin: 0; font-size: 18px; font-weight: 600; color: <?php echo esc_attr($urgency_style['text_color']); ?>;">
                                            <?php echo esc_html($expiry_status); ?>
                                        </p>
                                        <p style="margin: 5px 0 0 0; font-size: 12px; color: #888888;">
                                            <?php echo esc_html($formatted_expiry); ?>
                                        </p>
                                    </div>
                                </div>
                                
                            </div>
                            
                            <!-- Call to action button -->
                            <div style="text-align: center; margin: 40px 0;">
                                <a href="<?php echo esc_url($payment_link); ?>" 
                                   class="cta-button"
                                   style="display: inline-block; padding: 16px 40px; background-color: <?php echo esc_attr($color); ?>; color: #ffffff; text-decoration: none; font-size: 18px; font-weight: 600; border-radius: 50px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); transition: all 0.3s ease; text-transform: uppercase; letter-spacing: 0.5px;">
                                    <?php echo esc_html($config['cta_text']); ?>
                                </a>
                            </div>
                            
                            <!-- Alternative link -->
                            <p style="margin: 20px 0; font-size: 13px; line-height: 1.6; color: #777777; text-align: center;">
                                <?php echo esc_html($t['link_text']); ?>
                            </p>
                            
                            <p style="margin: 0 0 30px 0; font-size: 11px; color: <?php echo esc_attr($color); ?>; text-align: center; word-break: break-all; background-color: #f8f9fa; padding: 10px; border-radius: 4px;">
                                <?php echo esc_url($payment_link); ?>
                            </p>
                            
                            <!-- Important information box -->
                            <div style="background-color: #e8f5e9; padding: 20px; border-radius: 8px; margin: 30px 0; border-left: 4px solid #4caf50;">
                                <h4 style="margin: 0 0 15px 0; font-size: 16px; color: #2e7d32; font-weight: 600;">
                                    <?php echo esc_html($t['important_info']); ?>
                                </h4>
                                <ul style="margin: 0; padding-left: 20px; color: #388e3c; line-height: 1.6;">
                                    <li style="margin-bottom: 8px;"><?php echo esc_html($t['secure_payment']); ?></li>
                                    <li style="margin-bottom: 8px;"><?php echo esc_html($t['guarantee_spot']); ?></li>
                                    <li style="margin-bottom: 8px;"><?php echo esc_html($t['support_organizer']); ?></li>
                                    <li style="margin-bottom: 0;"><?php echo esc_html($t['avoid_complications']); ?></li>
                                </ul>
                            </div>
                            
                            <!-- Special urgency notice for critical reminders -->
                            <?php if ($urgency_level === 'urgent' || $urgency_level === 'critical'): ?>
                            <div style="background-color: #ffebee; padding: 20px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #f44336;">
                                <p style="margin: 0; font-size: 14px; color: #c62828; font-weight: 600;">
                                    <?php if ($language === 'en'): ?>
                                        <strong>Important:</strong> This is <?php echo $urgency_level === 'critical' ? 'a final notice' : 'an urgent reminder'; ?>. 
                                        Please complete your payment immediately to avoid losing your reservation.
                                    <?php else: ?>
                                        <strong>Importante:</strong> Questo è <?php echo $urgency_level === 'critical' ? 'l\'ultimo avviso' : 'un promemoria urgente'; ?>. 
                                        Completa il pagamento immediatamente per non perdere la tua prenotazione.
                                    <?php endif; ?>
                                </p>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Footer notes -->
                            <p style="margin: 30px 0 0 0; font-size: 13px; line-height: 1.6; color: #777777;">
                                <?php echo esc_html($t['already_paid']); ?> <?php echo esc_html($t['questions']); ?>
                            </p>
                            
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 30px; text-align: center; border-top: 1px solid #e9ecef;">
                            <p style="margin: 0 0 10px 0; font-size: 14px; color: #6c757d;">
                                <?php echo esc_html($t['footer_text']); ?> <strong><?php echo esc_html($site_name); ?></strong>
                            </p>
                            <p style="margin: 0; font-size: 12px; color: #adb5bd;">
                                © <?php echo date('Y'); ?> <?php echo esc_html($site_name); ?>. <?php echo esc_html($t['copyright']); ?>
                            </p>
                        </td>
                    </tr>
                    
                </table>
                
            </td>
        </tr>
    </table>
    
</body>
</html>