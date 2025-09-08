<?php
/**
 * Template Name: Preventivo Review
 * Description: Template personalizzato per visualizzare il riepilogo del preventivo.
 */

// Evita l'accesso diretto
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

get_header();

// Recupera l'ID del preventivo dalla query string
$preventivo_id = isset( $_GET['preventivo_id'] ) ? intval( $_GET['preventivo_id'] ) : 0;

if ( ! $preventivo_id ) {
    echo '<p>' . esc_html__( 'ID preventivo non valido.', 'born-to-ride-booking' ) . '</p>';
    get_footer();
    exit;
}

// Recupera il preventivo
$preventivo = get_post( $preventivo_id );

if ( ! $preventivo || $preventivo->post_type !== 'btr_preventivi' ) {
    echo '<p>' . esc_html__( 'Preventivo non trovato.', 'born-to-ride-booking' ) . '</p>';
    get_footer();
    exit;
}

// Recupera i metadati del preventivo
$cliente_nome        = get_post_meta( $preventivo_id, '_cliente_nome', true );
$cliente_email       = get_post_meta( $preventivo_id, '_cliente_email', true );
$cliente_telefono    = get_post_meta( $preventivo_id, '_cliente_telefono', true );
$pacchetto_id        = get_post_meta( $preventivo_id, '_pacchetto_id', true );
$prezzo_totale       = get_post_meta( $preventivo_id, '_prezzo_totale', true );
$camere_selezionate  = get_post_meta( $preventivo_id, '_camere_selezionate', true );
$stato_preventivo    = get_post_meta( $preventivo_id, '_stato_preventivo', true );
$durata_giorni       = get_post_meta( $preventivo_id, '_durata', true );

// Recupera costi extra
$costi_extra_durata = get_post_meta( $preventivo_id, '_costi_extra_durata', true );
$anagrafici_preventivo = get_post_meta( $preventivo_id, '_anagrafici_preventivo', true );

// Usa la classe centralizzata per calcolare i costi extra
$price_calculator = btr_price_calculator();
$extra_costs_result = $price_calculator->calculate_extra_costs($anagrafici_preventivo, $costi_extra_durata);

// Estrai i dati per compatibilità con il template esistente
$extra_costs_summary = $extra_costs_result['aggiunte'];
$riduzioni_summary = $extra_costs_result['riduzioni'];
$total_extra_costs = $extra_costs_result['totale_aggiunte'];
$total_riduzioni = $extra_costs_result['totale_riduzioni'];
$total_extra_costs_net = $extra_costs_result['totale'];

