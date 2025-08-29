/**
 * Script per gestire dinamicamente i costi extra con i campi hidden per i checkbox
 * File: admin-extra-costs-dynamic.js
 */
jQuery(document).ready(function($) {
    
    // Rimuovi qualsiasi handler esistente per evitare conflitti
    $(document).off('click', '#add-costo-extra-item');
    
    // Gestisce il click del bottone per aggiungere nuovi costi extra
    $(document).on('click', '#add-costo-extra-item', function(e) {
        e.preventDefault();
        
        // Trova l'indice più alto esistente
        let maxIndex = -1;
        $('#btr-costi-extra .btr-extra-cost-item').each(function() {
            const nameAttr = $(this).find('input[type="text"]').first().attr('name');
            if (nameAttr) {
                const match = nameAttr.match(/\[(\d+)\]/);
                if (match) {
                    const currentIndex = parseInt(match[1]);
                    if (currentIndex > maxIndex) {
                        maxIndex = currentIndex;
                    }
                }
            }
        });
        
        const newIndex = maxIndex + 1;
        
        // Template per il nuovo costo extra con campi hidden
        const newItem = `
        <div class="btr-extra-cost-item">
            <div class="btr-extra-cost-card">
                <div class="btr-extra-container">
                    <span class="dashicons dashicons-move drag-handle"></span>
                    <div class="btr-extra-cost-fields">
                        <div class="btr-field-group">
                            <label>Nome Costo Extra</label>
                            <input type="text" 
                                   name="btr_costi_extra[${newIndex}][nome]" 
                                   value="" 
                                   placeholder="es. Noleggio attrezzatura" />
                        </div>
                        
                        <div class="btr-field-group">
                            <label>Importo (€)</label>
                            <input type="number" 
                                   name="btr_costi_extra[${newIndex}][importo]" 
                                   value="" 
                                   step="0.01" 
                                   min="0" />
                        </div>
                        
                        <div class="btr-field-group">
                            <label>&nbsp;</label>
                            <button type="button"
                                    class="button btr-cost-tooltip-toggle"
                                    data-target="cost-tooltip-editor-${newIndex}">
                                Modifica Tooltip
                            </button>
                        </div>
                        
                        <div class="btr-field-group btr-checkbox-group">
                            <label title="Se selezionato, il costo viene moltiplicato per il numero di persone">
                                <input type="hidden" name="btr_costi_extra[${newIndex}][moltiplica_persone]" value="0" />
                                <input type="checkbox" name="btr_costi_extra[${newIndex}][moltiplica_persone]" value="1" 
                                       class="btr-cost-type-checkbox" data-index="${newIndex}" />
                                Per persona
                            </label>
                            <label title="Se selezionato, il costo viene moltiplicato per la durata (numero di notti)">
                                <input type="hidden" name="btr_costi_extra[${newIndex}][moltiplica_durata]" value="0" />
                                <input type="checkbox" name="btr_costi_extra[${newIndex}][moltiplica_durata]" value="1" 
                                       class="btr-cost-type-checkbox" data-index="${newIndex}" />
                                Per notte
                            </label>
                            <span class="btr-cost-type-indicator" id="cost-type-${newIndex}" style="margin-left: 10px; font-style: italic; color: #666;">
                                = Costo fisso
                            </span>
                        </div>
                    </div>
                    
                    <div class="btr-switch-container">
                        <label for="btr_costi_extra_${newIndex}_attivo">Attivo</label>
                        <input type="checkbox"
                               id="btr_costi_extra_${newIndex}_attivo"
                               name="btr_costi_extra[${newIndex}][attivo]"
                               value="1" 
                               checked />
                        <label class="btr-switch" for="btr_costi_extra_${newIndex}_attivo">
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
                
                <div id="cost-tooltip-editor-${newIndex}"
                     class="btr-cost-tooltip-content"
                     style="display:none; clear: both; width: 100%; box-sizing: border-box; margin-top: 0.5rem;">
                    <h4>Contenuto Tooltip</h4>
                    <p class="description" style="margin-top:0.25rem; font-size:0.875rem; color:#555;">
                        Il testo inserito qui verrà mostrato nel tooltip di questo costo extra sul frontend. 
                        Se lasciato vuoto, il tooltip non sarà visibile.
                    </p>
                    <textarea name="btr_costi_extra[${newIndex}][tooltip_text]" rows="5" style="width: 100%;"></textarea>
                </div>
            </div>
        </div>`;
        
        // Aggiungi il nuovo elemento
        $('#btr-costi-extra').append(newItem);
        
        // Re-inizializza il sortable se disponibile
        if ($.fn.sortable) {
            $('#btr-costi-extra').sortable('refresh');
        }
    });
    
    // Gestisce la rimozione degli elementi
    $(document).on('click', '#btr-costi-extra .remove-item', function(e) {
        e.preventDefault();
        $(this).closest('.btr-extra-cost-item').remove();
    });
    
    // Inizializza il sortable per il drag & drop
    if ($.fn.sortable) {
        $('#btr-costi-extra').sortable({
            handle: '.drag-handle',
            items: '.btr-extra-cost-item',
            update: function(event, ui) {
                // Riordina gli indici dopo il drag & drop
                $('#btr-costi-extra .btr-extra-cost-item').each(function(index) {
                    $(this).find('input, textarea, select').each(function() {
                        const name = $(this).attr('name');
                        if (name) {
                            const newName = name.replace(/\[\d+\]/, '[' + index + ']');
                            $(this).attr('name', newName);
                        }
                        
                        // Aggiorna anche gli ID e i for delle label
                        const id = $(this).attr('id');
                        if (id) {
                            const newId = id.replace(/_\d+_/, '_' + index + '_');
                            $(this).attr('id', newId);
                        }
                    });
                    
                    // Aggiorna i data-target dei bottoni tooltip
                    $(this).find('.btr-cost-tooltip-toggle').each(function() {
                        const target = $(this).data('target');
                        if (target) {
                            const newTarget = target.replace(/-\d+$/, '-' + index);
                            $(this).data('target', newTarget);
                            $(this).attr('data-target', newTarget);
                        }
                    });
                    
                    // Aggiorna gli ID dei div tooltip
                    $(this).find('.btr-cost-tooltip-content').each(function() {
                        const id = $(this).attr('id');
                        if (id) {
                            const newId = id.replace(/-\d+$/, '-' + index);
                            $(this).attr('id', newId);
                        }
                    });
                    
                    // Aggiorna i for delle label degli switch
                    $(this).find('label[for]').each(function() {
                        const forAttr = $(this).attr('for');
                        if (forAttr) {
                            const newFor = forAttr.replace(/_\d+_/, '_' + index + '_');
                            $(this).attr('for', newFor);
                        }
                    });
                    
                    // Aggiorna i data-index dei checkbox
                    $(this).find('.btr-cost-type-checkbox').each(function() {
                        $(this).attr('data-index', index);
                    });
                    
                    // Aggiorna gli ID degli indicatori
                    $(this).find('.btr-cost-type-indicator').each(function() {
                        $(this).attr('id', 'cost-type-' + index);
                    });
                });
            }
        });
    }
});