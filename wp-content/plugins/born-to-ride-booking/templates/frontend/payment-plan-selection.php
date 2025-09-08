<?php
/**
 * Template per la selezione del piano di pagamento
 * 
 * Variabili disponibili:
 * - $preventivo_id: ID del preventivo
 * - $anagrafici: Array con i dati dei partecipanti
 * - $adults_count: Numero di adulti che possono pagare
 * 
 * @package BornToRideBooking
 * @since 1.0.98
 */

if (!defined('ABSPATH')) {
    exit;
}

// Calcola totale dal preventivo
$riepilogo = get_post_meta($preventivo_id, '_riepilogo_calcoli_dettagliato', true);
$total_amount = 0;

if (!empty($riepilogo) && isset($riepilogo['totali']['totale_finale'])) {
    $total_amount = floatval($riepilogo['totali']['totale_finale']);
} else {
    $total_amount = floatval(get_post_meta($preventivo_id, '_prezzo_totale', true));
}

// Calcola importi per le varie opzioni
$deposit_percentage = 30; // Default 30%
$deposit_amount = round($total_amount * ($deposit_percentage / 100), 2);
$balance_amount = $total_amount - $deposit_amount;
$per_person_amount = round($total_amount / $adults_count, 2);
?>

<div class="btr-payment-plan-selection" id="btr-payment-plan-modal">
    <div class="btr-modal-overlay"></div>
    <div class="btr-modal-content">
        <div class="btr-modal-header">
            <h2><?php esc_html_e('Scegli la modalità di pagamento', 'born-to-ride-booking'); ?></h2>
            <p class="btr-modal-subtitle">
                <?php esc_html_e('Seleziona come preferisci gestire il pagamento per questa prenotazione', 'born-to-ride-booking'); ?>
            </p>
        </div>

        <div class="btr-payment-options">
            <!-- Opzione 1: Pagamento Completo -->
            <div class="btr-payment-option" data-plan-type="full">
                <div class="btr-option-header">
                    <input type="radio" name="payment_plan" id="plan_full" value="full" checked>
                    <label for="plan_full">
                        <span class="btr-option-icon">💳</span>
                        <span class="btr-option-title"><?php esc_html_e('Pagamento Completo', 'born-to-ride-booking'); ?></span>
                    </label>
                </div>
                <div class="btr-option-details">
                    <p><?php esc_html_e('Paga l\'intero importo in una sola soluzione', 'born-to-ride-booking'); ?></p>
                    <div class="btr-price-display">
                        <span class="btr-price-label"><?php esc_html_e('Totale da pagare:', 'born-to-ride-booking'); ?></span>
                        <span class="btr-price-amount"><?php echo btr_format_price_i18n($total_amount); ?></span>
                    </div>
                </div>
            </div>

            <!-- Opzione 2: Caparra + Saldo -->
            <div class="btr-payment-option" data-plan-type="deposit_balance">
                <div class="btr-option-header">
                    <input type="radio" name="payment_plan" id="plan_deposit" value="deposit_balance">
                    <label for="plan_deposit">
                        <span class="btr-option-icon">📅</span>
                        <span class="btr-option-title"><?php esc_html_e('Caparra + Saldo', 'born-to-ride-booking'); ?></span>
                    </label>
                </div>
                <div class="btr-option-details">
                    <p><?php esc_html_e('Prenota con una caparra e salda il resto prima della partenza', 'born-to-ride-booking'); ?></p>
                    <div class="btr-price-breakdown">
                        <div class="btr-price-row">
                            <span class="btr-price-label"><?php esc_html_e('Caparra oggi:', 'born-to-ride-booking'); ?></span>
                            <span class="btr-price-amount"><?php echo btr_format_price_i18n($deposit_amount); ?></span>
                            <span class="btr-price-percentage">(<?php echo $deposit_percentage; ?>%)</span>
                        </div>
                        <div class="btr-price-row">
                            <span class="btr-price-label"><?php esc_html_e('Saldo successivo:', 'born-to-ride-booking'); ?></span>
                            <span class="btr-price-amount"><?php echo btr_format_price_i18n($balance_amount); ?></span>
                        </div>
                    </div>
                    <div class="btr-deposit-slider">
                        <label for="deposit_percentage"><?php esc_html_e('Percentuale caparra:', 'born-to-ride-booking'); ?></label>
                        <input type="range" id="deposit_percentage" min="10" max="90" step="5" value="30">
                        <span class="btr-deposit-value">30%</span>
                    </div>
                </div>
            </div>

            <!-- Opzione 3: Suddivisione Gruppo -->
            <div class="btr-payment-option" data-plan-type="group_split">
                <div class="btr-option-header">
                    <input type="radio" name="payment_plan" id="plan_group" value="group_split">
                    <label for="plan_group">
                        <span class="btr-option-icon">👥</span>
                        <span class="btr-option-title"><?php esc_html_e('Suddivisione tra Partecipanti', 'born-to-ride-booking'); ?></span>
                    </label>
                </div>
                <div class="btr-option-details">
                    <p><?php esc_html_e('Ogni partecipante adulto riceve un link per pagare la propria quota', 'born-to-ride-booking'); ?></p>
                    <div class="btr-price-display">
                        <span class="btr-price-label"><?php esc_html_e('Quota per persona:', 'born-to-ride-booking'); ?></span>
                        <span class="btr-price-amount" id="per-person-amount"><?php echo btr_format_price_i18n($per_person_amount); ?></span>
                    </div>
                    
                    <!-- Configurazione distribuzione quote -->
                    <div class="btr-group-configuration" style="display: none;">
                        <h4><?php esc_html_e('Configura distribuzione quote', 'born-to-ride-booking'); ?></h4>
                        <p class="btr-info">
                            <?php 
                            printf(
                                esc_html__('Ci sono %d adulti che possono partecipare al pagamento.', 'born-to-ride-booking'),
                                $adults_count
                            );
                            ?>
                        </p>
                        
                        <div class="btr-participants-list">
                            <?php 
                            $adult_index = 0;
                            foreach ($anagrafici as $index => $participant): 
                                // Solo adulti possono pagare
                                if (!empty($participant['fascia']) && $participant['fascia'] !== 'adulto') {
                                    continue;
                                }
                                
                                $nome = esc_html($participant['nome'] ?? '');
                                $cognome = esc_html($participant['cognome'] ?? '');
                                $full_name = trim($nome . ' ' . $cognome);
                                if (empty($full_name)) {
                                    $full_name = sprintf(__('Partecipante %d', 'born-to-ride-booking'), $index + 1);
                                }
                                ?>
                                <div class="btr-participant-row" data-participant-index="<?php echo $index; ?>">
                                    <div class="btr-participant-info">
                                        <input type="checkbox" 
                                               id="participant_<?php echo $index; ?>" 
                                               name="paying_participants[]" 
                                               value="<?php echo $index; ?>"
                                               class="btr-paying-participant"
                                               checked>
                                        <label for="participant_<?php echo $index; ?>">
                                            <?php echo $full_name; ?>
                                        </label>
                                    </div>
                                    <div class="btr-participant-share">
                                        <label><?php esc_html_e('Quota:', 'born-to-ride-booking'); ?></label>
                                        <input type="number" 
                                               name="participant_share[<?php echo $index; ?>]" 
                                               class="btr-share-percentage"
                                               value="<?php echo round(100 / $adults_count, 1); ?>"
                                               min="0" 
                                               max="100" 
                                               step="0.1">
                                        <span>%</span>
                                        <span class="btr-share-amount" data-base-amount="<?php echo $total_amount; ?>">
                                            = <?php echo btr_format_price_i18n($per_person_amount); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php 
                            $adult_index++;
                            endforeach; 
                            ?>
                        </div>
                        
                        <div class="btr-total-check">
                            <span><?php esc_html_e('Totale quote:', 'born-to-ride-booking'); ?></span>
                            <span id="total-percentage">100%</span>
                            <span class="btr-validation-message"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="btr-modal-footer">
            <button type="button" class="btr-btn btr-btn-secondary" id="btr-cancel-plan">
                <?php esc_html_e('Annulla', 'born-to-ride-booking'); ?>
            </button>
            <button type="button" class="btr-btn btr-btn-primary" id="btr-confirm-plan">
                <?php esc_html_e('Conferma e Procedi', 'born-to-ride-booking'); ?>
            </button>
        </div>

        <input type="hidden" id="btr-preventivo-id" value="<?php echo esc_attr($preventivo_id); ?>">
        <?php wp_nonce_field('btr_payment_plan_nonce', 'btr_payment_plan_nonce'); ?>
    </div>
