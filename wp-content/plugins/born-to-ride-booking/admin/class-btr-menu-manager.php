<?php
/**
 * Gestore del Menu Admin per Born to Ride Booking
 * 
 * Organizza le voci di menu nell'ordine richiesto:
 * 1. Dashboard
 * 2. Pacchetti
 * 3. Preventivi
 * 4. Prenotazioni
 * 5. Changelog
 * 
 * @package Born_To_Ride_Booking
 * @since 1.0.75
 */

if (!defined('ABSPATH')) {
    exit; // Accesso diretto non consentito
}

class BTR_Menu_Manager {
    
    /**
     * Costruttore della classe
     */
    public function __construct() {
        // Registra i menu con priorità specifiche per l'ordinamento
        add_action('admin_menu', [$this, 'register_main_menu'], 5);
        add_action('admin_menu', [$this, 'register_submenu_pages'], 10);
        add_action('admin_menu', [$this, 'reorder_menu_items'], 99);
        
        // Carica gli asset per le pagine del plugin
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }
    
    /**
     * Registra il menu principale
     */
    public function register_main_menu() {
        add_menu_page(
            __('BTR Booking', 'born-to-ride-booking'),
            __('BTR Booking', 'born-to-ride-booking'),
            'manage_options',
            'btr-booking',
            [$this, 'render_dashboard'],
            'dashicons-tickets-alt',
            30
        );
    }
    
    /**
     * Registra le pagine del sottomenu nell'ordine desiderato
     */
    public function register_submenu_pages() {
        // 1. Dashboard (rimanda alla pagina principale)
        add_submenu_page(
            'btr-booking',
            __('Dashboard', 'born-to-ride-booking'),
            __('Dashboard', 'born-to-ride-booking'),
            'manage_options',
            'btr-booking',
            [$this, 'render_dashboard']
        );
        
        // 2. Pacchetti (collegamento al CPT)
        add_submenu_page(
            'btr-booking',
            __('Pacchetti', 'born-to-ride-booking'),
            __('Pacchetti', 'born-to-ride-booking'),
            'manage_options',
            'edit.php?post_type=btr_pacchetti'
        );
        
        // 3. Preventivi (collegamento al CPT)
        add_submenu_page(
            'btr-booking',
            __('Preventivi', 'born-to-ride-booking'),
            __('Preventivi', 'born-to-ride-booking'),
            'manage_options',
            'edit.php?post_type=btr_preventivi'
        );
        
        // 4. Prenotazioni (pagina custom se esiste)
        add_submenu_page(
            'btr-booking',
            __('Prenotazioni', 'born-to-ride-booking'),
            __('Prenotazioni', 'born-to-ride-booking'),
            'manage_options',
            'btr-prenotazioni',
            [$this, 'render_prenotazioni']
        );
        
        // 5. Changelog (nuova pagina)
        add_submenu_page(
            'btr-booking',
            __('Changelog', 'born-to-ride-booking'),
            __('Changelog', 'born-to-ride-booking'),
            'manage_options',
            'btr-changelog',
            [$this, 'render_changelog']
        );
        
        // 6. Database Migration (solo per admin)
        if (current_user_can('manage_options')) {
            add_submenu_page(
                'btr-booking',
                __('Database Migration', 'born-to-ride-booking'),
                __('Database Migration', 'born-to-ride-booking'),
                'manage_options',
                'btr-database-migration',
                [$this, 'render_database_migration']
            );
        }
    }
    
