<?php
/**
 * Born to Ride Booking - AJAX Changelog Reader
 * 
 * Legge dinamicamente le modifiche recenti dal CHANGELOG.md
 * 
 * @package Born_To_Ride_Booking
 */

// Previeni accesso diretto
if (!defined('ABSPATH')) {
    // Se chiamato direttamente, carica WordPress
    $wp_load = false;
    $paths_to_check = [
        '../../../../../wp-load.php',
        '../../../../wp-load.php',
        '../../../wp-load.php',
    ];
    
    foreach ($paths_to_check as $path) {
        if (file_exists($path)) {
            $wp_load = $path;
            break;
        }
    }
    
    if ($wp_load) {
        require_once($wp_load);
    } else {
        wp_die('WordPress non trovato');
    }
}

// Verifica permessi solo se WordPress è completamente caricato e non siamo in un include
if (!defined('DOING_AJAX') && !defined('WP_ADMIN')) {
    // Solo se chiamato direttamente come test
    if (function_exists('current_user_can') && !current_user_can('manage_options')) {
        wp_send_json_error('Permessi insufficienti');
    }
}

/**
 * Classe per leggere e parsare il CHANGELOG
 */
class BTR_Changelog_Reader {
    
    private $changelog_path;
    private $max_entries = 30;
    
    public function __construct() {
        $this->changelog_path = BTR_PLUGIN_DIR . 'CHANGELOG.md';
    }
    
    /**
     * Legge le modifiche recenti dal CHANGELOG
     */
    public function get_recent_changes() {
        if (!file_exists($this->changelog_path)) {
            return [];
        }
        
        $content = file_get_contents($this->changelog_path);
        $changes = [];
        
        // Dividi il contenuto in sezioni di versione
        $sections = preg_split('/^## \[/m', $content);
        
        // Analizza ogni sezione (salta la prima che è l'header)
        foreach (array_slice($sections, 1, 5) as $section) {
            // Estrai versione e data
            if (preg_match('/^([^\]]+)\]\s*-\s*(.+)$/m', $section, $version_match)) {
                $version = $version_match[1];
                $date = trim($version_match[2]);
                
                // Estrai tutte le modifiche (linee che iniziano con -)
                if (preg_match_all('/^-\s+(.+)$/m', $section, $changes_matches)) {
                    foreach ($changes_matches[1] as $change) {
                        // Pulisci il testo
                        $change = $this->clean_change_text($change);
                        if (!empty($change)) {
                            $changes[] = [
                                'version' => $version,
                                'date' => $date,
                                'text' => $change,
                                'category' => $this->detect_category($change)
                            ];
                        }
                    }
                }
            }
            
            // Limita il numero di modifiche
            if (count($changes) >= $this->max_entries) {
                break;
            }
        }
        
        return $changes;
    }
    
    /**
     * Pulisce il testo della modifica
     */
    private function clean_change_text($text) {
        // Rimuovi markdown bold
        $text = preg_replace('/\*\*([^*]+)\*\*/', '$1', $text);
        
        // Rimuovi backtick
        $text = str_replace('`', '', $text);
        
        // Rimuovi emoji comuni ma mantieni il testo
        $text = preg_replace('/^[🔧🐛✨⚡🔒📚♻️🎨💥🚀]+\s*/', '', $text);
        
        // Trim e normalizza spazi
        $text = trim(preg_replace('/\s+/', ' ', $text));
        
        return $text;
    }
    
    /**
     * Rileva la categoria della modifica
     */
    private function detect_category($text) {
        $text_lower = strtolower($text);
        
        if (strpos($text_lower, 'fix') !== false || strpos($text_lower, 'risolto') !== false) {
            return 'fix';
        } elseif (strpos($text_lower, 'aggiunt') !== false || strpos($text_lower, 'implement') !== false) {
            return 'feature';
        } elseif (strpos($text_lower, 'miglior') !== false || strpos($text_lower, 'ottimizz') !== false) {
            return 'improvement';
        } elseif (strpos($text_lower, 'rimoss') !== false || strpos($text_lower, 'eliminat') !== false) {
            return 'removed';
        } elseif (strpos($text_lower, 'security') !== false || strpos($text_lower, 'sicurezza') !== false) {
            return 'security';
        } else {
            return 'other';
        }
    }
    
