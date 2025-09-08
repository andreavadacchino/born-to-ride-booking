<?php
/**
 * Template per la visualizzazione del riepilogo preventivo con gestione separata di costi extra e riduzioni
 * 
 * @package Born_To_Ride_Booking
 * @since 1.0.53
 */

// Recupera i dati del preventivo
$preventivo_id = isset( $_GET['preventivo_id'] ) ? intval( $_GET['preventivo_id'] ) : 0;

if ( ! $preventivo_id ) {
    wp_die( __( 'ID preventivo non valido.', 'born-to-ride-booking' ) );
}

// Recupera i metadati del preventivo
$cliente_nome = get_post_meta( $preventivo_id, '_cliente_nome', true );
$cliente_email = get_post_meta( $preventivo_id, '_cliente_email', true );
$cliente_telefono = get_post_meta( $preventivo_id, '_cliente_telefono', true );
$pacchetto_id = get_post_meta( $preventivo_id, '_pacchetto_id', true );
$prezzo_totale = get_post_meta( $preventivo_id, '_prezzo_totale', true );
$camere_selezionate = get_post_meta( $preventivo_id, '_camere_selezionate', true );
// Recupera i dettagli dei partecipanti
$num_adults = intval( get_post_meta( $preventivo_id, '_num_adults', true ) );
$num_children = intval( get_post_meta( $preventivo_id, '_num_children', true ) );
$num_neonati = intval( get_post_meta( $preventivo_id, '_num_neonati', true ) );
$totale_persone = $num_adults + $num_children + $num_neonati;
$totale_sconto = 0; // Calcolare se necessario
$durata = get_post_meta( $preventivo_id, '_durata', true );

// Estrai giorni dalla durata
$durata_giorni = 0;
if ( preg_match( '/(\d+)\s*giorni/i', $durata, $matches ) ) {
    $durata_giorni = intval( $matches[1] );
}

// Recupera costi extra
$costi_extra_durata = get_post_meta( $preventivo_id, '_costi_extra_durata', true );
$anagrafici_preventivo = get_post_meta( $preventivo_id, '_anagrafici_preventivo', true );

// Usa la classe centralizzata per calcolare i costi extra
$price_calculator = btr_price_calculator();
$extra_costs_result = $price_calculator->calculate_extra_costs($anagrafici_preventivo, $costi_extra_durata);

// Estrai i dati per il template
$aggiunte_summary = $extra_costs_result['aggiunte'];
$riduzioni_summary = $extra_costs_result['riduzioni'];
$total_aggiunte = $extra_costs_result['totale_aggiunte'];
$total_riduzioni = $extra_costs_result['totale_riduzioni'];
$total_extra_costs_net = $extra_costs_result['totale'];
$dettaglio_partecipanti = $extra_costs_result['dettaglio_partecipanti'];

// Calcola il prezzo totale corretto includendo i costi extra
$prezzo_base = floatval($prezzo_totale);
$prezzo_totale_con_extra = $prezzo_base + $total_extra_costs_net;

// Recupera il nome del pacchetto
$pacchetto_nome = get_the_title( $pacchetto_id );

// Recupera la data scelta
$data_scelta = get_post_meta( $preventivo_id, '_data_pacchetto', true );
if ( empty( $data_scelta ) ) {
    $data_scelta = '-';
}

?>

