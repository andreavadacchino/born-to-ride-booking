<?php
/**
 * Simulazione Dati Reali - Analisi Logica Tabella Camere
 * 
 * Recupera i dati reali e simula la logica corretta
 */

if (!defined('ABSPATH')) {
    require_once dirname(__FILE__, 4) . '/wp-config.php';
}

// Security check
if (!current_user_can('manage_options')) {
    wp_die('Non hai i permessi per accedere a questa pagina.');
}

echo "<h1>🔍 SIMULAZIONE DATI REALI - Tabella Camere</h1>";

// Prendi ultimo preventivo
global $wpdb;
$ultimo_preventivo = $wpdb->get_var("SELECT MAX(ID) FROM {$wpdb->posts} WHERE post_type = 'preventivi'");

if (!$ultimo_preventivo) {
    echo "<p style='color: red;'>❌ Nessun preventivo trovato</p>";
    exit;
}

echo "<h2>📋 Preventivo ID: {$ultimo_preventivo}</h2>";

// Recupera dati booking_data_json
$booking_data_json = get_post_meta($ultimo_preventivo, '_btr_booking_data_json', true);
$booking_data = is_array($booking_data_json) ? $booking_data_json : [];

// Recupera dati camere_selezionate (vecchio sistema)
$camere_selezionate = get_post_meta($ultimo_preventivo, '_btr_camere_selezionate', true);
if (!is_array($camere_selezionate)) {
    $camere_selezionate = [];
}

echo "<h2>🏠 CONFRONTO SORGENTI DATI</h2>";

// SORGENTE 1: camere_selezionate (attualmente usata nel loop)
echo "<h3>❌ SORGENTE ATTUALE: _btr_camere_selezionate</h3>";
echo "<pre style='background: #ffeeee; padding: 10px; border: 1px solid red;'>";
print_r($camere_selezionate);
echo "</pre>";

// SORGENTE 2: booking_data_json (contiene assegnazioni corrette)
echo "<h3>✅ SORGENTE CORRETTA: _btr_booking_data_json['rooms']</h3>";
echo "<pre style='background: #eeffee; padding: 10px; border: 1px solid green;'>";
if (!empty($booking_data['rooms'])) {
    print_r($booking_data['rooms']);
} else {
    echo "NESSUN DATO ROOMS TROVATO!";
}
echo "</pre>";

// SIMULAZIONE LOGICA CORRETTA
echo "<h2>🎯 SIMULAZIONE LOGICA CORRETTA</h2>";

