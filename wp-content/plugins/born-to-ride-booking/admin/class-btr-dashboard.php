<?php
/**
 * Dashboard principale per Born to Ride Booking
 * 
 * @package Born_To_Ride_Booking
 * @since 1.0.5
 */

if (!defined('ABSPATH')) {
    exit; // Accesso diretto non consentito
}


/**
 * Classe che gestisce la dashboard principale del plugin
 */
class BTR_Dashboard {

    /**
     * Costruttore della classe
     */
    public function __construct() {
        // La registrazione del menu è ora gestita da BTR_Menu_Manager
        // add_action('admin_menu', [$this, 'register_dashboard_page'], 5);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_dashboard_assets']);
    }

    /**
     * Registra la pagina della dashboard
     */
    public function register_dashboard_page() {
        // Menu principale
        add_menu_page(
            __('BTR Booking', 'born-to-ride-booking'),
            __('BTR Booking', 'born-to-ride-booking'),
            'manage_options',
            'btr-booking',
            [$this, 'render_dashboard'],
            'dashicons-tickets-alt',
            30
        );

        // Sottomenu Dashboard
        add_submenu_page(
            'btr-booking',
            __('Dashboard', 'born-to-ride-booking'),
            __('Dashboard', 'born-to-ride-booking'),
            'manage_options',
            'btr-booking',
            [$this, 'render_dashboard']
        );
    }

