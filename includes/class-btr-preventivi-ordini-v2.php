<?php
/**
 * Classe migliorata per la conversione preventivi in ordini WooCommerce
 * con prodotti dettagliati per ogni partecipante
 * 
 * @since 1.0.80
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Preventivo_To_Order_V2 extends BTR_Preventivo_To_Order {
    
    /**
     * Override del metodo per aggiungere prodotti dettagliati
     * Crea un prodotto separato per ogni voce invece di aggregare
     */
    public function add_detailed_cart_items($preventivo_id, $anagrafici_data) {
        $items_added = false;

        if (empty($anagrafici_data) || !is_array($anagrafici_data)) {
            error_log('BTR V2: Dati anagrafici vuoti per preventivo #' . $preventivo_id);
            return false;
        }
        
        // IMPORTANTE: Applica il filtro No Skipass per rimuovere RC Skipass da chi ha No Skipass
        if (class_exists('BTR_No_Skipass_Filter')) {
            $anagrafici_data = BTR_No_Skipass_Filter::filter_rc_skipass($anagrafici_data, $preventivo_id);
        }
        
        // Log per debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BTR V2: ========== INIZIO CREAZIONE PRODOTTI DETTAGLIATI ==========');
            error_log('BTR V2: Preventivo ID: ' . $preventivo_id);
            error_log('BTR V2: Numero partecipanti: ' . count($anagrafici_data));
        }
        
        // Recupera dati necessari
        $camere_selezionate = get_post_meta($preventivo_id, '_camere_selezionate', true);
        $extra_night_flag = get_post_meta($preventivo_id, '_extra_night', true);
        $numero_notti_extra = intval(get_post_meta($preventivo_id, '_numero_notti_extra', true));
        $nome_pacchetto = get_post_meta($preventivo_id, '_nome_pacchetto', true) ?: 'Pacchetto Viaggio';
        $data_pacchetto = get_post_meta($preventivo_id, '_data_pacchetto', true);
        
        // Mappa camere per facile accesso
        $camere_map = $this->build_camere_map($camere_selezionate);
        
        // Contatori per assegnazione lettere camere
        $camera_counters = [];
        
        // Processa ogni partecipante individualmente
        foreach ($anagrafici_data as $index => $partecipante) {
            // Salta se non ha dati validi
            if (empty($partecipante['nome']) || empty($partecipante['cognome'])) {
                continue;
            }

            $items_added = true;

            // Determina la camera e la lettera assegnata
            $camera_info = $this->get_camera_info_for_partecipante($partecipante, $camere_map, $camera_counters);
            
            // 1. Aggiungi prodotto base per il partecipante
            $this->add_partecipante_base_product(
                $preventivo_id,
                $partecipante,
                $index,
                $camera_info,
                $nome_pacchetto,
                $data_pacchetto
            );
            
            // 2. Aggiungi assicurazioni selezionate
            $this->add_partecipante_assicurazioni(
                $preventivo_id,
                $partecipante,
                $index
            );
            
            // 3. Aggiungi costi extra (positivi come prodotti, negativi come fees)
            $this->add_partecipante_costi_extra(
                $preventivo_id,
                $partecipante,
                $index
            );
            
            // 4. Aggiungi notti extra se applicabili
            if ($extra_night_flag && $numero_notti_extra > 0) {
                $this->add_partecipante_notti_extra(
                    $preventivo_id,
                    $partecipante,
                    $index,
                    $camera_info,
                    $numero_notti_extra
                );
            }
        }
        
        // Aggiungi eventuali costi a durata (non legati a persone specifiche)
        $this->add_costi_durata($preventivo_id);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('BTR V2: ========== FINE CREAZIONE PRODOTTI DETTAGLIATI ==========');
        }

        if ($items_added) {
            $this->mark_detailed_cart_mode($preventivo_id);
        }

        return $items_added;
    }
    
    /**
     * Costruisce una mappa delle camere per accesso rapido
     */
    private function build_camere_map($camere_selezionate) {
        $map = [];
        if (!empty($camere_selezionate) && is_array($camere_selezionate)) {
            foreach ($camere_selezionate as $camera) {
                $tipo = $camera['tipo'] ?? '';
                if ($tipo) {
                    $map[$tipo] = $camera;
                }
            }
        }
        return $map;
    }
    
    /**
     * Ottiene informazioni camera per un partecipante
     */
    private function get_camera_info_for_partecipante($partecipante, $camere_map, &$camera_counters) {
        $camera_tipo = $partecipante['camera_tipo'] ?? '';
        
        if (empty($camera_tipo) || !isset($camere_map[$camera_tipo])) {
            // Fallback: usa la prima camera disponibile
            $camera_tipo = array_key_first($camere_map);
        }
        
        // Incrementa contatore per questa tipologia di camera
        if (!isset($camera_counters[$camera_tipo])) {
            $camera_counters[$camera_tipo] = 0;
        }
        $camera_counters[$camera_tipo]++;
        
        // Assegna lettera (A, B, C...)
        $lettera = chr(64 + $camera_counters[$camera_tipo]); // 65 = A
        
        $camera = $camere_map[$camera_tipo] ?? [];
        
        return [
            'tipo' => $camera_tipo,
            'lettera' => $lettera,
            'label' => $camera_tipo . ' ' . $lettera,
            'prezzo_camera' => floatval($camera['prezzo_camera'] ?? 0),
            'supplemento' => floatval($camera['supplemento'] ?? 0),
            'price_child_f1' => floatval($camera['price_child_f1'] ?? 0),
            'price_child_f2' => floatval($camera['price_child_f2'] ?? 0),
            'price_child_f3' => floatval($camera['price_child_f3'] ?? 0),
            'price_child_f4' => floatval($camera['price_child_f4'] ?? 0),
        ];
    }
    
    /**
     * Aggiunge il prodotto base per un partecipante
     */
    private function add_partecipante_base_product($preventivo_id, $partecipante, $index, $camera_info, $nome_pacchetto, $data_pacchetto) {
        $nome_completo = trim($partecipante['nome'] . ' ' . $partecipante['cognome']);
        
        // Determina la fascia di prezzo
        $fascia = $this->determine_fascia_partecipante($partecipante, $data_pacchetto);
        
        // Calcola prezzo base per questo partecipante
        $prezzo_base = $this->calculate_prezzo_partecipante($partecipante, $fascia, $camera_info);
        
        // Nome prodotto descrittivo
        $product_name = sprintf(
            '%s - Camera %s - %s',
            $nome_completo,
            $camera_info['label'],
            $nome_pacchetto
        );
        
        // Metadati dettagliati
        $meta_data = [
            'type' => 'partecipante_base',
            'preventivo_id' => $preventivo_id,
            'partecipante_index' => $index,
            'nome_completo' => $nome_completo,
            'nome' => $partecipante['nome'],
            'cognome' => $partecipante['cognome'],
            'fascia' => $fascia,
            'tipo_persona' => $partecipante['tipo_persona'] ?? 'adulto',
            'camera' => $camera_info['label'],
            'camera_tipo' => $camera_info['tipo'],
            'prezzo_base' => $prezzo_base - $camera_info['supplemento'],
            'supplemento' => $camera_info['supplemento'],
            'prezzo_totale' => $prezzo_base
        ];
        
        // Aggiungi al carrello
        $this->add_virtual_cart_item(
            $product_name,
            $prezzo_base,
            1,
            $meta_data
        );
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'BTR V2: Aggiunto prodotto base - %s: €%.2f (fascia: %s)',
                $product_name,
                $prezzo_base,
                $fascia
            ));
        }
    }
    
    /**
     * Determina la fascia di prezzo del partecipante
     */
    private function determine_fascia_partecipante($partecipante, $data_pacchetto) {
        // Se già specificata, usala
        if (!empty($partecipante['fascia'])) {
            return $partecipante['fascia'];
        }
        
        // Altrimenti calcola dall'età
        if (!empty($partecipante['data_nascita']) && !empty($data_pacchetto)) {
            $event_date = btr_parse_event_date($data_pacchetto);
            if ($event_date) {
                $dob = DateTime::createFromFormat('Y-m-d', $partecipante['data_nascita']);
                if (!$dob) {
                    $dob = DateTime::createFromFormat('d/m/Y', $partecipante['data_nascita']);
                }
                
                if ($dob) {
                    $age = (new DateTime($event_date))->diff($dob)->y;
                    
                    if ($age < 2) {
                        return 'neonato';
                    } elseif ($age >= 3 && $age <= 6) {
                        return 'bambini_f1';
                    } elseif ($age > 6 && $age <= 8) {
                        return 'bambini_f2';
                    } elseif ($age > 8 && $age <= 10) {
                        return 'bambini_f3';
                    } elseif ($age >= 11 && $age <= 12) {
                        return 'bambini_f4';
                    }
                }
            }
        }
        
        // Default adulto
        return 'adulto';
    }
    
    /**
     * Calcola il prezzo per un partecipante specifico
     */
    private function calculate_prezzo_partecipante($partecipante, $fascia, $camera_info) {
        $prezzo = 0;
        
        // Prezzo base in base alla fascia
        switch ($fascia) {
            case 'adulto':
                $prezzo = $camera_info['prezzo_camera'];
                break;
            case 'bambini_f1':
                $prezzo = $camera_info['price_child_f1'] ?: ($camera_info['prezzo_camera'] * 0.7);
                break;
            case 'bambini_f2':
                $prezzo = $camera_info['price_child_f2'] ?: ($camera_info['prezzo_camera'] * 0.5);
                break;
            case 'bambini_f3':
                $prezzo = $camera_info['price_child_f3'] ?: ($camera_info['prezzo_camera'] * 0.3);
                break;
            case 'bambini_f4':
                $prezzo = $camera_info['price_child_f4'] ?: ($camera_info['prezzo_camera'] * 0.2);
                break;
            case 'neonato':
                $prezzo = 0; // I neonati non pagano
                break;
            default:
                $prezzo = $camera_info['prezzo_camera'];
        }
        
        // Aggiungi supplemento camera
        $prezzo += $camera_info['supplemento'];
        
        return $prezzo;
    }
    
    /**
     * Aggiunge le assicurazioni per un partecipante
     */
    private function add_partecipante_assicurazioni($preventivo_id, $partecipante, $index) {
        if (empty($partecipante['assicurazioni']) || !is_array($partecipante['assicurazioni'])) {
            return;
        }
        
        $nome_completo = trim($partecipante['nome'] . ' ' . $partecipante['cognome']);
        
        foreach ($partecipante['assicurazioni'] as $slug => $selected) {
            if ($selected != '1') {
                continue;
            }
            
            $dettagli = $partecipante['assicurazioni_dettagliate'][$slug] ?? null;
            if (!$dettagli || !isset($dettagli['importo'])) {
                continue;
            }
            
            $importo = floatval($dettagli['importo']);
            if ($importo <= 0) {
                continue;
            }
            
            $descrizione = $dettagli['descrizione'] ?? 'Assicurazione';
            
            $product_name = sprintf('%s - %s', $nome_completo, $descrizione);
            
            $meta_data = [
                'type' => 'assicurazione',
                'preventivo_id' => $preventivo_id,
                'partecipante_index' => $index,
                'nome_completo' => $nome_completo,
                'assicurazione_slug' => $slug,
                'descrizione' => $descrizione,
                'product_id_originale' => $dettagli['product_id'] ?? null
            ];
            
            $this->add_virtual_cart_item(
                $product_name,
                $importo,
                1,
                $meta_data
            );
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    'BTR V2: Aggiunta assicurazione - %s: €%.2f',
                    $product_name,
                    $importo
                ));
            }
        }
    }
    
    /**
     * Aggiunge i costi extra per un partecipante
     */
    private function add_partecipante_costi_extra($preventivo_id, $partecipante, $index) {
        if (empty($partecipante['costi_extra']) || !is_array($partecipante['costi_extra'])) {
            return;
        }
        
        $nome_completo = trim($partecipante['nome'] . ' ' . $partecipante['cognome']);
        
        // Recupera i costi extra dal pacchetto per avere i prezzi
        $package_id = get_post_meta($preventivo_id, '_pacchetto_id', true);
        $package_costi_extra = [];
        if ($package_id) {
            $costi_extra_meta = get_post_meta($package_id, 'btr_costi_extra', true);
            if (is_array($costi_extra_meta)) {
                foreach ($costi_extra_meta as $extra) {
                    if (!empty($extra['nome'])) {
                        $slug = sanitize_title($extra['nome']);
                        $package_costi_extra[$slug] = $extra;
                    }
                }
            }
        }
        
        foreach ($partecipante['costi_extra'] as $slug => $info) {
            // Gestisci sia il formato legacy che quello nuovo
            $selected = is_array($info) ? ($info['selected'] ?? false) : ($info == '1');
            
            if (!$selected) {
                continue;
            }
            
            // Recupera dettagli del costo extra
            $dettagli = is_array($info) ? $info : ($partecipante['costi_extra_dettagliate'][$slug] ?? []);
            
            // Se non ci sono dettagli completi, prova a recuperarli dal pacchetto
            if (empty($dettagli['importo']) && empty($dettagli['prezzo'])) {
                if (isset($package_costi_extra[$slug])) {
                    $dettagli = array_merge($dettagli, $package_costi_extra[$slug]);
                }
            }
            
            $importo = floatval($dettagli['prezzo'] ?? $dettagli['importo'] ?? 0);
            
            // Log per debug
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    'BTR V2: Costo extra %s per %s - Importo: €%.2f',
                    $slug,
                    $nome_completo,
                    $importo
                ));
                if ($importo == 0) {
                    error_log('BTR V2: ATTENZIONE - Importo zero per: ' . $slug);
                    error_log('BTR V2: Dettagli: ' . print_r($dettagli, true));
                }
            }
            
            if ($importo == 0) {
                continue;
            }
            
            $descrizione = $dettagli['nome'] ?? $dettagli['descrizione'] ?? 'Costo Extra';
            
            if ($importo < 0) {
                // Sconto/Riduzione: aggiungi come fee
                $fee_name = sprintf('%s - %s', $nome_completo, $descrizione);
                $this->add_btr_fee_to_session($fee_name, $importo);
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf(
                        'BTR V2: Aggiunta fee (sconto) - %s: €%.2f',
                        $fee_name,
                        $importo
                    ));
                }
            } else {
                // Costo positivo: aggiungi come prodotto
                $product_name = sprintf('%s - %s', $nome_completo, $descrizione);
                
                $meta_data = [
                    'type' => 'costo_extra',
                    'preventivo_id' => $preventivo_id,
                    'partecipante_index' => $index,
                    'nome_completo' => $nome_completo,
                    'extra_slug' => $slug,
                    'descrizione' => $descrizione,
                    'importo_originale' => $importo
                ];
                
                $this->add_virtual_cart_item(
                    $product_name,
                    $importo,
                    1,
                    $meta_data
                );
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf(
                        'BTR V2: Aggiunto costo extra - %s: €%.2f',
                        $product_name,
                        $importo
                    ));
                }
            }
        }
    }
    
    /**
     * Aggiunge le notti extra per un partecipante
     */
    private function add_partecipante_notti_extra($preventivo_id, $partecipante, $index, $camera_info, $numero_notti) {
        $nome_completo = trim($partecipante['nome'] . ' ' . $partecipante['cognome']);
        $fascia = $this->determine_fascia_partecipante($partecipante, null);
        
        // Calcola prezzo notte extra in base alla fascia
        $prezzo_notte_extra = $this->calculate_prezzo_notte_extra($fascia, $camera_info);
        
        if ($prezzo_notte_extra <= 0) {
            return;
        }
        
        $totale_notti_extra = $prezzo_notte_extra * $numero_notti;
        
        $product_name = sprintf(
            '%s - Notti Extra (%d %s)',
            $nome_completo,
            $numero_notti,
            $numero_notti == 1 ? 'notte' : 'notti'
        );
        
        $meta_data = [
            'type' => 'notte_extra',
            'preventivo_id' => $preventivo_id,
            'partecipante_index' => $index,
            'nome_completo' => $nome_completo,
            'numero_notti' => $numero_notti,
            'prezzo_per_notte' => $prezzo_notte_extra,
            'fascia' => $fascia
        ];
        
        $this->add_virtual_cart_item(
            $product_name,
            $totale_notti_extra,
            1,
            $meta_data
        );
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                'BTR V2: Aggiunte notti extra - %s: €%.2f (€%.2f x %d notti)',
                $product_name,
                $totale_notti_extra,
                $prezzo_notte_extra,
                $numero_notti
            ));
        }
    }
    
    /**
     * Calcola il prezzo della notte extra per fascia
     */
    private function calculate_prezzo_notte_extra($fascia, $camera_info) {
        // Recupera il prezzo notte extra base (per adulti)
        $extra_night_pp = floatval(get_option('btr_extra_night_price', 62));
        
        // Applica riduzioni per bambini
        switch ($fascia) {
            case 'bambini_f1':
                return $extra_night_pp * 0.7;
            case 'bambini_f2':
                return $extra_night_pp * 0.5;
            case 'bambini_f3':
                return $extra_night_pp * 0.3;
            case 'bambini_f4':
                return $extra_night_pp * 0.2;
            case 'neonato':
                return 0;
            default:
                return $extra_night_pp;
        }
    }
    
    /**
     * Aggiunge costi a durata (non legati a persone specifiche)
     */
    private function add_costi_durata($preventivo_id) {
        $costi_extra_durata = get_post_meta($preventivo_id, '_costi_extra_durata', true);
        
        if (empty($costi_extra_durata) || !is_array($costi_extra_durata)) {
            return;
        }
        
        foreach ($costi_extra_durata as $slug => $info) {
            if (!isset($info['selected']) || !$info['selected']) {
                continue;
            }
            
            $importo = floatval($info['prezzo'] ?? 0);
            if ($importo == 0) {
                continue;
            }
            
            $descrizione = $info['nome'] ?? 'Servizio Extra';
            
            if ($importo < 0) {
                // Sconto globale
                $this->add_btr_fee_to_session($descrizione, $importo);
            } else {
                // Costo globale
                $meta_data = [
                    'type' => 'costo_durata',
                    'preventivo_id' => $preventivo_id,
                    'extra_slug' => $slug,
                    'descrizione' => $descrizione
                ];
                
                $this->add_virtual_cart_item(
                    $descrizione,
                    $importo,
                    1,
                    $meta_data
                );
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    'BTR V2: Aggiunto costo durata - %s: €%.2f',
                    $descrizione,
                    $importo
                ));
            }
        }
    }
}

