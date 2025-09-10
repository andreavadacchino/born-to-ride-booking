<?php
/**
 * Template email per promemoria pagamento
 * 
 * Variabili disponibili:
 * - $participant_name: Nome del partecipante
 * - $amount: Importo da pagare
 * - $payment_url: URL per il pagamento
 * - $due_date: Data scadenza pagamento
 * - $preventivo_data: Dati del preventivo
 * - $package_title: Titolo del pacchetto
 * - $dates: Date del viaggio
 * - $reminder_type: Tipo di promemoria (first, second, final)
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

// Personalizza messaggio in base al tipo di promemoria
$reminder_messages = [
    'first' => [
        'subject_prefix' => 'Promemoria',
        'urgency' => 'Ti ricordiamo che',
        'color' => '#0097c5'
    ],
    'second' => [
        'subject_prefix' => 'Secondo Promemoria',
        'urgency' => 'Non dimenticare:',
        'color' => '#ff9800'
    ],
    'final' => [
        'subject_prefix' => 'Promemoria Urgente',
        'urgency' => 'Ultimo avviso:',
        'color' => '#f44336'
    ]
];

$reminder_info = $reminder_messages[$reminder_type] ?? $reminder_messages['first'];
$header_color = $reminder_info['color'];
$urgency_text = $reminder_info['urgency'];
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Promemoria Pagamento - <?php echo esc_html($package_title); ?></title>
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
                    
                    <!-- Header con colore dinamico -->
                    <tr>
                        <td style="background-color: <?php echo esc_attr($header_color); ?>; padding: 40px 30px; border-radius: 8px 8px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; text-align: center;">
                                Promemoria Pagamento
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
                                <?php echo esc_html($urgency_text); ?> il pagamento per il viaggio è ancora in attesa.
                            </p>
                            
                            <!-- Alert scadenza -->
                            <?php if ($due_date): ?>
                            <div style="background-color: #ffebee; padding: 15px; border-radius: 8px; margin: 20px 0; border-left: 4px solid <?php echo esc_attr($header_color); ?>;">
                                <p style="margin: 0; font-size: 16px; color: #333333; text-align: center;">
                                    <strong>Scadenza pagamento:</strong><br>
                                    <span style="font-size: 20px; color: <?php echo esc_attr($header_color); ?>;">
                                        <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($due_date))); ?>
                                    </span>
                                </p>
                            </div>
                            <?php endif; ?>
                            
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
                                    Importo da pagare:
                                </p>
                                <p style="margin: 0; font-size: 32px; font-weight: bold; color: <?php echo esc_attr($header_color); ?>;">
                                    <?php echo btr_format_price_i18n($amount); ?>
                                </p>
                            </div>
                            
                            <!-- Call to action -->
                            <div style="text-align: center; margin: 30px 0;">
                                <a href="<?php echo esc_url($payment_url); ?>" 
                                   style="display: inline-block; padding: 15px 40px; background-color: <?php echo esc_attr($header_color); ?>; color: #ffffff; text-decoration: none; font-size: 18px; font-weight: bold; border-radius: 50px;">
                                    Paga Ora
                                </a>
                            </div>
                            
                            <p style="margin: 20px 0; font-size: 14px; line-height: 1.6; color: #666666; text-align: center;">
                                Se il pulsante non funziona, copia e incolla questo link nel tuo browser:
                            </p>
                            
                            <p style="margin: 0; font-size: 12px; color: #0097c5; text-align: center; word-break: break-all;">
                                <?php echo esc_url($payment_url); ?>
                            </p>
                            
                            <!-- Messaggio urgenza per promemoria finale -->
                            <?php if ($reminder_type === 'final'): ?>
                            <div style="background-color: #ffebee; padding: 15px; border-radius: 8px; margin: 30px 0; border-left: 4px solid #f44336;">
                                <p style="margin: 0; font-size: 14px; color: #c62828;">
                                    <strong>Attenzione:</strong> Questo è l'ultimo promemoria. Il mancato pagamento entro la scadenza potrebbe comportare la cancellazione della tua prenotazione.
                                </p>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Perché è importante -->
                            <div style="margin: 30px 0;">
                                <h3 style="margin: 0 0 15px 0; font-size: 18px; color: #333333;">
                                    Perché è importante pagare in tempo?
                                </h3>
                                <ul style="margin: 0; padding-left: 20px; color: #666666; line-height: 1.8;">
                                    <li>Garantisci il tuo posto per il viaggio</li>
                                    <li>Permetti all'organizzatore di finalizzare le prenotazioni</li>
                                    <li>Eviti complicazioni dell'ultimo minuto</li>
                                </ul>
                            </div>
                            
                            <p style="margin: 20px 0 0 0; font-size: 14px; line-height: 1.6; color: #666666;">
                                Se hai già effettuato il pagamento, ignora questa email. Per qualsiasi domanda, contatta l'organizzatore del viaggio.
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