<?php
/**
 * Database Migration Admin Page
 * 
 * Interfaccia admin per gestire le migrations del database
 * 
 * @package BornToRideBooking
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check permissions
if (!current_user_can('manage_options')) {
    wp_die(__('Non hai i permessi per accedere a questa pagina.', 'born-to-ride-booking'));
}

// Include migration class
require_once BTR_PLUGIN_DIR . 'includes/class-btr-database-migration.php';

// Handle actions
$action = isset($_GET['action']) ? sanitize_text_field($_GET['action']) : '';
$message = '';
$error = '';

if ($action && check_admin_referer('btr_migration_action')) {
    switch ($action) {
        case 'migrate':
            $results = BTR_Database_Migration::run();
            if (!empty($results)) {
                $success_count = count(array_filter($results, function($r) { return $r['success']; }));
                $message = sprintf(__('%d migrations eseguite con successo.', 'born-to-ride-booking'), $success_count);
                
                foreach ($results as $version => $result) {
                    if (!$result['success']) {
                        $error .= sprintf(__('Migration %s fallita: %s', 'born-to-ride-booking'), $version, $result['error']) . '<br>';
                    }
                }
            }
            break;
            
        case 'rollback':
            $target = isset($_GET['target']) ? sanitize_text_field($_GET['target']) : '0.0.0';
            $results = BTR_Database_Migration::rollback($target);
            if (!empty($results)) {
                $success_count = count(array_filter($results, function($r) { return $r['success']; }));
                $message = sprintf(__('%d rollback eseguiti con successo.', 'born-to-ride-booking'), $success_count);
            }
            break;
            
        case 'clean_backups':
            $days = isset($_GET['days']) ? intval($_GET['days']) : 30;
            $count = BTR_Database_Migration::clean_old_backups($days);
            $message = sprintf(__('%d tabelle di backup eliminate.', 'born-to-ride-booking'), $count);
            break;
    }
}

// Get current status
$status = BTR_Database_Migration::get_status();
$history = BTR_Database_Migration::get_history(20);
?>

<div class="wrap">
    <h1><?php echo esc_html__('Database Migration - Born to Ride', 'born-to-ride-booking'); ?></h1>
    
    <?php if ($message): ?>
        <div class="notice notice-success">
            <p><?php echo $message; ?></p>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="notice notice-error">
            <p><?php echo $error; ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Status Box -->
    <div class="card">
        <h2><?php _e('Stato Database', 'born-to-ride-booking'); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php _e('Versione Corrente', 'born-to-ride-booking'); ?></th>
                <td><strong><?php echo esc_html($status['current_version']); ?></strong></td>
            </tr>
            <tr>
                <th><?php _e('Versione Target', 'born-to-ride-booking'); ?></th>
                <td><strong><?php echo esc_html($status['target_version']); ?></strong></td>
            </tr>
            <tr>
                <th><?php _e('Stato', 'born-to-ride-booking'); ?></th>
                <td>
                    <?php if ($status['up_to_date']): ?>
                        <span style="color: green;">✅ <?php _e('Database aggiornato', 'born-to-ride-booking'); ?></span>
                    <?php else: ?>
                        <span style="color: orange;">⚠️ <?php _e('Aggiornamento richiesto', 'born-to-ride-booking'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
    
    <!-- Pending Migrations -->
    <?php if (!empty($status['pending_migrations'])): ?>
        <div class="card">
            <h2><?php _e('Migrations in Attesa', 'born-to-ride-booking'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Versione', 'born-to-ride-booking'); ?></th>
                        <th><?php _e('Descrizione', 'born-to-ride-booking'); ?></th>
                        <th><?php _e('File', 'born-to-ride-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($status['pending_migrations'] as $version => $info): ?>
                        <tr>
                            <td><?php echo esc_html($version); ?></td>
                            <td><?php echo esc_html($info['description']); ?></td>
                            <td><code><?php echo esc_html($info['file']); ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <p style="margin-top: 10px;">
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=btr-database-migration&action=migrate'), 'btr_migration_action'); ?>" 
                   class="button button-primary"
                   onclick="return confirm('<?php esc_attr_e('Sei sicuro di voler eseguire le migrations?', 'born-to-ride-booking'); ?>');">
                    <?php _e('Esegui Migrations', 'born-to-ride-booking'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>
    
    <!-- Executed Migrations -->
    <div class="card">
        <h2><?php _e('Migrations Eseguite', 'born-to-ride-booking'); ?></h2>
        <?php if (!empty($status['executed_migrations'])): ?>
            <ul>
                <?php foreach ($status['executed_migrations'] as $version): ?>
                    <li>✅ Version <?php echo esc_html($version); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p><?php _e('Nessuna migration eseguita.', 'born-to-ride-booking'); ?></p>
        <?php endif; ?>
    </div>
    
    <!-- Migration History -->
    <?php if (!empty($history)): ?>
        <div class="card">
            <h2><?php _e('Cronologia Migrations', 'born-to-ride-booking'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Data', 'born-to-ride-booking'); ?></th>
                        <th><?php _e('Versione', 'born-to-ride-booking'); ?></th>
                        <th><?php _e('Descrizione', 'born-to-ride-booking'); ?></th>
                        <th><?php _e('Stato', 'born-to-ride-booking'); ?></th>
                        <th><?php _e('Errore', 'born-to-ride-booking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $log): ?>
                        <tr>
                            <td><?php echo esc_html($log['executed_at']); ?></td>
                            <td><?php echo esc_html($log['version']); ?></td>
                            <td><?php echo esc_html($log['description']); ?></td>
                            <td>
                                <?php
                                $status_labels = [
                                    'completed' => '<span style="color: green;">✅ Completato</span>',
                                    'failed' => '<span style="color: red;">❌ Fallito</span>',
                                    'rolled_back' => '<span style="color: blue;">↩️ Rolled Back</span>',
                                    'rollback_failed' => '<span style="color: red;">❌ Rollback Fallito</span>'
                                ];
                                echo $status_labels[$log['status']] ?? esc_html($log['status']);
                                ?>
                            </td>
                            <td><?php echo esc_html($log['error_message']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <!-- Tools -->
    <div class="card">
        <h2><?php _e('Strumenti', 'born-to-ride-booking'); ?></h2>
        
        <h3><?php _e('Rollback', 'born-to-ride-booking'); ?></h3>
        <p><?php _e('Esegui rollback a una versione specifica del database.', 'born-to-ride-booking'); ?></p>
        <form method="get" action="">
            <input type="hidden" name="page" value="btr-database-migration">
            <input type="hidden" name="action" value="rollback">
            <?php wp_nonce_field('btr_migration_action'); ?>
            
            <label>
                <?php _e('Versione Target:', 'born-to-ride-booking'); ?>
                <input type="text" name="target" value="0.0.0" size="10">
            </label>
            
            <input type="submit" 
                   class="button" 
                   value="<?php esc_attr_e('Esegui Rollback', 'born-to-ride-booking'); ?>"
                   onclick="return confirm('<?php esc_attr_e('ATTENZIONE: Il rollback eliminerà dati. Continuare?', 'born-to-ride-booking'); ?>');">
        </form>
        
        <h3 style="margin-top: 20px;"><?php _e('Pulizia Backup', 'born-to-ride-booking'); ?></h3>
        <p><?php _e('Elimina tabelle di backup più vecchie di X giorni.', 'born-to-ride-booking'); ?></p>
        <form method="get" action="">
            <input type="hidden" name="page" value="btr-database-migration">
            <input type="hidden" name="action" value="clean_backups">
            <?php wp_nonce_field('btr_migration_action'); ?>
            
            <label>
                <?php _e('Giorni da mantenere:', 'born-to-ride-booking'); ?>
                <input type="number" name="days" value="30" min="1" max="365">
            </label>
            
            <input type="submit" 
                   class="button" 
                   value="<?php esc_attr_e('Pulisci Backup', 'born-to-ride-booking'); ?>">
        </form>
    </div>
    
    <!-- WP-CLI Info -->
    <div class="card">
        <h2><?php _e('Comandi WP-CLI', 'born-to-ride-booking'); ?></h2>
        <p><?php _e('Puoi anche gestire le migrations via WP-CLI:', 'born-to-ride-booking'); ?></p>
        <pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;">
# Esegui migrations
wp btr migrate

# Rollback a versione specifica
wp btr migrate:rollback 1.0.0

# Mostra stato
wp btr migrate:status
        </pre>
    </div>
</div>

<style>
.card {
    background: white;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin-top: 20px;
    margin-bottom: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.card h2 {
    margin-top: 0;
}

.card h3 {
    margin-bottom: 10px;
}

pre {
    white-space: pre-wrap;
    word-wrap: break-word;
}
</style>