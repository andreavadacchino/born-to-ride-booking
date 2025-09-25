<?php
/**
 * Classe centralizzata per tutti i calcoli di prezzo
 * 
 * @package Born_To_Ride_Booking
 * @since 1.0.53
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Price_Calculator {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Cache per risultati calcoli
     */
    private $cache = [];

    /**
     * Configurazione prezzi bambini per notti extra
     * TODO: Sostituire con dati dinamici da admin
     */
    private $child_extra_night_prices = [
        'f1' => 22.00,  // 3-6 anni
        'f2' => 23.00,  // 6-8 anni  
        'f3' => 24.00,  // 8-10 anni
        'f4' => 25.00,  // 11-12 anni
    ];

    /**
     * Valuta se un numero è da considerarsi zero per il calcolo dei totali
     */
    private function is_near_zero($value) {
        return abs(floatval($value)) < 0.01;
    }

    /**
     * Recupera il totale assicurazioni dai metadati disponibili
     */
    private function get_insurance_total_from_meta($preventivo_id) {
        $meta_keys = ['_totale_assicurazioni', '_insurance_total', '_subtotal_insurance'];

        foreach ($meta_keys as $key) {
            $raw_value = get_post_meta($preventivo_id, $key, true);
            $value = floatval($raw_value);

            if (!$this->is_near_zero($value)) {
                return $value;
            }
        }

        return 0.0;
    }

    /**
     * Recupera il totale costi extra dai metadati disponibili
     */
    private function get_extra_costs_total_from_meta($preventivo_id) {
        $meta_keys = ['_totale_costi_extra', '_extra_costs_total', '_subtotal_extra_costs'];

        foreach ($meta_keys as $key) {
            $raw_value = get_post_meta($preventivo_id, $key, true);
            $value = floatval($raw_value);

            if (!$this->is_near_zero($value)) {
                return $value;
            }
        }

        return 0.0;
    }

    /**
     * Ricostruisce il totale finale sommando tutte le componenti note
     */
    private function calculate_total_from_components($components) {
        $base         = floatval($components['base'] ?? 0);
        $extra_nights = floatval($components['extra_nights'] ?? 0);
        $extra_costs  = floatval($components['extra_costs'] ?? 0);
        $assicurazioni = floatval($components['assicurazioni'] ?? 0);
        $supplementi  = floatval($components['supplementi'] ?? 0);

        return round($base + $extra_nights + $extra_costs + $assicurazioni + $supplementi, 2);
    }
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Hook per pulire cache quando necessario
        add_action('btr_preventivo_saved', [$this, 'clear_cache']);
        add_action('woocommerce_cart_updated', [$this, 'clear_cache']);
    }
    
    /**
     * Calcola il totale per le notti extra
     * 
     * @param array $params Parametri per il calcolo
     * @return array Risultato dettagliato del calcolo
     */
    public function calculate_extra_nights($params) {
        $defaults = [
            'numero_notti_extra' => 0,
            'extra_night_pp' => 0,
            'adulti' => 0,
            'bambini_per_fascia' => [],
            'supplemento_camera' => 0,
            'camere' => [],
            'use_dynamic_pricing' => true
        ];
        
        $params = wp_parse_args($params, $defaults);
        
        // Check cache
        $cache_key = 'extra_nights_' . md5(serialize($params));
        if (isset($this->cache[$cache_key])) {
            btr_debug_log('BTR_Price_Calculator: Returning cached result for ' . $cache_key);
            return $this->cache[$cache_key];
        }
        
        $result = [
            'adulti' => [
                'count' => $params['adulti'],
                'price_per_night' => $params['extra_night_pp'],
                'total' => 0
            ],
            'bambini' => [],
            'supplementi' => [
                'camera' => 0,
                'totale' => 0
            ],
            'breakdown' => [],
            'totale' => 0
        ];
        
        // Calcola solo se ci sono notti extra
        if ($params['numero_notti_extra'] <= 0) {
            $this->cache[$cache_key] = $result;
            return $result;
        }
        
        // Calcolo adulti
        $result['adulti']['total'] = $params['adulti'] * $params['extra_night_pp'] * $params['numero_notti_extra'];
        $result['totale'] += $result['adulti']['total'];
        
        // Calcolo bambini con prezzi dinamici
        foreach ($params['bambini_per_fascia'] as $fascia => $count) {
            if ($count > 0) {
                $price_per_night = $this->get_child_extra_night_price($fascia, $params);
                $total = $price_per_night * $count * $params['numero_notti_extra'];
                
                $result['bambini'][$fascia] = [
                    'count' => $count,
                    'price_per_night' => $price_per_night,
                    'total' => $total
                ];
                
                $result['totale'] += $total;
            }
        }
        
        // Calcolo supplementi camera
        if ($params['supplemento_camera'] > 0 && !empty($params['camere'])) {
            $totale_persone_camere = 0;
            foreach ($params['camere'] as $camera) {
                $totale_persone_camere += isset($camera['persone']) ? $camera['persone'] : 0;
            }
            
            $result['supplementi']['camera'] = $params['supplemento_camera'] * $totale_persone_camere * $params['numero_notti_extra'];
            $result['supplementi']['totale'] = $result['supplementi']['camera'];
            $result['totale'] += $result['supplementi']['camera'];
        }
        
        // Genera breakdown dettagliato
        $result['breakdown'] = $this->generate_breakdown($result, $params);
        
        // Cache result
        $this->cache[$cache_key] = $result;
        
        btr_debug_log('BTR_Price_Calculator: Extra nights calculation result: ' . print_r($result, true));
        
        return $result;
    }
    
    /**
     * Ottieni il prezzo per notte extra per una fascia bambini
     * 
     * @param string $fascia Codice fascia (f1, f2, f3, f4)
     * @param array $context Contesto per prezzi dinamici
     * @return float Prezzo per notte
     */
    public function get_child_extra_night_price($fascia, $context = []) {
        // Utilizza la classe esistente BTR_Child_Extra_Night_Pricing
        $child_pricing = new BTR_Child_Extra_Night_Pricing();
        
        // Ottieni il prezzo adulto dal context
        $adult_price = isset($context['extra_night_pp']) ? floatval($context['extra_night_pp']) : 0;
        
        // Se il prezzo adulto è 0, usa i prezzi di fallback
        if ($adult_price <= 0) {
            return isset($this->child_extra_night_prices[$fascia]) 
                ? $this->child_extra_night_prices[$fascia] 
                : 0;
        }
        
        // Usa il metodo della classe esistente per calcolare il prezzo
        return $child_pricing->calculate_child_extra_night_price($fascia, $adult_price);
    }
    
    /**
     * Calcola il totale dei costi extra
     * 
     * @param array $anagrafici Dati anagrafici con costi extra
     * @param array $costi_extra_durata Costi extra per durata
     * @return array Risultato aggregato
     */
    public function calculate_extra_costs($anagrafici, $costi_extra_durata = []) {
        $result = [
            'per_persona' => [],
            'per_durata' => [],
            'riduzioni' => [],  // Separa le riduzioni (valori negativi)
            'aggiunte' => [],   // Separa le aggiunte (valori positivi)
            'totale_riduzioni' => 0,
            'totale_aggiunte' => 0,
            'totale' => 0,
            'dettaglio_partecipanti' => []
        ];
        
        // Processa costi extra per persona
        if (is_array($anagrafici)) {
            foreach ($anagrafici as $index => $persona) {
                $nome_completo = trim(($persona['nome'] ?? '') . ' ' . ($persona['cognome'] ?? ''));
                
                if (!empty($persona['costi_extra_dettagliate']) && is_array($persona['costi_extra_dettagliate'])) {
                    foreach ($persona['costi_extra_dettagliate'] as $key => $extra) {
                        $importo = floatval($extra['importo'] ?? 0);
                        $nome_extra = $extra['nome'] ?? $extra['descrizione'] ?? $key;
                        
                        // Aggiungi al dettaglio partecipante
                        if (!isset($result['dettaglio_partecipanti'][$nome_completo])) {
                            $result['dettaglio_partecipanti'][$nome_completo] = [];
                        }
                        $result['dettaglio_partecipanti'][$nome_completo][] = [
                            'nome' => $nome_extra,
                            'importo' => $importo
                        ];
                        
                        // Separa riduzioni da aggiunte
                        if ($importo < 0) {
                            // È una riduzione
                            if (!isset($result['riduzioni'][$key])) {
                                $result['riduzioni'][$key] = [
                                    'nome' => $nome_extra,
                                    'count' => 0,
                                    'importo_unitario' => $importo,
                                    'totale' => 0,
                                    'partecipanti' => []
                                ];
                            }
                            $result['riduzioni'][$key]['count']++;
                            $result['riduzioni'][$key]['totale'] += $importo;
                            $result['riduzioni'][$key]['partecipanti'][] = $nome_completo;
                            $result['totale_riduzioni'] += $importo;
                        } else {
                            // È un'aggiunta
                            if (!isset($result['aggiunte'][$key])) {
                                $result['aggiunte'][$key] = [
                                    'nome' => $nome_extra,
                                    'count' => 0,
                                    'importo_unitario' => $importo,
                                    'totale' => 0,
                                    'partecipanti' => []
                                ];
                            }
                            $result['aggiunte'][$key]['count']++;
                            $result['aggiunte'][$key]['totale'] += $importo;
                            $result['aggiunte'][$key]['partecipanti'][] = $nome_completo;
                            $result['totale_aggiunte'] += $importo;
                        }
                        
                        // Mantieni anche la struttura legacy per compatibilità
                        if (!isset($result['per_persona'][$key])) {
                            $result['per_persona'][$key] = [
                                'nome' => $nome_extra,
                                'count' => 0,
                                'importo_unitario' => $importo,
                                'totale' => 0
                            ];
                        }
                        $result['per_persona'][$key]['count']++;
                        $result['per_persona'][$key]['totale'] += $importo;
                    }
                }
            }
        }
        
        // Processa costi extra per durata
        if (is_array($costi_extra_durata)) {
            foreach ($costi_extra_durata as $key => $extra) {
                if (isset($extra['selezionato']) && $extra['selezionato']) {
                    $importo = floatval($extra['importo'] ?? 0);
                    $result['per_durata'][$key] = [
                        'nome' => $extra['nome'] ?? $extra['descrizione'] ?? $key,
                        'importo' => $importo,
                        'moltiplica_durata' => $extra['moltiplica_durata'] ?? false
                    ];
                    
                    // Separa anche questi in riduzioni/aggiunte
                    if ($importo < 0) {
                        $result['totale_riduzioni'] += $importo;
                    } else {
                        $result['totale_aggiunte'] += $importo;
                    }
                }
            }
        }
        
        // Calcola totale complessivo
        $result['totale'] = $result['totale_aggiunte'] + $result['totale_riduzioni'];
        
        btr_debug_log('BTR_Price_Calculator: Extra costs calculation result: ' . print_r($result, true));
        
        return $result;
    }
    
    /**
     * Genera breakdown dettagliato per visualizzazione
     * 
     * @param array $result Risultato calcolo
     * @param array $params Parametri originali
     * @return array Breakdown formattato
     */
    private function generate_breakdown($result, $params) {
        $breakdown = [];
        
        // Adulti
        if ($result['adulti']['total'] > 0) {
            $breakdown[] = sprintf(
                '%d %s x €%.2f x %d %s = €%.2f',
                $result['adulti']['count'],
                _n('adulto', 'adulti', $result['adulti']['count'], 'born-to-ride-booking'),
                $result['adulti']['price_per_night'],
                $params['numero_notti_extra'],
                _n('notte', 'notti', $params['numero_notti_extra'], 'born-to-ride-booking'),
                $result['adulti']['total']
            );
        }
        
        // Bambini per fascia
        foreach ($result['bambini'] as $fascia => $data) {
            if ($data['total'] > 0) {
                $label = $this->get_child_label($fascia, $params);
                $breakdown[] = sprintf(
                    '%d %s x €%.2f x %d %s = €%.2f',
                    $data['count'],
                    $label,
                    $data['price_per_night'],
                    $params['numero_notti_extra'],
                    _n('notte', 'notti', $params['numero_notti_extra'], 'born-to-ride-booking'),
                    $data['total']
                );
            }
        }
        
        // Supplementi
        if ($result['supplementi']['totale'] > 0) {
            $breakdown[] = sprintf(
                'Supplemento camera x %d %s = €%.2f',
                $params['numero_notti_extra'],
                _n('notte', 'notti', $params['numero_notti_extra'], 'born-to-ride-booking'),
                $result['supplementi']['totale']
            );
        }
        
        return $breakdown;
    }
    
    /**
     * Ottieni etichetta per fascia bambini
     * 
     * @param string $fascia Codice fascia
     * @param array $context Contesto con etichette custom
     * @return string Etichetta
     */
    private function get_child_label($fascia, $context = []) {
        // Controlla se ci sono etichette custom nel context
        if (isset($context['child_labels'][$fascia])) {
            return $context['child_labels'][$fascia];
        }
        
        // Usa il sistema di etichette dinamiche
        if (function_exists('btr_get_child_label')) {
            $preventivo_id = $context['preventivo_id'] ?? null;
            return btr_get_child_label($fascia, $preventivo_id);
        }
        
        // v1.0.182 - Genera etichette dinamicamente, NO hardcoded
        if (class_exists('BTR_Dynamic_Child_Categories')) {
            $dynamic_categories = new BTR_Dynamic_Child_Categories();
            $category = $dynamic_categories->get_category($fascia);
            if ($category && isset($category['label'])) {
                return $category['label'];
            }
        }
        
        // Genera dinamicamente se non disponibile
        $fascia_num = intval(substr($fascia, 1));
        $base_age = 2 + ($fascia_num * 3);
        $age_min = $base_age - 1;
        $age_max = $base_age + 1;
        return "Fascia età {$age_min}-{$age_max} anni";
    }
    
    /**
     * Pulisci cache
     */
    public function clear_cache() {
        $this->cache = [];
        btr_debug_log('BTR_Price_Calculator: Cache cleared');
    }
    
    /**
     * Calcola il totale generale del preventivo
     * 
     * @param array $params Tutti i parametri del preventivo
     * @return array Risultato con breakdown completo
     */
    public function calculate_preventivo_total($params) {
        $defaults = [
            'preventivo_id' => 0,
            'anagrafici' => [],
        ];

        $params = wp_parse_args($params, $defaults);
        $preventivo_id = intval($params['preventivo_id']);

        $result = [
            'base' => 0,
            'extra_nights' => 0,
            'extra_costs' => 0,
            'assicurazioni' => 0,
            'supplementi' => 0,
            'totale' => 0,
            'totale_finale' => 0,
            'aggiunte' => 0,
            'riduzioni' => 0,
            'fonte' => 'unknown',
            'valid' => false,
            'breakdown' => [],
        ];

        if ($preventivo_id <= 0) {
            return $result;
        }

        $cache_key = 'preventivo_total_' . $preventivo_id;
        if (isset($this->cache[$cache_key])) {
            return $this->cache[$cache_key];
        }

        $price_snapshot = get_post_meta($preventivo_id, '_price_snapshot', true);
        $has_snapshot = get_post_meta($preventivo_id, '_has_price_snapshot', true);

        if ($has_snapshot && is_array($price_snapshot) && !empty($price_snapshot)) {
            $hash_valid = true;
            if (!empty($price_snapshot['integrity_hash'])) {
                $expected_hash = hash('sha256', serialize([
                    $price_snapshot['rooms_total'] ?? 0,
                    $price_snapshot['totals']['grand_total'] ?? 0,
                    $price_snapshot['participants'] ?? [],
                    $price_snapshot['timestamp'] ?? '',
                ]));

                if ($expected_hash !== ($price_snapshot['integrity_hash'] ?? '')) {
                    $hash_valid = false;
                }
            }

            if ($hash_valid) {
                $result['fonte'] = 'snapshot';
                $result['valid'] = true;
                $result['snapshot_hash'] = $price_snapshot['integrity_hash'] ?? '';

                $result['base'] = floatval($price_snapshot['rooms_total'] ?? 0);
                $result['extra_nights'] = floatval($price_snapshot['extra_nights']['total_corrected'] ?? ($price_snapshot['extra_nights']['total'] ?? 0));
                $result['assicurazioni'] = floatval($price_snapshot['insurance']['total'] ?? 0);
                $result['extra_costs'] = floatval($price_snapshot['extra_costs']['total'] ?? 0);
                $result['aggiunte'] = floatval($price_snapshot['extra_costs']['aggiunte'] ?? 0);
                $result['riduzioni'] = floatval($price_snapshot['extra_costs']['riduzioni'] ?? 0);

                $grand_total = floatval($price_snapshot['totals']['grand_total'] ?? 0);
                $result['supplementi'] = floatval($price_snapshot['totals']['supplements_total'] ?? 0);

                // Se il dettaglio snapshot non contiene i costi extra, ricostruiscili
                if ($this->is_near_zero($result['extra_costs']) && (!$this->is_near_zero($result['aggiunte']) || !$this->is_near_zero($result['riduzioni']))) {
                    $computed_extra = floatval($result['aggiunte']) - floatval($result['riduzioni']);
                    if (!$this->is_near_zero($computed_extra)) {
                        $result['extra_costs'] = $computed_extra;
                    }
                }

                $insurance_meta = $this->get_insurance_total_from_meta($preventivo_id);
                if ($this->is_near_zero($result['assicurazioni'])) {
                    if (!$this->is_near_zero($insurance_meta)) {
                        $result['assicurazioni'] = $insurance_meta;
                    }
                } elseif (!$this->is_near_zero($insurance_meta) && abs($insurance_meta - $result['assicurazioni']) > 0.01) {
                    $result['assicurazioni'] = $insurance_meta;
                }

                $extra_meta = $this->get_extra_costs_total_from_meta($preventivo_id);
                if ($this->is_near_zero($result['extra_costs'])) {
                    if (!$this->is_near_zero($extra_meta)) {
                        $result['extra_costs'] = $extra_meta;
                        $result['aggiunte'] = max(0, $extra_meta);
                        $result['riduzioni'] = $extra_meta < 0 ? abs($extra_meta) : 0;
                    }
                } elseif (!$this->is_near_zero($extra_meta) && abs($extra_meta - $result['extra_costs']) > 0.01) {
                    $result['extra_costs'] = $extra_meta;
                    $result['aggiunte'] = max(0, $extra_meta);
                    $result['riduzioni'] = $extra_meta < 0 ? abs($extra_meta) : 0;
                }

                $grand_total_meta = floatval(get_post_meta($preventivo_id, '_totale_preventivo', true));
                if (!$this->is_near_zero($grand_total_meta) && abs($grand_total_meta - $grand_total) > 0.01) {
                    $grand_total = $grand_total_meta;
                }

                $components_sum = $result['base'] + $result['extra_nights'] + $result['extra_costs'] + $result['assicurazioni'];
                $current_total = $components_sum + max(0, floatval($result['supplementi']));
                $difference = round($grand_total - $current_total, 2);

                if ($difference > 0.01 && $this->is_near_zero($result['supplementi'])) {
                    // supplementi assenti, usa la differenza positiva come supplemento
                    $result['supplementi'] = $difference;
                } elseif ($difference < -0.01) {
                    // grand total inferiore alla somma componenti: rimuovi supplementi negativi
                    $result['supplementi'] = max(0, floatval($result['supplementi']) + $difference);
                }

                if ($result['supplementi'] < 0) {
                    $result['supplementi'] = 0;
                }

                $result['totale'] = $this->calculate_total_from_components($result);
                $result['totale_finale'] = round($grand_total, 2);

                if (abs($result['totale'] - $result['totale_finale']) > 0.01) {
                    $result['totale'] = $result['totale_finale'];
                }
                $result['breakdown'] = [
                    'rooms_total' => $price_snapshot['rooms_total'] ?? 0,
                    'extra_nights' => $price_snapshot['extra_nights'] ?? [],
                    'extra_costs' => $price_snapshot['extra_costs'] ?? [],
                    'insurance' => $price_snapshot['insurance'] ?? [],
                ];

                $this->cache[$cache_key] = $result;
                return $result;
            }
        }

        // Fallback sui metadati legacy se lo snapshot non è disponibile o non è valido
        $prezzo_totale = floatval(get_post_meta($preventivo_id, '_prezzo_totale', true));
        $extra_night_total = floatval(get_post_meta($preventivo_id, '_extra_night_total', true));
        $assicurazioni = $this->get_insurance_total_from_meta($preventivo_id);
        $extra_costs_total = $this->get_extra_costs_total_from_meta($preventivo_id);
        $aggiunte = floatval(get_post_meta($preventivo_id, '_totale_aggiunte_extra', true));
        $riduzioni = floatval(get_post_meta($preventivo_id, '_totale_sconti_riduzioni', true));

        if ($this->is_near_zero($extra_costs_total) && (!$this->is_near_zero($aggiunte) || !$this->is_near_zero($riduzioni))) {
            $extra_costs_total = $aggiunte - $riduzioni;
        }
        $grand_total_meta = floatval(get_post_meta($preventivo_id, '_prezzo_totale_completo', true));

        $base_rooms = $prezzo_totale;
        if ($extra_night_total > 0 && $prezzo_totale >= $extra_night_total) {
            $base_rooms = round($prezzo_totale - $extra_night_total, 2);
        } else {
            $extra_night_total = max(0, $extra_night_total);
        }

        if ($grand_total_meta <= 0) {
            $grand_total_meta = $prezzo_totale + $assicurazioni + $extra_costs_total;
        }

        $result['fonte'] = 'meta';
        $result['valid'] = ($grand_total_meta > 0);
        $result['base'] = $base_rooms;
        $result['extra_nights'] = $extra_night_total;
        $result['assicurazioni'] = $assicurazioni;
        $result['extra_costs'] = $extra_costs_total;
        $result['aggiunte'] = $aggiunte;
        $result['riduzioni'] = $riduzioni;

        $components_sum = $base_rooms + $extra_night_total + $extra_costs_total + $assicurazioni;
        $current_total = $components_sum + max(0, floatval($result['supplementi']));
        $supplemento_meta = round($grand_total_meta - $current_total, 2);

        if ($supplemento_meta > 0.01 && $this->is_near_zero($result['supplementi'])) {
            $result['supplementi'] = $supplemento_meta;
        } elseif ($supplemento_meta < -0.01) {
            $result['supplementi'] = max(0, floatval($result['supplementi']) + $supplemento_meta);
        }

        if ($result['supplementi'] < 0) {
            $result['supplementi'] = 0;
        }

        $result['totale'] = $this->calculate_total_from_components($result);
        $result['totale_finale'] = $result['totale'];

        $result['breakdown'] = [
            'meta' => [
                'prezzo_totale' => $prezzo_totale,
                'extra_night_total' => $extra_night_total,
                'totale_assicurazioni' => $assicurazioni,
                'totale_costi_extra' => $extra_costs_total,
            ],
        ];

        $this->cache[$cache_key] = $result;
        return $result;
    }
}

// Funzione helper globale per accesso rapido
if (!function_exists('btr_price_calculator')) {
    function btr_price_calculator() {
        return BTR_Price_Calculator::get_instance();
    }
}
