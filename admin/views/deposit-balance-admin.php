<?php
/**
 * Admin interface per gestione sistema Caparra + Saldo
 * 
 * @since 1.0.14
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verifica autorizzazioni
if (!current_user_can('edit_posts')) {
    wp_die('Non hai i permessi per accedere a questa pagina.');
}

$preventivo_id = intval($_GET['preventivo_id'] ?? 0);

if (!$preventivo_id || get_post_type($preventivo_id) !== 'preventivo') {
    // Mostra lista preventivi per selezione
    $this->render_preventivi_selection();
    return;
}

$preventivo = get_post($preventivo_id);
$anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
$prezzo_totale = (float) get_post_meta($preventivo_id, '_prezzo_totale', true);
$nome_pacchetto = get_post_meta($preventivo_id, '_nome_pacchetto', true);
$deposit_percentage = get_post_meta($preventivo_id, '_btr_deposit_percentage', true) ?: 30;
$payment_mode = get_post_meta($preventivo_id, '_btr_payment_mode', true);

// Statistiche pagamenti caparra/saldo
global $wpdb;
$table_payments = $wpdb->prefix . 'btr_group_payments';
$deposit_stats = $wpdb->get_row($wpdb->prepare("
    SELECT 
        COUNT(*) as total_deposits,
        SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_deposits,
        SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as paid_amount_deposits
    FROM {$table_payments}
    WHERE preventivo_id = %d AND payment_type = 'deposit'
", $preventivo_id));

$balance_stats = $wpdb->get_row($wpdb->prepare("
    SELECT 
        COUNT(*) as total_balances,
        SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_balances,
        SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as paid_amount_balances
    FROM {$table_payments}
    WHERE preventivo_id = %d AND payment_type = 'balance'
", $preventivo_id));

$deposit_amount_total = ($prezzo_totale * $deposit_percentage) / 100;
$balance_amount_total = $prezzo_totale - $deposit_amount_total;
?>

<div class="wrap">
    <h1>Gestione Caparra + Saldo</h1>
    
    <div class="btr-deposit-balance-header">
        <h2>Preventivo: <?= esc_html($nome_pacchetto) ?></h2>
        <p><strong>ID Preventivo:</strong> <?= $preventivo_id ?></p>
        <p><strong>Prezzo Totale:</strong> €<?= number_format($prezzo_totale, 2, ',', '.') ?></p>
        <p><strong>Numero Partecipanti:</strong> <?= count($anagrafici) ?></p>
        
        <div class="payment-mode-status">
            <?php if ($payment_mode === 'deposit_balance'): ?>
                <span class="status active">🎯 Modalità Caparra + Saldo Attiva</span>
            <?php else: ?>
                <span class="status inactive">⚪ Modalità Pagamento Standard</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Configurazione Caparra -->
    <div class="btr-deposit-config">
        <h3>Configurazione Caparra</h3>
        <div class="config-form">
            <label for="deposit-percentage">Percentuale Caparra:</label>
            <input type="number" id="deposit-percentage" value="<?= $deposit_percentage ?>" min="10" max="90" step="5">
            <span>%</span>
            
            <div class="amounts-preview">
                <div class="amount-item">
                    <strong>Caparra Totale:</strong> 
                    <span id="deposit-total">€<?= number_format($deposit_amount_total, 2, ',', '.') ?></span>
                </div>
                <div class="amount-item">
                    <strong>Saldo Totale:</strong> 
                    <span id="balance-total">€<?= number_format($balance_amount_total, 2, ',', '.') ?></span>
                </div>
                <div class="amount-item">
                    <strong>Caparra per Persona:</strong> 
                    <span id="deposit-per-person">€<?= number_format($deposit_amount_total / count($anagrafici), 2, ',', '.') ?></span>
                </div>
                <div class="amount-item">
                    <strong>Saldo per Persona:</strong> 
                    <span id="balance-per-person">€<?= number_format($balance_amount_total / count($anagrafici), 2, ',', '.') ?></span>
                </div>
            </div>
            
            <button id="update-deposit-config" class="button button-secondary" data-preventivo="<?= $preventivo_id ?>">
                Aggiorna Configurazione
            </button>
        </div>
    </div>

    <!-- Statistiche Caparr -->
    <div class="btr-deposit-stats">
        <h3>Stato Caparr (Fase 1)</h3>
        
        <?php if ($deposit_stats && $deposit_stats->total_deposits > 0): ?>
            <div class="stats-grid">
                <div class="stat-card deposit">
                    <h4>Caparr Totali</h4>
                    <div class="stat-number"><?= $deposit_stats->total_deposits ?></div>
                </div>
                <div class="stat-card success">
                    <h4>Caparr Pagate</h4>
                    <div class="stat-number"><?= $deposit_stats->paid_deposits ?></div>
                </div>
                <div class="stat-card amount">
                    <h4>Importo Incassato</h4>
                    <div class="stat-number">€<?= number_format($deposit_stats->paid_amount_deposits ?? 0, 0, ',', '.') ?></div>
                </div>
                <div class="stat-card progress">
                    <h4>Progresso</h4>
                    <div class="stat-number"><?= round(($deposit_stats->paid_deposits / $deposit_stats->total_deposits) * 100) ?>%</div>
                </div>
            </div>
        <?php else: ?>
            <div class="no-deposits">
                <p>Nessuna caparra ancora generata per questo preventivo.</p>
            </div>
        <?php endif; ?>
        
        <div class="deposit-actions">
            <?php if (!$deposit_stats || $deposit_stats->total_deposits == 0): ?>
                <button id="generate-deposits" class="button button-primary" data-preventivo="<?= $preventivo_id ?>">
                    🏦 Genera Link Caparr per Tutti
                </button>
            <?php else: ?>
                <button id="regenerate-deposits" class="button button-secondary" data-preventivo="<?= $preventivo_id ?>">
                    🔄 Rigenera Link Caparre
                </button>
                <a href="<?= admin_url('edit.php?post_type=preventivo&page=btr-group-payments&preventivo_id=' . $preventivo_id) ?>" 
                   class="button">
                    👁️ Visualizza Dettagli Caparr
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Statistiche Saldi -->
    <div class="btr-balance-stats">
        <h3>Stato Saldi (Fase 2)</h3>
        
        <?php if ($balance_stats && $balance_stats->total_balances > 0): ?>
            <div class="stats-grid">
                <div class="stat-card balance">
                    <h4>Saldi Totali</h4>
                    <div class="stat-number"><?= $balance_stats->total_balances ?></div>
                </div>
                <div class="stat-card success">
                    <h4>Saldi Pagati</h4>
                    <div class="stat-number"><?= $balance_stats->paid_balances ?></div>
                </div>
                <div class="stat-card amount">
                    <h4>Importo Incassato</h4>
                    <div class="stat-number">€<?= number_format($balance_stats->paid_amount_balances ?? 0, 0, ',', '.') ?></div>
                </div>
                <div class="stat-card progress">
                    <h4>Progresso</h4>
                    <div class="stat-number"><?= round(($balance_stats->paid_balances / $balance_stats->total_balances) * 100) ?>%</div>
                </div>
            </div>
        <?php else: ?>
            <div class="no-balances">
                <p>
                    <?php if ($deposit_stats && $deposit_stats->paid_deposits > 0): ?>
                        I link per i saldi saranno disponibili dopo che le caparr sono state pagate.
                    <?php else: ?>
                        Prima genera e fai pagare le caparr.
                    <?php endif; ?>
                </p>
            </div>
        <?php endif; ?>
        
        <div class="balance-actions">
            <?php if ($deposit_stats && $deposit_stats->paid_deposits > 0): ?>
                <button id="generate-balances" class="button button-primary" data-preventivo="<?= $preventivo_id ?>">
                    💰 Genera Link Saldi per Chi Ha Pagato
                </button>
                
                <div class="balance-deadline">
                    <label for="balance-deadline">Scadenza Saldo:</label>
                    <input type="date" id="balance-deadline" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                </div>
            <?php else: ?>
                <p class="disabled-action">
                    ⚠️ I link per i saldi saranno disponibili dopo il pagamento delle caparr.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Elenco Partecipanti con Status -->
    <div class="btr-participants-status">
        <h3>Status Partecipanti</h3>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Partecipante</th>
                    <th>Email</th>
                    <th>Caparra</th>
                    <th>Saldo</th>
                    <th>Totale</th>
                    <th>Status Complessivo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($anagrafici as $index => $partecipante): 
                    $nome_completo = trim(($partecipante['nome'] ?? '') . ' ' . ($partecipante['cognome'] ?? ''));
                    $email = $partecipante['email'] ?? '';
                    
                    // Verifica status pagamenti per questo partecipante
                    $deposit_status = $wpdb->get_var($wpdb->prepare(
                        "SELECT payment_status FROM {$table_payments} 
                         WHERE preventivo_id = %d AND participant_index = %d AND payment_type = 'deposit'
                         ORDER BY created_at DESC LIMIT 1",
                        $preventivo_id, $index
                    ));
                    
                    $balance_status = $wpdb->get_var($wpdb->prepare(
                        "SELECT payment_status FROM {$table_payments} 
                         WHERE preventivo_id = %d AND participant_index = %d AND payment_type = 'balance'
                         ORDER BY created_at DESC LIMIT 1",
                        $preventivo_id, $index
                    ));
                    
                    $deposit_amount = $deposit_amount_total / count($anagrafici);
                    $balance_amount = $balance_amount_total / count($anagrafici);
                    $total_amount = $deposit_amount + $balance_amount;
                ?>
                <tr>
                    <td><strong><?= esc_html($nome_completo) ?></strong></td>
                    <td><?= esc_html($email) ?></td>
                    <td>
                        <span class="payment-amount">€<?= number_format($deposit_amount, 2, ',', '.') ?></span>
                        <span class="payment-status <?= $deposit_status ?>">
                            <?php
                            switch ($deposit_status) {
                                case 'paid': echo '✅ Pagata'; break;
                                case 'pending': echo '⏳ In attesa'; break;
                                default: echo '⚪ Non generata';
                            }
                            ?>
                        </span>
                    </td>
                    <td>
                        <span class="payment-amount">€<?= number_format($balance_amount, 2, ',', '.') ?></span>
                        <span class="payment-status <?= $balance_status ?>">
                            <?php
                            switch ($balance_status) {
                                case 'paid': echo '✅ Pagato'; break;
                                case 'pending': echo '⏳ In attesa'; break;
                                default: echo $deposit_status === 'paid' ? '📋 Disponibile' : '⚪ Non disponibile';
                            }
                            ?>
                        </span>
                    </td>
                    <td><strong>€<?= number_format($total_amount, 2, ',', '.') ?></strong></td>
                    <td>
                        <?php if ($deposit_status === 'paid' && $balance_status === 'paid'): ?>
                            <span class="overall-status complete">🎉 Completo</span>
                        <?php elseif ($deposit_status === 'paid'): ?>
                            <span class="overall-status partial">⚡ Parziale</span>
                        <?php elseif ($deposit_status === 'pending'): ?>
                            <span class="overall-status pending">⏳ Caparra in attesa</span>
                        <?php else: ?>
                            <span class="overall-status none">⚪ Non iniziato</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.btr-deposit-balance-header {
    background: #f1f1f1;
    padding: 20px;
    margin-bottom: 20px;
    border-left: 4px solid #0073aa;
    border-radius: 4px;
}

.payment-mode-status .status {
    padding: 5px 10px;
    border-radius: 15px;
    font-weight: bold;
    font-size: 0.9em;
}

.payment-mode-status .status.active {
    background: #d4edda;
    color: #155724;
}

.payment-mode-status .status.inactive {
    background: #f8f9fa;
    color: #6c757d;
}

.btr-deposit-config, .btr-deposit-stats, .btr-balance-stats, .btr-participants-status {
    background: white;
    margin: 20px 0;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.config-form {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 20px;
}

.amounts-preview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin: 20px 0;
    padding: 15px;
    background: #f9f9f9;
    border-radius: 4px;
}

.amount-item {
    text-align: center;
    padding: 10px;
    background: white;
    border-radius: 4px;
    border-left: 3px solid #0073aa;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.stat-card {
    text-align: center;
    padding: 15px;
    background: white;
    border-radius: 4px;
    border-left: 4px solid #0073aa;
}

.stat-card.deposit { border-left-color: #17a2b8; }
.stat-card.balance { border-left-color: #28a745; }
.stat-card.success { border-left-color: #28a745; }
.stat-card.amount { border-left-color: #ffc107; }
.stat-card.progress { border-left-color: #6f42c1; }

.stat-card h4 {
    margin: 0 0 8px;
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
}

.stat-number {
    font-size: 1.8em;
    font-weight: bold;
    color: #0073aa;
}

.deposit-actions, .balance-actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
    align-items: center;
}

.balance-deadline {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 10px;
}

.payment-status {
    display: block;
    font-size: 0.8em;
    margin-top: 2px;
}

.payment-status.paid { color: #28a745; }
.payment-status.pending { color: #ffc107; }

.overall-status {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: bold;
}

.overall-status.complete { background: #d4edda; color: #155724; }
.overall-status.partial { background: #fff3cd; color: #856404; }
.overall-status.pending { background: #cce5ff; color: #004085; }
.overall-status.none { background: #f8f9fa; color: #6c757d; }

.disabled-action {
    color: #6c757d;
    font-style: italic;
}

.no-deposits, .no-balances {
    text-align: center;
    padding: 20px;
    color: #666;
    background: #f8f9fa;
    border-radius: 4px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Aggiorna preview importi quando cambia percentuale
    $('#deposit-percentage').on('input', function() {
        const percentage = parseFloat($(this).val());
        const totalAmount = <?= $prezzo_totale ?>;
        const participants = <?= count($anagrafici) ?>;
        
        const depositTotal = (totalAmount * percentage) / 100;
        const balanceTotal = totalAmount - depositTotal;
        const depositPerPerson = depositTotal / participants;
        const balancePerPerson = balanceTotal / participants;
        
        $('#deposit-total').text('€' + depositTotal.toLocaleString('it-IT', {minimumFractionDigits: 2}));
        $('#balance-total').text('€' + balanceTotal.toLocaleString('it-IT', {minimumFractionDigits: 2}));
        $('#deposit-per-person').text('€' + depositPerPerson.toLocaleString('it-IT', {minimumFractionDigits: 2}));
        $('#balance-per-person').text('€' + balancePerPerson.toLocaleString('it-IT', {minimumFractionDigits: 2}));
    });
    
    // Aggiorna configurazione caparra
    $('#update-deposit-config').on('click', function() {
        const button = $(this);
        const preventivo = button.data('preventivo');
        const percentage = $('#deposit-percentage').val();
        
        button.prop('disabled', true).text('Aggiornando...');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'btr_update_deposit_settings',
                preventivo_id: preventivo,
                deposit_percentage: percentage,
                nonce: '<?= wp_create_nonce("btr_group_payments") ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ Configurazione aggiornata!');
                    location.reload();
                } else {
                    alert('❌ Errore: ' + response.data);
                }
            },
            error: function() {
                alert('❌ Errore di comunicazione.');
            },
            complete: function() {
                button.prop('disabled', false).text('Aggiorna Configurazione');
            }
        });
    });
    
    // Genera link caparr
    $('#generate-deposits, #regenerate-deposits').on('click', function() {
        const button = $(this);
        const preventivo = button.data('preventivo');
        const percentage = $('#deposit-percentage').val();
        
        if (!confirm('Generare link di pagamento caparra per tutti i partecipanti?')) {
            return;
        }
        
        button.prop('disabled', true).text('Generando...');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'btr_generate_deposit_links',
                preventivo_id: preventivo,
                deposit_percentage: percentage,
                nonce: '<?= wp_create_nonce("btr_group_payments") ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ ' + response.data.message);
                    location.reload();
                } else {
                    alert('❌ Errore: ' + response.data);
                }
            },
            error: function() {
                alert('❌ Errore di comunicazione.');
            },
            complete: function() {
                button.prop('disabled', false).text(button.attr('id') === 'generate-deposits' ? '🏦 Genera Link Caparr per Tutti' : '🔄 Rigenera Link Caparr');
            }
        });
    });
    
    // Genera link saldi
    $('#generate-balances').on('click', function() {
        const button = $(this);
        const preventivo = button.data('preventivo');
        const deadline = $('#balance-deadline').val();
        
        if (!confirm('Generare link di pagamento saldo per chi ha pagato la caparra?')) {
            return;
        }
        
        button.prop('disabled', true).text('Generando...');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'btr_generate_balance_links',
                preventivo_id: preventivo,
                balance_deadline: deadline,
                nonce: '<?= wp_create_nonce("btr_group_payments") ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('✅ ' + response.data.message);
                    location.reload();
                } else {
                    alert('❌ Errore: ' + response.data);
                }
            },
            error: function() {
                alert('❌ Errore di comunicazione.');
            },
            complete: function() {
                button.prop('disabled', false).text('💰 Genera Link Saldi per Chi Ha Pagato');
            }
        });
    });
});
</script>

<?php 
// Funzione per mostrare lista preventivi se non specificato ID
function render_preventivi_selection() {
    $preventivi = get_posts([
        'post_type' => 'preventivo',
        'post_status' => 'publish',
        'numberposts' => -1,
        'meta_query' => [
            [
                'key' => '_anagrafici_preventivo',
                'compare' => 'EXISTS'
            ]
        ]
    ]);
    ?>
    <div class="wrap">
        <h1>Seleziona Preventivo per Gestione Caparra + Saldo</h1>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Preventivo</th>
                    <th>Partecipanti</th>
                    <th>Totale</th>
                    <th>Modalità</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($preventivi as $preventivo): 
                    $anagrafici = get_post_meta($preventivo->ID, '_anagrafici_preventivo', true);
                    $prezzo_totale = get_post_meta($preventivo->ID, '_prezzo_totale', true);
                    $payment_mode = get_post_meta($preventivo->ID, '_btr_payment_mode', true);
                    $nome_pacchetto = get_post_meta($preventivo->ID, '_nome_pacchetto', true);
                ?>
                <tr>
                    <td>
                        <strong><?= esc_html($nome_pacchetto ?: $preventivo->post_title) ?></strong><br>
                        <small>ID: <?= $preventivo->ID ?></small>
                    </td>
                    <td><?= is_array($anagrafici) ? count($anagrafici) : 0 ?> persone</td>
                    <td>€<?= number_format((float)$prezzo_totale, 2, ',', '.') ?></td>
                    <td>
                        <?php if ($payment_mode === 'deposit_balance'): ?>
                            <span class="status active">🎯 Caparra + Saldo</span>
                        <?php else: ?>
                            <span class="status inactive">⚪ Standard</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= admin_url('edit.php?post_type=preventivo&page=btr-deposit-balance&preventivo_id=' . $preventivo->ID) ?>" 
                           class="button button-primary">
                            Gestisci
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}
?>