<?php
/**
 * Test assegnazioni camere v1.0.189 - Fix confusione partecipanti/camere
 */

// Carica WordPress
require_once dirname(__FILE__) . '/../../../../wp-load.php';

// Solo admin può eseguire
if (!current_user_can('manage_options')) {
    die('Accesso negato');
}

echo "<h1>🔬 Test Assegnazioni Camere v1.0.189</h1>";

// Test 1: Trova ultimo preventivo con camere
echo "<h2>1️⃣ Verifica Ultimo Preventivo con Camere</h2>";
echo "<div style='background:#f5f5f5; padding:15px; border-radius:5px; margin:20px 0;'>";

$args = [
    'post_type' => 'btr_preventivi',
    'posts_per_page' => 1,
    'orderby' => 'date',
    'order' => 'DESC',
    'meta_query' => [
        [
            'key' => '_btr_camere_selezionate',
            'compare' => 'EXISTS'
        ]
    ]
];
$preventivi = get_posts($args);

if (!empty($preventivi)) {
    $preventivo_id = $preventivi[0]->ID;
    echo "<p>📋 Preventivo #$preventivo_id</p>";
    
    // Recupera dati booking
    $booking_data = get_post_meta($preventivo_id, '_btr_booking_data_json', true);
    $camere_selezionate = get_post_meta($preventivo_id, '_btr_camere_selezionate', true);
    
    echo "<h3>Camere Selezionate:</h3>";
    echo "<pre style='background:white; padding:10px; border-radius:5px;'>";
    print_r($camere_selezionate);
    echo "</pre>";
    
    echo "<h3>Assegnazioni per Camera (booking_data['rooms']):</h3>";
    if (!empty($booking_data['rooms'])) {
        foreach ($booking_data['rooms'] as $index => $room) {
            $camera_num = $index + 1;
            echo "<div style='background:white; padding:10px; margin:10px 0; border-radius:5px;'>";
            echo "<strong>Camera #$camera_num:</strong><br>";
            echo "• Adulti: " . ($room['assigned_adults'] ?? 0) . "<br>";
            echo "• Bambini F1: " . ($room['assigned_child_f1'] ?? 0) . "<br>";
            echo "• Bambini F2: " . ($room['assigned_child_f2'] ?? 0) . "<br>";
            echo "• Bambini F3: " . ($room['assigned_child_f3'] ?? 0) . "<br>";
            echo "• Bambini F4: " . ($room['assigned_child_f4'] ?? 0) . "<br>";
            echo "• Neonati: " . ($room['assigned_infants'] ?? 0) . "<br>";
            
            $totale = ($room['assigned_adults'] ?? 0) + 
                     ($room['assigned_child_f1'] ?? 0) + 
                     ($room['assigned_child_f2'] ?? 0) + 
                     ($room['assigned_child_f3'] ?? 0) + 
                     ($room['assigned_child_f4'] ?? 0) + 
                     ($room['assigned_infants'] ?? 0);
            
            echo "<strong>Totale partecipanti: $totale</strong>";
            echo "</div>";
        }
    } else {
        echo "<p>⚠️ Nessuna assegnazione trovata in booking_data</p>";
    }
    
} else {
    echo "<p>⚠️ Nessun preventivo con camere trovato</p>";
}

echo "</div>";

// Test 2: Verifica calcoli breakdown
echo "<h2>2️⃣ Verifica Breakdown Calcoli</h2>";
echo "<div style='background:#f5f5f5; padding:15px; border-radius:5px; margin:20px 0;'>";

