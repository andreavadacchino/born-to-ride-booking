<?php
declare(strict_types=1);
/**
 * BTR Preventivi V4 - Versione ottimizzata con WordPress best practices
 * 
 * Implementazione completa con:
 * - Naming convention italiana per tutti i meta fields
 * - Prefisso _btr_ consistente
 * - WordPress best practices (sanitizzazione, hooks, filters)
 * - Parsing corretto di tutti i campi dal payload
 * - Retrocompatibilità con sistema esistente
 * 
 * @package Born_To_Ride_Booking
 * @since 1.0.157
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Preventivi_V4 {
    /**
     * Custom Post Type slug
     */
    const CPT = 'btr_preventivi';
    
    /**
     * Logger instance
     * @var WC_Logger|null
     */
    private $logger = null;
    
    /**
     * Versione del sistema preventivi
     */
    const VERSION = '4.0';
    
    /**
     * Prefisso per i meta fields
     */
    const META_PREFIX = '_btr_';
    
    /**
     * Constructor
     */
    public function __construct() {
        // Logger inizializzato lazy
        $this->logger = null;
    }
    
    /**
     * Ottieni il logger (lazy initialization)
     */
    private function get_logger() {
        if ($this->logger === null && function_exists('wc_get_logger')) {
            $this->logger = wc_get_logger();
        }
        return $this->logger;
    }

    /**
     * Normalizza numeri/price a 2 decimali per evitare 917.5499999999
     */
    private function fmt_price($v): float {
        return round((float) $v, 2);
    }

    /**
     * Parsing robusto di valori monetari/float da stringhe con separatori IT/EN.
     * Esempi: "1.279", "1,279.00", "1.279,00", "1324.9", 1324.9
     */
    private function parse_price($value): float {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }
        $s = is_string($value) ? trim($value) : strval($value);
        if ($s === '') return 0.0;
        // rimuovi spazi e NBSP
        $s = str_replace(["\xC2\xA0", ' '], '', $s);
        
        // v1.0.205: FIX per formato "1.279" che è 1279 euro, non 1,279 euro
        // Se ha un solo punto e le cifre dopo il punto sono 3, è un separatore di migliaia
        if (preg_match('/^\d+\.\d{3}$/', $s)) {
            // Es: "1.279" -> rimuovi il punto -> "1279"
            $s = str_replace('.', '', $s);
            return (float) $s;
        }
        
        // Pattern con migliaia: 1.234,56 o 1,234.56
        if (preg_match('/^\d{1,3}([\.,]\d{3})+([\.,]\d+)?$/', $s)) {
            $lastDot = strrpos($s, '.');
            $lastComma = strrpos($s, ',');
            $decPos = max($lastDot !== false ? $lastDot : -1, $lastComma !== false ? $lastComma : -1);
            if ($decPos >= 0) {
                $decSep = $s[$decPos];
                $thousandSep = $decSep === '.' ? ',' : '.';
                $s = str_replace($thousandSep, '', $s);
                if ($decSep === ',') {
                    $s = str_replace(',', '.', $s);
                }
            }
        } else {
            // Caso semplice: sostituisci virgola con punto se non ci sono migliaia
            if (strpos($s, ',') !== false && strpos($s, '.') === false) {
                $s = str_replace(',', '.', $s);
            }
            // Rimuovi separatori di migliaia isolati
            if (preg_match('/^\d{1,3}(?:[\.,]\d{3})+$/', $s)) {
                $s = str_replace([',', '.'], '', $s);
            }
        }
        return (float) $s;
    }

    /**
     * Registra gli hook AJAX
     */
    public function register_ajax_hooks() {
        add_action('wp_ajax_btr_create_preventivo', [$this, 'create_preventivo']);
        add_action('wp_ajax_nopriv_btr_create_preventivo', [$this, 'create_preventivo']);
        
    }
    
    /**
     * Funzione principale per creare il preventivo
     * Completamente riscritta con best practices WordPress
     */
    public function create_preventivo() {
        try {
            // v1.0.157: Logging dettagliato per debug
            $start_time = microtime(true);
            $log_data = ['timestamp' => current_time('Y-m-d H:i:s')];
            
            // Step 1: Validazione sicurezza
            $this->validate_security();
            $log_data['security'] = 'OK';
            
            // Step 2: Parse completo del payload
            $dati_preventivo = $this->parse_payload_completo();
            $log_data['parse'] = 'OK';
            $log_data['cliente'] = $dati_preventivo['cliente']['email'] ?? 'N/A';
            $log_data['partecipanti'] = $dati_preventivo['partecipanti']['totale'] ?? 0;
            $log_data['bambini_f1-f4'] = [
                $dati_preventivo['partecipanti']['bambini_f1'] ?? 0,
                $dati_preventivo['partecipanti']['bambini_f2'] ?? 0,
                $dati_preventivo['partecipanti']['bambini_f3'] ?? 0,
                $dati_preventivo['partecipanti']['bambini_f4'] ?? 0
            ];
            $log_data['notti_extra_totale'] = $dati_preventivo['notti_extra']['totale'] ?? 0;
            
            // Step 3: Validazione dati
            $this->valida_dati_preventivo($dati_preventivo);
            $log_data['validazione'] = 'OK';
            
            // Step 4: Creazione post preventivo
            $preventivo_id = $this->crea_post_preventivo($dati_preventivo);
            $log_data['preventivo_id'] = $preventivo_id;
            
            // Step 5: Salvataggio dati strutturati
            $this->salva_dati_preventivo($preventivo_id, $dati_preventivo);
            $log_data['salvataggio'] = 'OK';
            
            // Step 6: Sincronizzazione WooCommerce
            $this->sincronizza_woocommerce($preventivo_id, $dati_preventivo);
            $log_data['wc_sync'] = 'OK';
            
            // Step 7: Hook per estensibilità
            do_action('btr_dopo_creazione_preventivo', $preventivo_id, $dati_preventivo);
            
            // Step 8: Genera PDF (opzionale)
            $pdf_url = apply_filters('btr_genera_pdf_preventivo', '', $preventivo_id);
            
            // Step 9: Invia email (opzionale)
            do_action('btr_invia_email_preventivo', $preventivo_id, $pdf_url);
            
            // Step 10: Risposta successo
            $redirect_url = add_query_arg('preventivo_id', $preventivo_id, home_url('/riepilogo-preventivo/'));
            
            // Log finale con timing
            $log_data['execution_time'] = round((microtime(true) - $start_time) * 1000, 2) . 'ms';
            $log_data['status'] = 'SUCCESS';
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[BTR v1.0.157] Preventivo creato: ' . json_encode($log_data));
            }
            
            // Salva log nel meta per debug
            update_post_meta($preventivo_id, '_btr_creation_log', $log_data);
            
            wp_send_json_success([
                'message' => 'Preventivo creato con successo',
                'preventivo_id' => $preventivo_id,
                'pdf_url' => $pdf_url,
                'redirect_url' => $redirect_url,
                'debug' => (defined('WP_DEBUG') && WP_DEBUG) ? $log_data : null
            ]);
            
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[BTR v1.0.157] ERRORE creazione preventivo: ' . $e->getMessage());
                error_log('[BTR v1.0.157] Stack trace: ' . $e->getTraceAsString());
            }
            $this->gestisci_errore($e);
        }
    }
    
    /**
     * Validazione sicurezza
     */
    private function validate_security() {
        $nonce = '';
        if (isset($_POST['nonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_POST['nonce']));
        } elseif (isset($_POST['_wpnonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce']));
        }

        if (!$nonce || !wp_verify_nonce($nonce, 'btr_booking_form_nonce')) {
            throw new Exception(__('Nonce di sicurezza non valido', 'born-to-ride-booking'));
        }
    }
    
    /**
     * Parse completo del payload con mapping corretto dei campi
     */
    private function parse_payload_completo() {
        $dati = [];
        
        // ========== DATI CLIENTE ==========
        // v1.0.157: Supporta sia customer_telefono che cliente_telefono
        // Aggiornato: supporta fallback customer_nome/customer_email
        $dati['cliente'] = [
            'nome'     => sanitize_text_field($_POST['cliente_nome'] ?? $_POST['customer_nome'] ?? ''),
            'email'    => sanitize_email($_POST['cliente_email'] ?? $_POST['customer_email'] ?? ''),
            'telefono' => sanitize_text_field($_POST['customer_telefono'] ?? $_POST['cliente_telefono'] ?? ''),
        ];
        
        // ========== DATI PACCHETTO ==========
        $dati['pacchetto'] = [
            'id'         => intval($_POST['package_id'] ?? $_POST['pkg_package_id'] ?? 0),
            'nome'       => sanitize_text_field($_POST['nome_pacchetto'] ?? $_POST['pkg_nome_pacchetto'] ?? ''),
            'slug'       => sanitize_text_field($_POST['package_slug'] ?? ''),
            'sku'        => sanitize_text_field($_POST['sku'] ?? ''),
            'prodotto_id'=> intval($_POST['product_id'] ?? $_POST['pkg_product_id'] ?? 0),
            'variante_id'=> intval($_POST['variant_id'] ?? $_POST['pkg_variant_id'] ?? 0),
        ];
        
        // ========== DURATA (parsing corretto) ==========
        $durata_raw = $_POST['durata'] ?? $_POST['pkg_durata'] ?? '';
        $durata_giorni = 0;
        $durata_notti = 0;
        
        if (is_numeric($durata_raw)) {
            $durata_giorni = intval($durata_raw);
            $durata_notti = max(0, $durata_giorni - 1);
        } elseif (preg_match('/(\d+)\s*giorni/i', $durata_raw, $matches)) {
            $durata_giorni = intval($matches[1]);
            $durata_notti = max(0, $durata_giorni - 1);
        }
        
        $dati['durata'] = [
            'testo' => sanitize_text_field($durata_raw),
            'giorni' => $durata_giorni,
            'notti' => $durata_notti,
        ];
        
        // ========== DATE ==========
        // v1.0.157: Usa dates_check_in/dates_check_out dal payload, aggiunto fallback pkg_*
        $dati['date'] = [
            'check_in'      => sanitize_text_field($_POST['dates_check_in'] ?? $_POST['check_in_date'] ?? ''),
            'check_out'     => sanitize_text_field($_POST['dates_check_out'] ?? $_POST['check_out_date'] ?? ''),
            'viaggio'       => sanitize_text_field($_POST['selected_date'] ?? $_POST['pkg_selected_date'] ?? ''),
            'date_ranges_id'=> sanitize_text_field($_POST['date_ranges_id'] ?? $_POST['pkg_date_ranges_id'] ?? ''),
        ];
        
        // ========== NOTTI EXTRA ==========
        // Cattura i valori raw; il totale verrà validato dopo il parsing dei partecipanti
        $extra_night_total_raw = floatval($_POST['extra_night_total'] ?? 0);
        $extra_night_pp        = floatval($_POST['extra_night_pp'] ?? 0);

        $dati['notti_extra'] = [
            'flag'               => intval($_POST['extra_night'] ?? 0),
            'data'               => sanitize_text_field($_POST['dates_extra_night'] ?? $_POST['extra_night_date'] ?? ''),
            'numero'             => intval($_POST['numero_notti_extra'] ?? 0),
            'prezzo_per_persona' => $this->fmt_price($extra_night_pp),
            'totale'             => $this->fmt_price($extra_night_total_raw),
        ];
        
        // ========== ESTRAZIONE DATI DA BOOKING_DATA_JSON ==========
        // Prima leggiamo il booking_data_json per estrarre dati aggiuntivi
        if (isset($_POST['booking_data_json'])) {
            $json_data = wp_unslash($_POST['booking_data_json']);
            $decoded = json_decode($json_data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $dati['booking_data_json'] = $decoded;
                
                // Estrai date dal JSON se non sono presenti nei POST diretti
                if (isset($decoded['dates'])) {
                    if (empty($dati['date']['check_in']) && !empty($decoded['dates']['check_in'])) {
                        $dati['date']['check_in'] = sanitize_text_field($decoded['dates']['check_in']);
                    }
                    if (empty($dati['date']['check_out']) && !empty($decoded['dates']['check_out'])) {
                        $dati['date']['check_out'] = sanitize_text_field($decoded['dates']['check_out']);
                    }
                    if (empty($dati['date']['viaggio']) && !empty($decoded['dates']['selected_date'])) {
                        $dati['date']['viaggio'] = sanitize_text_field($decoded['dates']['selected_date']);
                    }
                }
                
                // Estrai notti extra dal JSON se non sono presenti nei POST diretti
                if (isset($decoded['extra_nights'])) {
                    if (empty($dati['notti_extra']['flag']) && !empty($decoded['extra_nights']['enabled'])) {
                        $dati['notti_extra']['flag'] = intval($decoded['extra_nights']['enabled']);
                    }
                    if (empty($dati['notti_extra']['data']) && !empty($decoded['extra_nights']['date'])) {
                        $dati['notti_extra']['data'] = sanitize_text_field($decoded['extra_nights']['date']);
                    }
                    if (empty($dati['notti_extra']['numero']) && !empty($decoded['extra_nights']['nights_count'])) {
                        $dati['notti_extra']['numero'] = intval($decoded['extra_nights']['nights_count']);
                    }
                    if (empty($dati['notti_extra']['prezzo_per_persona']) && !empty($decoded['extra_nights']['price_per_person'])) {
                        $dati['notti_extra']['prezzo_per_persona'] = floatval($decoded['extra_nights']['price_per_person']);
                    }
                    if (empty($dati['notti_extra']['totale']) && !empty($decoded['extra_nights']['total_cost'])) {
                        $dati['notti_extra']['totale'] = floatval($decoded['extra_nights']['total_cost']);
                    }
                }
                
                // Estrai dai dettagli per notti extra se presenti nel pricing breakdown
                if (isset($decoded['pricing']['detailed_breakdown']['notti_extra'])) {
                    $notti_extra_detail = $decoded['pricing']['detailed_breakdown']['notti_extra'];
                    
                    // Aggiorna numero notti se disponibile
                    if (empty($dati['notti_extra']['numero']) && !empty($notti_extra_detail['numero_notti'])) {
                        $dati['notti_extra']['numero'] = intval($notti_extra_detail['numero_notti']);
                    }
                    
                    // Aggiorna totale notti extra se disponibile
                    if (empty($dati['notti_extra']['totale']) && !empty($notti_extra_detail['totale'])) {
                        $dati['notti_extra']['totale'] = floatval($notti_extra_detail['totale']);
                    }
                }
            }
        }
        
        // ========== LOGICA CHECK-IN DATE ADJUSTMENT ==========
        // Se la notte extra è abilitata, il check-in dovrebbe essere la data della notte extra
        if ($dati['notti_extra']['flag'] == 1 && !empty($dati['notti_extra']['data'])) {
            $dati['date']['check_in'] = $dati['notti_extra']['data'];
        }
        
        // ========== PARTECIPANTI ==========
        $dati['partecipanti'] = $this->parse_partecipanti();

        // FIX v1.0.169: Calcolo corretto notti extra con prezzi differenziati dal payload
        if (!empty($dati['notti_extra']['flag'])) {
            $num_adulti = intval($dati['partecipanti']['num_adulti']);
            $num_bambini = intval($dati['partecipanti']['num_bambini']);
            $num_notti = intval($dati['notti_extra']['numero']) ?: 1;
            
            // Usa i prezzi specifici dal payload se disponibili
            $prezzo_adulto = $this->parse_price($_POST['pricing_notti_extra_prezzo_adulto'] ?? $_POST['pricing_adulti_notte_extra_prezzo'] ?? 0);
            $supplemento_adulto = $this->parse_price($_POST['pricing_notti_extra_supplemento_adulto'] ?? $_POST['pricing_adulti_notte_extra_supplemento'] ?? 0);
            
            // Prezzi bambini per fascia dal payload
            $prezzo_bambino_f1 = $this->parse_price($_POST['pricing_bambini_f1_notte_extra_prezzo'] ?? 0);
            $supplemento_bambino_f1 = $this->parse_price($_POST['pricing_bambini_f1_notte_extra_supplemento'] ?? 0);
            $prezzo_bambino_f2 = $this->parse_price($_POST['pricing_bambini_f2_notte_extra_prezzo'] ?? 0);
            $supplemento_bambino_f2 = $this->parse_price($_POST['pricing_bambini_f2_notte_extra_supplemento'] ?? 0);
            $prezzo_bambino_f3 = $this->parse_price($_POST['pricing_bambini_f3_notte_extra_prezzo'] ?? 0);
            $supplemento_bambino_f3 = $this->parse_price($_POST['pricing_bambini_f3_notte_extra_supplemento'] ?? 0);
            $prezzo_bambino_f4 = $this->parse_price($_POST['pricing_bambini_f4_notte_extra_prezzo'] ?? 0);
            $supplemento_bambino_f4 = $this->parse_price($_POST['pricing_bambini_f4_notte_extra_supplemento'] ?? 0);
            
            // Calcola totale per adulti
            $totale_adulti = ($prezzo_adulto + $supplemento_adulto) * $num_adulti * $num_notti;
            
            // Calcola totale per bambini per fascia
            $totale_bambini = 0;
            if ($dati['partecipanti']['bambini_f1'] > 0) {
                $totale_bambini += ($prezzo_bambino_f1 + $supplemento_bambino_f1) * $dati['partecipanti']['bambini_f1'] * $num_notti;
            }
            if ($dati['partecipanti']['bambini_f2'] > 0) {
                $totale_bambini += ($prezzo_bambino_f2 + $supplemento_bambino_f2) * $dati['partecipanti']['bambini_f2'] * $num_notti;
            }
            if ($dati['partecipanti']['bambini_f3'] > 0) {
                $totale_bambini += ($prezzo_bambino_f3 + $supplemento_bambino_f3) * $dati['partecipanti']['bambini_f3'] * $num_notti;
            }
            if ($dati['partecipanti']['bambini_f4'] > 0) {
                $totale_bambini += ($prezzo_bambino_f4 + $supplemento_bambino_f4) * $dati['partecipanti']['bambini_f4'] * $num_notti;
            }
            
            // FIX v1.0.171: Validazione rigida totale notti extra con soglia €5
            // Il totale corretto deve includere adulti + bambini per fascia
            $totale_calcolato = $totale_adulti + $totale_bambini;
            
            // Se c'è un totale nel payload, verificalo contro il calcolato
            $totale_dal_payload = floatval($_POST['pricing_totale_notti_extra'] ?? $_POST['pricing_subtotale_notti_extra'] ?? 0);
            
            // Usa soglia di €5 per determinare se c'è discrepanza significativa
            if ($totale_dal_payload > 0 && abs($totale_dal_payload - $totale_calcolato) <= 5.00) {
                // Usa il totale dal payload se la differenza è minima (≤€5)
                $dati['notti_extra']['totale'] = $this->fmt_price($totale_dal_payload);
            } else {
                // Usa sempre il totale calcolato se la differenza è significativa (>€5)
                $dati['notti_extra']['totale'] = $this->fmt_price($totale_calcolato);
                
                if (defined('WP_DEBUG') && WP_DEBUG && $totale_dal_payload > 0 && abs($totale_dal_payload - $totale_calcolato) > 5.00) {
                    error_log('[BTR v1.0.171] DISCREPANZA NOTTI EXTRA: Payload €' . $totale_dal_payload . 
                             ' vs Calcolato €' . $totale_calcolato . ' (differenza: €' . abs($totale_dal_payload - $totale_calcolato) . ')');
                }
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[BTR v1.0.170] Notti extra - Adulti: ' . $num_adulti . ' (€' . $totale_adulti . ')' .
                         ', Bambini: ' . $num_bambini . ' (€' . $totale_bambini . ')' .
                         ', Totale: €' . $dati['notti_extra']['totale']);
            }
        }
        
        // ========== CAMERE ==========
        $dati['camere'] = $this->parse_camere();
        
        // ========== PREZZI (con mapping corretto dai campi reali) ==========
        // Parsing robusto per evitare errori tipo "1.279" interpretato come 1.279
        $prezzo_per_persona = $this->parse_price($_POST['pricing_adulti_prezzo_unitario'] ?? $_POST['price_per_person'] ?? 0);
        $totale_base        = $this->parse_price($_POST['pricing_subtotale_prezzi_base'] ?? $_POST['totale_base'] ?? 0);
        $totale_camere      = $this->parse_price($_POST['pricing_totale_camere'] ?? 0);
        $totale_extra       = $this->parse_price($_POST['pricing_totale_costi_extra'] ?? 0);
        $totale_assicuraz   = $this->parse_price($_POST['totale_assicurazioni'] ?? 0);
        $totale_gen_input   = $this->parse_price($_POST['pricing_totale_generale'] ?? $_POST['totale_preventivo'] ?? 0);
        $totale_notti_extra = $this->parse_price($_POST['pricing_totale_notti_extra'] ?? $_POST['pricing_subtotale_notti_extra'] ?? 0);

        // Preferisci breakdown dettagliato se disponibile
        $totale_from_breakdown = null;
        if (!empty($dati['booking_data_json']['pricing']['detailed_breakdown']['totali']['totale_generale'])) {
            $totale_from_breakdown = (float) $dati['booking_data_json']['pricing']['detailed_breakdown']['totali']['totale_generale'];
        } elseif (isset($_POST['pricing_breakdown_totale_generale'])) {
            $totale_from_breakdown = $this->parse_price($_POST['pricing_breakdown_totale_generale']);
        }

        if ($totale_from_breakdown !== null && $totale_from_breakdown > 0) {
            $totale_generale = $totale_from_breakdown;
        } else {
            $composto = $totale_camere + $totale_extra + $totale_assicuraz;
            $totale_generale = ($totale_gen_input > 0 && abs($composto - $totale_gen_input) <= 5)
                ? $totale_gen_input
                : ($composto > 0 ? $composto : $totale_gen_input);
        }

        $dati['prezzi'] = [
            'prezzo_per_persona' => $this->fmt_price($prezzo_per_persona),
            'totale_base'        => $this->fmt_price($totale_base),
            'totale_camere'      => $this->fmt_price($totale_camere),
            'totale_costi_extra' => $this->fmt_price($totale_extra),
            'totale_assicurazioni'=> $this->fmt_price($totale_assicuraz),
            'totale_generale'    => $this->fmt_price($totale_generale),
            'totale_notti_extra' => $this->fmt_price($totale_notti_extra),
        ];
        
        // ========== ANAGRAFICI ==========
        $dati['anagrafici'] = $this->parse_anagrafici();
        // Fallback telefono cliente dal primo anagrafico se mancante
        if (empty($dati['cliente']['telefono']) && !empty($dati['anagrafici'][0]['telefono'])) {
            $dati['cliente']['telefono'] = sanitize_text_field($dati['anagrafici'][0]['telefono']);
        }
        
        // Il booking_data_json è già stato processato sopra, non serve riprocessarlo
        
        // ========== CATEGORIE BAMBINI ==========
        if (isset($_POST['child_categories'])) {
            $child_cats = wp_unslash($_POST['child_categories']);
            $decoded = json_decode($child_cats, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $dati['categorie_bambini'] = $decoded;
            }
        }
        
        // ========== METODO PAGAMENTO ==========
        $dati['metodo_pagamento'] = sanitize_text_field($_POST['payment_method'] ?? 'full_payment');
        
        // ========== DATI SESSIONE ==========
        $dati['sessione'] = [
            'hash' => sanitize_text_field($_POST['session_hash'] ?? ''),
            'cart_item_key' => sanitize_text_field($_POST['cart_item_key'] ?? ''),
        ];
        
        return apply_filters('btr_dati_preventivo_parsed', $dati);
    }
    
    /**
     * Parse partecipanti con conteggi corretti (priorità nuovi campi, fallback legacy)
     */
    private function parse_partecipanti() {
        // Priorità ai nuovi campi participants_*
        $ad  = isset($_POST['participants_adults'])  ? intval($_POST['participants_adults'])  : null;
        $inf = isset($_POST['participants_infants']) ? intval($_POST['participants_infants']) : null;

        // Categorie bambini (nuovi nomi con fallback ai vecchi)
        $f1 = intval($_POST['participants_children_f1'] ?? $_POST['num_child_f1'] ?? 0);
        $f2 = intval($_POST['participants_children_f2'] ?? $_POST['num_child_f2'] ?? 0);
        $f3 = intval($_POST['participants_children_f3'] ?? $_POST['num_child_f3'] ?? 0);
        $f4 = intval($_POST['participants_children_f4'] ?? $_POST['num_child_f4'] ?? 0);
        $children_from_cats = $f1 + $f2 + $f3 + $f4;

        // Fallback legacy se i nuovi campi non ci sono
        $ad  = $ad  ?? intval($_POST['num_adults'] ?? 0);
        $inf = $inf ?? intval($_POST['num_infants'] ?? 0);

        // FIX v1.0.171: Rimuove fallback errato num_children che potrebbe contenere valore obsoleto
        // Usa SEMPRE la somma delle fasce o participants_children_total
        $num_children = $children_from_cats > 0
            ? $children_from_cats
            : intval($_POST['participants_children_total'] ?? 0);

        $partecipanti = [
            'num_adulti'  => $ad,
            'num_bambini' => $num_children,
            'num_neonati' => $inf,
            'bambini_f1'  => $f1,
            'bambini_f2'  => $f2,
            'bambini_f3'  => $f3,
            'bambini_f4'  => $f4,
            'dettagli'    => [],
            'etichette_bambini' => [] // Aggiungo array per etichette dal pacchetto
        ];
        
        // v1.0.188: PRIMA controlla se abbiamo etichette dal frontend
        $has_frontend_labels = false;
        
        if (!empty($_POST['child_labels_f1']) || !empty($_POST['child_labels_f2']) || 
            !empty($_POST['child_labels_f3']) || !empty($_POST['child_labels_f4'])) {
            
            // Usa le etichette dal frontend se disponibili
            if (!empty($_POST['child_labels_f1'])) {
                $partecipanti['etichette_bambini']['f1'] = sanitize_text_field($_POST['child_labels_f1']);
                $has_frontend_labels = true;
            }
            if (!empty($_POST['child_labels_f2'])) {
                $partecipanti['etichette_bambini']['f2'] = sanitize_text_field($_POST['child_labels_f2']);
                $has_frontend_labels = true;
            }
            if (!empty($_POST['child_labels_f3'])) {
                $partecipanti['etichette_bambini']['f3'] = sanitize_text_field($_POST['child_labels_f3']);
                $has_frontend_labels = true;
            }
            if (!empty($_POST['child_labels_f4'])) {
                $partecipanti['etichette_bambini']['f4'] = sanitize_text_field($_POST['child_labels_f4']);
                $has_frontend_labels = true;
            }
            
            btr_debug_log('[BTR v1.0.188] Etichette dal FRONTEND trovate: ' . json_encode($partecipanti['etichette_bambini']));
        }
        
        // Solo se NON abbiamo etichette dal frontend, usa quelle dal pacchetto
        if (!$has_frontend_labels && class_exists('BTR_Dynamic_Child_Categories')) {
            $package_id = intval($_POST['pkg_package_id'] ?? $_POST['package_id'] ?? 0);
            if ($package_id) {
                $child_categories_manager = new BTR_Dynamic_Child_Categories();
                $child_categories = $child_categories_manager->get_categories(true, $package_id);
                
                // Salva le etichette corrette dal pacchetto come fallback
                foreach ($child_categories as $cat) {
                    $partecipanti['etichette_bambini'][$cat['id']] = $cat['label'];
                }
                
                btr_debug_log('[BTR v1.0.188] Usando etichette dal PACCHETTO come fallback');
            }
        }

        $partecipanti['totale'] = $partecipanti['num_adulti'] + $partecipanti['num_bambini'] + $partecipanti['num_neonati'];
        // Distinguo tra paganti (adulti+bambini) e viaggiatori (inclusi i neonati)
        $partecipanti['tot_paganti']      = $partecipanti['num_adulti'] + $partecipanti['num_bambini'];
        $partecipanti['tot_viaggiatori']  = $partecipanti['totale'];

        // Warning se participants_total_people (FE) è discordante dal totale paganti (adulti+bambini)
        if (isset($_POST['participants_total_people'])) {
            $declared = intval($_POST['participants_total_people']);
            if ($declared !== $partecipanti['tot_paganti'] && defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[BTR v4] Avviso: participants_total_people=' . $declared . ' != paganti(calcolato)=' . $partecipanti['tot_paganti'] . ' (tot_viaggiatori=' . $partecipanti['tot_viaggiatori'] . ')');
            }
        }

        return $partecipanti;
    }
    
    /**
     * Parse camere selezionate
     */
    private function parse_camere() {
        $camere = [];
        
        if (isset($_POST['camere'])) {
            $camere_raw = $_POST['camere'];
            
            // Se è JSON, decodifica
            if (is_string($camere_raw)) {
                $decoded = json_decode(wp_unslash($camere_raw), true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $camere_raw = $decoded;
                }
            }
            
            // Parse ogni camera
            if (is_array($camere_raw)) {
                foreach ($camere_raw as $camera) {
                    $camere[] = [
                        'variation_id' => intval($camera['variation_id'] ?? 0),
                        'tipo' => sanitize_text_field($camera['tipo'] ?? ''),
                        'sottotipo' => sanitize_text_field($camera['sottotipo'] ?? ''),
                        'quantita' => intval($camera['quantita'] ?? 0),
                        // adulti/bambini/neonati (fallback) - FIX: priorità corretta assigned_adults
                        'adulti' => intval($camera['assigned_adults'] ?? $camera['adulti'] ?? 0),
                        'bambini' => intval($camera['bambini'] ?? 0),
                        'neonati' => intval($camera['neonati'] ?? $camera['assigned_infants'] ?? 0),
                        'prezzo_base' => floatval($camera['prezzo_per_persona'] ?? 0),
                        'supplemento' => floatval($camera['supplemento'] ?? 0),
                        'sconto' => floatval($camera['sconto'] ?? 0),
                        'totale' => floatval($camera['totale_camera'] ?? 0),
                        // Mappa assegnazioni per fascia, se presenti
                        'assigned_adults'    => intval($camera['assigned_adults'] ?? $camera['adulti'] ?? 0),
                        'bambini_f1_assegnati' => intval($camera['assigned_child_f1'] ?? 0),
                        'bambini_f2_assegnati' => intval($camera['assigned_child_f2'] ?? 0),
                        'bambini_f3_assegnati' => intval($camera['assigned_child_f3'] ?? 0),
                        'bambini_f4_assegnati' => intval($camera['assigned_child_f4'] ?? 0),
                    ];
                }
            }
        }
        
        return $camere;
    }
    
    /**
     * Parse dati anagrafici
     */
    private function parse_anagrafici() {
        $anagrafici = [];
        
        if (isset($_POST['anagrafici']) && is_array($_POST['anagrafici'])) {
            foreach ($_POST['anagrafici'] as $persona) {
                $anagrafica = [
                    'nome' => sanitize_text_field($persona['nome'] ?? ''),
                    'cognome' => sanitize_text_field($persona['cognome'] ?? ''),
                    'email' => sanitize_email($persona['email'] ?? ''),
                    'telefono' => sanitize_text_field($persona['telefono'] ?? ''),
                    'data_nascita' => sanitize_text_field($persona['data_nascita'] ?? ''),
                    'citta_nascita' => sanitize_text_field($persona['citta_nascita'] ?? ''),
                    'codice_fiscale' => sanitize_text_field($persona['codice_fiscale'] ?? ''),
                    'indirizzo' => sanitize_text_field($persona['indirizzo_residenza'] ?? ''),
                    'numero_civico' => sanitize_text_field($persona['numero_civico'] ?? ''),
                    'citta' => sanitize_text_field($persona['citta_residenza'] ?? ''),
                    'provincia' => sanitize_text_field($persona['provincia_residenza'] ?? ''),
                    'cap' => sanitize_text_field($persona['cap_residenza'] ?? ''),
                    'nazione' => sanitize_text_field($persona['nazione'] ?? 'IT'),
                    'camera' => intval($persona['camera'] ?? 0),
                    'camera_tipo' => sanitize_text_field($persona['camera_tipo'] ?? ''),
                    'tipo_letto' => sanitize_text_field($persona['tipo_letto'] ?? ''),
                    'fascia' => sanitize_text_field($persona['fascia'] ?? ''),
                    'eta' => intval($persona['age'] ?? 0),
                ];
                
                // Parse costi extra
                if (!empty($persona['costi_extra'])) {
                    $costi_extra = $persona['costi_extra'];
                    if (is_string($costi_extra)) {
                        $costi_extra = json_decode(wp_unslash($costi_extra), true);
                    }
                    $anagrafica['costi_extra'] = $costi_extra ?: [];
                }
                
                // Parse assicurazioni
                if (!empty($persona['assicurazioni'])) {
                    $assicurazioni = $persona['assicurazioni'];
                    if (is_string($assicurazioni)) {
                        $assicurazioni = json_decode(wp_unslash($assicurazioni), true);
                    }
                    $anagrafica['assicurazioni'] = $assicurazioni ?: [];
                }
                
                $anagrafici[] = $anagrafica;
            }
        }
        
        return $anagrafici;
    }
    
    /**
     * Validazione dati preventivo
     */
    private function valida_dati_preventivo($dati) {
        // v1.0.157: Validazione completa del payload
        $warnings = [];
        
        // Validazioni obbligatorie
        if (empty($dati['cliente']['nome'])) {
            throw new Exception('Nome cliente obbligatorio');
        }
        
        if (!is_email($dati['cliente']['email'])) {
            throw new Exception('Email cliente non valida');
        }
        
        if (empty($dati['pacchetto']['id'])) {
            throw new Exception('ID pacchetto obbligatorio');
        }
        
        if ($dati['partecipanti']['totale'] < 1) {
            throw new Exception('Almeno un partecipante richiesto');
        }
        
        // Validazioni avanzate non bloccanti
        if (empty($dati['cliente']['telefono'])) {
            $warnings[] = 'Telefono cliente mancante';
        }
        
        // Verifica bambini per categoria
        $totale_bambini_cat = $dati['partecipanti']['bambini_f1'] + $dati['partecipanti']['bambini_f2'] + 
                              $dati['partecipanti']['bambini_f3'] + $dati['partecipanti']['bambini_f4'];
        if ($dati['partecipanti']['num_bambini'] > 0 && $totale_bambini_cat == 0) {
            $warnings[] = "Bambini={$dati['partecipanti']['num_bambini']} ma categorie f1-f4 vuote";
        }
        
        // Verifica notti extra
        if ($dati['notti_extra']['flag'] == 1 && $dati['notti_extra']['totale'] == 0) {
            $warnings[] = 'Notti extra abilitate ma totale = 0';
        }
        
        // Verifica date
        if (empty($dati['date']['check_in'])) {
            $warnings[] = 'Data check-in mancante';
        }

        // Verifica coerenza assegnazioni camere vs partecipanti (nessuna correzione automatica)
        $sum = [
            'adulti'  => 0,
            'f1'      => 0,
            'f2'      => 0,
            'f3'      => 0,
            'f4'      => 0,
            'neonati' => 0,
        ];
        if (!empty($dati['camere']) && is_array($dati['camere'])) {
            foreach ($dati['camere'] as $idx => $room) {
                $sum['adulti']  += intval($room['adulti'] ?? $room['assigned_adults'] ?? 0);
                $sum['f1']      += intval($room['bambini_f1_assegnati'] ?? $room['assigned_child_f1'] ?? 0);
                $sum['f2']      += intval($room['bambini_f2_assegnati'] ?? $room['assigned_child_f2'] ?? 0);
                $sum['f3']      += intval($room['bambini_f3_assegnati'] ?? $room['assigned_child_f3'] ?? 0);
                $sum['f4']      += intval($room['bambini_f4_assegnati'] ?? $room['assigned_child_f4'] ?? 0);
                $sum['neonati'] += intval($room['neonati'] ?? $room['assigned_infants'] ?? 0);
            }
        }
        $expected = [
            'adulti'  => intval($dati['partecipanti']['num_adulti'] ?? 0),
            'f1'      => intval($dati['partecipanti']['bambini_f1'] ?? 0),
            'f2'      => intval($dati['partecipanti']['bambini_f2'] ?? 0),
            'f3'      => intval($dati['partecipanti']['bambini_f3'] ?? 0),
            'f4'      => intval($dati['partecipanti']['bambini_f4'] ?? 0),
            'neonati' => intval($dati['partecipanti']['num_neonati'] ?? 0),
        ];

        $mismatch = [];
        foreach ($expected as $k => $v) {
            if ($v !== $sum[$k]) {
                $mismatch[$k] = [ 'attesi' => $v, 'assegnati' => $sum[$k] ];
            }
        }
        if (!empty($mismatch)) {
            // Errore bloccante: l’assegnazione inviata dal frontend non è coerente
            $msg = 'Assegnazione partecipanti incoerente: ' . json_encode($mismatch);
            throw new Exception($msg);
        }
        
        // Log warnings
        if (!empty($warnings) && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[BTR v1.0.157] Validazione avvisi: ' . implode(' | ', $warnings));
        }
        
        // Hook per validazioni aggiuntive
        do_action('btr_valida_preventivo', $dati);
    }
    
    /**
     * Crea il post del preventivo
     */
    private function crea_post_preventivo($dati) {
        $post_data = [
            'post_type' => self::CPT,
            'post_title' => sprintf(
                'Preventivo per %s - %s - %s',
                $dati['cliente']['nome'],
                $dati['pacchetto']['nome'],
                current_time('d/m/Y H:i:s')
            ),
            'post_status' => 'publish',
            'post_author' => get_current_user_id() ?: 1
        ];
        
        $preventivo_id = wp_insert_post($post_data);
        
        if (is_wp_error($preventivo_id)) {
            throw new Exception('Errore creazione preventivo: ' . $preventivo_id->get_error_message());
        }
        
        return $preventivo_id;
    }
    
    /**
     * Salva tutti i dati del preventivo con naming italiano e best practices
     */
    private function salva_dati_preventivo($preventivo_id, $dati) {
        // ========== 1. SALVA JSON COMPLETO (Single Source of Truth) ==========
        $json_data = wp_json_encode($dati);
        update_post_meta($preventivo_id, self::META_PREFIX . 'dati_completi_json', wp_slash($json_data));
        update_post_meta($preventivo_id, self::META_PREFIX . 'versione', self::VERSION);
        update_post_meta($preventivo_id, self::META_PREFIX . 'timestamp', current_time('mysql'));
        
        // v1.0.156: Salva TUTTI i campi del payload come meta separati
        $this->salva_payload_completo($preventivo_id, $dati);
        
        // ========== 2. DATI CLIENTE ==========
        update_post_meta($preventivo_id, self::META_PREFIX . 'cliente_nome', $dati['cliente']['nome']);
        update_post_meta($preventivo_id, self::META_PREFIX . 'cliente_email', $dati['cliente']['email']);
        update_post_meta($preventivo_id, self::META_PREFIX . 'cliente_telefono', $dati['cliente']['telefono']);
        
        // Retrocompatibilità
        update_post_meta($preventivo_id, '_cliente_nome', $dati['cliente']['nome']);
        update_post_meta($preventivo_id, '_cliente_email', $dati['cliente']['email']);
        update_post_meta($preventivo_id, '_cliente_telefono', $dati['cliente']['telefono']);
        
        // ========== 3. DATI PACCHETTO ==========
        update_post_meta($preventivo_id, self::META_PREFIX . 'pacchetto_id', $dati['pacchetto']['id']);
        update_post_meta($preventivo_id, self::META_PREFIX . 'pacchetto_nome', $dati['pacchetto']['nome']);
        update_post_meta($preventivo_id, self::META_PREFIX . 'pacchetto_slug', $dati['pacchetto']['slug']);
        update_post_meta($preventivo_id, self::META_PREFIX . 'prodotto_id', $dati['pacchetto']['prodotto_id']);
        update_post_meta($preventivo_id, self::META_PREFIX . 'variante_id', $dati['pacchetto']['variante_id']);
        
        // FIX v1.0.173: Rimosso _package_id duplicato, manteniamo solo _pacchetto_id per retrocompatibilità
        update_post_meta($preventivo_id, '_pacchetto_id', $dati['pacchetto']['id']);
        
        // ========== 4. DURATA ==========
        update_post_meta($preventivo_id, self::META_PREFIX . 'durata', $dati['durata']['testo']);
        update_post_meta($preventivo_id, self::META_PREFIX . 'durata_giorni', $dati['durata']['giorni']);
        update_post_meta($preventivo_id, self::META_PREFIX . 'durata_notti', $dati['durata']['notti']);
        
        // Retrocompatibilità
        update_post_meta($preventivo_id, '_durata', $dati['durata']['testo']);
        update_post_meta($preventivo_id, '_duration_days', $dati['durata']['giorni']);
        update_post_meta($preventivo_id, '_duration_nights', $dati['durata']['notti']);
        
        // ========== 5. DATE ==========
        update_post_meta($preventivo_id, self::META_PREFIX . 'data_check_in', $dati['date']['check_in']);
        update_post_meta($preventivo_id, self::META_PREFIX . 'data_check_out', $dati['date']['check_out']);
        update_post_meta($preventivo_id, self::META_PREFIX . 'data_viaggio', $dati['date']['viaggio']);
        update_post_meta($preventivo_id, self::META_PREFIX . 'date_ranges_id', $dati['date']['date_ranges_id']);
        
        // Retrocompatibilità
        update_post_meta($preventivo_id, '_check_in_date', $dati['date']['check_in']);
        update_post_meta($preventivo_id, '_check_out_date', $dati['date']['check_out']);
        update_post_meta($preventivo_id, '_data_pacchetto', $dati['date']['viaggio']);
        
        // ========== 6. NOTTI EXTRA ==========
        update_post_meta($preventivo_id, self::META_PREFIX . 'notti_extra_flag', $dati['notti_extra']['flag']);
        update_post_meta($preventivo_id, self::META_PREFIX . 'notti_extra_data', $dati['notti_extra']['data']);
        update_post_meta($preventivo_id, self::META_PREFIX . 'notti_extra_numero', $dati['notti_extra']['numero']);
        update_post_meta($preventivo_id, self::META_PREFIX . 'notti_extra_prezzo_pp', $dati['notti_extra']['prezzo_per_persona']);
        update_post_meta($preventivo_id, self::META_PREFIX . 'notti_extra_totale', $dati['notti_extra']['totale']);
        
        // Retrocompatibilità
        update_post_meta($preventivo_id, '_extra_night_flag', $dati['notti_extra']['flag']);
        update_post_meta($preventivo_id, '_extra_night_date', $dati['notti_extra']['data']);
        update_post_meta($preventivo_id, '_numero_notti_extra', $dati['notti_extra']['numero']);
        
        // ========== 7. PARTECIPANTI ==========
        update_post_meta($preventivo_id, self::META_PREFIX . 'num_adulti', $dati['partecipanti']['num_adulti']);
        update_post_meta($preventivo_id, self::META_PREFIX . 'num_bambini', $dati['partecipanti']['num_bambini']);
        update_post_meta($preventivo_id, self::META_PREFIX . 'num_neonati', $dati['partecipanti']['num_neonati']);
        update_post_meta($preventivo_id, self::META_PREFIX . 'totale_persone', $dati['partecipanti']['totale']);
        // Totali distinti: paganti vs viaggiatori (inclusi neonati)
        update_post_meta($preventivo_id, self::META_PREFIX . 'totale_paganti', $dati['partecipanti']['tot_paganti'] ?? ($dati['partecipanti']['num_adulti'] + $dati['partecipanti']['num_bambini']));
        update_post_meta($preventivo_id, self::META_PREFIX . 'totale_viaggiatori', $dati['partecipanti']['tot_viaggiatori'] ?? $dati['partecipanti']['totale']);
        
        // Categorie bambini
        update_post_meta($preventivo_id, self::META_PREFIX . 'bambini_f1', $dati['partecipanti']['bambini_f1']);
        update_post_meta($preventivo_id, self::META_PREFIX . 'bambini_f2', $dati['partecipanti']['bambini_f2']);
        update_post_meta($preventivo_id, self::META_PREFIX . 'bambini_f3', $dati['partecipanti']['bambini_f3']);
        update_post_meta($preventivo_id, self::META_PREFIX . 'bambini_f4', $dati['partecipanti']['bambini_f4']);
        
        // FIX v1.0.169: Salvataggio unificato partecipanti - evita duplicazioni
        // Usa solo il prefisso standard "_num_" per la retrocompatibilità
        update_post_meta($preventivo_id, '_num_adults', $dati['partecipanti']['num_adulti']);
        update_post_meta($preventivo_id, '_num_children', $dati['partecipanti']['num_bambini']);
        update_post_meta($preventivo_id, '_num_neonati', $dati['partecipanti']['num_neonati']);
        update_post_meta($preventivo_id, '_num_paganti', $dati['partecipanti']['tot_paganti'] ?? ($dati['partecipanti']['num_adulti'] + $dati['partecipanti']['num_bambini']));
        
        // v1.0.188: Salva etichette categorie bambini (priorità frontend, fallback pacchetto)
        if (!empty($dati['partecipanti']['etichette_bambini'])) {
            update_post_meta($preventivo_id, '_child_category_labels', $dati['partecipanti']['etichette_bambini']);
            btr_debug_log('[BTR v1.0.188] Etichette salvate in _child_category_labels: ' . json_encode($dati['partecipanti']['etichette_bambini']));
            
            // Salva anche i campi individuali per compatibilità
            foreach ($dati['partecipanti']['etichette_bambini'] as $fascia => $label) {
                update_post_meta($preventivo_id, '_child_label_' . $fascia, $label);
            }
        }
        
        // ========== 8. CAMERE ==========
        update_post_meta($preventivo_id, self::META_PREFIX . 'camere_selezionate', $dati['camere']);
        
        // Retrocompatibilità
        update_post_meta($preventivo_id, '_camere_selezionate', $dati['camere']);
        
        // ========== 9. PREZZI ==========
        update_post_meta($preventivo_id, self::META_PREFIX . 'prezzo_per_persona', $this->fmt_price($dati['prezzi']['prezzo_per_persona']));
        update_post_meta($preventivo_id, self::META_PREFIX . 'totale_base', $this->fmt_price($dati['prezzi']['totale_base']));
        update_post_meta($preventivo_id, self::META_PREFIX . 'totale_camere', $this->fmt_price($dati['prezzi']['totale_camere']));
        update_post_meta($preventivo_id, self::META_PREFIX . 'totale_costi_extra', $this->fmt_price($dati['prezzi']['totale_costi_extra']));
        update_post_meta($preventivo_id, self::META_PREFIX . 'totale_assicurazioni', $this->fmt_price($dati['prezzi']['totale_assicurazioni']));
        update_post_meta($preventivo_id, self::META_PREFIX . 'totale_generale', $this->fmt_price($dati['prezzi']['totale_generale']));
        update_post_meta($preventivo_id, self::META_PREFIX . 'totale_notti_extra', $this->fmt_price($dati['prezzi']['totale_notti_extra']));

        // === TOTALE VIAGGIO (lordo) distinto dal totale da pagare ora ===
        $totale_viaggio = 0.0;
        // 1) Tentativo da POST breakdown aggregato (campo nuovo lato FE)
        if (isset($_POST['pricing_breakdown_totale_generale'])) {
            $totale_viaggio = $this->fmt_price($_POST['pricing_breakdown_totale_generale']);
        }
        // 2) Fallback dal booking_data_json detailed breakdown
        if (!$totale_viaggio && !empty($dati['booking_data_json']['pricing']['detailed_breakdown']['totali']['totale_generale'])) {
            $totale_viaggio = $this->fmt_price($dati['booking_data_json']['pricing']['detailed_breakdown']['totali']['totale_generale']);
        }
        // 3) Fallback legacy (prezzo_totale dal payload)
        if (!$totale_viaggio && isset($_POST['prezzo_totale'])) {
            $totale_viaggio = $this->fmt_price($_POST['prezzo_totale']);
        }
        update_post_meta($preventivo_id, self::META_PREFIX . 'totale_viaggio', $totale_viaggio);
        // Snapshot coerenza contabile per debug
        // ATTENZIONE: tot_camere è già ALL-IN (base + supp + notti extra)
        // quindi non sommiamo di nuovo 'totale_notti_extra' per evitare doppio conteggio
        $somma_componenti = $this->fmt_price(($dati['prezzi']['totale_camere'] ?? 0) + ($dati['prezzi']['totale_costi_extra'] ?? 0) + ($dati['prezzi']['totale_assicurazioni'] ?? 0));
        $delta_generale = $this->fmt_price($somma_componenti - ($dati['prezzi']['totale_generale'] ?? 0));
        update_post_meta($preventivo_id, self::META_PREFIX . 'coerenza_conti', [
            'totale_viaggio'   => $totale_viaggio,
            'totale_generale'  => $this->fmt_price($dati['prezzi']['totale_generale'] ?? 0),
            'somma_componenti' => $somma_componenti,
            'delta_generale'   => $delta_generale,
        ]);
        
        // Retrocompatibilità
        update_post_meta($preventivo_id, '_price_per_person', $dati['prezzi']['prezzo_per_persona']);
        update_post_meta($preventivo_id, '_totale_base', $dati['prezzi']['totale_base']);
        update_post_meta($preventivo_id, '_prezzo_totale', $this->fmt_price($dati['prezzi']['totale_generale']));
        update_post_meta($preventivo_id, '_totale_preventivo', $this->fmt_price($dati['prezzi']['totale_generale']));
        
        // ========== 10. ANAGRAFICI ==========
        // FIX v1.0.171: Rimuove duplicazione - salva solo con prefisso standard
        update_post_meta($preventivo_id, self::META_PREFIX . 'anagrafici', $dati['anagrafici']);
        
        // ========== 11. BOOKING DATA JSON ==========
        if (!empty($dati['booking_data_json'])) {
            update_post_meta($preventivo_id, self::META_PREFIX . 'booking_data_json', $dati['booking_data_json']);
            update_post_meta($preventivo_id, '_booking_data_json', $dati['booking_data_json']);
            
            // Estrai dati specifici se presenti
            $this->estrai_dati_booking_json($preventivo_id, $dati['booking_data_json']);
        }
        
        // ========== 12. CATEGORIE BAMBINI ==========
        if (!empty($dati['categorie_bambini'])) {
            update_post_meta($preventivo_id, self::META_PREFIX . 'categorie_bambini', $dati['categorie_bambini']);
            update_post_meta($preventivo_id, '_child_categories', $dati['categorie_bambini']);
        }
        
        // ========== 13. STATO E METODO PAGAMENTO ==========
        update_post_meta($preventivo_id, self::META_PREFIX . 'stato', 'creato');
        update_post_meta($preventivo_id, self::META_PREFIX . 'metodo_pagamento', $dati['metodo_pagamento']);
        
        // Retrocompatibilità
        update_post_meta($preventivo_id, '_stato_preventivo', 'creato');
        update_post_meta($preventivo_id, '_payment_method', $dati['metodo_pagamento']);
        
        // ========== 14. DATI SESSIONE ==========
        if (!empty($dati['sessione']['hash'])) {
            update_post_meta($preventivo_id, self::META_PREFIX . 'sessione_hash', $dati['sessione']['hash']);
            update_post_meta($preventivo_id, '_wc_session_hash', $dati['sessione']['hash']);
        }
        
        if (!empty($dati['sessione']['cart_item_key'])) {
            update_post_meta($preventivo_id, self::META_PREFIX . 'cart_item_key', $dati['sessione']['cart_item_key']);
            update_post_meta($preventivo_id, '_wc_cart_item_key', $dati['sessione']['cart_item_key']);
        }
        
        // Hook per salvare dati aggiuntivi
        do_action('btr_salva_dati_preventivo_aggiuntivi', $preventivo_id, $dati);
    }
    
    /**
     * v1.0.156: Salva OGNI campo del payload come meta separato per accesso diretto
     * Questo garantisce che TUTTI i dati siano facilmente recuperabili
     */
    private function salva_payload_completo($preventivo_id, $dati) {
        // ===== METADATA =====
        if (isset($_POST['metadata_timestamp'])) {
            update_post_meta($preventivo_id, '_metadata_timestamp', sanitize_text_field($_POST['metadata_timestamp']));
        }
        if (isset($_POST['metadata_user_agent'])) {
            update_post_meta($preventivo_id, '_metadata_user_agent', sanitize_text_field($_POST['metadata_user_agent']));
        }
        if (isset($_POST['metadata_url'])) {
            update_post_meta($preventivo_id, '_metadata_url', esc_url_raw($_POST['metadata_url']));
        }
        
        // ===== PACKAGE INFO =====
        $package_fields = [
            'pkg_package_id', 'pkg_product_id', 'pkg_variant_id', 'pkg_date_ranges_id',
            'pkg_nome_pacchetto', 'pkg_tipologia_prenotazione', 'pkg_durata', 'pkg_selected_date'
        ];
        foreach ($package_fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($preventivo_id, '_' . $field, sanitize_text_field($_POST[$field]));
            }
        }
        
        // ===== CUSTOMER =====
        // FIX v1.0.173: Rimossi campi duplicati - i dati cliente sono già salvati
        // nella sezione principale con prefisso _btr_cliente_*
        // Manteniamo solo _cliente_email per compatibilità con vecchi preventivi
        if (isset($_POST['customer_email'])) {
            update_post_meta($preventivo_id, '_cliente_email', sanitize_email($_POST['customer_email']));
        }
        
        // ===== PARTICIPANTS =====
        // FIX v1.0.169: Rimosso per evitare duplicazioni - i dati dei partecipanti
        // sono già salvati nella sezione principale con prefisso _num_
        // Manteniamo solo i dettagli per fascia che non sono salvati altrove
        $child_details = [
            'participants_children_f1', 'participants_children_f2', 
            'participants_children_f3', 'participants_children_f4'
        ];
        foreach ($child_details as $field) {
            if (isset($_POST[$field])) {
                // Salva con prefisso _btr_ per dettagli fasce
                $key = str_replace('participants_children_', '_btr_bambini_', $field);
                update_post_meta($preventivo_id, $key, intval($_POST[$field]));
            }
        }
        
        // ===== PRICING COMPLETO - TUTTI I CAMPI =====
        $pricing_fields = [
            'pricing_total_price', 'pricing_breakdown_available', 'pricing_totale_camere',
            'pricing_totale_costi_extra', 'pricing_totale_assicurazioni', 
            'pricing_totale_generale_display', 'pricing_totale_generale',
            'pricing_subtotale_prezzi_base', 'pricing_subtotale_supplementi_base',
            'pricing_subtotale_notti_extra', 'pricing_subtotale_supplementi_extra',
            'pricing_breakdown_totale_generale',
            'pricing_adulti_quantita', 'pricing_adulti_prezzo_unitario', 'pricing_adulti_totale',
            'pricing_bambini_f1_quantita', 'pricing_bambini_f1_prezzo_unitario', 'pricing_bambini_f1_totale',
            'pricing_bambini_f2_quantita', 'pricing_bambini_f2_prezzo_unitario', 'pricing_bambini_f2_totale',
            'pricing_bambini_f3_quantita', 'pricing_bambini_f3_prezzo_unitario', 'pricing_bambini_f3_totale',
            'pricing_bambini_f4_quantita', 'pricing_bambini_f4_prezzo_unitario', 'pricing_bambini_f4_totale'
        ];
        foreach ($pricing_fields as $field) {
            if (isset($_POST[$field])) {
                $value = $_POST[$field];
                // Numerico per campi prezzo/quantità (parsing robusto)
                if (strpos($field, 'prezzo') !== false || strpos($field, 'totale') !== false ||
                    strpos($field, 'quantita') !== false || strpos($field, 'price') !== false) {
                    $value = $this->fmt_price($this->parse_price($value));
                } else {
                    $value = sanitize_text_field($value);
                }
                update_post_meta($preventivo_id, '_' . $field, $value);
            }
        }
        
        // ===== NOTTI EXTRA CON PREZZI DIFFERENZIATI =====
        $extra_nights_fields = [
            'pricing_notti_extra_attive', 'pricing_notti_extra_numero',
            'pricing_notti_extra_prezzo_adulto', 'pricing_notti_extra_supplemento_adulto',
            'pricing_bambini_f1_notte_extra_prezzo', 'pricing_bambini_f1_notte_extra_supplemento',
            'pricing_bambini_f2_notte_extra_prezzo', 'pricing_bambini_f2_notte_extra_supplemento',
            'pricing_bambini_f3_notte_extra_prezzo', 'pricing_bambini_f3_notte_extra_supplemento',
            'pricing_bambini_f4_notte_extra_prezzo', 'pricing_bambini_f4_notte_extra_supplemento'
        ];
        foreach ($extra_nights_fields as $field) {
            if (isset($_POST[$field])) {
                $value = $this->fmt_price($this->parse_price($_POST[$field]));
                update_post_meta($preventivo_id, '_' . $field, $value);
            }
        }
        
        // Campi extra nights standard
        if (isset($_POST['extra_nights_enabled'])) {
            update_post_meta($preventivo_id, '_extra_nights_enabled', intval($_POST['extra_nights_enabled']));
        }
        if (isset($_POST['extra_nights_price_per_person'])) {
            update_post_meta($preventivo_id, '_extra_nights_price_per_person', floatval($_POST['extra_nights_price_per_person']));
        }
        
        // FIX v1.0.169 + robustezza: Salva totale notti extra con logica affidabile
        if (isset($_POST['extra_nights_enabled']) && intval($_POST['extra_nights_enabled']) === 1) {
            $totale_calcolato = isset($dati['notti_extra']['totale']) ? (float) $dati['notti_extra']['totale'] : 0.0;
            $totale_dal_payload = $this->parse_price($_POST['pricing_totale_notti_extra'] ?? $_POST['pricing_subtotale_notti_extra'] ?? 0);
            $totale_fallback = $this->parse_price($_POST['extra_nights_total_cost'] ?? $_POST['extra_night_total'] ?? 0);

            if ($totale_dal_payload > 0 && $totale_calcolato > 0 && abs($totale_dal_payload - $totale_calcolato) <= 5.00) {
                update_post_meta($preventivo_id, '_extra_nights_total_cost', $totale_dal_payload);
                update_post_meta($preventivo_id, '_extra_nights_total_cost_source', 'payload');
                update_post_meta($preventivo_id, '_extra_night_total', $totale_dal_payload);
                update_post_meta($preventivo_id, '_totale_notti_extra', $totale_dal_payload);
            } elseif ($totale_calcolato > 0) {
                update_post_meta($preventivo_id, '_extra_nights_total_cost', $totale_calcolato);
                update_post_meta($preventivo_id, '_extra_nights_total_cost_source', 'calculated');
                update_post_meta($preventivo_id, '_extra_night_total', $totale_calcolato);
                update_post_meta($preventivo_id, '_totale_notti_extra', $totale_calcolato);
            } elseif ($totale_fallback > 0) {
                update_post_meta($preventivo_id, '_extra_nights_total_cost', $totale_fallback);
                update_post_meta($preventivo_id, '_extra_nights_total_cost_source', 'fallback');
                update_post_meta($preventivo_id, '_extra_night_total', $totale_fallback);
                update_post_meta($preventivo_id, '_totale_notti_extra', $totale_fallback);
            }
            
            // Salva anche i dettagli se disponibili
            if (isset($_POST['pricing_notti_extra_prezzo_adulto'])) {
                update_post_meta($preventivo_id, '_extra_nights_adult_price', floatval($_POST['pricing_notti_extra_prezzo_adulto']));
                update_post_meta($preventivo_id, '_extra_nights_adult_supplement', floatval($_POST['pricing_notti_extra_supplemento_adulto'] ?? 0));
            }
            
            // Salva prezzi per fascia bambini se disponibili
            for ($i = 1; $i <= 4; $i++) {
                if (isset($_POST["pricing_bambini_f{$i}_notte_extra_prezzo"])) {
                    update_post_meta($preventivo_id, "_extra_nights_child_f{$i}_price", floatval($_POST["pricing_bambini_f{$i}_notte_extra_prezzo"]));
                    update_post_meta($preventivo_id, "_extra_nights_child_f{$i}_supplement", floatval($_POST["pricing_bambini_f{$i}_notte_extra_supplemento"] ?? 0));
                }
            }
        } elseif (isset($_POST['extra_nights_total_cost'])) {
            update_post_meta($preventivo_id, '_extra_nights_total_cost', $this->parse_price($_POST['extra_nights_total_cost']));
        }
        
        if (isset($_POST['extra_nights_date'])) {
            update_post_meta($preventivo_id, '_extra_nights_date', sanitize_text_field($_POST['extra_nights_date']));
        }

        // ===== CHILD LABELS (snapshot delle etichette inviate dal frontend) =====
        $child_labels = [];
        $has_front_labels = false;
        foreach (['f1','f2','f3','f4'] as $fx) {
            $k = 'child_labels_' . $fx;
            if (!empty($_POST[$k])) {
                $label = sanitize_text_field($_POST[$k]);
                update_post_meta($preventivo_id, '_child_label_' . $fx, $label);
                $child_labels[$fx] = $label;
                $has_front_labels = true;
            }
        }
        if ($has_front_labels) {
            update_post_meta($preventivo_id, '_child_category_labels', $child_labels);
        }
        
        // ===== DATE =====
        if (isset($_POST['dates_check_in'])) {
            update_post_meta($preventivo_id, '_dates_check_in', sanitize_text_field($_POST['dates_check_in']));
        }
        if (isset($_POST['dates_check_out'])) {
            update_post_meta($preventivo_id, '_dates_check_out', sanitize_text_field($_POST['dates_check_out']));
        }
        if (isset($_POST['dates_extra_night'])) {
            update_post_meta($preventivo_id, '_dates_extra_night', sanitize_text_field($_POST['dates_extra_night']));
        }
        
        // ===== ROOMS =====
        if (isset($_POST['rooms']) && is_array($_POST['rooms'])) {
            foreach ($_POST['rooms'] as $index => $room) {
                update_post_meta($preventivo_id, '_room_' . $index . '_type', sanitize_text_field($room['type'] ?? ''));
                update_post_meta($preventivo_id, '_room_' . $index . '_quantity', intval($room['quantity'] ?? 0));
                update_post_meta($preventivo_id, '_room_' . $index . '_capacity', intval($room['capacity'] ?? 0));
                update_post_meta($preventivo_id, '_room_' . $index . '_price', floatval($room['price'] ?? 0));
                update_post_meta($preventivo_id, '_room_' . $index . '_variation_id', intval($room['variation_id'] ?? 0));
                update_post_meta($preventivo_id, '_room_' . $index . '_supplemento', floatval($room['supplemento'] ?? 0));
            }
        }
        if (isset($_POST['rooms_count'])) {
            update_post_meta($preventivo_id, '_rooms_count', intval($_POST['rooms_count']));
        }

        // Debug: divergenza tra rooms[] (flat) e camere JSON (fonte autorevole)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $camere_json = $_POST['camere'] ?? null;
            if ($camere_json && is_string($camere_json)) {
                $camere_dec = json_decode(wp_unslash($camere_json), true);
                if (json_last_error() === JSON_ERROR_NONE && isset($_POST['rooms']) && is_array($_POST['rooms'])) {
                    $vid_rooms  = array_map(function($r){ return intval($r['variation_id'] ?? 0); }, $_POST['rooms']);
                    $vid_camere = array_map(function($r){ return intval($r['variation_id'] ?? 0); }, $camere_dec);
                    if ($vid_rooms !== $vid_camere) {
                        error_log('[BTR v1.0.158] Divergenza variation_id tra rooms[] e camere JSON: rooms=' . json_encode($vid_rooms) . ' vs camere=' . json_encode($vid_camere));
                    }
                }
            }
        }
        
        // ===== EXTRA COSTS PRICES (solo quelli SELEZIONATI) =====
        // v1.0.158: Corretto per salvare solo i costi extra effettivamente selezionati
        $selected_extra_costs = [];
        
        // Estrai i costi extra selezionati dagli anagrafici
        if (isset($_POST['anagrafici']) && is_array($_POST['anagrafici'])) {
            foreach ($_POST['anagrafici'] as $anagrafico) {
                if (isset($anagrafico['costi_extra']) && is_array($anagrafico['costi_extra'])) {
                    foreach ($anagrafico['costi_extra'] as $cost_key => $cost_data) {
                        if (isset($cost_data['selected']) && $cost_data['selected'] == '1' && isset($cost_data['price'])) {
                            $clean_key = str_replace('-', '_', sanitize_key($cost_key));
                            // Aggiungi al nostro array di costi selezionati (usa il valore se diverso da 0)
                            if (floatval($cost_data['price']) != 0) {
                                $selected_extra_costs[$clean_key] = floatval($cost_data['price']);
                            }
                        }
                    }
                }
            }
        }
        
        // Salva SOLO i costi extra selezionati
        foreach ($selected_extra_costs as $key => $price) {
            update_post_meta($preventivo_id, '_extra_cost_price_' . $key, $price);
        }
        
        // Salva l'array dei costi selezionati (non tutti)
        update_post_meta($preventivo_id, '_extra_costs_selected_array', $selected_extra_costs);
        
        // Salva il listino completo dei costi extra (se presente nel POST)
        if (isset($_POST['extra_costs_prices']) && is_array($_POST['extra_costs_prices'])) {
            $price_list = array_map('floatval', $_POST['extra_costs_prices']);
            update_post_meta($preventivo_id, '_extra_costs_price_list', $price_list);
        }
        
        // Log per debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[BTR v1.0.158] Costi extra selezionati: ' . print_r($selected_extra_costs, true));
        }
        
        // ===== TOTALS BREAKDOWN =====
        if (isset($_POST['totals_rooms'])) {
            update_post_meta($preventivo_id, '_totals_rooms', round((float) $_POST['totals_rooms'], 2));
        }
        if (isset($_POST['totals_extra_costs'])) {
            update_post_meta($preventivo_id, '_totals_extra_costs', round((float) $_POST['totals_extra_costs'], 2));
        }
        if (isset($_POST['totals_insurances'])) {
            update_post_meta($preventivo_id, '_totals_insurances', round((float) $_POST['totals_insurances'], 2));
        }
        if (isset($_POST['totals_grand_total'])) {
            update_post_meta($preventivo_id, '_totals_grand_total', round((float) $_POST['totals_grand_total'], 2));
        }
        if (isset($_POST['totals_display_total'])) {
            update_post_meta($preventivo_id, '_totals_display_total', round((float) $_POST['totals_display_total'], 2));
        }
        
        // ===== ANAGRAFICI CON COSTI EXTRA PER PERSONA =====
        if (isset($_POST['anagrafici']) && is_array($_POST['anagrafici'])) {
            foreach ($_POST['anagrafici'] as $index => $persona) {
                // Dati persona
                update_post_meta($preventivo_id, '_anagrafico_' . $index . '_nome', sanitize_text_field($persona['nome'] ?? ''));
                update_post_meta($preventivo_id, '_anagrafico_' . $index . '_cognome', sanitize_text_field($persona['cognome'] ?? ''));
                update_post_meta($preventivo_id, '_anagrafico_' . $index . '_email', sanitize_email($persona['email'] ?? ''));
                update_post_meta($preventivo_id, '_anagrafico_' . $index . '_telefono', sanitize_text_field($persona['telefono'] ?? ''));
                
                // Costi extra per questa persona
                if (isset($persona['costi_extra']) && is_array($persona['costi_extra'])) {
                    foreach ($persona['costi_extra'] as $extra_key => $extra_data) {
                        if (isset($extra_data['selected']) && $extra_data['selected']) {
                            $clean_key = str_replace('-', '_', sanitize_key($extra_key));
                            update_post_meta($preventivo_id, '_anagrafico_' . $index . '_extra_' . $clean_key . '_selected', 1);
                            update_post_meta($preventivo_id, '_anagrafico_' . $index . '_extra_' . $clean_key . '_price', round((float) ($extra_data['price'] ?? 0), 2));
                        }
                    }
                }
            }
        }
        if (isset($_POST['anagrafici_count'])) {
            update_post_meta($preventivo_id, '_anagrafici_count', intval($_POST['anagrafici_count']));
        }
        
        // ===== BOOKING DATA JSON (già decodificato) =====
        if (isset($_POST['booking_data_json'])) {
            $json_data = wp_unslash($_POST['booking_data_json']);
            $decoded = json_decode($json_data, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // Salva pricing dettagliato dal JSON
                if (isset($decoded['pricing']['detailed_breakdown'])) {
                    $breakdown = $decoded['pricing']['detailed_breakdown'];
                    
                    // Partecipanti breakdown
                    if (isset($breakdown['partecipanti'])) {
                        foreach ($breakdown['partecipanti'] as $tipo => $dati_tipo) {
                            if (is_array($dati_tipo)) {
                                foreach ($dati_tipo as $key => $value) {
                                    update_post_meta($preventivo_id, '_breakdown_' . $tipo . '_' . $key, $value);
                                }
                            }
                        }
                    }
                    
                    // Totali breakdown
                    if (isset($breakdown['totali'])) {
                        foreach ($breakdown['totali'] as $key => $value) {
                            update_post_meta($preventivo_id, '_breakdown_totali_' . $key, floatval($value));
                        }
                    }
                    
                    // Notti extra breakdown
                    if (isset($breakdown['notti_extra'])) {
                        foreach ($breakdown['notti_extra'] as $key => $value) {
                            update_post_meta($preventivo_id, '_breakdown_notti_extra_' . $key, $value);
                        }
                    }
                }
            }
        }
        
        // ===== ALTRI CAMPI DAL PAYLOAD =====
        $altri_campi = [
            'cliente_nome', 'cliente_email', 'package_id', 'product_id', 
            'variant_id', 'date_ranges_id', 'tipologia_prenotazione',
            'durata', 'nome_pacchetto', 'prezzo_totale', 'camere',
            'costi_extra_durata', 'num_adults', 'num_infants',
            'extra_night', 'selected_date', 'extra_night_pp', 'extra_night_total',
            'riepilogo_calcoli_dettagliato'
        ];
        
        // Gestione speciale per btr_extra_night_date 
        // (che dovrebbe essere 23 Gennaio non 25 Gennaio)
        // FIX v1.0.171: Priorità corretta date notti extra
        // Priorità: extra_nights_date (corretto) > dates_extra_night > btr_extra_night_date
        $extra_night_date = null;
        if (isset($_POST['extra_nights_date']) && !empty($_POST['extra_nights_date'])) {
            // PRIMA priorità: extra_nights_date (tipicamente contiene la data corretta)
            $extra_night_date = sanitize_text_field($_POST['extra_nights_date']);
        } elseif (isset($_POST['dates_extra_night']) && !empty($_POST['dates_extra_night'])) {
            // SECONDA priorità: dates_extra_night 
            $extra_night_date = sanitize_text_field($_POST['dates_extra_night']);
        } elseif (isset($_POST['btr_extra_night_date']) && !empty($_POST['btr_extra_night_date'])) {
            // TERZA priorità: btr_extra_night_date (può contenere check-out date)
            $extra_night_date = sanitize_text_field($_POST['btr_extra_night_date']);
        }
        
        if ($extra_night_date) {
            // Salva con tutti i prefissi per compatibilità
            update_post_meta($preventivo_id, '_btr_extra_night_date', $extra_night_date);
            update_post_meta($preventivo_id, '_extra_nights_date', $extra_night_date);
            update_post_meta($preventivo_id, '_dates_extra_night', $extra_night_date);
            update_post_meta($preventivo_id, '_payload_btr_extra_night_date', $extra_night_date);
        }
        
        // FIX v1.0.170: Calcolo corretto num_children - rimozione duplicazioni
        // Calcolo consolidato già salvato in parse_payload_completo
        // Non duplicare il salvataggio qui
        
        foreach ($altri_campi as $campo) {
            if (isset($_POST[$campo])) {
                $value = $_POST[$campo];
                
                // Deserializza se necessario
                if (is_string($value) && (strpos($value, '{') === 0 || strpos($value, '[') === 0)) {
                    $decoded = json_decode(wp_unslash($value), true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $value = $decoded;
                    }
                }
                
                update_post_meta($preventivo_id, '_payload_' . $campo, $value);
            }
        }
        
        // Log completamento salvataggio
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[BTR v1.0.156] Payload completo salvato per preventivo #' . $preventivo_id);
        }
    }
    
    /**
     * Estrai dati specifici dal booking_data_json
     */
    private function estrai_dati_booking_json($preventivo_id, $booking_data) {
        // Salva date e breakdown se presenti
        if (isset($booking_data['dates'])) {
            update_post_meta($preventivo_id, self::META_PREFIX . 'date_dettaglio', $booking_data['dates']);
        }
        if (isset($booking_data['pricing']['detailed_breakdown'])) {
            update_post_meta($preventivo_id, self::META_PREFIX . 'riepilogo_calcoli', $booking_data['pricing']['detailed_breakdown']);
            update_post_meta($preventivo_id, '_riepilogo_calcoli_dettagliato', $booking_data['pricing']['detailed_breakdown']);
        }

        // ===== Extra costs (nuovo formato) =====
        $price_list = $booking_data['extra_costs_prices']    ?? [];
        $duration   = $booking_data['extra_costs_duration']  ?? [];
        $anagrafici = $booking_data['anagrafici']            ?? [];

        $aggregato = [];
        $tot_extra = 0.0;

        // FIX v1.0.174: Aggregazione corretta costi extra - verifica 'attivo' e 'selected'
        // Per-person selections dagli anagrafici
        if (is_array($anagrafici)) {
            foreach ($anagrafici as $persona) {
                // Verifica costi_extra_dettagliate (formato nuovo)
                if (!empty($persona['costi_extra_dettagliate']) && is_array($persona['costi_extra_dettagliate'])) {
                    foreach ($persona['costi_extra_dettagliate'] as $key => $cfg) {
                        // Verifica che sia attivo E con importo > 0
                        if (!empty($cfg['attivo']) && $cfg['attivo'] === true && 
                            !empty($cfg['importo']) && floatval($cfg['importo']) > 0) {
                            
                            $unit = floatval($cfg['importo']);
                            $norm = str_replace('-', '_', sanitize_key($key));
                            if (!isset($aggregato[$norm])) {
                                $aggregato[$norm] = [
                                    'nome'            => $cfg['nome'] ?? ucwords(str_replace(['-', '_'], ' ', $key)),
                                    'quantita'        => 0,
                                    'prezzo_unitario' => $unit,
                                    'totale'          => 0.0,
                                ];
                            }
                            $aggregato[$norm]['quantita'] += 1;
                            $aggregato[$norm]['totale'] += $unit;
                            $tot_extra += $unit;
                        }
                    }
                }
                // Fallback su costi_extra (formato vecchio)
                else if (!empty($persona['costi_extra']) && is_array($persona['costi_extra'])) {
                    foreach ($persona['costi_extra'] as $key => $cfg) {
                        // FIX BUG 1: Verifica se il costo è selezionato E ha un valore > 0
                        $is_selected = (!empty($cfg['selected']) && $cfg['selected'] == '1') || (!empty($cfg['selected']) && $cfg['selected'] === true);
                        $has_price = isset($cfg['price']) && floatval($cfg['price']) > 0;
                        
                        if ($is_selected && $has_price) {
                            $unit = floatval($cfg['price']);
                            $norm = str_replace('-', '_', sanitize_key($key));
                            if (!isset($aggregato[$norm])) {
                                $aggregato[$norm] = [
                                    'nome'            => ucwords(str_replace(['-', '_'], ' ', $key)),
                                    'quantita'        => 0,
                                    'prezzo_unitario' => $unit,
                                    'totale'          => 0.0,
                                ];
                            }
                            $aggregato[$norm]['quantita'] += 1;
                            $aggregato[$norm]['prezzo_unitario'] = $unit;
                            $aggregato[$norm]['totale'] += $unit;
                            $tot_extra += $unit;
                        }
                    }
                }
            }
        }

        // Selezioni per-durata (globali)
        if (is_array($duration)) {
            foreach ($duration as $key => $cfg) {
                if (!empty($cfg['selected'])) {
                    $unit = isset($cfg['price']) ? floatval($cfg['price']) : floatval($price_list[$key] ?? 0);
                    $norm = str_replace('-', '_', sanitize_key($key));
                    if (!isset($aggregato[$norm])) {
                        $aggregato[$norm] = [
                            'nome'            => ucwords(str_replace(['-', '_'], ' ', $key)),
                            'quantita'        => 0,
                            'prezzo_unitario' => $unit,
                            'totale'          => 0.0,
                        ];
                    }
                    $aggregato[$norm]['quantita'] += 1;
                    $aggregato[$norm]['prezzo_unitario'] = $unit;
                    $aggregato[$norm]['totale'] += $unit;
                    $tot_extra += $unit;
                }
            }
        }

        update_post_meta($preventivo_id, self::META_PREFIX . 'costi_extra_aggregati', $aggregato);
        update_post_meta($preventivo_id, self::META_PREFIX . 'totale_costi_extra_calcolato', $tot_extra);
        update_post_meta($preventivo_id, self::META_PREFIX . 'listino_costi_extra', $price_list);

        // ===== Extra nights =====
        if (isset($booking_data['extra_nights'])) {
            update_post_meta($preventivo_id, self::META_PREFIX . 'notti_extra_dettaglio', $booking_data['extra_nights']);
            if (isset($booking_data['extra_nights']['total_cost'])) {
                update_post_meta($preventivo_id, self::META_PREFIX . 'totale_notti_extra_json', round((float) $booking_data['extra_nights']['total_cost'], 2));
            }
        }

        // ===== Assicurazioni =====
        if (isset($booking_data['insurance'])) {
            update_post_meta($preventivo_id, self::META_PREFIX . 'assicurazioni_dettaglio', $booking_data['insurance']);
            if (isset($booking_data['insurance']['total'])) {
                update_post_meta($preventivo_id, self::META_PREFIX . 'totale_assicurazioni_calcolato', round((float) $booking_data['insurance']['total'], 2));
            }
        }
    }
    
    /**
     * CORREZIONE v1.0.146: Aggrega costi extra per tipo per query veloci
     */
    private function aggrega_costi_extra_per_tipo($preventivo_id, $extra_costs) {
        $aggregato = [];
        
        // Aggrega dal booking_data_json se disponibile
        if (is_array($extra_costs)) {
            foreach ($extra_costs as $cost) {
                $nome = sanitize_key($cost['name'] ?? '');
                if ($nome) {
                    if (!isset($aggregato[$nome])) {
                        $aggregato[$nome] = [
                            'nome' => $cost['name'],
                            'quantita' => 0,
                            'prezzo_unitario' => floatval($cost['price'] ?? 0),
                            'totale' => 0
                        ];
                    }
                    $aggregato[$nome]['quantita'] += intval($cost['quantity'] ?? 1);
                    $aggregato[$nome]['totale'] += floatval($cost['total'] ?? 0);
                }
            }
        }
        
        // Aggrega anche dai dati anagrafici se disponibili
        $anagrafici = get_post_meta($preventivo_id, self::META_PREFIX . 'anagrafici', true);
        if (is_array($anagrafici)) {
            foreach ($anagrafici as $persona) {
                if (!empty($persona['costi_extra']) && is_array($persona['costi_extra'])) {
                    foreach ($persona['costi_extra'] as $key => $value) {
                        if ($value) {
                            $nome_normalizzato = str_replace('-', '_', sanitize_key($key));
                            if (!isset($aggregato[$nome_normalizzato])) {
                                $aggregato[$nome_normalizzato] = [
                                    'nome' => ucwords(str_replace(['-', '_'], ' ', $key)),
                                    'quantita' => 0,
                                    'prezzo_unitario' => 0,
                                    'totale' => 0
                                ];
                            }
                            $aggregato[$nome_normalizzato]['quantita']++;
                            
                            // Cerca di trovare il prezzo dal booking_data_json
                            if (isset($extra_costs)) {
                                foreach ($extra_costs as $cost) {
                                    if (sanitize_key($cost['name']) === $nome_normalizzato) {
                                        $aggregato[$nome_normalizzato]['prezzo_unitario'] = floatval($cost['price'] ?? 0);
                                        $aggregato[$nome_normalizzato]['totale'] += floatval($cost['price'] ?? 0);
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Salva aggregati per tipo
        foreach ($aggregato as $key => $data) {
            update_post_meta($preventivo_id, self::META_PREFIX . 'costo_extra_' . $key, $data);
            
            // Retrocompatibilità
            update_post_meta($preventivo_id, '_costo_extra_' . $key . '_quantita', $data['quantita']);
            update_post_meta($preventivo_id, '_costo_extra_' . $key . '_totale', $data['totale']);
        }
        
        // Salva riepilogo aggregato
        update_post_meta($preventivo_id, self::META_PREFIX . 'costi_extra_aggregati', $aggregato);
        
        // Log per debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[BTR v1.0.157] Costi extra aggregati per preventivo ' . $preventivo_id . ': ' . print_r($aggregato, true));
        }
    }
    
    /**
     * Sincronizza con WooCommerce
     */
    private function sincronizza_woocommerce($preventivo_id, $dati) {
        if (!WC()->session) {
            return;
        }
        
        // Salva in sessione
        WC()->session->set('btr_preventivo_id', $preventivo_id);
        WC()->session->set('_preventivo_id', $preventivo_id);
        
        // Salva dati essenziali
        WC()->session->set('btr_quote_data', [
            'quote_id' => $preventivo_id,
            'package_id' => $dati['pacchetto']['id'],
            'total' => $dati['prezzi']['totale_generale'],
            'totale_viaggio' => get_post_meta($preventivo_id, self::META_PREFIX . 'totale_viaggio', true),
            'payment_method' => $dati['metodo_pagamento']
        ]);
        
        // Aggiorna carrello se presente
        if (!empty($dati['sessione']['cart_item_key']) && WC()->cart) {
            $cart_item_key = $dati['sessione']['cart_item_key'];
            $cart = WC()->cart->get_cart();
            
            if (isset($cart[$cart_item_key])) {
                WC()->cart->cart_contents[$cart_item_key]['btr_preventivo_id'] = $preventivo_id;
                WC()->cart->set_session();
            }
        }
        
        // Hook per sincronizzazioni aggiuntive
        do_action('btr_sincronizza_woocommerce_preventivo', $preventivo_id, $dati);
    }
    
    /**
     * Gestione errori
     */
    private function gestisci_errore($exception) {
        if ($logger = $this->get_logger()) {
            $logger->error(
                'Errore creazione preventivo V4: ' . $exception->getMessage(),
                [
                    'source' => 'BTR_Preventivi_V4',
                    'trace' => $exception->getTraceAsString()
                ]
            );
        }
        
        wp_send_json_error([
            'message' => 'Si è verificato un errore nella creazione del preventivo: ' . $exception->getMessage()
        ]);
    }
}
?>
