<?php
/**
 * Test debug etichette bambini - Analisi completa del flusso
 */

// Carica WordPress
require_once dirname(__FILE__) . '/../../../../wp-load.php';

// Solo admin può eseguire
if (!current_user_can('manage_options')) {
    die('Accesso negato');
}

echo "<h1>🔍 Debug Completo Etichette Bambini</h1>";
echo "<pre style='background:#f5f5f5; padding:15px; border-radius:5px;'>";

// Trova l'ultimo preventivo
$args = [
    'post_type' => 'preventivi',
    'posts_per_page' => 1,
    'orderby' => 'date',
    'order' => 'DESC'
];
$preventivi = get_posts($args);

if (empty($preventivi)) {
    echo "❌ Nessun preventivo trovato.\n";
    exit;
}

$preventivo = $preventivi[0];
$preventivo_id = $preventivo->ID;

echo "📋 Analisi Preventivo #$preventivo_id\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// 1. Analizza TUTTI i meta fields con etichette
echo "1️⃣ TUTTI I META FIELDS CON ETICHETTE:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$all_meta = get_post_meta($preventivo_id);
$label_fields = [];

foreach ($all_meta as $key => $values) {
    if (strpos($key, 'label') !== false || strpos($key, 'etichetta') !== false) {
        $label_fields[$key] = $values[0];
    }
}

if (empty($label_fields)) {
    echo "   ⚠️ Nessun campo etichetta trovato\n";
} else {
    foreach ($label_fields as $key => $value) {
        // Decodifica se è JSON
        if (is_string($value) && (strpos($value, '{') === 0 || strpos($value, '[') === 0)) {
            $decoded = json_decode($value, true);
            if ($decoded !== null) {
                $value = $decoded;
            }
        }
        
        echo "   $key:\n";
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $status = (is_string($v) && strpos($v, 'Bambini') === 0) ? '❌ HARDCODED' : '✅ DINAMICA';
                echo "      $k: \"$v\" $status\n";
            }
        } else {
            $status = (is_string($value) && strpos($value, 'Bambini') === 0) ? '❌ HARDCODED' : '✅ DINAMICA';
            echo "      \"$value\" $status\n";
        }
    }
}

echo "\n";

// 2. Controlla i campi individuali
echo "2️⃣ CAMPI INDIVIDUALI PER FASCIA:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

for ($i = 1; $i <= 4; $i++) {
    // Controlla vari formati possibili
    $formats = [
        "_child_label_f$i",
        "_child_labels_f$i",
        "_breakdown_bambini_f{$i}_etichetta",
        "_fascia_f{$i}_label"
    ];
    
    foreach ($formats as $format) {
        $value = get_post_meta($preventivo_id, $format, true);
        if ($value) {
            $status = strpos($value, 'Bambini') === 0 ? '❌ HARDCODED' : '✅ DINAMICA';
            echo "   $format: \"$value\" $status\n";
        }
    }
}

echo "\n";

// 3. Analizza il payload originale se salvato
echo "3️⃣ PAYLOAD ORIGINALE (se salvato):\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$payload = get_post_meta($preventivo_id, '_btr_booking_data_json', true);
if ($payload) {
    // TYPE-SAFE FIX: Handle both array (WordPress deserialized) and string (JSON)
    $data = is_array($payload) ? $payload : json_decode($payload, true);
    if ($data && isset($data['child_labels_f1'])) {
        echo "   child_labels_f1: \"{$data['child_labels_f1']}\"\n";
        echo "   child_labels_f2: \"{$data['child_labels_f2']}\"\n";
        echo "   child_labels_f3: \"{$data['child_labels_f3']}\"\n";
        echo "   child_labels_f4: \"{$data['child_labels_f4']}\"\n";
    } else {
        echo "   ⚠️ child_labels_f* NON presenti nel payload\n";
    }
} else {
    echo "   ⚠️ Payload non salvato\n";
}

echo "\n";

// 4. Test della funzione get_child_category_labels_from_package
echo "4️⃣ TEST FUNZIONE get_child_category_labels_from_package():\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

if (class_exists('BTR_Preventivi')) {
    $preventivi_obj = new BTR_Preventivi();
    $package_id = get_post_meta($preventivo_id, '_package_id', true);
    
    if ($package_id) {
        // Chiama la funzione
        $labels = $preventivi_obj->get_child_category_labels_from_package($package_id, $preventivo_id);
        
        if (is_array($labels)) {
            foreach ($labels as $fascia => $label) {
                $status = strpos($label, 'Bambini') === 0 ? '❌ HARDCODED' : '✅ DINAMICA';
                echo "   $fascia: \"$label\" $status\n";
            }
        } else {
            echo "   ⚠️ Nessuna etichetta restituita\n";
        }
    } else {
        echo "   ⚠️ Package ID non trovato\n";
    }
} else {
    echo "   ⚠️ Classe BTR_Preventivi non disponibile\n";
}

echo "\n";

// 5. Diagnosi finale
echo "📊 DIAGNOSI:\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$main_labels = get_post_meta($preventivo_id, '_child_category_labels', true);
$has_hardcoded = false;

if (is_array($main_labels)) {
    foreach ($main_labels as $label) {
        if (strpos($label, 'Bambini') === 0) {
            $has_hardcoded = true;
            break;
        }
    }
}

if ($has_hardcoded) {
    echo "❌ PROBLEMA: _child_category_labels contiene valori hardcoded\n\n";
    echo "POSSIBILI CAUSE:\n";
    echo "1. Frontend non sta inviando child_labels_f1-f4 nel payload AJAX\n";
    echo "2. Backend non sta leggendo correttamente i parametri POST\n";
    echo "3. La funzione get_child_category_labels_from_package() sovrascrive\n";
    echo "4. Hook o filtri che modificano i valori dopo il salvataggio\n";
} else {
    echo "✅ OK: Le etichette sono dinamiche e corrette\n";
}

echo "</pre>";

// Script per test manuale
echo "<h2>🧪 Test Manuale AJAX</h2>";
echo "<button id='test-ajax-labels' style='padding:10px 20px; background:#0073aa; color:white; border:none; border-radius:5px; cursor:pointer;'>Test Invio Etichette</button>";
echo "<div id='test-result' style='margin-top:20px; padding:15px; background:#f5f5f5; border-radius:5px; display:none;'></div>";

?>

<script>
jQuery(document).ready(function($) {
    $('#test-ajax-labels').click(function() {
        // Simula l'invio delle etichette
        const testData = {
            action: 'btr_test_labels',
            child_labels_f1: '3-6 anni',
            child_labels_f2: '6-12',
            child_labels_f3: '12-14',
            child_labels_f4: '14-15',
            nonce: '<?php echo wp_create_nonce('btr_test'); ?>'
        };
        
        console.log('Invio test labels:', testData);
        
        $.post(ajaxurl, testData, function(response) {
            $('#test-result').show().html('<strong>Risposta:</strong><br>' + JSON.stringify(response, null, 2));
        });
    });
});
</script>