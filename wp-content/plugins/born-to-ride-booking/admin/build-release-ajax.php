<?php
/**
 * Build Release tramite AJAX - interfaccia integrata WordPress
 */

// Impedisci accesso diretto
if (!defined('ABSPATH')) {
    exit;
}

// Verifica permessi solo se non siamo in una chiamata AJAX
if (!wp_doing_ajax() && !current_user_can('manage_options')) {
    wp_die('Accesso negato');
}

// Configurazione
$plugin_dir = BTR_PLUGIN_DIR;
$plugin_file_path = $plugin_dir . 'born-to-ride-booking.php';
$changelog_path = $plugin_dir . 'CHANGELOG.md';

// Ottieni versione corrente
$plugin_content = file_get_contents($plugin_file_path);
preg_match('/Version:\s*(.+)/', $plugin_content, $matches);
$current_version = isset($matches[1]) ? trim($matches[1]) : '1.0.0';

// Funzione per recuperare i commit Git recenti
if (!function_exists('get_recent_git_commits')) {
function get_recent_git_commits($limit = 20) {
    $plugin_dir = BTR_PLUGIN_DIR;
    $commits = [];
    
    // Trova la directory .git anche se è in una directory superiore
    $git_dir = $plugin_dir;
    $found_git = false;
    
    // Cerca .git fino a 5 livelli sopra
    for ($i = 0; $i < 5; $i++) {
        if (is_dir($git_dir . '/.git')) {
            $found_git = true;
            break;
        }
        $git_dir = dirname($git_dir);
    }
    
    if ($found_git) {
        // Cambia alla directory git e ottieni il percorso relativo del plugin
        $relative_path = 'wp-content/plugins/born-to-ride-booking';
        
        // Esegui git log per ottenere i commit recenti del plugin
        $command = sprintf(
            'cd %s && git log --oneline --no-merges -n %d --pretty=format:"%%s" -- %s 2>/dev/null',
            escapeshellarg($git_dir),
            $limit,
            escapeshellarg($relative_path)
        );
        
        // SECURITY FIX: exec() disabled for security reasons
        // exec($command, $output, $return_var);
        $return_var = 1; // Force error state
        $output = [];
        
        if ($return_var === 0 && !empty($output)) {
            foreach ($output as $line) {
                // Pulisci e formatta il messaggio
                $message = trim($line);
                
                // Rimuovi prefissi comuni dai commit
                $message = preg_replace('/^(MILESTONE|CRITICAL FIX|URGENT FIX|FIX):\s*/i', '', $message);
                
                // Salta i commit di merge
                if (!empty($message) && stripos($message, 'Merge') !== 0) {
                    $commits[] = $message;
                }
            }
        }
    }
    
    return $commits;
}
}

// Funzione per recuperare le modifiche recenti dai file di documentazione
if (!function_exists('get_recent_changes_from_docs')) {
function get_recent_changes_from_docs() {
    $changes = [];
    $plugin_dir = BTR_PLUGIN_DIR;
    
    // Controlla i file di documentazione modifiche recenti
    $doc_files = [
        'DOCUMENTAZIONE-MODIFICHE-2025-01-11.md',
        'MODIFICHE-DETTAGLIATE-2025-01-10.md'
    ];
    
    foreach ($doc_files as $doc_file) {
        $file_path = $plugin_dir . $doc_file;
        if (file_exists($file_path)) {
            $content = file_get_contents($file_path);
            
            // Estrai le modifiche principali
            if (preg_match_all('/^[-*]\s+(.+)$/m', $content, $matches)) {
                foreach ($matches[1] as $change) {
                    // Pulisci e aggiungi solo modifiche rilevanti
                    $change = strip_tags($change);
                    if (strlen($change) > 10 && strlen($change) < 200) {
                        $changes[] = $change;
                    }
                }
            }
        }
    }
    
    // Aggiungi anche le modifiche hardcoded basate sul lavoro recente
    $recent_work = [
        "Fix critico: Risolto totale checkout duplicato (€1.567,90 → €791,45)",
        "Implementato date picker moderno con calendario italiano",
        "Aggiunto select province con ricerca e navigazione tastiera",
        "Migliorata selezione date di nascita con dropdown mese/anno",
        "Abilitato input manuale date con validazione formato DD/MM/YYYY",
        "Cambiato colore primario da arancione a blu (#0097c5)",
        "Fix duplicazione box info neonati al cambio partecipanti",
        "Fix visualizzazione info culla in tutti i form adulti",
        "Abilitato autocomplete campo telefono",
        "Aggiunto sistema di build release automatizzato",
        "Creato menu sviluppatore nell'admin WordPress",
        "Implementata compilazione automatica changelog da Git/documentazione"
    ];
    
    // Unisci e rimuovi duplicati
    $all_changes = array_unique(array_merge($changes, $recent_work));
    
    return array_slice($all_changes, 0, 10);
}
}

