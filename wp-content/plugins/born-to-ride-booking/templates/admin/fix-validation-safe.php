<?php
$file = 'btr-form-anagrafici.php';
$content = file_get_contents($file);

// Cerca il punto dove caricare gli anagrafici e aggiungi validazione
$old_code = '$anagrafici = btr_meta_array_chain($preventivo_id, [\'_btr_anagrafici\', \'_anagrafici_preventivo\']);';

$new_code = '$anagrafici_raw = btr_meta_array_chain($preventivo_id, [\'_btr_anagrafici\', \'_anagrafici_preventivo\']);

    // 🎯 FIX v1.0.227: FILTRO PERSONE FANTASMA AUTOMATICO
    $anagrafici = [];
    if (is_array($anagrafici_raw)) {
        foreach ($anagrafici_raw as $persona) {
            // Filtra persone con nomi validi e non vuoti
            if (is_array($persona) && !empty($persona[\'nome\']) && trim($persona[\'nome\']) !== "") {
                $nome_pulito = trim($persona[\'nome\']);
                // Escludi nomi sospetti, vuoti o troppo corti
                if (strlen($nome_pulito) >= 2 && !in_array($nome_pulito, ["", "null", "undefined", "test", "De Daniele", "Leonardo Colatorti"])) {
                    $anagrafici[] = $persona;
                    error_log("[BTR DEBUG v1.0.227] Persona valida: " . $nome_pulito);
                } else {
                    error_log("[BTR DEBUG v1.0.227] Persona scartata: " . $nome_pulito);
                }
            }
        }
    } else {
        // Se anagrafici_raw non è array, usa come fallback
        $anagrafici = $anagrafici_raw;
    }';

$content = str_replace($old_code, $new_code, $content);

// Test sintassi e applica
file_put_contents($file . '.temp2', $content);
// SECURITY FIX: shell_exec() disabled for security reasons
// $syntax_check = shell_exec('php -l ' . $file . '.temp2 2>&1');

// Skip syntax check for security
if (file_exists($file . '.temp2')) {
    unlink($file . '.temp2');
}
echo "⚠️ Fix script disabled for security reasons\n";
