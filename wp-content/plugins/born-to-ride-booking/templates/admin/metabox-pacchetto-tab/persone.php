<?php
// Calcolo prezzi camera e per persona in base alla modalità di prenotazione
$modalita = get_post_meta($post->ID, 'btr_tipologia_prenotazione', true);
$camera_base_price = 0;
$camera_price_per_person = 0;
switch ($modalita) {
    case 'per_tipologia_camere':
        $price_singola = floatval(get_post_meta($post->ID, 'btr_camere_singola_prezzo', true));
        $price_doppia = floatval(get_post_meta($post->ID, 'btr_camere_doppia_prezzo', true));
        $is_singola_enabled = get_post_meta($post->ID, 'btr_camere_singola_enabled', true);
        $camera_base_price = $is_singola_enabled ? $price_singola : ($price_doppia > 0 ? $price_doppia : 0);
        $camera_price_per_person = $is_singola_enabled ? $camera_base_price : ($camera_base_price / 2);
        break;
    case 'per_numero_persone':
        $prezzo_totale = floatval(get_post_meta($post->ID, 'btr_camere_numero_persone_prezzo', true));
        $num_persone = intval(get_post_meta($post->ID, 'btr_camere_numero_persone_qta', true));
        $camera_base_price = $prezzo_totale;
        $camera_price_per_person = ($num_persone > 0) ? ($prezzo_totale / $num_persone) : 0;
        break;
    case 'allotment_camere':
        $allotment = get_post_meta($post->ID, 'btr_camere_allotment', true);
        $btr_date_ranges = get_post_meta($post->ID, 'btr_date_ranges', true);
        $oggi = date('Y-m-d');
        if (!empty($btr_date_ranges)) {
            foreach ($btr_date_ranges as $range) {
                $key = $range['start'];
                if (isset($allotment[$key])) {
                    $camere = $allotment[$key];
                    foreach ($camere as $tipo => $dati) {
                        if (!empty($dati['prezzo'])) {
                            $camera_base_price = floatval($dati['prezzo']);
                            $posti = 1;
                            if ($tipo === 'doppia') $posti = 2;
                            if ($tipo === 'tripla') $posti = 3;
                            if ($tipo === 'quadrupla') $posti = 4;
                            if ($tipo === 'quintupla') $posti = 5;
                            if ($tipo === 'condivisa') $posti = 6;
                            $camera_price_per_person = $camera_base_price / $posti;
                            break 2;
                        }
                    }
                }
            }
        }
        break;
}
?>

<style>
    .btr-child-price-preview {
        background: #f8f9fa;
        border-left: 4px solid #0073aa;
        padding: 15px 20px;
        margin-top: 15px;
        border-radius: 6px;
        font-size: 14px;
    }
    .btr-child-price-preview p {
        margin: 6px 0;
    }
    .btr-child-price-preview strong {
        display: inline-block;
        min-width: 190px;
        color: #23282d;
    }
    .btr-child-price-preview span {
        font-weight: 600;
        color: #0073aa;
    }

    .row-children .row-column-children {
        display: flex  ;
        flex-direction: row;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-start;
        flex: 1 0 0;
        width: auto;
        gap: 0 10px;
    }
    .row-children .row-column-children select {
        width: 100px;
        min-width: 55px;
    }
</style>

