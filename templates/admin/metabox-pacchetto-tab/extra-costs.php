<?php
// Inserimento slot di default "Culla per Neonati" e "No Skipass" se non presenti
if (!isset($btr_costi_extra) || !is_array($btr_costi_extra)) {
    $btr_costi_extra = [];
}

// Controlla se esiste "Culla per Neonati"
$has_culla = false;
foreach ($btr_costi_extra as $extra) {
    if (isset($extra['slug']) && $extra['slug'] === 'culla-per-neonati') {
        $has_culla = true;
        break;
    }
}

// Controlla se esiste "No Skipass"
$has_no_skipass = false;
foreach ($btr_costi_extra as $extra) {
    if (isset($extra['slug']) && $extra['slug'] === 'no-skipass') {
        $has_no_skipass = true;
        break;
    }
}

// Aggiungi "Culla per Neonati" se non presente
if (!$has_culla) {
    $culla_extra = [
        'nome' => 'Culla per Neonati',
        'slug' => 'culla-per-neonati',
        'importo' => '',
        'moltiplica_persone' => '1',
        'moltiplica_durata' => '0',
        'attivo' => '1',
        'tooltip_text' => 'Culla aggiuntiva per neonati (0-2 anni).',
    ];
    array_unshift($btr_costi_extra, $culla_extra);
}

