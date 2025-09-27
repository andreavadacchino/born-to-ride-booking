<?php
// Test per verificare che il cache-busting funzioni correttamente
// Simula il caricamento della pagina di selezione pagamento

// Carica WordPress
require_once('wp-load.php');

// ID del preventivo da testare (37512 o altro)
$preventivo_id = 37512;

// Recupera i dati del preventivo
$totale_preventivo = get_post_meta($preventivo_id, '_prezzo_totale', true);
$numero_adulti = get_post_meta($preventivo_id, '_num_adults', true);
$numero_bambini = get_post_meta($preventivo_id, '_num_children', true);
$numero_neonati = get_post_meta($preventivo_id, '_num_neonati', true);

// Calcolo totale persone (come nel template)
$totale_persone = intval($numero_adulti ?? 0) + intval($numero_bambini ?? 0) + intval($numero_neonati ?? 0);

// Calcolo quota per persona
$quota_per_persona = $totale_preventivo > 0 && $totale_persone > 0 ? $totale_preventivo / $totale_persone : 0;

echo "=== TEST CACHE BUSTING ===\n\n";
echo "Preventivo ID: $preventivo_id\n";
echo "Totale Preventivo: €" . number_format($totale_preventivo, 2) . "\n";
echo "Numero Adulti: $numero_adulti\n";
echo "Numero Bambini: $numero_bambini\n";
echo "Numero Neonati: $numero_neonati\n";
echo "TOTALE PERSONE: $totale_persone\n";
echo "Quota per persona: €" . number_format($quota_per_persona, 2) . "\n\n";

// Simula il JavaScript generato
echo "=== JAVASCRIPT GENERATO ===\n";
echo "// Cache-busting: timestamp " . time() . " per forzare reload JS\n";
echo "const totalParticipants = " . intval($totale_persone) . ";\n";
echo "const quotaPerPerson = " . floatval($quota_per_persona) . ";\n\n";

// Log di debug con timestamp
$timestamp = date('H:i:s');
echo "console.log('[BTR Cache-Bust $timestamp] totalParticipants:', $totale_persone);\n";
echo "console.log('[BTR Cache-Bust $timestamp] Adulti: $numero_adulti, Bambini: $numero_bambini, Neonati: $numero_neonati');\n\n";

// Verifica data-participants nel form
echo "=== ATTRIBUTO DATA-PARTICIPANTS NEL FORM ===\n";
echo '<form data-participants="' . intval($totale_persone) . '">' . "\n";
echo "Il valore corretto per data-participants è: $totale_persone\n\n";

// Test di validazione
echo "=== TEST VALIDAZIONE ===\n";
echo "Se Andrea ha 4 quote e Moira ha 0 quote:\n";
$totalShares = 4 + 0;
echo "totalShares = $totalShares\n";
echo "totalParticipants = $totale_persone\n";
echo "Validazione: " . ($totalShares === $totale_persone ? "✅ OK - Nessun alert" : "❌ MISMATCH - Verrà mostrato l'alert") . "\n\n";

echo "Se Andrea ha 3 quote e Moira ha 1 quota:\n";
$totalShares = 3 + 1;
echo "totalShares = $totalShares\n";
echo "totalParticipants = $totale_persone\n";
echo "Validazione: " . ($totalShares === $totale_persone ? "✅ OK - Nessun alert" : "❌ MISMATCH - Verrà mostrato l'alert") . "\n\n";

// Verifica classificazione partecipanti
$anagrafici = get_post_meta($preventivo_id, '_anagrafici_preventivo', true);
if ($anagrafici) {
    echo "=== CLASSIFICAZIONE PARTECIPANTI ===\n";
    $adulti_paganti = 0;
    foreach ($anagrafici as $index => $persona) {
        $tipo = strtolower(trim($persona['tipo_persona'] ?? ''));
        $fascia = strtolower(trim($persona['fascia'] ?? ''));
        $is_adult = ($tipo === 'adulto') || ($fascia === 'adulto');
        
        // Calcolo età se necessario
        if (!$is_adult && !empty($persona['data_nascita'])) {
            $birthDate = new DateTime($persona['data_nascita']);
            $today = new DateTime();
            if ($birthDate <= $today) {
                $age = $today->diff($birthDate)->y;
                $is_adult = ($age >= 18);
            }
        }
        
        $nome = trim(($persona['nome'] ?? '') . ' ' . ($persona['cognome'] ?? ''));
        
        if ($is_adult && $nome) {
            $adulti_paganti++;
            echo "  ✅ Adulto pagante: $nome\n";
        } else {
            echo "  ❌ Non pagante: $nome (tipo: $tipo, fascia: $fascia)\n";
        }
    }
    echo "Totale adulti paganti (checkbox generate): $adulti_paganti\n";
} else {
    echo "⚠️ Nessun anagrafico trovato per il preventivo\n";
}

echo "\n=== ISTRUZIONI PER IL TEST ===\n";
echo "1. Apri la pagina di selezione pagamento: http://localhost:10018/selezione-piano-pagamento/?preventivo_id=$preventivo_id\n";
echo "2. Premi Ctrl+F5 (o Cmd+Shift+R su Mac) per forzare il refresh completo\n";
echo "3. Apri la console del browser (F12)\n";
echo "4. Verifica che vedi i log con timestamp [BTR Cache-Bust " . date('H:i:s') . "]\n";
echo "5. Seleziona i partecipanti e verifica che non appaia più l'alert\n";
echo "6. Se l'alert appare ancora, controlla i valori nel log [BTR Validation]\n";
?>