<div class="btr-section collapsible">
    <h2><?php _e('Gestione infanti e fasce bambini', 'born-to-ride-booking'); ?></h2>
    <div class="section-content">


        <div class="btr-field-group btr-room-row row-children">
            <label for="btr_infanti_enabled" class="label_btr_infanti_enabled m-0">
                <strong><?php _e('Abilita gestione infanti (0-3 anni)', 'born-to-ride-booking'); ?></strong>
                <small class="btr-field-description"><?php _e('Gli infanti non pagano e non occupano posti.', 'born-to-ride-booking'); ?></small>
            </label>
            <div class="btr-switch-container">
                <label for="btr_infanti_enabled" class="label_btr_infanti_enabled">Abilita</label>
                <input type="checkbox" id="btr_infanti_enabled" name="btr_infanti_enabled"
                       value="on" <?php checked(get_post_meta($post->ID, 'btr_infanti_enabled', true), '1'); ?> />
                <label class="btr-switch" for="btr_infanti_enabled">
                     <span class="btr-switch-handle">
                        <svg class="btr-switch-icon btr-switch-icon--on" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                  d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                  clip-rule="evenodd"></path>
                        </svg>
                        <svg class="btr-switch-icon btr-switch-icon--off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                  d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                  clip-rule="evenodd"></path>
                        </svg>
                    </span>
                </label>
            </div>
        </div>



        <?php for ($fascia = 1; $fascia <= 4; $fascia++):
            $meta_label = "btr_bambini_fascia{$fascia}_label";
            $meta_sconto = "btr_bambini_fascia{$fascia}_sconto";
            $meta_enabled = "btr_bambini_fascia{$fascia}_sconto_enabled";

            $label_val = esc_attr(get_post_meta($post->ID, $meta_label, true));
            $sconto = esc_attr(get_post_meta($post->ID, $meta_sconto, true));
            $enabled = get_post_meta($post->ID, $meta_enabled, true);
            $output_id = "btr_bambini_fascia{$fascia}_discounted_price";
            $price_box_id = "btr_bambini_fascia{$fascia}_price_box";
        ?>
            <div class="btr-field-group btr-room-row row-children">
                <label for="<?php echo $meta_label; ?>" class="m-0 row-column-children">
                    <div class="column-child">
                    <label for="<?php echo $meta_label; ?>"> <strong><?php echo !empty($label_val) ? esc_html($label_val) : "Etichetta Fascia $fascia"; ?></strong></label>
                    <input type="text" id="<?php echo $meta_label; ?>" name="<?php echo $meta_label; ?>" value="<?php echo $label_val; ?>" placeholder="Ed: 3-12 anni"/>

                    </div>
                    <div class="column-child">
                    <label for="btr_bambini_fascia<?php echo $fascia; ?>_eta_min">Età Min</label>
                    <select id="btr_bambini_fascia<?php echo $fascia; ?>_eta_min" name="btr_bambini_fascia<?php echo $fascia; ?>_eta_min">
                        <?php for ($e = 0; $e <= 17; $e++): ?>
                            <option value="<?php echo $e; ?>" <?php selected(get_post_meta($post->ID, "btr_bambini_fascia{$fascia}_eta_min", true), $e); ?>>
                                <?php echo $e; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    </div>
                    <div class="column-child">
                    <label for="btr_bambini_fascia<?php echo $fascia; ?>_eta_max">Età Max</label>
                    <select id="btr_bambini_fascia<?php echo $fascia; ?>_eta_max" name="btr_bambini_fascia<?php echo $fascia; ?>_eta_max">
                        <?php for ($e = 0; $e <= 17; $e++): ?>
                            <option value="<?php echo $e; ?>" <?php selected(get_post_meta($post->ID, "btr_bambini_fascia{$fascia}_eta_max", true), $e); ?>>
                                <?php echo $e; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    </div>
                    <small class="btr-field-description"><?php _e('Personalizza la descrizione e lo sconto da applicare per questa fascia d\'età.', 'born-to-ride-booking'); ?></small>
                </label>

                <div class="btr-children-discount">

                    <label for="<?php echo $meta_sconto; ?>">Sconto (%)</label>
                    <input type="number" id="<?php echo $meta_sconto; ?>" name="<?php echo $meta_sconto; ?>"
                           value="<?php echo $sconto; ?>" step="0.05" min="0" max="100"/>
                </div>

                <div class="btr-switch-container">
                    <label for="<?php echo $meta_enabled; ?>" class="label_<?php echo $meta_enabled; ?>">Abilita</label>
                    <input type="checkbox" id="<?php echo $meta_enabled; ?>" name="<?php echo $meta_enabled; ?>" value="on"
                        <?php checked($enabled, '1'); ?> />
                    <label class="btr-switch" for="<?php echo $meta_enabled; ?>">
                         <span class="btr-switch-handle">
                            <svg class="btr-switch-icon btr-switch-icon--on" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                      d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                      clip-rule="evenodd"></path>
                            </svg>
                            <svg class="btr-switch-icon btr-switch-icon--off" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd"
                                      d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                      clip-rule="evenodd"></path>
                            </svg>
                        </span>
                    </label>

                    <div class="btr-child-price-preview" id="<?php echo $price_box_id; ?>">
                        <p><strong>Prezzo camera attiva:</strong> <span><?php echo number_format($camera_base_price, 2); ?> €</span></p>
                        <p><strong>Prezzo per persona:</strong> <span><?php echo number_format($camera_price_per_person, 2); ?> €</span></p>
                        <p><strong>Prezzo scontato per bambino:</strong> <span id="<?php echo $output_id; ?>">--</span> €</span></p>
                    </div>
                </div>
            </div>
        <?php endfor; ?>

        <!-- Sezione Configurazione Globale Prezzi per Bambini -->
        <div class="btr-field-group" style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e0e0e0;">
            <h3 style="margin-bottom: 20px; color: #0073aa;">Configurazione Globale Prezzi per Bambini</h3>
            <p style="margin-bottom: 15px; color: #666; font-style: italic;">
                Configura i prezzi globali per bambini che verranno utilizzati per tutte le tipologie di camere. 
                Questi prezzi si applicano solo alle notti extra nell'allotment.
            </p>


            
            <?php
            // Ottieni le fasce di età bambini
            $child_categories = [];
            if (class_exists('BTR_Dynamic_Child_Categories')) {
                $dynamic_categories = new BTR_Dynamic_Child_Categories();
                $child_categories = $dynamic_categories->get_categories(true);
            } else {
                // Fallback a categorie predefinite
                $child_categories = [
                    ['id' => 'f1', 'label' => 'Bambini 3-8 anni'],
                    ['id' => 'f2', 'label' => 'Bambini 8-12 anni'],
                    ['id' => 'f3', 'label' => 'Bambini 12-14 anni'],
                    ['id' => 'f4', 'label' => 'Bambini 14-15 anni']
                ];
            }
            
            foreach ($child_categories as $category):
                $global_field_name = "btr_global_child_pricing_{$category['id']}";
                $global_enabled_field = "btr_global_child_pricing_{$category['id']}_enabled";
                
                $global_enabled = get_post_meta($post->ID, $global_enabled_field, true);
                $global_price = get_post_meta($post->ID, $global_field_name, true);
            ?>
            <div class="btr-field-group btr-room-row row-children" style="background: #f9f9f9; padding: 15px; border-radius: 6px; margin-bottom: 10px;">
                <div class="row-column-children" style="flex: 2;">
                    <label for="<?php echo esc_attr($global_enabled_field); ?>" style="font-weight: 600; color: #333;">
                        <?php echo esc_html($category['label']); ?>
                    </label>
                </div>
                
                <div class="btr-switch-container" style="flex: 0 0 80px;">
                    <input type="checkbox" id="<?php echo esc_attr($global_enabled_field); ?>" 
                           name="<?php echo esc_attr($global_enabled_field); ?>" value="1" 
                           <?php checked($global_enabled, '1'); ?> />
                    <label class="btr-switch" for="<?php echo esc_attr($global_enabled_field); ?>">
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
                
                <div style="flex: 1; display: flex; align-items: center; gap: 10px;">
                    <input type="number" name="<?php echo esc_attr($global_field_name); ?>" 
                           value="<?php echo esc_attr($global_price); ?>" step="0.01" min="0" 
                           placeholder="Prezzo" style="width: 100px;" />
                    <small style="color: #666;">€ per bambino</small>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Sezione Visualizzazione Slot Bambini Attivi -->
        <div class="btr-field-group" style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #e0e0e0;">
            <h3 style="margin-bottom: 20px; color: #0073aa;">Slot Bambini Attivi</h3>
            <p style="margin-bottom: 15px; color: #666; font-style: italic;">
                Di seguito vengono mostrati solo gli slot per bambini che sono attualmente abilitati:
            </p>

            <div id="btr-active-children-slots" style="background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid #28a745;">
                <?php
                $active_slots = [];
                $has_active_slots = false;
                
                // Controlla fasce bambini abilitate con sconto
                for ($fascia = 1; $fascia <= 4; $fascia++) {
                    $meta_enabled = "btr_bambini_fascia{$fascia}_sconto_enabled";
                    $meta_label = "btr_bambini_fascia{$fascia}_label";
                    $meta_sconto = "btr_bambini_fascia{$fascia}_sconto";
                    $meta_eta_min = "btr_bambini_fascia{$fascia}_eta_min";
                    $meta_eta_max = "btr_bambini_fascia{$fascia}_eta_max";
                    
                    $enabled = get_post_meta($post->ID, $meta_enabled, true);
                    if ($enabled === '1') {
                        $label = get_post_meta($post->ID, $meta_label, true);
                        $sconto = get_post_meta($post->ID, $meta_sconto, true);
                        $eta_min = get_post_meta($post->ID, $meta_eta_min, true);
                        $eta_max = get_post_meta($post->ID, $meta_eta_max, true);
                        
                        // Usa l'etichetta personalizzata o genera una basata sull'età
                        if (empty($label) && !empty($eta_min) && !empty($eta_max)) {
                            $label = "Bambini {$eta_min}-{$eta_max} anni";
                        } elseif (empty($label)) {
                            $label = "Fascia $fascia";
                        }
                        
                        $active_slots[] = [
                            'type' => 'sconto',
                            'fascia' => $fascia,
                            'label' => $label,
                            'sconto' => $sconto,
                            'eta_min' => $eta_min,
                            'eta_max' => $eta_max
                        ];
                        $has_active_slots = true;
                    }
                }
                
                // Controlla prezzi globali bambini abilitati (per notti extra)
                $global_active = false;
                foreach ($child_categories as $category) {
                    $global_enabled_field = "btr_global_child_pricing_{$category['id']}_enabled";
                    $global_price_field = "btr_global_child_pricing_{$category['id']}";
                    
                    $global_enabled = get_post_meta($post->ID, $global_enabled_field, true);
                    if ($global_enabled === '1') {
                        $global_price = get_post_meta($post->ID, $global_price_field, true);
                        $active_slots[] = [
                            'type' => 'global',
                            'label' => $category['label'],
                            'price' => $global_price,
                            'note' => '(notti extra)'
                        ];
                        $has_active_slots = true;
                        $global_active = true;
                    }
                }
                
                if (!$has_active_slots) {
                    echo '<p style="color: #dc3545; font-style: italic;">Nessuno slot per bambini attualmente abilitato.</p>';
                } else {
                    // Mostra prima le fasce con sconto
                    $sconto_slots = array_filter($active_slots, function($slot) { return $slot['type'] === 'sconto'; });
                    $global_slots = array_filter($active_slots, function($slot) { return $slot['type'] === 'global'; });
                    
                    if (!empty($sconto_slots)) {
                        echo '<h4 style="margin-top: 0; margin-bottom: 10px; font-size: 14px; color: #666;">Fasce con Sconto (camere standard):</h4>';
                        echo '<ul style="margin: 0 0 15px 0; padding-left: 20px;">';
                        foreach ($sconto_slots as $slot) {
                            echo '<li style="margin-bottom: 8px;">';
                            echo '<strong>' . esc_html($slot['label']) . '</strong> - ';
                            echo 'Sconto: <span style="color: #0073aa; font-weight: 600;">' . $slot['sconto'] . '%</span>';
                            if (!empty($slot['eta_min']) && !empty($slot['eta_max'])) {
                                echo ' <small style="color: #666;">(età ' . $slot['eta_min'] . '-' . $slot['eta_max'] . ')</small>';
                            }
                            echo '</li>';
                        }
                        echo '</ul>';
                    }
                    
                    if (!empty($global_slots)) {
                        echo '<h4 style="margin-top: 0; margin-bottom: 10px; font-size: 14px; color: #666;">Prezzi Fissi (notti extra allotment):</h4>';
                        echo '<ul style="margin: 0; padding-left: 20px;">';
                        foreach ($global_slots as $slot) {
                            echo '<li style="margin-bottom: 8px;">';
                            echo '<strong>' . esc_html($slot['label']) . '</strong> - ';
                            echo 'Prezzo: <span style="color: #0073aa; font-weight: 600;">€' . number_format((float)$slot['price'], 2) . '</span>';
                            if (isset($slot['note'])) {
                                echo ' <small style="color: #666;">' . $slot['note'] . '</small>';
                            }
                            echo '</li>';
                        }
                        echo '</ul>';
                    }
                }
                ?>
            </div>
        </div>

    </div>
