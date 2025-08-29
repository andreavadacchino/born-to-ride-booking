/**
 * Script amministrazione piani di pagamento
 * 
 * @package BornToRideBooking
 * @since 1.0.98
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Cache elementi DOM
    const $bulkActions = $('.bulkactions select[name="action"]');
    const $checkAll = $('#cb-select-all, #cb-select-all-2');
    const $paymentRows = $('.wp-list-table tbody tr');
    
    /**
     * Gestione checkbox "seleziona tutto"
     */
    $checkAll.on('change', function() {
        const isChecked = $(this).prop('checked');
        $('input[name="payment_ids[]"]').prop('checked', isChecked);
        
        // Sincronizza entrambi i checkbox
        $checkAll.prop('checked', isChecked);
    });
    
    /**
     * Gestione azioni bulk
     */
    $('.bulkactions .button.action').on('click', function(e) {
        const action = $bulkActions.val();
        
        if (!action) {
            return;
        }
        
        const $checkedBoxes = $('input[name="payment_ids[]"]:checked');
        
        if ($checkedBoxes.length === 0) {
            e.preventDefault();
            alert(btr_payment_admin.strings.no_selection || 'Seleziona almeno un pagamento');
            return;
        }
        
        // Conferma per invio reminder bulk
        if (action === 'bulk_send_reminders') {
            if (!confirm(btr_payment_admin.strings.confirm_bulk_reminders)) {
                e.preventDefault();
                return;
            }
        }
    });
    
    /**
     * Gestione click su link promemoria singolo
     */
    $('a[href*="action=send_reminder"]').on('click', function(e) {
        if (!confirm(btr_payment_admin.strings.confirm_send_reminder)) {
            e.preventDefault();
            return;
        }
    });
    
    /**
     * Mostra/nascondi dettagli pagamento
     */
    $('.view-payment-details').on('click', function(e) {
        e.preventDefault();
        
        const paymentId = $(this).data('payment-id');
        showPaymentDetails(paymentId);
    });
    
    /**
     * Mostra dettagli pagamento in modal
     */
    function showPaymentDetails(paymentId) {
        // Mostra loading
        showLoadingModal();
        
        // Richiesta AJAX
        $.ajax({
            url: btr_payment_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'btr_get_payment_details',
                payment_id: paymentId,
                nonce: btr_payment_admin.nonce
            },
            success: function(response) {
                hideLoadingModal();
                
                if (response.success) {
                    showPaymentModal(response.data.payment);
                } else {
                    alert(response.data.message || btr_payment_admin.strings.error);
                }
            },
            error: function() {
                hideLoadingModal();
                alert(btr_payment_admin.strings.error);
            }
        });
    }
    
    /**
     * Modal dettagli pagamento
     */
    function showPaymentModal(payment) {
        // Rimuovi modal esistente
        $('#btr-payment-modal').remove();
        
        // Crea HTML modal
        const modalHtml = `
            <div id="btr-payment-modal" class="btr-modal">
                <div class="btr-modal-content">
                    <div class="btr-modal-header">
                        <h2>Dettagli Pagamento #${payment.payment_id}</h2>
                        <button class="btr-modal-close">&times;</button>
                    </div>
                    <div class="btr-modal-body">
                        <table class="form-table">
                            <tr>
                                <th>Preventivo:</th>
                                <td>${payment.preventivo_title || 'Preventivo #' + payment.preventivo_id}</td>
                            </tr>
                            <tr>
                                <th>Pacchetto:</th>
                                <td>${payment.package_title || '-'}</td>
                            </tr>
                            <tr>
                                <th>Partecipante:</th>
                                <td>
                                    <strong>${payment.group_member_name || payment.participant_name}</strong><br>
                                    <a href="mailto:${payment.participant_email}">${payment.participant_email}</a>
                                </td>
                            </tr>
                            <tr>
                                <th>Importo:</th>
                                <td class="btr-amount">${formatCurrency(payment.amount)}</td>
                            </tr>
                            <tr>
                                <th>Stato:</th>
                                <td>${getStatusBadge(payment.payment_status)}</td>
                            </tr>
                            <tr>
                                <th>Creato il:</th>
                                <td>${formatDate(payment.created_at)}</td>
                            </tr>
                            ${payment.paid_at ? `
                            <tr>
                                <th>Pagato il:</th>
                                <td>${formatDate(payment.paid_at)}</td>
                            </tr>
                            ` : ''}
                            <tr>
                                <th>Scadenza:</th>
                                <td>${formatDate(payment.expires_at)}</td>
                            </tr>
                            ${payment.payment_hash ? `
                            <tr>
                                <th>Link pagamento:</th>
                                <td>
                                    <input type="text" value="${window.location.origin}/pagamento-gruppo/${payment.payment_hash}" readonly class="regular-text">
                                    <button class="button button-small copy-link" data-link="${window.location.origin}/pagamento-gruppo/${payment.payment_hash}">Copia</button>
                                </td>
                            </tr>
                            ` : ''}
                        </table>
                    </div>
                    <div class="btr-modal-footer">
                        ${payment.payment_status === 'pending' ? `
                            <button class="button button-primary send-reminder-btn" data-payment-id="${payment.payment_id}">
                                Invia Promemoria
                            </button>
                        ` : ''}
                        ${payment.wc_order_id ? `
                            <a href="${getOrderEditUrl(payment.wc_order_id)}" class="button">
                                Visualizza Ordine
                            </a>
                        ` : ''}
                        <button class="button btr-modal-close">Chiudi</button>
                    </div>
                </div>
            </div>
        `;
        
        // Aggiungi al body
        $('body').append(modalHtml);
        
        // Event handlers
        $('#btr-payment-modal .btr-modal-close').on('click', function() {
            $('#btr-payment-modal').remove();
        });
        
        $('#btr-payment-modal .copy-link').on('click', function() {
            const link = $(this).data('link');
            copyToClipboard(link);
            $(this).text('Copiato!');
            setTimeout(() => {
                $(this).text('Copia');
            }, 2000);
        });
        
        $('#btr-payment-modal .send-reminder-btn').on('click', function() {
            const paymentId = $(this).data('payment-id');
            sendReminderAjax(paymentId);
        });
    }
    
    /**
     * Invia reminder via AJAX
     */
    function sendReminderAjax(paymentId) {
        if (!confirm(btr_payment_admin.strings.confirm_send_reminder)) {
            return;
        }
        
        const $button = $('.send-reminder-btn[data-payment-id="' + paymentId + '"]');
        const originalText = $button.text();
        
        $button.prop('disabled', true).text(btr_payment_admin.strings.processing);
        
        $.ajax({
            url: btr_payment_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'btr_send_payment_reminder',
                payment_id: paymentId,
                nonce: btr_payment_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $button.text('Inviato!');
                    setTimeout(() => {
                        $('#btr-payment-modal').remove();
                        location.reload();
                    }, 1500);
                } else {
                    alert(response.data.message || btr_payment_admin.strings.error);
                    $button.prop('disabled', false).text(originalText);
                }
            },
            error: function() {
                alert(btr_payment_admin.strings.error);
                $button.prop('disabled', false).text(originalText);
            }
        });
    }
    
    /**
     * Aggiorna stato pagamento
     */
    $('.update-payment-status').on('change', function() {
        const $select = $(this);
        const paymentId = $select.data('payment-id');
        const newStatus = $select.val();
        const originalValue = $select.data('original-value');
        
        if (newStatus === originalValue) {
            return;
        }
        
        if (!confirm('Sei sicuro di voler aggiornare lo stato del pagamento?')) {
            $select.val(originalValue);
            return;
        }
        
        // Disabilita select
        $select.prop('disabled', true);
        
        $.ajax({
            url: btr_payment_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'btr_update_payment_status',
                payment_id: paymentId,
                status: newStatus,
                nonce: btr_payment_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Aggiorna valore originale
                    $select.data('original-value', newStatus);
                    
                    // Mostra notifica
                    showNotification('Stato aggiornato con successo', 'success');
                    
                    // Ricarica dopo 1 secondo
                    setTimeout(() => {
                        location.reload();
                    }, 1000);
                } else {
                    alert(response.data.message || btr_payment_admin.strings.error);
                    $select.val(originalValue);
                }
                
                $select.prop('disabled', false);
            },
            error: function() {
                alert(btr_payment_admin.strings.error);
                $select.val(originalValue);
                $select.prop('disabled', false);
            }
        });
    });
    
    /**
     * Gestione filtri data
     */
    $('input[name="date_from"], input[name="date_to"]').on('change', function() {
        const dateFrom = $('input[name="date_from"]').val();
        const dateTo = $('input[name="date_to"]').val();
        
        if (dateFrom && dateTo && dateFrom > dateTo) {
            alert('La data di inizio deve essere precedente alla data di fine');
            $(this).val('');
        }
    });
    
    /**
     * Reset filtri
     */
    $('.reset-filters').on('click', function(e) {
        e.preventDefault();
        
        // Reset tutti i filtri
        $('select[name="status"], select[name="plan_type"]').val('');
        $('input[name="date_from"], input[name="date_to"], input[name="s"]').val('');
        
        // Submit form
        $(this).closest('form').submit();
    });
    
    /**
     * Export CSV con progress
     */
    $('a[href*="action=export"]').on('click', function(e) {
        showNotification('Preparazione export CSV...', 'info');
    });
    
    // Utility functions
    
    /**
     * Mostra modal loading
     */
    function showLoadingModal() {
        const loadingHtml = `
            <div id="btr-loading-modal" class="btr-modal">
                <div class="btr-modal-content btr-loading">
                    <div class="spinner is-active"></div>
                    <p>${btr_payment_admin.strings.processing}</p>
                </div>
            </div>
        `;
        $('body').append(loadingHtml);
    }
    
    /**
     * Nascondi modal loading
     */
    function hideLoadingModal() {
        $('#btr-loading-modal').remove();
    }
    
    /**
     * Formatta valuta
     */
    function formatCurrency(amount) {
        return new Intl.NumberFormat('it-IT', {
            style: 'currency',
            currency: 'EUR'
        }).format(amount);
    }
    
    /**
     * Formatta data
     */
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('it-IT', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
    
    /**
     * Ottieni badge stato
     */
    function getStatusBadge(status) {
        const badges = {
            'pending': '<span class="btr-status pending">In attesa</span>',
            'paid': '<span class="btr-status paid">Pagato</span>',
            'failed': '<span class="btr-status failed">Fallito</span>',
            'expired': '<span class="btr-status expired">Scaduto</span>'
        };
        
        return badges[status] || status;
    }
    
    /**
     * Ottieni URL modifica ordine
     */
    function getOrderEditUrl(orderId) {
        return btr_payment_admin.admin_url + 'post.php?post=' + orderId + '&action=edit';
    }
    
    /**
     * Copia negli appunti
     */
    function copyToClipboard(text) {
        const temp = $('<input>');
        $('body').append(temp);
        temp.val(text).select();
        document.execCommand('copy');
        temp.remove();
    }
    
    /**
     * Mostra notifica
     */
    function showNotification(message, type = 'info') {
        // Rimuovi notifiche esistenti
        $('.btr-notification').remove();
        
        const notificationHtml = `
            <div class="btr-notification notice notice-${type} is-dismissible">
                <p>${message}</p>
            </div>
        `;
        
        $('.wp-header-end').after(notificationHtml);
        
        // Auto-rimuovi dopo 5 secondi
        setTimeout(() => {
            $('.btr-notification').fadeOut(() => {
                $('.btr-notification').remove();
            });
        }, 5000);
    }
    
    /**
     * Inizializza tooltip
     */
    if ($.fn.tooltip) {
        $('.has-tooltip').tooltip();
    }
});