</div>

<style>
/* Stili CSS per il modal */
.btr-payment-plan-selection {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btr-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
}

.btr-modal-content {
    position: relative;
    background: white;
    max-width: 800px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
}

.btr-modal-header {
    padding: 30px;
    text-align: center;
    border-bottom: 1px solid #e0e0e0;
}

.btr-modal-header h2 {
    margin: 0 0 10px;
    font-size: 28px;
    color: #333;
}

.btr-modal-subtitle {
    margin: 0;
    color: #666;
    font-size: 16px;
}

.btr-payment-options {
    padding: 30px;
}

.btr-payment-option {
    margin-bottom: 20px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.btr-payment-option:hover {
    border-color: #0097c5;
    box-shadow: 0 4px 12px rgba(0, 151, 197, 0.1);
}

.btr-payment-option.selected {
    border-color: #0097c5;
    background: #f8fbfc;
}

.btr-option-header {
    padding: 20px;
    display: flex;
    align-items: center;
    cursor: pointer;
}

.btr-option-header input[type="radio"] {
    margin-right: 15px;
}

.btr-option-header label {
    flex: 1;
    display: flex;
    align-items: center;
    cursor: pointer;
    margin: 0;
}

.btr-option-icon {
    font-size: 24px;
    margin-right: 15px;
}

.btr-option-title {
    font-size: 18px;
    font-weight: 600;
    color: #333;
}

.btr-option-details {
    padding: 0 20px 20px 60px;
    display: none;
}

.btr-payment-option.selected .btr-option-details {
    display: block;
}

.btr-price-display,
.btr-price-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 15px 0;
    padding: 10px;
    background: #f5f5f5;
    border-radius: 4px;
}

