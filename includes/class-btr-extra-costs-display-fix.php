<?php
/**
 * Fix per la visualizzazione corretta dei costi extra
 * 
 * Questo file corregge il problema dove "a notte" viene mostrato
 * per tutti i costi extra anche quando non dovrebbe
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Extra_Costs_Display_Fix {
    
    /**
     * Formatta correttamente la visualizzazione di un costo extra
     * 
     * @param array $costo_dettaglio I dettagli del costo extra
     * @param string $nome_persona Il nome della persona (opzionale)
     * @return string HTML formattato per la visualizzazione
     */
    public static function format_extra_cost_display($costo_dettaglio, $nome_persona = '') {
        if (empty($costo_dettaglio)) {
            return '';
        }
        
        $importo = floatval($costo_dettaglio['importo'] ?? 0);
        $nome = $costo_dettaglio['descrizione'] ?? $costo_dettaglio['nome'] ?? 'Costo Extra';
        $moltiplica_durata = !empty($costo_dettaglio['moltiplica_durata']);
        $moltiplica_persone = !empty($costo_dettaglio['moltiplica_persone']);
        
        // Formatta l'importo
        $importo_formattato = number_format(abs($importo), 2, ',', '.');
        
        // Determina il prefisso per importi negativi
        $prefisso = $importo < 0 ? '-€' : '€';
        
        // Determina il suffisso basato sui flag di moltiplicazione
        $suffisso = '';
        if ($moltiplica_persone && $moltiplica_durata) {
            $suffisso = ' per persona per notte';
        } elseif ($moltiplica_persone) {
            $suffisso = ' per persona';
        } elseif ($moltiplica_durata) {
            $suffisso = ' per notte';
        }
        // Se nessun flag è attivo, è un costo fisso e non aggiungiamo suffisso
        
        // Costruisci la stringa di output
        $output = '';
        if (!empty($nome_persona)) {
            $output .= esc_html($nome_persona) . ' – ';
        }
        $output .= esc_html($nome) . ' ';
        $output .= $prefisso . $importo_formattato . $suffisso;
        
        return $output;
    }
    
    /**
     * Genera l'HTML per un riepilogo di costi extra
     * 
     * @param array $anagrafici Array dei partecipanti con i loro costi extra
     * @param bool $show_person_name Se mostrare il nome della persona
     * @return string HTML del riepilogo
     */
    public static function generate_extra_costs_summary_html($anagrafici, $show_person_name = true) {
        if (empty($anagrafici) || !is_array($anagrafici)) {
            return '';
        }
        
        $html = '<div class="btr-extra-costs-summary">';
        $html .= '<h3>' . esc_html__('Costi Extra', 'born-to-ride-booking') . '</h3>';
        
        $has_costs = false;
        
        foreach ($anagrafici as $idx => $persona) {
            if (empty($persona['costi_extra_dettagliate']) || !is_array($persona['costi_extra_dettagliate'])) {
                continue;
            }
            
            $nome_persona = '';
            if ($show_person_name) {
                $nome = $persona['nome'] ?? '';
                $cognome = $persona['cognome'] ?? '';
                $nome_persona = trim("$nome $cognome");
                if (empty($nome_persona)) {
                    $nome_persona = sprintf(__('Partecipante %d', 'born-to-ride-booking'), $idx + 1);
                }
            }
            
            foreach ($persona['costi_extra_dettagliate'] as $slug => $dettaglio) {
                // Salta i costi non attivi o con importo 0
                if (empty($dettaglio['attivo']) && floatval($dettaglio['importo'] ?? 0) == 0) {
                    continue;
                }
                
                $has_costs = true;
                $html .= '<div class="btr-extra-cost-item">';
                $html .= self::format_extra_cost_display($dettaglio, $nome_persona);
                $html .= '</div>';
            }
        }
        
        if (!$has_costs) {
            $html .= '<p>' . esc_html__('Nessun costo extra selezionato', 'born-to-ride-booking') . '</p>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Hook per sostituire la visualizzazione errata dei costi extra
     */
    public static function init() {
        // Aggiungi filtri per correggere la visualizzazione
        add_filter('btr_format_extra_cost_display', array(__CLASS__, 'format_extra_cost_display'), 10, 2);
        add_filter('btr_generate_extra_costs_summary', array(__CLASS__, 'generate_extra_costs_summary_html'), 10, 2);
        
        // Aggiungi CSS per lo styling
        add_action('wp_head', array(__CLASS__, 'add_inline_styles'));
    }
    
    /**
     * Aggiunge stili CSS inline per la visualizzazione
     */
    public static function add_inline_styles() {
        ?>
        <style>
            .btr-extra-costs-summary {
                margin: 20px 0;
                padding: 15px;
                background: #f9f9f9;
                border-radius: 5px;
            }
            .btr-extra-cost-item {
                padding: 8px 0;
                border-bottom: 1px solid #eee;
            }
            .btr-extra-cost-item:last-child {
                border-bottom: none;
            }
        </style>
        <?php
    }
    
    /**
     * Utility: Verifica se un costo extra dovrebbe mostrare "a notte"
     * 
     * @param array $costo_dettaglio I dettagli del costo
     * @return bool True se dovrebbe mostrare "a notte"
     */
    public static function should_show_per_night($costo_dettaglio) {
        return !empty($costo_dettaglio['moltiplica_durata']);
    }
    
    /**
     * Utility: Verifica se un costo extra dovrebbe mostrare "per persona"
     * 
     * @param array $costo_dettaglio I dettagli del costo
     * @return bool True se dovrebbe mostrare "per persona"
     */
    public static function should_show_per_person($costo_dettaglio) {
        return !empty($costo_dettaglio['moltiplica_persone']);
    }
}

// Inizializza la classe
BTR_Extra_Costs_Display_Fix::init();