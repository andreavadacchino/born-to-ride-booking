<?php
if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class BTR_Quotes_List_Table extends WP_List_Table {
    public function __construct() {
        parent::__construct([
            'singular' => 'preventivo',
            'plural' => 'preventivi',
            'ajax' => false
        ]);
    }

    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'quote_id' => 'ID',
            'product' => 'Prodotto',
            'dates' => 'Date',
            'price' => 'Prezzo',
            'expires_at' => 'Scadenza',
            'actions' => 'Azioni'
        ];
    }

    public function prepare_items() {
        global $wpdb;
        
        $per_page = 20;
        $current_page = $this->get_pagenum();
        
        $query = "SELECT * FROM {$wpdb->prefix}btr_quotes";
        $where = [];
        
        if (!empty($_REQUEST['s'])) {
            $search = sanitize_text_field($_REQUEST['s']);
            $where[] = $wpdb->prepare("(hash LIKE %s OR configuration LIKE %s)", "%$search%", "%$search%");
        }
        
        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }
        
        $total_items = $wpdb->get_var(str_replace('*', 'COUNT(*)', $query));
        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page' => $per_page
        ]);
        
        $this->items = $wpdb->get_results($wpdb->prepare(
            "$query ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            ($current_page - 1) * $per_page
        ));
    }

    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'product':
                $product = wc_get_product($item->product_id);
                return $product ? $product->get_name() : 'N/A';
                
            case 'dates':
                return date('d/m/Y', strtotime($item->start_date)) . ' - ' . date('d/m/Y', strtotime($item->end_date));
                
            case 'price':
                return wc_price($item->price);
                
            case 'expires_at':
                return date_i18n('d F Y H:i', strtotime($item->expires_at));
                
            case 'actions':
                return sprintf(
                    '<a href="%s" target="_blank">Visualizza</a> | '.
                    '<a href="%s" style="color:#a00">Elimina</a>',
                    home_url("/?quote={$item->hash}"),
                    wp_nonce_url(
                        admin_url('admin.php?page=btr-quotes&action=delete&quote='.$item->quote_id),
                        'btr_delete_quote'
                    )
                );
                
            default:
                return $item->$column_name;
        }
    }
}

