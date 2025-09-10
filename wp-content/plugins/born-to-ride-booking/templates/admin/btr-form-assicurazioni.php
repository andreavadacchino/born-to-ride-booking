    <input type="hidden" name="action" value="btr_save_assicurazioni_temp">

    <div id="btr-assicurazioni-container">
        <?php foreach ($anagrafici as $index => $persona): ?>
            <div class="btr-person-card" data-person-index="<?php echo $index; ?>">

                <h3 class="person-title">
                    <span class="icona-partecipante">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5.52 19c.64-2.2 1.84-3 3.22-3h6.52c1.38 0 2.58.8 3.22 3"/><circle cx="12" cy="10" r="3"/><circle cx="12" cy="12" r="10"/></svg>
                    </span>
                    <?php
                    $ordinali = ['Primo', 'Secondo', 'Terzo', 'Quarto', 'Quinto', 'Sesto'];
                    $posizione = $ordinali[$index] ?? sprintf(__('Partecipante %d', 'born-to-ride-booking'), $index + 1);
                    echo '<strong>' . esc_html($posizione) . '</strong> ' . esc_html__('partecipante', 'born-to-ride-booking');
                    ?>
                </h3>

                <fieldset class="btr-assicurazioni">
                    <?php
                    $btr_assicurazione_importi = get_post_meta($package_id, 'btr_assicurazione_importi', true);
                    if (!empty($btr_assicurazione_importi)) :
                        ?>
                        <h4><?php esc_html_e('Assicurazioni', 'born-to-ride-booking'); ?></h4>
                        <?php foreach ($btr_assicurazione_importi as $assicurazione):
                        $slug = sanitize_title($assicurazione['descrizione']);
                        $percentuale = $assicurazione['importo_perentuale'] ?? '';
                        $importo = $assicurazione['importo'] ?? '';
                        ?>
                        <div class="btr-assicurazione-item">
                            <label>
                                <input type="checkbox"
                                       name="anagrafici[<?php echo esc_attr($index); ?>][assicurazioni][<?php echo esc_attr($slug); ?>]"
                                       value="1" />
                                <?php echo esc_html($assicurazione['descrizione']); ?>
                                <?php if (!empty($assicurazione['assicurazione_view_prezzo'])): ?>
                                    <strong><?php echo number_format_i18n((float)$importo, 2); ?> €</strong>
                                <?php endif; ?>
                                <?php if (!empty($assicurazione['assicurazione_view_percentuale'])): ?>
                                    <strong>+ <?php echo floatval($percentuale); ?>%</strong>
                                <?php endif; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                        <p><?php esc_html_e('Nessuna assicurazione disponibile.', 'born-to-ride-booking'); ?></p>
                    <?php endif; ?>
                </fieldset>

            </div>
        <?php endforeach; ?>
    </div>


<div id="btr-assicurazioni-response" style="margin-top: 20px;"></div>



    <style>
        .btr-form {
            max-width: 100%;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 3em 2em;
            border-radius: 5px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .form-title {
            text-align: center;
            font-size: 24px;
            margin-bottom: 10px;
        }

        .form-subtitle {
            margin-bottom: 20px;
            color: #555;
        }

        .btr-person-card {
            margin-bottom: 2em;
            padding: 2em;
            background-color: #F8F8F8;
            border-radius: 2px;
        }

        .btr-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 5px 15px;
        }

        .btr-field-group {
            display: flex;
            flex-direction: column;
        }

        .btr-field-group.asign-camera {
            margin-top: 2em;
        }

        .btr-field-group label {
            margin-bottom: 5px;
            font-weight: normal;
            color: #707070;
            padding-left: 5px;
            font-size: 85%;
        }

        .btr-field-group input {
            padding: 10px;
            border: 1px solid #ccc;
            background-color: #fff !important;
        }

        .btr-room-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btr-room-button {
            padding: 10px 15px;
            background-color: #007cba;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .btr-room-button.disabled {
            background: #e0e0e0;
            border-color: #ccc;
            color: #aaa;
            cursor: not-allowed;
            box-shadow: none;
        }

        .btr-room-button.selected {

            background: #28a745;
            color: #fff;
            border-color: #28a745;
        }

        .btr-assicurazioni {
            margin-top: 2em;
        }
        .btr-assicurazioni h4 {
            color: #0097C5;
            margin-bottom: 0 !important;
            line-height: 1.1 !important;
        }
        .btr-assicurazioni p {
            color: #707070 !important;
        }



        /* Container pulsanti */
        .btr-room-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .btr-room-buttons a {
            font-size: 1.2em;
            border-radius: 0;
            background-color: #0097C5;
            color: #fff;
            padding: 1em 2em;
        }
        .btr-room-buttons a strong {
            text-transform: uppercase;
        }
        .btr-room-buttons a span {
            font-style: italic;
            font-size: 75%;
            margin-left: 5px;
        }


        /* Effetto hover */
        .btr-room-button:hover {
            box-shadow: 0 4px 6px rgba(0, 115, 230, 0.3);
        }




        /* Evidenziazione delle schede con dati mancanti */


        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25%, 75% { transform: translateX(-5px); }
            50% { transform: translateX(5px); }
        }

        /* Stile del contatore */
        .mancanti {
            font-size: 16px;
            font-weight: bold;
            color: #d9534f;
        }


        .person-title {
            color: #707070;
            font-size: 2em !important;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 10px;
            margin-bottom: 1.5em !important;
        }
        .person-title .icona-partecipante svg {
            stroke: #007cba;
            width: 1.3em;
            height: 1.3em;
        }
        .person-title strong {
            color: #007cba;
        }


    </style>