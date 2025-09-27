<?php
// Script per verificare il problema 400 Bad Request
require_once('wp-load.php');

// Test 1: Verifica che admin-ajax.php funzioni
echo "Test 1: Verifica admin-ajax.php base\n";
$test_url = admin_url('admin-ajax.php');
echo "URL: $test_url\n\n";

// Test 2: Chiamata senza action (dovrebbe dare 400)
echo "Test 2: Chiamata senza action (dovrebbe dare 400):\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $test_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, 'test=1');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP Code: $http_code\n\n";

// Test 3: Chiamata con action non esistente (dovrebbe dare 200 con response "0")
echo "Test 3: Chiamata con action non esistente:\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $test_url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, 'action=test_non_esistente');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP Code: $http_code\n";
echo "Response: $response\n\n";

// Test 4: Verifica se l'azione è registrata
echo "Test 4: Verifica se l'azione btr_save_anagrafici_temp è registrata:\n";
global $wp_filter;
if (isset($wp_filter['wp_ajax_btr_save_anagrafici_temp'])) {
    echo "✅ Azione wp_ajax_btr_save_anagrafici_temp è registrata\n";
} else {
    echo "❌ Azione wp_ajax_btr_save_anagrafici_temp NON è registrata\n";
}

if (isset($wp_filter['wp_ajax_nopriv_btr_save_anagrafici_temp'])) {
    echo "✅ Azione wp_ajax_nopriv_btr_save_anagrafici_temp è registrata\n";
} else {
    echo "❌ Azione wp_ajax_nopriv_btr_save_anagrafici_temp NON è registrata\n";
}