.btr-price-label {
    color: #666;
}

.btr-price-amount {
    font-size: 20px;
    font-weight: 600;
    color: #0097c5;
}

.btr-price-percentage {
    color: #999;
    font-size: 14px;
    margin-left: 10px;
}

.btr-deposit-slider {
    margin-top: 20px;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 4px;
}

.btr-deposit-slider label {
    display: block;
    margin-bottom: 10px;
    font-weight: 500;
}

.btr-deposit-slider input[type="range"] {
    width: 100%;
    margin-bottom: 10px;
}

.btr-deposit-value {
    display: inline-block;
    padding: 5px 15px;
    background: #0097c5;
    color: white;
    border-radius: 20px;
    font-weight: 600;
}

.btr-group-configuration {
    margin-top: 20px;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 4px;
}

.btr-group-configuration h4 {
    margin: 0 0 15px;
    font-size: 16px;
    color: #333;
}

.btr-info {
    background: #e3f2fd;
    padding: 10px 15px;
    border-radius: 4px;
    margin-bottom: 20px;
    color: #1976d2;
}

.btr-participants-list {
    margin: 20px 0;
}

.btr-participant-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    margin-bottom: 10px;
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
}

.btr-participant-info {
    display: flex;
    align-items: center;
}

.btr-participant-info input[type="checkbox"] {
    margin-right: 10px;
}

.btr-participant-share {
    display: flex;
    align-items: center;
    gap: 10px;
}

.btr-share-percentage {
    width: 80px;
    padding: 5px 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    text-align: center;
}

.btr-share-amount {
    min-width: 100px;
    text-align: right;
    font-weight: 600;
    color: #0097c5;
}

.btr-total-check {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    margin-top: 20px;
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 4px;
}

.btr-validation-message {
    color: #d32f2f;
    font-size: 14px;
}

.btr-validation-message.valid {
    color: #388e3c;
}

.btr-modal-footer {
    padding: 20px 30px;
    border-top: 1px solid #e0e0e0;
    display: flex;
    justify-content: flex-end;
    gap: 15px;
}

