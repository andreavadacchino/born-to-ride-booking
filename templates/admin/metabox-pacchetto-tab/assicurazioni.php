<?php
// Inserimento slot di default "Assicurazione RC Skipass" se non presente
if (!isset($btr_assicurazione_importi) || !is_array($btr_assicurazione_importi)) {
    $btr_assicurazione_importi = [];
}
$has_rc_skipass = false;
foreach ($btr_assicurazione_importi as $assicurazione) {
    if (isset($assicurazione['slug']) && $assicurazione['slug'] === 'rc-skipass') {
        $has_rc_skipass = true;
        break;
    }
}
if (!$has_rc_skipass) {
    $rc_skipass_assicurazione = [
        'descrizione' => 'Assicurazione RC Skipass',
        'slug' => 'rc-skipass',
        'importo' => '3.5', // Percentuale default
        'assicurazione_view_prezzo' => '1',
        'tooltip_text' => 'Assicurazione di responsabilità civile per danni causati durante l\'utilizzo degli impianti sciistici.',
    ];
    array_unshift($btr_assicurazione_importi, $rc_skipass_assicurazione);
}
?>
<div class="btr-section collapsible active btr-extra-costs-section">
    <h2>Assicurazione Annullamento </h2>

    <!-- Sezione Assicurazione Annullamento -->
    <div class="btr-info-box" style="border-left: 4px solid #0073aa; background: #f0f8ff; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
        <p style="margin: 0 0 5px 0;"><strong>Come funzionano le assicurazioni?</strong></p>
        <ul style="margin: 0; padding-left: 20px;">
            <li>Le assicurazioni sono gestite come combinazioni individuali (es. annullamento, medico-bagaglio) da associare ai pacchetti viaggio.</li>
            <li>Per poter attivare l'assicurazione lato frontend, è necessario prima <strong>creare almeno una combinazione</strong> qui sotto.</li>
            <li>Dopo aver salvato il pacchetto, sarà possibile attivare l'assicurazione nel frontend per i singoli partecipanti.</li>
            <li><strong>Nota:</strong> L'Assicurazione RC Skipass è obbligatoria e non può essere rimossa, ma può essere disattivata.</li>
        </ul>
    </div>

    <style>
        .btr-extra-costs-container {
            margin-bottom: 2em;
        }
    .btr-assicurazione-item.btr-extra-cost-card {
        display: flex;
        flex-direction: row;
        flex-wrap: wrap;
        align-items: center;
    }
    .btr-assicurazione-item.btr-extra-cost-card .btr-container-ass {
        display: flex;
        width: 100%;
        align-items: center;
    }

    /* Modern button styles */
    .btr-extra-costs-section .button {
        background: #f5f5f5;
        border: 1px solid #ccc;
        border-radius: 4px;
        padding: 0.33rem 1rem;
        color: #333;
        font-weight: 500;
        cursor: pointer;
        transition: background 0.2s ease, border-color 0.2s ease;
    }
    .btr-extra-costs-section .button:hover,
    .btr-extra-costs-section .button:focus {
        background: #e5e5e5;
        border-color: #bbb;
    }

    /* Remove-item icon button */
    .btr-assicurazione-item .remove-item {
        background: none;
        border: none;
        padding: 0.25rem;
        margin-left: 1rem;
        color: #d23669;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: color 0.2s ease;
        margin-top: 0.25rem;
    }
    .btr-assicurazione-item .remove-item svg {
        width: 1.2rem;
        height: 1.2rem;
        stroke: currentColor;
    }
    .btr-assicurazione-item .remove-item:hover,
    .btr-assicurazione-item .remove-item:focus {
        color: #a12756;
    }
    .btr-assicurazione-item .remove-item:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    </style>
    <div class="section-content">
        <div id="btr-assicurazione-importi" class="btr-extra-costs-container">
            <?php
            if (!empty($btr_assicurazione_importi) && is_array($btr_assicurazione_importi)) {
                foreach ($btr_assicurazione_importi as $index => $item) {
                    $is_rc_skipass = (isset($item['slug']) && $item['slug'] === 'rc-skipass');
                    // Genera slug se non esiste
                    $slug = $item['slug'] ?? '';
                    if (empty($slug) && !$is_rc_skipass) {
                        $slug = sanitize_title($item['descrizione'] ?? 'assicurazione') . '-' . substr(md5($item['descrizione'] . $index . time()), 0, 8);
                    }
                    ?>
                    <div class="btr-assicurazione-item btr-extra-cost-card">
                        <div class="btr-container-ass">
                        <span class="dashicons dashicons-move drag-handle"></span>
                        <div class="btr-extra-cost-fields">
                            <!-- Nome Costo Extra -->
                            <div class="btr-field-group desc">
                                <label>Descrizione Combinazione</label>
                                <input type="text" name="btr_assicurazione_importi[<?php echo esc_attr($index); ?>][descrizione]"
                                       value="<?php echo esc_attr($item['descrizione']); ?>" <?php if ($is_rc_skipass) echo 'readonly style="background:#f0f0f0;"'; ?>/>
                                <!-- Campo slug nascosto -->
                                <input type="hidden" name="btr_assicurazione_importi[<?php echo esc_attr($index); ?>][slug]" 
                                       value="<?php echo esc_attr($slug); ?>"/>
                            </div>
                            <!-- Tipo Importo -->
                            <div class="btr-field-group">
                                <label>Tipo Importo</label>
                                <select name="btr_assicurazione_importi[<?php echo esc_attr($index); ?>][tipo_importo]" 
                                        class="btr-tipo-importo-select" 
                                        data-index="<?php echo esc_attr($index); ?>">
                                    <option value="percentuale" <?php selected($item['tipo_importo'] ?? 'percentuale', 'percentuale'); ?>>Percentuale (%)</option>
                                    <option value="fisso" <?php selected($item['tipo_importo'] ?? 'percentuale', 'fisso'); ?>>Importo Fisso (€)</option>
                                </select>
                            </div>
                            <!-- Importo -->
                            <div class="btr-field-group">
                                <label class="btr-importo-label" data-index="<?php echo esc_attr($index); ?>">
                                    <?php if (($item['tipo_importo'] ?? 'percentuale') === 'fisso'): ?>
                                        Importo (€) <?= info_desc('L\'assicurazione avrà un costo fisso per persona.'); ?>
                                    <?php else: ?>
                                        Percentuale (%) <?= info_desc('L\'assicurazione verrà calcolata in base alla percentuale inserita sul costo totale a persona, comprensivo di prezzo del pacchetto, supplementi, notti extra ed eventuali costi extra.'); ?>
                                    <?php endif; ?>
                                </label>
                                <input type="number" name="btr_assicurazione_importi[<?php echo esc_attr($index); ?>][importo]" value="<?php echo esc_attr($item['importo']); ?>"
                                       step="0.01" min="0"/>
                            </div>
                            <div class="btr-field-group">
                                <label>&nbsp;</label>
                                <button type="button"
                                        class="button btr-tooltip-accordion-toggle"
                                        data-target="tooltip-editor-<?php echo esc_attr($index); ?>">
                                    <?php esc_html_e('Modifica Tooltip', 'born-to-ride-booking'); ?>
                                </button>
                            </div>
                            <div class="btr-switch-container ">
                                <label for="btr_assicurazione_importi<?php echo esc_attr($index); ?>_assicurazione_view_prezzo">Attiva</label>
                                <input type="checkbox"
                                       name="btr_assicurazione_importi[<?php echo esc_attr($index); ?>][assicurazione_view_prezzo]"
                                       id="btr_assicurazione_importi<?php echo esc_attr($index); ?>_assicurazione_view_prezzo"
                                       value="1"
                                    <?php checked($item['assicurazione_view_prezzo'] ?? '', '1'); ?>/>
                                <label class="btr-switch" for="btr_assicurazione_importi<?php echo esc_attr($index); ?>_assicurazione_view_prezzo">
        <span class="btr-switch-handle">
            <svg class="btr-switch-icon btr-switch-icon--on" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                      clip-rule="evenodd"/>
            </svg>
            <svg class="btr-switch-icon btr-switch-icon--off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd"
                      d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                      clip-rule="evenodd"/>
            </svg>
        </span>
                                </label>
                            </div>
                        </div>
                        <button class="remove-item button" type="button" aria-label="<?php esc_attr_e('Rimuovi combinazione', 'born-to-ride-booking'); ?>" <?php if ($is_rc_skipass) echo 'disabled'; ?>>
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                <line x1="10" y1="11" x2="10" y2="17"></line>
                                <line x1="14" y1="11" x2="14" y2="17"></line>
                            </svg>
                        </button>

                        </div>
                        <div id="tooltip-editor-<?php echo esc_attr($index); ?>"
                             class="btr-tooltip-accordion-content"
                             style="display:none; clear: both; width: 100%; box-sizing: border-box; margin-top: 0.5rem;">
                            <h2>Contenuto Tooltip</h2>
                            <p class="description" style="margin-top:0.25rem; font-size:0.875rem; color:#555;">
                              Il testo inserito qui verrà visualizzato nel tooltip dell'assicurazione sul frontend. Se lasciato vuoto, non verrà mostrata alcuna informazione.
                            </p>
                            <?php
                            wp_editor(
                                $item['tooltip_text'] ?? '',
                                'btr_assicurazione_tooltip_' . esc_attr($index),
                                array(
                                    'textarea_name' => "btr_assicurazione_importi[{$index}][tooltip_text]",
                                    'textarea_rows' => 5,
                                    'media_buttons' => true,
                                    'teeny' => false,
                                    'quicktags' => true,
                                )
                            );
                            ?>
                        </div>
                    </div>
                    <?php
                }
            } else {
                // Primo elemento se l'array è vuoto (in questo caso avrà RC Skipass)
                ?>
                <div class="btr-assicurazione-item btr-extra-cost-card">
                    <span class="dashicons dashicons-move drag-handle"></span>
                    <div class="btr-extra-cost-fields">
                        <div class="btr-field-group desc">
                            <label>Descrizione Combinazione</label>
                            <input type="text" name="btr_assicurazione_importi[0][descrizione]" value=""/>
                            <input type="hidden" name="btr_assicurazione_importi[0][slug]" value=""/>
                        </div>
                        <div class="btr-field-group">
                            <label>Tipo Importo</label>
                            <select name="btr_assicurazione_importi[0][tipo_importo]" 
                                    class="btr-tipo-importo-select" 
                                    data-index="0">
                                <option value="percentuale" selected>Percentuale (%)</option>
                                <option value="fisso">Importo Fisso (€)</option>
                            </select>
                        </div>
                        <div class="btr-field-group">
                            <label class="btr-importo-label" data-index="0">
                                Percentuale (%) <?= info_desc('L\'assicurazione verrà calcolata in base alla percentuale inserita sul costo totale a persona, comprensivo di prezzo del pacchetto, supplementi, notti extra ed eventuali costi extra.'); ?>
                            </label>
                            <input type="number" name="btr_assicurazione_importi[0][importo]" value="" step="0.01" min="0"/>
                        </div>
                        <div class="btr-field-group">
                            <label>&nbsp;</label>
                            <button type="button"
                                    class="button btr-tooltip-accordion-toggle"
                                    data-target="tooltip-editor-0">
                                <?php esc_html_e('Modifica Tooltip', 'born-to-ride-booking'); ?>
                            </button>
                        </div>
                        <div class="btr-field-group btr-checkbox-group">
                            <label>Visualizza solo</label>
                            <span>
                                <label>
                                    <input type="checkbox" name="btr_assicurazione_importi[0][assicurazione_view_prezzo]" value="1"/> Prezzo €
                                </label>
                                <label>
                                    <input type="checkbox" name="btr_assicurazione_importi[0][assicurazione_view_percentuale]" value="1"/> Percentuale %
                                </label>
                            </span>
                        </div>
                    </div>
                    <button class="remove-item button" type="button" aria-label="<?php esc_attr_e('Rimuovi combinazione', 'born-to-ride-booking'); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            <line x1="10" y1="11" x2="10" y2="17"></line>
                            <line x1="14" y1="11" x2="14" y2="17"></line>
                        </svg>
                    </button>
                    <div id="tooltip-editor-0"
                         class="btr-tooltip-accordion-content"
                         style="display:none; clear: both; width: 100%; box-sizing: border-box; margin-top: 0.5rem;">
                        <h2>Contenuto Tooltip</h2>
                        <p class="description" style="margin-top:0.25rem; font-size:0.875rem; color:#555;">
                          Il testo inserito qui verrà visualizzato nel tooltip dell'assicurazione sul frontend. Se lasciato vuoto, non verrà mostrata alcuna informazione.
                        </p>
                        <?php
                        wp_editor(
                            '',
                            'btr_assicurazione_tooltip_0',
                            array(
                                'textarea_name' => "btr_assicurazione_importi[0][tooltip_text]",
                                'textarea_rows' => 5,
                                'media_buttons' => true,
                                'teeny' => false,
                                'quicktags' => true,
                            )
                        );
                        ?>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
        <button id="add-assicurazione-item" class="button">Aggiungi Importo Assicurazione</button>
    </div>
</div>
<script>
jQuery(document).ready(function($){
    // Toggle tooltip editor
    $('.btr-tooltip-accordion-toggle').on('click', function(){
        var target = $(this).data('target');
        $('#' + target).slideToggle(200);
    });
    
    // Gestione cambio tipo importo
    function updateImportoLabel(selectElement) {
        var $select = $(selectElement);
        var index = $select.data('index');
        var tipoImporto = $select.val();
        var $label = $('.btr-importo-label[data-index="' + index + '"]');
        
        if (tipoImporto === 'fisso') {
            $label.html('Importo (€) <?= info_desc('L\\\'assicurazione avrà un costo fisso per persona.'); ?>');
        } else {
            $label.html('Percentuale (%) <?= info_desc('L\\\'assicurazione verrà calcolata in base alla percentuale inserita sul costo totale a persona, comprensivo di prezzo del pacchetto, supplementi, notti extra ed eventuali costi extra.'); ?>');
        }
    }
    
    // Gestisce il cambio del tipo importo per elementi esistenti
    $(document).on('change', '.btr-tipo-importo-select', function() {
        updateImportoLabel(this);
    });
    
    // Prevent removal of RC Skipass
    $(document).on('click', '.remove-item:disabled', function(e) {
        e.preventDefault();
        alert('L\'Assicurazione RC Skipass non può essere rimossa.');
        return false;
    });
    
    // Add new assicurazione item functionality
    $('#add-assicurazione-item').on('click', function() {
        var container = $('#btr-assicurazione-importi');
        var index = container.find('.btr-assicurazione-item').length;
        var uniqueSlug = 'assicurazione-' + Math.random().toString(36).substr(2, 8);
        
        var template = `
        <div class="btr-assicurazione-item btr-extra-cost-card">
            <div class="btr-container-ass">
                <span class="dashicons dashicons-move drag-handle"></span>
                <div class="btr-extra-cost-fields">
                    <div class="btr-field-group desc">
                        <label>Descrizione Combinazione</label>
                        <input type="text" name="btr_assicurazione_importi[${index}][descrizione]" value=""/>
                        <input type="hidden" name="btr_assicurazione_importi[${index}][slug]" value="${uniqueSlug}"/>
                    </div>
                    <div class="btr-field-group">
                        <label>Tipo Importo</label>
                        <select name="btr_assicurazione_importi[${index}][tipo_importo]" 
                                class="btr-tipo-importo-select" 
                                data-index="${index}">
                            <option value="percentuale" selected>Percentuale (%)</option>
                            <option value="fisso">Importo Fisso (€)</option>
                        </select>
                    </div>
                    <div class="btr-field-group">
                        <label class="btr-importo-label" data-index="${index}">
                            Percentuale (%)
                        </label>
                        <input type="number" name="btr_assicurazione_importi[${index}][importo]" value="" step="0.01" min="0"/>
                    </div>
                    <div class="btr-field-group">
                        <label>&nbsp;</label>
                        <button type="button" class="button btr-tooltip-accordion-toggle" data-target="tooltip-editor-${index}">
                            Modifica Tooltip
                        </button>
                    </div>
                    <div class="btr-switch-container">
                        <label for="btr_assicurazione_importi${index}_assicurazione_view_prezzo">Attiva</label>
                        <input type="checkbox" id="btr_assicurazione_importi${index}_assicurazione_view_prezzo" 
                               name="btr_assicurazione_importi[${index}][assicurazione_view_prezzo]" value="1" checked />
                        <label class="btr-switch" for="btr_assicurazione_importi${index}_assicurazione_view_prezzo">
                            <span class="btr-switch-handle">
                                <svg class="btr-switch-icon btr-switch-icon--on" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <svg class="btr-switch-icon btr-switch-icon--off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </span>
                        </label>
                    </div>
                </div>
                <button class="remove-item button" type="button" aria-label="Rimuovi combinazione">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        <line x1="10" y1="11" x2="10" y2="17"></line>
                        <line x1="14" y1="11" x2="14" y2="17"></line>
                    </svg>
                </button>
            </div>
            <div id="tooltip-editor-${index}" class="btr-tooltip-accordion-content" style="display:none; clear: both; width: 100%; margin-top: 0.5rem;">
                <h2>Contenuto Tooltip</h2>
                <textarea name="btr_assicurazione_importi[${index}][tooltip_text]" rows="3"></textarea>
            </div>
        </div>`;
        
        container.append(template);
    });
    
    // Remove item functionality
    $(document).on('click', '.remove-item:not(:disabled)', function() {
        $(this).closest('.btr-assicurazione-item').fadeOut(200, function() {
            $(this).remove();
        });
    });
});
</script>