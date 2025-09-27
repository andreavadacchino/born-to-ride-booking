<?php
/**
 * Test JavaScript Child Labels v1.0.160
 */
require_once('wp-load.php');

// Simula il contesto di un pacchetto
$package_id = 14466;

// Carica gli script necessari
wp_enqueue_script('jquery');
wp_enqueue_script('btr-booking-form-js', 
    plugins_url('assets/js/frontend-scripts.js', WP_PLUGIN_DIR . '/born-to-ride-booking/born-to-ride-booking.php'),
    array('jquery'),
    '1.0.160',
    true
);

// Localizza i dati come fa class-btr-shortcodes.php
if (class_exists('BTR_Preventivi')) {
    $dynamic_child_labels = BTR_Preventivi::btr_get_child_age_labels($package_id);
} else {
    $dynamic_child_labels = [];
}

// Aggiungi lo script inline
wp_add_inline_script(
    'btr-booking-form-js',
    'window.btrDynamicChildLabels = ' . wp_json_encode($dynamic_child_labels) . ';',
    'before'
);

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test JavaScript Child Labels</title>
    <?php wp_head(); ?>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .test-section { 
            margin: 20px 0; 
            padding: 15px; 
            border: 1px solid #ddd; 
            border-radius: 5px;
        }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .label-check { 
            margin: 10px 0; 
            padding: 10px; 
            background: #f5f5f5;
        }
        pre { background: #f0f0f0; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Test JavaScript Child Labels - v1.0.160</h1>
    
    <div class="test-section">
        <h2>1. PHP Backend Labels (Package <?php echo $package_id; ?>)</h2>
        <pre><?php print_r($dynamic_child_labels); ?></pre>
    </div>
    
    <div class="test-section">
        <h2>2. JavaScript Frontend Labels</h2>
        <div id="js-labels-test"></div>
    </div>
    
    <div class="test-section">
        <h2>3. Confronto e Validazione</h2>
        <div id="validation-result"></div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        console.log('=== Test JavaScript Child Labels v1.0.160 ===');
        
        // Test 1: Verifica che window.btrDynamicChildLabels esista
        const testContainer = $('#js-labels-test');
        const validationContainer = $('#validation-result');
        
        if (typeof window.btrDynamicChildLabels !== 'undefined') {
            testContainer.append('<p class="success">✅ window.btrDynamicChildLabels è disponibile!</p>');
            testContainer.append('<pre>' + JSON.stringify(window.btrDynamicChildLabels, null, 2) + '</pre>');
            
            // Test 2: Verifica i valori
            const expectedLabels = <?php echo json_encode($dynamic_child_labels); ?>;
            let allMatch = true;
            
            validationContainer.append('<h3>Validazione Etichette:</h3>');
            
            ['f1', 'f2', 'f3', 'f4'].forEach(function(fascia) {
                const phpLabel = expectedLabels[fascia] || 'Non definito';
                const jsLabel = window.btrDynamicChildLabels[fascia] || 'Non definito';
                const match = phpLabel === jsLabel;
                
                if (!match) allMatch = false;
                
                const status = match ? 
                    '<span class="success">✅ MATCH</span>' : 
                    '<span class="error">❌ MISMATCH</span>';
                
                validationContainer.append(
                    '<div class="label-check">' +
                    '<strong>' + fascia.toUpperCase() + ':</strong><br>' +
                    'PHP: "' + phpLabel + '"<br>' +
                    'JS: "' + jsLabel + '"<br>' +
                    status +
                    '</div>'
                );
            });
            
            // Risultato finale
            if (allMatch) {
                validationContainer.append('<h3 class="success">✅ TUTTE LE ETICHETTE CORRISPONDONO!</h3>');
            } else {
                validationContainer.append('<h3 class="error">❌ ALCUNE ETICHETTE NON CORRISPONDONO</h3>');
            }
            
            // Test 3: Simula l'uso nel frontend-scripts.js
            validationContainer.append('<h3>Test Utilizzo Frontend:</h3>');
            
            // Simula la funzione getChildLabel del frontend
            const getChildLabel = function(fasciaId, fallback) {
                const dynamicLabels = window.btrDynamicChildLabels || {};
                const fasciaKey = 'f' + fasciaId;
                return dynamicLabels[fasciaKey] || fallback;
            };
            
            // Test con fallback dinamici come nel codice aggiornato
            const dynamicLabels = window.btrDynamicChildLabels || {};
            const labelChildF1 = getChildLabel(1, dynamicLabels.f1 || '3-6 anni');
            const labelChildF2 = getChildLabel(2, dynamicLabels.f2 || '6-8 anni');
            const labelChildF3 = getChildLabel(3, dynamicLabels.f3 || '8-10 anni');
            const labelChildF4 = getChildLabel(4, dynamicLabels.f4 || '11-12 anni');
            
            validationContainer.append(
                '<div class="label-check">' +
                '<strong>Risultato simulazione frontend-scripts.js:</strong><br>' +
                'F1: ' + labelChildF1 + '<br>' +
                'F2: ' + labelChildF2 + '<br>' +
                'F3: ' + labelChildF3 + '<br>' +
                'F4: ' + labelChildF4 + '<br>' +
                '</div>'
            );
            
        } else {
            testContainer.append('<p class="error">❌ window.btrDynamicChildLabels NON è disponibile!</p>');
            testContainer.append('<p>Questo significa che le etichette dinamiche non sono state caricate nel JavaScript.</p>');
        }
        
        // Log completo per debug
        console.log('window.btrDynamicChildLabels:', window.btrDynamicChildLabels);
        console.log('window.btrChildFasce:', window.btrChildFasce);
    });
    </script>
    
    <?php wp_footer(); ?>
</body>
</html>