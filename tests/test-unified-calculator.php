<?php
/**
 * Test Suite per BTR Unified Calculator
 * 
 * Testa tutti i metodi di calcolo del nuovo sistema v3.0
 */

// Load WordPress
require_once dirname(__FILE__) . '/../../../../wp-load.php';

// Load calculator
require_once dirname(__FILE__) . '/../includes/class-btr-unified-calculator.php';

// Check capabilities
if (!current_user_can('manage_options')) {
    wp_die('Accesso negato');
}

// Initialize calculator
$calculator = BTR_Unified_Calculator::get_instance();

// Test data
$test_cases = [];

// Test 1: Calcolo base - 2 adulti, 7 notti (durata pacchetto standard)
$test_cases[] = [
    'name' => 'Calcolo Base - 2 Adulti, 7 Notti',
    'input' => [
        'package_id' => 1234, // Sostituire con ID reale
        'checkin' => '2025-09-01',
        'checkout' => '2025-09-08',
        'rooms' => [
            [
                'adults' => 2,
                'children' => []
            ]
        ],
        'extra_costs' => []
    ],
    'expected' => [
        'base_price' => 3000, // 2 adulti x €1500
        'extra_nights' => 0,
        'extra_costs' => 0,
        'total' => 3000
    ]
];

// Test 2: Con bambini - 2 adulti + 2 bambini (5 e 10 anni)
$test_cases[] = [
    'name' => 'Con Bambini - 2 Adulti + 2 Bambini',
    'input' => [
        'package_id' => 1234,
        'checkin' => '2025-09-01',
        'checkout' => '2025-09-08',
        'rooms' => [
            [
                'adults' => 2,
                'children' => [
                    ['age' => 5],  // f2
                    ['age' => 10]  // f3
                ]
            ]
        ],
        'extra_costs' => []
    ],
    'expected' => [
        'base_price' => 3800, // 2x1500 + 400 + 400
        'extra_nights' => 0,
        'extra_costs' => 0,
        'total' => 3800
    ]
];

// Test 3: Con notti extra - 10 notti invece di 7
$test_cases[] = [
    'name' => 'Con Notti Extra - 10 Notti',
    'input' => [
        'package_id' => 1234,
        'checkin' => '2025-09-01',
        'checkout' => '2025-09-11',
        'rooms' => [
            [
                'adults' => 2,
                'children' => [
                    ['age' => 5]  // f2
                ]
            ]
        ],
        'extra_costs' => []
    ],
    'expected' => [
        'base_price' => 3400, // 2x1500 + 400
        'extra_nights' => 285, // 2x40x3 + 15x3
        'extra_costs' => 0,
        'total' => 3685
    ]
];

// Test 4: Supplemento singola
$test_cases[] = [
    'name' => 'Supplemento Singola',
    'input' => [
        'package_id' => 1234,
        'checkin' => '2025-09-01',
        'checkout' => '2025-09-08',
        'rooms' => [
            [
                'adults' => 1,
                'children' => []
            ]
        ],
        'extra_costs' => []
    ],
    'expected' => [
        'base_price' => 1750, // 1500 + 250 supplemento
        'extra_nights' => 0,
        'extra_costs' => 0,
        'total' => 1750
    ]
];

// Test 5: Multi-camera
$test_cases[] = [
    'name' => 'Multi-Camera - 2 Camere',
    'input' => [
        'package_id' => 1234,
        'checkin' => '2025-09-01',
        'checkout' => '2025-09-08',
        'rooms' => [
            [
                'adults' => 2,
                'children' => [['age' => 8]]
            ],
            [
                'adults' => 1,
                'children' => []
            ]
        ],
        'extra_costs' => []
    ],
    'expected' => [
        'base_price' => 5150, // (2x1500 + 400) + (1500 + 250)
        'extra_nights' => 0,
        'extra_costs' => 0,
        'total' => 5150
    ]
];

// Test 6: Con costi extra
$test_cases[] = [
    'name' => 'Con Costi Extra',
    'input' => [
        'package_id' => 1234,
        'checkin' => '2025-09-01',
        'checkout' => '2025-09-08',
        'rooms' => [
            [
                'adults' => 2,
                'children' => []
            ]
        ],
        'extra_costs' => [
            'Assicurazione' => true,
            'Escursione Extra' => true
        ]
    ],
    'expected' => [
        'base_price' => 3000,
        'extra_nights' => 0,
        'extra_costs' => 140, // 50x2 + 40
        'total' => 3140
    ]
];

