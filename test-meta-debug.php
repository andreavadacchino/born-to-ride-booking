<?php
require_once('wp-load.php');

$preventivo_id = 36728;

echo "<h2>Debug Meta Preventivo $preventivo_id</h2>";

// Ottieni _btr_booking_data_json
$booking_data_json = get_post_meta($preventivo_id, '_btr_booking_data_json', true);
echo "<h3>_btr_booking_data_json:</h3>";
echo "<pre>";
if (is_array($booking_data_json)) {
    echo "È già un array!\n";
    if (isset($booking_data_json['rooms'])) {
        echo "Rooms trovate: " . count($booking_data_json['rooms']) . "\n\n";
        foreach ($booking_data_json['rooms'] as $index => $room) {
            echo "Room $index:\n";
            echo "  - assigned_adults: " . ($room['assigned_adults'] ?? 'N/A') . "\n";
            echo "  - assigned_child_f1: " . ($room['assigned_child_f1'] ?? '0') . "\n";
            echo "  - assigned_child_f2: " . ($room['assigned_child_f2'] ?? '0') . "\n";
            echo "  - assigned_infants: " . ($room['assigned_infants'] ?? '0') . "\n";
            echo "  - room_type: " . ($room['room_type'] ?? 'N/A') . "\n";
            echo "\n";
        }
    } else {
        echo "Nessuna chiave 'rooms' trovata. Chiavi disponibili:\n";
        print_r(array_keys($booking_data_json));
    }
} else {
    echo "Non è un array, tipo: " . gettype($booking_data_json) . "\n";
    if (is_string($booking_data_json)) {
        echo "Tentativo di json_decode...\n";
        $decoded = json_decode($booking_data_json, true);
        if ($decoded) {
            print_r($decoded);
        } else {
            echo "json_decode fallito\n";
        }
    }
}
echo "</pre>";

// Ottieni _camere_selezionate
echo "<h3>_camere_selezionate:</h3>";
$camere = get_post_meta($preventivo_id, '_camere_selezionate', true);
echo "<pre>";
if (is_array($camere)) {
    foreach ($camere as $index => $camera) {
        echo "Camera $index: " . $camera['tipo'] . "\n";
    }
}
echo "</pre>";