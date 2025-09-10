<?php
/**
 * Test per debug totale generale - v1.0.190
 * 
 * Debug del problema €1,28 invece di €680,75
 */

if (!defined('ABSPATH')) {
    require_once dirname(__FILE__, 4) . '/wp-config.php';
}

// Security check
if (!current_user_can('manage_options')) {
    wp_die('Non hai i permessi per accedere a questa pagina.');
}

echo "<h1>🔍 DEBUG TOTALE GENERALE v1.0.190</h1>";

// Prendi ultimo preventivo
global $wpdb;
$ultimo_preventivo = $wpdb->get_var("SELECT MAX(ID) FROM {$wpdb->posts} WHERE post_type = 'preventivi'");

if (!$ultimo_preventivo) {
    echo "<p style='color: red;'>❌ Nessun preventivo trovato</p>";
    exit;
}

echo "<h2>📋 Preventivo ID: {$ultimo_preventivo}</h2>";

// Test tutti i meta fields totale
$totali_meta = [
    '_btr_totale_generale',
    '_pricing_totale_generale', 
    '_totale_preventivo',
    '_prezzo_totale',
    '_totals_grand_total',
    '_pricing_total_price'
];

echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
echo "<tr><th>Meta Key</th><th>Valore Raw</th><th>Floatval</th><th>btr_format_price_i18n()</th></tr>";

foreach ($totali_meta as $meta_key) {
    $valore_raw = get_post_meta($ultimo_preventivo, $meta_key, true);
    $valore_float = floatval($valore_raw);
    $valore_formattato = function_exists('btr_format_price_i18n') ? btr_format_price_i18n($valore_float) : "Funzione non disponibile";
    
    echo "<tr>";
    echo "<td><strong>{$meta_key}</strong></td>";
    echo "<td>" . var_export($valore_raw, true) . "</td>";
    echo "<td>{$valore_float}</td>";
    echo "<td><span style='color: " . ($valore_formattato === '€1,28' ? 'red' : 'green') . "'>{$valore_formattato}</span></td>";
    echo "</tr>";
}

echo "</table>";

// Test la funzione diretta con valore noto
echo "<h2>🧪 Test Funzione Formattazione</h2>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Input</th><th>Output btr_format_price_i18n()</th><th>Stato</th></tr>";

$test_values = [680.75, 1.28, '680.75', '1.28', 680, 1];

foreach ($test_values as $test_val) {
    $result = function_exists('btr_format_price_i18n') ? btr_format_price_i18n($test_val) : "N/A";
    $status = ($result === '€1,28') ? '❌ PROBLEMA' : '✅ OK';
    
    echo "<tr>";
    echo "<td>" . var_export($test_val, true) . "</td>";
    echo "<td>{$result}</td>";
    echo "<td>{$status}</td>";
    echo "</tr>";
}

echo "</table>";

// Analisi del codice che legge il totale
echo "<h2>🔍 Analisi Codice Rendering</h2>";
$btr_preventivi = new BTR_Preventivi();
$totale_from_meta = $btr_preventivi->meta($ultimo_preventivo, '_btr_totale_generale', 0);
echo "<p><strong>Totale da BTR_Preventivi->meta():</strong> " . var_export($totale_from_meta, true) . " → " . btr_format_price_i18n(floatval($totale_from_meta)) . "</p>";

// Controlla se ci sono conversioni di valuta o altro
$locale_info = localeconv();
echo "<h3>💱 Info Locale</h3>";
echo "<pre>" . print_r($locale_info, true) . "</pre>";

echo "<p><strong>Diagnosi:</strong> Se vedi €1,28 nella colonna rossa, c'è un problema di conversione dati.</p>";
?>