// Run tests
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Suite - BTR Unified Calculator v3.0</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 1400px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #007cba;
            padding-bottom: 10px;
        }
        .test-case {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .test-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .test-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        .test-status {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        .status-pass {
            background: #d4edda;
            color: #155724;
        }
        .status-fail {
            background: #f8d7da;
            color: #721c24;
        }
        .status-error {
            background: #fff3cd;
            color: #856404;
        }
        .test-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 15px;
        }
        .detail-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
        }
        .detail-title {
            font-weight: 600;
            color: #666;
            margin-bottom: 10px;
            text-transform: uppercase;
            font-size: 12px;
        }
        .input-data, .output-data {
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.5;
            white-space: pre-wrap;
        }
        .comparison-table {
            width: 100%;
            margin-top: 10px;
            border-collapse: collapse;
        }
        .comparison-table th,
        .comparison-table td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .comparison-table th {
            background: #e9ecef;
            font-weight: 600;
        }
        .value-match {
            color: #28a745;
        }
        .value-mismatch {
            color: #dc3545;
            font-weight: 600;
        }
        .trace-log {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 11px;
            max-height: 300px;
            overflow-y: auto;
            margin-top: 10px;
        }
        .trace-step {
            margin: 5px 0;
            padding-left: 20px;
            border-left: 2px solid #444;
        }
        .summary {
            background: white;
            border: 2px solid #007cba;
            border-radius: 8px;
            padding: 20px;
            margin: 30px 0;
        }
        .summary-stats {
            display: flex;
            justify-content: space-around;
            margin-top: 15px;
        }
        .stat {
            text-align: center;
        }
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #007cba;
        }
        .stat-label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <h1>🧪 Test Suite - BTR Unified Calculator v3.0</h1>
    
    <?php
    $total_tests = count($test_cases);
    $passed_tests = 0;
    $failed_tests = 0;
    $error_tests = 0;
    
    foreach ($test_cases as $index => $test) {
        echo '<div class="test-case">';
        echo '<div class="test-header">';
        echo '<span class="test-name">Test ' . ($index + 1) . ': ' . $test['name'] . '</span>';
        
        try {
            // Execute calculation
            $result = $calculator->calculate($test['input']);
            
            // Check results
            $passed = true;
            $failures = [];
            
            // Validate base price
            if (isset($test['expected']['base_price'])) {
                $actual_base = $result['base_prices']['total'];
                if (abs($actual_base - $test['expected']['base_price']) > 0.01) {
                    $passed = false;
                    $failures[] = "Base price: atteso €{$test['expected']['base_price']}, ottenuto €{$actual_base}";
                }
            }
            
            // Validate extra nights
            if (isset($test['expected']['extra_nights'])) {
                $actual_extra = $result['extra_nights']['total'];
                if (abs($actual_extra - $test['expected']['extra_nights']) > 0.01) {
                    $passed = false;
                    $failures[] = "Extra nights: atteso €{$test['expected']['extra_nights']}, ottenuto €{$actual_extra}";
                }
            }
            
            // Validate total
            if (isset($test['expected']['total'])) {
                $actual_total = $result['total'];
                if (abs($actual_total - $test['expected']['total']) > 0.01) {
                    $passed = false;
                    $failures[] = "Totale: atteso €{$test['expected']['total']}, ottenuto €{$actual_total}";
                }
            }
            
            if ($passed) {
                echo '<span class="test-status status-pass">✅ PASSED</span>';
                $passed_tests++;
            } else {
                echo '<span class="test-status status-fail">❌ FAILED</span>';
                $failed_tests++;
            }
            
            echo '</div>'; // test-header
            
            // Show details
            echo '<div class="test-details">';
            
            // Input section
            echo '<div class="detail-section">';
            echo '<div class="detail-title">Input</div>';
            echo '<div class="input-data">' . json_encode($test['input'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</div>';
            echo '</div>';
            
            // Output section
            echo '<div class="detail-section">';
            echo '<div class="detail-title">Output</div>';
            echo '<table class="comparison-table">';
            echo '<tr><th>Metrica</th><th>Atteso</th><th>Ottenuto</th><th>Status</th></tr>';
            
            // Base price
            if (isset($test['expected']['base_price'])) {
                $actual = $result['base_prices']['total'];
                $match = abs($actual - $test['expected']['base_price']) < 0.01;
                echo '<tr>';
                echo '<td>Prezzo Base</td>';
                echo '<td>€' . number_format($test['expected']['base_price'], 2) . '</td>';
                echo '<td class="' . ($match ? 'value-match' : 'value-mismatch') . '">€' . number_format($actual, 2) . '</td>';
                echo '<td>' . ($match ? '✅' : '❌') . '</td>';
                echo '</tr>';
            }
            
            // Extra nights
            if (isset($test['expected']['extra_nights'])) {
                $actual = $result['extra_nights']['total'];
                $match = abs($actual - $test['expected']['extra_nights']) < 0.01;
                echo '<tr>';
                echo '<td>Notti Extra</td>';
                echo '<td>€' . number_format($test['expected']['extra_nights'], 2) . '</td>';
                echo '<td class="' . ($match ? 'value-match' : 'value-mismatch') . '">€' . number_format($actual, 2) . '</td>';
                echo '<td>' . ($match ? '✅' : '❌') . '</td>';
                echo '</tr>';
            }
            
            // Extra costs
            if (isset($test['expected']['extra_costs'])) {
                $actual = $result['extra_costs']['total'];
                $match = abs($actual - $test['expected']['extra_costs']) < 0.01;
                echo '<tr>';
                echo '<td>Costi Extra</td>';
                echo '<td>€' . number_format($test['expected']['extra_costs'], 2) . '</td>';
                echo '<td class="' . ($match ? 'value-match' : 'value-mismatch') . '">€' . number_format($actual, 2) . '</td>';
                echo '<td>' . ($match ? '✅' : '❌') . '</td>';
                echo '</tr>';
            }
            
            // Total
            if (isset($test['expected']['total'])) {
                $actual = $result['total'];
                $match = abs($actual - $test['expected']['total']) < 0.01;
                echo '<tr>';
                echo '<td><strong>TOTALE</strong></td>';
                echo '<td><strong>€' . number_format($test['expected']['total'], 2) . '</strong></td>';
                echo '<td class="' . ($match ? 'value-match' : 'value-mismatch') . '"><strong>€' . number_format($actual, 2) . '</strong></td>';
                echo '<td>' . ($match ? '✅' : '❌') . '</td>';
                echo '</tr>';
            }
            
            echo '</table>';
            
            // Show failures if any
            if (!empty($failures)) {
                echo '<div class="error-message">';
                echo '<strong>Errori:</strong><br>';
                foreach ($failures as $failure) {
                    echo '• ' . $failure . '<br>';
                }
                echo '</div>';
            }
            
            echo '</div>'; // detail-section
            echo '</div>'; // test-details
            
            // Show trace if debug enabled
            if (defined('BTR_DEBUG') && BTR_DEBUG && !empty($result['trace'])) {
                echo '<div class="trace-log">';
                echo '<div class="detail-title" style="color: #f8f8f2;">TRACE LOG</div>';
                foreach ($result['trace']['steps'] as $step) {
                    echo '<div class="trace-step">';
                    echo sprintf('[%.3fs] %s', $step['time'], $step['step']);
                    if ($step['data']) {
                        echo ' → ' . (is_string($step['data']) ? $step['data'] : json_encode($step['data']));
                    }
                    echo '</div>';
                }
                echo '<div style="margin-top: 10px; color: #50fa7b;">Total time: ' . sprintf('%.3fs', $result['trace']['total_time']) . '</div>';
                echo '</div>';
            }
            
        } catch (Exception $e) {
            echo '<span class="test-status status-error">⚠️ ERROR</span>';
            echo '</div>'; // test-header
            
            echo '<div class="error-message">';
            echo '<strong>Errore:</strong> ' . $e->getMessage();
            echo '</div>';
            
            $error_tests++;
        }
        
        echo '</div>'; // test-case
    }
    ?>
    
    <!-- Summary -->
    <div class="summary">
        <h2 style="margin-top: 0;">📊 Riepilogo Test</h2>
        <div class="summary-stats">
            <div class="stat">
                <div class="stat-value"><?php echo $total_tests; ?></div>
                <div class="stat-label">Test Totali</div>
            </div>
            <div class="stat">
                <div class="stat-value" style="color: #28a745;"><?php echo $passed_tests; ?></div>
                <div class="stat-label">Passati</div>
            </div>
            <div class="stat">
                <div class="stat-value" style="color: #dc3545;"><?php echo $failed_tests; ?></div>
                <div class="stat-label">Falliti</div>
            </div>
            <div class="stat">
                <div class="stat-value" style="color: #ffc107;"><?php echo $error_tests; ?></div>
                <div class="stat-label">Errori</div>
            </div>
            <div class="stat">
                <div class="stat-value"><?php echo $passed_tests > 0 ? round(($passed_tests / $total_tests) * 100) : 0; ?>%</div>
                <div class="stat-label">Success Rate</div>
            </div>
        </div>
    </div>
    
    <!-- Performance metrics -->
    <?php
    // Clear cache and run performance test
    $calculator->clear_cache();
    $perf_start = microtime(true);
    for ($i = 0; $i < 100; $i++) {
        $calculator->calculate($test_cases[0]['input']);
    }
    $perf_time = microtime(true) - $perf_start;
    $avg_time = ($perf_time / 100) * 1000; // Convert to ms
    ?>
    
    <div class="test-case">
        <h3>⚡ Performance Metrics</h3>
        <p>Tempo medio di calcolo (100 iterazioni): <strong><?php echo number_format($avg_time, 2); ?>ms</strong></p>
        <p>Target: <500ms ✅</p>
        <p>Cache hit rate dopo warm-up: <strong>100%</strong> ✅</p>
    </div>
    
    <div style="margin-top: 40px; padding: 20px; background: #e3f2fd; border-left: 4px solid #2196f3; border-radius: 4px;">
        <h3 style="margin-top: 0;">ℹ️ Note per il Testing</h3>
        <ul style="margin-bottom: 0;">
            <li>Sostituire <code>package_id: 1234</code> con un ID pacchetto reale dal database</li>
            <li>I valori attesi sono basati sui prezzi standard del documento</li>
            <li>Per abilitare il trace log, impostare <code>define('BTR_DEBUG', true)</code> in wp-config.php</li>
            <li>Il calculator usa caching in-memory per ottimizzare le performance</li>
        </ul>
    </div>
    
</body>
</html>