<?php
/**
 * Test etichette bambini v1.0.186
 * Verifica che le etichette vengano salvate correttamente
 */

// Carica WordPress
require_once dirname(__FILE__) . '/../../../../wp-load.php';

// Solo admin può eseguire
if (!current_user_can('manage_options')) {
    die('Accesso negato');
}

echo "<h1>🧪 Test Etichette Bambini v1.0.186</h1>";
echo "<pre style='background:#f5f5f5; padding:15px; border-radius:5px;'>";

// Trova l'ultimo preventivo creato
$args = [
    'post_type' => 'preventivi',
    'posts_per_page' => 1,
    'orderby' => 'date',
    'order' => 'DESC'
];
$preventivi = get_posts($args);

if (empty($preventivi)) {
    echo "❌ Nessun preventivo trovato. Crea prima un preventivo di test.\n";
    exit;
}

$preventivo = $preventivi[0];
$preventivo_id = $preventivo->ID;

echo "📋 Analisi Preventivo #$preventivo_id\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// 1. Controlla _child_category_labels (campo principale)
$child_category_labels = get_post_meta($preventivo_id, '_child_category_labels', true);
echo "1️⃣ _child_category_labels (PRINCIPALE):\n";
if (is_array($child_category_labels)) {
    foreach ($child_category_labels as $fascia => $label) {
        $status = strpos($label, 'Bambini') === 0 ? '❌ HARDCODED' : '✅ DINAMICA';
        echo "   $fascia: \"$label\" $status\n";
    }
} else {
    echo "   ⚠️ Non è un array o è vuoto\n";
}

echo "\n";

// 2. Controlla campi individuali (compatibilità)
echo "2️⃣ Campi individuali (compatibilità):\n";
$individual_labels = [];
for ($i = 1; $i <= 4; $i++) {
    $label = get_post_meta($preventivo_id, "_child_label_f$i", true);
    if ($label) {
        $individual_labels["f$i"] = $label;
        $status = strpos($label, 'Bambini') === 0 ? '❌ HARDCODED' : '✅ DINAMICA';
        echo "   _child_label_f$i: \"$label\" $status\n";
    } else {
        echo "   _child_label_f$i: (vuoto)\n";
    }
}

echo "\n";

// 3. Controlla breakdown etichette (dove vanno i valori corretti)
echo "3️⃣ Breakdown etichette (dal payload):\n";
for ($i = 1; $i <= 4; $i++) {
    $label = get_post_meta($preventivo_id, "_breakdown_bambini_f{$i}_etichetta", true);
    if ($label) {
        $status = strpos($label, 'Bambini') === 0 ? '❌ HARDCODED' : '✅ DINAMICA';
        echo "   _breakdown_bambini_f{$i}_etichetta: \"$label\" $status\n";
    }
}

echo "\n";

// 4. Diagnosi
echo "📊 DIAGNOSI:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$has_hardcoded = false;
if (is_array($child_category_labels)) {
    foreach ($child_category_labels as $label) {
        if (strpos($label, 'Bambini') === 0) {
            $has_hardcoded = true;
            break;
        }
    }
}

if ($has_hardcoded) {
    echo "❌ PROBLEMA: Le etichette principali contengono ancora valori hardcoded.\n";
    echo "   Il fix v1.0.186 NON sta funzionando correttamente.\n";
    echo "   Verifica che il frontend stia inviando le etichette nel payload.\n";
} else {
    echo "✅ SUCCESSO: Le etichette sono salvate correttamente!\n";
    echo "   Il fix v1.0.186 sta funzionando.\n";
}

echo "\n";

// 5. Suggerimenti
echo "💡 SUGGERIMENTI:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "1. Controlla il debug.log per vedere i log di BTR v1.0.186\n";
echo "2. Verifica nella console del browser che syncChildLabelsFromDOM() funzioni\n";
echo "3. Controlla il payload AJAX nella scheda Network del browser\n";

echo "</pre>";

// Mostra anche altri meta utili
echo "<h2>📦 Altri Meta del Preventivo</h2>";
echo "<pre style='background:#f5f5f5; padding:15px; border-radius:5px;'>";

$useful_meta = [
    '_btr_bambini_f1' => 'Numero bambini F1',
    '_btr_bambini_f2' => 'Numero bambini F2', 
    '_btr_bambini_f3' => 'Numero bambini F3',
    '_btr_bambini_f4' => 'Numero bambini F4',
    '_num_children' => 'Totale bambini',
    '_btr_id_pacchetto' => 'ID Pacchetto'
];

foreach ($useful_meta as $key => $desc) {
    $value = get_post_meta($preventivo_id, $key, true);
    if ($value !== '') {
        echo "$desc ($key): $value\n";
    }
}

echo "</pre>";
?>