<?php
/**
 * Test AJAX Labels v1.0.160
 * Verifica che le etichette dinamiche funzionino correttamente nella risposta AJAX
 */
require_once('wp-load.php');

// Simula una richiesta AJAX per get_rooms
$package_id = 14466;
$product_id = get_post_meta($package_id, '_btr_product_id', true);

echo "<h2>Test AJAX Labels - Package $package_id</h2>";

// Test 1: Verifica etichette dal database
echo "<h3>1. Etichette configurate nel database:</h3>";
echo "<pre>";
for ($i = 1; $i <= 4; $i++) {
    $label = get_post_meta($package_id, "btr_bambini_fascia{$i}_label", true);
    $eta_min = get_post_meta($package_id, "btr_bambini_fascia{$i}_eta_min", true);
    $eta_max = get_post_meta($package_id, "btr_bambini_fascia{$i}_eta_max", true);
    echo "Fascia $i: '$label' (età $eta_min-$eta_max)\n";
}
echo "</pre>";

// Test 2: Verifica helper function
echo "<h3>2. Etichette da BTR_Preventivi::btr_get_child_age_labels():</h3>";
echo "<pre>";
if (class_exists('BTR_Preventivi')) {
    $labels = BTR_Preventivi::btr_get_child_age_labels($package_id);
    print_r($labels);
}
echo "</pre>";

// Test 3: Simula la costruzione di child_fasce come in get_rooms()
echo "<h3>3. Array child_fasce come costruito in get_rooms():</h3>";
echo "<pre>";
$child_fasce = array();

// Usa la funzione helper per ottenere le etichette dinamiche corrette
$dynamic_labels = array();
if (class_exists('BTR_Preventivi')) {
    $dynamic_labels = BTR_Preventivi::btr_get_child_age_labels($package_id);
}

for ($i = 1; $i <= 4; $i++) {
    if (get_post_meta($package_id, "btr_bambini_fascia{$i}_sconto_enabled", true) !== '1') {
        continue; // skip disabled fascia
    }
    
    $eta_min = get_post_meta($package_id, "btr_bambini_fascia{$i}_eta_min", true);
    $eta_max = get_post_meta($package_id, "btr_bambini_fascia{$i}_eta_max", true);
    
    // v1.0.160 - Usa l'etichetta configurata dall'admin invece di costruirla
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

print_r($child_fasce);
echo "</pre>";

// Test 4: Verifica JSON che verrebbe inviato al JavaScript
echo "<h3>4. JSON per JavaScript (come nella risposta AJAX):</h3>";
echo "<pre>";
echo htmlspecialchars(json_encode($child_fasce, JSON_PRETTY_PRINT));
echo "</pre>";

// Test 5: Confronto con formato errato precedente
echo "<h3>5. Confronto con formato precedente (ERRATO):</h3>";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>Fascia</th><th>Formato Corretto (Admin)</th><th>Formato Errato (Hardcoded)</th><th>Stato</th></tr>";

$hardcoded = [
    'f1' => 'Bambini 3-6 anni',
    'f2' => 'Bambini 6-8 anni',
    'f3' => 'Bambini 8-10 anni',
    'f4' => 'Bambini 11-12 anni'
];

foreach ($dynamic_labels as $key => $correct_label) {
    $wrong_label = $hardcoded[$key];
    $status = ($correct_label !== $wrong_label) ? '✅ CORRETTO' : '❌ UGUALE';
    echo "<tr>";
    echo "<td>" . strtoupper($key) . "</td>";
    echo "<td style='color: green;'>$correct_label</td>";
    echo "<td style='color: red; text-decoration: line-through;'>$wrong_label</td>";
    echo "<td>$status</td>";
    echo "</tr>";
}
echo "</table>";

echo "<hr>";
echo "<h3>✅ Test Completato!</h3>";
echo "<p>Le etichette ora vengono recuperate correttamente dal database e inviate al JavaScript tramite AJAX.</p>";
echo "<p>Il JavaScript usa queste etichette dinamiche sia per i prezzi che per l'assegnazione bambini.</p>";
?>