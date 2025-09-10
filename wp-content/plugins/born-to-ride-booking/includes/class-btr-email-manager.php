<?php
/**
 * Classe per la gestione delle email con template moderno
 * 
 * @package Born_To_Ride_Booking
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Impedisce l'accesso diretto al file
}

class BTR_Email_Manager {
    /**
     * Invia un'email di preventivo al cliente
     *
     * @param int $preventivo_id ID del preventivo
     * @param string $pdf_path Percorso del file PDF da allegare
     * @return bool True se l'email è stata inviata con successo, false altrimenti
     */
    public function send_preventivo_email($preventivo_id, $pdf_path = '') {
        // Verifica che il preventivo esista
        $preventivo = get_post($preventivo_id);
        if (!$preventivo || $preventivo->post_type !== 'btr_preventivi') {
            error_log("Errore: Preventivo ID {$preventivo_id} non trovato o non valido");
            return false;
        }

        // Recupera i metadati del preventivo
        $cliente_nome = get_post_meta($preventivo_id, '_cliente_nome', true);
        $cliente_email = get_post_meta($preventivo_id, '_cliente_email', true);
        $nome_pacchetto = get_post_meta($preventivo_id, '_nome_pacchetto', true);
        $prezzo_totale = get_post_meta($preventivo_id, '_prezzo_totale', true);
        $data_scelta = get_post_meta($preventivo_id, '_data_pacchetto', true);
        $durata = get_post_meta($preventivo_id, '_durata', true);

        // Verifica che l'email del cliente sia valida
        if (empty($cliente_email) || !is_email($cliente_email)) {
            error_log("Errore: Email cliente non valida per il preventivo ID {$preventivo_id}");
            return false;
        }

        // Configura l'email
        $to = $cliente_email;
        $subject = sprintf(__('Il tuo preventivo per %s è pronto - Born To Ride Booking', 'born-to-ride-booking'), $nome_pacchetto);
        
        // Intestazioni email
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <noreply@' . str_replace('www.', '', $_SERVER['HTTP_HOST']) . '>',
        );

        // Crea il contenuto HTML dell'email
        $message = $this->get_email_template($preventivo_id);

        // Allega il PDF se disponibile
        if (!empty($pdf_path) && file_exists($pdf_path)) {
            // Invia l'email con allegato
            return $this->send_email_with_attachment($to, $subject, $message, $headers, $pdf_path);
        } else {
            // Invia l'email senza allegato
            return wp_mail($to, $subject, $message, $headers);
        }
    }

    /**
     * Invia un'email con allegato
     *
     * @param string $to Destinatario
     * @param string $subject Oggetto
     * @param string $message Contenuto
     * @param array $headers Intestazioni
     * @param string $attachment Percorso del file da allegare
     * @return bool True se l'email è stata inviata con successo, false altrimenti
     */
    private function send_email_with_attachment($to, $subject, $message, $headers, $attachment) {
        // Utilizza la funzione wp_mail con allegato
        return wp_mail($to, $subject, $message, $headers, array($attachment));
    }

    /**
     * Genera il template HTML per l'email del preventivo
     *
     * @param int $preventivo_id ID del preventivo
     * @return string Contenuto HTML dell'email
     */
    private function get_email_template($preventivo_id) {
        // Recupera i metadati del preventivo
        $cliente_nome = get_post_meta($preventivo_id, '_cliente_nome', true);
        $nome_pacchetto = get_post_meta($preventivo_id, '_nome_pacchetto', true);
        $prezzo_totale = get_post_meta($preventivo_id, '_prezzo_totale', true);
        $data_scelta = get_post_meta($preventivo_id, '_data_pacchetto', true);
        $durata = get_post_meta($preventivo_id, '_durata', true);
        $num_adults = get_post_meta($preventivo_id, '_num_adults', true);
        $num_children = get_post_meta($preventivo_id, '_num_children', true);
        $camere_selezionate = get_post_meta($preventivo_id, '_camere_selezionate', true);

        // URL del preventivo
        $preventivo_url = home_url('/riepilogo-preventivo/?preventivo_id=' . $preventivo_id);

        // Riepilogo camere
        $riepilogo_camere = [];
        if (!empty($camere_selezionate) && is_array($camere_selezionate)) {
            foreach ($camere_selezionate as $camera) {
                $tipo = strtolower($camera['tipo'] ?? '');
                $quantita = intval($camera['quantita'] ?? 1);
                if (!empty($tipo)) {
                    if (!isset($riepilogo_camere[$tipo])) {
                        $riepilogo_camere[$tipo] = 0;
                    }
                    $riepilogo_camere[$tipo] += $quantita;
                }
            }
        }

        $riepilogo_stringa = [];
        foreach ($riepilogo_camere as $tipo => $quantita) {
            $riepilogo_stringa[] = $quantita . ' ' . $tipo . ($quantita > 1 ? 'e' : '');
        }

        // Ottieni il logo del sito
        $logo_url = '';
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
        }

        // Colori del template
        $primary_color = '#0097c5';
        $secondary_color = '#2d3748';
        $background_color = '#f8fafc';
        $text_color = '#4a5568';
        $accent_color = '#e67e22';

        // Template HTML
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo esc_html(sprintf(__('Il tuo preventivo per %s - Born To Ride Booking', 'born-to-ride-booking'), $nome_pacchetto)); ?></title>
            <style type="text/css">
                /* Stile generale */
                body {
                    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
                    line-height: 1.6;
                    color: <?php echo $text_color; ?>;
                    background-color: <?php echo $background_color; ?>;
                    margin: 0;
                    padding: 0;
                }
                
                /* Container principale */
                .email-container {
                    max-width: 600px;
                    margin: 0 auto;
                    background-color: #ffffff;
                    border-radius: 8px;
                    overflow: hidden;
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
                }
                
                /* Header */
                .email-header {
                    background-color: <?php echo $primary_color; ?>;
                    color: #ffffff;
                    padding: 30px;
                    text-align: center;
                }
                
                .email-header img {
                    max-width: 200px;
                    height: auto;
                    margin-bottom: 20px;
                }
                
                .email-header h1 {
                    margin: 0;
                    font-size: 24px;
                    font-weight: 700;
                }
                
                /* Contenuto */
                .email-content {
                    padding: 30px;
                }
                
                .email-content h2 {
                    color: <?php echo $primary_color; ?>;
                    font-size: 20px;
                    margin-top: 0;
                    margin-bottom: 20px;
                    border-bottom: 2px solid <?php echo $primary_color; ?>;
                    padding-bottom: 10px;
                }
                
                .email-content p {
                    margin-bottom: 20px;
                    font-size: 16px;
                }
                
                /* Card */
                .info-card {
                    background-color: #f8fafc;
                    border-radius: 8px;
                    padding: 20px;
                    margin-bottom: 25px;
                    border-left: 4px solid <?php echo $primary_color; ?>;
                }
                
                .info-card h3 {
                    color: <?php echo $primary_color; ?>;
                    margin-top: 0;
                    margin-bottom: 15px;
                    font-size: 18px;
                }
                
                .info-row {
                    display: block;
                    margin-bottom: 10px;
                }
                
                .info-label {
                    font-weight: bold;
                    color: <?php echo $secondary_color; ?>;
                }
                
                .info-value {
                    color: <?php echo $text_color; ?>;
                }
                
                /* Pulsante */
                .btn-container {
                    text-align: center;
                    margin: 30px 0;
                }
                
                .btn {
                    display: inline-block;
                    background-color: <?php echo $accent_color; ?>;
                    color: #ffffff !important;
                    text-decoration: none;
                    padding: 12px 25px;
                    border-radius: 4px;
                    font-weight: bold;
                    font-size: 16px;
                    text-align: center;
                }
                
                /* Footer */
                .email-footer {
                    background-color: <?php echo $secondary_color; ?>;
                    color: rgba(255, 255, 255, 0.7);
                    padding: 30px;
                    text-align: center;
                    font-size: 14px;
                }
                
                .email-footer p {
                    margin: 5px 0;
                }
                
                .email-footer a {
                    color: #ffffff;
                    text-decoration: none;
                }
                
                /* Responsive */
                @media screen and (max-width: 600px) {
                    .email-container {
                        width: 100% !important;
                    }
                    
                    .email-header, .email-content, .email-footer {
                        padding: 20px !important;
                    }
                }
            </style>
        </head>
        <body>
            <div class="email-container">
                <div class="email-header">
                    <?php if (!empty($logo_url)): ?>
                        <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
                    <?php else: ?>
                        <h1><?php echo esc_html(get_bloginfo('name')); ?></h1>
                    <?php endif; ?>
                    <h1><?php esc_html_e('Il tuo preventivo è pronto!', 'born-to-ride-booking'); ?></h1>
                </div>
                
                <div class="email-content">
                    <p><?php echo sprintf(esc_html__('Ciao %s,', 'born-to-ride-booking'), esc_html($cliente_nome)); ?></p>
                    
                    <p><?php esc_html_e('Grazie per aver richiesto un preventivo. Abbiamo preparato un\'offerta personalizzata per il tuo viaggio.', 'born-to-ride-booking'); ?></p>
                    
                    <div class="info-card">
                        <h3><?php esc_html_e('Dettagli del Pacchetto', 'born-to-ride-booking'); ?></h3>
                        
                        <div class="info-row">
                            <span class="info-label"><?php esc_html_e('Pacchetto:', 'born-to-ride-booking'); ?></span>
                            <span class="info-value"><?php echo esc_html($nome_pacchetto); ?></span>
                        </div>
                        
                        <div class="info-row">
                            <span class="info-label"><?php esc_html_e('Data:', 'born-to-ride-booking'); ?></span>
                            <span class="info-value"><?php echo esc_html($data_scelta); ?></span>
                        </div>
                        
                        <div class="info-row">
                            <span class="info-label"><?php esc_html_e('Durata:', 'born-to-ride-booking'); ?></span>
                            <span class="info-value"><?php echo esc_html($durata); ?></span>
                        </div>
                        
                        <div class="info-row">
                            <span class="info-label"><?php esc_html_e('Partecipanti:', 'born-to-ride-booking'); ?></span>
                            <span class="info-value">
                                <?php echo esc_html($num_adults); ?> <?php esc_html_e('adulti', 'born-to-ride-booking'); ?>
                                <?php if ($num_children > 0): ?>
                                    + <?php echo esc_html($num_children); ?> <?php esc_html_e('bambini', 'born-to-ride-booking'); ?>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <div class="info-row">
                            <span class="info-label"><?php esc_html_e('Sistemazione:', 'born-to-ride-booking'); ?></span>
                            <span class="info-value"><?php echo esc_html(implode(', ', $riepilogo_stringa)); ?></span>
                        </div>
                        
                        <div class="info-row">
                            <span class="info-label"><?php esc_html_e('Prezzo Totale:', 'born-to-ride-booking'); ?></span>
                            <span class="info-value">€<?php echo number_format($prezzo_totale, 2, ',', '.'); ?></span>
                        </div>
                    </div>
                    
                    <p><?php esc_html_e('Abbiamo allegato a questa email il PDF con tutti i dettagli del preventivo. Per visualizzare il preventivo online o procedere con la prenotazione, clicca sul pulsante qui sotto:', 'born-to-ride-booking'); ?></p>
                    
                    <div class="btn-container">
                        <a href="<?php echo esc_url($preventivo_url); ?>" class="btn"><?php esc_html_e('Visualizza Preventivo', 'born-to-ride-booking'); ?></a>
                    </div>
                    
                    <p><?php esc_html_e('Il preventivo ha una validità di 7 giorni dalla data di emissione. Per qualsiasi domanda o chiarimento, non esitare a contattarci.', 'born-to-ride-booking'); ?></p>
                    
                    <p><?php esc_html_e('Cordiali saluti,', 'born-to-ride-booking'); ?><br>
                    <?php echo esc_html(get_bloginfo('name')); ?></p>
                </div>
                
                <div class="email-footer">
                    <p><?php echo esc_html(get_bloginfo('name')); ?> - <?php echo esc_html(get_bloginfo('description')); ?></p>
                    <p><a href="<?php echo esc_url(home_url()); ?>"><?php echo esc_url(home_url()); ?></a></p>
                    <p><?php esc_html_e('Email:', 'born-to-ride-booking'); ?> info@<?php echo str_replace('www.', '', $_SERVER['HTTP_HOST']); ?></p>
                    <p><?php esc_html_e('Tel:', 'born-to-ride-booking'); ?> +39 123 456 7890</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
