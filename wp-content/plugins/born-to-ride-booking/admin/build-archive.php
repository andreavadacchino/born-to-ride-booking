<?php
/**
 * Archivio completo dei build del plugin
 */

// Impedisci accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Verifica permessi
if (!current_user_can('manage_options')) {
    wp_die('Accesso negato');
}

$build_dir = BTR_PLUGIN_DIR . 'build/';
$files = [];

if (is_dir($build_dir)) {
    $files = glob($build_dir . '*.zip');
    // Ordina per data modifica (più recenti prima)
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
}

// Helper function
if (!function_exists('formatBytes')) {
    function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

// Calcola statistiche
$total_size = 0;
$versions = [];
foreach ($files as $file) {
    $total_size += filesize($file);
    preg_match('/v(\d+\.\d+\.\d+)/', basename($file), $matches);
    if (isset($matches[1])) {
        $versions[] = $matches[1];
    }
}
?>

<div class="wrap">
    <h1>📦 Archivio Build - Born to Ride Booking</h1>
    
    <?php if (!empty($files)): ?>
    
    <div class="card" style="margin-bottom: 20px;">
        <h2>📊 Statistiche</h2>
        <p>
            <strong>Build totali:</strong> <?php echo count($files); ?> |
            <strong>Spazio occupato:</strong> <?php echo formatBytes($total_size); ?> |
            <strong>Ultima versione:</strong> <?php echo !empty($versions) ? $versions[0] : 'N/A'; ?> |
            <strong>Prima versione:</strong> <?php echo !empty($versions) ? end($versions) : 'N/A'; ?>
        </p>
    </div>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th style="width: 30%;">File</th>
                <th style="width: 10%;">Versione</th>
                <th style="width: 10%;">Dimensione</th>
                <th style="width: 20%;">Data Creazione</th>
                <th style="width: 15%;">Hash MD5</th>
                <th style="width: 15%;">Azioni</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($files as $file): 
                $filename = basename($file);
                $size = formatBytes(filesize($file));
                $date = date('d/m/Y H:i:s', filemtime($file));
                $url = BTR_PLUGIN_URL . 'build/' . $filename;
                $md5 = md5_file($file);
                
                // Estrai versione dal nome file
                preg_match('/v(\d+\.\d+\.\d+)/', $filename, $matches);
                $version = isset($matches[1]) ? $matches[1] : 'N/A';
            ?>
            <tr>
                <td>
                    <code style="font-size: 13px;"><?php echo esc_html($filename); ?></code>
                </td>
                <td>
                    <strong><?php echo esc_html($version); ?></strong>
                </td>
                <td><?php echo esc_html($size); ?></td>
                <td><?php echo esc_html($date); ?></td>
                <td>
                    <code style="font-size: 11px;" title="<?php echo esc_attr($md5); ?>">
                        <?php echo substr($md5, 0, 8) . '...'; ?>
                    </code>
                </td>
                <td>
                    <a href="<?php echo esc_url($url); ?>" class="button button-small button-primary" download>
                        📥 Scarica
                    </a>
                    <button class="button button-small button-link-delete delete-build" data-file="<?php echo esc_attr($filename); ?>">
                        Elimina
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div style="margin-top: 20px;">
        <button id="delete-old-builds" class="button">
            🗑️ Elimina build più vecchi di 30 giorni
        </button>
        <button id="delete-all-but-latest" class="button">
            🧹 Mantieni solo gli ultimi 5 build
        </button>
    </div>
    
    <?php else: ?>
    
    <div class="notice notice-info">
        <p>Nessun build trovato. Crea il tuo primo build dalla pagina <a href="<?php echo admin_url('admin.php?page=btr-build-release'); ?>">Build Release</a>.</p>
    </div>
    
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    // Gestisci eliminazione singolo build
    $('.delete-build').on('click', function() {
        if (!confirm('Sei sicuro di voler eliminare questo file?')) {
            return;
        }
        
        const $button = $(this);
        const filename = $button.data('file');
        const $row = $button.closest('tr');
        
        $button.prop('disabled', true).text('Eliminazione...');
        
        $.post(ajaxurl, {
            action: 'btr_build_release_ajax',
            ajax_action: 'btr_delete_build',
            filename: filename,
            _wpnonce: '<?php echo wp_create_nonce('btr_build_release_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                $row.fadeOut(400, function() {
                    $(this).remove();
                    // Se non ci sono più righe, ricarica la pagina
                    if ($('tbody tr').length === 0) {
                        location.reload();
                    }
                });
            } else {
                alert('Errore durante l\'eliminazione: ' + response.data);
                $button.prop('disabled', false).text('Elimina');
            }
        });
    });
    
    // Elimina build vecchi
    $('#delete-old-builds').on('click', function() {
        if (!confirm('Eliminare tutti i build più vecchi di 30 giorni?')) {
            return;
        }
        
        const $button = $(this);
        $button.prop('disabled', true).text('Eliminazione in corso...');
        
        // Qui servirebbe un'azione AJAX dedicata per eliminare i vecchi build
        alert('Funzione in sviluppo');
        $button.prop('disabled', false).text('🗑️ Elimina build più vecchi di 30 giorni');
    });
    
    // Mantieni solo ultimi 5
    $('#delete-all-but-latest').on('click', function() {
        if (!confirm('Mantenere solo gli ultimi 5 build?')) {
            return;
        }
        
        const $button = $(this);
        $button.prop('disabled', true).text('Pulizia in corso...');
        
        // Qui servirebbe un'azione AJAX dedicata
        alert('Funzione in sviluppo');
        $button.prop('disabled', false).text('🧹 Mantieni solo gli ultimi 5 build');
    });
});
</script>