<?php
/**
 * BTR Order Recovery System
 * 
 * Gestisce il recupero di ordini abbandonati e la riparazione di ordini esistenti
 * 
 * @package Born_To_Ride_Booking
 * @since 1.0.235
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class BTR_Order_Recovery
 * 
 * Sistema intelligente per recuperare ordini abbandonati e riparare ordini esistenti
 * Include anche funzionalità per generare link di recovery sicuri
 */
class BTR_Order_Recovery {
    
    /**
     * Instance singleton
     */
    private static $instance = null;
    
    /**
     * Get instance
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
    public function __construct() {
        // Hook per gestire recovery link
        add_action('init', [$this, 'handle_recovery_link']);
        
        // Hook per riparare ordini al login
        add_action('wp_login', [$this, 'repair_user_orders_on_login'], 10, 2);
        
        // Ajax handler per check ordini da riparare
        add_action('wp_ajax_btr_check_orders_to_repair', [$this, 'ajax_check_orders_to_repair']);

        // NUOVO: Ajax handlers per gestione ordini bozza
        add_action('wp_ajax_btr_get_draft_orders', [$this, 'ajax_get_draft_orders']);
        add_action('wp_ajax_btr_delete_draft_orders', [$this, 'ajax_delete_draft_orders']);
        add_action('wp_ajax_btr_get_cleanup_log', [$this, 'ajax_get_cleanup_log']);

        // NUOVO: Menu admin per gestione ordini bozza
        add_action('admin_menu', [$this, 'add_admin_menu']);

        // Scheduled action per identificare ordini abbandonati
        add_action('btr_check_abandoned_orders', [$this, 'check_and_mark_abandoned_orders']);

        // NUOVO: Scheduled action per auto-cleanup ordini bozza
        add_action('btr_check_abandoned_orders', [$this, 'scheduled_draft_cleanup']);

        // Schedule cron se non esiste
        if (!wp_next_scheduled('btr_check_abandoned_orders')) {
            wp_schedule_event(time(), 'hourly', 'btr_check_abandoned_orders');
        }
    }
    
    /**
     * Ripara ordini esistenti senza metadati corretti
     * 
     * Questo metodo trova ordini che hanno _btr_preventivo_id ma non _btr_is_group_organizer
     * e aggiunge i metadati mancanti
     * 
     * @param int|null $user_id Limita la riparazione agli ordini di un utente specifico
     * @return array Report con numero di ordini riparati
     */
    public function repair_existing_orders($user_id = null) {
        global $wpdb;
        
        $repaired = 0;
        $errors = [];
        
        // Query per trovare ordini con preventivo ma senza flag organizzatore
        $query = "
            SELECT DISTINCT p.ID, p.post_author, pm1.meta_value as preventivo_id
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_btr_preventivo_id'
            LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_btr_is_group_organizer'
            WHERE p.post_type IN ('shop_order', 'shop_order_placehold')
            AND pm2.meta_id IS NULL
        ";
        
        if ($user_id) {
            $query .= $wpdb->prepare(" AND p.post_author = %d", $user_id);
        }
        
        $orders_to_repair = $wpdb->get_results($query);
        
        foreach ($orders_to_repair as $order_data) {
            try {
                // Verifica che il preventivo esista e sia valido
                $preventivo = get_post($order_data->preventivo_id);
                if (!$preventivo || $preventivo->post_type !== 'btr_preventivi') {
                    continue;
                }
                
                // Verifica se ci sono pagamenti di gruppo per questo preventivo
                $has_group_payments = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}btr_group_payments WHERE preventivo_id = %d",
                    $order_data->preventivo_id
                ));
                
