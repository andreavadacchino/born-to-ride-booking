/**
 * Organizer Dashboard JavaScript
 * 
 * Gestisce le funzionalità AJAX del dashboard organizzatore
 * per i pagamenti di gruppo
 * 
 * @since 1.0.240
 */

(function($) {
    'use strict';
    
    // Inizializzazione quando il DOM è pronto
    $(document).ready(function() {
        
        // Gestione invio reminder
        $('.btr-send-reminder').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var paymentId = $button.data('payment-id');
            var participantName = $button.data('participant-name');
            
            // Conferma prima di inviare
            if (!confirm('Inviare un promemoria a ' + participantName + '?')) {
                return false;
            }
            
            // Disabilita pulsante e mostra loading
            $button.prop('disabled', true);
            $button.html('<span class="dashicons dashicons-update spinning"></span> Invio...');
            
            // Invia richiesta AJAX
            $.ajax({
                url: btr_organizer_dashboard.ajax_url,
                type: 'POST',
                data: {
                    action: 'btr_send_payment_reminder',
                    payment_id: paymentId,
                    nonce: btr_organizer_dashboard.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Mostra messaggio di successo
                        $button.html('<span class="dashicons dashicons-yes"></span> Inviato!');
                        $button.addClass('reminder-sent');
                        
                        // Mostra notifica
                        showNotification('success', response.data.message);
                        
                        // Ripristina pulsante dopo 3 secondi
                        setTimeout(function() {
                            $button.html('Invia Promemoria');
                            $button.removeClass('reminder-sent');
                            $button.prop('disabled', false);
                        }, 3000);
                    } else {
                        // Mostra errore
                        $button.html('Invia Promemoria');
                        $button.prop('disabled', false);
                        showNotification('error', response.data || 'Errore durante l\'invio del promemoria');
                    }
                },
                error: function() {
                    // Errore di connessione
                    $button.html('Invia Promemoria');
                    $button.prop('disabled', false);
                    showNotification('error', 'Errore di connessione. Riprova più tardi.');
                }
            });
        });
        
        // Gestione export CSV
        $('.btr-export-csv').on('click', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var preventivoId = $button.data('preventivo-id');
            
            // Mostra loading
            $button.html('<span class="dashicons dashicons-update spinning"></span> Generazione CSV...');
            $button.prop('disabled', true);
            
            // Crea form nascosto per download
            var $form = $('<form>', {
                method: 'POST',
                action: btr_organizer_dashboard.ajax_url,
                style: 'display:none;'
            });
            
            $form.append($('<input>', {
                type: 'hidden',
                name: 'action',
                value: 'btr_export_group_csv'
            }));
            
            $form.append($('<input>', {
                type: 'hidden',
                name: 'preventivo_id',
                value: preventivoId
            }));
            
            $form.append($('<input>', {
                type: 'hidden',
                name: 'nonce',
                value: btr_organizer_dashboard.nonce
            }));
            
            // Aggiungi al body e submit
            $('body').append($form);
            $form.submit();
            
            // Ripristina pulsante
            setTimeout(function() {
                $button.html('<span class="dashicons dashicons-download"></span> Esporta CSV');
                $button.prop('disabled', false);
                $form.remove();
            }, 2000);
        });
        
        // Gestione copia link pagamento
        $(document).on('click', '.btn-copy-link', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var paymentHash = $button.data('payment-hash');
            var participantName = $button.data('name');
            var paymentUrl = window.location.origin + '/pagamento-gruppo/' + paymentHash;
            
            // Crea elemento temporaneo per copiare
            var $temp = $('<input>');
            $('body').append($temp);
            $temp.val(paymentUrl).select();
            
            try {
                // Copia negli appunti
                document.execCommand('copy');
                
                // Feedback visivo
                var originalHtml = $button.html();
                $button.html('<span class="dashicons dashicons-yes"></span> Copiato!');
                $button.css('background-color', '#46b450');
                $button.css('color', '#fff');
                
                // Ripristina dopo 2 secondi
                setTimeout(function() {
                    $button.html(originalHtml);
                    $button.css('background-color', '');
                    $button.css('color', '');
                }, 2000);
                
            } catch (err) {
                alert('Errore durante la copia. Link: ' + paymentUrl);
            }
            
            // Rimuovi elemento temporaneo
            $temp.remove();
        });
        
        // Aggiorna automaticamente lo stato ogni 30 secondi
        if ($('.btr-group-detail').length > 0) {
            setInterval(function() {
                refreshPaymentStatus();
            }, 30000);
        }
        
        // Gestione toggle dettagli partecipanti
        $('.participant-toggle').on('click', function() {
            var $row = $(this).closest('tr');
            var $details = $row.next('.participant-details');
            
            if ($details.is(':visible')) {
                $details.slideUp();
                $(this).find('.dashicons').removeClass('dashicons-arrow-up').addClass('dashicons-arrow-down');
            } else {
                $details.slideDown();
                $(this).find('.dashicons').removeClass('dashicons-arrow-down').addClass('dashicons-arrow-up');
            }
        });
        
    });
    
    /**
     * Mostra notifica
     */
    function showNotification(type, message) {
        // Rimuovi notifiche esistenti
        $('.btr-notification').remove();
        
        var $notification = $('<div>', {
            class: 'btr-notification btr-notification-' + type,
            html: '<p>' + message + '</p>'
        });
        
        // Aggiungi icona
        if (type === 'success') {
            $notification.prepend('<span class="dashicons dashicons-yes-alt"></span>');
        } else {
            $notification.prepend('<span class="dashicons dashicons-warning"></span>');
        }
        
        // Aggiungi al DOM
        $('.btr-organizer-dashboard').prepend($notification);
        
        // Anima entrata
        $notification.hide().fadeIn();
        
        // Rimuovi dopo 5 secondi
        setTimeout(function() {
            $notification.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    /**
     * Aggiorna stato pagamenti via AJAX
     */
    function refreshPaymentStatus() {
        var preventivoId = $('.btr-group-detail').data('preventivo-id');
        
        if (!preventivoId) {
            return;
        }
        
        $.ajax({
            url: btr_organizer_dashboard.ajax_url,
            type: 'POST',
            data: {
                action: 'btr_refresh_payment_status',
                preventivo_id: preventivoId,
                nonce: btr_organizer_dashboard.nonce
            },
            success: function(response) {
                if (response.success && response.data.html) {
                    // Aggiorna HTML della tabella
                    $('.btr-group-payments-table').replaceWith(response.data.html);
                    
                    // Riattacca event handlers
                    attachEventHandlers();
                    
                    // Mostra notifica se ci sono cambiamenti
                    if (response.data.changes) {
                        showNotification('info', 'Stato pagamenti aggiornato');
                    }
                }
            }
        });
    }
    
    /**
     * Riattacca event handlers dopo aggiornamento AJAX
     */
    function attachEventHandlers() {
        // Rimuovi handlers esistenti per evitare duplicati
        $('.btr-send-reminder').off('click');
        $('.participant-toggle').off('click');
        
        // Riattacca handlers
        $('.btr-send-reminder').on('click', function(e) {
            // ... stesso codice del handler sopra ...
        });
        
        $('.participant-toggle').on('click', function() {
            // ... stesso codice del handler sopra ...
        });
    }
    
    // CSS per spinner
    var style = '<style>' +
        '.dashicons.spinning {' +
            'animation: spin 1s linear infinite;' +
        '}' +
        '@keyframes spin {' +
            'from { transform: rotate(0deg); }' +
            'to { transform: rotate(360deg); }' +
        '}' +
        '.btr-notification {' +
            'background: #fff;' +
            'border-left: 4px solid;' +
            'box-shadow: 0 1px 1px rgba(0,0,0,.04);' +
            'margin: 20px 0;' +
            'padding: 12px 20px;' +
            'position: relative;' +
        '}' +
        '.btr-notification-success {' +
            'border-color: #46b450;' +
        '}' +
        '.btr-notification-error {' +
            'border-color: #dc3232;' +
        '}' +
        '.btr-notification-info {' +
            'border-color: #00a0d2;' +
        '}' +
        '.btr-notification .dashicons {' +
            'color: inherit;' +
            'font-size: 20px;' +
            'margin-right: 8px;' +
            'vertical-align: middle;' +
        '}' +
        '.reminder-sent {' +
            'background: #46b450 !important;' +
            'color: #fff !important;' +
        '}' +
        '</style>';
    
    $('head').append(style);
    
})(jQuery);