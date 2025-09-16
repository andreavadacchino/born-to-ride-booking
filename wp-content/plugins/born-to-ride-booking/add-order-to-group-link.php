<?php
// Script per aggiungere link da ordine WooCommerce a dashboard pagamenti gruppo
// v1.0.239 - Migliora navigazione tra ordine e pagamenti gruppo

$file = __DIR__ . '/includes/class-btr-organizer-dashboard.php';

if (!file_exists($file)) {
    die("ERRORE: File non trovato: $file\n");
}

$content = file_get_contents($file);
if (!$content) {
    die("ERRORE: Impossibile leggere il file\n");
}

// Trova la sezione del constructor
$old_constructor = '        // Enqueue scripts
        add_action(\'wp_enqueue_scripts\', [$this, \'enqueue_scripts\']);
    }';

$new_constructor = '        // Enqueue scripts
        add_action(\'wp_enqueue_scripts\', [$this, \'enqueue_scripts\']);
        
        // Hook ordine WooCommerce per link a pagamenti gruppo
        add_action(\'woocommerce_order_details_after_order_table\', [$this, \'display_group_payment_link\']);
        add_action(\'woocommerce_admin_order_data_after_order_details\', [$this, \'display_admin_group_payment_link\']);
    }';

if (strpos($content, $old_constructor) === false) {
    die("ERRORE: Constructor non trovato nel formato atteso\n");
}

$content = str_replace($old_constructor, $new_constructor, $content);

// Aggiungi i nuovi metodi prima dell'ultima chiusura di classe
$old_class_end = '}

// Inizializza
BTR_Organizer_Dashboard::get_instance();';

$new_class_end = '    
    /**
     * Mostra link a pagamenti gruppo nella pagina ordine frontend
     */
    public function display_group_payment_link($order) {
        // Verifica se è un ordine organizzatore gruppo
        $is_group_organizer = $order->get_meta(\'_btr_is_group_organizer\');
        if ($is_group_organizer !== \'yes\') {
            return;
        }
        
        $preventivo_id = $order->get_meta(\'_btr_preventivo_id\');
        if (!$preventivo_id) {
            return;
        }
        
        // Verifica che l\'utente sia il proprietario dell\'ordine
        if ($order->get_customer_id() != get_current_user_id()) {
            return;
        }
        
        $group_payment_url = add_query_arg(\'payment-group\', $preventivo_id, wc_get_endpoint_url(\'group-payments\'));
        $stats = $this->get_payment_stats($preventivo_id);
        
        ?>
        <div class="btr-group-payment-notice" style="background: #f7f7f7; border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 5px;">
            <h3><?php esc_html_e(\'Pagamento di Gruppo\', \'born-to-ride-booking\'); ?></h3>
            <p>
                <?php esc_html_e(\'Questo ordine è configurato per il pagamento di gruppo.\', \'born-to-ride-booking\'); ?>
                <br>
                <strong><?php echo esc_html(sprintf(
                    __(\'Stato: %d su %d partecipanti hanno pagato (%s%%)\', \'born-to-ride-booking\'),
                    $stats[\'paid_count\'],
                    $stats[\'total_participants\'],
                    $stats[\'completion_percentage\']
                )); ?></strong>
            </p>
            
            <a href="<?php echo esc_url($group_payment_url); ?>" class="button" style="margin-top: 10px;">
                <?php esc_html_e(\'Gestisci Pagamenti Gruppo\', \'born-to-ride-booking\'); ?>
            </a>
        </div>
        <?php
    }
    
    /**
     * Mostra link a pagamenti gruppo nell\'admin ordine
     */
    public function display_admin_group_payment_link($order) {
        // Verifica se è un ordine organizzatore gruppo
        $is_group_organizer = $order->get_meta(\'_btr_is_group_organizer\');
        if ($is_group_organizer !== \'yes\') {
            return;
        }
        
        $preventivo_id = $order->get_meta(\'_btr_preventivo_id\');
        if (!$preventivo_id) {
            return;
        }
        
        $customer_id = $order->get_customer_id();
        $frontend_url = add_query_arg(\'payment-group\', $preventivo_id, 
            wc_get_endpoint_url(\'group-payments\', \'\', wc_get_page_permalink(\'myaccount\')));
        
        ?>
        <div class="order-data-row" style="margin-top: 10px;">
            <p class="form-field form-field-wide">
                <strong><?php esc_html_e(\'Pagamento di Gruppo:\', \'born-to-ride-booking\'); ?></strong>
                <?php esc_html_e(\'Questo è un ordine organizzatore per pagamento di gruppo.\', \'born-to-ride-booking\'); ?>
                <br>
                <span class="description">
                    <?php esc_html_e(\'Preventivo ID:\', \'born-to-ride-booking\'); ?> #<?php echo esc_html($preventivo_id); ?>
                </span>
                <?php if ($customer_id): ?>
                <br>
                <a href="<?php echo esc_url($frontend_url); ?>" target="_blank" class="button button-secondary" style="margin-top: 5px;">
                    <?php esc_html_e(\'Vedi Dashboard Pagamenti Gruppo\', \'born-to-ride-booking\'); ?>
                </a>
                <?php endif; ?>
            </p>
        </div>
        <?php
    }
}

// Inizializza
BTR_Organizer_Dashboard::get_instance();';

$content = str_replace($old_class_end, $new_class_end, $content);

// Scrivi il file aggiornato
if (file_put_contents($file, $content)) {
    echo "✅ File aggiornato con successo!\n";
    echo "✅ Aggiunto link da ordine WooCommerce a dashboard pagamenti gruppo\n";
    echo "✅ Il link appare sia nel frontend che nell'admin\n";
    echo "\n📌 Test:\n";
    echo "1. Frontend: Accedi a http://localhost:10018/mio-account/orders/\n";
    echo "2. Clicca su 'Visualizza' per l'ordine #37504\n";
    echo "3. Vedrai una sezione 'Pagamento di Gruppo' con link alla dashboard\n";
    echo "\n";
    echo "4. Admin: Accedi a /wp-admin/post.php?post=37504&action=edit\n";
    echo "5. Vedrai il link nella sezione dettagli ordine\n";
} else {
    die("ERRORE: Impossibile scrivere il file\n");
}