// Aggiungi "No Skipass" se non presente
if (!$has_no_skipass) {
    $no_skipass_extra = [
        'nome' => 'No Skipass',
        'slug' => 'no-skipass',
        'importo' => '0', // Potrebbe essere un risparmio/sconto
        'moltiplica_persone' => '1',
        'moltiplica_durata' => '0',
        'attivo' => '1',
        'tooltip_text' => 'Seleziona questa opzione se non desideri includere lo skipass nel pacchetto.',
    ];
    // Aggiungi dopo la culla ma prima degli altri
    if ($has_culla) {
        array_splice($btr_costi_extra, 1, 0, [$no_skipass_extra]);
    } else {
        array_unshift($btr_costi_extra, $no_skipass_extra);
    }
}
?>
<div class="btr-section collapsible active btr-extra-costs-section">
    <div class="btr-info-box" style="border-left: 4px solid #0073aa; background: #f0f8ff; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
        <p style="margin: 0 0 5px 0;"><strong>Come funzionano i costi extra?</strong></p>
        <ul style="margin: 0; padding-left: 20px;">
            <li>Puoi aggiungere supplementi o servizi extra per persona o per durata del viaggio.</li>
            <li>I costi <strong>"Per persona"</strong> vengono moltiplicati per il numero di partecipanti.</li>
            <li>I costi <strong>"Per durata"</strong> vengono applicati una volta sola per tutto il gruppo.</li>
            <li>Se selezioni <strong>entrambe le opzioni</strong>, il costo diventa <strong>"Per persona per notte"</strong> (moltiplicato per persone × notti).</li>
            <li>Dopo averli creati e salvato il pacchetto, saranno selezionabili dagli utenti nel frontend durante la prenotazione.</li>
        </ul>
    </div>
    <h2>Costi Extra</h2>
    <div class="section-content">
        <p class="description">Imposta costi extra aggiuntivi per questo pacchetto, come supplementi particolari o servizi.</p>
        
        <style>
            .btr-extra-costs-section .btr-extra-cost-card {
                display: flex;
                flex-direction: row;
                flex-wrap: wrap;
                align-items: center;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 5px;
                padding: 15px;
                margin-bottom: 15px;
            }
            .btr-extra-container {
                display: flex;
                width: 100%;
                align-items: center;
            }
            .btr-extra-cost-fields {
                display: flex;
                flex: 1;
                gap: 15px;
                align-items: center;
                flex-wrap: wrap;
            }
            .btr-extra-cost-card .drag-handle {
                cursor: move;
                margin-right: 10px;
                color: #999;
            }
            .btr-extra-cost-item {
                width: 100%;
            }
        </style>
        
        <div id="btr-costi-extra" class="btr-extra-costs-container">
            <?php
            if (!empty($btr_costi_extra) && is_array($btr_costi_extra)) {
                foreach ($btr_costi_extra as $index => $costo) {
                    // IMPORTANTE: Mostriamo TUTTI i costi extra, non solo quelli "per persona"
                    ?>
                    <div class="btr-extra-cost-item">
                        <div class="btr-extra-cost-card">
                            <div class="btr-extra-container">
                                <span class="dashicons dashicons-move drag-handle"></span>
                                <div class="btr-extra-cost-fields">
                                    <!-- Nome Costo Extra -->
                                    <div class="btr-field-group">
                                        <label>Nome Costo Extra</label>
                                        <input type="text" 
                                               name="btr_costi_extra[<?php echo esc_attr($index); ?>][nome]"
                                               value="<?php echo esc_attr($costo['nome'] ?? ''); ?>" 
                                               placeholder="es. Skipass" />
                                    </div>
                                    
                                    <!-- Importo (€) -->
                                    <div class="btr-field-group">
                                        <label>Importo (€)</label>
                                        <input type="number" 
                                               name="btr_costi_extra[<?php echo esc_attr($index); ?>][importo]"
                                               value="<?php echo esc_attr($costo['importo'] ?? ''); ?>" 
                                               step="0.01" 
                                               min="0" />
                                    </div>
                                    
                                    <div class="btr-field-group">
                                        <label>&nbsp;</label>
                                        <button type="button"
                                                class="button btr-cost-tooltip-toggle"
                                                data-target="cost-tooltip-editor-<?php echo esc_attr($index); ?>">
                                            <?php esc_html_e('Modifica Tooltip', 'born-to-ride-booking'); ?>
                                        </button>
                                    </div>
                                    
                                    <!-- Opzioni (Persone / Durata) -->
                                    <div class="btr-field-group btr-checkbox-group">
                                        <label title="Se selezionato, il costo viene moltiplicato per il numero di persone">
                                            <!-- Hidden field per garantire che il valore sia sempre inviato -->
                                            <input type="hidden" 
                                                   name="btr_costi_extra[<?php echo esc_attr($index); ?>][moltiplica_persone]" 
                                                   value="0" />
                                            <input type="checkbox" 
                                                   name="btr_costi_extra[<?php echo esc_attr($index); ?>][moltiplica_persone]" 
                                                   value="1" 
                                                   <?php checked($costo['moltiplica_persone'] ?? '0', '1'); ?>
                                                   class="btr-cost-type-checkbox" 
                                                   data-index="<?php echo esc_attr($index); ?>" />
                                            Per persona
                                        </label>
                                        <label title="Se selezionato, il costo viene moltiplicato per la durata (numero di notti)">
                                            <!-- Hidden field per garantire che il valore sia sempre inviato -->
                                            <input type="hidden" 
                                                   name="btr_costi_extra[<?php echo esc_attr($index); ?>][moltiplica_durata]" 
                                                   value="0" />
                                            <input type="checkbox" 
                                                   name="btr_costi_extra[<?php echo esc_attr($index); ?>][moltiplica_durata]" 
                                                   value="1" 
                                                   <?php checked($costo['moltiplica_durata'] ?? '0', '1'); ?>
                                                   class="btr-cost-type-checkbox" 
                                                   data-index="<?php echo esc_attr($index); ?>" />
                                            Per notte
                                        </label>
                                        <span class="btr-cost-type-indicator" id="cost-type-<?php echo esc_attr($index); ?>" style="margin-left: 10px; font-style: italic; color: #666;">
                                            <?php 
                                            if (($costo['moltiplica_persone'] ?? '0') == '1' && ($costo['moltiplica_durata'] ?? '0') == '1') {
                                                echo '= Per persona per notte';
                                            } elseif (($costo['moltiplica_persone'] ?? '0') == '1') {
                                                echo '= Per persona (totale soggiorno)';
                                            } elseif (($costo['moltiplica_durata'] ?? '0') == '1') {
                                                echo '= Per soggiorno (tutte le notti)';
                                            } else {
                                                echo '= Costo fisso';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="btr-switch-container">
                                    <label for="btr_costi_extra_<?php echo esc_attr($index); ?>_attivo">Attivo</label>
                                    <input type="checkbox"
                                           id="btr_costi_extra_<?php echo esc_attr($index); ?>_attivo"
                                           name="btr_costi_extra[<?php echo esc_attr($index); ?>][attivo]"
                                           value="1" 
                                           <?php checked($costo['attivo'] ?? '1', '1'); ?> />
                                    <label class="btr-switch" for="btr_costi_extra_<?php echo esc_attr($index); ?>_attivo">
                                        <span class="btr-switch-handle">
                                            <svg class="btr-switch-icon btr-switch-icon--on" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                            </svg>
                                            <svg class="btr-switch-icon btr-switch-icon--off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                            </svg>
                                        </span>
                                    </label>
                                </div>
                                
                                <?php if (!in_array($costo['slug'] ?? '', ['culla-per-neonati', 'no-skipass'])): ?>
                                    <button class="remove-item button">Rimuovi</button>
                                <?php else: ?>
                                    <span class="btr-locked-item" title="Questo elemento non può essere rimosso">
                                        <span class="dashicons dashicons-lock"></span>
                                        Bloccato
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div id="cost-tooltip-editor-<?php echo esc_attr($index); ?>"
                                 class="btr-cost-tooltip-content"
                                 style="display:none; clear: both; width: 100%; box-sizing: border-box; margin-top: 0.5rem;">
                                <h4>Contenuto Tooltip</h4>
                                <p class="description" style="margin-top:0.25rem; font-size:0.875rem; color:#555;">
                                    Il testo inserito qui verrà mostrato nel tooltip di questo costo extra sul frontend. 
                                    Se lasciato vuoto, il tooltip non sarà visibile.
                                </p>
                                <?php
                                wp_editor(
                                    $costo['tooltip_text'] ?? '',
                                    'btr_costo_extra_tooltip_' . esc_attr($index),
                                    array(
                                        'textarea_name' => "btr_costi_extra[{$index}][tooltip_text]",
                                        'textarea_rows' => 5,
                                        'media_buttons' => true,
                                        'teeny' => false,
                                        'quicktags' => true,
                                    )
                                );
                                ?>
                            </div>
                        </div>
                    </div> <!-- .btr-extra-cost-item -->
                    <?php
                }
            } else {
                // Primo elemento se l'array è vuoto
                ?>
                <div class="btr-extra-cost-item">
                    <div class="btr-extra-cost-card">
                        <div class="btr-extra-container">
                            <span class="dashicons dashicons-move drag-handle"></span>
                            <div class="btr-extra-cost-fields">
                                <div class="btr-field-group">
                                    <label>Nome Costo Extra</label>
                                    <input type="text" 
                                           name="btr_costi_extra[0][nome]" 
                                           value="" 
                                           placeholder="es. Noleggio attrezzatura" />
                                </div>
                                
                                <div class="btr-field-group">
                                    <label>Importo (€)</label>
                                    <input type="number" 
                                           name="btr_costi_extra[0][importo]" 
                                           value="" 
                                           step="0.01" 
                                           min="0" />
                                </div>
                                
                                <div class="btr-field-group">
                                    <label>&nbsp;</label>
                                    <button type="button"
                                            class="button btr-cost-tooltip-toggle"
                                            data-target="cost-tooltip-editor-0">
                                        <?php esc_html_e('Modifica Tooltip', 'born-to-ride-booking'); ?>
                                    </button>
                                </div>
                                
                                <div class="btr-field-group btr-checkbox-group">
                                    <label title="Se selezionato, il costo viene moltiplicato per il numero di persone">
                                        <input type="hidden" name="btr_costi_extra[0][moltiplica_persone]" value="0" />
                                        <input type="checkbox" name="btr_costi_extra[0][moltiplica_persone]" value="1" 
                                               class="btr-cost-type-checkbox" data-index="0" />
                                        Per persona
                                    </label>
                                    <label title="Se selezionato, il costo viene moltiplicato per la durata (numero di notti)">
                                        <input type="hidden" name="btr_costi_extra[0][moltiplica_durata]" value="0" />
                                        <input type="checkbox" name="btr_costi_extra[0][moltiplica_durata]" value="1" 
                                               class="btr-cost-type-checkbox" data-index="0" />
                                        Per notte
                                    </label>
                                    <span class="btr-cost-type-indicator" id="cost-type-0" style="margin-left: 10px; font-style: italic; color: #666;">
                                        = Costo fisso
                                    </span>
                                </div>
                            </div>
                            
                            <div class="btr-switch-container">
                                <label for="btr_costi_extra_0_attivo">Attivo</label>
                                <input type="checkbox" 
                                       id="btr_costi_extra_0_attivo" 
                                       name="btr_costi_extra[0][attivo]" 
                                       value="1" 
                                       checked />
                                <label class="btr-switch" for="btr_costi_extra_0_attivo">
                                    <span class="btr-switch-handle">
                                        <svg class="btr-switch-icon btr-switch-icon--on" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        <svg class="btr-switch-icon btr-switch-icon--off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                        </svg>
                                    </span>
                                </label>
                            </div>
                            
                            <button class="remove-item button">Rimuovi</button>
                        </div>
                        
                        <div id="cost-tooltip-editor-0"
                             class="btr-cost-tooltip-content"
                             style="display:none;">
                            <h4>Contenuto Tooltip</h4>
                            <p class="description" style="margin-top:0.25rem; font-size:0.875rem; color:#555;">
                                Il testo inserito qui verrà mostrato nel tooltip di questo costo extra sul frontend. 
                                Se lasciato vuoto, il tooltip non sarà visibile.
                            </p>
                            <?php
                            wp_editor(
                                '',
                                'btr_costo_extra_tooltip_0',
                                array(
                                    'textarea_name' => 'btr_costi_extra[0][tooltip_text]',
                                    'textarea_rows' => 5,
                                    'media_buttons' => true,
                                    'teeny' => false,
                                    'quicktags' => true,
                                )
                            );
                            ?>
                        </div>
                    </div>
                </div> <!-- .btr-extra-cost-item -->
                <?php
            }
            ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Remove any existing handlers to prevent duplicates
            $(document).off('click', '.btr-cost-tooltip-toggle');
            
            // Toggle tooltip with event prevention
            $(document).on('click', '.btr-cost-tooltip-toggle', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var target = $(this).data('target');
                $('#' + target).slideToggle(200);
            });
            
            // Update cost type indicator
            function updateCostTypeIndicator(index) {
                var perPersona = $('input[name="btr_costi_extra[' + index + '][moltiplica_persone]"][type="checkbox"]').is(':checked');
                var perNotte = $('input[name="btr_costi_extra[' + index + '][moltiplica_durata]"][type="checkbox"]').is(':checked');
                var indicator = $('#cost-type-' + index);
                
                if (perPersona && perNotte) {
                    indicator.text('= Per persona per notte');
                } else if (perPersona) {
                    indicator.text('= Per persona (totale soggiorno)');
                } else if (perNotte) {
                    indicator.text('= Per soggiorno (tutte le notti)');
                } else {
                    indicator.text('= Costo fisso');
                }
            }
            
            // Bind to checkbox changes
            $(document).on('change', '.btr-cost-type-checkbox', function() {
                var index = $(this).data('index');
                updateCostTypeIndicator(index);
            });
            
            // Update the JavaScript template for new items
            window.updateExtraCostTemplate = function(index) {
                // This will be called when new items are added via the "Aggiungi Costo Extra" button
                updateCostTypeIndicator(index);
            };
        });
        </script>
        
        <style>
            /* Ensure tooltip editor appears as a block below the card row */
            .btr-extra-costs-section .btr-cost-tooltip-content {
                clear: both;
                width: auto;
                box-sizing: border-box;
                margin: 0.5rem 1rem 1rem;
                padding: 15px;
                background: #f9f9f9;
                border: 1px solid #ddd;
                border-radius: 5px;
            }
        </style>
        
        <button id="add-costo-extra-item" class="button button-primary" style="margin-top: 15px;">
            <span class="dashicons dashicons-plus-alt" style="vertical-align: middle;"></span>
            Aggiungi Costo Extra
        </button>
    </div>
</div>