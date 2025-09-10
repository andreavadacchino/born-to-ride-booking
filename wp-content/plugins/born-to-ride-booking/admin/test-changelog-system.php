<?php
/**
 * Test del sistema di lettura CHANGELOG
 * 
 * File di test per verificare il funzionamento del sistema
 * NON includere nella distribuzione
 */

// Carica WordPress
require_once('../../../../../wp-load.php');

// Verifica permessi
if (!current_user_can('manage_options')) {
    wp_die('Non autorizzato');
}

// Test diretto della classe
if (!class_exists('BTR_Changelog_Reader')) {
    require_once 'ajax-changelog-reader.php';
}

$reader = new BTR_Changelog_Reader();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Changelog System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .test-section {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h2 {
            color: #333;
            border-bottom: 2px solid #0073aa;
            padding-bottom: 10px;
        }
        pre {
            background: #f0f0f0;
            padding: 15px;
            border-radius: 4px;
            overflow: auto;
            max-height: 400px;
        }
        .success {
            color: green;
            font-weight: bold;
        }
        .error {
            color: red;
            font-weight: bold;
        }
        ul {
            list-style: none;
            padding: 0;
        }
        li {
            padding: 8px;
            margin: 5px 0;
            background: #f9f9f9;
            border-left: 3px solid #0073aa;
        }
    </style>
</head>
<body>
    <h1>🧪 Test Sistema Lettura CHANGELOG</h1>
    
    <div class="test-section">
        <h2>1. Test Lettura CHANGELOG.md</h2>
        <?php
        try {
            $changes = $reader->get_recent_changes();
            echo '<p class="success">✅ Lettura CHANGELOG completata</p>';
            echo '<p>Trovate ' . count($changes) . ' modifiche recenti:</p>';
            echo '<ul>';
            foreach (array_slice($changes, 0, 5) as $change) {
                echo '<li>';
                echo '<strong>v' . $change['version'] . '</strong> - ' . $change['date'] . '<br>';
                echo htmlspecialchars($change['text']);
                echo ' <em>(' . $change['category'] . ')</em>';
                echo '</li>';
            }
            echo '</ul>';
        } catch (Exception $e) {
            echo '<p class="error">❌ Errore: ' . $e->getMessage() . '</p>';
        }
        ?>
    </div>
    
    <div class="test-section">
        <h2>2. Test Lettura Git Commits</h2>
        <?php
        try {
            $commits = $reader->get_git_commits(10);
            if (!empty($commits)) {
                echo '<p class="success">✅ Lettura Git commits completata</p>';
                echo '<p>Ultimi ' . count($commits) . ' commits:</p>';
                echo '<ul>';
                foreach ($commits as $commit) {
                    echo '<li>' . htmlspecialchars($commit) . '</li>';
                }
                echo '</ul>';
            } else {
                echo '<p>⚠️ Nessun commit Git trovato (potrebbe non essere un repository Git)</p>';
            }
        } catch (Exception $e) {
            echo '<p class="error">❌ Errore: ' . $e->getMessage() . '</p>';
        }
        ?>
    </div>
    
    <div class="test-section">
        <h2>3. Test Combinazione Modifiche</h2>
        <?php
        try {
            $all_changes = $reader->get_all_recent_changes();
            echo '<p class="success">✅ Combinazione modifiche completata</p>';
            echo '<p>Totale modifiche combinate: ' . count($all_changes) . '</p>';
            echo '<pre>' . htmlspecialchars(json_encode($all_changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
        } catch (Exception $e) {
            echo '<p class="error">❌ Errore: ' . $e->getMessage() . '</p>';
        }
        ?>
    </div>
    
    <div class="test-section">
        <h2>4. Test Endpoint AJAX</h2>
        <button onclick="testAjax()">🚀 Test AJAX Endpoint</button>
        <div id="ajax-result" style="margin-top: 20px;"></div>
    </div>
    
    <script>
    function testAjax() {
        const resultDiv = document.getElementById('ajax-result');
        resultDiv.innerHTML = '<p>⏳ Caricamento...</p>';
        
        // Test con jQuery se disponibile
        if (typeof jQuery !== 'undefined') {
            jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                action: 'btr_get_changelog_suggestions',
                nonce: '<?php echo wp_create_nonce('btr_ajax_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    resultDiv.innerHTML = '<p class="success">✅ AJAX funziona!</p>' +
                        '<p>Modifiche ricevute: ' + response.data.count + '</p>' +
                        '<p>Fonte: ' + response.data.source + '</p>' +
                        '<pre>' + JSON.stringify(response.data.changes, null, 2) + '</pre>';
                } else {
                    resultDiv.innerHTML = '<p class="error">❌ Errore AJAX: ' + response.data + '</p>';
                }
            }).fail(function(xhr, status, error) {
                resultDiv.innerHTML = '<p class="error">❌ Errore richiesta: ' + error + '</p>';
            });
        } else {
            resultDiv.innerHTML = '<p class="error">❌ jQuery non disponibile</p>';
        }
    }
    </script>
    
    <div class="test-section">
        <h2>5. Test Endpoint Standalone</h2>
        <button onclick="testStandalone()">🔧 Test Standalone Endpoint</button>
        <div id="standalone-result" style="margin-top: 20px;"></div>
    </div>
    
    <script>
    function testStandalone() {
        const resultDiv = document.getElementById('standalone-result');
        resultDiv.innerHTML = '<p>⏳ Caricamento...</p>';
        
        fetch('ajax-changelog-standalone.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = '<p class="success">✅ Standalone funziona!</p>' +
                        '<p>Modifiche ricevute: ' + data.data.count + '</p>' +
                        '<p>Fonte: ' + data.data.source + '</p>' +
                        '<pre>' + JSON.stringify(data.data.changes, null, 2) + '</pre>';
                } else {
                    resultDiv.innerHTML = '<p class="error">❌ Errore: ' + data.error + '</p>';
                }
            })
            .catch(error => {
                resultDiv.innerHTML = '<p class="error">❌ Errore fetch: ' + error + '</p>';
            });
    }
    </script>
</body>
</html>