 <!-- Sezione Durata della Prenotazione -->
    <div class="btr-section collapsible active">
        <h2>Durata della Prenotazione </h2>
        <div class="section-content">
            <div class="btr-inline-fields">
                <!-- Selezione Tipo Durata -->
                <div class="btr-field-group required">
                    <label for="btr_tipo_durata"><strong>Seleziona il tipo di durata:</strong></label>
                    <select id="btr_tipo_durata" name="btr_tipo_durata" class="select2">
                        <!--<option value="unita_libere" <?php selected($btr_tipo_durata, 'unita_libere'); ?>>Il cliente può prenotare unità di</option>-->
                        <option value="unita_fisse" <?php selected($btr_tipo_durata, 'unita_fisse'); ?>>Unità fisse di</option>
                    </select>
                    <small>Seleziona se il cliente può scegliere liberamente la durata o se il pacchetto ha una durata fissa.</small>
                </div>
                <!-- Sezione per "Il cliente può prenotare unità di" -->
                <div id="durata_unita_libere" class="btr-field-group conditional-field" style="display: <?php echo ($btr_tipo_durata === 'unita_libere') ? 'block' : 'none'; ?>">
                    <label for="btr_numero_giorni_libere"><strong>Numero di Giorni:</strong></label>
                    <input type="number" id="btr_numero_giorni_libere" name="btr_numero_giorni_libere" value="<?php echo esc_attr($btr_numero_giorni_libere); ?>" min="1"/>
                    <small>Specifica il numero di giorni che il cliente può prenotare liberamente.</small>
                </div>
                <!-- Sezione per "Unità fisse di" -->
                <div id="durata_unita_fisse" class="btr-field-group conditional-field" style="display: <?php echo ($btr_tipo_durata === 'unita_fisse') ? 'block' : 'none'; ?>">
                    <div class="btr-field-group">
                        <label for="btr_numero_giorni_fisse"><strong>Numero di Giorni:</strong></label>
                        <input type="number" id="btr_numero_giorni_fisse" name="btr_numero_giorni_fisse" value="<?php echo esc_attr($btr_numero_giorni_fisse); ?>" min="1"/>
                        <small>Definisci il numero fisso di giorni per il pacchetto.</small>
                    </div>
                    <div class="btr-field-group">
                        <label for="btr_numero_notti"><strong>Numero di Notti:</strong></label>
                        <input type="number" id="btr_numero_notti" name="btr_numero_notti" value="<?php echo esc_attr($btr_numero_notti); ?>" min="1"/>
                        <small>Definisci il numero fisso di notti per il pacchetto.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sezione Range di Date Disponibili - Nuovo Sistema -->
    <div class="btr-section collapsible active">
        <h2>Range di Date Disponibili <small>(Sistema Avanzato)</small></h2>
        <div class="section-content">
            <p class="description">
                <strong>Nuovo sistema di gestione date:</strong> Seleziona un intervallo continuo di date per creare automaticamente 
                tutti i giorni disponibili. Il sistema sostituisce il vecchio calendario discreto con range continui più efficienti.
            </p>
            
            <!-- Date Range Picker Container -->
            <div class="btr-date-range-picker-container">
                <div class="btr-field-group">
                    <label for="btr-date-range-picker">
                        <strong>Seleziona Range Date</strong>
                        <?=info_desc('Clicca per aprire il calendario e seleziona data di inizio e fine. Il sistema genererà automaticamente tutti i giorni intermedi.'); ?>
                    </label>
                    <input type="text" 
                           id="btr-date-range-picker" 
                           class="btr-date-range-picker" 
                           placeholder="Seleziona data inizio e fine..."
                           data-package-id="<?php echo get_the_ID(); ?>"
                           data-range-end-input="#date-range-end"
                           readonly />
                    
                    <!-- Hidden input per la data fine (richiesto da rangePlugin) -->
                    <input type="hidden" id="date-range-end" />
                </div>
                
                <!-- Opzioni Avanzate -->
                <div class="btr-advanced-options" style="margin-top: 20px;">
                    <h4>Opzioni Avanzate</h4>
                    <div class="btr-inline-fields">
                        <div class="btr-field-group">
                            <label>Capacità Massima (per giorno)</label>
                            <input type="number" 
                                   id="btr-max-capacity" 
                                   min="1" 
                                   placeholder="Lascia vuoto per illimitato" />
                            <small>Numero massimo di prenotazioni per singolo giorno</small>
                        </div>
                        
                        <div class="btr-field-group">
                            <label>Modificatore Prezzo (%)</label>
                            <input type="number" 
                                   id="btr-price-modifier" 
                                   step="0.01" 
                                   value="0" 
                                   placeholder="0.00" />
                            <small>Percentuale di aumento/riduzione prezzo (es. 10 = +10%, -5 = -5%)</small>
                        </div>
                        
                        <div class="btr-field-group">
                            <label>Note</label>
                            <textarea id="btr-range-notes" 
                                      rows="3" 
                                      placeholder="Note opzionali per questo range di date..."></textarea>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Range Esistenti -->
            <div class="btr-existing-ranges" style="margin-top: 30px;">
                <h4>Range Salvati</h4>
                <div id="btr-existing-ranges-list">
                    <p class="description">I range di date salvati appariranno qui dopo il salvataggio.</p>
                </div>
                <button type="button" id="btr-refresh-ranges" class="button button-secondary">
                    <span class="dashicons dashicons-update"></span> Aggiorna Lista
                </button>
            </div>
            
            <!-- Retrocompatibilità: mantiene i campi esistenti nascosti per compatibilità -->
            <div style="display: none;">
                <input type="hidden" name="btr_date_ranges_legacy" value="1" />
            </div>
        </div>
    </div>

    <!-- CSS Stili per il nuovo date picker -->
    <style>
        .btr-date-range-picker-container {
            background: #f9f9f9;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .btr-date-range-picker {
            width: 100%;
            padding: 10px;
            font-size: 14px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .btr-advanced-options {
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        
        .btr-advanced-options h4 {
            margin: 0 0 15px 0;
            color: #333;
        }
        
        .btr-existing-ranges {
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        
        .btr-existing-ranges h4 {
            margin: 0 0 15px 0;
            color: #333;
        }
        
        #btr-existing-ranges-list {
            min-height: 50px;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 10px;
        }
        
        .btr-range-item {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .btr-range-info h5 {
            margin: 0 0 5px 0;
            color: #2271b1;
        }
        
        .btr-range-info p {
            margin: 0;
            color: #666;
            font-size: 13px;
        }
        
        .btr-range-actions button {
            margin-left: 5px;
        }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // Inizializza il date range picker quando la tab è attiva
        $('.meta-box-tabs li[data-tab="date"]').on('click', function() {
            setTimeout(function() {
                if (typeof BTRDateRangePicker !== 'undefined') {
                    // Inizializza solo se non già inizializzato
                    if (!window.btrDateRangePickerInstance) {
                        window.btrDateRangePickerInstance = new BTRDateRangePicker('.btr-date-range-picker');
                    }
                }
            }, 100);
        });
        
        // Gestisci il refresh dei range esistenti
        $('#btr-refresh-ranges').on('click', function() {
            var packageId = $('.btr-date-range-picker').data('package-id');
            if (!packageId) return;
            
            var $button = $(this);
            var originalText = $button.html();
            $button.html('<span class="dashicons dashicons-update spin"></span> Caricamento...');
            $button.prop('disabled', true);
            
            $.ajax({
                url: window.ajaxurl || '/wp-admin/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'btr_get_package_date_ranges',
                    package_id: packageId,
                    nonce: window.btrAdmin?.nonce || ''
                },
                success: function(response) {
                    if (response.success) {
                        displayExistingRanges(response.data.ranges);
                    } else {
                        alert('Errore nel caricamento dei range: ' + (response.data.message || 'Errore sconosciuto'));
                    }
                },
                error: function() {
                    alert('Errore di connessione al server');
                },
                complete: function() {
                    $button.html(originalText);
                    $button.prop('disabled', false);
                }
            });
        });
        
        // Funzione per visualizzare i range esistenti
        function displayExistingRanges(ranges) {
            var $container = $('#btr-existing-ranges-list');
            
            if (!ranges || ranges.length === 0) {
                $container.html('<p class="description">Nessun range di date salvato.</p>');
                return;
            }
            
            var html = '';
            ranges.forEach(function(range) {
                html += '<div class="btr-range-item">' +
                    '<div class="btr-range-info">' +
                        '<h5>Range: ' + range.range_start_date + ' - ' + range.range_end_date + '</h5>' +
                        '<p><strong>Giorni totali:</strong> ' + range.total_days + ' | ' +
                        '<strong>Disponibili:</strong> ' + range.available_days + ' | ' +
                        '<strong>Aggiornato:</strong> ' + range.updated_at + '</p>' +
                    '</div>' +
                    '<div class="btr-range-actions">' +
                        '<button type="button" class="button button-small button-secondary" onclick="viewRangeDetails(' + range.package_id + ', \'' + range.range_start_date + '\', \'' + range.range_end_date + '\')">Dettagli</button>' +
                    '</div>' +
                '</div>';
            });
            
            $container.html(html);
        }
        
        // Carica automaticamente i range esistenti quando la tab viene aperta
        $('.meta-box-tabs li[data-tab="date"]').on('click', function() {
            setTimeout(function() {
                $('#btr-refresh-ranges').trigger('click');
            }, 200);
        });
    });
    
    // Funzione globale per visualizzare i dettagli di un range
    function viewRangeDetails(packageId, startDate, endDate) {
        alert('Dettagli range:\nPacchetto: ' + packageId + '\nDa: ' + startDate + '\nA: ' + endDate + '\n\nFunzionalità completa in sviluppo...');
    }
    </script>

    <!-- Legacy Date Ranges (per retrocompatibilità) -->
    <div class="btr-section collapsible" style="display: none;" id="legacy-date-ranges">
        <h2>Range di Date Disponibili (Sistema Precedente)</h2>
        <div class="section-content">
            <p class="description">
                <strong>Sistema precedente:</strong> Utilizza questo sistema solo se hai problemi con il nuovo date picker.
                <button type="button" id="toggle-legacy-system" class="button button-link">Mostra sistema precedente</button>
            </p>
            
            <div id="btr-date-ranges">
                <?php
                // Mantieni il codice legacy per retrocompatibilità
                if (!empty($btr_date_ranges) && is_array($btr_date_ranges)) {
                    foreach ($btr_date_ranges as $index => $range) {
                        ?>
                        <div class="btr-date-range">
                            <span class="dashicons dashicons-move drag-handle"></span>
                            <div class="btr-inline-fields">
                                <div class="btr-field-group">
                                    <label>Data Inizio</label>
                                    <input type="date" name="btr_date_ranges[<?php echo esc_attr($index); ?>][start]" value="<?php echo esc_attr($range['start']); ?>" id="start_date_<?php echo esc_attr($index); ?>">
                                </div>
                                <div class="btr-field-group">
                                    <label>Data Fine</label>
                                    <input type="date" name="btr_date_ranges[<?php echo esc_attr($index); ?>][end]" value="<?php echo esc_attr($range['end']); ?>" id="end_date_<?php echo esc_attr($index); ?>">
                                </div>
                                <div class="btr-field-group">
                                    <label>Data rinominata</label>
                                    <input type="text" name="btr_date_ranges[<?php echo esc_attr($index); ?>][name]" value="<?php echo esc_attr($range['name'] ?? ''); ?>"
                                           id="formatted_date_<?php echo esc_attr($index); ?>">
                                </div>
                                <button class="remove-item button">Rimuovi</button>
                            </div>
                        </div>
                        <?php
                    }
                }
                ?>
            </div>
            <button id="add-date-range" class="button">Aggiungi Range di Date</button>
        </div>
    </div>