// Funzione helper per ottenere il prezzo dalla camera per fascia
if (!function_exists('btr_get_price_from_camera')) {
    function btr_get_price_from_camera($camera, $fascia) {
        switch ($fascia) {
            case 'bambini_f1':
            case 'f1':
                return floatval($camera['price_child_f1'] ?? $camera['prezzo_camera'] ?? 0);
            case 'bambini_f2':
            case 'f2':
                return floatval($camera['price_child_f2'] ?? $camera['prezzo_camera'] ?? 0);
            case 'bambini_f3':
            case 'f3':
                return floatval($camera['price_child_f3'] ?? $camera['prezzo_camera'] ?? 0);
            case 'bambini_f4':
            case 'f4':
                return floatval($camera['price_child_f4'] ?? $camera['prezzo_camera'] ?? 0);
            case 'neonato':
                return 0;
            default:
                return floatval($camera['prezzo_camera'] ?? 0);
        }
    }
}

// Funzione helper per parsare la data evento
if (!function_exists('btr_parse_event_date')) {
    function btr_parse_event_date($data_pacchetto) {
        if (empty($data_pacchetto)) {
            return null;
        }
        
        // Prova diversi formati
        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y'];
        foreach ($formats as $format) {
            $date = DateTime::createFromFormat($format, $data_pacchetto);
            if ($date !== false) {
                return $date->format('Y-m-d');
            }
        }
        
        return null;
    }
}