                if ($has_group_payments > 0) {
                    // Aggiungi metadati mancanti
                    update_post_meta($order_data->ID, '_btr_is_group_organizer', 'yes');
                    update_post_meta($order_data->ID, '_btr_order_type', 'group_organizer');
                    update_post_meta($order_data->ID, '_customer_user', $order_data->post_author);
                    
                    // Recupera e salva totali dal preventivo
                    $totale = get_post_meta($order_data->preventivo_id, '_prezzo_totale', true);
                    if ($totale) {
                        update_post_meta($order_data->ID, '_btr_total_amount', $totale);
                    }
                    
                    // Log
                    btr_debug_log('BTR Recovery: Riparato ordine ' . $order_data->ID . ' per preventivo ' . $order_data->preventivo_id);
                    
                    // Aggiungi nota all'ordine
                    $order = wc_get_order($order_data->ID);
                    if ($order) {
                        $order->add_order_note(__('Ordine organizzatore riparato dal sistema recovery.', 'born-to-ride-booking'));
                    }
                    
                    $repaired++;
                }
                
            } catch (Exception $e) {
                $errors[] = 'Errore riparazione ordine ' . $order_data->ID . ': ' . $e->getMessage();
                btr_debug_log('BTR Recovery Error: ' . $e->getMessage());
            }
        }
        
        return [
            'repaired' => $repaired,
            'errors' => $errors,
            'checked' => count($orders_to_repair)
        ];
    }
    
    /**
     * Identifica ordini abbandonati e li marca per recovery
     */
    public function check_and_mark_abandoned_orders() {
        global $wpdb;
        
        // Trova ordini draft più vecchi di 1 ora
        $one_hour_ago = date('Y-m-d H:i:s', strtotime('-1 hour'));
        
        $abandoned_orders = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, p.post_date, p.post_author
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
            WHERE p.post_type IN ('shop_order', 'shop_order_placehold')
            AND p.post_status = 'draft'
            AND p.post_date < %s
            AND pm.meta_key = '_btr_is_group_organizer'
            AND pm.meta_value = 'yes'
            AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm2 
                WHERE pm2.post_id = p.ID 
                AND pm2.meta_key = '_btr_abandoned_notified'
            )
        ", $one_hour_ago));
        
        foreach ($abandoned_orders as $order_data) {
            // Marca come notificato per evitare spam
            update_post_meta($order_data->ID, '_btr_abandoned_notified', current_time('timestamp'));
            
            // Genera recovery token
            $recovery_token = $this->generate_recovery_token($order_data->ID);
            update_post_meta($order_data->ID, '_btr_recovery_token', $recovery_token);
            
            // Trigger per email (gestito da class-btr-abandoned-cart-emails.php)
            do_action('btr_order_abandoned', $order_data->ID, $recovery_token);
            
            btr_debug_log('BTR Recovery: Ordine ' . $order_data->ID . ' marcato come abbandonato');
        }
    }
    
    /**
     * Genera token sicuro per recovery link
     */
    private function generate_recovery_token($order_id) {
        return wp_hash($order_id . wp_salt('auth') . time());
    }
    
    /**
     * Gestisce click su recovery link
     */
    public function handle_recovery_link() {
        if (!isset($_GET['btr-recovery']) || !isset($_GET['token'])) {
            return;
        }
        
        $order_id = intval($_GET['btr-recovery']);
        $token = sanitize_text_field($_GET['token']);
        
        // Verifica token
        $stored_token = get_post_meta($order_id, '_btr_recovery_token', true);
        if (!$stored_token || $stored_token !== $token) {
            wp_die(__('Link di recupero non valido o scaduto.', 'born-to-ride-booking'));
        }
        
        // Verifica che l'ordine esista e sia draft
        $order = wc_get_order($order_id);
        if (!$order || $order->get_status() !== 'draft') {
            wp_die(__('Ordine non trovato o già completato.', 'born-to-ride-booking'));
        }
        
        // Auto-login se necessario
        $user_id = $order->get_customer_id();
        if ($user_id && !is_user_logged_in()) {
            wp_set_auth_cookie($user_id, true);
            wp_set_current_user($user_id);
        }
        
        // Ripristina carrello
        $this->restore_cart_from_order($order);
        
        // Redirect al checkout
        wp_redirect(wc_get_checkout_url());
        exit;
    }
    
    /**
     * Ripristina carrello da ordine draft
     */
    private function restore_cart_from_order($order) {
        // Svuota carrello corrente
        WC()->cart->empty_cart();
        
        // Recupera dati dal preventivo
        $preventivo_id = $order->get_meta('_btr_preventivo_id');
        if (!$preventivo_id) {
            return;
        }
        
        // Ricrea il prodotto virtuale nel carrello
        $product_id = get_option('btr_virtual_organizer_product_id');
        if ($product_id) {
            WC()->cart->add_to_cart($product_id, 1, 0, array(), array(
                'btr_preventivo_id' => $preventivo_id,
                'btr_order_type' => 'group_organizer',
                'btr_total_amount' => $order->get_meta('_btr_total_amount'),
                'btr_covered_amount' => $order->get_meta('_btr_covered_amount'),
                'custom_price' => 0
            ));
            
            // Ripristina dati sessione
            WC()->session->set('btr_is_organizer_order', true);
            WC()->session->set('btr_preventivo_id', $preventivo_id);
            WC()->session->set('btr_payment_type', 'group_organizer');
            WC()->session->set('btr_total_amount', $order->get_meta('_btr_total_amount'));
            WC()->session->set('btr_covered_amount', $order->get_meta('_btr_covered_amount'));
            WC()->session->set('btr_participants_info', $order->get_meta('_btr_participants_info'));
            
            // Salva sessione
            WC()->session->save_data();
            
            btr_debug_log('BTR Recovery: Carrello ripristinato per ordine ' . $order->get_id());
        }
    }
    
    /**
     * Ripara ordini dell'utente al login
     */
    public function repair_user_orders_on_login($user_login, $user) {
        $this->repair_existing_orders($user->ID);
    }
    
    /**
     * Ajax handler per check ordini da riparare (admin)
     */
    public function ajax_check_orders_to_repair() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die('Unauthorized');
        }
        
        $result = $this->repair_existing_orders();
        
        wp_send_json_success($result);
    }
    
    /**
     * Metodo helper per generare recovery URL
     */
    public static function get_recovery_url($order_id, $token = null) {
        if (!$token) {
            $token = get_post_meta($order_id, '_btr_recovery_token', true);
        }

        return add_query_arg([
            'btr-recovery' => $order_id,
            'token' => $token
        ], home_url());
    }

    /**
     * Ottieni ordini in bozza da eliminare
     *
     * @param int $days_old Giorni di anzianità minima
     * @return array Lista ordini con dettagli
     */
    public function get_draft_orders_for_cleanup($days_old = 1) {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, p.post_date, p.post_author, pm.meta_value as preventivo_id,
                   u.display_name as customer_name
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_btr_preventivo_id'
            LEFT JOIN {$wpdb->users} u ON p.post_author = u.ID
            WHERE p.post_type IN ('shop_order', 'shop_order_placehold')
            AND p.post_status = 'draft'
            AND p.post_date < DATE_SUB(NOW(), INTERVAL %d DAY)
            AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm2
                WHERE pm2.post_id = p.ID
                AND pm2.meta_key IN ('_payment_processing', '_payment_method', '_transaction_id')
            )
            ORDER BY p.post_date DESC
        ", $days_old));

        $orders = [];
        foreach ($results as $row) {
            $order = wc_get_order($row->ID);
            $preventivo_title = get_the_title($row->preventivo_id);

            $orders[] = [
                'id' => $row->ID,
                'date' => $row->post_date,
                'customer' => $row->customer_name ?: 'Guest',
                'preventivo_id' => $row->preventivo_id,
                'preventivo_title' => $preventivo_title,
                'total' => $order ? $order->get_total() : 0,
                'age_days' => floor((time() - strtotime($row->post_date)) / 86400)
            ];
        }

        return $orders;
    }

    /**
     * Elimina ordini in bozza specificati
     *
     * @param array $order_ids Array di ID ordini da eliminare
     * @param string $reason Motivo eliminazione per log
     * @return array Report eliminazione
     */
    public function delete_draft_orders($order_ids, $reason = 'manual') {
        if (!current_user_can('manage_woocommerce')) {
            return ['error' => 'Insufficient permissions'];
        }

        $deleted = 0;
        $errors = [];
        $log_entries = [];

        foreach ($order_ids as $order_id) {
            try {
                $order = wc_get_order($order_id);
                if (!$order) {
                    $errors[] = "Ordine $order_id non trovato";
                    continue;
                }

                // Verifica che sia davvero in bozza
                if ($order->get_status() !== 'draft') {
                    $errors[] = "Ordine $order_id non è in bozza (status: {$order->get_status()})";
                    continue;
                }

                // Backup metadati per log
                $preventivo_id = $order->get_meta('_btr_preventivo_id');
                $total = $order->get_total();
                $customer = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();

                // Log eliminazione
                $log_entry = [
                    'timestamp' => current_time('mysql'),
                    'order_id' => $order_id,
                    'preventivo_id' => $preventivo_id,
                    'customer' => $customer,
                    'total' => $total,
                    'reason' => $reason,
                    'admin_user' => get_current_user_id()
                ];

                // Elimina ordine
                if (wp_delete_post($order_id, true)) {
                    $deleted++;
                    $log_entries[] = $log_entry;
                    btr_debug_log("BTR Draft Cleanup: Eliminato ordine bozza $order_id (preventivo: $preventivo_id, motivo: $reason)");
                } else {
                    $errors[] = "Errore eliminazione ordine $order_id";
                }

            } catch (Exception $e) {
                $errors[] = "Errore ordine $order_id: " . $e->getMessage();
                btr_debug_log('BTR Draft Cleanup Error: ' . $e->getMessage());
            }
        }

        // Salva log eliminazioni
        if (!empty($log_entries)) {
            $existing_log = get_option('btr_draft_orders_cleanup_log', []);
            $existing_log = array_merge($existing_log, $log_entries);

            // Mantieni solo ultimi 100 record
            if (count($existing_log) > 100) {
                $existing_log = array_slice($existing_log, -100);
            }

            update_option('btr_draft_orders_cleanup_log', $existing_log);
        }

        return [
            'deleted' => $deleted,
            'errors' => $errors,
            'processed' => count($order_ids),
            'log_entries' => count($log_entries)
        ];
    }

    /**
     * Auto-cleanup ordini bozza vecchi
     *
     * @param int $days_retention Giorni dopo cui eliminare automaticamente
     * @return array Report eliminazione
     */
    public function auto_cleanup_old_drafts($days_retention = 7) {
        $auto_cleanup_enabled = get_option('btr_auto_cleanup_draft_orders', false);
        if (!$auto_cleanup_enabled) {
            return ['skipped' => 'Auto-cleanup disabilitato'];
        }

        $old_orders = $this->get_draft_orders_for_cleanup($days_retention);
        if (empty($old_orders)) {
            return ['deleted' => 0, 'message' => 'Nessun ordine da eliminare'];
        }

        $order_ids = array_column($old_orders, 'id');
        $result = $this->delete_draft_orders($order_ids, 'auto_cleanup');

        btr_debug_log("BTR Auto Cleanup: Eliminati {$result['deleted']} ordini bozza più vecchi di $days_retention giorni");

        return $result;
    }

    /**
     * AJAX: Ottieni ordini bozza per interfaccia admin
     */
    public function ajax_get_draft_orders() {
        // Verifica nonce e permessi
        if (!check_ajax_referer('btr_admin_nonce', 'nonce', false) || !current_user_can('manage_options')) {
            wp_die('Accesso negato');
        }

        try {
            $days_old = isset($_POST['days_old']) ? intval($_POST['days_old']) : 1;
            $draft_orders = $this->get_draft_orders_for_cleanup($days_old);

            wp_send_json_success([
                'orders' => $draft_orders,
                'count' => count($draft_orders),
                'days_filter' => $days_old
            ]);

        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Elimina ordini bozza selezionati
     */
    public function ajax_delete_draft_orders() {
        // Verifica nonce e permessi
        if (!check_ajax_referer('btr_admin_nonce', 'nonce', false) || !current_user_can('manage_options')) {
            wp_die('Accesso negato');
        }

        try {
            $order_ids = isset($_POST['order_ids']) ? array_map('intval', $_POST['order_ids']) : [];
            $reason = isset($_POST['reason']) ? sanitize_text_field($_POST['reason']) : 'manual_admin';

            if (empty($order_ids)) {
                wp_send_json_error(['message' => 'Nessun ordine selezionato']);
                return;
            }

            $result = $this->delete_draft_orders($order_ids, $reason);

            wp_send_json_success([
                'deleted' => $result['deleted'],
                'errors' => $result['errors'],
                'processed' => $result['processed'],
                'message' => "Eliminati {$result['deleted']} ordini su {$result['processed']} selezionati"
            ]);

        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX: Ottieni log pulizia ordini bozza
     */
    public function ajax_get_cleanup_log() {
        // Verifica nonce e permessi
        if (!check_ajax_referer('btr_admin_nonce', 'nonce', false) || !current_user_can('manage_options')) {
            wp_die('Accesso negato');
        }

        try {
            $cleanup_log = get_option('btr_draft_orders_cleanup_log', []);
            $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 50;

            // Ordina per timestamp decrescente e limita
            usort($cleanup_log, function($a, $b) {
                return strtotime($b['timestamp']) - strtotime($a['timestamp']);
            });

            if ($limit > 0) {
                $cleanup_log = array_slice($cleanup_log, 0, $limit);
            }

            wp_send_json_success([
                'log_entries' => $cleanup_log,
                'total' => count($cleanup_log)
            ]);

        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Aggiunge menu admin per gestione ordini bozza
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=btr_pacchetti',
            'Ordini Bozza',
            'Ordini Bozza',
            'manage_options',
            'btr-draft-orders',
            [$this, 'admin_page_draft_orders']
        );
    }

    /**
     * Pagina admin per gestione ordini bozza
     */
    public function admin_page_draft_orders() {
        wp_enqueue_script('jquery');

        // Salva impostazioni se inviate
        if (isset($_POST['save_settings'])) {
            check_admin_referer('btr_draft_settings');

            $auto_cleanup = isset($_POST['auto_cleanup']) ? true : false;
            $retention_days = intval($_POST['retention_days']);

            update_option('btr_auto_cleanup_draft_orders', $auto_cleanup);
            update_option('btr_draft_orders_retention_days', $retention_days);

            echo '<div class="notice notice-success"><p>Impostazioni salvate!</p></div>';
        }

        $auto_cleanup = get_option('btr_auto_cleanup_draft_orders', false);
        $retention_days = get_option('btr_draft_orders_retention_days', 7);
        ?>

        <div class="wrap">
            <h1>Gestione Ordini Bozza</h1>

            <div class="card">
                <h2>⚙️ Impostazioni Auto-Pulizia</h2>
                <form method="post" action="">
                    <?php wp_nonce_field('btr_draft_settings'); ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">Auto-pulizia</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="auto_cleanup" value="1" <?php checked($auto_cleanup, true); ?> />
                                    Elimina automaticamente ordini bozza vecchi
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Giorni di conservazione</th>
                            <td>
                                <input type="number" name="retention_days" value="<?php echo esc_attr($retention_days); ?>" min="1" max="30" />
                                <p class="description">Giorni dopo cui eliminare automaticamente gli ordini bozza</p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button('Salva Impostazioni', 'primary', 'save_settings'); ?>
                </form>
            </div>

            <div class="card">
                <h2>🗑️ Gestione Manuale</h2>

                <div style="margin-bottom: 15px;">
                    <label for="days-filter">Mostra ordini più vecchi di:</label>
                    <select id="days-filter">
                        <option value="1">1 giorno</option>
                        <option value="3">3 giorni</option>
                        <option value="7">7 giorni</option>
                        <option value="14">14 giorni</option>
                        <option value="30">30 giorni</option>
                    </select>
                    <button type="button" id="load-orders" class="button">Carica Ordini</button>
                </div>

                <div id="orders-list">
                    <p>Seleziona un filtro e clicca "Carica Ordini" per vedere gli ordini bozza disponibili.</p>
                </div>

                <div id="bulk-actions" style="display:none; margin-top: 15px;">
                    <button type="button" id="select-all" class="button">Seleziona Tutti</button>
                    <button type="button" id="delete-selected" class="button button-secondary">Elimina Selezionati</button>
                </div>
            </div>

            <div class="card">
                <h2>📋 Log Eliminazioni</h2>
                <button type="button" id="load-log" class="button">Carica Log</button>
                <div id="cleanup-log" style="margin-top: 15px;">
                    <p>Clicca "Carica Log" per vedere le ultime eliminazioni.</p>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var nonce = '<?php echo wp_create_nonce('btr_admin_nonce'); ?>';

            // Carica ordini bozza
            $('#load-orders').click(function() {
                var daysOld = $('#days-filter').val();

                $.post(ajaxurl, {
                    action: 'btr_get_draft_orders',
                    days_old: daysOld,
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        displayOrders(response.data.orders);
                    } else {
                        $('#orders-list').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                    }
                });
            });

            // Elimina ordini selezionati
            $('#delete-selected').click(function() {
                var selectedOrders = [];
                $('input[name="order_ids[]"]:checked').each(function() {
                    selectedOrders.push($(this).val());
                });

                if (selectedOrders.length === 0) {
                    alert('Seleziona almeno un ordine da eliminare');
                    return;
                }

                if (!confirm('Sei sicuro di voler eliminare ' + selectedOrders.length + ' ordini bozza?')) {
                    return;
                }

                $.post(ajaxurl, {
                    action: 'btr_delete_draft_orders',
                    order_ids: selectedOrders,
                    reason: 'manual_admin_bulk',
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        $('#load-orders').click(); // Ricarica lista
                    } else {
                        alert('Errore: ' + response.data.message);
                    }
                });
            });

            // Seleziona tutti
            $('#select-all').click(function() {
                $('input[name="order_ids[]"]').prop('checked', true);
            });

            // Carica log
            $('#load-log').click(function() {
                $.post(ajaxurl, {
                    action: 'btr_get_cleanup_log',
                    limit: 20,
                    nonce: nonce
                }, function(response) {
                    if (response.success) {
                        displayLog(response.data.log_entries);
                    } else {
                        $('#cleanup-log').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                    }
                });
            });

            function displayOrders(orders) {
                if (orders.length === 0) {
                    $('#orders-list').html('<p>Nessun ordine bozza trovato per il periodo selezionato.</p>');
                    $('#bulk-actions').hide();
                    return;
                }

                var html = '<table class="widefat"><thead><tr>';
                html += '<th><input type="checkbox" id="select-all-checkbox"></th>';
                html += '<th>ID Ordine</th><th>Preventivo ID</th><th>Data Creazione</th><th>Stato</th><th>Totale</th>';
                html += '</tr></thead><tbody>';

                $.each(orders, function(i, order) {
                    html += '<tr>';
                    html += '<td><input type="checkbox" name="order_ids[]" value="' + order.id + '"></td>';
                    html += '<td>' + order.id + '</td>';
                    html += '<td>' + (order.preventivo_id || 'N/A') + '</td>';
                    html += '<td>' + order.date_created + '</td>';
                    html += '<td>' + order.status + '</td>';
                    html += '<td>' + (order.total || '0') + '</td>';
                    html += '</tr>';
                });

                html += '</tbody></table>';
                $('#orders-list').html(html);
                $('#bulk-actions').show();

                // Handler per select all checkbox
                $('#select-all-checkbox').change(function() {
                    $('input[name="order_ids[]"]').prop('checked', this.checked);
                });
            }

            function displayLog(logEntries) {
                if (logEntries.length === 0) {
                    $('#cleanup-log').html('<p>Nessuna eliminazione registrata.</p>');
                    return;
                }

                var html = '<table class="widefat"><thead><tr>';
                html += '<th>Data</th><th>Ordine ID</th><th>Preventivo ID</th><th>Motivo</th>';
                html += '</tr></thead><tbody>';

                $.each(logEntries, function(i, entry) {
                    html += '<tr>';
                    html += '<td>' + entry.timestamp + '</td>';
                    html += '<td>' + entry.order_id + '</td>';
                    html += '<td>' + (entry.preventivo_id || 'N/A') + '</td>';
                    html += '<td>' + entry.reason + '</td>';
                    html += '</tr>';
                });

                html += '</tbody></table>';
                $('#cleanup-log').html(html);
            }
        });
        </script>

        <style>
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            margin: 20px 0;
            padding: 20px;
        }
        .card h2 {
            margin-top: 0;
        }
        </style>
        <?php
    }
}

// Inizializza
BTR_Order_Recovery::get_instance();