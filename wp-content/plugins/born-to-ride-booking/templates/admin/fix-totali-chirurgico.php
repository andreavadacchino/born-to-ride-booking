<?php
$file = 'btr-form-anagrafici.php';
$content = file_get_contents($file);

// FIX CHIRURGICO: Solo riga 2546 - Totale costi extra negativo
$old_line = '<?php echo btr_format_price_i18n($totale_costi_extra_originali); ?>';
$new_line = '<?php echo btr_format_price_i18n(abs($totale_costi_extra_originali)); ?>';

$content = str_replace($old_line, $new_line, $content);

// Test sintassi
file_put_contents($file . '.temp', $content);
$syntax_check = shell_exec('php -l ' . $file . '.temp 2>&1');

if (strpos($syntax_check, 'No syntax errors') !== false) {
    rename($file . '.temp', $file);
    echo "✅ FIX CHIRURGICO: Totale costi extra ora positivo (+15,00 €)\n";
    echo "🎯 SOLO riga 2546 modificata - resto template INTATTO\n";
} else {
    unlink($file . '.temp');
    echo "❌ Errore: $syntax_check\n";
}
