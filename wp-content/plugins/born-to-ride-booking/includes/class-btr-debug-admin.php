<?php
/**
 * Classe per aggiungere pagine di debug nell'admin WordPress
 * Solo per sviluppo/test - rimuovere in produzione
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Debug_Admin {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_debug_menu']);
    }
    
    /**
     * Aggiunge menu di debug nell'admin
     */
    public function add_debug_menu() {
        // Solo per utenti admin e solo se WP_DEBUG è attivo
        if (!current_user_can('manage_options') || !WP_DEBUG) {
            return;
        }
        
        add_menu_page(
            'BTR Debug',
            'BTR Debug',
            'manage_options',
            'btr-debug',
            [$this, 'debug_main_page'],
            'dashicons-bug',
            30
        );
        
        add_submenu_page(
            'btr-debug',
            'DEBUG FLUSSO REALE',
            'DEBUG FLUSSO REALE',
            'manage_options',
            'test-real-flow-debug',
            [$this, 'test_real_flow_debug_page']
        );
        
        add_submenu_page(
            'btr-debug',
            'Debug Costi Extra',
            'Debug Costi Extra',
            'manage_options',
            'debug-costi-extra',
            [$this, 'debug_costi_extra_page']
        );
        
        add_submenu_page(
            'btr-debug',
            'Test Preliminare Sistema',
            'Test Preliminare Sistema',
            'manage_options',
            'test-preliminary-check',
            [$this, 'test_preliminary_check_page']
        );
        
        add_submenu_page(
            'btr-debug',
            'Test Preventivo 29594',
            'Test Preventivo 29594',
            'manage_options',
            'test-preventivo-29594',
            [$this, 'test_preventivo_29594_page']
        );
        
        add_submenu_page(
            'btr-debug',
            'Debug Costi Extra',
            'Debug Costi Extra',
            'manage_options',
            'debug-costi-extra',
            [$this, 'debug_costi_extra_page']
        );
        
        add_submenu_page(
            'btr-debug',
            'Test Refactoring',
            'Test Refactoring',
            'manage_options',
            'test-refactoring',
            [$this, 'test_refactoring_page']
        );
        
                    add_submenu_page(
                'btr-debug',
                'Test Salvataggio Costi Extra',
                'Test Salvataggio Costi Extra',
                'manage_options',
                'test-salvataggio-costi',
                [$this, 'test_salvataggio_costi_page']
            );
            
            add_submenu_page(
                'btr-debug',
                'Test Payload Costi Extra',
                'Test Payload Costi Extra',
                'manage_options',
                'test-payload-costi',
                [$this, 'test_payload_costi_page']
            );
            
            add_submenu_page(
                'btr-debug',
                'Test Completo Verifica Costi Extra',
                'Test Completo Verifica',
                'manage_options',
                'test-complete-verification',
                [$this, 'test_complete_verification_page']
            );
            
            add_submenu_page(
                'btr-debug',
                'Test Database Metadati',
                'Test Database Metadati',
                'manage_options',
                'test-database-metadata',
                [$this, 'test_database_metadata_page']
            );
            
            add_submenu_page(
                'btr-debug',
                'Test Simulazione AJAX Reale',
                'Test AJAX Reale',
                'manage_options',
                'test-ajax-simulation',
                [$this, 'test_ajax_simulation_page']
            );
            
            add_submenu_page(
                'btr-debug',
                'Test Implementazione Migliorata',
                'Test Implementazione Migliorata',
                'manage_options',
                'test-improved-implementation',
                [$this, 'test_improved_implementation_page']
            );
            
            add_submenu_page(
                'btr-debug',
                'Test Immediato Fix Verifica',
                'Test Immediato Fix Verifica',
                'manage_options',
                'test-immediate-fix-verification',
                [$this, 'test_immediate_fix_verification_page']
            );
    }
    
    /**
     * Pagina principale di debug
     */
    public function debug_main_page() {
        echo '<div class="wrap">';
        echo '<h1>BTR Plugin Debug</h1>';
        echo '<div class="notice notice-warning"><p><strong>Attenzione:</strong> Queste pagine sono solo per debug e sviluppo.</p></div>';
        
        echo '<div class="card">';
        echo '<h2>Strumenti Disponibili</h2>';
        echo '<h3 style="color: #dc3545;">🚨 DEBUG PROBLEMA REALE</h3>';
        echo '<ul>';
        echo '<li><a href="' . admin_url('admin.php?page=test-immediate-fix-verification') . '" style="font-weight: bold; color: #dc3545; font-size: 18px;">⚡ TEST IMMEDIATO FIX VERIFICA</a> - <strong>NUOVA CORREZIONE</strong> - Testa il fix con il payload reale dell\'utente</li>';
        echo '<li><a href="' . admin_url('admin.php?page=test-real-flow-debug') . '" style="font-weight: bold; color: #dc3545; font-size: 16px;">🚨 DEBUG FLUSSO REALE</a> - <strong>PROBLEMA ATTUALE</strong> - Analizza perché i costi extra non vengono salvati</li>';
        echo '</ul>';
        echo '<h3 style="color: #0073aa;">🔧 Test Preliminari</h3>';
        echo '<ul>';
        echo '<li><a href="' . admin_url('admin.php?page=test-preliminary-check') . '" style="font-weight: bold; color: #d63384;">🔧 Test Preliminare Sistema</a> - <strong>INIZIA QUI</strong> - Verifica configurazione sistema</li>';
        echo '</ul>';
        echo '<h3 style="color: #0073aa;">🧪 Test Specifici</h3>';
        echo '<ul>';
        echo '<li><a href="' . admin_url('admin.php?page=test-preventivo-29594') . '">Test Preventivo 29594</a> - Test specifico per il preventivo problematico</li>';
        echo '<li><a href="' . admin_url('admin.php?page=debug-costi-extra') . '">Debug Costi Extra</a> - Test funzioni costi extra</li>';
        echo '<li><a href="' . admin_url('admin.php?page=test-refactoring') . '">Test Refactoring</a> - Test generale del refactoring</li>';
                    echo '<li><a href="' . admin_url('admin.php?page=test-salvataggio-costi') . '">Test Salvataggio Costi Extra</a> - Test completo flusso salvataggio</li>';
            echo '<li><a href="' . admin_url('admin.php?page=test-payload-costi') . '">Test Payload Costi Extra</a> - Test specifico con payload utente</li>';
            echo '<li><a href="' . admin_url('admin.php?page=test-complete-verification') . '">Test Completo Verifica</a> - Test completo salvataggio costi extra nei metadati</li>';
            echo '<li><a href="' . admin_url('admin.php?page=test-database-metadata') . '">Test Database Metadati</a> - Verifica diretta database e metadati preventivi</li>';
            echo '<li><a href="' . admin_url('admin.php?page=test-ajax-simulation') . '">Test AJAX Reale</a> - Simulazione completa chiamata create_preventivo con verifica risultati</li>';
            echo '<li><a href="' . admin_url('admin.php?page=test-improved-implementation') . '">Test Implementazione Migliorata</a> - Test metadati aggregati e performance query</li>';
            echo '<li><a href="' . admin_url('admin.php?page=test-immediate-fix-verification') . '">Test Immediato Fix Verifica</a> - Test correzione con payload reale utente</li>';
        echo '</ul>';
        echo '</div>';
        
        // Mostra info sistema
        echo '<div class="card">';
        echo '<h2>Info Sistema</h2>';
        echo '<p><strong>Plugin Version:</strong> ' . (defined('BTR_VERSION') ? BTR_VERSION : 'Non definita') . '</p>';
        echo '<p><strong>WordPress Version:</strong> ' . get_bloginfo('version') . '</p>';
        echo '<p><strong>PHP Version:</strong> ' . PHP_VERSION . '</p>';
        echo '<p><strong>Debug Mode:</strong> ' . (WP_DEBUG ? 'Attivo' : 'Disattivo') . '</p>';
        echo '<p><strong>Classe BTR_Preventivi:</strong> ' . (class_exists('BTR_Preventivi') ? 'Caricata' : 'Non caricata') . '</p>';
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Pagina debug flusso reale
     */
    public function test_real_flow_debug_page() {
        echo '<div class="wrap">';
        echo '<h1>DEBUG FLUSSO REALE - Costi Extra Non Salvati</h1>';
        
        // Include il file di test
        $test_file = BTR_PLUGIN_DIR . 'tests/test-real-flow-debugging.php';
        if (file_exists($test_file)) {
            include $test_file;
        } else {
            echo '<div class="notice notice-error"><p>File di test non trovato: ' . $test_file . '</p></div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Pagina test preliminare sistema
     */
    public function test_preliminary_check_page() {
        echo '<div class="wrap">';
        echo '<h1>Test Preliminare Sistema</h1>';
        
        // Include il file di test
        $test_file = BTR_PLUGIN_DIR . 'tests/test-preliminary-system-check.php';
        if (file_exists($test_file)) {
            include $test_file;
        } else {
            echo '<div class="notice notice-error"><p>File di test non trovato: ' . $test_file . '</p></div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Pagina test preventivo 29594
     */
    public function test_preventivo_29594_page() {
        echo '<div class="wrap">';
        echo '<h1>Test Preventivo 29594</h1>';
        
        // Include il file di test
        $test_file = BTR_PLUGIN_DIR . 'tests/test-preventivo-29594-fixed.php';
        if (file_exists($test_file)) {
            include $test_file;
        } else {
            echo '<div class="notice notice-error"><p>File di test non trovato: ' . $test_file . '</p></div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Pagina debug costi extra riepilogo
     */
    public function debug_costi_extra_page() {
        echo '<div class="wrap">';
        echo '<h1>Debug Costi Extra Riepilogo</h1>';
        
        // Include il file di test
        $test_file = BTR_PLUGIN_DIR . 'tests/debug-costi-extra-riepilogo.php';
        if (file_exists($test_file)) {
            include $test_file;
        } else {
            echo '<div class="notice notice-error"><p>File di test non trovato: ' . $test_file . '</p></div>';
        }
        
        echo '</div>';
    }
    
    /**
     * Pagina test refactoring
     */
    public function test_refactoring_page() {
        echo '<div class="wrap">';
        echo '<h1>Test Refactoring Generale</h1>';
        
        // Include il file di test
        $test_file = BTR_PLUGIN_DIR . 'tests/test-preventivo-review-refactor.php';
        if (file_exists($test_file)) {
            include $test_file;
        } else {
            echo '<div class="notice notice-error"><p>File di test non trovato: ' . $test_file . '</p></div>';
        }
        
        echo '</div>';
    }
    
          /**
       * Pagina test salvataggio costi extra
       */
      public function test_salvataggio_costi_page() {
          echo '<div class="wrap">';
          echo '<h1>Test Salvataggio Costi Extra</h1>';
          
          // Include il file di test
          $test_file = BTR_PLUGIN_DIR . 'tests/test-costi-extra-salvataggio.php';
          if (file_exists($test_file)) {
              include $test_file;
          } else {
              echo '<div class="notice notice-error"><p>File di test non trovato: ' . $test_file . '</p></div>';
          }
          
          echo '</div>';
      }
      
      /**
       * Pagina test payload costi extra
       */
      public function test_payload_costi_page() {
          echo '<div class="wrap">';
          echo '<h1>Test Payload Costi Extra</h1>';
          
          // Include il file di test
          $test_file = BTR_PLUGIN_DIR . 'tests/test-payload-costi-extra.php';
          if (file_exists($test_file)) {
              include $test_file;
          } else {
              echo '<div class="notice notice-error"><p>File di test non trovato: ' . $test_file . '</p></div>';
          }
          
          echo '</div>';
      }
      
      /**
       * Pagina test completo verifica costi extra
       */
      public function test_complete_verification_page() {
          echo '<div class="wrap">';
          echo '<h1>Test Completo Verifica Costi Extra</h1>';
          
          // Include il file di test
          $test_file = BTR_PLUGIN_DIR . 'tests/test-complete-costi-extra-verification.php';
          if (file_exists($test_file)) {
              include $test_file;
          } else {
              echo '<div class="notice notice-error"><p>File di test non trovato: ' . $test_file . '</p></div>';
          }
          
          echo '</div>';
      }
      
      /**
       * Pagina test database metadati
       */
      public function test_database_metadata_page() {
          echo '<div class="wrap">';
          echo '<h1>Test Database Metadati</h1>';
          
          // Include il file di test
          $test_file = BTR_PLUGIN_DIR . 'tests/test-database-metadata-verification.php';
          if (file_exists($test_file)) {
              include $test_file;
          } else {
              echo '<div class="notice notice-error"><p>File di test non trovato: ' . $test_file . '</p></div>';
          }
          
          echo '</div>';
      }
      
      /**
       * Pagina test simulazione AJAX reale
       */
      public function test_ajax_simulation_page() {
          echo '<div class="wrap">';
          echo '<h1>Test Simulazione AJAX Reale</h1>';
          
          // Include il file di test
          $test_file = BTR_PLUGIN_DIR . 'tests/test-real-ajax-simulation.php';
          if (file_exists($test_file)) {
              include $test_file;
          } else {
              echo '<div class="notice notice-error"><p>File di test non trovato: ' . $test_file . '</p></div>';
          }
          
          echo '</div>';
      }
      
      /**
       * Pagina test implementazione migliorata
       */
      public function test_improved_implementation_page() {
          echo '<div class="wrap">';
          echo '<h1>Test Implementazione Migliorata</h1>';
          
          // Include il file di test
          $test_file = BTR_PLUGIN_DIR . 'tests/test-improved-extra-costs-implementation.php';
          if (file_exists($test_file)) {
              include $test_file;
          } else {
              echo '<div class="notice notice-error"><p>File di test non trovato: ' . $test_file . '</p></div>';
          }
          
          echo '</div>';
      }
      
      /**
       * Pagina test immediato fix verifica
       */
      public function test_immediate_fix_verification_page() {
          echo '<div class="wrap">';
          echo '<h1>Test Immediato Fix Verifica</h1>';
          
          // Include il file di test
          $test_file = BTR_PLUGIN_DIR . 'tests/test-immediate-fix-verification.php';
          if (file_exists($test_file)) {
              include $test_file;
          } else {
              echo '<div class="notice notice-error"><p>File di test non trovato: ' . $test_file . '</p></div>';
          }
          
          echo '</div>';
      }
}

// Inizializza solo se in modalità debug
if (WP_DEBUG && is_admin()) {
    new BTR_Debug_Admin();
}