    /**
     * Riordina le voci di menu per assicurare l'ordine corretto
     */
    public function reorder_menu_items() {
        global $submenu;
        
        if (isset($submenu['btr-booking'])) {
            // Salva le voci originali
            $original_items = $submenu['btr-booking'];
            
            // Riordina manualmente
            $submenu['btr-booking'] = [];
            
            // Array per voci non mappate
            $unmapped_items = [];
            $next_position = 50; // Posizione iniziale per voci non mappate
            
            // Aggiungi nell'ordine desiderato
            foreach ($original_items as $key => $item) {
                if (strpos($item[2], 'btr-booking') !== false && $item[0] === 'Dashboard') {
                    // Dashboard
                    $submenu['btr-booking'][0] = $item;
                } elseif (strpos($item[2], 'btr_pacchetti') !== false) {
                    // Pacchetti
                    $submenu['btr-booking'][10] = $item;
                } elseif (strpos($item[2], 'btr_preventivi') !== false) {
                    // Preventivi
                    $submenu['btr-booking'][20] = $item;
                } elseif (strpos($item[2], 'btr-prenotazioni') !== false) {
                    // Prenotazioni
                    $submenu['btr-booking'][30] = $item;
                } elseif (strpos($item[2], 'btr-changelog') !== false) {
                    // Changelog
                    $submenu['btr-booking'][40] = $item;
                } elseif (strpos($item[2], 'btr-payment-plans') !== false) {
                    // Piani di Pagamento
                    $submenu['btr-booking'][45] = $item;
                } elseif (strpos($item[2], 'btr-payment-plans-settings') !== false) {
                    // Impostazioni Pagamenti
                    $submenu['btr-booking'][46] = $item;
                } elseif (strpos($item[2], 'btr-gateway-settings') !== false) {
                    // Gateway Pagamenti
                    $submenu['btr-booking'][47] = $item;
                } elseif (strpos($item[2], 'btr-database-migration') !== false) {
                    // Database Migration
                    $submenu['btr-booking'][48] = $item;
                } else {
                    // Mantieni tutte le altre voci
                    $unmapped_items[$next_position] = $item;
                    $next_position++;
                }
            }
            
            // Aggiungi le voci non mappate alla fine
            foreach ($unmapped_items as $position => $item) {
                $submenu['btr-booking'][$position] = $item;
            }
            
            // Ordina le chiavi numeriche
            ksort($submenu['btr-booking']);
        }
    }
    
