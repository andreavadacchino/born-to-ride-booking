<?php
if (!defined('ABSPATH')) {
    exit; // Nessun accesso diretto consentito
}

if (!class_exists('BTR_Prenotazioni_Manager')) {

    class BTR_Prenotazioni_Manager
    {
        private const TEXT_DOMAIN = 'born-to-ride-booking';

        public function __construct()
        {
            add_action('admin_menu', [$this, 'add_prenotazioni_menu']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

            // AJAX handler per eliminazione ordini
            add_action('wp_ajax_btr_delete_order_from_list', [$this, 'ajax_delete_order']);
        }

        public function add_prenotazioni_menu()
        {
            add_submenu_page(
                'btr-booking', // Menu parent
                __('Prenotazioni', self::TEXT_DOMAIN),
                __('Prenotazioni', self::TEXT_DOMAIN),
                'manage_woocommerce',
                'btr-prenotazioni',
                [$this, 'render_prenotazioni_page']
            );
            add_submenu_page(
                null,
                __('Dettagli Prenotazione', self::TEXT_DOMAIN),
                __('Dettagli Prenotazione', self::TEXT_DOMAIN),
                'manage_woocommerce',
                'dettagli-prenotazione',
                [$this, 'render_prenotazione_detail_page']
            );
        }

        public function enqueue_admin_assets($hook)
        {
            // Aggiorna il check per il nuovo percorso del menu
            if ($hook === 'btr-booking_page_btr-prenotazioni') {
                wp_enqueue_style('fomantic-ui', 'https://cdn.jsdelivr.net/npm/fomantic-ui/dist/semantic.min.css');
                wp_enqueue_style('datatables', 'https://cdn.datatables.net/1.13.5/css/dataTables.semanticui.min.css');
                wp_enqueue_script('jquery-datatables', 'https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js', ['jquery'], null, true);
                wp_enqueue_script('datatables-ui', 'https://cdn.datatables.net/1.13.5/js/dataTables.semanticui.min.js', ['jquery-datatables'], null, true);
                wp_enqueue_script('fomantic-ui', 'https://cdn.jsdelivr.net/npm/fomantic-ui/dist/semantic.min.js', ['jquery'], null, true);
                wp_add_inline_script('datatables-ui', '
                    jQuery(document).ready(function($) {
                        var table = $("#prenotazioni-table").DataTable({
                            language: {
                                url: "//cdn.datatables.net/plug-ins/1.13.5/i18n/Italian.json"
                            },
                            dom: "<\'ui stackable grid\'<\'row\'<\'eight wide column\'f><\'eight wide column\'l>>t<\'row\'<\'six wide column\'i><\'six wide column\'p>>>",
                            responsive: true,
                            order: [[0, "desc"]]
                        });

                        $(".filter-status .item").on("click", function() {
                            var value = $(this).data("value");
                            table.column(2).search(value ? "^" + value + "$" : "", true, false).draw();
                        });

                        $(".ui.dropdown").dropdown();

                        // Handler per eliminazione ordini
                        $(document).on("click", ".delete-order", function() {
                            var orderId = $(this).data("order-id");
                            var row = $(this).closest("tr");

                            if (confirm("Sei sicuro di voler eliminare l\'ordine bozza #" + orderId + "?")) {
                                $.post(ajaxurl, {
                                    action: "btr_delete_order_from_list",
                                    order_id: orderId,
                                    nonce: "' . wp_create_nonce('btr_delete_order') . '"
                                }, function(response) {
                                    if (response.success) {
                                        table.row(row).remove().draw();
                                        alert("Ordine eliminato con successo");
                                    } else {
                                        alert("Errore: " + response.data.message);
                                    }
                                });
                            }
                        });
                    });
                ');
            }
        }

        public function render_prenotazioni_page()
        {
            if (!current_user_can('manage_woocommerce')) {
                wp_die(__('Non hai i permessi per accedere a questa pagina.', self::TEXT_DOMAIN));
            }

            $orders = $this->get_orders();
            $order_statuses = wc_get_order_statuses();

            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('Prenotazioni', self::TEXT_DOMAIN) . '</h1>';

            echo '<div class="ui stackable grid">';
            echo '<div class="row">';
            echo '<div class="four wide column">';
            echo '<div class="ui fluid dropdown filter-status">';
            echo '<div class="text">' . esc_html__('Filtra per Stato', self::TEXT_DOMAIN) . '</div>';
            echo '<i class="dropdown icon"></i>';
            echo '<div class="menu">';
            echo '<div class="item" data-value="">' . esc_html__('Tutti gli Stati', self::TEXT_DOMAIN) . '</div>';
            foreach ($order_statuses as $status_key => $status_label) {
                echo '<div class="item" data-value="' . esc_attr($status_key) . '">' . esc_html($status_label) . '</div>';
            }
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';

            echo '<table id="prenotazioni-table" class="ui celled table">';
            echo '<thead>
                    <tr>
                        <th>' . __('Numero Prenotazione', self::TEXT_DOMAIN) . '</th>
                        <th>' . __('Data', self::TEXT_DOMAIN) . '</th>
                        <th>' . __('Stato', self::TEXT_DOMAIN) . '</th>
                        <th>' . __('Cliente', self::TEXT_DOMAIN) . '</th>
                        <th>' . __('Prodotti', self::TEXT_DOMAIN) . '</th>
                        <th>' . __('Azioni', self::TEXT_DOMAIN) . '</th>
                    </tr>
                  </thead>';
            echo '<tbody>';
            foreach ($orders as $order) {
                echo '<tr>';
                echo '<td>' . esc_html($order['order_id']) . '</td>';
                echo '<td>' . esc_html($order['order_date']) . '</td>';
                echo '<td data-status="' . esc_attr($order['status_key']) . '">' . esc_html($order['status_label']) . '</td>';
                echo '<td>' . esc_html($order['customer_name']) . '</td>';
                echo '<td>' . esc_html($order['product_names']) . '</td>';
                echo '<td>';
                echo '<a href="' . esc_url($order['detail_url']) . '" class="ui button">' . __('Dettagli', self::TEXT_DOMAIN) . '</a>';

                // Aggiungi pulsante Elimina solo per ordini pending/bozza
                if (in_array($order['status_key'], ['wc-pending', 'wc-checkout-draft', 'wc-draft'])) {
                    echo ' <button type="button" class="ui button red delete-order" data-order-id="' . esc_attr($order['order_id']) . '">' . __('Elimina', self::TEXT_DOMAIN) . '</button>';
                }

                echo '</td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        }

        private function get_orders()
        {
            $args = [
                'limit' => -1,
                'orderby' => 'date',
                'order' => 'DESC',
                'return' => 'ids',
                'status' => array_keys(wc_get_order_statuses()),
            ];
            $order_ids = wc_get_orders($args);
            $orders = [];
            foreach ($order_ids as $order_id) {
                $order = wc_get_order($order_id);
                if ($order) {
                    $items = $order->get_items();
                    $product_names = [];
                    foreach ($items as $item) {
                        $product_names[] = $item->get_name();
                    }
                    $status_key = 'wc-' . $order->get_status();
                    $orders[] = [
                        'order_id' => $order->get_id(),
                        'order_date' => $order->get_date_created() ? $order->get_date_created()->date('d/m/Y H:i') : '',
                        'status_key' => $status_key,
                        'status_label' => wc_get_order_status_name($status_key),
                        'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                        'product_names' => implode(', ', $product_names),
                        'detail_url' => add_query_arg(['page' => 'dettagli-prenotazione', 'prenotazione_id' => $order->get_id()], admin_url('admin.php')),
                    ];
                }
            }
            return $orders;
        }

        /**
         * Renderizza la pagina di dettaglio della prenotazione.
         */
        public function render_prenotazione_detail_page()
        {
            if (!isset($_GET['prenotazione_id'])) {
                echo '<div class="wrap"><h1>' . esc_html__('Dettagli Prenotazione', self::TEXT_DOMAIN) . '</h1>';
                echo '<p>' . esc_html__('ID Prenotazione non fornito.', self::TEXT_DOMAIN) . '</p></div>';
                return;
            }
            $order_id = intval($_GET['prenotazione_id']);
            $order = wc_get_order($order_id);
            if (!$order) {
                echo '<div class="wrap"><h1>' . esc_html__('Dettagli Prenotazione', self::TEXT_DOMAIN) . '</h1>';
                echo '<p>' . esc_html__('Ordine non trovato.', self::TEXT_DOMAIN) . '</p></div>';
                return;
            }
            echo '<div class="wrap woocommerce"><h1>' . sprintf(esc_html__('Dettagli Prenotazione #%d', self::TEXT_DOMAIN), $order_id) . '</h1>';
            if (!class_exists('BTR_Prenotazioni_OrderView')) {
                echo '<p><em>' . esc_html__('Classe di visualizzazione non disponibile.', self::TEXT_DOMAIN) . '</em></p>';
            } else {
                $view = new BTR_Prenotazioni_OrderView();
                echo $view->render_orderlike_view($order_id);
            }
            echo '</div>';
        }

        /**
         * AJAX handler per eliminare ordini dalla lista
         */
        public function ajax_delete_order() {
            // Verifica nonce e permessi
            if (!check_ajax_referer('btr_delete_order', 'nonce', false) || !current_user_can('manage_woocommerce')) {
                wp_send_json_error(['message' => 'Permessi insufficienti']);
                return;
            }

            $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
            if (!$order_id) {
                wp_send_json_error(['message' => 'ID ordine non valido']);
                return;
            }

            $order = wc_get_order($order_id);
            if (!$order) {
                wp_send_json_error(['message' => 'Ordine non trovato']);
                return;
            }

            // Verifica che sia un ordine bozza/pending
            if (!in_array($order->get_status(), ['pending', 'checkout-draft', 'draft'])) {
                wp_send_json_error(['message' => 'Solo ordini bozza possono essere eliminati']);
                return;
            }

            // Elimina l'ordine
            if (wp_delete_post($order_id, true)) {
                btr_debug_log("BTR Lista Prenotazioni: Eliminato ordine bozza #$order_id");
                wp_send_json_success(['message' => 'Ordine eliminato con successo']);
            } else {
                wp_send_json_error(['message' => 'Errore durante eliminazione']);
            }
        }
    }
}