    /**
     * Carica gli stili e gli script per la dashboard
     */
    public function enqueue_dashboard_assets($hook) {
        if (strpos($hook, 'btr-booking') !== false) {
            // CSS principale
            wp_enqueue_style(
                'btr-dashboard-style',
                BTR_PLUGIN_URL . 'admin/css/btr-dashboard.css',
                [], 
                BTR_VERSION
            );

            // Script principali
            wp_enqueue_script(
                'btr-dashboard-script',
                BTR_PLUGIN_URL . 'admin/js/btr-dashboard.js',
                ['jquery', 'wp-api', 'jquery-ui-datepicker'],
                BTR_VERSION,
                true
            );
            
            // Variabili JS
            wp_localize_script('btr-dashboard-script', 'btrDashVars', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('btr_dashboard_nonce'),
                'labels' => [
                    'today' => __('Oggi', 'born-to-ride-booking'),
                    'yesterday' => __('Ieri', 'born-to-ride-booking'),
                    'last7days' => __('Ultimi 7 giorni', 'born-to-ride-booking'),
                    'last30days' => __('Ultimi 30 giorni', 'born-to-ride-booking'),
                ]
            ]);
        }
    }

    /**
     * Renderizza la dashboard
     */
    public function render_dashboard() {
        // Recupera i dati per la dashboard
        $stats = $this->get_dashboard_stats();
        $recent_bookings = $this->get_recent_bookings();
        $recent_quotes = $this->get_recent_quotes();
        
        // Inizia l'output
        ?>
        <div class="wrap btr-dashboard-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <div class="btr-dashboard-header">
                <div class="btr-welcome-panel">
                    <h2><?php _e('Benvenuto in Born to Ride Booking', 'born-to-ride-booking'); ?></h2>
                    <p class="about-description">
                        <?php _e('Gestisci prenotazioni, pacchetti e preventivi in un\'unica interfaccia semplice e intuitiva.', 'born-to-ride-booking'); ?>
                    </p>
                    
                    <div class="btr-quick-actions">
                        <a href="<?php echo admin_url('post-new.php?post_type=btr_pacchetti'); ?>" class="button button-primary">
                            <span class="dashicons dashicons-plus"></span> 
                            <?php _e('Nuovo Pacchetto', 'born-to-ride-booking'); ?>
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=btr-prenotazioni'); ?>" class="button">
                            <span class="dashicons dashicons-calendar-alt"></span> 
                            <?php _e('Gestisci Prenotazioni', 'born-to-ride-booking'); ?>
                        </a>
                        <a href="<?php echo admin_url('edit.php?post_type=btr_preventivi'); ?>" class="button">
                            <span class="dashicons dashicons-money-alt"></span> 
                            <?php _e('Vedi Preventivi', 'born-to-ride-booking'); ?>
                        </a>
                    </div>
                </div>
            </div>

            <div class="btr-dashboard-stats">
                <div class="btr-stat-card" data-stat="pacchetti">
                    <div class="btr-stat-icon">
                        <span class="dashicons dashicons-tickets-alt"></span>
                    </div>
                    <div class="btr-stat-content">
                        <h3><?php _e('Pacchetti', 'born-to-ride-booking'); ?></h3>
                        <div class="btr-stat-value"><?php echo $stats['pacchetti']; ?></div>
                        <div class="btr-stat-description">
                            <?php _e('Pacchetti attivi', 'born-to-ride-booking'); ?>
                        </div>
                    </div>
                </div>

                <div class="btr-stat-card" data-stat="prenotazioni">
                    <div class="btr-stat-icon">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <div class="btr-stat-content">
                        <h3><?php _e('Prenotazioni', 'born-to-ride-booking'); ?></h3>
                        <div class="btr-stat-value"><?php echo $stats['prenotazioni']; ?></div>
                        <div class="btr-stat-description">
                            <?php _e('Totali', 'born-to-ride-booking'); ?>
                        </div>
                    </div>
                </div>

                <div class="btr-stat-card" data-stat="preventivi">
                    <div class="btr-stat-icon">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                    <div class="btr-stat-content">
                        <h3><?php _e('Preventivi', 'born-to-ride-booking'); ?></h3>
                        <div class="btr-stat-value"><?php echo $stats['preventivi']; ?></div>
                        <div class="btr-stat-description">
                            <?php _e('Preventivi attivi', 'born-to-ride-booking'); ?>
                        </div>
                    </div>
                </div>



                <div class="btr-stat-card" data-stat="valore">
                    <div class="btr-stat-icon">
                        <span class="dashicons dashicons-chart-bar"></span>
                    </div>
                    <div class="btr-stat-content">
                        <h3><?php _e('Valore', 'born-to-ride-booking'); ?></h3>
                        <div class="btr-stat-value">€<?php echo number_format($stats['valore'], 2, ',', '.'); ?></div>
                        <div class="btr-stat-description">
                            <?php _e('Valore totale prenotazioni', 'born-to-ride-booking'); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="btr-dashboard-main">
                <div class="btr-dashboard-tabs">
                    <ul class="btr-tabs-nav">
                        <li class="active"><a href="#btr-tab-prenotazioni"><?php _e('Prenotazioni Recenti', 'born-to-ride-booking'); ?></a></li>
                        <li><a href="#btr-tab-preventivi"><?php _e('Preventivi Recenti', 'born-to-ride-booking'); ?></a></li>
                    </ul>
                    
                    <div class="btr-tabs-content">
                        <div id="btr-tab-prenotazioni" class="btr-tab-pane active">
                            <?php if (empty($recent_bookings)) : ?>
                                <div class="btr-no-items">
                                    <p><?php _e('Nessuna prenotazione recente trovata.', 'born-to-ride-booking'); ?></p>
                                </div>
                            <?php else : ?>
                                <table class="btr-recent-items-table">
                                    <thead>
                                        <tr>
                                            <th><?php _e('ID', 'born-to-ride-booking'); ?></th>
                                            <th><?php _e('Data', 'born-to-ride-booking'); ?></th>
                                            <th><?php _e('Cliente', 'born-to-ride-booking'); ?></th>
                                            <th><?php _e('Pacchetto', 'born-to-ride-booking'); ?></th>
                                            <th><?php _e('Stato', 'born-to-ride-booking'); ?></th>
                                            <th><?php _e('Valore', 'born-to-ride-booking'); ?></th>
                                            <th><?php _e('Azioni', 'born-to-ride-booking'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_bookings as $booking) : ?>
                                            <tr>
                                                <td>#<?php echo esc_html($booking->id); ?></td>
                                                <td><?php echo esc_html($booking->date); ?></td>
                                                <td><?php echo esc_html($booking->customer); ?></td>
                                                <td><?php echo esc_html($booking->package); ?></td>
                                                <td>
                                                    <span class="btr-status btr-status-<?php echo sanitize_html_class($booking->status); ?>">
                                                        <?php echo esc_html($booking->status_label); ?>
                                                    </span>
                                                </td>
                                                <td>€<?php echo number_format($booking->value, 2, ',', '.'); ?></td>
                                                <td>
                                                    <a href="<?php echo esc_url($booking->view_url); ?>" class="button button-small">
                                                        <?php _e('Dettagli', 'born-to-ride-booking'); ?>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <div class="btr-view-all">
                                    <a href="<?php echo admin_url('admin.php?page=btr-prenotazioni'); ?>" class="button">
                                        <?php _e('Vedi tutte le prenotazioni', 'born-to-ride-booking'); ?> 
                                        <span class="dashicons dashicons-arrow-right-alt"></span>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div id="btr-tab-preventivi" class="btr-tab-pane">
                            <?php if (empty($recent_quotes)) : ?>
                                <div class="btr-no-items">
                                    <p><?php _e('Nessun preventivo recente trovato.', 'born-to-ride-booking'); ?></p>
                                </div>
                            <?php else : ?>
                                <table class="btr-recent-items-table">
                                    <thead>
                                        <tr>
                                            <th><?php _e('ID', 'born-to-ride-booking'); ?></th>
                                            <th><?php _e('Data', 'born-to-ride-booking'); ?></th>
                                            <th><?php _e('Cliente', 'born-to-ride-booking'); ?></th>
                                            <th><?php _e('Pacchetto', 'born-to-ride-booking'); ?></th>
                                            <th><?php _e('Stato', 'born-to-ride-booking'); ?></th>
                                            <th><?php _e('Valore', 'born-to-ride-booking'); ?></th>
                                            <th><?php _e('Azioni', 'born-to-ride-booking'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_quotes as $quote) : ?>
                                            <tr>
                                                <td>#<?php echo esc_html($quote->id); ?></td>
                                                <td><?php echo esc_html($quote->date); ?></td>
                                                <td><?php echo esc_html($quote->customer); ?></td>
                                                <td><?php echo esc_html($quote->package); ?></td>
                                                <td>
                                                    <span class="btr-status btr-status-<?php echo sanitize_html_class($quote->status); ?>">
                                                        <?php echo esc_html($quote->status_label); ?>
                                                    </span>
                                                </td>
                                                <td>€<?php echo number_format($quote->value, 2, ',', '.'); ?></td>
                                                <td>
                                                    <a href="<?php echo esc_url($quote->view_url); ?>" class="button button-small">
                                                        <?php _e('Dettagli', 'born-to-ride-booking'); ?>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <div class="btr-view-all">
                                    <a href="<?php echo admin_url('edit.php?post_type=btr_preventivi'); ?>" class="button">
                                        <?php _e('Vedi tutti i preventivi', 'born-to-ride-booking'); ?> 
                                        <span class="dashicons dashicons-arrow-right-alt"></span>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="btr-dashboard-sidebar">
                    <div class="btr-sidebar-widget">
                        <h3><?php _e('Azioni Rapide', 'born-to-ride-booking'); ?></h3>
                        <ul class="btr-quick-links">
                            <li>
                                <a href="<?php echo admin_url('post-new.php?post_type=btr_pacchetti'); ?>">
                                    <span class="dashicons dashicons-plus"></span>
                                    <?php _e('Crea Nuovo Pacchetto', 'born-to-ride-booking'); ?>
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo admin_url('edit.php?post_type=btr_pacchetti'); ?>">
                                    <span class="dashicons dashicons-list-view"></span>
                                    <?php _e('Gestisci Pacchetti', 'born-to-ride-booking'); ?>
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo admin_url('admin.php?page=btr-prenotazioni'); ?>">
                                    <span class="dashicons dashicons-calendar-alt"></span>
                                    <?php _e('Visualizza Prenotazioni', 'born-to-ride-booking'); ?>
                                </a>
                            </li>
                            <li>
                                <a href="<?php echo admin_url('edit.php?post_type=btr_preventivi'); ?>">
                                    <span class="dashicons dashicons-money-alt"></span>
                                    <?php _e('Gestisci Preventivi', 'born-to-ride-booking'); ?>
                                </a>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="btr-sidebar-widget">
                        <h3><?php _e('Documentazione', 'born-to-ride-booking'); ?></h3>
                        <p>
                            <?php _e('Consulta la documentazione per imparare ad utilizzare tutte le funzionalità di Born to Ride Booking.', 'born-to-ride-booking'); ?>
                        </p>
                        <a href="#" class="button">
                            <span class="dashicons dashicons-book"></span>
                            <?php _e('Leggi la Documentazione', 'born-to-ride-booking'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Gestione dei tab
            $('.btr-tabs-nav a').on('click', function(e) {
                e.preventDefault();
                var target = $(this).attr('href');
                
                // Attiva il tab selezionato
                $('.btr-tabs-nav li').removeClass('active');
                $(this).parent().addClass('active');
                
                // Mostra il contenuto del tab
                $('.btr-tab-pane').removeClass('active');
                $(target).addClass('active');
            });
        });
        </script>
        <?php
    }

    /**
     * Recupera le statistiche per la dashboard
     * 
     * @return array Array con le statistiche
     */
    private function get_dashboard_stats() {
        // Numero di pacchetti attivi
        $pacchetti = get_posts([
            'post_type' => 'btr_pacchetti',
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields' => 'ids'
        ]);
        $count_pacchetti = count($pacchetti);
        
        // Prenotazioni totali
        $count_prenotazioni = 0;
        
        // Verifica se esiste una tabella personalizzata per le prenotazioni
        global $wpdb;
        $prenotazioni_table = $wpdb->prefix . 'btr_prenotazioni';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$prenotazioni_table'") == $prenotazioni_table) {
            // Se esiste la tabella personalizzata, conta le prenotazioni da lì
            $count_prenotazioni = $wpdb->get_var("SELECT COUNT(*) FROM $prenotazioni_table");
        } else {
            // Altrimenti, prova a contare gli ordini WooCommerce con metadati specifici
            $args = [
                'limit' => -1,
                'return' => 'ids',
                'meta_query' => [
                    [
                        'key' => '_btr_is_booking',
                        'value' => '1',
                        'compare' => '='
                    ]
                ]
            ];
            
            // Se non ci sono metadati specifici, conta tutti gli ordini degli ultimi 90 giorni
            if (!has_filter('woocommerce_order_data_store_cpt_get_orders_query', 'handle_custom_meta_query')) {
                $args = [
                    'limit' => -1,
                    'date_created' => '>' . (time() - 90 * DAY_IN_SECONDS),
                    'return' => 'ids',
                ];
            }
            
            $orders = function_exists('wc_get_orders') ? wc_get_orders($args) : [];
            $count_prenotazioni = count($orders);
        }
        
        // Preventivi attivi
        $count_preventivi = 0;
        // Alternativa: conta i post di tipo preventivo se esiste il CPT
        $preventivi = get_posts([
            'post_type' => 'btr_preventivi',
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields' => 'ids'
        ]);
        $count_preventivi = count($preventivi);
        
        // Calcola il valore delle prenotazioni
        $valore_totale = 0;
        
        // Prova a calcolare il valore dalle prenotazioni nella tabella personalizzata
        if ($wpdb->get_var("SHOW TABLES LIKE '$prenotazioni_table'") == $prenotazioni_table) {
            $valore_totale = $wpdb->get_var("SELECT SUM(importo) FROM $prenotazioni_table");
        } else if (function_exists('wc_get_orders')) {
            // Altrimenti calcola dagli ordini WooCommerce
            $args = [
                'limit' => -1,
                'return' => 'ids',
                'meta_query' => [
                    [
                        'key' => '_btr_is_booking',
                        'value' => '1',
                        'compare' => '='
                    ]
                ]
            ];
            
            // Se non ci sono metadati specifici, usa tutti gli ordini degli ultimi 90 giorni
            if (!has_filter('woocommerce_order_data_store_cpt_get_orders_query', 'handle_custom_meta_query')) {
                $args = [
                    'limit' => -1,
                    'date_created' => '>' . (time() - 90 * DAY_IN_SECONDS),
                    'return' => 'ids',
                ];
            }
            
            $order_ids = wc_get_orders($args);
            
            foreach ($order_ids as $order_id) {
                $order = wc_get_order($order_id);
                if ($order) {
                    $valore_totale += $order->get_total();
                }
            }
        }
        
        // Assicurati che il valore sia un numero valido
        $valore_totale = is_numeric($valore_totale) ? $valore_totale : 0;
        
        return [
            'pacchetti' => $count_pacchetti,
            'prenotazioni' => $count_prenotazioni,
            'preventivi' => $count_preventivi,
            'valore' => $valore_totale
        ];
    }

    /**
     * Recupera le prenotazioni recenti
     * 
     * @return array Array con le prenotazioni recenti
     */
    private function get_recent_bookings() {
        $bookings = [];
        global $wpdb;
        
        // Verifica se esiste una tabella personalizzata per le prenotazioni
        $prenotazioni_table = $wpdb->prefix . 'btr_prenotazioni';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$prenotazioni_table'") == $prenotazioni_table) {
            // Recupera le prenotazioni dalla tabella personalizzata
            $results = $wpdb->get_results(
                "SELECT * FROM $prenotazioni_table ORDER BY data_prenotazione DESC LIMIT 5"
            );
            
            if ($results) {
                foreach ($results as $result) {
                    $booking = new stdClass();
                    $booking->id = $result->id;
                    $booking->date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($result->data_prenotazione));
                    
                    // Recupera il nome del cliente
                    $customer_name = '';
                    if (!empty($result->cliente_id)) {
                        $user = get_user_by('id', $result->cliente_id);
                        if ($user) {
                            $customer_name = $user->display_name;
                        } else {
                            $customer_name = !empty($result->cliente_nome) ? $result->cliente_nome : __('Cliente', 'born-to-ride-booking') . ' #' . $result->cliente_id;
                        }
                    } else {
                        $customer_name = !empty($result->cliente_nome) ? $result->cliente_nome : __('Cliente sconosciuto', 'born-to-ride-booking');
                    }
                    
                    $booking->customer = $customer_name;
                    
                    // Recupera il nome del pacchetto
                    $package_name = '';
                    if (!empty($result->pacchetto_id)) {
                        $package = get_post($result->pacchetto_id);
                        if ($package) {
                            $package_name = $package->post_title;
                        } else {
                            $package_name = !empty($result->pacchetto_nome) ? $result->pacchetto_nome : __('Pacchetto', 'born-to-ride-booking') . ' #' . $result->pacchetto_id;
                        }
                    } else {
                        $package_name = !empty($result->pacchetto_nome) ? $result->pacchetto_nome : __('Pacchetto sconosciuto', 'born-to-ride-booking');
                    }
                    
                    $booking->package = $package_name;
                    
                    // Stato della prenotazione
                    $booking->status = !empty($result->stato) ? $result->stato : 'pending';
                    $stati = [
                        'pending' => __('In attesa', 'born-to-ride-booking'),
                        'processing' => __('In elaborazione', 'born-to-ride-booking'),
                        'completed' => __('Completata', 'born-to-ride-booking'),
                        'cancelled' => __('Annullata', 'born-to-ride-booking'),
                    ];
                    $booking->status_label = isset($stati[$booking->status]) ? $stati[$booking->status] : $booking->status;
                    
                    // Valore della prenotazione
                    $booking->value = !empty($result->importo) ? $result->importo : 0;
                    
                    // URL per visualizzare i dettagli
                    $booking->view_url = admin_url('admin.php?page=dettagli-prenotazione&prenotazione_id=' . $result->id);
                    
                    $bookings[] = $booking;
                }
            }
        } else {
            // Recupera gli ultimi ordini da WooCommerce
            if (function_exists('wc_get_orders')) {
                $args = [
                    'limit' => 5,
                    'orderby' => 'date',
                    'order' => 'DESC',
                    'return' => 'ids',
                    'meta_query' => [
                        [
                            'key' => '_btr_is_booking',
                            'value' => '1',
                            'compare' => '='
                        ]
                    ]
                ];
                
                // Se non ci sono metadati specifici, usa tutti gli ordini
                if (!has_filter('woocommerce_order_data_store_cpt_get_orders_query', 'handle_custom_meta_query')) {
                    $args = [
                        'limit' => 5,
                        'orderby' => 'date',
                        'order' => 'DESC',
                        'return' => 'ids',
                    ];
                }
                
                $order_ids = wc_get_orders($args);
                
                foreach ($order_ids as $order_id) {
                    $order = wc_get_order($order_id);
                    if ($order) {
                        $items = $order->get_items();
                        $product_name = '';
                        
                        if (!empty($items)) {
                            $item = reset($items);
                            $product_name = $item->get_name();
                        }
                        
                        $booking = new stdClass();
                        $booking->id = $order->get_id();
                        $booking->date = $order->get_date_created()->date_i18n(get_option('date_format') . ' ' . get_option('time_format'));
                        $booking->customer = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                        $booking->package = $product_name;
                        $booking->status = $order->get_status();
                        $booking->status_label = wc_get_order_status_name($order->get_status());
                        $booking->value = $order->get_total();
                        $booking->view_url = admin_url('admin.php?page=dettagli-prenotazione&prenotazione_id=' . $order->get_id());
                        
                        $bookings[] = $booking;
                    }
                }
            }
        }
        
        return $bookings;
    }

    /**
     * Recupera i preventivi recenti
     * 
     * @return array Array con i preventivi recenti
     */
    private function get_recent_quotes() {
        $quotes = [];
        global $wpdb;
        
        // Verifica se esiste il custom post type per i preventivi
        if (post_type_exists('btr_preventivi')) {
            // Recupera i preventivi dal CPT
            $args = [
                'post_type' => 'btr_preventivi',
                'post_status' => 'publish',
                'posts_per_page' => 5,
                'orderby' => 'date',
                'order' => 'DESC',
            ];
            
            $preventivi_query = new WP_Query($args);
            
            if ($preventivi_query->have_posts()) {
                while ($preventivi_query->have_posts()) {
                    $preventivi_query->the_post();
                    $post_id = get_the_ID();
                    
                    // Recupera i metadati del preventivo
                    $cliente_nome = get_post_meta($post_id, '_cliente_nome', true);
                    $cliente_email = get_post_meta($post_id, '_cliente_email', true);
                    $pacchetto_id = get_post_meta($post_id, '_pacchetto_id', true);
                    $prezzo_totale = get_post_meta($post_id, '_prezzo_totale', true);
                    $stato_preventivo = get_post_meta($post_id, '_stato_preventivo', true);
                    
                    // Recupera il nome del pacchetto
                    $pacchetto_nome = '';
                    if (!empty($pacchetto_id)) {
                        $pacchetto = get_post($pacchetto_id);
                        if ($pacchetto) {
                            $pacchetto_nome = $pacchetto->post_title;
                        }
                    }
                    
                    // Mappa gli stati dei preventivi
                    $stati = [
                        'creato'     => __('Creato', 'born-to-ride-booking'),
                        'in_attesa'  => __('In Attesa', 'born-to-ride-booking'),
                        'completato' => __('Completato', 'born-to-ride-booking'),
                        'annullato'  => __('Annullato', 'born-to-ride-booking'),
                    ];
                    
                    $quote = new stdClass();
                    $quote->id = $post_id;
                    $quote->date = get_the_date(get_option('date_format') . ' ' . get_option('time_format'));
                    $quote->customer = !empty($cliente_nome) ? $cliente_nome : __('Cliente sconosciuto', 'born-to-ride-booking');
                    $quote->package = !empty($pacchetto_nome) ? $pacchetto_nome : __('Pacchetto sconosciuto', 'born-to-ride-booking');
                    $quote->status = !empty($stato_preventivo) ? $stato_preventivo : 'creato';
                    $quote->status_label = isset($stati[$quote->status]) ? $stati[$quote->status] : ucfirst($quote->status);
                    $quote->value = !empty($prezzo_totale) ? floatval($prezzo_totale) : 0;
                    $quote->view_url = admin_url('post.php?post=' . $post_id . '&action=edit');
                    
                    $quotes[] = $quote;
                }
                
                wp_reset_postdata();
            }
        } else {
            // Verifica se esiste una tabella personalizzata per i preventivi
            $preventivi_table = $wpdb->prefix . 'btr_quotes';
            
            if ($wpdb->get_var("SHOW TABLES LIKE '$preventivi_table'") == $preventivi_table) {
                // Recupera i preventivi dalla tabella personalizzata
                $results = $wpdb->get_results(
                    "SELECT * FROM $preventivi_table ORDER BY created_at DESC LIMIT 5"
                );
                
                if ($results) {
                    foreach ($results as $result) {
                        $quote = new stdClass();
                        $quote->id = $result->id;
                        $quote->date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($result->created_at));
                        
                        // Recupera il nome del cliente
                        $quote->customer = !empty($result->customer_name) ? $result->customer_name : __('Cliente sconosciuto', 'born-to-ride-booking');
                        
                        // Recupera il nome del pacchetto
                        $pacchetto_id = $result->package_id ?? 0;
                        $pacchetto_nome = '';
                        if (!empty($pacchetto_id)) {
                            $pacchetto = get_post($pacchetto_id);
                            if ($pacchetto) {
                                $pacchetto_nome = $pacchetto->post_title;
                            }
                        }
                        $quote->package = !empty($pacchetto_nome) ? $pacchetto_nome : __('Pacchetto sconosciuto', 'born-to-ride-booking');
                        
                        // Stato del preventivo
                        $quote->status = !empty($result->status) ? $result->status : 'pending';
                        $stati = [
                            'pending' => __('In attesa', 'born-to-ride-booking'),
                            'processing' => __('In elaborazione', 'born-to-ride-booking'),
                            'completed' => __('Completato', 'born-to-ride-booking'),
                            'cancelled' => __('Annullato', 'born-to-ride-booking'),
                        ];
                        $quote->status_label = isset($stati[$quote->status]) ? $stati[$quote->status] : ucfirst($quote->status);
                        
                        // Valore del preventivo
                        $quote->value = !empty($result->total_price) ? floatval($result->total_price) : 0;
                        
                        // URL per visualizzare i dettagli
                        $quote->view_url = admin_url('admin.php?page=btr-quotes&action=view&id=' . $result->id);
                        
                        $quotes[] = $quote;
                    }
                }
            }
        }
        
        return $quotes;
    }
}
