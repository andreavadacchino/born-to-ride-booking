<?php
/**
 * Template per il dettaglio preventivo nell'admin
 * Stile professionale simile agli ordini WooCommerce
 */

if (!defined('ABSPATH')) {
    exit;
}

// Recupera tutti i dati del preventivo
$preventivo_id = $post->ID;
$cliente_nome = get_post_meta($preventivo_id, '_cliente_nome', true);
$cliente_email = get_post_meta($preventivo_id, '_cliente_email', true);
$cliente_telefono = get_post_meta($preventivo_id, '_cliente_telefono', true);
$pacchetto_id = get_post_meta($preventivo_id, '_pacchetto_id', true);
$nome_pacchetto = get_post_meta($preventivo_id, '_nome_pacchetto', true);
$prezzo_totale = get_post_meta($preventivo_id, '_prezzo_totale', true);
$prezzo_totale_completo = get_post_meta($preventivo_id, '_prezzo_totale_completo', true);
$btr_grand_total = get_post_meta($preventivo_id, '_btr_grand_total', true);
$durata = get_post_meta($preventivo_id, '_durata', true);
$data_pacchetto = get_post_meta($preventivo_id, '_data_pacchetto', true);
$date_ranges = get_post_meta($preventivo_id, '_date_ranges', true);
$data_inizio = get_post_meta($preventivo_id, '_data_inizio', true);
$data_fine = get_post_meta($preventivo_id, '_data_fine', true);
$num_adults = get_post_meta($preventivo_id, '_num_adults', true);
$num_children = get_post_meta($preventivo_id, '_num_children', true);
$num_neonati = get_post_meta($preventivo_id, '_num_neonati', true);
$anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
$camere_selezionate = get_post_meta($preventivo_id, '_camere_selezionate', true);
$riepilogo_preventivo = get_post_meta($preventivo_id, '_riepilogo_preventivo', true);
$stato_preventivo = get_post_meta($preventivo_id, '_stato_preventivo', true) ?: 'pending';
$costi_extra_durata = get_post_meta($preventivo_id, '_costi_extra_durata', true);
$extra_nights_dates = get_post_meta($preventivo_id, '_extra_nights_dates', true);
$extra_nights_count = get_post_meta($preventivo_id, '_extra_nights_count', true);
$numero_notti_extra = get_post_meta($preventivo_id, '_numero_notti_extra', true);
$btr_extra_night_date = get_post_meta($preventivo_id, '_btr_extra_night_date', true);
$extra_night = get_post_meta($preventivo_id, '_extra_night', true);
$extra_night_pp = get_post_meta($preventivo_id, '_extra_night_pp', true);
$extra_night_total = get_post_meta($preventivo_id, '_extra_night_total', true);
$totale_assicurazioni = get_post_meta($preventivo_id, '_totale_assicurazioni', true);
$totale_costi_extra = get_post_meta($preventivo_id, '_totale_costi_extra', true);
$supplemento_totale = get_post_meta($preventivo_id, '_supplemento_totale', true);
$child_category_labels = get_post_meta($preventivo_id, '_child_category_labels', true);
$riepilogo_calcoli_dettagliato = get_post_meta($preventivo_id, '_riepilogo_calcoli_dettagliato', true);
$extra_costs_summary = get_post_meta($preventivo_id, '_extra_costs_summary', true);
$extra_costs_by_participant = get_post_meta($preventivo_id, '_extra_costs_by_participant', true);
$assicurazioni = get_post_meta($preventivo_id, '_assicurazioni', true);
$supplemento_camera = get_post_meta($preventivo_id, '_supplemento_camera', true);
$dettaglio_persone_per_categoria = get_post_meta($preventivo_id, '_dettaglio_persone_per_categoria', true);
$breakdown_dettagliato = get_post_meta($preventivo_id, '_breakdown_dettagliato', true);
$note_cliente = get_post_meta($preventivo_id, '_note_cliente', true);
$note_interne = get_post_meta($preventivo_id, '_note_interne', true);
$product_id = get_post_meta($preventivo_id, '_product_id', true);
$tipologia_prenotazione = get_post_meta($preventivo_id, '_tipologia_prenotazione', true);

// Calcola totale partecipanti
$totale_partecipanti = count($anagrafici ?: []);

