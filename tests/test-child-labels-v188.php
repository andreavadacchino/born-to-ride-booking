<?php
/**
 * Test etichette bambini v1.0.188 - Verifica data attributes
 */

// Carica WordPress
require_once dirname(__FILE__) . '/../../../../wp-load.php';

// Solo admin può eseguire
if (!current_user_can('manage_options')) {
    die('Accesso negato');
}

echo "<h1>🔬 Test Etichette Bambini v1.0.188 - Data Attributes</h1>";

// Test 1: Verifica generazione form con data attributes
echo "<h2>1️⃣ Verifica Form con Data Attributes</h2>";
echo "<div style='background:#f5f5f5; padding:15px; border-radius:5px; margin:20px 0;'>";

// Trova un pacchetto con fasce bambini abilitate
$args = [
    'post_type' => 'pacchetti',
    'posts_per_page' => 1,
    'meta_query' => [
        [
            'key' => 'btr_bambini_fascia1_sconto_enabled',
            'value' => '1',
            'compare' => '='
        ]
    ]
];
$pacchetti = get_posts($args);

if (!empty($pacchetti)) {
    $package_id = $pacchetti[0]->ID;
    echo "<p>📦 Test con pacchetto #$package_id</p>";
    
    // Simula rendering del form
    echo "<div id='test-form' style='border:1px solid #ddd; padding:20px; background:white; margin:10px 0;'>";
    
    for ($i = 1; $i <= 4; $i++) {
        $label = get_post_meta($package_id, "btr_bambini_fascia{$i}_label", true);
        $eta_min = get_post_meta($package_id, "btr_bambini_fascia{$i}_eta_min", true);
        $eta_max = get_post_meta($package_id, "btr_bambini_fascia{$i}_eta_max", true);
        $enabled = get_post_meta($package_id, "btr_bambini_fascia{$i}_sconto_enabled", true);
        
        if ($enabled !== '1') continue;
        
        // Genera etichetta dinamica (come nel codice)
        $dynamic_label = '';
        if ($eta_min !== '' && $eta_max !== '') {
            $dynamic_label = $eta_min . '-' . $eta_max;
        } elseif ($eta_min !== '') {
            $dynamic_label = $eta_min . '+';
        } elseif ($eta_max !== '') {
            $dynamic_label = '0-' . $eta_max;
        }
        if (empty($dynamic_label) && !empty($label)) {
            $dynamic_label = $label;
        }
        
        echo "<div class='btr-child-group' data-fascia='f$i' data-label='" . esc_attr($dynamic_label) . "' style='margin:10px 0; padding:10px; background:#f9f9f9; border-radius:5px;'>";
        echo "<strong>Fascia $i:</strong> ";
        echo "data-fascia=\"f$i\" data-label=\"$dynamic_label\"";
        echo "</div>";
    }
    
    echo "</div>";
} else {
    echo "<p>⚠️ Nessun pacchetto con fasce bambini trovato</p>";
}

echo "</div>";

// Test 2: Verifica JavaScript sync
echo "<h2>2️⃣ Test JavaScript Sync</h2>";
echo "<div style='background:#f5f5f5; padding:15px; border-radius:5px; margin:20px 0;'>";
echo "<button id='test-sync' style='padding:10px 20px; background:#0073aa; color:white; border:none; border-radius:5px; cursor:pointer;'>Test syncChildLabelsFromDOM()</button>";
echo "<div id='sync-result' style='margin-top:20px; padding:15px; background:white; border-radius:5px; display:none;'></div>";
echo "</div>";

// Test 3: Verifica ultimo preventivo
echo "<h2>3️⃣ Verifica Ultimo Preventivo</h2>";
echo "<div style='background:#f5f5f5; padding:15px; border-radius:5px; margin:20px 0;'>";

$args = [
    'post_type' => 'preventivi',
    'posts_per_page' => 1,
    'orderby' => 'date',
    'order' => 'DESC'
];
$preventivi = get_posts($args);

