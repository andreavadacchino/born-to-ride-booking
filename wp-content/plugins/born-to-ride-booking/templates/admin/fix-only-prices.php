<?php
$file = 'btr-form-anagrafici.php';
$content = file_get_contents($file);

// FIX CHIRURGICO: Solo meta keys per PREZZI nella tabella totali
// NON toccare anagrafici, partecipanti o assicurazioni

// Correggi solo la lettura del prezzo totale (linea ~289)
$old_price_meta = '$prezzo_totale_preventivo = floatval(get_post_meta($preventivo_id, \'_payload_prezzo_totale\', true));';
$new_price_meta = '// 🎯 FIX v1.0.227: Meta key corretto per prezzo totale
$prezzo_totale_preventivo = floatval(get_post_meta($preventivo_id, \'_prezzo_totale_completo\', true));
if (empty($prezzo_totale_preventivo)) {
    $prezzo_totale_preventivo = floatval(get_post_meta($preventivo_id, \'_prezzo_totale\', true));
}';

$content = str_replace($old_price_meta, $new_price_meta, $content);

// Test sintassi
file_put_contents($file . '.temp', $content);
$syntax_check = shell_exec('php -l ' . $file . '.temp 2>&1');

if (strpos($syntax_check, 'No syntax errors') !== false) {
    rename($file . '.temp', $file);
    echo "✅ SOLO prezzi corretti - partecipanti e assicurazioni INTATTI\n";
} else {
    unlink($file . '.temp');
    echo "❌ Errore: $syntax_check\n";
}
