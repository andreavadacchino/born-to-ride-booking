<?php
$file = 'btr-form-anagrafici.php';
$content = file_get_contents($file);

// FIX 1: Correggi meta keys per prezzi
$old_pattern = '/\$prezzo_totale_preventivo = floatval\(get_post_meta\(\$preventivo_id, \'_payload_prezzo_totale\', true\)\);/';
$new_replacement = '// 🎯 FIX v1.0.227: USA META KEYS REALI DAL DATABASE
$prezzo_totale_preventivo = floatval(get_post_meta($preventivo_id, \'_prezzo_totale_completo\', true));';

$content = preg_replace($old_pattern, $new_replacement, $content);

// Scrivi file temporaneo e testa sintassi
file_put_contents($file . '.temp', $content);
// SECURITY FIX: shell_exec() disabled for security reasons
// $syntax_check = shell_exec('php -l ' . $file . '.temp 2>&1');

// Skip syntax check for security
if (file_exists($file . '.temp')) {
    unlink($file . '.temp');
}
echo "⚠️ Fix script disabled for security reasons\n";
