<?php
/**
 * Menu sviluppatore per Born to Ride Booking
 * Questa classe NON viene inclusa nella distribuzione del plugin
 * 
 * @package BornToRideBooking/Admin
 * @since 1.0.26
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe per gestire il menu sviluppatore
 */
class BTR_Developer_Menu {
    
    /**
     * Costruttore
     */
    public function __construct() {
        // Aggiungi menu solo se siamo in ambiente di sviluppo
        if ($this->is_development_environment()) {
            add_action('admin_menu', [$this, 'add_developer_menu'], 100);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_developer_styles']);
            
            // Registra handler AJAX per build release
            add_action('wp_ajax_btr_build_release_ajax', [$this, 'handle_build_release_ajax']);
        }
    }
    
    /**
     * Verifica se siamo in ambiente di sviluppo
     * 
     * @return bool
     */
    private function is_development_environment() {
        // Verifica vari indicatori di ambiente sviluppo
        return (
            defined('WP_DEBUG') && WP_DEBUG === true ||
            defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'local' ||
            defined('BTR_DEV_MODE') && BTR_DEV_MODE === true ||
            file_exists(BTR_PLUGIN_DIR . '/build-release.php')
        );
    }
    
    /**
     * Aggiunge il menu sviluppatore
     */
    public function add_developer_menu() {
        // Menu principale sviluppatore
        add_menu_page(
            'BTR Developer Tools',
            'BTR Dev Tools',
            'manage_options',
            'btr-developer',
            [$this, 'render_developer_dashboard'],
            'dashicons-hammer',
            99
        );
        
        // Sottomenu Build Release
        add_submenu_page(
            'btr-developer',
            'Build Release',
            '🚀 Build Release',
            'manage_options',
            'btr-build-release',
            [$this, 'render_build_release']
        );
        
        // Sottomenu Build ZIP
        add_submenu_page(
            'btr-developer',
            'Build ZIP',
            '📦 Build ZIP',
            'manage_options',
            'btr-build-zip',
            [$this, 'render_build_zip']
        );
        
        // Sottomenu Archivio Build
        add_submenu_page(
            'btr-developer',
            'Archivio Build',
            '📚 Archivio Build',
            'manage_options',
            'btr-build-archive',
            [$this, 'render_build_archive']
        );
        
        // Sottomenu Test Scripts
        if (is_dir(BTR_PLUGIN_DIR . '/tests')) {
            add_submenu_page(
                'btr-developer',
                'Test Scripts',
                '🧪 Test Scripts',
                'manage_options',
                'btr-test-scripts',
                [$this, 'render_test_scripts']
            );
        }
        
        // Sottomenu Documentation
        add_submenu_page(
            'btr-developer',
            'Documentation',
            '📚 Documentation',
            'manage_options',
            'btr-documentation',
            [$this, 'render_documentation']
        );
    }
    
    /**
     * Enqueue stili per le pagine sviluppatore
     */
    public function enqueue_developer_styles($hook) {
        if (strpos($hook, 'btr-developer') === false && 
            strpos($hook, 'btr-build') === false && 
            strpos($hook, 'btr-test') === false &&
            strpos($hook, 'btr-documentation') === false) {
            return;
        }
        
        // Inline styles per evitare file CSS aggiuntivi
        wp_add_inline_style('wp-admin', '
            .btr-dev-container {
                max-width: 1200px;
                margin: 20px auto;
                padding: 20px;
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            .btr-dev-header {
                border-bottom: 2px solid #0097c5;
                padding-bottom: 20px;
                margin-bottom: 30px;
            }
            .btr-dev-header h1 {
                color: #0097c5;
                margin: 0 0 10px 0;
            }
            .btr-dev-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 20px;
                margin-top: 30px;
            }
            .btr-dev-card {
                background: #f8f9fa;
                border: 1px solid #e9ecef;
                border-radius: 8px;
                padding: 20px;
                transition: all 0.3s ease;
            }
            .btr-dev-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                border-color: #0097c5;
            }
            .btr-dev-card h3 {
                margin-top: 0;
                color: #333;
            }
            .btr-dev-card .button {
                margin-top: 15px;
            }
            .btr-dev-warning {
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                color: #856404;
                padding: 15px;
                border-radius: 5px;
                margin-bottom: 20px;
            }
            .btr-dev-iframe-container {
                width: 100%;
                height: calc(100vh - 200px);
                min-height: 600px;
                border: 1px solid #ddd;
                border-radius: 5px;
                overflow: hidden;
                background: #fff;
            }
            .btr-dev-iframe-container iframe {
                width: 100%;
                height: 100%;
                border: none;
            }
            .btr-test-list {
                list-style: none;
                padding: 0;
            }
            .btr-test-list li {
                padding: 10px;
                border-bottom: 1px solid #eee;
            }
            .btr-test-list li:hover {
                background: #f5f5f5;
            }
        ');
    }
    