    /**
     * Renderizza la dashboard (delega alla classe BTR_Dashboard)
     */
    public function render_dashboard() {
        // Se esiste la classe BTR_Dashboard, usa quella
        if (class_exists('BTR_Dashboard')) {
            $dashboard = new BTR_Dashboard();
            $dashboard->render_dashboard();
            return;
        }
        
        // Fallback semplice
        ?>
        <div class="wrap">
            <h1><?php _e('BTR Booking - Dashboard', 'born-to-ride-booking'); ?></h1>
            <div class="notice notice-info">
                <p><?php _e('Dashboard principale di Born to Ride Booking. Da qui puoi gestire pacchetti, preventivi e prenotazioni.', 'born-to-ride-booking'); ?></p>
            </div>
            
            <div class="btr-quick-actions" style="margin: 20px 0;">
                <h2><?php _e('Azioni Rapide', 'born-to-ride-booking'); ?></h2>
                <p>
                    <a href="<?php echo admin_url('post-new.php?post_type=btr_pacchetti'); ?>" class="button button-primary">
                        <?php _e('Nuovo Pacchetto', 'born-to-ride-booking'); ?>
                    </a>
                    <a href="<?php echo admin_url('edit.php?post_type=btr_pacchetti'); ?>" class="button">
                        <?php _e('Gestisci Pacchetti', 'born-to-ride-booking'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=btr-prenotazioni'); ?>" class="button">
                        <?php _e('Vedi Prenotazioni', 'born-to-ride-booking'); ?>
                    </a>
                </p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Renderizza la pagina delle prenotazioni
     */
    public function render_prenotazioni() {
        // Se esiste una classe specifica per le prenotazioni, usa quella
        if (class_exists('BTR_Prenotazioni_Manager')) {
            // Delega alla classe esistente
            ?>
            <div class="wrap">
                <h1><?php _e('Prenotazioni', 'born-to-ride-booking'); ?></h1>
                <div class="notice notice-info">
                    <p><?php _e('Gestione delle prenotazioni Born to Ride Booking.', 'born-to-ride-booking'); ?></p>
                </div>
                <!-- Qui si potrebbe integrare la visualizzazione esistente -->
            </div>
            <?php
            return;
        }
        
        // Fallback semplice
        ?>
        <div class="wrap">
            <h1><?php _e('Prenotazioni', 'born-to-ride-booking'); ?></h1>
            <div class="notice notice-warning">
                <p><?php _e('La gestione delle prenotazioni è in fase di implementazione.', 'born-to-ride-booking'); ?></p>
            </div>
            
            <p>
                <a href="<?php echo admin_url('admin.php?page=wc-orders'); ?>" class="button button-primary">
                    <?php _e('Vedi Ordini WooCommerce', 'born-to-ride-booking'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Renderizza la pagina Database Migration
     */
    public function render_database_migration() {
        // Include la vista della pagina di migration
        require_once BTR_PLUGIN_DIR . 'admin/views/database-migration-page.php';
    }
    
    /**
     * Renderizza la pagina del changelog
     */
    public function render_changelog() {
        // Percorso del file changelog
        $changelog_file = BTR_PLUGIN_DIR . 'CHANGELOG.md';
        
        ?>
        <div class="wrap btr-dashboard-wrap">
            
            <div class="btr-changelog-header">
                <h1><?php _e('Changelog - Born to Ride Booking', 'born-to-ride-booking'); ?></h1>
                <p><strong><?php _e('Versione Corrente:', 'born-to-ride-booking'); ?></strong> <span class="btr-version-tag">v<?php echo BTR_VERSION; ?></span></p>
                <p><?php _e('Tutte le modifiche significative al plugin sono documentate qui sotto.', 'born-to-ride-booking'); ?></p>
            </div>
            
            <!-- Filtri opzionali per futuro sviluppo -->
            <div class="btr-changelog-filters" style="display: none;">
                <label for="version-filter"><?php _e('Filtra per versione:', 'born-to-ride-booking'); ?></label>
                <select id="version-filter">
                    <option value=""><?php _e('Tutte le versioni', 'born-to-ride-booking'); ?></option>
                </select>
                
                <label for="type-filter"><?php _e('Tipo modifica:', 'born-to-ride-booking'); ?></label>
                <select id="type-filter">
                    <option value=""><?php _e('Tutti i tipi', 'born-to-ride-booking'); ?></option>
                    <option value="nuovo"><?php _e('Nuove funzionalità', 'born-to-ride-booking'); ?></option>
                    <option value="fix"><?php _e('Correzioni', 'born-to-ride-booking'); ?></option>
                    <option value="miglioramento"><?php _e('Miglioramenti', 'born-to-ride-booking'); ?></option>
                </select>
            </div>
            
            <div class="btr-changelog-content">
                <?php if (file_exists($changelog_file)) : ?>
                    <?php echo $this->parse_changelog_content($changelog_file); ?>
                <?php else : ?>
                    <div class="notice notice-error">
                        <p><?php _e('File changelog non trovato.', 'born-to-ride-booking'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="btr-quick-actions">
                <h2><?php _e('Azioni Rapide', 'born-to-ride-booking'); ?></h2>
                <a href="<?php echo admin_url('admin.php?page=btr-booking'); ?>" class="button">
                    <span class="dashicons dashicons-dashboard"></span>
                    <?php _e('Torna alla Dashboard', 'born-to-ride-booking'); ?>
                </a>
                <a href="<?php echo admin_url('edit.php?post_type=btr_pacchetti'); ?>" class="button">
                    <span class="dashicons dashicons-tickets-alt"></span>
                    <?php _e('Gestisci Pacchetti', 'born-to-ride-booking'); ?>
                </a>
                <a href="<?php echo admin_url('edit.php?post_type=btr_preventivi'); ?>" class="button">
                    <span class="dashicons dashicons-money-alt"></span>
                    <?php _e('Vedi Preventivi', 'born-to-ride-booking'); ?>
                </a>
            </div>
        </div>
        <?php
    }
    
    /**
     * Parsifica e formatta il contenuto del changelog da Markdown a HTML
     */
    private function parse_changelog_content($file_path) {
        $content = file_get_contents($file_path);
        
        if (!$content) {
            return '<p>' . __('Impossibile leggere il file changelog.', 'born-to-ride-booking') . '</p>';
        }
        
        // Parsing avanzato da Markdown a HTML
        $html = $content;
        
        // Headers con badge versione e data separati (formato italiano dd/mm/YYYY)
        $html = preg_replace_callback('/^## \[([\d\.]+)\] - (\d{4})-(\d{2})-(\d{2})$/m', function($matches) {
            $version = $matches[1];
            $year = $matches[2];
            $month = $matches[3];
            $day = $matches[4];
            
            // Formato italiano dd/mm/YYYY
            $data_italiana = intval($day) . '/' . intval($month) . '/' . $year;
            
            return '<h2><span class="btr-version-tag">v' . $version . '</span><span class="btr-date-tag">' . $data_italiana . '</span></h2>';
        }, $html);
        
        // Sezioni funzionalità con emoji per badge
        $html = preg_replace('/^### 📋 (.+)$/m', '<h3><span class="btr-badge nuovo">NUOVO</span>$1</h3>', $html);
        $html = preg_replace('/^### 🔧 (.+)$/m', '<h3><span class="btr-badge fix">FIX</span>$1</h3>', $html);
        $html = preg_replace('/^### 🎯 (.+)$/m', '<h3><span class="btr-badge fix">FIX</span>$1</h3>', $html);
        $html = preg_replace('/^### 🛠️ (.+)$/m', '<h3><span class="btr-badge tecnico">TECNICO</span>$1</h3>', $html);
        $html = preg_replace('/^### ⚡ (.+)$/m', '<h3><span class="btr-badge miglioramento">MIGLIORAMENTO</span>$1</h3>', $html);
        $html = preg_replace('/^### 🔍 (.+)$/m', '<h3><span class="btr-badge tecnico">ANALISI</span>$1</h3>', $html);
        $html = preg_replace('/^### 📊 (.+)$/m', '<h3><span class="btr-badge tecnico">TEST</span>$1</h3>', $html);
        
        // Altri header senza emoji
        $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^#### (.+)$/m', '<h4>$1</h4>', $html);
        
        // Evidenziazione parole chiave con badge
        $html = preg_replace('/\*\*NUOVO:\s*(.+?)\*\*/', '<span class="btr-badge nuovo">NUOVO</span><strong>$1</strong>', $html);
        $html = preg_replace('/\*\*RISOLTO:\s*(.+?)\*\*/', '<span class="btr-badge fix">RISOLTO</span><strong>$1</strong>', $html);
        $html = preg_replace('/\*\*FIX:\s*(.+?)\*\*/', '<span class="btr-badge fix">FIX</span><strong>$1</strong>', $html);
        $html = preg_replace('/\*\*MIGLIORATO:\s*(.+?)\*\*/', '<span class="btr-badge miglioramento">MIGLIORATO</span><strong>$1</strong>', $html);
        
        // Bold and code standard
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/`(.+?)`/', '<code>$1</code>', $html);
        
        // Links con target blank per esterni
        $html = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $html);
        
        // Liste con parsing migliorato
        $html = preg_replace('/^- (.+)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/^  - (.+)$/m', '<li style="margin-left: 20px;">$1</li>', $html);
        
        // Wrap consecutive li elements in ul
        $html = preg_replace('/(<li>.*?<\/li>(?:\s*<li.*?>.*?<\/li>)*)/s', '<ul>$1</ul>', $html);
        
        // Paragrafi
        $html = preg_replace('/\n\n/', '</p><p>', $html);
        $html = '<p>' . $html . '</p>';
        
        // Pulizia paragrafi vuoti
        $html = preg_replace('/<p>\s*<\/p>/', '', $html);
        $html = preg_replace('/<p><\/p>/', '', $html);
        
        // Fix formatting intorno ai headers
        $html = preg_replace('/<\/p>\s*(<h[1-6]>)/', '$1', $html);
        $html = preg_replace('/(<\/h[1-6]>)\s*<p>/', '$1', $html);
        
        // Fix formatting intorno alle liste
        $html = preg_replace('/<\/p>\s*(<ul>)/', '$1', $html);
        $html = preg_replace('/(<\/ul>)\s*<p>/', '$1', $html);
        
        // Aggiungi wrapper per contenuto versioni
        $html = preg_replace('/(<h2>.*?<\/h2>)(.*?)(?=<h2>|$)/s', '$1<div class="btr-version-content">$2</div>', $html);
        
        // Pulizia finale
        $html = str_replace('<p><h', '<h', $html);
        $html = str_replace('</h1></p>', '</h1>', $html);
        $html = str_replace('</h2></p>', '</h2>', $html);
        $html = str_replace('</h3></p>', '</h3>', $html);
        $html = str_replace('</h4></p>', '</h4>', $html);
        
        return $html;
    }
    
    /**
     * Carica gli asset per le pagine admin
     */
    public function enqueue_admin_assets($hook) {
        // Carica solo nelle pagine del plugin BTR
        if (strpos($hook, 'btr-booking') !== false || strpos($hook, 'btr-') !== false) {
            // CSS generale per le pagine BTR
            wp_enqueue_style(
                'btr-admin-general',
                BTR_PLUGIN_URL . 'admin/css/btr-admin.css',
                [],
                BTR_VERSION
            );
            
            // Script generale
            wp_enqueue_script(
                'btr-admin-general',
                BTR_PLUGIN_URL . 'admin/js/btr-admin.js',
                ['jquery'],
                BTR_VERSION,
                true
            );
        }
    }
}