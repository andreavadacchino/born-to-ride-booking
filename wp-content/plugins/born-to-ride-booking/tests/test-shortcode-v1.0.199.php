<?php
/**
 * Test Shortcode Riepilogo v1.0.199
 * 
 * Verifica le correzioni applicate per quantità camere e totali
 */

if (!defined('ABSPATH')) {
    require_once dirname(__FILE__, 5) . '/wp-config.php';
}

// Security check
if (!current_user_can('manage_options')) {
    wp_die('Non hai i permessi per accedere a questa pagina.');
}

echo "<h1>🧪 TEST SHORTCODE TABELLA v1.0.199</h1>";

// Usa ID preventivo esistente
$preventivo_id = 36721;

// Verifica che esista
global $wpdb;
$preventivo_exists = $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE ID = %d AND post_type = 'preventivi'", $preventivo_id));

if (!$preventivo_exists) {
    echo "<p style='color: red;'>❌ Preventivo ID {$preventivo_id} non trovato</p>";
    exit;
}

echo "<h2>📋 TEST CORREZIONI v1.0.199</h2>";
echo "<p><strong>✅ Fix applicati:</strong></p>";
echo "<ul>";
echo "<li>🔢 <strong>Quantità camere</strong>: 2x Tripla ora mostra 6 partecipanti totali</li>";
echo "<li>💰 <strong>Notti extra</strong>: Lettura da _totale_notti_extra quando flag attivo</li>";
echo "<li>🧮 <strong>Totale generale</strong>: Hotfix per totali errati (< €10)</li>";
echo "</ul>";

echo "<h2>🎯 OUTPUT SHORTCODE</h2>";
echo "<div style='border: 2px solid #ddd; padding: 15px; margin: 10px 0;'>";

// Esegui shortcode
$output = do_shortcode("[btr_riepilogo_preventivo id='{$preventivo_id}']");
echo $output;

echo "</div>";

// Verifica dati sorgente per debug
echo "<h2>🔍 VERIFICA DATI SORGENTE</h2>";

$booking_data_json = get_post_meta($preventivo_id, '_btr_booking_data_json', true);
$booking_data = is_array($booking_data_json) ? $booking_data_json : [];

if (!empty($booking_data['rooms'])) {
    echo "<h3>✅ BOOKING DATA DISPONIBILE</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Camera</th><th>Tipo</th><th>Quantità</th><th>Persone/unità</th><th>Tot. Persone</th></tr>";
    
    foreach ($booking_data['rooms'] as $i => $room) {
        $persone_per_unita = 
            intval($room['assigned_adults'] ?? 0) +
            intval($room['assigned_child_f1'] ?? 0) +
            intval($room['assigned_child_f2'] ?? 0) +
            intval($room['assigned_child_f3'] ?? 0) +
            intval($room['assigned_child_f4'] ?? 0) +
            intval($room['assigned_infants'] ?? 0);
            
        $quantita = intval($room['quantita'] ?? 1);
        $persone_totali = $persone_per_unita * $quantita;
        
        echo "<tr>";
        echo "<td>" . ($i + 1) . "</td>";
        echo "<td>{$room['tipo']}</td>";
        echo "<td>{$quantita}</td>";
        echo "<td>{$persone_per_unita}</td>";
        echo "<td><strong>{$persone_totali}</strong></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>❌ Nessun dato camere disponibile</p>";
}

// Verifica totali notti extra
echo "<h3>🌙 VERIFICA NOTTI EXTRA</h3>";
$notti_extra_flag = get_post_meta($preventivo_id, '_btr_notti_extra_flag', true);
$totale_notti_extra_v1 = get_post_meta($preventivo_id, '_totale_notti_extra', true);
$totale_notti_extra_v2 = get_post_meta($preventivo_id, '_btr_totale_notti_extra', true);

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Campo</th><th>Valore</th></tr>";
echo "<tr><td>Flag notti extra</td><td>" . ($notti_extra_flag ? '✅ Attivo' : '❌ Disattivo') . "</td></tr>";
echo "<tr><td>_totale_notti_extra</td><td>" . btr_format_price_i18n($totale_notti_extra_v1) . "</td></tr>";
echo "<tr><td>_btr_totale_notti_extra</td><td>" . btr_format_price_i18n($totale_notti_extra_v2) . "</td></tr>";
echo "</table>";

// Verifica totale generale
echo "<h3>💰 VERIFICA TOTALE GENERALE</h3>";
$prezzo_totale = get_post_meta($preventivo_id, '_prezzo_totale', true);
$totale_camere = get_post_meta($preventivo_id, '_btr_totale_camere', true);

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>Campo</th><th>Valore</th></tr>";
echo "<tr><td>Prezzo totale salvato</td><td>" . btr_format_price_i18n($prezzo_totale) . "</td></tr>";
echo "<tr><td>Totale camere</td><td>" . btr_format_price_i18n($totale_camere) . "</td></tr>";
echo "<tr><td>Hotfix attivo?</td><td>" . ($prezzo_totale < 10 && $totale_camere > 100 ? '✅ SÌ' : '❌ NO') . "</td></tr>";
echo "</table>";

echo "<h2>✅ RISULTATO ATTESO</h2>";
echo "<p><strong>Se le correzioni funzionano:</strong></p>";
echo "<ul>";
echo "<li>✅ 2x Tripla deve mostrare <strong>6 partecipanti totali</strong> (non 5)</li>";
echo "<li>✅ Notti extra incluse nel totale se flag attivo</li>";
echo "<li>✅ Totale generale corretto (non €1,28)</li>";
echo "</ul>";

echo "<hr>";
echo "<p style='font-size: 0.9em; color: #666;'>Test v1.0.199 completato</p>";
?>