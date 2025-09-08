<style>
  /* Extra night layout: calendar & fields side by side */
  .btr-extra-night-content {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
      align-items: flex-start;
  }
  .btr-extra-night-content .btr-field-group {
      flex: 1 1 200px;
      margin-bottom: 1rem;
  }
  .daterangepicker.show-calendar-always {
      display: block !important;
      opacity: 1 !important;
      visibility: visible !important;
      position: relative !important;
      z-index: auto !important;
      top: 0 !important;
      left: 0 !important;
  }

  .btr-accordion-item {
      margin-bottom: 10px;
      align-items: center;
      background-color: #fff;
      border: 1px solid #e0e0e0;
      border-radius: 8px;
      padding: 15px;
      box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
      transition: transform 0.3s, box-shadow 0.3s;
  }

  .btr-accordion-header {
      background: #f7f7f7;
      cursor: pointer;
      width: 100%;
      padding: 10px 15px;
      font-weight: 600;
      text-align: left;
      border: none;
      outline: none;
  }

  .btr-accordion-body {
      padding: 15px;
      display: none;
  }

  .btr-accordion-body.show {
      display: block;
  }

  /* Evidenziazione giorno disabilitato */
  input[type="date"].highlighted-date::-webkit-calendar-picker-indicator {
      filter: invert(35%) sepia(95%) saturate(500%) hue-rotate(330deg) brightness(90%);
  }

  input[type="date"].highlighted-date::part(text) {
      background-color: #ffe4e4;
  }

  /* Evidenziazione e stile moderno per la data di riferimento */
  input[type="date"].highlighted-date {
      position: relative;
      border: 2px solid #ff4d4d;
      border-radius: 6px;
      background-color: #fff5f5;
      font-weight: 500;
      padding: 8px 12px;
  }

  input[type="date"].highlighted-date::-webkit-calendar-picker-indicator {
      opacity: 1;
      cursor: pointer;
  }


</style>

<div id="sezione_disponibilita_camere_case3" class="btr-section collapsible active" style="display: none;">
    <h2>Gestione camere per data (modalità Allotment)</h2>
    <div class="section-content">

        <div class="btr-field-group">
            <label class="btr-label">Tipologia di persone ammesse</label>
            <div class="btr-checkbox-group-vertical tipologie-persone-numero_persone">

                <div class="btr-switch-container">

                    <label for="btr_ammessi_adulti_allotment" class="label_btr_ammessi_adulti_allotment">Adulti</label>
                    <input type="checkbox" id="btr_ammessi_adulti_allotment" name="btr_ammessi_adulti_allotment" value="1" <?php checked($btr_ammessi_adulti_allotment ?? '', '1'); ?>/>
                    <label class="btr-switch" for="btr_ammessi_adulti_allotment">
                                     <span class="btr-switch-handle">
                                        <svg class="btr-switch-icon btr-switch-icon--on" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
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

                <div class="btr-switch-container">

                    <label for="btr_ammessi_bambini_allotment" class="label_btr_ammessi_bambini_allotment">Bambini</label>
                    <input type="checkbox" id="btr_ammessi_bambini_allotment" name="btr_ammessi_bambini_allotment" value="1" <?php checked($btr_ammessi_bambini_allotment ?? '', '1'); ?> />
                    <label class="btr-switch" for="btr_ammessi_bambini_allotment">
                                 <span class="btr-switch-handle">
                                    <svg class="btr-switch-icon btr-switch-icon--on" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
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
            <small class="btr-field-description">Seleziona le tipologie di persone ammesse per la prenotazione.</small>
        </div>





<?php
// Recupera i dati salvati per l'allotment camere
$allotment_data = $btr_camere_allotment ?? [];

// Normalizza la struttura di $btr_camere_extra_allotment_by_date per sicurezza/coerenza
$raw_extra = $btr_camere_extra_allotment_by_date ?? [];
$extra_allotments_by_date = [];
foreach ($raw_extra as $data => $valori) {
    $range = isset($valori['range']) ? (is_array($valori['range']) ? array_map('sanitize_text_field', $valori['range']) : array_map('sanitize_text_field', explode(',', $valori['range']))) : [];
    $extra_allotments_by_date[$data] = [
        'range' => $range,
        'totale' => isset($valori['totale']) && !is_array($valori['totale']) ? intval($valori['totale']) : 0,
        'prezzo_per_persona' => isset($valori['prezzo_per_persona']) && !is_array($valori['prezzo_per_persona']) ? floatval($valori['prezzo_per_persona']) : 0,
        'supplemento' => isset($valori['supplemento']) && !is_array($valori['supplemento']) ? floatval($valori['supplemento']) : 0,
    ];
}