// Le richieste AJAX per suggerimenti sono gestite in class-btr-developer-menu.php
// per evitare problemi di caricamento doppio delle funzioni

// Gestisci richiesta eliminazione build
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'btr_delete_build') {
    check_admin_referer('btr_build_release_nonce');
    
    $filename = sanitize_file_name($_POST['filename']);
    $file_path = BTR_PLUGIN_DIR . 'build/' . $filename;
    
    if (file_exists($file_path) && strpos($filename, '.zip') !== false) {
        if (unlink($file_path)) {
            wp_send_json_success('File eliminato con successo');
        } else {
            wp_send_json_error('Impossibile eliminare il file');
        }
    } else {
        wp_send_json_error('File non trovato');
    }
    exit;
}

// Gestisci richieste AJAX
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'btr_build_release') {
    check_admin_referer('btr_build_release_nonce');
    
    $new_version = sanitize_text_field($_POST['new_version']);
    $changelog_entry = sanitize_textarea_field($_POST['changelog']);
    
    $response = ['success' => false, 'message' => ''];
    
    try {
        // 1. Aggiorna versione nel file principale
        $updated_content = preg_replace(
            '/Version:\s*.+/',
            'Version: ' . $new_version,
            $plugin_content
        );
        
        // Aggiorna anche la costante BTR_VERSION
        $updated_content = preg_replace(
            "/define\(\s*'BTR_VERSION'\s*,\s*'[^']+'\s*\)/",
            "define( 'BTR_VERSION', '$new_version' )",
            $updated_content
        );
        
        file_put_contents($plugin_file_path, $updated_content);
        
        // 2. Aggiorna CHANGELOG.md
        $date = date('Y-m-d');
        $changelog_content = file_exists($changelog_path) ? file_get_contents($changelog_path) : "# Changelog\n\nTutte le modifiche significative al plugin sono documentate in questo file.\n\n";
        
        // Prepara la nuova entry
        $new_entry = "\n## [{$new_version}] - {$date}\n\n";
        $changes = array_filter(array_map('trim', explode("\n", $changelog_entry)));
        foreach ($changes as $change) {
            if ($change) {
                $new_entry .= "- {$change}\n";
            }
        }
        
        // Inserisci dopo il titolo
        $changelog_parts = explode("\n## ", $changelog_content, 2);
        if (count($changelog_parts) > 1) {
            $new_changelog = $changelog_parts[0] . $new_entry . "\n## " . $changelog_parts[1];
        } else {
            $new_changelog = $changelog_content . $new_entry;
        }
        
        file_put_contents($changelog_path, $new_changelog);
        
        // 3. Crea ZIP
        $build_dir = $plugin_dir . 'build';
        if (!is_dir($build_dir)) {
            mkdir($build_dir, 0755, true);
        }
        
        // Includi la logica di creazione ZIP
        $silent_mode = true;
        ob_start();
        include($plugin_dir . 'build-plugin-zip.php');
        ob_end_clean();
        
        $zip_file = $build_dir . '/' . $plugin_name . '-v' . $new_version . '.zip';
        $zip_url = BTR_PLUGIN_URL . 'build/' . $plugin_name . '-v' . $new_version . '.zip';
        
        if (file_exists($zip_file)) {
            $size = formatBytes(filesize($zip_file));
            $response['success'] = true;
            $response['message'] = "Release v{$new_version} creata con successo!";
            $response['zip_url'] = $zip_url;
            $response['zip_size'] = $size;
        } else {
            throw new Exception('Errore nella creazione del file ZIP');
        }
        
    } catch (Exception $e) {
        $response['message'] = 'Errore: ' . $e->getMessage();
    }
    
    wp_send_json($response);
    exit;
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
?>

<div class="wrap">
    <h1>🚀 Build Release - Born to Ride Booking</h1>
    
    <div class="card" style="max-width: 800px;">
        <h2>Versione attuale: <?php echo $current_version; ?></h2>
        
        <form id="build-release-form">
            <?php wp_nonce_field('btr_build_release_nonce', '_wpnonce'); ?>
            <input type="hidden" name="ajax_action" value="btr_build_release">
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="new_version">Nuova versione</label>
                    </th>
                    <td>
                        <input type="text" 
                               id="new_version" 
                               name="new_version" 
                               class="regular-text" 
                               pattern="\d+\.\d+\.\d+" 
                               placeholder="es. 1.0.27"
                               required>
                        <p class="description">Usa semantic versioning (MAJOR.MINOR.PATCH)</p>
                        
                        <div style="margin-top: 10px;">
                            <?php
                            list($major, $minor, $patch) = explode('.', $current_version);
                            $suggestions = [
                                $major . '.' . $minor . '.' . ($patch + 1) => 'Patch (bugfix)',
                                $major . '.' . ($minor + 1) . '.0' => 'Minor (nuove funzionalità)',
                                ($major + 1) . '.0.0' => 'Major (breaking changes)'
                            ];
                            foreach ($suggestions as $version => $label) {
                                echo '<button type="button" class="button button-small version-suggestion" data-version="' . $version . '">';
                                echo $version . ' - ' . $label . '</button> ';
                            }
                            ?>
                        </div>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="changelog">Modifiche</label>
                    </th>
                    <td>
                        <textarea id="changelog" 
                                  name="changelog" 
                                  rows="10" 
                                  cols="50" 
                                  class="large-text"
                                  placeholder="Inserisci una modifica per riga"
                                  required></textarea>
                        <p class="description">Inserisci una modifica per riga. Saranno formattate automaticamente.</p>
                        <p style="margin-top: 10px;">
                            <button type="button" id="load-suggestions" class="button">
                                📋 Carica modifiche recenti
                            </button>
                            <span class="suggestions-spinner spinner" style="float: none;"></span>
                        </p>
                        <div id="suggestions-list" style="display: none; margin-top: 15px; background: #f0f0f0; padding: 15px; border-radius: 5px;">
                            <strong>Seleziona le modifiche da includere:</strong>
                            <div id="suggestions-checkboxes" style="margin-top: 10px; max-height: 300px; overflow-y: auto;"></div>
                            <p style="margin-top: 10px;">
                                <button type="button" id="apply-suggestions" class="button button-primary">
                                    ✅ Applica selezionate
                                </button>
                                <button type="button" id="select-all-suggestions" class="button">
                                    Seleziona tutto
                                </button>
                            </p>
                        </div>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary button-large">
                    🚀 Crea Release
                </button>
                <span class="spinner" style="float: none;"></span>
            </p>
            
            <div id="build-progress" style="display: none; margin-top: 20px;">
                <h4>Creazione release in corso...</h4>
                <div class="progress-wrapper" style="background: #f0f0f0; border-radius: 5px; padding: 3px;">
                    <div class="progress-bar" style="background: #0097c5; height: 30px; border-radius: 3px; width: 0%; transition: width 0.3s ease;">
                        <span class="progress-text" style="display: block; text-align: center; line-height: 30px; color: white; font-weight: bold;">0%</span>
                    </div>
                </div>
                <p class="progress-status" style="margin-top: 10px; font-style: italic;">Inizializzazione...</p>
            </div>
        </form>
        
        <div id="build-result" style="display: none; margin-top: 20px;"></div>
    </div>
    
    <?php if (file_exists($changelog_path)): ?>
    <div class="card" style="max-width: 800px; margin-top: 20px;">
        <h2>📋 Changelog attuale</h2>
        <pre style="background: #f0f0f0; padding: 15px; overflow: auto; max-height: 400px;"><?php 
            echo esc_html(file_get_contents($changelog_path)); 
        ?></pre>
    </div>
    <?php endif; ?>
    
    <?php 
    // Mostra build precedenti
    $build_dir = BTR_PLUGIN_DIR . 'build/';
    if (is_dir($build_dir)): 
        $files = glob($build_dir . '*.zip');
        if ($files):
            // Ordina per data modifica (più recenti prima)
            usort($files, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
    ?>
    <div class="card" style="max-width: 800px; margin-top: 20px;">
        <h2>📦 Build Precedenti</h2>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>File</th>
                    <th>Versione</th>
                    <th>Dimensione</th>
                    <th>Data</th>
                    <th>Azioni</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                foreach (array_slice($files, 0, 10) as $file): 
                    $filename = basename($file);
                    $size = formatBytes(filesize($file));
                    $date = date('d/m/Y H:i', filemtime($file));
                    $url = BTR_PLUGIN_URL . 'build/' . $filename;
                    
                    // Estrai versione dal nome file
                    preg_match('/v(\d+\.\d+\.\d+)/', $filename, $matches);
                    $version = isset($matches[1]) ? $matches[1] : 'N/A';
                ?>
                <tr>
                    <td><code><?php echo esc_html($filename); ?></code></td>
                    <td><?php echo esc_html($version); ?></td>
                    <td><?php echo esc_html($size); ?></td>
                    <td><?php echo esc_html($date); ?></td>
                    <td>
                        <a href="<?php echo esc_url($url); ?>" class="button button-small" download>
                            📥 Scarica
                        </a>
                        <button class="button button-small delete-build" data-file="<?php echo esc_attr($filename); ?>">
                            🗑️ Elimina
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p style="margin-top: 15px;">
            <em>Mostrati gli ultimi 10 build. Totale: <?php echo count($files); ?> file ZIP.</em>
        </p>
    </div>
    <?php 
        endif;
    endif; 
    ?>
</div>

<script>
// Assicuriamoci che ajaxurl sia definito
if (typeof ajaxurl === 'undefined') {
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
}

jQuery(document).ready(function($) {
    // Gestisci suggerimenti versione
    $('.version-suggestion').on('click', function() {
        $('#new_version').val($(this).data('version'));
    });
    
    // Gestisci caricamento modifiche suggerite
    $('#load-suggestions').on('click', function() {
        const $spinner = $('.suggestions-spinner');
        const $suggestionsList = $('#suggestions-list');
        const $checkboxesContainer = $('#suggestions-checkboxes');
        
        $spinner.addClass('is-active');
        
        // Usa il nuovo endpoint per leggere dinamicamente dal CHANGELOG
        $.post(ajaxurl, {
            action: 'btr_get_changelog_suggestions',
            nonce: '<?php echo wp_create_nonce("btr_ajax_nonce"); ?>'
        }, function(response) {
            $spinner.removeClass('is-active');
            
            if (response.success && response.data.changes.length > 0) {
                // Crea checkbox per ogni modifica
                let html = '';
                response.data.changes.forEach(function(change, index) {
                    html += `
                        <label style="display: block; margin-bottom: 8px; padding: 5px; border-radius: 3px;">
                            <input type="checkbox" class="suggestion-checkbox" value="${change.replace(/"/g, '&quot;')}" checked>
                            <span style="margin-left: 5px;">${change}</span>
                        </label>
                    `;
                });
                
                $checkboxesContainer.html(html);
                $suggestionsList.show();
                
                // Mostra info sulla fonte
                if (response.data.source) {
                    $suggestionsList.prepend('<p style="color: #666; font-size: 12px; margin-bottom: 10px;">Modifiche caricate da: ' + response.data.source + '</p>');
                }
            } else {
                alert('Nessuna modifica recente trovata nel CHANGELOG.');
            }
        }).fail(function(xhr, status, error) {
            $spinner.removeClass('is-active');
            console.error('Errore nel caricamento:', error);
            
            // Fallback: prova prima l'endpoint con WordPress
            $.getJSON('<?php echo BTR_PLUGIN_URL; ?>admin/ajax-changelog-reader.php?test=1', function(response) {
                if (response.success && response.data.changes.length > 0) {
                    let html = '';
                    response.data.changes.forEach(function(change, index) {
                        html += `
                            <label style="display: block; margin-bottom: 8px; padding: 5px; border-radius: 3px;">
                                <input type="checkbox" class="suggestion-checkbox" value="${change.replace(/"/g, '&quot;')}" checked>
                                <span style="margin-left: 5px;">${change}</span>
                            </label>
                        `;
                    });
                    
                    $checkboxesContainer.html(html);
                    $suggestionsList.show();
                    
                    // Mostra info sulla fonte
                    $suggestionsList.prepend('<p style="color: #666; font-size: 12px; margin-bottom: 10px;">Modifiche caricate da: ' + (response.data.source || 'CHANGELOG.md') + '</p>');
                } else {
                    // Secondo fallback: prova standalone
                    $.getJSON('<?php echo BTR_PLUGIN_URL; ?>admin/ajax-changelog-standalone.php', function(response2) {
                        if (response2.success && response2.data.changes.length > 0) {
                            let html = '';
                            response2.data.changes.forEach(function(change, index) {
                                html += `
                                    <label style="display: block; margin-bottom: 8px; padding: 5px; border-radius: 3px;">
                                        <input type="checkbox" class="suggestion-checkbox" value="${change.replace(/"/g, '&quot;')}" checked>
                                        <span style="margin-left: 5px;">${change}</span>
                                    </label>
                                `;
                            });
                            
                            $checkboxesContainer.html(html);
                            $suggestionsList.show();
                            $suggestionsList.prepend('<p style="color: #666; font-size: 12px; margin-bottom: 10px;">Modifiche caricate da: CHANGELOG.md (standalone)</p>');
                        } else {
                            alert('Impossibile caricare le modifiche dal CHANGELOG. Verifica che il file esista.');
                        }
                    });
                }
            });
        });
    });
    
    // Gestisci selezione/deselezione tutto
    $('#select-all-suggestions').on('click', function() {
        const allChecked = $('.suggestion-checkbox:checked').length === $('.suggestion-checkbox').length;
        $('.suggestion-checkbox').prop('checked', !allChecked);
        $(this).text(allChecked ? 'Seleziona tutto' : 'Deseleziona tutto');
    });
    
    // Gestisci applicazione suggerimenti
    $('#apply-suggestions').on('click', function() {
        const selectedChanges = [];
        $('.suggestion-checkbox:checked').each(function() {
            selectedChanges.push($(this).val());
        });
        
        if (selectedChanges.length > 0) {
            // Aggiungi al textarea
            const currentContent = $('#changelog').val();
            const newContent = currentContent ? 
                currentContent + '\n' + selectedChanges.join('\n') : 
                selectedChanges.join('\n');
            
            $('#changelog').val(newContent);
            $('#suggestions-list').hide();
        } else {
            alert('Seleziona almeno una modifica.');
        }
    });
    
    // Funzione per aggiornare la barra di progresso
    function updateProgress(percent, status) {
        const $progress = $('#build-progress');
        const $bar = $progress.find('.progress-bar');
        const $text = $progress.find('.progress-text');
        const $status = $progress.find('.progress-status');
        
        $bar.css('width', percent + '%');
        $text.text(percent + '%');
        $status.text(status);
    }
    
    // Gestisci submit form
    $('#build-release-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $spinner = $form.find('.spinner');
        const $result = $('#build-result');
        const $submit = $form.find('button[type="submit"]');
        const $progress = $('#build-progress');
        
        // Mostra spinner e progress
        $spinner.addClass('is-active');
        $submit.prop('disabled', true);
        $result.hide();
        $progress.show();
        
        // Simula progresso durante la richiesta
        updateProgress(10, 'Aggiornamento versione...');
        
        setTimeout(() => updateProgress(30, 'Aggiornamento changelog...'), 500);
        setTimeout(() => updateProgress(50, 'Creazione archivio ZIP...'), 1000);
        setTimeout(() => updateProgress(70, 'Compressione file...'), 1500);
        
        // Invia richiesta AJAX
        $.post(ajaxurl, $form.serialize() + '&action=btr_build_release_ajax', function(response) {
            // Completa progresso
            updateProgress(90, 'Finalizzazione...');
            
            setTimeout(() => {
                updateProgress(100, 'Completato!');
                
                $spinner.removeClass('is-active');
                $submit.prop('disabled', false);
                
                if (response.success) {
                    $result.html(`
                        <div class="notice notice-success">
                            <p><strong>✅ ${response.message}</strong></p>
                            <p>📦 Dimensione: ${response.zip_size}</p>
                            <p>
                                <a href="${response.zip_url}" class="button button-primary" download>
                                    📥 Scarica ${response.zip_url.split('/').pop()}
                                </a>
                            </p>
                        </div>
                    `).show();
                    
                    // Nascondi progress bar dopo successo
                    setTimeout(() => {
                        $progress.fadeOut();
                        // Reset form
                        $form[0].reset();
                    }, 1000);
                    
                    // Ricarica pagina dopo 5 secondi per aggiornare versione
                    setTimeout(function() {
                        location.reload();
                    }, 5000);
                } else {
                    $progress.hide();
                    $result.html(`
                        <div class="notice notice-error">
                            <p><strong>❌ ${response.message}</strong></p>
                        </div>
                    `).show();
                }
            }, 500);
        }).fail(function(xhr, status, error) {
            $spinner.removeClass('is-active');
            $submit.prop('disabled', false);
            $progress.hide();
            
            $result.html(`
                <div class="notice notice-error">
                    <p><strong>❌ Errore durante la creazione della release</strong></p>
                    <p>${error}</p>
                </div>
            `).show();
        });
    });
    
    // Gestisci eliminazione build
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
            _wpnonce: $('#_wpnonce').val()
        }, function(response) {
            if (response.success) {
                $row.fadeOut(400, function() {
                    $(this).remove();
                });
            } else {
                alert('Errore durante l\'eliminazione: ' + response.data);
                $button.prop('disabled', false).text('🗑️ Elimina');
            }
        });
    });
});
</script>