// Mantieni la funzione legacy per compatibilità (ma ora usa la classe centralizzata)
function btr_aggregate_extra_costs($anagrafici_preventivo, $costi_extra_durata, $durata_giorni) {
    $price_calculator = btr_price_calculator();
    return $price_calculator->calculate_extra_costs($anagrafici_preventivo, $costi_extra_durata);
    
    // 1. Processa costi extra per durata
    if ( ! empty( $costi_extra_durata ) && is_array( $costi_extra_durata ) ) {
        foreach ( $costi_extra_durata as $slug => $costo ) {
            if ( ! empty( $costo['attivo'] ) ) {
                $importo_base = floatval( $costo['importo'] ?? 0 );
                $nome = $costo['nome'] ?? ucfirst( str_replace( '-', ' ', $slug ) );
                
                // I costi per durata sono applicati una sola volta per tutto il gruppo
                $importo_totale = $importo_base;
                
                $extra_costs_summary[$slug] = [
                    'nome' => $nome,
                    'tipo' => 'durata',
                    'importo_unitario' => $importo_base,
                    'importo_totale' => $importo_totale,
                    'quantita' => 1,
                    'descrizione' => sprintf(__('Costo per durata: %s', 'born-to-ride-booking'), $nome)
                ];
                
                $total_extra_costs += $importo_totale;
            }
        }
    }
    
    // 2. Processa costi extra per persona
    if ( ! empty( $anagrafici_preventivo ) && is_array( $anagrafici_preventivo ) ) {
        $person_costs = [];
        
        foreach ( $anagrafici_preventivo as $persona ) {
            if ( ! empty( $persona['costi_extra_dettagliate'] ) && is_array( $persona['costi_extra_dettagliate'] ) ) {
                foreach ( $persona['costi_extra_dettagliate'] as $cost_key => $dettaglio ) {
                    if ( ! empty( $dettaglio['attivo'] ) ) {
                        $slug = $dettaglio['slug'] ?? $cost_key;
                        $nome = $dettaglio['nome'] ?? ucfirst( str_replace( '-', ' ', $slug ) );
                        $importo_base = floatval( $dettaglio['importo'] ?? 0 );
                        $moltiplica_persone = ! empty( $dettaglio['moltiplica_persone'] );
                        $moltiplica_durata = ! empty( $dettaglio['moltiplica_durata'] );
                        
                        // Calcola l'importo finale considerando i moltiplicatori
                        $importo_finale = $importo_base;
                        if ( $moltiplica_durata && $durata_giorni > 0 ) {
                            $importo_finale *= intval( $durata_giorni );
                        }
                        
                        // Aggrega per slug
                        if ( ! isset( $person_costs[$slug] ) ) {
                            $person_costs[$slug] = [
                                'nome' => $nome,
                                'importo_unitario' => $importo_base,
                                'importo_per_persona' => $importo_finale,
                                'moltiplica_persone' => $moltiplica_persone,
                                'moltiplica_durata' => $moltiplica_durata,
                                'persone' => 0,
                                'totale' => 0
                            ];
                        }
                        
                        $person_costs[$slug]['persone']++;
                        $person_costs[$slug]['totale'] += $importo_finale;
                    }
                }
            }
        }
        
        // Aggiungi i costi per persona al summary
        foreach ( $person_costs as $slug => $cost_data ) {
            $nome = $cost_data['nome'];
            $persone = $cost_data['persone'];
            $importo_totale = $cost_data['totale'];
            
            $descrizione_parts = [];
            if ( $cost_data['moltiplica_persone'] ) {
                $descrizione_parts[] = sprintf(__('%d persone', 'born-to-ride-booking'), $persone);
            }
            if ( $cost_data['moltiplica_durata'] && $durata_giorni > 0 ) {
                $descrizione_parts[] = sprintf(__('%d giorni', 'born-to-ride-booking'), intval($durata_giorni));
            }
            
            $descrizione = $nome;
            if ( ! empty( $descrizione_parts ) ) {
                $descrizione .= sprintf(' (%s)', implode(', ', $descrizione_parts));
            }
            
            $extra_costs_summary[$slug . '_persona'] = [
                'nome' => $nome,
                'tipo' => 'persona',
                'importo_unitario' => $cost_data['importo_unitario'],
                'importo_totale' => $importo_totale,
                'quantita' => $persone,
                'descrizione' => $descrizione
            ];
            
            $total_extra_costs += $importo_totale;
        }
    }
    
    return [
        'summary' => $extra_costs_summary,
        'total' => $total_extra_costs
    ];
}

// Calcola i costi extra aggregati
$extra_costs_data = btr_aggregate_extra_costs($anagrafici_preventivo, $costi_extra_durata, $durata_giorni);
$extra_costs_summary = $extra_costs_data['summary'];
$total_extra_costs = $extra_costs_data['total'];

// Calcola il prezzo totale corretto includendo i costi extra
$prezzo_base = floatval($prezzo_totale);
$prezzo_totale_con_extra = $prezzo_base + $total_extra_costs;

// Recupera il nome del pacchetto
$pacchetto_nome = get_the_title( $pacchetto_id );

// Recupera la data scelta dal metadato _data_pacchetto
$data_scelta = get_post_meta( $preventivo_id, '_data_pacchetto', true );
if ( empty( $data_scelta ) ) {
    $data_scelta = '-';
}

// Calcola il numero totale di persone e il totale sconto
$totale_persone = 0;
$totale_sconto = 0;
if ( ! empty( $camere_selezionate ) && is_array( $camere_selezionate ) ) {
    foreach ( $camere_selezionate as $camera ) {
        $capacity = 1; // Default capacity
        if ( isset( $camera['tipo'] ) ) {
            $capacity = match ( strtolower( $camera['tipo'] ) ) {
                'singola'   => 1,
                'doppia'    => 2,
                'tripla'    => 3,
                'quadrupla' => 4,
                'quintupla' => 5,
                default     => 1,
            };
        }
        $totale_persone += $capacity * intval( $camera['quantita'] ?? 0 );
        $totale_sconto += ( $camera['prezzo_per_persona'] ?? 0 ) * ( $camera['quantita'] ?? 0 ) * ( $camera['sconto'] ?? 0 ) / 100;
    }
}

