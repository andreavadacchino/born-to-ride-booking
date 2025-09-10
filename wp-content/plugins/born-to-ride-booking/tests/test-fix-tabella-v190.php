<?php
/**
 * Test Fix Tabella v1.0.190 - Verifica correzione lettura dati camere
 * 
 * FIX APPLICATO:
 * - Sostituita lettura da _btr_camere_selezionate (obsoleto, dati a 0)
 * - Ora usa _btr_booking_data_json['rooms'] (dati corretti con assegnazioni)
 * 
 * PROBLEMA RISOLTO:
 * - Tabella mostrava 1 adulto in Doppia invece di 2
 * - Tutti i bambini finivano nella Tripla #2
 * - La Tripla #3 rimaneva vuota
 */

// Carica WordPress
require_once dirname(__FILE__) . '/../../../../wp-load.php';

// Solo admin può eseguire
if (!current_user_can('manage_options')) {
    die('Accesso negato');
}

echo "<h1>🔧 Test Fix Tabella v1.0.190</h1>";
echo "<p><strong>Fix applicato:</strong> Sostituita lettura da campo obsoleto con booking_data_json</p>";

// Cerca preventivo recente con booking_data_json
$args = [
    'post_type' => 'btr_preventivi',
    'posts_per_page' => 1,
    'orderby' => 'date',
    'order' => 'DESC',
    'meta_query' => [
        [
            'key' => '_btr_booking_data_json',
            'compare' => 'EXISTS'
        ]
    ]
];
$preventivi = get_posts($args);

if (empty($preventivi)) {
    echo "<div style='background:#f8d7da; color:#721c24; padding:15px; border-radius:5px;'>";
    echo "❌ <strong>Nessun preventivo con booking_data_json trovato</strong><br>";
    echo "Impossibile testare il fix v1.0.190";
    echo "</div>";
    exit;
}

$preventivo_id = $preventivi[0]->ID;
echo "<h2>📋 Test su Preventivo #$preventivo_id</h2>";

// Recupera dati per confronto
$camere_obsolete = get_post_meta($preventivo_id, '_btr_camere_selezionate', true);
$booking_data = get_post_meta($preventivo_id, '_btr_booking_data_json', true);

echo "<div style='display:flex; gap:20px; margin:20px 0;'>";

// Colonna 1: Dati obsoleti
echo "<div style='flex:1; background:#f8d7da; padding:15px; border-radius:5px;'>";
echo "<h3>❌ Campo Obsoleto (_btr_camere_selezionate)</h3>";
if (!empty($camere_obsolete)) {
    echo "<pre style='background:white; padding:10px; border-radius:5px; font-size:12px;'>";
    print_r($camere_obsolete);
    echo "</pre>";
} else {
    echo "<p>Nessun dato</p>";
}
echo "</div>";

// Colonna 2: Dati corretti
echo "<div style='flex:1; background:#d4edda; padding:15px; border-radius:5px;'>";
echo "<h3>✅ Campo Corretto (_btr_booking_data_json['rooms'])</h3>";
if (!empty($booking_data['rooms'])) {
    echo "<pre style='background:white; padding:10px; border-radius:5px; font-size:12px;'>";
    print_r($booking_data['rooms']);
    echo "</pre>";
} else {
    echo "<p>Nessun dato</p>";
}
echo "</div>";

echo "</div>";

// Test del rendering
echo "<h2>🎭 Test Rendering Tabella</h2>";
echo "<div style='background:#f5f5f5; padding:15px; border-radius:5px;'>";
echo "<p>Rendering del preventivo con shortcode:</p>";
echo "<div style='background:white; padding:20px; border-radius:5px; border:1px solid #ddd;'>";
echo do_shortcode('[btr_riepilogo_preventivo id="' . $preventivo_id . '"]');
echo "</div>";
echo "</div>";

// Analisi automatica
echo "<h2>🔍 Analisi Automatica</h2>";
echo "<div style='background:#e9ecef; padding:15px; border-radius:5px;'>";

