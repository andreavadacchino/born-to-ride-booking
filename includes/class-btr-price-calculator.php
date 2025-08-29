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
        $result = [
            'base' => 0,
            'extra_nights' => 0,
            'extra_costs' => 0,
            'assicurazioni' => 0,
            'supplementi' => 0,
            'totale' => 0,
            'breakdown' => []
        ];
        
        // TODO: Implementare calcolo completo preventivo
        // Questo sarà il metodo principale che orchestra tutti gli altri calcoli
        
        return $result;
    }
}

// Funzione helper globale per accesso rapido
if (!function_exists('btr_price_calculator')) {
    function btr_price_calculator() {
        return BTR_Price_Calculator::get_instance();
    }
}