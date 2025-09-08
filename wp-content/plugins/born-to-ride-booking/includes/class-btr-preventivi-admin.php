<?php
/**
 * Gestione amministrativa dei preventivi con funzionalità di pagamento
 * 
 * @since 1.0.0
 * @version 1.0.14
 */

if (!defined('ABSPATH')) {
    exit;
}

class BTR_Preventivi_Admin {
    
    public function __construct() {
        // Hook per la gestione admin dei preventivi
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'admin_init']);
        
        // Colonne personalizzate nella lista preventivi
        add_filter('manage_btr_preventivi_posts_columns', [$this, 'add_custom_columns']);
        add_action('manage_btr_preventivi_posts_custom_column', [$this, 'show_custom_columns'], 10, 2);
        add_filter('post_row_actions', [$this, 'add_row_actions'], 10, 2);
        
        // Metabox per preventivi
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post', [$this, 'save_preventivo_meta']);
        
        // Stili admin
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        
        // Hook per sostituire il template di edit
        add_action('add_meta_boxes', [$this, 'remove_default_editor'], 1);
        add_action('edit_form_after_title', [$this, 'replace_edit_form'], 10, 1);
        add_action('admin_footer', [$this, 'hide_default_metaboxes']);
    }

    /**
     * Aggiunge menu admin
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=btr_preventivi',
            'Gestione Preventivi',
            'Gestione Preventivi',
            'manage_options',
            'btr-preventivi-admin',
            [$this, 'admin_page']
        );
    }

    /**
     * Inizializzazione admin
     */
    public function admin_init() {
        // Registra settings se necessario
        register_setting('btr_preventivi_settings', 'btr_preventivi_options');
    }

    /**
     * Aggiunge colonne personalizzate
     */
    public function add_custom_columns($columns) {
        $new_columns = [];
        
        // Mantieni colonne esistenti fino a 'date'
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['btr_participants'] = 'Partecipanti';
                $new_columns['btr_total_price'] = 'Totale';
                $new_columns['btr_payments'] = 'Pagamenti';
            }
        }
        
        return $new_columns;
    }

    /**
     * Mostra contenuto colonne personalizzate
     */
    public function show_custom_columns($column, $post_id) {
        switch ($column) {
            case 'btr_participants':
                $anagrafici = get_post_meta($post_id, '_anagrafici_preventivo', true);
                $count = is_array($anagrafici) ? count($anagrafici) : 0;
                echo '<strong>' . $count . '</strong> persone';
                break;
                
            case 'btr_total_price':
                $prezzo_totale = get_post_meta($post_id, '_prezzo_totale', true);
                if ($prezzo_totale) {
                    echo '€' . number_format((float)$prezzo_totale, 2, ',', '.');
                } else {
                    echo '<span style="color: #999;">—</span>';
                }
                break;
                
            case 'btr_payments':
                $this->show_payments_column($post_id);
                break;
        }
    }

    /**
     * Mostra colonna pagamenti con statistiche
     */
    private function show_payments_column($post_id) {
        $anagrafici = get_post_meta($post_id, '_anagrafici_preventivo', true);
        
        if (empty($anagrafici) || count($anagrafici) <= 1) {
            echo '<span style="color: #999;">N/A</span>';
            return;
        }

        // Verifica se la classe BTR_Group_Payments esiste
        if (!class_exists('BTR_Group_Payments')) {
            echo '<span style="color: #999;">Sistema non attivo</span>';
            return;
        }

        $group_payments = new BTR_Group_Payments();
        $stats = $group_payments->get_payment_stats($post_id);
        
        if ($stats && $stats['total_payments'] > 0) {
            $completion_rate = round(($stats['paid_count'] / $stats['total_payments']) * 100);
            echo "<div style='font-size: 12px; line-height: 1.3;'>";
            echo "<strong>{$stats['paid_count']}/{$stats['total_payments']}</strong> pagati<br>";
            echo "€" . number_format($stats['paid_amount'], 0, ',', '.') . "/€" . number_format($stats['total_amount'], 0, ',', '.') . "<br>";
            echo "<div style='background: #f0f0f1; height: 4px; border-radius: 2px; margin: 2px 0;'>";
            echo "<div style='background: #00a32a; height: 4px; width: {$completion_rate}%; border-radius: 2px;'></div>";
            echo "</div>";
            echo "<small style='color: #666;'>{$completion_rate}% completato</small>";
            echo "</div>";
        } else {
            echo "<small style='color: #666;'>Nessun pagamento</small>";
        }
    }

    /**
     * Aggiunge azioni alle righe
     */
    public function add_row_actions($actions, $post) {
        if ($post->post_type === 'btr_preventivi') {
            $anagrafici = get_post_meta($post->ID, '_anagrafici_preventivo', true);
            
            // Link ai pagamenti di gruppo
            if (!empty($anagrafici) && count($anagrafici) > 1) {
                $url = admin_url('edit.php?post_type=btr_preventivi&page=btr-group-payments&preventivo_id=' . $post->ID);
                $actions['group_payments'] = '<a href="' . esc_url($url) . '" title="Gestisci pagamenti individuali">💳 Pagamenti</a>';
            }
            
            // Link visualizzazione preventivo
            $actions['view_preventivo'] = '<a href="' . get_permalink($post->ID) . '" target="_blank" title="Visualizza preventivo">👁️ Visualizza</a>';
        }
        
        return $actions;
    }

    /**
     * Aggiunge metabox
     */
    public function add_meta_boxes() {
        add_meta_box(
            'btr_preventivo_details',
            'Dettagli Preventivo',
            [$this, 'render_details_metabox'],
            'btr_preventivi',
            'normal',
            'high'
        );

        // Metabox pagamenti solo se ci sono più partecipanti
        add_meta_box(
            'btr_preventivo_payments',
            'Gestione Pagamenti',
            [$this, 'render_payments_metabox'],
            'btr_preventivi',
            'side',
            'high'
        );
    }

    /**
     * Renderizza metabox dettagli
     */
    public function render_details_metabox($post) {
        wp_nonce_field('btr_preventivo_meta', 'btr_preventivo_meta_nonce');
        
        $nome_pacchetto = get_post_meta($post->ID, '_nome_pacchetto', true);
        $prezzo_totale = get_post_meta($post->ID, '_prezzo_totale', true);
        $durata = get_post_meta($post->ID, '_durata', true);
        $date_ranges = get_post_meta($post->ID, '_date_ranges', true);
        $anagrafici = get_post_meta($post->ID, '_anagrafici_preventivo', true);
    
        ?>
        <table class="form-table">
            <tr>
                <th><label for="nome_pacchetto">Nome Pacchetto</label></th>
                <td>
                    <input type="text" id="nome_pacchetto" name="_nome_pacchetto" 
                           value="<?= esc_attr($nome_pacchetto) ?>" class="regular-text" />
                </td>
            </tr>
            <tr>
                <th><label for="prezzo_totale">Prezzo Totale</label></th>
                <td>
                    <input type="number" id="prezzo_totale" name="_prezzo_totale" 
                           value="<?= esc_attr($prezzo_totale) ?>" step="0.01" class="regular-text" />
                    <span class="description">€</span>
                </td>
            </tr>
            <tr>
                <th><label for="durata">Durata</label></th>
                <td>
                    <input type="text" id="durata" name="_durata" 
                           value="<?= esc_attr($durata) ?>" class="regular-text" />
                    <span class="description">Es: 3 giorni, 2 notti</span>
                </td>
            </tr>
            <tr>
                <th>Partecipanti</th>
                <td>
                    <?php if (!empty($anagrafici)): ?>
                        <strong><?= count($anagrafici) ?></strong> persone registrate
                        <details style="margin-top: 8px;">
                            <summary style="cursor: pointer;">Mostra elenco</summary>
                            <ul style="margin: 8px 0; padding-left: 20px;">
                                <?php foreach ($anagrafici as $i => $persona): ?>
                                <li><?= esc_html(($persona['nome'] ?? '') . ' ' . ($persona['cognome'] ?? '')) ?> 
                                    <?= !empty($persona['email']) ? '(' . esc_html($persona['email']) . ')' : '' ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                    <?php else: ?>
                        <span style="color: #666;">Nessun partecipante registrato</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Renderizza metabox pagamenti
     */
    public function render_payments_metabox($post) {
        // Include il metabox dei pagamenti
        if (file_exists(BTR_PLUGIN_DIR . 'admin/views/payments-metabox.php')) {
            // Simula la funzione che renderebbe il metabox
            $preventivo_id = $post->ID;
            $anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
            $prezzo_totale = (float) get_post_meta($preventivo_id, '_prezzo_totale', true);
            
            if (empty($anagrafici) || count($anagrafici) <= 1) {
                echo '<div class="notice notice-info inline">
                        <p><strong>Pagamenti individuali non disponibili</strong><br>
                        Questa funzionalità è disponibile solo per preventivi con più di un partecipante.</p>
                      </div>';
                return;
            }
            
            if (!class_exists('BTR_Group_Payments')) {
                echo '<div class="notice notice-warning inline">
                        <p><strong>Sistema pagamenti non attivo</strong><br>
                        Contatta l\'amministratore del sistema.</p>
                      </div>';
                return;
            }
            
            $group_payments = new BTR_Group_Payments();
            $payment_stats = $group_payments->get_payment_stats($preventivo_id);
            $quota_individuale = $prezzo_totale / count($anagrafici);
            
            // Rendering semplificato del metabox
            echo '<div class="btr-payments-metabox">';
            echo '<div class="payment-stats-simple">';
            echo '<p><strong>' . count($anagrafici) . '</strong> partecipanti</p>';
            echo '<p><strong>€' . number_format($quota_individuale, 2, ',', '.') . '</strong> a persona</p>';
            
            if ($payment_stats) {
                echo '<p><strong>' . $payment_stats['paid_count'] . '/' . $payment_stats['total_payments'] . '</strong> pagati</p>';
            }
            
            echo '</div>';
            
            echo '<div style="margin-top: 15px;">';
            echo '<a href="' . admin_url('edit.php?post_type=btr_preventivi&page=btr-group-payments&preventivo_id=' . $preventivo_id) . '" class="button button-primary">Gestisci Pagamenti</a>';
            echo '</div>';
            
            echo '</div>';
        }
    }

    /**
     * Salva metadati preventivo
     */
    public function save_preventivo_meta($post_id) {
        // Verifica nonce
        if (!isset($_POST['btr_preventivo_meta_nonce']) || 
            !wp_verify_nonce($_POST['btr_preventivo_meta_nonce'], 'btr_preventivo_meta')) {
            return;
        }

        // Verifica permessi
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Evita autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Salva solo per preventivi
        if (get_post_type($post_id) !== 'btr_preventivi') {
            return;
        }

        // Salva metadati
        $fields = ['_nome_pacchetto', '_prezzo_totale', '_durata'];
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
    }

    /**
     * Carica script admin
     */
    public function enqueue_admin_scripts($hook) {
        global $post_type;
        
        if ($post_type === 'btr_preventivi') {
            wp_enqueue_style('btr-admin-preventivi', 
                BTR_PLUGIN_URL . 'assets/css/admin-preventivi.css', 
                [], BTR_VERSION);
            
            // Aggiungi il nuovo CSS per il dettaglio preventivo
            if ($hook === 'post.php' && isset($_GET['action']) && $_GET['action'] === 'edit') {
                wp_enqueue_style('btr-admin-preventivo-detail', 
                    BTR_PLUGIN_URL . 'assets/css/admin-preventivo-detail.css', 
                    [], BTR_VERSION);
            }
                
            wp_enqueue_script('btr-admin-preventivi',
                BTR_PLUGIN_URL . 'assets/js/admin-preventivi.js',
                ['jquery'], BTR_VERSION, true);
                
            wp_localize_script('btr-admin-preventivi', 'btrAdmin', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('btr_admin_nonce')
            ]);
        }
    }

    /**
     * Pagina admin principale
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Gestione Preventivi</h1>
            <div class="btr-admin-dashboard">
                <div class="btr-stats-grid">
                    <?php $this->render_stats_dashboard(); ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Renderizza dashboard statistiche
     */
    private function render_stats_dashboard() {
        global $wpdb;
        
        // Statistiche preventivi
        $total_preventivi = wp_count_posts('btr_preventivi')->publish;
        
        // Statistiche pagamenti se disponibili
        if (class_exists('BTR_Group_Payments')) {
            $table_payments = $wpdb->prefix . 'btr_group_payments';
            $payments_stats = $wpdb->get_row("
                SELECT 
                    COUNT(*) as total_payments,
                    SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                    SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as total_paid
                FROM {$table_payments}
            ");
        }
        
        ?>
        <div class="btr-stat-card">
            <h3>Preventivi Totali</h3>
            <div class="stat-number"><?= $total_preventivi ?></div>
        </div>
        
        <?php if (isset($payments_stats)): ?>
        <div class="btr-stat-card">
            <h3>Pagamenti Completati</h3>
            <div class="stat-number"><?= $payments_stats->paid_count ?? 0 ?></div>
        </div>
        
        <div class="btr-stat-card">
            <h3>Totale Incassato</h3>
            <div class="stat-number">€<?= number_format($payments_stats->total_paid ?? 0, 0, ',', '.') ?></div>
        </div>
        <?php endif; ?>
        
        <style>
        .btr-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .btr-stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .btr-stat-card h3 {
            margin: 0 0 10px;
            color: #666;
            font-size: 14px;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #0073aa;
        }
        </style>
        <?php
    }
    
    /**
     * Sostituisce il form di edit con il template custom
     */
    public function replace_edit_form($post) {
        if ($post->post_type !== 'btr_preventivi') {
            return;
        }
        
        // Rimuovi il contenuto predefinito
        remove_post_type_support('btr_preventivi', 'editor');
        
        // Carica il template custom
        if (file_exists(BTR_PLUGIN_DIR . 'templates/admin/preventivo-detail.php')) {
            ?>
            <style>
                /* Nascondi elementi standard di WordPress */
                #titlediv,
                #post-body #normal-sortables,
                #post-body #advanced-sortables,
                .postbox-container {
                    display: none !important;
                }
                
                /* Resetta il layout per il nostro template */
                #post-body-content {
                    margin-right: 0 !important;
                }
                
                #post-body.columns-2 {
                    margin-right: 0 !important;
                }
                
                /* Assicura che il nostro contenuto sia visibile */
                .btr-preventivo-detail-wrapper {
                    display: block !important;
                    visibility: visible !important;
                }
            </style>
            <?php
            
            // Includi il template
            include BTR_PLUGIN_DIR . 'templates/admin/preventivo-detail.php';
        }
    }
    
    /**
     * Rimuove l'editor predefinito
     */
    public function remove_default_editor() {
        global $post_type;
        if ($post_type === 'btr_preventivi') {
            remove_post_type_support('btr_preventivi', 'editor');
            remove_post_type_support('btr_preventivi', 'title');
        }
    }
    
    /**
     * Nascondi i metabox predefiniti
     */
    public function hide_default_metaboxes() {
        global $post_type;
        if ($post_type === 'btr_preventivi') {
            ?>
            <script>
                jQuery(document).ready(function($) {
                    // Nascondi tutti i metabox standard
                    $('#normal-sortables, #side-sortables, #advanced-sortables').hide();
                    // Nascondi il titolo
                    $('#titlediv').hide();
                    // Nascondi il pulsante di pubblicazione standard
                    $('#publishing-action').hide();
                    // Nascondi la barra laterale
                    $('#postbox-container-1').hide();
                });
            </script>
            <?php
        }
    }
}