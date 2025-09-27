<?php
// Test script per verificare la sottomissione del form anagrafici

// Carica WordPress
require_once('wp-load.php');

// Simula dati del form - deve essere serializzato come stringa
$form_data = array(
    'preventivo_id' => '37252',
    'nome_1' => 'Mario',
    'cognome_1' => 'Rossi',
    'telefono_1' => '3331234567',
    'email_1' => 'mario.rossi@test.com',
    'data_nascita_1' => '1980-01-01',
    'btr_update_anagrafici_nonce_field' => wp_create_nonce('btr_update_anagrafici_nonce')
);

// Serializza i dati del form come stringa
$serialized_data = http_build_query($form_data);

// Dati da inviare via AJAX
$test_data = array(
    'action' => 'btr_save_anagrafici_temp',
    'data' => $serialized_data,
    'nonce' => wp_create_nonce('btr_update_anagrafici_nonce') // fallback nonce
);

// Esegui chiamata AJAX simulata
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:10018/wp-admin/admin-ajax.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($test_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'X-Requested-With: XMLHttpRequest',
    'Content-Type: application/x-www-form-urlencoded'
));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_HEADER, true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Separa headers e body
list($header, $body) = explode("\r\n\r\n", $response, 2);

echo "HTTP Status Code: " . $http_code . "\n";
echo "Headers:\n" . $header . "\n\n";
echo "Response Body:\n" . $body . "\n";

// Decodifica risposta JSON
$json_response = json_decode($body, true);
if ($json_response) {
    echo "\nDecoded Response:\n";
    echo "Success: " . ($json_response['success'] ? 'true' : 'false') . "\n";
    if (isset($json_response['data'])) {
        echo "Message: " . ($json_response['data']['message'] ?? 'N/A') . "\n";
        if (isset($json_response['data']['redirect_url'])) {
            echo "Redirect URL: " . $json_response['data']['redirect_url'] . "\n";
        }
    }
}