<?php
$file = 'btr-form-anagrafici.php';
$content = file_get_contents($file);

// FIX CHIRURGICO: Solo riga 2546 - Totale costi extra negativo
$old_line = '<?php echo btr_format_price_i18n($totale_costi_extra_originali); ?>';
$new_line = '<?php echo btr_format_price_i18n(abs($totale_costi_extra_originali)); ?>';

$content = str_replace($old_line, $new_line, $content);

// Test sintassi
file_put_contents($file . '.temp', $content);
// SECURITY FIX: shell_exec() disabled for security reasons
// $syntax_check = shell_exec('php -l ' . $file . '.temp 2>&1');

// Skip syntax check for security
if (file_exists($file . '.temp')) {
    unlink($file . '.temp');
}
echo "⚠️ Fix script disabled for security reasons\n";
