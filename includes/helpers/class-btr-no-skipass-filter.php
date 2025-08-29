<?php
/**
 * Helper class per filtrare RC Skipass quando c'è No Skipass
 * 
 * @since 1.0.81
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_No_Skipass_Filter {
    
    /**
     * Filtra i dati anagrafici rimuovendo RC Skipass per chi ha No Skipass
     * 
     * @param array $anagrafici_data Dati anagrafici
     * @param int $preventivo_id ID del preventivo
     * @return array Dati filtrati
     */
    public static function filter_rc_skipass($anagrafici_data, $preventivo_id = null) {
        if (empty($anagrafici_data) || !is_array($anagrafici_data)) {
            return $anagrafici_data;
        }
        
        // Debug
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[BTR No Skipass Filter] ========== INIZIO FILTRO RC SKIPASS ==========');
            error_log('[BTR No Skipass Filter] Preventivo ID: ' . $preventivo_id);
            error_log('[BTR No Skipass Filter] Numero partecipanti: ' . count($anagrafici_data));
        }
        
        // Processa ogni partecipante
        foreach ($anagrafici_data as $index => &$partecipante) {
            // Verifica se questo partecipante ha No Skipass
            $has_no_skipass = false;
            
            // Controlla nei costi extra
            if (!empty($partecipante['costi_extra']) && is_array($partecipante['costi_extra'])) {
                // Cerca lo slug "no-skipass"
                if (isset($partecipante['costi_extra']['no-skipass']) && $partecipante['costi_extra']['no-skipass'] == '1') {
                    $has_no_skipass = true;
                }
                
                // Fallback: cerca anche "No Skipass" nel nome
                foreach ($partecipante['costi_extra'] as $slug => $info) {
                    if (is_array($info) && !empty($info['selected'])) {
                        if (isset($info['nome']) && stripos($info['nome'], 'no skipass') !== false) {
                            $has_no_skipass = true;
                            break;
                        }
                    }
                }
            }
            
            // Controlla anche nei costi extra dettagliati
            if (!$has_no_skipass && !empty($partecipante['costi_extra_dettagliate']) && is_array($partecipante['costi_extra_dettagliate'])) {
                foreach ($partecipante['costi_extra_dettagliate'] as $slug => $dettagli) {
                    if (($slug === 'no-skipass' || (isset($dettagli['nome']) && stripos($dettagli['nome'], 'no skipass') !== false)) 
                        && !empty($dettagli['attivo'])) {
                        $has_no_skipass = true;
                        break;
                    }
                }
            }
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $nome = trim(($partecipante['nome'] ?? '') . ' ' . ($partecipante['cognome'] ?? ''));
                error_log("[BTR No Skipass Filter] Partecipante $index ($nome) - Ha No Skipass: " . ($has_no_skipass ? 'SI' : 'NO'));
            }
            
            // Se ha No Skipass, rimuovi RC Skipass dalle assicurazioni
            if ($has_no_skipass) {
                // Rimuovi da assicurazioni base
                if (!empty($partecipante['assicurazioni']) && is_array($partecipante['assicurazioni'])) {
                    unset($partecipante['assicurazioni']['rc-skipass']);
                    unset($partecipante['assicurazioni']['assicurazione-rc-skipass']);
                    
                    // Cerca e rimuovi qualsiasi chiave che contenga "rc" e "skipass"
                    foreach ($partecipante['assicurazioni'] as $slug => $value) {
                        if (stripos($slug, 'rc') !== false && stripos($slug, 'skipass') !== false) {
                            unset($partecipante['assicurazioni'][$slug]);
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                error_log("[BTR No Skipass Filter] Rimossa assicurazione: $slug");
                            }
                        }
                    }
                }
                
                // Rimuovi da assicurazioni dettagliate
                if (!empty($partecipante['assicurazioni_dettagliate']) && is_array($partecipante['assicurazioni_dettagliate'])) {
                    unset($partecipante['assicurazioni_dettagliate']['rc-skipass']);
                    unset($partecipante['assicurazioni_dettagliate']['assicurazione-rc-skipass']);
                    
                    // Cerca e rimuovi qualsiasi chiave che contenga "rc" e "skipass"
                    foreach ($partecipante['assicurazioni_dettagliate'] as $slug => $dettagli) {
                        if (stripos($slug, 'rc') !== false && stripos($slug, 'skipass') !== false) {
                            unset($partecipante['assicurazioni_dettagliate'][$slug]);
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                error_log("[BTR No Skipass Filter] Rimossa assicurazione dettagliata: $slug");
                            }
                        }
                        // Controlla anche il nome/descrizione
                        elseif (isset($dettagli['descrizione']) && stripos($dettagli['descrizione'], 'rc skipass') !== false) {
                            unset($partecipante['assicurazioni_dettagliate'][$slug]);
                            if (defined('WP_DEBUG') && WP_DEBUG) {
                                error_log("[BTR No Skipass Filter] Rimossa assicurazione dettagliata per descrizione: $slug");
                            }
                        }
                    }
                }
            }
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[BTR No Skipass Filter] ========== FINE FILTRO RC SKIPASS ==========');
        }
        
        return $anagrafici_data;
    }
    
    /**
     * Verifica se un preventivo ha No Skipass globale
     * 
     * @param int $preventivo_id
     * @return bool
     */
    public static function has_global_no_skipass($preventivo_id) {
        // Controlla il meta _no_skipass
        $no_skipass = get_post_meta($preventivo_id, '_no_skipass', true);
        if ($no_skipass) {
            return true;
        }
        
        // Controlla nei costi extra globali
        $costi_extra = get_post_meta($preventivo_id, '_costi_extra', true);
        if (!empty($costi_extra) && is_array($costi_extra)) {
            if (!empty($costi_extra['no-skipass'])) {
                return true;
            }
        }
        
        return false;
    }
}