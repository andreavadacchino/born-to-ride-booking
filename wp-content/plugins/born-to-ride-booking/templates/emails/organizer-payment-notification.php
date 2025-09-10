<?php
/**
 * Template email notifica organizzatore per pagamento partecipante
 * 
 * Variabili disponibili:
 * - $payment: oggetto pagamento dal database
 * - $participant_name: nome del partecipante che ha pagato
 * - $amount_paid: importo pagato formattato
 * - $package_title: titolo del pacchetto viaggio
 * - $payment_stats: statistiche pagamenti gruppo
 * - $dashboard_url: URL dashboard organizzatore
 * - $preventivo_id: ID del preventivo
 * - $order_id: ID ordine del partecipante
 * - $payment_date: data/ora pagamento
 * 
 * @since 1.0.240
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html__('Notifica Pagamento Ricevuto', 'born-to-ride-booking'); ?></title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f4; font-family: Arial, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f4f4; padding: 20px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #0097c5; padding: 30px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 28px;">
                                <?php echo esc_html__('Pagamento Ricevuto!', 'born-to-ride-booking'); ?>
                            </h1>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <h2 style="color: #333333; margin: 0 0 20px;">
                                <?php echo esc_html(sprintf(__('%s ha pagato la sua quota', 'born-to-ride-booking'), $participant_name)); ?>
                            </h2>
                            
                            <p style="color: #666666; font-size: 16px; line-height: 1.6;">
                                <?php echo esc_html__('Buone notizie! Un partecipante del gruppo ha completato il pagamento della sua quota individuale.', 'born-to-ride-booking'); ?>
                            </p>
                            
                            <!-- Dettagli Pagamento -->
                            <div style="background-color: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0;">
                                <h3 style="color: #0097c5; margin: 0 0 15px; font-size: 18px;">
                                    <?php echo esc_html__('Dettagli Pagamento', 'born-to-ride-booking'); ?>
                                </h3>
                                
                                <table width="100%" cellpadding="5" style="font-size: 14px;">
                                    <tr>
                                        <td style="color: #666666;"><strong><?php echo esc_html__('Partecipante:', 'born-to-ride-booking'); ?></strong></td>
                                        <td style="color: #333333;"><?php echo esc_html($participant_name); ?></td>
                                    </tr>
                                    <tr>
                                        <td style="color: #666666;"><strong><?php echo esc_html__('Importo Pagato:', 'born-to-ride-booking'); ?></strong></td>
                                        <td style="color: #333333;"><?php echo esc_html($amount_paid); ?></td>
                                    </tr>
                                    <tr>
                                        <td style="color: #666666;"><strong><?php echo esc_html__('Data Pagamento:', 'born-to-ride-booking'); ?></strong></td>
                                        <td style="color: #333333;"><?php echo esc_html($payment_date); ?></td>
                                    </tr>
                                    <tr>
                                        <td style="color: #666666;"><strong><?php echo esc_html__('Pacchetto:', 'born-to-ride-booking'); ?></strong></td>
                                        <td style="color: #333333;"><?php echo esc_html($package_title); ?></td>
                                    </tr>
                                </table>
                            </div>
                            
                            <!-- Stato Generale Pagamenti -->
                            <div style="background-color: #e8f4f8; padding: 20px; border-radius: 5px; margin: 20px 0;">
                                <h3 style="color: #0097c5; margin: 0 0 15px; font-size: 18px;">
                                    <?php echo esc_html__('Stato Pagamenti Gruppo', 'born-to-ride-booking'); ?>
                                </h3>
                                
                                <?php
                                $progress_percentage = $payment_stats['completion_percentage'];
                                $progress_color = $progress_percentage >= 75 ? '#28a745' : ($progress_percentage >= 50 ? '#ffc107' : '#dc3545');
                                ?>
                                
                                <!-- Progress Bar -->
                                <div style="background-color: #e0e0e0; height: 20px; border-radius: 10px; overflow: hidden; margin: 15px 0;">
                                    <div style="background-color: <?php echo $progress_color; ?>; height: 100%; width: <?php echo $progress_percentage; ?>%; transition: width 0.3s;"></div>
                                </div>
                                
                                <p style="text-align: center; color: #333333; font-size: 24px; font-weight: bold; margin: 10px 0;">
                                    <?php echo esc_html($progress_percentage); ?>% Completato
                                </p>
                                
                                <table width="100%" cellpadding="5" style="font-size: 14px;">
                                    <tr>
                                        <td style="color: #666666;"><strong><?php echo esc_html__('Partecipanti Totali:', 'born-to-ride-booking'); ?></strong></td>
                                        <td style="color: #333333;"><?php echo esc_html($payment_stats['total_participants']); ?></td>
                                    </tr>
                                    <tr>
                                        <td style="color: #666666;"><strong><?php echo esc_html__('Hanno Pagato:', 'born-to-ride-booking'); ?></strong></td>
                                        <td style="color: #28a745; font-weight: bold;"><?php echo esc_html($payment_stats['paid_count']); ?></td>
                                    </tr>
                                    <tr>
                                        <td style="color: #666666;"><strong><?php echo esc_html__('In Attesa:', 'born-to-ride-booking'); ?></strong></td>
                                        <td style="color: #dc3545;"><?php echo esc_html($payment_stats['pending_count']); ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="2"><hr style="border: none; border-top: 1px solid #e0e0e0; margin: 10px 0;"></td>
                                    </tr>
                                    <tr>
                                        <td style="color: #666666;"><strong><?php echo esc_html__('Totale Raccolto:', 'born-to-ride-booking'); ?></strong></td>
                                        <td style="color: #28a745; font-weight: bold;">€<?php echo number_format($payment_stats['total_paid'], 2, ',', '.'); ?></td>
                                    </tr>
                                    <tr>
                                        <td style="color: #666666;"><strong><?php echo esc_html__('Ancora da Raccogliere:', 'born-to-ride-booking'); ?></strong></td>
                                        <td style="color: #333333;">€<?php echo number_format($payment_stats['total_pending'], 2, ',', '.'); ?></td>
                                    </tr>
                                </table>
                            </div>
                            
                            <!-- Call to Action -->
                            <div style="text-align: center; margin: 30px 0;">
                                <a href="<?php echo esc_url($dashboard_url); ?>" 
                                   style="display: inline-block; padding: 15px 30px; background-color: #0097c5; color: #ffffff; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">
                                    <?php echo esc_html__('Visualizza Dettagli Ordine', 'born-to-ride-booking'); ?>
                                </a>
                            </div>
                            
                            <?php if ($payment_stats['pending_count'] > 0): ?>
                            <div style="background-color: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;">
                                <p style="color: #856404; margin: 0; font-size: 14px;">
                                    <strong><?php echo esc_html__('Promemoria:', 'born-to-ride-booking'); ?></strong> 
                                    <?php echo esc_html(sprintf(
                                        __('Ci sono ancora %d partecipanti che devono completare il pagamento.', 'born-to-ride-booking'),
                                        $payment_stats['pending_count']
                                    )); ?>
                                </p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($payment_stats['completion_percentage'] == 100): ?>
                            <div style="background-color: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;">
                                <p style="color: #155724; margin: 0; font-size: 16px; font-weight: bold; text-align: center;">
                                    🎉 <?php echo esc_html__('Complimenti! Tutti i partecipanti hanno completato il pagamento!', 'born-to-ride-booking'); ?>
                                </p>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f9f9f9; padding: 30px; text-align: center; border-top: 1px solid #e0e0e0;">
                            <p style="color: #666666; font-size: 14px; margin: 0 0 10px;">
                                <?php echo esc_html__('Questa email è stata inviata automaticamente dal sistema di prenotazioni.', 'born-to-ride-booking'); ?>
                            </p>
                            <p style="color: #666666; font-size: 14px; margin: 0;">
                                <?php echo esc_html(get_bloginfo('name')); ?> | 
                                <a href="<?php echo esc_url(home_url()); ?>" style="color: #0097c5; text-decoration: none;">
                                    <?php echo esc_url(home_url()); ?>
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