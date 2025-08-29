<?php
/**
 * Enhanced Email Template for "Expires Today" Reminders - Italian
 * 
 * Specialized template for urgent same-day expiry reminders
 * 
 * @package BornToRideBooking
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Override config for maximum urgency
$config = $template_config;
$color = '#f44336'; // Red for urgency
$urgency_level = 'urgent';

// Package title
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
$package_title = $package_title ?: 'Viaggio';
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🚨 URGENTE: Pagamento Scade Oggi - <?php echo esc_html($site_name); ?></title>
    <style type="text/css">
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        .urgent-pulse {
            animation: pulse 2s infinite;
        }
        @media only screen and (max-width: 600px) {
            .email-container { width: 100% !important; margin: 0 !important; }
            .content-padding { padding: 20px !important; }
            .cta-button { padding: 12px 30px !important; font-size: 16px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #ffebee;">
    
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #ffebee;">
        <tr>
            <td align="center" style="padding: 20px 10px;">
                
                <!-- Urgent banner -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" class="email-container" style="background-color: #f44336; border-radius: 8px 8px 0 0; margin-bottom: 2px;">
                    <tr>
                        <td style="padding: 15px; text-align: center;">
                            <p style="margin: 0; color: #ffffff; font-size: 14px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px;">
                                🚨 ATTENZIONE: SCADENZA OGGI 🚨
                            </p>
                        </td>
                    </tr>
                </table>
                
                <!-- Main email container -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" class="email-container" style="background-color: #ffffff; border-radius: 0 0 12px 12px; box-shadow: 0 8px 16px rgba(244,67,54,0.3); border: 3px solid #f44336;">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #f44336, #d32f2f); padding: 40px 30px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 32px; font-weight: 700; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">
                                PAGAMENTO SCADE OGGI
                            </h1>
                            <p style="margin: 15px 0 0 0; color: #ffffff; font-size: 18px; opacity: 0.95;">
                                ⏰ Poche ore rimaste!
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Main content -->
                    <tr>
                        <td class="content-padding" style="padding: 40px 30px;">
                            
                            <!-- Urgent greeting -->
                            <p style="margin: 0 0 20px 0; font-size: 20px; line-height: 1.6; color: #333333;">
                                Ciao <strong><?php echo esc_html($participant_name); ?></strong>,
                            </p>
                            
                            <!-- Critical message -->
                            <div style="background-color: #ffcdd2; padding: 25px; border-radius: 8px; margin: 25px 0; border: 2px solid #f44336;">
                                <p style="margin: 0 0 15px 0; font-size: 18px; line-height: 1.7; color: #c62828; font-weight: 600;">
                                    🚨 <strong>ATTENZIONE IMMEDIATA RICHIESTA</strong>
                                </p>
                                <p style="margin: 0; font-size: 16px; line-height: 1.7; color: #d32f2f;">
                                    Il tuo pagamento per "<strong><?php echo esc_html($package_title); ?></strong>" 
                                    <span style="background-color: #f44336; color: white; padding: 2px 8px; border-radius: 4px; font-weight: bold;">SCADE OGGI</span>!
                                </p>
                            </div>
                            
                            <!-- Countdown effect -->
                            <div class="urgent-pulse" style="background: linear-gradient(45deg, #ff5722, #f44336); padding: 25px; border-radius: 12px; margin: 30px 0; text-align: center; box-shadow: 0 4px 12px rgba(244,67,54,0.4);">
                                <p style="margin: 0 0 10px 0; color: #ffffff; font-size: 16px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
                                    Importo da pagare OGGI
                                </p>
                                <p style="margin: 0; color: #ffffff; font-size: 36px; font-weight: 900; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">
                                    <?php echo esc_html($formatted_amount); ?>
                                </p>
                            </div>
                            
                            <!-- Massive CTA button -->
                            <div style="text-align: center; margin: 40px 0;">
                                <a href="<?php echo esc_url($payment_link); ?>" 
                                   class="cta-button urgent-pulse"
                                   style="display: inline-block; padding: 20px 50px; background: linear-gradient(45deg, #ff5722, #f44336); color: #ffffff; text-decoration: none; font-size: 22px; font-weight: 800; border-radius: 50px; box-shadow: 0 8px 16px rgba(244,67,54,0.4); text-transform: uppercase; letter-spacing: 1px; border: 3px solid #ffffff;">
                                    🚀 PAGA IMMEDIATAMENTE
                                </a>
                            </div>
                            
                            <!-- Why act now -->
                            <div style="background-color: #fff3e0; padding: 25px; border-radius: 8px; margin: 30px 0; border-left: 6px solid #ff9800;">
                                <h3 style="margin: 0 0 15px 0; font-size: 18px; color: #ef6c00; font-weight: 700;">
                                    ⚡ Perché agire SUBITO:
                                </h3>
                                <ul style="margin: 0; padding-left: 20px; color: #f57c00; line-height: 1.8; font-weight: 600;">
                                    <li style="margin-bottom: 10px;">🎯 <strong>Il tuo posto è a RISCHIO</strong> - Altri partecipanti potrebbero prendere il tuo posto</li>
                                    <li style="margin-bottom: 10px;">⏰ <strong>Scadenza alle 23:59 di oggi</strong> - Dopo sarà troppo tardi</li>
                                    <li style="margin-bottom: 10px;">💰 <strong>Nessun rimborso possibile</strong> se perdi la scadenza</li>
                                    <li style="margin-bottom: 0;">✅ <strong>Conferma istantanea</strong> una volta completato il pagamento</li>
                                </ul>
                            </div>
                            
                            <!-- Alternative link with emphasis -->
                            <div style="background-color: #f5f5f5; padding: 20px; border-radius: 8px; margin: 30px 0; border: 2px dashed #666;">
                                <p style="margin: 0 0 10px 0; font-size: 14px; color: #666; text-align: center; font-weight: 600;">
                                    Se il pulsante non funziona, usa questo link IMMEDIATAMENTE:
                                </p>
                                <p style="margin: 0; font-size: 12px; color: #f44336; text-align: center; word-break: break-all; font-weight: bold;">
                                    <?php echo esc_url($payment_link); ?>
                                </p>
                            </div>
                            
                            <!-- Final warning -->
                            <div style="background-color: #ffebee; padding: 20px; border-radius: 8px; margin: 25px 0; border: 2px solid #f44336; text-align: center;">
                                <p style="margin: 0; font-size: 16px; color: #c62828; font-weight: 700;">
                                    ⚠️ <strong>ULTIMO AVVISO:</strong> Dopo oggi, la tua prenotazione sarà automaticamente CANCELLATA e non potrà essere recuperata.
                                </p>
                            </div>
                            
                            <!-- Support info -->
                            <p style="margin: 30px 0 0 0; font-size: 14px; line-height: 1.6; color: #666; text-align: center;">
                                <strong>Problemi tecnici?</strong> Contatta immediatamente il nostro supporto: 
                                <a href="mailto:<?php echo esc_attr(get_option('admin_email')); ?>" style="color: #f44336; font-weight: bold;">
                                    <?php echo esc_html(get_option('admin_email')); ?>
                                </a>
                            </p>
                            
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f44336; padding: 20px; text-align: center; color: #ffffff;">
                            <p style="margin: 0; font-size: 14px; font-weight: 600;">
                                Email urgente inviata da <?php echo esc_html($site_name); ?> - <?php echo date('d/m/Y H:i'); ?>
                            </p>
                        </td>
                    </tr>
                    
                </table>
                
            </td>
        </tr>
    </table>
    
</body>
</html>