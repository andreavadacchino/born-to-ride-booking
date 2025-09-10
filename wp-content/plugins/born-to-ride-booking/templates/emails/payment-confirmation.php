<?php
/**
 * Template email per conferma pagamento
 * 
 * Variabili disponibili:
 * - $participant_name: Nome del partecipante
 * - $amount: Importo pagato
 * - $payment_date: Data del pagamento
 * - $order_number: Numero ordine WooCommerce
 * - $preventivo_data: Dati del preventivo
 * - $package_title: Titolo del pacchetto
 * - $dates: Date del viaggio
 * 
 * @package BornToRideBooking
 * @since 1.0.98
 */

if (!defined('ABSPATH')) {
    exit;
}

// Estrai dati preventivo
$package_title = $preventivo_data['package_title'] ?? 'Viaggio';
$dates = $preventivo_data['dates'] ?? '';
$organizer_name = $preventivo_data['organizer_name'] ?? '';
$payment_date_formatted = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($payment_date));
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conferma Pagamento - <?php echo esc_html($package_title); ?></title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f4f4f4;">
        <tr>
            <td align="center" style="padding: 40px 0;">
                <!-- Container principale -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    
                    <!-- Header con successo -->
                    <tr>
                        <td style="background-color: #4caf50; padding: 40px 30px; border-radius: 8px 8px 0 0; text-align: center;">
                            <div style="width: 80px; height: 80px; background-color: rgba(255,255,255,0.2); border-radius: 50%; margin: 0 auto 20px; display: inline-block; line-height: 80px;">
                                <span style="font-size: 48px; color: #ffffff;">✓</span>
                            </div>
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px;">
                                Pagamento Confermato!
                            </h1>
                        </td>
                    </tr>
                    
                    <!-- Contenuto principale -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #333333;">
                                Ciao <strong><?php echo esc_html($participant_name); ?></strong>,
                            </p>
                            
                            <p style="margin: 0 0 20px 0; font-size: 16px; line-height: 1.6; color: #333333;">
                                Il tuo pagamento è stato ricevuto con successo! Grazie per aver completato la tua quota per il viaggio.
                            </p>
                            
                            <!-- Dettagli pagamento -->
                            <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                                <h2 style="margin: 0 0 15px 0; font-size: 18px; color: #333333;">
                                    Dettagli del Pagamento
                                </h2>
                                
                                <table style="width: 100%; border-collapse: collapse;">
                                    <tr>
                                        <td style="padding: 8px 0; color: #666666;">Importo pagato:</td>
                                        <td style="padding: 8px 0; text-align: right; font-weight: bold; color: #333333;">
                                            <?php echo btr_format_price_i18n($amount); ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; color: #666666;">Data pagamento:</td>
                                        <td style="padding: 8px 0; text-align: right; color: #333333;">
                                            <?php echo esc_html($payment_date_formatted); ?>
                                        </td>
                                    </tr>
                                    <?php if ($order_number): ?>
                                    <tr>
                                        <td style="padding: 8px 0; color: #666666;">Numero ordine:</td>
                                        <td style="padding: 8px 0; text-align: right; color: #333333;">
                                            #<?php echo esc_html($order_number); ?>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                            
                            <!-- Dettagli viaggio -->
                            <div style="background-color: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0;">
                                <h2 style="margin: 0 0 10px 0; font-size: 20px; color: #0097c5;">
                                    <?php echo esc_html($package_title); ?>
                                </h2>
                                <?php if ($dates): ?>
                                <p style="margin: 0; font-size: 14px; color: #666666;">
                                    <strong>Date:</strong> <?php echo esc_html($dates); ?>
                                </p>
                                <?php endif; ?>
                                <?php if ($organizer_name): ?>
                                <p style="margin: 10px 0 0 0; font-size: 14px; color: #666666;">
                                    <strong>Organizzatore:</strong> <?php echo esc_html($organizer_name); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Prossimi passi -->
                            <div style="margin: 30px 0;">
                                <h3 style="margin: 0 0 15px 0; font-size: 18px; color: #333333;">
                                    Cosa succede ora?
                                </h3>
                                <ul style="margin: 0; padding-left: 20px; color: #666666; line-height: 1.8;">
                                    <li>Conserva questa email come conferma del tuo pagamento</li>
                                    <li>L'organizzatore del viaggio riceverà una notifica del tuo pagamento</li>
                                    <li>Riceverai ulteriori informazioni sul viaggio man mano che si avvicina la data di partenza</li>
                                </ul>
                            </div>
                            
                            <!-- Note importanti -->
                            <div style="background-color: #fff3cd; padding: 15px; border-radius: 8px; margin: 30px 0; border-left: 4px solid #ffc107;">
                                <p style="margin: 0; font-size: 14px; color: #856404;">
                                    <strong>Importante:</strong> Questa email costituisce la ricevuta del tuo pagamento. Ti consigliamo di conservarla per i tuoi archivi.
                                </p>
                            </div>
                            
                            <p style="margin: 20px 0 0 0; font-size: 14px; line-height: 1.6; color: #666666;">
                                Per qualsiasi domanda sul viaggio o sul pagamento, contatta l'organizzatore o rispondi a questa email.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; text-align: center;">
                            <p style="margin: 0 0 10px 0; font-size: 14px; color: #666666;">
                                Grazie per aver scelto Born to Ride!
                            </p>
                            <p style="margin: 0; font-size: 12px; color: #999999;">
                                © <?php echo date('Y'); ?> Born to Ride. Tutti i diritti riservati.
                            </p>
                        </td>
                    </tr>
                    
                </table>
            </td>
        </tr>
    </table>
</body>
</html>