<style>
.riepilogo-preventivo {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.riepilogo-preventivo h1 {
    color: #333;
    margin-bottom: 30px;
    padding-bottom: 10px;
    border-bottom: 2px solid #0073aa;
}

.riepilogo-preventivo h2 {
    color: #0073aa;
    margin-top: 30px;
    margin-bottom: 20px;
}

.riepilogo-preventivo h3 {
    color: #555;
    margin-top: 20px;
    margin-bottom: 15px;
}

.riepilogo-preventivo .badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.riepilogo-preventivo .badge-primary {
    background-color: #007cba;
    color: white;
}

.riepilogo-preventivo .badge-success {
    background-color: #46b450;
    color: white;
}

.riepilogo-preventivo .badge-warning {
    background-color: #f0ad4e;
    color: white;
}

.riepilogo-preventivo .badge-danger {
    background-color: #dc3545;
    color: white;
}

.riepilogo-preventivo .participant-card {
    background: #f8f9fa;
    border-left: 4px solid #007cba;
}

.riepilogo-preventivo .participant-info {
    background: white;
    padding: 10px;
    border-radius: 4px;
    margin: 10px 0;
}

.riepilogo-preventivo .widefat {
    width: 100%;
    border-collapse: collapse;
    margin: 15px 0;
}

.riepilogo-preventivo .widefat th,
.riepilogo-preventivo .widefat td {
    padding: 12px 8px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

.riepilogo-preventivo .widefat th {
    background-color: #f1f1f1;
    font-weight: bold;
}

.riepilogo-preventivo .striped tbody tr:nth-child(even) {
    background-color: #f9f9f9;
}

.riepilogo-info-cliente {
    background: #f0f6fc;
    border: 1px solid #c3e6ff;
    border-radius: 6px;
    padding: 20px;
    margin: 20px 0;
}

.riepilogo-info-cliente p {
    margin: 8px 0;
}

.extra-costs-section {
    margin: 30px 0;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 8px;
}

.riduzioni-section {
    background: #fff3cd;
    border: 1px solid #ffeeba;
}

.aggiunte-section {
    background: #d4edda;
    border: 1px solid #c3e6cb;
}

.totale-finale {
    background: #e9ecef;
    padding: 15px;
    margin: 20px 0;
    border-radius: 5px;
    font-size: 1.2em;
    text-align: right;
}

.totale-finale .amount {
    font-weight: bold;
    color: #28a745;
}

.totale-finale .amount.negative {
    color: #dc3545;
}

@media (max-width: 768px) {
    .riepilogo-preventivo {
        padding: 10px;
    }
    
    .participant-info {
        grid-template-columns: 1fr !important;
    }
    
    .widefat {
        font-size: 14px;
    }
    
    .widefat th,
    .widefat td {
        padding: 8px 4px;
    }
}
</style>

<div class="riepilogo-preventivo">
    <h1><?php esc_html_e( 'Riepilogo Preventivo', 'born-to-ride-booking' ); ?></h1>
    
    <div class="riepilogo-info-cliente">
        <p><strong><?php esc_html_e( 'Nome Cliente:', 'born-to-ride-booking' ); ?></strong> <?php echo esc_html( $cliente_nome ); ?></p>
        <p><strong><?php esc_html_e( 'Email Cliente:', 'born-to-ride-booking' ); ?></strong> <?php echo esc_html( $cliente_email ); ?></p>
        <p><strong><?php esc_html_e( 'Telefono Cliente:', 'born-to-ride-booking' ); ?></strong> <?php echo esc_html( $cliente_telefono ); ?></p>
        <p><strong><?php esc_html_e( 'Pacchetto:', 'born-to-ride-booking' ); ?></strong> <?php echo esc_html( $pacchetto_nome ); ?></p>
        <p><strong><?php esc_html_e( 'Data Scelta:', 'born-to-ride-booking' ); ?></strong> <?php echo esc_html( $data_scelta ); ?></p>
        <p><strong><?php esc_html_e( 'Partecipanti:', 'born-to-ride-booking' ); ?></strong> 
            <?php echo intval( $num_adults ); ?> Adulti
            <?php if ( $num_children > 0 ) : ?>, <?php echo intval( $num_children ); ?> Bambini<?php endif; ?>
            <?php if ( $num_neonati > 0 ) : ?>, <?php echo intval( $num_neonati ); ?> Neonati (non paganti)<?php endif; ?>
        </p>
        <p><strong><?php esc_html_e( 'Numero Totale Persone:', 'born-to-ride-booking' ); ?></strong> <?php echo intval( $totale_persone ); ?></p>
        <p><strong><?php esc_html_e( 'Prezzo Pacchetto:', 'born-to-ride-booking' ); ?></strong> €<?php echo esc_html( number_format( $prezzo_base, 2 ) ); ?></p>
    </div>

    <!-- Dettaglio Camere Selezionate -->
    <?php if ( ! empty( $camere_selezionate ) && is_array( $camere_selezionate ) ) : ?>
        <h2><?php esc_html_e( 'Camere Selezionate:', 'born-to-ride-booking' ); ?></h2>
        <table class="widefat striped">
            <thead>
            <tr>
                <th><?php esc_html_e( 'Tipo Camera', 'born-to-ride-booking' ); ?></th>
                <th><?php esc_html_e( 'Quantità', 'born-to-ride-booking' ); ?></th>
                <th><?php esc_html_e( 'Prezzo per Persona', 'born-to-ride-booking' ); ?></th>
                <th><?php esc_html_e( 'Supplemento', 'born-to-ride-booking' ); ?></th>
                <th><?php esc_html_e( 'Totale', 'born-to-ride-booking' ); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php 
            $totale_camere_complessivo = 0;
            foreach ( $camere_selezionate as $camera ) : 
                $tipo = isset( $camera['tipo'] ) ? $camera['tipo'] : '';
                $quantita = isset( $camera['quantita'] ) ? intval( $camera['quantita'] ) : 0;
                $prezzo_per_persona = isset( $camera['prezzo_per_persona'] ) ? floatval( $camera['prezzo_per_persona'] ) : 0;
                $supplemento = isset( $camera['supplemento'] ) ? floatval( $camera['supplemento'] ) : 0;
                $totale_camera = isset( $camera['totale_camera'] ) ? floatval( $camera['totale_camera'] ) : 0;
                $totale_camere_complessivo += $totale_camera;
            ?>
                <tr>
                    <td><strong><?php echo esc_html( $tipo ); ?></strong></td>
                    <td><?php echo esc_html( $quantita ); ?></td>
                    <td>€<?php echo esc_html( number_format( $prezzo_per_persona, 2 ) ); ?></td>
                    <td>€<?php echo esc_html( number_format( $supplemento, 2 ) ); ?></td>
                    <td><strong>€<?php echo esc_html( number_format( $totale_camera, 2 ) ); ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background-color: #f9f9f9; font-weight: bold;">
                    <td colspan="4" style="text-align: right;"><?php esc_html_e( 'Totale Camere:', 'born-to-ride-booking' ); ?></td>
                    <td><strong>€<?php echo number_format( $totale_camere_complessivo, 2 ); ?></strong></td>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>

    <!-- Costi Aggiuntivi (Aggiunte) -->
    <?php if ( ! empty( $aggiunte_summary ) ) : ?>
        <div class="extra-costs-section aggiunte-section">
            <h2><?php esc_html_e( '➕ Costi Aggiuntivi', 'born-to-ride-booking' ); ?></h2>
            <table class="widefat striped">
                <thead>
                <tr>
                    <th><?php esc_html_e( 'Servizio', 'born-to-ride-booking' ); ?></th>
                    <th><?php esc_html_e( 'Prezzo Unitario', 'born-to-ride-booking' ); ?></th>
                    <th><?php esc_html_e( 'Quantità', 'born-to-ride-booking' ); ?></th>
                    <th><?php esc_html_e( 'Partecipanti', 'born-to-ride-booking' ); ?></th>
                    <th><?php esc_html_e( 'Totale', 'born-to-ride-booking' ); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ( $aggiunte_summary as $slug => $cost_data ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( $cost_data['nome'] ); ?></strong></td>
                        <td>€<?php echo number_format( floatval( $cost_data['importo_unitario'] ), 2 ); ?></td>
                        <td><?php echo intval( $cost_data['count'] ); ?></td>
                        <td>
                            <?php 
                            $partecipanti_list = array_slice($cost_data['partecipanti'], 0, 3);
                            echo esc_html( implode(', ', $partecipanti_list) );
                            if (count($cost_data['partecipanti']) > 3) {
                                echo ' ...';
                            }
                            ?>
                        </td>
                        <td><strong>€<?php echo number_format( floatval( $cost_data['totale'] ), 2 ); ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background-color: #f9f9f9; font-weight: bold;">
                        <td colspan="4" style="text-align: right;"><?php esc_html_e( 'Totale Aggiunte:', 'born-to-ride-booking' ); ?></td>
                        <td><strong>€<?php echo number_format( $total_aggiunte, 2 ); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>

    <!-- Riduzioni -->
    <?php if ( ! empty( $riduzioni_summary ) ) : ?>
        <div class="extra-costs-section riduzioni-section">
            <h2><?php esc_html_e( '➖ Riduzioni e Sconti', 'born-to-ride-booking' ); ?></h2>
            <table class="widefat striped">
                <thead>
                <tr>
                    <th><?php esc_html_e( 'Tipo Riduzione', 'born-to-ride-booking' ); ?></th>
                    <th><?php esc_html_e( 'Valore Unitario', 'born-to-ride-booking' ); ?></th>
                    <th><?php esc_html_e( 'Quantità', 'born-to-ride-booking' ); ?></th>
                    <th><?php esc_html_e( 'Partecipanti', 'born-to-ride-booking' ); ?></th>
                    <th><?php esc_html_e( 'Totale Riduzione', 'born-to-ride-booking' ); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ( $riduzioni_summary as $slug => $cost_data ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( $cost_data['nome'] ); ?></strong></td>
                        <td>€<?php echo number_format( floatval( $cost_data['importo_unitario'] ), 2 ); ?></td>
                        <td><?php echo intval( $cost_data['count'] ); ?></td>
                        <td>
                            <?php 
                            $partecipanti_list = array_slice($cost_data['partecipanti'], 0, 3);
                            echo esc_html( implode(', ', $partecipanti_list) );
                            if (count($cost_data['partecipanti']) > 3) {
                                echo ' ...';
                            }
                            ?>
                        </td>
                        <td><strong style="color: #dc3545;">€<?php echo number_format( floatval( $cost_data['totale'] ), 2 ); ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background-color: #f9f9f9; font-weight: bold;">
                        <td colspan="4" style="text-align: right;"><?php esc_html_e( 'Totale Riduzioni:', 'born-to-ride-booking' ); ?></td>
                        <td><strong style="color: #dc3545;">€<?php echo number_format( $total_riduzioni, 2 ); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>

    <!-- Dettaglio per Partecipante -->
    <?php if ( ! empty( $dettaglio_partecipanti ) ) : ?>
        <h2><?php esc_html_e( 'Dettaglio Extra per Partecipante', 'born-to-ride-booking' ); ?></h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
            <?php foreach ( $dettaglio_partecipanti as $nome => $extras ) : ?>
                <div class="participant-card" style="padding: 15px; border-radius: 5px;">
                    <h4 style="margin-top: 0; color: #0073aa;"><?php echo esc_html( $nome ); ?></h4>
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <?php foreach ( $extras as $extra ) : ?>
                            <li style="padding: 5px 0; border-bottom: 1px solid #eee;">
                                <?php echo esc_html( $extra['nome'] ); ?>: 
                                <strong style="float: right; <?php echo $extra['importo'] < 0 ? 'color: #dc3545;' : 'color: #28a745;'; ?>">
                                    €<?php echo number_format( $extra['importo'], 2 ); ?>
                                </strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Riepilogo Totale Finale -->
    <div class="totale-finale">
        <table style="width: 300px; margin-left: auto;">
            <tr>
                <td><?php esc_html_e( 'Prezzo Pacchetto:', 'born-to-ride-booking' ); ?></td>
                <td style="text-align: right;">€<?php echo number_format( $prezzo_base, 2 ); ?></td>
            </tr>
            <?php if ( $total_aggiunte > 0 ) : ?>
            <tr>
                <td><?php esc_html_e( 'Costi Aggiuntivi:', 'born-to-ride-booking' ); ?></td>
                <td style="text-align: right; color: #28a745;">+€<?php echo number_format( $total_aggiunte, 2 ); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ( $total_riduzioni < 0 ) : ?>
            <tr>
                <td><?php esc_html_e( 'Riduzioni:', 'born-to-ride-booking' ); ?></td>
                <td style="text-align: right; color: #dc3545;">€<?php echo number_format( $total_riduzioni, 2 ); ?></td>
            </tr>
            <?php endif; ?>
            <tr style="border-top: 2px solid #333;">
                <td><strong><?php esc_html_e( 'TOTALE FINALE:', 'born-to-ride-booking' ); ?></strong></td>
                <td style="text-align: right;" class="amount <?php echo $prezzo_totale_con_extra < 0 ? 'negative' : ''; ?>">
                    <strong>€<?php echo number_format( $prezzo_totale_con_extra, 2 ); ?></strong>
                </td>
            </tr>
        </table>
    </div>

    <!-- Pulsanti Azione -->
    <div style="text-align: center; margin-top: 30px;">
        <a href="<?php echo esc_url( home_url( '/prenota-pacchetto/?preventivo_id=' . $preventivo_id ) ); ?>" 
           class="button button-primary" style="font-size: 18px; padding: 15px 30px;">
            <?php esc_html_e( 'Procedi con la Prenotazione', 'born-to-ride-booking' ); ?>
        </a>
    </div>
</div>