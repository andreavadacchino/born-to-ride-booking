/**
 * Script per drag & drop degli extra costs nell'admin
 * 
 * @package Born_To_Ride_Booking
 * @since 1.0.55
 */

jQuery(function($) {
    'use strict';
    
    // Inizializza solo se siamo nella pagina giusta
    if (!$('#btr_costi_extra_metabox').length) {
        return;
    }
    
    // Prepara la lista per il sortable
    function initializeSortable() {
        var $container = $('#btr_costi_extra_repeater');
        
        if (!$container.length) {
            return;
        }
        
        // Aggiungi classe container se non presente
        if (!$container.hasClass('btr-extra-costs-list')) {
            $container.addClass('btr-extra-costs-list');
        }
        
        // Aggiungi handle e badge ordine a ogni item
        $container.find('.btr-extra-cost-item').each(function(index) {
            var $item = $(this);
            
            // Aggiungi handle se non presente
            if (!$item.find('.btr-drag-handle').length) {
                $item.prepend('<div class="btr-drag-handle" title="Trascina per riordinare"></div>');
            }
            
            // Aggiungi/aggiorna badge ordine
            var $badge = $item.find('.btr-extra-order-badge');
            if (!$badge.length) {
                $badge = $('<span class="btr-extra-order-badge"></span>');
                $item.append($badge);
            }
            $badge.text('#' + (index + 1));
            
            // Wrap del contenuto se necessario
            if (!$item.find('.btr-extra-cost-content').length) {
                $item.children().not('.btr-drag-handle, .btr-extra-order-badge').wrapAll('<div class="btr-extra-cost-content"></div>');
            }
        });
        
        // Inizializza jQuery UI Sortable
        $container.sortable({
            items: '.btr-extra-cost-item',
            handle: '.btr-drag-handle',
            placeholder: 'ui-sortable-placeholder',
            revert: 200,
            tolerance: 'pointer',
            cursor: 'grabbing',
            
            start: function(event, ui) {
                ui.item.addClass('is-dragging');
                // Imposta altezza placeholder
                ui.placeholder.height(ui.item.outerHeight());
            },
            
            stop: function(event, ui) {
                ui.item.removeClass('is-dragging');
                // Aggiorna badge ordine
                updateOrderBadges();
                // Salva nuovo ordine
                saveNewOrder();
            }
        });
    }
    
    // Aggiorna i badge con il nuovo ordine
    function updateOrderBadges() {
        $('.btr-extra-cost-item').each(function(index) {
            $(this).find('.btr-extra-order-badge').text('#' + (index + 1));
            // Aggiorna anche il campo hidden dell'ordine
            $(this).find('.btr-extra-order-field').val(index);
        });
    }
    
    // Salva il nuovo ordine via AJAX
    function saveNewOrder() {
        var $container = $('#btr_costi_extra_repeater');
        var package_id = $('#post_ID').val();
        
        if (!package_id) {
            console.error('Package ID non trovato');
            return;
        }
        
        // Raccogli l'ordine corrente
        var order = [];
        $container.find('.btr-extra-cost-item').each(function() {
            var slug = $(this).find('input[name*="[slug]"]').val();
            if (slug) {
                order.push(slug);
            }
        });
        
        // Mostra stato salvataggio
        showStatus('saving');
        
        // Invia richiesta AJAX
        $.post(btr_sortable.ajax_url, {
            action: 'btr_save_extra_costs_order',
            nonce: btr_sortable.nonce,
            package_id: package_id,
            order: order
        })
        .done(function(response) {
            if (response.success) {
                showStatus('saved');
            } else {
                showStatus('error');
                console.error(response.data.message);
            }
        })
        .fail(function() {
            showStatus('error');
            console.error('Errore di connessione');
        });
    }
    
    // Mostra stato del salvataggio
    function showStatus(status) {
        var $statusEl = $('.btr-sort-status');
        
        if (!$statusEl.length) {
            $statusEl = $('<span class="btr-sort-status"></span>');
            $('#btr_costi_extra_metabox h2').append($statusEl);
        }
        
        // Rimuovi classi precedenti
        $statusEl.removeClass('saving saved error show');
        
        // Aggiungi nuova classe e testo
        switch(status) {
            case 'saving':
                $statusEl.addClass('saving show').text(btr_sortable.saving_text);
                break;
            case 'saved':
                $statusEl.addClass('saved show').text(btr_sortable.saved_text);
                setTimeout(function() {
                    $statusEl.removeClass('show');
                }, 3000);
                break;
            case 'error':
                $statusEl.addClass('error show').text(btr_sortable.error_text);
                setTimeout(function() {
                    $statusEl.removeClass('show');
                }, 5000);
                break;
        }
    }
    
    // Re-inizializza quando vengono aggiunti nuovi extra
    $(document).on('btr_extra_added', function() {
        setTimeout(initializeSortable, 100);
    });
    
    // Gestisci l'aggiunta di nuovi extra
    $(document).on('click', '.btr-add-extra-cost', function() {
        setTimeout(function() {
            initializeSortable();
            updateOrderBadges();
        }, 100);
    });
    
    // Inizializza al caricamento
    initializeSortable();
    
    // Fix per compatibilità con altri script
    if (window.btrExtraCostsRepeater && window.btrExtraCostsRepeater.onItemAdded) {
        var originalOnItemAdded = window.btrExtraCostsRepeater.onItemAdded;
        window.btrExtraCostsRepeater.onItemAdded = function() {
            originalOnItemAdded.apply(this, arguments);
            setTimeout(initializeSortable, 100);
        };
    }
});