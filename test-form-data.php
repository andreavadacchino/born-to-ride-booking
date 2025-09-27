<?php
// Test per verificare cosa viene inviato dal form

// Carica WordPress
require_once('wp-load.php');

// Simula una chiamata AJAX con dati vuoti
echo "Test 1: Chiamata con data vuoto\n";
$response = wp_remote_post(admin_url('admin-ajax.php'), array(
    'body' => array(
        'action' => 'btr_save_anagrafici_temp',
        'data' => ''
    ),
    'headers' => array(
        'X-Requested-With' => 'XMLHttpRequest'
    )
));

$body = wp_remote_retrieve_body($response);
echo "Response: " . $body . "\n\n";

// Test 2: Chiamata senza parametro data
echo "Test 2: Chiamata senza parametro data\n";
$response2 = wp_remote_post(admin_url('admin-ajax.php'), array(
    'body' => array(
        'action' => 'btr_save_anagrafici_temp'
    ),
    'headers' => array(
        'X-Requested-With' => 'XMLHttpRequest'
    )
));

$body2 = wp_remote_retrieve_body($response2);
echo "Response: " . $body2 . "\n\n";

// Test 3: Chiamata con dati di test
echo "Test 3: Chiamata con dati di test\n";
$test_data = http_build_query(array(
    'preventivo_id' => '37497',
    'nome_1' => 'Test',
    'cognome_1' => 'User',
    'btr_update_anagrafici_nonce_field' => wp_create_nonce('btr_update_anagrafici_nonce')
));

$response3 = wp_remote_post(admin_url('admin-ajax.php'), array(
    'body' => array(
        'action' => 'btr_save_anagrafici_temp',
        'data' => $test_data
    ),
    'headers' => array(
        'X-Requested-With' => 'XMLHttpRequest'
    )
));

$body3 = wp_remote_retrieve_body($response3);
echo "Response: " . $body3 . "\n";