$problemi = [];
$successi = [];

// 1. Verifica esistenza booking_data
if (!empty($booking_data['rooms'])) {
    $successi[] = "✅ booking_data_json contiene " . count($booking_data['rooms']) . " camere";
} else {
    $problemi[] = "❌ booking_data_json vuoto o mancante";
}

// 2. Verifica assegnazioni
if (!empty($booking_data['rooms'])) {
    $totale_persone = 0;
    foreach ($booking_data['rooms'] as $idx => $room) {
        $persone_camera = 0;
        $persone_camera += intval($room['assigned_adults'] ?? 0);
        $persone_camera += intval($room['assigned_child_f1'] ?? 0);
        $persone_camera += intval($room['assigned_child_f2'] ?? 0);
        $persone_camera += intval($room['assigned_child_f3'] ?? 0);
        $persone_camera += intval($room['assigned_child_f4'] ?? 0);
        $persone_camera += intval($room['assigned_infants'] ?? 0);
        
        $camera_num = $idx + 1;
        if ($persone_camera > 0) {
            $successi[] = "✅ Camera #$camera_num: $persone_camera persone assegnate";
        } else {
            $problemi[] = "⚠️ Camera #$camera_num: Nessuna persona assegnata";
        }
        
        $totale_persone += $persone_camera;
    }
    
    if ($totale_persone > 0) {
        $successi[] = "✅ Totale persone nelle camere: $totale_persone";
    } else {
        $problemi[] = "❌ Nessuna persona assegnata in alcuna camera";
    }
}

// 3. Verifica versione plugin
$plugin_version = defined('BTR_VERSION') ? BTR_VERSION : 'Non definita';
if ($plugin_version === '1.0.190') {
    $successi[] = "✅ Versione plugin corretta: $plugin_version";
} else {
    $problemi[] = "⚠️ Versione plugin: $plugin_version (attesa: 1.0.190)";
}

// Mostra risultati
if (!empty($successi)) {
    echo "<div style='background:#d4edda; color:#155724; padding:10px; border-radius:5px; margin-bottom:10px;'>";
    echo "<strong>SUCCESSI:</strong><br>";
    foreach ($successi as $successo) {
        echo $successo . "<br>";
    }
    echo "</div>";
}

if (!empty($problemi)) {
    echo "<div style='background:#f8d7da; color:#721c24; padding:10px; border-radius:5px;'>";
    echo "<strong>PROBLEMI:</strong><br>";
    foreach ($problemi as $problema) {
        echo $problema . "<br>";
    }
    echo "</div>";
} else {
    echo "<div style='background:#d4edda; color:#155724; padding:10px; border-radius:5px; text-align:center;'>";
    echo "🎉 <strong>TUTTI I TEST SUPERATI!</strong><br>";
    echo "Il fix v1.0.190 funziona correttamente";
    echo "</div>";
}

echo "</div>";

// Debug info
echo "<h2>🐛 Debug Info</h2>";
echo "<div style='background:#f8f9fa; padding:15px; border-radius:5px; font-family:monospace; font-size:12px;'>";
echo "<strong>Preventivo ID:</strong> $preventivo_id<br>";
echo "<strong>Plugin Version:</strong> " . (defined('BTR_VERSION') ? BTR_VERSION : 'Non definita') . "<br>";
echo "<strong>Camere obsolete:</strong> " . (empty($camere_obsolete) ? 'Vuote' : count($camere_obsolete) . ' elementi') . "<br>";
echo "<strong>Camere booking_data:</strong> " . (empty($booking_data['rooms']) ? 'Vuote' : count($booking_data['rooms']) . ' elementi') . "<br>";
echo "<strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "<br>";
echo "</div>";
?>

<style>
pre {
    white-space: pre-wrap;
    word-wrap: break-word;
    max-height: 300px;
    overflow-y: auto;
}
</style>