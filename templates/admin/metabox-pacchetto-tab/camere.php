<!-- Sezione Disponibilità Camere per Gestione per Tipologia di Camere (Caso 1) -->
<div id="sezione_disponibilita_camere_case1" class="btr-section collapsible active">
    <h2>Disponibilità Camere </h2>
    <div class="section-content">




        <div class="btr-field-group">
            <label class="btr-label">Tipologia di persone ammesse</label>
            <div class="btr-checkbox-group-vertical tipologie-persone-numero_persone">

                <div class="btr-switch-container">

                    <label for="btr_ammessi_adulti" class="label_btr_ammessi_adulti">Adulti</label>
                    <input type="checkbox" id="btr_ammessi_adulti" name="btr_ammessi_adulti" value="1" <?php checked($btr_ammessi_adulti, '1'); ?>/>
                    <label class="btr-switch" for="btr_ammessi_adulti">
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

                    <label for="btr_ammessi_bambini" class="label_btr_ammessi_bambini">Bambini</label>
                    <input type="checkbox" id="btr_ammessi_bambini" name="btr_ammessi_bambini" value="1" <?php checked($btr_ammessi_bambini, '1'); ?> />
                    <label class="btr-switch" for="btr_ammessi_bambini">
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

        <br><hr><br><br>


        <div class="btr-field-group btr-switch-visualizzazione">
            <label class="btr-label label_btr_show_disabled_rooms" for="btr_show_disabled_rooms"><strong>Visualizza camere
                    disabilitate</strong> <?=info_desc('Se disabilitato, verranno mostrate solo le camere compatibili con il numero di partecipanti. Se abilitato, tutte le camere verranno mostrate ma quelle non compatibili risulteranno disabilitate.'); ?></label>


            <div class="btr-switch-container">

                <label for="btr_show_disabled_rooms" class="label_btr_show_disabled_rooms">Attiva</label>
                <input type="checkbox" id="btr_show_disabled_rooms" name="btr_show_disabled_rooms" value="1" <?php checked(get_post_meta($post->ID, 'btr_show_disabled_rooms', true), '1'); ?> />
                <label class="btr-switch" for="btr_show_disabled_rooms">
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


            <small class="btr-field-description">
                Se disabilitato, verranno mostrate solo le camere compatibili con il numero di partecipanti. Se abilitato, tutte le camere verranno mostrate ma quelle non compatibili risulteranno disabilitate.
            </small>
        </div>

        <br><hr><br><br>


        <p class="btr-label">Specifica il numero di camere disponibili per questo pacchetto.</p>
        <div class="btr-room-selection">


            <div class="btr-show-global-discount">
                <div class="btr-switch-container">

                    <label for="btr_show_box_sconto_g" class="label_btr_show_box_sconto_g">Usa uno sconto globale</label>
                    <input type="checkbox" id="btr_show_box_sconto_g" name="btr_show_box_sconto_g" value="1" <?php checked($btr_show_box_sconto_g, '1'); ?> />
                    <label class="btr-switch" for="btr_show_box_sconto_g">
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



            <div class="btr-section-content <?php echo $btr_show_box_sconto_g?'show':'';?>">
                <!-- Campo Sconto Percentuale -->
                <div class="btr-field-group">
                    <label for="btr_sconto_percentuale">Sconto Percentuale (%)</label>
                    <input type="number" id="btr_sconto_percentuale" name="btr_sconto_percentuale" value="<?php echo esc_attr($btr_sconto_percentuale); ?>" step="0.01" min="0"
                           max="100" />
                    <small>
                        Inserisci il valore dello sconto globale da applicare a tutti i campi sconto.
                        Questo valore sarà utilizzato solo se l'opzione <strong>"Applica Prezzo e Sconto Globale"</strong> è selezionata.
                    </small>
                </div>

                <!-- Interruttore per Applicare lo Sconto Globale -->
                <div class="btr-field-group">
                    <div class="btr-switch-container">

                        <label for="btr_apply_global_sconto">Applica Prezzo e Sconto Globale</label>
                        <input type="checkbox" id="btr_apply_global_sconto" name="btr_apply_global_sconto" value="1" <?php checked($btr_apply_global_sconto, '1'); ?> />
                        <label class="btr-switch" for="btr_apply_global_sconto">
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
                    <small>
                        Seleziona questa opzione per applicare lo sconto percentuale indicato a tutti i campi relativi.
                        Deselezionando questa opzione, i valori individuali rimarranno invariati.
                    </small>
                </div>
            </div>


            <div class="btr-rooms-container">

                <!-- Riga Camera Singola -->
                <div class="btr-room-row btr-singola">
                    <!-- Icona della Camera -->
                    <div class="btr-room-icon">
                            <span class="room-icon">
                                <svg id="Raggruppa_2" data-name="Raggruppa 2" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="238.249" height="218.489" viewBox="0 0 238.249 218.489">
                                      <defs>
                                        <clipPath id="clip-path">
                                          <rect id="Rettangolo_1" data-name="Rettangolo 1" width="238.249" height="218.489" fill="none"></rect>
                                        </clipPath>
                                      </defs>
                                      <g id="Raggruppa_1" data-name="Raggruppa 1">
                                        <path id="Tracciato_1" data-name="Tracciato 1" d="M237.811,190.718v0a1.75,1.75,0,0,0-.148-.264V179.909q0-28.092-.006-56.183c-.006-10.256-6.866-19.93-16.312-23-2.55-.829-3.244-1.817-3.229-4.6.145-26.7.151-48.724.02-69.307-.07-11.083-4.707-19.151-13.408-23.333A35.93,35.93,0,0,0,189.942.19c-17.433-.233-35.159-.2-52.3-.167Q117.7.062,97.768.039C81.325.03,64.319.02,47.593.062,30.45.106,19.8,10.8,19.8,27.983l0,14.6c0,17.681-.011,35.963.039,53.946.006,2.386-.351,3.3-2.477,4A24.716,24.716,0,0,0,.318,124.51q0,26.971,0,53.943v12.029l-.048.064L0,190.927l.118.451a8.37,8.37,0,0,1,.2,1.436l0,10.981q0,4.368,0,8.736a6.146,6.146,0,0,0,1.621,4.488,5.161,5.161,0,0,0,3.839,1.458c3.093-.014,5.018-2.275,5.023-5.9q0-2.455.016-4.911l0-.522c1.048-2.195,1.272-6.16,1.312-8.369a24.686,24.686,0,0,1,4.149-.185H221.917c.282,0,.565.006.847.012.6.013,1.214.027,1.841-.015a3.288,3.288,0,0,1,1.852.283c.023,3.825.089,6.616.545,7.789a3.222,3.222,0,0,0,.208.432c.008,1.3.024,2.607.041,3.91l.024,2c.037,3.337,1.992,5.49,4.984,5.49h.019c3.177-.009,5.287-2.2,5.376-5.589.014-.492.013-.985.011-1.479v-3.783a1.717,1.717,0,0,0,.115-.185c.736-1.377.5-15.689.032-16.735M174.9,96.728a24.99,24.99,0,0,0,2.277-18.093c-1.326-5.888-2.873-12.183-4.731-19.245-3.234-12.3-12.8-19.643-25.6-19.647q-27.71-.008-55.416,0c-13.347,0-22.836,7.462-26.036,20.466q-.963,3.907-1.945,7.811c-.829,3.309-1.658,6.617-2.464,9.931a25.644,25.644,0,0,0,2.066,18.6,9.319,9.319,0,0,0,.4,2.1H31.011c-.009-.161-.019-.32-.028-.479-.057-.935-.11-1.818-.11-2.688V88.967l.191-.2-.019-.426c-.192-4.135-.069-6.942.021-8.992a10.829,10.829,0,0,0-.193-3.969l0-5.474q-.008-22.033,0-44.066c0-8.792,5.936-14.7,14.76-14.706,48.033-.025,97.417-.024,146.776,0,8.788,0,14.7,5.938,14.7,14.765q.024,24.972.01,49.946l0,22.89H173.89c.358-.741.672-1.389,1.013-2.005M72.045,88.149l3.126-1.691-4.18-.118a16.534,16.534,0,0,1,.319-5.114c1.406-6.465,3.059-13.155,4.91-19.884,1.81-6.58,7.016-10.512,13.928-10.519q28.842-.024,57.683,0c7.09.006,12.407,4.181,14.223,11.168,1.616,6.22,3.179,12.613,4.778,19.544a14.524,14.524,0,0,1,.191,5.709l-1.2-.171-.366,4.616a13.97,13.97,0,0,1-12.32,7.232c-8,.07-16.141.054-24.012.038q-5.22-.01-10.44-.015l-9.039.007q-11.961.014-23.92-.011c-5.946-.02-10.9-2.825-13.3-7.518-.018-.771-.028-1.546-.014-2.328l.016-.9ZM10.79,172.422a3.563,3.563,0,0,1,.549-2.635,3.762,3.762,0,0,1,2.718-.639c7.363.136,14.849.111,22.087.087q4.315-.014,8.631-.019H222.616c2.2,0,3.794,0,4.206.4s.407,1.936.411,4.073q.012,5.052,0,10.1c-.007,2.047-.012,3.526-.375,3.9s-1.793.363-3.783.364q-38.464,0-76.931,0H92.97q-39.03,0-78.063,0c-1.778,0-3.313,0-3.687-.368s-.376-1.871-.385-3.62c-.008-1.334.006-2.668.02-4,.026-2.5.052-5.09-.065-7.643m215.881-14.9c-.472.48-1.437.713-2.991.695-27.14-.1-54.732-.092-81.415-.083l-23.446.006-23.424-.006c-26.448-.009-53.792-.02-80.688.088-1.767.006-2.791-.219-3.3-.734-.486-.5-.694-1.451-.657-3.008.143-5.914.118-11.923.094-17.735-.015-3.7-.03-7.41,0-11.115.069-9.928,5.854-15.622,15.872-15.622l92.312,0,92.312,0c9.93,0,15.711,5.727,15.859,15.712.065,4.434.049,8.949.033,13.315-.019,5.088-.039,10.348.071,15.525.034,1.569-.161,2.483-.632,2.962"></path>
                                      </g>
                                    </svg>
                            </span>
                    </div>

                    <!-- Dettagli della Camera -->
                    <div class="btr-room-details">
                        <h4 class="btr-room-name">Singola</h4>
                    </div>

                    <!-- Contatore della Camera -->
                    <div class="btr-room-qty">
                        <label for="btr_supplemento_singole">Quantità</label>
                        <div class="qty-input">
                            <button class="qty-count qty-count--minus" data-action="minus" type="button">-</button>
                            <input class="product-qty" type="number" name="btr_num_singole" min="0" value="<?=$btr_num_singole?$btr_num_singole : '0'; ?>">
                            <button class="qty-count qty-count--add" data-action="add" type="button">+</button>
                        </div>
                    </div>

                    <!-- Campo Supplemento -->
                    <div class="btr-room-supplement">
                        <label for="btr_supplemento_singole">Supp. persona (€) <?= info_desc('Supplemento a persona, il totale supplemento viene ricalcolato dinamicamente frontend'); ?></label>
                        <input type="number" id="btr_supplemento_singole" name="btr_supplemento_singole" value="<?php echo esc_attr($btr_supplemento_singole); ?>" step="0.05"
                               min="0" />
                    </div>
                    <!-- Prezzo per persona -->
                    <div class="btr-room-perperson">
                        <label>Prezzo per persona (€)</label>
                        <input type="number"
                               class="btr-prezzo-persona"
                               data-persone="<?php
                               echo match ('singola') {
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
                               if (isset($camere['singola']['prezzo_per_persona']) && $camere['singola']['prezzo_per_persona'] !== '') {
                                   echo esc_attr($camere['singola']['prezzo_per_persona']);
                               } elseif (isset($camere['singola']['prezzo'])) {
                                   $posti = match ('singola') {
                                       'singola' => 1,
                                       'doppia' => 2,
                                       'tripla' => 3,
                                       'quadrupla' => 4,
                                       'quintupla' => 5,
                                       'condivisa' => 1,
                                       default => 1
                                   };
                                   echo esc_attr(number_format(floatval($camere['singola']['prezzo']) / $posti, 2, '.', ''));
                               }
                               ?>">
                    </div>

                    <!-- Campo Prezzo -->
                    <div class="btr-room-pricing">
                        <label for="btr_prezzo_singole">Prezzo camera (€)</label>
                        <input type="number" id="btr_prezzo_singole" name="btr_prezzo_singole" value="<?php echo esc_attr($btr_prezzo_singole); ?>" step="0.05" min="0" />
                    </div>

                    <!-- Campo Sconto -->
                    <div class="btr-room-discount">
                        <label for="btr_sconto_singole">Sconto (%)</label>
                        <input type="number" id="btr_sconto_singole" name="btr_sconto_singole" value="<?php echo esc_attr($btr_sconto_singole); ?>" step="0.05" min="0"
                               max="100" />
                    </div>

                    <!-- Sezione Prezzi per Bambini -->
                    <div class="btr-child-pricing-section">
                        <h5>Prezzi per Bambini</h5>
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
                            $field_name = "btr_child_pricing_singole_{$category['id']}";
                            $enabled_field = "btr_child_pricing_singole_{$category['id']}_enabled";
                            
                            $enabled = get_post_meta($post->ID, $enabled_field, true);
                            $price = get_post_meta($post->ID, $field_name, true);
                        ?>
                        <div class="btr-child-pricing-row">
                            <div class="btr-child-pricing-label">
                                <label for="<?php echo esc_attr($enabled_field); ?>"><?php echo esc_html($category['label']); ?></label>
                            </div>
                            
                            <div class="btr-child-pricing-enabled">
                                <input type="checkbox" id="<?php echo esc_attr($enabled_field); ?>" name="<?php echo esc_attr($enabled_field); ?>" value="1" <?php checked($enabled, '1'); ?> />
                            </div>
                            
                            <div class="btr-child-pricing-price">
                                <input type="number" name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr($price); ?>" step="0.01" min="0" placeholder="Prezzo" />
                                <small>€ per bambino</small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Scheda Camera Doppia -->
                <div class="btr-room-row btr-doppia">
                    <!-- Icona della Camera -->
                    <div class="btr-room-icon">
                            <span class="room-icon">
                               <svg id="Raggruppa_2" data-name="Raggruppa 2" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="305.831" height="216.597"
                                    viewBox="0 0 305.831 216.597">
                          <defs>
                            <clipPath id="clip-path">
                              <rect id="Rettangolo_1" data-name="Rettangolo 1" width="305.831" height="216.597" fill="none"/>
                            </clipPath>
                          </defs>
                          <g id="Raggruppa_1" data-name="Raggruppa 1">
                            <path id="Tracciato_1" data-name="Tracciato 1"
                                  d="M152.677.049c30.967,0,61.936-.165,92.9.151A42.674,42.674,0,0,1,262.2,3.609c12.769,5.732,17.934,16.779,18.087,30.264.2,17.6.1,35.212-.007,52.818-.016,2.65.707,3.935,3.366,4.773a31.233,31.233,0,0,1,22.139,30.121q.114,43.641.013,87.282c0,5.957-4.6,9.572-8.65,6.64a8.489,8.489,0,0,1-2.786-5.621,121.105,121.105,0,0,1-.12-14.222c.1-3.2-.689-4.573-4.372-4.569q-136.916.162-273.833.007c-3.723,0-4.771,1.224-4.594,4.723.232,4.608.092,9.238.031,13.857-.052,3.908-2.348,6.761-5.422,6.91-3.144.152-6-2.967-6-6.8C.027,179.57-.073,149.352.1,119.135c.068-11.859,9.428-23.8,21.428-27.5,3.4-1.048,4.066-2.623,4.041-5.793-.132-17.106.414-34.23-.193-51.315C24.731,16.383,37.516-.139,60.151,0c30.841.191,61.684.048,92.526.048m-.133,153.584q68.35,0,136.7,0c4.909,0,4.936-.013,4.952-5.028.024-7.74.088-15.481-.033-23.22a41.316,41.316,0,0,0-.9-9.269c-2.269-9.116-10.274-14.787-20.441-14.788q-98.126-.012-196.251,0c-14.857,0-29.713-.045-44.569.018-10.414.044-18.818,6.775-19.833,17.119-.934,9.522-.54,19.178-.65,28.777-.073,6.4-.009,6.4,6.2,6.4H152.544m13.78-63.981c0-10.594.5-20.705-.141-30.742a19.07,19.07,0,0,1,20.01-20.157c12.846.442,25.719.058,38.58.11,10.64.042,17.349,5.028,19.543,15.409,2.465,11.661,8.063,22.78,6.023,35.633,4.843,0,9.32-.183,13.774.06,3.3.179,4.266-.946,4.237-4.249-.153-17.355-.511-34.726.079-52.062.412-12.089-8.727-21.717-21.726-21.659-62.555.278-125.113.122-187.669.128-13.783,0-21.89,8.035-21.921,21.78q-.057,25.284,0,50.569a9.058,9.058,0,0,0,.222,3.329c.347.841,1.39,1.946,2.158,1.971,5.2.173,10.411.091,15.523.091,0-3.642-.561-6.942.1-9.974,2.053-9.36,4.45-18.651,6.952-27.9A17.67,17.67,0,0,1,78.861,38.926c14.108-.126,28.22-.172,42.327.009,10.713.138,18.223,7.951,18.3,18.651q.089,12.549-.017,25.1c-.021,2.28-.4,4.556-.631,6.969Zm-13.785,89.472H284.762c10.048,0,9.989,0,9.467-10.237-.134-2.64-1.067-3.961-3.819-3.773-1.367.093-2.746.007-4.12.007q-132.971,0-265.943.012c-9.888,0-8.787-1.148-8.836,8.757-.026,5.228.017,5.233,5.434,5.233H152.539M97.141,89.9c7.483,0,14.967.069,22.449-.024,5.154-.065,8.2-2.7,8.34-7.637q.332-12.15-.011-24.315c-.151-5.019-2.987-7.546-7.892-7.567q-19.83-.084-39.66.007c-4.207.02-6.493,1.867-7.523,5.874-2.012,7.83-4.041,15.657-5.884,23.527C65.43,86.3,68.31,89.83,75.066,89.889c7.358.064,14.717.015,22.075.011m111.656,0c7.359,0,14.719.059,22.077-.018,6.445-.068,9.39-3.738,7.923-10.028-1.835-7.868-3.847-15.695-5.863-23.519-1.065-4.135-3.5-5.979-7.852-5.99q-19.458-.051-38.917,0c-5.731.017-8.359,2.714-8.4,8.566q-.071,11.039,0,22.078c.046,6.177,2.734,8.852,8.949,8.9,7.359.057,14.719.014,22.078.012"/>
                          </g>
                        </svg>
                            </span>
                    </div>

                    <!-- Dettagli della Camera -->
                    <div class="btr-room-details">
                        <h4 class="btr-room-name">Doppia/Matrimoniale</h4>
                    </div>

                    <!-- Contatore della Camera -->
                    <div class="btr-room-qty">
                        <label for="btr_supplemento_singole">Quantità</label>
                        <div class="qty-input">
                            <button class="qty-count qty-count--minus" data-action="minus" type="button">-</button>
                            <input class="product-qty" type="number" name="btr_num_doppie" min="0" value="<?=$btr_num_doppie?$btr_num_doppie : '0'; ?>">
                            <button class="qty-count qty-count--add" data-action="add" type="button">+</button>
                        </div>
                    </div>

                    <!-- Campo Supplemento -->
                    <div class="btr-room-supplement">
                        <label for="btr_supplemento_doppie">Supp. persona (€) <?= info_desc('Supplemento a persona, il totale supplemento viene ricalcolato dinamicamente frontend'); ?></label>
                        <input type="number" id="btr_supplemento_doppie" name="btr_supplemento_doppie" value="<?php echo esc_attr($btr_supplemento_doppie); ?>" step="0.05"
                               min="0" />
                    </div>

                    <!-- Prezzo per persona -->
                    <div class="btr-room-perperson">
                        <label>Prezzo per persona (€)</label>
                        <input type="number"
                               class="btr-prezzo-persona"
                               data-persone="<?php
                               echo match ('doppia') {
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
                               if (isset($camere['doppia']['prezzo_per_persona']) && $camere['doppia']['prezzo_per_persona'] !== '') {
                                   echo esc_attr($camere['doppia']['prezzo_per_persona']);
                               } elseif (isset($camere['doppia']['prezzo'])) {
                                   $posti = match ('doppia') {
                                       'singola' => 1,
                                       'doppia' => 2,
                                       'tripla' => 3,
                                       'quadrupla' => 4,
                                       'quintupla' => 5,
                                       'condivisa' => 1,
                                       default => 1
                                   };
                                   echo esc_attr(number_format(floatval($camere['doppia']['prezzo']) / $posti, 2, '.', ''));
                               }
                               ?>">
                    </div>
                    <!-- Campo Prezzo -->
                    <div class="btr-room-pricing">
                        <label for="btr_prezzo_doppie">Prezzo camera (€)</label>
                        <input type="number" id="btr_prezzo_doppie" name="btr_prezzo_doppie" value="<?php echo esc_attr($btr_prezzo_doppie); ?>" step="0.05" min="0" />
                    </div>

                    <!-- Campo Sconto -->
                    <div class="btr-room-discount">
                        <label for="btr_sconto_doppie">Sconto (%)</label>
                        <input type="number" id="btr_sconto_doppie" name="btr_sconto_doppie" value="<?php echo esc_attr($btr_sconto_doppie); ?>" step="0.05" min="0"
                               max="100" />
                    </div>

                    <!-- Sezione Prezzi per Bambini -->
                    <div class="btr-child-pricing-section">
                        <h5>Prezzi per Bambini</h5>
                        <?php
                        foreach ($child_categories as $category):
                            $field_name = "btr_child_pricing_doppie_{$category['id']}";
                            $enabled_field = "btr_child_pricing_doppie_{$category['id']}_enabled";
                            
                            $enabled = get_post_meta($post->ID, $enabled_field, true);
                            $price = get_post_meta($post->ID, $field_name, true);
                        ?>
                        <div class="btr-child-pricing-row">
                            <div class="btr-child-pricing-label">
                                <label for="<?php echo esc_attr($enabled_field); ?>"><?php echo esc_html($category['label']); ?></label>
                            </div>
                            
                            <div class="btr-child-pricing-enabled">
                                <input type="checkbox" id="<?php echo esc_attr($enabled_field); ?>" name="<?php echo esc_attr($enabled_field); ?>" value="1" <?php checked($enabled, '1'); ?> />
                            </div>
                            
                            <div class="btr-child-pricing-price">
                                <input type="number" name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr($price); ?>" step="0.01" min="0" placeholder="Prezzo" />
                                <small>€ per bambino</small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Scheda Camera Tripla -->
                <div class="btr-room-row btr-tripla">
                    <!-- Icona della Camera -->
                    <div class="btr-room-icon">
                            <span class="room-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="579.249" height="218.489" viewBox="0 0 579.249 218.489">
                                  <defs>
                                    <clipPath id="clip-path">
                                      <rect id="Rettangolo_1" data-name="Rettangolo 1" width="238.249" height="218.489" fill="none"/>
                                    </clipPath>
                                    <clipPath id="clip-path-2">
                                      <rect id="Rettangolo_2" data-name="Rettangolo 2" width="305.831" height="216.597" fill="none"/>
                                    </clipPath>
                                  </defs>
                                  <g id="Raggruppa_5" data-name="Raggruppa 5" transform="translate(375 -722)">
                                    <g id="Raggruppa_2" data-name="Raggruppa 2" transform="translate(-34 722)">
                                      <g id="Raggruppa_1" data-name="Raggruppa 1" clip-path="url(#clip-path)">
                                        <path id="Tracciato_1" data-name="Tracciato 1"
                                              d="M237.811,190.718v0a1.75,1.75,0,0,0-.148-.264V179.909q0-28.092-.006-56.183c-.006-10.256-6.866-19.93-16.312-23-2.55-.829-3.244-1.817-3.229-4.6.145-26.7.151-48.724.02-69.307-.07-11.083-4.707-19.151-13.408-23.333A35.93,35.93,0,0,0,189.942.19c-17.433-.233-35.159-.2-52.3-.167Q117.7.062,97.768.039C81.325.03,64.319.02,47.593.062,30.45.106,19.8,10.8,19.8,27.983l0,14.6c0,17.681-.011,35.963.039,53.946.006,2.386-.351,3.3-2.477,4A24.716,24.716,0,0,0,.318,124.51q0,26.971,0,53.943v12.029l-.048.064L0,190.927l.118.451a8.37,8.37,0,0,1,.2,1.436l0,10.981q0,4.368,0,8.736a6.146,6.146,0,0,0,1.621,4.488,5.161,5.161,0,0,0,3.839,1.458c3.093-.014,5.018-2.275,5.023-5.9q0-2.455.016-4.911l0-.522c1.048-2.195,1.272-6.16,1.312-8.369a24.686,24.686,0,0,1,4.149-.185H221.917c.282,0,.565.006.847.012.6.013,1.214.027,1.841-.015a3.288,3.288,0,0,1,1.852.283c.023,3.825.089,6.616.545,7.789a3.222,3.222,0,0,0,.208.432c.008,1.3.024,2.607.041,3.91l.024,2c.037,3.337,1.992,5.49,4.984,5.49h.019c3.177-.009,5.287-2.2,5.376-5.589.014-.492.013-.985.011-1.479v-3.783a1.717,1.717,0,0,0,.115-.185c.736-1.377.5-15.689.032-16.735M174.9,96.728a24.99,24.99,0,0,0,2.277-18.093c-1.326-5.888-2.873-12.183-4.731-19.245-3.234-12.3-12.8-19.643-25.6-19.647q-27.71-.008-55.416,0c-13.347,0-22.836,7.462-26.036,20.466q-.963,3.907-1.945,7.811c-.829,3.309-1.658,6.617-2.464,9.931a25.644,25.644,0,0,0,2.066,18.6,9.319,9.319,0,0,0,.4,2.1H31.011c-.009-.161-.019-.32-.028-.479-.057-.935-.11-1.818-.11-2.688V88.967l.191-.2-.019-.426c-.192-4.135-.069-6.942.021-8.992a10.829,10.829,0,0,0-.193-3.969l0-5.474q-.008-22.033,0-44.066c0-8.792,5.936-14.7,14.76-14.706,48.033-.025,97.417-.024,146.776,0,8.788,0,14.7,5.938,14.7,14.765q.024,24.972.01,49.946l0,22.89H173.89c.358-.741.672-1.389,1.013-2.005M72.045,88.149l3.126-1.691-4.18-.118a16.534,16.534,0,0,1,.319-5.114c1.406-6.465,3.059-13.155,4.91-19.884,1.81-6.58,7.016-10.512,13.928-10.519q28.842-.024,57.683,0c7.09.006,12.407,4.181,14.223,11.168,1.616,6.22,3.179,12.613,4.778,19.544a14.524,14.524,0,0,1,.191,5.709l-1.2-.171-.366,4.616a13.97,13.97,0,0,1-12.32,7.232c-8,.07-16.141.054-24.012.038q-5.22-.01-10.44-.015l-9.039.007q-11.961.014-23.92-.011c-5.946-.02-10.9-2.825-13.3-7.518-.018-.771-.028-1.546-.014-2.328l.016-.9ZM10.79,172.422a3.563,3.563,0,0,1,.549-2.635,3.762,3.762,0,0,1,2.718-.639c7.363.136,14.849.111,22.087.087q4.315-.014,8.631-.019H222.616c2.2,0,3.794,0,4.206.4s.407,1.936.411,4.073q.012,5.052,0,10.1c-.007,2.047-.012,3.526-.375,3.9s-1.793.363-3.783.364q-38.464,0-76.931,0H92.97q-39.03,0-78.063,0c-1.778,0-3.313,0-3.687-.368s-.376-1.871-.385-3.62c-.008-1.334.006-2.668.02-4,.026-2.5.052-5.09-.065-7.643m215.881-14.9c-.472.48-1.437.713-2.991.695-27.14-.1-54.732-.092-81.415-.083l-23.446.006-23.424-.006c-26.448-.009-53.792-.02-80.688.088-1.767.006-2.791-.219-3.3-.734-.486-.5-.694-1.451-.657-3.008.143-5.914.118-11.923.094-17.735-.015-3.7-.03-7.41,0-11.115.069-9.928,5.854-15.622,15.872-15.622l92.312,0,92.312,0c9.93,0,15.711,5.727,15.859,15.712.065,4.434.049,8.949.033,13.315-.019,5.088-.039,10.348.071,15.525.034,1.569-.161,2.483-.632,2.962"/>
                                      </g>
                                    </g>
                                    <g id="Raggruppa_4" data-name="Raggruppa 4" transform="translate(-375 723.892)">
                                      <g id="Raggruppa_3" data-name="Raggruppa 3" clip-path="url(#clip-path-2)">
                                        <path id="Tracciato_2" data-name="Tracciato 2"
                                              d="M152.677.049c30.967,0,61.936-.165,92.9.151A42.674,42.674,0,0,1,262.2,3.609c12.769,5.732,17.934,16.779,18.087,30.264.2,17.6.1,35.212-.007,52.818-.016,2.65.707,3.935,3.366,4.773a31.233,31.233,0,0,1,22.139,30.121q.114,43.641.013,87.282c0,5.957-4.6,9.572-8.65,6.64a8.489,8.489,0,0,1-2.786-5.621,121.105,121.105,0,0,1-.12-14.222c.1-3.2-.689-4.573-4.372-4.569q-136.916.162-273.833.007c-3.723,0-4.771,1.224-4.594,4.723.232,4.608.092,9.238.031,13.857-.052,3.908-2.348,6.761-5.422,6.91-3.144.152-6-2.967-6-6.8C.027,179.57-.073,149.352.1,119.135c.068-11.859,9.428-23.8,21.428-27.5,3.4-1.048,4.066-2.623,4.041-5.793-.132-17.106.414-34.23-.193-51.315C24.731,16.383,37.516-.139,60.151,0c30.841.191,61.684.048,92.526.048m-.133,153.584q68.35,0,136.7,0c4.909,0,4.936-.013,4.952-5.028.024-7.74.088-15.481-.033-23.22a41.316,41.316,0,0,0-.9-9.269c-2.269-9.116-10.274-14.787-20.441-14.788q-98.126-.012-196.251,0c-14.857,0-29.713-.045-44.569.018-10.414.044-18.818,6.775-19.833,17.119-.934,9.522-.54,19.178-.65,28.777-.073,6.4-.009,6.4,6.2,6.4H152.544m13.78-63.981c0-10.594.5-20.705-.141-30.742a19.07,19.07,0,0,1,20.01-20.157c12.846.442,25.719.058,38.58.11,10.64.042,17.349,5.028,19.543,15.409,2.465,11.661,8.063,22.78,6.023,35.633,4.843,0,9.32-.183,13.774.06,3.3.179,4.266-.946,4.237-4.249-.153-17.355-.511-34.726.079-52.062.412-12.089-8.727-21.717-21.726-21.659-62.555.278-125.113.122-187.669.128-13.783,0-21.89,8.035-21.921,21.78q-.057,25.284,0,50.569a9.058,9.058,0,0,0,.222,3.329c.347.841,1.39,1.946,2.158,1.971,5.2.173,10.411.091,15.523.091,0-3.642-.561-6.942.1-9.974,2.053-9.36,4.45-18.651,6.952-27.9A17.67,17.67,0,0,1,78.861,38.926c14.108-.126,28.22-.172,42.327.009,10.713.138,18.223,7.951,18.3,18.651q.089,12.549-.017,25.1c-.021,2.28-.4,4.556-.631,6.969Zm-13.785,89.472H284.762c10.048,0,9.989,0,9.467-10.237-.134-2.64-1.067-3.961-3.819-3.773-1.367.093-2.746.007-4.12.007q-132.971,0-265.943.012c-9.888,0-8.787-1.148-8.836,8.757-.026,5.228.017,5.233,5.434,5.233H152.539M97.141,89.9c7.483,0,14.967.069,22.449-.024,5.154-.065,8.2-2.7,8.34-7.637q.332-12.15-.011-24.315c-.151-5.019-2.987-7.546-7.892-7.567q-19.83-.084-39.66.007c-4.207.02-6.493,1.867-7.523,5.874-2.012,7.83-4.041,15.657-5.884,23.527C65.43,86.3,68.31,89.83,75.066,89.889c7.358.064,14.717.015,22.075.011m111.656,0c7.359,0,14.719.059,22.077-.018,6.445-.068,9.39-3.738,7.923-10.028-1.835-7.868-3.847-15.695-5.863-23.519-1.065-4.135-3.5-5.979-7.852-5.99q-19.458-.051-38.917,0c-5.731.017-8.359,2.714-8.4,8.566q-.071,11.039,0,22.078c.046,6.177,2.734,8.852,8.949,8.9,7.359.057,14.719.014,22.078.012"/>
                                      </g>
                                    </g>
                                  </g>
                                </svg>
                            </span>
                    </div>

                    <!-- Dettagli della Camera -->
                    <div class="btr-room-details">
                        <h4 class="btr-room-name">Tripla</h4>
                    </div>

                    <!-- Contatore della Camera -->
                    <div class="btr-room-qty">
                        <label for="btr_supplemento_singole">Quantità</label>
                        <div class="qty-input">
                            <button class="qty-count qty-count--minus" data-action="minus" type="button">-</button>
                            <input class="product-qty" type="number" name="btr_num_triple" min="0" value="<?=$btr_num_triple?$btr_num_triple : '0'; ?>">
                            <button class="qty-count qty-count--add" data-action="add" type="button">+</button>
                        </div>
                    </div>

                    <!-- Campo Supplemento -->
                    <div class="btr-room-supplement">
                        <label for="btr_supplemento_triple">Supp. persona (€) <?= info_desc('Supplemento a persona, il totale supplemento viene ricalcolato dinamicamente frontend'); ?></label>
                        <input type="number" id="btr_supplemento_triple" name="btr_supplemento_triple" value="<?php echo esc_attr($btr_supplemento_triple); ?>" step="0.05"
                               min="0" />
                    </div>

                    <!-- Prezzo per persona -->
                    <div class="btr-room-perperson">
                        <label>Prezzo per persona (€)</label>
                        <input type="number"
                               class="btr-prezzo-persona"
                               data-persone="<?php
                               echo match ('tripla') {
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
                               if (isset($camere['tripla']['prezzo_per_persona']) && $camere['tripla']['prezzo_per_persona'] !== '') {
                                   echo esc_attr($camere['tripla']['prezzo_per_persona']);
                               } elseif (isset($camere['tripla']['prezzo'])) {
                                   $posti = match ('tripla') {
                                       'singola' => 1,
                                       'doppia' => 2,
                                       'tripla' => 3,
                                       'quadrupla' => 4,
                                       'quintupla' => 5,
                                       'condivisa' => 1,
                                       default => 1
                                   };
                                   echo esc_attr(number_format(floatval($camere['tripla']['prezzo']) / $posti, 2, '.', ''));
                               }
                               ?>">
                    </div>

                    <!-- Campo Prezzo -->
                    <div class="btr-room-pricing">
                        <label for="btr_prezzo_triple">Prezzo camera (€)</label>
                        <input type="number" id="btr_prezzo_triple" name="btr_prezzo_triple" value="<?php echo esc_attr($btr_prezzo_triple); ?>" step="0.05" min="0" />
                    </div>

                    <!-- Campo Sconto -->
                    <div class="btr-room-discount">
                        <label for="btr_sconto_triple">Sconto (%)</label>
                        <input type="number" id="btr_sconto_triple" name="btr_sconto_triple" value="<?php echo esc_attr($btr_sconto_triple); ?>" step="0.05" min="0"
                               max="100" />
                    </div>

                    <!-- Sezione Prezzi per Bambini -->
                    <div class="btr-child-pricing-section">
                        <h5>Prezzi per Bambini</h5>
                        <?php
                        foreach ($child_categories as $category):
                            $field_name = "btr_child_pricing_triple_{$category['id']}";
                            $enabled_field = "btr_child_pricing_triple_{$category['id']}_enabled";
                            
                            $enabled = get_post_meta($post->ID, $enabled_field, true);
                            $price = get_post_meta($post->ID, $field_name, true);
                        ?>
                        <div class="btr-child-pricing-row">
                            <div class="btr-child-pricing-label">
                                <label for="<?php echo esc_attr($enabled_field); ?>"><?php echo esc_html($category['label']); ?></label>
                            </div>
                            
                            <div class="btr-child-pricing-enabled">
                                <input type="checkbox" id="<?php echo esc_attr($enabled_field); ?>" name="<?php echo esc_attr($enabled_field); ?>" value="1" <?php checked($enabled, '1'); ?> />
                            </div>
                            
                            <div class="btr-child-pricing-price">
                                <input type="number" name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr($price); ?>" step="0.01" min="0" placeholder="Prezzo" />
                                <small>€ per bambino</small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Scheda Camera Quadrupla -->
                <div class="btr-room-row btr-quadrupla">
                    <!-- Icona della Camera -->
                    <div class="btr-room-icon">
                            <span class="room-icon">
                               <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="240.026" height="80.251" viewBox="0 0 240.026 80.251">
                                  <defs>
                                    <clipPath id="clip-path">
                                      <rect id="Rettangolo_2" data-name="Rettangolo 2" width="113.313" height="80.251" fill="none"/>
                                    </clipPath>
                                  </defs>
                                  <g id="Raggruppa_6" data-name="Raggruppa 6" transform="translate(375 -723.892)">
                                    <g id="Raggruppa_4" data-name="Raggruppa 4" transform="translate(-375 723.892)">
                                      <g id="Raggruppa_3" data-name="Raggruppa 3" clip-path="url(#clip-path)">
                                        <path id="Tracciato_2" data-name="Tracciato 2"
                                              d="M56.568.018c11.473,0,22.948-.061,34.42.056a15.811,15.811,0,0,1,6.16,1.263c4.731,2.124,6.645,6.217,6.7,11.213.073,6.522.037,13.046,0,19.569a1.528,1.528,0,0,0,1.247,1.768,11.572,11.572,0,0,1,8.2,11.16q.042,16.169,0,32.339c0,2.207-1.7,3.546-3.2,2.46a3.145,3.145,0,0,1-1.032-2.083,44.87,44.87,0,0,1-.044-5.269c.037-1.186-.255-1.694-1.62-1.693q-50.728.06-101.457,0c-1.379,0-1.768.454-1.7,1.75.086,1.707.034,3.423.011,5.134a2.342,2.342,0,0,1-2.009,2.56A2.374,2.374,0,0,1,.02,77.728C.01,66.532-.027,55.336.037,44.14c.025-4.394,3.493-8.82,7.939-10.19,1.259-.388,1.506-.972,1.5-2.146-.049-6.338.153-12.682-.072-19.013C9.163,6.07,13.9-.051,22.287,0,33.713.071,45.141.018,56.568.018m-.049,56.9h50.649c1.819,0,1.829,0,1.835-1.863.009-2.868.033-5.736-.012-8.6a15.308,15.308,0,0,0-.335-3.434c-.841-3.378-3.807-5.479-7.574-5.479q-36.356,0-72.712,0c-5.5,0-11.009-.017-16.513.007A7.05,7.05,0,0,0,4.508,43.89c-.346,3.528-.2,7.106-.241,10.662-.027,2.369,0,2.371,2.3,2.371H56.519m5.106-23.705c0-3.925.186-7.671-.052-11.39a7.066,7.066,0,0,1,7.414-7.468c4.76.164,9.529.021,14.294.041,3.942.016,6.428,1.863,7.241,5.709.913,4.32,2.987,8.44,2.232,13.2,1.794,0,3.453-.068,5.1.022,1.222.066,1.581-.351,1.57-1.574-.057-6.43-.189-12.866.029-19.289a7.685,7.685,0,0,0-8.05-8.025c-23.177.1-46.355.045-69.533.047-5.107,0-8.11,2.977-8.122,8.07q-.021,9.368,0,18.736a3.356,3.356,0,0,0,.082,1.233c.129.312.515.721.8.73,1.927.064,3.857.034,5.751.034a18.312,18.312,0,0,1,.039-3.7c.761-3.468,1.649-6.91,2.576-10.339a6.547,6.547,0,0,1,6.221-4.838c5.227-.047,10.456-.064,15.682,0a6.665,6.665,0,0,1,6.779,6.91q.033,4.649-.006,9.3c-.008.845-.149,1.688-.234,2.582Zm-5.107,33.15h48.99c3.723,0,3.7,0,3.508-3.793-.05-.978-.4-1.468-1.415-1.4-.506.034-1.017,0-1.526,0q-49.267,0-98.534,0c-3.664,0-3.256-.425-3.274,3.245-.01,1.937.006,1.939,2.013,1.939H56.517M35.992,33.309c2.773,0,5.545.026,8.318-.009,1.91-.024,3.04-1,3.09-2.83q.123-4.5,0-9.009a2.62,2.62,0,0,0-2.924-2.8q-7.347-.031-14.694,0a2.561,2.561,0,0,0-2.787,2.176c-.745,2.9-1.5,5.8-2.18,8.717-.567,2.421.5,3.729,3,3.751,2.726.024,5.453.006,8.179,0m41.369,0c2.727,0,5.453.022,8.18-.007,2.388-.025,3.479-1.385,2.936-3.715-.68-2.915-1.425-5.815-2.172-8.714a2.651,2.651,0,0,0-2.909-2.219q-7.209-.019-14.419,0c-2.123.006-3.1,1.006-3.111,3.174q-.026,4.09,0,8.18c.017,2.289,1.013,3.28,3.316,3.3,2.727.021,5.453.005,8.18,0"
                                              transform="translate(0 0)"/>
                                      </g>
                                    </g>
                                    <g id="Raggruppa_5" data-name="Raggruppa 5" transform="translate(-248.287 723.892)">
                                      <g id="Raggruppa_3-2" data-name="Raggruppa 3" clip-path="url(#clip-path)">
                                        <path id="Tracciato_2-2" data-name="Tracciato 2"
                                              d="M56.568.018c11.473,0,22.948-.061,34.42.056a15.811,15.811,0,0,1,6.16,1.263c4.731,2.124,6.645,6.217,6.7,11.213.073,6.522.037,13.046,0,19.569a1.528,1.528,0,0,0,1.247,1.768,11.572,11.572,0,0,1,8.2,11.16q.042,16.169,0,32.339c0,2.207-1.7,3.546-3.2,2.46a3.145,3.145,0,0,1-1.032-2.083,44.87,44.87,0,0,1-.044-5.269c.037-1.186-.255-1.694-1.62-1.693q-50.728.06-101.457,0c-1.379,0-1.768.454-1.7,1.75.086,1.707.034,3.423.011,5.134a2.342,2.342,0,0,1-2.009,2.56A2.374,2.374,0,0,1,.02,77.728C.01,66.532-.027,55.336.037,44.14c.025-4.394,3.493-8.82,7.939-10.19,1.259-.388,1.506-.972,1.5-2.146-.049-6.338.153-12.682-.072-19.013C9.163,6.07,13.9-.051,22.287,0,33.713.071,45.141.018,56.568.018m-.049,56.9h50.649c1.819,0,1.829,0,1.835-1.863.009-2.868.033-5.736-.012-8.6a15.308,15.308,0,0,0-.335-3.434c-.841-3.378-3.807-5.479-7.574-5.479q-36.356,0-72.712,0c-5.5,0-11.009-.017-16.513.007A7.05,7.05,0,0,0,4.508,43.89c-.346,3.528-.2,7.106-.241,10.662-.027,2.369,0,2.371,2.3,2.371H56.519m5.106-23.705c0-3.925.186-7.671-.052-11.39a7.066,7.066,0,0,1,7.414-7.468c4.76.164,9.529.021,14.294.041,3.942.016,6.428,1.863,7.241,5.709.913,4.32,2.987,8.44,2.232,13.2,1.794,0,3.453-.068,5.1.022,1.222.066,1.581-.351,1.57-1.574-.057-6.43-.189-12.866.029-19.289a7.685,7.685,0,0,0-8.05-8.025c-23.177.1-46.355.045-69.533.047-5.107,0-8.11,2.977-8.122,8.07q-.021,9.368,0,18.736a3.356,3.356,0,0,0,.082,1.233c.129.312.515.721.8.73,1.927.064,3.857.034,5.751.034a18.312,18.312,0,0,1,.039-3.7c.761-3.468,1.649-6.91,2.576-10.339a6.547,6.547,0,0,1,6.221-4.838c5.227-.047,10.456-.064,15.682,0a6.665,6.665,0,0,1,6.779,6.91q.033,4.649-.006,9.3c-.008.845-.149,1.688-.234,2.582Zm-5.107,33.15h48.99c3.723,0,3.7,0,3.508-3.793-.05-.978-.4-1.468-1.415-1.4-.506.034-1.017,0-1.526,0q-49.267,0-98.534,0c-3.664,0-3.256-.425-3.274,3.245-.01,1.937.006,1.939,2.013,1.939H56.517M35.992,33.309c2.773,0,5.545.026,8.318-.009,1.91-.024,3.04-1,3.09-2.83q.123-4.5,0-9.009a2.62,2.62,0,0,0-2.924-2.8q-7.347-.031-14.694,0a2.561,2.561,0,0,0-2.787,2.176c-.745,2.9-1.5,5.8-2.18,8.717-.567,2.421.5,3.729,3,3.751,2.726.024,5.453.006,8.179,0m41.369,0c2.727,0,5.453.022,8.18-.007,2.388-.025,3.479-1.385,2.936-3.715-.68-2.915-1.425-5.815-2.172-8.714a2.651,2.651,0,0,0-2.909-2.219q-7.209-.019-14.419,0c-2.123.006-3.1,1.006-3.111,3.174q-.026,4.09,0,8.18c.017,2.289,1.013,3.28,3.316,3.3,2.727.021,5.453.005,8.18,0"
                                              transform="translate(0 0)"/>
                                      </g>
                                    </g>
                                  </g>
                                </svg>
                            </span>
                    </div>

                    <!-- Dettagli della Camera -->
                    <div class="btr-room-details">
                        <h4 class="btr-room-name">Quadrupla</h4>
                    </div>

                    <!-- Contatore della Camera -->
                    <div class="btr-room-qty">
                        <label for="btr_supplemento_singole">Quantità</label>
                        <div class="qty-input">
                            <button class="qty-count qty-count--minus" data-action="minus" type="button">-</button>
                            <input class="product-qty" type="number" name="btr_num_quadruple" min="0" value="<?=$btr_num_quadruple?$btr_num_quadruple : '0'; ?>">
                            <button class="qty-count qty-count--add" data-action="add" type="button">+</button>
                        </div>
                    </div>

                    <!-- Campo Supplemento -->
                    <div class="btr-room-supplement">
                        <label for="btr_supplemento_quadruple">Supp. persona (€) <?= info_desc('Supplemento a persona, il totale supplemento viene ricalcolato dinamicamente frontend'); ?></label>
                        <input type="number" id="btr_supplemento_quadruple" name="btr_supplemento_quadruple" value="<?php echo esc_attr($btr_supplemento_quadruple); ?>" step="0.05"
                               min="0" />
                    </div>
                    <!-- Prezzo per persona -->
                    <div class="btr-room-perperson">
                        <label>Prezzo per persona (€)</label>
                        <input type="number"
                               class="btr-prezzo-persona"
                               data-persone="<?php
                               echo match ('quadrupla') {
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
                               if (isset($camere['quadrupla']['prezzo_per_persona']) && $camere['quadrupla']['prezzo_per_persona'] !== '') {
                                   echo esc_attr($camere['quadrupla']['prezzo_per_persona']);
                               } elseif (isset($camere['quadrupla']['prezzo'])) {
                                   $posti = match ('quadrupla') {
                                       'singola' => 1,
                                       'doppia' => 2,
                                       'tripla' => 3,
                                       'quadrupla' => 4,
                                       'quintupla' => 5,
                                       'condivisa' => 1,
                                       default => 1
                                   };
                                   echo esc_attr(number_format(floatval($camere['quadrupla']['prezzo']) / $posti, 2, '.', ''));
                               }
                               ?>">
                    </div>
                    <!-- Campo Prezzo -->
                    <div class="btr-room-pricing">
                        <label for="btr_prezzo_quadruple">Prezzo camera (€)</label>
                        <input type="number" id="btr_prezzo_quadruple" name="btr_prezzo_quadruple" value="<?php echo esc_attr($btr_prezzo_quadruple); ?>" step="0.05" min="0" />
                    </div>

                    <!-- Campo Sconto -->
                    <div class="btr-room-discount">
                        <label for="btr_sconto_quadruple">Sconto (%)</label>
                        <input type="number" id="btr_sconto_quadruple" name="btr_sconto_quadruple" value="<?php echo esc_attr($btr_sconto_quadruple); ?>" step="0.05" min="0"
                               max="100" />
                    </div>

                    <!-- Sezione Prezzi per Bambini -->
                    <div class="btr-child-pricing-section">
                        <h5>Prezzi per Bambini</h5>
                        <?php
                        foreach ($child_categories as $category):
                            $field_name = "btr_child_pricing_quadruple_{$category['id']}";
                            $enabled_field = "btr_child_pricing_quadruple_{$category['id']}_enabled";
                            
                            $enabled = get_post_meta($post->ID, $enabled_field, true);
                            $price = get_post_meta($post->ID, $field_name, true);
                        ?>
                        <div class="btr-child-pricing-row">
                            <div class="btr-child-pricing-label">
                                <label for="<?php echo esc_attr($enabled_field); ?>"><?php echo esc_html($category['label']); ?></label>
                            </div>
                            
                            <div class="btr-child-pricing-enabled">
                                <input type="checkbox" id="<?php echo esc_attr($enabled_field); ?>" name="<?php echo esc_attr($enabled_field); ?>" value="1" <?php checked($enabled, '1'); ?> />
                            </div>
                            
                            <div class="btr-child-pricing-price">
                                <input type="number" name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr($price); ?>" step="0.01" min="0" placeholder="Prezzo" />
                                <small>€ per bambino</small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                </div>

                <!-- Scheda Camera Quintupla -->
                <div class="btr-room-row btr-quintupla">
                    <!-- Icona della Camera -->
                    <div class="btr-room-icon">
                            <span class="room-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="262.317" height="62.416" viewBox="0 0 262.317 62.416">
                                  <defs>
                                    <clipPath id="clip-path">
                                      <rect id="Rettangolo_1" data-name="Rettangolo 1" width="68.061" height="62.416" fill="none"/>
                                    </clipPath>
                                    <clipPath id="clip-path-2">
                                      <rect id="Rettangolo_2" data-name="Rettangolo 2" width="87.367" height="61.875" fill="none"/>
                                    </clipPath>
                                  </defs>
                                  <g id="Raggruppa_7" data-name="Raggruppa 7" transform="translate(58.25 -803.28)">
                                    <g id="Raggruppa_2" data-name="Raggruppa 2" transform="translate(136.006 803.28)">
                                      <g id="Raggruppa_1" data-name="Raggruppa 1" clip-path="url(#clip-path)">
                                        <path id="Tracciato_1" data-name="Tracciato 1"
                                              d="M67.936,54.483h0a.5.5,0,0,0-.042-.075V51.4q0-8.025,0-16.05a7.022,7.022,0,0,0-4.66-6.571c-.728-.237-.927-.519-.922-1.313.041-7.628.043-13.919.006-19.8C62.3,4.5,60.971,2.191,58.485,1A10.264,10.264,0,0,0,54.261.055C49.281-.012,44.217,0,39.321.007q-5.7.011-11.391,0c-4.7,0-9.555-.005-14.334.007-4.9.013-7.94,3.069-7.94,7.976v4.172c0,5.051,0,10.274.011,15.411,0,.682-.1.942-.708,1.142A7.061,7.061,0,0,0,.091,35.569q0,7.7,0,15.41v3.436l-.014.018L0,54.543l.034.129a2.391,2.391,0,0,1,.057.41v3.137q0,1.248,0,2.5A1.756,1.756,0,0,0,.553,62a1.474,1.474,0,0,0,1.1.417c.884,0,1.433-.65,1.435-1.686q0-.7,0-1.4v-.149a6.9,6.9,0,0,0,.375-2.391,7.051,7.051,0,0,1,1.185-.053H63.4c.081,0,.161,0,.242,0,.171,0,.347.008.526,0a.939.939,0,0,1,.529.081,8.852,8.852,0,0,0,.156,2.225.92.92,0,0,0,.059.123c0,.373.007.745.012,1.117l.007.571a1.417,1.417,0,0,0,1.424,1.568h.005a1.5,1.5,0,0,0,1.536-1.6c0-.141,0-.281,0-.423V59.317a.493.493,0,0,0,.033-.053,22.3,22.3,0,0,0,.009-4.781M49.965,27.633a7.139,7.139,0,0,0,.65-5.169c-.379-1.682-.821-3.48-1.352-5.5a7.2,7.2,0,0,0-7.312-5.613q-7.916,0-15.831,0A7.216,7.216,0,0,0,18.683,17.2q-.275,1.116-.556,2.231c-.237.945-.474,1.89-.7,2.837a7.326,7.326,0,0,0,.59,5.312,2.662,2.662,0,0,0,.113.6H8.859c0-.046-.005-.091-.008-.137-.016-.267-.031-.519-.031-.768V25.416l.055-.057-.005-.122c-.055-1.181-.02-1.983.006-2.569a3.094,3.094,0,0,0-.055-1.134V19.97q0-6.294,0-12.588a3.987,3.987,0,0,1,4.216-4.2c13.722-.007,27.829-.007,41.93,0a3.989,3.989,0,0,1,4.2,4.218q.007,7.134,0,14.268v6.539H49.675c.1-.212.192-.4.289-.573M20.581,25.182l.893-.483-1.194-.034a4.723,4.723,0,0,1,.091-1.461c.4-1.847.874-3.758,1.4-5.68a3.932,3.932,0,0,1,3.979-3q8.239-.007,16.478,0a4.028,4.028,0,0,1,4.063,3.19c.462,1.777.908,3.6,1.365,5.583a4.149,4.149,0,0,1,.055,1.631l-.342-.049-.1,1.319a3.991,3.991,0,0,1-3.519,2.066c-2.286.02-4.611.015-6.86.011q-1.491,0-2.982,0l-2.582,0q-3.417,0-6.833,0a4.131,4.131,0,0,1-3.8-2.148c-.005-.22-.008-.442,0-.665l0-.256ZM3.082,49.256a1.018,1.018,0,0,1,.157-.753,1.075,1.075,0,0,1,.776-.183c2.1.039,4.242.032,6.31.025q1.233,0,2.466-.005h50.8a2.731,2.731,0,0,1,1.2.115,2.557,2.557,0,0,1,.117,1.164q0,1.443,0,2.886a2.59,2.59,0,0,1-.107,1.113,2.432,2.432,0,0,1-1.081.1H26.559q-11.15,0-22.3,0a2.421,2.421,0,0,1-1.053-.105,2.345,2.345,0,0,1-.11-1.034c0-.381,0-.762.006-1.143.007-.715.015-1.454-.019-2.183M64.753,45a1.208,1.208,0,0,1-.854.2c-7.753-.029-15.635-.026-23.258-.024l-6.7,0-6.692,0c-7.555,0-15.367-.006-23.05.025-.5,0-.8-.063-.942-.21a1.205,1.205,0,0,1-.188-.859c.041-1.689.034-3.406.027-5.066,0-1.058-.009-2.117,0-3.175.02-2.836,1.672-4.463,4.534-4.463H60.374c2.837,0,4.488,1.636,4.53,4.488.019,1.267.014,2.556.009,3.8-.005,1.453-.011,2.956.02,4.435a1.185,1.185,0,0,1-.181.846"
                                              transform="translate(0 0)"/>
                                      </g>
                                    </g>
                                    <g id="Raggruppa_6" data-name="Raggruppa 6" transform="translate(-58.25 803.821)">
                                      <g id="Raggruppa_4" data-name="Raggruppa 4" transform="translate(0 0)">
                                        <g id="Raggruppa_3" data-name="Raggruppa 3" clip-path="url(#clip-path-2)">
                                          <path id="Tracciato_2" data-name="Tracciato 2"
                                                d="M43.616.014c8.846,0,17.693-.047,26.539.043a12.191,12.191,0,0,1,4.749.974c3.648,1.637,5.123,4.793,5.167,8.646.057,5.029.029,10.059,0,15.089a1.179,1.179,0,0,0,.962,1.364,8.922,8.922,0,0,1,6.324,8.6q.033,12.467,0,24.934c0,1.7-1.314,2.734-2.471,1.9a2.425,2.425,0,0,1-.8-1.606,34.6,34.6,0,0,1-.034-4.063c.029-.914-.2-1.306-1.249-1.305q-39.113.046-78.226,0c-1.064,0-1.363.35-1.312,1.349.066,1.316.026,2.639.009,3.959A1.806,1.806,0,0,1,1.73,61.874,1.83,1.83,0,0,1,.015,59.93C.008,51.3-.021,42.666.029,34.033A8.794,8.794,0,0,1,6.15,26.176c.97-.3,1.162-.749,1.154-1.655-.038-4.887.118-9.779-.055-14.659C7.065,4.68,10.718-.04,17.184,0,25.994.055,34.8.014,43.616.014m-.038,43.874H82.629c1.4,0,1.41,0,1.415-1.436.007-2.211.025-4.422-.009-6.633a11.8,11.8,0,0,0-.259-2.648,5.685,5.685,0,0,0-5.839-4.225q-28.032,0-56.063,0c-4.244,0-8.488-.013-12.732.005a5.435,5.435,0,0,0-5.666,4.89c-.267,2.72-.154,5.479-.186,8.221-.021,1.827,0,1.828,1.771,1.828H43.578m3.937-18.277c0-3.026.143-5.915-.04-8.782a5.448,5.448,0,0,1,5.716-5.758c3.67.126,7.347.017,11.021.031,3.04.012,4.956,1.436,5.583,4.4.7,3.331,2.3,6.508,1.721,10.179,1.383,0,2.662-.052,3.935.017.942.051,1.219-.27,1.21-1.214-.044-4.958-.146-9.92.023-14.873a5.925,5.925,0,0,0-6.206-6.187c-17.87.079-35.741.035-53.612.037-3.937,0-6.253,2.3-6.262,6.222q-.016,7.223,0,14.446a2.588,2.588,0,0,0,.063.951c.1.24.4.556.616.563,1.486.049,2.974.026,4.434.026a14.119,14.119,0,0,1,.03-2.849c.586-2.674,1.271-5.328,1.986-7.972a5.048,5.048,0,0,1,4.8-3.73c4.03-.036,8.062-.049,12.092,0a5.139,5.139,0,0,1,5.227,5.328q.025,3.585,0,7.169c-.006.651-.115,1.3-.18,1.991Zm-3.938,25.56H81.348c2.87,0,2.854,0,2.7-2.924-.038-.754-.3-1.132-1.091-1.078-.391.027-.784,0-1.177,0q-37.986,0-75.972,0c-2.825,0-2.51-.328-2.524,2.5-.007,1.493,0,1.495,1.552,1.495H43.576M27.751,25.682c2.138,0,4.276.02,6.413-.007a2.117,2.117,0,0,0,2.382-2.182q.095-3.471,0-6.946a2.02,2.02,0,0,0-2.255-2.162q-5.665-.024-11.33,0a1.975,1.975,0,0,0-2.149,1.678c-.575,2.237-1.154,4.473-1.681,6.721-.437,1.867.386,2.875,2.316,2.892,2.1.018,4.2,0,6.306,0m31.9,0c2.1,0,4.2.017,6.307-.005,1.841-.019,2.682-1.068,2.263-2.865-.524-2.248-1.1-4.484-1.675-6.719A2.044,2.044,0,0,0,64.3,14.383q-5.559-.015-11.117,0c-1.637,0-2.388.775-2.4,2.447q-.02,3.153,0,6.307c.013,1.765.781,2.529,2.556,2.542,2.1.016,4.2,0,6.307,0"
                                                transform="translate(0 0)"/>
                                        </g>
                                      </g>
                                      <g id="Raggruppa_5" data-name="Raggruppa 5" transform="translate(97.699 0)">
                                        <g id="Raggruppa_3-2" data-name="Raggruppa 3" clip-path="url(#clip-path-2)">
                                          <path id="Tracciato_2-2" data-name="Tracciato 2"
                                                d="M43.616.014c8.846,0,17.693-.047,26.539.043a12.191,12.191,0,0,1,4.749.974c3.648,1.637,5.123,4.793,5.167,8.646.057,5.029.029,10.059,0,15.089a1.179,1.179,0,0,0,.962,1.364,8.922,8.922,0,0,1,6.324,8.6q.033,12.467,0,24.934c0,1.7-1.314,2.734-2.471,1.9a2.425,2.425,0,0,1-.8-1.606,34.6,34.6,0,0,1-.034-4.063c.029-.914-.2-1.306-1.249-1.305q-39.113.046-78.226,0c-1.064,0-1.363.35-1.312,1.349.066,1.316.026,2.639.009,3.959A1.806,1.806,0,0,1,1.73,61.874,1.83,1.83,0,0,1,.015,59.93C.008,51.3-.021,42.666.029,34.033A8.794,8.794,0,0,1,6.15,26.176c.97-.3,1.162-.749,1.154-1.655-.038-4.887.118-9.779-.055-14.659C7.065,4.68,10.718-.04,17.184,0,25.994.055,34.8.014,43.616.014m-.038,43.874H82.629c1.4,0,1.41,0,1.415-1.436.007-2.211.025-4.422-.009-6.633a11.8,11.8,0,0,0-.259-2.648,5.685,5.685,0,0,0-5.839-4.225q-28.032,0-56.063,0c-4.244,0-8.488-.013-12.732.005a5.435,5.435,0,0,0-5.666,4.89c-.267,2.72-.154,5.479-.186,8.221-.021,1.827,0,1.828,1.771,1.828H43.578m3.937-18.277c0-3.026.143-5.915-.04-8.782a5.448,5.448,0,0,1,5.716-5.758c3.67.126,7.347.017,11.021.031,3.04.012,4.956,1.436,5.583,4.4.7,3.331,2.3,6.508,1.721,10.179,1.383,0,2.662-.052,3.935.017.942.051,1.219-.27,1.21-1.214-.044-4.958-.146-9.92.023-14.873a5.925,5.925,0,0,0-6.206-6.187c-17.87.079-35.741.035-53.612.037-3.937,0-6.253,2.3-6.262,6.222q-.016,7.223,0,14.446a2.588,2.588,0,0,0,.063.951c.1.24.4.556.616.563,1.486.049,2.974.026,4.434.026a14.119,14.119,0,0,1,.03-2.849c.586-2.674,1.271-5.328,1.986-7.972a5.048,5.048,0,0,1,4.8-3.73c4.03-.036,8.062-.049,12.092,0a5.139,5.139,0,0,1,5.227,5.328q.025,3.585,0,7.169c-.006.651-.115,1.3-.18,1.991Zm-3.938,25.56H81.348c2.87,0,2.854,0,2.7-2.924-.038-.754-.3-1.132-1.091-1.078-.391.027-.784,0-1.177,0q-37.986,0-75.972,0c-2.825,0-2.51-.328-2.524,2.5-.007,1.493,0,1.495,1.552,1.495H43.576M27.751,25.682c2.138,0,4.276.02,6.413-.007a2.117,2.117,0,0,0,2.382-2.182q.095-3.471,0-6.946a2.02,2.02,0,0,0-2.255-2.162q-5.665-.024-11.33,0a1.975,1.975,0,0,0-2.149,1.678c-.575,2.237-1.154,4.473-1.681,6.721-.437,1.867.386,2.875,2.316,2.892,2.1.018,4.2,0,6.306,0m31.9,0c2.1,0,4.2.017,6.307-.005,1.841-.019,2.682-1.068,2.263-2.865-.524-2.248-1.1-4.484-1.675-6.719A2.044,2.044,0,0,0,64.3,14.383q-5.559-.015-11.117,0c-1.637,0-2.388.775-2.4,2.447q-.02,3.153,0,6.307c.013,1.765.781,2.529,2.556,2.542,2.1.016,4.2,0,6.307,0"
                                                transform="translate(0 0)"/>
                                        </g>
                                      </g>
                                    </g>
                                  </g>
                                </svg>
                            </span>
                    </div>

                    <!-- Dettagli della Camera -->
                    <div class="btr-room-details">
                        <h4 class="btr-room-name">Quintupla</h4>
                    </div>

                    <!-- Contatore della Camera -->
                    <div class="btr-room-qty">
                        <label for="btr_supplemento_singole">Quantità</label>
                        <div class="qty-input">
                            <button class="qty-count qty-count--minus" data-action="minus" type="button">-</button>
                            <input class="product-qty" type="number" name="btr_num_quintuple" min="0" value="<?=$btr_num_quintuple?$btr_num_quintuple : '0'; ?>">
                            <button class="qty-count qty-count--add" data-action="add" type="button">+</button>
                        </div>
                    </div>


                    <!-- Campo Supplemento -->
                    <div class="btr-room-supplement">
                        <label for="btr_supplemento_quintuple">Supp. persona (€) <?= info_desc('Supplemento a persona, il totale supplemento viene ricalcolato dinamicamente frontend'); ?></label>
                        <input type="number" id="btr_supplemento_quintuple" name="btr_supplemento_quintuple" value="<?php echo esc_attr($btr_supplemento_quintuple); ?>" step="0.05"
                               min="0" />
                    </div>
                    <!-- Prezzo per persona -->
                    <div class="btr-room-perperson">
                        <label>Prezzo per persona (€)</label>
                        <input type="number"
                               class="btr-prezzo-persona"
                               data-persone="<?php
                               echo match ('quintupla') {
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
                               if (isset($camere['quintupla']['prezzo_per_persona']) && $camere['quintupla']['prezzo_per_persona'] !== '') {
                                   echo esc_attr($camere['quintupla']['prezzo_per_persona']);
                               } elseif (isset($camere['quintupla']['prezzo'])) {
                                   $posti = match ('quintupla') {
                                       'singola' => 1,
                                       'doppia' => 2,
                                       'tripla' => 3,
                                       'quadrupla' => 4,
                                       'quintupla' => 5,
                                       'condivisa' => 1,
                                       default => 1
                                   };
                                   echo esc_attr(number_format(floatval($camere['quintupla']['prezzo']) / $posti, 2, '.', ''));
                               }
                               ?>">
                    </div>
                    <!-- Campo Prezzo -->
                    <div class="btr-room-pricing">
                        <label for="btr_prezzo_quintuple">Prezzo camera (€)</label>
                        <input type="number" id="btr_prezzo_quintuple" name="btr_prezzo_quintuple" value="<?php echo esc_attr($btr_prezzo_quintuple); ?>" step="0.05" min="0" />
                    </div>

                    <!-- Campo Sconto -->
                    <div class="btr-room-discount">
                        <label for="btr_sconto_quintuple">Sconto (%)</label>
                        <input type="number" id="btr_sconto_quintuple" name="btr_sconto_quintuple" value="<?php echo esc_attr($btr_sconto_quintuple); ?>" step="0.05" min="0"
                               max="100" />
                    </div>
                </div>


            </div>





            <script>
                jQuery(document).ready(function($) {

                    var QtyInput = (function () {
                        var $qtyInputs = $(".qty-input");

                        if (!$qtyInputs.length) {
                            return;
                        }

                        var $inputs = $qtyInputs.find(".product-qty");
                        var $countBtn = $qtyInputs.find(".qty-count");
                        var qtyMin = parseInt($inputs.attr("min"));
                        var qtyMax = parseInt($inputs.attr("max"));

                        // Inizializza lo stato dei pulsanti
                        $inputs.each(function () {
                            var $this = $(this);
                            var qty = parseInt($this.val());
                            var $minusBtn = $this.siblings(".qty-count--minus");
                            var $addBtn = $this.siblings(".qty-count--add");

                            if (qty <= qtyMin) {
                                $minusBtn.attr("disabled", true);
                            } else {
                                $minusBtn.attr("disabled", false);
                            }

                            if (qty >= qtyMax) {
                                $addBtn.attr("disabled", true);
                            } else {
                                $addBtn.attr("disabled", false);
                            }
                        });

                        // Cambia stato durante l'input manuale
                        $inputs.change(function () {
                            var $this = $(this);
                            var $minusBtn = $this.siblings(".qty-count--minus");
                            var $addBtn = $this.siblings(".qty-count--add");
                            var qty = parseInt($this.val());

                            if (isNaN(qty) || qty <= qtyMin) {
                                $this.val(qtyMin);
                                $minusBtn.attr("disabled", true);
                            } else {
                                $minusBtn.attr("disabled", false);

                                if (qty >= qtyMax) {
                                    $this.val(qtyMax);
                                    $addBtn.attr("disabled", true);
                                } else {
                                    $this.val(qty);
                                    $addBtn.attr("disabled", false);
                                }
                            }
                        });

                        // Cambia stato quando si clicca sui pulsanti
                        $countBtn.click(function () {
                            var operator = this.dataset.action;
                            var $this = $(this);
                            var $input = $this.siblings(".product-qty");
                            var qty = parseInt($input.val());

                            if (operator == "add") {
                                qty += 1;
                                if (qty >= qtyMin + 1) {
                                    $this.siblings(".qty-count--minus").attr("disabled", false);
                                }

                                if (qty >= qtyMax) {
                                    $this.attr("disabled", true);
                                }
                            } else {
                                qty = qty <= qtyMin ? qtyMin : (qty -= 1);

                                if (qty == qtyMin) {
                                    $this.attr("disabled", true);
                                }

                                if (qty < qtyMax) {
                                    $this.siblings(".qty-count--add").attr("disabled", false);
                                }
                            }

                            $input.val(qty);
                        });
                    })();

                });

            </script>


        </div>
    </div>
</div>


<!-- Sezione Disponibilità Camere per Gestione per Numero di Persone (Caso 2) -->
<div id="sezione_disponibilita_camere_case2" class="btr-section collapsible active" style="display: none;">
    <h2>Numero di persone e disponibilità camere</h2>
    <div class="section-content">


        <div class="btr-section-content-numero_persone">
            <div class="btr-field-group required">
                <label for="btr_num_persone_max_case2" class="btr-label">Numero massimo di persone <?=info_desc('Inserisci il numero massimo totale di persone per la prenotazione.
'); ?></label>

                <div class="qty-input max_numero_persone2">
                    <button class="qty-count qty-count--minus" data-action="minus" type="button">-</button>
                    <input class="product-qty" type="number" id="btr_num_persone_max_case2" name="btr_num_persone_max_case2" min="1"
                           value="<?= $btr_num_persone_max_case2 ? $btr_num_persone_max_case2 : '1'; ?>">
                    <button class="qty-count qty-count--add" data-action="add" type="button">+</button>
                </div>




                <small class="btr-field-description">Inserisci il numero massimo totale di persone per la prenotazione.</small>
            </div>

            <div class="btr-field-group">
                <label class="btr-label">Tipologia di persone ammesse</label>
                <div class="btr-checkbox-group-vertical tipologie-persone-numero_persone">

                    <div class="btr-switch-container">

                        <label for="btr_ammessi_adulti" class="label_btr_ammessi_adulti">Adulti</label>
                        <input type="checkbox" id="btr_ammessi_adulti" name="btr_ammessi_adulti" value="1" <?php checked($btr_ammessi_adulti, '1'); ?>/>
                        <label class="btr-switch" for="btr_ammessi_adulti">
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

                        <label for="btr_ammessi_bambini" class="label_btr_ammessi_bambini">Bambini</label>
                        <input type="checkbox" id="btr_ammessi_bambini" name="btr_ammessi_bambini" value="1" <?php checked($btr_ammessi_bambini, '1'); ?> />
                        <label class="btr-switch" for="btr_ammessi_bambini">
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
        </div>



        <label class="btr-label">Disponibilità camere per tipologia</label>

        <p class="description">Specifica il numero massimo di camere disponibili per ogni tipologia.</p>
        <!-- Contenitore per l'Alert Visivo -->
        <div id="people-alert" class="people-alert" style="display: none;">
            <span class="dashicons dashicons-warning"></span> Hai superato il numero massimo di persone!
        </div>
        <!-- Contatore Visivo delle Persone Rimanenti -->

        <div class="btr-horizontal-counter">
            <!-- Contatore per Camere Condivise -->
            <div class="btr-counter-box">
                <span class="btr-counter-label">Persone in camere condivise</span>
                <span id="remaining-people" class="btr-counter-value">0</span>
            </div>

            <!-- Divisore centrale -->
            <div class="btr-counter-divider"></div>

            <!-- Contatore per Camere Posti Limitati -->
            <div class="btr-counter-box">
                <span class="btr-counter-label">Persone in camere con posti limitati</span>
                <span id="assigned-people" class="btr-counter-value">0</span>
            </div>
        </div>





        <div class="btr-rooms-container camere_case2">

            <!-- Riga Camera Singola -->
            <div class="btr-room-row btr-singola">
                <!-- Icona della Camera -->
                <div class="btr-room-icon">
                            <span class="room-icon">
                                <svg id="Raggruppa_2" data-name="Raggruppa 2" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="238.249" height="218.489" viewBox="0 0 238.249 218.489">
                                      <defs>
                                        <clipPath id="clip-path">
                                          <rect id="Rettangolo_1" data-name="Rettangolo 1" width="238.249" height="218.489" fill="none"></rect>
                                        </clipPath>
                                      </defs>
                                      <g id="Raggruppa_1" data-name="Raggruppa 1">
                                        <path id="Tracciato_1" data-name="Tracciato 1" d="M237.811,190.718v0a1.75,1.75,0,0,0-.148-.264V179.909q0-28.092-.006-56.183c-.006-10.256-6.866-19.93-16.312-23-2.55-.829-3.244-1.817-3.229-4.6.145-26.7.151-48.724.02-69.307-.07-11.083-4.707-19.151-13.408-23.333A35.93,35.93,0,0,0,189.942.19c-17.433-.233-35.159-.2-52.3-.167Q117.7.062,97.768.039C81.325.03,64.319.02,47.593.062,30.45.106,19.8,10.8,19.8,27.983l0,14.6c0,17.681-.011,35.963.039,53.946.006,2.386-.351,3.3-2.477,4A24.716,24.716,0,0,0,.318,124.51q0,26.971,0,53.943v12.029l-.048.064L0,190.927l.118.451a8.37,8.37,0,0,1,.2,1.436l0,10.981q0,4.368,0,8.736a6.146,6.146,0,0,0,1.621,4.488,5.161,5.161,0,0,0,3.839,1.458c3.093-.014,5.018-2.275,5.023-5.9q0-2.455.016-4.911l0-.522c1.048-2.195,1.272-6.16,1.312-8.369a24.686,24.686,0,0,1,4.149-.185H221.917c.282,0,.565.006.847.012.6.013,1.214.027,1.841-.015a3.288,3.288,0,0,1,1.852.283c.023,3.825.089,6.616.545,7.789a3.222,3.222,0,0,0,.208.432c.008,1.3.024,2.607.041,3.91l.024,2c.037,3.337,1.992,5.49,4.984,5.49h.019c3.177-.009,5.287-2.2,5.376-5.589.014-.492.013-.985.011-1.479v-3.783a1.717,1.717,0,0,0,.115-.185c.736-1.377.5-15.689.032-16.735M174.9,96.728a24.99,24.99,0,0,0,2.277-18.093c-1.326-5.888-2.873-12.183-4.731-19.245-3.234-12.3-12.8-19.643-25.6-19.647q-27.71-.008-55.416,0c-13.347,0-22.836,7.462-26.036,20.466q-.963,3.907-1.945,7.811c-.829,3.309-1.658,6.617-2.464,9.931a25.644,25.644,0,0,0,2.066,18.6,9.319,9.319,0,0,0,.4,2.1H31.011c-.009-.161-.019-.32-.028-.479-.057-.935-.11-1.818-.11-2.688V88.967l.191-.2-.019-.426c-.192-4.135-.069-6.942.021-8.992a10.829,10.829,0,0,0-.193-3.969l0-5.474q-.008-22.033,0-44.066c0-8.792,5.936-14.7,14.76-14.706,48.033-.025,97.417-.024,146.776,0,8.788,0,14.7,5.938,14.7,14.765q.024,24.972.01,49.946l0,22.89H173.89c.358-.741.672-1.389,1.013-2.005M72.045,88.149l3.126-1.691-4.18-.118a16.534,16.534,0,0,1,.319-5.114c1.406-6.465,3.059-13.155,4.91-19.884,1.81-6.58,7.016-10.512,13.928-10.519q28.842-.024,57.683,0c7.09.006,12.407,4.181,14.223,11.168,1.616,6.22,3.179,12.613,4.778,19.544a14.524,14.524,0,0,1,.191,5.709l-1.2-.171-.366,4.616a13.97,13.97,0,0,1-12.32,7.232c-8,.07-16.141.054-24.012.038q-5.22-.01-10.44-.015l-9.039.007q-11.961.014-23.92-.011c-5.946-.02-10.9-2.825-13.3-7.518-.018-.771-.028-1.546-.014-2.328l.016-.9ZM10.79,172.422a3.563,3.563,0,0,1,.549-2.635,3.762,3.762,0,0,1,2.718-.639c7.363.136,14.849.111,22.087.087q4.315-.014,8.631-.019H222.616c2.2,0,3.794,0,4.206.4s.407,1.936.411,4.073q.012,5.052,0,10.1c-.007,2.047-.012,3.526-.375,3.9s-1.793.363-3.783.364q-38.464,0-76.931,0H92.97q-39.03,0-78.063,0c-1.778,0-3.313,0-3.687-.368s-.376-1.871-.385-3.62c-.008-1.334.006-2.668.02-4,.026-2.5.052-5.09-.065-7.643m215.881-14.9c-.472.48-1.437.713-2.991.695-27.14-.1-54.732-.092-81.415-.083l-23.446.006-23.424-.006c-26.448-.009-53.792-.02-80.688.088-1.767.006-2.791-.219-3.3-.734-.486-.5-.694-1.451-.657-3.008.143-5.914.118-11.923.094-17.735-.015-3.7-.03-7.41,0-11.115.069-9.928,5.854-15.622,15.872-15.622l92.312,0,92.312,0c9.93,0,15.711,5.727,15.859,15.712.065,4.434.049,8.949.033,13.315-.019,5.088-.039,10.348.071,15.525.034,1.569-.161,2.483-.632,2.962"></path>
                                      </g>
                                    </svg>
                            </span>
                </div>

                <!-- Dettagli della Camera -->
                <div class="btr-room-details">
                    <h4 class="btr-room-name">Singola</h4>
                </div>

                <!-- Contatore della Camera -->
                <div class="btr-room-qty">
                    <label for="btr_num_singole_max">Limita quantità <?=info_desc('Indica il numero massimo di camere singole per limitare le disponibilità, lascia 0 per non limitare'); ?></label>
                    <div class="qty-input">
                        <button class="qty-count qty-count--minus" data-action="minus" type="button">-</button>
                        <input class="product-qty room-input" type="number" id="btr_num_singole_max" name="btr_num_singole_max" min="0"
                               value="<?=$btr_num_singole_max?$btr_num_singole_max : '0'; ?>">
                        <button class="qty-count qty-count--add" data-action="add" type="button">+</button>
                    </div>
                </div>


                <!-- Campo Supplemento -->
                <div class="btr-room-supplement">
                    <label for="btr_supplemento_singole_max">Supp. persona (€) <?= info_desc('Supplemento a persona, il totale supplemento viene ricalcolato dinamicamente frontend'); ?></label>
                    <input type="number" id="btr_supplemento_singole_max" name="btr_supplemento_singole_max" value="<?php echo esc_attr($btr_supplemento_singole_max); ?>" step="0.05"
                           min="0" />
                </div>
                <!-- Prezzo per persona -->
                <div class="btr-room-perperson">
                    <label>Prezzo per persona (€)</label>
                    <input type="number"
                           class="btr-prezzo-persona"
                           data-persone="<?php
                           echo match ('singola') {
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
                           if (isset($camere['singola']['prezzo_per_persona']) && $camere['singola']['prezzo_per_persona'] !== '') {
                               echo esc_attr($camere['singola']['prezzo_per_persona']);
                           } elseif (isset($camere['singola']['prezzo'])) {
                               $posti = match ('singola') {
                                   'singola' => 1,
                                   'doppia' => 2,
                                   'tripla' => 3,
                                   'quadrupla' => 4,
                                   'quintupla' => 5,
                                   'condivisa' => 1,
                                   default => 1
                               };
                               echo esc_attr(number_format(floatval($camere['singola']['prezzo']) / $posti, 2, '.', ''));
                           }
                           ?>">
                </div>

                <!-- Campo Prezzo -->
                <div class="btr-room-pricing">
                    <label for="btr_prezzo_singole_max">Prezzo camera (€)</label>
                    <input type="number" id="btr_prezzo_singole_max" name="btr_prezzo_singole_max" value="<?php echo esc_attr($btr_prezzo_singole_max); ?>" step="0.05" min="0" />
                </div>

                <!-- Campo Sconto -->
                <div class="btr-room-discount">
                    <label for="btr_sconto_singole_max">Sconto (%)</label>
                    <input type="number" id="btr_sconto_singole_max" name="btr_sconto_singole_max" value="<?php echo esc_attr($btr_sconto_singole_max); ?>" step="0.05" min="0"
                           max="100" />
                </div>

                <!-- Checkbox per Escludere la Tipologia di Camera -->
                <div class="room-exclude">

                    <div class="btr-switch-container">

                        <label for="btr_exclude_singole_max" class="label_btr_exclude_singole_max">Escludi camera</label>
                        <input type="checkbox" id="btr_exclude_singole_max" name="btr_exclude_singole_max" value="on" <?php checked($btr_exclude_singole_max, 'on'); ?> />
                        <label class="btr-switch" for="btr_exclude_singole_max">
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
            </div>

            <!-- Riga Camera Doppia -->
            <div class="btr-room-row btr-doppia">
                <!-- Icona della Camera -->
                <div class="btr-room-icon">
                            <span class="room-icon">
                               <svg id="Raggruppa_2" data-name="Raggruppa 2" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="305.831" height="216.597"
                                    viewBox="0 0 305.831 216.597">
                          <defs>
                            <clipPath id="clip-path">
                              <rect id="Rettangolo_1" data-name="Rettangolo 1" width="305.831" height="216.597" fill="none"/>
                            </clipPath>
                          </defs>
                          <g id="Raggruppa_1" data-name="Raggruppa 1">
                            <path id="Tracciato_1" data-name="Tracciato 1"
                                  d="M152.677.049c30.967,0,61.936-.165,92.9.151A42.674,42.674,0,0,1,262.2,3.609c12.769,5.732,17.934,16.779,18.087,30.264.2,17.6.1,35.212-.007,52.818-.016,2.65.707,3.935,3.366,4.773a31.233,31.233,0,0,1,22.139,30.121q.114,43.641.013,87.282c0,5.957-4.6,9.572-8.65,6.64a8.489,8.489,0,0,1-2.786-5.621,121.105,121.105,0,0,1-.12-14.222c.1-3.2-.689-4.573-4.372-4.569q-136.916.162-273.833.007c-3.723,0-4.771,1.224-4.594,4.723.232,4.608.092,9.238.031,13.857-.052,3.908-2.348,6.761-5.422,6.91-3.144.152-6-2.967-6-6.8C.027,179.57-.073,149.352.1,119.135c.068-11.859,9.428-23.8,21.428-27.5,3.4-1.048,4.066-2.623,4.041-5.793-.132-17.106.414-34.23-.193-51.315C24.731,16.383,37.516-.139,60.151,0c30.841.191,61.684.048,92.526.048m-.133,153.584q68.35,0,136.7,0c4.909,0,4.936-.013,4.952-5.028.024-7.74.088-15.481-.033-23.22a41.316,41.316,0,0,0-.9-9.269c-2.269-9.116-10.274-14.787-20.441-14.788q-98.126-.012-196.251,0c-14.857,0-29.713-.045-44.569.018-10.414.044-18.818,6.775-19.833,17.119-.934,9.522-.54,19.178-.65,28.777-.073,6.4-.009,6.4,6.2,6.4H152.544m13.78-63.981c0-10.594.5-20.705-.141-30.742a19.07,19.07,0,0,1,20.01-20.157c12.846.442,25.719.058,38.58.11,10.64.042,17.349,5.028,19.543,15.409,2.465,11.661,8.063,22.78,6.023,35.633,4.843,0,9.32-.183,13.774.06,3.3.179,4.266-.946,4.237-4.249-.153-17.355-.511-34.726.079-52.062.412-12.089-8.727-21.717-21.726-21.659-62.555.278-125.113.122-187.669.128-13.783,0-21.89,8.035-21.921,21.78q-.057,25.284,0,50.569a9.058,9.058,0,0,0,.222,3.329c.347.841,1.39,1.946,2.158,1.971,5.2.173,10.411.091,15.523.091,0-3.642-.561-6.942.1-9.974,2.053-9.36,4.45-18.651,6.952-27.9A17.67,17.67,0,0,1,78.861,38.926c14.108-.126,28.22-.172,42.327.009,10.713.138,18.223,7.951,18.3,18.651q.089,12.549-.017,25.1c-.021,2.28-.4,4.556-.631,6.969Zm-13.785,89.472H284.762c10.048,0,9.989,0,9.467-10.237-.134-2.64-1.067-3.961-3.819-3.773-1.367.093-2.746.007-4.12.007q-132.971,0-265.943.012c-9.888,0-8.787-1.148-8.836,8.757-.026,5.228.017,5.233,5.434,5.233H152.539M97.141,89.9c7.483,0,14.967.069,22.449-.024,5.154-.065,8.2-2.7,8.34-7.637q.332-12.15-.011-24.315c-.151-5.019-2.987-7.546-7.892-7.567q-19.83-.084-39.66.007c-4.207.02-6.493,1.867-7.523,5.874-2.012,7.83-4.041,15.657-5.884,23.527C65.43,86.3,68.31,89.83,75.066,89.889c7.358.064,14.717.015,22.075.011m111.656,0c7.359,0,14.719.059,22.077-.018,6.445-.068,9.39-3.738,7.923-10.028-1.835-7.868-3.847-15.695-5.863-23.519-1.065-4.135-3.5-5.979-7.852-5.99q-19.458-.051-38.917,0c-5.731.017-8.359,2.714-8.4,8.566q-.071,11.039,0,22.078c.046,6.177,2.734,8.852,8.949,8.9,7.359.057,14.719.014,22.078.012"/>
                          </g>
                        </svg>
                            </span>
                </div>

                <!-- Dettagli della Camera -->
                <div class="btr-room-details">
                    <h4 class="btr-room-name">Doppie/Matrimoniali</h4>
                </div>

                <!-- Contatore della Camera -->
                <div class="btr-room-qty">
                    <label for="btr_num_doppie_max">Limita quantità <?=info_desc('Indica il numero massimo di camere doppie/matrimoniali per limitare le disponibilità, lascia 0 per non limitare'); ?></label>
                    <div class="qty-input">
                        <button class="qty-count qty-count--minus" data-action="minus" type="button">-</button>
                        <input class="product-qty room-input" type="number" id="btr_num_doppie_max" name="btr_num_doppie_max" min="0" value="<?=$btr_num_doppie_max?$btr_num_doppie_max
                            : '0'; ?>">
                        <button class="qty-count qty-count--add" data-action="add" type="button">+</button>
                    </div>
                </div>

                <!-- Campo Supplemento -->
                <div class="btr-room-supplement">
                    <label for="btr_supplemento_doppie_max">Supp. persona (€) <?= info_desc('Supplemento a persona, il totale supplemento viene ricalcolato dinamicamente frontend'); ?></label>
                    <input type="number" id="btr_supplemento_doppie_max" name="btr_supplemento_doppie_max" value="<?php echo esc_attr($btr_supplemento_doppie_max); ?>" step="0.05"
                           min="0" />
                </div>
                <!-- Prezzo per persona -->
                <div class="btr-room-perperson">
                    <label>Prezzo per persona (€)</label>
                    <input type="number"
                           class="btr-prezzo-persona"
                           data-persone="<?php
                           echo match ('doppia') {
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
                           if (isset($camere['doppia']['prezzo_per_persona']) && $camere['doppia']['prezzo_per_persona'] !== '') {
                               echo esc_attr($camere['doppia']['prezzo_per_persona']);
                           } elseif (isset($camere['doppia']['prezzo'])) {
                               $posti = match ('doppia') {
                                   'singola' => 1,
                                   'doppia' => 2,
                                   'tripla' => 3,
                                   'quadrupla' => 4,
                                   'quintupla' => 5,
                                   'condivisa' => 1,
                                   default => 1
                               };
                               echo esc_attr(number_format(floatval($camere['doppia']['prezzo']) / $posti, 2, '.', ''));
                           }
                           ?>">
                </div>
                <!-- Campo Prezzo -->
                <div class="btr-room-pricing">
                    <label for="btr_prezzo_doppie_max">Prezzo camera (€)</label>
                    <input type="number" id="btr_prezzo_doppie_max" name="btr_prezzo_doppie_max" value="<?php echo esc_attr($btr_prezzo_doppie_max); ?>" step="0.05" min="0" />
                </div>

                <!-- Campo Sconto -->
                <div class="btr-room-discount">
                    <label for="btr_sconto_doppie_max">Sconto (%)</label>
                    <input type="number" id="btr_sconto_doppie_max" name="btr_sconto_doppie_max" value="<?php echo esc_attr($btr_sconto_doppie_max); ?>" step="0.05" min="0"
                           max="100" />
                </div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btr-room-row').forEach(row => {
        const prezzoCamera = row.querySelector('input[name*="[prezzo]"], input[name^="btr_prezzo_"]');
        const prezzoPersona = row.querySelector('.btr-prezzo-persona');
        if (!prezzoPersona) return;
        const persone = parseInt(prezzoPersona.dataset.persone || 1);

        if (prezzoCamera && prezzoPersona && (!prezzoPersona.value || parseFloat(prezzoPersona.value) === 0)) {
            const prezzoTotale = parseFloat(prezzoCamera.value || 0);
            prezzoPersona.value = (prezzoTotale / persone).toFixed(2);
        }

        prezzoPersona?.addEventListener('input', function () {
            const val = parseFloat(this.value || 0);
            if (!isNaN(val)) {
                prezzoCamera.value = (val * persone).toFixed(2);
            }
        });

        prezzoCamera?.addEventListener('input', function () {
            const val = parseFloat(this.value || 0);
            if (!isNaN(val)) {
                prezzoPersona.value = (val / persone).toFixed(2);
            }
        });
    });
});
</script>


                <!-- Checkbox per Escludere la Tipologia di Camera -->
                <div class="room-exclude">

                    <div class="btr-switch-container">

                        <label for="btr_exclude_doppie_max" class="label_btr_exclude_doppie_max">Escludi camera</label>
                        <input type="checkbox" id="btr_exclude_doppie_max" name="btr_exclude_doppie_max" value="on" <?php checked($btr_exclude_doppie_max, 'on'); ?> />
                        <label class="btr-switch" for="btr_exclude_doppie_max">
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
            </div>

            <!-- Scheda Camera Tripla -->
            <div class="btr-room-row btr-tripla">
                <!-- Icona della Camera -->
                <div class="btr-room-icon">
                            <span class="room-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="579.249" height="218.489" viewBox="0 0 579.249 218.489">
                                  <defs>
                                    <clipPath id="clip-path">
                                      <rect id="Rettangolo_1" data-name="Rettangolo 1" width="238.249" height="218.489" fill="none"/>
                                    </clipPath>
                                    <clipPath id="clip-path-2">
                                      <rect id="Rettangolo_2" data-name="Rettangolo 2" width="305.831" height="216.597" fill="none"/>
                                    </clipPath>
                                  </defs>
                                  <g id="Raggruppa_5" data-name="Raggruppa 5" transform="translate(375 -722)">
                                    <g id="Raggruppa_2" data-name="Raggruppa 2" transform="translate(-34 722)">
                                      <g id="Raggruppa_1" data-name="Raggruppa 1" clip-path="url(#clip-path)">
                                        <path id="Tracciato_1" data-name="Tracciato 1"
                                              d="M237.811,190.718v0a1.75,1.75,0,0,0-.148-.264V179.909q0-28.092-.006-56.183c-.006-10.256-6.866-19.93-16.312-23-2.55-.829-3.244-1.817-3.229-4.6.145-26.7.151-48.724.02-69.307-.07-11.083-4.707-19.151-13.408-23.333A35.93,35.93,0,0,0,189.942.19c-17.433-.233-35.159-.2-52.3-.167Q117.7.062,97.768.039C81.325.03,64.319.02,47.593.062,30.45.106,19.8,10.8,19.8,27.983l0,14.6c0,17.681-.011,35.963.039,53.946.006,2.386-.351,3.3-2.477,4A24.716,24.716,0,0,0,.318,124.51q0,26.971,0,53.943v12.029l-.048.064L0,190.927l.118.451a8.37,8.37,0,0,1,.2,1.436l0,10.981q0,4.368,0,8.736a6.146,6.146,0,0,0,1.621,4.488,5.161,5.161,0,0,0,3.839,1.458c3.093-.014,5.018-2.275,5.023-5.9q0-2.455.016-4.911l0-.522c1.048-2.195,1.272-6.16,1.312-8.369a24.686,24.686,0,0,1,4.149-.185H221.917c.282,0,.565.006.847.012.6.013,1.214.027,1.841-.015a3.288,3.288,0,0,1,1.852.283c.023,3.825.089,6.616.545,7.789a3.222,3.222,0,0,0,.208.432c.008,1.3.024,2.607.041,3.91l.024,2c.037,3.337,1.992,5.49,4.984,5.49h.019c3.177-.009,5.287-2.2,5.376-5.589.014-.492.013-.985.011-1.479v-3.783a1.717,1.717,0,0,0,.115-.185c.736-1.377.5-15.689.032-16.735M174.9,96.728a24.99,24.99,0,0,0,2.277-18.093c-1.326-5.888-2.873-12.183-4.731-19.245-3.234-12.3-12.8-19.643-25.6-19.647q-27.71-.008-55.416,0c-13.347,0-22.836,7.462-26.036,20.466q-.963,3.907-1.945,7.811c-.829,3.309-1.658,6.617-2.464,9.931a25.644,25.644,0,0,0,2.066,18.6,9.319,9.319,0,0,0,.4,2.1H31.011c-.009-.161-.019-.32-.028-.479-.057-.935-.11-1.818-.11-2.688V88.967l.191-.2-.019-.426c-.192-4.135-.069-6.942.021-8.992a10.829,10.829,0,0,0-.193-3.969l0-5.474q-.008-22.033,0-44.066c0-8.792,5.936-14.7,14.76-14.706,48.033-.025,97.417-.024,146.776,0,8.788,0,14.7,5.938,14.7,14.765q.024,24.972.01,49.946l0,22.89H173.89c.358-.741.672-1.389,1.013-2.005M72.045,88.149l3.126-1.691-4.18-.118a16.534,16.534,0,0,1,.319-5.114c1.406-6.465,3.059-13.155,4.91-19.884,1.81-6.58,7.016-10.512,13.928-10.519q28.842-.024,57.683,0c7.09.006,12.407,4.181,14.223,11.168,1.616,6.22,3.179,12.613,4.778,19.544a14.524,14.524,0,0,1,.191,5.709l-1.2-.171-.366,4.616a13.97,13.97,0,0,1-12.32,7.232c-8,.07-16.141.054-24.012.038q-5.22-.01-10.44-.015l-9.039.007q-11.961.014-23.92-.011c-5.946-.02-10.9-2.825-13.3-7.518-.018-.771-.028-1.546-.014-2.328l.016-.9ZM10.79,172.422a3.563,3.563,0,0,1,.549-2.635,3.762,3.762,0,0,1,2.718-.639c7.363.136,14.849.111,22.087.087q4.315-.014,8.631-.019H222.616c2.2,0,3.794,0,4.206.4s.407,1.936.411,4.073q.012,5.052,0,10.1c-.007,2.047-.012,3.526-.375,3.9s-1.793.363-3.783.364q-38.464,0-76.931,0H92.97q-39.03,0-78.063,0c-1.778,0-3.313,0-3.687-.368s-.376-1.871-.385-3.62c-.008-1.334.006-2.668.02-4,.026-2.5.052-5.09-.065-7.643m215.881-14.9c-.472.48-1.437.713-2.991.695-27.14-.1-54.732-.092-81.415-.083l-23.446.006-23.424-.006c-26.448-.009-53.792-.02-80.688.088-1.767.006-2.791-.219-3.3-.734-.486-.5-.694-1.451-.657-3.008.143-5.914.118-11.923.094-17.735-.015-3.7-.03-7.41,0-11.115.069-9.928,5.854-15.622,15.872-15.622l92.312,0,92.312,0c9.93,0,15.711,5.727,15.859,15.712.065,4.434.049,8.949.033,13.315-.019,5.088-.039,10.348.071,15.525.034,1.569-.161,2.483-.632,2.962"/>
                                      </g>
                                    </g>
                                    <g id="Raggruppa_4" data-name="Raggruppa 4" transform="translate(-375 723.892)">
                                      <g id="Raggruppa_3" data-name="Raggruppa 3" clip-path="url(#clip-path-2)">
                                        <path id="Tracciato_2" data-name="Tracciato 2"
                                              d="M152.677.049c30.967,0,61.936-.165,92.9.151A42.674,42.674,0,0,1,262.2,3.609c12.769,5.732,17.934,16.779,18.087,30.264.2,17.6.1,35.212-.007,52.818-.016,2.65.707,3.935,3.366,4.773a31.233,31.233,0,0,1,22.139,30.121q.114,43.641.013,87.282c0,5.957-4.6,9.572-8.65,6.64a8.489,8.489,0,0,1-2.786-5.621,121.105,121.105,0,0,1-.12-14.222c.1-3.2-.689-4.573-4.372-4.569q-136.916.162-273.833.007c-3.723,0-4.771,1.224-4.594,4.723.232,4.608.092,9.238.031,13.857-.052,3.908-2.348,6.761-5.422,6.91-3.144.152-6-2.967-6-6.8C.027,179.57-.073,149.352.1,119.135c.068-11.859,9.428-23.8,21.428-27.5,3.4-1.048,4.066-2.623,4.041-5.793-.132-17.106.414-34.23-.193-51.315C24.731,16.383,37.516-.139,60.151,0c30.841.191,61.684.048,92.526.048m-.133,153.584q68.35,0,136.7,0c4.909,0,4.936-.013,4.952-5.028.024-7.74.088-15.481-.033-23.22a41.316,41.316,0,0,0-.9-9.269c-2.269-9.116-10.274-14.787-20.441-14.788q-98.126-.012-196.251,0c-14.857,0-29.713-.045-44.569.018-10.414.044-18.818,6.775-19.833,17.119-.934,9.522-.54,19.178-.65,28.777-.073,6.4-.009,6.4,6.2,6.4H152.544m13.78-63.981c0-10.594.5-20.705-.141-30.742a19.07,19.07,0,0,1,20.01-20.157c12.846.442,25.719.058,38.58.11,10.64.042,17.349,5.028,19.543,15.409,2.465,11.661,8.063,22.78,6.023,35.633,4.843,0,9.32-.183,13.774.06,3.3.179,4.266-.946,4.237-4.249-.153-17.355-.511-34.726.079-52.062.412-12.089-8.727-21.717-21.726-21.659-62.555.278-125.113.122-187.669.128-13.783,0-21.89,8.035-21.921,21.78q-.057,25.284,0,50.569a9.058,9.058,0,0,0,.222,3.329c.347.841,1.39,1.946,2.158,1.971,5.2.173,10.411.091,15.523.091,0-3.642-.561-6.942.1-9.974,2.053-9.36,4.45-18.651,6.952-27.9A17.67,17.67,0,0,1,78.861,38.926c14.108-.126,28.22-.172,42.327.009,10.713.138,18.223,7.951,18.3,18.651q.089,12.549-.017,25.1c-.021,2.28-.4,4.556-.631,6.969Zm-13.785,89.472H284.762c10.048,0,9.989,0,9.467-10.237-.134-2.64-1.067-3.961-3.819-3.773-1.367.093-2.746.007-4.12.007q-132.971,0-265.943.012c-9.888,0-8.787-1.148-8.836,8.757-.026,5.228.017,5.233,5.434,5.233H152.539M97.141,89.9c7.483,0,14.967.069,22.449-.024,5.154-.065,8.2-2.7,8.34-7.637q.332-12.15-.011-24.315c-.151-5.019-2.987-7.546-7.892-7.567q-19.83-.084-39.66.007c-4.207.02-6.493,1.867-7.523,5.874-2.012,7.83-4.041,15.657-5.884,23.527C65.43,86.3,68.31,89.83,75.066,89.889c7.358.064,14.717.015,22.075.011m111.656,0c7.359,0,14.719.059,22.077-.018,6.445-.068,9.39-3.738,7.923-10.028-1.835-7.868-3.847-15.695-5.863-23.519-1.065-4.135-3.5-5.979-7.852-5.99q-19.458-.051-38.917,0c-5.731.017-8.359,2.714-8.4,8.566q-.071,11.039,0,22.078c.046,6.177,2.734,8.852,8.949,8.9,7.359.057,14.719.014,22.078.012"/>
                                      </g>
                                    </g>
                                  </g>
                                </svg>
                            </span>
                </div>

                <!-- Dettagli della Camera -->
                <div class="btr-room-details">
                    <h4 class="btr-room-name">Tripla</h4>
                </div>

                <!-- Contatore della Camera -->
                <div class="btr-room-qty">
                    <label for="btr_supplemento_singole">Limita quantità <?=info_desc('Indica il numero massimo di camere triple per limitare le disponibilità, lascia 0 per non limitare'); ?>
                        <div class="qty-input">
                            <button class="qty-count qty-count--minus" data-action="minus" type="button">-</button>
                            <input class="product-qty room-input" type="number" name="btr_num_triple_max" min="0" value="<?=$btr_num_triple_max?$btr_num_triple_max : '0'; ?>">
                            <button class="qty-count qty-count--add" data-action="add" type="button">+</button>
                        </div>
                </div>

                <!-- Campo Supplemento -->
                <div class="btr-room-supplement">
                    <label for="btr_supplemento_triple_max">Supp. persona (€) <?= info_desc('Supplemento a persona, il totale supplemento viene ricalcolato dinamicamente frontend'); ?></label>
                    <input type="number" id="btr_supplemento_triple_max" name="btr_supplemento_triple_max" value="<?php echo esc_attr($btr_supplemento_triple_max); ?>" step="0.05"
                           min="0" />
                </div>
                <!-- Prezzo per persona -->
                <div class="btr-room-perperson">
                    <label>Prezzo per persona (€)</label>
                    <input type="number"
                           class="btr-prezzo-persona"
                           data-persone="<?php
                           echo match ('tripla') {
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
                           if (isset($camere['doppia']['prezzo_per_persona']) && $camere['doppia']['prezzo_per_persona'] !== '') {
                               echo esc_attr($camere['doppia']['prezzo_per_persona']);
                           } elseif (isset($camere['doppia']['prezzo'])) {
                               $posti = match ('doppia') {
                                   'singola' => 1,
                                   'doppia' => 2,
                                   'tripla' => 3,
                                   'quadrupla' => 4,
                                   'quintupla' => 5,
                                   'condivisa' => 1,
                                   default => 1
                               };
                               echo esc_attr(number_format(floatval($camere['doppia']['prezzo']) / $posti, 2, '.', ''));
                           }
                           ?>">
                </div>
                <!-- Campo Prezzo -->
                <div class="btr-room-pricing">
                    <label for="btr_prezzo_triple_max">Prezzo camera (€)</label>
                    <input type="number" id="btr_prezzo_triple_max" name="btr_prezzo_triple_max" value="<?php echo esc_attr($btr_prezzo_triple_max); ?>" step="0.05" min="0" />
                </div>

                <!-- Campo Sconto -->
                <div class="btr-room-discount">
                    <label for="btr_sconto_triple_max">Sconto (%)</label>
                    <input type="number" id="btr_sconto_triple_max" name="btr_sconto_triple_max" value="<?php echo esc_attr($btr_sconto_triple_max); ?>" step="0.05" min="0"
                           max="100" />
                </div>

                <!-- Checkbox per Escludere la Tipologia di Camera -->
                <div class="room-exclude">

                    <div class="btr-switch-container">

                        <label for="btr_exclude_triple_max" class="label_btr_exclude_triple_max">Escludi camera</label>
                        <input type="checkbox" id="btr_exclude_triple_max" name="btr_exclude_triple_max" value="on" <?php checked($btr_exclude_triple_max, 'on'); ?> />
                        <label class="btr-switch" for="btr_exclude_triple_max">
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

            </div>

            <!-- Scheda Camera Quadrupla -->
            <div class="btr-room-row btr-quadrupla">
                <!-- Icona della Camera -->
                <div class="btr-room-icon">
                            <span class="room-icon">
                               <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="240.026" height="80.251" viewBox="0 0 240.026 80.251">
                                  <defs>
                                    <clipPath id="clip-path">
                                      <rect id="Rettangolo_2" data-name="Rettangolo 2" width="113.313" height="80.251" fill="none"/>
                                    </clipPath>
                                  </defs>
                                  <g id="Raggruppa_6" data-name="Raggruppa 6" transform="translate(375 -723.892)">
                                    <g id="Raggruppa_4" data-name="Raggruppa 4" transform="translate(-375 723.892)">
                                      <g id="Raggruppa_3" data-name="Raggruppa 3" clip-path="url(#clip-path)">
                                        <path id="Tracciato_2" data-name="Tracciato 2"
                                              d="M56.568.018c11.473,0,22.948-.061,34.42.056a15.811,15.811,0,0,1,6.16,1.263c4.731,2.124,6.645,6.217,6.7,11.213.073,6.522.037,13.046,0,19.569a1.528,1.528,0,0,0,1.247,1.768,11.572,11.572,0,0,1,8.2,11.16q.042,16.169,0,32.339c0,2.207-1.7,3.546-3.2,2.46a3.145,3.145,0,0,1-1.032-2.083,44.87,44.87,0,0,1-.044-5.269c.037-1.186-.255-1.694-1.62-1.693q-50.728.06-101.457,0c-1.379,0-1.768.454-1.7,1.75.086,1.707.034,3.423.011,5.134a2.342,2.342,0,0,1-2.009,2.56A2.374,2.374,0,0,1,.02,77.728C.01,66.532-.027,55.336.037,44.14c.025-4.394,3.493-8.82,7.939-10.19,1.259-.388,1.506-.972,1.5-2.146-.049-6.338.153-12.682-.072-19.013C9.163,6.07,13.9-.051,22.287,0,33.713.071,45.141.018,56.568.018m-.049,56.9h50.649c1.819,0,1.829,0,1.835-1.863.009-2.868.033-5.736-.012-8.6a15.308,15.308,0,0,0-.335-3.434c-.841-3.378-3.807-5.479-7.574-5.479q-36.356,0-72.712,0c-5.5,0-11.009-.017-16.513.007A7.05,7.05,0,0,0,4.508,43.89c-.346,3.528-.2,7.106-.241,10.662-.027,2.369,0,2.371,2.3,2.371H56.519m5.106-23.705c0-3.925.186-7.671-.052-11.39a7.066,7.066,0,0,1,7.414-7.468c4.76.164,9.529.021,14.294.041,3.942.016,6.428,1.863,7.241,5.709.913,4.32,2.987,8.44,2.232,13.2,1.794,0,3.453-.068,5.1.022,1.222.066,1.581-.351,1.57-1.574-.057-6.43-.189-12.866.029-19.289a7.685,7.685,0,0,0-8.05-8.025c-23.177.1-46.355.045-69.533.047-5.107,0-8.11,2.977-8.122,8.07q-.021,9.368,0,18.736a3.356,3.356,0,0,0,.082,1.233c.129.312.515.721.8.73,1.927.064,3.857.034,5.751.034a18.312,18.312,0,0,1,.039-3.7c.761-3.468,1.649-6.91,2.576-10.339a6.547,6.547,0,0,1,6.221-4.838c5.227-.047,10.456-.064,15.682,0a6.665,6.665,0,0,1,6.779,6.91q.033,4.649-.006,9.3c-.008.845-.149,1.688-.234,2.582Zm-5.107,33.15h48.99c3.723,0,3.7,0,3.508-3.793-.05-.978-.4-1.468-1.415-1.4-.506.034-1.017,0-1.526,0q-49.267,0-98.534,0c-3.664,0-3.256-.425-3.274,3.245-.01,1.937.006,1.939,2.013,1.939H56.517M35.992,33.309c2.773,0,5.545.026,8.318-.009,1.91-.024,3.04-1,3.09-2.83q.123-4.5,0-9.009a2.62,2.62,0,0,0-2.924-2.8q-7.347-.031-14.694,0a2.561,2.561,0,0,0-2.787,2.176c-.745,2.9-1.5,5.8-2.18,8.717-.567,2.421.5,3.729,3,3.751,2.726.024,5.453.006,8.179,0m41.369,0c2.727,0,5.453.022,8.18-.007,2.388-.025,3.479-1.385,2.936-3.715-.68-2.915-1.425-5.815-2.172-8.714a2.651,2.651,0,0,0-2.909-2.219q-7.209-.019-14.419,0c-2.123.006-3.1,1.006-3.111,3.174q-.026,4.09,0,8.18c.017,2.289,1.013,3.28,3.316,3.3,2.727.021,5.453.005,8.18,0"
                                              transform="translate(0 0)"/>
                                      </g>
                                    </g>
                                    <g id="Raggruppa_5" data-name="Raggruppa 5" transform="translate(-248.287 723.892)">
                                      <g id="Raggruppa_3-2" data-name="Raggruppa 3" clip-path="url(#clip-path)">
                                        <path id="Tracciato_2-2" data-name="Tracciato 2"
                                              d="M56.568.018c11.473,0,22.948-.061,34.42.056a15.811,15.811,0,0,1,6.16,1.263c4.731,2.124,6.645,6.217,6.7,11.213.073,6.522.037,13.046,0,19.569a1.528,1.528,0,0,0,1.247,1.768,11.572,11.572,0,0,1,8.2,11.16q.042,16.169,0,32.339c0,2.207-1.7,3.546-3.2,2.46a3.145,3.145,0,0,1-1.032-2.083,44.87,44.87,0,0,1-.044-5.269c.037-1.186-.255-1.694-1.62-1.693q-50.728.06-101.457,0c-1.379,0-1.768.454-1.7,1.75.086,1.707.034,3.423.011,5.134a2.342,2.342,0,0,1-2.009,2.56A2.374,2.374,0,0,1,.02,77.728C.01,66.532-.027,55.336.037,44.14c.025-4.394,3.493-8.82,7.939-10.19,1.259-.388,1.506-.972,1.5-2.146-.049-6.338.153-12.682-.072-19.013C9.163,6.07,13.9-.051,22.287,0,33.713.071,45.141.018,56.568.018m-.049,56.9h50.649c1.819,0,1.829,0,1.835-1.863.009-2.868.033-5.736-.012-8.6a15.308,15.308,0,0,0-.335-3.434c-.841-3.378-3.807-5.479-7.574-5.479q-36.356,0-72.712,0c-5.5,0-11.009-.017-16.513.007A7.05,7.05,0,0,0,4.508,43.89c-.346,3.528-.2,7.106-.241,10.662-.027,2.369,0,2.371,2.3,2.371H56.519m5.106-23.705c0-3.925.186-7.671-.052-11.39a7.066,7.066,0,0,1,7.414-7.468c4.76.164,9.529.021,14.294.041,3.942.016,6.428,1.863,7.241,5.709.913,4.32,2.987,8.44,2.232,13.2,1.794,0,3.453-.068,5.1.022,1.222.066,1.581-.351,1.57-1.574-.057-6.43-.189-12.866.029-19.289a7.685,7.685,0,0,0-8.05-8.025c-23.177.1-46.355.045-69.533.047-5.107,0-8.11,2.977-8.122,8.07q-.021,9.368,0,18.736a3.356,3.356,0,0,0,.082,1.233c.129.312.515.721.8.73,1.927.064,3.857.034,5.751.034a18.312,18.312,0,0,1,.039-3.7c.761-3.468,1.649-6.91,2.576-10.339a6.547,6.547,0,0,1,6.221-4.838c5.227-.047,10.456-.064,15.682,0a6.665,6.665,0,0,1,6.779,6.91q.033,4.649-.006,9.3c-.008.845-.149,1.688-.234,2.582Zm-5.107,33.15h48.99c3.723,0,3.7,0,3.508-3.793-.05-.978-.4-1.468-1.415-1.4-.506.034-1.017,0-1.526,0q-49.267,0-98.534,0c-3.664,0-3.256-.425-3.274,3.245-.01,1.937.006,1.939,2.013,1.939H56.517M35.992,33.309c2.773,0,5.545.026,8.318-.009,1.91-.024,3.04-1,3.09-2.83q.123-4.5,0-9.009a2.62,2.62,0,0,0-2.924-2.8q-7.347-.031-14.694,0a2.561,2.561,0,0,0-2.787,2.176c-.745,2.9-1.5,5.8-2.18,8.717-.567,2.421.5,3.729,3,3.751,2.726.024,5.453.006,8.179,0m41.369,0c2.727,0,5.453.022,8.18-.007,2.388-.025,3.479-1.385,2.936-3.715-.68-2.915-1.425-5.815-2.172-8.714a2.651,2.651,0,0,0-2.909-2.219q-7.209-.019-14.419,0c-2.123.006-3.1,1.006-3.111,3.174q-.026,4.09,0,8.18c.017,2.289,1.013,3.28,3.316,3.3,2.727.021,5.453.005,8.18,0"
                                              transform="translate(0 0)"/>
                                      </g>
                                    </g>
                                  </g>
                                </svg>
                            </span>
                </div>

                <!-- Dettagli della Camera -->
                <div class="btr-room-details">
                    <h4 class="btr-room-name">Quadrupla</h4>
                </div>

                <!-- Contatore della Camera -->
                <div class="btr-room-qty">
                    <label for="btr_num_quadruple_max">Limita quantità <?=info_desc('Indica il numero massimo di camere quadruple per limitare le disponibilità, lascia 0 per non limitare'); ?>
                        <div class="qty-input">
                            <button class="qty-count qty-count--minus" data-action="minus" type="button">-</button>
                            <input class="product-qty room-input" type="number" id="btr_num_quadruple_max" name="btr_num_quadruple_max" min="0"
                                   value="<?=$btr_num_quadruple_max?$btr_num_quadruple_max : '0'; ?>">
                            <button class="qty-count qty-count--add" data-action="add" type="button">+</button>
                        </div>
                </div>

                <!-- Campo Supplemento -->
                <div class="btr-room-supplement">
                    <label for="btr_supplemento_quadruple_max">Supp. persona (€) <?= info_desc('Supplemento a persona, il totale supplemento viene ricalcolato dinamicamente frontend'); ?></label>
                    <input type="number" id="btr_supplemento_quadruple_max" name="btr_supplemento_quadruple_max" value="<?php echo esc_attr($btr_supplemento_quadruple_max); ?>" step="0.05"
                           min="0" />
                </div>
                <!-- Prezzo per persona -->
                <div class="btr-room-perperson">
                    <label>Prezzo per persona (€)</label>
                    <input type="number"
                           class="btr-prezzo-persona"
                           data-persone="<?php
                           echo match ('quadrupla') {
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
                           if (isset($camere['doppia']['prezzo_per_persona']) && $camere['doppia']['prezzo_per_persona'] !== '') {
                               echo esc_attr($camere['doppia']['prezzo_per_persona']);
                           } elseif (isset($camere['doppia']['prezzo'])) {
                               $posti = match ('doppia') {
                                   'singola' => 1,
                                   'doppia' => 2,
                                   'tripla' => 3,
                                   'quadrupla' => 4,
                                   'quintupla' => 5,
                                   'condivisa' => 1,
                                   default => 1
                               };
                               echo esc_attr(number_format(floatval($camere['doppia']['prezzo']) / $posti, 2, '.', ''));
                           }
                           ?>">
                </div>
                <!-- Campo Prezzo -->
                <div class="btr-room-pricing">
                    <label for="btr_prezzo_quadruple_max">Prezzo camera (€)</label>
                    <input type="number" id="btr_prezzo_quadruple_max" name="btr_prezzo_quadruple_max" value="<?php echo esc_attr($btr_prezzo_quadruple_max); ?>" step="0.05" min="0" />
                </div>

                <!-- Campo Sconto -->
                <div class="btr-room-discount">
                    <label for="btr_sconto_quadruple_max">Sconto (%)</label>
                    <input type="number" id="btr_sconto_quadruple_max" name="btr_sconto_quadruple_max" value="<?php echo esc_attr($btr_sconto_quadruple_max); ?>" step="0.05" min="0"
                           max="100" />
                </div>

                <!-- Checkbox per Escludere la Tipologia di Camera -->
                <div class="room-exclude">

                    <div class="btr-switch-container">

                        <label for="btr_exclude_quadruple_max" class="label_btr_exclude_quadruple_max">Escludi camera</label>
                        <input type="checkbox" id="btr_exclude_quadruple_max" name="btr_exclude_quadruple_max" value="on" <?php checked($btr_exclude_quadruple_max, 'on'); ?> />
                        <label class="btr-switch" for="btr_exclude_quadruple_max">
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
            </div>


            <!-- Scheda Camera Quintupla -->
            <div class="btr-room-row btr-quintupla">
                <!-- Icona della Camera -->
                <div class="btr-room-icon">
                            <span class="room-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="262.317" height="62.416" viewBox="0 0 262.317 62.416">
                                  <defs>
                                    <clipPath id="clip-path">
                                      <rect id="Rettangolo_1" data-name="Rettangolo 1" width="68.061" height="62.416" fill="none"/>
                                    </clipPath>
                                    <clipPath id="clip-path-2">
                                      <rect id="Rettangolo_2" data-name="Rettangolo 2" width="87.367" height="61.875" fill="none"/>
                                    </clipPath>
                                  </defs>
                                  <g id="Raggruppa_7" data-name="Raggruppa 7" transform="translate(58.25 -803.28)">
                                    <g id="Raggruppa_2" data-name="Raggruppa 2" transform="translate(136.006 803.28)">
                                      <g id="Raggruppa_1" data-name="Raggruppa 1" clip-path="url(#clip-path)">
                                        <path id="Tracciato_1" data-name="Tracciato 1"
                                              d="M67.936,54.483h0a.5.5,0,0,0-.042-.075V51.4q0-8.025,0-16.05a7.022,7.022,0,0,0-4.66-6.571c-.728-.237-.927-.519-.922-1.313.041-7.628.043-13.919.006-19.8C62.3,4.5,60.971,2.191,58.485,1A10.264,10.264,0,0,0,54.261.055C49.281-.012,44.217,0,39.321.007q-5.7.011-11.391,0c-4.7,0-9.555-.005-14.334.007-4.9.013-7.94,3.069-7.94,7.976v4.172c0,5.051,0,10.274.011,15.411,0,.682-.1.942-.708,1.142A7.061,7.061,0,0,0,.091,35.569q0,7.7,0,15.41v3.436l-.014.018L0,54.543l.034.129a2.391,2.391,0,0,1,.057.41v3.137q0,1.248,0,2.5A1.756,1.756,0,0,0,.553,62a1.474,1.474,0,0,0,1.1.417c.884,0,1.433-.65,1.435-1.686q0-.7,0-1.4v-.149a6.9,6.9,0,0,0,.375-2.391,7.051,7.051,0,0,1,1.185-.053H63.4c.081,0,.161,0,.242,0,.171,0,.347.008.526,0a.939.939,0,0,1,.529.081,8.852,8.852,0,0,0,.156,2.225.92.92,0,0,0,.059.123c0,.373.007.745.012,1.117l.007.571a1.417,1.417,0,0,0,1.424,1.568h.005a1.5,1.5,0,0,0,1.536-1.6c0-.141,0-.281,0-.423V59.317a.493.493,0,0,0,.033-.053,22.3,22.3,0,0,0,.009-4.781M49.965,27.633a7.139,7.139,0,0,0,.65-5.169c-.379-1.682-.821-3.48-1.352-5.5a7.2,7.2,0,0,0-7.312-5.613q-7.916,0-15.831,0A7.216,7.216,0,0,0,18.683,17.2q-.275,1.116-.556,2.231c-.237.945-.474,1.89-.7,2.837a7.326,7.326,0,0,0,.59,5.312,2.662,2.662,0,0,0,.113.6H8.859c0-.046-.005-.091-.008-.137-.016-.267-.031-.519-.031-.768V25.416l.055-.057-.005-.122c-.055-1.181-.02-1.983.006-2.569a3.094,3.094,0,0,0-.055-1.134V19.97q0-6.294,0-12.588a3.987,3.987,0,0,1,4.216-4.2c13.722-.007,27.829-.007,41.93,0a3.989,3.989,0,0,1,4.2,4.218q.007,7.134,0,14.268v6.539H49.675c.1-.212.192-.4.289-.573M20.581,25.182l.893-.483-1.194-.034a4.723,4.723,0,0,1,.091-1.461c.4-1.847.874-3.758,1.4-5.68a3.932,3.932,0,0,1,3.979-3q8.239-.007,16.478,0a4.028,4.028,0,0,1,4.063,3.19c.462,1.777.908,3.6,1.365,5.583a4.149,4.149,0,0,1,.055,1.631l-.342-.049-.1,1.319a3.991,3.991,0,0,1-3.519,2.066c-2.286.02-4.611.015-6.86.011q-1.491,0-2.982,0l-2.582,0q-3.417,0-6.833,0a4.131,4.131,0,0,1-3.8-2.148c-.005-.22-.008-.442,0-.665l0-.256ZM3.082,49.256a1.018,1.018,0,0,1,.157-.753,1.075,1.075,0,0,1,.776-.183c2.1.039,4.242.032,6.31.025q1.233,0,2.466-.005h50.8a2.731,2.731,0,0,1,1.2.115,2.557,2.557,0,0,1,.117,1.164q0,1.443,0,2.886a2.59,2.59,0,0,1-.107,1.113,2.432,2.432,0,0,1-1.081.1H26.559q-11.15,0-22.3,0a2.421,2.421,0,0,1-1.053-.105,2.345,2.345,0,0,1-.11-1.034c0-.381,0-.762.006-1.143.007-.715.015-1.454-.019-2.183M64.753,45a1.208,1.208,0,0,1-.854.2c-7.753-.029-15.635-.026-23.258-.024l-6.7,0-6.692,0c-7.555,0-15.367-.006-23.05.025-.5,0-.8-.063-.942-.21a1.205,1.205,0,0,1-.188-.859c.041-1.689.034-3.406.027-5.066,0-1.058-.009-2.117,0-3.175.02-2.836,1.672-4.463,4.534-4.463H60.374c2.837,0,4.488,1.636,4.53,4.488.019,1.267.014,2.556.009,3.8-.005,1.453-.011,2.956.02,4.435a1.185,1.185,0,0,1-.181.846"
                                              transform="translate(0 0)"/>
                                      </g>
                                    </g>
                                    <g id="Raggruppa_6" data-name="Raggruppa 6" transform="translate(-58.25 803.821)">
                                      <g id="Raggruppa_4" data-name="Raggruppa 4" transform="translate(0 0)">
                                        <g id="Raggruppa_3" data-name="Raggruppa 3" clip-path="url(#clip-path-2)">
                                          <path id="Tracciato_2" data-name="Tracciato 2"
                                                d="M43.616.014c8.846,0,17.693-.047,26.539.043a12.191,12.191,0,0,1,4.749.974c3.648,1.637,5.123,4.793,5.167,8.646.057,5.029.029,10.059,0,15.089a1.179,1.179,0,0,0,.962,1.364,8.922,8.922,0,0,1,6.324,8.6q.033,12.467,0,24.934c0,1.7-1.314,2.734-2.471,1.9a2.425,2.425,0,0,1-.8-1.606,34.6,34.6,0,0,1-.034-4.063c.029-.914-.2-1.306-1.249-1.305q-39.113.046-78.226,0c-1.064,0-1.363.35-1.312,1.349.066,1.316.026,2.639.009,3.959A1.806,1.806,0,0,1,1.73,61.874,1.83,1.83,0,0,1,.015,59.93C.008,51.3-.021,42.666.029,34.033A8.794,8.794,0,0,1,6.15,26.176c.97-.3,1.162-.749,1.154-1.655-.038-4.887.118-9.779-.055-14.659C7.065,4.68,10.718-.04,17.184,0,25.994.055,34.8.014,43.616.014m-.038,43.874H82.629c1.4,0,1.41,0,1.415-1.436.007-2.211.025-4.422-.009-6.633a11.8,11.8,0,0,0-.259-2.648,5.685,5.685,0,0,0-5.839-4.225q-28.032,0-56.063,0c-4.244,0-8.488-.013-12.732.005a5.435,5.435,0,0,0-5.666,4.89c-.267,2.72-.154,5.479-.186,8.221-.021,1.827,0,1.828,1.771,1.828H43.578m3.937-18.277c0-3.026.143-5.915-.04-8.782a5.448,5.448,0,0,1,5.716-5.758c3.67.126,7.347.017,11.021.031,3.04.012,4.956,1.436,5.583,4.4.7,3.331,2.3,6.508,1.721,10.179,1.383,0,2.662-.052,3.935.017.942.051,1.219-.27,1.21-1.214-.044-4.958-.146-9.92.023-14.873a5.925,5.925,0,0,0-6.206-6.187c-17.87.079-35.741.035-53.612.037-3.937,0-6.253,2.3-6.262,6.222q-.016,7.223,0,14.446a2.588,2.588,0,0,0,.063.951c.1.24.4.556.616.563,1.486.049,2.974.026,4.434.026a14.119,14.119,0,0,1,.03-2.849c.586-2.674,1.271-5.328,1.986-7.972a5.048,5.048,0,0,1,4.8-3.73c4.03-.036,8.062-.049,12.092,0a5.139,5.139,0,0,1,5.227,5.328q.025,3.585,0,7.169c-.006.651-.115,1.3-.18,1.991Zm-3.938,25.56H81.348c2.87,0,2.854,0,2.7-2.924-.038-.754-.3-1.132-1.091-1.078-.391.027-.784,0-1.177,0q-37.986,0-75.972,0c-2.825,0-2.51-.328-2.524,2.5-.007,1.493,0,1.495,1.552,1.495H43.576M27.751,25.682c2.138,0,4.276.02,6.413-.007a2.117,2.117,0,0,0,2.382-2.182q.095-3.471,0-6.946a2.02,2.02,0,0,0-2.255-2.162q-5.665-.024-11.33,0a1.975,1.975,0,0,0-2.149,1.678c-.575,2.237-1.154,4.473-1.681,6.721-.437,1.867.386,2.875,2.316,2.892,2.1.018,4.2,0,6.306,0m31.9,0c2.1,0,4.2.017,6.307-.005,1.841-.019,2.682-1.068,2.263-2.865-.524-2.248-1.1-4.484-1.675-6.719A2.044,2.044,0,0,0,64.3,14.383q-5.559-.015-11.117,0c-1.637,0-2.388.775-2.4,2.447q-.02,3.153,0,6.307c.013,1.765.781,2.529,2.556,2.542,2.1.016,4.2,0,6.307,0"
                                                transform="translate(0 0)"/>
                                        </g>
                                      </g>
                                      <g id="Raggruppa_5" data-name="Raggruppa 5" transform="translate(97.699 0)">
                                        <g id="Raggruppa_3-2" data-name="Raggruppa 3" clip-path="url(#clip-path-2)">
                                          <path id="Tracciato_2-2" data-name="Tracciato 2"
                                                d="M43.616.014c8.846,0,17.693-.047,26.539.043a12.191,12.191,0,0,1,4.749.974c3.648,1.637,5.123,4.793,5.167,8.646.057,5.029.029,10.059,0,15.089a1.179,1.179,0,0,0,.962,1.364,8.922,8.922,0,0,1,6.324,8.6q.033,12.467,0,24.934c0,1.7-1.314,2.734-2.471,1.9a2.425,2.425,0,0,1-.8-1.606,34.6,34.6,0,0,1-.034-4.063c.029-.914-.2-1.306-1.249-1.305q-39.113.046-78.226,0c-1.064,0-1.363.35-1.312,1.349.066,1.316.026,2.639.009,3.959A1.806,1.806,0,0,1,1.73,61.874,1.83,1.83,0,0,1,.015,59.93C.008,51.3-.021,42.666.029,34.033A8.794,8.794,0,0,1,6.15,26.176c.97-.3,1.162-.749,1.154-1.655-.038-4.887.118-9.779-.055-14.659C7.065,4.68,10.718-.04,17.184,0,25.994.055,34.8.014,43.616.014m-.038,43.874H82.629c1.4,0,1.41,0,1.415-1.436.007-2.211.025-4.422-.009-6.633a11.8,11.8,0,0,0-.259-2.648,5.685,5.685,0,0,0-5.839-4.225q-28.032,0-56.063,0c-4.244,0-8.488-.013-12.732.005a5.435,5.435,0,0,0-5.666,4.89c-.267,2.72-.154,5.479-.186,8.221-.021,1.827,0,1.828,1.771,1.828H43.578m3.937-18.277c0-3.026.143-5.915-.04-8.782a5.448,5.448,0,0,1,5.716-5.758c3.67.126,7.347.017,11.021.031,3.04.012,4.956,1.436,5.583,4.4.7,3.331,2.3,6.508,1.721,10.179,1.383,0,2.662-.052,3.935.017.942.051,1.219-.27,1.21-1.214-.044-4.958-.146-9.92.023-14.873a5.925,5.925,0,0,0-6.206-6.187c-17.87.079-35.741.035-53.612.037-3.937,0-6.253,2.3-6.262,6.222q-.016,7.223,0,14.446a2.588,2.588,0,0,0,.063.951c.1.24.4.556.616.563,1.486.049,2.974.026,4.434.026a14.119,14.119,0,0,1,.03-2.849c.586-2.674,1.271-5.328,1.986-7.972a5.048,5.048,0,0,1,4.8-3.73c4.03-.036,8.062-.049,12.092,0a5.139,5.139,0,0,1,5.227,5.328q.025,3.585,0,7.169c-.006.651-.115,1.3-.18,1.991Zm-3.938,25.56H81.348c2.87,0,2.854,0,2.7-2.924-.038-.754-.3-1.132-1.091-1.078-.391.027-.784,0-1.177,0q-37.986,0-75.972,0c-2.825,0-2.51-.328-2.524,2.5-.007,1.493,0,1.495,1.552,1.495H43.576M27.751,25.682c2.138,0,4.276.02,6.413-.007a2.117,2.117,0,0,0,2.382-2.182q.095-3.471,0-6.946a2.02,2.02,0,0,0-2.255-2.162q-5.665-.024-11.33,0a1.975,1.975,0,0,0-2.149,1.678c-.575,2.237-1.154,4.473-1.681,6.721-.437,1.867.386,2.875,2.316,2.892,2.1.018,4.2,0,6.306,0m31.9,0c2.1,0,4.2.017,6.307-.005,1.841-.019,2.682-1.068,2.263-2.865-.524-2.248-1.1-4.484-1.675-6.719A2.044,2.044,0,0,0,64.3,14.383q-5.559-.015-11.117,0c-1.637,0-2.388.775-2.4,2.447q-.02,3.153,0,6.307c.013,1.765.781,2.529,2.556,2.542,2.1.016,4.2,0,6.307,0"
                                                transform="translate(0 0)"/>
                                        </g>
                                      </g>
                                    </g>
                                  </g>
                                </svg>
                            </span>
                </div>

                <!-- Dettagli della Camera -->
                <div class="btr-room-details">
                    <h4 class="btr-room-name">Quintupla</h4>
                </div>

                <!-- Contatore della Camera -->
                <div class="btr-room-qty">
                    <label for="btr_num_quintuple_max">Limita quantità <?=info_desc('Indica il numero massimo di camere quintuple per limitare le disponibilità, lascia 0 per non limitare'); ?>
                        <div class="qty-input">
                            <button class="qty-count qty-count--minus" data-action="minus" type="button">-</button>
                            <input class="product-qty room-input" type="number" id="btr_num_quintuple_max" name="btr_num_quintuple_max" min="0"
                                   value="<?=$btr_num_quintuple_max?$btr_num_quintuple_max : '0'; ?>">
                            <button class="qty-count qty-count--add" data-action="add" type="button">+</button>
                        </div>
                </div>


                <!-- Campo Supplemento -->
                <div class="btr-room-supplement">
                    <label for="btr_supplemento_quintuple_max">Supp. persona (€) <?= info_desc('Supplemento a persona, il totale supplemento viene ricalcolato dinamicamente frontend'); ?></label>
                    <input type="number" id="btr_supplemento_quintuple_max" name="btr_supplemento_quintuple_max" value="<?php echo esc_attr($btr_supplemento_quintuple_max); ?>" step="0.05"
                           min="0" />
                </div>
                <!-- Prezzo per persona -->
                <div class="btr-room-perperson">
                    <label>Prezzo per persona (€)</label>
                    <input type="number"
                           class="btr-prezzo-persona"
                           data-persone="<?php
                           echo match ('quintupla') {
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
                           if (isset($camere['doppia']['prezzo_per_persona']) && $camere['doppia']['prezzo_per_persona'] !== '') {
                               echo esc_attr($camere['doppia']['prezzo_per_persona']);
                           } elseif (isset($camere['doppia']['prezzo'])) {
                               $posti = match ('doppia') {
                                   'singola' => 1,
                                   'doppia' => 2,
                                   'tripla' => 3,
                                   'quadrupla' => 4,
                                   'quintupla' => 5,
                                   'condivisa' => 1,
                                   default => 1
                               };
                               echo esc_attr(number_format(floatval($camere['doppia']['prezzo']) / $posti, 2, '.', ''));
                           }
                           ?>">
                </div>
                <!-- Campo Prezzo -->
                <div class="btr-room-pricing">
                    <label for="btr_prezzo_quintuple_max">Prezzo camera (€)</label>
                    <input type="number" id="btr_prezzo_quintuple_max" name="btr_prezzo_quintuple_max" value="<?php echo esc_attr($btr_prezzo_quintuple_max); ?>" step="0.05" min="0" />
                </div>

                <!-- Campo Sconto -->
                <div class="btr-room-discount">
                    <label for="btr_sconto_quintuple_max">Sconto (%)</label>
                    <input type="number" id="btr_sconto_quintuple_max" name="btr_sconto_quintuple_max" value="<?php echo esc_attr($btr_sconto_quintuple_max); ?>" step="0.05" min="0"
                           max="100" />
                </div>
                <!-- Checkbox per Escludere la Tipologia di Camera -->
                <div class="room-exclude">

                    <div class="btr-switch-container">

                        <label for="btr_exclude_quintuple_max" class="label_btr_exclude_quintuple_max">Escludi camera</label>
                        <input type="checkbox" id="btr_exclude_quintuple_max" name="btr_exclude_quintuple_max" value="on" <?php checked($btr_exclude_quintuple_max, 'on'); ?> />
                        <label class="btr-switch" for="btr_exclude_quintuple_max">
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

            </div>

            <!-- Scheda Camera Quintupla -->
            <div class="btr-room-row btr-quintupla">
                <!-- Icona della Camera -->
                <div class="btr-room-icon">
                            <span class="room-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="262.317" height="62.416" viewBox="0 0 262.317 62.416">
                                  <defs>
                                    <clipPath id="clip-path">
                                      <rect id="Rettangolo_1" data-name="Rettangolo 1" width="68.061" height="62.416" fill="none"/>
                                    </clipPath>
                                    <clipPath id="clip-path-2">
                                      <rect id="Rettangolo_2" data-name="Rettangolo 2" width="87.367" height="61.875" fill="none"/>
                                    </clipPath>
                                  </defs>
                                  <g id="Raggruppa_7" data-name="Raggruppa 7" transform="translate(58.25 -803.28)">
                                    <g id="Raggruppa_2" data-name="Raggruppa 2" transform="translate(136.006 803.28)">
                                      <g id="Raggruppa_1" data-name="Raggruppa 1" clip-path="url(#clip-path)">
                                        <path id="Tracciato_1" data-name="Tracciato 1"
                                              d="M67.936,54.483h0a.5.5,0,0,0-.042-.075V51.4q0-8.025,0-16.05a7.022,7.022,0,0,0-4.66-6.571c-.728-.237-.927-.519-.922-1.313.041-7.628.043-13.919.006-19.8C62.3,4.5,60.971,2.191,58.485,1A10.264,10.264,0,0,0,54.261.055C49.281-.012,44.217,0,39.321.007q-5.7.011-11.391,0c-4.7,0-9.555-.005-14.334.007-4.9.013-7.94,3.069-7.94,7.976v4.172c0,5.051,0,10.274.011,15.411,0,.682-.1.942-.708,1.142A7.061,7.061,0,0,0,.091,35.569q0,7.7,0,15.41v3.436l-.014.018L0,54.543l.034.129a2.391,2.391,0,0,1,.057.41v3.137q0,1.248,0,2.5A1.756,1.756,0,0,0,.553,62a1.474,1.474,0,0,0,1.1.417c.884,0,1.433-.65,1.435-1.686q0-.7,0-1.4v-.149a6.9,6.9,0,0,0,.375-2.391,7.051,7.051,0,0,1,1.185-.053H63.4c.081,0,.161,0,.242,0,.171,0,.347.008.526,0a.939.939,0,0,1,.529.081,8.852,8.852,0,0,0,.156,2.225.92.92,0,0,0,.059.123c0,.373.007.745.012,1.117l.007.571a1.417,1.417,0,0,0,1.424,1.568h.005a1.5,1.5,0,0,0,1.536-1.6c0-.141,0-.281,0-.423V59.317a.493.493,0,0,0,.033-.053,22.3,22.3,0,0,0,.009-4.781M49.965,27.633a7.139,7.139,0,0,0,.65-5.169c-.379-1.682-.821-3.48-1.352-5.5a7.2,7.2,0,0,0-7.312-5.613q-7.916,0-15.831,0A7.216,7.216,0,0,0,18.683,17.2q-.275,1.116-.556,2.231c-.237.945-.474,1.89-.7,2.837a7.326,7.326,0,0,0,.59,5.312,2.662,2.662,0,0,0,.113.6H8.859c0-.046-.005-.091-.008-.137-.016-.267-.031-.519-.031-.768V25.416l.055-.057-.005-.122c-.055-1.181-.02-1.983.006-2.569a3.094,3.094,0,0,0-.055-1.134V19.97q0-6.294,0-12.588a3.987,3.987,0,0,1,4.216-4.2c13.722-.007,27.829-.007,41.93,0a3.989,3.989,0,0,1,4.2,4.218q.007,7.134,0,14.268v6.539H49.675c.1-.212.192-.4.289-.573M20.581,25.182l.893-.483-1.194-.034a4.723,4.723,0,0,1,.091-1.461c.4-1.847.874-3.758,1.4-5.68a3.932,3.932,0,0,1,3.979-3q8.239-.007,16.478,0a4.028,4.028,0,0,1,4.063,3.19c.462,1.777.908,3.6,1.365,5.583a4.149,4.149,0,0,1,.055,1.631l-.342-.049-.1,1.319a3.991,3.991,0,0,1-3.519,2.066c-2.286.02-4.611.015-6.86.011q-1.491,0-2.982,0l-2.582,0q-3.417,0-6.833,0a4.131,4.131,0,0,1-3.8-2.148c-.005-.22-.008-.442,0-.665l0-.256ZM3.082,49.256a1.018,1.018,0,0,1,.157-.753,1.075,1.075,0,0,1,.776-.183c2.1.039,4.242.032,6.31.025q1.233,0,2.466-.005h50.8a2.731,2.731,0,0,1,1.2.115,2.557,2.557,0,0,1,.117,1.164q0,1.443,0,2.886a2.59,2.59,0,0,1-.107,1.113,2.432,2.432,0,0,1-1.081.1H26.559q-11.15,0-22.3,0a2.421,2.421,0,0,1-1.053-.105,2.345,2.345,0,0,1-.11-1.034c0-.381,0-.762.006-1.143.007-.715.015-1.454-.019-2.183M64.753,45a1.208,1.208,0,0,1-.854.2c-7.753-.029-15.635-.026-23.258-.024l-6.7,0-6.692,0c-7.555,0-15.367-.006-23.05.025-.5,0-.8-.063-.942-.21a1.205,1.205,0,0,1-.188-.859c.041-1.689.034-3.406.027-5.066,0-1.058-.009-2.117,0-3.175.02-2.836,1.672-4.463,4.534-4.463H60.374c2.837,0,4.488,1.636,4.53,4.488.019,1.267.014,2.556.009,3.8-.005,1.453-.011,2.956.02,4.435a1.185,1.185,0,0,1-.181.846"
                                              transform="translate(0 0)"/>
                                      </g>
                                    </g>
                                    <g id="Raggruppa_6" data-name="Raggruppa 6" transform="translate(-58.25 803.821)">
                                      <g id="Raggruppa_4" data-name="Raggruppa 4" transform="translate(0 0)">
                                        <g id="Raggruppa_3" data-name="Raggruppa 3" clip-path="url(#clip-path-2)">
                                          <path id="Tracciato_2" data-name="Tracciato 2"
                                                d="M43.616.014c8.846,0,17.693-.047,26.539.043a12.191,12.191,0,0,1,4.749.974c3.648,1.637,5.123,4.793,5.167,8.646.057,5.029.029,10.059,0,15.089a1.179,1.179,0,0,0,.962,1.364,8.922,8.922,0,0,1,6.324,8.6q.033,12.467,0,24.934c0,1.7-1.314,2.734-2.471,1.9a2.425,2.425,0,0,1-.8-1.606,34.6,34.6,0,0,1-.034-4.063c.029-.914-.2-1.306-1.249-1.305q-39.113.046-78.226,0c-1.064,0-1.363.35-1.312,1.349.066,1.316.026,2.639.009,3.959A1.806,1.806,0,0,1,1.73,61.874,1.83,1.83,0,0,1,.015,59.93C.008,51.3-.021,42.666.029,34.033A8.794,8.794,0,0,1,6.15,26.176c.97-.3,1.162-.749,1.154-1.655-.038-4.887.118-9.779-.055-14.659C7.065,4.68,10.718-.04,17.184,0,25.994.055,34.8.014,43.616.014m-.038,43.874H82.629c1.4,0,1.41,0,1.415-1.436.007-2.211.025-4.422-.009-6.633a11.8,11.8,0,0,0-.259-2.648,5.685,5.685,0,0,0-5.839-4.225q-28.032,0-56.063,0c-4.244,0-8.488-.013-12.732.005a5.435,5.435,0,0,0-5.666,4.89c-.267,2.72-.154,5.479-.186,8.221-.021,1.827,0,1.828,1.771,1.828H43.578m3.937-18.277c0-3.026.143-5.915-.04-8.782a5.448,5.448,0,0,1,5.716-5.758c3.67.126,7.347.017,11.021.031,3.04.012,4.956,1.436,5.583,4.4.7,3.331,2.3,6.508,1.721,10.179,1.383,0,2.662-.052,3.935.017.942.051,1.219-.27,1.21-1.214-.044-4.958-.146-9.92.023-14.873a5.925,5.925,0,0,0-6.206-6.187c-17.87.079-35.741.035-53.612.037-3.937,0-6.253,2.3-6.262,6.222q-.016,7.223,0,14.446a2.588,2.588,0,0,0,.063.951c.1.24.4.556.616.563,1.486.049,2.974.026,4.434.026a14.119,14.119,0,0,1,.03-2.849c.586-2.674,1.271-5.328,1.986-7.972a5.048,5.048,0,0,1,4.8-3.73c4.03-.036,8.062-.049,12.092,0a5.139,5.139,0,0,1,5.227,5.328q.025,3.585,0,7.169c-.006.651-.115,1.3-.18,1.991Zm-3.938,25.56H81.348c2.87,0,2.854,0,2.7-2.924-.038-.754-.3-1.132-1.091-1.078-.391.027-.784,0-1.177,0q-37.986,0-75.972,0c-2.825,0-2.51-.328-2.524,2.5-.007,1.493,0,1.495,1.552,1.495H43.576M27.751,25.682c2.138,0,4.276.02,6.413-.007a2.117,2.117,0,0,0,2.382-2.182q.095-3.471,0-6.946a2.02,2.02,0,0,0-2.255-2.162q-5.665-.024-11.33,0a1.975,1.975,0,0,0-2.149,1.678c-.575,2.237-1.154,4.473-1.681,6.721-.437,1.867.386,2.875,2.316,2.892,2.1.018,4.2,0,6.306,0m31.9,0c2.1,0,4.2.017,6.307-.005,1.841-.019,2.682-1.068,2.263-2.865-.524-2.248-1.1-4.484-1.675-6.719A2.044,2.044,0,0,0,64.3,14.383q-5.559-.015-11.117,0c-1.637,0-2.388.775-2.4,2.447q-.02,3.153,0,6.307c.013,1.765.781,2.529,2.556,2.542,2.1.016,4.2,0,6.307,0"
                                                transform="translate(0 0)"/>
                                        </g>
                                      </g>
                                      <g id="Raggruppa_5" data-name="Raggruppa 5" transform="translate(97.699 0)">
                                        <g id="Raggruppa_3-2" data-name="Raggruppa 3" clip-path="url(#clip-path-2)">
                                          <path id="Tracciato_2-2" data-name="Tracciato 2"
                                                d="M43.616.014c8.846,0,17.693-.047,26.539.043a12.191,12.191,0,0,1,4.749.974c3.648,1.637,5.123,4.793,5.167,8.646.057,5.029.029,10.059,0,15.089a1.179,1.179,0,0,0,.962,1.364,8.922,8.922,0,0,1,6.324,8.6q.033,12.467,0,24.934c0,1.7-1.314,2.734-2.471,1.9a2.425,2.425,0,0,1-.8-1.606,34.6,34.6,0,0,1-.034-4.063c.029-.914-.2-1.306-1.249-1.305q-39.113.046-78.226,0c-1.064,0-1.363.35-1.312,1.349.066,1.316.026,2.639.009,3.959A1.806,1.806,0,0,1,1.73,61.874,1.83,1.83,0,0,1,.015,59.93C.008,51.3-.021,42.666.029,34.033A8.794,8.794,0,0,1,6.15,26.176c.97-.3,1.162-.749,1.154-1.655-.038-4.887.118-9.779-.055-14.659C7.065,4.68,10.718-.04,17.184,0,25.994.055,34.8.014,43.616.014m-.038,43.874H82.629c1.4,0,1.41,0,1.415-1.436.007-2.211.025-4.422-.009-6.633a11.8,11.8,0,0,0-.259-2.648,5.685,5.685,0,0,0-5.839-4.225q-28.032,0-56.063,0c-4.244,0-8.488-.013-12.732.005a5.435,5.435,0,0,0-5.666,4.89c-.267,2.72-.154,5.479-.186,8.221-.021,1.827,0,1.828,1.771,1.828H43.578m3.937-18.277c0-3.026.143-5.915-.04-8.782a5.448,5.448,0,0,1,5.716-5.758c3.67.126,7.347.017,11.021.031,3.04.012,4.956,1.436,5.583,4.4.7,3.331,2.3,6.508,1.721,10.179,1.383,0,2.662-.052,3.935.017.942.051,1.219-.27,1.21-1.214-.044-4.958-.146-9.92.023-14.873a5.925,5.925,0,0,0-6.206-6.187c-17.87.079-35.741.035-53.612.037-3.937,0-6.253,2.3-6.262,6.222q-.016,7.223,0,14.446a2.588,2.588,0,0,0,.063.951c.1.24.4.556.616.563,1.486.049,2.974.026,4.434.026a14.119,14.119,0,0,1,.03-2.849c.586-2.674,1.271-5.328,1.986-7.972a5.048,5.048,0,0,1,4.8-3.73c4.03-.036,8.062-.049,12.092,0a5.139,5.139,0,0,1,5.227,5.328q.025,3.585,0,7.169c-.006.651-.115,1.3-.18,1.991Zm-3.938,25.56H81.348c2.87,0,2.854,0,2.7-2.924-.038-.754-.3-1.132-1.091-1.078-.391.027-.784,0-1.177,0q-37.986,0-75.972,0c-2.825,0-2.51-.328-2.524,2.5-.007,1.493,0,1.495,1.552,1.495H43.576M27.751,25.682c2.138,0,4.276.02,6.413-.007a2.117,2.117,0,0,0,2.382-2.182q.095-3.471,0-6.946a2.02,2.02,0,0,0-2.255-2.162q-5.665-.024-11.33,0a1.975,1.975,0,0,0-2.149,1.678c-.575,2.237-1.154,4.473-1.681,6.721-.437,1.867.386,2.875,2.316,2.892,2.1.018,4.2,0,6.306,0m31.9,0c2.1,0,4.2.017,6.307-.005,1.841-.019,2.682-1.068,2.263-2.865-.524-2.248-1.1-4.484-1.675-6.719A2.044,2.044,0,0,0,64.3,14.383q-5.559-.015-11.117,0c-1.637,0-2.388.775-2.4,2.447q-.02,3.153,0,6.307c.013,1.765.781,2.529,2.556,2.542,2.1.016,4.2,0,6.307,0"
                                                transform="translate(0 0)"/>
                                        </g>
                                      </g>
                                    </g>
                                  </g>
                                </svg>
                            </span>
                </div>

                <!-- Dettagli della Camera -->
                <div class="btr-room-details">
                    <h4 class="btr-room-name">Condivisa</h4>
                </div>

                <!-- Contatore della Camera -->
                <div class="btr-room-qty">
                    <label for="btr_num_quintuple_max">Limita quantità <?=info_desc('Indica il numero massimo di camere condivise per limitare le disponibilità, lascia 0 per non limitare'); ?>
                        <div class="qty-input">
                            <button class="qty-count qty-count--minus" data-action="minus" type="button">-</button>
                            <input class="product-qty room-input" type="number" id="btr_num_condivisa_max" name="btr_num_condivisa_max" min="0"
                                   value="<?=$btr_num_condivisa_max?$btr_num_condivisa_max : '0'; ?>">
                            <button class="qty-count qty-count--add" data-action="add" type="button">+</button>
                        </div>
                </div>


                <!-- Campo Supplemento -->
                <div class="btr-room-supplement">
                    <label for="btr_supplemento_condivisa_max">Supp. persona (€) <?= info_desc('Supplemento a persona, il totale supplemento viene ricalcolato dinamicamente frontend'); ?></label>
                    <input type="number" id="btr_supplemento_condivisa_max" name="btr_supplemento_condivisa_max" value="<?php echo esc_attr($btr_supplemento_condivisa_max); ?>" step="0.05"
                           min="0" />
                </div>
                <!-- Prezzo per persona -->
                <div class="btr-room-perperson">
                    <label>Prezzo per persona (€)</label>
                    <input type="number"
                           class="btr-prezzo-persona"
                           data-persone="<?php
                           echo match ('condivisa') {
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
                           if (isset($camere['doppia']['prezzo_per_persona']) && $camere['doppia']['prezzo_per_persona'] !== '') {
                               echo esc_attr($camere['doppia']['prezzo_per_persona']);
                           } elseif (isset($camere['doppia']['prezzo'])) {
                               $posti = match ('doppia') {
                                   'singola' => 1,
                                   'doppia' => 2,
                                   'tripla' => 3,
                                   'quadrupla' => 4,
                                   'quintupla' => 5,
                                   'condivisa' => 1,
                                   default => 1
                               };
                               echo esc_attr(number_format(floatval($camere['doppia']['prezzo']) / $posti, 2, '.', ''));
                           }
                           ?>">
                </div>
                <!-- Campo Prezzo -->
                <div class="btr-room-pricing">
                    <label for="btr_prezzo_condivisa_max">Prezzo camera (€)</label>
                    <input type="number" id="btr_prezzo_condivisa_max" name="btr_prezzo_condivisa_max" value="<?php echo esc_attr($btr_prezzo_condivisa_max); ?>" step="0.05" min="0" />
                </div>

                <!-- Campo Sconto -->
                <div class="btr-room-discount">
                    <label for="btr_sconto_condivisa_max">Sconto (%)</label>
                    <input type="number" id="btr_sconto_condivisa_max" name="btr_sconto_condivisa_max" value="<?php echo esc_attr($btr_sconto_condivisa_max); ?>" step="0.05" min="0"
                           max="100" />
                </div>
                <!-- Checkbox per Escludere la Tipologia di Camera -->
                <div class="room-exclude">

                    <div class="btr-switch-container">

                        <label for="btr_exclude_condivisa_max" class="label_btr_exclude_condivisa_max">Escludi camera</label>
                        <input type="checkbox" id="btr_exclude_condivisa_max" name="btr_exclude_condivisa_max" value="on" <?php checked($btr_exclude_condivisa_max, 'on'); ?> />
                        <label class="btr-switch" for="btr_exclude_condivisa_max">
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

            </div>

        </div>

    </div>
</div>




<?php



// include gestione-allotment-camere.php
include  'gestione-allotment-camere.php';