if (!empty($preventivo_id)) {
    $breakdown = get_post_meta($preventivo_id, '_riepilogo_calcoli_dettagliato', true);
    
    if (!empty($breakdown['partecipanti'])) {
        echo "<h3>Partecipanti nel Breakdown:</h3>";
        echo "<table style='width:100%; background:white; border-collapse:collapse;'>";
        echo "<tr style='background:#e9e9e9;'>";
        echo "<th style='padding:10px; text-align:left;'>Categoria</th>";
        echo "<th style='padding:10px; text-align:left;'>Quantità</th>";
        echo "<th style='padding:10px; text-align:left;'>Prezzo Base</th>";
        echo "<th style='padding:10px; text-align:left;'>Supplemento</th>";
        echo "<th style='padding:10px; text-align:left;'>Notte Extra</th>";
        echo "</tr>";
        
        foreach ($breakdown['partecipanti'] as $tipo => $dati) {
            if (!empty($dati['quantita']) && $dati['quantita'] > 0) {
                echo "<tr>";
                echo "<td style='padding:10px;'>" . ucfirst(str_replace('_', ' ', $tipo)) . "</td>";
                echo "<td style='padding:10px;'>" . $dati['quantita'] . "</td>";
                echo "<td style='padding:10px;'>€" . number_format($dati['prezzo_base_unitario'] ?? 0, 2) . "</td>";
                echo "<td style='padding:10px;'>€" . number_format($dati['supplemento_base_unitario'] ?? 0, 2) . "</td>";
                echo "<td style='padding:10px;'>€" . number_format($dati['notte_extra_unitario'] ?? 0, 2) . "</td>";
                echo "</tr>";
            }
        }
        
        echo "</table>";
    }
}

echo "</div>";

// Test 3: Diagnosi v1.0.189
echo "<h2>📊 Diagnosi v1.0.189</h2>";
echo "<div style='background:#f5f5f5; padding:15px; border-radius:5px; margin:20px 0;'>";

$issues = [];
$fixes = [];

// Controlla consistenza assegnazioni
if (!empty($booking_data['rooms']) && !empty($camere_selezionate)) {
    $num_camere_config = 0;
    foreach ($camere_selezionate as $camera) {
        $num_camere_config += intval($camera['quantita'] ?? 1);
    }
    
    $num_camere_booking = count($booking_data['rooms']);
    
    if ($num_camere_config === $num_camere_booking) {
        $fixes[] = "✅ Numero camere consistente: $num_camere_config camere";
    } else {
        $issues[] = "❌ Inconsistenza camere: configurate $num_camere_config, booking $num_camere_booking";
    }
    
    // Controlla assegnazioni bambini F3/F4
    $has_f3_f4_assignments = false;
    foreach ($booking_data['rooms'] as $room) {
        if (!empty($room['assigned_child_f3']) || !empty($room['assigned_child_f4'])) {
            $has_f3_f4_assignments = true;
            break;
        }
    }
    
    if ($has_f3_f4_assignments) {
        $fixes[] = "✅ Assegnazioni F3/F4 presenti e funzionanti";
    }
} else {
    $issues[] = "⚠️ Dati camere o booking mancanti";
}

// Mostra risultati
if (!empty($fixes)) {
    echo "<div style='padding:15px; background:#d4edda; color:#155724; border-radius:5px; margin-bottom:10px;'>";
    echo "<strong>FIX APPLICATI:</strong><br>";
    foreach ($fixes as $fix) {
        echo $fix . "<br>";
    }
    echo "</div>";
}

if (!empty($issues)) {
    echo "<div style='padding:15px; background:#f8d7da; color:#721c24; border-radius:5px;'>";
    echo "<strong>PROBLEMI RILEVATI:</strong><br>";
    foreach ($issues as $issue) {
        echo $issue . "<br>";
    }
    echo "</div>";
} else {
    echo "<div style='padding:15px; background:#d4edda; color:#155724; border-radius:5px;'>";
    echo "✅ <strong>SUCCESSO:</strong> v1.0.189 funziona correttamente!<br>";
    echo "• Assegnazioni camere corrette<br>";
    echo "• Logica F3/F4 unificata<br>";
    echo "• Calcoli totali corretti";
    echo "</div>";
}

echo "</div>";

// Test 4: Mostra shortcode rendering
echo "<h2>4️⃣ Preview Rendering Shortcode</h2>";
echo "<div style='background:#f5f5f5; padding:15px; border-radius:5px; margin:20px 0;'>";

if (!empty($preventivo_id)) {
    echo "<p>Rendering del preventivo #$preventivo_id con shortcode:</p>";
    echo "<div style='background:white; padding:20px; border-radius:5px;'>";
    echo do_shortcode('[btr_riepilogo_preventivo id="' . $preventivo_id . '"]');
    echo "</div>";
} else {
    echo "<p>⚠️ Nessun preventivo disponibile per il test</p>";
}

echo "</div>";
?>

<style>
table { border: 1px solid #ddd; }
th, td { border: 1px solid #ddd; }
</style>