<?php
/**
 * Test Completo Flusso Prenotazione - Etichette Dinamiche v1.0.160
 * 
 * Verifica che le etichette delle fasce età bambini siano corrette
 * in tutto il flusso di prenotazione
 */
require_once('wp-load.php');

// Package di test
$package_id = 14466;
$product_id = get_post_meta($package_id, '_btr_product_id', true);

?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Test Completo Etichette - Flusso Prenotazione</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .test-section { 
            margin: 30px 0; 
            padding: 20px; 
            border: 2px solid #ddd; 
            border-radius: 8px;
            background: #f9f9f9;
        }
        .test-step {
            margin: 15px 0;
            padding: 15px;
            background: white;
            border-left: 4px solid #007cba;
        }
        .success { 
            color: #00a32a; 
            font-weight: bold; 
        }
        .error { 
            color: #d63638; 
            font-weight: bold; 
        }
        .warning {
            color: #dba617;
            font-weight: bold;
        }
        pre { 
            background: #f0f0f0; 
            padding: 10px; 
            overflow-x: auto;
            border-radius: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        th {
            background: #f0f0f0;
        }
        .label-comparison {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        .label-box {
            padding: 15px;
            border-radius: 8px;
        }
        .label-box.correct {
            background: #e6f4ea;
            border: 2px solid #00a32a;
        }
        .label-box.incorrect {
            background: #fce8e6;
            border: 2px solid #d63638;
        }
        .progress-bar {
            width: 100%;
            height: 30px;
            background: #e0e0e0;
            border-radius: 15px;
            overflow: hidden;
            margin: 20px 0;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #00a32a, #007cba);
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <h1>🧪 Test Completo Etichette Bambini - Flusso Prenotazione</h1>
    <p><strong>Package ID:</strong> <?php echo $package_id; ?> | <strong>Product ID:</strong> <?php echo $product_id; ?></p>
    
    <div class="progress-bar">
        <div class="progress-fill" id="progress" style="width: 0%">0%</div>
    </div>

    <?php
    $total_tests = 0;
    $passed_tests = 0;
    
    // Funzione helper per aggiornare progress
    function update_progress($current, $total) {
        $percent = round(($current / $total) * 100);
        echo "<script>document.getElementById('progress').style.width = '{$percent}%'; document.getElementById('progress').textContent = '{$percent}%';</script>";
        flush();
    }
    ?>

    <!-- STEP 1: Configurazione Database -->
    <div class="test-section">
        <h2>📊 Step 1: Verifica Configurazione Database</h2>
        
        <?php
        $total_tests += 4;
        
        echo "<div class='test-step'>";
        echo "<h3>Etichette configurate nel package $package_id:</h3>";
        echo "<table>";
        echo "<tr><th>Fascia</th><th>Etichetta Admin</th><th>Range Età</th><th>Stato</th></tr>";
        
        $expected_labels = [
            'f1' => 'Bambini (3-6)',
            'f2' => 'Bambini (6-12)',
            'f3' => 'Bambini (12-14)',
            'f4' => 'Bambini (14-15)'
        ];
        
        for ($i = 1; $i <= 4; $i++) {
            $label = get_post_meta($package_id, "btr_bambini_fascia{$i}_label", true);
            $eta_min = get_post_meta($package_id, "btr_bambini_fascia{$i}_eta_min", true);
            $eta_max = get_post_meta($package_id, "btr_bambini_fascia{$i}_eta_max", true);
            $expected = $expected_labels["f{$i}"];
            
            $is_correct = ($label === $expected);
            if ($is_correct) $passed_tests++;
            
            $status = $is_correct ? 
                "<span class='success'>✅ CORRETTO</span>" : 
                "<span class='error'>❌ ERRATO (atteso: $expected)</span>";
            
            echo "<tr>";
            echo "<td>F{$i}</td>";
            echo "<td>$label</td>";
            echo "<td>{$eta_min}-{$eta_max} anni</td>";
            echo "<td>$status</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "</div>";
        
        update_progress($passed_tests, 20);
        ?>
    </div>

    <!-- STEP 2: Helper Function PHP -->
    <div class="test-section">
        <h2>🔧 Step 2: Verifica Helper Function PHP</h2>
        
        <?php
        $total_tests += 4;
        
        echo "<div class='test-step'>";
        echo "<h3>BTR_Preventivi::btr_get_child_age_labels():</h3>";
        
        if (class_exists('BTR_Preventivi')) {
            $labels = BTR_Preventivi::btr_get_child_age_labels($package_id);
            
            echo "<table>";
            echo "<tr><th>Fascia</th><th>Etichetta Helper</th><th>Etichetta Attesa</th><th>Stato</th></tr>";
            
            foreach ($expected_labels as $fascia => $expected) {
                $actual = $labels[$fascia] ?? 'NON TROVATA';
                $is_correct = ($actual === $expected);
                if ($is_correct) $passed_tests++;
                
                $status = $is_correct ? 
                    "<span class='success'>✅ MATCH</span>" : 
                    "<span class='error'>❌ MISMATCH</span>";
                
                echo "<tr>";
                echo "<td>" . strtoupper($fascia) . "</td>";
                echo "<td>$actual</td>";
                echo "<td>$expected</td>";
                echo "<td>$status</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='error'>❌ Classe BTR_Preventivi non trovata!</p>";
        }
        
        echo "</div>";
        update_progress($passed_tests, 20);
        ?>
    </div>

    <!-- STEP 3: AJAX Response -->
    <div class="test-section">
        <h2>🌐 Step 3: Verifica Risposta AJAX get_rooms()</h2>
        
        <?php
        $total_tests += 4;
        
        echo "<div class='test-step'>";
        echo "<h3>Simulazione risposta AJAX:</h3>";
        
        // Simula la costruzione di child_fasce come in get_rooms()
        $child_fasce = array();
        $dynamic_labels = BTR_Preventivi::btr_get_child_age_labels($package_id);
        
        for ($i = 1; $i <= 4; $i++) {
            if (get_post_meta($package_id, "btr_bambini_fascia{$i}_sconto_enabled", true) !== '1') {
                continue;
            }
            
            $eta_min = get_post_meta($package_id, "btr_bambini_fascia{$i}_eta_min", true);
            $eta_max = get_post_meta($package_id, "btr_bambini_fascia{$i}_eta_max", true);
            
            $fascia_key = 'f' . $i;
            $display_label = isset($dynamic_labels[$fascia_key]) ? $dynamic_labels[$fascia_key] : "Bambini ({$eta_min}–{$eta_max})";
            
            $child_fasce[] = array(
                'id'       => $i,
                'label'    => $display_label,
                'age_min'  => (int) $eta_min,
                'age_max'  => (int) $eta_max,
                'discount' => (float) get_post_meta($package_id, "btr_bambini_fascia{$i}_sconto", true),
            );
        }
        
        echo "<h4>Array child_fasce (JSON):</h4>";
        echo "<pre>" . htmlspecialchars(json_encode($child_fasce, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
        
        // Verifica etichette
        echo "<h4>Verifica etichette nell'array:</h4>";
        echo "<table>";
        echo "<tr><th>Fascia ID</th><th>Label in AJAX</th><th>Atteso</th><th>Stato</th></tr>";
        
        foreach ($child_fasce as $fascia) {
            $expected = $expected_labels['f' . $fascia['id']];
            $is_correct = ($fascia['label'] === $expected);
            if ($is_correct) $passed_tests++;
            
            $status = $is_correct ? 
                "<span class='success'>✅ CORRETTO</span>" : 
                "<span class='error'>❌ ERRATO</span>";
            
            echo "<tr>";
            echo "<td>{$fascia['id']}</td>";
            echo "<td>{$fascia['label']}</td>";
            echo "<td>$expected</td>";
            echo "<td>$status</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "</div>";
        update_progress($passed_tests, 20);
        ?>
    </div>

    <!-- STEP 4: JavaScript Integration -->
    <div class="test-section">
        <h2>📱 Step 4: Verifica Integrazione JavaScript</h2>
        
        <div class="test-step">
            <h3>window.btrDynamicChildLabels:</h3>
            <div id="js-test-results"></div>
        </div>
        
        <script>
        // Simula il caricamento delle etichette come fa class-btr-shortcodes.php
        window.btrDynamicChildLabels = <?php echo json_encode($dynamic_labels); ?>;
        
        // Test JavaScript
        (function() {
            const testResults = document.getElementById('js-test-results');
            const expectedLabels = <?php echo json_encode($expected_labels); ?>;
            
            let html = '<h4>Etichette caricate in JavaScript:</h4>';
            html += '<table>';
            html += '<tr><th>Fascia</th><th>JS Label</th><th>Expected</th><th>Status</th></tr>';
            
            let jsTestsPassed = 0;
            const jsTestsTotal = 4;
            
            for (let fascia in expectedLabels) {
                const jsLabel = window.btrDynamicChildLabels[fascia] || 'NON DEFINITO';
                const expected = expectedLabels[fascia];
                const isCorrect = (jsLabel === expected);
                
                if (isCorrect) jsTestsPassed++;
                
                const status = isCorrect ? 
                    '<span class="success">✅ MATCH</span>' : 
                    '<span class="error">❌ MISMATCH</span>';
                
                html += '<tr>';
                html += '<td>' + fascia.toUpperCase() + '</td>';
                html += '<td>' + jsLabel + '</td>';
                html += '<td>' + expected + '</td>';
                html += '<td>' + status + '</td>';
                html += '</tr>';
            }
            
            html += '</table>';
            
            // Test funzione getChildLabel
            html += '<h4>Test funzione getChildLabel():</h4>';
            
            const getChildLabel = function(fasciaId, fallback) {
                const dynamicLabels = window.btrDynamicChildLabels || {};
                const fasciaKey = 'f' + fasciaId;
                return dynamicLabels[fasciaKey] || fallback;
            };
            
            html += '<pre>';
            for (let i = 1; i <= 4; i++) {
                const label = getChildLabel(i, 'Fallback F' + i);
                html += 'F' + i + ': ' + label + '\n';
            }
            html += '</pre>';
            
            testResults.innerHTML = html;
            
            // Aggiorna contatori globali
            <?php 
            echo "window.totalTests = " . ($total_tests + 4) . ";";
            echo "window.passedTests = " . $passed_tests . " + jsTestsPassed;";
            ?>
            
            // Aggiorna progress bar
            const percent = Math.round((window.passedTests / window.totalTests) * 100);
            document.getElementById('progress').style.width = percent + '%';
            document.getElementById('progress').textContent = percent + '%';
        })();
        </script>
    </div>

    <!-- STEP 5: Child Labels Manager -->
    <div class="test-section">
        <h2>🎯 Step 5: Verifica Child Labels Manager</h2>
        
        <?php
        $total_tests += 4;
        
        echo "<div class='test-step'>";
        echo "<h3>BTR_Child_Labels_Manager:</h3>";
        
        if (class_exists('BTR_Child_Labels_Manager')) {
            $manager = BTR_Child_Labels_Manager::get_instance();
            $all_labels = $manager->get_all_labels($package_id);
            
            echo "<table>";
            echo "<tr><th>Fascia</th><th>Manager Label</th><th>Expected</th><th>Status</th></tr>";
            
            foreach ($expected_labels as $fascia => $expected) {
                $actual = $all_labels[$fascia] ?? 'NON TROVATA';
                $is_correct = ($actual === $expected);
                if ($is_correct) $passed_tests++;
                
                $status = $is_correct ? 
                    "<span class='success'>✅ CORRETTO</span>" : 
                    "<span class='error'>❌ ERRATO</span>";
                
                echo "<tr>";
                echo "<td>" . strtoupper($fascia) . "</td>";
                echo "<td>$actual</td>";
                echo "<td>$expected</td>";
                echo "<td>$status</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p class='warning'>⚠️ Classe BTR_Child_Labels_Manager non trovata</p>";
        }
        
        echo "</div>";
        update_progress($passed_tests, 20);
        ?>
    </div>

    <!-- RIEPILOGO FINALE -->
    <div class="test-section" style="background: #e6f4ea; border-color: #00a32a;">
        <h2>📊 Riepilogo Test</h2>
        
        <script>
        // Calcola risultati finali
        (function() {
            const totalTests = window.totalTests || <?php echo $total_tests; ?>;
            const passedTests = window.passedTests || <?php echo $passed_tests; ?>;
            const failedTests = totalTests - passedTests;
            const successRate = Math.round((passedTests / totalTests) * 100);
            
            let statusClass = 'success';
            let statusIcon = '✅';
            let statusText = 'TUTTI I TEST PASSATI!';
            
            if (failedTests > 0) {
                statusClass = failedTests > 2 ? 'error' : 'warning';
                statusIcon = failedTests > 2 ? '❌' : '⚠️';
                statusText = failedTests + ' test falliti';
            }
            
            document.write('<div style="text-align: center; font-size: 1.5em; margin: 20px 0;">');
            document.write('<p><strong>Test Totali:</strong> ' + totalTests + '</p>');
            document.write('<p><strong>Test Passati:</strong> <span class="success">' + passedTests + '</span></p>');
            document.write('<p><strong>Test Falliti:</strong> <span class="error">' + failedTests + '</span></p>');
            document.write('<p><strong>Success Rate:</strong> ' + successRate + '%</p>');
            document.write('<h3 class="' + statusClass + '">' + statusIcon + ' ' + statusText + '</h3>');
            document.write('</div>');
            
            // Consigli per il debugging
            if (failedTests > 0) {
                document.write('<div class="test-step">');
                document.write('<h3>🔍 Suggerimenti per il Debug:</h3>');
                document.write('<ol>');
                document.write('<li>Verifica che il file <code>class-btr-preventivi.php</code> contenga la funzione helper <code>btr_get_child_age_labels()</code></li>');
                document.write('<li>Controlla che <code>frontend-scripts.js</code> usi <code>window.btrDynamicChildLabels</code> invece di valori hardcoded</li>');
                document.write('<li>Assicurati che <code>class-btr-shortcodes.php</code> passi le etichette dinamiche tramite <code>wp_localize_script</code></li>');
                document.write('<li>Verifica che le etichette siano configurate correttamente nel pannello admin del package</li>');
                document.write('</ol>');
                document.write('</div>');
            }
        })();
        </script>
    </div>

    <!-- Test Interattivi -->
    <div class="test-section">
        <h2>🧪 Test Interattivi</h2>
        
        <div class="test-step">
            <h3>Simula Selezione Camere:</h3>
            <p>Clicca sui pulsanti per simulare l'assegnazione bambini e verificare le etichette:</p>
            
            <div id="interactive-test" style="margin: 20px 0;">
                <!-- Simulazione pulsanti assegnazione -->
            </div>
            
            <script>
            (function() {
                const container = document.getElementById('interactive-test');
                const labels = window.btrDynamicChildLabels || {};
                
                // Simula i pulsanti di assegnazione come in frontend-scripts.js
                let html = '<div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">';
                
                for (let i = 1; i <= 4; i++) {
                    const fasciaKey = 'f' + i;
                    const label = labels[fasciaKey] || 'Fascia ' + i;
                    
                    html += '<button style="padding: 10px; cursor: pointer;" onclick="alert(\'Selezionato: ' + label + '\')">';
                    html += '👶 ' + label;
                    html += '</button>';
                }
                
                html += '</div>';
                container.innerHTML = html;
            })();
            </script>
        </div>
        
        <div class="test-step">
            <h3>Link Utili per Test Manuale:</h3>
            <ul>
                <li><a href="/pacchetti/<?php echo get_post_field('post_name', $package_id); ?>/" target="_blank">🔗 Apri Package nel Frontend</a></li>
                <li><a href="/wp-admin/post.php?post=<?php echo $package_id; ?>&action=edit" target="_blank">⚙️ Modifica Package nell'Admin</a></li>
                <li><a href="/test-ajax-labels.php" target="_blank">🧪 Test AJAX Labels</a></li>
                <li><a href="/test-js-labels.php" target="_blank">🧪 Test JS Labels</a></li>
            </ul>
        </div>
    </div>

    <div style="margin: 40px 0; padding: 20px; background: #f0f0f0; border-radius: 8px;">
        <h3>📝 Note Versione 1.0.160</h3>
        <ul>
            <li>✅ Creata funzione helper centralizzata <code>BTR_Preventivi::btr_get_child_age_labels()</code></li>
            <li>✅ Rimosse tutte le etichette hardcoded dal PHP</li>
            <li>✅ Corretto timing issue in JavaScript con <code>window.btrDynamicLabelsFromServer</code></li>
            <li>✅ Sincronizzate etichette tra prezzi e pulsanti assegnazione</li>
            <li>✅ Aggiornato <code>BTR_Child_Labels_Manager</code> per usare helper centralizzato</li>
        </ul>
    </div>

</body>
</html>