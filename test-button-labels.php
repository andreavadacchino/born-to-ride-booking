<?php
/**
 * Test Etichette Pulsanti Assegnazione - v1.0.178
 * Verifica che le etichette nei pulsanti di assegnazione bambini siano corrette
 */
require_once('wp-load.php');

$package_id = 14466;
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Test Etichette Pulsanti v1.0.178</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .success { color: #00a32a; font-weight: bold; }
        .error { color: #d63638; font-weight: bold; }
        .warning { color: #dba617; font-weight: bold; }
        .comparison-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .comparison-table th, .comparison-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        .comparison-table th {
            background: #f0f0f0;
        }
        .code-block {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            font-family: monospace;
            font-size: 14px;
        }
        .fix-note {
            background: #e6f4ea;
            border-left: 4px solid #00a32a;
            padding: 15px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <h1>🧪 Test Etichette Pulsanti Assegnazione Bambini - v1.0.178</h1>
    
    <div class="fix-note">
        <h2>✅ Correzione Implementata (v1.0.178)</h2>
        <p><strong>Problema:</strong> I pulsanti di assegnazione bambini mostravano etichette hardcoded invece di quelle configurate dall'admin.</p>
        <p><strong>Soluzione:</strong> Modificato <code>frontend-scripts.js</code> per usare <code>window.btrDynamicLabelsFromServer</code> quando genera i pulsanti.</p>
    </div>

    <h2>📊 Verifica Etichette Package <?php echo $package_id; ?></h2>
    
    <?php
    // Recupera etichette dal database
    $db_labels = [];
    for ($i = 1; $i <= 4; $i++) {
        $label = get_post_meta($package_id, "btr_bambini_fascia{$i}_label", true);
        $eta_min = get_post_meta($package_id, "btr_bambini_fascia{$i}_eta_min", true);
        $eta_max = get_post_meta($package_id, "btr_bambini_fascia{$i}_eta_max", true);
        $db_labels["f{$i}"] = [
            'label' => $label,
            'min' => $eta_min,
            'max' => $eta_max
        ];
    }
    
    // Recupera etichette dalla funzione helper
    $helper_labels = [];
    if (class_exists('BTR_Preventivi')) {
        $helper_labels = BTR_Preventivi::btr_get_child_age_labels($package_id);
    }
    ?>
    
    <table class="comparison-table">
        <thead>
            <tr>
                <th>Fascia</th>
                <th>Etichetta Database</th>
                <th>Etichetta Helper PHP</th>
                <th>Range Età</th>
                <th>Stato</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($db_labels as $fascia => $data): ?>
            <tr>
                <td><strong><?php echo strtoupper($fascia); ?></strong></td>
                <td><?php echo $data['label']; ?></td>
                <td><?php echo $helper_labels[$fascia] ?? 'N/A'; ?></td>
                <td><?php echo $data['min'] . '-' . $data['max'] . ' anni'; ?></td>
                <td>
                    <?php if ($data['label'] === ($helper_labels[$fascia] ?? '')): ?>
                        <span class="success">✅ MATCH</span>
                    <?php else: ?>
                        <span class="error">❌ MISMATCH</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>📝 Codice JavaScript Aggiornato</h2>
    
    <div class="code-block">
        <h3>Prima (ERRATO - v1.0.160):</h3>
        <pre>// Usava le variabili locali definite PRIMA che arrivassero i dati dal server
childButtonsHtml += `&lt;strong&gt;${labelChildF1}&lt;/strong&gt;`; // "Bambini 3-6 anni"
childButtonsHtml += `&lt;strong&gt;${labelChildF2}&lt;/strong&gt;`; // "Bambini 6-8 anni"</pre>
    </div>
    
    <div class="code-block">
        <h3>Dopo (CORRETTO - v1.0.178):</h3>
        <pre>// Ora usa le etichette dal server quando disponibili
const f1Label = (window.btrDynamicLabelsFromServer && window.btrDynamicLabelsFromServer.f1) || labelChildF1;
const f2Label = (window.btrDynamicLabelsFromServer && window.btrDynamicLabelsFromServer.f2) || labelChildF2;
childButtonsHtml += `&lt;strong&gt;${f1Label}&lt;/strong&gt;`; // "Bambini (3-6)"
childButtonsHtml += `&lt;strong&gt;${f2Label}&lt;/strong&gt;`; // "Bambini (6-12)"</pre>
    </div>

    <h2>🔍 Simulazione Output</h2>
    
    <table class="comparison-table">
        <thead>
            <tr>
                <th>Elemento</th>
                <th>Prima (Hardcoded)</th>
                <th>Dopo (Dinamico)</th>
                <th>Risultato</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Prezzi Camera</strong></td>
                <td>3-6 anni: €111,30</td>
                <td>Bambini (3-6): €111,30</td>
                <td><span class="success">✅ Già corretto</span></td>
            </tr>
            <tr>
                <td><strong>Pulsante F1</strong></td>
                <td>Bambini 3-6 anni [B1]</td>
                <td>Bambini (3-6) [B1]</td>
                <td><span class="success">✅ Ora corretto</span></td>
            </tr>
            <tr>
                <td><strong>Pulsante F2</strong></td>
                <td>Bambini 6-8 anni [B1]</td>
                <td>Bambini (6-12) [B1]</td>
                <td><span class="success">✅ Ora corretto</span></td>
            </tr>
            <tr>
                <td><strong>Pulsante F3</strong></td>
                <td>12-14 [B1]</td>
                <td>Bambini (12-14) [B1]</td>
                <td><span class="success">✅ Già corretto</span></td>
            </tr>
            <tr>
                <td><strong>Pulsante F4</strong></td>
                <td>14-15 [B1]</td>
                <td>Bambini (14-15) [B1]</td>
                <td><span class="success">✅ Già corretto</span></td>
            </tr>
        </tbody>
    </table>

    <h2>📋 File Modificati</h2>
    
    <ul>
        <li><code>frontend-scripts.js</code> - Linee 1924-1925 (F1) e 1947-1948 (F2)</li>
        <li><code>born-to-ride-booking.php</code> - Aggiornata versione a 1.0.178</li>
    </ul>

    <div class="fix-note" style="background: #fff3cd; border-color: #dba617;">
        <h3>⚠️ Test nel Frontend</h3>
        <p>Per verificare completamente la correzione:</p>
        <ol>
            <li>Vai alla pagina del pacchetto nel frontend</li>
            <li>Seleziona adulti e bambini</li>
            <li>Clicca su "Verifica disponibilità"</li>
            <li>Nelle camere che appaiono, verifica che:
                <ul>
                    <li>I prezzi mostrino: "Bambini (3-6)", "Bambini (6-12)", ecc.</li>
                    <li>I pulsanti di assegnazione mostrino le STESSE etichette</li>
                </ul>
            </li>
        </ol>
    </div>

    <script>
    // Simula le etichette come vengono caricate nel frontend
    window.btrDynamicChildLabels = <?php echo json_encode($helper_labels); ?>;
    
    // Simula l'arrivo delle etichette dal server via AJAX
    window.btrDynamicLabelsFromServer = <?php echo json_encode($helper_labels); ?>;
    
    console.log('📝 Etichette caricate:', {
        'btrDynamicChildLabels': window.btrDynamicChildLabels,
        'btrDynamicLabelsFromServer': window.btrDynamicLabelsFromServer
    });
    
    // Test della logica di selezione etichette
    console.log('🧪 Test logica pulsanti:');
    for (let i = 1; i <= 4; i++) {
        const fasciaKey = 'f' + i;
        const fallback = 'Fallback F' + i;
        const serverLabel = window.btrDynamicLabelsFromServer ? window.btrDynamicLabelsFromServer[fasciaKey] : null;
        const finalLabel = serverLabel || fallback;
        
        console.log(`F${i}: Server="${serverLabel}" → Final="${finalLabel}"`);
    }
    </script>

</body>
</html>