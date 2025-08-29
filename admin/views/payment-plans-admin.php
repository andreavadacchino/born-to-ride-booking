<?php
/**
 * Vista amministrativa per la gestione dei piani di pagamento
 * 
 * @package BornToRideBooking
 * @since 1.0.98
 */

if (!defined('ABSPATH')) {
    exit;
}

// Gestione azioni bulk
if (isset($_POST['action']) && $_POST['action'] === 'bulk_send_reminders') {
    check_admin_referer('btr_payment_plans_bulk_action');
    $payment_ids = isset($_POST['payment_ids']) ? array_map('intval', $_POST['payment_ids']) : [];
    
    if (!empty($payment_ids)) {
        $reminders_sent = 0;
        foreach ($payment_ids as $payment_id) {
            if (BTR_Payment_Plans_Admin::send_payment_reminder($payment_id)) {
                $reminders_sent++;
            }
        }
        
        echo '<div class="notice notice-success"><p>';
        printf(
            _n('Inviato %d promemoria di pagamento.', 'Inviati %d promemoria di pagamento.', $reminders_sent, 'born-to-ride-booking'),
            $reminders_sent
        );
        echo '</p></div>';
    }
}

// Filtri
$filter_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
$filter_plan_type = isset($_GET['plan_type']) ? sanitize_text_field($_GET['plan_type']) : '';
$filter_date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';
$search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

// Paginazione
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$per_page = 20;
$offset = ($current_page - 1) * $per_page;

// Query pagamenti
global $wpdb;
$where_clauses = ["1=1"];
$where_values = [];

if ($filter_status) {
    $where_clauses[] = "gp.payment_status = %s";
    $where_values[] = $filter_status;
}

if ($filter_plan_type) {
    $where_clauses[] = "gp.payment_plan_type = %s";
    $where_values[] = $filter_plan_type;
}

if ($filter_date_from) {
    $where_clauses[] = "DATE(gp.created_at) >= %s";
    $where_values[] = $filter_date_from;
}

if ($filter_date_to) {
    $where_clauses[] = "DATE(gp.created_at) <= %s";
    $where_values[] = $filter_date_to;
}

if ($search_query) {
    $where_clauses[] = "(gp.participant_name LIKE %s OR gp.participant_email LIKE %s OR p.post_title LIKE %s)";
    $search_like = '%' . $wpdb->esc_like($search_query) . '%';
    $where_values[] = $search_like;
    $where_values[] = $search_like;
    $where_values[] = $search_like;
}

$where_sql = implode(' AND ', $where_clauses);

// Query totale record
$total_query = "
    SELECT COUNT(DISTINCT gp.payment_id)
    FROM {$wpdb->prefix}btr_group_payments gp
    LEFT JOIN {$wpdb->posts} p ON gp.preventivo_id = p.ID
    WHERE $where_sql
";

$total_items = $wpdb->get_var($wpdb->prepare($total_query, ...$where_values));

// Query pagamenti con JOIN per preventivo
$query = "
    SELECT 
        gp.*,
        p.post_title as preventivo_title,
        pp.plan_type,
        pp.total_amount as plan_total,
        pp.total_participants
    FROM {$wpdb->prefix}btr_group_payments gp
    LEFT JOIN {$wpdb->posts} p ON gp.preventivo_id = p.ID
    LEFT JOIN {$wpdb->prefix}btr_payment_plans pp ON gp.preventivo_id = pp.preventivo_id
    WHERE $where_sql
    ORDER BY gp.created_at DESC
    LIMIT %d OFFSET %d
";

$payments = $wpdb->get_results($wpdb->prepare($query, ...array_merge($where_values, [$per_page, $offset])));

// Statistiche
$stats_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid,
        SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN payment_status = 'failed' THEN 1 ELSE 0 END) as failed,
        SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as total_paid,
        SUM(CASE WHEN payment_status = 'pending' THEN amount ELSE 0 END) as total_pending
    FROM {$wpdb->prefix}btr_group_payments