// Determina classe stato
$stato_class = '';
$stato_label = '';
switch($stato_preventivo) {
    case 'confirmed':
        $stato_class = 'confirmed';
        $stato_label = 'Confermato';
        break;
    case 'completed':
        $stato_class = 'completed';
        $stato_label = 'Completato';
        break;
    case 'cancelled':
        $stato_class = 'cancelled';
        $stato_label = 'Annullato';
        break;
    case 'convertito':
        $stato_class = 'completed';
        $stato_label = 'Convertito';
        break;
    default:
        $stato_class = 'pending';
        $stato_label = 'In Attesa';
}

// Decodifica le etichette delle categorie bambini
$child_labels = [];
if ($child_category_labels) {
    if (is_string($child_category_labels)) {
        // Prova prima JSON
        $decoded = json_decode($child_category_labels, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $child_labels = $decoded;
        } else {
            // Poi prova unserialize con gestione errori
            $unserialized = @unserialize($child_category_labels);
            if ($unserialized !== false && is_array($unserialized)) {
                $child_labels = $unserialized;
            }
        }
    } elseif (is_array($child_category_labels)) {
        $child_labels = $child_category_labels;
    }
}

// v1.0.160 - Fallback usando la funzione helper per etichette dinamiche
if (empty($child_labels)) {
    // Recupera il package_id se non l'abbiamo già
    if (empty($pacchetto_id)) {
        $pacchetto_id = get_post_meta($preventivo_id, '_btr_pacchetto_id', true);
        if (empty($pacchetto_id)) {
            $pacchetto_id = get_post_meta($preventivo_id, '_btr_id_pacchetto', true);
        }
    }
    
    // Usa la funzione helper per recuperare le etichette dinamiche
    // v1.0.185: Passa il preventivo_id per ottenere le etichette corrette salvate
    if (class_exists('BTR_Preventivi')) {
        $child_labels = BTR_Preventivi::btr_get_child_age_labels($preventivo_id);
    } else {
        // Fallback estremo se la classe non è disponibile - v1.0.185: NO hardcoded
        $child_labels = [
            'f1' => '3-6 anni',
            'f2' => '6-12',
            'f3' => '12-14',
            'f4' => '14-15'
        ];
    }
}

// Decodifica le camere
$camere = [];
if ($camere_selezionate) {
    $camere = is_string($camere_selezionate) ? unserialize($camere_selezionate) : $camere_selezionate;
}

// Calcola totale finale
$totale_finale = $prezzo_totale_completo ?: $btr_grand_total ?: $prezzo_totale;

?>