// Recupera i prezzi bambini per notti extra dal campo dedicato
$extra_allotment_child_prices = get_post_meta($post->ID, 'btr_extra_allotment_child_prices', true) ?? [];

/*
// DEBUG: stampa i dati degli extra allotment per data
echo '<pre>DEBUG: Extra allotment by date';
print_r($extra_allotments_by_date);
echo '</pre>';
*/
//printr($btr_camere_allotment);
?>
            <div class="btr-accordion" id="accordion-allotment-camere">
                <?php foreach ($btr_date_ranges as $index => $info_data):
                    $data_key = $info_data['start'];
                    $data_label = $info_data['name'];
                    $camere = $allotment_data[$data_key] ?? [];
                ?>
                <div class="btr-accordion-item">
                    <button class="btr-accordion-header" type="button" data-bs-target="#allotment-date-<?php echo esc_attr($index); ?>" aria-expanded="false" aria-controls="allotment-date-<?php echo esc_attr($index); ?>">
                        <?php echo esc_html($data_label); ?>
                    </button>
                    <div id="allotment-date-<?php echo esc_attr($index); ?>" class="btr-accordion-body">
                        <div class="btr-field-group required">
                            <label for="btr_allotment_totale_<?php echo esc_attr($index); ?>" class="btr-label">
                                Numero massimo camere totali disponibili per questa data
                                <?= info_desc('Imposta il numero massimo complessivo di camere disponibili per questa data, indipendentemente dalla tipologia.'); ?>
                            </label>
                            <div class="qty-input max_numero_persone2">
                                <button class="qty-count qty-count--minus" data-action="minus" type="button">-</button>
                                <input class="product-qty" type="number"
                                    id="btr_allotment_totale_<?php echo esc_attr($index); ?>"
                                    name="btr_allotment_totale[<?php echo esc_attr($data_key); ?>]"
                                    min="0"
                                    value="<?php echo esc_attr($allotment_data[$data_key]['totale'] ?? 1); ?>">
                                <button class="qty-count qty-count--add" data-action="add" type="button">+</button>
                            </div>
                            <small class="btr-field-description">Inserisci il numero massimo totale di camere disponibili per questa data.</small>
                        </div>
                        <div class="btr-rooms-container camere_case2">
                            <?php
                            $tipologie = ['singola', 'doppia', 'tripla', 'quadrupla', 'quintupla', 'condivisa'];
                            foreach ($tipologie as $tipo):
                                $valori = $camere[$tipo] ?? [];
                                $label_tipo = ucfirst($tipo);
                            ?>
                            <div class="btr-room-row btr-<?php echo $tipo; ?>">
                                <div class="btr-room-details">
                                    <h4 class="btr-room-name"><?php echo $label_tipo; ?></h4>
                                </div>

                                <div class="btr-room-qty">
                                    <label>Limita quantità</label>
                                    <div class="qty-input">
                                        <button class="qty-count qty-count--minus" data-action="minus" type="button">-</button>
                                        <input class="product-qty room-input" type="number" name="btr_camere_allotment[<?php echo esc_attr($data_key); ?>][<?php echo $tipo; ?>][limite]" min="0"
                                               value="<?php echo esc_attr($valori['limite'] ?? 0); ?>">
                                        <button class="qty-count qty-count--add" data-action="add" type="button">+</button>
                                    </div>
                                </div>

                                <div class="btr-room-supplement">
                                    <label>Supp. persona (€) <?= info_desc('Supplemento a persona, il totale supplemento viene ricalcolato dinamicamente frontend'); ?></label>
                                    <input type="number" name="btr_camere_allotment[<?php echo esc_attr($data_key); ?>][<?php echo $tipo; ?>][supplemento]" step="0.01" min="0"
                                           value="<?php echo esc_attr($valori['supplemento'] ?? ''); ?>">
                                </div>


                                <div class="btr-room-perperson">
                                    <label>Prezzo per persona (€)</label>
                                    <input type="number"
                                           class="btr-prezzo-persona"
                                           data-persone="<?php
                                           echo match ($tipo) {
                                               'singola' => 1,
                                               'doppia' => 2,
                                               'tripla' => 3,
                                               'quadrupla' => 4,
                                               'quintupla' => 5,
                                               'condivisa' => 1,
                                               default => 1
                                           };
                                           ?>"
                                           step="0.01"
                                           min="0"
                                           value="<?php
                                           if (isset($valori['prezzo_per_persona']) && $valori['prezzo_per_persona'] !== '') {
                                               echo esc_attr($valori['prezzo_per_persona']);
                                           } elseif (isset($valori['prezzo'])) {
                                               $posti = match ($tipo) {
                                                   'singola' => 1,
                                                   'doppia' => 2,
                                                   'tripla' => 3,
                                                   'quadrupla' => 4,
                                                   'quintupla' => 5,
                                                   'condivisa' => 1,
                                                   default => 1
                                               };
                                               echo esc_attr(number_format(floatval($valori['prezzo']) / $posti, 2, '.', ''));
                                           }
                                           ?>">
                                </div>

                                <div class="btr-room-pricing">
                                    <label>Prezzo camera (€)</label>
                                    <input type="number" name="btr_camere_allotment[<?php echo esc_attr($data_key); ?>][<?php echo $tipo; ?>][prezzo]" step="0.01" min="0"
                                           value="<?php echo esc_attr($valori['prezzo'] ?? ''); ?>">
                                </div>

                                <div class="btr-room-discount">
                                    <label>Sconto (%)</label>
                                    <input type="number" name="btr_camere_allotment[<?php echo esc_attr($data_key); ?>][<?php echo $tipo; ?>][sconto]" step="0.01" min="0" max="100"
                                           value="<?php echo esc_attr($valori['sconto'] ?? ''); ?>">
                                </div>



                                <div class="room-exclude">
                                    <div class="btr-switch-container">
                                        <label for="btr_exclude_<?php echo esc_attr($data_key . '_' . $tipo); ?>" class="label_btr_exclude_<?php echo $tipo; ?>">Escludi camera</label>
                                        <input type="checkbox" id="btr_exclude_<?php echo esc_attr($data_key . '_' . $tipo); ?>" name="btr_camere_allotment[<?php echo esc_attr($data_key); ?>][<?php echo $tipo; ?>][esclusa]" value="1" <?php checked($valori['esclusa'] ?? false, true); ?> />
                                        <label class="btr-switch" for="btr_exclude_<?php echo esc_attr($data_key . '_' . $tipo); ?>">
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
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="btr-extra-night-block">
                            <h2 style="margin-top: 20px; border-top: 1px dashed #ccc; padding-top: 15px;">Gestione notte aggiuntiva</h2>
                            <div class="btr-extra-night-content">
                                <div class="btr-field-group">
                                    <label class="btr-label">Data notte extra (max ±3 giorni)</label>
                                    <input type="text"
                                           id="daterange-<?php echo esc_attr($index); ?>"
                                           name="btr_camere_extra_allotment_by_date[<?php echo $data_key; ?>][range][]"
                                           class="daterange-extra-night"
                                           data-start-date="<?php echo esc_attr($info_data['start']); ?>"
                                           data-end-date="<?php echo esc_attr($info_data['end']); ?>"
                                           placeholder="Seleziona range notti extra"
                                           value="<?php echo esc_attr(implode(', ', array_filter($extra_allotments_by_date[$data_key]['range'] ?? []))); ?>"
                                           readonly>
                                </div>

                                <div class="btr-field-group required">
                                    <div class="btr-wrap-extra-night">
                                        <label for="btr_allotment_totale_<?php echo esc_attr($index); ?>" class="btr-label">
                                        Numero massimo camere totali disponibili per questa data
                                        <?= info_desc('Imposta il numero massimo complessivo di camere disponibili per questa data, indipendentemente dalla tipologia.'); ?>
                                    </label>
                                    <div class="qty-input max_numero_persone2">
                                        <button class="qty-count qty-count--minus" data-action="minus" type="button">-</button>
                                        <input class="product-qty" type="number"
                                               id="btr_allotment_totale_<?php echo esc_attr($index); ?>"
                                               name="btr_camere_extra_allotment_by_date[<?php echo esc_attr($data_key); ?>][totale]"
                                               min="0"
                                               value="<?php echo esc_attr($extra_allotments_by_date[$data_key]['totale'] ?? ''); ?>">
                                        <button class="qty-count qty-count--add" data-action="add" type="button">+</button>
                                    </div>
                                    <small class="btr-field-description">Inserisci il numero massimo totale di camere disponibili per le notti extra.</small>
                                </div>
                                    <div class="btr-wrap-extra-night">
                                    <!-- Logica di Pricing -->
                                    <div class="btr-field-group">
                                        <label class="btr-label">
                                            Modalità di pricing
                                            <?= info_desc('Scegli come calcolare il prezzo delle notti extra: per persona o per camera totale.'); ?>
                                        </label>
                                        <div style="margin-top: 8px;">
                                            <label style="display: flex; align-items: center; gap: 8px; font-weight: normal;">
                                                <input type="checkbox" 
                                                       id="btr_allotment_pricing_per_room_<?php echo esc_attr($index); ?>"
                                                       name="btr_camere_extra_allotment_by_date[<?php echo esc_attr($data_key); ?>][pricing_per_room]"
                                                       value="1"
                                                       <?php 
                                                       // Debug per vedere il valore salvato
                                                       $pricing_per_room_saved = isset($extra_allotments_by_date[$data_key]['pricing_per_room']) ? $extra_allotments_by_date[$data_key]['pricing_per_room'] : false;
                                                       // Converte esplicitamente a boolean (gestisce stringhe vuote e altri edge cases)
                                                       $is_pricing_per_room = ($pricing_per_room_saved === true || $pricing_per_room_saved === '1' || $pricing_per_room_saved === 1);
                                                       error_log("[TEMPLATE DEBUG] Data key: $data_key, Raw value: '" . var_export($pricing_per_room_saved, true) . "', Final boolean: " . ($is_pricing_per_room ? 'true' : 'false'));
                                                       checked($is_pricing_per_room); 
                                                       ?>
                                                       onchange="togglePricingMode(this, '<?php echo esc_attr($index); ?>')">
                                                <span>Prezzo per camera (totale da dividere per numero di persone)</span>
                                            </label>
                                        </div>
                                        <small class="btr-field-description">
                                            <strong>Non selezionato:</strong> Il prezzo inserito viene applicato per ogni persona<br>
                                            <strong>Selezionato:</strong> Il prezzo inserito è il totale per camera e viene diviso automaticamente per il numero di persone
                                        </small>
                                    </div>
                                    
                                    <div class="btr-field-group required">
                                        <label for="btr_allotment_prezzo_per_persona_<?php echo esc_attr($index); ?>" class="btr-label">
                                            <span id="btr_pricing_label_<?php echo esc_attr($index); ?>">
                                                <?php echo $is_pricing_per_room ? 'Prezzo per camera (€)' : 'Prezzo per persona (€)'; ?>
                                            </span>
                                            <?= info_desc('Imposta il prezzo per questa data secondo la modalità selezionata sopra.'); ?>
                                        </label>
                                        <input class="btr-input" type="number"
                                               id="btr_allotment_prezzo_per_persona_<?php echo esc_attr($index); ?>"
                                               name="btr_camere_extra_allotment_by_date[<?php echo esc_attr($data_key); ?>][prezzo_per_persona]"
                                               step="0.01"
                                               min="0"
                                               value="<?php echo esc_attr($extra_allotments_by_date[$data_key]['prezzo_per_persona'] ?? ''); ?>">
                                        <small class="btr-field-description" id="btr_pricing_desc_<?php echo esc_attr($index); ?>">
                                            <?php echo $is_pricing_per_room ? 'Inserisci il prezzo totale per camera che verrà diviso per il numero di persone.' : 'Inserisci il prezzo per persona per le notti extra.'; ?>
                                        </small>
                                    </div>
                                    </div>

                                    <!-- Sezione Prezzi per Bambini - Notti Extra -->
                                    <?php
                                    // Ottieni solo le fasce di bambini attive
                                    $active_child_categories = [];
                                    
                                    // Debug: mostra i dati salvati
                                    error_log("[DEBUG TEMPLATE] Post ID: " . $post->ID);
                                    error_log("[DEBUG TEMPLATE] extra_allotment_child_prices per $data_key: " . print_r($extra_allotment_child_prices[$data_key] ?? 'VUOTO', true));
                                    
                                    // Controlla fasce bambini con sconto abilitate
                                    for ($fascia = 1; $fascia <= 4; $fascia++) {
                                        $meta_enabled = "btr_bambini_fascia{$fascia}_sconto_enabled";
                                        $meta_label = "btr_bambini_fascia{$fascia}_label";
                                        $meta_eta_min = "btr_bambini_fascia{$fascia}_eta_min";
                                        $meta_eta_max = "btr_bambini_fascia{$fascia}_eta_max";
                                        
                                        $enabled = get_post_meta($post->ID, $meta_enabled, true);
                                        if ($enabled === '1') {
                                            $label = get_post_meta($post->ID, $meta_label, true);
                                            $eta_min = get_post_meta($post->ID, $meta_eta_min, true);
                                            $eta_max = get_post_meta($post->ID, $meta_eta_max, true);
                                            
                                            // Usa l'etichetta personalizzata o genera una basata sull'età
                                            if (empty($label) && !empty($eta_min) && !empty($eta_max)) {
                                                $label = "Bambini {$eta_min}-{$eta_max} anni";
                                            } elseif (empty($label)) {
                                                $label = "Fascia $fascia";
                                            }
                                            
                                            $active_child_categories[] = [
                                                'id' => "f{$fascia}",
                                                'label' => $label,
                                                'type' => 'sconto'
                                            ];
                                        }
                                    }
                                    
                                    // Controlla prezzi globali bambini abilitati
                                    $child_categories = [
                                        ['id' => 'f1', 'label' => 'Bambini 3-8 anni'],
                                        ['id' => 'f2', 'label' => 'Bambini 8-12 anni'],
                                        ['id' => 'f3', 'label' => 'Bambini 12-14 anni'],
                                        ['id' => 'f4', 'label' => 'Bambini 14-15 anni']
                                    ];
                                    
                                    foreach ($child_categories as $category) {
                                        $global_enabled_field = "btr_global_child_pricing_{$category['id']}_enabled";
                                        $global_enabled = get_post_meta($post->ID, $global_enabled_field, true);
                                        
                                        error_log("[DEBUG TEMPLATE] $global_enabled_field: $global_enabled");
                                        
                                        if ($global_enabled === '1') {
                                            // Controlla se non è già presente dalle fasce con sconto
                                            $already_exists = false;
                                            foreach ($active_child_categories as $active) {
                                                if ($active['id'] === $category['id']) {
                                                    $already_exists = true;
                                                    break;
                                                }
                                            }
                                            
                                            if (!$already_exists) {
                                                $active_child_categories[] = [
                                                    'id' => $category['id'],
                                                    'label' => $category['label'],
                                                    'type' => 'global'
                                                ];
                                            }
                                        }
                                    }
                                    
                                    error_log("[DEBUG TEMPLATE] active_child_categories: " . print_r($active_child_categories, true));
                                    
                                    // Mostra solo se ci sono categorie attive
                                    if (!empty($active_child_categories)): 
                                    ?>
                                    <div class="btr-wrap-extra-night">
                                        <div class="btr-field-group">
                                            <h5 class="btr-label">Prezzi per Bambini - Notti Extra</h5>
                                            <p style="margin-bottom: 10px; font-size: 12px; color: #666; margin-top: 0px;">
                                                Inserisci i prezzi per le categorie di bambini attive. Questi prezzi sovrascrivono quelli globali.
                                            </p>
                                            
                                            <?php foreach ($active_child_categories as $category):
                                                // Use dedicated field name for extra allotment child pricing to avoid conflicts
                                                $field_name = "btr_extra_allotment_child_prices[{$data_key}][{$category['id']}]";
                                                $price = isset($extra_allotment_child_prices[$data_key][$category['id']]) ? $extra_allotment_child_prices[$data_key][$category['id']] : '';
                                                
                                                // Se è un prezzo globale e non c'è un valore salvato, usa il prezzo globale come placeholder
                                                $placeholder = 'Prezzo';
                                                if ($category['type'] === 'global' && empty($price)) {
                                                    $global_price = get_post_meta($post->ID, "btr_global_child_pricing_{$category['id']}", true);
                                                    if (!empty($global_price)) {
                                                        $placeholder = "Default: €" . number_format((float)$global_price, 2);
                                                    }
                                                }
                                            ?>
                                            <div class="btr-child-pricing-row" style="padding: 8px; background: #fff; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 8px;">
                                                <div style="display: flex; align-items: center; gap: 10px;">
                                                    <label style="flex: 1; font-weight: 500; color: #333;">
                                                        <?php echo esc_html($category['label']); ?>
                                                    </label>
                                                    
                                                    <div style="display: flex; align-items: center; gap: 5px;">
                                                        <input type="number" 
                                                               name="<?php echo esc_attr($field_name); ?>" 
                                                               value="<?php echo esc_attr($price); ?>" 
                                                               step="0.01" 
                                                               min="0" 
                                                               placeholder="<?php echo esc_attr($placeholder); ?>"
                                                               style="width: 120px; padding: 4px 8px;" />
                                                        <small style="color: #666;">€ per bambino</small>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                </div>
                            </div> <!-- .btr-extra-night-content -->
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>



    </div>