";
$stats = $wpdb->get_row($stats_query);

// Calcola pagine totali
$total_pages = ceil($total_items / $per_page);
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('Gestione Piani di Pagamento', 'born-to-ride-booking'); ?>
    </h1>
    
    <a href="<?php echo admin_url('admin.php?page=btr-payment-plans-settings'); ?>" class="page-title-action">
        <?php esc_html_e('Impostazioni', 'born-to-ride-booking'); ?>
    </a>
    
    <hr class="wp-header-end">
    
    <!-- Statistiche -->
    <div class="btr-admin-stats">
        <div class="btr-stat-card">
            <h3><?php esc_html_e('Totale Pagamenti', 'born-to-ride-booking'); ?></h3>
            <p class="btr-stat-number"><?php echo number_format($stats->total); ?></p>
        </div>
        
        <div class="btr-stat-card">
            <h3><?php esc_html_e('Pagati', 'born-to-ride-booking'); ?></h3>
            <p class="btr-stat-number success"><?php echo number_format($stats->paid); ?></p>
            <p class="btr-stat-amount"><?php echo btr_format_price_i18n($stats->total_paid); ?></p>
        </div>
        
        <div class="btr-stat-card">
            <h3><?php esc_html_e('In Attesa', 'born-to-ride-booking'); ?></h3>
            <p class="btr-stat-number warning"><?php echo number_format($stats->pending); ?></p>
            <p class="btr-stat-amount"><?php echo btr_format_price_i18n($stats->total_pending); ?></p>
        </div>
        
        <div class="btr-stat-card">
            <h3><?php esc_html_e('Falliti', 'born-to-ride-booking'); ?></h3>
            <p class="btr-stat-number error"><?php echo number_format($stats->failed); ?></p>
        </div>
    </div>
    
    <!-- Filtri -->
    <div class="tablenav top">
        <form method="get" action="">
            <input type="hidden" name="page" value="btr-payment-plans">
            
            <div class="alignleft actions">
                <select name="status" id="filter-status">
                    <option value=""><?php esc_html_e('Tutti gli stati', 'born-to-ride-booking'); ?></option>
                    <option value="pending" <?php selected($filter_status, 'pending'); ?>><?php esc_html_e('In attesa', 'born-to-ride-booking'); ?></option>
                    <option value="paid" <?php selected($filter_status, 'paid'); ?>><?php esc_html_e('Pagato', 'born-to-ride-booking'); ?></option>
                    <option value="failed" <?php selected($filter_status, 'failed'); ?>><?php esc_html_e('Fallito', 'born-to-ride-booking'); ?></option>
                    <option value="expired" <?php selected($filter_status, 'expired'); ?>><?php esc_html_e('Scaduto', 'born-to-ride-booking'); ?></option>
                </select>
                
                <select name="plan_type" id="filter-plan-type">
                    <option value=""><?php esc_html_e('Tutti i piani', 'born-to-ride-booking'); ?></option>
                    <option value="full" <?php selected($filter_plan_type, 'full'); ?>><?php esc_html_e('Pagamento completo', 'born-to-ride-booking'); ?></option>
                    <option value="deposit_balance" <?php selected($filter_plan_type, 'deposit_balance'); ?>><?php esc_html_e('Caparra + Saldo', 'born-to-ride-booking'); ?></option>
                    <option value="group_split" <?php selected($filter_plan_type, 'group_split'); ?>><?php esc_html_e('Suddivisione gruppo', 'born-to-ride-booking'); ?></option>
                </select>
                
                <input type="date" name="date_from" value="<?php echo esc_attr($filter_date_from); ?>" placeholder="<?php esc_attr_e('Dal', 'born-to-ride-booking'); ?>">
                <input type="date" name="date_to" value="<?php echo esc_attr($filter_date_to); ?>" placeholder="<?php esc_attr_e('Al', 'born-to-ride-booking'); ?>">
                
                <input type="submit" class="button" value="<?php esc_attr_e('Filtra', 'born-to-ride-booking'); ?>">
            </div>
            
            <div class="alignleft actions">
                <a href="<?php echo admin_url('admin.php?page=btr-payment-plans&action=export'); ?>" class="button">
                    <?php esc_html_e('Esporta CSV', 'born-to-ride-booking'); ?>
                </a>
            </div>
            
            <p class="search-box">
                <label class="screen-reader-text" for="payment-search"><?php esc_html_e('Cerca pagamenti', 'born-to-ride-booking'); ?></label>
                <input type="search" id="payment-search" name="s" value="<?php echo esc_attr($search_query); ?>">
                <input type="submit" class="button" value="<?php esc_attr_e('Cerca', 'born-to-ride-booking'); ?>">
            </p>
        </form>
    </div>
    
    <!-- Tabella pagamenti -->
    <form method="post" action="">
        <?php wp_nonce_field('btr_payment_plans_bulk_action'); ?>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all">
                    </td>
                    <th scope="col" class="manage-column"><?php esc_html_e('ID', 'born-to-ride-booking'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Preventivo', 'born-to-ride-booking'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Partecipante', 'born-to-ride-booking'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Piano', 'born-to-ride-booking'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Importo', 'born-to-ride-booking'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Stato', 'born-to-ride-booking'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Data', 'born-to-ride-booking'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Azioni', 'born-to-ride-booking'); ?></th>
                </tr>
            </thead>
            
            <tbody>
                <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="9" class="text-center">
                            <?php esc_html_e('Nessun pagamento trovato.', 'born-to-ride-booking'); ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <th scope="row" class="check-column">
                                <input type="checkbox" name="payment_ids[]" value="<?php echo esc_attr($payment->payment_id); ?>">
                            </th>
                            <td><?php echo esc_html($payment->payment_id); ?></td>
                            <td>
                                <a href="<?php echo get_edit_post_link($payment->preventivo_id); ?>">
                                    <?php echo esc_html($payment->preventivo_title ?: 'Preventivo #' . $payment->preventivo_id); ?>
                                </a>
                            </td>
                            <td>
                                <strong><?php echo esc_html($payment->group_member_name ?: $payment->participant_name); ?></strong>
                                <br>
                                <small><?php echo esc_html($payment->participant_email); ?></small>
                            </td>
                            <td>
                                <?php
                                $plan_labels = [
                                    'full' => __('Completo', 'born-to-ride-booking'),
                                    'deposit_balance' => __('Caparra+Saldo', 'born-to-ride-booking'),
                                    'group_split' => __('Gruppo', 'born-to-ride-booking')
                                ];
                                $plan_type = $payment->payment_plan_type ?: 'full';
                                echo esc_html($plan_labels[$plan_type] ?? $plan_type);
                                
                                if ($plan_type === 'group_split' && $payment->share_percentage) {
                                    echo ' <small>(' . esc_html($payment->share_percentage) . '%)</small>';
                                }
                                ?>
                            </td>
                            <td>
                                <strong><?php echo btr_format_price_i18n($payment->amount); ?></strong>
                            </td>
                            <td>
                                <?php
                                $status_labels = [
                                    'pending' => '<span class="btr-status pending">In attesa</span>',
                                    'paid' => '<span class="btr-status paid">Pagato</span>',
                                    'failed' => '<span class="btr-status failed">Fallito</span>',
                                    'expired' => '<span class="btr-status expired">Scaduto</span>'
                                ];
                                echo $status_labels[$payment->payment_status] ?? $payment->payment_status;
                                ?>
                            </td>
                            <td>
                                <?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($payment->created_at))); ?>
                                <?php if ($payment->expires_at && $payment->payment_status === 'pending'): ?>
                                    <br>
                                    <small>
                                        <?php 
                                        echo esc_html__('Scade:', 'born-to-ride-booking') . ' ';
                                        echo esc_html(date_i18n('d/m/Y', strtotime($payment->expires_at))); 
                                        ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <?php if ($payment->payment_status === 'pending'): ?>
                                        <a href="<?php echo home_url('/pagamento-gruppo/' . $payment->payment_hash); ?>" target="_blank">
                                            <?php esc_html_e('Link pagamento', 'born-to-ride-booking'); ?>
                                        </a> |
                                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=btr-payment-plans&action=send_reminder&payment_id=' . $payment->payment_id), 'send_reminder_' . $payment->payment_id); ?>">
                                            <?php esc_html_e('Invia promemoria', 'born-to-ride-booking'); ?>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($payment->wc_order_id): ?>
                                        <a href="<?php echo get_edit_post_link($payment->wc_order_id); ?>">
                                            <?php esc_html_e('Ordine', 'born-to-ride-booking'); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            
            <tfoot>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all-2">
                    </td>
                    <th scope="col" class="manage-column"><?php esc_html_e('ID', 'born-to-ride-booking'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Preventivo', 'born-to-ride-booking'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Partecipante', 'born-to-ride-booking'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Piano', 'born-to-ride-booking'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Importo', 'born-to-ride-booking'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Stato', 'born-to-ride-booking'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Data', 'born-to-ride-booking'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Azioni', 'born-to-ride-booking'); ?></th>
                </tr>
            </tfoot>
        </table>
        
        <!-- Azioni bulk -->
        <div class="tablenav bottom">
            <div class="alignleft actions bulkactions">
                <select name="action">
                    <option value=""><?php esc_html_e('Azioni di massa', 'born-to-ride-booking'); ?></option>
                    <option value="bulk_send_reminders"><?php esc_html_e('Invia promemoria', 'born-to-ride-booking'); ?></option>
                </select>
                <input type="submit" class="button action" value="<?php esc_attr_e('Applica', 'born-to-ride-booking'); ?>">
            </div>
            
            <!-- Paginazione -->
            <?php if ($total_pages > 1): ?>
                <div class="tablenav-pages">
                    <span class="displaying-num">
                        <?php 
                        printf(
                            _n('%s elemento', '%s elementi', $total_items, 'born-to-ride-booking'),
                            number_format_i18n($total_items)
                        ); 
                        ?>
                    </span>
                    
                    <span class="pagination-links">
                        <?php
                        $pagination_args = [
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'total' => $total_pages,
                            'current' => $current_page,
                            'show_all' => false,
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'type' => 'plain'
                        ];
                        
                        echo paginate_links($pagination_args);
                        ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<style>
/* Stili admin */
.btr-admin-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.btr-stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
}

.btr-stat-card h3 {
    margin: 0 0 10px;
    font-size: 14px;
    color: #666;
    font-weight: normal;
}

.btr-stat-number {
    font-size: 32px;
    font-weight: 600;
    margin: 0;
    color: #23282d;
}

.btr-stat-number.success {
    color: #46b450;
}

.btr-stat-number.warning {
    color: #ffb900;
}

.btr-stat-number.error {
    color: #dc3232;
}

.btr-stat-amount {
    font-size: 14px;
    color: #666;
    margin: 5px 0 0;
}

.btr-status {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}

.btr-status.pending {
    background: #fff5e6;
    color: #996800;
}

.btr-status.paid {
    background: #e6f7e6;
    color: #2e7d2e;
}

.btr-status.failed {
    background: #fce6e6;
    color: #a00;
}

.btr-status.expired {
    background: #f0f0f1;
    color: #666;
}

.text-center {
    text-align: center;
}

@media screen and (max-width: 782px) {
    .btr-admin-stats {
        grid-template-columns: 1fr;
    }
}
</style>