.btr-btn {
    padding: 12px 30px;
    border: none;
    border-radius: 4px;
    font-size: 16px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btr-btn-primary {
    background: #0097c5;
    color: white;
}

.btr-btn-primary:hover {
    background: #007aa3;
}

.btr-btn-secondary {
    background: #f5f5f5;
    color: #666;
}

.btr-btn-secondary:hover {
    background: #e0e0e0;
}

/* Responsive */
@media (max-width: 768px) {
    .btr-modal-content {
        width: 95%;
        max-height: 95vh;
    }
    
    .btr-participant-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .btr-participant-share {
        width: 100%;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Gestione selezione piano di pagamento
    $('.btr-payment-option').on('click', function() {
        $('.btr-payment-option').removeClass('selected');
        $(this).addClass('selected');
        $(this).find('input[type="radio"]').prop('checked', true);
        
        // Mostra/nascondi configurazione gruppo
        if ($(this).data('plan-type') === 'group_split') {
            $('.btr-group-configuration').slideDown();
        } else {
            $('.btr-group-configuration').slideUp();
        }
    });
    
    // Gestione slider caparra
    $('#deposit_percentage').on('input', function() {
        const percentage = $(this).val();
        const total = <?php echo $total_amount; ?>;
        const deposit = (total * percentage / 100).toFixed(2);
        const balance = (total - deposit).toFixed(2);
        
        $('.btr-deposit-value').text(percentage + '%');
        $('.btr-payment-option[data-plan-type="deposit_balance"] .btr-price-row').eq(0).find('.btr-price-amount').text('€' + deposit);
        $('.btr-payment-option[data-plan-type="deposit_balance"] .btr-price-row').eq(1).find('.btr-price-amount').text('€' + balance);
    });
    
    // Gestione cambio quote partecipanti
    $('.btr-share-percentage').on('input', updateShareAmounts);
    $('.btr-paying-participant').on('change', updateShareAmounts);
    
    function updateShareAmounts() {
        const total = <?php echo $total_amount; ?>;
        let totalPercentage = 0;
        
        $('.btr-participant-row').each(function() {
            const isChecked = $(this).find('.btr-paying-participant').is(':checked');
            const shareInput = $(this).find('.btr-share-percentage');
            
            if (isChecked) {
                const percentage = parseFloat(shareInput.val()) || 0;
                totalPercentage += percentage;
                
                const amount = (total * percentage / 100).toFixed(2);
                $(this).find('.btr-share-amount').text('= €' + amount);
                shareInput.prop('disabled', false);
            } else {
                shareInput.prop('disabled', true);
                $(this).find('.btr-share-amount').text('= €0,00');
            }
        });
        
        // Aggiorna totale e validazione
        $('#total-percentage').text(totalPercentage.toFixed(1) + '%');
        
        if (Math.abs(totalPercentage - 100) < 0.1) {
            $('.btr-validation-message').text('✓ Distribuzione corretta').addClass('valid');
            $('#btr-confirm-plan').prop('disabled', false);
        } else {
            $('.btr-validation-message').text('⚠ Il totale deve essere 100%').removeClass('valid');
            $('#btr-confirm-plan').prop('disabled', true);
        }
    }
    
    // Conferma piano di pagamento
    $('#btr-confirm-plan').on('click', function() {
        const selectedPlan = $('input[name="payment_plan"]:checked').val();
        const preventivoId = $('#btr-preventivo-id').val();
        const nonce = $('#btr_payment_plan_nonce').val();
        
        let data = {
            action: 'btr_save_payment_plan',
            nonce: nonce,
            preventivo_id: preventivoId,
            plan_type: selectedPlan
        };
        
        // Aggiungi dati specifici per tipo di piano
        if (selectedPlan === 'deposit_balance') {
            data.deposit_percentage = $('#deposit_percentage').val();
        } else if (selectedPlan === 'group_split') {
            data.payment_distribution = [];
            $('.btr-participant-row').each(function() {
                if ($(this).find('.btr-paying-participant').is(':checked')) {
                    data.payment_distribution.push({
                        participant_index: $(this).data('participant-index'),
                        percentage: $(this).find('.btr-share-percentage').val()
                    });
                }
            });
        }
        
        // Disabilita pulsante durante invio
        $(this).prop('disabled', true).text('Elaborazione...');
        
        // Invia richiesta AJAX
        $.post('<?php echo admin_url('admin-ajax.php'); ?>', data, function(response) {
            if (response.success) {
                // Procedi al checkout in base al tipo di piano
                if (selectedPlan === 'full') {
                    // Redirect al checkout standard
                    window.location.href = '<?php echo wc_get_checkout_url(); ?>';
                } else if (selectedPlan === 'deposit_balance') {
                    // Redirect al checkout caparra
                    window.location.href = '<?php echo home_url('/checkout-deposit/'); ?>?preventivo=' + preventivoId;
                } else if (selectedPlan === 'group_split') {
                    // Mostra modal con link generati
                    generateGroupLinks(preventivoId, data.payment_distribution);
                }
            } else {
                alert('Errore: ' + response.data.message);
                $('#btr-confirm-plan').prop('disabled', false).text('Conferma e Procedi');
            }
        });
    });
    
    // Genera link per pagamento gruppo
    function generateGroupLinks(preventivoId, distribution) {
        $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
            action: 'btr_generate_group_split_links',
            nonce: $('#btr_payment_plan_nonce').val(),
            preventivo_id: preventivoId,
            distribution: distribution
        }, function(response) {
            if (response.success) {
                // Mostra modal con i link generati
                showGroupLinksModal(response.data.links);
            } else {
                alert('Errore nella generazione dei link: ' + response.data.message);
            }
        });
    }
    
    // Mostra modal con link di pagamento
    function showGroupLinksModal(links) {
        // Implementazione del modal con i link
        // TODO: Creare template per visualizzazione link
        console.log('Link generati:', links);
        alert('Link di pagamento generati con successo! Verranno inviati via email ai partecipanti.');
        
        // Redirect a pagina di conferma
        window.location.href = '<?php echo home_url('/conferma-prenotazione/'); ?>?status=links_sent';
    }
    
    // Chiudi modal
    $('#btr-cancel-plan, .btr-modal-overlay').on('click', function() {
        $('#btr-payment-plan-modal').fadeOut();
    });
    
    // Inizializza stato
    $('.btr-payment-option').first().click();
    updateShareAmounts();
});
</script>