</div>


<!-- Litepicker -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/litepicker/dist/css/litepicker.css" />
<script src="https://cdn.jsdelivr.net/npm/litepicker/dist/litepicker.js"></script>


<script>
jQuery(document).ready(function($) {
    // Utility per ottenere la data ISO (YYYY-MM-DD) in timezone locale,
    // evitando lo shift di un giorno dovuto alla conversione UTC di `toISOString()`.
    function formatDateISO(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }
    // Localizzazione giorni della settimana in italiano
    const giorniSettimana = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];
    const headers = document.querySelectorAll('.btr-accordion-header');
    headers.forEach(header => {
        header.addEventListener('click', function () {
            const body = document.getElementById(this.dataset.bsTarget.replace('#', ''));
            body.classList.toggle('show');
            this.setAttribute('aria-expanded', body.classList.contains('show'));
        });
    });

    // Funzione per aggiornare le etichette quando cambia la modalità di pricing
    window.togglePricingMode = function(checkbox, index) {
        const isPerRoom = checkbox.checked;
        const label = document.getElementById('btr_pricing_label_' + index);
        const description = document.getElementById('btr_pricing_desc_' + index);
        
        if (isPerRoom) {
            label.textContent = 'Prezzo per camera (€)';
            description.textContent = 'Inserisci il prezzo totale per camera che verrà diviso per il numero di persone.';
        } else {
            label.textContent = 'Prezzo per persona (€)';
            description.textContent = 'Inserisci il prezzo per persona per le notti extra.';
        }
    };

    $('.btr-prezzo-persona').on('input', function () {
        const persone = parseInt($(this).data('persone')) || 1;
        const prezzoPersona = parseFloat($(this).val()) || 0;
        const prezzoTotale = (prezzoPersona * persone).toFixed(2);
        const container = $(this).closest('.btr-room-row');
        const prezzoInput = container.find('input[name*="[prezzo]"]');
        if (prezzoInput.length) {
            prezzoInput.val(prezzoTotale);
        }
    });

    $('.btr-room-row').each(function () {
        const row = $(this);
        const suppPerPersona = row.find('.btr-supplemento-persona');
        const suppTotale = row.find('input[name*="[supplemento]"]');
        const persone = parseInt(suppPerPersona.data('persone')) || 1;

        if (!suppPerPersona.val() || parseFloat(suppPerPersona.val()) === 0) {
            const val = parseFloat(suppTotale.val()) || 0;
            if (!isNaN(val)) {
                suppPerPersona.val((val / persone).toFixed(2));
            }
        }

        suppPerPersona.on('input', function () {
            const val = parseFloat($(this).val()) || 0;
            suppTotale.val((val * persone).toFixed(2));
        });

        suppTotale.on('input', function () {
            const val = parseFloat($(this).val()) || 0;
            suppPerPersona.val((val / persone).toFixed(2));
        });
    });

    // Funzione per verificare se una data è selezionabile come notte extra (solo parte di data, senza orario)
    function isSelectableDate(date, referenceStart, referenceEnd) {
        const selectableDaysBefore = 3;
        const selectableDaysAfter = 3;

        const d = new Date(date.getFullYear(), date.getMonth(), date.getDate());
        const refStart = new Date(referenceStart.getFullYear(), referenceStart.getMonth(), referenceStart.getDate());
        const refEnd = new Date(referenceEnd.getFullYear(), referenceEnd.getMonth(), referenceEnd.getDate());

        const startSelectableDate = new Date(refStart);
        startSelectableDate.setDate(refStart.getDate() - selectableDaysBefore);

        const endSelectableDate = new Date(refEnd);
        endSelectableDate.setDate(refEnd.getDate() + selectableDaysAfter);

        return (d >= startSelectableDate && d < refStart) || (d > refEnd && d <= endSelectableDate);
    }

    // Calcola i giorni della data di riferimento tra startDate e endDate inclusi
    $('.daterange-extra-night').each(function () {
        const input = this;
        const parent = $(input).parent();
        const startDate = new Date($(input).data('start-date'));
        const endDate = new Date($(input).data('end-date'));
        const oneDay = 86400000;

        const calendarEl = document.createElement('div');
        calendarEl.className = 'btr-custom-calendar';
        calendarEl.dataset.calendarFor = input.id;

        // MODIFICA: mostra mese corrente e mese successivo
        const today = new Date(startDate.getFullYear(), startDate.getMonth(), 1);
        const monthsToRender = 2;
        const month = today.getMonth();
        const year = today.getFullYear();

        // Array di ISO delle date di riferimento
        const referenceISO = [];
        let d = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate());
        while (d <= new Date(endDate.getFullYear(), endDate.getMonth(), endDate.getDate())) {
            referenceISO.push(formatDateISO(d));
            d.setDate(d.getDate() + 1);
        }
        // [DEBUG] Giorni di riferimento (referenceISO)
        //console.log('[DEBUG] Giorni di riferimento (referenceISO):', referenceISO);

        for (let m = 0; m < monthsToRender; m++) {
            const currentMonth = new Date(year, month + m, 1);
            const currentMonthNumber = currentMonth.getMonth();
            const currentYear = currentMonth.getFullYear();

            const monthTable = document.createElement('table');
            const headerRow = document.createElement('tr');
            giorniSettimana.forEach(d => {
                const th = document.createElement('th');
                th.textContent = d;
                headerRow.appendChild(th);
            });
            monthTable.appendChild(headerRow);

            const firstDay = new Date(currentYear, currentMonthNumber, 1);
            const startOffset = (firstDay.getDay() + 6) % 7;
            let row = document.createElement('tr');
            for (let i = 0; i < startOffset; i++) {
                row.appendChild(document.createElement('td'));
            }

            const lastDayOfMonth = new Date(currentYear, currentMonthNumber + 1, 0).getDate();
            for (let i = 1; i <= lastDayOfMonth; i++) {
                const current = new Date(currentYear, currentMonthNumber, i);
                const td = document.createElement('td');
                td.textContent = i;
                const iso = formatDateISO(current);
                td.dataset.iso = iso;

                // Nuova logica assegnazione classi (priorità ref-day, selected solo su allowed/ref, data-iso sempre presente)
                const savedDates = (input.value || '').split(',').map(s => s.trim()).filter(Boolean);
                const isSaved = savedDates.includes(iso);
                const isReference = referenceISO.includes(iso);
                const isSelectable = isSelectableDate(current, startDate, endDate);

                if (isReference) {
                    td.className = 'ref-day';
                    if (isSaved) td.classList.add('selected');
                } else if (isSelectable) {
                    td.className = 'allowed-day';
                    if (isSaved) td.classList.add('selected');
                    td.addEventListener('click', () => {
                        let range = [];
                        // Se la data cliccata è prima del range principale
                        if (current < startDate) {
                            for (let i = 0; i < 3; i++) {
                                let d = new Date(current);
                                d.setDate(current.getDate() + i);
                                range.push(new Date(d.getFullYear(), d.getMonth(), d.getDate()));
                            }
                        } else {
                            for (let i = 2; i >= 0; i--) {
                                let d = new Date(current);
                                d.setDate(current.getDate() - i);
                                range.push(new Date(d.getFullYear(), d.getMonth(), d.getDate()));
                            }
                        }
                        // Filtro e ordinamento
                        const minDate = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate() - 3);
                        const maxDate = new Date(endDate.getFullYear(), endDate.getMonth(), endDate.getDate() + 3);
                        range = range.filter(d => d >= minDate && d <= maxDate && !referenceISO.includes(formatDateISO(d)));
                        // Aggiorna input e feedback
                        const sorted = [...range].sort((a, b) => a - b);
                        const formatted = sorted.map(d => {
                            return formatDateISO(d);
                        });
                        input.value = formatted.join(', ');
                        const feedbackId = 'feedback-' + input.id;
                        let feedbackEl = document.getElementById(feedbackId);
                        if (!feedbackEl) {
                            feedbackEl = document.createElement('div');
                            feedbackEl.id = feedbackId;
                            feedbackEl.className = 'btr-feedback-text';
                            input.parentNode.appendChild(feedbackEl);
                        }
                        feedbackEl.textContent = 'Hai selezionato: ' + range.map(d =>
                            d.toLocaleDateString('it-IT', { weekday: 'short', day: '2-digit', month: 'short' })
                        ).join(', ');
                        // Aggiorna selezione visiva
                        document.querySelectorAll('.allowed-day').forEach(el => el.classList.remove('selected'));
                        range.forEach(r => {
                            const isoDate = formatDateISO(r);
                            const cell = calendarEl.querySelector(`td[data-iso="${isoDate}"]`);
                            if (cell) cell.classList.add('selected');
                        });
                    });
                } else {
                    td.className = 'disabled-day';
                }

                row.appendChild(td);
                if (row.children.length === 7) {
                    monthTable.appendChild(row);
                    row = document.createElement('tr');
                }
            }
            if (row.children.length > 0) monthTable.appendChild(row);
            // [DEBUG] Completato rendering mese
            //console.log('[DEBUG] Completato rendering mese:', currentMonth.toLocaleString('it-IT', { month: 'long', year: 'numeric' }));
            calendarEl.appendChild(monthTable);

            // DEBUG finale per il calendario corrente
            //console.log('[DEBUG] Calendario renderizzato per:', currentMonth.toISOString().slice(0, 7));
        }
        // (Blocco rimosso: evidenziazione giorni già salvati, ora gestito direttamente dentro il ciclo di creazione delle celle)
        parent.append(calendarEl);

        // --- INIZIO BLOCCO MODIFICATO: SELEZIONA SOLO I GIORNI SALVATI, EVIDENZIA IN ROSSO I GIORNI DI RIFERIMENTO ---
        setTimeout(() => {
          const savedDates = (input.value || '').split(',').map(s => s.trim()).filter(Boolean);
          const calendar = document.querySelector(`.btr-custom-calendar[data-calendar-for="${input.id}"]`);
          if (!calendar) return;

          savedDates.forEach((iso) => {
            const dayCell = calendar.querySelector(`td[data-iso="${iso}"]`);
            if (dayCell) {
              console.log('[DEBUG] Seleziono il giorno salvato (riferito a input):', iso);
              dayCell.classList.add('selected');
              if (!dayCell.classList.contains('allowed-day') && !dayCell.classList.contains('ref-day')) {
                dayCell.classList.add('allowed-day');
              }
            } else {
              console.warn('[DEBUG] Giorno non trovato nel DOM per input', input.id, ':', iso);
            }
          });
        }, 100);
        // --- FINE BLOCCO MODIFICATO ---

       // console.log('[DEBUG] Completato rendering calendario per input:', input.id);
    });
});
</script>
<style>


    .btr-wrap-extra-night {
        display: flex;
        flex-direction: row;
        flex-wrap: wrap;
        margin-bottom: 2em;
    }