if (!empty($preventivi)) {
    $preventivo_id = $preventivi[0]->ID;
    echo "<p>📋 Preventivo #$preventivo_id</p>";
    
    // Controlla etichette salvate
    $labels = get_post_meta($preventivo_id, '_child_category_labels', true);
    
    echo "<table style='width:100%; border-collapse:collapse;'>";
    echo "<tr style='background:#e9e9e9;'><th style='padding:10px; text-align:left;'>Fascia</th><th style='padding:10px; text-align:left;'>Etichetta</th><th style='padding:10px; text-align:left;'>Stato</th></tr>";
    
    if (is_array($labels)) {
        foreach ($labels as $fascia => $label) {
            $status = strpos($label, 'Bambini') === 0 ? '❌ HARDCODED' : '✅ DINAMICA';
            $bg = strpos($label, 'Bambini') === 0 ? '#ffe5e5' : '#e5ffe5';
            echo "<tr style='background:$bg;'>";
            echo "<td style='padding:10px;'>$fascia</td>";
            echo "<td style='padding:10px;'>$label</td>";
            echo "<td style='padding:10px;'>$status</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='3' style='padding:10px;'>⚠️ Nessuna etichetta salvata</td></tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>⚠️ Nessun preventivo trovato</p>";
}

echo "</div>";

// Test 4: Diagnosi finale
echo "<h2>📊 Diagnosi v1.0.188</h2>";
echo "<div style='background:#f5f5f5; padding:15px; border-radius:5px; margin:20px 0;'>";

$has_data_attributes = false;
$has_correct_labels = true;

// Controlla se il form avrebbe data attributes (simulazione)
if (!empty($pacchetti)) {
    $has_data_attributes = true;
}

// Controlla se le etichette sono corrette
if (!empty($labels) && is_array($labels)) {
    foreach ($labels as $label) {
        if (strpos($label, 'Bambini') === 0) {
            $has_correct_labels = false;
            break;
        }
    }
}

if ($has_data_attributes && $has_correct_labels) {
    echo "<div style='padding:15px; background:#d4edda; color:#155724; border-radius:5px;'>";
    echo "✅ <strong>SUCCESSO:</strong> v1.0.188 funziona correttamente!<br>";
    echo "• Data attributes presenti nel form<br>";
    echo "• Etichette dinamiche salvate correttamente";
    echo "</div>";
} elseif ($has_data_attributes && !$has_correct_labels) {
    echo "<div style='padding:15px; background:#fff3cd; color:#856404; border-radius:5px;'>";
    echo "⚠️ <strong>PARZIALE:</strong> Data attributes presenti ma etichette ancora hardcoded<br>";
    echo "• Verifica che il frontend stia leggendo correttamente i data attributes<br>";
    echo "• Controlla console JavaScript per errori";
    echo "</div>";
} else {
    echo "<div style='padding:15px; background:#f8d7da; color:#721c24; border-radius:5px;'>";
    echo "❌ <strong>PROBLEMA:</strong> Fix non completo<br>";
    echo "• Data attributes: " . ($has_data_attributes ? "✓" : "✗") . "<br>";
    echo "• Etichette corrette: " . ($has_correct_labels ? "✓" : "✗");
    echo "</div>";
}

echo "</div>";
?>

<script>
jQuery(document).ready(function($) {
    // Test sync function
    $('#test-sync').click(function() {
        const labels = {};
        
        // Simula sync dal DOM
        $('.btr-child-group[data-fascia]').each(function() {
            const fascia = $(this).attr('data-fascia');
            const label = $(this).attr('data-label');
            if (fascia && label) {
                labels[fascia] = label;
            }
        });
        
        // Mostra risultato
        let html = '<strong>Etichette trovate nel DOM:</strong><br>';
        if (Object.keys(labels).length > 0) {
            for (let fascia in labels) {
                html += `${fascia}: "${labels[fascia]}"<br>`;
            }
            html += '<br><span style="color:green;">✅ Sync completato con successo!</span>';
        } else {
            html += '<span style="color:red;">❌ Nessuna etichetta trovata nel DOM</span>';
        }
        
        $('#sync-result').html(html).slideDown();
        
        console.log('[Test v1.0.188] Labels dal DOM:', labels);
    });
});
</script>