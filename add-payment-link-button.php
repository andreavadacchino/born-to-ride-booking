<?php
// Script per aggiungere pulsante link pagamento nella tabella partecipanti
// v1.0.238 - Migliora UX dashboard pagamenti gruppo

$file = __DIR__ . '/includes/class-btr-organizer-dashboard.php';

if (!file_exists($file)) {
    die("ERRORE: File non trovato: $file\n");
}

$content = file_get_contents($file);
if (!$content) {
    die("ERRORE: Impossibile leggere il file\n");
}

// Trova la sezione delle azioni nella tabella
$old_actions = '<td data-title="<?php esc_attr_e(\'Azioni\', \'born-to-ride-booking\'); ?>">
                                <?php if ($payment->payment_status === \'pending\'): ?>
                                    <button class="button btn-send-reminder" 
                                            data-payment-id="<?php echo esc_attr($payment->payment_id); ?>"
                                            data-email="<?php echo esc_attr($payment->participant_email); ?>"
                                            data-name="<?php echo esc_attr($payment->participant_name); ?>">
                                        <?php esc_html_e(\'Invia Promemoria\', \'born-to-ride-booking\'); ?>
                                    </button>';

// Nuove azioni con pulsante link pagamento
$new_actions = '<td data-title="<?php esc_attr_e(\'Azioni\', \'born-to-ride-booking\'); ?>">
                                <?php if ($payment->payment_status === \'pending\'): ?>
                                    <button class="button btn-copy-link" 
                                            data-payment-hash="<?php echo esc_attr($payment->payment_hash); ?>"
                                            data-name="<?php echo esc_attr($payment->participant_name); ?>"
                                            title="<?php esc_attr_e(\'Copia link pagamento\', \'born-to-ride-booking\'); ?>">
                                        <span class="dashicons dashicons-admin-links"></span>
                                        <?php esc_html_e(\'Link\', \'born-to-ride-booking\'); ?>
                                    </button>
                                    <button class="button btn-send-reminder" 
                                            data-payment-id="<?php echo esc_attr($payment->payment_id); ?>"
                                            data-email="<?php echo esc_attr($payment->participant_email); ?>"
                                            data-name="<?php echo esc_attr($payment->participant_name); ?>">
                                        <?php esc_html_e(\'Invia Promemoria\', \'born-to-ride-booking\'); ?>
                                    </button>';

if (strpos($content, $old_actions) === false) {
    die("ERRORE: Sezione azioni non trovata nel formato atteso\n");
}

$content = str_replace($old_actions, $new_actions, $content);

// Aggiungi JavaScript per gestire il click sul pulsante
$old_script_end = '        <script>
        jQuery(document).ready(function($) {';

$new_script_end = '        <script>
        jQuery(document).ready(function($) {
            // Gestione copia link pagamento
            $(\'.btn-copy-link\').on(\'click\', function(e) {
                e.preventDefault();
                var hash = $(this).data(\'payment-hash\');
                var name = $(this).data(\'name\');
                var paymentUrl = \'<?php echo home_url(\'/pagamento-gruppo/\'); ?>\' + hash;
                
                // Crea elemento temporaneo per copiare
                var $temp = $(\'<input>\');
                $(\'body\').append($temp);
                $temp.val(paymentUrl).select();
                document.execCommand(\'copy\');
                $temp.remove();
                
                // Feedback visivo
                var $btn = $(this);
                var originalText = $btn.html();
                $btn.html(\'<span class="dashicons dashicons-yes"></span> Copiato!\');
                setTimeout(function() {
                    $btn.html(originalText);
                }, 2000);
                
                // Log per debug
                console.log(\'Link copiato per \' + name + \': \' + paymentUrl);
            });
            
';

$content = str_replace($old_script_end, $new_script_end, $content);

// Aggiungi stili per il pulsante
$old_styles = '        .payment-status.warning {
            background: #fff3cd;
            color: #856404;
        }';

$new_styles = '        .payment-status.warning {
            background: #fff3cd;
            color: #856404;
        }
        .btn-copy-link {
            margin-right: 5px;
        }
        .btn-copy-link .dashicons {
            font-size: 16px;
            line-height: 28px;
            vertical-align: middle;
            margin-right: 4px;
        }';

$content = str_replace($old_styles, $new_styles, $content);

// Scrivi il file aggiornato
if (file_put_contents($file, $content)) {
    echo "✅ File aggiornato con successo!\n";
    echo "✅ Aggiunto pulsante 'Link' per copiare il link di pagamento\n";
    echo "✅ Il pulsante copia automaticamente il link negli appunti\n";
    echo "\n📌 Test:\n";
    echo "1. Accedi a: http://localhost:10018/mio-account/group-payments/?payment-group=37503\n";
    echo "2. Nella tabella partecipanti vedrai il pulsante 'Link' accanto a 'Invia Promemoria'\n";
    echo "3. Clicca sul pulsante per copiare il link di pagamento\n";
} else {
    die("ERRORE: Impossibile scrivere il file\n");
}
