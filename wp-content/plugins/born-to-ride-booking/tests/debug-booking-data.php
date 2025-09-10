<?php
/**
 * Debug Booking Data JSON
 * 
 * Analizza la struttura del booking_data_json per verificare quantità camere
 */

if (!defined('ABSPATH')) {
    require_once dirname(__FILE__, 5) . '/wp-config.php';
}

echo "<h1>🔍 DEBUG BOOKING DATA JSON</h1>";

$preventivo_id = 36721;

// Recupera e analizza booking_data_json
$booking_data_json = get_post_meta($preventivo_id, '_btr_booking_data_json', true);

if (is_string($booking_data_json)) {
    $booking_data = maybe_unserialize($booking_data_json);
} else {
    $booking_data = $booking_data_json;
}

echo "<h2>📊 STRUTTURA DATI</h2>";

if (empty($booking_data)) {
    echo "<p style='color: red;'>❌ Nessun booking_data_json trovato</p>";
    exit;
}

if (!is_array($booking_data)) {
    echo "<p style='color: red;'>❌ booking_data_json non è un array valido</p>";
    echo "<pre>";
    var_dump($booking_data);
    echo "</pre>";
    exit;
}

echo "<h3>🏠 CAMERE SELEZIONATE</h3>";

if (empty($booking_data['rooms'])) {
    echo "<p style='color: red;'>❌ Nessuna camera nel booking_data_json</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr>";
    echo "<th>Index</th>";
    echo "<th>Tipo</th>";
    echo "<th>Quantità</th>";
    echo "<th>Capacity</th>";
    echo "<th>Adulti</th>";
    echo "<th>F1</th>";
    echo "<th>F2</th>";
    echo "<th>F3</th>";
    echo "<th>F4</th>";
    echo "<th>Neonati</th>";
    echo "<th>Tot/unità</th>";
    echo "<th><strong>Tot Globale</strong></th>";
    echo "</tr>";
    
    $total_persone = 0;
    foreach ($booking_data['rooms'] as $index => $room) {
        $quantita = intval($room['quantita'] ?? 1);
        $persone_per_unita = 
            intval($room['assigned_adults'] ?? 0) +
            intval($room['assigned_child_f1'] ?? 0) +
            intval($room['assigned_child_f2'] ?? 0) +
            intval($room['assigned_child_f3'] ?? 0) +
            intval($room['assigned_child_f4'] ?? 0) +
            intval($room['assigned_infants'] ?? 0);
        
        $persone_totali = $persone_per_unita * $quantita;
        $total_persone += $persone_totali;
        
        echo "<tr>";
        echo "<td>{$index}</td>";
        echo "<td>" . esc_html($room['tipo'] ?? '') . "</td>";
        echo "<td><strong>{$quantita}</strong></td>";
        echo "<td>" . intval($room['capacity'] ?? 0) . "</td>";
        echo "<td>" . intval($room['assigned_adults'] ?? 0) . "</td>";
        echo "<td>" . intval($room['assigned_child_f1'] ?? 0) . "</td>";
        echo "<td>" . intval($room['assigned_child_f2'] ?? 0) . "</td>";
        echo "<td>" . intval($room['assigned_child_f3'] ?? 0) . "</td>";
        echo "<td>" . intval($room['assigned_child_f4'] ?? 0) . "</td>";
        echo "<td>" . intval($room['assigned_infants'] ?? 0) . "</td>";
        echo "<td>{$persone_per_unita}</td>";
        echo "<td><strong style='color: " . ($quantita > 1 ? 'green' : 'black') . ";'>{$persone_totali}</strong></td>";
        echo "</tr>";
    }
    
    echo "<tr style='background: #f0f0f0; font-weight: bold;'>";
    echo "<td colspan='11' style='text-align: right;'>TOTALE PERSONE:</td>";
    echo "<td><strong>{$total_persone}</strong></td>";
    echo "</tr>";
    
    echo "</table>";
}

echo "<h3>🎯 VERIFICA CORREZIONE v1.0.199</h3>";

$fix_needed = false;
if (!empty($booking_data['rooms'])) {
    foreach ($booking_data['rooms'] as $room) {
        $quantita = intval($room['quantita'] ?? 1);
        if ($quantita > 1) {
            $fix_needed = true;
            break;
        }
    }
}

if ($fix_needed) {
    echo "<p style='color: green;'>✅ <strong>Scenario perfetto per test v1.0.199</strong></p>";
    echo "<p>Questo preventivo ha camere con quantità > 1, perfetto per testare il fix.</p>";
} else {
    echo "<p style='color: orange;'>⚠️ Questo preventivo non ha camere multiple</p>";
    echo "<p>Per testare il fix servono camere con quantità > 1 (es: 2x Triple).</p>";
}

echo "<h3>💰 TOTALI ATTUALI</h3>";
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Campo Meta</th><th>Valore</th></tr>";

$meta_fields = [
    '_prezzo_totale' => 'Prezzo Totale',
    '_btr_totale_camere' => 'Totale Camere',
    '_btr_totale_costi_extra' => 'Costi Extra',
    '_totale_notti_extra' => 'Notti Extra'
];

foreach ($meta_fields as $key => $label) {
    $value = get_post_meta($preventivo_id, $key, true);
    echo "<tr>";
    echo "<td>{$label}</td>";
    echo "<td>" . btr_format_price_i18n($value) . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<hr>";
echo "<p style='font-size: 0.9em; color: #666;'>Debug completato per preventivo {$preventivo_id}</p>";
?>