if (!empty($booking_data['rooms'])) {
    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f5f5f5;'>";
    echo "<th>Camera</th><th>Persone Assegnate</th><th>Dettaglio Prezzi</th><th>Subtotale</th>";
    echo "</tr>";
    
    $grand_total = 0;
    
    foreach ($booking_data['rooms'] as $index => $room) {
        $camera_numero = $index + 1;
        $tipo = $room['tipo'] ?? 'N/A';
        
        // Calcola persone assegnate
        $adulti = intval($room['assigned_adults'] ?? 0);
        $bambini_f1 = intval($room['assigned_child_f1'] ?? 0);
        $bambini_f2 = intval($room['assigned_child_f2'] ?? 0);
        $bambini_f3 = intval($room['assigned_child_f3'] ?? 0);
        $bambini_f4 = intval($room['assigned_child_f4'] ?? 0);
        $neonati = intval($room['assigned_infants'] ?? 0);
        
        $totale_persone = $adulti + $bambini_f1 + $bambini_f2 + $bambini_f3 + $bambini_f4 + $neonati;
        
        // Calcola prezzi base
        $prezzo_adulto = floatval($room['prezzo_per_persona'] ?? 0);
        $supplemento = floatval($room['supplemento'] ?? 0);
        
        echo "<tr>";
        echo "<td><strong>{$tipo} #{$camera_numero}</strong></td>";
        echo "<td style='text-align: center;'>{$totale_persone}</td>";
        echo "<td>";
        
        $subtotale_camera = 0;
        
        // Dettagli adulti
        if ($adulti > 0) {
            $totale_adulti = $adulti * $prezzo_adulto;
            $supplemento_adulti = $adulti * $supplemento;
            $subtotale_camera += $totale_adulti + $supplemento_adulti;
            
            echo "<strong>{$adulti}x Adulti:</strong><br>";
            echo "• Prezzo pacchetto: {$adulti}× €" . number_format($prezzo_adulto, 2, ',', '.') . " = <strong>€" . number_format($totale_adulti, 2, ',', '.') . "</strong><br>";
            echo "• Supplemento camera: {$adulti}× €" . number_format($supplemento, 2, ',', '.') . " = <strong>€" . number_format($supplemento_adulti, 2, ',', '.') . "</strong><br>";
            echo "<br>";
        }
        
        // Dettagli bambini F1 (3-8 anni)
        if ($bambini_f1 > 0) {
            $prezzo_f1 = floatval($room['price_child_f1'] ?? 0);
            $totale_f1 = $bambini_f1 * $prezzo_f1;
            $supplemento_f1 = $bambini_f1 * $supplemento;
            $subtotale_camera += $totale_f1 + $supplemento_f1;
            
            echo "<strong>{$bambini_f1}x Bambini 3-8 anni:</strong><br>";
            echo "• Prezzo pacchetto: {$bambini_f1}× €" . number_format($prezzo_f1, 2, ',', '.') . " = <strong>€" . number_format($totale_f1, 2, ',', '.') . "</strong><br>";
            echo "• Supplemento camera: {$bambini_f1}× €" . number_format($supplemento, 2, ',', '.') . " = <strong>€" . number_format($supplemento_f1, 2, ',', '.') . "</strong><br>";
            echo "<br>";
        }
        
        // Dettagli bambini F2 (8-12 anni)
        if ($bambini_f2 > 0) {
            $prezzo_f2 = floatval($room['price_child_f2'] ?? 0);
            $totale_f2 = $bambini_f2 * $prezzo_f2;
            $supplemento_f2 = $bambini_f2 * $supplemento;
            $subtotale_camera += $totale_f2 + $supplemento_f2;
            
            echo "<strong>{$bambini_f2}x Bambini 8-12 anni:</strong><br>";
            echo "• Prezzo pacchetto: {$bambini_f2}× €" . number_format($prezzo_f2, 2, ',', '.') . " = <strong>€" . number_format($totale_f2, 2, ',', '.') . "</strong><br>";
            echo "• Supplemento camera: {$bambini_f2}× €" . number_format($supplemento, 2, ',', '.') . " = <strong>€" . number_format($supplemento_f2, 2, ',', '.') . "</strong><br>";
            echo "<br>";
        }
        
        // Dettagli bambini F3 (12-14 anni)
        if ($bambini_f3 > 0) {
            $prezzo_f3 = floatval($room['price_child_f3'] ?? 0);
            $totale_f3 = $bambini_f3 * $prezzo_f3;
            $supplemento_f3 = $bambini_f3 * $supplemento;
            $subtotale_camera += $totale_f3 + $supplemento_f3;
            
            echo "<strong>{$bambini_f3}x Bambini 12-14 anni:</strong><br>";
            echo "• Prezzo pacchetto: {$bambini_f3}× €" . number_format($prezzo_f3, 2, ',', '.') . " = <strong>€" . number_format($totale_f3, 2, ',', '.') . "</strong><br>";
            echo "• Supplemento camera: {$bambini_f3}× €" . number_format($supplemento, 2, ',', '.') . " = <strong>€" . number_format($supplemento_f3, 2, ',', '.') . "</strong><br>";
            echo "<br>";
        }
        
        // Dettagli neonati
        if ($neonati > 0) {
            echo "<strong>{$neonati}x Neonati:</strong><br>";
            echo "• Non paganti (occupano posti letto)<br>";
            echo "<br>";
        }
        
        echo "</td>";
        echo "<td style='text-align: right;'><strong>€" . number_format($subtotale_camera, 2, ',', '.') . "</strong></td>";
        echo "</tr>";
        
        $grand_total += $subtotale_camera;
    }
    
    echo "<tr style='background: #f0f8ff; font-weight: bold;'>";
    echo "<td colspan='3' style='text-align: right;'>TOTALE CAMERE:</td>";
    echo "<td style='text-align: right;'>€" . number_format($grand_total, 2, ',', '.') . "</td>";
    echo "</tr>";
    
    echo "</table>";
    
    // Confronto con totale salvato
    $totale_salvato = floatval(get_post_meta($ultimo_preventivo, '_btr_totale_camere', 0));
    echo "<h3>📊 CONFRONTO TOTALI</h3>";
    echo "<p><strong>Totale Calcolato (logica corretta):</strong> €" . number_format($grand_total, 2, ',', '.') . "</p>";
    echo "<p><strong>Totale Salvato nei meta:</strong> €" . number_format($totale_salvato, 2, ',', '.') . "</p>";
    
    if (abs($grand_total - $totale_salvato) < 0.01) {
        echo "<p style='color: green;'>✅ <strong>MATCH!</strong> I calcoli sono coerenti.</p>";
    } else {
        echo "<p style='color: red;'>❌ <strong>DISCREPANZA!</strong> Differenza: €" . number_format(abs($grand_total - $totale_salvato), 2, ',', '.') . "</p>";
    }
    
} else {
    echo "<p style='color: red;'>❌ ERRORE: Nessun dato rooms trovato in booking_data_json</p>";
}

echo "<h2>🔧 CORREZIONE NECESSARIA</h2>";
echo "<p>Il codice attuale usa <code>\$camere_selezionate</code> per iterare le camere ma <code>booking_data_json</code> per i prezzi.</p>";
echo "<p><strong>SOLUZIONE:</strong> Usare <code>booking_data_json['rooms']</code> per TUTTO il loop.</p>";
?>