input.daterange-extra-night {
    display: block;
    border: 2px solid #ccc;
    padding: 8px;
    border-radius: 5px;
    display: none;
}

.litepicker-wrapper {
    margin-top: 10px;
    border: 1px solid #ccc;
    border-radius: 8px;
    padding: 12px;
    display: inline-block;
}
.litepicker .is-locked {
    background-color: #ffd6d6 !important;
    border: 1px solid #ff4d4d !important;
    color: #990000 !important;
    position: relative;
}

/* Miglioramenti UI Litepicker moderni */
.litepicker {
    font-family: "Segoe UI", sans-serif;
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
}
.litepicker .container__days .day-item {
    border-radius: 6px;
    transition: background-color 0.2s ease;
}
.litepicker .container__days .day-item:hover {
    background-color: #f0f0f0;
}
.litepicker .container__days .day-item.is-start-date,
.litepicker .container__days .day-item.is-end-date,
.litepicker .container__days .day-item.is-in-range {
    background-color: #0073aa !important;
    color: white !important;
}
.litepicker .container__days .day-item.is-locked {
    background-color: #ffe3e3 !important;
    border: 1px solid #ff4d4d;
    color: #c00 !important;
}
  /* Calendario custom 2025 - UI migliorata */
  .btr-custom-calendar {
      font-family: 'Inter', 'Segoe UI', sans-serif;
      border-radius: 12px;
      padding: 16px;
      background-color: #ffffff;
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.06);
      overflow: hidden;
      max-width: 100%;
      border: 1px solid #e0e0e0;
      transition: box-shadow 0.2s ease-in-out;
  }
  .btr-custom-calendar table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 4px;
  }
  .btr-custom-calendar th {
      padding: 6px 0;
      font-weight: 600;
      font-size: 0.85rem;
      color: #555;
      text-transform: uppercase;
  }
  .btr-custom-calendar td {
      width: 40px;
      height: 40px;
      line-height: 40px;
      font-size: 0.9rem;
      border-radius: 6px;
      text-align: center;
      transition: background-color 0.2s ease, color 0.2s ease;
      user-select: none;
  }
  .btr-custom-calendar .ref-day {
      background-color: #ffe2e2;
      color: #c0392b;
      font-weight: bold;
      pointer-events: none;
      opacity: 0.6;
  }
  .btr-custom-calendar .disabled-day {
      background-color: #f4f4f4;
      color: #aaa;
      cursor: not-allowed;
  }
  .btr-custom-calendar .allowed-day {
      background-color: #fdfdfd;
      border: 1px solid transparent;
      cursor: pointer;
  }
  .btr-custom-calendar .allowed-day:hover {
      background-color: #eef4ff;
      border-color: #0073aa;
  }
  .btr-custom-calendar .allowed-day.selected {
      background-color: #0073aa;
      color: #ffffff;
      font-weight: 600;
  }
  .btr-feedback-text {
      margin-top: 10px;
      font-size: 0.9rem;
      color: #333;
      background-color: #f9f9f9;
      border-left: 4px solid #0073aa;
      padding: 8px 12px;
      border-radius: 6px;
  }
</style>
