<?php
/**
 * Template part per assegnazione dettagliata costi nel pagamento di gruppo
 * 
 * @package BornToRideBooking
 * @since 1.0.100
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Variabili necessarie passate dal template principale
$preventivo_id = $preventivo_id ?? 0;
$adulti_paganti = $adulti_paganti ?? [];
$anagrafici = $anagrafici ?? [];
$costi_extra_meta = $costi_extra_meta ?? [];

// Recupera assicurazioni dal preventivo
$assicurazioni_attive = [];
if (!empty($anagrafici)) {
    foreach ($anagrafici as $index => $persona) {
        if (!empty($persona['assicurazioni_dettagliate'])) {
            foreach ($persona['assicurazioni_dettagliate'] as $slug => $dettagli) {
                if (isset($persona['assicurazioni'][$slug]) && $persona['assicurazioni'][$slug] === '1') {
                    $assicurazioni_attive[] = [
                        'persona_index' => $index,
                        'persona_nome' => $persona['nome'] . ' ' . $persona['cognome'],
                        'slug' => $slug,
                        'descrizione' => $dettagli['descrizione'],
                        'importo' => floatval($dettagli['importo'] ?? 0)
                    ];
                }
            }
        }
    }
}
?>

<div class="group-payment-detailed-assignment">
    <h3><?php esc_html_e('Assegnazione Dettagliata Costi', 'born-to-ride-booking'); ?></h3>
    <p class="description">
        <?php esc_html_e('Assegna ogni costo specifico a un partecipante. Puoi dividere anche singoli costi tra più persone.', 'born-to-ride-booking'); ?>
    </p>
    
    <!-- Assegnazione Assicurazioni -->
    <?php if (!empty($assicurazioni_attive)): ?>
    <div class="cost-section insurance-assignment">
        <h4><?php esc_html_e('Assicurazioni', 'born-to-ride-booking'); ?></h4>
        <table class="assignment-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Assicurazione', 'born-to-ride-booking'); ?></th>
                    <th><?php esc_html_e('Per', 'born-to-ride-booking'); ?></th>
                    <th><?php esc_html_e('Importo', 'born-to-ride-booking'); ?></th>
                    <th><?php esc_html_e('Pagata da', 'born-to-ride-booking'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assicurazioni_attive as $ass): ?>
                <tr>
                    <td><?php echo esc_html($ass['descrizione']); ?></td>
                    <td><?php echo esc_html($ass['persona_nome']); ?></td>
                    <td>€ <?php echo number_format($ass['importo'], 2, ',', '.'); ?></td>
                    <td>
                        <select name="insurance_payer[<?php echo $ass['persona_index']; ?>][<?php echo $ass['slug']; ?>]" 
                                class="insurance-payer-select"
                                data-amount="<?php echo $ass['importo']; ?>">
                            <option value=""><?php esc_html_e('Seleziona...', 'born-to-ride-booking'); ?></option>
                            <?php foreach ($adulti_paganti as $adulto): ?>
                                <option value="<?php echo esc_attr($adulto['index']); ?>">
                                    <?php echo esc_html($adulto['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="split"><?php esc_html_e('Dividi equamente', 'born-to-ride-booking'); ?></option>
                        </select>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- Assegnazione Costi Extra -->
    <?php if (!empty($costi_extra_meta)): ?>
    <div class="cost-section extras-assignment">
        <h4><?php esc_html_e('Costi Extra', 'born-to-ride-booking'); ?></h4>
        <table class="assignment-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Servizio Extra', 'born-to-ride-booking'); ?></th>
                    <th><?php esc_html_e('Importo', 'born-to-ride-booking'); ?></th>
                    <th><?php esc_html_e('Pagato da', 'born-to-ride-booking'); ?></th>
                    <th><?php esc_html_e('Note', 'born-to-ride-booking'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($costi_extra_meta as $index => $extra): ?>
                <tr>
                    <td><?php echo esc_html($extra['descrizione'] ?? 'Costo Extra'); ?></td>
                    <td>€ <?php echo number_format(floatval($extra['importo'] ?? 0), 2, ',', '.'); ?></td>
                    <td>
                        <select name="extra_payer[<?php echo $index; ?>]" 
                                class="extra-payer-select"
                                data-amount="<?php echo floatval($extra['importo'] ?? 0); ?>">
                            <option value=""><?php esc_html_e('Seleziona...', 'born-to-ride-booking'); ?></option>
                            <?php foreach ($adulti_paganti as $adulto): ?>
                                <option value="<?php echo esc_attr($adulto['index']); ?>">
                                    <?php echo esc_html($adulto['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="split"><?php esc_html_e('Dividi equamente', 'born-to-ride-booking'); ?></option>
                        </select>
                    </td>
                    <td>
                        <?php 
                        if (isset($extra['moltiplica_durata']) && $extra['moltiplica_durata']) {
                            echo '<span class="badge">Per notte</span>';
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <!-- Assegnazione Camere -->
    <div class="cost-section rooms-assignment">
        <h4><?php esc_html_e('Camere', 'born-to-ride-booking'); ?></h4>
        <p class="info">
            <?php esc_html_e('Le camere sono già state assegnate durante la prenotazione. Qui puoi solo decidere chi paga per ogni camera.', 'born-to-ride-booking'); ?>
        </p>
        <?php
        $camere_selezionate = get_post_meta($preventivo_id, '_camere_selezionate', true);
        if (!empty($camere_selezionate) && is_array($camere_selezionate)):
        ?>
        <table class="assignment-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Camera', 'born-to-ride-booking'); ?></th>
                    <th><?php esc_html_e('Occupanti', 'born-to-ride-booking'); ?></th>
                    <th><?php esc_html_e('Importo', 'born-to-ride-booking'); ?></th>
                    <th><?php esc_html_e('Pagata da', 'born-to-ride-booking'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($camere_selezionate as $index => $camera): ?>
                <tr>
                    <td>
                        <?php echo esc_html($camera['tipo'] ?? 'Camera'); ?>
                        <?php if ($camera['quantita'] > 1): ?>
                            (x<?php echo $camera['quantita']; ?>)
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        // Mostra chi occupa la camera basandosi sui dati anagrafici
                        $occupanti = [];
                        foreach ($anagrafici as $persona) {
                            if (isset($persona['camera']) && $persona['camera'] == $index . '-' . $camera['tipo']) {
                                $occupanti[] = $persona['nome'] . ' ' . $persona['cognome'];
                            }
                        }
                        echo !empty($occupanti) ? implode(', ', $occupanti) : '-';
                        ?>
                    </td>
                    <td>€ <?php echo number_format(floatval($camera['totale_camera'] ?? 0), 2, ',', '.'); ?></td>
                    <td>
                        <select name="room_payer[<?php echo $index; ?>]" 
                                class="room-payer-select"
                                data-amount="<?php echo floatval($camera['totale_camera'] ?? 0); ?>">
                            <option value=""><?php esc_html_e('Seleziona...', 'born-to-ride-booking'); ?></option>
                            <?php foreach ($adulti_paganti as $adulto): ?>
                                <option value="<?php echo esc_attr($adulto['index']); ?>">
                                    <?php echo esc_html($adulto['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="occupants"><?php esc_html_e('Dividi tra occupanti', 'born-to-ride-booking'); ?></option>
                            <option value="split"><?php esc_html_e('Dividi tra tutti', 'born-to-ride-booking'); ?></option>
                        </select>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
    
    <!-- Riepilogo Assegnazioni -->
    <div class="assignment-summary">
        <h4><?php esc_html_e('Riepilogo Assegnazioni', 'born-to-ride-booking'); ?></h4>
        <div id="assignment-summary-content">
            <p class="calculating"><?php esc_html_e('Calcolo in corso...', 'born-to-ride-booking'); ?></p>
        </div>
    </div>
</div>

<style>
.group-payment-detailed-assignment {
    margin-top: 30px;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 8px;
}

.cost-section {
    margin-bottom: 30px;
}

.assignment-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.assignment-table th,
.assignment-table td {
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.assignment-table th {
    background: #f0f0f0;
    font-weight: 600;
}

.assignment-table select {
    width: 100%;
    padding: 5px;
}

.badge {
    display: inline-block;
    padding: 2px 8px;
    background: #e0e0e0;
    border-radius: 3px;
    font-size: 12px;
}

.assignment-summary {
    margin-top: 30px;
    padding: 20px;
    background: white;
    border-radius: 5px;
    border: 1px solid #ddd;
}

#assignment-summary-content {
    margin-top: 15px;
}

.participant-summary {
    margin-bottom: 15px;
    padding: 10px;
    background: #f5f5f5;
    border-radius: 3px;
}

.participant-summary strong {
    display: block;
    margin-bottom: 5px;
}

.cost-item {
    padding-left: 20px;
    font-size: 14px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Aggiorna riepilogo quando cambiano le assegnazioni
    function updateAssignmentSummary() {
        const participants = {};
        const totalAmount = <?php echo floatval(get_post_meta($preventivo_id, '_totale_preventivo', true)); ?>;
        
        // Raccogli assegnazioni assicurazioni
        $('.insurance-payer-select').each(function() {
            const payer = $(this).val();
            const amount = parseFloat($(this).data('amount'));
            
            if (payer && payer !== 'split') {
                if (!participants[payer]) {
                    participants[payer] = { items: [], total: 0 };
                }
                participants[payer].items.push({
                    type: 'insurance',
                    description: $(this).closest('tr').find('td:first').text(),
                    amount: amount
                });
                participants[payer].total += amount;
            }
        });
        
        // Raccogli assegnazioni costi extra
        $('.extra-payer-select').each(function() {
            const payer = $(this).val();
            const amount = parseFloat($(this).data('amount'));
            
            if (payer && payer !== 'split') {
                if (!participants[payer]) {
                    participants[payer] = { items: [], total: 0 };
                }
                participants[payer].items.push({
                    type: 'extra',
                    description: $(this).closest('tr').find('td:first').text(),
                    amount: amount
                });
                participants[payer].total += amount;
            }
        });
        
        // Raccogli assegnazioni camere
        $('.room-payer-select').each(function() {
            const payer = $(this).val();
            const amount = parseFloat($(this).data('amount'));
            
            if (payer && payer !== 'split' && payer !== 'occupants') {
                if (!participants[payer]) {
                    participants[payer] = { items: [], total: 0 };
                }
                participants[payer].items.push({
                    type: 'room',
                    description: $(this).closest('tr').find('td:first').text(),
                    amount: amount
                });
                participants[payer].total += amount;
            }
        });
        
        // Genera HTML riepilogo
        let summaryHtml = '';
        const adultiPaganti = <?php echo json_encode($adulti_paganti); ?>;
        
        adultiPaganti.forEach(function(adulto) {
            const index = adulto.index.toString();
            const data = participants[index] || { items: [], total: 0 };
            
            summaryHtml += '<div class="participant-summary">';
            summaryHtml += '<strong>' + adulto.nome + '</strong>';
            
            if (data.items.length > 0) {
                data.items.forEach(function(item) {
                    summaryHtml += '<div class="cost-item">';
                    summaryHtml += item.description + ': € ' + item.amount.toFixed(2).replace('.', ',');
                    summaryHtml += '</div>';
                });
                summaryHtml += '<div class="cost-item"><strong>Totale: € ' + data.total.toFixed(2).replace('.', ',') + '</strong></div>';
            } else {
                summaryHtml += '<div class="cost-item">Nessun costo assegnato</div>';
            }
            
            summaryHtml += '</div>';
        });
        
        // Calcola totale assegnato
        let totalAssigned = 0;
        Object.values(participants).forEach(p => totalAssigned += p.total);
        
        if (Math.abs(totalAssigned - totalAmount) > 0.01) {
            summaryHtml += '<div class="warning">⚠️ Attenzione: Totale assegnato (€' + totalAssigned.toFixed(2).replace('.', ',') + ') diverso dal totale preventivo (€' + totalAmount.toFixed(2).replace('.', ',') + ')</div>';
        }
        
        $('#assignment-summary-content').html(summaryHtml);
    }
    
    // Ascolta cambiamenti
    $('.insurance-payer-select, .extra-payer-select, .room-payer-select').on('change', updateAssignmentSummary);
    
    // Aggiorna al caricamento
    updateAssignmentSummary();
});
</script>