<?php
/**
 * Template per la gestione dei pagamenti di gruppo
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
    wp_die('Preventivo non valido.');
}

$preventivo = get_post($preventivo_id);
$anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
$prezzo_totale = (float) get_post_meta($preventivo_id, '_prezzo_totale', true);
$nome_pacchetto = get_post_meta($preventivo_id, '_nome_pacchetto', true);

// Ottieni statistiche pagamenti esistenti
$group_payments = new BTR_Group_Payments();
$payment_stats = $group_payments->get_payment_stats($preventivo_id);
?>

<div class="wrap">
    <h1>Gestione Pagamenti di Gruppo</h1>
    
    <div class="btr-group-payments-header">
        <h2>Preventivo: <?= esc_html($nome_pacchetto) ?></h2>
        <p><strong>ID Preventivo:</strong> <?= $preventivo_id ?></p>
        <p><strong>Prezzo Totale:</strong> €<?= number_format($prezzo_totale, 2, ',', '.') ?></p>
        <p><strong>Numero Partecipanti:</strong> <?= count($anagrafici) ?></p>
        
        <?php if ($payment_stats): ?>
        <div class="payment-stats">
            <h3>Statistiche Pagamenti</h3>
            <ul>
                <li><strong>Pagamenti Totali:</strong> <?= $payment_stats['total_payments'] ?></li>
                <li><strong>Pagamenti Completati:</strong> <?= $payment_stats['paid_count'] ?></li>
                <li><strong>Pagamenti Pendenti:</strong> <?= $payment_stats['pending_count'] ?></li>
                <li><strong>Importo Pagato:</strong> €<?= number_format($payment_stats['paid_amount'], 2, ',', '.') ?></li>
                <li><strong>Importo Totale:</strong> €<?= number_format($payment_stats['total_amount'], 2, ',', '.') ?></li>
            </ul>
        </div>
        <?php endif; ?>
    </div>

    <div class="btr-participants-table">
        <h3>Partecipanti</h3>
        
        <?php if (empty($anagrafici)): ?>
            <p>Nessun partecipante trovato per questo preventivo.</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Email</th>
                        <th>Quota Individuale</th>
                        <th>Status Pagamento</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $quota_individuale = $prezzo_totale / count($anagrafici);
                    foreach ($anagrafici as $index => $partecipante): 
                        $nome_completo = trim(($partecipante['nome'] ?? '') . ' ' . ($partecipante['cognome'] ?? ''));
                        $email = $partecipante['email'] ?? '';
                        
                        // Verifica se esiste già un pagamento per questo partecipante
                        global $wpdb;
                        $table_payments = $wpdb->prefix . 'btr_group_payments';
                        $existing_payment = $wpdb->get_row($wpdb->prepare(
                            "SELECT * FROM {$table_payments} WHERE preventivo_id = %d AND participant_index = %d ORDER BY created_at DESC LIMIT 1",
                            $preventivo_id, $index
                        ), ARRAY_A);
                        
                        $payment_status = $existing_payment ? $existing_payment['payment_status'] : 'none';
                        $payment_url = '';
                        
                        if ($existing_payment && $payment_status === 'pending') {
                            $table_links = $wpdb->prefix . 'btr_payment_links';
                            $link_data = $wpdb->get_row($wpdb->prepare(
                                "SELECT link_hash FROM {$table_links} WHERE payment_id = %d AND is_active = 1 AND expires_at > NOW()",
                                $existing_payment['payment_id']
                            ));
                            
                            if ($link_data) {
                                $payment_url = home_url('/pay-individual/' . $link_data->link_hash);
                            }
                        }
                    ?>
                    <tr>
                        <td><?= esc_html($nome_completo) ?></td>
                        <td><?= esc_html($email) ?></td>
                        <td>€<?= number_format($quota_individuale, 2, ',', '.') ?></td>
                        <td>
                            <?php
                            switch ($payment_status) {
                                case 'paid':
                                    echo '<span class="payment-status paid">✓ Pagato</span>';
                                    break;
                                case 'pending':
                                    echo '<span class="payment-status pending">⏳ In attesa</span>';
                                    break;
                                case 'failed':
                                    echo '<span class="payment-status failed">✗ Fallito</span>';
                                    break;
                                case 'expired':
                                    echo '<span class="payment-status expired">⏰ Scaduto</span>';
                                    break;
                                default:
                                    echo '<span class="payment-status none">— Non generato</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($payment_status === 'none' || $payment_status === 'expired'): ?>
                                <button type="button" class="button generate-payment-link" 
                                        data-preventivo="<?= $preventivo_id ?>" 
                                        data-participant="<?= $index ?>"
                                        data-email="<?= esc_attr($email) ?>">
                                    Genera Link Pagamento
                                </button>
                            <?php elseif ($payment_status === 'pending' && $payment_url): ?>
                                <a href="<?= esc_url($payment_url) ?>" target="_blank" class="button">Visualizza Link</a>
                                <button type="button" class="button send-payment-email" 
                                        data-payment-id="<?= $existing_payment['payment_id'] ?>">
                                    Invia Email
                                </button>
                            <?php elseif ($payment_status === 'paid'): ?>
                                <span class="description">Pagamento completato</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="btr-bulk-actions">
        <h3>Azioni di Gruppo</h3>
        <p>
            <button type="button" id="generate-all-links" class="button button-primary" 
                    data-preventivo="<?= $preventivo_id ?>">
                Genera Link per Tutti i Partecipanti
            </button>
            
            <button type="button" id="send-all-emails" class="button" 
                    data-preventivo="<?= $preventivo_id ?>">
                Invia Email a Tutti
            </button>
        </p>
    </div>
</div>

<style>
.btr-group-payments-header {
    background: #f1f1f1;
    padding: 20px;
    margin-bottom: 20px;
    border-left: 4px solid #0073aa;
}

.payment-stats {
    margin-top: 15px;
}

.payment-stats ul {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 10px;
    list-style: none;
    padding: 0;
}

.payment-status {
    padding: 4px 8px;
    border-radius: 3px;
    font-weight: bold;
}

.payment-status.paid {
    background: #d4edda;
    color: #155724;
}

.payment-status.pending {
    background: #fff3cd;
    color: #856404;
}

.payment-status.failed {
    background: #f8d7da;
    color: #721c24;
}

.payment-status.expired {
    background: #e2e3e5;
    color: #383d41;
}

.payment-status.none {
    background: #f8f9fa;
    color: #6c757d;
}

.btr-bulk-actions {
    margin-top: 30px;
    padding: 20px;
    background: #fff;
    border: 1px solid #ccd0d4;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Genera link singolo
    $('.generate-payment-link').on('click', function() {
        const button = $(this);
        const preventivo = button.data('preventivo');
        const participant = button.data('participant');
        const email = button.data('email');
        
        if (!email) {
            alert('Email del partecipante non trovata.');
            return;
        }
        
        button.prop('disabled', true).text('Generando...');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'btr_generate_individual_payment_link',
                preventivo_id: preventivo,
                participant_index: participant,
                nonce: '<?= wp_create_nonce("btr_group_payments") ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('Link di pagamento generato con successo!');
                    location.reload();
                } else {
                    alert('Errore: ' + response.data);
                }
            },
            error: function() {
                alert('Errore nella comunicazione con il server.');
            },
            complete: function() {
                button.prop('disabled', false).text('Genera Link Pagamento');
            }
        });
    });
    
    // Genera tutti i link
    $('#generate-all-links').on('click', function() {
        const button = $(this);
        const preventivo = button.data('preventivo');
        
        if (!confirm('Generare link di pagamento per tutti i partecipanti?')) {
            return;
        }
        
        button.prop('disabled', true).text('Generando...');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'btr_generate_group_payment_links',
                preventivo_id: preventivo,
                payment_type: 'full',
                nonce: '<?= wp_create_nonce("btr_group_payments") ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('Link di pagamento generati con successo per ' + response.data.links.length + ' partecipanti!');
                    location.reload();
                } else {
                    alert('Errore: ' + response.data);
                }
            },
            error: function() {
                alert('Errore nella comunicazione con il server.');
            },
            complete: function() {
                button.prop('disabled', false).text('Genera Link per Tutti i Partecipanti');
            }
        });
    });
    
    // Invia email singola
    $('.send-payment-email').on('click', function() {
        const button = $(this);
        const paymentId = button.data('payment-id');
        
        button.prop('disabled', true).text('Inviando...');
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'btr_send_payment_email',
                payment_id: paymentId,
                nonce: '<?= wp_create_nonce("btr_group_payments") ?>'
            },
            success: function(response) {
                if (response.success) {
                    alert('Email inviata con successo!');
                } else {
                    alert('Errore: ' + response.data);
                }
            },
            error: function() {
                alert('Errore nella comunicazione con il server.');
            },
            complete: function() {
                button.prop('disabled', false).text('Invia Email');
            }
        });
    });
});
</script>