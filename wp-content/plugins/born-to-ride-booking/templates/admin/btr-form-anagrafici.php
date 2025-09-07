<?php
/**
 * Template: form-anagrafici.php
 * Usa le variabili:
 *   $order_id, $preventivo_id, $anagrafici, $totale_persone, $remaining_time, $camere_acquistate
 * 
 * v1.0.211: Added UX improvements for better accessibility and mobile experience
 */

// Load UX improvements inline (fix auto-save issue)
?>
<link rel="stylesheet" type="text/css" href="<?php echo BTR_PLUGIN_URL; ?>assets/css/btr-checkout-improvements.css?v=1.0.215">
<script src="<?php echo BTR_PLUGIN_URL; ?>assets/js/btr-checkout-ux-improvements.js?v=1.0.215"></script>
<?php
?>

<?php
// Helper locali (best practice 2025): lettura meta con fallback a più chiavi e normalizzazione array
if (!function_exists('btr_meta_chain')) {
    function btr_meta_chain($post_id, $keys, $default = '') {
        foreach ((array) $keys as $key) {
            $v = get_post_meta($post_id, $key, true);
            if ($v !== '' && $v !== null) {
                return $v;
            }
        }
        return $default;
    }
}
if (!function_exists('btr_meta_array_chain')) {
    function btr_meta_array_chain($post_id, $keys) {
        foreach ((array) $keys as $key) {
            $v = get_post_meta($post_id, $key, true);
            if (empty($v)) { continue; }
            if (is_array($v)) { return $v; }
            // Tenta JSON poi serialize
            $decoded = json_decode($v, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
            $unser = @unserialize($v);
            if ($unser !== false && is_array($unser)) {
                return $unser;
            }
        }
        return [];
    }
}

/*
echo '<h3>Dati Meta del Preventivo</h3>';
if (!empty($preventivo_id)) {
    $preventivo_meta = get_post_meta($preventivo_id);
    if (!empty($preventivo_meta)) {
        echo '<ul>';
        foreach ($preventivo_meta as $key => $value) {
            echo '<li><strong>' . esc_html($key) . ':</strong> ' . esc_html(is_array($value) ? implode(', ', $value) : $value) . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>Nessun dato meta trovato per il preventivo.</p>';
    }
}

echo '<h3>Dati Meta dell\'Ordine</h3>';
if (!empty($order_id)) {
    $ordine_meta = get_post_meta($order_id);
    if (!empty($ordine_meta)) {
        echo '<ul>';
        foreach ($ordine_meta as $key => $value) {
            echo '<li><strong>' . esc_html($key) . ':</strong> ' . esc_html(is_array($value) ? implode(', ', $value) : $value) . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>Nessun dato meta trovato per l\'ordine.</p>';
    }
}
*/
?>


<?php

// ID pacchetto con fallback a nuove chiavi _btr_*
$package_id = btr_meta_chain($preventivo_id, ['_btr_pacchetto_id', '_btr_id_pacchetto', '_pacchetto_id']);

// DEBUG: Verifica che il package_id sia valido
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[BTR DEBUG] ============ VERIFICA PACKAGE ID ============');
    error_log('[BTR DEBUG] Preventivo ID: ' . $preventivo_id);
    error_log('[BTR DEBUG] Package ID recuperato: ' . $package_id);
    
    if ($package_id) {
        $package_post = get_post($package_id);
        if ($package_post) {
            error_log('[BTR DEBUG] Package trovato: ' . $package_post->post_title);
            error_log('[BTR DEBUG] Package status: ' . $package_post->post_status);
        } else {
            error_log('[BTR DEBUG] ERRORE: Package post non trovato!');
        }
    } else {
        error_log('[BTR DEBUG] ERRORE: Package ID vuoto!');
    }
}

$btr_destinazione = get_post_meta($package_id, 'btr_destinazione', true);
$localita_destinazione = get_post_meta($package_id, 'btr_localita_destinazione', true);
// Data pacchetto con fallback: preferisci check-in/selected_date
$data_pacchetto = btr_meta_chain($preventivo_id, ['_btr_data_check_in', '_selected_date', '_data_pacchetto', '_date_ranges']);
// Durata con fallback nuova chiave
$durata = btr_meta_chain($preventivo_id, ['_btr_durata', '_durata']);


// --- Durata con eventuale notte extra ---
// Flag notti extra con fallback nuova chiave
$extra_night_flag = btr_meta_chain($preventivo_id, ['_btr_notti_extra_flag', '_extra_night']);
$numero_notti_extra = 0;
if (!empty($extra_night_flag)) {
    // Prova prima dal meta specifico, che è il più affidabile
    $saved_nights = get_post_meta($preventivo_id, '_numero_notti_extra', true);
    if (!empty($saved_nights) && is_numeric($saved_nights)) {
        $numero_notti_extra = intval($saved_nights);
    } else {
        // Fallback: conta le date se disponibili nel meta _btr_extra_night_date
        $extra_night_date_meta = btr_meta_chain($preventivo_id, ['_btr_extra_night_date', '_extra_nights_date'], '');
        if (is_array($extra_night_date_meta)) {
            $numero_notti_extra = count($extra_night_date_meta);
        } elseif (!empty($extra_night_date_meta)) {
            $numero_notti_extra = 1; // Se la data è una stringa singola
        }
    }
}

$durata_label = $durata;
if ($numero_notti_extra > 0) {
    // Usa _n per la traduzione corretta di "notte/notti"
    $durata_label .= ' + ' . sprintf(
        _n('%d notte extra', '%d notti extra', $numero_notti_extra, 'born-to-ride-booking'),
        $numero_notti_extra
    );
}


// Recupera i metadati del preventivo (fallback a nuove chiavi mappate)
$cliente_nome = btr_meta_chain($preventivo_id, ['_btr_cliente_nome', '_cliente_nome']);
$cliente_email = btr_meta_chain($preventivo_id, ['_btr_cliente_email', '_cliente_email']);
$cliente_telefono = btr_meta_chain($preventivo_id, ['_btr_cliente_telefono', '_cliente_telefono']);
$pacchetto_id = $package_id;
$camere_selezionate = btr_meta_array_chain($preventivo_id, ['_btr_camere_selezionate', '_camere_selezionate']);
$stato_preventivo = get_post_meta($preventivo_id, '_stato_preventivo', true);
$data_scelta = $data_pacchetto;
$num_adults = intval(btr_meta_chain($preventivo_id, ['_btr_num_adulti', '_num_adults'], 0));
$num_children = intval(btr_meta_chain($preventivo_id, ['_btr_num_bambini', '_num_children'], 0));
$nome_pacchetto = btr_meta_chain($preventivo_id, ['_btr_pacchetto_nome', '_nome_pacchetto']);
$durata = btr_meta_chain($preventivo_id, ['_btr_durata', '_durata']);
$supplemento_totale = get_post_meta($preventivo_id, '_supplemento_totale', true);
$extra_night_date = btr_meta_chain($preventivo_id, ['_btr_extra_night_date', '_extra_nights_date']);
$riepilogo_calcoli_dettagliato = get_post_meta($preventivo_id, '_riepilogo_calcoli_dettagliato', true);

// Conteggio neonati con fallback
$num_neonati = intval(btr_meta_chain($preventivo_id, ['_num_neonati', '_btr_num_neonati'], 0));

// Fallback per il totale persone se non passato dal caller
if (!isset($totale_persone) || !is_numeric($totale_persone) || intval($totale_persone) <= 0) {
    if (!empty($anagrafici) && is_array($anagrafici)) {
        $totale_persone = count($anagrafici);
    } else {
        $totale_persone = intval($num_adults) + intval($num_children) + intval($num_neonati);
    }
}

// NUOVA FUNZIONALITÀ: Controlla se l'utente ha selezionato "no skipass" nella prima fase
$no_skipass_selected = get_post_meta($preventivo_id, '_no_skipass', true);
if (empty($no_skipass_selected)) {
    // Controlla nei costi extra usando lo slug univoco "no-skipass"
    $costi_extra_preventivo = get_post_meta($preventivo_id, '_costi_extra', true);
    if (!empty($costi_extra_preventivo) && is_array($costi_extra_preventivo)) {
        if (!empty($costi_extra_preventivo['no-skipass'])) {
            $no_skipass_selected = true;
        }
    }
}

if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[BTR DEBUG] No skipass selezionato: ' . ($no_skipass_selected ? 'SI' : 'NO'));
}

// ──────────────────────────────────────────────────────────────
//  PATCH 2025‑07‑08 – usa il breakdown salvato per i totali reali
// ──────────────────────────────────────────────────────────────
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[BTR DEBUG] ============ CONTROLLO RIEPILOGO DETTAGLIATO ============');
    error_log('[BTR DEBUG] Riepilogo dettagliato empty? ' . (empty($riepilogo_calcoli_dettagliato) ? 'SI' : 'NO'));
    error_log('[BTR DEBUG] Tipo: ' . gettype($riepilogo_calcoli_dettagliato));
}

if ( !empty( $riepilogo_calcoli_dettagliato ) ) {
    if ( is_string( $riepilogo_calcoli_dettagliato ) ) {
        $riepilogo_calcoli_dettagliato = maybe_unserialize( $riepilogo_calcoli_dettagliato );
        if ( is_string( $riepilogo_calcoli_dettagliato ) ) {
            $riepilogo_calcoli_dettagliato = json_decode( $riepilogo_calcoli_dettagliato, true );
        }
    }

    if ( is_array( $riepilogo_calcoli_dettagliato ) && !empty( $riepilogo_calcoli_dettagliato['totali'] ) ) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[BTR DEBUG] ✅ USANDO RIEPILOGO DETTAGLIATO per i calcoli');
        }
        // Totale notti extra (solo quota notte, senza costi extra a persona)
        $extra_night_total = floatval( $riepilogo_calcoli_dettagliato['totali']['subtotale_notti_extra'] ?? 0 );

        // Prezzo pacchetto + supplementi base
        $package_plus_supplement = floatval(
            $riepilogo_calcoli_dettagliato['totali']['subtotale_prezzi_base'] ?? 0
        ) + floatval(
            $riepilogo_calcoli_dettagliato['totali']['subtotale_supplementi_base'] ?? 0
        );

        // CORREZIONE 2025-01-20: Usa btr_price_calculator per calcolo coerente
        $prezzo_base = floatval(get_post_meta($preventivo_id, '_prezzo_totale', true));
        // Preferisci _btr_anagrafici se presente, altrimenti _anagrafici_preventivo
        $anagrafici_data = btr_meta_array_chain($preventivo_id, ['_btr_anagrafici', '_anagrafici_preventivo']);
        $costi_extra_durata = get_post_meta($preventivo_id, '_costi_extra_durata', true);
        
        // Usa la classe centralizzata per calcolare i costi extra
        $price_calculator = btr_price_calculator();
        $extra_costs_result = $price_calculator->calculate_extra_costs($anagrafici_data, $costi_extra_durata);
        
        // Calcola assicurazioni separate
        $tot_assic = 0;
        if (is_array($anagrafici_data)) {
            foreach ($anagrafici_data as $persona) {
                if (!empty($persona['assicurazioni_dettagliate'])) {
                    foreach ($persona['assicurazioni_dettagliate'] as $ass) {
                        $tot_assic += isset($ass['importo']) ? (float)$ass['importo'] : 0;
                    }
                }
            }
        }
        
        // Salva il totale assicurazioni originali per l'uso nel JavaScript
        $totale_assicurazioni_originali = $tot_assic;
        
        // Calcola totale usando stessa logica del preventivo-review-fixed.php
        $total_extra_costs_net = $extra_costs_result['totale']; // Include aggiunte e riduzioni
        $prezzo_totale_preventivo = $prezzo_base + $tot_assic + $total_extra_costs_net;
        
        // Mantieni variabili legacy per backward compatibility
        $totale_generale_breakdown = $prezzo_base; // Solo base senza assicurazioni/extra
        $totale_costi_extra_meta = $total_extra_costs_net;
        $totale_sconti_riduzioni = 0; // Già incluso in total_extra_costs_net
        $prezzo_totale            = $prezzo_totale_preventivo;
        
        // Debug per verificare i valori
        // Allinea le variabili usate nel template riepilogo
        $extra_night_cost        = $extra_night_total;          // mostra 124,00 € anziché moltiplicazione fissa
        $package_price_no_extra  = $package_plus_supplement;    // 591,73 € (pacchetto + supplementi base)
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[BTR DEBUG CHECKOUT] ============ VALORI PREVENTIVO ============');
            error_log('[BTR DEBUG CHECKOUT] Preventivo ID: ' . $preventivo_id);
            error_log('[BTR DEBUG CHECKOUT] Totale generale breakdown: ' . $totale_generale_breakdown);
            error_log('[BTR DEBUG CHECKOUT] Totale costi extra meta: ' . $totale_costi_extra_meta);
            error_log('[BTR DEBUG CHECKOUT] Totale sconti/riduzioni: ' . $totale_sconti_riduzioni);
            error_log('[BTR DEBUG CHECKOUT] Package price no extra: ' . $package_price_no_extra);
            error_log('[BTR DEBUG CHECKOUT] Extra night cost: ' . $extra_night_cost);
            error_log('[BTR DEBUG CHECKOUT] Totale camere (package + extra): ' . ($package_price_no_extra + $extra_night_cost));
            error_log('[BTR DEBUG CHECKOUT] Prezzo totale preventivo finale: ' . $prezzo_totale_preventivo);
            error_log('[BTR DEBUG CHECKOUT] Riepilogo dettagliato: ' . print_r($riepilogo_calcoli_dettagliato, true));
        }
    } else {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[BTR DEBUG] ❌ NON USANDO RIEPILOGO DETTAGLIATO - array non valido o totali mancanti');
            error_log('[BTR DEBUG] Is array: ' . (is_array($riepilogo_calcoli_dettagliato) ? 'SI' : 'NO'));
            error_log('[BTR DEBUG] Has totali: ' . (!empty($riepilogo_calcoli_dettagliato['totali']) ? 'SI' : 'NO'));
        }
    }
} else {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[BTR DEBUG] ❌ NON USANDO RIEPILOGO DETTAGLIATO - vuoto o non trovato');
    }
}

// --- FIX v1.0.157: Usa i dati salvati direttamente ---
// PRIORITÀ 1: Usa i meta _pricing_* salvati correttamente nel DB
$prezzo_totale_preventivo = floatval(get_post_meta($preventivo_id, '_pricing_totale_generale', true));
if (empty($prezzo_totale_preventivo)) {
    // Fallback: prova altre chiavi
    $prezzo_totale_preventivo = floatval(get_post_meta($preventivo_id, '_totals_grand_total', true));
    if (empty($prezzo_totale_preventivo)) {
        $prezzo_totale_preventivo = floatval(get_post_meta($preventivo_id, '_btr_grand_total', true));
        if (empty($prezzo_totale_preventivo)) {
            $prezzo_totale_preventivo = floatval(get_post_meta($preventivo_id, '_prezzo_totale', true));
        }
    }
}

// 🚨 CRITICAL FIX v1.0.219: Unified Calculator PRIMA delle assegnazioni
// Recupera altri totali dai meta salvati
$totale_camere_raw = get_post_meta($preventivo_id, '_pricing_totale_camere', true);
$totale_camere_saved = floatval($totale_camere_raw);
$totale_costi_extra_saved = floatval(get_post_meta($preventivo_id, '_pricing_totale_costi_extra', true));
$totale_assicurazioni_saved = floatval(get_post_meta($preventivo_id, '_pricing_totale_assicurazioni', true));

// 🚨 DEBUG: Valore esatto dal database
error_log('[BTR DEBUG] Preventivo #' . $preventivo_id . ' - _pricing_totale_camere RAW: ' . var_export($totale_camere_raw, true));
error_log('[BTR DEBUG] Preventivo #' . $preventivo_id . ' - totale_camere_saved: €' . number_format($totale_camere_saved, 2));

// 🚨 FIX v1.0.222: DISABILITATO Unified Calculator - usa SOLO valori DB
// Il Unified Calculator sta CORROMPENDO i valori corretti con zeri o calcoli errati
// TEMPORANEAMENTE DISABILITATO per usare i valori salvati nel DB che sono CORRETTI
if (false && class_exists('BTR_Unified_Calculator')) { // DISABILITATO CON false &&
    error_log('[BTR FORM ANAGRAFICI] ⚠️ Unified Calculator DISABILITATO in v1.0.222');
    
    // Carica dati minimi per calcolo
    $supplementi_extra = floatval(get_post_meta($preventivo_id, '_pricing_supplementi_extra', true));
    $totale_notti_extra = floatval(get_post_meta($preventivo_id, '_pricing_totale_notti_extra', true)) ?: $supplementi_extra;
    
    $unified_data = [
        'pricing_totale_camere' => $totale_camere_saved,
        'totale_notti_extra' => $totale_notti_extra,
        'totale_assicurazioni' => $totale_assicurazioni_saved,
        'totale_costi_extra' => $totale_costi_extra_saved,
        'preventivo_id' => $preventivo_id
    ];
    
    $unified_result = BTR_Unified_Calculator::calculate($unified_data);
    
    // SOVRASCRIVE immediatamente le variabili
    $totale_camere_saved = $unified_result['totale_camere'];
    $totale_costi_extra_saved = $unified_result['totale_costi_extra'];
    $totale_assicurazioni_saved = $unified_result['totale_assicurazioni'];
    
    error_log('[BTR FORM ANAGRAFICI] ✅ FIXED: totale_camere = €' . number_format($totale_camere_saved, 2));
}

// 🔧 UNIFIED CALCULATOR v1.0.218: Dati completi come payment-selection
if (class_exists('BTR_Unified_Calculator')) {
    // Carica supplementi extra e notti extra
    $supplementi_extra = floatval(get_post_meta($preventivo_id, '_pricing_supplementi_extra', true));
    $totale_notti_extra = floatval(get_post_meta($preventivo_id, '_pricing_totale_notti_extra', true)) ?: $supplementi_extra;
    
    // 🔧 TYPE-SAFE FIX v1.0.218: WordPress auto-deserializes meta arrays
    $riepilogo_json = get_post_meta($preventivo_id, '_btr_booking_data_json', true);
    $partecipanti_data = [];
    if (!empty($riepilogo_json)) {
        // Handle both array (WordPress deserialized) and string (JSON) formats
        if (is_array($riepilogo_json)) {
            // Data is already deserialized by WordPress
            $decoded = $riepilogo_json;
        } elseif (is_string($riepilogo_json)) {
            // Data is JSON string, decode it
            $decoded = json_decode($riepilogo_json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('[BTR ERROR] JSON decode error: ' . json_last_error_msg());
                $decoded = [];
            }
        } else {
            $decoded = [];
        }
        
        if (is_array($decoded) && isset($decoded['partecipanti'])) {
            $partecipanti_data = $decoded['partecipanti'];
        }
    }
    
    $unified_data = [
        'pricing_totale_camere' => $totale_camere_saved,
        'supplementi_extra' => $supplementi_extra,
        'totale_notti_extra' => $totale_notti_extra, // 🔧 Fix gap come in payment-selection
        'totale_costi_extra' => $totale_costi_extra_saved,
        'totale_assicurazioni' => $totale_assicurazioni_saved,
        'partecipanti' => $partecipanti_data, // Per calcolo assicurazioni accurate
        'preventivo_id' => $preventivo_id,
        'pricing_num_adults' => get_post_meta($preventivo_id, '_num_adults', true),
        'pricing_num_children' => get_post_meta($preventivo_id, '_num_children', true),
        'pricing_num_neonati' => get_post_meta($preventivo_id, '_num_neonati', true)
    ];
    
    // 🗺️ CACHE INVALIDATION: Clear cache come in payment-selection
    if (method_exists('BTR_Unified_Calculator', 'clear_cache')) {
        $calculator_instance = BTR_Unified_Calculator::get_instance();
        $calculator_instance->clear_cache();
    }
    
    $unified_result = BTR_Unified_Calculator::calculate($unified_data);
    
    // Confronta con i dati salvati
    $saved_total = $prezzo_totale_preventivo;
    $unified_total = $unified_result['totale_finale'];
    $discrepanza = abs($saved_total - $unified_total);
    
    if ($discrepanza > 0.01) {
        error_log('[BTR FORM ANAGRAFICI] 🚨 DISCREPANZA RILEVATA:');
        error_log('- Dati salvati: €' . number_format($saved_total, 2));
        error_log('- Unified Calculator: €' . number_format($unified_total, 2));
        error_log('- Discrepanza: €' . number_format($discrepanza, 2));
        
        // 🚨 CRITICAL FIX v1.0.221: NON sovrascrivere con valori 0 dal Unified Calculator
        if ($unified_result['totale_camere'] > 0) {
            $totale_camere_saved = $unified_result['totale_camere'];
            error_log('[BTR] ✅ Unified Calculator totale_camere: €' . number_format($totale_camere_saved, 2));
        } else {
            error_log('[BTR] ⚠️ Unified Calculator returned 0 for totale_camere, keeping DB value: €' . number_format($totale_camere_saved, 2));
        }
        
        // Solo aggiorna altri valori se non sono 0
        if ($unified_total > 0) {
            $prezzo_totale_preventivo = $unified_total;
        }
        if ($unified_result['totale_costi_extra'] !== 0) {
            $totale_costi_extra_saved = $unified_result['totale_costi_extra'];
        }
        if ($unified_result['totale_assicurazioni'] > 0) {
            $totale_assicurazioni_saved = $unified_result['totale_assicurazioni'];
        }
        $supplementi_extra = $unified_result['totale_supplementi'] ?? $supplementi_extra;
        $totale_notti_extra = $unified_result['totale_notti_extra'] ?? $totale_notti_extra;
        
        error_log('[BTR FORM ANAGRAFICI] ✅ Valori sincronizzati con Unified Calculator v1.0.218');
        error_log('[BTR FORM ANAGRAFICI] - Supplementi: €' . number_format($supplementi_extra, 2));
        error_log('[BTR FORM ANAGRAFICI] - Notti extra: €' . number_format($totale_notti_extra, 2));
    }
}

if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[BTR DEBUG] ✅ v1.0.217 - USANDO DATI SINCRONIZZATI:');
    error_log('[BTR DEBUG] Totale generale: ' . $prezzo_totale_preventivo);
    error_log('[BTR DEBUG] Totale camere: ' . $totale_camere_saved);
    error_log('[BTR DEBUG] Totale costi extra: ' . $totale_costi_extra_saved);
    error_log('[BTR DEBUG] Totale assicurazioni: ' . $totale_assicurazioni_saved);
}

$camere_acquistate = get_post_meta($preventivo_id, '_camere_selezionate', true);
if ( is_string( $camere_acquistate ) ) {
    $camere_acquistate = json_decode($camere_acquistate, true);
}

// Funzione per determinare il numero di persone in base al tipo di stanza
function determine_number_of_persons($tipo) {
    switch (strtolower($tipo)) {
        case 'singola':
            return 1;
        case 'doppia':
        case 'doppia/matrimoniale':
        case 'matrimoniale':
            return 2;
        case 'tripla':
            return 3;
        case 'quadrupla':
            return 4;
        case 'quintupla':
            return 5;
        case 'condivisa':
            return 1;
        default:
            return 1;
    }
}

if ( ! function_exists( 'btr_get_price_from_camera' ) ) {
    /**
     * Restituisce il prezzo unitario corretto (adulto o ridotto bambino)
     * per la camera passata.
     *
     * @param array  $camera       Dati camera (prezzo adulti e riduzioni).
     * @param string $child_fascia '' = adulto | 'f1' | 'f2'.
     *
     * @return float
     */
    function btr_get_price_from_camera( $camera, $child_fascia = '' ) {
        // Restituisce il prezzo corretto in base alla fascia (adulto, f1, f2).
        // Se la fascia è f1 o f2 prova ad usare i prezzi ridotti.
        // ‑ Per f2: se non esiste (o coincide con l'adulto) ripiega sul prezzo f1.
        // ‑ Se nessun prezzo ridotto è disponibile ritorna il prezzo adulto.
        $adult_price = floatval( $camera['prezzo_per_persona'] ?? 0 );

        if ( $child_fascia === 'f1' ) {
            $f1 = floatval( $camera['price_child_f1'] ?? 0 );
            if ( $f1 > 0 ) {
                return $f1;
            }
        }

        if ( $child_fascia === 'f2' ) {
            $f2 = floatval( $camera['price_child_f2'] ?? 0 );
            // Usa F2 solo se effettivamente scontato rispetto all'adulto
            if ( $f2 > 0 && $f2 < $adult_price ) {
                return $f2;
            }
            // Altrimenti prova il prezzo F1 (che spesso è quello ridotto vero)
            $f1 = floatval( $camera['price_child_f1'] ?? 0 );
            if ( $f1 > 0 ) {
                return $f1;
            }
        }

        // Default: prezzo adulto
        return $adult_price;
    }
}

if ( ! function_exists( 'btr_parse_event_date' ) ) {
    /**
     * Converte la data del pacchetto (es. "24 - 25 Gennaio 2026") in formato YYYY‑MM‑DD.
     *
     * @param string $raw_date Data originale del pacchetto.
     * @return string          Data normalizzata (YYYY‑MM‑DD) o stringa vuota se il parse fallisce.
     */
    function btr_parse_event_date( $raw_date ) {
        if ( empty( $raw_date ) ) {
            return '';
        }
        // Se è un range, prendi la parte prima del trattino.
        if ( strpos( $raw_date, '-' ) !== false ) {
            $raw_date = trim( explode( '-', $raw_date )[0] );
        }
        // Mesi italiani → inglesi (per strtotime)
        $mesi = [
            'Gennaio'   => 'January',
            'Febbraio'  => 'February',
            'Marzo'     => 'March',
            'Aprile'    => 'April',
            'Maggio'    => 'May',
            'Giugno'    => 'June',
            'Luglio'    => 'July',
            'Agosto'    => 'August',
            'Settembre' => 'September',
            'Ottobre'   => 'October',
            'Novembre'  => 'November',
            'Dicembre'  => 'December',
        ];
        $raw_date_eng = strtr( $raw_date, $mesi );
        $ts = strtotime( $raw_date_eng );
        if ( ! $ts ) {
            return '';
        }
        return date( 'Y-m-d', $ts );
    }
}

// ══════════════════════════════════════════════════════════════════════
// RIMOSSO CALCOLO DUPLICATO: Le linee seguenti sono state rimosse perché
// creavano discrepanze con il riepilogo preventivo. I totali corretti 
// sono già calcolati nelle linee 99-135 usando '_riepilogo_calcoli_dettagliato'
// ══════════════════════════════════════════════════════════════════════

// NOTA: Il sistema ora usa ESCLUSIVAMENTE i valori dal breakdown del preventivo
// per garantire coerenza con il riepilogo preventivo originale.
// I totali sono disponibili in:
// - $prezzo_totale_preventivo (totale finale corretto)
// - $package_price_no_extra (pacchetto base + supplementi)
// - $extra_night_cost (notti extra)
// - $totale_costi_extra_meta (costi extra/sconti)

// FIX v1.0.157: Carica anagrafici dai meta individuali salvati
$anagrafici = [];

// PRIORITÀ 1: Carica dai meta individuali _anagrafico_X_*
$anagrafici_count = intval(get_post_meta($preventivo_id, '_anagrafici_count', true));
if ($anagrafici_count > 0) {
    for ($i = 0; $i < $anagrafici_count; $i++) {
        $nome = get_post_meta($preventivo_id, "_anagrafico_{$i}_nome", true);
        if (!empty($nome)) {
            $anagrafico = [
                'nome' => $nome,
                'cognome' => get_post_meta($preventivo_id, "_anagrafico_{$i}_cognome", true),
                'email' => get_post_meta($preventivo_id, "_anagrafico_{$i}_email", true),
                'telefono' => get_post_meta($preventivo_id, "_anagrafico_{$i}_telefono", true),
                'costi_extra' => []
            ];
            
            // Carica costi extra per questa persona
            $extra_skipass = get_post_meta($preventivo_id, "_anagrafico_{$i}_extra_no_skipass_selected", true);
            if ($extra_skipass) {
                $anagrafico['costi_extra']['no-skipass'] = [
                    'selected' => '1',
                    'price' => get_post_meta($preventivo_id, "_anagrafico_{$i}_extra_no_skipass_price", true) ?: '-35'
                ];
            }
            
            $extra_culla = get_post_meta($preventivo_id, "_anagrafico_{$i}_extra_culla_per_neonati_selected", true);
            if ($extra_culla) {
                $anagrafico['costi_extra']['culla-per-neonati'] = [
                    'selected' => '1',
                    'price' => get_post_meta($preventivo_id, "_anagrafico_{$i}_extra_culla_per_neonati_price", true) ?: '15'
                ];
            }
            
            $anagrafici[] = $anagrafico;
        }
    }
}

// FALLBACK: Se non trovati dai meta individuali, prova dal JSON completo
if (empty($anagrafici)) {
    $anagrafici = btr_meta_array_chain($preventivo_id, ['_btr_anagrafici', '_anagrafici_preventivo']);
}

// Ultimo fallback: crea schede vuote
if (empty($anagrafici)) {
    $count = intval(btr_meta_chain($preventivo_id, ['_btr_totale_viaggiatori', '_btr_totale_persone'], 0));
    if ($count > 0) {
        $anagrafici = array_fill(0, $count, []);
    }
}

if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[BTR DEBUG] ✅ Anagrafici caricati: ' . count($anagrafici) . ' persone');
    foreach ($anagrafici as $idx => $persona) {
        error_log("[BTR DEBUG] Persona $idx: " . ($persona['nome'] ?? 'VUOTO') . ' ' . ($persona['cognome'] ?? ''));
    }
}

// Inizializza prezzo/notte extra per persona per evitare notice
$extra_night_pp = floatval(btr_meta_chain($preventivo_id, [
    '_pricing_notti_extra_prezzo_adulto',
    '_extra_nights_price_per_person',
    '_btr_notti_extra_prezzo_pp',
    '_extra_night_pp'
], 0));

// Calcola il totale delle assicurazioni
$totale_assicurazioni = 0;

// DEBUG: Verifica struttura dati anagrafici per assicurazioni
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[BTR DEBUG] ============ ANALISI ASSICURAZIONI ============');
    error_log('[BTR DEBUG] Preventivo ID: ' . $preventivo_id);
    
    if (!empty($anagrafici) && is_array($anagrafici)) {
        error_log('[BTR DEBUG] Anagrafici array contiene ' . count($anagrafici) . ' persone');
        
        foreach ($anagrafici as $idx => $persona) {
            error_log("[BTR DEBUG] --- Persona $idx ---");
            
            // Controlla tutte le possibili chiavi per assicurazioni
            if (isset($persona['assicurazioni'])) {
                error_log('[BTR DEBUG] Ha chiave "assicurazioni": ' . print_r($persona['assicurazioni'], true));
            }
            if (isset($persona['assicurazioni_dettagliate'])) {
                error_log('[BTR DEBUG] Ha chiave "assicurazioni_dettagliate": ' . print_r($persona['assicurazioni_dettagliate'], true));
            }
            if (isset($persona['assicurazione'])) {
                error_log('[BTR DEBUG] Ha chiave "assicurazione": ' . print_r($persona['assicurazione'], true));
            }
            
            // Mostra tutte le chiavi disponibili per questa persona
            error_log('[BTR DEBUG] Chiavi disponibili per persona ' . $idx . ': ' . implode(', ', array_keys($persona)));
        }
    } else {
        error_log('[BTR DEBUG] Anagrafici è vuoto o non è un array');
    }
    
    // Controlla anche i metadati del preventivo
    $ass_meta = get_post_meta($preventivo_id, '_assicurazioni_selezionate', true);
    if ($ass_meta) {
        error_log('[BTR DEBUG] Meta _assicurazioni_selezionate: ' . print_r($ass_meta, true));
    }
    
    $tot_ass_meta = get_post_meta($preventivo_id, '_totale_assicurazioni', true);
    if ($tot_ass_meta) {
        error_log('[BTR DEBUG] Meta _totale_assicurazioni: ' . $tot_ass_meta);
    }
}

