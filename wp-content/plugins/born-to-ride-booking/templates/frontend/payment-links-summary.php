<?php
/**
 * Template per mostrare i link di pagamento generati per gruppo
 * 
 * @package BornToRideBooking
 * @since 1.0.98
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ottieni i dati dalla sessione o dai parametri
$preventivo_id = isset($_GET['preventivo_id']) ? intval($_GET['preventivo_id']) : 0;
$payment_links = [];

// Prima prova a ottenere i link dalla sessione
if (WC()->session) {
    $payment_links = WC()->session->get('btr_generated_payment_links', []);
    $session_preventivo_id = WC()->session->get('btr_payment_preventivo_id', 0);
    
    // Verifica che il preventivo corrisponda
    if ($session_preventivo_id != $preventivo_id) {
        $payment_links = [];
    }
}

// Se non ci sono link in sessione, prova a recuperarli dal database
if (empty($payment_links) && $preventivo_id && class_exists('BTR_Group_Payments')) {
    $group_payments = new BTR_Group_Payments();
    $stats = $group_payments->get_payment_stats($preventivo_id);
    
    // Se ci sono pagamenti esistenti, recuperali
    if ($stats && $stats['total_payments'] > 0) {
        global $wpdb;
        $table_payments = $wpdb->prefix . 'btr_group_payments';
        $table_links = $wpdb->prefix . 'btr_payment_links';
        
        $payment_links = $wpdb->get_results($wpdb->prepare("
            SELECT 
                p.payment_id,
                p.participant_name,
                p.participant_email,
                p.amount,
                p.payment_status,
                p.expires_at,
                CONCAT(%s, l.link_hash) as payment_url
            FROM {$table_payments} p
            LEFT JOIN {$table_links} l ON p.payment_id = l.payment_id
            WHERE p.preventivo_id = %d 
            AND l.is_active = 1
            ORDER BY p.participant_name
        ", home_url('/pay-individual/'), $preventivo_id), ARRAY_A);
    }
}

// Ottieni informazioni del preventivo
$preventivo = get_post($preventivo_id);
$nome_pacchetto = get_post_meta($preventivo_id, '_nome_pacchetto', true);
$prezzo_totale = get_post_meta($preventivo_id, '_prezzo_totale', true);
$date_range = get_post_meta($preventivo_id, '_date_ranges', true);

?>

<div class="btr-payment-links-summary">
    <div class="btr-container">
        
        <?php if (!$preventivo || empty($payment_links)) : ?>
            <div class="btr-error-message">
                <h2><?php _e('Errore', 'born-to-ride-booking'); ?></h2>
                <p><?php _e('Nessun link di pagamento trovato per questo preventivo.', 'born-to-ride-booking'); ?></p>
            </div>
        <?php else : ?>
            
            <div class="btr-header">
                <h1><?php _e('Link di Pagamento Generati', 'born-to-ride-booking'); ?></h1>
                <div class="btr-package-info">
                    <h2><?php echo esc_html($nome_pacchetto); ?></h2>
                    <?php if ($date_range) : ?>
                        <p class="dates"><?php echo esc_html($date_range); ?></p>
                    <?php endif; ?>
                    <p class="total-amount">
                        <?php _e('Totale preventivo:', 'born-to-ride-booking'); ?> 
                        <strong>€<?php echo number_format($prezzo_totale, 2, ',', '.'); ?></strong>
                    </p>
                </div>
            </div>

            <div class="btr-success-message">
                <p>✅ <?php _e('I link di pagamento sono stati generati con successo!', 'born-to-ride-booking'); ?></p>
                <p><?php _e('Ogni partecipante riceverà il proprio link personale via email.', 'born-to-ride-booking'); ?></p>
            </div>

            <div class="btr-payment-links-table">
                <h3><?php _e('Riepilogo Link di Pagamento', 'born-to-ride-booking'); ?></h3>
                
                <table class="btr-links-table">
                    <thead>
                        <tr>
                            <th><?php _e('Partecipante', 'born-to-ride-booking'); ?></th>
                            <th><?php _e('Email', 'born-to-ride-booking'); ?></th>
                            <th><?php _e('Importo', 'born-to-ride-booking'); ?></th>
                            <th><?php _e('Stato', 'born-to-ride-booking'); ?></th>
                            <th><?php _e('Azioni', 'born-to-ride-booking'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payment_links as $link) : 
                            $is_paid = isset($link['payment_status']) && $link['payment_status'] === 'paid';
                        ?>
                            <tr class="<?php echo $is_paid ? 'paid' : 'pending'; ?>">
                                <td class="participant-name">
                                    <?php echo esc_html($link['participant_name']); ?>
                                </td>
                                <td class="participant-email">
                                    <?php echo esc_html($link['participant_email']); ?>
                                </td>
                                <td class="amount">
                                    €<?php echo number_format($link['amount'], 2, ',', '.'); ?>
                                </td>
                                <td class="status">
                                    <?php if ($is_paid) : ?>
                                        <span class="status-paid">✅ <?php _e('Pagato', 'born-to-ride-booking'); ?></span>
                                    <?php else : ?>
                                        <span class="status-pending">⏳ <?php _e('In attesa', 'born-to-ride-booking'); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions">
                                    <div class="action-buttons">
                                        <?php if (!$is_paid) : ?>
                                            <button class="btn-copy-link" 
                                                    data-link="<?php echo esc_attr($link['payment_url']); ?>"
                                                    title="<?php _e('Copia link', 'born-to-ride-booking'); ?>">
                                                📋 <?php _e('Copia', 'born-to-ride-booking'); ?>
                                            </button>
                                            <button class="btn-send-email" 
                                                    data-payment-id="<?php echo esc_attr($link['payment_id']); ?>"
                                                    title="<?php _e('Invia email', 'born-to-ride-booking'); ?>">
                                                ✉️ <?php _e('Invia Email', 'born-to-ride-booking'); ?>
                                            </button>
                                            <button class="btn-show-qr" 
                                                    data-link="<?php echo esc_attr($link['payment_url']); ?>"
                                                    data-name="<?php echo esc_attr($link['participant_name']); ?>"
                                                    title="<?php _e('Mostra QR Code', 'born-to-ride-booking'); ?>">
                                                📱 <?php _e('QR', 'born-to-ride-booking'); ?>
                                            </button>
                                        <?php else : ?>
                                            <span class="payment-completed"><?php _e('Completato', 'born-to-ride-booking'); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2"><strong><?php _e('Totale', 'born-to-ride-booking'); ?></strong></td>
                            <td><strong>€<?php echo number_format($prezzo_totale, 2, ',', '.'); ?></strong></td>
                            <td colspan="2">
                                <?php 
                                $paid_count = 0;
                                foreach ($payment_links as $link) {
                                    if (isset($link['payment_status']) && $link['payment_status'] === 'paid') {
                                        $paid_count++;
                                    }
                                }
                                echo sprintf(__('%d di %d pagati', 'born-to-ride-booking'), $paid_count, count($payment_links));
                                ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="btr-actions-bottom">
                <button class="btn-send-all-emails" id="send-all-emails">
                    ✉️ <?php _e('Invia Email a Tutti i Partecipanti', 'born-to-ride-booking'); ?>
                </button>
                <button class="btn-print" onclick="window.print()">
                    🖨️ <?php _e('Stampa Riepilogo', 'born-to-ride-booking'); ?>
                </button>
                
                <!-- NUOVO: Pulsante per procedere come organizzatore -->
                <button class="btn-organizer-proceed" id="organizer-proceed" data-preventivo-id="<?php echo esc_attr($preventivo_id); ?>">
                    🛒 <?php _e('Procedi come Organizzatore', 'born-to-ride-booking'); ?>
                </button>
                
                <?php if (current_user_can('edit_posts')) : ?>
                    <a href="<?php echo admin_url('edit.php?post_type=btr_preventivi&page=btr-group-payments&preventivo_id=' . $preventivo_id); ?>" 
                       class="btn-admin-view">
                        ⚙️ <?php _e('Gestione Admin', 'born-to-ride-booking'); ?>
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Info aggiuntiva per organizzatore -->
            <div class="btr-organizer-info">
                <div class="btr-alert btr-alert-info">
                    <svg class="btr-alert-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div class="btr-alert-content">
                        <strong><?php _e('Nota per l\'Organizzatore:', 'born-to-ride-booking'); ?></strong>
                        <p><?php _e('Dopo aver inviato i link ai partecipanti, puoi procedere con la creazione dell\'ordine cliccando su "Procedi come Organizzatore". L\'ordine verrà creato e rimarrà in attesa fino a quando tutti i partecipanti non avranno completato il loro pagamento.', 'born-to-ride-booking'); ?></p>
                    </div>
                </div>
            </div>

        <?php endif; ?>
        
    </div>
</div>

<!-- Modal per QR Code -->
<div id="qr-modal" class="btr-modal" style="display:none;">
    <div class="btr-modal-content">
        <span class="close">&times;</span>
        <h3><?php _e('QR Code Pagamento', 'born-to-ride-booking'); ?></h3>
        <div id="qr-container"></div>
        <p class="qr-participant-name"></p>
    </div>
</div>

<style>
.btr-payment-links-summary {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 2rem;
}

.btr-header {
    text-align: center;
    margin-bottom: 2rem;
}

.btr-header h1 {
    color: #0097c5;
    margin-bottom: 1rem;
}

.btr-package-info {
    background: #f9f9f9;
    padding: 1.5rem;
    border-radius: 8px;
    margin-bottom: 2rem;
}

.btr-package-info h2 {
    margin: 0 0 0.5rem;
    color: #333;
}

.btr-package-info .dates {
    color: #666;
    margin: 0.5rem 0;
}

.btr-package-info .total-amount {
    font-size: 1.2rem;
    margin: 1rem 0 0;
}

.btr-success-message {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
    padding: 1rem;
    border-radius: 4px;
    margin-bottom: 2rem;
    text-align: center;
}

.btr-success-message p {
    margin: 0.5rem 0;
}

.btr-error-message {
    background: #f8d7da;
    border: 1px solid #f5c6cb;
    color: #721c24;
    padding: 2rem;
    border-radius: 4px;
    text-align: center;
}

.btr-payment-links-table {
    background: white;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.btr-payment-links-table h3 {
    margin: 0 0 1.5rem;
    color: #0097c5;
}

.btr-links-table {
    width: 100%;
    border-collapse: collapse;
}

.btr-links-table th,
.btr-links-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.btr-links-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #333;
}

.btr-links-table tbody tr:hover {
    background: #f8f9fa;
}

.btr-links-table tbody tr.paid {
    opacity: 0.7;
}

.status-paid {
    color: #28a745;
    font-weight: 500;
}

.status-pending {
    color: #ffc107;
    font-weight: 500;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.action-buttons button {
    padding: 0.5rem 1rem;
    border: 1px solid #ddd;
    background: white;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.9rem;
    transition: all 0.3s;
}

.action-buttons button:hover {
    background: #0097c5;
    color: white;
    border-color: #0097c5;
}

.btn-copy-link:hover {
    background: #28a745 !important;
    border-color: #28a745 !important;
}

.btn-send-email:hover {
    background: #17a2b8 !important;
    border-color: #17a2b8 !important;
}

.btn-show-qr:hover {
    background: #6c757d !important;
    border-color: #6c757d !important;
}

.payment-completed {
    color: #28a745;
    font-weight: 500;
}

.btr-actions-bottom {
    margin-top: 2rem;
    text-align: center;
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.btr-actions-bottom button,
.btr-actions-bottom a {
    padding: 1rem 2rem;
    border: none;
    border-radius: 4px;
    font-size: 1rem;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: background 0.3s;
}

.btn-send-all-emails {
    background: #17a2b8;
    color: white;
}

.btn-send-all-emails:hover {
    background: #138496;
}

.btn-print {
    background: #6c757d;
    color: white;
}

.btn-print:hover {
    background: #5a6268;
}

.btn-admin-view {
    background: #343a40;
    color: white;
}

.btn-admin-view:hover {
    background: #23272b;
}

.btn-organizer-proceed {
    background: #28a745;
    color: white;
    font-weight: 600;
}

.btn-organizer-proceed:hover {
    background: #218838;
}

.btr-organizer-info {
    margin-top: 2rem;
    max-width: 800px;
    margin-left: auto;
    margin-right: auto;
}

.btr-alert {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    background: #e3f2fd;
    border: 1px solid #0097c5;
    border-radius: 8px;
    padding: 1.5rem;
}

.btr-alert-info {
    background: #e3f2fd;
    border-color: #0097c5;
}

.btr-alert-icon {
    flex-shrink: 0;
    width: 24px;
    height: 24px;
    color: #0097c5;
}

.btr-alert-content {
    flex: 1;
}

.btr-alert-content strong {
    display: block;
    margin-bottom: 0.5rem;
    color: #0c5460;
}

.btr-alert-content p {
    margin: 0;
    color: #333;
    line-height: 1.6;
}

/* Modal styles */
.btr-modal {
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.btr-modal-content {
    background-color: #fefefe;
    margin: 15% auto;
    padding: 2rem;
    border: 1px solid #888;
    width: 80%;
    max-width: 400px;
    border-radius: 8px;
    text-align: center;
}

.close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close:hover,
.close:focus {
    color: black;
}

#qr-container {
    margin: 2rem 0;
}

