<?php
/**
 * Analisi Assegnazione Camere - Preventivo 36721
 */

if (!defined('ABSPATH')) {
    require_once dirname(__FILE__, 5) . '/wp-config.php';
}

if (!current_user_can('manage_options')) {
    wp_die('Non hai i permessi per accedere a questa pagina.');
}

$preventivo_id = 36721;

echo "<h1>🔍 ANALISI ASSEGNAZIONE CAMERE - Preventivo {$preventivo_id}</h1>";

$booking_data = get_post_meta($preventivo_id, '_btr_booking_data_json', true);
if (!is_array($booking_data)) {
    echo "<p style='color: red;'>❌ Nessun booking_data_json trovato</p>";
    exit;
}

echo "<h2>👥 PARTECIPANTI TOTALI</h2>";
$participants = $booking_data['participants'] ?? [];
echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Tipo</th><th>Quantità</th></tr>";
echo "<tr><td>Adulti</td><td>" . ($participants['adults'] ?? 0) . "</td></tr>";
echo "<tr><td>Bambini F1</td><td>" . ($participants['children']['f1'] ?? 0) . "</td></tr>";
echo "<tr><td>Bambini F2</td><td>" . ($participants['children']['f2'] ?? 0) . "</td></tr>";
echo "<tr><td>Bambini F3</td><td>" . ($participants['children']['f3'] ?? 0) . "</td></tr>";
echo "<tr><td>Bambini F4</td><td>" . ($participants['children']['f4'] ?? 0) . "</td></tr>";
echo "<tr><td>Neonati</td><td>" . ($participants['infants'] ?? 0) . "</td></tr>";
echo "<tr><th>TOTALE</th><th>" . ($participants['total_people'] ?? 0) . "</th></tr>";
echo "</table>";

echo "<h2>🏠 CAMERE SELEZIONATE</h2>";
$rooms = $booking_data['rooms'] ?? [];
$camera_index = 1;

foreach ($rooms as $room) {
    $assegnati = 
        intval($room['assigned_adults'] ?? 0) +
        intval($room['assigned_child_f1'] ?? 0) +
        intval($room['assigned_child_f2'] ?? 0) +
        intval($room['assigned_child_f3'] ?? 0) +
        intval($room['assigned_child_f4'] ?? 0) +
        intval($room['assigned_infants'] ?? 0);
    
    echo "<h3>Camera {$camera_index}: {$room['tipo']} (Quantità: {$room['quantita']})</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Tipo</th><th>Assegnati</th></tr>";
    echo "<tr><td>Adulti</td><td>" . intval($room['assigned_adults'] ?? 0) . "</td></tr>";
    echo "<tr><td>Bambini F1</td><td>" . intval($room['assigned_child_f1'] ?? 0) . "</td></tr>";
    echo "<tr><td>Bambini F2</td><td>" . intval($room['assigned_child_f2'] ?? 0) . "</td></tr>";
    echo "<tr><td>Bambini F3</td><td>" . intval($room['assigned_child_f3'] ?? 0) . "</td></tr>";
    echo "<tr><td>Bambini F4</td><td>" . intval($room['assigned_child_f4'] ?? 0) . "</td></tr>";
    echo "<tr><td>Neonati</td><td>" . intval($room['assigned_infants'] ?? 0) . "</td></tr>";
    echo "<tr style='background: #f0f0f0;'><th>TOTALE</th><th>{$assegnati}</th></tr>";
    echo "</table>";
    
    $camera_index++;
}

echo "<h2>⚠️ PROBLEMA IDENTIFICATO</h2>";
echo "<p><strong>Il problema è che la logica di assegnazione non rispetta la regola delle triple!</strong></p>";
echo "<p>Secondo l'utente:</p>";
echo "<ul>";
echo "<li>✅ <strong>Doppia</strong>: 1 adulto + 1 neonato (corretto)</li>";
echo "<li>❌ <strong>Triple</strong>: Ogni tripla dovrebbe avere almeno 1 adulto + bambini</li>";
echo "</ul>";

echo "<h2>🎯 CORREZIONE NECESSARIA</h2>";
echo "<p>La logica di assegnazione dovrebbe:</p>";
echo "<ol>";
echo "<li>Assegnare 1 adulto per ogni camera tripla</li>";
echo "<li>Distribuire i bambini tra le triple disponibili</li>";
echo "<li>Rispettare la capacità delle camere</li>";
echo "</ol>";

$totale_adulti = $participants['adults'] ?? 0;
$numero_triple = 0;
foreach ($rooms as $room) {
    if (stripos($room['tipo'], 'tripla') !== false) {
        $numero_triple += intval($room['quantita'] ?? 1);
    }
}

echo "<p><strong>Analisi matematica:</strong></p>";
echo "<p>Adulti totali: {$totale_adulti}</p>";
echo "<p>Numero triple: {$numero_triple}</p>";
echo "<p>Adulti disponibili per triple: " . ($totale_adulti - 1) . " (escludendo quello nella doppia)</p>";

if (($totale_adulti - 1) < $numero_triple) {
    echo "<p style='color: red;'>⚠️ <strong>PROBLEMA</strong>: Non ci sono abbastanza adulti per tutte le triple!</p>";
    echo "<p>Soluzione: Alcuni bambini dovranno essere in tripla senza adulto oppure la configurazione è errata.</p>";
}
?>