if (!empty($anagrafici) && is_array($anagrafici)) {
    foreach ($anagrafici as $persona) {
        // Prima prova con assicurazioni_dettagliate
        if (!empty($persona['assicurazioni_dettagliate']) && is_array($persona['assicurazioni_dettagliate'])) {
            foreach ($persona['assicurazioni_dettagliate'] as $ass) {
                $importo = floatval($ass['importo'] ?? 0);
                $totale_assicurazioni += $importo;
            }
        }
        // Fallback: prova con altre possibili chiavi
        elseif (!empty($persona['assicurazioni']) && is_array($persona['assicurazioni'])) {
            foreach ($persona['assicurazioni'] as $ass) {
                if (is_array($ass)) {
                    $importo = floatval($ass['importo'] ?? 0);
                } else {
                    $importo = floatval($ass);
                }
                $totale_assicurazioni += $importo;
            }
        }
    }
}

// Se ancora zero, prova a recuperare dal meta del preventivo
if ($totale_assicurazioni == 0) {
    $totale_ass_saved = floatval(get_post_meta($preventivo_id, '_totale_assicurazioni', true));
    if ($totale_ass_saved > 0) {
        $totale_assicurazioni = $totale_ass_saved;
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[BTR DEBUG] Usato totale assicurazioni dal meta: ' . $totale_assicurazioni);
        }
    }
}

// Se ancora zero, prova dal riepilogo calcoli dettagliato
if ($totale_assicurazioni == 0 && !empty($riepilogo_calcoli_dettagliato)) {
    if (isset($riepilogo_calcoli_dettagliato['assicurazioni']) && is_array($riepilogo_calcoli_dettagliato['assicurazioni'])) {
        foreach ($riepilogo_calcoli_dettagliato['assicurazioni'] as $ass) {
            if (isset($ass['quantita']) && isset($ass['prezzo_unitario'])) {
                $totale_assicurazioni += floatval($ass['quantita']) * floatval($ass['prezzo_unitario']);
            }
        }
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[BTR DEBUG] Assicurazioni dal riepilogo calcoli: ' . $totale_assicurazioni);
        }
    }
    
    // Alternativa: controlla se esiste già un totale_assicurazioni nel riepilogo
    if ($totale_assicurazioni == 0 && isset($riepilogo_calcoli_dettagliato['totale_assicurazioni'])) {
        $totale_assicurazioni = floatval($riepilogo_calcoli_dettagliato['totale_assicurazioni']);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[BTR DEBUG] Totale assicurazioni dal riepilogo diretto: ' . $totale_assicurazioni);
        }
    }
}

// Se abbiamo già calcolato le assicurazioni nel blocco del riepilogo dettagliato (linee 139-165), usa quel valore
if ($totale_assicurazioni == 0 && isset($totale_assicurazioni_originali) && $totale_assicurazioni_originali > 0) {
    $totale_assicurazioni = $totale_assicurazioni_originali;
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('[BTR DEBUG] Usato totale assicurazioni originali calcolato: ' . $totale_assicurazioni);
    }
}

if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[BTR DEBUG] Totale assicurazioni finale: ' . $totale_assicurazioni);
}

// === LOGICA DINAMICA COSTI EXTRA ===
// Recupera il totale originale del preventivo e i costi extra inclusi
$totale_preventivo_originale = floatval(get_post_meta($preventivo_id, '_btr_grand_total', true));
if (empty($totale_preventivo_originale)) {
    $totale_preventivo_originale = floatval(get_post_meta($preventivo_id, '_prezzo_totale', true));
}

// Traccia quali costi extra erano originariamente selezionati nel preventivo
$costi_extra_originali_per_persona = [];
$totale_costi_extra_originali = 0;

// IMPORTANTE: Usa il totale dal meta piuttosto che ricalcolare dai dettagli
// perché i dettagli potrebbero non riflettere le selezioni effettive
$totale_costi_extra_meta_saved = floatval(get_post_meta($preventivo_id, '_totale_costi_extra', true));

if (!empty($anagrafici) && is_array($anagrafici)) {
    foreach ($anagrafici as $persona_idx => $persona) {
        $costi_extra_originali_per_persona[$persona_idx] = [];
        
        // MIGLIORAMENTO: Controlla TUTTI i costi extra nei dettagli, anche se non hanno checkbox
        // perché alcuni potrebbero essere stati salvati solo nei dettagli
        if (!empty($persona['costi_extra_dettagliate']) && is_array($persona['costi_extra_dettagliate'])) {
            foreach ($persona['costi_extra_dettagliate'] as $slug => $dettagli) {
                // Un costo extra era selezionato se:
                // 1. Ha una checkbox marcata OPPURE
                // 2. Ha un importo > 0 nei dettagli (era selezionato nel preventivo)
                $has_checkbox = !empty($persona['costi_extra'][$slug]);
                $has_positive_amount = floatval($dettagli['importo'] ?? 0) != 0;
                
                if ($has_checkbox || $has_positive_amount) {
                    $costi_extra_originali_per_persona[$persona_idx][$slug] = [
                        'descrizione' => $dettagli['descrizione'] ?? '',
                        'importo' => floatval($dettagli['importo'] ?? 0)
                    ];
                    $totale_costi_extra_originali += floatval($dettagli['importo'] ?? 0);
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("[BTR DEBUG] Costo extra trovato - Persona $persona_idx, Slug: $slug, Importo: " . $dettagli['importo']);
                    }
                }
            }
        }
    }
}

// Se il totale calcolato dai dettagli non corrisponde al meta, usa il meta
if (abs($totale_costi_extra_originali - $totale_costi_extra_meta_saved) > 0.01) {
    $totale_costi_extra_originali = $totale_costi_extra_meta_saved;
}

// Assicurati che la variabile sia sempre definita (anche se 0)
if (!isset($totale_costi_extra_originali)) {
    $totale_costi_extra_originali = 0;
}

// DEBUG: Log dei dati originali del preventivo
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[BTR DEBUG] ============ COSTI EXTRA ORIGINALI ============');
    error_log('[BTR DEBUG] Dati anagrafici preventivo: ' . print_r($anagrafici, true));
    error_log('[BTR DEBUG] Costi extra originali tracciati: ' . print_r($costi_extra_originali_per_persona, true));
    error_log('[BTR DEBUG] Totale costi extra originali: ' . $totale_costi_extra_originali);
    error_log('[BTR DEBUG] Meta _totale_costi_extra: ' . get_post_meta($preventivo_id, '_totale_costi_extra', true));
    error_log('[BTR DEBUG] Meta _anagrafici_preventivo: ' . print_r(get_post_meta($preventivo_id, '_anagrafici_preventivo', true), true));
}

// Calcola i costi extra
$totale_extra_persone = 0;
$totale_extra_durata = 0;
$extra_durata_unique = [];
$durata_giorni = (int) preg_replace('/[^0-9]/', '', $durata);

if (!empty($anagrafici) && is_array($anagrafici)) {
    foreach ($anagrafici as $pers) {
        if (empty($pers['costi_extra_dettagliate'])) {
            continue;
        }
        foreach ($pers['costi_extra_dettagliate'] as $ex_slug => $ex) {
            $imp = floatval($ex['importo'] ?? 0);
            $sc = floatval($ex['sconto'] ?? 0);
            $netto = $imp - ($imp * $sc / 100);

            if (!empty($ex['moltiplica_persone'])) {
                $totale_extra_persone += $netto;
            } elseif (!empty($ex['moltiplica_durata'])) {
                $extra_durata_unique[$ex_slug] = $netto;
            }
        }
    }
}

$totale_extra_durata = array_sum($extra_durata_unique) * max(1, $durata_giorni);
$totale_costi_extra = $totale_extra_persone + $totale_extra_durata;

// Calcola il totale finale
/*
if (isset($extra_night_total) && floatval($extra_night_total) > 0) {
    $extra_night_total = floatval($extra_night_total);
} elseif (isset($extra_night_pp) && floatval($extra_night_pp) > 0 && isset($extra_night_flag) && $extra_night_flag) {
    $extra_night_total = floatval($extra_night_pp) * intval($num_adults + $num_children);
} else {
    $extra_night_total = 0;
}

// IMPORTANTE: Usa il prezzo totale dal preventivo, non ricalcolare
$prezzo_totale = $prezzo_totale_preventivo;
*/



// Recupera il nome del pacchetto
$pacchetto_nome = $pacchetto_id ? get_the_title($pacchetto_id) : __('Non specificato', 'born-to-ride-booking');

// Calcola il numero totale di persone e lo sconto
// Usa il calcolo corretto dai dati del riepilogo calcoli dettagliato
$totale_persone = intval($num_adults) + intval($num_children); // Valore di fallback
if (!empty($riepilogo_calcoli_dettagliato) && is_array($riepilogo_calcoli_dettagliato)) {
    $partecipanti = $riepilogo_calcoli_dettagliato['partecipanti'] ?? [];
    $count_adults_total = 0;
    $count_children_total = 0;
    
    // Conta adulti
    if (isset($partecipanti['adulti']['quantita'])) {
        $count_adults_total = intval($partecipanti['adulti']['quantita']);
    }
    
    // Conta bambini dalle diverse fasce d'età
    foreach (['bambini_f1', 'bambini_f2', 'bambini_f3', 'bambini_f4'] as $fascia) {
        if (isset($partecipanti[$fascia]['quantita'])) {
            $count_children_total += intval($partecipanti[$fascia]['quantita']);
        }
    }
    
    $totale_persone = $count_adults_total + $count_children_total;
}

$riepilogo_camere = [];
foreach ($camere_selezionate as $camera) {
    $tipo = strtolower($camera['tipo'] ?? '');
    $quantita = intval($camera['quantita'] ?? 1);
    if (!empty($tipo)) {
        if (!isset($riepilogo_camere[$tipo])) {
            $riepilogo_camere[$tipo] = 0;
        }
        $riepilogo_camere[$tipo] += $quantita;
    }
}

$riepilogo_stringa = [];
foreach ($riepilogo_camere as $tipo => $quantita) {
    $riepilogo_stringa[] = $quantita . ' ' . $tipo . ($quantita > 1 ? 'e' : '');
}

$total_camere = array_sum($riepilogo_camere); // Somma tutte le quantità
$etichetta_tipologia = $total_camere === 1 ? 'camera' : 'camere';

// Recupera i dati anagrafici
$anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
if (is_string($anagrafici)) {
    $maybe_array = json_decode($anagrafici, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $anagrafici = $maybe_array;
    } else {
        $anagrafici = maybe_unserialize($anagrafici);
    }
}

// DEBUG IMMEDIATO: Verifica cosa contengono realmente i dati anagrafici
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('[BTR DEBUG] ============ DATI ANAGRAFICI RAW ============');
    error_log('[BTR DEBUG] Anagrafici raw dal meta: ' . print_r(get_post_meta($preventivo_id, '_anagrafici_preventivo', true), true));
    error_log('[BTR DEBUG] Anagrafici processati: ' . print_r($anagrafici, true));
    
    // Controlla se ci sono costi extra nei dati anagrafici
    if (!empty($anagrafici) && is_array($anagrafici)) {
        foreach ($anagrafici as $idx => $persona) {
            if (!empty($persona['costi_extra_dettagliate'])) {
                error_log("[BTR DEBUG] Persona $idx ha costi extra dettagliati: " . print_r($persona['costi_extra_dettagliate'], true));
            }
            if (!empty($persona['costi_extra'])) {
                error_log("[BTR DEBUG] Persona $idx ha costi extra checkbox: " . print_r($persona['costi_extra'], true));
            }
        }
    }
}

// === LOGICA NEONATI RIMOSSA ===
// I neonati sono ora trattati come partecipanti reali che occupano posti in camera
// Non vengono più creati "neonati fantasma"
// $get_post_meta = get_post_meta($preventivo_id); // Commentato - non usato


//printr(get_post_meta($preventivo_id));
?>

<!--
<div id="fws_67db4c14c470b" data-column-margin="default" data-midnight="dark"
     class="wpb_row vc_row-fluid vc_row top-level vc_row-o-equal-height vc_row-flex vc_row-o-content-middle" style="padding-top: 0px; padding-bottom: 4em; ">
    <div class="row-bg-wrap" data-bg-animation="none" data-bg-animation-delay="" data-bg-overlay="false">
        <div class="inner-wrap row-bg-layer">
            <div class="row-bg viewport-desktop" style=""></div>
        </div>
    </div>
    <div class="row_col_wrap_12 col span_12 dark left">
        <div class="vc_col-sm-4 wpb_column column_container vc_column_container col no-extra-padding inherit_tablet inherit_phone border_right_desktop_1px border_color_cccccc border_style_solid "
             data-padding-pos="all" data-has-bg-color="false" data-bg-color="" data-bg-opacity="1" data-animation="" data-delay="0">
            <div class="vc_column-inner">
                <div class="wpb_wrapper">
                    <h1 style="color: #0097c5;text-align: left; letter-spacing: 0;font-size: 50px;" class="vc_custom_heading vc_do_custom_heading"><?= esc_html
                        ($btr_destinazione ?? ''); ?></h1>
                    <h3 style="color: #000000;text-align: left" class="vc_custom_heading vc_do_custom_heading"><?= esc_html($localita_destinazione ?? ''); ?></h3>
                    <div class="wpb_text_column wpb_content_element ">
                        <div class="wpb_wrapper">
                            <p>
                                    <?php
                                    // Get the start date
                                    $start_date = '';
                                    if (!empty($data_pacchetto)) {
                                        $start_date = $data_pacchetto;
                                    } else {
                                        // Fallback: get the date from the package if available
                                        $package_date = get_post_meta($package_id, 'btr_data_inizio', true);
                                        if (!empty($package_date)) {
                                            $start_date = $package_date;
                                            // Also update the preventivo meta for future use
                                            update_post_meta($preventivo_id, '_data_pacchetto', $package_date);
                                        } else {
                                            // If no date is available, use current date as fallback
                                            $start_date = date_i18n(get_option('date_format'));
                                        }
                                    }

                                    // Calculate the end date based on the duration
                                    $durata_giorni = (int) preg_replace('/[^0-9]/', '', $durata);

                                    // If we have a valid start date and duration, calculate and display the date range
                                    if (!empty($start_date) && $durata_giorni > 0) {
                                        // Convert the start date to a DateTime object
                                        $start_date_obj = DateTime::createFromFormat(get_option('date_format'), $start_date);

                                        if ($start_date_obj) {
                                            // Calculate the end date by adding the duration (minus 1 day since the first day is already counted)
                                            $end_date_obj = clone $start_date_obj;
                                            $end_date_obj->modify('+' . ($durata_giorni - 1) . ' days');

                                            // Format the end date
                                            $end_date = $end_date_obj->format(get_option('date_format'));

                                            // Display the date range
                                            echo esc_html($start_date . ' - ' . $end_date);
                                        } else {
                                            // If we couldn't parse the start date, just show it
                                            echo esc_html($start_date);
                                        }
                                    } else {
                                        // If we don't have a valid duration, just show the start date
                                        echo esc_html($start_date);
                                    }
                                    ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="vc_col-sm-8 wpb_column column_container vc_column_container col padding-2-percent inherit_tablet inherit_phone " data-padding-pos="left"
             data-has-bg-color="false" data-bg-color="" data-bg-opacity="1" data-animation="" data-delay="0">
            <div class="vc_column-inner">
                <div class="wpb_wrapper">
                    <div class="iwithtext">
                        <div class="iwt-icon"><img decoding="async" src="https://borntoride.labuix.com/wp-content/uploads/2024/10/ICON-calendario-300x300.png" alt=""></div>
                        <div class="iwt-text"><strong>DURATA</strong>:
                            <?= esc_html($durata_label); ?></div>
                        <div class="clear"></div>
                    </div>
                    <div class="iwithtext">
                        <div class="iwt-icon"><img decoding="async" src="https://borntoride.labuix.com/wp-content/uploads/2024/10/ICON-prezzo-300x300.png" alt=""></div>
                        <div class="iwt-text"><strong>PREZZO TOTALE</strong>: <span class="total-top-price"><?php echo btr_format_price_i18n($prezzo_totale); ?></span></div>
                        <div class="clear"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
-->




<?php if ($remaining_time > 0): ?>
    <div id="btr-header-container" class="btr-header-container">

        <div class="btr-form-title-container">
            <h2 class="btr-form-title"><?php esc_html_e('Inserisci i dati anagrafici e assegna le camere', 'born-to-ride-booking'); ?></h2>
            <p class="btr-form-subtitle"><?php printf(__('Totale persone: %d', 'born-to-ride-booking'), $totale_persone); ?></p>

            <p class="form-subtitle mancanti-container">
                <span>
                    <?php esc_html_e('Persone con dati mancanti:', 'born-to-ride-booking'); ?>
                    <span class="mancanti">0</span>/<span class="totale-persone"><?php echo esc_html($totale_persone); ?></span>
                </span>
            </p>

        </div>

        <div id="btr-countdown" class="btr-countdown" data-remaining-time="<?php echo esc_attr($remaining_time); ?>">
            <div class="btr-countdown-title">
                <?php esc_html_e('Tempo rimanente per completare i dati:', 'born-to-ride-booking'); ?>
            </div>
            <div class="btr-countdown-timer">
                <div class="btr-countdown-box">
                    <span class="btr-time" id="btr-hours">--</span>
                    <span class="btr-label"><?php esc_html_e('Ore', 'born-to-ride-booking'); ?></span>
                </div>
                <div class="btr-countdown-divider">:</div>
                <div class="btr-countdown-box">
                    <span class="btr-time" id="btr-minutes">--</span>
                    <span class="btr-label"><?php esc_html_e('Min.', 'born-to-ride-booking'); ?></span>
                </div>
                <div class="btr-countdown-divider">:</div>
                <div class="btr-countdown-box">
                    <span class="btr-time" id="btr-seconds">--</span>
                    <span class="btr-label"><?php esc_html_e('Sec.', 'born-to-ride-booking'); ?></span>
                </div>
            </div>
        </div>


    </div>
<?php else: ?>

    <div class="wpb_wrapper ps-1">
        <h2 id="title-step" style="color: #0097c5;text-align: left; font-size: 30px; margin-bottom:0" class="vc_custom_heading vc_do_custom_heading"><?php esc_html_e('Concludi l\'ordine', 'born-to-ride-booking'); ?></h2>
        <p id="desc-step">
            <?php
            printf(
                _n(
                    'Inserisci tutti i dati per partecipare al camp!',
                    'Inserisci tutti i dati dei %d utenti per partecipare al camp!',
                    $totale_persone,
                    'born-to-ride-booking'
                ),
                $totale_persone
            );
            ?>
        </p>
    </div>

<?php endif; ?>




<?php if (empty($remaining_time)): ?>
<form class="btr-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="POST" onsubmit="return typeof validateAllData !== 'undefined' ? validateAllData() : false;">
<?php else: ?>
<form id="btr-anagrafici-form" class="btr-form">
<?php endif;

 printr(get_post_meta($preventivo_id)); // Debug rimosso per produzione
