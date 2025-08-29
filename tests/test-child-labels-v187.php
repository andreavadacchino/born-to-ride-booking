<?php
/**
 * Test etichette bambini v1.0.187 - FIX DEFINITIVO
 * Verifica che le etichette NON vengano sovrascritte durante il rendering
 */

// Carica WordPress
require_once dirname(__FILE__) . '/../../../../wp-load.php';

// Solo admin può eseguire
if (!current_user_can('manage_options')) {
    die('Accesso negato');
}

echo "<h1>🔬 Test Etichette Bambini v1.0.187 - FIX DEFINITIVO</h1>";
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

// 1. Controlla _child_category_labels PRIMA del rendering
echo "1️⃣ PRIMA del rendering (dati salvati):\n";
$labels_before = get_post_meta($preventivo_id, '_child_category_labels', true);
if (is_array($labels_before)) {
    foreach ($labels_before as $fascia => $label) {
        $status = strpos($label, 'Bambini') === 0 ? '❌ HARDCODED' : '✅ DINAMICA';
        echo "   $fascia: \"$label\" $status\n";
    }
} else {
    echo "   ⚠️ Nessuna etichetta salvata\n";
}

echo "\n";

// 2. Simula il rendering del preventivo (questo chiama get_child_category_labels_from_package)
echo "2️⃣ Simulazione rendering preventivo...\n";
$preventivi_obj = new BTR_Preventivi();
// Forza il rendering per triggerare get_child_category_labels_from_package
ob_start();
$preventivi_obj->render_riepilogo_preventivo_shortcode(['id' => $preventivo_id]);
ob_end_clean();
echo "   Rendering completato\n\n";

// 3. Controlla _child_category_labels DOPO il rendering
echo "3️⃣ DOPO il rendering (potrebbero essere sovrascritte):\n";
$labels_after = get_post_meta($preventivo_id, '_child_category_labels', true);
if (is_array($labels_after)) {
    foreach ($labels_after as $fascia => $label) {
        $status = strpos($label, 'Bambini') === 0 ? '❌ HARDCODED' : '✅ DINAMICA';
        echo "   $fascia: \"$label\" $status\n";
    }
}

echo "\n";

// 4. Confronto PRIMA/DOPO
echo "4️⃣ CONFRONTO:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$changed = false;
if (is_array($labels_before) && is_array($labels_after)) {
    foreach ($labels_before as $fascia => $label_before) {
        $label_after = $labels_after[$fascia] ?? '';
        if ($label_before !== $label_after) {
            echo "   ⚠️ $fascia CAMBIATA: \"$label_before\" → \"$label_after\"\n";
            $changed = true;
        }
    }
}

if (!$changed) {
    echo "   ✅ Nessuna modifica alle etichette dopo il rendering\n";
}

echo "\n";

// 5. DIAGNOSI FINALE
echo "📊 DIAGNOSI v1.0.187:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$has_hardcoded_after = false;
if (is_array($labels_after)) {
    foreach ($labels_after as $label) {
        if (strpos($label, 'Bambini') === 0) {
            $has_hardcoded_after = true;
            break;
        }
    }
}

if ($has_hardcoded_after) {
    echo "❌ PROBLEMA PERSISTE: Le etichette contengono ancora valori hardcoded dopo il rendering.\n";
    echo "   Il fix v1.0.187 NON ha risolto completamente il problema.\n";
} else if ($changed) {
    echo "⚠️ PARZIALE: Le etichette sono cambiate ma non sono hardcoded.\n";
} else {
    echo "✅ SUCCESSO: Il fix v1.0.187 funziona correttamente!\n";
    echo "   Le etichette dinamiche sono preservate anche dopo il rendering.\n";
}

echo "\n";

// 6. Debug log check
echo "💡 CONTROLLO LOG:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "Controlla wp-content/debug.log per i seguenti messaggi:\n";
echo "- [BTR v1.0.187] get_child_category_labels_from_package: Etichette valide già presenti\n";
echo "- [BTR v1.0.187] Child_Labels_Manager: Etichette già presenti dal frontend\n";

echo "</pre>";

// Mostra breakdown per confronto
echo "<h2>📦 Campi Breakdown (per confronto)</h2>";
echo "<pre style='background:#f5f5f5; padding:15px; border-radius:5px;'>";

for ($i = 1; $i <= 4; $i++) {
    $breakdown_label = get_post_meta($preventivo_id, "_breakdown_bambini_f{$i}_etichetta", true);
    if ($breakdown_label) {
        $status = strpos($breakdown_label, 'Bambini') === 0 ? '❌ HARDCODED' : '✅ DINAMICA';
        echo "_breakdown_bambini_f{$i}_etichetta: \"$breakdown_label\" $status\n";
    }
}

echo "</pre>";
?>