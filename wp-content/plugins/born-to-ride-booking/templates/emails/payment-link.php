<?php
/**
 * Template email per invio link pagamento
 * 
 * Variabili disponibili:
 * - $participant_name: Nome del partecipante
 * - $amount: Importo da pagare
 * - $payment_url: URL per il pagamento
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
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invito al Pagamento - <?php echo esc_html($package_title); ?></title>
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
                    
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #0097c5; padding: 40px 30px; border-radius: 8px 8px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; text-align: center;">
                                Invito al Pagamento
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
                                <?php echo esc_html($organizer_name); ?> ti ha invitato a partecipare al viaggio:
                            </p>
                            
                            <!-- Dettagli viaggio -->
                            <div style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                                <h2 style="margin: 0 0 10px 0; font-size: 20px; color: #0097c5;">
                                    <?php echo esc_html($package_title); ?>
                                </h2>
                                <?php if ($dates): ?>
                                <p style="margin: 0; font-size: 14px; color: #666666;">
                                    <strong>Date:</strong> <?php echo esc_html($dates); ?>
                                </p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Importo da pagare -->
                            <div style="text-align: center; margin: 30px 0;">
                                <p style="margin: 0 0 10px 0; font-size: 16px; color: #666666;">
                                    La tua quota di partecipazione è:
                                </p>
                                <p style="margin: 0; font-size: 32px; font-weight: bold; color: #0097c5;">
                                    <?php echo btr_format_price_i18n($amount); ?>
                                </p>
                            </div>
                            
                            <!-- Call to action -->
                            <div style="text-align: center; margin: 30px 0;">
                                <a href="<?php echo esc_url($payment_url); ?>" 
                                   style="display: inline-block; padding: 15px 40px; background-color: #0097c5; color: #ffffff; text-decoration: none; font-size: 18px; font-weight: bold; border-radius: 50px;">
                                    Paga Ora
                                </a>
                            </div>
                            
                            <p style="margin: 20px 0; font-size: 14px; line-height: 1.6; color: #666666; text-align: center;">
                                Se il pulsante non funziona, copia e incolla questo link nel tuo browser:
                            </p>
                            
                            <p style="margin: 0; font-size: 12px; color: #0097c5; text-align: center; word-break: break-all;">
                                <?php echo esc_url($payment_url); ?>
                            </p>
                            
                            <!-- Note sicurezza -->
                            <div style="background-color: #fff3cd; padding: 15px; border-radius: 8px; margin: 30px 0; border-left: 4px solid #ffc107;">
                                <p style="margin: 0; font-size: 14px; color: #856404;">
                                    <strong>Nota sulla sicurezza:</strong> Questo link di pagamento è personale e non deve essere condiviso con altri. È valido per un singolo pagamento.
                                </p>
                            </div>
                            
                            <p style="margin: 20px 0 0 0; font-size: 14px; line-height: 1.6; color: #666666;">
                                Per qualsiasi domanda, contatta l'organizzatore del viaggio o rispondi a questa email.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; text-align: center;">
                            <p style="margin: 0 0 10px 0; font-size: 14px; color: #666666;">
                                Questa email è stata inviata da Born to Ride
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