    /**
     * Ottiene anche commit Git recenti se disponibili
     */
    public function get_git_commits($limit = 10) {
        $commits = [];
        
        // Trova la directory .git
        $git_dir = BTR_PLUGIN_DIR;
        $found_git = false;
        
        for ($i = 0; $i < 5; $i++) {
            if (is_dir($git_dir . '/.git')) {
                $found_git = true;
                break;
            }
            $git_dir = dirname($git_dir);
        }
        
        if ($found_git) {
            $command = sprintf(
                'cd %s && git log --oneline -n %d --pretty=format:"%%s" -- wp-content/plugins/born-to-ride-booking 2>/dev/null',
                escapeshellarg($git_dir),
                $limit
            );
            
            // SECURITY FIX: exec() disabled for security reasons
            // exec($command, $output, $return_var);
            $return_var = 1; // Force error state
            $output = [];

            // Return empty commits array - git log not available
            return [];
        }
        
        return $commits;
    }
    
    /**
     * Combina modifiche dal CHANGELOG e Git
     */
    public function get_all_recent_changes() {
        $changelog_changes = $this->get_recent_changes();
        $git_commits = $this->get_git_commits();
        
        // Converti modifiche changelog in formato semplice per il form
        $formatted_changes = [];
        
        // Prima aggiungi le modifiche dal CHANGELOG
        foreach ($changelog_changes as $change) {
            $formatted_changes[] = $change['text'];
        }
        
        // Poi aggiungi i commit Git non già presenti
        foreach ($git_commits as $commit) {
            $commit_clean = $this->clean_change_text($commit);
            
            // Evita duplicati
            $is_duplicate = false;
            foreach ($formatted_changes as $existing) {
                if (stripos($existing, $commit_clean) !== false || stripos($commit_clean, $existing) !== false) {
                    $is_duplicate = true;
                    break;
                }
            }
            
            if (!$is_duplicate && !empty($commit_clean)) {
                $formatted_changes[] = $commit_clean;
            }
        }
        
        // Rimuovi duplicati e limita
        $formatted_changes = array_unique($formatted_changes);
        $formatted_changes = array_slice($formatted_changes, 0, 20);
        
        return $formatted_changes;
    }
}

// Gestisci la richiesta AJAX
if (defined('DOING_AJAX') && DOING_AJAX) {
    add_action('wp_ajax_btr_get_changelog_suggestions', 'btr_handle_changelog_suggestions');
}

function btr_handle_changelog_suggestions() {
    // Verifica nonce se fornito
    if (isset($_POST['nonce']) && !wp_verify_nonce($_POST['nonce'], 'btr_ajax_nonce')) {
        wp_send_json_error('Nonce non valido');
    }
    
    try {
        $reader = new BTR_Changelog_Reader();
        $changes = $reader->get_all_recent_changes();
        
        if (empty($changes)) {
            wp_send_json_error('Nessuna modifica recente trovata');
        }
        
        wp_send_json_success([
            'changes' => $changes,
            'count' => count($changes),
            'source' => 'CHANGELOG.md + Git'
        ]);
        
    } catch (Exception $e) {
        wp_send_json_error('Errore: ' . $e->getMessage());
    }
}

// Se chiamato direttamente (per test)
if (isset($_GET['test']) && current_user_can('manage_options')) {
    $reader = new BTR_Changelog_Reader();
    $changes = $reader->get_all_recent_changes();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => [
            'changes' => $changes,
            'count' => count($changes)
        ]
    ]);
    exit;
}