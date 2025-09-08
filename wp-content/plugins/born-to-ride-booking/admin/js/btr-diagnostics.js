/**
 * JavaScript per la pagina di diagnostica Born to Ride Booking
 * 
 * @since 1.0.107
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        
        // Run diagnostics button
        $('#btr-run-diagnostics').on('click', function() {
            var $button = $(this);
            var $results = $('#btr-diagnostics-results');
            
            // Disabilita il pulsante
            $button.prop('disabled', true).text(btrDiagnostics.strings.running);
            
            // Mostra loading
            $results.html('<div class="btr-diagnostics-loading"><span class="spinner is-active"></span><p>' + btrDiagnostics.strings.running + '</p></div>').show();
            
            // Esegui diagnostica via AJAX
            $.ajax({
                url: btrDiagnostics.ajax_url,
                type: 'POST',
                data: {
                    action: 'btr_run_diagnostics',
                    nonce: btrDiagnostics.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $results.html(response.data.html);
                        
                        // Calcola e mostra summary
                        showStatusSummary();
                        
                        // Abilita export
                        enableExport();
                    } else {
                        $results.html('<div class="notice notice-error"><p>' + (response.data.message || btrDiagnostics.strings.error) + '</p></div>');
                    }
                },
                error: function() {
                    $results.html('<div class="notice notice-error"><p>' + btrDiagnostics.strings.error + '</p></div>');
                },
                complete: function() {
                    // Riabilita il pulsante
                    $button.prop('disabled', false).text($button.data('original-text') || 'Esegui Diagnostica Completa');
                }
            });
        });
        
        // Salva testo originale del pulsante
        $('#btr-run-diagnostics').each(function() {
            $(this).data('original-text', $(this).text());
        });
        
        // Funzione per mostrare summary
        function showStatusSummary() {
            var totalChecks = 0;
            var okChecks = 0;
            var errorChecks = 0;
            
            // Conta i check
            $('.status-indicator').each(function() {
                totalChecks++;
                if ($(this).hasClass('status-ok')) {
                    okChecks++;
                } else if ($(this).hasClass('status-error')) {
                    errorChecks++;
                }
            });
            
            // Crea summary HTML
            var summaryHtml = '<div class="btr-status-summary">';
            summaryHtml += '<div class="btr-status-summary-item ok"><span class="count">' + okChecks + '</span><span class="label">OK</span></div>';
            summaryHtml += '<div class="btr-status-summary-item error"><span class="count">' + errorChecks + '</span><span class="label">Errori</span></div>';
            summaryHtml += '<div class="btr-status-summary-item total"><span class="count">' + totalChecks + '</span><span class="label">Totale</span></div>';
            summaryHtml += '</div>';
            
            // Inserisci dopo il titolo
            $('.btr-diagnostics-results h2').after(summaryHtml);
        }
        
        // Export JSON functionality
        function enableExport() {
            $('#btr-export-diagnostics').on('click', function() {
                if (typeof diagnosticsData !== 'undefined') {
                    // Crea blob JSON
                    var dataStr = JSON.stringify(diagnosticsData, null, 2);
                    var dataBlob = new Blob([dataStr], {type: 'application/json'});
                    
                    // Crea link download
                    var link = document.createElement('a');
                    link.href = URL.createObjectURL(dataBlob);
                    link.download = 'btr-diagnostics-' + new Date().toISOString().slice(0,10) + '.json';
                    
                    // Trigger download
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                }
            });
        }
        
        // Auto-refresh info boxes every 30 seconds
        if ($('.btr-info-grid').length) {
            setInterval(function() {
                // Qui potremmo implementare un refresh AJAX delle info rapide
                // Per ora lasciamo solo il timer
            }, 30000);
        }
        
        // Expand/Collapse sections
        $(document).on('click', '.btr-diagnostic-section h3', function() {
            var $section = $(this).parent();
            var $table = $section.find('table');
            
            if ($table.is(':visible')) {
                $table.slideUp(200);
                $(this).addClass('collapsed');
            } else {
                $table.slideDown(200);
                $(this).removeClass('collapsed');
            }
        });
        
        // Highlight rows with errors
        $(document).on('mouseenter', '.btr-diagnostic-table tr', function() {
            if ($(this).find('.status-error').length) {
                $(this).css('background-color', '#fff5f5');
            }
        }).on('mouseleave', '.btr-diagnostic-table tr', function() {
            $(this).css('background-color', '');
        });
        
        // Copy to clipboard functionality for values
        $(document).on('click', '.btr-diagnostic-table td:nth-child(3)', function() {
            var text = $(this).text();
            if (text && navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    // Visual feedback
                    var $td = $(this);
                    var originalBg = $td.css('background-color');
                    $td.css('background-color', '#d4edda');
                    setTimeout(function() {
                        $td.css('background-color', originalBg);
                    }, 300);
                }.bind(this));
            }
        });
        
        // Filtro per sezioni con errori
        var $filterButton = $('<button class="button" style="margin-left: 10px;">Mostra Solo Errori</button>');
        $('#btr-run-diagnostics').after($filterButton);
        
        $filterButton.on('click', function() {
            var showAll = $(this).data('show-all');
            
            if (showAll) {
                // Mostra tutto
                $('.btr-diagnostic-section').show();
                $('.btr-diagnostic-table tr').show();
                $(this).text('Mostra Solo Errori');
                $(this).data('show-all', false);
            } else {
                // Mostra solo errori
                $('.btr-diagnostic-table tbody tr').each(function() {
                    if ($(this).find('.status-error').length) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
                
                // Nascondi sezioni senza errori visibili
                $('.btr-diagnostic-section').each(function() {
                    if ($(this).find('.status-error:visible').length === 0) {
                        $(this).hide();
                    }
                });
                
                $(this).text('Mostra Tutto');
                $(this).data('show-all', true);
            }
        });
        
    });
    
})(jQuery);