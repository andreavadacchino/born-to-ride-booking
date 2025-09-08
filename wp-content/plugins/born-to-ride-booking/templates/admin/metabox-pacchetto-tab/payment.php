<?php
// Impedisce l'accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Recupera i valori salvati
$payment_mode = get_post_meta($post->ID, '_btr_payment_mode', true) ?: 'full';
$deposit_percentage = get_post_meta($post->ID, '_btr_deposit_percentage', true) ?: 30;
$enable_group_payment = get_post_meta($post->ID, '_btr_enable_group_payment', true);
$group_payment_threshold = get_post_meta($post->ID, '_btr_group_payment_threshold', true) ?: 10;
$payment_reminder_days = get_post_meta($post->ID, '_btr_payment_reminder_days', true) ?: 7;
?>

<div class="btr-metabox-section">
    <h2>Configurazione Modalità di Pagamento</h2>
    <p class="description">Configura le opzioni di pagamento disponibili per questo pacchetto.</p>
    
    <!-- Modalità di Pagamento -->
    <div class="btr-field-group">
        <label for="btr_payment_mode">
            <strong>Modalità di Pagamento</strong>
            <span class="required">*</span>
        </label>
        <select id="btr_payment_mode" name="btr_payment_mode" class="btr-select" required>
            <option value="full" <?php selected($payment_mode, 'full'); ?>>Solo Pagamento Completo</option>
            <option value="deposit" <?php selected($payment_mode, 'deposit'); ?>>Caparra + Saldo</option>
            <option value="both" <?php selected($payment_mode, 'both'); ?>>Entrambe le opzioni</option>
        </select>
        <small>Seleziona quali modalità di pagamento saranno disponibili per questo pacchetto.</small>
    </div>
    
    <!-- Configurazione Caparra (visibile solo se deposit o both) -->
    <div id="deposit-settings" class="btr-conditional-section" style="<?php echo in_array($payment_mode, ['deposit', 'both']) ? 'display:block;' : 'display:none;'; ?>">
        <h3>Impostazioni Caparra</h3>
        
        <div class="btr-field-group">
            <label for="btr_deposit_percentage">
                <strong>Percentuale Caparra (%)</strong>
            </label>
            <input type="number" 
                   id="btr_deposit_percentage" 
                   name="btr_deposit_percentage" 
                   value="<?php echo esc_attr($deposit_percentage); ?>" 
                   min="10" 
                   max="90" 
                   step="5"
                   class="btr-input-number">
            <small>Percentuale dell'importo totale richiesta come caparra (10-90%).</small>
        </div>
        
        <div class="btr-field-group">
            <label for="btr_payment_reminder_days">
                <strong>Giorni Anticipo Promemoria Saldo</strong>
            </label>
            <input type="number" 
                   id="btr_payment_reminder_days" 
                   name="btr_payment_reminder_days" 
                   value="<?php echo esc_attr($payment_reminder_days); ?>" 
                   min="1" 
                   max="30"
                   class="btr-input-number">
            <small>Quanti giorni prima della partenza inviare il promemoria per il saldo.</small>
        </div>
    </div>
    
    <!-- Pagamento Suddiviso tra Partecipanti -->
    <div class="btr-section-divider"></div>
    
    <h3>Pagamento Suddiviso tra Partecipanti</h3>
    
    <div class="btr-field-group">
        <div class="btr-switch-container">
            <input type="checkbox" 
                   id="btr_enable_group_payment" 
                   name="btr_enable_group_payment" 
                   value="1" 
                   <?php checked($enable_group_payment, '1'); ?>>
            <label for="btr_enable_group_payment">
                <strong>Abilita Pagamento di Gruppo</strong>
            </label>
            <label class="btr-switch" for="btr_enable_group_payment">
                <span class="btr-switch-handle">
                    <svg class="btr-switch-icon btr-switch-icon--on" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                    </svg>
                    <svg class="btr-switch-icon btr-switch-icon--off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                    </svg>
                </span>
            </label>
        </div>
        <small>Permetti ai gruppi di suddividere il pagamento tra i partecipanti.</small>
    </div>
    
    <!-- Soglia Minima Partecipanti (visibile solo se gruppo abilitato) -->
    <div id="group-settings" class="btr-conditional-section" style="<?php echo $enable_group_payment ? 'display:block;' : 'display:none;'; ?>">
        <div class="btr-field-group">
            <label for="btr_group_payment_threshold">
                <strong>Soglia Minima Partecipanti</strong>
            </label>
            <input type="number" 
                   id="btr_group_payment_threshold" 
                   name="btr_group_payment_threshold" 
                   value="<?php echo esc_attr($group_payment_threshold); ?>" 
                   min="2" 
                   max="50"
                   class="btr-input-number">
            <small>Numero minimo di partecipanti per attivare l'opzione di pagamento suddiviso.</small>
        </div>
        
        <div class="btr-info-box">
            <span class="dashicons dashicons-info"></span>
            <div>
                <strong>Come funziona il pagamento di gruppo:</strong>
                <ul>
                    <li>Disponibile solo per gruppi con almeno il numero di partecipanti indicato</li>
                    <li>I bambini non possono pagare e devono essere assegnati a un adulto</li>
                    <li>Ogni partecipante riceve il proprio link di pagamento</li>
                    <li>La prenotazione è confermata solo quando tutti hanno pagato</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<style>
.btr-metabox-section {
    padding: 20px;
    background: #f9f9f9;
    border-radius: 8px;
}

.btr-metabox-section h2 {
    margin-top: 0;
    margin-bottom: 10px;
    font-size: 18px;
    font-weight: 600;
}

.btr-metabox-section h3 {
    margin-top: 20px;
    margin-bottom: 15px;
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

.btr-field-group {
    margin-bottom: 20px;
}

.btr-field-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #23282d;
}

.btr-field-group small {
    display: block;
    margin-top: 5px;
    color: #666;
    font-size: 13px;
}

.btr-select,
.btr-input-number {
    width: 100%;
    max-width: 300px;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.btr-input-number {
    max-width: 120px;
}

.btr-section-divider {
    margin: 30px 0;
    border-top: 1px solid #ddd;
}

.btr-conditional-section {
    margin-top: 20px;
    padding: 20px;
    background: #fff;
    border: 1px solid #e1e1e1;
    border-radius: 4px;
}

.btr-info-box {
    display: flex;
    gap: 15px;
    margin-top: 20px;
    padding: 15px;
    background: #e8f4fd;
    border: 1px solid #b8daff;
    border-radius: 4px;
}

.btr-info-box .dashicons {
    flex-shrink: 0;
    color: #2271b1;
    font-size: 24px;
}

.btr-info-box ul {
    margin: 10px 0 0 20px;
    list-style-type: disc;
}

.btr-info-box li {
    margin-bottom: 5px;
    font-size: 13px;
}

.required {
    color: #dc3232;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Toggle deposit settings visibility
    $('#btr_payment_mode').on('change', function() {
        const mode = $(this).val();
        if (mode === 'deposit' || mode === 'both') {
            $('#deposit-settings').slideDown();
        } else {
            $('#deposit-settings').slideUp();
        }
    });
    
    // Toggle group payment settings visibility
    $('#btr_enable_group_payment').on('change', function() {
        if ($(this).is(':checked')) {
            $('#group-settings').slideDown();
        } else {
            $('#group-settings').slideUp();
        }
    });
});
</script>