?>

    <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('btr_save_anagrafici'); ?>"><?php wp_nonce_field('btr_update_anagrafici_nonce', 'btr_update_anagrafici_nonce_field'); ?>

    <input type="hidden" name="action" value="btr_save_anagrafici">
    <input type="hidden" name="order_id" value="<?php echo esc_attr($order_id); ?>">
    <input type="hidden" name="preventivo_id" value="<?php echo esc_attr($preventivo_id); ?>">

    <style>
      .btr-accordion-header {display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:#f8f9fb;border:1px solid #e9ecef;border-radius:8px;cursor:pointer;}
      .btr-accordion-header:focus {outline:2px solid #2271b1; outline-offset:2px;}
      .btr-accordion-title {display:flex;align-items:center;gap:8px;margin-right: auto;}
      .btr-accordion-meta {display:flex;align-items:center;gap:8px;}
      .btr-accordion-arrow {transition:transform .2s ease;}
      .btr-accordion-header[aria-expanded="false"] .btr-accordion-arrow {transform:rotate(-90deg);}
      .person-title[aria-expanded="false"] .btr-accordion-arrow {transform:rotate(-90deg);}
      .btr-person-content {display:none;padding:14px;border-left:1px solid #e9ecef;border-right:1px solid #e9ecef;border-bottom:1px solid #e9ecef;border-radius:0 0 8px 8px;margin-bottom:12px;}
      .btr-person-content[hidden] {display:none !important;}
      .btr-badge-status {padding:2px 8px;border-radius:10px;font-size:12px;line-height:1.6;}
      .btr-badge-complete {background:#e7f7ed;color:#1a7f37;}
      .btr-badge-missing {background:#fff3cd;color:#856404;}
      /* Riepilogo card rimosso */
      /* Header accordion basato su h3 esistente */
      .person-title {display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:#f8f9fb;border:1px solid #e9ecef;border-radius:8px;cursor:pointer;margin-bottom:0;}
      .btr-person-card.collapsed .person-title {border-radius:8px;}
    </style>


    <!-- Riepilogo Pacchetto -->
    <div class="btr-card">
        <div class="btr-card-header">
            <h2>
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                <?php esc_html_e('Dettagli Pacchetto', 'born-to-ride-booking'); ?>

            </h2>
        </div>
        <div class="btr-card-body">
            <div class="btr-summary-box">
                <div class="btr-summary-title"><?php esc_html_e('Riepilogo', 'born-to-ride-booking'); ?></div>
                <div class="btr-summary-list">
                    <div class="btr-summary-item" style="justify-content: flex-start;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                        <span>
                            <?php 
                            // Calcola il numero corretto di partecipanti dal riepilogo calcoli dettagliato
                            $count_adults = intval($num_adults);
                            $count_children = 0;
                            $count_neonati = 0;
                            
                            // Usa i dati del riepilogo calcoli dettagliato se disponibili
                            if (!empty($riepilogo_calcoli_dettagliato) && is_array($riepilogo_calcoli_dettagliato)) {
                                $partecipanti = $riepilogo_calcoli_dettagliato['partecipanti'] ?? [];
                                
                                // Conta adulti
                                if (isset($partecipanti['adulti']['quantita'])) {
                                    $count_adults = intval($partecipanti['adulti']['quantita']);
                                }
                                
                                // Conta bambini dalle diverse fasce d'età
                                $count_children = 0;
                                foreach (['bambini_f1', 'bambini_f2', 'bambini_f3', 'bambini_f4'] as $fascia) {
                                    if (isset($partecipanti[$fascia]['quantita'])) {
                                        $count_children += intval($partecipanti[$fascia]['quantita']);
                                    }
                                }
                            } else {
                                // Fallback: usa i meta field (meno affidabile)
                                $count_children = intval($num_children);
                            }
                            
                            // Conta i neonati dai dati anagrafici o dai meta field
                            $count_neonati = intval(get_post_meta($preventivo_id, '_num_neonati', true));
                            if ($count_neonati === 0 && !empty($anagrafici) && is_array($anagrafici)) {
                                foreach ($anagrafici as $p) {
                                    $event_date = btr_parse_event_date( $data_pacchetto );
                                    if ( ! empty( $p['data_nascita'] ) && ! empty( $event_date ) ) {
                                        $dob = DateTime::createFromFormat( 'Y-m-d', $p['data_nascita'] );
                                        if ( ! $dob ) {
                                            $dob = DateTime::createFromFormat( 'd/m/Y', $p['data_nascita'] );
                                        }
                                        if ( $dob ) {
                                            $age = ( new DateTime( $event_date ) )->diff( $dob )->y;
                                            if ( $age < 2 ) {
                                                $count_neonati++;
                                            }
                                        }
                                    }
                                }
                            }
                            
                            $total_partecipanti = $count_adults + $count_children;
                            echo esc_html($total_partecipanti); 
                            esc_html_e(' partecipanti', 'born-to-ride-booking');
                            
                            if ($count_neonati > 0) {
                                echo ' + ' . esc_html($count_neonati) . ' ';
                                echo _n('neonato', 'neonati', $count_neonati, 'born-to-ride-booking');
                            }
                            ?>
                        </span>
                    </div>
                    <div class="btr-summary-item" style="justify-content: flex-start;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                        <span><?php echo esc_html(implode(' - ', $riepilogo_stringa)); ?></span>
                    </div>
                    <div class="btr-summary-item" style="justify-content: flex-start;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        <span><?php echo esc_html($durata_label); ?></span>
                    </div>
                </div>
            </div>

            <div class="btr-grid">
                <div class="btr-info-group">
                    <span class="btr-info-label"><?php esc_html_e('Pacchetto', 'born-to-ride-booking'); ?></span>
                    <div class="btr-info-value"><?php echo esc_html($nome_pacchetto); ?></div>
                </div>
                <div class="btr-info-group">
                    <span class="btr-info-label"><?php esc_html_e('Data', 'born-to-ride-booking'); ?></span>
                    <div class="btr-info-value">
                        <?php
                            echo esc_html(get_post_meta($preventivo_id, '_date_ranges', true));
                        ?>

                        <?php
                        // Display extra night date if available
                        if (!empty($extra_night_date)) {
                            esc_html_e( ' + notte extra', 'born-to-ride-booking' );
                            echo ' (';
                            if (is_array($extra_night_date)) {
                                // Format dates in Italian format (DD/MM/YYYY)
                                $formatted_dates = array_map(function($date) {
                                    $date_obj = DateTime::createFromFormat('Y-m-d', $date);
                                    return $date_obj ? $date_obj->format('d/m/Y') : $date;
                                }, $extra_night_date);
                                echo esc_html(implode(', ', $formatted_dates));
                            } else {
                                // Single date
                                $date_obj = DateTime::createFromFormat('Y-m-d', $extra_night_date);
                                echo esc_html($date_obj ? $date_obj->format('d/m/Y') : $extra_night_date);
                            }
                            echo ')';
                        }
                        ?>
                    </div>
                </div>
                <div class="btr-info-group">
                    <span class="btr-info-label"><?php esc_html_e('Durata', 'born-to-ride-booking'); ?></span>
                    <div class="btr-info-value"><?php echo esc_html($durata_label); ?></div>
                </div>
                <div class="btr-info-group">
                    <span class="btr-info-label"><?php esc_html_e('Partecipanti', 'born-to-ride-booking'); ?></span>
                    <div class="btr-info-value">
                        <?php
                        // Usa i valori corretti calcolati in precedenza
                        $display_adults = $count_adults_total ?? intval($num_adults);
                        $display_children = $count_children_total ?? intval($num_children);
                        $display_neonati = intval(get_post_meta($preventivo_id, '_num_neonati', true));
                        
                        echo esc_html($display_adults); ?> <?php esc_html_e('adulti', 'born-to-ride-booking'); ?>
                        <?php if ($display_children > 0): ?>
                            + <?php echo esc_html($display_children); ?> <?php esc_html_e('bambini', 'born-to-ride-booking'); ?>
                        <?php endif; ?>
                        <?php if ($display_neonati > 0): ?>
                            + <?php echo esc_html($display_neonati); ?> <?php echo _n('neonato', 'neonati', $display_neonati, 'born-to-ride-booking'); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>




    <?php ob_start(); ?>
    <div class="btr-room-buttons">
        <?php
        // Capacità di fallback per ogni tipologia
        $btr_default_capacita = [
            'Singola'             => 1,
            'Doppia'              => 2,
            'Doppia/Matrimoniale' => 2,
            'Tripla'              => 3,
            'Quadrupla'           => 4,
            'Quintupla'           => 5,
            'Condivisa'           => 6,
        ];
        // Prepara array lettere per etichette camere
        $lettere = range('A', 'Z');
        ?>
        <?php foreach ($camere_acquistate as $camera_id => $camera): ?>
            <?php for ($i = 1; $i <= $camera['quantita']; $i++): ?>
                <?php
                // Lettera sequenziale (A, B, C…) per ogni camera della stessa tipologia
                $lettera = chr(65 + $i - 1);
                $etichetta_camera = $camera['tipo'] . ' (' . $lettera . ')';
                // Determina la capacità effettiva: se non presente o non valida usa il default
                $capacita_camera = (isset($camera['capacita']) && intval($camera['capacita']) > 0)
                    ? $btr_default_capacita[$camera['tipo']]
                    : ($btr_default_capacita[$camera['tipo']] ?? 1);
                ?>
                <a type="button" class="btr-room-button"
                    data-room-id="<?php echo esc_attr($camera_id . '-' . $i); ?>"
                    data-room-type="<?php echo esc_attr($camera['tipo']); ?>"
                   data-capacita="<?php echo esc_attr($capacita_camera); ?>"
                   data-pp="<?php echo esc_attr($camera['prezzo_per_persona'] ?? 0); ?>"
                   data-supplemento="<?php echo esc_attr($camera['supplemento'] ?? 0); ?>">
                    <div class="btr-room-icon">
                        <?php if ($camera['tipo'] === 'Doppia'): ?>
                            <!-- Icona Doppia -->
                            <svg id="Raggruppa_35" data-name="Raggruppa 35" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="45.18" height="41.607" viewBox="0 0 45.18 41.607">
                              <defs>
                                <clipPath id="clip-path-doppia">
                                  <rect width="45.18" height="41.607" fill="#fff"/>
                                </clipPath>
                              </defs>
                              <g clip-path="url(#clip-path-doppia)">
                                <path d="M45.18,26.337V38.578A3.621,3.621,0,0,1,38.8,40.253a4.118,4.118,0,0,1-.747-1.63V36.905H7.239v1.717a4.116,4.116,0,0,1-.747,1.63A3.621,3.621,0,0,1,.108,38.578c.255-3.952-.341-8.34,0-12.242a10.632,10.632,0,0,1,2.727-5.859l0-14.886A6.279,6.279,0,0,1,8.336,0H36.689a6.266,6.266,0,0,1,5.589,5.593V20.3a10.6,10.6,0,0,1,2.9,6.035m-4.666-7.4V5.508c0-1.625-2.33-3.8-4-3.747L8.349,1.775C6.846,1.841,4.6,3.944,4.6,5.42V19.115a5.785,5.785,0,0,1,1.838-.891c.1-1.437-.192-3,.235-4.389a4.721,4.721,0,0,1,4.041-3.267c2.873-.2,5.987.151,8.885.014a4.99,4.99,0,0,1,3.015,1.833c.193.033.383-.395.516-.521a5.129,5.129,0,0,1,2.562-1.312c2.911.156,6.094-.241,8.973-.013A4.778,4.778,0,0,1,38.64,13.9c.4,1.363.107,2.917.212,4.324ZM21.764,17.53V14.844a3.094,3.094,0,0,0-2.284-2.471c-2.785.086-5.834-.249-8.591-.043a2.883,2.883,0,0,0-2.363,1.6,7.152,7.152,0,0,0-.318.915v2.862c.542-.052,1.177-.159,1.713-.18,3.911-.152,7.923.121,11.844,0m15.317.176V14.844c0-1.228-1.507-2.426-2.681-2.514-2.739-.2-5.753.142-8.52.027a3.165,3.165,0,0,0-2.356,2.4V17.53c3.947.124,8-.164,11.932,0,.508.021,1.111.13,1.625.18M43.419,29.86V26.469a9.429,9.429,0,0,0-1.161-3.33,8.17,8.17,0,0,0-6.89-3.852c-8.314-.438-17.007.339-25.36,0-3.671-.278-8.139,3.457-8.139,7.182V29.86h23.9a1.006,1.006,0,0,1,.906.858c.025.269-.281.9-.554.9H1.869v3.523h41.55V31.621H34.308a1.278,1.278,0,0,1-.5-.463.955.955,0,0,1,.769-1.3ZM5.478,36.905H1.869v1.453c0,.03.189.489.229.564a1.85,1.85,0,0,0,3.1.127,4.973,4.973,0,0,0,.278-.6Zm37.941,0H39.81v1.541a4.973,4.973,0,0,0,.278.6,1.85,1.85,0,0,0,3.1-.127c.039-.075.229-.534.229-.564Z" fill="#fff"/>
                                <path d="M250.993,255.053a.873.873,0,1,1-.873-.873.873.873,0,0,1,.873.873" transform="translate(-219.922 -224.274)" fill="#fff"/>
                              </g>
                            </svg>
                        <?php elseif ($camera['tipo'] === 'Singola'): ?>
                            <!-- Icona Singola -->
                            <svg id="Raggruppa_37" data-name="Raggruppa 37" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="52.956" height="29.804" viewBox="0 0 52.956 29.804">
                              <defs>
                                <clipPath id="clip-path-singola">
                                  <rect width="52.956" height="29.804" fill="#fff"/>
                                </clipPath>
                              </defs>
                              <g clip-path="url(#clip-path-singola)">
                                <path d="M0,1.971a2.522,2.522,0,0,1,4.576-.8,4.94,4.94,0,0,1,.388.851v7.9a5.818,5.818,0,0,1,1.109-.6,29.743,29.743,0,0,1,6.791-.217,4.039,4.039,0,0,1,3.375,2.671,4.763,4.763,0,0,1,2.632-1.037h26.9a4.468,4.468,0,0,1,2.219.83c-.512-3.43,3.78-4.685,4.966-1.342V29.224c-.206.248-.328.455-.679.51a22.307,22.307,0,0,1-3.412.011c-.3-.027-.874-.232-.874-.573V25.611H4.965v3.561c0,.34-.578.546-.874.573a22.307,22.307,0,0,1-3.412-.011c-.35-.055-.472-.261-.679-.51ZM1.655,28.088H3.31V1.654H1.655Zm49.647,0v-17.6c0-.72-1.655-.72-1.655,0v17.6ZM14.894,15.7V12.759a7.516,7.516,0,0,0-.307-.726,2.737,2.737,0,0,0-1.735-1.262,35.649,35.649,0,0,0-5.461-.029A2.559,2.559,0,0,0,5.312,11.97a6,6,0,0,0-.347.789V15.7Zm33.1,4.955V14.41A2.809,2.809,0,0,0,45.769,12.4l-26.794,0a2.742,2.742,0,0,0-2.426,2.017v6.245Zm-33.1-3.3H4.965v6.607H47.992V22.307H15.463a1.286,1.286,0,0,1-.569-.568Z" fill="#fff"/>
                              </g>
                            </svg>
                        <?php endif; ?>
                    </div>
                    <strong><?php echo esc_html($etichetta_camera); ?></strong>
                    <span><?php echo esc_html($capacita_camera); ?> posti disponibili</span>
                </a>
            <?php endfor; ?>
        <?php endforeach; ?>
    </div>
    <?php $btr_room_buttons_html = ob_get_clean(); ?>

    <div id="btr-assicurazioni-container">
        <?php
        // Guardia finale: se $anagrafici è ancora vuoto, tenta re-idratazione e crea placeholder
        if (empty($anagrafici) || !is_array($anagrafici)) {
            $anagrafici = btr_meta_array_chain($preventivo_id, ['_btr_anagrafici', '_anagrafici_preventivo']);
            if (empty($anagrafici)) {
                $raw_json = btr_meta_chain($preventivo_id, ['_btr_dati_completi_json'], '');
                if (!empty($raw_json)) {
                    $decoded = json_decode($raw_json, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        if (!empty($decoded['booking_data_json']['anagrafici'])) {
                            $anagrafici = $decoded['booking_data_json']['anagrafici'];
                        } elseif (!empty($decoded['anagrafici'])) {
                            $anagrafici = $decoded['anagrafici'];
                        }
                    }
                }
            }
            if (empty($anagrafici)) {
                $count_final = intval($totale_persone);
                if ($count_final <= 0) {
                    $count_final = intval(btr_meta_chain($preventivo_id, ['_anagrafici_count','_btr_totale_viaggiatori','_btr_totale_persone'], 0));
                }
                if ($count_final > 0) {
                    $anagrafici = array_fill(0, $count_final, []);
                }
            }
        }
        ?>
        <?php foreach ($anagrafici as $index => $persona): ?>
            <?php
            // Normalizza chiavi persona per compatibilità (mapping legacy → nuovi campi)
            if (!isset($persona['indirizzo_residenza']) && !empty($persona['indirizzo'])) {
                $persona['indirizzo_residenza'] = $persona['indirizzo'];
            }
            if (!isset($persona['citta_residenza']) && !empty($persona['citta'])) {
                $persona['citta_residenza'] = $persona['citta'];
            }
            if (!isset($persona['provincia_residenza']) && !empty($persona['provincia'])) {
                $persona['provincia_residenza'] = $persona['provincia'];
            }
            if (!isset($persona['cap_residenza']) && !empty($persona['cap'])) {
                $persona['cap_residenza'] = $persona['cap'];
            }

            // ──────────────────────────────────────────────────────────────
            // Prezzo base (adulto / ridotto bambino) per questa persona
            // ──────────────────────────────────────────────────────────────

            // 1. Determina la fascia bambino (f1 / f2) partendo dai dati disponibili
            $child_fascia = '';
            if ( ! empty( $persona['fascia'] ) ) {
                $child_fascia = strtolower( $persona['fascia'] );
            } else {
                // Calcola la fascia a partire dall'età alla data dell'evento
                $event_date = btr_parse_event_date( $data_pacchetto );
                if ( ! empty( $persona['data_nascita'] ) && ! empty( $event_date ) ) {
                    $dob = DateTime::createFromFormat( 'Y-m-d', $persona['data_nascita'] );
                    if ( ! $dob ) {
                        $dob = DateTime::createFromFormat( 'd/m/Y', $persona['data_nascita'] );
                    }
                    if ( $dob ) {
                        $age = ( new DateTime( $event_date ) )->diff( $dob )->y;
                        if ( $age < 2 ) {
                            $child_fascia = 'neonato'; // 0-2 anni = neonato
                        } else {
                            // Usa i range dinamici delle categorie bambini
                            if (class_exists('BTR_Dynamic_Child_Categories')) {
                                $child_categories_manager = new BTR_Dynamic_Child_Categories();
                                $child_categories = $child_categories_manager->get_categories(true);
                                
                                foreach ($child_categories as $category) {
                                    if ($age >= $category['age_min'] && $age <= $category['age_max']) {
                                        $child_fascia = $category['id'];
                                        break;
                                    }
                                }
                            } else {
                                // Fallback con valori di default allineati
                                if ( $age >= 3 && $age <= 8 ) {
                                    $child_fascia = 'f1';
                                } elseif ( $age >= 8 && $age <= 12 ) {
                                    $child_fascia = 'f2';
                                } elseif ( $age >= 12 && $age <= 14 ) {
                                    $child_fascia = 'f3';
                                } elseif ( $age >= 14 && $age <= 15 ) {
                                    $child_fascia = 'f4';
                                }
                            }
                        }
                    }
                }
                
                // Check se il partecipante è un neonato basandosi sul numero totale di neonati
                $num_neonati = intval(get_post_meta($preventivo_id, '_num_neonati', true));
                if ($num_neonati > 0 && $child_fascia === '') {
                    // Calcola se questo indice potrebbe essere un neonato
                    $total_partecipanti_paganti = intval($num_adults) + intval($num_children);
                    
                    // Se l'indice è oltre i partecipanti paganti, potrebbe essere un neonato
                    if ($index >= $total_partecipanti_paganti && $index < ($total_partecipanti_paganti + $num_neonati)) {
                        $child_fascia = 'neonato';
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log("[BTR DEBUG] Partecipante $index identificato come neonato dal conteggio");
                        }
                    }
                }
                
                // Fallback: se l'indice supera il numero di adulti, assegna fascia in base all'ordine
                if ( $child_fascia === '' && $index >= intval( $num_adults ) ) {
                    // Calcola l'indice relativo del bambino (0-based)
                    $child_index = $index - intval( $num_adults );
                    
                    // Assegna fasce diverse in base all'ordine dei bambini
                    // Questo è un fallback quando non abbiamo date di nascita
                    $fasce_disponibili = ['f1', 'f2', 'f3', 'f4'];
                    $child_fascia = $fasce_disponibili[$child_index % count($fasce_disponibili)];
                }
            }

            // ------------------------------------------------------------------
            // Recupera prezzo e supplemento dalla TIPOLOGIA di camera associata
            // Ogni tipologia ha:
            //   • prezzo adulto
            //   • price_child_f1
            //   • price_child_f2
            //   • supplemento (applicato per persona)
            // ------------------------------------------------------------------
            $prezzo_pp      = 0;
            $supplemento_pp = 0;

            if ( ! empty( $persona['camera_tipo'] ) && ! empty( $camere_selezionate ) ) {
                foreach ( $camere_selezionate as $cam_sel ) {
                    if ( strcasecmp( $cam_sel['tipo'], $persona['camera_tipo'] ) === 0 ) {
                        $prezzo_pp      = btr_get_price_from_camera( $cam_sel, $child_fascia );
                        $supplemento_pp = floatval( $cam_sel['supplemento'] ?? 0 );
                        break;
                    }
                }
            }

            // Fallback: se la tipologia non viene trovata usa la prima camera disponibile
            if ( $prezzo_pp <= 0 && ! empty( $camere_selezionate ) ) {
                $prezzo_pp      = btr_get_price_from_camera( $camere_selezionate[0], $child_fascia );
                $supplemento_pp = floatval( $camere_selezionate[0]['supplemento'] ?? 0 );
            }

            // Salva il supplemento in $persona per compatibilità con il resto del codice
            $persona['supplemento'] = $supplemento_pp;

            $base_price_static = $prezzo_pp
                + $supplemento_pp
                + ( ( intval( $extra_night_flag ) === 1 ) ? $extra_night_pp : 0 );
            ?>
            <div class="btr-person-card"
                 data-person-index="<?php echo $index; ?>"
                 data-base-price="<?php echo esc_attr($base_price_static); ?>">

                <h3 class="person-title" role="button" tabindex="0" aria-expanded="false">
                    <span class="icona-partecipante">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5.52 19c.64-2.2 1.84-3 3.22-3h6.52c1.38 0 2.58.8 3.22 3"/><circle cx="12" cy="10" r="3"/><circle cx="12" cy="12" r="10"/></svg>
                    </span>
                    <?php
                    $ordinali = [
                        'Primo', 'Secondo', 'Terzo', 'Quarto', 'Quinto', 'Sesto', 'Settimo', 'Ottavo', 'Nono', 'Decimo',
                        'Undicesimo', 'Dodicesimo', 'Tredicesimo', 'Quattordicesimo', 'Quindicesimo',
                        'Sedicesimo', 'Diciassettesimo', 'Diciottesimo', 'Diciannovesimo', 'Ventesimo',
                        'Ventunesimo', 'Ventiduesimo', 'Ventitreesimo', 'Ventiquattresimo', 'Venticinquesimo',
                        'Ventiseiesimo', 'Ventisettesimo', 'Ventottesimo', 'Ventinovesimo', 'Trentesimo'
                    ];
                    $posizione = $ordinali[$index] ?? sprintf(__('Partecipante %d', 'born-to-ride-booking'), $index + 1);
                    // ▸ Label "(Adulto)" vs "(Bambino X-Y anni)" vs "(Neonato 0-2 anni)" accanto al titolo
                    $is_child   = ( $child_fascia !== '' && $child_fascia !== 'neonato' ) || ( $index >= intval( $num_adults ) );
                    $is_infant  = ( $child_fascia === 'neonato' );
                    
                    // Recupera etichette base dalle opzioni
                    $adult_label = get_option('btr_label_adult_singular', 'Adulto');
                    $child_label = get_option('btr_label_child_singular', 'Bambino');
                    $infant_label = get_option('btr_label_infant_singular', 'Neonato');
                    
                    if ( $is_infant ) {
                        // Neonati: mostra solo "Neonato 0-2 anni"
                        $label_tipo = '(' . $infant_label . ' 0-2 anni)';
                    } elseif ( $is_child ) {
                        // Bambini: recupera l'etichetta specifica della fascia
                        $child_labels = [];

                        // Recupera etichette centralizzate (preferibile)
                        if (class_exists('BTR_Preventivi')) {
                            $child_labels = BTR_Preventivi::btr_get_child_age_labels(!empty($package_id) ? $package_id : $preventivo_id);
                        }
                        
                        // Se non ci sono etichette nel pacchetto, usa il sistema dinamico
                        if (empty($child_labels) && class_exists('BTR_Dynamic_Child_Categories')) {
                            $child_categories_manager = new BTR_Dynamic_Child_Categories();
                            $child_categories = $child_categories_manager->get_categories(true);
                            foreach ($child_categories as $category) {
                                $child_labels[$category['id']] = $category['label'];
                            }
                        }
                        
                        // Determina l'etichetta della fascia specifica
                        if ($child_fascia && isset($child_labels[$child_fascia])) {
                            // Usa direttamente l'etichetta della fascia (es. "3-8 anni")
                            $fascia_label = $child_labels[$child_fascia];
                            
                            // Se l'etichetta della fascia già contiene "Bambini", usala così com'è
                            if (stripos($fascia_label, $child_label) !== false || stripos($fascia_label, 'bambini') !== false) {
                                $label_tipo = '(' . $fascia_label . ')';
                            } else {
                                // Altrimenti prependi "Bambino"
                                $label_tipo = '(' . $child_label . ' ' . $fascia_label . ')';
                            }
                        } else {
                            // Fallback generico se non si trova la fascia
                            $label_tipo = '(' . $child_label . ')';
                        }
                    } else {
                        // Adulti
                        $label_tipo = '(' . $adult_label . ')';
                    }
                    $label_partecipante = get_option('btr_label_participant', 'partecipante');
                    echo '<span class="btr-accordion-title"><strong>' . esc_html( $posizione ) . '</strong> ' . esc_html( $label_partecipante ) . ' ' . esc_html( $label_tipo ) . '</span>';
                    ?>
                    <span class="btr-accordion-meta">
                        <span class="btr-badge-status">···</span>
                        <svg class="btr-accordion-arrow" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                    </span>
                </h3>


                <div class="btr-person-content">
                <div class="btr-grid">
                    <div class="btr-field-group w-50">
                        <label for="btr_nome_<?php echo $index; ?>">
                            <?php esc_html_e('Nome', 'born-to-ride-booking'); ?>
                        </label>
                        <input type="text" 
                               id="btr_nome_<?php echo $index; ?>" 
                               name="anagrafici[<?php echo $index; ?>][nome]" 
                               value="<?php echo esc_attr($persona['nome'] ?? ''); ?>" >
                    </div>
                    <div class="btr-field-group w-50">
                        <label for="btr_cognome_<?php echo $index; ?>">
                            <?php esc_html_e('Cognome', 'born-to-ride-booking'); ?>
                        </label>
                        <input type="text" 
                               id="btr_cognome_<?php echo $index; ?>" 
                               name="anagrafici[<?php echo $index; ?>][cognome]" 
                               value="<?php echo esc_attr($persona['cognome'] ?? ''); ?>" >
                    </div>
                    
                    <!-- Campo hidden per salvare la fascia -->
                    <input type="hidden" name="anagrafici[<?php echo $index; ?>][fascia]" value="<?php echo esc_attr($child_fascia); ?>">
                    
                    <!-- Campo hidden per salvare il tipo_persona -->
                    <?php 
                    $tipo_persona = '';
                    if ($is_infant) {
                        $tipo_persona = 'neonato';
                    } elseif ($is_child) {
                        $tipo_persona = 'bambino';
                    } else {
                        $tipo_persona = 'adulto';
                    }
                    ?>
                    <input type="hidden" name="anagrafici[<?php echo $index; ?>][tipo_persona]" value="<?php echo esc_attr($tipo_persona); ?>">

                    <?php if ($child_fascia !== 'neonato'): ?>
                        <?php if(!$is_child): ?>
                    <div class="btr-field-group w-60">
                        <label for="btr_email_<?php echo $index; ?>"><?php esc_html_e('Email personale', 'born-to-ride-booking'); ?></label>
                        <input type="email" id="btr_email_<?php echo $index; ?>" name="anagrafici[<?php echo $index; ?>][email]" value="<?php echo esc_attr($persona['email'] ?? ''); ?>" >
                    </div>
                    <div class="btr-field-group w-40">
                        <label for="btr_telefono_<?php echo $index; ?>"><?php esc_html_e('Telefono', 'born-to-ride-booking'); ?></label>
                        <input type="tel" id="btr_telefono_<?php echo $index; ?>" name="anagrafici[<?php echo $index; ?>][telefono]" value="<?php echo esc_attr($persona['telefono'] ?? ''); ?>" >
                    </div>
                    <?php endif; ?>
                    <div class="btr-field-group w-30">
                        <label for="btr_data_nascita_<?php echo $index; ?>"><?php esc_html_e('Data di nascita', 'born-to-ride-booking'); ?></label>
                        <input type="date" id="btr_data_nascita_<?php echo $index; ?>" name="anagrafici[<?php echo $index; ?>][data_nascita]" value="<?php echo esc_attr($persona['data_nascita'] ?? ''); ?>" required>
                    </div>

                    <div class="btr-field-group w-30">
                        <label for="btr_citta_nascita_<?php echo $index; ?>"><?php esc_html_e('Città di Nascita', 'born-to-ride-booking'); ?></label>
                        <input type="text" id="btr_citta_nascita_<?php echo $index; ?>" name="anagrafici[<?php echo $index; ?>][citta_nascita]" value="<?php echo esc_attr($persona['citta_nascita'] ?? ''); ?>" >
                    </div>
                    <div class="btr-field-group w-40 codice-fiscale-field <?php echo ($index === 0 || !empty($persona['assicurazioni'])) ? '' : 'hidden-field'; ?>">
                        <label for="btr_codice_fiscale_<?php echo $index; ?>"><?php esc_html_e('Codice Fiscale', 'born-to-ride-booking'); ?></label>
                        <input type="text" id="btr_codice_fiscale_<?php echo $index; ?>" name="anagrafici[<?php echo $index; ?>][codice_fiscale]" value="<?php echo esc_attr($persona['codice_fiscale'] ?? ''); ?>" >
                    </div>
                    <div class="btr-field-group w-30 address-field <?php echo ($index === 0 || !empty($persona['assicurazioni'])) ? '' : 'hidden-field'; ?>" data-person-index="<?php echo $index; ?>">
                        <label for="btr_indirizzo_residenza_<?php echo $index; ?>"><?php esc_html_e('Indirizzo di residenza', 'born-to-ride-booking'); ?></label>
                        <input type="text" id="btr_indirizzo_residenza_<?php echo $index; ?>" name="anagrafici[<?php echo $index; ?>][indirizzo_residenza]" value="<?php echo esc_attr($persona['indirizzo_residenza'] ?? ''); ?>" >
                    </div>
                    <div class="btr-field-group w-15 address-field <?php echo ($index === 0 || !empty($persona['assicurazioni'])) ? '' : 'hidden-field'; ?>" data-person-index="<?php echo $index; ?>">
                        <label for="btr_numero_civico_<?php echo $index; ?>"><?php esc_html_e('Numero civico', 'born-to-ride-booking'); ?></label>
                        <input type="text" id="btr_numero_civico_<?php echo $index; ?>" name="anagrafici[<?php echo $index; ?>][numero_civico]" value="<?php echo esc_attr($persona['numero_civico'] ?? ''); ?>" >
                    </div>
                    <div class="btr-field-group w-15">
                        <label for="btr_citta_residenza_<?php echo $index; ?>"><?php esc_html_e('Città di Residenza', 'born-to-ride-booking'); ?></label>
                        <input type="text" id="btr_citta_residenza_<?php echo $index; ?>" name="anagrafici[<?php echo $index; ?>][citta_residenza]" value="<?php echo esc_attr($persona['citta_residenza'] ?? ''); ?>" >
                    </div>
                    <div class="btr-field-group w-10 address-field <?php echo ($index === 0 || !empty($persona['assicurazioni'])) ? '' : 'hidden-field'; ?>" data-person-index="<?php echo $index; ?>">
                        <label for="btr_cap_<?php echo $index; ?>"><?php esc_html_e('CAP', 'born-to-ride-booking'); ?></label>
                        <input type="text" id="btr_cap_<?php echo $index; ?>" name="anagrafici[<?php echo $index; ?>][cap_residenza]" value="<?php echo esc_attr($persona['cap_residenza'] ?? ''); ?>" >
                    </div>
                    <div class="btr-field-group w-20">
                        <label for="btr_provincia_residenza_<?php echo $index; ?>"><?php esc_html_e('Provincia di residenza', 'born-to-ride-booking'); ?></label>
                        <div class="btr-custom-select">
                            <input type="text" id="btr_provincia_residenza_<?php echo $index; ?>" name="anagrafici[<?php echo $index; ?>][provincia_residenza]" placeholder="Seleziona provincia..." autocomplete="off" readonly class="btr-province-display provincia-residenza-field" data-index="<?php echo $index; ?>" value="<?php echo esc_attr($persona['provincia_residenza'] ?? ''); ?>">
                            <div class="btr-select-dropdown" style="display: none">
                              <div class="btr-select-header">
                                <input type="text" class="btr-province-search" placeholder="Cerca provincia...">
                                <svg class="btr-search-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                              </div>
                              <div class="btr-select-options-scroll">
                                <div class="btr-select-option" data-value="ESTERO">ESTERO</div>
                                <div class="btr-select-option" data-value="AG">Agrigento</div>
                                <div class="btr-select-option" data-value="AL">Alessandria</div>
                                <div class="btr-select-option" data-value="AN">Ancona</div>
                                <div class="btr-select-option" data-value="AO">Aosta</div>
                                <div class="btr-select-option" data-value="AR">Arezzo</div>
                                <div class="btr-select-option" data-value="AP">Ascoli Piceno</div>
                                <div class="btr-select-option" data-value="AT">Asti</div>
                                <div class="btr-select-option" data-value="AV">Avellino</div>
                                <div class="btr-select-option" data-value="BA">Bari</div>
                                <div class="btr-select-option" data-value="BT">Barletta-Andria-Trani</div>
                                <div class="btr-select-option" data-value="BL">Belluno</div>
                                <div class="btr-select-option" data-value="BN">Benevento</div>
                                <div class="btr-select-option" data-value="BG">Bergamo</div>
                                <div class="btr-select-option" data-value="BI">Biella</div>
                                <div class="btr-select-option" data-value="BO">Bologna</div>
                                <div class="btr-select-option" data-value="BZ">Bolzano</div>
                                <div class="btr-select-option" data-value="BS">Brescia</div>
                                <div class="btr-select-option" data-value="BR">Brindisi</div>
                                <div class="btr-select-option" data-value="CA">Cagliari</div>
                                <div class="btr-select-option" data-value="CL">Caltanissetta</div>
                                <div class="btr-select-option" data-value="CB">Campobasso</div>
                                <div class="btr-select-option" data-value="CE">Caserta</div>
                                <div class="btr-select-option" data-value="CT">Catania</div>
                                <div class="btr-select-option" data-value="CZ">Catanzaro</div>
                                <div class="btr-select-option" data-value="CH">Chieti</div>
                                <div class="btr-select-option" data-value="CO">Como</div>
                                <div class="btr-select-option" data-value="CS">Cosenza</div>
                                <div class="btr-select-option" data-value="CR">Cremona</div>
                                <div class="btr-select-option" data-value="KR">Crotone</div>
                                <div class="btr-select-option" data-value="CN">Cuneo</div>
                                <div class="btr-select-option" data-value="EN">Enna</div>
                                <div class="btr-select-option" data-value="FM">Fermo</div>
                                <div class="btr-select-option" data-value="FE">Ferrara</div>
                                <div class="btr-select-option" data-value="FI">Firenze</div>
                                <div class="btr-select-option" data-value="FG">Foggia</div>
                                <div class="btr-select-option" data-value="FC">Forlì-Cesena</div>
                                <div class="btr-select-option" data-value="FR">Frosinone</div>
                                <div class="btr-select-option" data-value="GE">Genova</div>
                                <div class="btr-select-option" data-value="GO">Gorizia</div>
                                <div class="btr-select-option" data-value="GR">Grosseto</div>
                                <div class="btr-select-option" data-value="IM">Imperia</div>
                                <div class="btr-select-option" data-value="IS">Isernia</div>
                                <div class="btr-select-option" data-value="AQ">L'Aquila</div>
                                <div class="btr-select-option" data-value="SP">La Spezia</div>
                                <div class="btr-select-option" data-value="LT">Latina</div>
                                <div class="btr-select-option" data-value="LE">Lecce</div>
                                <div class="btr-select-option" data-value="LC">Lecco</div>
                                <div class="btr-select-option" data-value="LI">Livorno</div>
                                <div class="btr-select-option" data-value="LO">Lodi</div>
                                <div class="btr-select-option" data-value="LU">Lucca</div>
                                <div class="btr-select-option" data-value="MC">Macerata</div>
                                <div class="btr-select-option" data-value="MN">Mantova</div>
                                <div class="btr-select-option" data-value="MS">Massa-Carrara</div>
                                <div class="btr-select-option" data-value="MT">Matera</div>
                                <div class="btr-select-option" data-value="ME">Messina</div>
                                <div class="btr-select-option" data-value="MI">Milano</div>
                                <div class="btr-select-option" data-value="MO">Modena</div>
                                <div class="btr-select-option" data-value="MB">Monza e della Brianza</div>
                                <div class="btr-select-option" data-value="NA">Napoli</div>
                                <div class="btr-select-option" data-value="NO">Novara</div>
                                <div class="btr-select-option" data-value="NU">Nuoro</div>
                                <div class="btr-select-option" data-value="OR">Oristano</div>
                                <div class="btr-select-option" data-value="PD">Padova</div>
                                <div class="btr-select-option" data-value="PA">Palermo</div>
                                <div class="btr-select-option" data-value="PR">Parma</div>
                                <div class="btr-select-option" data-value="PV">Pavia</div>
                                <div class="btr-select-option" data-value="PG">Perugia</div>
                                <div class="btr-select-option" data-value="PU">Pesaro e Urbino</div>
                                <div class="btr-select-option" data-value="PE">Pescara</div>
                                <div class="btr-select-option" data-value="PC">Piacenza</div>
                                <div class="btr-select-option" data-value="PI">Pisa</div>
                                <div class="btr-select-option" data-value="PT">Pistoia</div>
                                <div class="btr-select-option" data-value="PN">Pordenone</div>
                                <div class="btr-select-option" data-value="PZ">Potenza</div>
                                <div class="btr-select-option" data-value="PO">Prato</div>
                                <div class="btr-select-option" data-value="RG">Ragusa</div>
                                <div class="btr-select-option" data-value="RA">Ravenna</div>
                                <div class="btr-select-option" data-value="RC">Reggio Calabria</div>
                                <div class="btr-select-option" data-value="RE">Reggio Emilia</div>
                                <div class="btr-select-option" data-value="RI">Rieti</div>
                                <div class="btr-select-option" data-value="RN">Rimini</div>
                                <div class="btr-select-option" data-value="RM">Roma</div>
                                <div class="btr-select-option" data-value="RO">Rovigo</div>
                                <div class="btr-select-option" data-value="SA">Salerno</div>
                                <div class="btr-select-option" data-value="SS">Sassari</div>
                                <div class="btr-select-option" data-value="SV">Savona</div>
                                <div class="btr-select-option" data-value="SI">Siena</div>
                                <div class="btr-select-option" data-value="SR">Siracusa</div>
                                <div class="btr-select-option" data-value="SO">Sondrio</div>
                                <div class="btr-select-option" data-value="TA">Taranto</div>
                                <div class="btr-select-option" data-value="TE">Teramo</div>
                                <div class="btr-select-option" data-value="TR">Terni</div>
                                <div class="btr-select-option" data-value="TO">Torino</div>
                                <div class="btr-select-option" data-value="TP">Trapani</div>
                                <div class="btr-select-option" data-value="TN">Trento</div>
                                <div class="btr-select-option" data-value="TV">Treviso</div>
                                <div class="btr-select-option" data-value="TS">Trieste</div>
                                <div class="btr-select-option" data-value="UD">Udine</div>
                                <div class="btr-select-option" data-value="VA">Varese</div>
                                <div class="btr-select-option" data-value="VE">Venezia</div>
                                <div class="btr-select-option" data-value="VB">Verbano-Cusio-Ossola</div>
                                <div class="btr-select-option" data-value="VC">Vercelli</div>
                                <div class="btr-select-option" data-value="VR">Verona</div>
                                <div class="btr-select-option" data-value="VV">Vibo Valentia</div>
                                <div class="btr-select-option" data-value="VI">Vicenza</div>
                                <div class="btr-select-option" data-value="VT">Viterbo</div>
                              </div>
                            </div>
                        </div>
                    </div>
                    <div class="btr-field-group nazione-residenza-field" id="nazione_residenza_container_<?php echo $index; ?>" style="display: <?php echo (strtoupper($persona['provincia_residenza'] ?? '') === 'ESTERO') ? 'block' : 'none'; ?>;">
                        <label for="btr_nazione_residenza_<?php echo $index; ?>"><?php esc_html_e('Indicare la nazione di residenza', 'born-to-ride-booking'); ?></label>
                        <input type="text" id="btr_nazione_residenza_<?php echo $index; ?>" name="anagrafici[<?php echo $index; ?>][nazione_residenza]" value="<?php echo esc_attr($persona['nazione_residenza'] ?? ''); ?>" >
                    </div>
               
                <?php endif; ?>
                </div>
                <?php if ($child_fascia === 'neonato'): ?>
                    <div class="btr-infant-indicator">
                        <strong>👶 Neonato (0-2 anni)</strong><br>
                        <small>Questo partecipante è un neonato. Occupa un posto in camera ma non ha costi base. Un adulto può richiedere la "Culla per Neonati" come costo extra.</small>
                    </div>
                <?php endif; ?>

                <div class="btr-field-group asign-camera">
                    <h4><?php esc_html_e('A quale camera lo vuoi associare?', 'born-to-ride-booking'); ?></h4>
                    <?php echo $btr_room_buttons_html; ?>
                    <!-- Campo nascosto per salvare l'ID della camera -->
                    <input type="hidden" name="anagrafici[<?php echo $index; ?>][camera]" id="btr_camera_<?php echo $index; ?>" value="<?php echo esc_attr($persona['camera'] ?? ''); ?>">
                    <!-- Campo nascosto per salvare la tipologia della camera -->
                    <input type="hidden" name="anagrafici[<?php echo $index; ?>][camera_tipo]" id="btr_camera_tipo_<?php echo $index; ?>" value="<?php echo esc_attr($persona['camera_tipo'] ?? ''); ?>">
                    
                    <!-- Selezione tipo letto per camera doppia -->
                    <div class="btr-bed-type-selector" id="btr_bed_type_container_<?php echo $index; ?>" style="display: none; margin-top: 20px;">
                        <h4><?php esc_html_e('Che tipo di letto preferisci?', 'born-to-ride-booking'); ?></h4>
                        <div class="btr-bed-type-buttons">
                            <button type="button" class="btr-bed-type-button" data-bed-type="letti_singoli" data-index="<?php echo $index; ?>">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="8" width="7" height="10" rx="1"/>
                                    <rect x="14" y="8" width="7" height="10" rx="1"/>
                                    <path d="M7 8V6a1 1 0 011-1h0a1 1 0 011 1v2M18 8V6a1 1 0 011-1h0a1 1 0 011 1v2"/>
                                </svg>
                                <strong><?php esc_html_e('Letti Singoli', 'born-to-ride-booking'); ?></strong>
                            </button>
                            <button type="button" class="btr-bed-type-button" data-bed-type="matrimoniale" data-index="<?php echo $index; ?>">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="8" width="18" height="10" rx="1"/>
                                    <path d="M12 8V6a1 1 0 011-1h0a1 1 0 011 1v2"/>
                                </svg>
                                <strong><?php esc_html_e('Letto Matrimoniale', 'born-to-ride-booking'); ?></strong>
                            </button>
                        </div>
                        <!-- Campo nascosto per salvare il tipo di letto -->
                        <input type="hidden" name="anagrafici[<?php echo $index; ?>][tipo_letto]" id="btr_tipo_letto_<?php echo $index; ?>" value="<?php echo esc_attr($persona['tipo_letto'] ?? ''); ?>">
                    </div>
                </div>


                <fieldset class="btr-assicurazioni">
                    <?php
                    $btr_assicurazione_importi = get_post_meta($package_id, 'btr_assicurazione_importi', true);
                    
                    // DEBUG: Verifica i dati delle assicurazioni
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[BTR DEBUG] ============ ANALISI ASSICURAZIONI FORM ============');
                        error_log('[BTR DEBUG] Package ID: ' . $package_id);
                        error_log('[BTR DEBUG] btr_assicurazione_importi: ' . print_r($btr_assicurazione_importi, true));
                        
                        // Controlla altri possibili meta per assicurazioni
                        $all_package_meta = get_post_meta($package_id);
                        $insurance_related_keys = array_filter(array_keys($all_package_meta), function($key) {
                            return stripos($key, 'assicura') !== false || stripos($key, 'insurance') !== false;
                        });
                        
                        if (!empty($insurance_related_keys)) {
                            error_log('[BTR DEBUG] Altri meta assicurazioni trovati nel pacchetto:');
                            foreach ($insurance_related_keys as $key) {
                                error_log('[BTR DEBUG] ' . $key . ': ' . print_r(get_post_meta($package_id, $key, true), true));
                            }
                        }
                    }
                    
                    if (!empty($btr_assicurazione_importi)) :
                        // ── Extra‑night flags (usati per il calcolo dinamico dell'assicurazione)
                        $extra_night_flag = intval( get_post_meta( $preventivo_id, '_extra_night', true ) );
                        $extra_night_pp   = floatval( get_post_meta( $preventivo_id, '_extra_night_pp', true ) );
                        ?>
                        <?php 
                        // Mostra assicurazioni solo per adulti e bambini, non per neonati
                        if ($child_fascia !== 'neonato'): 
                        ?>
                        <h4><?php esc_html_e('Assicurazioni', 'born-to-ride-booking'); ?></h4>
                        <p><?php esc_html_e('Desideri associare delle assicurazioni a questo partecipante?', 'born-to-ride-booking'); ?></p>

                        <?php
                        // Regola: se questo partecipante ha selezionato "no skipass", non mostrare RC Skipass
                        $no_skipass_for_person = false;
                        // 1) Dati dalla card (payload salvato)
                        if (!empty($persona['costi_extra'])) {
                            // Accetta sia 'no-skipass' che 'no_skipass' come chiave
                            $ns = $persona['costi_extra']['no-skipass'] ?? $persona['costi_extra']['no_skipass'] ?? null;
                            if (is_array($ns)) {
                                $sel = $ns['selected'] ?? $ns['selezionato'] ?? '';
                                $no_skipass_for_person = ($sel === 1 || $sel === '1' || $sel === true || $sel === 'true');
                            } elseif (!empty($ns)) {
                                $no_skipass_for_person = ($ns === 1 || $ns === '1' || $ns === true || $ns === 'true');
                            }
                        }
                        // 2) Fallback: meta individuale _anagrafico_{index}_extra_no_skipass_selected
                        if (!$no_skipass_for_person) {
                            $meta_key = '_anagrafico_' . $index . '_extra_no_skipass_selected';
                            $val = get_post_meta($preventivo_id, $meta_key, true);
                            if ($val !== '') {
                                $no_skipass_for_person = ($val === '1' || $val === 1 || $val === true || $val === 'true');
                            }
                        }
                        ?>
                        <?php foreach ($btr_assicurazione_importi as $assicurazione_index => $assicurazione) :
                        if (empty($assicurazione['descrizione'])) {
                            continue;
                        }
                        $slug = sanitize_title($assicurazione['descrizione']);
                        
                        // LOGICA SPECIALE RC SKIPASS
                        // Verifica se è RC Skipass usando lo slug univoco
                        $is_rc_skipass = (isset($assicurazione['slug']) && $assicurazione['slug'] === 'rc-skipass');
                        
                        // Se è RC Skipass e il partecipante è un neonato, nascondila (i neonati non sciano)
                        if ($is_rc_skipass && $child_fascia === 'neonato') {
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                error_log("[BTR DEBUG] RC Skipass nascosta per partecipante $index - è un neonato");
                            }
                            continue; // Non mostrarla
                        }
                        
                        // Se è RC Skipass e l'utente/partecipante NON ha selezionato "no skipass", pre-seleziona (ma deselezionabile)
                        if ($is_rc_skipass && !$no_skipass_selected && !$no_skipass_for_person) {
                            // Verifica se è stata esplicitamente deselezionata dall'utente
                            if (isset($persona['assicurazioni']) && array_key_exists($slug, $persona['assicurazioni'])) {
                                $is_selected = !empty($persona['assicurazioni'][$slug]);
                            } else {
                                $is_selected = true; // Pre-selezionata di default
                            }
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                error_log("[BTR DEBUG] RC Skipass per partecipante $index - pre-selezionata: " . ($is_selected ? 'SI' : 'NO'));
                            }
                        } else {
                            $is_selected = !empty($persona['assicurazioni'][$slug]);
                        }
                        
                        // Se è RC Skipass e no skipass è selezionato a livello booking o persona, nascondila
                        if ($is_rc_skipass && ($no_skipass_selected || $no_skipass_for_person)) {
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                error_log("[BTR DEBUG] RC Skipass nascosta per partecipante $index - no skipass selezionato");
                            }
                            continue; // Non mostrarla
                        }
                        // Gestione tipo importo: fisso o percentuale
                        $tipo_importo = $assicurazione['tipo_importo'] ?? 'percentuale';
                        $valore_importo = floatval( $assicurazione['importo'] ?? 0 );
                        
                        if ($tipo_importo === 'fisso') {
                            // Importo fisso in euro
                            $importo = $valore_importo;
                            $percentuale = 0; // Non c'è percentuale per importo fisso
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                error_log("[BTR DEBUG] Assicurazione FISSA: {$assicurazione['descrizione']} - Importo: €{$importo}");
                            }
                        } else {
                            // Calcolo percentuale (comportamento originale)
                            $percentuale = $valore_importo;
                            // Use the full package cost as the base for insurance calculations
                            // Base = costo camera + supplemento + eventuale notte extra di questo partecipante
                            $totale_base = $base_price_static;
                            // Importo assicurazione = percentuale del totale base
                            $importo = round( $totale_base * ( $percentuale / 100 ), 2 );
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                error_log("[BTR DEBUG] Assicurazione PERCENTUALE: {$assicurazione['descrizione']} - Base: €{$totale_base}, Percentuale: {$percentuale}%, Importo: €{$importo}");
                            }
                        }

                        // ──────────────────────────────────────────────────────────
                        // Ricerca ID prodotto assicurazione
                        // 1. Prima da meta "btr_assicurazioni_prodotti" con match tollerante
                        // 2. Se fallisce, tenta un WP_Query sul titolo del prodotto
                        // ──────────────────────────────────────────────────────────
                        $product_id = 0;
                        $prodotti_assicurazione = get_post_meta( $package_id, 'btr_assicurazioni_prodotti', true );

                        if ( ! empty( $prodotti_assicurazione ) ) {
                            foreach ( $prodotti_assicurazione as $prodotto ) {
                                if ( empty( $prodotto['descrizione'] ) || $prodotto['descrizione'] !== $assicurazione['descrizione'] ) {
                                    continue;
                                }

                                // Confronto tollerante sui centesimi per evitare problemi di arrotondamento
                                $diff = abs( floatval( $prodotto['importo'] ?? 0 ) - floatval( $importo ) );
                                if ( $diff < 0.01 ) {
                                    $product_id = intval( $prodotto['id'] ?? 0 );
                                    break;
                                }
                            }
                        }

                        // Fallback: cerca il prodotto pubblicato con titolo uguale alla descrizione
                        if ( $product_id === 0 ) {
                            $query = new WP_Query( [
                                'post_type'      => 'product',
                                'title'          => $assicurazione['descrizione'],
                                'post_status'    => 'publish',
                                'posts_per_page' => 1,
                                'fields'         => 'ids',
                            ] );

                            if ( $query->have_posts() ) {
                                $product_id = intval( $query->posts[0] );
                            }
                            wp_reset_postdata();
                        }
                        ?>
                        <div class="btr-assicurazione-item" 
                             data-percent="<?php echo esc_attr($percentuale); ?>"
                             data-tipo-importo="<?php echo esc_attr($tipo_importo); ?>"
                             data-importo-fisso="<?php echo esc_attr($tipo_importo === 'fisso' ? $importo : 0); ?>">
                            <label>
                                <input type="checkbox"
                                       name="anagrafici[<?php echo esc_attr($index); ?>][assicurazioni][<?php echo esc_attr($slug); ?>]"
                                       value="1" 
                                       <?php checked($is_selected, true); ?>
                                       <?php if ($is_rc_skipass): ?>
                                       data-rc-skipass="true"
                                       <?php endif; ?>
                                       <?php if ($is_rc_skipass): ?>
                                       data-no-fiscal-code="true"
                                       <?php endif; ?> />
                                <?php echo esc_html($assicurazione['descrizione']); ?>
                                
                                
                                <?php if (!empty($assicurazione['tooltip_text'])) : ?>
                                    <span class="btr-info-wrapper">
                                        <button type="button"
                                                class="btr-info-icon"
                                                aria-describedby="tooltip-<?php echo esc_attr($slug); ?>"
                                                aria-label="<?php esc_attr_e('Informazioni polizza', 'born-to-ride-booking'); ?>"
                                                title="">
                                            <svg class="btr-icon btr-icon-info-outline" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none"/>
                                                <line x1="12" y1="8" x2="12" y2="12" stroke="currentColor" stroke-width="2"/>
                                                <circle cx="12" cy="16" r="1" fill="currentColor"/>
                                            </svg>
                                        </button>
                                        <div id="tooltip-<?php echo esc_attr($slug); ?>"
                                             role="tooltip"
                                             class="btr-tooltip">
                                            <?php echo wp_kses_post($assicurazione['tooltip_text']); ?>
                                        </div>
                                    </span>
                                <?php endif; ?>
                                <!-- <?php echo ' <span class="btr-debug-base">( ' . number_format_i18n( $totale_base, 2 ) . ' € )</span>'; ?>-->
                                <?php if (!empty($assicurazione['assicurazione_view_prezzo']) && $assicurazione['assicurazione_view_prezzo'] == '1') : ?>
                                    <strong><?php echo btr_format_price_i18n((float)$importo); ?></strong>
                                <?php endif; ?>
                                <?php if (!empty($assicurazione['assicurazione_view_percentuale']) && $assicurazione['assicurazione_view_percentuale'] == '1' && $tipo_importo === 'percentuale') : ?>
                                    <strong>+ <?php echo floatval($percentuale); ?>%</strong>
                                <?php endif; ?>
                            </label>
                            <input type="hidden"
                                   name="anagrafici[<?php echo esc_attr( $index ); ?>][assicurazioni_dettagliate][<?php echo esc_attr( $slug ); ?>][product_id]"
                                   value="<?php echo esc_attr( $product_id ); ?>"<?php echo $is_selected ? '' : ' disabled'; ?>>
                            <input type="hidden"
                                   name="anagrafici[<?php echo esc_attr($index); ?>][assicurazioni_dettagliate][<?php echo esc_attr($slug); ?>][descrizione]"
                                   value="<?php echo esc_attr($assicurazione['descrizione']); ?>"<?php echo $is_selected ? '' : ' disabled'; ?>>
                            <input type="hidden"
                                   name="anagrafici[<?php echo esc_attr($index); ?>][assicurazioni_dettagliate][<?php echo esc_attr($slug); ?>][importo]"
                                   value="<?php echo esc_attr($importo); ?>"<?php echo $is_selected ? '' : ' disabled'; ?>>
                            <input type="hidden"
                                   name="anagrafici[<?php echo esc_attr($index); ?>][assicurazioni_dettagliate][<?php echo esc_attr($slug); ?>][tipo_importo]"
                                   value="<?php echo esc_attr($tipo_importo); ?>"<?php echo $is_selected ? '' : ' disabled'; ?>>
                        </div>
                        <?php if ($is_rc_skipass): ?>
                            <div class="btr-rc-skipass-alert" data-person-index="<?php echo esc_attr($index); ?>" style="display: none; margin-top: 5px; padding: 8px 12px; background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; color: #856404; font-size: 0.85em; line-height: 1.4;">
                                <strong>⚠️</strong> Obbligatoria per legge. Disattivabile solo con assicurazione personale.
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php else : ?>
                        <p><?php esc_html_e('Nessuna assicurazione disponibile.', 'born-to-ride-booking'); ?></p>
                    <?php endif; ?>
                    <?php endif; ?>
                </fieldset>

                <?php
                /* ==================== COSTI EXTRA PER PARTECIPANTE ==================== */
                $btr_costi_extra = get_post_meta( $package_id, 'btr_costi_extra', true );
                
                // v1.0.212: Aggiungi automaticamente "Culla per Neonati" se non presente
                if (!isset($btr_costi_extra) || !is_array($btr_costi_extra)) {
                    $btr_costi_extra = [];
                }
                
                // Controlla se esiste "Culla per Neonati"
                $has_culla = false;
                foreach ($btr_costi_extra as $extra) {
                    if (isset($extra['slug']) && $extra['slug'] === 'culla-per-neonati') {
                        $has_culla = true;
                        break;
                    }
                }
                
                // Aggiungi "Culla per Neonati" se non presente e ci sono neonati
                if (!$has_culla && $num_neonati > 0) {
                    // Recupera il prezzo della culla dal preventivo salvato o usa vuoto
                    $culla_price = get_post_meta($preventivo_id, '_extra_cost_price_culla_per_neonati', true) ?: '';
                    
                    $culla_extra = [
                        'nome' => 'Culla per Neonati',
                        'slug' => 'culla-per-neonati',
                        'importo' => $culla_price, // Prezzo dal preventivo o vuoto (JS userà default)
                        'moltiplica_persone' => '1',
                        'moltiplica_durata' => '0',
                        'attivo' => '1',
                        'tooltip_text' => 'Culla aggiuntiva per neonati (0-2 anni).',
                    ];
                    array_unshift($btr_costi_extra, $culla_extra);
                }
                
                // Mostra costi extra solo per adulti e bambini, non per neonati
                if ( ! empty( $btr_costi_extra ) && $child_fascia !== 'neonato' ) : ?>
                    <fieldset class="btr-assicurazioni">
                        <h4><?php esc_html_e( 'Costi Extra', 'born-to-ride-booking' ); ?></h4>
                        <p><?php esc_html_e( 'Desideri associare dei costi extra a questo partecipante?', 'born-to-ride-booking' ); ?></p>

                        <?php 
                        // === LOGICA CULLA NEONATI ===
                        // Conta quanti neonati ci sono e quante culle sono già state selezionate
                        $count_neonati_totali = 0;
                        $count_culle_selezionate = 0;
                        
                        // PRIMA: Usa il valore affidabile salvato nel preventivo
                        if ($num_neonati > 0) {
                            $count_neonati_totali = $num_neonati;
                        }
                        
                        // FALLBACK: Se non c'è il meta o è 0, conta manualmente dai dati anagrafici
                        if ($count_neonati_totali === 0 && !empty($anagrafici) && is_array($anagrafici)) {
                            foreach ($anagrafici as $p_idx => $p) {
                                // Conta neonati dalla fascia o tipo persona
                                if ((!empty($p['fascia']) && $p['fascia'] === 'neonato') || (!empty($p['tipo_persona']) && $p['tipo_persona'] === 'neonato')) {
                                    $count_neonati_totali++;
                                } elseif (!empty($p['data_nascita'])) {
                                    $event_date = btr_parse_event_date( $data_pacchetto );
                                    if (!empty($event_date)) {
                                        $dob = DateTime::createFromFormat( 'Y-m-d', $p['data_nascita'] );
                                        if ( ! $dob ) {
                                            $dob = DateTime::createFromFormat( 'd/m/Y', $p['data_nascita'] );
                                        }
                                        if ( $dob ) {
                                            $age = ( new DateTime( $event_date ) )->diff( $dob )->y;
                                            if ( $age < 2 ) {
                                                $count_neonati_totali++;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        
                        // Conta culle già selezionate
                        if (!empty($anagrafici) && is_array($anagrafici)) {
                            foreach ($anagrafici as $p_idx => $p) {
                                if (!empty($p['costi_extra_dettagliate']['culla-per-neonati'])) {
                                    $count_culle_selezionate++;
                                }
                            }
                        }
                        
                        // Debug logging per troubleshooting
                        if (defined('WP_DEBUG') && WP_DEBUG) {
                            error_log("[BTR CULLA DEBUG] CONTEGGIO: num_neonati_meta={$num_neonati}, count_neonati_totali={$count_neonati_totali}, count_culle_selezionate={$count_culle_selezionate}");
                        }
                        
                        foreach ( $btr_costi_extra as $extra_idx => $extra ) :
                            if (empty($extra['nome']) && empty($extra['descrizione'])) {
                                continue;
                            }
                            if ( $extra['moltiplica_durata'] ?? false ) {
                                // Se il costo extra è moltiplicato per il numero di persone, salta
                                continue;
                            }
                            // Crea slug robusto
                            $slug_extra = sanitize_title( $extra['slug'] ?? ( $extra['nome'] ?? 'extra' ) );
                            
                            // Verifica se questo costo extra era presente nel preventivo originale
                            $was_in_preventivo = !empty($costi_extra_originali_per_persona[$index][$slug_extra]);
                            
                            // FIX BUG COSTI EXTRA: Un costo extra è selezionato se:
                            // 1. È presente nell'array costi_extra (checkbox marcata dall'utente) OPPURE
                            // 2. Era presente nel preventivo originale (deve essere pre-selezionato)
                            $is_selected = !empty($persona['costi_extra'][$slug_extra]) || $was_in_preventivo;
                            
                            // DEBUG: Verifica selezione
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                error_log("[BTR DEBUG] ========== COSTO EXTRA $slug_extra ==========");
                                error_log("[BTR DEBUG] Persona $index - Slug: $slug_extra");
                                error_log("[BTR DEBUG] Era nel preventivo: " . ($was_in_preventivo ? 'SI' : 'NO'));
                                error_log("[BTR DEBUG] Checkbox selezionata: " . ($is_selected ? 'SI' : 'NO'));
                                $__btr_val = $persona['costi_extra'][$slug_extra] ?? 'non presente';
                                if (is_array($__btr_val)) { $__btr_val = wp_json_encode($__btr_val); }
                                error_log("[BTR DEBUG] Valore in costi_extra: " . $__btr_val);
                                error_log("[BTR DEBUG] Dati persona: " . print_r($persona, true));
                                if ($was_in_preventivo) {
                                    error_log("[BTR DEBUG] Dettagli originali: " . print_r($costi_extra_originali_per_persona[$index][$slug_extra] ?? [], true));
                                }
                            }
                            
                            // === LOGICA SPECIALE PER CULLA NEONATI ===
                            $is_culla_neonati = ($slug_extra === 'culla-per-neonati');
                            $culla_disabled = false;
                            $culla_message = '';
                            $show_culla = true; // Default: mostra sempre (sarà filtrato dopo)
                            
                            // Determina se questo partecipante è un adulto
                            // Un partecipante è adulto se:
                            // 1. Non ha una fascia età di bambino (f1, f2, f3, f4) E non è un neonato
                            // 2. OPPURE se è nei primi N partecipanti dove N = num_adults
                            $is_adult = ($child_fascia === '' || $child_fascia === 'adulto') && ($index < intval($num_adults));
                            
                            if ($is_culla_neonati) {
                                // Debug logging per la logica culla
                                if (defined('WP_DEBUG') && WP_DEBUG) {
                                    error_log("[BTR CULLA DEBUG] Partecipante {$index}: fascia='{$child_fascia}', is_adult=" . ($is_adult ? 'true' : 'false') . ", neonati_totali={$count_neonati_totali}, culle_selezionate={$count_culle_selezionate}");
                                }
                                
                                // REGOLA 1: La culla appare SOLO agli adulti E solo se ci sono neonati
                                if ($count_neonati_totali === 0 || !$is_adult) {
                                    $show_culla = false;
                                    if (defined('WP_DEBUG') && WP_DEBUG) {
                                        $reason = $count_neonati_totali === 0 ? 'nessun neonato' : 'non è adulto';
                                        error_log("[BTR CULLA DEBUG] Culla nascosta per partecipante {$index}: {$reason}");
                                    }
                                }
                                // REGOLA 2: Se questo adulto può vedere la culla, gestisci la logica di disabilitazione
                                elseif ($is_adult && $count_neonati_totali > 0) {
                                    // Se ci sono già culle selezionate >= numero neonati E questa non era già selezionata
                                    if ($count_culle_selezionate >= $count_neonati_totali && !$is_selected) {
                                        $culla_disabled = true;
                                        $culla_message = sprintf('(Già assegnate %d/%d culle per i neonati)', $count_culle_selezionate, $count_neonati_totali);
                                    }
                                }
                            }
                            
                            $importo_extra      = $extra['importo']      ?? '';
                            $percentuale_extra  = $extra['percentuale']  ?? '';
                            
                            // Non mostrare la voce se è una culla e questo partecipante non deve vederla
                            if ($is_culla_neonati && !$show_culla) {
                                continue;
                            }
                            ?>
                            <div class="btr-assicurazione-item">
                                <label class="<?php echo $culla_disabled ? 'btr-disabled-option' : ''; ?>">
                                    <input type="checkbox"
                                           name="anagrafici[<?php echo esc_attr( $index ); ?>][costi_extra][<?php echo esc_attr( $slug_extra ); ?>]"
                                           value="1" 
                                           <?php 
                                           checked( $is_selected, true ); 
                                           // DEBUG IMMEDIATO per verificare lo stato della checkbox
                                           if (defined('WP_DEBUG') && WP_DEBUG) {
                                               error_log("[BTR CHECKBOX] Persona $index, Slug: $slug_extra, Checked: " . ($is_selected ? 'SI' : 'NO'));
                                           }
                                           ?>
                                           <?php disabled( $culla_disabled, true ); ?>
                                           data-was-in-preventivo="<?php echo $was_in_preventivo ? '1' : '0'; ?>"
                                           data-original-amount="<?php echo esc_attr($was_in_preventivo ? $costi_extra_originali_per_persona[$index][$slug_extra]['importo'] : 0); ?>"
                                           data-person-index="<?php echo esc_attr($index); ?>"
                                           data-cost-slug="<?php echo esc_attr($slug_extra); ?>"
                                           data-importo="<?php echo esc_attr($importo_extra); ?>"
                                           data-is-culla="<?php echo $is_culla_neonati ? '1' : '0'; ?>"
                                           <?php if ($is_culla_neonati): ?>
                                           data-crib-checkbox="true" 
                                           data-participant-index="<?php echo esc_attr($index); ?>"
                                           data-max-cribs="<?php echo esc_attr($count_neonati_totali); ?>"
                                           class="btr-crib-checkbox"
                                           <?php endif; ?> />
                                    <?php 
                                    // Pulisci il nome rimuovendo prezzi e suffissi
                                    $nome_pulito = $extra['nome'] ?? ( $extra['descrizione'] ?? 'Extra' );
                                    // Rimuovi pattern come "€ XX,XX a notte" o "€ XX,XX per persona"
                                    $nome_pulito = preg_replace('/\s*€\s*[\d.,]+\s*(a notte|per persona|per notte)?/i', '', $nome_pulito);
                                    
                                    // FIX v1.0.210: Assicurati che "a notte" non venga mai mostrato per animale domestico
                                    if ($slug_extra === 'animale-domestico') {
                                        $nome_pulito = str_replace([' a notte', ' per notte'], '', $nome_pulito);
                                    }
                                    
                                    echo esc_html( trim($nome_pulito) ); 
                                    ?>
                                    <?php if ($was_in_preventivo): ?>
                                        <small class="btr-original-selection" style="color: #0097c5; font-weight: 500;">(selezionato nel preventivo)</small>
                                    <?php endif; ?>
                                    <?php if ($culla_message): ?>
                                        <small class="btr-culla-message" style="color: #666; font-style: italic;"><?php echo esc_html($culla_message); ?></small>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $extra['tooltip_text'] ) ) : ?>
                                        <span class="btr-info-wrapper">
                                            <button type="button"
                                                    class="btr-info-icon"
                                                    aria-describedby="cost-tooltip-<?php echo esc_attr( $slug_extra ); ?>"
                                                    aria-label="<?php esc_attr_e( 'Informazioni costo extra', 'born-to-ride-booking' ); ?>"
                                                    title="">
                                                <svg class="btr-icon btr-icon-info-outline"
                                                     xmlns="http://www.w3.org/2000/svg"
                                                     viewBox="0 0 24 24"
                                                     aria-hidden="true"
                                                     focusable="false">
                                                    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2" fill="none"/>
                                                    <line x1="12" y1="8" x2="12" y2="12" stroke="currentColor" stroke-width="2"/>
                                                    <circle cx="12" cy="16" r="1" fill="currentColor"/>
                                                </svg>
                                            </button>
                                            <div id="cost-tooltip-<?php echo esc_attr( $slug_extra ); ?>"
                                                 role="tooltip"
                                                 class="btr-tooltip">
                                                <?php 
                                                // FIX v1.0.210: Per animale domestico, rimuovi riferimenti a "a notte" dal tooltip
                                                $tooltip_text = $extra['tooltip_text'];
                                                if ($slug_extra === 'animale-domestico') {
                                                    $tooltip_text = str_replace('€ 10,00 a notte', '€ 10,00', $tooltip_text);
                                                    $tooltip_text = str_replace('calcolato a', 'di', $tooltip_text);
                                                }
                                                echo wp_kses_post( $tooltip_text ); 
                                                ?>
                                            </div>
                                        </span>
                                    <?php endif; ?>

                                    <?php if ( $importo_extra !== '' ) : ?>
                                        <strong><?php echo btr_format_price_i18n( (float) $importo_extra ); ?></strong>
                                    <?php endif; ?>
                                    <?php if ( $percentuale_extra !== '' ) : ?>
                                        <strong>+ <?php echo floatval( $percentuale_extra ); ?>%</strong>
                                    <?php endif; ?>
                                </label>

                                <!-- Dettagli nascosti per il backend -->
                                <input type="hidden"
                                       name="anagrafici[<?php echo esc_attr( $index ); ?>][costi_extra_dettagliate][<?php echo esc_attr( $slug_extra ); ?>][descrizione]"
                                       value="<?php echo esc_attr( $extra['nome'] ?? ( $extra['descrizione'] ?? 'Extra' ) ); ?>"
                                       data-checkbox-selected="<?php echo $is_selected ? '1' : '0'; ?>">
                                <?php
                                // --- Inserimento product_id hidden dopo descrizione (solo una volta per ogni voce, qui va bene dopo descrizione) ---
                                $product_title = $extra['nome'] ?? ( $extra['descrizione'] ?? '' );
                                $product_query = new WP_Query([
                                    'post_type'      => 'product',
                                    'title'          => $product_title,
                                    'post_status'    => 'publish',
                                    'posts_per_page' => 1,
                                ]);
                                $product_id = 0;
                                if ($product_query->have_posts()) {
                                    $product_id = $product_query->posts[0]->ID;
                                }
                                wp_reset_postdata();
                                ?>
                                <input type="hidden"
                                       name="anagrafici[<?php echo esc_attr( $index ); ?>][costi_extra_dettagliate][<?php echo esc_attr( $slug_extra ); ?>][product_id]"
                                       value="<?php echo esc_attr( $product_id ); ?>"
                                       data-checkbox-selected="<?php echo $is_selected ? '1' : '0'; ?>">
                                <input type="hidden"
                                       name="anagrafici[<?php echo esc_attr( $index ); ?>][costi_extra_dettagliate][<?php echo esc_attr( $slug_extra ); ?>][importo]"
                                       value="<?php echo esc_attr( $importo_extra ); ?>"
                                       data-checkbox-selected="<?php echo $is_selected ? '1' : '0'; ?>">
                                <input type="hidden"
                                       name="anagrafici[<?php echo esc_attr( $index ); ?>][costi_extra_dettagliate][<?php echo esc_attr( $slug_extra ); ?>][percentuale]"
                                       value="<?php echo esc_attr( $percentuale_extra ); ?>"
                                       data-checkbox-selected="<?php echo $is_selected ? '1' : '0'; ?>">
                                <input type="hidden"
                                       name="anagrafici[<?php echo esc_attr( $index ); ?>][costi_extra_dettagliate][<?php echo esc_attr( $slug_extra ); ?>][moltiplica_durata]"
                                       value="<?php echo esc_attr( !empty($extra['moltiplica_durata']) ? '1' : '0' ); ?>"
                                       data-checkbox-selected="<?php echo $is_selected ? '1' : '0'; ?>">
                                <input type="hidden"
                                       name="anagrafici[<?php echo esc_attr( $index ); ?>][costi_extra_dettagliate][<?php echo esc_attr( $slug_extra ); ?>][moltiplica_persone]"
                                       value="<?php echo esc_attr( !empty($extra['moltiplica_persone']) ? '1' : '0' ); ?>"
                                       data-checkbox-selected="<?php echo $is_selected ? '1' : '0'; ?>">
                            </div>
                        <?php endforeach; ?>
                    </fieldset>
                <?php elseif ( ! empty( $btr_costi_extra ) ) : ?>
                    <!-- Neonato fantasma: nessun costo extra -->
                    <div class="btr-infant-no-extras" style="background: #f9f9f9; padding: 10px; border-radius: 4px; margin: 10px 0;">
                        <p style="margin: 0; color: #666;"><strong>🚫 Neonato</strong> - Non può avere costi extra o assicurazioni.</p>
                    </div>
                <?php else : ?>
                    <p><?php esc_html_e( 'Nessun costo extra disponibile.', 'born-to-ride-booking' ); ?></p>
                <?php endif; ?>

                </div>
            </div>
        <?php endforeach; ?>
    </div>


    <!-- Aggiungi questo codice prima del pulsante di checkout -->
    <?php if (empty($remaining_time)): ?>
        <div id="btr-checkout-summary" class="btr-checkout-summary">
            <div class="btr-summary-header">
            <div class="wpb_wrapper ps-1">
                <h2 id="title-step" style="color: #0097c5;text-align: left; font-size: 30px; margin-bottom:0" class="vc_custom_heading vc_do_custom_heading">Riepilogo ordine</h2>
                <p id="desc-step">Verifica i dettagli prima di procedere al checkout</p>
            </div>
            </div>


            <div class="btr-summary-grid">
                <div class="btr-summary-card">
                    <div class="btr-summary-card-header">
                        <div class="btr-summary-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                        </div>
                        <h4>Partecipanti</h4>
                    </div>
                    <div class="btr-summary-card-body">
                        <div class="btr-summary-item">
                            <span class="btr-summary-label">Totale partecipanti</span>
                            <span class="btr-summary-value" id="btr-summary-total-participants"><?php echo esc_html($total_partecipanti); ?></span>
                        </div>
                        <div class="btr-summary-item">
                            <span class="btr-summary-label">Partecipanti con assicurazione</span>
                            <span class="btr-summary-value" id="btr-summary-insured-participants">0</span>
                        </div>
                    </div>
                </div>

                <div class="btr-summary-card">
                    <div class="btr-summary-card-header">
                        <div class="btr-summary-icon">
                            <svg id="Raggruppa_35" data-name="Raggruppa 35" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="45.18" height="41.607" viewBox="0 0 45.18 41.607">
                                <defs>
                                    <clipPath id="clip-path">
                                        <rect id="Rettangolo_24" data-name="Rettangolo 24" width="45.18" height="41.607" fill="#fff"/>
                                    </clipPath>
                                </defs>
                                <g id="Raggruppa_21" data-name="Raggruppa 21" clip-path="url(#clip-path)">
                                    <path id="Tracciato_35" data-name="Tracciato 35" d="M45.18,26.337V38.578A3.621,3.621,0,0,1,38.8,40.253a4.118,4.118,0,0,1-.747-1.63V36.905H7.239v1.717a4.116,4.116,0,0,1-.747,1.63A3.621,3.621,0,0,1,.108,38.578c.255-3.952-.341-8.34,0-12.242a10.632,10.632,0,0,1,2.727-5.859l0-14.886A6.279,6.279,0,0,1,8.336,0H36.689a6.266,6.266,0,0,1,5.589,5.593V20.3a10.6,10.6,0,0,1,2.9,6.035m-4.666-7.4V5.508c0-1.625-2.33-3.8-4-3.747L8.349,1.775C6.846,1.841,4.6,3.944,4.6,5.42V19.115a5.785,5.785,0,0,1,1.838-.891c.1-1.437-.192-3,.235-4.389a4.721,4.721,0,0,1,4.041-3.267c2.873-.2,5.987.151,8.885.014a4.99,4.99,0,0,1,3.015,1.833c.193.033.383-.395.516-.521a5.129,5.129,0,0,1,2.562-1.312c2.911.156,6.094-.241,8.973-.013A4.778,4.778,0,0,1,38.64,13.9c.4,1.363.107,2.917.212,4.324ZM21.764,17.53V14.844a3.094,3.094,0,0,0-2.284-2.471c-2.785.086-5.834-.249-8.591-.043a2.883,2.883,0,0,0-2.363,1.6,7.152,7.152,0,0,0-.318.915v2.862c.542-.052,1.177-.159,1.713-.18,3.911-.152,7.923.121,11.844,0m15.317.176V14.844c0-1.228-1.507-2.426-2.681-2.514-2.739-.2-5.753.142-8.52.027a3.165,3.165,0,0,0-2.356,2.4V17.53c3.947.124,8-.164,11.932,0,.508.021,1.111.13,1.625.18M43.419,29.86V26.469a9.429,9.429,0,0,0-1.161-3.33,8.17,8.17,0,0,0-6.89-3.852c-8.314-.438-17.007.339-25.36,0-3.671-.278-8.139,3.457-8.139,7.182V29.86h23.9a1.006,1.006,0,0,1,.906.858c.025.269-.281.9-.554.9H1.869v3.523h41.55V31.621H34.308a1.278,1.278,0,0,1-.5-.463.955.955,0,0,1,.769-1.3ZM5.478,36.905H1.869v1.453c0,.03.189.489.229.564a1.85,1.85,0,0,0,3.1.127,4.973,4.973,0,0,0,.278-.6Zm37.941,0H39.81v1.541a4.973,4.973,0,0,0,.278.6,1.85,1.85,0,0,0,3.1-.127c.039-.075.229-.534.229-.564Z" transform="translate(0)" fill="#fff"/>
                                    <path id="Tracciato_36" data-name="Tracciato 36" d="M250.993,255.053a.873.873,0,1,1-.873-.873.873.873,0,0,1,.873.873" transform="translate(-219.922 -224.274)" fill="currentColor"/>
                                </g>
                            </svg>

                        </div>
                        <h4>Camere</h4>
                    </div>


                    <div class="btr-summary-card-body">
                        <div id="btr-summary-rooms-list">
                            <?php foreach ($camere_acquistate as $camera_id => $camera): ?>
                                <div class="btr-summary-item">
                                    <span class="btr-summary-label"><?php echo esc_html($camera['tipo']); ?></span>
                                    <?php 
                                    $cap = isset($camera['capacita']) ? $camera['capacita'] : (isset($camera['capacity']) ? $camera['capacity'] : ($btr_default_capacita[$camera['tipo']] ?? ''));
                                    ?>
                                    <span class="btr-summary-value"><?php echo esc_html($camera['quantita']); ?><?php echo ($cap !== '' ? ' × ' . esc_html($cap) . ' posti' : ''); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="btr-summary-item btr-summary-total">
                            <span class="btr-summary-label">Totale camere</span>
                            <span class="btr-summary-value">
                            <?php
                            $totale_camere = 0;
                            foreach ($camere_acquistate as $camera) {
                                $totale_camere += $camera['quantita'];
                            }
                            echo esc_html($totale_camere);
                            // Usa il breakdown dettagliato del preventivo per calcoli precisi
                            $riepilogo_calcoli_dettagliato = get_post_meta( $preventivo_id, '_riepilogo_calcoli_dettagliato', true );

                            // Decodifica (può essere serializzato o JSON)
                            if ( ! empty( $riepilogo_calcoli_dettagliato ) && is_string( $riepilogo_calcoli_dettagliato ) ) {
                                $riepilogo_calcoli_dettagliato = maybe_unserialize( $riepilogo_calcoli_dettagliato );
                                if ( is_string( $riepilogo_calcoli_dettagliato ) ) {
                                    $riepilogo_calcoli_dettagliato = json_decode( $riepilogo_calcoli_dettagliato, true );
                                }
                            }

                            // CORREZIONE 2025-01-19: Variabili già calcolate correttamente alle linee 163-164
                            // Non ridefinire qui per evitare calcoli errati
                            // Le variabili $package_price_no_extra e $extra_night_cost sono già impostate
                            // dal riepilogo dettagliato nella parte superiore del file
                            ?>
                        </span>
                        </div>
                    </div>
                </div>



                <div id="btr-summary-card-insurance"
                     class="btr-summary-card btr-summary-card-full"
                     data-category="insurance"
                     style="<?php echo $totale_assicurazioni > 0 ? '' : 'display:none;'; ?>">
                    <div class="btr-summary-card-header">
                        <div class="btr-summary-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                        </div>
                        <h4>Assicurazioni</h4>
                    </div>
                    <div class="btr-summary-card-body" id="btr-summary-insurance-container">
                        <div class="btr-summary-item btr-summary-placeholder">
                            <span class="btr-summary-label">Nessuna assicurazione selezionata</span>
                        </div>
                        <!-- Le assicurazioni verranno aggiunte dinamicamente via JavaScript -->
                    </div>
                </div>

                <div id="btr-summary-card-extra"
                     class="btr-summary-card btr-summary-card-full"
                     data-category="extra"
                     style="<?php echo (!empty($btr_costi_extra) && is_array($btr_costi_extra)) ? '' : 'display:none;'; ?>">
                    <div class="btr-summary-card-header">
                        <div class="btr-summary-icon">
                            <!-- icona pacco / plus -->
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M8 12h8M12 8v8"></path></svg>
                        </div>
                        <h4>Costi Extra</h4>
                    </div>
                    <div class="btr-summary-card-body" id="btr-summary-extra-container">
                        <div class="btr-summary-item btr-summary-placeholder">
                            <span class="btr-summary-label">Nessun costo extra selezionato</span>
                        </div>
                        <!-- I costi extra verranno aggiunti dinamicamente via JavaScript -->
                        <!-- Tracking dei costi originali per la gestione finanziaria -->
                        <div class="btr-cost-tracking" style="display: none;">
                            <span class="original-total"><?php echo esc_attr($totale_preventivo_originale); ?></span>
                            <span class="original-extra-costs"><?php echo esc_attr($totale_costi_extra_originali); ?></span>
                        </div>
                    </div>
                </div>

                <div class="btr-summary-card btr-summary-card-total">
                    <div class="btr-summary-card-header">
                        <div class="btr-summary-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#000000" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="20.5" r="1"/><circle cx="18" cy="20.5" r="1"/><path d="M2.5 2.5h3l2.7 12.4a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 2-1.6l1.6-8.4H7.1"/></svg>
                        </div>
                        <h4>Totale</h4>
                    </div>
                    <div class="btr-summary-card-body">
                        <div class="btr-summary-item">
                            <span class="btr-summary-label">Totale Camere</span>
                            <span class="btr-summary-value" id="btr-summary-package-price"><?php echo btr_format_price_i18n($totale_camere_saved); ?></span>
                            
                        </div>

                        <?php 
                        // NOTA: Notti extra ora incluse nel "Totale Camere" sopra
                        // Non mostrare riga separata per evitare confusione
                        if ( false && intval( $extra_night_flag ) === 1 || $extra_night_cost > 0 ) : ?>
                            <div class="btr-summary-item" style="display: none;">
                                <span class="btr-summary-label"><?php echo esc_html($durata_label); ?> (notti extra)</span>
                                <span
                                        class="btr-summary-value"
                                        id="btr-summary-extra-night-total"
                                        data-pp="<?php echo esc_attr( $extra_night_pp ); ?>"
                                        data-participants="<?php echo esc_attr( intval( $num_adults ) + intval( $num_children ) ); ?>">
                                    <?php echo btr_format_price_i18n( $extra_night_cost ); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        <div class="btr-summary-item summary-insurance-total"
                             style="<?php echo $totale_assicurazioni > 0 ? '' : 'display:none;'; ?>">
                            <span class="btr-summary-label">Totale assicurazioni</span>
                            <span class="btr-summary-value" id="btr-summary-insurance-total">0,00 €</span>
                        </div>
                        <div class="btr-summary-item summary-extra-total"
                             style="<?php 
                             // Mostra se ci sono costi extra disponibili o se ci sono costi extra già selezionati
                             $show_extra_section = (!empty($btr_costi_extra) && is_array($btr_costi_extra)) || $totale_costi_extra_originali != 0;
                             echo $show_extra_section ? '' : 'display:none;'; 
                             if (defined('WP_DEBUG') && WP_DEBUG) {
                                 error_log('[BTR DEBUG] Show extra section: ' . ($show_extra_section ? 'SI' : 'NO'));
                                 error_log('[BTR DEBUG] btr_costi_extra available: ' . ((!empty($btr_costi_extra) && is_array($btr_costi_extra)) ? 'SI' : 'NO'));
                                 error_log('[BTR DEBUG] totale_costi_extra_originali: ' . $totale_costi_extra_originali);
                             }
                             ?>">
                            <span class="btr-summary-label">Totale costi extra</span>
                            <span class="btr-summary-value" id="btr-summary-extra-total"><?php echo btr_format_price_i18n(abs($totale_costi_extra_originali)); ?></span>
                        </div>
                        <div class="btr-summary-item btr-summary-grand-total">
                            <span class="btr-summary-label">Totale finale</span>
                            <span class="btr-summary-value" id="btr-summary-grand-total">
                                <?php
                                // USA ESATTAMENTE IL TOTALE DEL PREVENTIVO SENZA RICALCOLI
                                echo btr_format_price_i18n( $prezzo_totale_preventivo );
                                ?> €
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="btr-summary-notes">
                <div class="btr-note">
                    <div class="btr-note-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                    </div>
                    <div class="btr-note-content">
                        <h5>Informazioni importanti</h5>
                        <p>
                          <?php esc_html_e('Assicurati che tutti i dati dei partecipanti siano corretti e che ogni partecipante sia assegnato a una camera.', 'born-to-ride-booking'); ?>
                          <?php
                            $assicurazioni = get_post_meta($package_id, 'btr_assicurazione_importi', true);
                            if ( ! empty( $assicurazioni ) ) {
                              esc_html_e('Le assicurazioni sono opzionali ma fortemente consigliate.', 'born-to-ride-booking');
                            }
                          ?>
                        </p>
                    </div>
                </div>
            </div>
        <script>
        (function () {
          // Funzione helper per formattazione prezzi italiana
          function btrFormatPriceInline(amount, decimals = 2) {
            amount = parseFloat(amount) || 0;
            const formatted = amount.toLocaleString('it-IT', {
              minimumFractionDigits: decimals,
              maximumFractionDigits: decimals
            });
            return '€' + formatted;
          }
          
          // Improved moneyToFloat: handles spaces, euro, nbsp, thousands separator, Italian decimal
          const moneyToFloat = txt => {
            if (!txt) return 0;
            return parseFloat(
              txt
                .toString()
                .replace(/\s|€|&nbsp;/g, '') // remove spaces, euro sign, nbsp
                .replace(/\./g, '')          // remove thousands separator
                .replace(',', '.')           // convert Italian decimal
            ) || 0;
          };

          function toggleBlocks(){
            const insTot  = document.getElementById('btr-summary-insurance-total');
            const extraTot= document.getElementById('btr-summary-extra-total');
            const insCard = document.getElementById('btr-summary-card-insurance');
            const extraCard=document.getElementById('btr-summary-card-extra');
            const insRow = document.querySelector('.summary-insurance-total');
            const extraRow= document.querySelector('.summary-extra-total');

            // Per le assicurazioni: mostra se il totale è > 0 O se ci sono assicurazioni nascoste per "no skipass"
            if(insTot && insCard && insRow){
              const hasInsuranceTotal = moneyToFloat(insTot.textContent) > 0;
              const hasHiddenInsurance = window.hasHiddenInsuranceDueToNoSkipass === true;
              
              // Controlla anche se ci sono assicurazioni disponibili nel form
              const hasAvailableInsurances = document.querySelectorAll('.btr-assicurazione-item').length > 0;
              
              if(!hasInsuranceTotal && !hasHiddenInsurance && !hasAvailableInsurances){
                insCard.style.display='none';
                insRow.style.display='none';
              }else{
                insCard.style.display='';
                insRow.style.display='';
              }
            }
            
            // CORREZIONE: Per i costi extra, mostra se ci sono elementi selezionati (anche con totale negativo)
            if(extraTot && extraCard && extraRow){
              const extraContainer = document.getElementById('btr-summary-extra-container');
              const hasSelectedExtras = extraContainer && 
                extraContainer.querySelectorAll('.btr-summary-item:not(.btr-summary-placeholder)').length > 0;
              
              if(!hasSelectedExtras){
                extraCard.style.display='none';
                extraRow.style.display='none';
              }else{
                extraCard.style.display='';
                extraRow.style.display='';
              }
            }
          }

          // -------------------------------------------------------------
          // MANTIENI I TOTALI DEL PREVENTIVO - aggiorna solo assicurazioni e costi extra dinamici
          function recalcGrandTotal () {
            // Elements
            const pkgEl   = document.getElementById('btr-summary-package-price'); // Ora è "Totale Camere" (include notti extra)
            const insEl   = document.getElementById('btr-summary-insurance-total');
            const extraEl = document.getElementById('btr-summary-extra-total');
            const grandEl = document.getElementById('btr-summary-grand-total');
            if (!pkgEl || !grandEl) return;
            
            // Variabile mancante - sconti/riduzioni già inclusi nel totale
            const scontiRiduzioni = 0;

            // MANTIENI il totale camere dal preventivo (include già notti extra)
            const originalRoomTotal = <?php echo json_encode($totale_camere_saved); ?>; // UNIFIED v1.0.217
            pkgEl.textContent = btrFormatPriceInline(parseFloat(originalRoomTotal));

            // Dynamic insurance total
            let insVal = 0;
            if (insEl) {
              insVal = Array.from(document.querySelectorAll('.btr-assicurazione-item input[name*="[assicurazioni]"]:checked'))
                .reduce((sum, cb) => {
                  const impInput = cb.closest('.btr-assicurazione-item')
                                    .querySelector('input[name*="[assicurazioni_dettagliate]"][name*="[importo]"]');
                  return sum + (impInput ? moneyToFloat(impInput.value) : 0);
                }, 0);
              insEl.textContent = btrFormatPriceInline(insVal);
              // La visibilità verrà gestita da toggleBlocks() che verrà chiamata dopo
            }

            // Dynamic extra total
            let extraVal = 0;
            if (extraEl) {
              extraVal = Array.from(document.querySelectorAll('.btr-assicurazione-item input[name*="[costi_extra]"]:checked'))
                .reduce((sum, cb) => {
                  const impInput = cb.closest('.btr-assicurazione-item')
                                    .querySelector('input[name*="[costi_extra_dettagliate]"][name*="[importo]"]');
                  return sum + (impInput ? moneyToFloat(impInput.value) : 0);
                }, 0);
              extraEl.textContent = btrFormatPriceInline(extraVal);
            }

            // Calcola il grand total: Totale Camere + assicurazioni + costi extra + sconti
            const roomTotal = parseFloat(originalRoomTotal);
            let currentExtraTotal = extraEl ? moneyToFloat(extraEl.textContent) : 0;
            let currentInsuranceTotal = insEl ? moneyToFloat(insEl.textContent) : 0;
            
            // Se i totali sono ancora a 0, usa i valori iniziali dal preventivo
            if (currentExtraTotal === 0 && <?php echo json_encode($totale_costi_extra_originali); ?> > 0) {
                currentExtraTotal = <?php echo json_encode($totale_costi_extra_originali); ?>;
                if (extraEl) extraEl.textContent = btrFormatPriceInline(currentExtraTotal);
            }
            
            // CORREZIONE 2025-01-20: Calcolo semplice e diretto del totale
            // Totale = Totale Camere + Assicurazioni + Costi Extra
            // (roomTotal già dichiarato sopra, non serve ridichiararlo)
            
            // DEBUG: Log dei valori per verificare il calcolo
            console.log('[BTR DEBUG JS] Calcolo totale semplice:', {
                roomTotal: roomTotal,
                currentInsuranceTotal: currentInsuranceTotal,
                currentExtraTotal: currentExtraTotal,
                formula: 'roomTotal + currentInsuranceTotal + currentExtraTotal'
            });
            
            const dynamicGrandTotal = roomTotal + currentInsuranceTotal + currentExtraTotal;
            
            grandEl.textContent = btrFormatPriceInline(dynamicGrandTotal);
            
            // Debug log per verificare i calcoli
            if (typeof console !== 'undefined' && console.log) {
              console.log('[BTR] Grand Total Calculation:', {
                roomTotal: roomTotal,
                insuranceTotal: currentInsuranceTotal, 
                extraTotal: currentExtraTotal,
                scontiRiduzioni: scontiRiduzioni,
                grandTotal: dynamicGrandTotal
              });
            }
            
            // Refresh block visibility after totals update
            toggleBlocks();
          }

          // initial check
          document.addEventListener('DOMContentLoaded', function() {
              toggleBlocks();
              // Ricalcola i totali iniziali per assicurarsi che siano corretti
              recalcGrandTotal();
          });

            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('.btr-person-card')
                    .forEach(card => updateInsuranceForPerson(card));
                // Inizializza il riepilogo con i nomi dei partecipanti
                updateSummaryWithParticipantNames();
            });

          // update blocks and totals on load
          toggleBlocks();
          recalcGrandTotal();
          // Aggiorna il riepilogo iniziale
          setTimeout(() => updateSummaryWithParticipantNames(), 100);

          // update when custom event is fired by existing summary‑update code
          document.addEventListener('btr:summaryUpdate', () => { toggleBlocks(); recalcGrandTotal(); });
          // === GESTIONE DINAMICA COSTI EXTRA E CALCOLI FINANZIARI ===
          document.addEventListener('change', e => {
            if (e.target.closest('.btr-assicurazione-item')) {
              toggleBlocks();
              recalcGrandTotal();
              
              // Se è un costo extra, aggiorna anche il tracking finanziario
              if (e.target.name && e.target.name.includes('[costi_extra]')) {
                updateCostTracking(e.target);
                updateSummaryWithParticipantNames();
              }
            }
              if (e.target.name && e.target.name.includes('[costi_extra]')) {
                  const card = e.target.closest('.btr-person-card');
                  updateInsuranceForPerson(card);
              updateCostTracking(e.target);
              updateSummaryWithParticipantNames();
            }
            // Aggiorna anche per le assicurazioni
            if (e.target.name && e.target.name.includes('[assicurazioni]')) {
              updateSummaryWithParticipantNames();
            }
          });
          
          // Funzione per gestire il tracking finanziario dei costi extra
          function updateCostTracking(checkbox) {
            const wasInPreventivo = checkbox.dataset.wasInPreventivo === '1';
            const originalAmount = parseFloat(checkbox.dataset.originalAmount || '0');
            const isChecked = checkbox.checked;
            const personIndex = checkbox.dataset.personIndex;
            const costSlug = checkbox.dataset.costSlug;
            
            // Ottieni il totale attuale
            const grandTotalEl = document.getElementById('btr-summary-grand-total');
            const packagePriceEl = document.getElementById('btr-summary-package-price');
            
            if (!grandTotalEl || !packagePriceEl) return;
            
            const currentGrandTotal = moneyToFloat(grandTotalEl.textContent);
            const currentPackagePrice = moneyToFloat(packagePriceEl.textContent);
            
            // Logica finanziaria: se era nel preventivo e viene deselezionato, sottrai
            // Se non era nel preventivo e viene selezionato, aggiungi
            let adjustment = 0;
            
            if (wasInPreventivo && !isChecked) {
              // Era selezionato nel preventivo, ora deselezionato: sottrai
              adjustment = -originalAmount;
            } else if (wasInPreventivo && isChecked) {
              // Era selezionato nel preventivo, rimane selezionato: nessun aggiustamento
              adjustment = 0;
            } else if (!wasInPreventivo && isChecked) {
              // Non era nel preventivo, ora selezionato: aggiungi
              const currentAmount = parseFloat(
                checkbox.closest('.btr-assicurazione-item')
                  .querySelector('input[name*="[costi_extra_dettagliate]"][name*="[importo]"]')
                  ?.value || '0'
              );
              adjustment = currentAmount;
            }
            
            // Debug log
            if (typeof console !== 'undefined' && console.log) {
              console.log('Cost tracking:', {
                personIndex,
                costSlug,
                wasInPreventivo,
                isChecked,
                originalAmount,
                adjustment
              });
            }
          }
          
          // Funzione per aggiornare il riepilogo con i nomi dei partecipanti
          function updateSummaryWithParticipantNames() {
            // Aggiorna sezione assicurazioni
            updateInsuranceSummaryWithNames();
            // Aggiorna sezione costi extra
            updateExtraCostsSummaryWithNames();
          }
          
          function updateInsuranceSummaryWithNames() {
            const insuranceContainer = document.getElementById('btr-summary-insurance-container');
            if (!insuranceContainer) return;
            
            // Rimuovi elementi esistenti tranne il placeholder
            insuranceContainer.querySelectorAll('.btr-summary-item:not(.btr-summary-placeholder)').forEach(el => el.remove());
            
            let totalInsurance = 0;
            let hasInsurance = false;
            
            // Raccoglie tutte le assicurazioni selezionate
            document.querySelectorAll('.btr-person-card').forEach((card, index) => {
              const personName = getPersonName(card, index);
              
              card.querySelectorAll('input[name*="[assicurazioni]"]:checked').forEach(checkbox => {
                const item = checkbox.closest('.btr-assicurazione-item');
                const description = checkbox.nextSibling?.textContent?.trim() || 'Assicurazione';
                const amountInput = item.querySelector('input[name*="[assicurazioni_dettagliate]"][name*="[importo]"]');
                const amount = parseFloat(amountInput?.value || '0');
                
                if (amount > 0) {
                  hasInsurance = true;
                  totalInsurance += amount;
                  
                  // Crea elemento nel riepilogo
                  const summaryItem = document.createElement('div');
                  summaryItem.className = 'btr-summary-item';
                  summaryItem.innerHTML = `
                    <span class="btr-summary-label">${description} - ${personName}</span>
                    <span class="btr-summary-value">${amount.toLocaleString('it-IT', {minimumFractionDigits: 2})} €</span>
                  `;
                  insuranceContainer.appendChild(summaryItem);
                }
              });
            });
            
            // Mostra/nascondi placeholder e riga totale assicurazioni
            const placeholder = insuranceContainer.querySelector('.btr-summary-placeholder');
            if (placeholder) {
              placeholder.style.display = hasInsurance ? 'none' : 'block';
            }
            
            // Gestisci visibilità riga totale assicurazioni
            const insuranceTotalRow = document.querySelector('.summary-insurance-total');
            if (insuranceTotalRow) {
              insuranceTotalRow.style.display = hasInsurance ? 'flex' : 'none';
              console.log('[BTR VANILLA DEBUG] Riga totale assicurazioni:', hasInsurance ? 'mostrata' : 'nascosta');
            }
            
            // CORREZIONE 2025-01-19: Chiama toggleBlocks per sincronizzare visibilità card
            setTimeout(() => {
              if (typeof toggleBlocks === 'function') {
                console.log('[BTR VANILLA DEBUG] Chiamando toggleBlocks da updateInsuranceSummaryWithNames');
                toggleBlocks();
              }
            }, 10);
          }
          
          function updateExtraCostsSummaryWithNames() {
            const extraContainer = document.getElementById('btr-summary-extra-container');
            if (!extraContainer) return;
            
            // Rimuovi elementi esistenti tranne il placeholder
            extraContainer.querySelectorAll('.btr-summary-item:not(.btr-summary-placeholder)').forEach(el => el.remove());
            
            let totalExtra = 0;
            let hasExtra = false;
            
            // Raccoglie tutti i costi extra selezionati
            document.querySelectorAll('.btr-person-card').forEach((card, index) => {
              const personName = getPersonName(card, index);
              
              card.querySelectorAll('input[name*="[costi_extra]"]:checked').forEach(checkbox => {
                const item = checkbox.closest('.btr-assicurazione-item');
                
                // Prendi la descrizione dal campo hidden, non dal label che contiene anche il prezzo
                const descriptionInput = item.querySelector('input[name*="[costi_extra_dettagliate]"][name*="[descrizione]"]');
                const description = descriptionInput?.value || 'Costo Extra';
                
                const amountInput = item.querySelector('input[name*="[costi_extra_dettagliate]"][name*="[importo]"]');
                const moltiplicaDurataInput = item.querySelector('input[name*="[costi_extra_dettagliate]"][name*="[moltiplica_durata]"]');
                const amount = parseFloat(amountInput?.value || '0');
                const moltiplicaDurata = moltiplicaDurataInput?.value === '1';
                const wasInPreventivo = checkbox.dataset.wasInPreventivo === '1';
                
                // DEBUG: Log per verificare i valori dai campi hidden
                if (typeof console !== 'undefined' && console.log) {
                  console.log('[BTR] Hidden Field Debug:', {
                    description: description,
                    amountFromHidden: amount,
                    moltiplicaDurata: moltiplicaDurata,
                    amountInputValue: amountInput?.value,
                    isNegative: amount < 0
                  });
                }
                
                // CORREZIONE: Mostra tutti i costi extra, sia positivi che negativi (riduzioni)
                if (amount !== 0) {
                  hasExtra = true;
                  totalExtra += amount;
                  
                  // Crea elemento nel riepilogo
                  const summaryItem = document.createElement('div');
                  summaryItem.className = 'btr-summary-item';
                  
                  let statusBadge = '';
                  if (wasInPreventivo) {
                    statusBadge = ' <small style="color: #0097c5; font-weight: 500;">(incluso nel preventivo)</small>';
                  }
                  
                  // Formattazione corretta per valori negativi  
                  const formattedAmount = amount < 0 
                    ? `-€${Math.abs(amount).toLocaleString('it-IT', {minimumFractionDigits: 2})}`
                    : `€${amount.toLocaleString('it-IT', {minimumFractionDigits: 2})}`;
                  
                  // Non aggiungere alcun suffisso per i costi extra
                  summaryItem.innerHTML = `
                    <span class="btr-summary-label">${personName} – ${description}${statusBadge}</span>
                    <span class="btr-summary-value" style="${amount < 0 ? 'color: #d63384;' : ''}">${formattedAmount}</span>
                  `;
                  extraContainer.appendChild(summaryItem);
                }
              });
            });
            
            // Mostra/nascondi placeholder e riga totale costi extra
            const placeholder = extraContainer.querySelector('.btr-summary-placeholder');
            if (placeholder) {
              placeholder.style.display = hasExtra ? 'none' : 'block';
            }
            
            // Gestisci visibilità riga totale costi extra
            const extraTotalRow = document.querySelector('.summary-extra-total');
            if (extraTotalRow) {
              extraTotalRow.style.display = hasExtra ? 'flex' : 'none';
              console.log('[BTR VANILLA DEBUG] Riga totale costi extra:', hasExtra ? 'mostrata' : 'nascosta');
            }
            
            // CORREZIONE 2025-01-19: Chiama toggleBlocks per sincronizzare visibilità card  
            setTimeout(() => {
              if (typeof toggleBlocks === 'function') {
                console.log('[BTR VANILLA DEBUG] Chiamando toggleBlocks da updateExtraCostsSummaryWithNames');
                toggleBlocks();
              }
            }, 10);
          }
          
          function getPersonName(card, index) {
            const nomeInput = card.querySelector('input[name*="[nome]"]');
            const cognomeInput = card.querySelector('input[name*="[cognome]"]');
            const nome = nomeInput?.value?.trim() || '';
            const cognome = cognomeInput?.value?.trim() || '';
            
            if (nome && cognome) {
              return `${nome} ${cognome}`;
            } else if (nome) {
              return nome;
            } else {
              // Fallback ai numeri ordinali
              const ordinali = ['Primo', 'Secondo', 'Terzo', 'Quarto', 'Quinto', 'Sesto', 'Settimo', 'Ottavo', 'Nono', 'Decimo'];
              return ordinali[index] || `Partecipante ${index + 1}`;
            }
          }
          
          // === GESTIONE DINAMICA CULLA NEONATI (SOLO ADULTI) ===
          function updateCullaAvailability() {
            // Trova tutte le checkbox culla (solo adulti le hanno)
            const cullaCheckboxes = [];
            let adultoConCulla = null;
            
            document.querySelectorAll('.btr-person-card').forEach((card, index) => {
              // Trova checkbox culla per questo partecipante (solo adulti ce l'hanno)
              const cullaCheckbox = card.querySelector('input[data-is-culla="1"]');
              if (cullaCheckbox) {
                cullaCheckboxes.push({
                  checkbox: cullaCheckbox,
                  personIndex: index,
                  wasInPreventivo: cullaCheckbox.dataset.wasInPreventivo === '1'
                });
                
                if (cullaCheckbox.checked) {
                  adultoConCulla = index;
                }
              }
            });
            
            // Aggiorna stato delle checkbox culla (logica: solo 1 adulto alla volta)
            cullaCheckboxes.forEach(({checkbox, personIndex, wasInPreventivo}) => {
              const label = checkbox.closest('label');
              let shouldDisable = false;
              let message = '';
              
              // Se un altro adulto ha già la culla e questo non è quello
              if (adultoConCulla !== null && adultoConCulla !== personIndex && !checkbox.checked) {
                shouldDisable = true;
                message = '(Culla già assegnata a un altro adulto)';
              }
              
              // Applica stato
              checkbox.disabled = shouldDisable;
              label.classList.toggle('btr-disabled-option', shouldDisable);
              
              // Aggiorna messaggio
              let messageEl = label.querySelector('.btr-culla-message');
              if (message) {
                if (!messageEl) {
                  messageEl = document.createElement('small');
                  messageEl.className = 'btr-culla-message';
                  messageEl.style.color = '#666';
                  messageEl.style.fontStyle = 'italic';
                  messageEl.style.display = 'block';
                  messageEl.style.marginTop = '4px';
                  messageEl.style.fontSize = '0.85em';
                  label.appendChild(messageEl);
                }
                messageEl.textContent = message;
              } else if (messageEl) {
                messageEl.remove();
              }
            });
          }
          
          // Inizializza la disponibilità culle al caricamento
          document.addEventListener('DOMContentLoaded', () => {
            updateCullaAvailability();
          });

            /* === Ricalcola importo assicurazione per un partecipante ================= */
            function updateInsuranceForPerson(card){
                if (!card) return;
                let base = parseFloat(card.dataset.basePrice || '0');
                if (!base) {
                    // ricava prezzo per persona + supplemento + eventuale notte extra
                    const roomId = card.querySelector('input[name*="[camera]"]').value;
                    if (roomId) {
                        const btn     = document.querySelector('.btr-room-button[data-room-id="'+roomId+'"]');
                        const pp      = parseFloat(btn?.dataset.pp || '0');
                        const supp    = parseFloat(btn?.dataset.supplemento || '0');
                        const nightEl = document.getElementById('btr-summary-extra-night-total');
                        const ppNight = nightEl
                            ? parseFloat((nightEl.dataset.pp || '0').replace(',', '.'))
                            : 0;
                        base = pp + supp + ppNight;
                        card.dataset.basePrice = base;
                    }
                }

                // Somma dei costi-extra spuntati
                const extraTotal = Array.from(
                    card.querySelectorAll('.btr-assicurazione-item input[name*="[costi_extra]"]:checked')
                ).reduce((sum, cb) => {
                    const imp = cb.closest('.btr-assicurazione-item')
                        .querySelector('input[name*="[costi_extra_dettagliate]"][name*="[importo]"]');
                    return sum + (imp ? moneyToFloat(imp.value) : 0);
                }, 0);

                const totaleBase = base + extraTotal;

                // Aggiorna ogni assicurazione della card
                card.querySelectorAll('.btr-assicurazione-item[data-percent]').forEach(item => {
                    // Controlla il tipo di importo
                    const tipoImporto = item.dataset.tipoImporto || 'percentuale';
                    let nuovoImporto;
                    
                    if (tipoImporto === 'fisso') {
                        // Per importi fissi, usa il valore predefinito
                        nuovoImporto = parseFloat(item.dataset.importoFisso || '0');
                        console.log('[BTR DEBUG] Assicurazione importo fisso:', {
                            tipoImporto,
                            importoFisso: item.dataset.importoFisso,
                            nuovoImporto
                        });
                    } else {
                        // Per percentuali, calcola sul totale base
                        const perc = parseFloat(item.dataset.percent || '0');
                        nuovoImporto = +(totaleBase * (perc / 100)).toFixed(2);
                        console.log('[BTR DEBUG] Assicurazione percentuale:', {
                            tipoImporto,
                            percentuale: perc,
                            totaleBase,
                            nuovoImporto
                        });
                    }

                    // hidden input per il backend
                    const inp = item.querySelector('input[name*="[assicurazioni_dettagliate]"][name*="[importo]"]');
                    if (inp) inp.value = nuovoImporto;

                    // prezzo visibile (strong che NON contiene "%")
                    let priceEl = null;
                    item.querySelectorAll('label strong').forEach(el => {
                      if (!el.textContent.includes('%') && !priceEl) {
                        priceEl = el;
                      }
                    });
                    if (priceEl) {
                        priceEl.textContent = btrFormatPriceInline(nuovoImporto);
                    }
                });
            }
        })();
        </script>
        </div>
    <?php endif; ?>


    <style>

        /* Align the insurance price to the right */
        .btr-assicurazione-item label strong:first-of-type {
            margin-left: auto;
        }
        .btr-toggle-details {
            font-size: 0.875rem;
            color: var(--btr-primary);
            text-decoration: underline;
            cursor: pointer;
            display: inline-block;
            margin-top: 0.25rem;
        }
        .btr-details-content {
            font-size: 0.875rem;
            color: var(--btr-gray-700);
            margin-top: 0.25rem;
            display: block;
        }
        .btr-details-content[hidden] {
            display: none;
        }
        .hidden-field {
            display: none;
        }
        
        /* Transizione smooth per mostrare/nascondere campi indirizzo */
        .address-field {
            transition: opacity 0.3s ease, max-height 0.3s ease;
            overflow: hidden;
            opacity: 1;
            max-height: 100px; /* Altezza massima per permettere la transizione */
        }
        
        .address-field.hidden-field {
            opacity: 0;
            max-height: 0;
            margin-top: 0;
            margin-bottom: 0;
            padding-top: 0;
            padding-bottom: 0;
        }

        /* Stili per i campi con errore */
        .btr-field-error {
            border: 2px solid var(--btr-danger) !important;
            background-color: rgba(220, 53, 69, 0.05) !important;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
        }

        /* Animazione per attirare l'attenzione sui campi con errore */
        @keyframes btr-field-error-pulse {
            0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
        }

        .btr-field-error:focus {
            animation: btr-field-error-pulse 1.5s infinite;
        }

        /* Stili per l'errore di assegnazione camera */
        .btr-camera-error {
            border: 2px solid var(--btr-danger) !important;
            border-radius: var(--btr-radius);
            padding: 10px;
            background-color: rgba(220, 53, 69, 0.05) !important;
            position: relative;
        }

        .btr-camera-error::before {
            content: "⚠️ Seleziona una camera";
            display: block;
            color: var(--btr-danger);
            font-weight: bold;
            margin-bottom: 10px;
            font-size: 0.9rem;
        }

        .btr-camera-error .btr-room-button {
            animation: btr-button-pulse 2s infinite;
        }

        @keyframes btr-button-pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        /* Stili per overlay notifiche errori */
        .btr-notification-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 9998;
            backdrop-filter: blur(2px);
            pointer-events: none;
        }
        
        /* Stili per contenitore errori dettagliati */
        .btr-error-container {
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .btr-error-title {
            font-size: 1.2rem;
            color: inherit;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .btr-error-section {
            margin-bottom: 20px;
        }
        
        .btr-error-section h4 {
            font-size: 1rem;
            color: inherit;
            margin-bottom: 10px;
            font-weight: 500;
        }
        
        .btr-error-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .btr-error-list li {
            padding: 8px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.15);
        }
        
        .btr-error-list li:last-child {
            border-bottom: none;
        }
        
        .btr-error-fields {
            font-size: 0.9rem;
            opacity: 0.8;
            font-style: italic;
        }
        
        .btr-error-help {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(0, 0, 0, 0.2);
        }
        
        .btr-error-help p {
            margin: 0;
            color: inherit;
            font-size: 0.9rem;
        }
        
        /* Disabilita smooth scroll CSS del tema su questa pagina */
        html { scroll-behavior: auto !important; }

        /* Design moderno per toast notifications - Born to Ride style */
        .btr-toast-container {
            position: fixed;
            top: 30px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 10000;
            width: 90%;
            max-width: 800px; /* Aumentato per contenuti più lunghi */
            max-height: 80vh; /* Limita altezza massima */
            overflow-y: auto; /* Scroll se necessario */
            display: flex;
            flex-direction: column;
            gap: 15px;
            pointer-events: none;
        }
        
        .btr-toast {
            pointer-events: auto;
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 20px 24px;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15), 
                        0 6px 12px rgba(0, 0, 0, 0.1);
            font-family: system-ui, -apple-system, sans-serif;
            font-size: 15px;
            line-height: 1.5;
            background: #fff;
            opacity: 0;
            transform: translateY(-20px) scale(0.95);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            border-left: 5px solid;
            overflow: hidden;
        }
        
        .btr-toast.visible {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
        
        .btr-toast.disappearing {
            opacity: 0 !important;
            transform: translateY(-20px) scale(0.95) !important;
            transition: all 0.3s ease-out;
        }
        
        /* Stili per tipo con colori Born to Ride */
        .btr-toast.success {
            background-color: #f0f9ff;
            border-left-color: #0097c5;
            color: #0c4a6e;
        }
        
        .btr-toast.info {
            background-color: #f0f9ff;
            border-left-color: #0097c5;
            color: #0c4a6e;
        }
        
        .btr-toast.error {
            background-color: #fef2f2;
            border-left-color: #dc2626;
            color: #7f1d1d;
        }
        
        /* Icone moderne per tipo */
        .btr-toast-icon {
            flex-shrink: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .btr-toast.success .btr-toast-icon::before { 
            content: '✓';
            color: #0097c5;
            font-weight: bold;
        }
        
        .btr-toast.info .btr-toast-icon::before { 
            content: 'ℹ';
            color: #0097c5;
            font-weight: bold;
        }
        
        .btr-toast.error .btr-toast-icon::before { 
            content: '⚠';
            color: #dc2626;
        }
        
        /* Contenuto del messaggio */
        .btr-toast-message {
            flex: 1;
            word-wrap: break-word;
            max-height: 70vh !important;
            overflow-y: auto !important;
            padding-right: 10px !important;
        }
        
        /* Stile scrollbar per notifiche */
        .btr-toast-message::-webkit-scrollbar {
            width: 6px;
        }
        
        .btr-toast-message::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 3px;
        }
        
        .btr-toast-message::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 3px;
        }
        
        .btr-toast-message::-webkit-scrollbar-thumb:hover {
            background: rgba(0, 0, 0, 0.5);
        }
        
        .btr-toast-message h3,
        .btr-toast-message h4 {
            margin: 0 0 10px 0;
            font-size: 16px;
            font-weight: 600;
        }
        
        .btr-toast-message ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .btr-toast-message li {
            margin: 5px 0;
        }
        
        /* Pulsante di chiusura moderno */
        .btr-toast-close {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background-color: rgba(0, 0, 0, 0.05);
            color: inherit;
            border: none;
            font-size: 18px;
            line-height: 1;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            opacity: 0.7;
        }
        
        .btr-toast-close:hover {
            background-color: rgba(0, 0, 0, 0.1);
            opacity: 1;
            transform: scale(1.1);
        }
        
        /* Progress bar per durata automatica */
        .btr-toast-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background-color: currentColor;
            opacity: 0.2;
            transition: width linear;
        }
        
        /* Mobile responsive */
        @media (max-width: 640px) {
            .btr-toast-container {
                width: calc(100% - 30px);
                top: 15px;
            }
            
            .btr-toast {
                padding: 16px 20px;
                font-size: 14px;
            }
        }
        
        /* Animazione highlight per card con errori */
        @keyframes btr-error-highlight {
            0% { background-color: transparent; }
            50% { background-color: rgba(220, 38, 38, 0.1); }
            100% { background-color: transparent; }
        }
        
        .btr-person-card.btr-error-highlight {
            animation: btr-error-highlight 1s ease-out 2;
        }
        
        /* Evidenziazione campo specifico con errore */
        .btr-field-highlight {
            position: relative;
            animation: btr-field-pulse 1s ease-out 3;
        }
        
        @keyframes btr-field-pulse {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(0, 151, 197, 0);
            }
            50% {
                box-shadow: 0 0 0 8px rgba(0, 151, 197, 0.3);
            }
        }
        
        /* Miglioramento visibilità campi con errore */
        .has-error input:focus,
        .has-error select:focus {
            border-color: #dc3545;
            outline: 2px solid rgba(220, 53, 69, 0.25);
            outline-offset: 2px;
        }
        
        /* Stili per la selezione del tipo di letto */
        .btr-bed-type-selector {
            margin-top: 20px;
            padding: 20px;
            background-color: var(--btr-gray-50);
            border-radius: var(--btr-radius);
            border: 1px solid var(--btr-gray-200);
        }
        
        .btr-bed-type-selector h4 {
            margin-bottom: 15px;
            color: var(--btr-gray-700);
            font-size: 1rem;
        }
        
        .btr-bed-type-buttons {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .btr-bed-type-button {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            padding: 15px 25px;
            background: white;
            border: 2px solid var(--btr-gray-300);
            border-radius: var(--btr-radius);
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 140px;
        }
        
        .btr-bed-type-button:hover {
            border-color: var(--btr-primary);
            background-color: var(--btr-primary-light);
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .btr-bed-type-button.selected {
            border-color: var(--btr-primary);
            background-color: var(--btr-primary);
            color: white;
        }
        
        .btr-bed-type-button.selected svg {
            stroke: white;
        }
        
        .btr-bed-type-button.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .btr-bed-type-button svg {
            width: 32px;
            height: 32px;
            stroke: var(--btr-primary);
            transition: stroke 0.3s ease;
        }
        
        .btr-bed-type-button strong {
            font-size: 0.9rem;
            font-weight: 600;
        }
        /* Stili per il pannello riepilogativo - Design 2025 */
        .btr-checkout-summary {
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.02);
            margin: 2.5rem 0;
            position: relative;
            border: 1px solid var(--btr-gray-200);
            border-radius: var(--btr-radius);
            padding: 1.25rem;
            margin-bottom: 1rem;
            background-color: white;
        }


        .btr-summary-header {
            margin-bottom: 2rem;
        }

        .btr-summary-header h3 {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--btr-gray-900);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }

        .btr-summary-subtitle {
            font-size: 1rem;
            color: var(--btr-gray-600);
            margin: 0;
        }

        .btr-summary-grid {
            display: grid;
            grid-template-columns: repeat(1, 1fr);
            gap: 1.5rem;
            margin-bottom: 2.5rem;
        }

        @media (min-width: 768px) {
            .btr-summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 1024px) {
            .btr-summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .btr-summary-card {
            background-color: white;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid var(--btr-gray-100);
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
            margin-bottom: 1rem;
        }

        .btr-summary-card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.02);
            transform: translateY(-2px);
        }

        .btr-summary-card-full {
            grid-column: 1 / -1;
        }
        .btr-summary-card-header {
            padding: 1.25rem;
            background-color: var(--btr-gray-50);
            border-bottom: 1px solid var(--btr-gray-100);
            /* display: flex; */
            /* align-items: center; */
            /* gap: 0.75rem; */
        }

        .btr-summary-card-header svg {
            width: 1.5rem;
            height: 1.5rem;
            color: var(--btr-primary);
        }

        .btr-summary-card-header h4 {
            font-size: 1.125rem;
            font-weight: 700;
            color: var(--btr-gray-800);
            margin: 0;
        }

        .btr-summary-card-body {
            padding: 1.25rem;
        }

        /* Miglioramenti layout riepilogo */
        .btr-summary-card-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .btr-summary-icon {
            width: 2rem;
            height: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--btr-primary-light);
            border-radius: 50%;
        }
        .btr-summary-icon svg {
            width: 1.25rem;
            height: 1.25rem;
            color: var(--btr-primary);
            stroke: var(--btr-primary);
        }
        .btr-summary-grid {
            grid-gap: 2rem;
        }
        .btr-summary-item {
            padding: 1rem 0;
        }

        .btr-summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px dashed var(--btr-gray-100);
        }

        .btr-summary-item:last-child {
            border-bottom: none;
        }

        .btr-summary-label {
            font-size: 0.9375rem;
            color: var(--btr-gray-700);
        }

        .btr-summary-value {
            font-size: 0.9375rem;
            font-weight: 600;
            color: var(--btr-gray-900);
        }

        .btr-summary-total {
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid var(--btr-gray-200);
        }

        .btr-summary-card-total {
            background: linear-gradient(145deg, var(--btr-primary-light) 0%, white 100%);
            border: 1px solid rgba(0, 151, 197, 0.15);
            grid-column: 1 / -1;
        }

        .btr-summary-card-total .btr-summary-card-header {
            background-color: rgba(0, 151, 197, 0.08);
            border-color: rgba(0, 151, 197, 0.1);
        }

        .btr-summary-grand-total {
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 2px solid rgba(0, 151, 197, 0.2);
            background-color: rgba(0, 151, 197, 0.05);
            border-radius: 8px;
            padding: 0.75rem;
            margin-bottom: 0;
        }

        .btr-summary-grand-total .btr-summary-label {
            font-weight: 700;
            color: var(--btr-primary-dark);
        }

        .btr-summary-grand-total .btr-summary-value {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--btr-primary-dark);
        }

        .btr-summary-placeholder {
            color: var(--btr-gray-500);
            font-style: italic;
        }

        .btr-summary-notes {
            display: grid;
            grid-template-columns: repeat(1, 1fr);
            gap: 1rem;
        }

        /* Stili per il dropdown della provincia */
        .btr-custom-select {
            position: relative;
            width: 100%;
        }

        .btr-province-display {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--btr-gray-300, #d1d5db);
            border-radius: var(--btr-radius, 4px);
            background-color: white;
            cursor: pointer;
        }

        .btr-select-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            width: 100%;
            max-height: 300px;
            background-color: white;
            border: 1px solid var(--btr-gray-300, #d1d5db);
            border-radius: var(--btr-radius, 4px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            z-index: 1000;
        }

        .btr-select-header {
            padding: 8px;
            border-bottom: 1px solid var(--btr-gray-200, #e5e7eb);
            position: relative;
        }

        .btr-province-search {
            width: 100%;
            padding: 8px 30px 8px 8px;
            border: 1px solid var(--btr-gray-300, #d1d5db);
            border-radius: var(--btr-radius, 4px);
        }

        .btr-search-icon {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
        }

        .btr-select-options-scroll {
            max-height: 250px;
            overflow-y: auto;
        }

        .btr-select-option {
            padding: 8px 12px;
            cursor: pointer;
        }

        .btr-select-option:hover {
            background-color: var(--btr-gray-100, #f3f4f6);
        }
        .btr-room-icon {
            display: inline-block;
            vertical-align: middle;
            width: 1.5rem;
            height: 1.5rem;
            margin-right: 0.5rem;
        }
        .btr-room-icon svg {
            width: 100%;
            height: auto;
        }
        
        /* Stili per opzioni disabilitate (culla neonati) */
        .btr-disabled-option {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .btr-disabled-option input[disabled] {
            cursor: not-allowed;
        }
        
        .btr-culla-message {
            display: block;
            margin-top: 4px;
            font-size: 0.85em;
        }
        
        .btr-infant-indicator {
            background-color: #e8f4fd;
            border-left: 4px solid #2196f3;
            padding: 8px 12px;
            margin-top: 10px;
            border-radius: 4px;
            font-size: 0.9em;
            color: #1976d2;
        }
    </style>

    <?php if (empty($remaining_time)): ?>
        <input type="hidden" name="action" value="btr_convert_to_checkout">
        <input type="hidden" name="preventivo_id" value="<?php echo esc_attr($preventivo_id); ?>">
        <?php wp_nonce_field('btr_convert_to_checkout_nonce', 'btr_convert_nonce'); ?>
        <?php
        // —— Dati "notte extra" (se presenti) —— //
        $extra_night       = get_post_meta( $preventivo_id, '_extra_night', true );
        $extra_night_pp    = get_post_meta( $preventivo_id, '_extra_night_pp', true );
        //$extra_night_total = get_post_meta( $preventivo_id, '_extra_night_total', true );
        ?>
        <input type="hidden" name="extra_night"       value="<?php echo esc_attr( $extra_night ); ?>">
        <input type="hidden" name="extra_night_pp"    value="<?php echo esc_attr( $extra_night_pp ); ?>">
        <input type="hidden" name="extra_night_total" value="<?php echo esc_attr( $extra_night_total ); ?>">
        <button type="submit" class="nectar-button medium regular-tilt accent-color tilt regular-button instance-3 ld-ext-right instance-0 w-100 mt-5">
            <?php esc_html_e('Continua', 'born-to-ride-booking'); ?>
        </button>
    <?php else: ?>
        <button type="submit" class="button button-primary">
            <?php esc_html_e('Salva e Completa', 'born-to-ride-booking'); ?>
        </button>
    <?php endif; ?>

</form>

<div id="btr-anagrafici-response" style="margin-top:20px;"></div>

<script>
    (function ($) {
        'use strict';

        // Definizione della funzione showNotification per il frontend
        window.showNotification = function(message, type = 'info', duration = 5000, dismissable = false) {
            // Crea container se non esiste
            if (!$('.btr-toast-container').length) {
                $('body').append('<div class="btr-toast-container"></div>');
            }
            
            // Calcola durata dinamica basata sulla lunghezza del messaggio
            if (duration === 'auto') {
                const words = message.replace(/<[^>]*>/g, '').split(/\s+/).length;
                duration = Math.max(5000, Math.min(15000, words * 200));
            }
            
            // Crea il toast con icona
            const $toast = $(`
                <div class="btr-toast ${type}">
                    <div class="btr-toast-icon"></div>
                    <div class="btr-toast-message">${message}</div>
                    ${dismissable ? '<button class="btr-toast-close">&times;</button>' : ''}
                    ${duration > 0 ? '<div class="btr-toast-progress"></div>' : ''}
                </div>
            `);
            
            // Aggiungi al container
            $('.btr-toast-container').append($toast);
            
            // Anima l'entrata
            setTimeout(() => $toast.addClass('visible'), 10);
            
            // Progress bar animation
            if (duration > 0) {
                const $progress = $toast.find('.btr-toast-progress');
                $progress.css('transition', `width ${duration}ms linear`);
                setTimeout(() => $progress.css('width', '100%'), 50);
                
                // Auto-rimuovi
                setTimeout(() => {
                    $toast.removeClass('visible').addClass('disappearing');
                    setTimeout(() => $toast.remove(), 300);
                }, duration);
            }
            
            // Gestione chiusura manuale
            $toast.find('.btr-toast-close').on('click', function() {
                $toast.removeClass('visible').addClass('disappearing');
                setTimeout(() => $toast.remove(), 300);
                // Rimuovi overlay se esiste
                $('.btr-notification-overlay').remove();
            });
            
            return $toast;
        };

        // Rimuovo l'override dannoso - la prima definizione è sufficiente

        // Capacità standard per tipologia nel caso i dati-capacita siano errati
        const defaultCapByType = {
            'Singola': 1,
            'Doppia': 2,
            'Tripla': 3,
            'Quadrupla': 4,
            'Quintupla': 5,
            'Condivisa': 6
        };

        // Aggiorna data attributes per tracciare stato selezione senza disabilitare input
        function updateSelectionState($item){
            const checked = $item.find('input[type="checkbox"]').prop('checked');
            $item.find('input[type="hidden"]').attr('data-checkbox-selected', checked ? '1' : '0');
            
            // FIX BUG COSTI EXTRA: Disabilita i campi hidden quando la checkbox non è selezionata
            // Questo impedisce che vengano inviati dati non selezionati al server
            if (checked) {
                $item.find('input[type="hidden"]').prop('disabled', false);
            } else {
                $item.find('input[type="hidden"]').prop('disabled', true);
            }
        }

        // Inizializzazione allo start
        $(document).ready(function(){
            $('.btr-assicurazione-item').each(function(){ updateSelectionState($(this)); });
            
            // AGGIUNTO: Forza aggiornamento riepilogo al caricamento
            setTimeout(() => {
                if (typeof updateSummaryWithParticipantNames === 'function') {
                    updateSummaryWithParticipantNames();
                }
                if (typeof recalcGrandTotal === 'function') {
                    recalcGrandTotal();
                }
                if (typeof updateSummaryInsurance === 'function') {
                    updateSummaryInsurance();
                }
                console.log('[BTR] 📊 Riepilogo aggiornato al caricamento pagina');
            }, 500);
        });

        // Toggle runtime
        $(document).on('change','.btr-assicurazione-item input[type="checkbox"]', function(){
            updateSelectionState($(this).closest('.btr-assicurazione-item'));
        });


        const roomAllocations = {}; // Traccia i posti occupati per ogni camera
        const personRoomAssignments = {}; // Traccia quale persona è assegnata a quale camera
        const roomCapacities = {}; // Mappa capacità per ogni camera
        
        // Inizializza il tracking dei tipi letto salvati
        $(document).ready(function() {
            $('.btr-person-card').each(function() {
                const card = $(this);
                const personIndex = card.data('person-index');
                const roomId = $(`#btr_camera_${personIndex}`).val();
                const roomType = $(`#btr_camera_tipo_${personIndex}`).val();
                const bedType = $(`#btr_tipo_letto_${personIndex}`).val();
                
                // Se ha una camera assegnata
                if (roomId && roomId !== 'neonato-no-room') {
                    // Se è una camera doppia e ha un tipo letto salvato
                    if (bedType && (roomType === 'Doppia' || roomType === 'Doppia/Matrimoniale')) {
                        roomBedTypes[roomId] = bedType;
                        
                        // Mostra il selettore con il tipo selezionato
                        const container = $(`#btr_bed_type_container_${personIndex}`);
                        container.show();
                        container.find(`.btr-bed-type-button[data-bed-type="${bedType}"]`).addClass('selected');
                    }
                }
            });
        });
        // const roomLabels = {};

        function initializeRoomAssignments() {
            // Mappa capacità per ogni camera (una sola volta)
            $('.btr-room-button').each(function () {
                const $btn = $(this);
                const rId = $btn.data('room-id');
                let cap = parseInt($btn.data('capacita'), 10);
                if (!cap || cap < 1) {
                    // fallback se attributo mancante/errato
                    cap = defaultCapByType[$btn.data('room-type')] || 1;
                }
                if (!roomCapacities[rId]) {
                    roomCapacities[rId] = cap;
                }
            });
            $('.btr-person-card').each(function () {
                const personCard = $(this);
                const personIndex = personCard.data('person-index');
                const assignedRoom = $(`#btr_camera_${personIndex}`).val();

                if (assignedRoom) {
                    if (!roomAllocations[assignedRoom]) {
                        roomAllocations[assignedRoom] = 0;
                    }
                    roomAllocations[assignedRoom]++;
                    personRoomAssignments[personIndex] = assignedRoom;
                }
            });

            updateRoomButtons();
        }

        // Gestione dinamica RC Skipass quando si seleziona/deseleziona "no skipass"
        $(document).on('change', 'input[name^="anagrafici"][name*="[costi_extra]"]', function() {
            var $checkbox = $(this);
            var checkboxName = $checkbox.attr('name');
            
            // Verifica se è il checkbox "no skipass" usando lo slug univoco
            if (checkboxName && checkboxName.includes('[no-skipass]')) {
                var isNoSkipassSelected = $checkbox.is(':checked');
                var $currentPersonCard = $checkbox.closest('.btr-person-card');
                var currentPersonIndex = $currentPersonCard.data('person-index');
                
                console.log('[BTR DEBUG] No skipass changed:', isNoSkipassSelected, 'per partecipante', currentPersonIndex);
                
                // Gestisci la visibilità di RC Skipass SOLO per il partecipante corrente
                var $rcSkipassContainer = $currentPersonCard.find('input[data-rc-skipass="true"]').closest('.btr-assicurazione-item');
                var $rcSkipassCheckbox = $currentPersonCard.find('input[data-rc-skipass="true"]');
                
                if ($rcSkipassContainer.length > 0) {
                    if (isNoSkipassSelected) {
                        // Nascondi RC Skipass se "no skipass" è selezionato
                        $rcSkipassContainer.hide();
                        $rcSkipassCheckbox.prop('checked', false);
                        console.log('[BTR DEBUG] RC Skipass nascosta per partecipante', currentPersonIndex);
                    } else {
                        // Mostra RC Skipass e pre-selezionala se "no skipass" non è selezionato
                        $rcSkipassContainer.show();
                        $rcSkipassCheckbox.prop('checked', true);
                        console.log('[BTR DEBUG] RC Skipass mostrata e pre-selezionata per partecipante', currentPersonIndex);
                    }
                }
                
                // Controlla se almeno un partecipante ha assicurazioni nascoste per no skipass
                var hasAnyHiddenInsurance = false;
                $('.btr-person-card').each(function() {
                    var $card = $(this);
                    var hasNoSkipass = $card.find('input[name*="[costi_extra][no-skipass]"]:checked').length > 0;
                    if (hasNoSkipass) {
                        hasAnyHiddenInsurance = true;
                        return false; // break
                    }
                });
                
                // Aggiorna i riepiloghi
                window.hasHiddenInsuranceDueToNoSkipass = hasAnyHiddenInsurance;
                updateSummaryInsurance();
                updateExtraCostsSummaryWithNames();
            }
        });
        
        // Funzione per gestire la visibilità dei campi in base alle assicurazioni
        function handleInsuranceFieldVisibility(personIndex) {
            console.log('[BTR DEBUG] handleInsuranceFieldVisibility chiamata per partecipante', personIndex);
            
            var personCard = $('.btr-person-card[data-person-index="' + personIndex + '"]');
            if (!personCard.length) {
                console.error('[BTR DEBUG] Person card non trovata per indice', personIndex);
                return;
            }
            
            var codiceFiscaleField = personCard.find('.codice-fiscale-field');
            var addressFields = personCard.find('.address-field');
            
            // Debug: verifica che i campi siano trovati
            console.log('[BTR DEBUG] Campi trovati:', {
                codiceFiscale: codiceFiscaleField.length,
                address: addressFields.length
            });
            
            // Check if any insurance is selected for this person
            var hasInsurance = false;
            var hasInsuranceRequiringFiscalCode = false;
            
            personCard.find('input[name^="anagrafici"][name*="[assicurazioni]"]:checked').each(function() {
                hasInsurance = true;
                
                // RC Skipass non richiede codice fiscale né indirizzo
                var isRcSkipass = $(this).data('no-fiscal-code') === true || 
                                 $(this).data('rc-skipass') === true;
                
                console.log('[BTR DEBUG] Assicurazione selezionata:', {
                    value: $(this).val(),
                    isRcSkipass: isRcSkipass,
                    dataNoFiscalCode: $(this).data('no-fiscal-code'),
                    dataRcSkipass: $(this).data('rc-skipass')
                });
                
                if (!isRcSkipass) {
                    hasInsuranceRequiringFiscalCode = true;
                }
            });
            
            console.log('[BTR DEBUG] Stato assicurazioni per partecipante', personIndex, {
                hasInsurance: hasInsurance,
                hasInsuranceRequiringFiscalCode: hasInsuranceRequiringFiscalCode
            });
            
            // Gestione visibilità per il primo partecipante (sempre visibili)
            if (personIndex === 0) {
                codiceFiscaleField.removeClass('hidden-field');
                addressFields.removeClass('hidden-field');
                console.log('[BTR DEBUG] Partecipante 0 - tutti i campi sempre visibili');
                return;
            }
            
            // Per gli altri partecipanti, mostra/nascondi in base alle assicurazioni
            if (hasInsuranceRequiringFiscalCode) {
                // Mostra i campi
                codiceFiscaleField.removeClass('hidden-field');
                addressFields.removeClass('hidden-field');
                console.log('[BTR DEBUG] Mostrando campi per partecipante', personIndex);
            } else {
                // Nascondi i campi
                codiceFiscaleField.addClass('hidden-field');
                addressFields.addClass('hidden-field');
                console.log('[BTR DEBUG] Nascondendo campi per partecipante', personIndex);
            }
        }
        
        // Event handler per il cambio di assicurazione
        $(document).on('change', 'input[name^="anagrafici"][name*="[assicurazioni]"]', function() {
            var $checkbox = $(this);
            var personIndex = $checkbox.closest('.btr-person-card').data('person-index');
            
            console.log('[BTR DEBUG] Assicurazione cambiata per partecipante', personIndex);
            
            // Aggiorna anche il riepilogo e il totale generale
            if (typeof recalcGrandTotal === 'function') {
                recalcGrandTotal();
            }
            
            // Gestione alert RC Skipass
            if ($checkbox.data('rc-skipass') === true) {
                var $alert = $checkbox.closest('.btr-assicurazione-item').next('.btr-rc-skipass-alert');
                if (!$checkbox.is(':checked')) {
                    $alert.slideDown(300);
                    console.log('[BTR DEBUG] RC Skipass deselezionata - mostrando alert');
                } else {
                    $alert.slideUp(300);
                    console.log('[BTR DEBUG] RC Skipass selezionata - nascondendo alert');
                }
            }
            
            // Gestisci la visibilità dei campi
            handleInsuranceFieldVisibility(personIndex);
            
            // Rilancia la validazione dopo aver cambiato la visibilità dei campi
            if (typeof validateAnagrafici === 'function') {
                setTimeout(function() {
                    validateAnagrafici();
                }, 100);
            }
            
            // Rilancia anche validateAllData per aggiornare i messaggi di errore dettagliati
            if (typeof validateAllData === 'function') {
                setTimeout(function() {
                    validateAllData();
                }, 150);
            }
        });
        
        // Inizializza la visibilità dei campi al caricamento della pagina
        $(document).ready(function() {
            console.log('[BTR DEBUG] Inizializzazione visibilità campi...');
            $('.btr-person-card').each(function() {
                var personIndex = $(this).data('person-index');
                handleInsuranceFieldVisibility(personIndex);
            });
            
            // Rilancia la validazione dopo l'inizializzazione
            if (typeof validateAnagrafici === 'function') {
                setTimeout(function() {
                    validateAnagrafici();
                }, 200);
            }
            
            // Rilancia anche validateAllData per l'inizializzazione
            if (typeof validateAllData === 'function') {
                setTimeout(function() {
                    validateAllData();
                }, 250);
            }
        });

        // Oggetto per tracciare il tipo di letto per ogni camera
        const roomBedTypes = {};
        
        $(document).off('click.btrRoom').on('click.btrRoom', '.btr-room-button', function (e) {
            e.preventDefault(); e.stopPropagation();
            const button = $(this);
            const roomId = button.data('room-id');
            const roomType = button.data('room-type');
            let capacity = roomCapacities[roomId];
            if (!capacity) {
                capacity = parseInt(button.data('capacita'), 10) || defaultCapByType[roomType] || 1;
            }
            const personCard = button.closest('.btr-person-card');
            const personIndex = personCard.data('person-index');
            const inputRoomId = `#btr_camera_${personIndex}`;
            const inputRoomType = `#btr_camera_tipo_${personIndex}`;
            const currentAssignedRoom = $(inputRoomId).val();
            const bedTypeContainer = $(`#btr_bed_type_container_${personIndex}`);
            const bedTypeInput = $(`#btr_tipo_letto_${personIndex}`);

            // Toggle off if clicking the same assigned room
            if (currentAssignedRoom === roomId) {
                roomAllocations[roomId]--;
                delete personRoomAssignments[personIndex];
                $(inputRoomId).val('');
                $(inputRoomType).val('');
                // Nascondi selettore tipo letto
                bedTypeContainer.hide();
                bedTypeInput.val('');
                // Se nessuno usa più questa camera, rimuovi il tipo letto salvato
                const stillUsed = Object.values(personRoomAssignments).some(r => r === roomId);
                if (!stillUsed) {
                    delete roomBedTypes[roomId];
                }
                // Aggiorna selezione UI immediata
                //personCard.find('.btr-room-button').removeClass('selected');
                updateRoomButtons();
                return;
            }

            // Free any previous assignment
            if (currentAssignedRoom) {
                roomAllocations[currentAssignedRoom]--;
                delete personRoomAssignments[personIndex];
                // Nascondi selettore tipo letto precedente
                bedTypeContainer.hide();
                bedTypeInput.val('');
                // Se nessuno usa più la camera precedente, rimuovi il tipo letto
                const stillUsed = Object.values(personRoomAssignments).some(r => r === currentAssignedRoom);
                if (!stillUsed) {
                    delete roomBedTypes[currentAssignedRoom];
                }
            }

            // Capacity check: do not exceed
            if ((roomAllocations[roomId] || 0) >= capacity) {
                showNotification('Capacità massima raggiunta per questa camera.', 'error', 0, true);
                // Restore previous assignment if existed
                if (currentAssignedRoom) {
                    roomAllocations[currentAssignedRoom] = (roomAllocations[currentAssignedRoom] || 0) + 1;
                    personRoomAssignments[personIndex] = currentAssignedRoom;
                }
                updateRoomButtons();
                return;
            }

            // Assign the new room
            $(inputRoomId).val(roomId);
            $(inputRoomType).val(roomType);
            roomAllocations[roomId] = (roomAllocations[roomId] || 0) + 1;
            personRoomAssignments[personIndex] = roomId;

            // Clear camera required errors on successful selection
            const $cameraSection = personCard.find('.asign-camera');
            $cameraSection.removeClass('has-error btr-camera-error');
            $cameraSection.find('.btr-field-error').remove();
            updateCardErrorStatus(personCard);
            // Se non ci sono più errori, rimuovi overlay che blocca i click
            if ($('.btr-person-card .has-error, .btr-camera-error').length === 0) {
                $('.btr-notification-overlay').remove();
            }

            // Evidenzia immediatamente la camera selezionata per questa scheda
            personCard.find('.btr-room-button').removeClass('selected');
            button.addClass('selected');
            
            // Gestione tipo letto per camere doppie
            if (roomType === 'Doppia' || roomType === 'Doppia/Matrimoniale') {
                // Se la camera ha già un tipo letto assegnato
                if (roomBedTypes[roomId]) {
                    // Imposta automaticamente lo stesso tipo letto
                    bedTypeInput.val(roomBedTypes[roomId]);
                    bedTypeContainer.show();
                    // Disabilita i bottoni e mostra solo il tipo selezionato
                    bedTypeContainer.find('.btr-bed-type-button').each(function() {
                        const btn = $(this);
                        if (btn.data('bed-type') === roomBedTypes[roomId]) {
                            btn.addClass('selected disabled');
                        } else {
                            btn.hide();
                        }
                    });
                    // Aggiungi messaggio informativo
                    if (!bedTypeContainer.find('.bed-type-info').length) {
                        bedTypeContainer.find('.btr-bed-type-buttons').after(
                            '<p class="bed-type-info" style="margin-top: 10px; color: #666; font-size: 0.9rem;">' +
                            'Tipo letto già selezionato per questa camera da un altro partecipante.' +
                            '</p>'
                        );
                    }
                } else {
                    // Prima persona che seleziona questa camera doppia
                    bedTypeContainer.show();
                    bedTypeContainer.find('.btr-bed-type-button').removeClass('selected disabled').show();
                    bedTypeContainer.find('.bed-type-info').remove();
                }
            } else {
                // Non è una camera doppia, nascondi il selettore
                bedTypeContainer.hide();
                bedTypeInput.val('');
            }
            
            updateRoomButtons();
        });
        
        // Gestione click sui bottoni tipo letto
        $(document).on('click', '.btr-bed-type-button', function() {
            const button = $(this);
            if (button.hasClass('disabled')) return;
            
            const bedType = button.data('bed-type');
            const personIndex = button.data('index');
            const roomId = $(`#btr_camera_${personIndex}`).val();
            
            // Aggiorna UI
            button.siblings().removeClass('selected');
            button.addClass('selected');
            
            // Salva il valore
            $(`#btr_tipo_letto_${personIndex}`).val(bedType);
            
            // Salva il tipo letto per questa camera
            if (roomId) {
                roomBedTypes[roomId] = bedType;
                
                // Aggiorna tutti gli altri partecipanti che hanno la stessa camera
                $('.btr-person-card').each(function() {
                    const card = $(this);
                    const idx = card.data('person-index');
                    if (idx !== personIndex && $(`#btr_camera_${idx}`).val() === roomId) {
                        // Aggiorna il tipo letto per questo partecipante
                        $(`#btr_tipo_letto_${idx}`).val(bedType);
                        const container = $(`#btr_bed_type_container_${idx}`);
                        container.find('.btr-bed-type-button').each(function() {
                            const btn = $(this);
                            if (btn.data('bed-type') === bedType) {
                                btn.addClass('selected disabled');
                            } else {
                                btn.hide();
                            }
                        });
                        if (!container.find('.bed-type-info').length) {
                            container.find('.btr-bed-type-buttons').after(
                                '<p class="bed-type-info" style="margin-top: 10px; color: #666; font-size: 0.9rem;">' +
                                'Tipo letto già selezionato per questa camera da un altro partecipante.' +
                                '</p>'
                            );
                        }
                    }
                });
            }
        });

        function updateRoomButtons() {
            $('.btr-room-button').each(function () {
                const button = $(this);
                const iconHtml = button.find('.btr-room-icon').prop('outerHTML') || '';
                const roomId = button.data('room-id');
                const roomType = button.data('room-type');
                const capacity = roomCapacities[roomId] || defaultCapByType[roomType] || parseInt(button.data('capacita'), 10) || 1;
                const allocated = roomAllocations[roomId] || 0;

                // Estrae l'etichetta già presente nel bottone (generata da PHP)
                const roomLabel = button.find('strong').text().trim();

                if (allocated >= capacity) {
                    button.html(`${iconHtml}<strong>${roomLabel}</strong> <span>completa</span>`);
                    button.addClass('disabled');
                } else {
                    button.html(`${iconHtml}<strong>${roomLabel}</strong> <span>${capacity - allocated} posti disponibili</span>`);
                    button.removeClass('disabled');
                }
            });

            // Applica classe selected in base al valore hidden per ogni scheda
            $('.btr-person-card').each(function() {
                const $card = $(this);
                const idx = $card.data('person-index');
                const selectedRoom = ($(`#btr_camera_${idx}`).val() || '').toString().trim();
                const $buttons = $card.find('.btr-room-button');
                $buttons.removeClass('selected');
                if (selectedRoom && selectedRoom !== '0') {
                    $buttons.filter(function(){ return ($(this).data('room-id') || '').toString().trim() === selectedRoom; }).addClass('selected');
                }
            });
        }


        

        initializeRoomAssignments();

        if ($('#btr-countdown').length) {
            let remainingTime = parseInt($('#btr-countdown').data('remaining-time'), 10);
            const timerEl = $('#btr-countdown-timer');
            const btrhours = $('#btr-hours');
            const btrminutes = $('#btr-minutes');
            const btrseconds = $('#btr-seconds');
            setInterval(function () {
                if (remainingTime > 0) {
                    const hours = Math.floor(remainingTime / 3600);
                    const minutes = Math.floor((remainingTime % 3600) / 60);
                    const seconds = remainingTime % 60;
                    timerEl.text(`${hours}h ${minutes}m ${seconds}s`);
                    btrhours.text(`${hours}`);
                    btrminutes.text(`${minutes}`);
                    btrseconds.text(`${seconds}`);
                    remainingTime--;
                } else {
                    timerEl.text('<?php echo esc_js(__('Tempo scaduto!', 'born-to-ride-booking')); ?>');
                }
            }, 1000);
        }


        // Funzione per validare ogni scheda .btr-person-card
        function validateAnagrafici() {
            let missingCount = 0; // Contatore delle persone mancanti
            const totalPersons = $('.btr-person-card').length; // Totale persone nel form

            $('.btr-person-card').each(function () {
                const personCard = $(this);
                const personIndex = personCard.data('person-index');
                let isComplete = true;

                // Controlla solo input rilevanti (no hidden), escludendo campi non necessari e campi nascosti
                personCard.find('input[type="text"], input[type="email"], input[type="date"], input[type="tel"]')
                    .not('.btr-province-search')
                    .not('input[name*="[nazione_residenza]"]')
                    .each(function () {
                        const $input = $(this);
                        const $fieldGroup = $input.closest('.btr-field-group');
                        
                        // Salta gli input in campi nascosti (address-field e codice-fiscale-field)
                        if ($fieldGroup.hasClass('hidden-field')) {
                            console.log('[BTR DEBUG] Saltando validazione per campo nascosto:', $input.attr('name'));
                            return true; // continue
                        }
                        
                        if (!$input.val().trim()) {
                            console.log('[BTR DEBUG] Campo obbligatorio vuoto:', $input.attr('name'));
                            isComplete = false;
                        }
                    });

                // Valida il campo nazione di residenza solo se la provincia selezionata è ESTERO
                var $provField = personCard.find('.provincia-residenza-field');
                var provincia = (($provField.attr('data-value')) || $provField.val() || '').toString().trim().toUpperCase();
                if (provincia === 'ESTERO') {
                    var nazioneInput = personCard.find('input[name*="[nazione_residenza]"]');
                    if (!nazioneInput.val().trim()) {
                        isComplete = false;
                        nazioneInput.addClass('btr-field-error');
                    }
                    else { nazioneInput.removeClass('btr-field-error'); }
                }

                // Camera obbligatoria: verifica hidden [camera]
                const $roomHidden = personCard.find('input[type="hidden"][name*="[camera]"]');
                const $cameraSection = personCard.find('.asign-camera');
                const roomHiddenVal = ($roomHidden.val() || '').toString().trim();
                if (!roomHiddenVal || roomHiddenVal === '0') {
                    isComplete = false;
                    $cameraSection.addClass('has-error');
                } else {
                    $cameraSection.removeClass('has-error btr-camera-error');
                    $cameraSection.find('.btr-field-error').remove();
                }

                // Aggiunge o rimuove la classe btr-missing
                if (!isComplete) {
                    personCard.addClass('btr-missing');
                    missingCount++;
                    console.log('[BTR DEBUG] Partecipante', personIndex, 'incompleto');
                } else {
                    personCard.removeClass('btr-missing');
                    console.log('[BTR DEBUG] Partecipante', personIndex, 'completo');
                }
            });

            // Aggiorna il contatore totale delle schede incomplete e il totale delle persone
            $('.mancanti').text(missingCount);
            $('.totale-persone').text(totalPersons);
            console.log('[BTR DEBUG] Validazione completata - partecipanti mancanti:', missingCount);
        }

        // Esegui validazione all'apertura della pagina (sempre, anche senza countdown)
        $(document).ready(function () {
            validateAnagrafici();
        });

        // Aggiungi evento per validare ad ogni input compilato (sempre)
        $(document).on('input change', '.btr-person-card input, .btr-person-card select', function () {
            validateAnagrafici();
        });

        // Gestione del campo nazione di residenza quando provincia è "ESTERO"
        $(document).on('input change', '.provincia-residenza-field', function() {
            const index = $(this).data('index');
            const value = (($(this).val()) || '').toString().trim().toUpperCase();
            const dataValue = (($(this).attr('data-value')) || '').toString().trim().toUpperCase();
            const $nazioneContainer = $('#nazione_residenza_container_' + index);

            if (value === 'ESTERO' || dataValue === 'ESTERO') {
                $nazioneContainer.show();
            } else {
                $nazioneContainer.hide();
            }

            // Pulisci lo stato di errore quando la provincia è selezionata
            const $fg = $(this).closest('.btr-field-group');
            if (value || dataValue) {
                $(this).removeClass('btr-field-error');
                $fg.removeClass('has-error');
                $(this).siblings('.btr-field-error').remove();
                updateCardErrorStatus($(this).closest('.btr-person-card'));
            }
        });

        // Gestione del dropdown per la provincia di residenza
        $(document).ready(function() {
            // Apri il dropdown quando si clicca sull'input
            $(document).on('click', '.btr-province-display', function(e) {
                e.stopPropagation();
                const $dropdown = $(this).siblings('.btr-select-dropdown');
                $('.btr-select-dropdown').not($dropdown).hide();
                $dropdown.toggle();
            });

            // Chiudi il dropdown quando si clicca fuori
            $(document).on('click', function() {
                $('.btr-select-dropdown').hide();
            });

            // Impedisci la chiusura quando si clicca all'interno del dropdown
            $(document).on('click', '.btr-select-dropdown', function(e) {
                e.stopPropagation();
            });

            // Filtra le opzioni quando si digita nella casella di ricerca
            $(document).on('input', '.btr-province-search', function() {
                const searchText = $(this).val().toLowerCase();
                const $options = $(this).closest('.btr-select-dropdown').find('.btr-select-option');

                $options.each(function() {
                    const optionText = $(this).text().toLowerCase();
                    if (optionText.includes(searchText)) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });

            // Seleziona una provincia quando si clicca su un'opzione
            $(document).on('click', '.btr-select-option', function() {
                const value = $(this).data('value');
                const text = $(this).text();
                const $input = $(this).closest('.btr-custom-select').find('.btr-province-display');

                $input.val(text);
                $input.attr('data-value', value);
                $input.trigger('change'); // Attiva l'evento change per mostrare/nascondere il campo nazione estera

                // Chiudi il dropdown
                $(this).closest('.btr-select-dropdown').hide();
            });

            // Pulisci errori su change di select e date
            $(document).on('input change', '.btr-person-card select, .btr-person-card input[type="date"]', function() {
                const $field = $(this);
                const $fg = $field.closest('.btr-field-group');
                const val = ($field.val() || '').toString().trim();
                if (val) {
                    $field.removeClass('btr-field-error');
                    $fg.removeClass('has-error');
                    $fg.find('.btr-field-error').remove();
                    updateCardErrorStatus($field.closest('.btr-person-card'));
                    if ($('.btr-person-card .has-error, .btr-camera-error').length === 0) {
                        $('.btr-notification-overlay').remove();
                    }
                }
            });

            // Data di nascita: pulizia errori anche da eventi dei datepicker
            $(document).on('change input blur changeDate apply.daterangepicker', 'input[name$="[data_nascita]"]', function() {
                const $field = $(this);
                const $fg = $field.closest('.btr-field-group');
                const val = ($field.val() || '').toString().trim();
                if (val) {
                    $field.removeClass('btr-field-error');
                    $fg.removeClass('has-error');
                    $fg.find('.btr-field-error').remove();
                    updateCardErrorStatus($field.closest('.btr-person-card'));
                }
            });
        });

        // ------------------- PERSON SUMMARY TOGGLE -------------------
        /*
        function refreshPersonCard($card) {
            const allInputs = $card.find('input[type="text"], input[type="email"], input[type="date"], input[type="tel"], input[type="hidden"][name*="[camera]"]');
            let complete = true;
            allInputs.each(function () {
                if (!$(this).val().trim()) {
                    complete = false;
                    return false;
                }
            });

            if (complete) {
                // Popola il riepilogo
                $card.find('.summary-nome').text($card.find('input[name$="[nome]"]').val() + ' ' + $card.find('input[name$="[cognome]"]').val());
                $card.find('.summary-email').text($card.find('input[name$="[email]"]').val());
                $card.find('.summary-telefono').text($card.find('input[name$="[telefono]"]').val());
                $card.find('.summary-camera').text($card.find('input[name$="[camera_tipo]"]').val());

                // Nasconde campi editabili, mostra riepilogo
                $card.find('.btr-grid, .asign-camera, fieldset.btr-assicurazioni').hide();
                $card.find('.btr-person-summary').show();
            } else {
                // Mostra campi editabili
                $card.find('.btr-grid, .asign-camera, fieldset.btr-assicurazioni').show();
                $card.find('.btr-person-summary').hide();
            }
        }

        // Inizializza su tutti i partecipanti
        $('.btr-person-card').each(function () {
            refreshPersonCard($(this));
        });

        // Toggle riepilogo / modifica
        $(document).on('click', '.btr-edit-person', function () {
            const $card = $(this).closest('.btr-person-card');
            $card.find('.btr-grid, .asign-camera, fieldset.btr-assicurazioni').show();
            $card.find('.btr-person-summary').hide();
            $('html, body').animate({ scrollTop: $card.offset().top - 80 }, 400);
        });

        // Rinforza il refresh quando l'utente compila o cambia dati
        $(document).on('input change', '.btr-person-card input', function () {
            const $card = $(this).closest('.btr-person-card');
            refreshPersonCard($card);
        });

        // ----------------- END PERSON SUMMARY TOGGLE -----------------

        /* ==================== RIEPILOGO ASSICURAZIONI & COSTI EXTRA ==================== */
        // Converte una stringa di prezzo in numero.
        // Se il testo contiene un "%" (sovrapprezzo percentuale) lo ignora
        // perché il calcolo effettivo sarà gestito dal backend.
        function parsePrice(str) {
            if (!str) return 0;

            // Se il valore è percentuale (es. "+10%") lo consideriamo 0 nel riepilogo live
            if (str.includes('%')) {
                return 0;
            }

            // Converte "1.234,56 €" -> 1234.56
            return (
                parseFloat(
                    str
                        .replace(/\./g, '')      // rimuove i punti separatori delle migliaia
                        .replace(',', '.')       // converte la virgola in punto decimale
                        .replace(/[^\d.-]/g, '') // rimuove qualsiasi altro carattere
                ) || 0
            );
        }

        function updateSummaryInsurance() {
            const $container = $('#btr-summary-insurance-container');
            const $placeholder = $container.find('.btr-summary-placeholder');
            $container.find('.btr-summary-item').not('.btr-summary-placeholder').remove(); // Pulisce vecchi item

            // Costi Extra container
            const $extraContainer   = $('#btr-summary-extra-container');
            const $extraPlaceholder = $extraContainer.find('.btr-summary-placeholder');
            $extraContainer.find('.btr-summary-item').not('.btr-summary-placeholder').remove();

            let totalInsurance = 0;
            let participantsWithInsurance = new Set();
            let totalExtra = 0;

            $('.btr-person-card').each(function () {
                const $card = $(this);
                let personName = ($card.find('input[name$="[nome]"]').val() + ' ' + $card.find('input[name$="[cognome]"]').val()).trim();
                if (!personName) {
                    const personTitle = $card.find('.person-title strong').text();
                    personName = personTitle || `Partecipante #${$card.data('person-index') + 1}`;
                }
                
                // DEBUG: Log di tutti i checkbox costi extra per questa persona
                const allExtraCheckboxes = $card.find('input[type="checkbox"][name*="[costi_extra]"]');
                console.log('[BTR DEBUG] Persona:', personName, 'Checkbox costi extra totali:', allExtraCheckboxes.length);
                allExtraCheckboxes.each(function() {
                    const isChecked = $(this).is(':checked');
                    const name = $(this).attr('name');
                    console.log('[BTR DEBUG]  - Checkbox:', name, 'Checked:', isChecked);
                });


                // Assicurazioni
                $card.find('input[type="checkbox"][name*="[assicurazioni]"]:checked').each(function () {
                    const $row = $(this).closest('.btr-assicurazione-item');
                    const label = $.trim($row.clone()      // clona per ottenere solo testo senza checkbox
                        .children('label')
                        .children()
                        .remove()
                        .end()
                        .text());
                    const priceText = $row.find('strong').first().text();
                    const priceVal = parsePrice(priceText);
                    totalInsurance += priceVal;
                    participantsWithInsurance.add(personName);

                    $(`<div class="btr-summary-item"><span class="btr-summary-label">${personName} &ndash; ${label}</span><span class="btr-summary-value">${priceText}</span></div>`).appendTo($container);
                });

                // Costi extra per persona
                $card.find('input[type="checkbox"][name*="[costi_extra]"]:checked').each(function () {
                    // DEBUG: Log per verificare che i checkbox siano trovati
                    console.log('[BTR DEBUG] Checkbox costo extra trovato:', $(this).attr('name'), 'checked:', $(this).is(':checked'));
                    const $row = $(this).closest('.btr-assicurazione-item');
                    const label = $.trim($row.clone().children('label').children().remove().end().text());
                    const priceText = $row.find('strong').first().text();
                    const extraVal = parsePrice(priceText);
                    
                    // DEBUG: Log per verificare i valori
                    if (typeof console !== 'undefined' && console.log) {
                        console.log('[BTR] Extra Cost Debug:', {
                            label: label,
                            priceText: priceText,
                            extraVal: extraVal,
                            isNegative: extraVal < 0
                        });
                    }
                    
                    // CORREZIONE: Includi anche i valori negativi (riduzioni) nel totale
                    if (extraVal !== 0) {
                        totalExtra += extraVal;
                        
                        // Stile per valori negativi (riduzioni)
                        const valueStyle = extraVal < 0 ? ' style="color: #d63384;"' : '';
                        
                        $(`<div class="btr-summary-item"><span class="btr-summary-label">${personName} &ndash; ${label}</span><span class="btr-summary-value"${valueStyle}>${priceText}</span></div>`).appendTo($extraContainer);
                    }
                });
            });

            // Aggiorna placeholder e riga totale assicurazioni
            const hasInsuranceItems = $container.children().not('.btr-summary-placeholder').length > 0;
            const hasHiddenInsurances = window.hasHiddenInsuranceDueToNoSkipass === true;
            
            if (hasInsuranceItems) {
                $placeholder.hide();
                $('.summary-insurance-total').show(); // Mostra riga totale assicurazioni
                console.log('[BTR DEBUG] Mostro riga totale assicurazioni');
            } else if (hasHiddenInsurances) {
                // Se ci sono assicurazioni nascoste per "no skipass", mostra un messaggio diverso
                $placeholder.html('<span class="btr-summary-label" style="color: #856404; font-style: italic;">RC Skipass non disponibile (No Skipass selezionato)</span>');
                $placeholder.show();
                $('.summary-insurance-total').hide(); // Nascondi riga totale se è 0
                console.log('[BTR DEBUG] Mostro messaggio RC Skipass non disponibile');
            } else {
                $placeholder.html('<span class="btr-summary-label">Nessuna assicurazione selezionata</span>');
                $placeholder.show();
                $('.summary-insurance-total').hide(); // Nascondi riga totale assicurazioni
                console.log('[BTR DEBUG] Nascondo riga totale assicurazioni');
            }

            // Aggiorna placeholder e riga totale costi extra
            const hasExtraItems = $extraContainer.children().not('.btr-summary-placeholder').length > 0;
            if (hasExtraItems) {
                $extraPlaceholder.hide();
                $('.summary-extra-total').show(); // Mostra riga totale costi extra
                console.log('[BTR DEBUG] Mostro riga totale costi extra');
            } else {
                $extraPlaceholder.show();
                $('.summary-extra-total').hide(); // Nascondi riga totale costi extra
                console.log('[BTR DEBUG] Nascondo riga totale costi extra');
            }

            // ---------------------------------------------------------------
            //  Calcoli finali: il prezzo pacchetto NON include la notte extra
            //  ⇒ Dobbiamo sommare extraNightTotal al totale finale.
            // ---------------------------------------------------------------
            let extraNightTotal = 0;
            const nightEl = $('#btr-summary-extra-night-total');
            if (nightEl.length) {
                const pp = parseFloat((nightEl.data('pp') || '0').toString().replace(',', '.')) || 0;

                // Tenta prima dal data-participants, in fallback dal contatore totale partecipanti
                let persons = parseInt((nightEl.data('participants') || '').toString(), 10);
                if (!persons || isNaN(persons)) {
                    persons = parseInt($('#btr-summary-total-participants').text().replace(/\D/g, ''), 10) || 0;
                }

                extraNightTotal = pp * persons;

                // Aggiorna la stringa visualizzata (es. "166,00 € × 3 = 498,00 €")
                if (extraNightTotal > 0) {
                    const ppFormatted = pp.toLocaleString('it-IT', { minimumFractionDigits: 2 });
                    nightEl.text(
                        `${ppFormatted} € × ${persons} = ` +
                        extraNightTotal.toLocaleString('it-IT', { minimumFractionDigits: 2 }) +
                        ' €'
                    );
                }
            }

            // Backend-driven totals fix (2025-07-08)
            const packagePrice   = parsePrice($('#btr-summary-package-price').text());
            const totalOptional  = totalInsurance + totalExtra;
            extraNightTotal = parseFloat(<?php echo json_encode($extra_night_cost); ?>);
            // Sovrascrive la stringa dell'elemento con il totale formattato (rimuove "× 4 = ...")
            $('#btr-summary-extra-night-total').text(
              extraNightTotal.toLocaleString('it-IT', { minimumFractionDigits: 2 }) + ' €'
            );
            
            // CORREZIONE 2025-01-20: Calcolo semplice e diretto
            // FIX v1.0.158: Usa il totale del preventivo salvato invece di ricalcolarlo
            // Il totale del preventivo (€688.55) include già camere + costi extra
            // Dobbiamo solo aggiungere le nuove assicurazioni selezionate nel checkout
            const preventivoTotal = <?php echo json_encode($prezzo_totale ?: 0); ?>; // 🚨 FIX v1.0.218: Usa $prezzo_totale (€774,04) non $prezzo_totale_preventivo (€33,49)
            
            // Calcola il totale finale: preventivo + nuove assicurazioni
            const grandTotal = preventivoTotal + totalInsurance + totalExtra; // FIX v1.0.228: Include costi extra
            
            console.log('[BTR DEBUG jQuery] Calcolo totale con preventivo salvato:', {
                preventivoTotal: preventivoTotal,
                totalInsurance: totalInsurance,
                totalExtra: totalExtra,                grandTotal: grandTotal,
                formula: 'preventivoTotal + totalInsurance + totalExtra',
                note: 'Usando totale preventivo salvato (€688.55) + nuove assicurazioni'
            });

            $('#btr-summary-insurance-total').text(
                totalInsurance.toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €'
            );
            
            // La visibilità del box verrà gestita da toggleBlocks() che tiene conto anche di hasHiddenInsuranceDueToNoSkipass
            // CORREZIONE: Formattazione corretta per totali negativi
            const formattedExtraTotal = totalExtra < 0 
                ? `-${Math.abs(totalExtra).toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} €`
                : `${totalExtra.toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} €`;
            
            $('#btr-summary-extra-total').text(formattedExtraTotal);
            
            // Aggiungi stile rosso per totali negativi
            if (totalExtra < 0) {
                $('#btr-summary-extra-total').css('color', '#d63384');
            } else {
                $('#btr-summary-extra-total').css('color', '');
            }
            $('#btr-summary-grand-total').text(
                grandTotal.toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €'
            );
            // Aggiorna partecipanti assicurati
            $('#btr-summary-insured-participants').text(participantsWithInsurance.size);
            
            // CORREZIONE 2025-01-19: Chiama toggleBlocks per aggiornare visibilità dei card
            setTimeout(function() {
                if (typeof toggleBlocks === 'function') {
                    console.log('[BTR DEBUG] Chiamando toggleBlocks dalla jQuery updateSummaryInsurance');
                    toggleBlocks();
                }
            }, 10);
        }

        // NUOVA FUNZIONE: Inizializza logica campi condizionali
        function initializeConditionalFields() {
            console.log('[BTR DEBUG] Inizializzando campi condizionali...');
            
            // Per ogni partecipante, controlla lo stato iniziale delle assicurazioni
            $('.btr-person-card').each(function() {
                var personIndex = $(this).data('person-index');
                var personCard = $(this);
                
                // Controlla assicurazioni per campi codice fiscale e indirizzo
                var hasInsurance = false;
                var hasInsuranceRequiringFiscalCode = false;
                
                personCard.find('input[name*="[assicurazioni]"]:checked').each(function() {
                    hasInsurance = true;
                    var isRcSkipass = $(this).data('no-fiscal-code') === true;
                    if (!isRcSkipass) {
                        hasInsuranceRequiringFiscalCode = true;
                    }
                });
                
                // Gestisci campi codice fiscale
                var codiceFiscaleField = personCard.find('.codice-fiscale-field');
                if (hasInsuranceRequiringFiscalCode || personIndex === 0) {
                    codiceFiscaleField.removeClass('hidden-field');
                } else {
                    codiceFiscaleField.addClass('hidden-field');
                }
                
                // Gestisci campi indirizzo (usando la stessa logica del codice fiscale)
                var addressFields = personCard.find('.address-field');
                if (hasInsuranceRequiringFiscalCode || personIndex === 0) {
                    addressFields.removeClass('hidden-field');
                } else {
                    addressFields.addClass('hidden-field');
                }
                
                // RC Skipass check (non più obbligatoria)
                var rcSkipassCheckbox = personCard.find('input[data-rc-skipass="true"]');
                if (rcSkipassCheckbox.length > 0) {
                    console.log('[BTR DEBUG] Init: RC Skipass trovata per partecipante', personIndex);
                    
                    // Controlla se "no skipass" è selezionato PER QUESTO PARTECIPANTE
                    var noSkipassSelectedForThisPerson = personCard.find('input[name*="[costi_extra][no-skipass]"]:checked').length > 0;
                    
                    if (noSkipassSelectedForThisPerson) {
                        // Se "no skipass" è selezionato per questo partecipante, nascondi RC Skipass
                        rcSkipassCheckbox.closest('.btr-assicurazione-item').hide();
                        rcSkipassCheckbox.prop('checked', false);
                        console.log('[BTR DEBUG] Init: RC Skipass nascosta per partecipante', personIndex, '- no skipass selezionato per questo partecipante');
                    }
                }
                
                console.log('[BTR DEBUG] Partecipante', personIndex, '- hasInsurance:', hasInsurance, 'hasInsuranceRequiringFiscalCode:', hasInsuranceRequiringFiscalCode);
            });
        }

        // Inizializza il riepilogo alla prima apertura
        $(document).ready(function() {
            console.log('[BTR DEBUG] Document ready - inizializzo summary insurance e extra costs');
            
            // Controlla se "no skipass" è già selezionato all'inizializzazione
            var noSkipassInitiallySelected = false;
            $('input[name^="anagrafici"][name*="[costi_extra][no-skipass]"]').each(function() {
                if ($(this).is(':checked')) {
                    noSkipassInitiallySelected = true;
                    return false; // break
                }
            });
            window.hasHiddenInsuranceDueToNoSkipass = noSkipassInitiallySelected;
            
            updateSummaryInsurance();
            
            // NUOVA FUNZIONALITÀ: Inizializza logica campi condizionali
            initializeConditionalFields();
            
            // CORREZIONE 2025-01-19: Assicura che anche le funzioni vanilla JS siano chiamate
            setTimeout(function() {
                if (typeof updateSummaryWithParticipantNames === 'function') {
                    console.log('[BTR DEBUG] Chiamando updateSummaryWithParticipantNames dalla jQuery ready');
                    updateSummaryWithParticipantNames();
                }
                if (typeof updateExtraCostsSummaryWithNames === 'function') {
                    console.log('[BTR DEBUG] Chiamando updateExtraCostsSummaryWithNames dalla jQuery ready');
                    updateExtraCostsSummaryWithNames();
                }
                // Forza un aggiornamento del totale generale
                if (typeof recalcGrandTotal === 'function') {
                    console.log('[BTR DEBUG] Chiamando recalcGrandTotal dalla jQuery ready');
                    recalcGrandTotal();
                }
                
                // CORREZIONE 2025-01-19: Assicura che i card siano visibili
                if (typeof toggleBlocks === 'function') {
                    console.log('[BTR DEBUG] Chiamando toggleBlocks dalla jQuery ready');
                    toggleBlocks();
                }
            }, 100);
        });

        // Aggiorna riepilogo quando si (de)seleziona un'assicurazione o costo extra
        $(document).on('change', '.btr-person-card input[type="checkbox"]', function() {
            console.log('[BTR DEBUG] Checkbox changed:', $(this).attr('name'), 'checked:', $(this).is(':checked'));
            updateSummaryInsurance();
            
            // Sincronizza anche con il sistema vanilla JS
            setTimeout(function() {
                if (typeof updateSummaryWithParticipantNames === 'function') {
                    updateSummaryWithParticipantNames();
                }
                if (typeof updateExtraCostsSummaryWithNames === 'function') {
                    updateExtraCostsSummaryWithNames();
                }
                if (typeof recalcGrandTotal === 'function') {
                    recalcGrandTotal();
                }
            }, 10);
        });


    })(jQuery);



</script>


    <script>
        // FIX CRITICO v1.0.233: Funzioni globali per gestione errori schede
        // DEVONO essere globali perché chiamate dai room-button event handlers
        function updateCardErrorStatus($card) {
            // Se non ci sono più campi con errore nella scheda, rimuovi la classe btr-missing
            if (jQuery($card).find('.has-error').length === 0) {
                jQuery($card).removeClass('btr-missing');
                // Aggiorna anche il contatore delle persone mancanti se presente
                updateMissingCounter();
            }
        }

        function updateMissingCounter() {
            if (jQuery('.mancanti').length) {
                const missingCount = jQuery('.btr-person-card.btr-missing').length;
                jQuery('.mancanti').text(missingCount);
            }
        }

        // Sistema di validazione migliorato con rimozione immediata degli errori
        (function ($) {
            'use strict';

            // Aggiungi validazione in tempo reale per tutti i campi
            $(function() {
                setupLiveValidation();
            });

            // Funzione per impostare la validazione in tempo reale
            function setupLiveValidation() {
                // Validazione in tempo reale per i campi di input
                $(document).on('input', '.btr-person-card input[type="text"], .btr-person-card input[type="email"], .btr-person-card input[type="date"], .btr-person-card input[type="tel"]', function() {
                    const $input = $(this);
                    const $fieldGroup = $input.closest('.btr-field-group');

                    // Se il campo è stato compilato, rimuovi l'errore
                    if ($input.val().trim()) {
                        $fieldGroup.removeClass('has-error');
                        $fieldGroup.find('.btr-field-error').remove();

                        // Controlla se la scheda ha ancora errori
                        updateCardErrorStatus($input.closest('.btr-person-card'));
                    }
                });

                
            }


            // Funzione migliorata per validare tutti i dati anagrafici
            function validateAllData() {
                let missingData = [];
                let missingRooms = [];
                let specificErrors = {};
                let totalErrors = 0;
                let firstErrorCard = null;

                // Crea un helper per aggiungere errori in modo consistente
                function addError($element, message, fieldName, personName) {
                    const $errorMsg = $(`<div class="btr-field-error">${message}</div>`);
                    $element.after($errorMsg);

                    // Aggiungi alla lista dei campi mancanti
                    if (!missingData.includes(personName)) {
                        missingData.push(personName);
                    }

                    // Aggiungi il campo specifico mancante
                    if (fieldName && specificErrors[personName] && !specificErrors[personName].includes(fieldName)) {
                        specificErrors[personName].push(fieldName);
                    }

                    totalErrors++;
                    return $errorMsg;
                }

                // Resetta lo stato di tutti i campi
                $('.btr-person-card').removeClass('btr-missing');
                $('.btr-field-error').remove();
                $('.btr-field-group').removeClass('has-error');
                $('.asign-camera').removeClass('has-error');

                // Valida ogni scheda persona
                $('.btr-person-card').each(function(index) {
                    const $card = $(this);
                    const personIndex = $card.data('person-index');
                    const personName = $card.find('.person-title strong').text();
                    let hasErrors = false;
                    specificErrors[personName] = [];

                    // Verifica i campi obbligatori (escludi provincia search, nazione_residenza e campi nascosti)
                    $card.find('input[type="text"], input[type="email"], input[type="date"], input[type="tel"]')
                        .not('.btr-province-search')
                        .not('input[name*="[nazione_residenza]"]')
                        .each(function() {
                            const $input = $(this);
                            const fieldName = $input.prev('label').text().trim();
                            const $fieldGroup = $input.closest('.btr-field-group');

                            // NUOVA LOGICA: Salta i campi nascosti
                            if ($fieldGroup.hasClass('hidden-field')) {
                                console.log('[BTR DEBUG] validateAllData - Saltando campo nascosto:', $input.attr('name'));
                                return true; // continue
                            }

                            // Gestione speciale per codice fiscale
                            if ($input.attr('name') && $input.attr('name').includes('[codice_fiscale]')) {
                                // Per il primo partecipante, il codice fiscale è sempre obbligatorio
                                // Per gli altri, solo se hanno un'assicurazione selezionata (e il campo è visibile)
                                const isFirstParticipant = index === 0;
                                const hasInsurance = $card.find('input[name*="[assicurazioni]"]:checked').length > 0;
                                
                                // Controlla se ha assicurazioni che richiedono codice fiscale (esclusa RC Skipass)
                                let hasInsuranceRequiringFiscalCode = false;
                                $card.find('input[name*="[assicurazioni]"]:checked').each(function() {
                                    const isRcSkipass = $(this).data('no-fiscal-code') === true || 
                                                       $(this).data('rc-skipass') === true;
                                    if (!isRcSkipass) {
                                        hasInsuranceRequiringFiscalCode = true;
                                    }
                                });

                                if (!$input.val().trim() && (isFirstParticipant || hasInsuranceRequiringFiscalCode)) {
                                    $fieldGroup.addClass('has-error');
                                    addError($input, '<?php _e('Campo obbligatorio', 'born-to-ride-booking'); ?>', fieldName, personName);
                                    hasErrors = true;
                                }
                            } 
                            // Gestione speciale per campi indirizzo (indirizzo_residenza, numero_civico, cap_residenza)
                            else if ($input.attr('name') && 
                                    ($input.attr('name').includes('[indirizzo_residenza]') || 
                                     $input.attr('name').includes('[numero_civico]') || 
                                     $input.attr('name').includes('[cap_residenza]'))) {
                                
                                // Per il primo partecipante, sempre obbligatorio
                                // Per gli altri, solo se hanno assicurazioni che richiedono indirizzo (esclusa RC Skipass)
                                const isFirstParticipant = index === 0;
                                
                                let hasInsuranceRequiringAddress = false;
                                $card.find('input[name*="[assicurazioni]"]:checked').each(function() {
                                    const isRcSkipass = $(this).data('no-fiscal-code') === true || 
                                                       $(this).data('rc-skipass') === true;
                                    if (!isRcSkipass) {
                                        hasInsuranceRequiringAddress = true;
                                    }
                                });

                                if (!$input.val().trim() && (isFirstParticipant || hasInsuranceRequiringAddress)) {
                                    $fieldGroup.addClass('has-error');
                                    addError($input, '<?php _e('Campo obbligatorio', 'born-to-ride-booking'); ?>', fieldName, personName);
                                    hasErrors = true;
                                }
                            }
                            // Altri campi sempre obbligatori se visibili
                            else if (!$input.val().trim()) {
                                $fieldGroup.addClass('has-error');
                                addError($input, '<?php _e('Campo obbligatorio', 'born-to-ride-booking'); ?>', fieldName, personName);
                                hasErrors = true;
                            } else if ($input.attr('type') === 'email' && !isValidEmail($input.val().trim())) {
                                $fieldGroup.addClass('has-error');
                                addError($input, '<?php _e('Email non valida', 'born-to-ride-booking'); ?>', fieldName, personName);
                                hasErrors = true;
                            } else if ($input.attr('type') === 'date' && !isValidBirthDate($input.val().trim())) {
                                $fieldGroup.addClass('has-error');
                                addError($input, '<?php _e('Data di nascita non valida (deve essere nel passato)', 'born-to-ride-booking'); ?>', fieldName, personName);
                                hasErrors = true;
                            }
                        });

                    // Valida nazione di residenza solo se provincia è ESTERO
                    var $prov = $card.find('.provincia-residenza-field');
                    var provinciaVal = (($prov.attr('data-value')) || $prov.val() || '').toString().trim().toUpperCase();
                    if (provinciaVal === 'ESTERO') {
                        var nazioneInput = $card.find('input[name*="[nazione_residenza]"]');
                        var fieldName = '<?php _e('Nazione di residenza', 'born-to-ride-booking'); ?>';
                        if (!nazioneInput.val().trim()) {
                            var $fieldGroup = nazioneInput.closest('.btr-field-group');
                            $fieldGroup.addClass('has-error');
                            addError(nazioneInput, '<?php _e('Campo obbligatorio', 'born-to-ride-booking'); ?>', fieldName, personName);
                            hasErrors = true;
                        }
                    }

                    // Verifica l'assegnazione della camera
                    const $roomInput = $card.find('input[type="hidden"][name*="[camera]"]');
                    const roomVal = ($roomInput.val() || '').toString().trim();
                    if (!roomVal || roomVal === '0') {
                        const $cameraSection = $card.find('.asign-camera');
                        $cameraSection.addClass('has-error');
                        addError($cameraSection.find('h4'), '<?php _e('Seleziona una camera', 'born-to-ride-booking'); ?>', '<?php _e('Camera', 'born-to-ride-booking'); ?>', personName);
                        hasErrors = true;

                        // Aggiungi alla lista delle camere mancanti
                        if (!missingRooms.includes(personName)) {
                            missingRooms.push(personName);
                        }
                    }

                    // Evidenzia la scheda se ci sono errori
                    if (hasErrors) {
                        $card.addClass('btr-missing');

                        // Salva la prima scheda con errori per lo scroll
                        if (!firstErrorCard) {
                            firstErrorCard = $card;
                        }
                    }
                });

                // Aggiorna il contatore delle persone mancanti
                updateMissingCounter();

                // Scorri alla prima scheda con errori con precisione migliorata
                if (firstErrorCard) {
                    // Delay per assicurare che gli elementi siano nel DOM
                    setTimeout(() => {
                        // Verifica che l'elemento esista ancora
                        if (!firstErrorCard.length || !firstErrorCard.is(':visible')) {
                            console.warn('[BTR] Elemento con errore non trovato per scroll');
                            return;
                        }
                        
                        // Funzione per calcolare offset dinamico
                        const calculatePreciseOffset = () => {
                            let totalOffset = 0;
                            
                            // Admin bar (se presente)
                            const $adminBar = $('#wpadminbar');
                            if ($adminBar.length && $adminBar.is(':visible')) {
                                totalOffset += $adminBar.outerHeight() || 0;
                            }
                            
                            // Header sticky del sito (se presente)
                            const $stickyHeader = $('.header-sticky, .sticky-header, #header[style*="fixed"], #header[style*="sticky"]');
                            if ($stickyHeader.length && $stickyHeader.is(':visible')) {
                                totalOffset += $stickyHeader.outerHeight() || 0;
                            }
                            
                            // Aggiungi margine extra per migliore visibilità
                            const viewportHeight = $(window).height();
                            const extraMargin = Math.min(100, viewportHeight * 0.1); // 10% viewport o max 100px
                            totalOffset += extraMargin;
                            
                            return totalOffset;
                        };
                        
                        // Calcola posizione target con offset dinamico
                        const dynamicOffset = calculatePreciseOffset();
                        const cardOffset = firstErrorCard.offset();
                        
                        // Gestione errore se offset non disponibile
                        if (!cardOffset || typeof cardOffset.top === 'undefined') {
                            console.error('[BTR] Impossibile calcolare offset per scroll');
                            return;
                        }
                        
                        const cardPosition = cardOffset.top;
                        const scrollTarget = Math.max(0, cardPosition - dynamicOffset);
                        
                        // Disabilita temporaneamente lo scroll automatico per evitare conflitti
                        $('html, body').stop(true, false);
                        
                        // Scroll fluido con easing personalizzato
                        $('html, body').animate({
                            scrollTop: scrollTarget
                        }, 800, 'easeInOutCubic', function() {
                            // (Rimosso secondo scroll correttivo per ridurre i salti)
                            
                            // Evidenzia la card con animazione
                            firstErrorCard.addClass('btr-error-highlight');
                            
                            // Focus sul primo campo con errore dopo breve delay
                            setTimeout(() => {
                                const $firstErrorField = firstErrorCard.find('.has-error input:visible, .has-error select:visible').first();
                                if ($firstErrorField.length) {
                                    $firstErrorField.focus();
                                    // Evidenzia il campo specifico
                                    $firstErrorField.parent().addClass('btr-field-highlight');
                                    setTimeout(() => {
                                        $firstErrorField.parent().removeClass('btr-field-highlight');
                                    }, 3000);
                                }
                            }, 200);
                            
                            // Rimuovi l'highlight dopo l'animazione
                            setTimeout(() => {
                                firstErrorCard.removeClass('btr-error-highlight');
                            }, 3000);
                        });
                        
                        // Fallback per jQuery easing se non disponibile
                        if (!$.easing.easeInOutCubic) {
                            $.easing.easeInOutCubic = function(x, t, b, c, d) {
                                if ((t /= d / 2) < 1) return c / 2 * t * t * t + b;
                                return c / 2 * ((t -= 2) * t * t + 2) + b;
                            };
                        }
                    }, 200); // Chiusura del setTimeout aggiunto all'inizio
                }

                // Mostra notifica se ci sono errori
                if (missingData.length > 0 || missingRooms.length > 0) {
                    // Crea un messaggio più chiaro e dettagliato
                    let errorTitle = '<?php _e('Per completare la prenotazione:', 'born-to-ride-booking'); ?>';
                    let errorDetails = '';

                    // Aggiungi dettagli per ogni persona con dati mancanti
                    if (missingData.length > 0) {
                        errorDetails += `<div class="btr-error-section">
                            <h4><?php _e('Completa i dati anagrafici per:', 'born-to-ride-booking'); ?></h4>
                            <ul class="btr-error-list">`;

                        missingData.forEach((person, index) => {
                            errorDetails += `<li>
                                <strong>${person}</strong>`;
                            if (specificErrors[person] && specificErrors[person].length > 0) {
                                errorDetails += ` <span class="btr-error-fields">(${specificErrors[person].join(', ')})</span>`;
                            }
                            errorDetails += `</li>`;
                        });

                        errorDetails += `</ul></div>`;
                    }

                    // Aggiungi dettagli per ogni persona senza camera assegnata
                    if (missingRooms.length > 0) {
                        errorDetails += `<div class="btr-error-section">
                            <h4><?php _e('Assegna una camera a:', 'born-to-ride-booking'); ?></h4>
                            <ul class="btr-error-list">`;

                        missingRooms.forEach((person, index) => {
                            errorDetails += `<li><strong>${person}</strong></li>`;
                        });

                        errorDetails += `</ul></div>`;
                    }

                    // Aggiungi un messaggio di aiuto e un pulsante per continuare
                    errorDetails += `<div class="btr-error-help">
                        <p><?php _e('Clicca sulle schede evidenziate in rosso per completare i dati mancanti.', 'born-to-ride-booking'); ?></p>
                    </div>`;

                    // Crea una notifica persistente che rimane visibile finché l'utente non la chiude
                    const $notification = showNotification(
                        `<div class="btr-error-container">
                            <h3 class="btr-error-title">${errorTitle}</h3>
                            <div class="btr-error-content">${errorDetails}</div>
                        </div>`,
                        'error',
                        0,  // Durata 0 significa che rimane finché non viene chiusa
                        true // Dismissable
                    );


                    return false;
                }

                return true;
            }

            // Helper per validare email
            function isValidEmail(email) {
                const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
                return re.test(email);
            }

            // FIX v1.0.236: Helper per validare date di nascita
            function isValidBirthDate(dateValue) {
                if (!dateValue) return false;
                
                const selectedDate = new Date(dateValue);
                const today = new Date();
                
                // Reset time to compare only dates
                today.setHours(0, 0, 0, 0);
                selectedDate.setHours(0, 0, 0, 0);
                
                // Reject future dates
                if (selectedDate >= today) {
                    return false;
                }
                
                // Reject dates too far in the past (over 120 years)
                const minDate = new Date();
                minDate.setFullYear(minDate.getFullYear() - 120);
                if (selectedDate < minDate) {
                    return false;
                }
                
                return true;
            }

            function submitAnagraficiAjax($form){
                const fd = new FormData($form[0]);
                // Rimuovi eventuali azioni/nonce di conversione presenti nel form
                fd.delete('action');
                fd.delete('btr_convert_nonce');
                // Forza azione corretta e nonce di salvataggio
                fd.append('action', 'btr_save_anagrafici');
                const saveNonce = $form.find('[name="btr_update_anagrafici_nonce_field"]').val();
                if (saveNonce) fd.set('btr_update_anagrafici_nonce_field', saveNonce);
                // Assicura ID chiave
                const preventivoId = $form.find('[name="preventivo_id"]').val();
                if (preventivoId) fd.set('preventivo_id', preventivoId);
                const orderId = $form.find('[name="order_id"]').val() || 0;
                fd.set('order_id', orderId);

                showNotification('Salvataggio in corso...', 'info');
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    method: 'POST',
                    data: fd,
                    processData: false,
                    contentType: false,
                    dataType: 'json'
                }).done(function(response){
                    if (response && response.success) {
                        // Se già fornito un redirect, usalo
                        if (response.data && response.data.redirect_url) {
                            showNotification('Dati salvati con successo!', 'success');
                            setTimeout(function(){ window.location.href = response.data.redirect_url; }, 600);
                            return;
                        }
                        // Altrimenti, esegui conversione a checkout in una seconda richiesta
                        const convFd = new FormData();
                        convFd.append('action', 'btr_convert_to_checkout');
                        convFd.append('preventivo_id', preventivoId || '');
                        const convNonce = $form.find('[name="btr_convert_nonce"]').val();
                        if (convNonce) convFd.append('btr_convert_nonce', convNonce);

                        $.ajax({
                            url: '<?php echo admin_url('admin-ajax.php'); ?>',
                            method: 'POST',
                            data: convFd,
                            processData: false,
                            contentType: false,
                            dataType: 'json'
                        }).done(function(resp2){
                            if (resp2 && resp2.success && resp2.data && resp2.data.redirect_url) {
                                window.location.href = resp2.data.redirect_url;
                            } else {
                                showNotification((resp2 && resp2.data && resp2.data.message) || 'Impossibile iniziare il checkout.', 'error', 0, true);
                            }
                        }).fail(function(jq){
                            showNotification('Errore di connessione (convert).', 'error', 0, true);
                        });
                    } else {
                        showNotification((response && response.data && response.data.message) || 'Errore durante il salvataggio', 'error', 0, true);
                    }
                }).fail(function(jqXHR){
                    let msg = 'Errore di connessione. Riprova più tardi.';
                    try { if (jqXHR.responseText) { msg += ' ' + jqXHR.responseText.substring(0,120); } } catch(e){}
                    showNotification(msg, 'error', 0, true);
                });
            }

            // Gestione del submit per il form non-AJAX (con fallback ad AJAX per redirect corretto)
            $(document).on('submit', 'form.btr-form:not(#btr-anagrafici-form)', function(e) {
                if (!validateAllData()) {
                    e.preventDefault();
                    return false;
                }
                e.preventDefault();
                submitAnagraficiAjax($(this));
                return false;
            });

            // Gestione del submit per il form AJAX (salva e completa)
            $('#btr-anagrafici-form').off('submit');
            $(document).off('submit', '#btr-anagrafici-form');
            $('#btr-anagrafici-form').off('submit.btr').on('submit.btr', function(e) {
                e.preventDefault(); e.stopImmediatePropagation();

                if (!validateAllData()) {
                    return false;
                }

                submitAnagraficiAjax($(this));
            });

        })(jQuery);
    </script>

    <!-- Script per gestione dinamica Culla per Neonati -->
    <script>
        (function($) {
            $(document).ready(function() {
                
                // Gestione dinamica delle culle per neonati
                function updateCribLogic() {
                    const $cribCheckboxes = $('.btr-crib-checkbox');
                    const maxCribs = parseInt($cribCheckboxes.first().data('max-cribs') || 0, 10);
                    let selectedCribs = 0;
                    
                    // Conta le culle attualmente selezionate
                    $cribCheckboxes.each(function() {
                        if ($(this).is(':checked')) {
                            selectedCribs++;
                        }
                    });
                    
                    console.log('🍼 Crib Logic Update:', {
                        selectedCribs: selectedCribs,
                        maxCribs: maxCribs,
                        totalCheckboxes: $cribCheckboxes.length
                    });
                    
                    // Aggiorna lo stato di ogni checkbox
                    $cribCheckboxes.each(function() {
                        const $checkbox = $(this);
                        const $label = $checkbox.closest('label');
                        const $item = $checkbox.closest('.btr-assicurazione-item');
                        
                        // Se questa checkbox è selezionata, lasciala abilitata
                        if ($checkbox.is(':checked')) {
                            $checkbox.prop('disabled', false);
                            $label.removeClass('btr-disabled-option');
                            return;
                        }
                        
                        // Se abbiamo raggiunto il limite, disabilita le altre
                        if (selectedCribs >= maxCribs) {
                            $checkbox.prop('disabled', true);
                            $label.addClass('btr-disabled-option');
                            
                            // Aggiungi/aggiorna messaggio
                            let $message = $item.find('.btr-culla-message');
                            if ($message.length === 0) {
                                $message = $('<small class="btr-culla-message" style="color: #dc3232; font-style: italic; display: block; margin-top: 5px;"></small>');
                                $label.append($message);
                            }
                            $message.text(`(Raggiunte ${selectedCribs}/${maxCribs} culle disponibili)`);
                            
                        } else {
                            // Riabilita se sotto il limite
                            $checkbox.prop('disabled', false);
                            $label.removeClass('btr-disabled-option');
                            
                            // Rimuovi messaggio se presente
                            $item.find('.btr-culla-message').remove();
                        }
                    });
                    
                    // Aggiorna/aggiungi contatore visivo globale
                    updateGlobalCribCounter(selectedCribs, maxCribs);
                }
                
                function updateGlobalCribCounter(selectedCribs, maxCribs) {
                    if (maxCribs === 0) return; // Nessun neonato, nessun contatore
                    
                    // Rimuovi tutti i box esistenti
                    $('.btr-crib-info').remove();
                    
                    if (selectedCribs > 0 || maxCribs > 0) {
                        // Crea l'HTML del contatore
                        const counterHtml = `
                            <div class="btr-crib-info" style="
                                background: linear-gradient(135deg, #fef3cd 0%, #fff8e1 100%);
                                border: 1px solid #ffc107;
                                border-radius: 8px;
                                padding: 15px;
                                margin: 20px 0;
                                display: flex;
                                align-items: center;
                                gap: 10px;
                                box-shadow: 0 2px 4px rgba(255, 193, 7, 0.1);
                            ">
                                <svg style="width: 24px; height: 24px; color: #856404;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                                </svg>
                                <strong>🍼 Culle per neonati:</strong> 
                                <span class="btr-crib-counter">${selectedCribs}/${maxCribs}</span> selezionate
                                ${maxCribs === 1 ? ' (massimo 1 culla disponibile)' : ` (massimo ${maxCribs} culle disponibili)`}
                            </div>
                        `;
                        
                        // Inserisci il box in tutti i form degli adulti che hanno la checkbox culla
                        $('fieldset.btr-assicurazioni:has(.btr-crib-checkbox)').each(function() {
                            $(this).before(counterHtml);
                        });
                    }
                }
                
                // Event listener per cambiamenti alle culle
                $(document).on('change', '.btr-crib-checkbox', function() {
                    console.log('🍼 Crib checkbox changed:', $(this).is(':checked'));
                    
                    // Aggiorna la logica dopo un breve delay per permettere al DOM di aggiornarsi
                    setTimeout(updateCribLogic, 10);
                    
                    // Calcola e aggiorna il totale dinamicamente
                    updateDynamicTotals();
                });
                
                // Inizializza la logica delle culle al caricamento
                if ($('.btr-crib-checkbox').length > 0) {
                    console.log('🍼 Initializing crib logic...');
                    updateCribLogic();
                }
                
                // Funzione per aggiornare i totali dinamicamente (riutilizza logica esistente)
                function updateDynamicTotals() {
                    // Questa funzione dovrebbe interfacciarsi con il sistema di calcolo totali esistente
                    // Per ora log per debug
                    console.log('💰 Updating dynamic totals due to crib selection change');
                    
                    // Trigger evento personalizzato per altre parti del codice
                    $(document).trigger('btr-cost-changed', {
                        type: 'crib',
                        action: 'selection_changed'
                    });
                }
                
            });
        })(jQuery);
    </script>

    <script>
        // Dati immutabili dal preventivo per calcoli JS
        window.BTR_PREVENTIVO_DATA = {
            package_price: <?php echo json_encode( $totale_camere_saved ); ?>, // UNIFIED v1.0.217
            extra_night_cost: <?php echo json_encode( $extra_night_cost ); ?>
        };
    </script>

    <script>
      (function($){
        function updateCardBadge($card){
          var missing = $card.hasClass('btr-missing');
          var $badge = $card.find('.btr-badge-status').first();
          if ($badge.length){
            if (missing){
              $badge.text('Da completare').removeClass('btr-badge-complete').addClass('btr-badge-missing');
            } else {
              $badge.text('Completo').removeClass('btr-badge-missing').addClass('btr-badge-complete');
            }
          }
          return missing;
        }

        function setExpanded($card, expand){
          var $header = $card.children('.person-title');
          // Toggle solo il wrapper immediatamente successivo al titolo, evitando annidamenti
          var $content = $header.next('.btr-person-content');
          if (!$content.length) return;
          if (expand){
            $header.attr('aria-expanded','true');
            $content.show();
            $card.removeClass('collapsed');
          } else {
            $header.attr('aria-expanded','false');
            $content.hide();
            $card.addClass('collapsed');
          }
        }

        function refreshAccordions(init){
          $('.btr-person-card').each(function(i, el){
            var $card = $(el);
            var missing = updateCardBadge($card);
            var expand = (init ? (i === 0) : false);
            setExpanded($card, expand);
          });
        }

        $(document).on('click keydown', '.person-title', function(e){
          if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
          var $header = $(this);
          var $card = $header.closest('.btr-person-card');
          var expanded = $header.attr('aria-expanded') === 'true';
          setExpanded($card, !expanded);
        });

        $(function(){
          setTimeout(function(){ refreshAccordions(true); }, 0);
          $(document).on('input change', '.btr-person-card input, .btr-person-card select', function(){
            var $card = $(this).closest('.btr-person-card');
            updateCardBadge($card);
          });
          $(document).on('btr:validateAnagrafici:done', function(){
            // Alla validazione, apri la prima card con dati mancanti
            var $missing = $('.btr-person-card.btr-missing').first();
            if ($missing.length){ setExpanded($missing, true); }
          });
        });
      })(jQuery);
    </script>