// Stampa il riepilogo del preventivo
?>
<style>
.riepilogo-preventivo {
    max-width: 1200px;
    margin: 20px auto;
    padding: 20px;
    font-family: Arial, sans-serif;
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
        <p><strong><?php esc_html_e( 'Numero Totale Persone:', 'born-to-ride-booking' ); ?></strong> <?php echo intval( $totale_persone ); ?></p>
        <p><strong><?php esc_html_e( 'Sconto Totale:', 'born-to-ride-booking' ); ?></strong> €<?php echo esc_html( number_format( $totale_sconto, 2 ) ); ?></p>
        <p><strong><?php esc_html_e( 'Prezzo Base:', 'born-to-ride-booking' ); ?></strong> €<?php echo esc_html( number_format( $prezzo_base, 2 ) ); ?></p>
        <?php if ( $total_extra_costs > 0 ) : ?>
        <p><strong><?php esc_html_e( 'Costi Extra Totali:', 'born-to-ride-booking' ); ?></strong> €<?php echo esc_html( number_format( $total_extra_costs, 2 ) ); ?></p>
        <?php endif; ?>
        <p><strong><?php esc_html_e( 'Prezzo Totale:', 'born-to-ride-booking' ); ?></strong> €<?php echo esc_html( number_format( $prezzo_totale_con_extra, 2 ) ); ?></p>
        <p><strong><?php esc_html_e( 'Stato Preventivo:', 'born-to-ride-booking' ); ?></strong> <?php echo esc_html( ucfirst( $stato_preventivo ) ); ?></p>
    </div>

    <!-- Dettaglio camere con costi extra -->
    <?php if ( ! empty( $camere_selezionate ) ) : ?>
        <h2><?php esc_html_e( 'Dettaglio Camere:', 'born-to-ride-booking' ); ?></h2>
        <table class="widefat striped">
            <thead>
            <tr>
                <th><?php esc_html_e( 'Tipo', 'born-to-ride-booking' ); ?></th>
                <th><?php esc_html_e( 'Camere', 'born-to-ride-booking' ); ?></th>
                <th><?php esc_html_e( 'Prezzo per Persona', 'born-to-ride-booking' ); ?></th>
                <th><?php esc_html_e( 'Supplemento', 'born-to-ride-booking' ); ?></th>
                <th><?php esc_html_e( 'Note', 'born-to-ride-booking' ); ?></th>
                <th><?php esc_html_e( 'Totale', 'born-to-ride-booking' ); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ( $camere_selezionate as $camera ) : ?>
                <tr>
                    <td><?php echo esc_html( $camera['tipo'] ?? '-' ); ?></td>
                    <td><?php echo intval( $camera['quantita'] ?? 0 ); ?></td>
                    <td>€<?php echo number_format( floatval( $camera['prezzo_per_persona'] ?? 0 ), 2 ); ?></td>
                    <td>€<?php echo number_format( floatval( $camera['supplemento'] ?? 0 ), 2 ); ?></td>
                    <td><?php echo esc_html( ucfirst( $camera['tipo'] ?? '-' ) ); ?></td>
                    <td>€<?php echo number_format( floatval( $camera['totale_camera'] ?? 0 ), 2 ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <!-- Riepilogo Costi Extra -->
    <?php if ( ! empty( $extra_costs_summary ) ) : ?>
        <h2><?php esc_html_e( 'Riepilogo Costi Extra Selezionati:', 'born-to-ride-booking' ); ?></h2>
        <table class="widefat striped">
            <thead>
            <tr>
                <th><?php esc_html_e( 'Servizio', 'born-to-ride-booking' ); ?></th>
                <th><?php esc_html_e( 'Tipo', 'born-to-ride-booking' ); ?></th>
                <th><?php esc_html_e( 'Descrizione', 'born-to-ride-booking' ); ?></th>
                <th><?php esc_html_e( 'Prezzo Unitario', 'born-to-ride-booking' ); ?></th>
                <th><?php esc_html_e( 'Quantità/Persone', 'born-to-ride-booking' ); ?></th>
                <th><?php esc_html_e( 'Totale', 'born-to-ride-booking' ); ?></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ( $extra_costs_summary as $slug => $cost_data ) : ?>
                <tr>
                    <td><strong><?php echo esc_html( $cost_data['nome'] ); ?></strong></td>
                    <td>
                        <?php if ( $cost_data['tipo'] === 'durata' ) : ?>
                            <span class="badge badge-primary"><?php esc_html_e( 'Per Durata', 'born-to-ride-booking' ); ?></span>
                        <?php else : ?>
                            <span class="badge badge-success"><?php esc_html_e( 'Per Persona', 'born-to-ride-booking' ); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html( $cost_data['descrizione'] ); ?></td>
                    <td>€<?php echo number_format( floatval( $cost_data['importo_unitario'] ), 2 ); ?></td>
                    <td><?php echo intval( $cost_data['quantita'] ); ?></td>
                    <td><strong>€<?php echo number_format( floatval( $cost_data['importo_totale'] ), 2 ); ?></strong></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background-color: #f9f9f9; font-weight: bold;">
                    <td colspan="5" style="text-align: right;"><?php esc_html_e( 'Totale Costi Extra:', 'born-to-ride-booking' ); ?></td>
                    <td><strong>€<?php echo number_format( $total_extra_costs, 2 ); ?></strong></td>
                </tr>
            </tfoot>
        </table>
    <?php else : ?>
        <h3><?php esc_html_e( 'Nessun costo extra selezionato', 'born-to-ride-booking' ); ?></h3>
    <?php endif; ?>

    <!-- Dettaglio Partecipanti con Costi Extra -->
    <?php if ( ! empty( $anagrafici_preventivo ) && is_array( $anagrafici_preventivo ) ) : ?>
        <h2><?php esc_html_e( 'Dettaglio Partecipanti:', 'born-to-ride-booking' ); ?></h2>
        <div class="participants-details">
            <?php foreach ( $anagrafici_preventivo as $index => $persona ) : ?>
                <div class="participant-card" style="border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 5px;">
                    <h4><?php printf( esc_html__( 'Partecipante %d: %s %s', 'born-to-ride-booking' ), 
                        $index + 1, 
                        esc_html( $persona['nome'] ?? '' ), 
                        esc_html( $persona['cognome'] ?? '' ) 
                    ); ?></h4>
                    
                    <div class="participant-info" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                        <p><strong><?php esc_html_e( 'Email:', 'born-to-ride-booking' ); ?></strong> <?php echo esc_html( $persona['email'] ?? '-' ); ?></p>
                        <p><strong><?php esc_html_e( 'Telefono:', 'born-to-ride-booking' ); ?></strong> <?php echo esc_html( $persona['telefono'] ?? '-' ); ?></p>
                        <p><strong><?php esc_html_e( 'Camera:', 'born-to-ride-booking' ); ?></strong> <?php echo esc_html( $persona['camera'] ?? '-' ); ?></p>
                        <p><strong><?php esc_html_e( 'Tipo Camera:', 'born-to-ride-booking' ); ?></strong> <?php echo esc_html( $persona['camera_tipo'] ?? '-' ); ?></p>
                        <?php if ( ! empty( $persona['tipo_letto'] ) && ( $persona['camera_tipo'] === 'Doppia' || $persona['camera_tipo'] === 'Doppia/Matrimoniale' ) ) : ?>
                            <p><strong><?php esc_html_e( 'Tipo Letto:', 'born-to-ride-booking' ); ?></strong> <?php echo esc_html( $persona['tipo_letto'] === 'letti_singoli' ? 'Letti Singoli' : 'Letto Matrimoniale' ); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ( ! empty( $persona['costi_extra_dettagliate'] ) && is_array( $persona['costi_extra_dettagliate'] ) ) : ?>
                        <h5><?php esc_html_e( 'Costi Extra Selezionati:', 'born-to-ride-booking' ); ?></h5>
                        <ul style="margin: 0; padding-left: 20px;">
                            <?php foreach ( $persona['costi_extra_dettagliate'] as $cost_key => $dettaglio ) : ?>
                                <?php if ( ! empty( $dettaglio['attivo'] ) ) : ?>
                                    <li>
                                        <strong><?php echo esc_html( $dettaglio['nome'] ?? ucfirst( str_replace( '-', ' ', $cost_key ) ) ); ?></strong>
                                        - €<?php echo number_format( floatval( $dettaglio['importo'] ?? 0 ), 2 ); ?>
                                        <?php if ( ! empty( $dettaglio['moltiplica_durata'] ) && $durata_giorni > 0 ) : ?>
                                            <?php printf( esc_html__( ' x %d giorni = €%s', 'born-to-ride-booking' ), 
                                                intval( $durata_giorni ), 
                                                number_format( floatval( $dettaglio['importo'] ?? 0 ) * intval( $durata_giorni ), 2 )
                                            ); ?>
                                        <?php endif; ?>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p><em><?php esc_html_e( 'Nessun costo extra selezionato per questo partecipante.', 'born-to-ride-booking' ); ?></em></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>
<?php
get_footer();