<style>
/* Stili per la barra di progresso */
#build-progress {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

#build-progress h4 {
    margin-top: 0;
    color: #333;
}

.progress-wrapper {
    position: relative;
    overflow: hidden;
}

.progress-bar {
    background: linear-gradient(90deg, #0097c5 0%, #00b5e5 100%);
    box-shadow: 0 2px 4px rgba(0,151,197,0.3);
    position: relative;
    overflow: hidden;
}

.progress-bar::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(
        45deg,
        rgba(255,255,255,0.1) 25%,
        transparent 25%,
        transparent 50%,
        rgba(255,255,255,0.1) 50%,
        rgba(255,255,255,0.1) 75%,
        transparent 75%,
        transparent
    );
    background-size: 20px 20px;
    animation: progress-stripes 0.5s linear infinite;
}

@keyframes progress-stripes {
    0% {
        background-position: 0 0;
    }
    100% {
        background-position: 20px 0;
    }
}

.progress-text {
    position: relative;
    z-index: 1;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
}

.progress-status {
    color: #666;
    animation: pulse 1.5s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.5;
    }
}

/* Animazione successo */
.progress-bar[style*="width: 100%"] {
    background: linear-gradient(90deg, #28a745 0%, #34ce57 100%);
}

.progress-bar[style*="width: 100%"]::after {
    animation: none;
    background: none;
}

/* Stili per le checkbox dei suggerimenti */
#suggestions-list {
    max-height: 400px;
    overflow-y: auto;
}

#suggestions-checkboxes label {
    transition: background-color 0.2s ease;
    cursor: pointer;
}

#suggestions-checkboxes label:hover {
    background-color: #e8f4f8;
}

#suggestions-checkboxes input[type="checkbox"] {
    margin-right: 8px;
}

#suggestions-checkboxes input[type="checkbox"]:checked + span {
    font-weight: 500;
}

/* Animazione per il caricamento */
.suggestions-spinner.is-active {
    visibility: visible;
}

/* Badge per la fonte */
#suggestions-list > p:first-child {
    background: #f0f0f0;
    padding: 5px 10px;
    border-radius: 3px;
    display: inline-block;
}
</style>