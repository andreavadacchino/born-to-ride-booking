<?php
/**
 * Interfaccia amministrativa per gestione update database
 * 
 * @package BornToRideBooking
 * @subpackage Admin
 * @since 1.0.98
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verifica permessi
if (!current_user_can('manage_options')) {
    wp_die(__('Non hai i permessi necessari per accedere a questa pagina.', 'born-to-ride-booking'));
}

// Include updater
require_once BTR_PLUGIN_DIR . 'includes/class-btr-database-updater.php';
$updater = new BTR_Database_Updater();

// Gestione azioni
$message = '';
$message_type = '';

if (isset($_POST['action']) && isset($_POST['_wpnonce'])) {
    if ($_POST['action'] === 'run_updates' && wp_verify_nonce($_POST['_wpnonce'], 'btr_run_db_updates')) {
        try {
            $result = $updater->check_and_run_updates();
            if ($result) {
                $message = __('Update del database completati con successo!', 'born-to-ride-booking');
                $message_type = 'success';
            } else {
                $message = __('Nessun update da eseguire o update già in corso.', 'born-to-ride-booking');
                $message_type = 'info';
            }
        } catch (Exception $e) {
            $message = sprintf(__('Errore durante l\'update: %s', 'born-to-ride-booking'), $e->getMessage());
            $message_type = 'error';
        }
    } elseif ($_POST['action'] === 'clear_failed' && wp_verify_nonce($_POST['_wpnonce'], 'btr_clear_failed_updates')) {
        $updater->clear_failed_updates();
        $message = __('Update falliti rimossi con successo.', 'born-to-ride-booking');
        $message_type = 'success';
    }
}

// Ottieni dati
$current_version = get_option('btr_db_version', '0');
$pending_updates = $updater->get_pending_updates();
$failed_updates = $updater->get_failed_updates();
$update_log = $updater->get_update_log(50);
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php if ($message): ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Stato Database -->
    <div class="card">
        <h2><?php esc_html_e('Stato Database', 'born-to-ride-booking'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e('Versione Plugin', 'born-to-ride-booking'); ?></th>
                <td><strong><?php echo BTR_VERSION; ?></strong></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Versione Database', 'born-to-ride-booking'); ?></th>
                <td><strong><?php echo esc_html($current_version); ?></strong></td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Update Pendenti', 'born-to-ride-booking'); ?></th>
                <td>
                    <?php if (empty($pending_updates)): ?>
                        <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                        <?php esc_html_e('Nessuno', 'born-to-ride-booking'); ?>
                    <?php else: ?>
                        <span class="dashicons dashicons-warning" style="color: #ffb900;"></span>
                        <strong><?php echo count($pending_updates); ?></strong>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
    
    <!-- Update Pendenti -->
    <?php if (!empty($pending_updates)): ?>
    <div class="card">
        <h2><?php esc_html_e('Update Pendenti', 'born-to-ride-booking'); ?></h2>
        <div class="notice notice-warning inline">
            <p><?php esc_html_e('ATTENZIONE: Esegui sempre un backup completo del database prima di procedere con gli update!', 'born-to-ride-booking'); ?></p>
        </div>
        
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Versione', 'born-to-ride-booking'); ?></th>
                    <th><?php esc_html_e('File', 'born-to-ride-booking'); ?></th>
                    <th><?php esc_html_e('Descrizione', 'born-to-ride-booking'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pending_updates as $update): ?>
                <tr>
                    <td><strong><?php echo esc_html($update['version']); ?></strong></td>
                    <td><code><?php echo esc_html($update['file']); ?></code></td>
                    <td>
                        <?php 
                        // Descrizione specifica per versione
                        switch($update['version']) {
                            case '1.0.98':
                                echo esc_html__('Aggiunge supporto per piani di pagamento estesi (caparra, gruppo)', 'born-to-ride-booking');
                                break;
                            default:
                                echo esc_html__('Update database', 'born-to-ride-booking');
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <form method="post" style="margin-top: 20px;">
            <?php wp_nonce_field('btr_run_db_updates'); ?>
            <input type="hidden" name="action" value="run_updates">
            <button type="submit" class="button button-primary" onclick="return confirm('<?php esc_attr_e('Sei sicuro di voler eseguire gli update del database? Assicurati di aver fatto un backup!', 'born-to-ride-booking'); ?>')">
                <?php esc_html_e('Esegui Update Database', 'born-to-ride-booking'); ?>
            </button>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- Update Falliti -->
    <?php if (!empty($failed_updates)): ?>
    <div class="card">
        <h2><?php esc_html_e('Update Falliti', 'born-to-ride-booking'); ?></h2>
        <div class="notice notice-error inline">
            <p><?php esc_html_e('Gli update seguenti sono falliti. Controlla i log per maggiori dettagli.', 'born-to-ride-booking'); ?></p>
        </div>
        
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Versione', 'born-to-ride-booking'); ?></th>
                    <th><?php esc_html_e('Errore', 'born-to-ride-booking'); ?></th>
                    <th><?php esc_html_e('Data', 'born-to-ride-booking'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($failed_updates as $version => $data): ?>
                <tr>
                    <td><strong><?php echo esc_html($version); ?></strong></td>
                    <td><?php echo esc_html($data['error']); ?></td>
                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $data['timestamp'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <form method="post" style="margin-top: 20px;">
            <?php wp_nonce_field('btr_clear_failed_updates'); ?>
            <input type="hidden" name="action" value="clear_failed">
            <button type="submit" class="button">
                <?php esc_html_e('Rimuovi Update Falliti', 'born-to-ride-booking'); ?>
            </button>
        </form>
    </div>
    <?php endif; ?>
    
    <!-- Log Update -->
    <div class="card">
        <h2><?php esc_html_e('Log Update Database', 'born-to-ride-booking'); ?></h2>
        
        <?php if (empty($update_log)): ?>
            <p><?php esc_html_e('Nessun log disponibile.', 'born-to-ride-booking'); ?></p>
        <?php else: ?>
            <div style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">
                <?php foreach (array_reverse($update_log) as $entry): ?>
                    <?php
                    $icon = '';
                    $color = '';
                    switch($entry['type']) {
                        case 'error':
                            $icon = 'dismiss';
                            $color = '#dc3232';
                            break;
                        case 'warning':
                            $icon = 'warning';
                            $color = '#ffb900';
                            break;
                        case 'success':
                            $icon = 'yes-alt';
                            $color = '#46b450';
                            break;
                        default:
                            $icon = 'info';
                            $color = '#00a0d2';
                    }
                    ?>
                    <div style="margin-bottom: 10px; padding: 10px; background: white; border-left: 3px solid <?php echo $color; ?>;">
                        <span class="dashicons dashicons-<?php echo $icon; ?>" style="color: <?php echo $color; ?>; margin-right: 5px;"></span>
                        <strong><?php echo esc_html($entry['timestamp']); ?></strong> - 
                        <?php echo esc_html($entry['action']); ?>
                        (v<?php echo esc_html($entry['version']); ?>)
                        
                        <?php if (!empty($entry['data'])): ?>
                            <div style="margin-top: 5px; padding-left: 25px;">
                                <pre style="background: #f4f4f4; padding: 5px; font-size: 12px;"><?php 
                                    echo esc_html(json_encode($entry['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); 
                                ?></pre>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Informazioni Debug -->
    <?php if (defined('BTR_DEBUG') && BTR_DEBUG): ?>
    <div class="card">
        <h2><?php esc_html_e('Informazioni Debug', 'born-to-ride-booking'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row">PHP Memory Limit</th>
                <td><?php echo ini_get('memory_limit'); ?></td>
            </tr>
            <tr>
                <th scope="row">Max Execution Time</th>
                <td><?php echo ini_get('max_execution_time'); ?> secondi</td>
            </tr>
            <tr>
                <th scope="row">WordPress Memory Limit</th>
                <td><?php echo WP_MEMORY_LIMIT; ?></td>
            </tr>
            <tr>
                <th scope="row">Lock Status</th>
                <td>
                    <?php if (get_transient('btr_db_updating')): ?>
                        <span style="color: #dc3232;">LOCKED - Update in corso</span>
                    <?php else: ?>
                        <span style="color: #46b450;">UNLOCKED - Pronto</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
    <?php endif; ?>
    
</div>

<style>
.card {
    background: #fff;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    margin-top: 20px;
    padding: 20px;
}
.card h2 {
    margin-top: 0;
}
.form-table th {
    width: 200px;
}
</style>