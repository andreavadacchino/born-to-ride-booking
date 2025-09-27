<?php
/**
 * Test AJAX Endpoints v3.0.0
 * 
 * Test suite per verificare il funzionamento degli endpoints AJAX.
 * 
 * @package BornToRide
 * @since 3.0.0
 */

// Carica WordPress
require_once dirname(__FILE__) . '/../../../../wp-load.php';

// Verifica permessi admin
if (!current_user_can('manage_options')) {
    wp_die('Accesso negato');
}

// Inizializza componenti
require_once BTR_PLUGIN_DIR . 'includes/class-btr-ajax-endpoints.php';
require_once BTR_PLUGIN_DIR . 'includes/class-btr-unified-calculator.php';
require_once BTR_PLUGIN_DIR . 'includes/class-btr-price-manager.php';
require_once BTR_PLUGIN_DIR . 'includes/class-btr-validation-engine.php';
require_once BTR_PLUGIN_DIR . 'includes/class-btr-cache-manager.php';

$ajax_endpoints = BTR_Ajax_Endpoints::get_instance();

// Test data
$test_package_id = 136; // Replace with actual package ID
$test_data = [
    'package_id' => $test_package_id,
    'adulti' => 2,
    'bambini' => [
        'totale' => 2,
        'fasce' => [
            'f1' => 1,
            'f2' => 1,
            'f3' => 0,
            'f4' => 0
        ]
    ],
    'camere' => [
        [
            'adulti' => 2,
            'bambini' => ['f1', 'f2'],
            'tipo' => 'quadrupla'
        ]
    ],
    'notti_extra' => 2,
    'supplemento_singola' => false,
    'costi_extra' => [
        'skipass' => 4,
        'assicurazione' => 2
    ]
];

// Risultati test
$test_results = [];

/**
 * Test 1: Health Check
 */
