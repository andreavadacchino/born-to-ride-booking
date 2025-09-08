<?php
/**
 * Metabox per gestione pagamenti nella pagina di modifica preventivo
 * 
 * @since 1.0.14
 */

if (!defined('ABSPATH')) {
    exit;
}

function btr_add_payments_metabox() {
    add_meta_box(
        'btr-group-payments-metabox',
        'Gestione Pagamenti di Gruppo',
        'btr_render_payments_metabox',
        'preventivo',
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'btr_add_payments_metabox');

function btr_render_payments_metabox($post) {
    $preventivo_id = $post->ID;
    $anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
    $prezzo_totale = (float) get_post_meta($preventivo_id, '_prezzo_totale', true);
    
    if (empty($anagrafici) || count($anagrafici) <= 1) {
        echo '<div class="notice notice-info inline">
                <p><strong>Pagamenti individuali non disponibili</strong><br>
                Questa funzionalità è disponibile solo per preventivi con più di un partecipante.</p>
              </div>';
        return;
    }
    
    $group_payments = new BTR_Group_Payments();
    $payment_stats = $group_payments->get_payment_stats($preventivo_id);
    $quota_individuale = $prezzo_totale / count($anagrafici);
    ?>
    
    <div class="btr-payments-metabox">
        <div class="payment-stats-grid">
            <div class="stat-item">
                <div class="stat-number"><?= count($anagrafici) ?></div>
                <div class="stat-label">Partecipanti</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">€<?= number_format($quota_individuale, 0, ',', '.') ?></div>
                <div class="stat-label">Quota individuale</div>
            </div>
            
            <?php if ($payment_stats): ?>
            <div class="stat-item success">
                <div class="stat-number"><?= $payment_stats['paid_count'] ?>/<?= $payment_stats['total_payments'] ?></div>
                <div class="stat-label">Completati</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">€<?= number_format($payment_stats['paid_amount'], 0, ',', '.') ?></div>
                <div class="stat-label">Incassato</div>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($payment_stats && $payment_stats['total_payments'] > 0): ?>
        <div class="payment-progress">
            <?php 
            $completion_rate = round(($payment_stats['paid_count'] / $payment_stats['total_payments']) * 100);
            $remaining_amount = $payment_stats['total_amount'] - $payment_stats['paid_amount'];
            ?>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?= $completion_rate ?>%"></div>
            </div>
            <div class="progress-info">
                <span><strong><?= $completion_rate ?>%</strong> completato</span>
                <?php if ($remaining_amount > 0): ?>
                <span class="remaining">Mancano €<?= number_format($remaining_amount, 0, ',', '.') ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="payment-actions">
            <a href="<?= admin_url('edit.php?post_type=preventivo&page=btr-group-payments&preventivo_id=' . $preventivo_id) ?>" 
               class="button button-primary button-hero">
                💳 Gestisci Pagamenti
            </a>
            
            <?php if (!$payment_stats || $payment_stats['total_payments'] == 0): ?>
            <button type="button" id="quick-generate-all-links" class="button button-secondary" 
                    data-preventivo="<?= $preventivo_id ?>" style="margin-top: 8px;">
                📧 Genera e Invia a Tutti
            </button>
            <?php endif; ?>
        </div>

        <?php if ($payment_stats && $payment_stats['pending_count'] > 0): ?>
        <div class="payment-alerts">
            <div class="alert warning">
                <strong><?= $payment_stats['pending_count'] ?></strong> pagamenti in attesa
            </div>
        </div>
        <?php endif; ?>
    </div>

    <style>
    .btr-payments-metabox {
        font-size: 13px;
    }
    
    .payment-stats-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        margin-bottom: 15px;
    }
    
    .stat-item {
        text-align: center;
        padding: 10px 8px;
        background: #f9f9f9;
        border-radius: 4px;
        border-left: 3px solid #0073aa;
    }
    
    .stat-item.success {
        border-left-color: #00a32a;
    }
    
    .stat-number {
        font-size: 16px;
        font-weight: bold;
        color: #0073aa;
        line-height: 1.2;
    }
    
    .stat-item.success .stat-number {
        color: #00a32a;
    }
    
    .stat-label {
        font-size: 11px;
        color: #666;
        margin-top: 2px;
    }
    
    .payment-progress {
        margin-bottom: 15px;
    }
    
    .progress-bar {
        height: 6px;
        background: #f0f0f1;
        border-radius: 3px;
        overflow: hidden;
        margin-bottom: 6px;
    }
    
    .progress-fill {
        height: 100%;
        background: #00a32a;
        transition: width 0.3s ease;
    }
    
    .progress-info {
        display: flex;
        justify-content: space-between;
        font-size: 11px;
        color: #666;
    }
    
    .progress-info .remaining {
        color: #d63638;
        font-weight: 500;
    }
    
    .payment-actions {
        margin-bottom: 15px;
    }
    
    .payment-actions .button {
        width: 100%;
        text-align: center;
        justify-content: center;
    }
    
    .payment-alerts .alert {
        padding: 8px;
        border-radius: 4px;
        font-size: 12px;
        text-align: center;
    }
    
    .payment-alerts .alert.warning {
        background: #fff3cd;
        border: 1px solid #ffeaa7;
        color: #856404;
    }
    </style>

    <script>
    jQuery(document).ready(function($) {
        $('#quick-generate-all-links').on('click', function() {
            const button = $(this);
            const preventivo = button.data('preventivo');
            
            if (!confirm('Generare link di pagamento e inviare email a tutti i partecipanti?')) {
                return;
            }
            
            button.prop('disabled', true).text('⏳ Generando...');
            
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
                        alert('✅ Link generati e email inviate con successo!');
                        location.reload();
                    } else {
                        alert('❌ Errore: ' + response.data);
                    }
                },
                error: function() {
                    alert('❌ Errore nella comunicazione con il server.');
                },
                complete: function() {
                    button.prop('disabled', false).text('📧 Genera e Invia a Tutti');
                }
            });
        });
    });
    </script>
    
    <?php
}
?>