.qr-participant-name {
    font-weight: 600;
    color: #333;
}

/* Print styles */
@media print {
    .btr-actions-bottom,
    .action-buttons,
    .btn-copy-link,
    .btn-send-email,
    .btn-show-qr {
        display: none !important;
    }
    
    .btr-payment-links-summary {
        padding: 0;
    }
    
    .btr-payment-links-table {
        box-shadow: none;
    }
}

/* Responsive */
@media (max-width: 768px) {
    .btr-links-table {
        font-size: 0.9rem;
    }
    
    .btr-links-table th,
    .btr-links-table td {
        padding: 0.5rem;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .action-buttons button {
        width: 100%;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Debug: verifica che jQuery sia caricato
    console.log('BTR Payment Links: jQuery loaded, version:', $.fn.jquery);
    console.log('BTR Payment Links: Document ready fired');
    
    // Debug: verifica che il pulsante esista
    var $organizerButton = $('#organizer-proceed');
    console.log('BTR Payment Links: Organizer button found:', $organizerButton.length > 0);
    if ($organizerButton.length > 0) {
        console.log('BTR Payment Links: Button data-preventivo-id:', $organizerButton.data('preventivo-id'));
    }
    
    // Copia link
    $('.btn-copy-link').on('click', function() {
        var link = $(this).data('link');
        var $button = $(this);
        
        // Crea un elemento temporaneo per copiare
        var $temp = $('<input>');
        $('body').append($temp);
        $temp.val(link).select();
        document.execCommand('copy');
        $temp.remove();
        
        // Feedback visivo
        var originalText = $button.text();
        $button.text('✅ Copiato!');
        setTimeout(function() {
            $button.text(originalText);
        }, 2000);
    });
    
    // Invia email singola
    $('.btn-send-email').on('click', function() {
        var $button = $(this);
        var paymentId = $button.data('payment-id');
        
        $button.prop('disabled', true).text('📤 Invio...');
        
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'btr_send_payment_email',
                payment_id: paymentId,
                nonce: '<?php echo wp_create_nonce('btr_group_payments'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $button.text('✅ Inviata!');
                    setTimeout(function() {
                        $button.text('✉️ Invia Email').prop('disabled', false);
                    }, 3000);
                } else {
                    alert('Errore nell\'invio dell\'email: ' + response.data);
                    $button.text('✉️ Invia Email').prop('disabled', false);
                }
            },
            error: function() {
                alert('Errore di comunicazione con il server');
                $button.text('✉️ Invia Email').prop('disabled', false);
            }
        });
    });
    
    // Invia email a tutti
    $('#send-all-emails').on('click', function() {
        if (!confirm('<?php echo esc_js(__('Sei sicuro di voler inviare le email a tutti i partecipanti?', 'born-to-ride-booking')); ?>')) {
            return;
        }
        
        var $button = $(this);
        var $emailButtons = $('.btn-send-email:not(:disabled)');
        var total = $emailButtons.length;
        var sent = 0;
        
        if (total === 0) {
            alert('<?php echo esc_js(__('Nessuna email da inviare', 'born-to-ride-booking')); ?>');
            return;
        }
        
        $button.prop('disabled', true).text('📤 Invio in corso... 0/' + total);
        
        // Invia email una alla volta con delay
        $emailButtons.each(function(index) {
            setTimeout(function() {
                $(this).click();
                sent++;
                $button.text('📤 Invio in corso... ' + sent + '/' + total);
                
                if (sent === total) {
                    setTimeout(function() {
                        $button.text('✅ Tutte le email inviate!').prop('disabled', false);
                        setTimeout(function() {
                            $button.text('✉️ Invia Email a Tutti i Partecipanti');
                        }, 3000);
                    }, 1000);
                }
            }.bind(this), index * 1000); // 1 secondo di delay tra ogni invio
        });
    });
    
    // Mostra QR Code
    $('.btn-show-qr').on('click', function() {
        var link = $(this).data('link');
        var name = $(this).data('name');
        
        // Genera QR Code (richiede libreria QR code)
        $('#qr-container').html('<p>QR Code per: ' + link + '</p>');
        $('.qr-participant-name').text(name);
        $('#qr-modal').show();
    });
    
    // Chiudi modal
    $('.close, #qr-modal').on('click', function(e) {
        if (e.target === this) {
            $('#qr-modal').hide();
        }
    });
    
    // Gestione pulsante "Procedi come Organizzatore"
    // Usa event delegation per essere sicuri che funzioni anche se il DOM cambia
    $(document).on('click', '#organizer-proceed', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('BTR Payment Links: Organizer button clicked!');
        
        var $button = $(this);
        var preventivoId = $button.data('preventivo-id');
        
        console.log('BTR Payment Links: Preventivo ID:', preventivoId);
        
        if (!preventivoId) {
            alert('<?php echo esc_js(__('Errore: ID preventivo non trovato.', 'born-to-ride-booking')); ?>');
            return;
        }
        
        // Conferma azione
        if (!confirm('<?php echo esc_js(__('Vuoi procedere con la creazione dell\'ordine come organizzatore? L\'ordine rimarrà in attesa fino al completamento dei pagamenti dei partecipanti.', 'born-to-ride-booking')); ?>')) {
            return;
        }
        
        // Disabilita il pulsante
        $button.prop('disabled', true).html('<?php echo esc_js(__('Creazione ordine in corso...', 'born-to-ride-booking')); ?>');
        
        console.log('BTR Payment Links: Sending AJAX request...');
        console.log('BTR Payment Links: AJAX URL:', '<?php echo admin_url('admin-ajax.php'); ?>');
        console.log('BTR Payment Links: Action:', 'btr_create_organizer_order');
        console.log('BTR Payment Links: Nonce:', '<?php echo wp_create_nonce('btr_payment_organizer_nonce'); ?>');
        
        // Chiamata AJAX per creare l'ordine organizzatore
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'btr_create_organizer_order',
                preventivo_id: preventivoId,
                nonce: '<?php echo wp_create_nonce('btr_payment_organizer_nonce'); ?>'
            },
            success: function(response) {
                console.log('BTR Payment Links: AJAX Success:', response);
                
                if (response.success) {
                    // Redirect all'ordine creato o alla pagina di conferma
                    if (response.data.redirect_url) {
                        console.log('BTR Payment Links: Redirecting to:', response.data.redirect_url);
                        window.location.href = response.data.redirect_url;
                    } else {
                        alert('<?php echo esc_js(__('Ordine creato con successo!', 'born-to-ride-booking')); ?>');
                        location.reload();
                    }
                } else {
                    var errorMsg = response.data && response.data.message ? response.data.message : '<?php echo esc_js(__('Errore sconosciuto', 'born-to-ride-booking')); ?>';
                    alert('<?php echo esc_js(__('Errore nella creazione dell\'ordine: ', 'born-to-ride-booking')); ?>' + errorMsg);
                    $button.prop('disabled', false).html('🛒 <?php echo esc_js(__('Procedi come Organizzatore', 'born-to-ride-booking')); ?>');
                }
            },
            error: function(xhr, status, error) {
                console.error('BTR Payment Links: AJAX Error:', status, error);
                console.error('BTR Payment Links: Response:', xhr.responseText);
                alert('<?php echo esc_js(__('Si è verificato un errore di comunicazione con il server. Riprova.', 'born-to-ride-booking')); ?>');
                $button.prop('disabled', false).html('🛒 <?php echo esc_js(__('Procedi come Organizzatore', 'born-to-ride-booking')); ?>');
            }
        });
    });
    
    // Test alternativo: bind diretto sul pulsante se esiste
    if ($organizerButton.length > 0) {
        console.log('BTR Payment Links: Attempting direct bind on organizer button');
        $organizerButton.off('click').on('click', function(e) {
            console.log('BTR Payment Links: Direct bind click triggered!');
            // Trigger the delegated handler
            $(this).trigger('click');
        });
    }
});

// Test se jQuery è disponibile globalmente
if (typeof jQuery === 'undefined') {
    console.error('BTR Payment Links: jQuery not loaded!');
} else {
    console.log('BTR Payment Links: jQuery is available globally');
}
</script>