    /**
     * Render dashboard sviluppatore
     */
    public function render_developer_dashboard() {
        ?>
        <div class="wrap btr-dev-container">
            <div class="btr-dev-header">
                <h1>🛠️ Born to Ride Developer Tools</h1>
                <p>Strumenti di sviluppo e manutenzione per il plugin Born to Ride Booking</p>
            </div>
            
            <?php if (!$this->is_development_environment()): ?>
                <div class="btr-dev-warning">
                    <strong>⚠️ Attenzione:</strong> Questi strumenti sono visibili solo in ambiente di sviluppo. 
                    Non saranno inclusi nella distribuzione finale del plugin.
                </div>
            <?php endif; ?>
            
            <div class="btr-dev-grid">
                <div class="btr-dev-card">
                    <h3>🚀 Build Release</h3>
                    <p>Crea una nuova release del plugin con aggiornamento automatico della versione e changelog.</p>
                    <a href="<?php echo admin_url('admin.php?page=btr-build-release'); ?>" class="button button-primary">
                        Crea Release
                    </a>
                </div>
                
                <div class="btr-dev-card">
                    <h3>📦 Build ZIP</h3>
                    <p>Genera un archivio ZIP del plugin senza aggiornare la versione.</p>
                    <a href="<?php echo admin_url('admin.php?page=btr-build-zip'); ?>" class="button">
                        Genera ZIP
                    </a>
                </div>
                
                <div class="btr-dev-card">
                    <h3>📚 Archivio Build</h3>
                    <p>Visualizza e gestisci tutti i build ZIP creati del plugin.</p>
                    <a href="<?php echo admin_url('admin.php?page=btr-build-archive'); ?>" class="button">
                        Vedi Archivio
                    </a>
                </div>
                
                <?php if (is_dir(BTR_PLUGIN_DIR . '/tests')): ?>
                <div class="btr-dev-card">
                    <h3>🧪 Test Scripts</h3>
                    <p>Esegui script di test e debug per verificare le funzionalità del plugin.</p>
                    <a href="<?php echo admin_url('admin.php?page=btr-test-scripts'); ?>" class="button">
                        Vedi Test
                    </a>
                </div>
                <?php endif; ?>
                
                <div class="btr-dev-card">
                    <h3>📚 Documentation</h3>
                    <p>Visualizza la documentazione tecnica e i file README del plugin.</p>
                    <a href="<?php echo admin_url('admin.php?page=btr-documentation'); ?>" class="button">
                        Vedi Docs
                    </a>
                </div>
                
                <div class="btr-dev-card">
                    <h3>📊 Plugin Info</h3>
                    <p><strong>Versione:</strong> <?php echo BTR_VERSION; ?></p>
                    <p><strong>Directory:</strong> <?php echo basename(BTR_PLUGIN_DIR); ?></p>
                    <p><strong>Debug Mode:</strong> <?php echo BTR_DEBUG ? '✅ Attivo' : '❌ Disattivo'; ?></p>
                    <?php 
                    // Conta i build esistenti
                    $build_dir = BTR_PLUGIN_DIR . 'build/';
                    $build_count = 0;
                    if (is_dir($build_dir)) {
                        $files = glob($build_dir . '*.zip');
                        $build_count = count($files);
                    }
                    ?>
                    <p><strong>Build creati:</strong> <?php echo $build_count; ?></p>
                </div>
                
                <div class="btr-dev-card">
                    <h3>🔧 Quick Actions</h3>
                    <p>
                        <a href="<?php echo BTR_PLUGIN_URL . 'CHANGELOG.md'; ?>" target="_blank">View Changelog</a> |
                        <a href="<?php echo BTR_PLUGIN_URL . '.distignore'; ?>" target="_blank">View .distignore</a>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render pagina build release
     */
    public function render_build_release() {
        // Usa l'interfaccia AJAX invece dell'iframe
        include BTR_PLUGIN_DIR . 'admin/build-release-ajax.php';
    }
    
    /**
     * Render pagina build ZIP
     */
    public function render_build_zip() {
        $build_zip_url = BTR_PLUGIN_URL . 'build-plugin-zip.php';
        ?>
        <div class="wrap btr-dev-container">
            <div class="btr-dev-header">
                <h1>📦 Build ZIP</h1>
                <p>Genera un archivio ZIP del plugin senza modificare la versione</p>
            </div>
            
            <div class="btr-dev-iframe-container">
                <iframe src="<?php echo esc_url($build_zip_url); ?>"></iframe>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render pagina test scripts
     */
    public function render_test_scripts() {
        $tests_dir = BTR_PLUGIN_DIR . '/tests';
        $test_files = glob($tests_dir . '/*.php');
        ?>
        <div class="wrap btr-dev-container">
            <div class="btr-dev-header">
                <h1>🧪 Test Scripts</h1>
                <p>Script di test e debug disponibili</p>
            </div>
            
            <div class="btr-dev-warning">
                <strong>⚠️ Attenzione:</strong> Esegui questi script solo se sai cosa stai facendo. 
                Alcuni possono modificare dati nel database.
            </div>
            
            <?php if ($test_files): ?>
                <ul class="btr-test-list">
                    <?php foreach ($test_files as $file): 
                        $filename = basename($file);
                        $file_url = BTR_PLUGIN_URL . 'tests/' . $filename;
                    ?>
                        <li>
                            <strong><?php echo esc_html($filename); ?></strong>
                            <a href="<?php echo esc_url($file_url); ?>" target="_blank" class="button button-small">
                                Esegui
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>Nessuno script di test trovato nella directory /tests/</p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render pagina documentazione
     */
    public function render_documentation() {
        $docs = [
            'CHANGELOG.md' => 'Changelog',
            'README.md' => 'README',
            'README-prezzi-bambini.md' => 'Prezzi Bambini',
            'COSTI_EXTRA_IMPLEMENTATION.md' => 'Implementazione Costi Extra',
            'FRONTEND_MODIFICATIONS.md' => 'Modifiche Frontend',
            'DOCUMENTAZIONE-MODIFICHE-2025-01-11.md' => 'Modifiche Recenti',
            'PUNTO-RIPRISTINO-2025-01-11.md' => 'Punto di Ripristino'
        ];
        ?>
        <div class="wrap btr-dev-container">
            <div class="btr-dev-header">
                <h1>📚 Documentation</h1>
                <p>File di documentazione disponibili</p>
            </div>
            
            <div class="btr-dev-grid">
                <?php foreach ($docs as $file => $title): 
                    $file_path = BTR_PLUGIN_DIR . '/' . $file;
                    if (file_exists($file_path)):
                ?>
                    <div class="btr-dev-card">
                        <h3><?php echo esc_html($title); ?></h3>
                        <p><code><?php echo esc_html($file); ?></code></p>
                        <a href="<?php echo esc_url(BTR_PLUGIN_URL . $file); ?>" target="_blank" class="button">
                            Visualizza
                        </a>
                    </div>
                <?php 
                    endif;
                endforeach; 
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render pagina archivio build
     */
    public function render_build_archive() {
        include BTR_PLUGIN_DIR . 'admin/build-archive.php';
    }
    
    /**
     * Gestisce la richiesta AJAX per build release
     */
    public function handle_build_release_ajax() {
        // Verifica permessi
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Accesso negato');
            return;
        }
        
        // Verifica nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'btr_build_release_nonce')) {
            wp_send_json_error('Nonce non valido');
            return;
        }
        
        // Gestisci la richiesta in base all'azione
        $ajax_action = isset($_POST['ajax_action']) ? $_POST['ajax_action'] : '';
        
        if ($ajax_action === 'btr_get_suggested_changes') {
            // Includi le funzioni necessarie
            require_once BTR_PLUGIN_DIR . 'admin/ajax-functions.php';
            
            // Chiama le funzioni per ottenere i suggerimenti
            $git_commits = btr_get_recent_git_commits();
            $doc_changes = btr_get_recent_changes_from_docs();
            
            // Combina e rimuovi duplicati
            $suggested_changes = array_unique(array_merge($doc_changes, $git_commits));
            
            wp_send_json_success([
                'changes' => array_slice($suggested_changes, 0, 15)
            ]);
        } else if ($ajax_action === 'btr_delete_build') {
            // Gestisci eliminazione build
            $filename = sanitize_file_name($_POST['filename']);
            $file_path = BTR_PLUGIN_DIR . 'build/' . $filename;
            
            if (file_exists($file_path) && strpos($filename, '.zip') !== false) {
                if (unlink($file_path)) {
                    wp_send_json_success('File eliminato con successo');
                } else {
                    wp_send_json_error('Impossibile eliminare il file');
                }
            } else {
                wp_send_json_error('File non trovato');
            }
        } else {
            // Per altre azioni, includi il file che gestisce la logica
            include BTR_PLUGIN_DIR . 'admin/build-release-ajax.php';
        }
    }
}

// Inizializza solo se siamo in admin
if (is_admin()) {
    new BTR_Developer_Menu();
}