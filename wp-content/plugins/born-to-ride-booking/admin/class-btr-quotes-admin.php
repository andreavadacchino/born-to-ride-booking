<?php
class BTR_Quotes_Admin {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'handle_actions']);
    }

    public function add_admin_menu() {
        add_submenu_page(
            'btr-booking',
            'Preventivi Born to Ride',
            'Preventivi',
            'manage_options',
            'btr-quotes',
            [$this, 'render_admin_page']
        );
    }

    public function render_admin_page() {
        require_once plugin_dir_path(__FILE__) . 'admin/class-btr-quotes-list-table.php';
        $quotes_table = new BTR_Quotes_List_Table();
        $quotes_table->prepare_items();
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Preventivi Salvati</h1>
            
            <form method="post">
                <?php $quotes_table->search_box('Cerca preventivi', 'search_id'); ?>
                <?php $quotes_table->display(); ?>
            </form>
        </div>
        <?php
    }

    public function handle_actions() {
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['quote'])) {
            check_admin_referer('btr_delete_quote');
            
            global $wpdb;
            $wpdb->delete(
                $wpdb->prefix . 'btr_quotes',
                ['quote_id' => intval($_GET['quote'])],
                ['%d']
            );
            
            wp_redirect(admin_url('admin.php?page=btr-quotes'));
            exit;
        }
    }
}