function test_health_check() {
    $url = admin_url('admin-ajax.php');
    
    $response = wp_remote_post($url, [
        'body' => [
            'action' => 'btr_v3_health_check'
        ]
    ]);
    
    if (is_wp_error($response)) {
        return [
            'name' => 'Health Check',
            'status' => 'FAILED',
            'error' => $response->get_error_message()
        ];
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    return [
        'name' => 'Health Check',
        'status' => $data['success'] ? 'PASSED' : 'FAILED',
        'data' => $data['data'] ?? null
    ];
}

/**
 * Test 2: Calculate Endpoint
 */
function test_calculate($test_data) {
    $url = admin_url('admin-ajax.php');
    $nonce = wp_create_nonce('btr_v3_ajax');
    
    $response = wp_remote_post($url, [
        'body' => [
            'action' => 'btr_v3_calculate',
            'nonce' => $nonce,
            'data' => json_encode($test_data)
        ]
    ]);
    
    if (is_wp_error($response)) {
        return [
            'name' => 'Calculate',
            'status' => 'FAILED',
            'error' => $response->get_error_message()
        ];
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    return [
        'name' => 'Calculate',
        'status' => $data['success'] ? 'PASSED' : 'FAILED',
        'data' => $data['data'] ?? null,
        'metrics' => $data['data']['_metrics'] ?? null
    ];
}

/**
 * Test 3: Get Prices Endpoint
 */
function test_get_prices($package_id) {
    $url = admin_url('admin-ajax.php');
    $nonce = wp_create_nonce('btr_v3_ajax');
    
    $response = wp_remote_post($url, [
        'body' => [
            'action' => 'btr_v3_get_prices',
            'nonce' => $nonce,
            'package_id' => $package_id
        ]
    ]);
    
    if (is_wp_error($response)) {
        return [
            'name' => 'Get Prices',
            'status' => 'FAILED',
            'error' => $response->get_error_message()
        ];
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    return [
        'name' => 'Get Prices',
        'status' => $data['success'] ? 'PASSED' : 'FAILED',
        'data' => $data['data'] ?? null,
        'cached' => $data['data']['cached'] ?? false
    ];
}

/**
 * Test 4: Save State Endpoint
 */
function test_save_state($test_data) {
    $url = admin_url('admin-ajax.php');
    $nonce = wp_create_nonce('btr_v3_ajax');
    $session_id = 'test_' . time();
    
    $response = wp_remote_post($url, [
        'body' => [
            'action' => 'btr_v3_save_state',
            'nonce' => $nonce,
            'session_id' => $session_id,
            'state' => json_encode($test_data)
        ]
    ]);
    
    if (is_wp_error($response)) {
        return [
            'name' => 'Save State',
            'status' => 'FAILED',
            'error' => $response->get_error_message()
        ];
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    return [
        'name' => 'Save State',
        'status' => $data['success'] ? 'PASSED' : 'FAILED',
        'session_id' => $session_id,
        'saved' => $data['data']['saved'] ?? false
    ];
}

/**
 * Test 5: Load State Endpoint
 */
function test_load_state($session_id) {
    $url = admin_url('admin-ajax.php');
    $nonce = wp_create_nonce('btr_v3_ajax');
    
    $response = wp_remote_post($url, [
        'body' => [
            'action' => 'btr_v3_load_state',
            'nonce' => $nonce,
            'session_id' => $session_id
        ]
    ]);
    
    if (is_wp_error($response)) {
        return [
            'name' => 'Load State',
            'status' => 'FAILED',
            'error' => $response->get_error_message()
        ];
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    return [
        'name' => 'Load State',
        'status' => $data['success'] ? 'PASSED' : 'FAILED',
        'state' => $data['data']['state'] ?? null
    ];
}

/**
 * Test 6: Validate Field Endpoint
 */
function test_validate_field() {
    $url = admin_url('admin-ajax.php');
    $nonce = wp_create_nonce('btr_v3_ajax');
    
    $response = wp_remote_post($url, [
        'body' => [
            'action' => 'btr_v3_validate_field',
            'nonce' => $nonce,
            'field' => 'email',
            'value' => 'test@example.com',
            'type' => 'email'
        ]
    ]);
    
    if (is_wp_error($response)) {
        return [
            'name' => 'Validate Field',
            'status' => 'FAILED',
            'error' => $response->get_error_message()
        ];
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    return [
        'name' => 'Validate Field',
        'status' => $data['success'] ? 'PASSED' : 'FAILED',
        'valid' => $data['data']['valid'] ?? false
    ];
}

/**
 * Test 7: Cache Stats Endpoint (Admin)
 */
function test_cache_stats() {
    $url = admin_url('admin-ajax.php');
    $nonce = wp_create_nonce('btr_v3_ajax');
    
    $response = wp_remote_post($url, [
        'body' => [
            'action' => 'btr_v3_cache_stats',
            'nonce' => $nonce
        ]
    ]);
    
    if (is_wp_error($response)) {
        return [
            'name' => 'Cache Stats',
            'status' => 'FAILED',
            'error' => $response->get_error_message()
        ];
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    return [
        'name' => 'Cache Stats',
        'status' => $data['success'] ? 'PASSED' : 'FAILED',
        'stats' => $data['data']['stats'] ?? null
    ];
}

// Esegui test
echo "<h1>BTR v3.0 AJAX Endpoints Test Suite</h1>";
echo "<pre style='background: #f5f5f5; padding: 20px; border-radius: 5px;'>";

// Test 1: Health Check
$result = test_health_check();
$test_results[] = $result;
echo "Test 1: Health Check - " . $result['status'] . "\n";
if ($result['status'] === 'PASSED' && isset($result['data'])) {
    echo "  Components:\n";
    foreach ($result['data']['components'] as $component => $status) {
        echo "    - $component: " . $status['status'] . "\n";
    }
}

// Test 2: Calculate
$result = test_calculate($test_data);
$test_results[] = $result;
echo "\nTest 2: Calculate - " . $result['status'] . "\n";
if ($result['status'] === 'PASSED' && isset($result['metrics'])) {
    echo "  Calculation time: " . $result['metrics']['calculation_time'] . "\n";
    echo "  Cache hit: " . ($result['metrics']['cache_hit'] ? 'Yes' : 'No') . "\n";
    if (isset($result['data']['totale'])) {
        echo "  Total calculated: €" . $result['data']['totale'] . "\n";
    }
}

// Test 3: Get Prices
$result = test_get_prices($test_package_id);
$test_results[] = $result;
echo "\nTest 3: Get Prices - " . $result['status'] . "\n";
if ($result['status'] === 'PASSED') {
    echo "  Cached: " . ($result['cached'] ? 'Yes' : 'No') . "\n";
}

// Test 4: Save State
$result = test_save_state($test_data);
$test_results[] = $result;
$session_id = $result['session_id'] ?? null;
echo "\nTest 4: Save State - " . $result['status'] . "\n";
if ($result['status'] === 'PASSED') {
    echo "  Session ID: " . $session_id . "\n";
    echo "  Saved: " . ($result['saved'] ? 'Yes' : 'No') . "\n";
}

// Test 5: Load State (if save was successful)
if ($session_id) {
    $result = test_load_state($session_id);
    $test_results[] = $result;
    echo "\nTest 5: Load State - " . $result['status'] . "\n";
    if ($result['status'] === 'PASSED' && $result['state']) {
        echo "  State loaded successfully\n";
    }
}

// Test 6: Validate Field
$result = test_validate_field();
$test_results[] = $result;
echo "\nTest 6: Validate Field - " . $result['status'] . "\n";
if ($result['status'] === 'PASSED') {
    echo "  Email validation: " . ($result['valid'] ? 'Valid' : 'Invalid') . "\n";
}

// Test 7: Cache Stats
$result = test_cache_stats();
$test_results[] = $result;
echo "\nTest 7: Cache Stats - " . $result['status'] . "\n";
if ($result['status'] === 'PASSED' && $result['stats']) {
    echo "  Hit rate: " . $result['stats']['hit_rate'] . "%\n";
    echo "  Total entries: " . $result['stats']['total_entries'] . "\n";
}

// Riepilogo
echo "\n" . str_repeat('=', 60) . "\n";
echo "TEST SUMMARY\n";
echo str_repeat('=', 60) . "\n";

$passed = 0;
$failed = 0;

foreach ($test_results as $result) {
    if ($result['status'] === 'PASSED') {
        $passed++;
        echo "✅ ";
    } else {
        $failed++;
        echo "❌ ";
    }
    echo $result['name'] . "\n";
}

echo "\nTotal: " . count($test_results) . " tests\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Success Rate: " . round(($passed / count($test_results)) * 100, 2) . "%\n";

echo "</pre>";

// Performance benchmark
echo "<h2>Performance Benchmark</h2>";
echo "<pre style='background: #f5f5f5; padding: 20px; border-radius: 5px;'>";

$iterations = 10;
$total_time = 0;

echo "Running $iterations calculation iterations...\n";

for ($i = 0; $i < $iterations; $i++) {
    $start = microtime(true);
    
    $url = admin_url('admin-ajax.php');
    $nonce = wp_create_nonce('btr_v3_ajax');
    
    wp_remote_post($url, [
        'body' => [
            'action' => 'btr_v3_calculate',
            'nonce' => $nonce,
            'data' => json_encode($test_data)
        ]
    ]);
    
    $time = (microtime(true) - $start) * 1000;
    $total_time += $time;
    echo "  Iteration " . ($i + 1) . ": " . round($time, 2) . "ms\n";
}

$avg_time = $total_time / $iterations;
echo "\nAverage calculation time: " . round($avg_time, 2) . "ms\n";
echo "Target: <500ms - " . ($avg_time < 500 ? "✅ ACHIEVED" : "❌ NOT MET") . "\n";

echo "</pre>";

// Note operative
echo "<h2>Note Operative</h2>";
echo "<div style='background: #fffacd; padding: 20px; border-radius: 5px; border-left: 4px solid #ffd700;'>";
echo "<ul>";
echo "<li><strong>Nonce Security</strong>: Tutti gli endpoints richiedono un nonce valido per la sicurezza</li>";
echo "<li><strong>Rate Limiting</strong>: Massimo 100 richieste per minuto per IP</li>";
echo "<li><strong>Cache</strong>: I prezzi sono cachati per 1 ora per migliorare le performance</li>";
echo "<li><strong>State Management</strong>: Gli stati sono salvati sia in sessione WooCommerce che in transient</li>";
echo "<li><strong>Admin Endpoints</strong>: Alcuni endpoints richiedono capability 'manage_options'</li>";
echo "</ul>";
echo "</div>";
?>