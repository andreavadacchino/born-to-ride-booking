<?php
/**
 * Template for displaying the preventivo (quote) summary
 *
 * @package Born_To_Ride_Booking
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Impedisce l'accesso diretto al file
}

// Recupera l'ID del preventivo dalla query string
$preventivo_id = isset($_GET['preventivo_id']) ? intval($_GET['preventivo_id']) : 0;

// Verifica che il preventivo esista
$preventivo = get_post($preventivo_id);
if (!$preventivo || $preventivo->post_type !== 'btr_preventivi') {
    ?>
    <div class="btr-error-message">
        <h3><?php esc_html_e('Preventivo non trovato', 'born-to-ride-booking'); ?></h3>
        <p><?php esc_html_e('Il preventivo richiesto non esiste o è stato rimosso.', 'born-to-ride-booking'); ?></p>
        <a href="<?php echo esc_url(home_url()); ?>" class="btr-button"><?php esc_html_e('Torna alla home', 'born-to-ride-booking'); ?></a>
    </div>
    <?php
    return;
}

// Recupera i metadati del preventivo
$cliente_nome = get_post_meta($preventivo_id, '_cliente_nome', true);
$cliente_email = get_post_meta($preventivo_id, '_cliente_email', true);
$pacchetto_id = get_post_meta($preventivo_id, '_pacchetto_id', true);
$prezzo_totale = get_post_meta($preventivo_id, '_prezzo_totale', true);
$camere_selezionate = get_post_meta($preventivo_id, '_camere_selezionate', true);
$stato_preventivo = get_post_meta($preventivo_id, '_stato_preventivo', true);
$data_scelta = get_post_meta($preventivo_id, '_data_pacchetto', true);
$num_adults = get_post_meta($preventivo_id, '_num_adults', true);
$num_children = get_post_meta($preventivo_id, '_num_children', true);
$nome_pacchetto = get_post_meta($preventivo_id, '_nome_pacchetto', true);
$durata = get_post_meta($preventivo_id, '_durata', true);
$anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
$costi_extra_durata = get_post_meta($preventivo_id, '_costi_extra_durata', true);
$pdf_path = get_post_meta($preventivo_id, '_pdf_path', true);

// Recupera il nome del pacchetto
$pacchetto_nome = $pacchetto_id ? get_the_title($pacchetto_id) : __('Non specificato', 'born-to-ride-booking');

// Formatta il prezzo
$prezzo_formattato = number_format($prezzo_totale, 2, ',', '.');

// Calcola la data di scadenza (7 giorni dalla creazione)
$data_creazione = strtotime($preventivo->post_date);
$data_scadenza = date_i18n(get_option('date_format'), strtotime('+7 days', $data_creazione));

// Controlla se il preventivo è scaduto
$is_expired = time() > strtotime('+7 days', $data_creazione);

// Prepara il link per il download del PDF
$pdf_url = '';
if (!empty($pdf_path) && file_exists($pdf_path)) {
    $pdf_filename = basename($pdf_path);
    $pdf_url = home_url('wp-content/uploads/btr-preventivi/' . $pdf_filename);
}

?>

<div class="btr-preventivo-summary">
    <div class="btr-preventivo-header">
        <h2><?php echo esc_html(sprintf(__('Preventivo #%d - %s', 'born-to-ride-booking'), $preventivo_id, $nome_pacchetto)); ?></h2>
        
        <?php if ($is_expired): ?>
            <div class="btr-preventivo-expired">
                <p><?php esc_html_e('Questo preventivo è scaduto. Per favore, contattaci per un nuovo preventivo.', 'born-to-ride-booking'); ?></p>
            </div>
        <?php else: ?>
            <div class="btr-preventivo-valid">
                <p><?php echo esc_html(sprintf(__('Preventivo valido fino al %s', 'born-to-ride-booking'), $data_scadenza)); ?></p>
            </div>
        <?php endif; ?>
    </div>

    <div class="btr-preventivo-content">
        <div class="btr-preventivo-section btr-preventivo-cliente">
            <h3><?php esc_html_e('Dati Cliente', 'born-to-ride-booking'); ?></h3>
            <div class="btr-preventivo-info-grid">
                <div class="btr-preventivo-info-row">
                    <span class="btr-preventivo-label"><?php esc_html_e('Nome:', 'born-to-ride-booking'); ?></span>
                    <span class="btr-preventivo-value"><?php echo esc_html($cliente_nome); ?></span>
                </div>
                <div class="btr-preventivo-info-row">
                    <span class="btr-preventivo-label"><?php esc_html_e('Email:', 'born-to-ride-booking'); ?></span>
                    <span class="btr-preventivo-value"><?php echo esc_html($cliente_email); ?></span>
                </div>
            </div>
        </div>

        <div class="btr-preventivo-section btr-preventivo-pacchetto">
            <h3><?php esc_html_e('Dettagli Pacchetto', 'born-to-ride-booking'); ?></h3>
            <div class="btr-preventivo-info-grid">
                <div class="btr-preventivo-info-row">
                    <span class="btr-preventivo-label"><?php esc_html_e('Pacchetto:', 'born-to-ride-booking'); ?></span>
                    <span class="btr-preventivo-value"><?php echo esc_html($nome_pacchetto); ?></span>
                </div>
                <div class="btr-preventivo-info-row">
                    <span class="btr-preventivo-label"><?php esc_html_e('Data:', 'born-to-ride-booking'); ?></span>
                    <span class="btr-preventivo-value"><?php echo esc_html($data_scelta); ?></span>
                </div>
                <div class="btr-preventivo-info-row">
                    <span class="btr-preventivo-label"><?php esc_html_e('Durata:', 'born-to-ride-booking'); ?></span>
                    <span class="btr-preventivo-value"><?php echo esc_html($durata); ?></span>
                </div>
                <div class="btr-preventivo-info-row">
                    <span class="btr-preventivo-label"><?php esc_html_e('Partecipanti:', 'born-to-ride-booking'); ?></span>
                    <span class="btr-preventivo-value">
                        <?php 
                        echo esc_html(sprintf(_n('%d adulto', '%d adulti', $num_adults, 'born-to-ride-booking'), $num_adults));
                        if ($num_children > 0) {
                            echo ', ' . esc_html(sprintf(_n('%d bambino', '%d bambini', $num_children, 'born-to-ride-booking'), $num_children));
                        }
                        ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="btr-preventivo-section btr-preventivo-camere">
            <h3><?php esc_html_e('Sistemazione', 'born-to-ride-booking'); ?></h3>
            <?php if (!empty($camere_selezionate) && is_array($camere_selezionate)): ?>
                <div class="btr-preventivo-camere-list">
                    <?php foreach ($camere_selezionate as $camera): ?>
                        <div class="btr-preventivo-camera-item">
                            <span class="btr-preventivo-camera-tipo"><?php echo esc_html($camera['tipo']); ?></span>
                            <span class="btr-preventivo-camera-quantita">x<?php echo esc_html($camera['quantita']); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p><?php esc_html_e('Nessuna sistemazione selezionata.', 'born-to-ride-booking'); ?></p>
            <?php endif; ?>
        </div>

        <?php if (!empty($anagrafici) && is_array($anagrafici)): ?>
            <div class="btr-preventivo-section btr-preventivo-anagrafici">
                <h3><?php esc_html_e('Dati Partecipanti', 'born-to-ride-booking'); ?></h3>
                <?php foreach ($anagrafici as $index => $persona): ?>
                    <div class="btr-preventivo-persona">
                        <h4><?php 
                            // Determina il tipo di partecipante
                            $fascia = isset($persona['fascia']) ? $persona['fascia'] : '';
                            $is_infant = ($fascia === 'neonato');
                            $is_child = in_array($fascia, ['f1', 'f2', 'f3', 'f4']);
                            
                            // Recupera etichette base dalle opzioni
                            $adult_label = get_option('btr_label_adult_singular', 'Adulto');
                            $child_label = get_option('btr_label_child_singular', 'Bambino');
                            $infant_label = get_option('btr_label_infant_singular', 'Neonato');
                            
                            if ($is_infant) {
                                $tipo_label = $infant_label . ' 0-2 anni';
                            } elseif ($is_child) {
                                // Recupera etichette delle fasce bambini
                                $child_labels = get_post_meta($preventivo_id, '_child_category_labels', true);
                                
                                if (empty($child_labels) && class_exists('BTR_Dynamic_Child_Categories')) {
                                    $child_categories_manager = new BTR_Dynamic_Child_Categories();
                                    $child_categories = $child_categories_manager->get_categories(true);
                                    $child_labels = [];
                                    foreach ($child_categories as $category) {
                                        $child_labels[$category['id']] = $category['label'];
                                    }
                                }
                                
                                if ($fascia && isset($child_labels[$fascia])) {
                                    $fascia_label = $child_labels[$fascia];
                                    if (stripos($fascia_label, $child_label) !== false || stripos($fascia_label, 'bambini') !== false) {
                                        $tipo_label = $fascia_label;
                                    } else {
                                        $tipo_label = $child_label . ' ' . $fascia_label;
                                    }
                                } else {
                                    $tipo_label = $child_label;
                                }
                            } else {
                                $tipo_label = $adult_label;
                            }
                            
                            echo esc_html(sprintf(__('Partecipante %d', 'born-to-ride-booking'), $index + 1)) . ' - ' . esc_html($tipo_label);
                        ?></h4>
                        <div class="btr-preventivo-info-grid">
                            <div class="btr-preventivo-info-row">
                                <span class="btr-preventivo-label"><?php esc_html_e('Nome:', 'born-to-ride-booking'); ?></span>
                                <span class="btr-preventivo-value"><?php echo esc_html($persona['nome'] ?? ''); ?></span>
                            </div>
                            <div class="btr-preventivo-info-row">
                                <span class="btr-preventivo-label"><?php esc_html_e('Cognome:', 'born-to-ride-booking'); ?></span>
                                <span class="btr-preventivo-value"><?php echo esc_html($persona['cognome'] ?? ''); ?></span>
                            </div>
                            <?php if (!empty($persona['data_nascita'])): ?>
                                <div class="btr-preventivo-info-row">
                                    <span class="btr-preventivo-label"><?php esc_html_e('Data di nascita:', 'born-to-ride-booking'); ?></span>
                                    <span class="btr-preventivo-value"><?php echo esc_html($persona['data_nascita']); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($persona['camera_tipo'])): ?>
                                <div class="btr-preventivo-info-row">
                                    <span class="btr-preventivo-label"><?php esc_html_e('Sistemazione:', 'born-to-ride-booking'); ?></span>
                                    <span class="btr-preventivo-value"><?php echo esc_html($persona['camera_tipo']); ?></span>
                                </div>
                                <?php if (!empty($persona['tipo_letto']) && ($persona['camera_tipo'] === 'Doppia' || $persona['camera_tipo'] === 'Doppia/Matrimoniale')): ?>
                                    <div class="btr-preventivo-info-row">
                                        <span class="btr-preventivo-label"><?php esc_html_e('Tipo letto:', 'born-to-ride-booking'); ?></span>
                                        <span class="btr-preventivo-value"><?php echo esc_html($persona['tipo_letto'] === 'letti_singoli' ? 'Letti Singoli' : 'Letto Matrimoniale'); ?></span>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php if (!empty($persona['assicurazioni_dettagliate']) && is_array($persona['assicurazioni_dettagliate'])): ?>
                                <div class="btr-preventivo-info-row btr-preventivo-assicurazioni">
                                    <span class="btr-preventivo-label"><?php esc_html_e('Assicurazioni:', 'born-to-ride-booking'); ?></span>
                                    <div class="btr-preventivo-assicurazioni-list">
                                        <?php foreach ($persona['assicurazioni_dettagliate'] as $slug => $assicurazione): ?>
                                            <div class="btr-preventivo-assicurazione-item">
                                                <span class="btr-preventivo-assicurazione-nome"><?php echo esc_html($assicurazione['descrizione']); ?></span>
                                                <?php if ($assicurazione['importo'] > 0 || $assicurazione['percentuale'] > 0): ?>
                                                    <span class="btr-preventivo-assicurazione-prezzo">
                                                        <?php 
                                                        if ($assicurazione['importo'] > 0) {
                                                            echo '€' . number_format($assicurazione['importo'], 2, ',', '.');
                                                        }
                                                        if ($assicurazione['percentuale'] > 0) {
                                                            echo ' (+' . $assicurazione['percentuale'] . '%)';
                                                        }
                                                        ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($persona['costi_extra_dettagliate']) && is_array($persona['costi_extra_dettagliate'])): ?>
                                <div class="btr-preventivo-info-row btr-preventivo-costi-extra">
                                    <span class="btr-preventivo-label"><?php esc_html_e('Costi Extra:', 'born-to-ride-booking'); ?></span>
                                    <div class="btr-preventivo-costi-extra-list">
                                        <?php foreach ($persona['costi_extra_dettagliate'] as $slug => $costo_extra): ?>
                                            <?php if (!empty($costo_extra['attivo'])): ?>
                                                <div class="btr-preventivo-costo-extra-item">
                                                    <span class="btr-preventivo-costo-extra-nome"><?php echo esc_html($costo_extra['nome']); ?></span>
                                                    <?php if ($costo_extra['importo'] > 0): ?>
                                                        <span class="btr-preventivo-costo-extra-prezzo">
                                                            €<?php echo number_format($costo_extra['importo'], 2, ',', '.'); ?>
                                                            <?php if (!empty($costo_extra['sconto']) && $costo_extra['sconto'] > 0): ?>
                                                                <span class="btr-preventivo-sconto">(-<?php echo $costo_extra['sconto']; ?>%)</span>
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($costi_extra_durata) && is_array($costi_extra_durata)): ?>
            <div class="btr-preventivo-section btr-preventivo-costi-extra-durata">
                <h3><?php esc_html_e('Costi Extra (Viaggio)', 'born-to-ride-booking'); ?></h3>
                <div class="btr-preventivo-costi-extra-durata-list">
                    <?php foreach ($costi_extra_durata as $slug => $costo_extra): ?>
                        <?php if (!empty($costo_extra['attivo'])): ?>
                            <div class="btr-preventivo-costo-extra-durata-item">
                                <span class="btr-preventivo-costo-extra-durata-nome"><?php echo esc_html($costo_extra['nome']); ?></span>
                                <?php if ($costo_extra['importo'] > 0): ?>
                                    <span class="btr-preventivo-costo-extra-durata-prezzo">
                                        €<?php echo number_format($costo_extra['importo'], 2, ',', '.'); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="btr-preventivo-section btr-preventivo-totale">
            <h3><?php esc_html_e('Prezzo Totale', 'born-to-ride-booking'); ?></h3>
            <div class="btr-preventivo-prezzo">
                <span class="btr-preventivo-prezzo-value">€<?php echo esc_html($prezzo_formattato); ?></span>
            </div>
        </div>

        <div class="btr-preventivo-actions">
            <?php if (!empty($pdf_url)): ?>
                <a href="<?php echo esc_url($pdf_url); ?>" class="btr-button btr-button-pdf" download>
                    <?php esc_html_e('Scarica PDF', 'born-to-ride-booking'); ?>
                </a>
            <?php endif; ?>
            
            <?php if (!$is_expired): ?>
                <a href="<?php echo esc_url(add_query_arg('preventivo_id', $preventivo_id, home_url('/procedi-con-prenotazione/'))); ?>" class="btr-button btr-button-primary">
                    <?php esc_html_e('Procedi con la prenotazione', 'born-to-ride-booking'); ?>
                </a>
            <?php endif; ?>
            
            <a href="<?php echo esc_url(home_url()); ?>" class="btr-button btr-button-secondary">
                <?php esc_html_e('Torna alla home', 'born-to-ride-booking'); ?>
            </a>
        </div>
    </div>
</div>

<style>
    .btr-preventivo-summary {
        max-width: 1000px;
        margin: 0 auto;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        color: #333;
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        padding: 30px;
    }

    .btr-preventivo-header {
        border-bottom: 1px solid #eee;
        padding-bottom: 20px;
        margin-bottom: 30px;
    }

    .btr-preventivo-header h2 {
        margin: 0 0 15px;
        color: #0097c5;
        font-size: 28px;
    }

    .btr-preventivo-expired {
        background-color: #ffecec;
        border-left: 4px solid #ff5252;
        padding: 10px 15px;
        margin-top: 15px;
    }

    .btr-preventivo-valid {
        background-color: #e6f7ff;
        border-left: 4px solid #0097c5;
        padding: 10px 15px;
        margin-top: 15px;
    }

    .btr-preventivo-section {
        margin-bottom: 30px;
    }

    .btr-preventivo-section h3 {
        color: #0097c5;
        font-size: 20px;
        margin: 0 0 15px;
        padding-bottom: 8px;
        border-bottom: 1px solid #eee;
    }

    .btr-preventivo-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 15px;
    }

    .btr-preventivo-info-row {
        display: flex;
        margin-bottom: 8px;
    }

    .btr-preventivo-label {
        font-weight: bold;
        min-width: 120px;
        color: #555;
    }

    .btr-preventivo-value {
        flex: 1;
    }

    .btr-preventivo-camere-list {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .btr-preventivo-camera-item {
        background-color: #f5f5f5;
        padding: 8px 15px;
        border-radius: 4px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .btr-preventivo-camera-tipo {
        font-weight: bold;
    }

    .btr-preventivo-camera-quantita {
        color: #0097c5;
        font-weight: bold;
    }

    .btr-preventivo-persona {
        background-color: #f9f9f9;
        padding: 15px;
        border-radius: 6px;
        margin-bottom: 15px;
    }

    .btr-preventivo-persona h4 {
        margin: 0 0 15px;
        color: #333;
        font-size: 18px;
    }

    .btr-preventivo-assicurazioni-list {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .btr-preventivo-assicurazione-item {
        display: flex;
        justify-content: space-between;
    }

    .btr-preventivo-prezzo {
        text-align: center;
        margin: 20px 0;
    }

    .btr-preventivo-prezzo-value {
        font-size: 32px;
        font-weight: bold;
        color: #0097c5;
    }

    .btr-preventivo-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        justify-content: center;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #eee;
    }

    .btr-button {
        display: inline-block;
        padding: 12px 24px;
        border-radius: 4px;
        text-decoration: none;
        font-weight: bold;
        text-align: center;
        transition: all 0.3s ease;
    }

    .btr-button-primary {
        background-color: #0097c5;
        color: white;
    }

    .btr-button-primary:hover {
        background-color: #007ba3;
        color: white;
    }

    .btr-button-secondary {
        background-color: #f5f5f5;
        color: #333;
    }

    .btr-button-secondary:hover {
        background-color: #e5e5e5;
        color: #333;
    }

    .btr-button-pdf {
        background-color: #ff5722;
        color: white;
    }

    .btr-button-pdf:hover {
        background-color: #e64a19;
        color: white;
    }

    .btr-error-message {
        text-align: center;
        padding: 50px 20px;
    }

    @media (max-width: 768px) {
        .btr-preventivo-summary {
            padding: 20px;
        }

        .btr-preventivo-info-grid {
            grid-template-columns: 1fr;
        }

        .btr-preventivo-actions {
            flex-direction: column;
        }

        .btr-button {
            width: 100%;
        }
    }
</style>