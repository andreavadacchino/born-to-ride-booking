<?php
/**
 * Born to Ride Booking - Admin AJAX Handler
 * 
 * Gestisce tutte le chiamate AJAX dell'area amministrativa
 * 
 * @package Born_To_Ride_Booking
 */

// Impedisci accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe per gestire le richieste AJAX admin
 */
class BTR_Admin_Ajax {
    
    /**
     * Costruttore
     */
    public function __construct() {
        // Registra le azioni AJAX
        add_action('wp_ajax_btr_get_changelog_suggestions', [$this, 'handle_changelog_suggestions']);
        add_action('wp_ajax_btr_build_release_ajax', [$this, 'handle_build_release']);
        
        // Include i file necessari
        $this->include_dependencies();
    }
    
    /**
     * Include le dipendenze necessarie
     */
    private function include_dependencies() {
        // Non includere qui - verrà incluso solo quando necessario nel metodo handle
    }
    
    /**
     * Gestisce le richieste per ottenere suggerimenti dal CHANGELOG
     */
    public function handle_changelog_suggestions() {
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        // Verifica nonce se fornito
        if (isset($_POST['nonce']) && !wp_verify_nonce($_POST['nonce'], 'btr_ajax_nonce')) {
            wp_send_json_error('Nonce non valido');
        }
        
        try {
            // Include il changelog reader solo quando necessario
            if (!class_exists('BTR_Changelog_Reader')) {
                require_once BTR_PLUGIN_DIR . 'admin/ajax-changelog-reader.php';
            }
            
            $reader = new BTR_Changelog_Reader();
            $changes = $reader->get_all_recent_changes();
            
            if (empty($changes)) {
                // Se il CHANGELOG è vuoto, prova a recuperare da Git
                $git_changes = $reader->get_git_commits(20);
                if (!empty($git_changes)) {
                    $changes = $git_changes;
                } else {
                    wp_send_json_error('Nessuna modifica recente trovata');
                }
            }
            
            wp_send_json_success([
                'changes' => $changes,
                'count' => count($changes),
                'source' => 'CHANGELOG.md + Git commits',
                'timestamp' => current_time('timestamp')
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error('Errore: ' . $e->getMessage());
        }
    }
    
    /**
     * Gestisce la creazione di una nuova release
     */
    public function handle_build_release() {
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permessi insufficienti');
        }
        
        // Verifica nonce
        check_admin_referer('btr_build_release_nonce');
        
        // Delega al file esistente
        $_POST['ajax_action'] = 'btr_build_release';
        include BTR_PLUGIN_DIR . 'admin/build-release-ajax.php';
        exit;
    }
    
    /**
     * Metodo helper per ottenere modifiche formattate per il changelog
     */
    public static function get_formatted_changes_for_version($version) {
        try {
            // Include il changelog reader solo quando necessario
            if (!class_exists('BTR_Changelog_Reader')) {
                require_once BTR_PLUGIN_DIR . 'admin/ajax-changelog-reader.php';
            }
            
            $reader = new BTR_Changelog_Reader();
            $all_changes = $reader->get_recent_changes();
            
            // Filtra solo le modifiche della versione richiesta
            $version_changes = array_filter($all_changes, function($change) use ($version) {
                return $change['version'] === $version;
            });
            
            // Formatta per output
            $formatted = [];
            foreach ($version_changes as $change) {
                $formatted[] = $change['text'];
            }
            
            return $formatted;
            
        } catch (Exception $e) {
            return [];
        }
    }
}

// Inizializza la classe se siamo nell'admin
if (is_admin()) {
    new BTR_Admin_Ajax();
}