<style>
    /* Born to Ride – Admin Preventivo View */
    :root {
        --btr-primary: #0073aa;
        --btr-accent:  #d9000d;
        --btr-bg:      #ffffff;
        --btr-border:  #e0e0e0;
        --btr-shadow:  rgba(0, 0, 0, 0.06);
    }

    /* Container */
    .btr-container {
        margin: 20px auto;
        max-width: 1200px;
        background: var(--btr-bg);
        border: 1px solid var(--btr-border);
        border-radius: 10px;
        box-shadow: 0 3px 6px var(--btr-shadow);
        padding: 20px;
    }

    .btr-container.no-style {
        border: none;
        box-shadow: none;
        padding: 0;
    }

    /* Header */
    .btr-header {
        border-bottom: 2px solid #f0f0f0;
        margin-bottom: 20px;
        padding-bottom: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .btr-header h2 {
        margin: 0;
        font-size: 1.8rem;
        color: #333;
    }

    .btr-order-status {
        font-size: 1.1rem;
        color: var(--btr-accent);
        font-weight: 600;
    }

    .btr-order-status.confirmed { color: #00a32a; }
    .btr-order-status.completed { color: #0073aa; }
    .btr-order-status.cancelled { color: #d63638; }
    .btr-order-status.pending { color: #996800; }

    /* Sections */
    .btr-section { margin-top: 30px; }
    .btr-section h3 {
        font-size: 1.25rem;
        margin-bottom: 12px;
        color: #333;
        border-bottom: 2px solid #ddd;
        padding-bottom: 5px;
    }

    /* Tables */
    .btr-section table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 25px;
    }

    .btr-section table th,
    .btr-section table td {
        padding: 10px 12px;
        border: 1px solid var(--btr-border);
        text-align: left;
        vertical-align: top;
    }

    .btr-section table th {
        background: #f7f7f7;
        font-weight: 600;
    }

    .btr-section table tbody tr:nth-child(even) { background: #fafafa; }
    .btr-section table tbody tr:hover { background: #f1f7ff; }

    .btr-highlight { color: var(--btr-accent); font-weight: 700; }

    /* Room Cards Grid */
    .btr-rooms-container {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }

    .btr-room-box {
        background: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 6px;
        padding: 15px;
    }

    .btr-room-box h4 {
        margin: 0 0 10px 0;
        color: #333;
        font-size: 1.1rem;
    }

    .btr-room-box .room-type {
        color: #666;
        font-size: 0.9rem;
        margin-bottom: 8px;
    }

    .btr-room-box .participants-list {
        font-size: 0.9rem;
        line-height: 1.5;
        margin-top: 10px;
    }

    .btr-room-box .room-price {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid #ddd;
        font-weight: 600;
        color: var(--btr-accent);
    }

    /* Price Breakdown */
    .btr-price-summary {
        background: #f6f7f7;
        padding: 15px;
        border-radius: 6px;
        margin-top: 15px;
    }

    .btr-price-row {
        display: flex;
        justify-content: space-between;
        padding: 5px 0;
        font-size: 14px;
    }

    .btr-price-row.subtotal {
        font-weight: 600;
        padding-top: 10px;
        border-top: 1px solid #ddd;
    }

    .btr-price-row.total {
        font-weight: 700;
        font-size: 1.1rem;
        color: var(--btr-accent);
        padding-top: 10px;
        border-top: 2px solid #ddd;
        margin-top: 10px;
    }

    /* Buttons */
    .btr-button-stripe {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 8px 15px;
        background: #2271b1;
        color: #ffffff !important;
        font-size: 11px;
        font-weight: 500;
        border: none;
        text-transform: uppercase;
        border-radius: 4px;
        text-decoration: none;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transition: all 0.2s ease;
        margin-left: 1em;
        letter-spacing: .5px;
    }

    .btr-button-stripe:hover {
        box-shadow: 0 6px 8px rgba(0,0,0,0.15);
        transform: translateY(-1px);
        color: #ffffff !important;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .btr-section table {
            display: block;
            overflow-x: auto;
            white-space: nowrap;
        }
        .btr-rooms-container {
            grid-template-columns: 1fr;
        }
    }

    /* Info Message */
    .btr-info-message {
        background: #f0f0f1;
        border-radius: 4px;
        padding: 10px 15px;
        margin: 10px 0;
        font-size: 0.9rem;
        color: #666;
    }
</style>

<div class="btr-container no-style">
    <p>
        <a href="<?php echo admin_url('edit.php?post_type=btr_preventivi'); ?>" class="button button-secondary">
            &larr; <?php esc_html_e('Torna alla lista dei preventivi', 'born-to-ride-booking'); ?>
        </a>
    </p>
</div>

<div class="btr-container">
    <!-- Header -->
    <div class="btr-header">
        <h2><?php echo sprintf(__('Dettagli Preventivo #%d', 'born-to-ride-booking'), $preventivo_id); ?></h2>
        <span class="btr-order-status <?php echo $stato_class; ?>"><?php echo esc_html($stato_label); ?></span>
    </div>

    <!-- Dati Preventivo -->
    <div class="btr-section">
        <h3><?php esc_html_e('Dettagli Preventivo', 'born-to-ride-booking'); ?></h3>
        <table>
            <tr>
                <th><?php esc_html_e('Numero Preventivo', 'born-to-ride-booking'); ?></th>
                <td><strong>#<?php echo esc_html($preventivo_id); ?></strong></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Data Creazione', 'born-to-ride-booking'); ?></th>
                <td><?php echo get_the_date('d/m/Y H:i', $preventivo_id); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Pacchetto', 'born-to-ride-booking'); ?></th>
                <td>
                    <strong><?php echo esc_html($nome_pacchetto); ?></strong>
                    <?php if ($pacchetto_id): ?>
                        <a href="<?php echo get_edit_post_link($pacchetto_id); ?>" class="btr-button-stripe">
                            <?php esc_html_e('Modifica Pacchetto', 'born-to-ride-booking'); ?>
                        </a>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Periodo', 'born-to-ride-booking'); ?></th>
                <td><?php echo esc_html($date_ranges ?: $data_pacchetto ?: 'Non specificato'); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Durata', 'born-to-ride-booking'); ?></th>
                <td><?php echo esc_html($durata ?: 'Non specificata'); ?></td>
            </tr>
            <?php if ($numero_notti_extra || $extra_night): ?>
            <tr>
                <th><?php esc_html_e('Notti Extra', 'born-to-ride-booking'); ?></th>
                <td>
                    <?php 
                    $n_notti = $numero_notti_extra ?: $extra_night;
                    echo $n_notti . ' ' . ($n_notti == 1 ? 'notte' : 'notti');
                    if ($extra_night_pp): ?>
                        - €<?php echo number_format($extra_night_pp, 2, ',', '.'); ?> a persona/notte
                    <?php endif;
                    if ($extra_night_total): ?>
                        (Totale: €<?php echo number_format($extra_night_total, 2, ',', '.'); ?>)
                    <?php endif; ?>
                </td>
            </tr>
            <?php endif; ?>
            <tr>
                <th><?php esc_html_e('Totale Partecipanti', 'born-to-ride-booking'); ?></th>
                <td>
                    <strong><?php echo $totale_partecipanti; ?></strong>
                    (<?php echo $num_adults; ?> adulti<?php 
                    if ($num_children > 0) echo ', ' . $num_children . ' bambini';
                    if ($num_neonati > 0) echo ', ' . $num_neonati . ' neonati';
                    ?>)
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Totale Camere', 'born-to-ride-booking'); ?></th>
                <td><?php echo is_array($camere) ? count($camere) : 0; ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Totale Preventivo', 'born-to-ride-booking'); ?></th>
                <td class="btr-highlight">€<?php echo number_format($totale_finale, 2, ',', '.'); ?></td>
            </tr>
            <?php
            $pdf_path = get_post_meta($preventivo_id, '_pdf_path', true);
            $pdf_exists = false;
            
            if ($pdf_path) {
                if (strpos($pdf_path, '/') === 0) {
                    $pdf_exists = file_exists(WP_CONTENT_DIR . $pdf_path);
                } else {
                    $pdf_exists = file_exists($pdf_path);
                }
            }
            
            if ($pdf_exists): 
            ?>
            <tr>
                <th><?php esc_html_e('Documenti', 'born-to-ride-booking'); ?></th>
                <td>
                    <a href="<?php echo content_url($pdf_path); ?>" target="_blank" class="btr-button-stripe">
                        <?php esc_html_e('Visualizza PDF', 'born-to-ride-booking'); ?>
                    </a>
                </td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- Dati Cliente -->
    <div class="btr-section">
        <h3><?php esc_html_e('Dati Cliente', 'born-to-ride-booking'); ?></h3>
        <table>
            <tr>
                <th><?php esc_html_e('Nome', 'born-to-ride-booking'); ?></th>
                <td><?php echo esc_html($cliente_nome); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Email', 'born-to-ride-booking'); ?></th>
                <td><a href="mailto:<?php echo esc_attr($cliente_email); ?>"><?php echo esc_html($cliente_email); ?></a></td>
            </tr>
            <?php if ($cliente_telefono): ?>
            <tr>
                <th><?php esc_html_e('Telefono', 'born-to-ride-booking'); ?></th>
                <td><a href="tel:<?php echo esc_attr($cliente_telefono); ?>"><?php echo esc_html($cliente_telefono); ?></a></td>
            </tr>
            <?php endif; ?>
            <?php if ($note_cliente): ?>
            <tr>
                <th><?php esc_html_e('Note del Cliente', 'born-to-ride-booking'); ?></th>
                <td><?php echo nl2br(esc_html($note_cliente)); ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- Dati Anagrafici Partecipanti -->
    <div class="btr-section">
        <h3><?php esc_html_e('Dati Anagrafici Partecipanti', 'born-to-ride-booking'); ?></h3>
        <table>
            <thead>
                <tr>
                    <th><?php esc_html_e('Nome', 'born-to-ride-booking'); ?></th>
                    <th><?php esc_html_e('Cognome', 'born-to-ride-booking'); ?></th>
                    <th><?php esc_html_e('Email', 'born-to-ride-booking'); ?></th>
                    <th><?php esc_html_e('Telefono', 'born-to-ride-booking'); ?></th>
                    <th><?php esc_html_e('Data di nascita', 'born-to-ride-booking'); ?></th>
                    <th><?php esc_html_e('Città di nascita', 'born-to-ride-booking'); ?></th>
                    <th><?php esc_html_e('CF', 'born-to-ride-booking'); ?></th>
                    <th><?php esc_html_e('Tipo', 'born-to-ride-booking'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($anagrafici)):
                foreach ($anagrafici as $persona): 
                    $fascia = $persona['fascia'] ?? '';
                    $tipo_label = '';
                    
                    if ($fascia === 'neonato') {
                        $tipo_label = 'Neonato';
                    } elseif (!empty($fascia) && isset($child_labels[$fascia])) {
                        $tipo_label = $child_labels[$fascia];
                    } elseif (!empty($fascia) && strpos($fascia, 'f') === 0) {
                        $tipo_label = 'Bambino ' . $fascia;
                    } else {
                        $tipo_label = 'Adulto';
                    }
                ?>
                <tr>
                    <td><?php echo esc_html($persona['nome'] ?? ''); ?></td>
                    <td><?php echo esc_html($persona['cognome'] ?? ''); ?></td>
                    <td><?php echo esc_html($persona['email'] ?? '-'); ?></td>
                    <td><?php echo esc_html($persona['telefono'] ?? '-'); ?></td>
                    <td><?php echo esc_html($persona['data_nascita'] ?? '-'); ?></td>
                    <td><?php echo esc_html($persona['citta_nascita'] ?? '-'); ?></td>
                    <td><?php echo esc_html($persona['codice_fiscale'] ?? '-'); ?></td>
                    <td><strong><?php echo esc_html($tipo_label); ?></strong></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" style="text-align: center; color: #666;">
                        <?php esc_html_e('Nessun partecipante registrato', 'born-to-ride-booking'); ?>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Camere Prenotate -->
    <?php if (!empty($camere)): ?>
    <div class="btr-section">
        <h3><?php esc_html_e('Camere Prenotate', 'born-to-ride-booking'); ?></h3>
        <div class="btr-rooms-container">
            <?php 
            foreach ($camere as $index => $camera): 
                $quantita = isset($camera['quantita']) ? intval($camera['quantita']) : 1;
                
                for ($room_instance = 0; $room_instance < $quantita; $room_instance++):
                    $room_key = $index . '-' . ($room_instance + 1);
                    $partecipanti_camera = [];
                    $tipo_letto = null;
                    
                    if (!empty($anagrafici)) {
                        foreach ($anagrafici as $persona) {
                            if (($persona['camera'] ?? '') === $room_key) {
                                $nome_completo = trim(($persona['nome'] ?? '') . ' ' . ($persona['cognome'] ?? ''));
                                $tipo_partecipante = '';
                                
                                if (!empty($persona['fascia'])) {
                                    if ($persona['fascia'] === 'neonato') {
                                        $tipo_partecipante = ' (neonato)';
                                    } elseif (isset($child_labels[$persona['fascia']])) {
                                        $label = preg_replace('/^Bambini\s+/', '', $child_labels[$persona['fascia']]);
                                        $tipo_partecipante = ' (' . $label . ')';
                                    }
                                }
                                
                                $partecipanti_camera[] = $nome_completo . $tipo_partecipante;
                                
                                if (empty($tipo_letto) && !empty($persona['tipo_letto'])) {
                                    $tipo_letto = $persona['tipo_letto'];
                                }
                            }
                        }
                    }
            ?>
            <div class="btr-room-box">
                <h4><?php echo esc_html($camera['tipo'] ?? 'Camera'); ?> <?php echo $quantita > 1 ? '#' . ($room_instance + 1) : ''; ?></h4>
                <?php if ($tipo_letto): ?>
                    <div class="room-type">
                        <?php echo $tipo_letto === 'matrimoniale' ? 'Letto matrimoniale' : 'Letti singoli'; ?>
                    </div>
                <?php endif; ?>
                <div class="participants-list">
                    <strong>Partecipanti:</strong><br>
                    <?php if (!empty($partecipanti_camera)): ?>
                        <?php foreach ($partecipanti_camera as $partecipante): ?>
                            • <?php echo esc_html($partecipante); ?><br>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <em>Nessun partecipante assegnato</em>
                    <?php endif; ?>
                </div>
                <?php if (!empty($camera['totale_camera']) && $room_instance == 0): ?>
                    <div class="room-price">
                        Totale: €<?php echo number_format($camera['totale_camera'], 2, ',', '.'); ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php 
                endfor;
            endforeach; 
            ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Dettagli Aggiuntivi per Partecipante -->
    <div class="btr-section">
        <h3><?php esc_html_e('Dettagli Aggiuntivi per Partecipante', 'born-to-ride-booking'); ?></h3>
        <table>
            <thead>
                <tr>
                    <th><?php esc_html_e('Partecipante', 'born-to-ride-booking'); ?></th>
                    <th colspan="2"><?php esc_html_e('Dettagli', 'born-to-ride-booking'); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($anagrafici)):
                foreach ($anagrafici as $persona): 
                    $full_name = trim(($persona['nome'] ?? '') . ' ' . ($persona['cognome'] ?? ''));
                    $email = $persona['email'] ?? '';
                    
                    // Costruisci lista assicurazioni
                    $ass_output = '<em>' . esc_html__('Nessuna assicurazione selezionata.', 'born-to-ride-booking') . '</em>';
                    if (!empty($persona['assicurazioni_dettagliate'])) {
                        $ass_output = '<ul style="margin: 4px 0 8px 18px;">';
                        foreach ($persona['assicurazioni_dettagliate'] as $key => $assic) {
                            $descr = $assic['descrizione'] ?? $key;
                            $imp = number_format(floatval($assic['importo'] ?? 0), 2, ',', '.');
                            $ass_output .= '<li>' . esc_html($descr) . ' – €' . $imp . '</li>';
                        }
                        $ass_output .= '</ul>';
                    }
                    
                    // Costruisci lista costi extra
                    $extra_output = '<em>' . esc_html__('Nessun costo extra selezionato.', 'born-to-ride-booking') . '</em>';
                    if (!empty($persona['costi_extra_dettagliate'])) {
                        $extra_output = '<ul style="margin: 4px 0 8px 18px;">';
                        foreach ($persona['costi_extra_dettagliate'] as $extra) {
                            $nome = $extra['descrizione'] ?? '';
                            $imp = number_format(floatval($extra['importo'] ?? 0), 2, ',', '.');
                            $extra_output .= '<li>' . esc_html($nome) . ' – €' . $imp . '</li>';
                        }
                        $extra_output .= '</ul>';
                    }
                ?>
                <tr>
                    <td>
                        <?php echo esc_html($full_name); ?><br>
                        <small><?php echo esc_html($email); ?></small>
                    </td>
                    <td style="width: 45%; vertical-align: top;">
                        <div style="margin-bottom:10px;">
                            <div style="font-weight:600;margin-bottom:2px;"><?php esc_html_e('Assicurazioni', 'born-to-ride-booking'); ?></div>
                            <?php echo $ass_output; ?>
                        </div>
                    </td>
                    <td style="width: 45%; vertical-align: top;">
                        <div style="margin-bottom:10px;">
                            <div style="font-weight:600;margin-bottom:2px;"><?php esc_html_e('Costi Extra', 'born-to-ride-booking'); ?></div>
                            <?php echo $extra_output; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Riepilogo Prezzi -->
    <div class="btr-section">
        <h3><?php esc_html_e('Riepilogo Prezzi', 'born-to-ride-booking'); ?></h3>
        
        <?php if (!empty($riepilogo_calcoli_dettagliato)): 
            $riepilogo = is_string($riepilogo_calcoli_dettagliato) ? unserialize($riepilogo_calcoli_dettagliato) : $riepilogo_calcoli_dettagliato;
            if (!empty($riepilogo['partecipanti'])): ?>
            <div class="btr-price-summary">
                <?php 
                // Mostra dettaglio per categoria di partecipanti
                foreach ($riepilogo['partecipanti'] as $tipo => $dati):
                    if ($tipo === 'adulti' || strpos($tipo, 'bambini_') === 0):
                ?>
                    <div style="margin-bottom: 15px;">
                        <h4 style="margin: 0 0 8px 0; font-size: 1rem;">
                            <?php 
                            if ($tipo === 'adulti') {
                                echo 'Adulti';
                            } elseif (strpos($tipo, 'bambini_') === 0 && !empty($dati['etichetta'])) {
                                echo 'Bambini ' . $dati['etichetta'];
                            } else {
                                echo ucfirst(str_replace('_', ' ', $tipo));
                            }
                            ?> (<?php echo $dati['quantita']; ?>)
                        </h4>
                        <div class="btr-price-row">
                            <span>Prezzo pacchetto: €<?php echo number_format($dati['prezzo_base_unitario'], 2, ',', '.'); ?> × <?php echo $dati['quantita']; ?></span>
                            <span>€<?php echo number_format($dati['subtotale_base'], 2, ',', '.'); ?></span>
                        </div>
                        <?php if (!empty($dati['subtotale_supplemento_base'])): ?>
                        <div class="btr-price-row">
                            <span>Supplemento base: €<?php echo number_format($dati['supplemento_base_unitario'], 2, ',', '.'); ?> × <?php echo $dati['quantita']; ?></span>
                            <span>€<?php echo number_format($dati['subtotale_supplemento_base'], 2, ',', '.'); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($dati['subtotale_notte_extra'])): ?>
                        <div class="btr-price-row">
                            <span>Notti extra: €<?php echo number_format($dati['notte_extra_unitario'], 2, ',', '.'); ?> × <?php echo $dati['quantita']; ?></span>
                            <span>€<?php echo number_format($dati['subtotale_notte_extra'], 2, ',', '.'); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($dati['subtotale_supplemento_extra'])): ?>
                        <div class="btr-price-row">
                            <span>Supplemento extra: €<?php echo number_format($dati['supplemento_extra_unitario'], 2, ',', '.'); ?> × <?php echo $dati['quantita']; ?></span>
                            <span>€<?php echo number_format($dati['subtotale_supplemento_extra'], 2, ',', '.'); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="btr-price-row subtotal">
                            <span>Subtotale</span>
                            <span>€<?php echo number_format($dati['totale'], 2, ',', '.'); ?></span>
                        </div>
                    </div>
                <?php 
                    endif;
                endforeach;
                ?>
                
                <?php if (!empty($totale_assicurazioni)): ?>
                <div class="btr-price-row">
                    <span>Totale Assicurazioni</span>
                    <span>€<?php echo number_format($totale_assicurazioni, 2, ',', '.'); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($totale_costi_extra)): ?>
                <div class="btr-price-row">
                    <span>Totale Costi Extra</span>
                    <span>€<?php echo number_format($totale_costi_extra, 2, ',', '.'); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="btr-price-row total">
                    <span>Totale Preventivo</span>
                    <span>€<?php echo number_format($totale_finale, 2, ',', '.'); ?></span>
                </div>
            </div>
            <?php endif; ?>
        <?php else: ?>
            <table>
                <tr>
                    <th><?php esc_html_e('Prezzo Pacchetto', 'born-to-ride-booking'); ?></th>
                    <td>€<?php echo number_format($prezzo_totale, 2, ',', '.'); ?></td>
                </tr>
                <?php if (!empty($totale_assicurazioni)): ?>
                <tr>
                    <th><?php esc_html_e('Assicurazioni', 'born-to-ride-booking'); ?></th>
                    <td>€<?php echo number_format($totale_assicurazioni, 2, ',', '.'); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($totale_costi_extra)): ?>
                <tr>
                    <th><?php esc_html_e('Costi Extra', 'born-to-ride-booking'); ?></th>
                    <td>€<?php echo number_format($totale_costi_extra, 2, ',', '.'); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th><?php esc_html_e('Totale Finale', 'born-to-ride-booking'); ?></th>
                    <td class="btr-highlight">€<?php echo number_format($totale_finale, 2, ',', '.'); ?></td>
                </tr>
            </table>
        <?php endif; ?>
    </div>

    <?php if ($note_interne): ?>
    <!-- Note Interne -->
    <div class="btr-section">
        <h3><?php esc_html_e('Note Interne', 'born-to-ride-booking'); ?></h3>
        <div class="btr-info-message">
            <?php echo nl2br(esc_html($note_interne)); ?>
        </div>
    </div>
    <?php endif; ?>

</div>