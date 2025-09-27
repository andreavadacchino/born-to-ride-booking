<?php
/**
 * Test per verificare le etichette dinamiche delle fasce età bambini
 * v1.0.160
 */
require_once('wp-load.php');

echo "<h2>Test Etichette Dinamiche Fasce Età Bambini</h2>";

// Test package 14466
$package_id = 14466;
echo "<h3>Test con Package ID: $package_id</h3>";

// Test recupero diretto dal database
echo "<h4>1. Meta Values dal Database:</h4>";
echo "<pre>";
for ($i = 1; $i <= 4; $i++) {
    $label = get_post_meta($package_id, 'btr_bambini_fascia' . $i . '_label', true);
    $eta_min = get_post_meta($package_id, 'btr_bambini_fascia' . $i . '_eta_min', true);
    $eta_max = get_post_meta($package_id, 'btr_bambini_fascia' . $i . '_eta_max', true);
    
    echo "Fascia f$i:\n";
    echo "  Label: " . ($label ?: 'NON TROVATO') . "\n";
    echo "  Età Min: " . ($eta_min ?: 'N/A') . "\n";
    echo "  Età Max: " . ($eta_max ?: 'N/A') . "\n\n";
}
echo "</pre>";

// Test funzione helper
if (class_exists('BTR_Preventivi')) {
    echo "<h4>2. Test Funzione Helper BTR_Preventivi::btr_get_child_age_labels():</h4>";
    echo "<pre>";
    $labels = BTR_Preventivi::btr_get_child_age_labels($package_id);
    print_r($labels);
    echo "</pre>";
    
    // Test con package_id non esistente
    echo "<h4>3. Test con Package ID non esistente (99999):</h4>";
    echo "<pre>";
    $labels_fallback = BTR_Preventivi::btr_get_child_age_labels(99999);
    print_r($labels_fallback);
    echo "</pre>";
    
    // Test con package_id null
    echo "<h4>4. Test con Package ID null:</h4>";
    echo "<pre>";
    $labels_null = BTR_Preventivi::btr_get_child_age_labels(null);
    print_r($labels_null);
    echo "</pre>";
} else {
    echo "<p style='color: red;'>⚠️ Classe BTR_Preventivi non disponibile!</p>";
}

// Test con preventivo esistente
$preventivo_id = 36728;
echo "<h3>Test con Preventivo ID: $preventivo_id</h3>";

// Recupera package_id dal preventivo
$package_from_preventivo = get_post_meta($preventivo_id, '_btr_pacchetto_id', true);
if (empty($package_from_preventivo)) {
    $package_from_preventivo = get_post_meta($preventivo_id, '_btr_id_pacchetto', true);
}
echo "<p>Package ID recuperato dal preventivo: " . ($package_from_preventivo ?: 'NON TROVATO') . "</p>";

// Test etichette salvate nel preventivo
$saved_labels = get_post_meta($preventivo_id, '_child_category_labels', true);
echo "<h4>5. Etichette salvate nel preventivo:</h4>";
echo "<pre>";
if ($saved_labels) {
    if (is_string($saved_labels)) {
        $decoded = json_decode($saved_labels, true);
        if ($decoded) {
            echo "Formato: JSON\n";
            print_r($decoded);
        } else {
            $unserialized = @unserialize($saved_labels);
            if ($unserialized) {
                echo "Formato: Serialized\n";
                print_r($unserialized);
            } else {
                echo "Formato: String\n";
                echo $saved_labels;
            }
        }
    } else {
        echo "Formato: Array\n";
        print_r($saved_labels);
    }
} else {
    echo "Nessuna etichetta salvata nel preventivo.\n";
}
echo "</pre>";

// Test BTR_Child_Labels_Manager se esiste
if (class_exists('BTR_Child_Labels_Manager')) {
    echo "<h3>Test BTR_Child_Labels_Manager</h3>";
    $manager = BTR_Child_Labels_Manager::get_instance();
    
    echo "<h4>6. Test get_dynamic_label():</h4>";
    echo "<pre>";
    for ($i = 1; $i <= 4; $i++) {
        $fascia = 'f' . $i;
        $label = $manager->get_dynamic_label($fascia, $preventivo_id);
        echo "Fascia $fascia: $label\n";
    }
    echo "</pre>";
    
    echo "<h4>7. Test get_all_labels():</h4>";
    echo "<pre>";
    $all_labels = $manager->get_all_labels($preventivo_id);
    print_r($all_labels);
    echo "</pre>";
}

echo "<hr>";
echo "<p><strong>✅ Test completato!</strong></p>";
echo "<p>Le etichette dovrebbero ora essere recuperate dinamicamente dal database invece di essere hardcoded.</p>";