<?php
/**
 * Test Tabella Riepilogo v1.0.191
 * 
 * Verifica che la tabella mostri i dati corretti
 */

if (!defined('ABSPATH')) {
    require_once dirname(__FILE__, 5) . '/wp-config.php';
}

// Security check
if (!current_user_can('manage_options')) {
    wp_die('Non hai i permessi per accedere a questa pagina.');
}

echo "<h1>🧪 TEST TABELLA RIEPILOGO v1.0.191</h1>";

// Usa ID preventivo più recente disponibile
$preventivo_id = 36721;

// Verifica che esista
global $wpdb;
$preventivo_exists = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID = %d AND post_type = 'preventivi'", $preventivo_id));

if (!$preventivo_exists) {
    echo "<p style='color: red;'>❌ Preventivo ID {$preventivo_id} non trovato</p>";
    exit;
}

echo "<h2>📋 Preventivo ID: {$preventivo_id}</h2>";

// Test lo shortcode
echo "<h2>🎯 OUTPUT SHORTCODE</h2>";
echo "<div style='border: 2px solid #ddd; padding: 15px; margin: 10px 0;'>";

// Simula l'output dello shortcode (il parametro corretto è 'id')
echo do_shortcode("[btr_riepilogo_preventivo id='{$preventivo_id}']");

echo "</div>";

echo "<h2>📊 ANALISI DATI</h2>";

// Verifica dati sorgente
$booking_data_json = get_post_meta($preventivo_id, '_btr_booking_data_json', true);
$booking_data = is_array($booking_data_json) ? $booking_data_json : [];

if (!empty($booking_data['rooms'])) {
    echo "<h3>✅ SORGENTE CORRETTA DISPONIBILE</h3>";
    echo "<p><strong>Numero camere in booking_data_json:</strong> " . count($booking_data['rooms']) . "</p>";
    
    foreach ($booking_data['rooms'] as $i => $room) {
        $totale_in_camera = 
            intval($room['assigned_adults'] ?? 0) +
            intval($room['assigned_child_f1'] ?? 0) +
            intval($room['assigned_child_f2'] ?? 0) +
            intval($room['assigned_child_f3'] ?? 0) +
            intval($room['assigned_child_f4'] ?? 0) +
            intval($room['assigned_infants'] ?? 0);
            
        echo "<p><strong>Camera " . ($i + 1) . ":</strong> {$totale_in_camera} persone assegnate</p>";
    }
} else {
    echo "<h3>❌ PROBLEMA: booking_data_json non contiene rooms</h3>";
}

// Confronto con vecchia sorgente
$camere_selezionate = get_post_meta($preventivo_id, '_btr_camere_selezionate', true);
if (!is_array($camere_selezionate)) {
    $camere_selezionate = [];
}

echo "<h3>🔍 CONFRONTO SORGENTI</h3>";
echo "<p><strong>camere_selezionate (obsoleta):</strong> " . count($camere_selezionate) . " elementi</p>";
echo "<p><strong>booking_data_json['rooms'] (corretta):</strong> " . (empty($booking_data['rooms']) ? 0 : count($booking_data['rooms'])) . " elementi</p>";

echo "<h2>🎯 VERIFICA RISULTATO</h2>";
echo "<p>Se la tabella sopra mostra SOLO i partecipanti assegnati a ogni camera (senza duplicazioni), il fix v1.0.191 funziona correttamente.</p>";
echo "<p><strong>Elementi da verificare:</strong></p>";
echo "<ul>";
echo "<li>✅ Ogni partecipante appare solo nella sua camera assegnata</li>";
echo "<li>✅ I totali sono corretti (non più €1,28)</li>";
echo "<li>✅ Le etichette fasce età sono dinamiche</li>";
echo "<li>✅ I prezzi sono quelli della camera specifica</li>";
echo "</ul>";
?>