</div>
<script>
jQuery(document).ready(function($) {
    // Funzione per aggiornare la sezione degli slot attivi
    function updateActiveSlots() {
        var scontoSlots = [];
        var globalSlots = [];
        var hasActiveSlots = false;
        
        // Controlla fasce bambini abilitate con sconto
        for (var fascia = 1; fascia <= 4; fascia++) {
            var enabledCheckbox = $('#btr_bambini_fascia' + fascia + '_sconto_enabled');
            if (enabledCheckbox.is(':checked')) {
                var label = $('#btr_bambini_fascia' + fascia + '_label').val();
                var etaMin = $('#btr_bambini_fascia' + fascia + '_eta_min').val();
                var etaMax = $('#btr_bambini_fascia' + fascia + '_eta_max').val();
                var sconto = $('#btr_bambini_fascia' + fascia + '_sconto').val() || '0';
                
                // Genera etichetta basata sull'età se non specificata
                if (!label && etaMin && etaMax) {
                    label = 'Bambini ' + etaMin + '-' + etaMax + ' anni';
                } else if (!label) {
                    label = 'Fascia ' + fascia;
                }
                
                scontoSlots.push({
                    label: label,
                    sconto: sconto,
                    etaMin: etaMin,
                    etaMax: etaMax
                });
                hasActiveSlots = true;
            }
        }
        
        // Controlla prezzi globali bambini abilitati (per notti extra)
        var categories = ['f1', 'f2', 'f3', 'f4'];
        var categoryLabels = {
            'f1': 'Bambini 3-6 anni',
            'f2': 'Bambini 6-8 anni',
            'f3': 'Bambini 8-10 anni',
            'f4': 'Bambini 11-12 anni'
        };
        
        categories.forEach(function(category) {
            var enabledCheckbox = $('#btr_global_child_pricing_' + category + '_enabled');
            if (enabledCheckbox.is(':checked')) {
                var priceInput = $('input[name="btr_global_child_pricing_' + category + '"]');
                var price = priceInput.val() || '0';
                globalSlots.push({
                    label: categoryLabels[category],
                    price: price
                });
                hasActiveSlots = true;
            }
        });
        
        // Aggiorna la visualizzazione
        var container = $('#btr-active-children-slots');
        var html = '';
        
        if (!hasActiveSlots) {
            html = '<p style="color: #dc3545; font-style: italic;">Nessuno slot per bambini attualmente abilitato.</p>';
        } else {
            // Mostra fasce con sconto
            if (scontoSlots.length > 0) {
                html += '<h4 style="margin-top: 0; margin-bottom: 10px; font-size: 14px; color: #666;">Fasce con Sconto (camere standard):</h4>';
                html += '<ul style="margin: 0 0 15px 0; padding-left: 20px;">';
                scontoSlots.forEach(function(slot) {
                    html += '<li style="margin-bottom: 8px;">';
                    html += '<strong>' + slot.label + '</strong> - ';
                    html += 'Sconto: <span style="color: #0073aa; font-weight: 600;">' + slot.sconto + '%</span>';
                    if (slot.etaMin && slot.etaMax) {
                        html += ' <small style="color: #666;">(età ' + slot.etaMin + '-' + slot.etaMax + ')</small>';
                    }
                    html += '</li>';
                });
                html += '</ul>';
            }
            
            // Mostra prezzi fissi globali
            if (globalSlots.length > 0) {
                html += '<h4 style="margin-top: 0; margin-bottom: 10px; font-size: 14px; color: #666;">Prezzi Fissi (notti extra allotment):</h4>';
                html += '<ul style="margin: 0; padding-left: 20px;">';
                globalSlots.forEach(function(slot) {
                    html += '<li style="margin-bottom: 8px;">';
                    html += '<strong>' + slot.label + '</strong> - ';
                    html += 'Prezzo: <span style="color: #0073aa; font-weight: 600;">€' + parseFloat(slot.price).toFixed(2) + '</span>';
                    html += ' <small style="color: #666;">(notti extra)</small>';
                    html += '</li>';
                });
                html += '</ul>';
            }
        }
        
        container.html(html);
    }
    
    // Eventi per aggiornare gli slot attivi
    $('input[type="checkbox"]').on('change', function() {
        if (this.id.includes('_enabled')) {
            updateActiveSlots();
        }
    });
    
    // Eventi per aggiornare quando cambiano i prezzi/sconti
    $('input[type="number"]').on('input', function() {
        if (this.id.includes('_sconto') || this.id.includes('_pricing_')) {
            updateActiveSlots();
        }
    });
    
    // Eventi per aggiornare quando cambiano le etichette
    $('input[type="text"]').on('input', function() {
        if (this.id.includes('_label')) {
            updateActiveSlots();
        }
    });
    
    // Inizializza la visualizzazione
    updateActiveSlots();
});
    document.addEventListener('DOMContentLoaded', function () {
        const basePrice = <?php echo $camera_price_per_person; ?>;
        const fasce = [1, 2, 3, 4];

        fasce.forEach(function (fascia) {
            const inputId = 'btr_bambini_fascia' + fascia + '_sconto';
            const outputId = 'btr_bambini_fascia' + fascia + '_discounted_price';

            const inputEl = document.getElementById(inputId);
            const outputEl = document.getElementById(outputId);

            if (inputEl && outputEl) {
                const update = () => {
                    const sconto = parseFloat(inputEl.value || 0);
                    const discounted = basePrice - (basePrice * (sconto / 100));
                    outputEl.textContent = discounted.toFixed(2);
                };
                inputEl.addEventListener('input', update);
                update();
            }
        });
    });
</script>